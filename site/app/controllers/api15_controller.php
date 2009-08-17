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
 *   Laura Thomson <lthomson@mozilla.com> (Original Author) 
 *   l.m.orchard <lorchard@mozilla.com>
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

uses('sanitize');
vendor('sphinx/sphinxapi');
vendor('sphinx/addonsSearch');
require_once('api_controller.php');

class Api15Controller extends ApiController
{

    public $name = 'Api15';

    public $newest_api_version = 1.5;   
    
    public function search($term) {
        $this->layout = 'rest'; 
        
        
        // summon the sphinx api
        $sphinx = new SphinxClient();
        $sphinx->SetServer(SPHINX_HOST, SPHINX_PORT);
        $sphinx->SetSelect("addon_id, app");
        $sphinx->SetFieldWeights(array('name'=> 4));
        $sphinx->SetSortMode ( SPH_SORT_EXPR, "@weight + IF(status=1, 0, 100)" );
        $sphinx->SetLimits(0, 60);
        $sphinx->SetFilter('inactive', array(0));
        
        // filter based on the app we're looking for e.g is this /firefox/ or /seamonkey/ etc
        $sphinx->SetFilter('app', array(APP_ID));
        
        // version filter
        // convert version to int
        // convert into to a thing to serach for
        if (preg_match('/\bversion:([0-9\.]+)/', $term, $matches)) {
            $term = str_replace($matches[0], '', $term);
            $version_int = AddonSearch::convert_version($matches[1]);
            // using 10x version number since that should cover a significantly larger number since SetFilterRange requires
            // max and min
            if ($version_int) {
                $sphinx->SetFilterRange('max_ver', $version_int, 10*$version_int);
                $sphinx->SetFilterRange('min_ver', 0, $version_int);
            }
        }
        
        // type filter 
        if (preg_match('/\btype:(\w+)/', $term, $matches)) {
            $term = str_replace($matches[0], '', $term);
            $type = AddonSearch::convert_type($matches[1]);
            if ($type) {
                $sphinx->SetFilter('type', array($type));
            }
        }
        
        // platform
        if (preg_match('/\bplatform:(\w+)/', $term, $matches)) {
            $term = str_replace($matches[0], '', $term);
            $platform = AddonSearch::convert_platform($matches[1]);
            if ($platform) {
                $sphinx->SetFilter('platform', array($platform, PLATFORM_ALL));
            }
        }
        
        // date filter
        if (preg_match("{\bafter:([0-9-]+)\b}", $term, $matches)) {
            
            $term      = str_replace($matches[0], '', $term);
            $timestamp = strtotime($matches[1]);
            if ($timestamp) {
                $sphinx->SetFilterRange('modified', $timestamp, time());
            }
        }
        
        
        // category filter
        // pull out the category
        // do the lookup
        if (preg_match('/\bcategory:(\w+)/', $term, $matches)) {
            $term = str_replace($matches[0], '',$term);
            $category = AddonSearch::convert_category($matches[1]);
            $sphinx->setFilter('category', array($category));
        }
        
        if (preg_match('/\btag:(\w+)/', $term, $matches)) {
            $term = str_replace($matches[0], '',$term);
            $tag = AddonSearch::convert_tag($matches[1]);
            $sphinx->setFilter('tag', array($tag));
        }
        

        $result        = $sphinx->Query($term);
        $total_results = $result['total_found'];
        $matches       = array();

        if ($total_results) {
            foreach($result['matches'] AS $match) {
                $matches[] = $match['attrs']['addon_id'];
                // var_dump($match['attrs']);
            }
        }
        // var_dump($result['matches']);
        // exit;
        $this->_getAddons($matches);
        
        // var_dump($matches);
        // var_dump($this->viewVars['addonsdata']);
        $this->publish('api_version', $this->api_version); 
        $this->publish('guids', array_flip($this->Application->getGUIDList()));
        $this->publish('app_names', $app_names = $this->Application->getIDList()); 
        $this->publish('total_results', $total_results); 
        $this->publish('os_translation', $this->os_translation);   
        $this->publish('addonsdata', $this->viewVars['addonsdata']);
    }
}
