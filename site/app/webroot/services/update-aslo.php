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
 *   Mike Morgan <morgamic@mozilla.com>
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
 * VersionCheck.php is a dynamic RDF that compares version information for
 * extensions and determines whether or not an update is needed.  If an update
 * is needed, the correct update file is referenced based on the AMO database
 * and repository.  The script is set to die silently instead of echoing errors
 * clients don't use anyway.  For testing, if you would like to debug, supply
 * the script with ?debug=true
 *
 * @package amo 
 * @subpackage pub
 */


/**
 * CONFIG
 *
 * Require site config.
 */
require_once('../../config/config.php');
require_once('../../config/config-local.php');
require_once('../../config/constants.php');
require_once('./functions.php');
require_once('../../../vendors/sphinx/addonsSearch.php');

/**
 *  VARIABLES
 *
 *  Initialize, set up and clean variables.
 */

// Required variables that we need to run the script.
$required_vars = array('id');
$optional_vars = array('appVersion', 'experimental');

// Mapping of addontypes to addontype_id.
// These are used in the urn, and should not be localized.
// We are using lower-case strings to be consistent with currently installed add-ons.
$addontypes = array(
    ADDON_EXTENSION => 'extension',
    ADDON_THEME => 'theme',
    ADDON_DICT => 'extension',
    ADDON_SEARCH => 'search',
    ADDON_LPAPP => 'item',
    ADDON_LPADDON => 'extension',
    ADDON_PLUGIN => 'plugin'
);

// Debug flag.
$debug = isset($_GET['debug']) ? true : false;

// Test flag.
$test = isset($_GET['test']) ? true : false;

// Array to hold errors for debugging.
$errors = array();

// Set OS.  get_os_id() can only return an int.
$sql['os_id'] = get_os_id();

// Iterate through required variables, and escape/assign them as necessary.
foreach ($required_vars as $var) {
    if (empty($_GET[$var])) {
        $errors[] = 'Required variable '.$var.' not set.'; // set debug error
    }
}

// Determine if we're detecting installed add-ons for a Facebook user
$detect_installed = !empty($_COOKIE['AMOfbUser']);

/**
 * DATABASE
 *
 * Connect to and select proper database.  By default the update script uses SHADOW.
 *
 * In order for testing to work, we can add a query variable specifying that the incoming request is for testing only.
 */

// Are we trying to run a test?  If so, use the test db.
if ($test) {
    $dbh = @mysql_connect(TEST_DB_HOST.':'.TEST_DB_PORT,TEST_DB_USER,TEST_DB_PASS);

    if (!is_resource($dbh)) {
        $errors[] = 'MySQL connection to TEST DB failed.';
    } elseif (!@mysql_select_db(TEST_DB_NAME, $dbh)) {
        $errors[] = 'Could not select TEST database '.TEST_DB_NAME.'.';
    }

// If we're trying to detect installed add-ons, we need write access
} elseif ($detect_installed) {
    $dbh = @mysql_connect(DB_HOST.':'.DB_PORT,DB_USER,DB_PASS);

    if (!is_resource($dbh)) {
        $errors[] = 'MySQL connection to DB failed.';
    } elseif (!@mysql_select_db(DB_NAME, $dbh)) {
        $errors[] = 'Could not select database '.DB_NAME.'.';
    }

// Otherwise, we're going to use SHADOW (our read-only db server).
} else {
    $dbh = @mysql_connect(SHADOW_DB_HOST.':'.SHADOW_DB_PORT,SHADOW_DB_USER,SHADOW_DB_PASS);

    if (!is_resource($dbh)) {
        $errors[] = 'MySQL connection to SHADOW DB failed.';
    } elseif (!@mysql_select_db(SHADOW_DB_NAME, $dbh)) {
        $errors[] = 'Could not select SHADOW database '.SHADOW_DB_NAME.'.';
    }
}



/*
 *  QUERIES  
 *  
 *  Our variables are there and we're connected to the database.
 *  Now we can format our data for SQL then attempt to retrieve update information.
 */ 
if (empty($errors) && !$detect_installed) {

    // Iterate through required variables, and escape/assign them as necessary.
    foreach ($required_vars as $var) {
        $sql[$var] = mysql_real_escape_string($_GET[$var]);
    }

    foreach ($optional_vars as $var) {
        if (isset($_GET[$var])) {
            $sql[$var] = mysql_real_escape_string($_GET[$var]);
        }
    }

    // we are sugar
    $sql['appID'] = '{3ca105e0-2280-4897-99a0-c277d1b733d2}';
    
    /**
     * Determine whether the add-on is hosted on AMO and if so, if it's public
     * or in the sandbox.
     */
    
    $where = '';
    if (!isset($sql['experimental']) || $sql['experimental'] == '0' || $sql['experimental'] == '') {
        $where .= ' AND status = '.STATUS_PUBLIC;
    }   

    $id_query = "
        SELECT
            id,
            status
        FROM
            addons
        WHERE
            guid = '{$sql['id']}' {$where} AND
            inactive = 0
        LIMIT 1
    ";
    
    $id_res = mysql_query($id_query);
    
    if (!$id_res) {
        $errors[] = 'Add-on GUID not found in database.';
    }
    else {
        // Add-on GUID was found in the db
        $addon = mysql_fetch_array($id_res, MYSQL_ASSOC);

        $where = 'WHERE TRUE';
        
        if (!isset($sql['experimental']) || $sql['experimental'] == '0' || $sql['experimental'] == '') {
            // If public, we only pull public files
            $where .= ' AND files.status = '.STATUS_PUBLIC;
        }

        if (isset($sql['appVersion'])) {
            $version_int = AddonsSearch::convert_version($sql['appVersion']);
            $where .= " AND {$version_int} >= appmin.version_int AND {$version_int} <= appmax.version_int";
        }
               
        $os_query = ($sql['os_id']) ? " OR files.platform_id = {$sql['os_id']}" : '';  // Set up os_id.
    
        // Query for possible updates.
        //
        // The query sorts by version.vid, which is an auto_increment primary key for that table.
        $query = "
            SELECT
                addons.guid as guid,
                addons.id as id,
                addons.addontype_id as type,
                applications.guid as appguid,
                appmin.version as min,
                appmax.version as max,
                files.id as file_id,
                files.hash,
                files.filename,
                files.size,
                versions.id as version_id,
                versions.releasenotes,
                versions.version as version
            FROM
                versions 
            INNER JOIN addons ON addons.id = versions.addon_id AND addons.id = {$addon['id']} 
            INNER JOIN applications_versions ON applications_versions.version_id = versions.id 
            INNER JOIN applications ON applications_versions.application_id = applications.id  AND applications.guid = '{$sql['appID']}'       
            INNER JOIN appversions appmin ON appmin.id = applications_versions.min
            INNER JOIN appversions appmax ON appmax.id = applications_versions.max  
            INNER JOIN files ON files.version_id = versions.id
            {$where}
            ORDER BY
                CAST(versions.version AS DECIMAL) DESC
            LIMIT 1 
        ";

        $res = mysql_query($query);
        
        if (!$res) {
            // Possibly failed because version does not exist on AMO
            $errors[] = 'MySQL query for update information failed.';
        } else {
            $data = mysql_fetch_array($res,MYSQL_ASSOC);
            
            if (!empty($data['file_id'])) {
                /**
                 * Note that if you're in a dev environment you'll want to set FILE_HOST to your local machine.
                 * I did not want to include logic here for DEV or not DEV mostly because this script doesn't
                 * need any extra crap in it.
                 *
                 * If you want to test updates, change your config and set up a web dir with public files in it.
                 */
                if (defined('FILES_HOST'))
                    $data['uri'] = FILES_HOST . '/' . $data['id'] . '/' . $data['filename'];
                else
                    $data['uri'] = SITE_URL . '/downloads/file/' . $data['file_id'] . '/' . $data['filename'];
            }
    
            if (!empty($data['type'])) {
                $data['type'] = $addontypes[$data['type']];
            }
        }
    }
}
// If we're detecting installed add-ons, record the user id and add-on GUID
// We don't return any updates regardless of their existance when doing this
elseif ($detect_installed) {
    $sequence = mysql_real_escape_string($_COOKIE['AMOfbSequence']);
    $fb_user = mysql_real_escape_string($_COOKIE['AMOfbUser']);
    $addon_guid = mysql_real_escape_string($_GET['id']);
    $disabled = ($_GET['status'] == 'userDisabled') ? 1 : 0;
    
    // Delete any add-ons from a previous detection sequence
    mysql_query("DELETE FROM facebook_detected WHERE fb_user = '{$fb_user}' AND sequence != '{$sequence}'");
    
    $insert = mysql_query("INSERT INTO facebook_detected (fb_user, addon_guid, disabled, sequence, created) VALUES ('{$fb_user}', '{$addon_guid}', '{$disabled}', '{$sequence}', NOW())");
    
    if (!$insert)
        $errors[] = 'MySQL query to log add-on GUID failed.';
    
    // We don't want this cached so that real update checks can function properly
    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private');
    header('Pragma: no-cache');
}


/*
 *  DEBUG
 *
 *  If we get here, something went wrong.  For testing purposes, we can
 *  optionally display errors based on $_GET['debug'].
 *
 *  By default, no errors are ever displayed because humans do not read this
 *  script.
 *
 *  Until there is some sort of API for how clients handle errors, 
 *  things should remain this way.
 */
if (defined('DEV') && $debug == true) {
    echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
    echo '<html lang="en">';

    echo '<head>';
    echo '<title>VersionCheck.php Debug Information</title>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '</head>';

    echo '<body>';

    echo '<h1>Parameters</h1>';
    echo '<pre>';
    $out = array();
    foreach ($_GET as $key=>$val) {
        $out[$key] = strip_tags(htmlentities($val));
    }
    print_r($out);
    echo '</pre>';

    if (!empty($query)) {
        echo '<h1>Query</h1>';
        echo '<pre>';
        echo strip_tags(htmlentities($id_query));
        echo '<br />';
        echo strip_tags(htmlentities($query));
        echo '</pre>';
    }

    if (!empty($data)) {
        echo '<h1>Result</h1>';
        echo '<pre>';
        foreach ($data as $key=>$val) {
            $data_esc[$key] = strip_tags(htmlentities($val));
        }
        print_r($data_esc);
        echo '</pre>';
    }

    if (!empty($errors) && is_array($errors)) {
        echo '<h1>Errors Found</h1>';
        echo '<pre>';
        foreach ($errors as $key=>$val) {
            $errors_esc[$key] = strip_tags(htmlentities($val));
        }
        print_r($errors_esc);
        echo '</pre>';
    } else {
        echo '<h1>No Errors Found</h1>';
    }

    echo '</body>';

    echo '</html>';
    exit;
}



/**
 * OUTPUT
 *
 * Generate our XML output.  We are assuming that we did not have to echo debug information.
 *
 * We will encode using UTF-8 for all update metadata, and display an empty XML document if there were no updates found.
 */
header('Cache-Control: public, max-age=3600');
header('Last-modified: ' . gmdate("D, j M Y H:i:s", time()) . " GMT");
header('Expires: ' . gmdate("D, j M Y H:i:s", time() + 3600) . " GMT");
header('Content-type: text/xml');

echo <<<XMLHEADER
<?xml version="1.0"?>
<RDF:RDF xmlns:RDF="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:em="http://www.mozilla.org/2004/em-rdf#">
XMLHEADER;

// If have our update array, encode it then display the XML for the update.
if (isset($data) && is_array($data) && !empty($data)) {

foreach ($data as $key=>$val) {
    $update[$key]=htmlentities($val,ENT_QUOTES,'UTF-8');
}

$hash = '';
if (!empty($update['hash'])) {
    $hash = "<em:updateHash>{$update['hash']}</em:updateHash>";
}

$updateInfoURL = '';
if (!empty($update['releasenotes'])) {
    /**
     * The locale is included as an additional parameter so that if the Firefox
     * locale is not supported by AMO, there's not a 404
     */
    $updateInfoURL = "<em:updateInfoURL>".SITE_URL."/versions/updateInfo/{$update['version_id']}/%APP_LOCALE%/</em:updateInfoURL>";
}

echo <<<XMLBODY
<RDF:Description about="urn:mozilla:{$update['type']}:{$update['guid']}">
    <em:updates>
        <RDF:Seq>
            <RDF:li resource="urn:mozilla:{$update['type']}:{$update['guid']}:{$update['version']}"/>
        </RDF:Seq>
    </em:updates>
</RDF:Description>

<RDF:Description about="urn:mozilla:{$update['type']}:{$update['guid']}:{$update['version']}">
    <em:version>{$update['version']}</em:version>
    <em:targetApplication>
        <RDF:Description>
            <em:id>{$update['appguid']}</em:id>
            <em:minVersion>{$update['min']}</em:minVersion>
            <em:maxVersion>{$update['max']}</em:maxVersion>
            <em:updateLink>{$update['uri']}</em:updateLink>
            <em:updateSize>{$update['size']}</em:updateSize>
            {$updateInfoURL}
            {$hash}
        </RDF:Description>
    </em:targetApplication>
</RDF:Description>
XMLBODY;

}

echo <<<XMLFOOTER
</RDF:RDF>
XMLFOOTER;
?>
