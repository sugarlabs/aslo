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

/**
 * Counts update pings and stores detailed data for statistics use
 */
class Count_Update_Pings {
    var $db;
    var $guids = array(); // array of GUIDs and add-on ids
    var $counts = array(); // array of update ping counts
    var $totalSkipped = array('incorrect_date' => 0, 'unknown_guid' => 0, 'SJ' => 0, 'NL' => 0, 'CN' => 0);
    var $totalCounted = 0;
    var $date; // Only count hits for this date (format: %Y-%m-%d)
    
   /**
    * Retain reference to database connection and cache GUIDs
    */
    function Count_Update_Pings(&$db, $date) {
        $this->db =& $db;
        $this->date = $date;
        
        $this->cacheGUIDs();
    }
    
   /**
    * Pulls all GUIDs and add-on IDs from database for quick lookup
    */
    function cacheGUIDs() {
        $guid_query = $this->db->read("SELECT id, guid FROM addons");
        while ($guid_row = mysql_fetch_array($guid_query)) {
            $guids[$guid_row['guid']] = $guid_row['id'];
        }
        
        $this->guids = $guids;
    }
    
   /**
    * Increment relevant properties of each add-on for each date
    *
    * @param array $details details from the parsed log line
    */
    function count($details) {
        // Make sure GUID is known
        if (empty($this->guids[$details['addon']['guid']])) {
            $this->totalSkipped['unknown_guid']++;
            outputIfVerbose("[UpdatePingCounter] Unknown GUID skipped: {$details['addon']['guid']}");
        }
        else {
            $addon_id = $this->guids[$details['addon']['guid']];
            $date = date('Ymd', $details['unixtime']);
            // Bug 468570
            if ($date != $this->date) {
                #$this->totalSkipped['incorrect_date']++;
                #outputIfVerbose("[UpdatePingCounter] Skipping out of range date {$date} for add-on id {$addon_id}");
                #return;
            }
            
            if (empty($this->counts[$date]))
                $this->counts[$date] = array();
                
            $count =& $this->counts[$date][$addon_id];
            
            // Total
            if (!empty($count['total']))
                $count['total']++;
            else
                $count['total'] = 1;
            
            // Versions
            if (!empty($details['addon']['version'])) {
                if (!empty($count['version'][$details['addon']['version']]))
                    $count['version'][$details['addon']['version']]++;
                else
                    $count['version'][$details['addon']['version']] = 1;
            }
            
            // Status
            if (!empty($details['addon']['status'])) {
                if (!empty($count['status'][$details['addon']['status']]))
                    $count['status'][$details['addon']['status']]++;
                else
                    $count['status'][$details['addon']['status']] = 1;
            }
            
            // Application
            if (!empty($details['addon']['appID']) && !empty($details['addon']['appVersion'])) {
                if (!empty($count['app'][$details['addon']['appID']][$details['addon']['appVersion']]))
                    $count['app'][$details['addon']['appID']][$details['addon']['appVersion']]++;
                else
                    $count['app'][$details['addon']['appID']][$details['addon']['appVersion']] = 1;
            }
            
            // OS
            if (!empty($details['addon']['appOS'])) {
                if (!empty($count['os'][$details['addon']['appOS']]))
                    $count['os'][$details['addon']['appOS']]++;
                else
                    $count['os'][$details['addon']['appOS']] = 1;
            }
            
            $this->totalCounted++;
            outputIfVerbose("[UpdatePingCounter] Locally updated counts for add-on ID: {$addon_id}");
        }
    }
    
   /**
    * Writes saved counts to the database
    */
    function updateCounts() {
        if (!empty($this->counts)) {
            echo "\n[UpdatePingCounter] WRITING COUNTS TO DATABASE...\n";
            foreach ($this->counts as $date => $addons) {
                foreach ($addons as $addon_id => $counts) {
                    $existing_entry_qry = $this->db->read("SELECT * FROM update_counts WHERE addon_id='{$addon_id}' AND date='{$date}'");
                    $existing_result = mysql_fetch_array($existing_entry_qry);
                    
                    // If row does not exist for date and add-on, insert new row
                    if (empty($existing_result)) {
                        $counts = $this->serializeCounts($counts);
                        
                        $this->db->write("
                            INSERT INTO update_counts (
                                addon_id,
                                count,
                                version,
                                status,
                                application,
                                os,
                                date
                            ) VALUES(
                                '{$addon_id}',
                                {$counts['total']},
                                '{$counts['version']}',
                                '{$counts['status']}',
                                '{$counts['app']}',
                                '{$counts['os']}',
                                '{$date}'
                            )");
                        
                        outputIfVerbose("[UpdatePingCounter] Created record for: {$date} / {$addon_id}");
                    }
                    // If row does exist, add the totals together
                    else {
                        $counts = $this->unserializeAndTotalCounts($counts, $existing_result);
                        
                        $this->db->write("
                            UPDATE update_counts
                            SET
                                count = {$counts['total']},
                                version = '{$counts['version']}',
                                status = '{$counts['status']}',
                                application = '{$counts['app']}',
                                os = '{$counts['os']}'
                            WHERE
                                addon_id='{$addon_id}' AND
                                date='{$date}'
                            ");
                        
                        outputIfVerbose("[UpdatePingCounter] Updated record for: {$date} / {$addon_id}");
                    }
                }
            }
            
            // Now that counts have been written, we can clear the array for future counting
            $this->counts = array();
        }
    }

   /**
    * Serializes the array of counts and escapes for SQL query
    *
    * @param array $counts the count totals for an add-on
    */
    function serializeCounts($counts) {   
        $counts['version'] = !empty($counts['version']) ? mysql_real_escape_string(serialize($counts['version'])) : '';
        $counts['status'] = !empty($counts['status']) ? mysql_real_escape_string(serialize($counts['status'])) : '';
        $counts['app'] = !empty($counts['app']) ? mysql_real_escape_string(serialize($counts['app'])) : '';
        $counts['os'] = !empty($counts['os']) ? mysql_real_escape_string(serialize($counts['os'])) : '';
        
        return $counts;
    }
    
   /**
    * Unserializes existing counts from the database and totals the existing
    * counts with the new counts from this session's parsing.
    *
    * @param array $counts the session's counts
    * @param array $existing the existing counts
    */
    function unserializeAndTotalCounts($counts, $existing) {
        // Unserialize existing data from database
        $existing = array(
                          'total' => $existing['count'],
                          'version' => unserialize($existing['version']),
                          'status' => unserialize($existing['status']),
                          'app' => unserialize($existing['application']),
                          'os' => unserialize($existing['os'])
                          );
        
        // Merge the two arrays into a new array
        $totalCounts = array_merge_recursive($counts, $existing);
        
        // If there are duplicate key names, an array will be created, so
        // we loop through and find such occurrences
        foreach ($totalCounts as $property => $items) {
            if ($property == 'total') {
                $totalCounts[$property] = array_sum($items);
            }
            elseif (!empty($items)) {
                foreach ($items as $item => $count) {
                    if ($property != 'app') {
                        if (is_array($count)) {
                            $totalCounts[$property][$item] = array_sum($count);
                        }
                    }
                    elseif (!empty($count)) {
                        // the app property has an additional level of elements
                        foreach ($count as $appVersion => $appVersionCount) {
                            if (is_array($appVersionCount)) {
                                $totalCounts[$property][$item][$appVersion] = array_sum($appVersionCount);
                            }
                        }
                    }
                }
            }
        }
        
        return $this->serializeCounts($totalCounts);
    }

   /**
    * Callback for after a logfile is parsed.
    */
    function logfileParsedCallback() {
        $this->updateCounts();
    }
}

?>
