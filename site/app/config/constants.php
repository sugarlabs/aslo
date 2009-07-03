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
 * Site URL default
 */
if (!defined('SITE_URL'))
    define('SITE_URL', 'https://addons.mozilla.org');

/**
 * Site State default
 */
if (!defined('SITE_STATE'))
    define('SITE_STATE', 'dev');

/**
 * Database config defaults and cascade.
 */
if (!defined('DB_HOST'))
    define('DB_HOST','localhost');
if (!defined('DB_PORT'))
    define('DB_PORT', '3306');

/**
 * File, image, etc defaults
 */
if (!defined('DEFAULT_ADDON_ICON'))
    define('DEFAULT_ADDON_ICON', 'default_icon.png');
if (!defined('DEFAULT_THEME_ICON'))
    define('DEFAULT_THEME_ICON', 'theme.png');

/**
 * Include shadow database selector
 * The shadow db selector is here so that services and bin can benefit
 * from it, although they won't benefit from the downtime detection */
require_once('shadowdb.inc.php');
if (!empty($shadow_databases)) {
    select_shadow_database($shadow_databases);
}

/**
 * SHADOW_DB_USER, SHADOW_DB_PASS, SHADOW_DB_NAME, SHADOW_DB_HOST, SHADOW_DB_PORT
 * The shadow_db has read-only access.  Default to the same as DB_* above.
 */
if (!defined('SHADOW_DB_USER'))
    define('SHADOW_DB_USER', DB_USER);
if (!defined('SHADOW_DB_PASS'))
    define('SHADOW_DB_PASS', DB_PASS);
if (!defined('SHADOW_DB_HOST'))
    define('SHADOW_DB_HOST', DB_HOST);
if (!defined('SHADOW_DB_NAME'))
    define('SHADOW_DB_NAME', DB_NAME);
if (!defined('SHADOW_DB_PORT'))
    define('SHADOW_DB_PORT', DB_PORT);

/**
 * TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME, TEST_DB_HOST, TEST_DB_PORT
 * The test database.  All fields default to their DB_* counterparts, except
 * for TEST_DB_NAME, which is DB_NAME . "-test".
 */
if (!defined('TEST_DB_USER'))
    define('TEST_DB_USER', DB_USER);
if (!defined('TEST_DB_PASS'))
    define('TEST_DB_PASS', DB_PASS);
if (!defined('TEST_DB_HOST'))
    define('TEST_DB_HOST', DB_HOST);
if (!defined('TEST_DB_NAME'))
    define('TEST_DB_NAME', DB_NAME . '-test');
if (!defined('TEST_DB_PORT'))
    define('TEST_DB_PORT', DB_PORT);

// Settings for query caching
if (!defined('QUERY_CACHE'))
    define('QUERY_CACHE', true); // are we caching queries?
if (!defined('CACHE_PAGES_FOR'))
    define('CACHE_PAGES_FOR', 60); // seconds

// This string will be prepended to all memcache keys.
if (!defined('MEMCACHE_PREFIX'))
    define('MEMCACHE_PREFIX', 'AMO_');

/**
 * Downloads
 */
// This is the url to the download controller
if (!defined('FILES_URL'))
    define('FILES_URL', 'downloads/file');

// This is the table to use in the downloads controller.  We put this in to
// temporarily redirect downloads in the event where we have to alter the
// downloads table.  The table defined in config is a tmp table so we don't 
// lose data.  If it's not there, we set the default here.
if (!defined('DOWNLOADS_TABLE'))
    define('DOWNLOADS_TABLE','downloads');

// Amount of time in minutes that is waited until files that were just made
// public are served from the download (mirror) location rather than straight
// from the application. This allows for mirror propagation time.
if (!defined('MIRROR_DELAY'))
    define('MIRROR_DELAY', 30);

if (!defined('DISABLE_AMO'))
    define('DISABLE_AMO', false);

/**
 * Applications
 */
define('APP_FIREFOX', 1);
define('APP_THUNDERBIRD', 18);
define('APP_SEAMONKEY', 59);
define('APP_SUNBIRD', 52);
define('APP_FENNEC', 60);
global $app_shortnames; // shortnames are used in URLs
$app_shortnames = array(
    'firefox'       => APP_FIREFOX,
    'thunderbird'   => APP_THUNDERBIRD,
    'seamonkey'     => APP_SEAMONKEY,
    'sunbird'       => APP_SUNBIRD,
    'fennec'        => APP_FENNEC
    );
global $app_prettynames;
$app_prettynames = array( // Overridden with L10n in bootstrap.php
    'firefox' => "Firefox",
    'thunderbird' => "Thunderbird",
    'seamonkey' => "SeaMonkey",
    'sunbird' => "Sunbird",
    'fennec' => "Fennec"
    );
global $browser_apps; // browser applications; for non-browser apps, use !in_array()
$browser_apps = array(
    APP_FIREFOX,
    APP_SEAMONKEY
);
global $other_layouts; // non-app top-level layouts in URLs
// controller => header
$other_layouts = array(
    'admin' => 'generic',
    'developers' => 'developers',
    'editors' => 'generic',
    'localizers' => 'generic',
    'statistics' => 'developers'
);
global $valid_layouts; // used in URLs, like /en-US/firefox or /en-US/developers
$valid_layouts = array_merge($other_layouts, $app_shortnames);

/**
 * Addontypes
 */
define('ADDON_ANY', '-1'); // default of advanced search
define('ADDON_EXTENSION', '1');
define('ADDON_THEME', '2');
define('ADDON_DICT', '3');
define('ADDON_SEARCH', '4');
define('ADDON_LPAPP', '5');
define('ADDON_LPADDON', '6');
define('ADDON_PLUGIN', '7');
define('ADDON_API', '8'); // not actually a type but used to identify extensions + themes

define('COUNT_ADDON_PLUGIN', 7); // Since the plugin page is static, define a static count here,

/**
 * Add-on Author Roles
 */
define('AUTHOR_ROLE_ADMINOWNER', 7);
define('AUTHOR_ROLE_ADMIN', 6);
define('AUTHOR_ROLE_OWNER', 5);
define('AUTHOR_ROLE_DEV', 4);
define('AUTHOR_ROLE_VIEWER', 1);
define('AUTHOR_ROLE_NONE', 0);

/**
 * Collection Author Roles
 */
define('COLLECTION_ROLE_ADMIN', 1);
define('COLLECTION_ROLE_PUBLISHER', 0);

define('COLLECTOR_ID', 11950);

/* hybrid categories (extensions + other add-on types) */
global $hybrid_categories;
$hybrid_categories = array(
    APP_FIREFOX => array(
        13  => ADDON_SEARCH
    ),
    APP_SEAMONKEY => array(
        47  => ADDON_SEARCH
    )
);

// We could pull these out of the database based on whether or not there are any of a given
// type that are compatible with the application in question, but that would be a lot of slow
// for very little gain.
global $app_listedtypes;
$app_listedtypes = array(
    APP_FIREFOX => array(ADDON_EXTENSION, ADDON_THEME, ADDON_DICT, ADDON_SEARCH, ADDON_PLUGIN),
    APP_THUNDERBIRD => array(ADDON_EXTENSION, ADDON_THEME, ADDON_DICT),
    APP_SEAMONKEY => array(ADDON_EXTENSION, ADDON_THEME, ADDON_DICT, ADDON_SEARCH, ADDON_PLUGIN),
    APP_SUNBIRD => array(ADDON_EXTENSION, ADDON_THEME, ADDON_DICT)
    );

/**
 * Add-on and File Statuses
 */
define('STATUS_NULL', '0');
define('STATUS_SANDBOX', '1');
define('STATUS_PENDING', '2');
define('STATUS_NOMINATED', '3');
define('STATUS_PUBLIC', '4');
define('STATUS_DISABLED', '5');
global $experimental_status, $valid_status;
$experimental_status = array(STATUS_SANDBOX, STATUS_PENDING, STATUS_NOMINATED);
$valid_status = array(STATUS_SANDBOX, STATUS_PENDING, STATUS_NOMINATED, STATUS_PUBLIC);

/**
 * Platforms
 * This was placed here for use mainly in the update service.
 */
define('PLATFORM_ANY', '-1'); //used by advanced search	
define('PLATFORM_ALL', '1');
define('PLATFORM_LINUX', '2');
define('PLATFORM_MAC', '3');
define('PLATFORM_BSD', '4');
define('PLATFORM_WIN', '5');
define('PLATFORM_SUN', '6');

/**
 * Default ACL user.  They have access to nothing.
 */
define('DEFAULT_ACL_USER','nobody@addons.mozilla.org');

/**
 * Regular expressions used in model validations.
 */
// URL address (basic domain name with optional port followed by optional slash anything)
define('VALID_URL_REQ','/^https?:\/\/([a-z0-9][a-z0-9-]*\.)+([a-z]+)(:[0-9]+)?(\/|$)/i');
define('VALID_URL_OPT','/(^$)|(^https?:\/\/([a-z0-9][a-z0-9-]*\.)+([a-z]+)(:[0-9]+)?(\/|$))/i');
// EMAIL optional validator
define('VALID_EMAIL_OPT', '/(^$)|\\A(?:^([a-z0-9][a-z0-9_\\-\\.\\+]*)@([a-z0-9][a-z0-9\\.\\-]{0,63}\\.(com|org|net|biz|info|name|net|pro|aero|coop|museum|[a-z]{2,4}))$)\\z/i');
// UUID required validator
define('VALID_UUID_REQ','/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/');
// regex to preg_replace() the bad characters in uploaded filenames
define('INVALID_FILENAME_CHARS', '/[^\w\d\.\-_!+]/');
// regex to preg_replace() the bad characters in tags.  White space is also stripped separately
// some of these are boolean search operators, others cause problems in URLs.  Be careful if you
// are removing anything from here.
define('INVALID_TAG_CHARS', "/(^[+-])|([\/\\\#\"':&%\*<>~])/");
// invalid collection nicknames
define('INVALID_COLLECTION_NICKNAME_CHARS', INVALID_FILENAME_CHARS);
/* Money: anything from 0.00 to 99.99.  Decimal not required. */
define('VALID_MONEY', '/^(\d{0,2}(\.\d\d)?|\.\d\d)$/');

/**
 * entities to be sanitized by publish()
 */
global $sanitize_patterns, $unsanitize_patterns;
$sanitize_patterns = array(
    'patterns'      => array("/%/u", "/\(/u", "/\)/u", "/\+/u", "/-/u"),
    'replacements'  => array("&#37;", "&#40;", "&#41;", "&#43;", "&#45;")
    );
$unsanitize_patterns = array(
    'patterns'      => array("/\&amp;/u", "/\&#37;/u", "/\&lt;/u", "/\&gt;/u", "/\&quot;/u", "/\&#039;/u", "/\&#40;/u", "/\&#41;/u", "/\&#43;/u", "/\&#45;/u"),
    'replacements'  => array("&", "%", "<", ">", '"', "'", "(", ")", "+", "-")
    );

/**
 * CSS & JS Last Changed Revision values, used to fix issues with long-term caching
 */
if(defined('ROOT')) {
  include_once ROOT.DS.APP_DIR.DS.'config'.DS.'revisions.php';
}

/**
 * Password Reset Expires: the number of days before a resetcode expires.
 */
define('PASSWORD_RESET_EXPIRES', 3);

/**
 * Paypal
 */
define('PAYPAL_API_VERSION', '50');
?>
