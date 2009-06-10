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
 * Determine the os_id based on passed OS or guess based on UA string.
 * @param string|null $os
 * @return int|bool $id id of the OS in the AMO database
 */
function get_os_id($appOS=null)
{

    if (is_null($appOS) && !empty($_GET['appOS']) && ctype_alpha($_GET['appOS'])) {
        $appOS = $_GET['appOS']; 
    }

    // possible matches
    $os = array(
        'linux'=>PLATFORM_LINUX,
        'bsd'=>PLATFORM_BSD,
        'darwin'=>PLATFORM_MAC,
        'winnt'=>PLATFORM_WIN,
        'sunos'=>PLATFORM_SUN
    );

    // Check for a match.
    $appOS = strtolower($appOS);
    foreach ($os as $string=>$id) {
        if ($appOS==$string) {
            return $id;
        }

        if (strpos($appOS,$string)!==false) {
            return $id;
        }
    }

    // If we get here, there is no defined OS and the query will instead rely
    // on "ALL" (1) in the OR
    return false;
}
?>
