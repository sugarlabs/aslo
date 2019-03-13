<?php
/* bernie: this file must not be world readable! */
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
 *   Wil Clouser <wclouser@mozilla.com> (Original Author)
 *   Mike Morgan <morgamic@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
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
 * Global configuration document.
 *
 * This document covers both the CakePHP based site (/site) and its service scripts.
 * Unless otherwise noted, trailing slashes should not be used.
 * @package amo
 */

/**
 * Site URL
 * Example: http://addons.mozilla.org
 * Example: http://khan-vm.mozilla.org (dev)
 * Example of getting a full controller url:
 *     echo SITE_URL.$html->url('/users/register');
 * Will default to http://addons.mozilla.org unless defined below
 */
define('SITE_URL', 'http://activities.sugarlabs.org');

/**
 * Services URL.
 * Example: http://addons.mozilla.org/services
 * Example: http://khan-vm.mozilla.org/amo/services (dev)
 */
define('SERVICE_URL','http://activities.sugarlabs.org');

/**
 * Site State
 * Example: production
 * Example: staging
 * Example: dev
 * All uses should default to dev
 */
define('SITE_STATE', 'dev');
 
/**
 * Files
 * The application uses these paths to piece together the URL for files.
 *
 * HOST and URL are separated because CakePHP has $html->webroot, and only appends
 * FILES_URL while the services need both since it doesn't have
 * Cake's context.
 *
 * No trailing slashes.
 */

/**
 * Host, including http://.  Should be everything leading up to addon ids.
 * Example: http://releases.mozilla.org/addons
  */
//define('FILES_HOST', 'http://download.sugarlabs.org/activities');       
define('FILES_HOST', 'http://activities.sugarlabs.org/activities');       

/**
 * Relative web path of the files directory.  Tacking this on to FILES_HOST should get you
 * the complete URL where your files are. This is the location of the downloads controller
 * and it should not normally need to be changed, the default is set in constants.php,
 * and shown commented out here.
 *
 * Example: downloads/file
 */
#define('FILES_URL', 'downloads');

/**
 * File path for storing XPI/JAR files (or any files associated with an add-on).
 * Example: /data/www/app/webroot/files
 */
define('REPO_PATH', '/var/www/files');

/**
 * File path for storing public files to be rsynced for updates
 * If left commented out, files will not be copied there and will use only REPO_PATH
 */
//define('PUBLIC_STAGING_PATH', '/srv/download/activities');

/**
 * The path to the gnu diff program (or any diff program able to create a unified diff).
 * If left commented out, it will use the xdiff package
 */
//define('DIFF_PATH', '/usr/bin/diff');

/**
 * This is the number of seconds for which repeat downloads will not
 * be counted, since Firefox does multiple gets during a single install process.
*/
define('DL_COUNT_DELAY', '10');

/**
 * Path to directory where detailed logfiles are kept. Files will be created in
 * this directory in the format: {DETAILED_LOG_PATH}/Y-M-D.txt
 */
//define('DETAILED_LOG_PATH', '');

/**
 * Path to directory for misc. AMO storage accessible by all webheads.
 */
define('NETAPP_STORAGE', '');

/**
 * Facebook Configuration
 */
// Whether the Facebook controller is enabled
define('FB_ENABLED', 'false');

// Facebook API keys
define('FB_API_KEY', '');
define('FB_API_SECRET', '');

// Facebook App URL
define('FB_URL', 'http://apps.facebook.com/add-ons');

// Facebook Image site - where images are pulled from
define('FB_IMAGE_SITE', 'https://addons.mozilla.org');

// Facebook Install site - where the add-on install page goes
define('FB_INSTALL_SITE', 'https://addons.mozilla.org');

// Facebook Bounce Percentage - percent of hits to bounce
//define('FB_BOUNCE_PERCENTAGE', 0);

/**
 * Database configuration.
 */

/**
 * DB_USER, DB_PASS, DB_NAME, DB_HOST, DB_PORT
 * This database has read/write capabilities.  Host and port default to localhost and 3306.
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER','remora');
define('DB_PASS','remora');
define('DB_NAME','remora');

/**
 * SHADOW_DB_USER, SHADOW_DB_PASS, SHADOW_DB_NAME, SHADOW_DB_HOST, SHADOW_DB_PORT
 * Array of shadow databases that have read-only access.
 * - If left alone, will default to DB_* above.
 * - DB_WEIGHTs must sum to 1. i.e., a weight of 0 will never get hit, a weight
 *   of .50 will get hit half of the time, and a weight of 1 will always get hit.
 * - The array keys need not be numeric and could be used for descriptive purposes
 *   that would appear in the monitor script.
 */
global $shadow_databases;
$shadow_databases = array(
    0 => array(
        'DB_HOST' => '',
        'DB_PORT' => 3306,
        'DB_NAME' => '',
        'DB_USER' => '',
        'DB_PASS' => '',
        'DB_WEIGHT' => 0
    )
);

/**
 * TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_HOST, TEST_DB_PORT
 * The test database.  All fields default to their DB_* counterparts, except
 * for TEST_DB_NAME, which is DB_NAME . "-test".
 */

/**
 * memcache configuration.
 * 
 * The memcache_config array lists all possible memcached servers to use in case the default server does not have the appropriate key.
 */
global $memcache_config;
$memcache_config = array(
    '127.0.0.1' => array(
       'port' => '11211',
       'persistent' => true,
       'weight' => '1',
       'timeout' => '1',
       'retry_interval' => 15
    )
);

/**
 * Recaptcha (recaptcha.net) configuration
 */
define('RECAPTCHA_ENABLED', false);
define('RECAPTCHA_PRIVATE_KEY', '...');
define('RECAPTCHA_PUBLIC_KEY', '...');

/**
 * Compatibility Center Versions
 */
define('COMPAT_DEFAULT_VERSION', '3.5');
global $compatibility_versions;
$compatibility_versions = array(
    '3.0', '3.5', '3.6'
);
// Consider all the mapped versions to be the same as the key.
global $version_aliases;
$version_aliases = array(
    '3.5' => array('3.1', '3.5')
);

define('SITE_WIKI', 'http://wiki.sugarlabs.org/go/Activity_Library');
define('SITE_EDITOR_WIKI', 'http://wiki.sugarlabs.org/go/Activity_Library/Editors');
define('SITE_DEVELOPER_WIKI', 'http://wiki.sugarlabs.org/go/Activity_Library/Website_Developers');
define('SITE_ABOUT', 'http://wiki.sugarlabs.org/go/Activity_Library/About');
define('SITE_FAQ', 'http://wiki.sugarlabs.org/go/Activity_Library/FAQ');
define('SITE_BLOG', 'http://planet.sugarlabs.org/');
define('SITE_IRC', '#sugar on chat.freenode.net');
define('SITE_CONTACT', 'http://wiki.sugarlabs.org/go/Service/activities#Administrative_contact');

global $SITE_RELEASE_EMAIL;
$SITE_RELEASE_EMAIL = array(
    'en_US' => array('email'    => 'sugar-devel@lists.sugarlabs.org',
                     'subject'  => '[ASLO] Release %s-%s',
                     'template' => 'release_en'),
    'es_ES' => array('email'   => 'olpc-sur@lists.laptop.org',
                     'subject' => '[ASLO] Nueva version %s-%s',
                     'template' => 'release_es'));

define('ADMIN_EMAIL', 'activities@sugarlabs.org');
define('EDITOR_EMAIL', 'aslo@lists.sugarlabs.org');
define('NOBODY_EMAIL', 'activities@sugarlabs.org');
define('SITE_NAME', 'Sugar Labs Activities');
define('SITE_MIME', 'application/vnd.olpc-sugar'); // application/x-xpinstall
define('SITE_ORG', 'Sugar Labs'); // Mozilla
define('SSITE_URL', 'https://activities.sugarlabs.org');
define('HELP_IRC', '#Sugar on irc.freenode.nt');
define('SITE_SUGAR_STABLE', '0.113');

define('SITE_APP', 1); // 1

define('ADDON_ACTIVITY', '1');
define('ADDON_CONTENT', '2');
?>
