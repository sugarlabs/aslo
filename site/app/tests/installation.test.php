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
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *   RJ Walsh <rwalsh@mozilla.com>
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

class InstallationTest extends UnitTestCase {

   /**
    * Tests directory permissions
    */
    function testPermissions() {
        $this->assertTrue(is_writable(CACHE), 'Permissions: '.CACHE.' writable');
        $this->assertTrue(is_writable(REPO_PATH), 'Permissions: '.REPO_PATH.' writable');
        $this->assertTrue(is_writable(REPO_PATH.'/temp'), 'Permissions: '.REPO_PATH.'/temp writable');
        $this->assertTrue(is_writable(REPO_PATH.'/extracted'), 'Permissions: '.REPO_PATH.'/extracted writable');
        $this->assertTrue(is_writable(TEST_DATA), 'Permissions: '.TEST_DATA.' writable');
        if (defined('PUBLIC_STAGING_PATH')) {
            $this->assertTrue(is_writable(PUBLIC_STAGING_PATH), 'Permissions: '.PUBLIC_STAGING_PATH.' writable');
        }
    }
    
   /**
    * Tests Apache and modules
    */
    function testApache() {
        $this->assertTrue((strpos(apache_get_version(), 'Apache/2') !== false), 'Apache: Version 2');
        $this->assertTrue(in_array('mod_rewrite', apache_get_modules()), 'Apache: Module mod_rewrite');
    }
    
   /**
    * Tests PHP and extensions
    */
    function testPHP() {
        $this->assertTrue((4 <= phpversion() && phpversion() < 6), 'PHP: Version '.phpversion());
        $this->assertTrue(extension_loaded('gettext'), 'PHP: Extension gettext');
        $this->assertTrue(extension_loaded('gd'), 'PHP: Extension gd');

        $this->assertTrue(function_exists('mb_strlen'), 'PHP: mb_strlen is available');
        $this->assertTrue(function_exists('mb_substr'), 'PHP: mb_substr is available');
        $this->assertTrue(function_exists('mb_strrpos'), 'PHP: mb_strrpos is available');
        $this->assertTrue(in_array('sha512', hash_algos()), 'PHP: sha512 is available');
        $this->assertTrue(in_array('sha1', hash_algos()), 'PHP: sha1 is available');

        $_memlimit_wanted = '16M';
        $_memlimit_actual = $this->_return_bytes(ini_get('memory_limit'));
        $this->assertTrue($_memlimit_actual >= $this->_return_bytes($_memlimit_wanted), "PHP's memory limit has to be larger or equal {$_memlimit_wanted}");
    }

    /**
     * return bytes from php.ini shorthand notation such as "8M"
     */
    function _return_bytes($val) {
        $val = trim($val);
        $last = strtolower(substr($val, -1));
        switch($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
    
   /**
    * Tests for PEAR and required modules
    */
    function testPear() {
        $this->assertTrue(include_once('PEAR.php'), 'PEAR: PEAR');
        $this->assertTrue(include_once('Archive/Zip.php'), 'PEAR: Module Archive_Zip');
    }

   /**
    * Tests DB connections
    */
    function testDB() {
        $db = ConnectionManager::getInstance();

        //If the specific config is not even in the database file, we need to fail or PHP will have a fatal error.
        if ($connected = @$db->getDataSource('default')) {
            $this->assertTrue($connected->isConnected(), 'Database: default');

            // Data in `addontypes`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `addontypes`');
            $this->assertTrue($r[0]['count']>0,'Data in `addontypes` exists.');
            unset($r);

            // Data in `translations`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `translations`');
            $this->assertTrue($r[0]['count']>0,'Data in `translations` exists.');
            unset($r);
        }
        else {
            $this->fail('Database: default - Your database configuration file is not up to date. Please re-copy the default.');
        }
        if ($connected = @$db->getDataSource('shadow')) {
            $this->assertTrue($connected->isConnected(), 'Database: shadow');

            // Data in `addontypes`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `addontypes`');
            $this->assertTrue($r[0]['count']>0,'Data in `addontypes` exists.');
            unset($r);

            // Data in `translations`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `translations`');
            $this->assertTrue($r[0]['count']>0,'Data in `translations` exists.');
            unset($r);
        }
        else {
            $this->fail('Database: shadow - Your database configuration file is not up to date. Please re-copy the default.');
        }
        if ($connected = $db->getDataSource('test')) {
            $this->assertTrue($connected->isConnected(), 'Database: test');       

            // Data in `addontypes`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `addontypes`');
            $this->assertTrue($r[0]['count']>0,'Data in `addontypes` exists.');
            unset($r);

            // Data in `translations`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `translations`');
            $this->assertTrue($r[0]['count']>0,'Data in `translations` exists.');
            unset($r);

            // Test data in `addons`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `addons`');
            $this->assertTrue($r[0]['count']>0,'Test data in `addons` exists.');
            unset($r);

            // Test data in `addons_categories`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `addons_categories`');
            $this->assertTrue($r[0]['count']>0,'Test data in `addons_categories` exists.');
            unset($r);

            // Test data in `addons_users`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `addons_users`');
            $this->assertTrue($r[0]['count']>0,'Test data in `addons_users` exists.');
            unset($r);

            // Test data in `applications`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `applications`');
            $this->assertTrue($r[0]['count']>0,'Test data in `applications` exists.');
            unset($r);

            // Test data in `appversions`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `appversions`');
            $this->assertTrue($r[0]['count']>0,'Test data in `appversions` exists.');
            unset($r);

            // Test data in `files`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `files`');
            $this->assertTrue($r[0]['count']>0,'Test data in `files` exists.');
            unset($r);

            // Test data in `platforms`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `platforms`');
            $this->assertTrue($r[0]['count']>0,'Test data in `platforms` exists.');
            unset($r);

            // Test data in `previews`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `previews`');
            $this->assertTrue($r[0]['count']>0,'Test data in `previews` exists.');
            unset($r);

            // Test data in `categories`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `categories`');
            $this->assertTrue($r[0]['count']>0,'Test data in `categories` exists.');
            unset($r);

            // Test data in `users`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `users`');
            $this->assertTrue($r[0]['count']>0,'Test data in `users` exists.');
            unset($r);

            // Test data in `versions`?
            $r = $connected->fetchRow('SELECT count(*) as count FROM `versions`');
            $this->assertTrue($r[0]['count']>0,'Test data in `versions` exists.');
            unset($r);
        }
        else {
            $this->fail('Database: test - Your database configuration file is not up to date. Please re-copy the default.');
        }
    }

	/**
	 * Tests that JSHydra is installed
	 */
	function testJSHydra() {
		$this->assertTrue(file_exists(JSHYDRA_PATH), 'JSHydra is installed at JSHYDRA_PATH: (return true)');
	}

}
?>
