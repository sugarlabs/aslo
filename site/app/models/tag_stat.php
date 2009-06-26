<?php
/*
 * Created on May 24, 2009
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
class TagStat extends AppModel {
	var $name = 'TagStat';	
	var $useTable = 'tag_stat';
	var $belongsTo = array('Tag');
	var $recursive = -1;
	var $primaryKey = 'tag_id';

	function deleteForTag($tag_id) {
		$this->execute("DELETE FROM tag_stat WHERE tag_id = {$tag_id}");
		
	}
}
?>
