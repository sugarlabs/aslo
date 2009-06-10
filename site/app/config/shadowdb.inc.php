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
 * Select a shadow database to use for this request
 * @param array $databases The weighted database possibilities
 * @param bool $set_constants Whether to set the constants (false for testing)
 */
function select_shadow_database($databases, $set_constants = true) {
    if (!empty($databases)) {
        $database_keys = array();
        // Store shadow db keys in an array proportionate to their weight
        foreach ($databases as $k => $shadow_database) {
            if ($shadow_database['DB_WEIGHT'] > 0)
                $database_keys = array_merge($database_keys, array_fill(0, $shadow_database['DB_WEIGHT'] * 100, $k));
        }
        
        if (!empty($database_keys)) {
            // Select random database from weighted array
            $shadow_database = $databases[$database_keys[array_rand($database_keys)]];
            
            // Constants shouldn't be set if we're testing because they can't be
            // changed or unset
            if ($set_constants) {
                define('SHADOW_DB_USER', $shadow_database['DB_USER']);
                define('SHADOW_DB_PASS', $shadow_database['DB_PASS']);
                define('SHADOW_DB_HOST', $shadow_database['DB_HOST']);
                define('SHADOW_DB_NAME', $shadow_database['DB_NAME']);
                define('SHADOW_DB_PORT', $shadow_database['DB_PORT']);
            }
            
            unset($database_keys);
            
            return $shadow_database;
        }
    }
    
    return array();
}

?>
