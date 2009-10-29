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
 *   Scott McCammon <smccammon@mozilla.com>
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
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

class HubRssKey extends AppModel
{
    var $name = "HubRssKey";
    var $useTable = 'hubrsskeys';

    /**
     * Fetches and optionally creates a secret key for the specified add-on
     *
     * @param int $addon_id
     * @param bool $create if true, attempts to create a key if none found
     * @return mixed rsskey string, or false on failure
     */
    function getKeyForAddon($addon_id, $create=true) {
        $retval = false;

        if ($addon_id > 0) {
            $rsskey = $this->findByAddon_id($addon_id);

            if (!empty($rsskey)) {
                $retval = $rsskey['HubRssKey']['rsskey'];

            } else if ($create) {
                $rsskey = array('HubRssKey' => array(
                    'addon_id' => $addon_id,
                    'rsskey' => $this->uuid()
                ));

                if ($this->save($rsskey)) {
                    $retval = $rsskey['HubRssKey']['rsskey'];
                }
            }
        }
        return $retval;
    }

    /**
     * Fetches and optionally creates a secret key for the specified user
     *
     * @param int $user_id
     * @param bool $create if true, attempts to create a key if none found
     * @return mixed rsskey string, or false on failure
     */
    function getKeyForUser($user_id, $create=true) {
        $retval = false;

        if ($user_id > 0) {
            $rsskey = $this->findByUser_id($user_id);

            if (!empty($rsskey)) {
                $retval = $rsskey['HubRssKey']['rsskey'];

            } else if ($create) {
                $rsskey = array('HubRssKey' => array(
                    'user_id' => $user_id,
                    'rsskey' => $this->uuid()
                ));

                if ($this->save($rsskey)) {
                    $retval = $rsskey['HubRssKey']['rsskey'];
                }
            }
        }
        return $retval;
    }

    /**
     * Lookup the add-on id associated with the given key
     * @param string $rsskey
     * @return mixed addon_id or false if not found
     */
    function getAddonForKey($rsskey) {
        $row = $this->isValidUUID($rsskey) ? $this->findByRsskey($rsskey) : array();
        return !empty($row['HubRssKey']['addon_id']) ? $row['HubRssKey']['addon_id'] : false;
    }

    /**
     * Lookup the user id associated with the given key
     * @param string $rsskey
     * @return mixed user_id or false if not found
     */
    function getUserForKey($rsskey) {
        $row = $this->isValidUUID($rsskey) ? $this->findByRsskey($rsskey) : array();
        return !empty($row['HubRssKey']['user_id']) ? $row['HubRssKey']['user_id'] : false;
    }

    /**
     * Generates a pseudo-random UUID.
     * Slightly modified version of a function submitted to php.net:
     * http://us2.php.net/manual/en/function.com-create-guid.php#52354
     *
     * @access public
     */
    function uuid() {
        mt_srand((double)microtime()*10000);
        $charid = md5(uniqid(rand(), true));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
              . substr($charid, 8, 4).$hyphen
              . substr($charid,12, 4).$hyphen
              . substr($charid,16, 4).$hyphen
              . substr($charid,20,12);

        return $uuid;
    } 

    /**
     * Verifies a UUID's structure (not that it actually exists in our database).
     *
     * @param string uuid
     * @return boolean
     */
    function isValidUUID($uuid) {
        if (preg_match(VALID_UUID_REQ, $uuid) > 0) {
            return true;
        }
        return false;
    } 
}
