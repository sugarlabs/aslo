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
  
    function runSimpleSearch($terms) {
        return $this->controller->Search->search($terms);
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
        $this->assertTrue(!empty($results), "found results for collection name match");
    }

    function testSearchCollectionDescription() {
        $results = $this->runSimpleCollectionSearch('description');
        $this->assertTrue(!empty($results), "found results for collection description match");
    }
}
?>
