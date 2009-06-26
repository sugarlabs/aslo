<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you not use this file except in compliance with
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
 *   Justin Scott <fligtar@gmail.com>
 *   Wil Clouser <clouserw@mozilla.com>
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

class Tag extends AppModel
{
    var $name = 'Tag';
    
    
 	var $hasOne = array('TagStat' =>
                              array('className'  => 'TagStat',
                                    'joinTable'  => 'tag_stat',
                                    'foreignKey' => 'tag_id',
                                    'dependent'   => true
                              )
 	
 	);
 	
	var $hasMany = array(
 									
									'UserTagAddon' => 
 										array( 'className' => 'UserTagAddon',
 										'foreignKey' => 'tag_id',
 										'dependent'   => true
  										)
 						);

 	var $recursive = 1;
 	
	function makeTagList($addon_data, $user, $showblacklist=true) {
	$this->caching = false;
	
      // Make a list of user_ids for the addon owners so we can see if these count as developer tags
       $developers = array();
       // Make a new array of developer tags
       $developerTags = array();
       // Make a new array of user tags
       $userTags = array();       
       foreach ($addon_data['User'] as $developer) {
       	$developers[] = $developer['id'];
       }
       
        $_related_tag_ids = array();
        foreach ($addon_data['Tag'] as $tagvalue){
            $_related_tag_ids[] = $tagvalue['id'];
        } 

        if (!empty($_related_tag_ids)) {
            $related_tags = $this->findAll("Tag.id IN (".implode(',', $_related_tag_ids).") and blacklisted=0",null,"Tag.tag_text asc");      	
				// Go through tags and assign developer status and owner status
				foreach ($related_tags as $tag) {
					// If the logged in user owns the tag or is a developer of this addon, mark the tag element
					if ($user) {
						if (($tag['UserTagAddon'][0]['user_id']==$user['id'])  || (in_array($user['id'],$developers))) {
							$tag['Tag']['OwnerOrDeveloper'] = 1;
						}
						$tag['LoggedInUser']=$user;
					}
					if (in_array($tag['UserTagAddon'][0]['user_id'], $developers)) {
							$developerTags[] = $tag;			
					} else {
							$userTags[] = $tag;					
					}
				}
			}
        else
            $related_tags = array();
		
		$this->caching = true;
		
		return array('userTags'=>$userTags, 'developerTags'=>$developerTags);
	}		 	
 	
 	
 	
 	/**
 	 * 
 	 */
 	function blacklistTag($tag_id) {
 		$dbTag = $this->findById($tag_id);
 		if( !empty($dbTag)) {
 			$this->id = $dbTag['Tag']['id'];
 			$this->saveField('blacklisted', 1);
 			
 			// remove all the tag-addon relations
 			$numDeletedFromAddons = $this->deleteFromAddons($tag_id);
 			
 		} else {
 			return false;
 		}
 	}
 	
 	function unblacklistTag($tag_id) {
 		$dbTag = $this->findById($tag_id);
 		if( !empty($dbTag)) {
 			$this->id = $dbTag['Tag']['id'];
 			$this->saveField('blacklisted', 0);
 		} else {
 			return false;
 		}
 	}
 	
 	function deleteFromAddons($tag_id) {
 		$sql = "DELETE FROM users_tags_addons WHERE tag_id={$tag_id}";
 		$res = $this->query($sql); 		
 		return $this->getAffectedRows();
 		
 	}

	/*
	 * Given an array of addon IDs this function returns a list of distinct tags for that list of addons.
	 * 
	 */
	function getDistinctTagsForAddons($addon_ids) {
		if (count($addon_ids) == 0 ) {
			return null;
		}
		
		return $this->findAll(" id in (select distinct(tag_id) from users_tags_addons where addon_id in (". implode(",", $addon_ids) . "))", array("id", "tag_text", "blacklisted", "created", "TagStat.tag_id", "TagStat.num_addons"), "num_addons DESC");
		
	}
  	function getMaxNumAddons() {
 		$sql = "select max(num_addons) as maxaddons from tag_stat;";
 		$res = $this->query($sql); 		
 		return $res[0][0]['maxaddons'];
 	}
 	
	function getTop($numTags, $sortBy) {
		
		if( $sortBy == "freq") {
			$sort = "num_addons DESC";	
		} else if( $sortBy == "alpha") {
			$sort = "tag_text ASC";
		} else {
			$sort = null;
		}
		
		return $this->findAll(" TagStat.num_addons > 0 ", null/*fields*/, $sort, $numTags);
	}
	
	/**
	 * Cake doesn't seem to be propogating deletions so doing it here.
	 */
	function beforeDelete() {
		$this->caching = false;
		$this->execute("delete from users_tags_addons where tag_id = {$this->id}");
		$this->execute("delete from tag_stat where tag_id = {$this->id}");
		$this->caching = true;
	}
}