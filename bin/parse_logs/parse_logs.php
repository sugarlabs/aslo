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

/**
 * AMO Log Parsing
 * Call without paramaters for usage.
 *      Ex: php -f parse_logs.php
 *
 * This file handles the command-line script and starts up the
 * parser for the real work, which then hands off to other classes
 * for counting.
 */

$start = microtime();
$start = explode(' ', $start);
$start = $start[1] + $start[0];

// Prevent running from the web
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

// Include log parser class
require_once('log_parser.class.php');

// Arguments aren't always in $_GET in some environments
if ($argv) {
    for ($i = 1; $i < count($argv); $i++) {
        $param = split('=', $argv[$i]);
        $_GET[$param[0]] = $param[1];
    }
}

$verbose = array_key_exists('v', $_GET);

// Validate arguments
if (!empty($_GET['logs']) && !empty($_GET['temp']) && !empty($_GET['type']) && !empty($_GET['geo'])) {
    if (is_readable($_GET['logs']) && is_writable($_GET['temp'])) {
        if (in_array($_GET['type'], array('collections', 'downloads', 'updatepings'))) {
            $parser = new Log_Parser($_GET['logs'], $_GET['temp'], $_GET['type'], $_GET['geo'], !empty($_GET['date']) ? $_GET['date'] : '');
            $parser->start();
            
            $finished = true;
        }
    }
    else
        echo "ERROR: Could not read log dir and/or could not write to temp dir.\n\n";
}

if (empty($finished)) {
    // Output usage instructions
    print "usage:\n";
    print "php -f parse_logs.php logs=[log_dir] temp=[tmp_dir] type=[parse_type] geo=[geo] date=[date] [v=v]\n";
    print "\tlog_dir:\tDirectory with the access log files\n";
    print "\ttmp_dir:\tDirectory for the temp file to be written\n";
    print "\tparse_type:\tdownloads or updatepings or collections\n";
    print "\tgeo:\tdatacenter from which logs are being parsed\n";
    print "\tdate:\tsingle date for which to parse update pings, in YYYY-MM-DD format\n";
    print "\tv:\tverbose output of progress\n";
    print "sample usage:\n";
    print "\tphp -f parse_logs.php logs=/data/addons.mozilla.org temp=/tmp type=updatepings date=`date --date='yesterday' +%Y-%m-%d` geo=CN v=v\n";
}

$end = microtime();
$end = explode(' ', $end);
$end = $end[1] + $end[0];

echo "\nExecution time: ".($end - $start)."\n";

?>
