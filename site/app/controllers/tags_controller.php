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

class TagsController extends AppController
{
    var $name = 'Tags';
    var $layout = 'mozilla';
    var $uses = array('Addon', 'Eventlog', 'Review', 'Tag', 'Translation', 'Version', 'ReviewsModerationFlag', 'UserTagAddon');
    var $components = array('Amo', 'Pagination', 'Session');
    var $helpers = array('Html', 'Link', 'Localization', 'Pagination', 'Time');
    var $namedArgs = true;
    var $beforeFilter = array('checkCSRF', 'getNamedArgs', '_checkSandbox');

    var $securityLevel = 'low';

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
    }
    
	/**
	* Add a new tag (AJAX)
	**/
	
	function add_ajax($addon_id, $tags_text) {
 		$this->_add_tag($addon_id, $tags_text);
		$this->render('tag_added', 'ajax');        
   }
   
 	/**
	* Add a new tag (Non-AJAX)
	**/
	
	function add() {
		$addon_id = $_REQUEST['addonid'];
		$tags_text = $_REQUEST['newTag'];
 		$this->_add_tag($addon_id, $tags_text);
 		// Send user back where he came from
 		if ($_REQUEST['origin'] == 'developers') {
			$this->redirect('/developers/addon/edit/' . $addon_id . '/tags');	    
 			exit();
 		}
		$this->redirect('/addon/' . $addon_id);	    
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
        $tags = $this->Tag->makeTagList($addon_data, $user);
        $this->publish('addon_id', $addon_data['Addon']['id']);
        $this->publish('userTags', $tags['userTags']);
        $this->publish('developerTags', $tags['developerTags']);  	
	}

	function addAndBlacklist() {
        $tags_text = $_REQUEST['newTag'];
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

	function remove_ajax($addon_id, $tag_id) {
        $this->Amo->clean($addon_id);
        $this->Amo->clean($tag_id);
        $this->Amo->checkLoggedIn(); // must be logged in
        $this->Addon->caching = false;        
        
		$this->Addon->removeTagFromAddons($tag_id, $addon_id);
		
		// Get tag list for addon
        $this->Addon->bindOnly('Tag', 'User');
 		$addon_data = $this->Addon->findById($addon_id);
        $tags = $this->Tag->makeTagList($addon_data, $user);
        $this->publish('addon_id', $addon_data['Addon']['id']);
        $this->publish('userTags', $tags['userTags']);
        $this->publish('developerTags', $tags['developerTags']);  
		$this->publish('message', ___('tag_message_tag_removed', 'Tag removed'));
        
		$this->render('tag_added', 'ajax');        
		
	}
		
	function remove($addon_id, $tag_id, $origin) {
        $this->Amo->clean($addon_id);
        $this->Amo->clean($tag_id);
        $this->Amo->checkLoggedIn(); // must be logged in
        $this->Addon->caching = false;        
        
		$this->Addon->removeTagFromAddons($tag_id, $addon_id);
		$this->flash('Tag removed');
 		if ($origin == 'developers') {
			$this->redirect('/developers/addon/edit/' . $addon_id . '/tags');	    
 			exit();
 		}
		$this->redirect('/addon/' . $addon_id);	 
	}		
		
	function splitTags($string) {
		// Code from http://us2.php.net/manual/en/function.split.php#81490
		$separator=' ';
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
        return $elements;		
	}		

	function top($numTags=100, $sortBy="freq") {
		// get the top tags
		$this->publish('numTags', $numTags);
		
		$topTags = $this->Tag->getTop($numTags, $sortBy);
		$this->publish('topTags', $topTags);
	}
 }

?>
