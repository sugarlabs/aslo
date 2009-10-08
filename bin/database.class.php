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
 *   Wil Clouser <wclouser@mozilla.com>
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

// Include config file
$root = dirname(dirname(__FILE__));
require_once("{$root}/site/app/config/config.php");
require_once("{$root}/site/app/config/constants.php");

/**
 * Database class for services
 */
class Database {
    var $write; // Writable database
    var $read; // Read-only database

   /**
    * Connect to databases
    */
    function Database($write_config=array(), $read_config=array()) {

        if (empty($write_config)) {
            $write_config = array(
                'host' => DB_HOST,
                'user' => DB_USER,
                'pass' => DB_PASS,
                'name' => DB_NAME
                );
        }

        if (empty($read_config)) {
            $read_config = array(
                'host' => SHADOW_DB_HOST,
                'user' => SHADOW_DB_USER,
                'pass' => SHADOW_DB_PASS,
                'name' => SHADOW_DB_NAME
                );
        }

        $this->write_config = $write_config;
        $this->read_config = $read_config;

        $this->personas_config = array(
            'host' => PERSONAS_DB_HOST,
            'user' => PERSONAS_DB_USER,
            'pass' => PERSONAS_DB_PASS,
            'name' => PERSONAS_DB_NAME
        );

        $this->connectWrite($write_config['host'], $write_config['user'], $write_config['pass'], $write_config['name']);
        $this->connectRead($read_config['host'], $read_config['user'], $read_config['pass'], $read_config['name']);

        $this->define_debug();
    }

   /**
    * Connects to read-only database
    */
    function connectRead($host, $username, $password, $database) {
        $this->read = mysql_connect($host, $username, $password) or die('Could not connect: '.mysql_error());
        mysql_select_db($database, $this->read) or die("Could not select read-only database {$database}");
    }

   /**
    * Connects to writable database
    */
    function connectWrite($host, $username, $password, $database) {
        $this->write = mysql_connect($host, $username, $password) or die('Could not connect: '.mysql_error());
        mysql_select_db($database, $this->write) or die("Could not select writable database {$database}");
    }

    /**
     * Runs the query against the slave db, so you should only do reads here.
     */
    function read($q) {
        return $this->query($q);
    }

    /**
     * Runs the query against the master db, which is where we want our writes.
     */
    function write($q) {
        return $this->query($q, true);
    }

   /**
    * Performs query using read-only database by default
    *
    * @param str $qry the query to execute
    * @param bool $useWrite whether to use the writable database
    */
    function query($qry, $useWrite = false) {
        if (!$result = mysql_query($qry, ($useWrite ? $this->write : $this->read))) {
            trigger_error('MySQL Error '.mysql_errno().': '.mysql_error()."\nQuery was: [".$qry.']', E_USER_NOTICE);
            return false;
        }

        return $result;
    }

   /**
    * Sets the stats_updating config value to prevent the Stats Dashboard from
    * showing inaccurate data while a script is running.
    */
    function lockStats() {
        if ($this->write("UPDATE `config` SET `value`='1' WHERE `key`='stats_updating'"))
            return true;
        else
            return false;
    }

   /**
    * Rests the stats_updating config value to make the Stats Dashboard function
    * again
    */
    function unlockStats() {
        if ($this->write("UPDATE `config` SET `value`='0' WHERE `key`='stats_updating'"))
            return true;
        else
            return false;
    }

   /**
    * Close database connections
    */
    function close() {
        mysql_close($this->write);
        mysql_close($this->read);
    }

    /**
     * Define the CRON_DEBUG constant.  Perhaps a little out of place in this class but all the maintenance scripts
     * that need it are including this file already.
     */
    function define_debug() {
        $_debug_result = $this->read('SELECT value FROM config WHERE `config`.`key`="cron_debug_enabled"');
        if (mysql_num_rows($_debug_result) == 1) {
            $row = mysql_fetch_array($_debug_result);
            if ($row['value'] == 1) {
                define('CRON_DEBUG', true);
            } else {
                define('CRON_DEBUG', false);
            }
        } else {
            // Default to true
            define('CRON_DEBUG', true);
        }
    }
}

?>
