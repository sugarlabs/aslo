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

class FacebookData extends AppModel
{
    var $name = 'FacebookData';
    var $tableName = 'facebook_data';

   /**
    * Updates anonymous usage data
    */
    function updateData($action, $fbUser, &$api_client) {
        $data = $this->getData($fbUser, $api_client);
        
        $age = $this->getAge($data[0]['birthday']);
        $ageRange = $this->getAgeRange($age);
        $sex = $this->getSex($data[0]['sex']);
        
        if ($sex !== false) {
            if ($action == 'add')
                $this->execute("UPDATE {$this->tableName} SET count_current=count_current+1, count_ever=count_ever+1 WHERE trait='sex_{$sex}'");
            elseif ($action == 'remove')
                $this->execute("UPDATE {$this->tableName} SET count_current=count_current-1 WHERE trait='sex_{$sex}'");
        }
        
        if ($age !== false) {
            if ($action == 'add')
                $this->execute("UPDATE {$this->tableName} SET count_current=count_current+1, count_ever=count_ever+1 WHERE trait='age_{$ageRange}'");
            elseif ($action == 'remove')
                $this->execute("UPDATE {$this->tableName} SET count_current=count_current-1 WHERE trait='age_{$ageRange}'");
        }
    }
    
    function getData($fbUser, &$api_client) {
        return $api_client->fql_query("SELECT sex, birthday FROM user WHERE uid='{$fbUser}'");
    }
    
    function getAge($birthday) {
        if (preg_match('/\d{4}/', $birthday, $matches)) {
            $year = $matches[0];
            
            return date('Y') - $year;
        }
        
        return false;
    }
    
    function getAgeRange($age) {
        if ($age < 12)
            return 'under12';
        elseif ($age >= 12 && $age <= 15)
            return '12to15';
        elseif ($age >= 16 && $age <= 19)
            return '16to19';
        elseif ($age >= 20 && $age <= 23)
            return '20to23';
        elseif ($age >= 24 && $age <= 27)
            return '24to27';
        elseif ($age >= 28 && $age <= 31)
            return '28to31';
        elseif ($age >= 32 && $age <= 35)
            return '32to35';
        elseif ($age >= 36 && $age <= 39)
            return '36to39';
        elseif ($age >= 40 && $age <= 49)
            return '40to49';
        elseif ($age >= 50 && $age <= 59)
            return '50to59';
        elseif ($age >= 60)
            return 'above60';
    }
    
    function getSex($sex) {
        if ($sex == 'male')
            return 'male';
        elseif ($sex == 'female')
            return 'female';
        else
            return false;
    }

}
?>
