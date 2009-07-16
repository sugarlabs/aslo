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

class ApiController extends AppController
{

    var $name = 'Api';

    // bump for new releases
    // 0 or unspecified is for Fx3b3
    // 0.9 is for Fx3b4
    var $newest_api_version = 1.4;   
 
    // cribbed from addonscontroller
    // some of this is excessive but will likely be needed as 
    // development continues
    var $beforeFilter = array('checkCSRF', 'getNamedArgs', '_checkSandbox', 'checkAdvancedSearch');
    var $uses = array('Addon', 'AddonCollection', 'Addontype', 'Application', 'Collection', 'File', 'GlobalStat', 'Platform', 'Category', 'Translation', /*'Review',*/ 'UpdateCount', 'Version');    
    var $components = array('Amo', 'Image', 'Pagination', 'Search', 'Session', 'Versioncompare');    
    var $helpers = array('Html', 'Link', 'Time', 'Localization', 'Ajax', 'Number', 'Pagination');
    var $namedArgs = true;
    var $exceptionCSRF = array("/feed");

    var $securityLevel = 'low';

    function beforeFilter() {

        if ($this->Config->getValue('api_disabled') == 1) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private');
            header('Pragma: no-cache');
            exit;
        }

        $this->forceShadowDb();

        // Disable ACLs; API is public for now
        // we may add keys later on 
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
       
        // extract API version
        $url = $_SERVER['REQUEST_URI'];

        $matches = array();
        if (preg_match('/api\/([\d\.]*)\//', $url, $matches)) {
            $this->api_version = $matches[1];
            if (!is_numeric($this->api_version)) {
                $this->api_version = $this->newest_api_version; 
            }
        } else {
           // nothing supplied: assume Fx3b3
            $this->api_version = 0; 
        }

        // set up translation table for os names
        // this is hardcoded in 
        $this->os_translation = array(
                                         'ALL' => 'ALL',
                                         'bsd' => 'BSD_OS',
                                         'BSD' => 'BSD_OS',
                                         'Linux' => 'Linux',
                                         'macosx' => 'Darwin',
                                         'MacOSX' => 'Darwin',
                                         'Solaris' => 'SunOS',
                                         'win' => 'WINNT',
                                         'Windows' => 'WINNT',
                                     );
    }

    /**
    * Return details of an addon
    * See requirement DTL-1 in 
    * [http://wiki.mozilla.org/index.php?title=Update:RequirementsV33]
    *
    * @param int $id the id of the addon
    */
    function addon($id) {
        $this->Amo->clean($id);
        $this->layout='rest';
        $this->_getAddons(array($id));
        if (isset($this->viewVars['addonsdata'][$id])) {
            $this->publish('addon', $this->viewVars['addonsdata'][$id]);
        } else {
            $error = ___('error_addon_notfound');
            $this->publish('error', $error);
            return;
        }
        // get real app names
        $app_names = $this->Application->getIDList();
        $this->publish('app_names', $app_names); 
        $guids = array_flip($this->Application->getGUIDList());
        $this->publish('guids', $guids); 
        $this->publish('api_version', $this->api_version); 
        $this->publish('os_translation', $this->os_translation);   
    }

    /**
    * Search for matching addons
    * See requirement SRCH-1 in 
    * [http://wiki.mozilla.org/index.php?title=Update:RequirementsV33]
    *
    * @param string $term searchterm 
    */
    function search($term, $searchtype=NULL, $maxresults = 10, $search_os = null, $search_version=null) {

        $this->layout = 'rest'; 
        $this->Sanitize = new Sanitize();    
        $versions=null;

        // if we're passed a version do something with it
        if (isset($search_version)) {
            $all_appversions = $this->Amo->getVersionIdsByApp(APP_ID);
            foreach($all_appversions as $appversion => $appvid) {
                if($this->Versioncompare->versionBetween($appversion, $search_version, $search_version)) {
                    $versions[] = $appvid; // remora version ids needed
                }            
             }
        } else {
             $versions=NULL;
        }

        // if we're passed an OS, check it's valid
        $platforms = $this->Platform->getNames();
        $platform_id = -1; 
        if(isset($search_os) && ($search_os != 'ALL') && ($search_os != 'all'))  {
            $ids = array_flip($platforms);
            $client_platforms = array_flip($this->os_translation);
            if (@isset($ids[$client_platforms[$search_os]])) {
              $platform_id = $ids[$client_platforms[$search_os]];   
            }
        }
        
        if ($searchtype==NULL || $searchtype =='all') {
            $searchtype = ADDON_API;
        }

        // We're not searching tags here
        $result_ids = $this->Search->search($term, '', false, $searchtype, 0, STATUS_PUBLIC,
                                            $versions , -1, false, -1, $platform_id);
        if (is_array($result_ids)) {
            $total_results = count($result_ids);
        } else {
            $total_results = 0;
        }

        // get the total number of addons that would be returned
        // if we did a regular search on AMO
        $amo_result_ids = $this->Search->search($term); /*, NULL, 0, NULL, 
                                                -1, -1, false, -1, -1); */

        if (is_array($amo_result_ids)) {
            $amo_total_results = count($amo_result_ids);
        } else {
            $amo_total_results = 0;
        }


        $this->_getAddons(array_slice($result_ids, 0, $maxresults));
      
        if ($this->api_version < 0.9) {
            // hack to make the count work right in the API
            //  TODO: to be fixed properly for Fx3 b4
            $hack_array = $this->viewVars['addonsdata'];
            $extras_needed = $total_results - $maxresults;
            for ($count=0; $count < $extras_needed; $count++) {
                $hack_array[] = 'dummy';
            }
       
            $this->set('addonsdata' , $hack_array);
        }

        // get real app names
        $app_names = $this->Application->getIDList();
        $guids = array_flip($this->Application->getGUIDList());
        $this->publish('guids', $guids); 
        $this->publish('app_names', $app_names); 
        $this->publish('api_version', $this->api_version); 
        $this->publish('total_results', $amo_total_results); 
        $this->publish('os_translation', $this->os_translation);   
        $this->publish('addonsdata', $this->viewVars['addonsdata']);
    }

    /**
    * List addons
    * See requirements LIST-1..3 in 
    * [http://wiki.mozilla.org/index.php?title=Update:RequirementsV33]
    * routes from api/list but list is a reserved word in PHP
    *
    * @param string $listtype (recommended, featured, new) 
    * @param string $addontype (all, extension, theme, plugin, dictionary, searchengine) 
    * @param int $number maximum number of results to return 
    */
    function list_addons($listtype='recommended', $addontype='all', 
                         $number = 3, $list_os=null, $list_version=null) {  

        $this->layout = 'rest';

        // process listtype
        switch ($listtype) {
            case 'new'        :
                           // new is less than ten days old
                           $select = 'distinct a.id ';
                           $days_since_creation = 10; 
                           $list_criteria = '(datediff(a.created, NOW()) < '.$days_since_creation.')'; 
                           $tables = 'addons as a';
                           break;
            case 'recommended':      
            case 'featured'   :
            default     :
                           $select = ' f.addon_id ';
                           $list_criteria = ' f.start < NOW() 
                                             AND f.end > NOW() 
                                             AND f.application_id = \''
                                             . APP_ID . '\'
                                              AND (f.locale = \''
                                             . LANG .'\' 
                                             OR f.locale IS NULL) 
                                             AND a.status = \''.STATUS_PUBLIC.'\' ';  
                           $tables = ' features as f LEFT JOIN 
                                       addons as a on 
                                       f.addon_id = a.id ';
                           break;
        }   

        // process addon type
        switch ($addontype) {
            case 'extension' :
                              $addontype_sql = ADDON_EXTENSION;
                              break;
            case 'theme' :
                              $addontype_sql = ADDON_THEME;
                              break;
            case 'plugin' :
                              $addontype_sql = ADDON_PLUGIN;
                              break;
            case 'dictionary' :
                              $addontype_sql = ADDON_DICT;
                              break;
            case 'searchengine' :
                              $addontype_sql = ADDON_SEARCH;
                              break;
            default:
                              $addontype_sql = NULL;

        } 
        $addon_criteria = '';
        if ($addontype_sql) {
           $addon_criteria = ' AND a.addontype_id = '. $addontype_sql.' ';        
        } 

       // platforms
       if ($list_os && $list_os != 'ALL' && $list_os != 'all') {
           $platforms = $this->Platform->getNames();
           $ids = array_flip($platforms); 
           $client_platforms = array_flip($this->os_translation);
           if (isset($client_platforms[$list_os]) 
               && isset($ids[$client_platforms[$list_os]])
               && $ids[$client_platforms[$list_os]] != NULL ) {              
               $platform_id = $ids[$client_platforms[$list_os]];            
               $all_id = $ids[$client_platforms['ALL']];            
               //echo "platform id is |$platform_id|"; exit;
               $tables .= 'JOIN versions AS v
                          ON v.addon_id = a.id
                          INNER JOIN files 
                          ON files.platform_id IN ('.$platform_id.', 
                                                   '.$all_id.')  
                          AND v.id = files.version_id'; 
           }        
       }  

       // versions
       if ($list_version) {
           $all_appversions = $this->Amo->getVersionIdsByApp(APP_ID);           
           $versions_wanted = array();
           $below_wanted = array();
           $above_wanted = array();
           foreach($all_appversions as $appversion => $appvid) {                
               $compare = $this->Versioncompare->compareVersions($appversion, $list_version); 
                if ($compare < 0) {
                    $below_wanted[] = $appvid;
                } else if ($compare > 0) {
                    $above_wanted[] = $appvid;
                } else {
                    $versions_wanted[] = $appvid;
                }
           }
           if (count($versions_wanted)) {
               $tables .= ' JOIN versions_summary AS vs
                            ON f.addon_id = vs.addon_id ';
               //$addon_criteria .= ' AND vs.min IN ('.$version_list.')
               //                     OR vs.max IN ('.$version_list.') '; 
               $_ver_string = "('".implode("', '", $versions_wanted) ."') ";
               $versions_criteria = " AND ((vs.min IN ".$_ver_string
                                    ." OR vs.max IN ".$_ver_string. ") ";
               $_below_string = "('".implode("', '", $below_wanted) ."') ";
               $_above_string = "('".implode("', '", $above_wanted) ."') ";
               $versions_criteria .= " OR (vs.min IN ".$_below_string
                                  ." AND vs.max IN ".$_above_string."))";
               $addon_criteria .= $versions_criteria;
           } else {
               $versions_criteria ='';
           }        
       }
       // process number
       if (!is_numeric($number)) {
         // revert to default value for this param if passed garbage
         $number = 3;
       }

       $sql = 'SELECT '. $select .' 
               FROM '.$tables 
               .' WHERE '
               . $list_criteria 
               . $addon_criteria ;

       if ($listtype =='recommended') {
           $sql .= ' UNION SELECT DISTINCT(f.addon_id) 
                           FROM ';
           $tables2 = ' addons_categories AS f LEFT JOIN addons AS a ON f.addon_id = a.id ';
           $where2 = ' WHERE f.feature = 1 
                       AND a.status =\''.STATUS_PUBLIC.'\' ';
           if ($list_os && $list_os != 'all' && $list_os != 'ALL'
                && isset($platform_id)) {
               // omg, query++
               $tables2 .= ' 
                           JOIN versions AS v
                           ON v.addon_id = f.addon_id              
                           INNER JOIN files                           
                           ON files.platform_id IN ('.$platform_id.' ,
                                                    '.$all_id.')
                           AND v.id = files.version_id ';
           }
           if ($list_version && count($versions_wanted)) {
               $tables2 .= ' JOIN versions_summary AS vs          
                             ON f.addon_id = vs.addon_id ';
           }
           $sql .= $tables2 . $where2;
           if (isset($versions_criteria)) {
               $sql .= $versions_criteria;
           }
       }
       $addons = $this->Addon->query($sql, true);
       $addon_ids = array(); 
       foreach ($addons as $addon)  {
          if ($listtype=='new')
            $addon_ids[] = $addon['a']['id'];
          else if ($listtype == 'recommended')
            $addon_ids[] = $addon[0]['addon_id'];
          else 
            $addon_ids[] = $addon['f']['addon_id'];
       }
 
       shuffle($addon_ids);
       $addon_ids = array_slice($addon_ids, 0, $number);
 
       $this->_getAddons($addon_ids);

       // get real app names
       $app_names = $this->Application->getIDList();
       $this->publish('app_names', $app_names); 
       $guids = array_flip($this->Application->getGUIDList());
       $this->publish('guids', $guids); 
       $this->publish('ids', $addon_ids);
       $this->publish('addonsdata', $this->viewVars['addonsdata']);
       $this->publish('api_version', $this->api_version); 
        $this->publish('os_translation', $this->os_translation);   
    }

    /**
     * List addons in a collection
     *
     * @param string $id
     * @param string $passwd_hash
     */
    function collections_feed($id, $passwd_hash=null) {
        $this->layout = 'rest';

        $addon_ids = array();
        $addon_collections = array();

        // Try looking up just the identified collection.
        $this->Collection->unbindFully();
        $collection = $this->Collection->find(array(
            'Collection.id' => $id
        ));
        if (!$collection) {
            $error = ___('error_collection_feed_notfound', 'Addon feed not found');
            $this->publish('error', $error);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // POST request method is treated as a write attempt.
            if (! $this->collections_feed_write($collection, $passwd_hash) )
                return;
        }

        // Publish the collection details for the feed.
        $this->publish('feed_id', 
            $collection['Collection']['id']);
        $this->publish('feed_created', 
            $collection['Collection']['created']);
        $this->publish('feed_modified', 
            $collection['Collection']['modified']);
        $this->publish('feed_closed', 
            !!$collection['Collection']['password']);
        $this->publish('feed_name', 
            $collection['Translation']['name']['string']);
        $this->publish('feed_description', 
            $collection['Translation']['description']['string']);

        // Now, attempt to look up all the addon references in the 
        // collection.  Collate the details by addon ID and extract
        // a list of those addon IDs.
        $collection_items = $this->AddonCollection->findAll(array(
            'AddonCollection.collection_id' => $id
        ));
        foreach ($collection_items as $item) {
            $id = $item['AddonCollection']['addon_id'];
            $addon_ids[] = $id;
            $addon_collections[$id] = $item;
        }

        // Fetch the addons for the feed, first tearing down the model bindings 
        // and selectively rebuilding them.
        $this->Addon->unbindFully();
        $this->Addon->bindModel(array(
            'hasAndBelongsToMany' => array(
                'User' => array(
                    'className'  => 'User',
                    'joinTable'  => 'addons_users',
                    'foreignKey' => 'addon_id',
                    'associationForeignKey'=> 'user_id',
                    'conditions' => 'addons_users.listed=1',
                    'order' => 'addons_users.position'
                ),
                'Category' => array(
                    'className'  => 'Category',
                    'joinTable'  => 'addons_categories',
                    'foreignKey' => 'addon_id',
                    'associationForeignKey'=> 'category_id'
                )
            )
        ));
        $addons_data = $this->Addon->findAll(array(
            'Addon.id' => $addon_ids,
            'Addon.inactive' => 0,
            'Addon.addontype_id' => array(
                ADDON_EXTENSION, ADDON_THEME, ADDON_DICT, 
                ADDON_SEARCH, ADDON_PLUGIN
            )
        ));

        // Rather than trying to join categories and addon types in SQL, collect IDs 
        // and make a pair of queries to fetch them.
        $category_ids = array();
        $addon_type_ids = array();
        foreach ($addons_data as $addon) {
            $addon_type_ids[$addon['Addon']['addontype_id']] = true;
            foreach ($addon['Category'] as $category) 
                $category_ids[$category['id']] = true;
        }

        // Query for addon types found in this set of addons, assemble a map 
        // for an in-code join later.
        $addon_type_rows = $this->Addontype->findAll(array(
            'Addontype.id' => array_keys($addon_type_ids)
        ));
        $addon_types = array();
        foreach ($addon_type_rows as $row) {
            $addon_types[$row['Addontype']['id']] = $row;
        }

        // Query for addon types found in this set of categories, assemble a map 
        // for an in-code join later.
        $category_rows = $this->Category->findAll(array(
            'Category.id' => array_keys($category_ids)
        ));
        $all_categories = array();
        foreach ($category_rows as $row) {
            $all_categories[$row['Category']['id']] = $row;
        }

        $app_names = $this->Application->getIDList();
        $guids = array_flip($this->Application->getGUIDList());

        $this->publish('app_names', $app_names); 
        $this->publish('guids', $guids); 
        $this->publish('ids', $addon_ids);
        $this->publish('api_version', $this->api_version); 
        $this->publish('os_translation', $this->os_translation);   

        // Process addons list to produce a much flatter and more easily 
        // sanitized array structure for the view, sprinkling in details
        // like categories and version information along the way.
        //
        // TODO: Reconcile this with the _getAddons() method from which this 
        // was refactored but not replaced.
        $addons_out = array();
        for ($i=0; $i<count($addons_data); $i++) {

            $addon = $addons_data[$i];
            $id    = $addon['Addon']['id'];

            $addontype_id = $addon['Addon']['addontype_id'];

            // make sure reported latest version matches version of file
            $install_version = $this->Version->getVersionByAddonId(
                $addon['Addon']['id'], STATUS_PUBLIC
            );

            // get filename for install
            $fileinfo = $this->File->findAllByVersion_id(
                $install_version, null, null, null, null, 0
            );
            if (!is_array($fileinfo) || count($fileinfo)==0) {
                continue;    
            }

            // Start constructing a flat minimal list of addon details made up of only 
            // what the view will need.
            $addon_out = array(
                'collection_added' => 
                    $addon_collections[$id]['AddonCollection']['added'],
                'collection_comments' => 
                    $addon_collections[$id]['Translation']['comments']['string'],
                'id' => $addon['Addon']['id'],
                'guid' => $addon['Addon']['guid'],
                'name' => $addon['Translation']['name']['string'],
                'summary' => $addon['Translation']['summary']['string'],
                'description' => 
                    $addon['Translation']['description']['string'],
                'addontype_id' => $addontype_id,
                'addontype_name' => 
                    $addon_types[$addontype_id]['Translation']['name']['string'],
                'icon' => 
                    $this->Image->getAddonIconURL($id),
                'thumbnail' => 
                    $this->Image->getHighlightedPreviewURL($id),
                'install_version' => $install_version,
                'status' => $addon['Addon']['status'],
                'users' => $addon['User'],
                'eula' => $addon['Translation']['eula']['string'],
                'averagerating' => $addon['Addon']['averagerating'],
                'categories' => array(),
                'compatible_apps' => array(),
                'all_compatible_os' => array(),
                'fileinfo' => array()
            );

            // Add the list of categories into the addon details
            foreach ($addon['Category'] as $x) {
                $x = $all_categories[ $x['id'] ];
                $addon_out['categories'][] = array(
                    'id'   => $x['Category']['id'],
                    'name' => $x['Translation']['name']['string']
                );
            }

            // Add the list of compatible apps into the addon details
            $compatible_apps = 
                $this->Version->getCompatibleApps($install_version);
            foreach ($compatible_apps as $x) {
                $addon_out['compatible_apps'][] = array( 
                    'id'   => $x['Application']['application_id'],   
                    'name' => $app_names[ $x['Application']['application_id']],   
                    'guid' => $guids[$app_names[$x['Application']['application_id']]], 
                    'min_version' => $x['Min_Version']['version'],  
                    'max_version' => $x['Max_Version']['version']  
                );
            }

            // Gather a list of platforms for files
            $platforms = array();
            foreach($fileinfo as &$file) {
                $this->Platform->unbindFully();
                $this_plat = $this->Platform->findById($file['Platform']['id']);
                $file['Platform']['apiname'] = $this_plat['Translation']['name']['string'];
                $platforms[] = $this_plat;
            }

            if ($this->api_version > 0 ) {
                // return an array of compatible os names
                // right now logic is still wrong, but this enables
                // xml changes and logic will be fixed later
                if (empty($platforms)) {
                    $all_compatible_os = array();
                } else {
                    $all_compatible_os = $platforms;
                }
                foreach ($all_compatible_os as $x) {
                    $addon_out['all_compatible_os'][] =
                        $this->os_translation[ $x['Translation']['name']['string'] ];
                }
            }

            // Add in the list of files available for the addon.
            foreach ($fileinfo as $x) {
                $addon_out['fileinfo'][] = array(
                    'id'       => $x['File']['id'],
                    'filename' => $x['File']['filename'],
                    'hash'     => $x['File']['hash'],
                    'os'       => $this->os_translation[ $x['Platform']['apiname'] ],
                );
            }

            // Finally, add this set of addon details to the list intended for 
            // the view.
            $addons_out[] = $addon_out;
        }

        $this->publish('addonsdata', $addons_out);
    }

    /**
     * Append an addon to a collection
     *
     * @param object $collection
     * @param string $passwd_hash
     */
    function collections_feed_write($collection, $passwd_hash=null) {
        try {

            // Try getting content type and length from the headers.
            $content_type = isset($_SERVER['CONTENT_TYPE']) ? 
                $_SERVER['CONTENT_TYPE'] : 'application/json';
            $content_length = isset($_SERVER['CONTENT_LENGTH']) ? 
                $_SERVER['CONTENT_LENGTH'] : FALSE;

            // If there's content waiting, try fetching it.
            if (!$content_length)
                throw new Exception('No addon data submitted');
            $data = file_get_contents('php://input');

            // Verify the password hash, if necessary.
            if ($collection['Collection']['password']) {
                if ($passwd_hash != $collection['Collection']['password'])
                    throw new Exception('Password hash mismatch');
            }
        
            if ($content_type == 'text/xml') { 
                // If the request claims to be submitting XML, attempt to 
                // extract the details from it.
                $doc = new SimpleXMLElement($data);
                $req_addon = array(
                    'guid'     => (string)$doc->guid,
                    'comments' => (string)$doc->comments
                );
            } else {
                // Otherwise, assume the incoming request is JSON.
                $req_addon = json_decode($data, TRUE);
            }
            
            // If there's no addon data, or the GUID is missing, abort.
            if (!$req_addon)
                throw new Exception('Invalid request data');

            if (!isset($req_addon['guid']) || !$req_addon['guid']) 
                throw new Exception('Missing addon GUID');

            // Try to find the requested addon, abort if not found.
            $addon = $this->Addon->find(array(
                'Addon.guid' => $req_addon['guid']
            ));
            if (!$addon)
                throw new Exception('No addon found for GUID');

            // Check if the addon is already in the collection.
            $existing_item = $this->AddonCollection->findAll(array(
                'AddonCollection.collection_id' => 
                    $collection['Collection']['id'],
                'AddonCollection.addon_id' => 
                    $addon['Addon']['id']
            ));
            if ($existing_item)
                throw new Exception('Addon already in collection');

            // Finally, add the addon to the collection and bump the 
            // modification timestamp.
            $this->AddonCollection->save(array(
                'collection_id' => $collection['Collection']['id'],
                'addon_id'      => $addon['Addon']['id'],
                'comments'      => $req_addon['comments'],
                'added'         => date('Y-m-d h:i:s', time())
            ));
            $this->Collection->save(array(
                'id'       => $collection['Collection']['id'],
                'modified' => date('Y-m-d h:i:s', time())
            ));

            return TRUE;

        } catch (Exception $e) {
            // In the case of any exceptions, toss an error back as a response.
            $error = $e->getMessage();
            $this->publish('error', $error);
            return FALSE;
        }
    }

    /**
    * Retrieve cumulative downloads for an addon 
    * See requirement DOWN-2 in
    * [http://wiki.mozilla.org/index.php?title=Update:RequirementsV33]
    * @param int $id  id of the addon 
    */
    function cumulative_downloads($id) {
        $this->Amo->clean($id);    
        $this->layout='rest';
        $_conditions = array(
            'Addon.id' => $id,
            'Addon.inactive' => 0,
            'Addon.addontype_id' => array(ADDON_EXTENSION, ADDON_THEME, 
                                          ADDON_DICT, ADDON_SEARCH, ADDON_PLUGIN)
            );

        // get basic addon data
        // same criteria as used by the amo display action 
        $addon_data = $this->Addon->find($_conditions, null , null , 1);

        if (empty($addon_data)) {
            $error = ___('error_addon_notfound');
            $this->publish('error', $error);
            return;
        }

        // get download count
        $downloads = $addon_data['Addon']['totaldownloads'];

        $this->publish('id', $id);
        $this->publish('downloads', $downloads); 
    } 

    /**
    * Retrieve update pings for an addon 
    * See requirement USE-2 in
    * [http://wiki.mozilla.org/index.php?title=Update:RequirementsV33]
    * @param int $id  id of the addon 
    * @param string $period day, month, year: period to report on
    * @param string $querydate date on which reporting period ENDS
    */
/*
// update_pings off for now
    function update_pings($id, $period='day', $querydate='') {
        $this->Amo->clean($id);    
        $this->layout='rest';

        // date handling
        if ($querydate == '') {
             //$querydate = 'foo';  // today
             $querydate = date('Y-m-d');  // today
        } else {
            if (!($checkdate = strtotime($querydate))) {
                // if it's not a valid date fall back to today
                $querydate = date('Y-m-d');  // today 
            } else {
                $querydate = date('Y-m-d', $checkdate);
            }
        }

        // period handling
        switch ($period)  {
            case 'week': 
                          $days = 7;      
                          break;
            case 'month':
                          $days = 30;
                          break; 
            case 'day':
            default: 
                          $period = 'day';
                          $days = 1;
        }

        $daylength = 24*60*60; 
        $startdate = date('Y-m-d', (strtotime($querydate)-($days*$daylength)));  
        $date_sql = ' and  date <= \''
                    . $querydate
                    .'\' and  date > \''
                    . $startdate .'\''; 


        $sql = 'select *
                from update_counts
                where addon_id = '.$id
                . $date_sql
                ; 
       
       // run the query
       $update_counts = $this->UpdateCount->query($sql);

       // extract and unmunge serialized data
       $total_count = 0;
       $version_counts = array();
       $status_counts = array();
       $application_counts = array();
       $os_counts = array();

       // get real app names
       $app_names = $this->Application->getGUIDList();

       foreach ($update_counts as $update_count) {
           $total_count += $update_count['update_counts']['count'];

           $vc = unserialize($update_counts[0]['update_counts']['version']);
           foreach ($vc as $version => $count) {
               if (!isset($version_counts["$version"])) {
                   $version_counts["$version"] = $count;
               } else {
                   $version_counts["$version"] += $count;
               }
           }

           $s = unserialize($update_count['update_counts']['status']);
           foreach ($s as $status => $count) {
              @$status_counts["$status"] += $count;
           }

           $apps = unserialize($update_count['update_counts']['application']);
           foreach ($apps as $app => $details) {
               foreach ($details as $version => $count) {
                   @$application_counts[$app][$version] += $count;   
               }
           }

           $os = unserialize($update_count['update_counts']['os']);
           foreach ($os as $os => $count) {
               @$os_counts["$os"] += $count;
           }
       }

       // output
       $this->publish('app_names', $app_names);
       $this->publish('id', $id);
       $this->publish('period', $period);
       $this->publish('querydate', $querydate);
       $this->publish('total_count', $total_count);
       $this->publish('version_counts', $version_counts);
       $this->publish('status_counts', $status_counts);
       $this->publish('application_counts', $application_counts);
       $this->publish('os_counts', $os_counts);
    } 
*/
    
    /**
     * Returns global stats for AMO for the specified date
     */
    function stats($date = '') {
        $this->layout = 'rest';
        
        if (empty($date)) {
            $date = date('Y-m-d');
        }
        else {
            $date = date('Y-m-d', strtotime($date));
        }
        
        $stats = array(
            'addon_total_downloads' => $this->GlobalStat->getNamedCount('addon_total_downloads', $date),
            'addon_total_updatepings' => $this->GlobalStat->getUpdatepingsUpToDate('addon_total_updatepings', $date),
            'addon_count_public' => $this->GlobalStat->getNamedCount('addon_count_public', $date),
            'addon_count_pending' => $this->GlobalStat->getNamedCount('addon_count_pending', $date),
            'addon_count_experimental' => $this->GlobalStat->getNamedCount('addon_count_experimental', $date),
            'addon_count_nominated' => $this->GlobalStat->getNamedCount('addon_count_nominated', $date),
            'collection_count_total' => $this->GlobalStat->getNamedCount('collection_count_total', $date),
            'collection_count_private' => $this->GlobalStat->getNamedCount('collection_count_private', $date),
            'collection_count_public' => $this->GlobalStat->getNamedCount('collection_count_public', $date),
            'collection_count_autopublishers' => $this->GlobalStat->getNamedCount('collection_count_autopublishers', $date),
            'collection_count_editorspicks' => $this->GlobalStat->getNamedCount('collection_count_editorspicks', $date),
            'collection_count_normal' => $this->GlobalStat->getNamedCount('collection_count_normal', $date),
            'collection_addon_downloads' => $this->GlobalStat->getNamedCount('collection_addon_downloads', $date),
            'collector_total_downloads' => $this->GlobalStat->getNamedCount('collector_total_downloads', $date),
            'collector_updatepings' => $this->GlobalStat->getUpdatepingsUpToDate('collector_updatepings', $date)
        );
        
        $this->publish('stats', $stats);
        $this->publish('date', $date);
    }

    /**
    * Return a complete list of available language packs 
    *
    */
    function get_language_packs() {

        $this->layout = 'rest';

        $conditions = array(
            'Addon.addontype_id' => array(ADDON_LPAPP),
            'Addon.status' => STATUS_PUBLIC,
            'Addon.inactive' => 0
        );

        $this->Addon->unbindfully();
        $lang_packs = $this->Addon->findAll($conditions, 'Addon.id');
        $ids = array();
        foreach ($lang_packs as $lp) {
            $ids[] = $lp['Addon']['id'];
        }

        $this->_getAddons($ids);

        // get real app names
        $app_names = $this->Application->getIDList();
        $guids = array_flip($this->Application->getGUIDList());
        $this->publish('guids', $guids);
        $this->publish('app_names', $app_names);
        $this->publish('os_translation', $this->os_translation);
        $this->publish('api_version', $this->api_version);
        $this->publish('addonsdata', $this->viewVars['addonsdata']);
    }

/* Utility functions follow */
    
    /**
    * Given an array of addon ids, return details for those addons 
    *
    * @param array $ids ids of the addons to retrieve
    */
    function _getAddons($ids) {
       $addonsdata = array();
       foreach ($ids as $id) {
        $_conditions = array(
            'Addon.id' => $id,
            'Addon.inactive' => 0,
            'Addon.addontype_id' => array(ADDON_EXTENSION, ADDON_THEME, ADDON_DICT, ADDON_SEARCH, ADDON_PLUGIN, ADDON_LPAPP)
            );

        // get basic addon data
        // same criteria as used by the amo display action 
        $this->Addon->bindOnly('User', 'Version', 'Tag', 'AddonCategory');
        $addon_data = $this->Addon->find($_conditions, null , null , 1);

        if (empty($addon_data)) {
            // this covers the case where we turned up something in the requested set that
            // was invalid for whatever reason.
            continue;
        }

        // get addon type
        $this_addon_type = $this->Addontype->findById($addon_data['Addon']['addontype_id']);
        $addon_data['Addon_type'] = $this_addon_type;
/*
// getting rid of reviews for now
        // get reviews
        $all_version_ids = 
            $this->Version->getVersionIdsByAddonId($addon_data['Addon']['id'],
            $this->status);
        $_review_versions = array();
        foreach ($all_version_ids as $_version) {
            $_review_versions[] = $_version['Version']['id'];
        }
        if (!empty($_review_versions)) {
            $reviews = $this->Review->findAll(array(
                "Review.version_id" => $_review_versions,
                'Review.reply_to IS NULL',
                'Translation.body_locale' => array(LANG, 'en-US')),
                array('Review.id', 
                      'Review.version_id', 
                      'Review.body', 
                      'Review.created', 
                      'Review.title',
                      'Review.rating', 
                      'User.id', 
                      'User.nickname', 
                      'User.firstname', 
                      'User.lastname'),
                    "Review.created DESC", null, null, 1);
        } else {
            $reviews = array();
        }

        $addon_data['Reviews'] = array_slice($reviews, 0, 3);
*/
        // make sure reported latest version matches version of file
        $install_version 
            = $this->Version->getVersionByAddonId($addon_data['Addon']['id'],
                                                  STATUS_PUBLIC);
        // find the addon version to report to user 
        foreach ($addon_data['Version'] as $v) {
          if ($v['id'] == $install_version) {
            $addon_data['install_version'] = $v['version'];
            break;
          }
        }

        // get filename for install
        $fileinfo = $this->File->findAllByVersion_id(
            $install_version, null, null, null, null, 0);

        if (!is_array($fileinfo) || count($fileinfo)==0) {
            // don't return addons that don't have a valid
            // file associated with them 
            continue;    
        }

        // get compatible apps
        $compatible_apps = $this->Version->getCompatibleApps($install_version);
        $addon_data['Compatible_apps'] = $compatible_apps;
        

        // get compatible platforms
         
        foreach($fileinfo as &$file) {
            $this->Platform->unbindFully();
            $this_plat = $this->Platform->findById($file['Platform']['id']);
            $file['Platform']['apiname'] = $this_plat['Translation']['name']['string'];
            $platforms[] = $this_plat;
        }

        if ($this->api_version > 0 ) {
           // return an array of compatible os names
           // right now logic is still wrong, but this enables
           // xml changes and logic will be fixed later
           if (empty($platforms)) {
               $addon_data['all_compatible_os'] = array();
            } else {
                $addon_data['all_compatible_os'] = $platforms;
           }


        }



        // pull highlighted preview thumbnail url
        $addon_data['Thumbnail'] = $this->Image->getHighlightedPreviewURL($id);

        // the icon
        $addon_data['Icon'] = $this->Image->getAddonIconURL($id);

        $addon_data['fileinfo'] = $fileinfo;

        // add data to array
        $addonsdata[$id] = $addon_data;
       }  
       $this->set('addonsdata' , $addonsdata);
    }

    /**
     * API specific publish 
     * Uses XML encoding and is UTF-8 safe
     * @param mixed the data array (or string) to be html-encoded (by reference)
     * @param bool clean the array keys as well?
     * @return void
    */   
    function publish($viewvar, $value, $sanitizeme = true) {
        if ($sanitizeme) {
            if (is_array($value)) {
                $this->_sanitizeArrayForXML($value);
            } else {
                $tmp = array($value);
                $this->_sanitizeArrayForXML($tmp);
                $value = $tmp[0];
            }
        }
        $this->set($viewvar, $value);
    }

    /**     
     * API specific sanitize
     * xml-encode an array, recursively 
     * UTF-8 safe
     *          
     * @param mixed the data array to be encoded 
     * @param bool clean the array keys as well?
     * @return void 
     */ 
    var $sanitize_patterns = array(
        "/\&/u", "/</u", "/>/u", 
        '/"/u', "/'/u",
        '/[\cA-\cL]/u',
        '/[\cN-\cZ]/u',
     );
    var $sanitize_replacements = array(
        "&amp;", "&lt;", "&gt;", 
        "&quot;", "&#39;", 
        "", 
        ""
    );
    var $sanitize_field_exceptions = array(
        'id'=>1, 'guid'=>1, 'addontype_id'=>1, 'status'=>1, 'higheststatus'=>1,
        'icontype'=>1, 'version_id'=>1, 'platform_id'=>1, 'size'=>1, 'hash'=>1, 
        'codereview'=>1, 'password'=>1, 'emailhidden'=>1, 'sandboxshown'=>1, 
        'averagerating'=>1, 'textdir'=>1, 'locale'=>1, 'locale_html'=>1, 
        'created'=>1, 'modified'=>1, 'datestatuschanged'=>1
    );
    function _sanitizeArrayForXML(&$data, $cleankeys = false) {

        if (empty($data)) return;

        foreach ($data as $key => $value) {
            if (isset($this->sanitize_field_exceptions[$key])) {
                // @todo This if() statement is a temporary solution until we come up with
                // a better way of excluding fields from being sanitized.
                continue;
            } else if (empty($value)) {
                continue;
            } else if (is_array($value)) {
                $this->_sanitizeArrayForXML($data[$key], $cleankeys);
            } else {
                $data[$key] = preg_replace(
                    $this->sanitize_patterns, 
                    $this->sanitize_replacements, 
                    $data[$key]
                );
            }
        }
            
        // change the keys if necessary
        if ($cleankeys) {
            $keys = array_keys($data);
            $this->_sanitizeArrayForXML($keys, false);
            $data = array_combine($keys, array_values($data));
        }

    }

    /**
     * Standalone string sanitize for XML
     *
     * @param string
     * @return string
     */
    function sanitizeForXML($value) {
        return preg_replace(
            $this->sanitize_patterns, 
            $this->sanitize_replacements, 
            $value
        );
    }
        
}
