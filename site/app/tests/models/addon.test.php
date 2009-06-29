<?php

class AddonModelTest extends UnitTestCase {

    var $tag1Id;
    var $tag2Id;
    var $tag3Id;
    


    function AddonModelTest() {
        loadModel('Addon');
        $this->Addon = new Addon();
    }
    
    

	function setUp() {
		$arrayTagData = array (
			'tag_text' => 'yem',
			'blacklisted' => 0,
			'created' => date('Y-m-d h:i:s', time())
			);

    	$ret = $this->Addon->Tag->save($arrayTagData);
    	//echo "ret=".$ret."<br>";
    	
    	$this->tag1Id = $this->Addon->Tag->getLastInsertId();
    	//echo "tag1Id=".$this->tag1Id."<br>";
    	
		$arrayTag2Data = array (
			'tag_text' => 'huynh',
			'blacklisted' => 0,
			'created' => date('Y-m-d h:i:s', time())
			);
    	
    	$ret = $this->Addon->Tag->id = null;
    	
    	$ret = $this->Addon->Tag->save($arrayTag2Data);
    	//echo "ret=".$ret."<br>";
    	
    	$this->tag2Id = $this->Addon->Tag->getLastInsertId();    	
    	//echo "tag2Id=".$this->tag2Id."<br>";	
    	
    	$ret = $this->Addon->Tag->id = null;
		$arrayTag2Data = array (
			'tag_text' => 'yh',
			'blacklisted' => 0,
			'created' => date('Y-m-d h:i:s', time())
			);
    	
    	$ret = $this->Addon->Tag->save($arrayTag2Data);
    	$this->tag3Id = $this->Addon->Tag->getLastInsertId();  
    	
    	$ret = $this->Addon->Tag->id = null;

    	// this is needed so subsequent find() calls after inserts actually get something back.
    	$this->Addon->Tag->TagStat->cacheQueries = false;
    	$this->Addon->Tag->cacheQueries = false;
    	
    	$this->Addon->addTag(1/*addon_id*/, $this->tag1Id, 1/*user_id*/);
    	$this->Addon->addTag(1/*addon_id*/, $this->tag1Id, 2/*user_id*/);
    	$this->Addon->addTag(1/*addon_id*/, $this->tag1Id, 8/*user_id*/);

		
    	$this->Addon->addTag(2/*addon_id*/, $this->tag2Id, 1/*user_id*/);
    	$this->Addon->addTag(3/*addon_id*/, $this->tag2Id, 1/*user_id*/);
    	$this->Addon->addTag(3/*addon_id*/, $this->tag2Id, 4/*user_id*/);
    	$this->Addon->addTag(4/*addon_id*/, $this->tag2Id, 2/*user_id*/);
    	$this->Addon->addTag(2/*addon_id*/, $this->tag2Id, 2/*user_id*/);
    	$this->Addon->addTag(3/*addon_id*/, $this->tag2Id, 2/*user_id*/);
  	
    	$this->Addon->addTag(3/*addon_id*/, $this->tag3Id, 1/*user_id*/);


	}
	
	
	function tearDown() {
    	$this->Addon->execute('delete from users_tags_addons');
    	$this->Addon->execute('delete from tag_stat');
		$this->Addon->execute('delete from tags');
		
    }


	function testStats() {
		
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag1Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 3);
		

		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag2Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 6);

		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag3Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 1);
		
	}
	
	

    function testGetAddonsForAuthors() {
        $this->assertEqual($this->Addon->getAddonsForAuthors(array(4)),
                           array(6));
        $this->assertEqual(sort($this->Addon->getAddonsForAuthors(array(1, 3))),
                           array(1, 2, 3, 4, 5));
    }

 
    
	function testGetTagsForUser() {
		$tags = $this->Addon->getTagsByUser(1);
		$this->assertEqual(count($tags), 3);
		$tags = $this->Addon->getTagsByUser(2);
		$this->assertEqual(count($tags), 2);
		$tags = $this->Addon->getTagsByUser(19);
		$this->assertEqual(count($tags), 0);
		$tags = $this->Addon->getTagsByUser(4);
		$this->assertEqual(count($tags), 1);
		$tags = $this->Addon->getTagsByUser(8);
		$this->assertEqual(count($tags), 1);
		
		
	}
	
	
	function testGetTagsForAddon() {
		$tags = $this->Addon->getTagsByAddon(1);
		$this->assertEqual(count($tags), 1);
		
		$tags = $this->Addon->getTagsByAddon(1);
		$this->assertEqual(count($tags), 1);
		
		$tags = $this->Addon->getTagsByAddon(3);
		$this->assertEqual(count($tags), 2);

		$tags = $this->Addon->getTagsByAddon(4);
		$this->assertEqual(count($tags), 1);
	}
	
	
	
	function testRemoveUserTagFromAddon() {
		$this->Addon->removeUserTagFromAddon(1, $this->tag1Id,1);
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag1Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 2);

		$this->Addon->removeUserTagFromAddon(4, $this->tag2Id,2);
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag2Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 6);

		$this->Addon->removeUserTagFromAddon(2, $this->tag2Id,4);
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag2Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 5);
				
	}
	
	
	
	
	function testRemoveTagFromAddons() {
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag1Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 3);
		
		// removing this tag from this addon should remove 3 instances of them
		$this->Addon->removeTagFromAddons($this->tag1Id,1);
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag1Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 0);
		
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag2Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 6);
		$this->Addon->removeTagFromAddons($this->tag2Id,3);
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag2Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 3);
		
		
		
	}
	
	
	
	function testBlacklistTag() { 
		$this->Addon->Tag->blacklistTag($this->tag1Id);
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag1Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 0);
		
		$this->Addon->Tag->unblacklistTag($this->tag1Id);
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag1Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 0);
		
						
	}
	
	
function testDeleteTag() {
		$this->Addon->Tag->delete($this->tag1Id);
		
		// make sure there is not tag1Id record in tags
		$tag = $this->Addon->Tag->findById($this->tag1Id);
		
		$this->assertEqual($tag['Tag'], null);
		
		// now make sure there are no tag stats or user tags for this tag
		$tagsForUser = $this->Addon->getTagsByUser(8);
		$this->assertEqual(count($tagsForUser), 0);
		
		// make sure that tag_stat is gone for tagId1
		$stat = $this->Addon->Tag->TagStat->findByTagId($this->tag1Id);
		$this->assertEqual($stat['TagStat']['num_addons'], 0);
	}
    
}
?>
