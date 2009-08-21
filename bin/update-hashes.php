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
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Mike Morgan <morgamic@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
 *   Reed Loden <reed@reedloden.com>
 *
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
 * The purpose of this script is to retro-actively update existing add-ons
 * with valid hashes.
 *
 * We may not necessarily use this, but it was written just in case we need
 * to run the update in the future.
 *
 * This script should not ever be accessed over HTTP, and instead run via cron.
 * Only sysadmins should be responsible for operating this script.
 *
 * @package amo
 * @subpackage bin
 */


// Before doing anything, test to see if we are calling this from the command
// line.  If this is being called from the web, HTTP environment variables will
// be automatically set by Apache.  If these are found, exit immediately.
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

require_once('database.class.php');

// New database class
$db = new Database();

$versions = array();
$hashes = array();

$fileQry_sql = "SELECT
                   addons.id as addon_id,
                   translations.localized_string as name,
                   versions.version,
                   files.id as file_id,
                   files.filename,
                   files.hash,
                   files.size
                FROM files
                INNER JOIN versions ON files.version_id=versions.id
                INNER JOIN addons ON versions.addon_id=addons.id
                INNER JOIN translations ON addons.name=translations.id
                WHERE
                   translations.locale='en-US'";

$fileQry_result = $db->read($fileQry_sql);

while ($fileInfo = mysql_fetch_array($fileQry_result)) {

    $file = REPO_PATH."/{$fileInfo['addon_id']}/{$fileInfo['filename']}";

    // If the file exists, get its sum and update its record.
    if (file_exists($file) && is_file($file)) {
        $hash = hash_file("sha256", $file);
        $size = round((filesize($file) / 1024), 0); //in KB

        debug("{$fileInfo['name']} {$fileInfo['version']} (file {$fileInfo['file_id']}): ");
        if ('sha256:'.$hash != $fileInfo['hash'] || $size != $fileInfo['size']) {
            $hash_update_sql = "UPDATE files SET hash='sha256:{$hash}', size='{$size}' WHERE id={$fileInfo['file_id']}";
            $hash_update_result = $db->write($hash_update_sql);

            if ('sha256:'.$hash != $fileInfo['hash']) {
                debug("HASH - new: sha256:{$hash}; old: {$fileInfo['hash']}");
            }

            if ($size != $fileInfo['size']) {
                debug("SIZE - new: {$size} KB; old: {$fileInfo['size']} KB");
            }
            debug('');
        }
        else {
            debug("No update needed.");
        }
    }
}

// Close our db connection
$db->close();

function debug($msg) {
    if (CRON_DEBUG) {
        echo "{$msg}\n";
    }
}

exit;
?>
