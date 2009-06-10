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
 * These Memcache tests are web tests so we can test how locale/
 * app changes affect caching
 */

class MemcachingWebTest extends WebTestHelper {
    var $id = 7;
    var $Cache; // holds the cache object
    
    function MemcachingWebTest() {
        loadModel('Memcaching');
        loadModel('Addon');
    }

    /**
     * Flush the cache before the test
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
    
    /**
     * Make sure caching pays attention to LANG
     */
    function testCachingRespectsLANG() {
        if (!QUERY_CACHE) return;

        // get German and English Addon data to compare with
        $this->Addon = new Addon();
        $this->Addon->caching = false;
        $this->Addon->cacheQueries = false;
        $no_controller = null;
        $this->Addon->setLang('de', $no_controller);
        $german = $this->Addon->findById($this->id); 
        $this->Addon->setLang('en-US', $no_controller);
        $english = $this->Addon->findById($this->id);
        // if the names are the same, this won't work...
        $this->assertNotEqual($german['Translation']['name']['string'], $english['Translation']['name']['string'], "English and German Strings of Addon #{$this->id} have to differ for cache test to work");

        $_uri = $this->actionURI("/addon/" . $this->id);
        $_en_uri = str_replace('/'.LANG.'/', '/en-US/', $_uri);
        $_de_uri = str_replace('/'.LANG.'/', '/de/', $_uri);
        
        // fetch the German page (this should get cached)
        $this->get($_de_uri);
        // check if it contains the German name
        $this->assertText($german['Translation']['name']['string'], 'German page contains German Addon name');
        // and, it obviously shouldn't have the English name
        $this->assertNoText($english['Translation']['name']['string'], 'German page does not contain English Addon name');

        // fetch the English page
        $this->get($_en_uri);
        // check if it contains the German name (in which case we incorrectly
        // got a cache hit)
        $this->assertNoText($german['Translation']['name']['string'], 'English page must not contain German Addon name');
        // but it should have the English name
        $this->assertText($english['Translation']['name']['string'], 'English page contains English Addon name');

    }

}
?>
