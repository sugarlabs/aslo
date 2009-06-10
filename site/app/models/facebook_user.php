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

class FacebookUser extends AppModel
{
    var $name = 'FacebookUser';
    var $tableName = 'facebook_users';

   /**
    * Check if user is in db
    */
    function exists($fbUser) {
        if ($this->query("SELECT * FROM {$this->tableName} WHERE fb_user='{$fbUser}'"))
            return true;
        else
            return false;
    }
    
   /**
    * Update timestamp of when user added the app
    */
    function add($fbUser) {
        if ($this->exists($fbUser)) {
            $this->execute("UPDATE {$this->tableName} SET added=NOW(), lastactivity=NOW() WHERE fb_user='{$fbUser}'");
        }
        else {
            $this->execute("INSERT INTO {$this->tableName} (fb_user, added, lastactivity) VALUES('{$fbUser}', NOW(), NOW())");
        }
    }
    
   /**
    * Update timestamp of when user removed the app
    */
    function remove($fbUser) {
        if ($this->exists($fbUser)) {
            $this->execute("UPDATE {$this->tableName} SET removed=NOW() WHERE fb_user='{$fbUser}'");
        }
        else {
            $this->execute("INSERT INTO {$this->tableName} (fb_user, removed) VALUES('{$fbUser}', NOW())");
        }
    }
    
   /**
    * Update last activity
    */
    function updateActivity($fbUser) {
        return $this->execute("UPDATE {$this->tableName} SET lastactivity=NOW() WHERE fb_user='{$fbUser}'");
    }
    
   /**
    * Count of users within a certain interval
    */
    function getUsersInInterval($field, $interval) {
        $result = $this->query("SELECT COUNT(*) AS users FROM {$this->tableName} WHERE {$field} >=  DATE_SUB(NOW(), INTERVAL {$interval})");
        
        return $result[0][0]['users'];
    }
    
   /**
    * Count of users that currently have the app added
    */
    function getUsersTotal() {
        $result = $this->query("SELECT COUNT(*) AS users FROM {$this->tableName} WHERE removed = '0000-00-00 00:00:00'", true);
        
        return $result[0][0]['users'];
    }
    
   /**
    * Count of users that have ever used the app
    */
    function getUsersEver() {
        $result = $this->query("SELECT COUNT(*) AS users FROM {$this->tableName}", true);
        
        return $result[0][0]['users'];
    }
    

}
?>
