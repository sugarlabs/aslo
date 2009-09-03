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
 *   Wil Clouser <clouserw@mozilla.com>
 *   Justin Scott <fligtar@mozilla.com>
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
 * This is a lightweight page designed to be monitored with a program like nagios. I
 * don't want to duplicate the test suite, but I do want something that we can hit
 * pretty often.  If there is a problem, this will throw a 500 error.
 *
 */

// Never cache this page
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private');
header('Pragma: no-cache');

// The monitor script doesn't load all of cake's defines which we are using in config.php
if (!defined('VENDORS')) {
    define('VENDORS', '../../../vendors/');
}

// Grab the site config
require_once('../../config/config.php');
require_once('../../config/constants.php');
require_once('../../../vendors/zxtm-api/moz_zxtmapi.class.php');

global $results, $shadow_databases, $zxtm_config;

// Check Main Database
    $dbh = @mysql_connect(DB_HOST.':'.DB_PORT,DB_USER,DB_PASS);
    testo('Connect to MAIN database ('.DB_HOST.')', is_resource($dbh));
    testo('Select MAIN database ('.DB_NAME.')', @mysql_select_db(DB_NAME, $dbh));
    unset ($dbh);

// Check Shadow Databases
    if (!empty($shadow_databases)) {
        foreach ($shadow_databases as $k => $shadow_database) {
            $dbh = @mysql_connect($shadow_database['DB_HOST'].':'.$shadow_database['DB_PORT'],$shadow_database['DB_USER'],$shadow_database['DB_PASS']);
            testo("Connect to SHADOW database {$k} @ {$shadow_database['DB_WEIGHT']} ({$shadow_database['DB_HOST']})", is_resource($dbh));
            testo("Select SHADOW database {$k} @ {$shadow_database['DB_WEIGHT']} ({$shadow_database['DB_NAME']})", @mysql_select_db($shadow_database['DB_NAME'], $dbh));
            unset ($dbh);
        }
    }
    elseif (defined('SHADOW_DB_NAME')) {
        $dbh = @mysql_connect(SHADOW_DB_HOST.':'.SHADOW_DB_PORT,SHADOW_DB_USER,SHADOW_DB_PASS);
        testo('Connect to SHADOW database ('.SHADOW_DB_HOST.')', is_resource($dbh));
        testo('Select SHADOW database ('.SHADOW_DB_NAME.')', @mysql_select_db(SHADOW_DB_NAME, $dbh));
        unset ($dbh);
    }

// Check Memcache
    testo('Memcache is installed', class_exists('Memcache'));
    testo('Memcache is configured', (is_array($memcache_config) && !empty($memcache_config)));

    if (class_exists('Memcache')) {
        $_memcache = new Memcache();

        $total = 0;
        foreach ($memcache_config as $host=>$options) {
            testo("Memcache server ({$host}) is responding", $_memcache->addServer($host, $options['port'], $options['persistent'], $options['weight'], $options['timeout'], $options['retry_interval']));
            $total++;
        }

        $_memcache->close();

        testo("At least 2 memcache servers? ({$total})", ($total>=2));
    }

// Check Zeus
    if (is_array($zxtm_config)) {
        testo('ZXTM connection is configured', !empty($zxtm_config['wsdl_uri']));

        if (is_array($zxtm_config) && !empty($zxtm_config['wsdl_uri'])) {
            try {
                $zxtm_config['wsdl_module'] = 'System.Cache.wsdl';
                $client = new moz_zxtmapi($zxtm_config);
                testo("Connect to Zeus", true);
                unset($client);
            } catch (Exception $e) {
                testo("Connect to Zeus: {$e->getMessage()}", false);
            }
        }
    }


// Print out all our results
    foreach ($results as $result) {

        if ($result['result'] === 'FAILED') {
            echo "<b style=\"color:red;\">{$result['message']}: {$result['result']}</b><br />\n";
        } else {
            echo "{$result['message']}: {$result['result']}<br />\n";
        }

    }

    echo '<hr />';
    echo '<p>What are we actually testing? <a href="http://viewvc.svn.mozilla.org/vc/addons/trunk/site/app/webroot/services/monitor.php?view=markup">Check the source</a>';
    echo '<p>Want more tests? <a href="/tests/">Hit the test suite</a></p>';


// Functions
    /**
     * To use as a general message function, pass two strings
     * To use to trigger errors, pass a message and a boolean
     */
    function testo($message, $result) {
        global $results;

        // If they passed in a boolean, we convert it to a string
        if (is_bool($result)) {
            $result = ($result ? 'success' : 'FAILED');
        }

        $results[] = array( 'message' => $message, 'result'  => $result );

        if ($result === 'FAILED') {
            header("HTTP/1.0 500 Internal Server Error");
        }
    }

?>
