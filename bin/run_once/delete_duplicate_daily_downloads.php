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
 *   Mike Morgan <morgamic@mozilla.com>
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

$addon_qry = $db->read("SELECT id FROM addons");

$dates = array(
    '2008-09-23',
    '2008-09-24',
    '2008-09-25',
    '2008-09-26',
    '2008-09-27',
    '2008-09-28'
);

while ($addon = mysql_fetch_array($addon_qry)) {

    foreach ($dates as $date) {
        echo "[{$date} Add-on {$addon['id']}]";
        $count_qry = $db->read("SELECT count(*), MIN(id) FROM download_counts WHERE addon_id={$addon['id']} AND date='{$date}'");
        $count = mysql_fetch_array($count_qry);
        if ($count[0] > 1) {
            echo " ... {$date} has {$count[0]} rows on {$date}";
            $db->write("DELETE FROM download_counts WHERE id > {$count[1]} AND addon_id={$addon['id']} AND date='{$date}'");
            echo " - deleted duplicate rows";
        } else {
            echo " - is OK"; 
        }
        echo "\n";
    }
}
?>
