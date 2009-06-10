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
 * CONFIG
 *
 * Require site config.
 */
require_once(dirname(__FILE__).'/../../config/config.php');
require_once(dirname(__FILE__).'/../../config/constants.php');



/**
 *  VARIABLES
 *
 *  Initialize, set up and clean variables.
 */

// Required variables that we need to run the script.
$required_vars = array('reqVersion',    // Used as a marker for the current URI scheme, in case it changes later.
                       'appGuid',       // GUID of the client requesting the blocklist.
                       'appVersion');   // Version of the client requesting the blocklist (not used).

// Debug flag.
$debug = isset($_GET['debug']) ? true : false;

// Test flag.
$test = isset($_GET['test']) ? true : false;

// Array to hold errors for debugging.
$errors = array();

// Iterate through required variables, and escape/assign them as necessary.
foreach ($required_vars as $var) {
    if (empty($_GET[$var])) {
        $errors[] = 'Required variable '.$var.' not set.'; // set debug error
    }
}



// If we have all of our data, clean it up for our queries.
if (empty($errors)) {

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

    // Otherwise, we're going to use SHADOW (our read-only db server).
    } else {
        $dbh = @mysql_connect(SHADOW_DB_HOST.':'.SHADOW_DB_PORT,SHADOW_DB_USER,SHADOW_DB_PASS);

        if (!is_resource($dbh)) {
            $errors[] = 'MySQL connection to SHADOW DB failed.';
        } elseif (!@mysql_select_db(SHADOW_DB_NAME, $dbh)) {
            $errors[] = 'Could not select SHADOW database '.SHADOW_DB_NAME.'.';
        }
    }

    // Iterate through required variables, and escape/assign them as necessary.
    foreach ($required_vars as $var) {
        $sql[$var] = mysql_real_escape_string($_GET[$var]);
    }

    /*
     *  QUERIES  
     *  
     *  All of our variables are cleaned.
     *  Retrieve extension and plugin blocklist data (two queries).
     */ 
    $blitems_q = "
        SELECT 
            blitems.id as itemId,
            blitems.guid as itemGuid,
            blitems.min as itemMin,
            blitems.max as itemMax,
            blitems.os as os,
            blapps.id as appId,
            blapps.blitem_id as appItemId,
            blapps.guid as appGuid,
            blapps.min as appMin,
            blapps.max as appMax
        FROM 
            blitems
        LEFT JOIN blapps on blitems.id = blapps.blitem_id
        WHERE
            blapps.guid = '{$sql['appGuid']}'
            OR blapps.guid IS NULL
        ORDER BY
            itemGuid, appGuid, itemMin, appMin
    ";

    $blitems_r = mysql_query($blitems_q);

    if (!$blitems_r) {
        $errors[] = 'MySQL query for blocklisted extensions failed.'; 
    }

    // If we have blocklisted extensions, arrange data for rendering.
    if (mysql_num_rows($blitems_r)==0) {
        $errors[] = 'No extensions are blocklisted for given application GUID and Version.'; 
    } else {
        // Array used for storing extensions stuff.
        $blocklist = array();

        // Array used for storing emItem metadata.
        $blocklistMeta = array();

        while ($row = mysql_fetch_array($blitems_r, MYSQL_ASSOC)) {

            // Escape data results for display, which is the only place it goes.
            foreach ($row as $key=>$val) {
                $row[$key] = htmlentities($val, ENT_QUOTES, 'UTF-8');
            }

            // Store any per-emItem meta here (right now `os` is the only
            // thing).  Only do so if it hasn't been stored yet.
            if (!empty($row['os']) && empty($blocklistMeta[$row['itemGuid']]['os'])) {
                $blocklistMeta[$row['itemGuid']]['os'] = $row['os']; 
            }

            // If we have item itemMin/itemMax values or an appId possible ranges, we create
            // hashes for each itemId and its related range.
            // 
            // Since itemGuids can have different itemIds, they are the first hash.  Each
            // itemId is effectively an item's versionRange.  For each one of these we create
            // a corresponding array containing the range values, which could be NULL.
            if (!empty($row['itemMin']) && !empty($row['itemMax']) || !empty($row['appItemId'])) {
                $blocklist['items'][$row['itemGuid']][$row['itemId']] = array(
                    'itemMin' => $row['itemMin'],
                    'itemMax' => $row['itemMax']
                );

            // Otherwise, our items array only contains a top-level containing the itemGuid.
            //
            // Doing so tells our template to terminate the item with /> because there is
            // nothing left to display.
            } else {
                $blocklist['items'][$row['itemGuid']] = null;
            }

            // If we retrieved non-null blapp data, store it in the apps array.
            //
            // These are referenced later by their foreign key relationship to items (appItemId).
            if ($row['appItemId']) {
                $blocklist['apps'][$row['itemGuid']][$row['appItemId']][$row['appGuid']][] = array(
                    'appMin' => $row['appMin'],
                    'appMax' => $row['appMax']
                );
            }
        }
    }

    $blplugins_q = "
        SELECT 
            p.name,
            p.description,
            p.filename,
            p.min,
            p.max,
            p.os,
            p.xpcomabi
        FROM 
            blplugins p
        WHERE
            (p.guid = '{$sql['appGuid']}' OR p.guid IS NULL)
    ";

    $blplugins_r = mysql_query($blplugins_q);

    if (!$blplugins_r) {
        $errors[] = 'MySQL query for blocklisted plugins failed.'; 
    }

    // If we have blocklisted plugins, arrange data for rendering.
    if (mysql_num_rows($blplugins_r)==0) {
        $errors[] = 'No plugins are blocklisted for given application GUID and Version.'; 
    } else {

        // Dummy object so we can include the component gracefully.
        class Object { }

        // Require version compare component from AMO.
        require_once(dirname(__FILE__).'/../../controllers/components/versioncompare.php');

        // Array used for storing plugins stuff.
        $pluginItems = array();

        while ($row = mysql_fetch_array($blplugins_r, MYSQL_ASSOC)) {

            // Escape data results for display, which is the only place it goes.
            foreach ($row as $key=>$val) {
                $row[$key] = htmlentities($val, ENT_QUOTES, 'UTF-8');
            }

            // Request version 2 and lower ignores targetApplication entries for plugins
            if ($sql['reqVersion'] <= 2) {
                // Assign pluginItems to blocklist array if the client version is
                // within the version range for the plugin item entry.  If the
                // version isn't within the range, we don't want to block that
                // plugin.
                $v = new  VersioncompareComponent();
                if ($v->versionBetween($sql['appVersion'],$row['min'],$row['max'])) {
                    $pluginItems[] = $row;
                }
            } else {
                $pluginItems[] = $row;
            }
        }
    }
} 



/**
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
if ($debug) {
    echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
    echo '<html lang="en">';

    echo '<head>';
    echo '<title>blocklist.php Debug Information</title>';
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
        echo $query;
        echo '</pre>';
    }

    if (!empty($blocklist)) {
        echo '<h1>Result - blocklist</h1>';
        echo '<pre>';
        print_r($blocklist);
        echo '</pre>';
    }

    if (!empty($pluginItems)) {
        echo '<h1>Result - pluginItems</h1>';
        echo '<pre>';
        print_r($pluginItems);
        echo '</pre>';
    }


    if (!empty($errors) && is_array($errors)) {
        echo '<h1>Errors Found</h1>';
        echo '<pre>';
        print_r($errors);
        echo '</pre>';
    } else {
        echo '<h1>No Errors Found</h1>';
    }

    echo '</body>';

    echo '</html>';
    exit;
}



/**
 * XML
 *
 * Form up our XML output and deliver it to the user.
 * If we get here, we have to deliver a parsable RDF.
 */
header('Content-type: text/xml');
echo "<?xml version=\"1.0\"?>\n";
echo "<blocklist xmlns=\"http://www.mozilla.org/2006/addons-blocklist\">\n";

if (!empty($blocklist) && is_array($blocklist)) {
    echo "  <emItems>\n";

    // Iterate through our items, but only if we have some.
    foreach ($blocklist['items'] as $itemGuid=>$item) {

        $itemOs = null;
        
        // Show the os info if it exists for this item.
        if (!empty($blocklistMeta[$itemGuid]['os'])) {
            $itemOs = " os=\"{$blocklistMeta[$itemGuid]['os']}\"";
        }

        // If we get an array, there's more to it than just the item GUID.  We'll have to pull it out.
        if (!empty($item) && is_array($item)) {

            echo "    <emItem id=\"{$itemGuid}\"{$itemOs}>\n";

            // Each item has version ranges -- what versions of that item are blocked.
            foreach ($item as $itemId=>$itemRange) {

                // If there is app-specific info, leave the versionRange open.
                if (!empty($blocklist['apps'][$itemGuid][$itemId])) {    
                    echo "      <versionRange";

                    // Display the min/max only if they exist.
                    if ($itemRange['itemMin'] && $itemRange['itemMax']) {
                        echo " minVersion=\"{$itemRange['itemMin']}\" maxVersion=\"{$itemRange['itemMax']}\"";
                    }
                    echo ">\n";

                    // Show each application and their version ranges.
                    foreach ($blocklist['apps'][$itemGuid][$itemId] as $appGuid=>$app) {
                        echo "        <targetApplication";

                        // Only show the appGuid if it exists.  If it doesn't, it means the add-on was banned
                        // for all possible applications.
                        if (!empty($appGuid)) {
                            echo " id=\"{$appGuid}\"";
                        }
                        echo ">\n";

                        // Show app version ranges that will be blocked.
                        foreach ($app as $appRange) {
                            if (!empty($appRange['appMin']) && !empty($appRange['appMax'])) {
                            echo "           <versionRange minVersion=\"{$appRange['appMin']}\" maxVersion=\"{$appRange['appMax']}\"/>\n"; 
                            }
                        }
                        echo "        </targetApplication>\n";
                    }
                    echo "      </versionRange>\n";

                // If there is no app-specific info, close it off and we're on to the next emItem.
                } else {
                    echo "      <versionRange";
                    if ($itemRange['itemMin'] && $itemRange['itemMax']) {
                        echo " minVersion=\"{$itemRange['itemMin']}\" maxVersion=\"{$itemRange['itemMax']}\"";
                    }
                    echo "/>\n";
                }
            }
            echo "    </emItem>\n";

        // If we get a string, then we have a uber-blocked add-on guid.  Show it and terminate the element.
        } else {
            echo "    <emItem id=\"{$itemGuid}\"{$itemOs}/>\n";
        }
    }
    echo "  </emItems>\n";
}

if (!empty($pluginItems) && is_array($pluginItems)) {
    echo "<pluginItems>\n";
    foreach ($pluginItems as $pluginItem) {
        $pluginOs = null;
        $pluginAbi = null;

        if (!empty($pluginItem['os'])) {
            $pluginOs = " os=\"{$pluginItem['os']}\"";
        }

        if (!empty($pluginItem['xpcomabi'])) {
            $pluginAbi = " xpcomabi=\"{$pluginItem['xpcomabi']}\"";
        }
        echo "  <pluginItem{$pluginOs}{$pluginAbi}>\n";

        if (!empty($pluginItem['name'])) {
            echo "    <match name=\"name\" exp=\"{$pluginItem['name']}\"/>\n";
        }

        if (!empty($pluginItem['description'])) {
            echo "    <match name=\"description\" exp=\"{$pluginItem['description']}\"/>\n";
        }

        if (!empty($pluginItem['filename'])) {
            echo "    <match name=\"filename\" exp=\"{$pluginItem['filename']}\"/>\n";
        }

        if ($sql['reqVersion'] > 2) {
            echo "    <versionRange>\n";
            echo "      <targetApplication id=\"{$sql['appGuid']}\">\n";
            echo "        <versionRange minVersion=\"{$pluginItem['min']}\" maxVersion=\"{$pluginItem['max']}\"/>\n";
            echo "      </targetApplication>\n";
            echo "    </versionRange>\n";
        }

        echo "  </pluginItem>\n";
    }
    echo "</pluginItems>\n";
}

echo "</blocklist>\n";
exit;
?>
