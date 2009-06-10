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
 * Tests Database Selection
 * If you're looking for database connectivity, that's in installation.test.php, silly.
 * Note: all of these tests have to go through hurdles to not set the actual
 * constants, as once set, constants can't be changed or unset.
 */
   
class DatabaseTest extends UnitTestCase {
    var $default_config = array(
                            0 => array(
                                'DB_HOST' => '',
                                'DB_NAME' => '',
                                'DB_USER' => '',
                                'DB_PASS' => '',
                                'DB_WEIGHT' => 0
                            )
                        );
    
    var $populated_config = array(
                            0 => array(
                                'DB_HOST' => 'localhost',
                                'DB_PORT' => 3306,
                                'DB_NAME' => 'shadow1',
                                'DB_USER' => 'root',
                                'DB_PASS' => '',
                                'DB_WEIGHT' => .75
                            ),
                            1 => array(
                                'DB_HOST' => 'localhost',
                                'DB_PORT' => 3306,
                                'DB_NAME' => 'shadow2',
                                'DB_USER' => 'root',
                                'DB_PASS' => '',
                                'DB_WEIGHT' => .25
                            )
                        );
    
    function testDefaults() {
        // Test that the default configuration will not cause SHADOW_* to be set
        $shadow = select_shadow_database($this->default_config, false);
        $this->assertTrue(empty($shadow), 'Default shadow db config does not cause SHADOW constants to be set');
    }
    
    function testPopulated() {
        // Test that with populated config, one database will be returned
        $shadow = select_shadow_database($this->populated_config, false);
        $this->assertFalse(empty($shadow), 'Populated shadow db config returns 1 database');
    }
    
    function testFallback() {
        global $shadow_databases;
        $db = ConnectionManager::getInstance();
        @$db->getDataSource('shadow');
        // Bunch of errors when disconnecting, so not disconnecting!
        //$db->_dataSources['shadow']->disconnect();
        
        // Save to restore after test
        $orig_shadow_databases = $shadow_databases;
        $original_config = $db->_dataSources['shadow']->config;
        
        ///////////////////////////////////////////////////////////////////////
        // Fallback with 1 bad db
        $shadow_databases = $this->populated_config;
        $this->_setToRealDatabase($shadow_databases[1]);
        $this->_updateShadowConfig($db->_dataSources['shadow'], $shadow_databases[0]);
        
        @$db->_dataSources['shadow']->connect();
        
        // We told it to use shadow db 1, but we *should* get shadow db 2 back
        // because db 1 is bad and db 2 is good
        $this->assertTrue(($db->_dataSources['shadow']->config['database'] == $shadow_databases[1]['DB_NAME']), 'Fallback to shadow database 2 when shadow database 1 is down');
        $this->assertFalse(defined('SHADOW_DISABLED'), 'Shadow databases are still enabled');
        //$db->_dataSources['shadow']->disconnect();
        
        // Reset
        $db->config->shadow = $original_config;
        
        ///////////////////////////////////////////////////////////////////////
        // Fallback with all bad db's
        $shadow_databases = $this->populated_config;
        $this->_updateShadowConfig($db->_dataSources['shadow'], $shadow_databases[0]);
        @$db->_dataSources['shadow']->connect();
        
        // We told it to use shadow db 1, but we *should* get SHADOW_DISABLED back
        // because all shadow db's are bad
        $this->assertTrue(defined('SHADOW_DISABLED'), 'Disabled shadow databases because all shadows are down');
        
        //$db->_dataSources['shadow']->disconnect();
        
        // Restore real database config back to original
        $shadow_databases = $orig_shadow_databases;
        $db->_dataSources['shadow']->config = $original_config;
    }
    
    /**
     * Sets a shadow config to be a real database connection
     */
    function _setToRealDatabase(&$shadow) {
        $shadow['DB_HOST'] = DB_HOST;
        $shadow['DB_PORT'] = DB_PORT;
        $shadow['DB_NAME'] = DB_NAME;
        $shadow['DB_USER'] = DB_USER;
        $shadow['DB_PASS'] = DB_PASS;
    }

    /**
     * Updates the db's shadow config
     */
    function _updateShadowConfig(&$db, $new) {
        $db->config['host'] = $new['DB_HOST'];
        $db->config['login'] = $new['DB_USER'];
        $db->config['password'] = $new['DB_PASS'];
        $db->config['database'] = $new['DB_NAME'];
    }
}
?>
