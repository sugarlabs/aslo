<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/e
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
 *    Justin Scott <fligtar@mozilla.com> (Original Author)
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
 This controller requires the bin/compatibility_report.php script to be
 run first to create NETAPP_STORAGE/compatibility.serialized. This script
 should be run once an hour to refresh the data.

 To add a new version to the compatibility dashboard:
    1. Add the version to $compatibility_versions array in config.php
    2. Change the COMPAT_DEFAULT_VERSION to the new version in config.php
    3. Add 2 sizes of the wordmark to app/webroot/images/wordmarks
*/

class CompatibilityController extends AppController
{
    var $name = 'Compatibility';
    var $uses = array('Addon', 'Appversion');
    var $components = array('Amo','Versioncompare');
    var $helpers = array('Html', 'Localization');

   /**
    * Require login for all actions
    */
    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
        
        $this->cssAdd = array('compatibility');
        $this->publish('cssAdd', $this->cssAdd);
        
        $this->jsAdd = array('compatibility.js');
        $this->publish('jsAdd', $this->jsAdd);
        
        $this->layout = 'amo2009';
        $this->pageTitle = ___('compatibility_dashboard_center_header', 'Add-on Compatibility Center').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        
        $this->publish('expand_categories', true);
    }
   
    /**
     * Index - Redirects to dashboard()
     */ 
    function index($version = COMPAT_DEFAULT_VERSION, $view = '') {
        $this->setAction('dashboard', $version, $view);
    }
    
    /**
    * Compatibility Dashboard
    */
    function dashboard($version = COMPAT_DEFAULT_VERSION, $view = '') {
        global $compatibility_versions;
        if (!in_array($version, $compatibility_versions)) $version = COMPAT_DEFAULT_VERSION;
        
        $data = unserialize(file_get_contents(NETAPP_STORAGE.'/compatibility-fx-'.$version.'.serialized'));
        
        $this->publish('totals', $data['totals']);
        $this->publish('percentages', $this->_percentages($data['totals']));
        $this->publish('version', $version);
        
        $session = $this->Session->read('User');
        $this->publish('loggedin', !empty($session));
        $this->render('dashboard');
    }

    /**
     * Detailed Report of add-ons
     * @param string $version Firefox version to report on
     * @param string $format Can be "ajax" to hide formatting
     */
    function report($version = COMPAT_DEFAULT_VERSION, $format = 'html') {
        global $compatibility_versions;
        if (!in_array($version, $compatibility_versions)) $version = COMPAT_DEFAULT_VERSION;
        
        $data = unserialize(file_get_contents(NETAPP_STORAGE.'/compatibility-fx-'.$version.'.serialized'));
        
        $this->publish('addons', $data['addons']);
        $this->publish('totals', $data['totals']);
        $this->publish('format', $format);
        $this->publish('version', $version);
        
        if ($format == 'ajax')
            $this->render('report', 'ajax');
        else
            $this->render('report', 'amo2009');
    }
    
    function developers($version = COMPAT_DEFAULT_VERSION, $format = 'html') {
        global $compatibility_versions;
        if (!in_array($version, $compatibility_versions)) $version = COMPAT_DEFAULT_VERSION;
        $session = $this->Session->read('User');
        
        if (!empty($session)) {
            $addon_ids = $this->Addon->getAddonsByUser($session['id']);
            $addons = array();
            
            if (!empty($addon_ids)) {
                $relatedVersions = $this->Appversion->getRelatedVersions($version);
                $appversions = $this->Versioncompare->getCompatibilityGrades($version, $relatedVersions[APP_FIREFOX]);
                
                foreach ($addon_ids as $addon_id => $addon_name) {
                    $stats = $this->Addon->query("SELECT date, count, application FROM update_counts WHERE addon_id = {$addon_id} ORDER BY date DESC LIMIT 1");
                    $updatepings = unserialize($stats[0]['update_counts']['application']);
                    
                    $addons[$addon_id] = array(
                        'name' => $addon_name,
                        'totalCount' => $stats[0]['update_counts']['count'],
                        'date' => $stats[0]['update_counts']['date'],
                        'versionCount' => 0
                    );
                    
                    // Tally active users of clients using the specified version
                    if (!empty($updatepings['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}'])) {
                        foreach ($updatepings['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}'] as $appversion => $count) {
                            if (strpos($appversion, $version) !== false) {
                                $addons[$addon_id]['versionCount'] += $count;
                            }
                        }
                    }
                    
                    $addons[$addon_id]['percentage'] = round(($addons[$addon_id]['versionCount'] / $addons[$addon_id]['totalCount'] * 100), 2);
                    
                    // Get latest version's compatibility
                    $compat = $this->Addon->query("SELECT appversions.version, versions.id FROM versions INNER JOIN applications_versions ON applications_versions.version_id = versions.id INNER JOIN appversions ON appversions.id = applications_versions.max WHERE versions.addon_id={$addon_id} AND applications_versions.application_id = ".APP_FIREFOX." ORDER BY versions.created DESC LIMIT 1");
                    
                    $addons[$addon_id]['appversion'] = $compat[0]['appversions']['version'];
                    $addons[$addon_id]['latestVersion'] = $compat[0]['versions']['id'];
                    $addons[$addon_id]['grade'] = $this->Versioncompare->gradeCompatibility($addons[$addon_id]['appversion'], $version, $appversions);
                }
            }
            
            $this->publish('addons', $addons);
        }
        
        $this->publish('loggedin', !empty($session));
        $this->publish('format', $format);
        $this->publish('version', $version);
        
        if ($format == 'ajax')
            $this->render('developers', 'ajax');
        else
            $this->render('developers', 'amo2009');
    }
    
    function users($version = COMPAT_DEFAULT_VERSION) {
        $this->publish('version', $version);
        $this->render('users');
    }

    /* Calculate compatibility percentages, making sure they add up to 100. */
    function _percentages($totals) {
        $k = array(COMPAT_OTHER, COMPAT_ALPHA, COMPAT_BETA, COMPAT_LATEST);
        $percentages = array();

        foreach ($k as $compat) {
            $p = $totals[$compat]['adu'] / $totals['adu95'];
            // Round to 1 decimal place.
            $percentages[$compat] = round($p * 100, 1);
        }
        // Put any over/under flow into COMPAT_LATEST, somewhat arbitrary.
        $percentages[COMPAT_LATEST] += 100 - array_sum($percentages);
        return $percentages;
    }
}

?>
