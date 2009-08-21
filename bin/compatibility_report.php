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
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Justin Scott <fligtar@mozilla.com> (Original Author)
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

// Prevent running from the web
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

// Include class files
require_once('database.class.php');
require_once('../site/app/controllers/components/versioncompare.php');

// for VersionCompare
class Object {}

$db = new Database();
$versioncompare = new VersioncompareComponent();

/**
 * Returns the SQL where-clause to find versions (and aliases) compatible
 * with $compatibility_version.
 */
function compatibleVersions($compatibility_version) {
    global $version_aliases, $versioncompare;
    $aliases = $versioncompare->_versionAlias($compatibility_version, $version_aliases);
    $compat_versions = array();
    foreach ($aliases as $alias) {
        $compat_versions[] = "version like '{$alias}%'";
    }
    return implode(' OR ', $compat_versions);
}

// Get latest update pings date
$date_qry = $db->read("SELECT date FROM update_counts ORDER BY date DESC LIMIT 1");
$date_array = mysql_fetch_assoc($date_qry);
$latest_date = $date_array['date'];

// Get all add-ons with Firefox compatibility ordered by active users descending
$addon_qry = $db->read("
            SELECT
                addons.id,
                translations.localized_string AS name,
                versions.version,
                appversions.version AS maxversion,
                update_counts.count AS updatepings,
                IF(features.id IS NULL, '0', '1') AS featured
            FROM update_counts
                INNER JOIN addons ON addons.id = update_counts.addon_id
                INNER JOIN versions ON versions.addon_id = addons.id
                INNER JOIN applications_versions ON applications_versions.version_id = versions.id
                INNER JOIN translations ON addons.name = translations.id
                INNER JOIN appversions ON applications_versions.max = appversions.id
                LEFT JOIN features ON addons.id = features.addon_id
            WHERE
                update_counts.date = '{$latest_date}' AND
                applications_versions.application_id = 1 AND
                translations.locale = 'en-US' AND
                versions.id = (
                    SELECT id FROM versions WHERE addon_id = addons.id ORDER BY created DESC LIMIT 1
                )
            GROUP BY
                addons.id
            ORDER BY
                update_counts.count DESC
        ");

$all_addons = array();
// Sum all update pings to establish total active users
while ($addon = mysql_fetch_assoc($addon_qry)) {
    $all_addons[] = $addon;
}

// set in site/app/config/config.php
global $compatibility_versions;

// Previous version defaults to 2.0 because 3.0 is the first compat version we have available
$previous_version = '2.0';

/**
 * iterate through each major compatibility version and make an individual
 * report based on above general info
 */
foreach ($compatibility_versions as $compatibility_version) {
    $compat_addons = array();
    $adu_total = 0;
    foreach ($all_addons as $addon) {
        // Only count this add-on if it is compatible with the major/minor release before
        if (!empty($previous_version) &&
            $versioncompare->compareVersions($addon['maxversion'], $previous_version) < 0)
            continue;

        $compat_addons[] = $addon;
        $adu_total += $addon['updatepings'];
    }

    $adu_top95 = floor($adu_total * .95);

    $totals = array(
        COMPAT_LATEST => array(
            'count' => 0,
            'adu' => 0
        ),
        COMPAT_BETA => array(
            'count' => 0,
            'adu' => 0
        ),
        COMPAT_ALPHA => array(
            'count' => 0,
            'adu' => 0
        ),
        COMPAT_OTHER => array(
            'count' => 0,
            'adu' => 0
        )
    );

    $versions_qry = $db->read("SELECT id, version
                                FROM appversions
                                WHERE application_id = ".APP_FIREFOX." AND
                                      (".compatibleVersions($compatibility_version).")
                                ORDER BY version");
    $versions = array();
    while ($version = mysql_fetch_assoc($versions_qry)) {
        $versions[$version['id']] = $version['version'];
    }

    $appversions = $versioncompare->getCompatibilityGrades($compatibility_version, $versions);


    $adu_counter = 0;
    $addons = array();

    // Iterate through each add-on
    foreach ($compat_addons as $addon) {
        // Only include add-ons that make up the top 95%
        if ($adu_counter >= $adu_top95) break;

        $classification = $versioncompare->gradeCompatibility($addon['maxversion'], $compatibility_version, $appversions);

        $totals[$classification]['count']++;
        $totals[$classification]['adu'] += $addon['updatepings'];

        $addons[] = array(
            'id' => $addon['id'],
            'name' => $addon['name'],
            'maxversion' => $addon['maxversion'],
            'featured' => $addon['featured'],
            'percentage' => ($addon['updatepings'] / $adu_top95),
            'classification' => $classification
        );

        $adu_counter += $addon['updatepings'];
    }

    $totals['adu95'] = $adu_top95;
    $totals['adu'] = $adu_total;
    $totals['addons95'] = count($addons);
    $totals['addons'] = mysql_num_rows($addon_qry);

    $output = array(
        'addons' => $addons,
        'totals' => $totals,
        'appversions' => $appversions
    );

    if (CRON_DEBUG) {
        echo "Report for Firefox {$compatibility_version}\n";
        echo "Using data from {$latest_date}\n";
        echo "{$totals['adu']} total active users; {$totals['adu95']} making up top 95%\n";
        echo "{$totals['addons']} rows returned; {$totals['addons95']} addons counted\n\n";

        print_r($output);
    }

    file_put_contents(NETAPP_STORAGE.'/compatibility-fx-'.$compatibility_version.'.serialized', serialize($output));

    $previous_version = $compatibility_version;
}

?>
