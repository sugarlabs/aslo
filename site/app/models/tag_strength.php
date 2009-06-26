<?php
/*
 * Created on May 24, 2009
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
class TagStrength extends AppModel {
	var $name = 'TagStrength';		
	var $useTable = 'tag_strength';
	//var $belongsTo = array('Tag');
	
 	function getRelatedTags($tag1_id) {
 		return $this->findByTag1Id($tag1_id);
 	}
	
	function deleteForTag($tag_id) {
		$this->execute("DELETE FROM tag_strength WHERE tag1_id = {$tag_id} OR tag2_id = {$tag_id}");
	}
}
?>
