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
 * Portions created by the Initial Developer are Copyright (C) 2008
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
 * This script checks the integrity of the add-on download and updateping counts
 * obtained from parsing logs.
 *
 * The script checks a number of popular add-ons for several things:
 *      - Does the download count from 2 days ago exist?
 *      - If so, is there a difference of greater or less than 50% from the
 *        previous 2 counts?
 *      - If it's Saturday, does the update ping count from Wednesday exist?
 *      - If so, is there a difference of greater or less than 50% from the
 *        previous 2 counts?
 *
 *  Download counter runs every night and can go into the next morning.
 *  Accounting for a potential 24 hour log delay from the CDN, this is checked
 *  2 days after.
 *  
 *  Update ping counter is only run on Thursday nights and can go into Friday
 *  mornings. Accounting for a potential 24 hour log delay from the CDN, this
 *  will be checked on Saturdays.
 */

// Prevent running from the web
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

// Include class files
require_once('database.class.php');

$db = new Database();

define('DAY_SUNDAY', 0);
define('DAY_MONDAY', 1);
define('DAY_TUESDAY', 2);
define('DAY_WEDNESDAY', 3);
define('DAY_THURSDAY', 4);
define('DAY_FRIDAY', 5);
define('DAY_SATURDAY', 6);

// Some popular add-ons to analyze
$addons = array(
    1865 => 'Adblock Plus',
    722 => 'NoScript',
    201 => 'DownThemAll'
);

foreach ($addons as $addon_id => $addon_name) {
    // DOWNLOADS
    $date = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-2, date('Y')));
    // Does download count from 2 days ago exist?
    $qry_2day = $db->read("SELECT count FROM download_counts WHERE date='{$date}' AND addon_id={$addon_id}");
    if (mysql_num_rows($qry_2day) > 0) {
        $count_2day = mysql_fetch_array($qry_2day);
        output('PASS', "[Downloads] Count from 2 days ago ({$date}) exists: {$count_2day['count']}", $addon_name);
        
        // Does that count have a 50% + or - difference from previous 2 counts?
        $qry_last2 = $db->read("SELECT date, count FROM download_counts WHERE addon_id={$addon_id} AND date < '{$date}' ORDER BY date DESC LIMIT 2");
        if (mysql_num_rows($qry_last2) > 0) {
            while ($row = mysql_fetch_array($qry_last2)) {
                $change = ceil(abs(($count_2day['count'] - $row['count']) / $row['count']) * 100);
                if ($change >= 50) {
                    output('FAIL', "[Downloads] Count from 2 days ago changed by {$change}% from {$row['date']} count of {$row['count']}", $addon_name);
                }
                else {
                    output('PASS', "[Downloads] Count from 2 days ago changed by {$change}% from {$row['date']} count of {$row['count']}", $addon_name);
                }
            }
        }
    }
    else {
        output('FAIL', "[Downloads] Count from 2 days ago ({$date}) does not exist", $addon_name);
    }
    
    // UPDATE PINGS
    if (date('w') == DAY_SATURDAY) {
        $wed = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-3, date('Y')));
        
        // Does update ping count from wednesday exist?
        $qry_wed = $db->read("SELECT count FROM update_counts WHERE date='{$wed}' AND addon_id={$addon_id}");
        if (mysql_num_rows($qry_wed) > 0) {
            $count_wed = mysql_fetch_array($qry_wed);
            output('PASS', "[Update Pings] Count from Wednesday ({$wed}) exists: {$count_wed['count']}", $addon_name);
            
            // Does that count have a 50% + or - difference from previous 2 counts?
            $qry_last2 = $db->read("SELECT date, count FROM update_counts WHERE addon_id={$addon_id} AND date < '{$wed}' ORDER BY date DESC LIMIT 2");
            if (mysql_num_rows($qry_last2) > 0) {
                while ($row = mysql_fetch_array($qry_last2)) {
                    $change = ceil(abs(($count_wed['count'] - $row['count'])/ $row['count']) * 100);
                    if ($change >= 50) {
                        output('FAIL', "[Update Pings] Count from Wednesday changed by {$change}% from {$row['date']} count of {$row['count']}", $addon_name);
                    }
                    else {
                        output('PASS', "[Update Pings] Count from Wednesday changed by {$change}% from {$row['date']} count of {$row['count']}", $addon_name);
                    }
                }
            }
        }
        else {
            output('FAIL', "[Update Pings] Count from Wednesday ({$wed}) does not exist", $addon_name);
        }
    }
    else {
        output('NOTICE', "[Update Pings] Update ping integrity not checked because today is ".date('l'), $addon_name);
    }
}


function output($result, $message, $addon_name = '') {
    echo "[{$result}]";
    if (!empty($addon_name))
        echo "[{$addon_name}] ";
    echo "{$message}\n";
}

?>
