<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is addons.mozilla.org site.
 *
 * The Initial Developer of the Original Code is
 * The Mozilla Foundation.
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Justin Scott <fligtar@mozilla.com> (Original Author)
 *   Andrei Hajdukewycz <sancus@off.net>
 *   Wil Clouser <wclouser@mozilla.com>
 *
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK ***** */

// Mozilla datacenter IPs to filter
$datacenters = array(
                    'NL' => array('63.245.213.'),
                    'CN' => array('59.151.50.')
                    );

// Include class files
$root = dirname(dirname(dirname(__FILE__)));
require_once "{$root}/bin/database.class.php";
require_once "{$root}/bin/parse_logs/count_downloads.class.php";
require_once "{$root}/bin/parse_logs/count_update_pings.class.php";

/**
 * Outputs message if script has been called with verbose flag
 */
function outputIfVerbose($message) {
    global $verbose;
    
    if ($verbose)
        print "{$message}\n";
}

/**
 * Parses the access logs and hands off to counting classes
 */
class Log_Parser {
    var $tmpDir; // writable directory for temp file
    var $logDir; // directory of the access logs
    var $date = ''; // date of updateping logs to parse
    var $type; // type of parsing
    var $db;
    var $counter; // the counter class
    var $geo = '';
    
   /**
    * Initiates parser and database connection
    *
    * @param string $logDir access log directory
    * @param string $tmpDir temporary file directory
    * @param string $parseType the type of parsing to be done
    * @param string $date the date of updateping logs to parse
    */
    function Log_Parser($logDir, $tmpDir, $parseType, $geo, $date = '') {
        $this->tmpDir = $tmpDir;
        $this->logDir = $logDir;
        $this->type = $parseType;
        $this->geo = $geo;
        
        $this->db =& new Database();
        
        $this->db->write("SET wait_timeout=28800");
        
        if ($parseType == 'downloads' || $parseType == 'collections') {
            $this->counter =& new Count_Downloads($this->db);
        }
        elseif ($parseType == 'updatepings') {
            if (empty($date)) {
                echo "\nUpdate ping counts must specify date parameter.\n";
                exit;
            }
            
            $this->date = $date;
            $this->counter =& new Count_Update_Pings($this->db, $this->date);
        }
    }
    
   /**
    * Finds all logfiles in the selected directory and if they have not already
    * been parsed, passes to parsing function.
    */
    function start() {
        echo "\n---------- [ BEGIN ACCESS LOG PARSING FOR {$this->geo}] ----------\n";
        exec("find {$this->logDir} -name \"access-{$this->date}*.gz\" -type f", $loglist);
        
        if (!empty($loglist)) {
            foreach ($loglist as $logfile) {
                if (!empty($logfile)) {
                    $logfile_query = $this->db->read("SELECT * FROM logs_parsed WHERE name='".mysql_real_escape_string(basename($logfile))."' AND geo='".mysql_real_escape_string($this->geo)."'");
                    $logfile_result = mysql_fetch_array($logfile_query);
                    
                    if ($logfile_result["{$this->type}_done"] == 1)
                        echo basename($logfile)." has already been parsed for {$this->geo}!\n";
                    else {
                        if (empty($logfile_result))
                            $this->db->write("INSERT INTO logs_parsed (name, geo) VALUES('".mysql_real_escape_string(basename($logfile))."', '".mysql_real_escape_string($this->geo)."')");
                        
                        $this->parse($logfile);
                    }
                }
            }
        }
        else
            echo "No logfiles found in {$this->logDir}\n";
        
        echo "\n---------- [ END ACCESS LOG PARSING ] ----------\n";
        
        $this->finish();
    }

   /**
    * Extracts log to temp file and begins matching line patterns and hands off
    * to counters.
    *
    * @todo this function should be split up because it's hard to test this way.
    * @param string $logfile the name of the current logfile
    */
    function parse($logfile) {
        echo "\n---------- [ Copying {$logfile} ] ----------\n";
        $tempFile = "{$this->tmpDir}/addon_log_file_".str_replace(' ', '_', microtime());
        
        if ($this->type == 'downloads')
            $pattern = 'downloads/file/';
        elseif ($this->type == 'updatepings')
            $pattern = 'VersionCheck.php';
        elseif ($this->type == 'collections')
            $pattern = 'GET [A-Za-z/-]*collections';
            
        // Strip relevant lines out of log file and write to a temp file
        exec("gzip -cd {$logfile} | grep '{$pattern}' > {$tempFile}");
        if (!$fp = fopen($tempFile, 'r'))
            die('Failed to open temp file');
        
        echo "\n---------- [ Parsing {$logfile} in {$this->geo}] ----------\n";
        
        while ($line = fgets($fp)) {

            $lineDetails = $this->parseLine($line);
            
            if (!is_array($lineDetails))
                continue;
                
            if ($geoFound = $this->fromMozillaDatacenter($lineDetails['ip'])) {
                $this->counter->totalSkipped[$geoFound]++;
                outputIfVerbose("[{$this->type}Counter] IP ({$lineDetails['ip']}) from Mozilla {$geoFound}; skipped");
                continue;
            }
            
            if ($this->type == $lineDetails['type'])
                $this->counter->count($lineDetails);
        }
        
        // Logfile post-parse callback
        $this->counter->logfileParsedCallback();
        
        // Mark file as finished parsing
        $this->db->write("UPDATE logs_parsed SET {$this->type}_done=1 WHERE name='".mysql_real_escape_string(basename($logfile))."' AND geo='".mysql_real_escape_string($this->geo)."'");
        
        fclose($fp);
        
        // Delete temp file
        unlink($tempFile);
        
        echo "\n---------- [ Finished parsing {$logfile} ] ----------\n";
    }

   /**
    * Split log line into the chunks we need
    */
    function parseLine($line) {

        if (empty($line))
            return false;

        // Match line patterns
        preg_match("/^(\S+) (\S+) (\S+)\s+\[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] \"(\S+) (.*?) (\S+)\" (\S+) (\S+) (\".*?\") (\".*?\") (\".*?\")$/", $line, $matches);

        if (empty($matches[0])) {
            outputIfVerbose("Could not match log entry to pattern: {$line}\n");
            return false;
        } else {

            $lineDetails = log_parser::getLineDetails($matches);

            return $lineDetails;
        }
    }

   /**
    * Breaks pattern matches into relevant descriptions
    */
    function getLineDetails($matches) {
        // Break matches up
        $log_data = array();
        $log_data['ip'] = $matches[1];
        $log_data['identity'] = $matches[2];
        $log_data['user'] = $matches[2];
        $log_data['date'] = $matches[4];
        $log_data['time'] = $matches[5];
        $log_data['timezone'] = $matches[6];
        $log_data['method'] = $matches[7];
        $log_data['path'] = $matches[8];
        $log_data['protocol'] = $matches[9];
        $log_data['status'] = $matches[10];
        $log_data['bytes'] = $matches[11];
        $log_data['referer'] = $matches[12];
        $log_data['agent'] = $matches[13];
        $log_data['otherstuff'] = $matches[14];
      
        // It's awesome that strtotime can't parse a standard apache timestamp
        $log_data['unixtime'] = strtotime(str_replace('/', ' ', $log_data['date'])." {$log_data['time']}");
      
        // Break up URL into pieces we need for download counts or update pings
        preg_match("/(file|VersionCheck\.php)(\/([0-9]*))?(\?reqVersion=([^&]+)&id=([^&]+)(&version=([^&]+))?(&maxAppVersion=([^&]+))?(&status=([^&]+))?(&appID=([^&]+))?(&appVersion=([^&]+))?(&appOS=([^&]+))?(&appABI=(\S*))?)?/", $log_data['path'], $matches);
        
        if (empty($matches)) {
            // If that first crazy regex fails, let's see if it's a collection
            preg_match("/(collections)\/success\?i=([0-9,].+)/", $log_data['path'], $matches);
            
            if (empty($matches)) 
                return false;
        }
        
        // Set request type
        if ($matches[1] == 'file')
            $log_data['type'] = 'downloads';
        elseif ($matches[1] == 'VersionCheck.php')
            $log_data['type'] = 'updatepings';
        elseif ($matches[1] == 'collections')
            $log_data['type'] = 'collections';
        
        // If a download, get the file id out
        if ($log_data['type'] == 'downloads') {
            $log_data['fileid'] = mysql_real_escape_string($matches[3]);
        }
        // If it's an update ping, get out all of the details
        elseif ($log_data['type'] == 'updatepings') {
            $log_data['addon']['reqVersion'] = !empty($matches[5]) ? $matches[5] : null;
            $log_data['addon']['guid'] = !empty($matches[6]) ? $matches[6] : null;
            $log_data['addon']['version'] = !empty($matches[8]) ? $matches[8] : null;
            $log_data['addon']['maxAppVersion'] = !empty($matches[10]) ? $matches[10] : null;
            $log_data['addon']['status'] = !empty($matches[12]) ? $matches[12] : null;
            $log_data['addon']['appID'] = !empty($matches[14]) ? $matches[14] : null;
            $log_data['addon']['appVersion'] = !empty($matches[16]) ? $matches[16] : null;
            $log_data['addon']['appOS'] = !empty($matches[18]) ? $matches[18] : null;
            $log_data['addon']['appABI'] = !empty($matches[20]) ? $matches[20] : null;
        }
        // If it's a collection update, parse out the add-on ids
        elseif ($log_data['type'] == 'collections') {
            $_ids = explode(',', $matches[2]);

            // Filter for numbers.  We can use ctype_* because explode() returns strings
            $log_data['addon_ids'] = array_filter($_ids, 'ctype_digit');
        }
        
        return $log_data;
    }
    
    /**
     * Determines if an IP is from a Mozilla datacenter
     */
    function fromMozillaDatacenter($ip) {
        global $datacenters;
        
        if (empty($datacenters))
            return false;
        
        foreach ($datacenters as $geo => $datacenter_ips) {
            foreach ($datacenter_ips as $datacenter_ip) {
                if (strpos($ip, $datacenter_ip) !== false)
                    return $geo;
            }
        }
        
        return false;
    }
    
   /**
    * Called when finished parsing all logfiles to update counts in the database
    */
    function finish() {
        echo "\n{$this->type} counted: {$this->counter->totalCounted}\n";
        foreach ($this->counter->totalSkipped as $skipped => $count) {
            echo "\tSkipped because of {$skipped}: {$count}\n";
        }
        
        $this->db->close();
    }
}

?>
