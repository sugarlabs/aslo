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
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2007
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

class Config extends AppModel
{
    var $name = 'Config';
    var $useTable = 'config';
    var $primaryKey = 'key';
    
    var $config = array();
    
   /**
    * Retrieve the value of a specific key.
    * @param string $key the key to retrieve
    * @return string
    */
    function getValue($key) {
        //If the config has been retrieved already, use it.
        if (!empty($this->config)) {
            return array_key_exists($key, $this->config) ? $this->config[$key] : null;
        }
        else {
            $config = $this->getConfig();
            return array_key_exists($key, $this->config) ? $config[$key] : null;
        }
    }
    
   /**
    * Pull all config from the database and store it
    * @return array
    */
    function getConfig() {
        $config = array();
        
        if ($configQry = $this->findAll()) {
            foreach ($configQry as $item) {
                $config[$item['Config']['key']] = $item['Config']['value'];
            }
        }
        
        //Store config for future use
        $this->config = $config;
        
        return $config;
    }

    /**
     * Clear the cached config values.
     */
    function expire() {
        $this->config = array();
    }
}
?>
