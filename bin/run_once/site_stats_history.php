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
 *   Scott McCammon <smccammon@mozilla.com> (Original Author)
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

// Prevent running from the web
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

// Include class files
require_once('../database.class.php');

$db = new Database();

$from_date = '2006-09-01';

$stats = array(
    'addon_downloads_new'  => "IFNULL((SELECT SUM(`count`) FROM download_counts WHERE `date` = '%DATE%'), 0)",
    'addon_count_new'      => "SELECT COUNT(*) FROM addons WHERE DATE(created) = '%DATE%'",
    'version_count_new'    => "SELECT COUNT(*) FROM versions WHERE DATE(created) = '%DATE%'",
    'user_count_new'       => "SELECT COUNT(*) FROM users WHERE DATE(created) = '%DATE%'",
    'review_count_new'     => "SELECT COUNT(*) FROM reviews WHERE DATE(created) = '%DATE%'",
    'collection_count_new' => "SELECT COUNT(*) FROM collections WHERE DATE(created) = '%DATE%'",
);

foreach ($stats as $stat => $sum_query) {
    $affected_rows = 0;

    echo "Processing {$stat} stats... ";

    // possibly resume where previous runs left off
    $rows = $db->read("SELECT IFNULL(MAX(`date`), '{$from_date}') AS latest FROM global_stats WHERE name = '{$stat}'");
    $row = mysql_fetch_array($rows);
    $start_ts = strtotime($row['latest']);

    $end_ts = time();
    for ($ts = $start_ts; $ts <= $end_ts; $ts += 86400) {
        $stat_date = date('Y-m-d', $ts);
        $db->write("REPLACE INTO global_stats (name, count, date)
                        VALUES ('{$stat}', (".str_replace('%DATE%', $stat_date, $sum_query)."), '{$stat_date}')");
        $affected_rows += mysql_affected_rows();
    }
    echo "({$affected_rows} rows affected)\n";
}

echo "done.\n";

?>
