<?php
/*
 * Created on May 25, 2009
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
class UserTagAddon extends AppModel {
	var $name = "UserTagAddon";
	var $useTable = 'users_tags_addons';
	
	var $belongsTo = array('Tag', 'User', 'Addon');
	var $recursive = -1;
	
	
	
	
	
}
?>
