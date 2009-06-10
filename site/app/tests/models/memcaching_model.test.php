<?php /* ***** BEGIN LICENSE BLOCK *****
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
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Frederic Wenzel <fwenzel@mozilla.com> (Original Author)
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

/**
 * This tests if the Model interface to Memcached works as expected.
 */

class MemcachingModelTest extends UnitTestCase {
    var $Cache; // holds the cache object
    var $testdata = array('User' => array(
        'username' => 'johndoe',
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'johndoe@example.com'));
    // for object caching: identification array, arbitrary
    var $identifier = array('my_test_data', 1234, array('some', 'more', 'info'));

    /**
     * Constructor
     */
    function MemcachingModelTest() {
        loadModel('Memcaching');
    }
    
    /**
     * On load, load the memcache object, if the config says it should work.
     * Also set up an empty mem cache for each test.
     */
    function setUp() {
        if (!QUERY_CACHE) {
            $this->pass('Memcached caching is not activated.');
            return;
        }

        if (!$this->Cache) {
            // Memcache extension exists
            $this->assertTrue(class_exists('Memcache'), 'Memcache extension is installed');

            // Memcaching model is instanciable and connects to server
            $this->Cache = new Memcaching();
            $this->assertTrue($this->Cache->memcacheConnected, 'Memcaching model is instanciable and connects to server');
        }
        $this->Cache->flush();
    }
    
    function testSet() {
        if (!QUERY_CACHE) return;
        $this->assertTrue($this->Cache->set('testdata', $this->testdata), 'storing something in the memcache'); 
    }

    function testGet() {
        if (!QUERY_CACHE) return;
        $this->Cache->set('testdata', $this->testdata);
        $this->assertEqual($this->Cache->get('testdata'), $this->testdata, 'retrieving something from the memcache'); 
    }
        
    function testAdd() {
        if (!QUERY_CACHE) return;
        $this->assertTrue($this->Cache->add('testdata', $this->testdata), 'add() something new to the memcache'); 
        $this->assertFalse($this->Cache->add('testdata', array()), 'add() does not overwrite an existing key in the memcache'); 
    }

    function testReplace() {
        if (!QUERY_CACHE) return;
        $this->Cache->set('testdata', $this->testdata);
        $this->assertTrue($this->Cache->replace('testdata', array('something', 'else')), 'replace() replaces existing data in the memcache'); 
        $this->assertFalse($this->Cache->replace('doesntexist', $this->testdata), 'replace() does not add a key that did not exist before');
    }
        
    function testDelete() {
        if (!QUERY_CACHE) return;
        $this->Cache->set('testdata', $this->testdata);
        $this->assertTrue($this->Cache->delete('testdata'), 'deleting existing data out of the memcache succeeds');
        $this->assertFalse($this->Cache->get('testdata'), 'deleting actually works');
        $this->assertFalse($this->Cache->delete('cantdeletethis'), 'deleting nonexistant data returns false');
    }
        
    function testFlush() {
        if (!QUERY_CACHE) return;
        $this->Cache->set('testdata', $this->testdata);
        $this->assertTrue($this->Cache->flush(), 'flushing the cache succeeds');
        $this->assertFalse($this->Cache->get('testdata'), 'flushing the cache actually works');
    }
    
    /**
     * This test was added to make sure empty result sets are stored just
     * like every other piece of data.
     */
    function testStoreAndRetrieveEmpty() {
        if (!QUERY_CACHE) return;
        // storing an empty array in the memcache...
        $this->assertTrue($this->Cache->set('emptydata', array()), 'storing an empty array succeeds');
        
        // ... and retrieving it
        $_retrieved = $this->Cache->get('emptydata');
        $this->assertTrue(is_array($_retrieved) && empty($_retrieved), 'retrieving an empty array from the memcache works');
    }

    /**
     * Here we want to verify that when someone calls findAll() or query()
     * they still get unique results by $model->name.
     */
    function testUniqueRecordsByModelName() {
        if (!QUERY_CACHE) return;
        loadmodel('Addontype');
        loadmodel('Platform');

        // Load test models.
        $model1 =& new Addontype();
        $model2 =& new Platform();

        // Do one callback to load the cache.
        $model1->findAll(null, null, null, null, null, -1);
        $model2->findAll(null, null, null, null, null, -1);

        // Perform a second callback to see what the cache spits out the serialize.
        $addontypes = serialize($model1->findAll(null, null, null, null, null, -1));
        $platforms = serialize($model2->findAll(null, null, null, null, null, -1));

        $this->assertNotEqual($addontypes, $platforms, 'Addontypes and Platforms differed by model name when pulled from cache by findAll().');
    }

    /**
     * Test that we can get extended stats.
     */
    function testGetExtendedStats() {
        if (!QUERY_CACHE) return;
        $this->assertIsA($this->Cache->getExtendedStats(),'array');
    }

    /**
     * Check if memcache-ing does not store query errors
     */
    function testMemcacheNoErrors() {
        if (!QUERY_CACHE) return;

        loadmodel('Addon');
        $this->Addon = new Addon();

        // replace Memcaching object by mock object to catch unwanted calls
        Mock::generate('MyMemcaching');
        $this->Addon->Cache = new MockMyMemcaching();
        // assert that the model does not try to store the invalid result to memcache
        $this->Addon->Cache->expectNever('set', 'invalid query should not be cached');

        $invalidquery = 'SELECT something invalid'; // SELECT query that'll fail
        
        // execute invalid query
        @$this->Addon->query($invalidquery);
    }
    
    /* * * Tests for object caching * * */
    
    /**
     * Test the generation of a cache key
     */
    function testGenerateCacheKey() {
        if (!QUERY_CACHE) return;
        
        // make a second, different identifier array
        $ident2 = $this->identifier;
        array_pop($ident2);
        $ident2[] = 'something else';
        
        $key1 = $this->Cache->_generateCacheKey($this->identifier);
        $key2 = $this->Cache->_generateCacheKey($ident2);
        $this->assertFalse(empty($key1) || empty($key2), '_generateCacheKey() generating nonempty cache keys');
        
        $this->assertNotEqual($key1, $key2, 'different cache keys for different identifiers');
    }
    
    /**
     * Try to write, then read an object through the object caching interface,
     * including writing to an expiration list.
     */
    function testReadWriteCacheObject() {
        if (!QUERY_CACHE) return;
        
        Mock::generatePartial('Memcaching', 'Mock_testRWCacheObj', array('set', 'get',
            '_generateCacheKey', '_addObjectToExpirationList'));
        $mockCache =& new Mock_testRWCacheObj();
        
        // mock a known cache key
        $_mock_cachekey = 'amo_unittest_cachekey';
        $mockCache->setReturnValue('_generateCacheKey', $_mock_cachekey, array($this->identifier));
        
        // assertions for writing
        $mockCache->setReturnValue('set', true);
        $mockCache->expectOnce('set', array($_mock_cachekey, $this->testdata, '*', '*'),
            'storing object in cache');
        
        // assertions for reading
        $mockCache->expectOnce('get', array($_mock_cachekey), 'reading object back from cache');
        
        // assertions for expiration lists
        $_expiration_lists = array('addon' => array(1, 2));
        $startlist = array('amo_unittest_existing_cachekey');
        $mockCache->expectCallCount('_addObjectToExpirationList', 2);
        
        // write an object to the cache
        $mockCache->writeCacheObject($this->identifier, $this->testdata, $_expiration_lists);
        
        // read the same object back
        $mockCache->readCacheObject($this->identifier);
        
        
        // count calls
        $mockCache->tally();
    }
    
    /**
     * Test marking/flushing of expiration lists
     */
    function testExpirationListFlush() {
        if (!QUERY_CACHE) return;
        
        // generate empty flush list
        global $flush_lists;
        $flush_lists = array();
        
        Mock::generatePartial('Memcaching', 'Mock_testExpListFlush', array('get', 'delete'));
        $this->Cache =& new Mock_testExpListFlush();
        
        // add list ids to flush list
        $this->Cache->markListForFlush('list 1');
        $this->Cache->markListForFlush('list 2');
        $this->assertEqual(count($flush_lists), 2, 'adding items to cache flush list');
        
        // prepare a list, then have it flushed from memcache
        $flush_lists = array('list1', 'list2');
        $testlist1 = array('foo1', 'bar1');
        $testlist2 = array('foo2', 'bar2');
        $this->Cache->setReturnValue('get', $testlist1, array('list1'));
        $this->Cache->setReturnValue('get', $testlist2, array('list2'));
        // assert that the lists are going to be read
        $this->Cache->expectCallCount('get', 2);
        // ... and emptied (6 times: 2 times 2 objects in the lists, plus the lists themselves)
        $this->Cache->expectCallCount('delete', 6);
        
        $this->Cache->flushMarkedLists();
        
        $this->Cache->tally();
    }

}

/**
 * Mock memcache object
 */
class MyMemcaching {
    function get() {}
    function set() {}
}

?>
