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

include_once APP_PATH.'config/bootstrap.php';

class LanguageConfigTest extends WebTestHelper {

    var $language_config;

	function setUp() 
    {
        // From bootstrap.php
        global $valid_languages, $supported_languages;

        $this->language_config = new LANGUAGE_CONFIG($valid_languages, $supported_languages, false);

	}

    /**
     * Walk through our languages array and make sure all the language files exist.
     */
    function testLangFilesExist() 
    {
        global $valid_languages;

        $text_domain = $this->language_config->text_domain;

        foreach ($valid_languages as $lang => $map) {
            // So, gettext() has some logic built into it.  If the current lang is
            // set to, say, 'en-US' but there is no 'en-US' directory, it will fall
            // back to 'en' (if it exists).  We'll emulate this behavior here.

            // First file we'll look for
            $lang_file = "{$text_domain}/{$map}/LC_MESSAGES/messages.mo";

            if (file_exists($lang_file)) {
                //it's there, we're good to go.
                continue;
            }

            // Check to see if our lang is a utf locale, and fallback on the non-utf map
            // if the initial map has failed.
            if (strpos($map,'.utf8')!==false) {
                $buf = split('\.',$map);

                if (file_exists("{$text_domain}/{$buf[0]}/LC_MESSAGES/messages.mo")) {
                    // Our utf-> non-utf fallback works, yay.
                    continue;
                }
            }

            // Is a default lang always 2 characters, or should we look for a dash?
            $map2 = substr($lang,0,2);

            if (file_exists("{$text_domain}/{$map2}/LC_MESSAGES/messages.mo")) {
                // Our fallback works, yay.
                continue;
            }

            // Bad things
            $this->fail("Couldn't find language file for ( {$lang} ) - looked for ( {$map} ) and ( {$map2} ).");
        }

    }

    /**
     * Our msgids should conform to the AMO L10n standards. Details at
     * https://wiki.mozilla.org/Update:Remora_Localization#L10n_standards
     *
     * We'll only check en-US here, but the others are built off en-US so it should be a good indicator.
     */
    function testLangFileIsValid() {
        $_language_file = "{$this->language_config->text_domain}/en_US/LC_MESSAGES/messages.po";

        $_language_file_contents = file_get_contents($_language_file);

        // If this test fails there is a string with spaces in it which is most likely an English string or 
        // sentence.  This should be fixed before merging en-US -> all locales
        $this->assertEqual(preg_match('/^msgid\ ".*?\ .*?"$/m', $_language_file_contents), 0);
    }

    /**
     * Checks and makes sure index pages will load with all valid languages
     */
    function testLocalizedPagesLoad()
    {
        global $valid_languages;

        $this->WebTestCase("Localized Pages aren't 404's Test :)");
        foreach ($valid_languages as $lang => $mapping) {
            $this->getPath($this->rawPath("/{$lang}"));
            $this->assertResponse(array('200','301','302'), "Loading " . $this->rawPath("/{$lang}"));
        }

        // something to note, if cake is *not* in production mode (DEBUG != 0) the pages
        // that don't exist actually come back with a 200 code instead of 404, which
        // means this test will only pass in production. :-/ -- clouserw
        $this->getAction("/xx-YY/");
        if (DEBUG == 0)
            $this->assertResponse(array('404'));
        else
            $this->assertResponse(array('200'));
    }

    /**
     * Make sure we can detect languages from the URL
     */
    function testDetectCurrentLanguage() 
    {
        $_temp = $_SERVER['QUERY_STRING'];

        $_SERVER['QUERY_STRING'] = '';

        // First part of the test is to try it with nothing - we should get back the default language.
        $this->assertEqual($this->language_config->detectCurrentLanguage(), $this->language_config->default_language);

        // While we're here, try it with the lang in $_GET
        $_GET['lang'] = 'de';
        $this->assertEqual($this->language_config->detectCurrentLanguage(), 'de');
        unset($_GET['lang']);

        // Next we'll try it with a language that doesn't exist:
        $_SERVER['QUERY_STRING'] = 'url=xx-YY/addon/1';
        $this->assertEqual($this->language_config->detectCurrentLanguage(), $this->language_config->default_language);

        // And finally, with one that does exist:
        $_SERVER['QUERY_STRING'] = 'url=fr/addon/1';
        $this->assertEqual($this->language_config->detectCurrentLanguage(), 'fr');

        // we'll put this back
        $_SERVER['QUERY_STRING'] = $_temp;
    }

    /**
     * Make sure the current language is set
     */
    function testGetCurrentLanguage() 
    {
        // This isn't much of a test, but really, there isn't much to test. We can't
        // use assertNotNull because the WebTestCase class doesn't have it :(
        $this->assertNotEqual($this->language_config->getCurrentLanguage(), null);
    }

    /**
     * Make sure we can set languages
     */
    function testSetCurrentLanguage()
    {
        // Try it with a bad language - should return false
        $this->assertFalse($this->language_config->setCurrentLanguage(array('xx_YY')));


        // Try it with a good language - should return true
        $this->assertTrue($this->language_config->setCurrentLanguage(array('en-US')));
    }

    /**
     * Test language setting via $_GET['lang'] arg passed by footer form.
     * This is actually done in bootstrap, but this was a relevant place to put it.
     */
    function testBootstrapLanguageRedirect() {
        global $valid_languages;

        $this->WebTestCase("Testing that bootstrap allows users to choose a language.");
        $this->_browser->skipParse = True;
        foreach ($valid_languages as $lang => $mapping) {
            // Bootstrap will do one redirect to switch lang.
            $this->setMaximumRedirects(1);

            // Check that $_GET overrides the rest of the URL
            $path = $this->actionPath("/?lang={$lang}");
            $this->getPath($path);

            // If we pass a valid language, our page should redirect us. 
            // and should also have the correct URL.
            $this->assertPattern('/lang="'.$lang.'"/', "Language {$lang} set for {$path}.");

            // Bootstrap will do two redirects for app and lang if neither are present.
            $this->setMaximumRedirects(2);

            $path = $this->rawPath("/?lang={$lang}");
            $this->getPath($path);

            // If we pass a valid language, our page should redirect us. 
            // and should also have the correct URL.
            $this->assertPattern('/lang="'.$lang.'"/', "Language {$lang} set for {$path}.");
            
            // Bootstrap should only do one redirect if there's an app present, but no lang
            $this->setMaximumRedirects(1);
            
            $path = $this->rawPath("/firefox/?lang={$lang}");
            $this->getPath($path);
            // If we pass a valid language, our page should redirect us. 
            // and should also have the correct URL.
            $this->assertPattern('/lang="'.$lang.'"/', "Language {$lang} set for {$path}.");
            
        }

        // If we pass an unknown language, there should be a redirect to a detected lang
        $this->setMaximumRedirects(1);
        $this->getPath($this->actionPath("")."/browse?lang=carebears");
        $this->assertResponse('200','Redirect to detected language for bogus language');
    }

    /**
     * Is the Accept-Language Request Header correctly interpreted?
     */
    function testRegularAcceptHeader() {
        $this->addHeader('Accept-Language: en-us;q=0.7, de');
        $this->getPath($this->rawPath('/firefox'));
        $pattern = '#Alle Rechte vorbehalten#';
        $this->assertPattern($pattern, 'Accept-language handler picks right locale according to score');
    }
    
    /**
     * Is the AL header ignored if the URL contains a locale?
     */
    function testURLLocaleIgnoresALHeader() {
        $this->addHeader('Accept-Language: de');
        $this->getPath($this->rawPath('/en-US/firefox/'));
        $pattern = '#All rights reserved#';
        $this->assertPattern($pattern, 'Accept-language is ignored when URL contains locale');
    }
    
    /**
     * Is 'de-de' in the AL header correctly resolved to 'de'?
     */
    function testALUnsharpMatching() {
        $this->addHeader('Accept-Language: de-de, en-us;q=0.3');
        $this->getPath($this->rawPath(''));
        $pattern = '#Alle Rechte vorbehalten#';
        $this->assertPattern($pattern, 'Accept-language handler resolves de-de to de');
    }

    /**
     * Does a malformed AL header lead to a correct fallback?
     */
    function testMalformedALIgnored() {
        $this->addHeader('Accept-Language: some,thing-very;very,,malform,ed!');
        $this->getPath($this->actionPath('/browse'));
        $pattern = '#All rights reserved#';
        $this->assertPattern($pattern, 'Malformed Accept-language leads to fallback');
    }
    
    /**
     * Do we fall back to the default when the langs requested are not supported?
     */
    function testUnsupportedALFallback() {
        $this->addHeader('Accept-Language: xx-yy, xx;q=0.5');
        $this->getPath($this->actionPath(''));
        $pattern = '#All rights reserved#';
        $this->assertPattern($pattern, 'Unsupported AL header request falls back to default');
    }
}
?>
