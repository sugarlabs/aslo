<?php
/*
 * Created on May 24, 2009
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
 
class TagModelTest extends UnitTestCase {
	var $tagId;
	
	
	function TagModelTest() {
        loadModel('Tag');
        $this->Tag = new Tag();
        
        loadModel('Addon');
        $this->Addon = new Addon();
        
        loadModel('UserTagAddon');
        $this->UserTagAddon = new UserTagAddon();


    	$this->Addon->Tag->TagStat->cacheQueries = false;
    	$this->Addon->Tag->cacheQueries = false;
        
    }
    
    function addTag($text) {
    	$ret = $this->Tag->id = null;
    	$ret = $this->Tag->save(array (
			'tag_text' => $text,
			'blacklisted' => 0,
			'created' => date('Y-m-d h:i:s', time())
			));
			
		$this->assertTrue($ret, "inserted tag '$text'");
		
		return $this->Tag->getLastInsertId(); 
    	
    }
    
    
    
    function setUp() {
    	$yem = "great";
		$this->assertEqual($yem, "great");
		$arrayTagData = array (
			'tag_text' => 'yem',
			'blacklisted' => 0,
			'created' => date('Y-m-d h:i:s', time())
			);
			
		$this->Tag->save($arrayTagData);
		$this->tagId = $this->Tag->getLastInsertId();
    	
    }
    
    
	function tearDown() {
    	$this->Addon->execute('delete from users_tags_addons');
    	$this->Addon->execute('delete from tag_stat');
		$this->Addon->execute('delete from tags');
    	
	}    
    
    
    function testInsertAndFindTag() {
		// now get the latest tag
		$dbTag = $this->Tag->findById($this->tagId);
		
		$this->assertEqual($dbTag['Tag']['id'], $this->tagId);
		$this->assertEqual($dbTag['Tag']['tag_text'], "yem");
		$this->assertEqual($dbTag['Tag']['blacklisted'], 0);
    }	


	function cleanupAllTestData() {
    	$this->Addon->execute('delete from users_tags_addons');
    	$this->Addon->execute('delete from tag_stat');
		$this->Addon->execute('delete from tags');		
	}
    
    function testGetDistinctTagsForAddons() {
    	$tag1Id = $this->addTag('tag1');
    	$tag2Id = $this->addTag('tag2');
    	$tag3Id = $this->addTag('tag3');
    	$tag4Id = $this->addTag('tag4');
    	$tag5Id = $this->addTag('tag5');
    	$tag6Id = $this->addTag('tag6');
    	$tag7Id = $this->addTag('tag7');
    	$tag8Id = $this->addTag('tag8');
    	
		$this->Addon->addTag(1, $tag1Id, 9); // addon+id, tag_id, user_id   
		$this->Addon->addTag(1, $tag2Id, 9); // addon+id, tag_id, user_id
		$this->Addon->addTag(2, $tag1Id, 9); // addon+id, tag_id, user_id    	
		$this->Addon->addTag(2, $tag2Id, 9); // addon+id, tag_id, user_id    	
		$this->Addon->addTag(3, $tag1Id, 9); // addon+id, tag_id, user_id    	
		$this->Addon->addTag(3, $tag2Id, 9); // addon+id, tag_id, user_id    	
		$this->Addon->addTag(4, $tag1Id, 9); // addon+id, tag_id, user_id    	
		$this->Addon->addTag(4, $tag4Id, 9); // addon+id, tag_id, user_id
		$this->Addon->addTag(5, $tag1Id, 9); // addon+id, tag_id, user_id
		$this->Addon->addTag(6, $tag1Id, 9); // addon+id, tag_id, user_id

		$results = $this->Tag->getDistinctTagsForAddons(array(1,2,3,4));
		$this->assertTrue(count($results) == 3, "3 unique tags found");
		//print_r($results);
		
		$tagIds = array($tag1Id, $tag2Id, $tag4Id);
		foreach( $results as $result) {
			$tagId = $result['Tag']['id'];
			$this->assertTrue(in_array($tagId, $tagIds), $tagId . " found");
		}
		
		
		// cleanup		
		$this->cleanupAllTestData();	
    			
    }
    
    function testBlacklistAndUnblacklist() {
    	$tag1Id = $this->addTag('tag1');
    	$this->Addon->addTag(1, $tag1Id, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(2, $tag1Id, 9); // addon+id, tag_id, user_id	
    	
    	$this->Addon->Tag->blacklistTag($tag1Id);
    	
    	$dbTag = $this->Addon->Tag->findById($tag1Id);
    	$this->assertTrue($dbTag['Tag']['blacklisted'] == "1", "tag is blacklisted");
    	
    	$dbUserTagAddons = $this->UserTagAddon->findByTagId($tag1Id);
    	$this->assertTrue($dbUserTagAddons == null, "tag removed from all addons");
    	
    	$dbTagStat = $this->Tag->TagStat->findByTagId($tag1Id);
    	$this->assertTrue($dbTagStat['TagStat']['num_addons'] == 0, "tag stat is 0");
    	
    	// now unblacklist
    	$this->Addon->Tag->unblacklistTag($tag1Id);
     	$dbTag = $this->Addon->Tag->findById($tag1Id);
    	$this->assertTrue($dbTag['Tag']['blacklisted'] == "0", "tag is UNblacklisted");
    	
    	$this->cleanupAllTestData();
    }
}
?>
