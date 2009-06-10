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
 * Mozilla Corporation
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *    Frederic Wenzel <fwenzel@mozilla.com> (Original Developer)
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

class ParseRSSTest extends WebTestHelper {
    var $feeds = array();
     
    function ParseRSSTest() {
        $this->WebTestCase("Views->Search->RSS Parsing Tests");
        $this->feeds = array(
            '/addons/rss/newest', // @todo needs a page and a uniform feed URL
            '/recommended/format:rss',
            '/browse/type:'.ADDON_EXTENSION.'/format:rss',
            '/browse/type:'.ADDON_THEME.'/format:rss',
            '/browse/type:'.ADDON_SEARCH.'/format:rss',
            '/browse/type:'.ADDON_EXTENSION.'/cat:12/format:rss',
            '/browse/type:'.ADDON_EXTENSION.'/cat:all/format:rss',
            '/browse/type:'.ADDON_EXTENSION.'/cat:all/sort:name/format:rss',
            '/browse/type:'.ADDON_EXTENSION.'/cat:all/sort:popular/format:rss',
            '/browse/type:'.ADDON_EXTENSION.'/cat:all/sort:updated/format:rss',
            '/browse/type:'.ADDON_EXTENSION.'/cat:all/sort:rated/format:rss',
            '/browse/type:'.ADDON_EXTENSION.'/cat:all/sort:newest/format:rss',
            '/addons/versions/7/format:rss',
            '/reviews/display/7/format:rss',
        );
    }

    function setUp() {
    }
    
    function testRSSParsing() {
        // do a simple parse test for all feeds listed above.
        foreach ($this->feeds as $feed) {
            $this->getAction($feed);
            $this->assertWantedPattern("/rss version/", "correct template for ".htmlspecialchars($feed));
            $this->assertWantedPattern("/<item>/", "some results were found");
            $this->assertTrue($this->checkXML(), 'XML validation');
            $this->restart();
        }
    }
}
 
?>
