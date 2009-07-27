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
 *   Andrei Hajdukewycz <sancus@off.net> (Original Author)
 *   Wil Clouser <clouserw@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *   Les Orchard <lorchard@mozilla.com>
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


class TagsController extends AppController
{
    var $name = 'Tags';
    var $layout = 'mozilla';
    var $uses = array('Addon', 'Eventlog', 'Review', 'Tag', 'Translation', 'Version', 'ReviewsModerationFlag', 'UserTagAddon', 'File', 'Platform');
    var $components = array('Amo', 'Pagination', 'Session', 'Search', 'Image');
    var $helpers = array('Html', 'Link', 'Localization', 'Pagination', 'Time');
    var $namedArgs = true;
    var $beforeFilter = array('checkCSRF', 'getNamedArgs', '_checkSandbox');

    var $securityLevel = 'low';

	/**
     * Holds the sanitize component, used to clean variables in our custom queries
     * @var object
     */
    var $Sanitize;

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
    }

    /**
     * Constructor.  Declared so we can initialize Sanitize.
     * 
     */
    function TagsController() {

        parent::__construct();

        $this->Sanitize = new Sanitize();
    }
    
   
 	/**
	* Add a new tag
	**/
	
	function add() {
        $this->Amo->checkLoggedIn(); // must be logged in	

		$addon_id = $_POST['addonid'];
		$tags_text = $_POST['newTag'];

        if (!is_numeric($addon_id)) {
            return false;
        }

 		$this->_add_tag($addon_id, $tags_text);

 		// Send user back where he came from
 		if (@$_POST['ajax']==1) {
 			$this->render('tag_added', 'ajax');  
 		} else {
 		 	if (@$_POST['origin'] == 'developers') {
				$this->redirect('/developers/addon/edit/' . $addon_id . '/tags');	    
 				exit();
 			}
			$this->redirect('/addon/' . $addon_id);	    
		}
   }  

	/**
	* Internal function to add a new tag
	**/
	function _add_tag($addon_id, $tags_text) {
      
        if (!is_numeric($addon_id)) {
            return false;
        }

        $this->Amo->checkLoggedIn(); // must be logged in
        
        $user = $this->Session->read('User');

		// Only 80 tags per addon.
		$num_tags = count($this->Addon->getTagsByAddon($addon_id));

 		// Split based on whitespace, but keep quotes. 
 		$split_tags = $this->splitTags($tags_text);

        // We disable caching here specifically for Tag->findByTagText().  If that gets cached
        // and a new tag is added any future attempt to add the same tag will fail until the
        // query is done again (so it can get the tag id).
        $this->Tag->caching = false;

		// Process each tag
        foreach ($split_tags as $tag_text) {
        	// If we're up to 80 tags, then break
        	if ($num_tags > 79) {
                $this->publish('message', ___('tag_message_tag_limit_reached', 'Tag limit reached'));
        		break;
        	}

            // Strip out stuff that breaks things; bug 502126
            $tag_text = preg_replace(INVALID_TAG_CHARS, '', $tag_text);

            // Strip out extra whitespace but allow a single space. Tags are already trim()'d
            $tag_text = preg_replace('/\s\s+/', ' ', $tag_text);

            // Minimum tag length is 2
            if (mb_strlen($tag_text) < 2) {
                continue;
            }
        
	        // Check if tag exists
  	 	    $tag = $this->Tag->findByTagText($tag_text);
   		    if (!empty($tag)) {
				// Tag exists. Use this tag id.
   	     		$tag_id = $tag['Tag']['id'];
   	    	} else {
  		       //Create Tag
				$arrayTagData = array ('Tag'=>array(
					'tag_text' => $tag_text,
					'blacklisted' => 0,
					'created' => date('Y-m-d h:i:s', time())
					));
				$this->Tag->create(); // re-initialize the model
				$this->Tag->save($arrayTagData);
				$tag_id = $this->Tag->getLastInsertId();
			}
            unset($tag);

			// Check if addon is already tagged with this tag
			$existing = $this->UserTagAddon->find("tag_id = $tag_id and addon_id=$addon_id");
			if (!empty($existing)) {
				// Load the model to see who the owner is
				$addon = $this->Addon->getAddon($addon_id, array('authors'));
				foreach($addon['User'] as $addon_user) {
					if ($addon_user['id'] == $user['id']) {
						// Logged in user is tag owner. 
						$owner=true;
					}
				}
				if (@$owner) {
					// Replace tag.
					$this->Addon->removeUserTagFromAddon($existing['UserTagAddon']['user_id'], $tag_id, $addon_id);
					$this->Addon->addTag($addon_id, $tag_id, $user['id']);
					
				} else {
					// User is not owner; skip to next tag in list
					continue;
				}
			} else {
				// Add Tag to Addon
				$this->Addon->addTag($addon_id, $tag_id, $user['id']);
				$this->publish('message', ___('tag_message_tag_added', 'Tag added'));
			}
            $num_tags++;
        } // foreach $tag	
        $this->Tag->caching = true;

        // We probably updated some stuff
        if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon_id}");
		
 		// Get tag list for addon
 		$addon_data = $this->Addon->getAddon($addon_id, array('authors', 'all_tags'));
        $tags = $this->Tag->makeTagList($addon_data, $user, $this->SimpleAcl->actionAllowed('Admin', 'DeleteAnyTag', $user));
        $this->publish('addon_id', $addon_data['Addon']['id']);
        $this->publish('userTags', $tags['userTags']);
        $this->publish('developerTags', $tags['developerTags']);  	
	}

	function addAndBlacklist() {
	    $this->Amo->checkLoggedIn(); // must be logged in
        $tags_text = $_POST['newTag'];
        $split_tags = $this->splitTags($tags_text);

		// Process each tag
        foreach ($split_tags as $tag_text) {
	        // Check if tag exists
  	 	     $tag = $this->Tag->findByTagText($tag_text);
   		     if (!empty($tag)) {
				// Tag exists. Use this tag id.
   	     		$tag_id = $tag['Tag']['id'];
   	    	} else {
  		       //Create Tag
				$arrayTagData = array ('Tag'=>array(
					'tag_text' => $tag_text,
					'blacklisted' => 0,
					'created' => date('Y-m-d h:i:s', time())
					));
				$this->Tag->create(); // re-initialize the model
				$this->Tag->save($arrayTagData);
				$tag_id = $this->Tag->getLastInsertId();
				
			}
            $this->Tag->blacklistTag($tag_id);
            unset($tag);
        }
		$this->redirect('admin/tags');
	}

	function remove() {
        $this->Amo->checkLoggedIn(); // must be logged in

        $addon_id = $_POST['addonid'];
        $tag_id = $_POST['tagid'];
        $origin = @$_POST['origin'];

        if (!(is_numeric($tag_id) && is_numeric($addon_id))) {
            return false;
        }

        $user = $this->Session->read('User');

        $addon_data = $this->Addon->getAddon($addon_id, array('authors', 'all_tags'));
        $tags = $this->Tag->makeTagList($addon_data, $user, $this->SimpleAcl->actionAllowed('Admin', 'DeleteAnyTag', $user));

        if (!$this->Tag->userCanModifyTagForAddonFromList($user['id'], $tag_id, $tags)) {
            return false;
        }

        $this->Addon->caching = false;    
		$this->Addon->removeTagFromAddon($tag_id, $addon_id);
        if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

		// Get tag list for addon, without the extra add-on
        $tags = $this->Tag->makeTagList($addon_data, $user, $this->SimpleAcl->actionAllowed('Admin', 'DeleteAnyTag', $user));
		
        $this->publish('addon_id', $addon_data['Addon']['id']);
        $this->publish('userTags', $tags['userTags']);
        $this->publish('developerTags', $tags['developerTags']);  
		$this->publish('message', ___('tag_message_tag_removed', 'Tag removed'));
        
 		if (@$_POST['ajax']==1) {
 			$this->render('tag_added', 'ajax');  
 		} else {
 		 	if ($origin == 'developers') {
				$this->redirect('/developers/addon/edit/' . $addon_id . '/tags');	    
 				exit();
 			}
			$this->redirect('/addon/' . $addon_id);	    
		}
	}
		
	function splitTags($string) {
		// Code from http://us2.php.net/manual/en/function.split.php#81490
		$separator=',';
        $elements = explode($separator, $string);
        for ($i = 0; $i < count($elements); $i++) {
            $nquotes = substr_count($elements[$i], '"');
            if ($nquotes %2 == 1) {
                for ($j = $i+1; $j < count($elements); $j++) {
                    if (substr_count($elements[$j], '"') %2 == 1) { // Look for an odd-number of quotes
                        // Put the quoted string's pieces back together again
                        array_splice($elements, $i, $j-$i+1,
                            implode($separator, array_slice($elements, $i, $j-$i+1)));
                        break;
                    }
                }
            }
            if ($nquotes > 0) {
                // Remove first and last quotes, then merge pairs of quotes
                $qstr =& $elements[$i];
                $qstr = substr_replace($qstr, '', strpos($qstr, '"'), 1);
                $qstr = substr_replace($qstr, '', strrpos($qstr, '"'), 1);
                $qstr = str_replace('""', '"', $qstr);
            }
        }

        // trim whitespace and remove empty values
        return array_filter(array_map('trim', $elements));		
	}		

	function top($numTags=100, $sortBy='freq') {
		$this->publish('numTags', $numTags);
		
		$topTags = $this->Tag->getTop($numTags, $sortBy);

		$this->publish('topTags', $topTags);
	}
	
	function display($tag_text) {
		global $app_shortnames;
		$associations = array(
            'single_category', 'all_categories', 'authors', 'compatible_apps', 'files',
            'latest_version', 'list_details', 'all_tags'
        );

		$tag = $this->Tag->findByTagText($tag_text);
		
		$this->publish('tag_text',$tag['Tag']['tag_text']);
		
		$appname = "";
        if (isset( $this->params['url']['appid']) && 
            in_array($this->params['url']['appid'], array_values($app_shortnames) ) ) {
            $appname = array_search($this->params['url']['appid'], $app_shortnames);
            $redirect = str_replace(APP_SHORTNAME, $appname, $_SERVER['REQUEST_URI']);
            
            if($this->params['url']['appid'] != APP_ID) { $this->redirect("http://".$_SERVER["HTTP_HOST"].$redirect, null, true); }
        }
        $this->publish('appid', APP_ID);

        $sort = "weeklydownloads";
        $sort_orders = array('newest', 'name', 'averagerating', 'weeklydownloads');
        if (isset( $this->params['url']['sort']) && 
            in_array($this->params['url']['sort'], $sort_orders) ) { $sort = $this->params['url']['sort']; }
        $this->publish('sort', $sort); //publish for element caching
		
		$_results = $this->Search->search($tag_text, null, true, null, 0, null, -1, -1, false, ADDON_ANY, PLATFORM_ANY, "", $sort);
		
		$this->Pagination->total = count($_results);
		$this->publish('total_count',$this->Pagination->total);
		
		// stolen from search_controller
		$pp= 20;
		$this->Pagination->show = $pp;
        $this->publish('pp',  $pp); //publish for element caching

        list($order,$limit,$page) = $this->Pagination->init();

        $this->publish("on_page", $page);
        // cut the appropriate slice out of the results array
        $offset = ($page-1)*$limit;
		$this->publish("offset",$offset);
		$_results = array_slice($_results, $offset, $limit);
		
		if (!empty($_results)) {
            $results = $this->Addon->getAddonList($_results, $associations);
        } else {
            $results = array();
        }
        $this->publish('bigHeader', true);
        $this->publish('bigHeaderText', sprintf(_('addons_home_header_details'), APP_PRETTYNAME));
        
        /* pull in platforms for install button */
        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('platforms', $platforms);

        $this->publish('results', $results);

		$this->pageTitle = sprintf(_('addons_display_pagetitle'), $tag['Tag']['tag_text']). ' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
		
		$this->render();
		return;
	}
 }

?>
