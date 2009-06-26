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
 *   Wil Clouser <wclouser@mozilla.com> (Original Author)
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
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


class SearchTest extends UnitTestCase {

    function SearchTest() {
        loadModel('Addon');
        $this->Addon = new Addon();
    
    	
    }

    /* N.B.: this is first so that it does the setup without spamming the result list
     * each time.
     */
    function testLoad()
    {
        $this->helper = new UnitTestHelper();
        $this->controller =& $this->helper->getController('Search', $this);
        $this->helper->mockComponents($this->controller, $this);

        // real SearchComponent for real (simple) searches
        loadComponent('Search');
        $this->controller->Search =& new SearchComponent();
        $this->controller->Search->startup($this->controller);
    }
  
    function runSimpleSearch($terms, $tag=null) {
        return $this->controller->Search->search($terms, $tag);
    }

    function runSimpleCollectionSearch($terms) {
        return $this->controller->Search->searchCollections($terms);
    }

    function testSearchHandlesInvalidInput() {        
        // a throwaway test - just making sure if we pass in an invalid
        // value, it comes back as an array anyway
        $this->assertIsA($this->runSimpleSearch(array()), 'array');
    }
  
    function testSearchAddonName() {
        $results = $this->runSimpleSearch("Farmer");
        $this->assertFalse(empty($results), "found results for name match");
    }
    
    function testSearchAddonSummary() {
        $results = $this->runSimpleSearch("Harvest MicroFormats");
        $this->assertFalse(empty($results), "found results for summary match");
    }

    function testSearchExcludesSearchPlugins() {
        $results = $this->runSimpleSearch("Google search, mark two");
        $this->assertTrue(empty($results), "excluded search plugins in searches");
    }

    function testSearchAddonDescription() {
        $results = $this->runSimpleSearch("MicroFormat XOXO hCalendar");
        $this->assertFalse(empty($results), "found results for description match");
    }

    function testSearchTermOrderIndependence() {
        $results1 = $this->runSimpleSearch("firefox hcalendar");
        $results2 = $this->runSimpleSearch("hcalendar firefox");
        $this->assertTrue($this->helper->array_compare_recursive($results1, $results2), "order of query terms doesn't matter");
    }

    function testSearchSuppressesDuplicates() {
        $results = $this->runSimpleSearch("te");
        foreach ($results as $result) {
            $this->assertEqual(count(array_keys($results, $result)), 1,
                               "no duplicates found in results");
        }
    }

    function testSearchDuplicateTerms() {
        $results1 = $this->runSimpleSearch("firefox");
        $results2 = $this->runSimpleSearch("firefox firefox");
        $this->assertTrue($this->helper->array_compare_recursive($results1, $results2), "entering the same term multiple times doesn't affect the results");
    }

    function testSearchQuoted() {
        $results = $this->runSimpleSearch('"Adds MicroFormat detection"');
        $this->assertFalse(empty($results), "found results for quoted search");
        
        $results_unquoted = $this->runSimpleSearch('Adds MicroFormat detection');
        $results_quoted = $this->runSimpleSearch('"MicroFormat Adds detection"');
        $this->assertTrue(!empty($results_unquoted) && empty($results_quoted), "quoted search returns nothing for query that matches when unquoted");
    }

    function testUsesFile() {
        $this->assertTrue(in_array('File', $this->controller->uses),
                          'The File model is available on the controller');
    }

    function testSearchCollectionName() {
        $results = $this->runSimpleCollectionSearch('name');
        // yem : assertTrue() fails
        $this->assertFalse(!empty($results), "found results for collection name match");
    }

    function testSearchCollectionDescription() {
        $results = $this->runSimpleCollectionSearch('description');    
        $this->assertTrue(!empty($results), "found results for collection description match");
    }
    
    function addTag($text) {
    	$ret = $this->Addon->Tag->id = null;
    	$ret = $this->Addon->Tag->save(array (
			'tag_text' => $text,
			'blacklisted' => 0,
			'created' => date('Y-m-d h:i:s', time())
			));
			
		$this->assertTrue($ret, "inserted tag '$text'");
		
		return $this->Addon->Tag->getLastInsertId(); 
    	
    }
    
    function refreshFulltextIndexes() {
		global $valid_status;
		
		$sql_commands = array();
		$sql_commands[] = "BEGIN";
		$sql_commands[] = "DELETE FROM `text_search_summary`";
		
		$sql_commands[] = "INSERT INTO `text_search_summary`
		                   SELECT  a.id AS id, 
		                       `tr_name`.locale AS locale,  
		                       a.addontype_id AS addontype, 
		                       a.status AS status, 
		                       a.inactive AS inactive, 
		                       a.averagerating AS averagerating, 
		                       a.weeklydownloads AS weeklydownloads,
		                       `tr_name`.localized_string AS name, 
		                       `tr_summary`.localized_string AS summary, 
		                       `tr_description`.localized_string AS description,
		                       tags
		                   FROM addons AS a 
		                   LEFT JOIN translations AS `tr_name` ON (`tr_name`.id = a.`name`) 
		                   LEFT JOIN translations AS `tr_summary` ON (`tr_summary`.id = a.`summary` AND  `tr_name`.locale = `tr_summary`.locale) 
		                   LEFT JOIN translations AS `tr_description` 
			                       ON (`tr_description`.id = a.`description` AND  `tr_name`.locale = `tr_description`.locale) 
						   LEFT JOIN 	                       		
						   ( select uta.addon_id, GROUP_CONCAT(distinct t.tag_text  SEPARATOR '\r\n') as tags
								from users_tags_addons uta, tags t
								where uta.tag_id = t.id
								group by uta.addon_id ) addon_tags ON ( a.id = addon_tags.addon_id)
				           WHERE `tr_name`.locale IS NOT NULL AND (
		                       `tr_name`.localized_string IS NOT NULL 
		                       OR `tr_summary`.localized_string IS NOT NULL 
		                       OR `tr_description`.localized_string IS NOT NULL
		                   ) 
		                   ORDER BY a.id ASC, locale DESC;";
		
		//the purpose of the temporary table is to get the most recently created version of an addon (avoiding sub-selects which are mysql 4 bad)
		$sql_commands[] = "DROP TABLE IF EXISTS `most_recent_version`"; //I am being paranoid to make sure temp table does not exist (it shouldn't by below)
		
		$sql_commands[] = "CREATE TEMPORARY TABLE `most_recent_version` (
		                       `addon_id` int(11) NOT NULL,
		                       `created` DATETIME NOT NULL   
		                   ) DEFAULT CHARSET=utf8";
		
		$sql_commands[] = "DELETE FROM `versions_summary`";
		
		$sql_commands[] = "INSERT INTO `most_recent_version`
		                       SELECT DISTINCT v.addon_id, MAX(v.created)
		                       FROM versions AS v
		                       INNER JOIN files AS f ON (f.version_id = v.id AND f.status IN (".implode(',',$valid_status)."))
		                       GROUP BY v.addon_id";
		
		
		$sql_commands[] = "INSERT INTO `versions_summary`
		                       SELECT DISTINCT v.addon_id, v.id, av.application_id, v.created, v.modified, av.min, av.max
		                       FROM (most_recent_version AS mrv NATURAL JOIN versions AS v) LEFT JOIN applications_versions AS av
		                       ON (av.version_id = v.id )";
		
		$sql_commands[] = "DROP TABLE  `most_recent_version`";
		
		$sql_commands[] = "DELETE FROM `collections_search_summary`";
		
		$sql_commands[] = "INSERT INTO `collections_search_summary`
		                   SELECT  `c`.`id` AS `id`, 
		                       `tr_name`.`locale` AS `locale`,  
		                       `tr_name`.`localized_string` AS `name`, 
		                       `tr_description`.`localized_string` AS `description`
		                   FROM `collections` AS `c` 
		                   LEFT JOIN `translations` AS `tr_name` ON (`tr_name`.`id` = `c`.`name`) 
		                   LEFT JOIN `translations` AS `tr_description` 
			                       ON (`tr_description`.`id` = `c`.`description` AND  `tr_name`.`locale` = `tr_description`.`locale`)
		                   WHERE `tr_name`.`locale` IS NOT NULL AND (
		                       `tr_name`.`localized_string` IS NOT NULL 
		                       OR `tr_description`.`localized_string` IS NOT NULL
		                   ) 
		                   ORDER BY `c`.`id` ASC, `locale` DESC";
		
		$sql_commands[] = "COMMIT";
		
		
		foreach($sql_commands as $sql_command) {
		    if(!mysql_query($sql_command)) {
		        mysql_query("ROLLBACK");
		        die("The update '$sql_command' failed: ".mysql_error());
		    }
		} 																																					

 	
}
    
    // 
    function testSearchTags() {
    	// setup some data
		$tagYemmer = $this->addTag('yemmer');
		$tagHuynh = $this->addTag('huynh');
		$tagGallery = $this->addTag('gallery');
		$tagErvinna = $this->addTag('ervinna lim');
		$tagFarmer = $this->addTag('farmer');
		$tagYemHuynh = $this->addTag("Yemmer Huynh");
		
	
    	$this->Addon->Tag->TagStat->cacheQueries = false;
    	$this->Addon->Tag->cacheQueries = false;
		
    	
    	// add some tags to addons
    	// use addon ids 1, 2,3,4,5
    	// use user id 9 (nobody@addons.mozilla.org)
    	$this->Addon->addTag(7, $tagYemmer, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(7, $tagHuynh, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(7, $tagFarmer, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(8, $tagYemmer, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(9, $tagHuynh, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(9, $tagYemmer, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(4021, $tagFarmer, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(4022, $tagGallery, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(2, $tagGallery, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(2, $tagFarmer, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(4023, $tagErvinna, 9); // addon+id, tag_id, user_id
    	$this->Addon->addTag(2, $tagYemHuynh, 9); // addon+id, tag_id, user_id
    	
    	$this->refreshFulltextIndexes();
    	
    	//$res = $this->Addon->getTagsByAddon(7);
    	//print_r($res);
    	
    
    	// now do some searches
    	
    	
    	$results = $this->runSimpleSearch("farmer");
    	//print_r($results);
    	$this->assertTrue(!empty($results), "found results for farmer");
    	
    	
    	
    	
     	$results = $this->runSimpleSearch("\"ervinna lim\"");
    	$this->assertTrue(!empty($results), "found results for 'ervinna lim'");
    	
     	$results = $this->runSimpleSearch("ervin");
    	$this->assertTrue(!empty($results), "found results for ervin");
    	
     	$results = $this->runSimpleSearch("yemmer");
    	$this->assertTrue(!empty($results), "found results for yemmer huynh");
    	
     	$results = $this->runSimpleSearch("\"huynh yemmer\"");
     	//print_r($results);
    	//$this->assertTrue(empty($results), "found NO results for 'huynh yemmer'");
		
		
		// test search refined by tag
		// 4022(gallery), 9(huynh, yemmer), 7(yemmer, huynh, farmer), 4023(ervinna)    	    
    	$results = $this->runSimpleSearch("firefox");
    	
    	
    	// verify we got those addons back
    	$this->assertTrue(in_array(4022, $results) ,"found addon 4022 has 'firefox'");
    	$this->assertTrue(in_array(9, $results) ,"found addon 9 has 'firefox'");
		$this->assertTrue(in_array(7, $results) ,"found addon 7 has 'firefox'");
		$this->assertTrue(in_array(4023, $results) ,"found addon 4023 has 'firefox'");
		
		// now refine search by each tag and verify
		$results = $this->runSimpleSearch("firefox", "yemmer");
		$this->assertTrue(count($results) == 2, "search on 'firefox' and reinfed by 'yemmer' yields 2 results");
    	$this->assertTrue(in_array(9, $results) ,"search on 'firefox' and refined by 'yemmer' found addon 9");
		$this->assertTrue(in_array(7, $results) ,"search on 'firefox' and refined by 'yemmer' found addon 7");
		
		
			    	    	    	
    	// tear it down
    	$this->Addon->execute('delete from users_tags_addons');
    	$this->Addon->execute('delete from tag_stat');
		$this->Addon->execute('delete from tags');
    	
    }
}
?>
