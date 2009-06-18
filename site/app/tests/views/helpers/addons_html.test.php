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
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *   
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
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

class AddonsHtmlHelperTest extends UnitTestCase {

    var $html = null;   // the helper
	
	function setUp() {

	    
        static $tags;
		loadHelper('AddonsHtml');
        loadModel('Addon');
        $this->Addon = new Addon();
        $this->html =& new AddonsHtmlHelper();
        $this->html->webroot = '';
        $this->html->here    = '';
        $this->html->base    = 'http://example.com/mybase';
        $this->html->plugin  = '';
        $this->html->params  = array('controller' => 'controller');
        if (empty($tags)) $tags = $this->html->loadConfig();
        $this->html->tags = $tags;
	}
   
    function tearDown() {
        unset($this->html);
    }
    
    /**
     * is link generated with locale?
     */
    function testLinkLocale() {
        $expected = '!<a href="http://example.com/mybase/'.LANG.'/'.APP_SHORTNAME.'/browse"( )*>foo</a>!';
        $result = $this->html->link('foo', '/browse');
        $this->assertTrue(preg_match($expected, $result), 'link generation with locale');
    }

    /**
     * is link generated without locale?
     */
    function testLinkNoLocale() {
        $expected = '!<a href="http://example.com/mybase/'.APP_SHORTNAME.'/browse"( )*>foo</a>!';
        $result = $this->html->linkNoLocale('foo', '/browse');
        $this->assertTrue(preg_match($expected, $result), 'link generation without locale');
    }
    
    /**
     * is link generated without locale and app?
     */
    function testLinkNoLocaleNoApp() {
        $expected = '!<a href="http://example.com/mybase/browse"( )*>foo</a>!';
        $result = $this->html->linkNoLocaleNoApp('foo', '/browse');
        $this->assertTrue(preg_match($expected, $result), 'link generation without locale or app');
    }

    /**
     * sanitized strings can be reverted
     */
    function testUnsanitize() {
        $test = '&lt;a href=&quot;addons.mozilla.org&quot;&gt;Mozilla&#039;s addon website &#40;AMO&#41;&lt;/a&gt;. &#43;/&#45;3.1&#37;. &amp;amp;';
        $expected = '<a href="addons.mozilla.org">Mozilla\'s addon website (AMO)</a>. +/-3.1%. &amp;';
        $result = $this->html->unsanitize($test);
        $this->assertEqual($expected, $result, 'sanitized strings can be reverted');
    }

    function testTruncate() {
        $this->assertEqual('abc', $this->html->truncateChars(5, 'abc'));
        $this->assertEqual('abcde', $this->html->truncateChars(5, 'abcde'));
        $this->assertEqual('ab...', $this->html->truncateChars(5, 'abcdef'));
    }
    
    /**
     * Tests that an addon that has the addons_tags.feature = 1 is shown as featured
     */
    function testRecommendedFlag() {
        $addon = $this->Addon->getListAddons(4021, array(STATUS_PUBLIC), '', true);
        $addon = $addon[0];
        $result = $this->html->flag($addon);
        phpQuery::newDocument($result);
        $this->assertEqual(pq('h5.flag a')->text(), 'recommended', 'Category recommended and recommended addons are flagged as recommended');
    }

    function testAppendParametersToUrl() {
        $url = 'http://www.mozilla.com';
        $this->assertEqual($this->html->appendParametersToUrl($url, array('var' => 'val')), "$url?var=val");
        $this->assertEqual($this->html->appendParametersToUrl($url, array('var' => 'val', 'foo' => 'bar')), "$url?var=val&foo=bar");

        $url = 'http://www.mozilla.com?blah';
        $this->assertEqual($this->html->appendParametersToUrl($url, array('var' => 'val')), "$url&var=val");
        $this->assertEqual($this->html->appendParametersToUrl($url, array('var' => 'val', 'foo' => 'bar')), "$url&var=val&foo=bar");

        $url = 'http://www.mozilla.com?';
        $this->assertEqual($this->html->appendParametersToUrl($url, array('var' => 'val')), "{$url}var=val");

        $url = 'http://www.mozilla.com?x&';
        $this->assertEqual($this->html->appendParametersToUrl($url, array('var' => 'val')), "{$url}var=val");
    }
}
?>
