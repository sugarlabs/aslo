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

/**
 * This will setup cake to handle language/locales passed in via $_GET['lang'].
 * Keeping with the cake style, this is a class.
 */

class LANGUAGE_CONFIG
{

    var $default_language = 'en-US';

    var $_default_domain   = 'messages';

    var $text_domain      = null;

    var $current_language  = null;

    /**
     * If you add a language it needs to be in this array
     */
    var $_valid_languages  = array();
    
    /*
     * Languages for which we will sniff and redirect.
     */
    var $_supported_languages = array();

    /**
     * This fills in the text_domain (telling where the .mo files are), as well as
     * optionally setting the current language
     * 
     * @param array valid_languages array of languages that are available (with mapping)
     * @param array valid_languages array of languages that are shown in the dropdown at the bottom of the footer
     * @param boolean set_language If true, we'll detect and set the current language
     */
    function LANGUAGE_CONFIG($valid_languages, $supported_languages, $set_language=false)
    {
        // This is where our .mo files are.
        $this->text_domain = ROOT.DS.APP_DIR.DS.'locale';

        $this->_valid_languages = $valid_languages;
        $this->_supported_languages = $supported_languages;

        if ($set_language == true) {
            $this->setCurrentLanguage(array($this->detectCurrentLanguage()));
        }

    }

    /**
     * Will look at the following, in order for a valid language:
     *      1) $_GET['lang']
     *      2) the current QUERY_STRING
     *      4) (if $checkAcceptLang) HTTP_ACCEPT_LANG header
     *      3) $this->default_language (fallback)
     *
     * @param boolean check the accept lang header?  Only do this if the redirect isn't cached!
     * @return string language (eg. 'en-US' or 'ru')
     */
    function detectCurrentLanguage($checkAcceptLang = false)
    {
        $_match = array();

        $_lang_candidate = null;

        // First check $_GET['lang']
        if (array_key_exists('lang', $_GET)) {
            $_lang_candidate = $_GET['lang'];
        }

        // Second check the URL
        if (is_null($_lang_candidate)) {
            // Cake:
            // the QUERY_STRING comes in in the form (eg.):
            //      url=de-DE/addons/display/1
            //      or simply url=de with end of line ($) afterwards
            preg_match('/=(.+?)(\/|$)/', $_SERVER['QUERY_STRING'], $_match);

            if (!empty($_match[1])) {
                $_lang_candidate = $_match[1];
            }
        }

        // SpecialCase++
        if (strtolower($_lang_candidate) == 'ja-jp-mac') {
            $_lang_candidate = 'ja';
        }

        if ($checkAcceptLang && is_null($_lang_candidate)) {
            if ( array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ) {

                $acclang = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

                foreach ($acclang as $val) {
                    // The value of the accept language could have a semi-colon in it (for
                    // priority).  If it does, we explode, grab the first value, trim the
                    // whitespace, and we've got the locale.
                    $language = trim(array_shift(explode(';', $val)));

                    // Check if the language is one we support
                    if (in_array(strtolower($language), array_map('strtolower', array_keys($this->_supported_languages)))) {

                        $_lang_candidate = $language;
                        break; // found one

                    } else {

                        // If there is a dash, this will grab the short language name
                        $language = array_shift(explode('-', $language));

                        if (in_array(strtolower($language), array_map('strtolower',array_keys($this->_supported_languages)))) {
                            $_lang_candidate = $language;
                            break; // found one
                        } else {
                            $_lang_candidate = null; // (it's already null, but hey..)
                        }
                    }
                }
            }
            
        }

        // Third, if no detection worked, give the fallback
        if (is_null($_lang_candidate) || !in_array($_lang_candidate, array_keys($this->_valid_languages))) {
            $_lang_candidate = $this->getFallbackLanguage();
        }

        $this->current_language = $_lang_candidate;

        return $this->current_language;
    }

    /**
     * Simply returns the current language, or the default if one isn't set.
     *
     * @return string current language
     */
    function getCurrentLanguage() 
    {
        if (empty($this->current_language)) {
            return $this->getFallbackLanguage();
        }

        return $this->current_language;
    }

    /**
     * Runs all the appropriate gettext functions for setting the current language to
     * whatever is passed in (or the default).
     *
     * @param array languages to try (eg. array('en-US', 'ru'))
     * @param string domain basically, what is the name of your .mo file? (before the .)
     * @return boolean true on success, false on failure
     */
    function setCurrentLanguage($langs=array(), $domain=null)
    {
        // Used below - will fill with valid language mappings to try via setlocale()
        $languages_to_try = array();

        if (empty($langs)) {
            $langs = array($this->default_language);
        }

        if (empty($domain)) {
            $domain = $this->_default_domain;
        }

        // Setup text domains - these don't care what the language is.
        bindtextdomain($domain, $this->text_domain);
        bind_textdomain_codeset($domain, 'UTF-8');
        textdomain($domain);

        foreach ($langs as $lang) {
            // Double check they know what they are talking about.
            if (!in_array($lang, array_keys($this->_valid_languages))) {
                // Perhaps we should fallback to the default?
                continue;
            } else {
                // Our language exists, add _the mapping_ to our testing array
                $languages_to_try[] = $this->_valid_languages[$lang];
            }
        }

        // The locales on the local machine use underscores.  Verify this if you like 
        // by typing `localedef --list-archive` at a prompt
        $languages_to_try = str_replace('-','_',$languages_to_try);

        // Set the language.  We can't use LC_ALL here because it includes LC_CTYPE
        // and some languages (I'm looking at you Turkish!) will break php when
        // string functions are used
        $lang = setlocale(LC_COLLATE, $languages_to_try);
        if ($lang != false) {
            setlocale(LC_MONETARY, $lang);
            setlocale(LC_NUMERIC, $lang);
            setlocale(LC_TIME, $lang);
            if (defined('LC_MESSAGES')) {
                setlocale(LC_MESSAGES, $lang);
            }
        }

        // If this is failing, chances are good you don't have the locale installed
        // on your machine.  Run `localedef --list-archive` to see a list of
        // installed locales.
        if ($lang === false) {

            // Fallback to defaults
            $lang = trim(str_replace('-','_',$this->default_language));

            setlocale(LC_COLLATE, $lang);
            setlocale(LC_MONETARY, $lang);
            setlocale(LC_NUMERIC, $lang);
            setlocale(LC_TIME, $lang);
            if (defined('LC_MESSAGES')) {
                setlocale(LC_MESSAGES, $lang);
            }

            // Right now this is just used to give the language to the <head> block,
            // so it would be accurate to set it here, since we'll be using it to
            // draw the page.  This could lead to pages where the user has specified
            // $lang, but everything is coming up in en-US.  If this line is removed,
            // the page will think it's displaying $lang, and will display everything
            // it can in $lang, but english will appear to fill in the gaps.
            // Hopefully this is a corner case.
            $this->current_language = $this->default_language;

            // In reality, we should have still successfully set the language - it's
            // just not the language we wanted, so I'm returning false.
            return false;
        }

        // setlocale() has returned what it considers the language.  However, we're
        // remapping the languages in $valid_languages, and setlocale() has returned
        // a mapping - not the original language.  So, we need to lookup the old
        // value, and reset our $lang to the real language, not the mapping. 
        if (in_array($lang,array_values($this->_valid_languages))) {
            $lang = array_search($lang,$this->_valid_languages);
        }

        // Set LANG environmental variable. This is not optional for Windows.
        if (defined('WINDOWS')) {
            global $language_returns;

            // In order to find out what language was returned, we have to use a
            // different array of returned strings. They are in the format
            // German_Germany.1252
            if (preg_match("/^(.+)\./", $lang, $matches)) {
                $lang = $language_returns[$matches[1]];
                putenv("LANG={$lang}");
            }
        }

        // Switch our underscore back to a dash
        $this->current_language = str_replace('_','-',$lang);

        return true;
    }

    /**
     * Try to get a fallback language from the accept-language HTTP header.
     * If that fails, just go for the default.
     */
    function getFallbackLanguage() {
        // if we don't have a hint, fall back to default
        if (!array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER))
            return $this->default_language;
        
        $_al = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $_alparts = explode(',', $_al, 2);
        $_alparts = array_map('strtolower', $_alparts);
        $_alparts = array_map('trim', $_alparts);
        
        $_langscores = array();
        $_additional_langs = array();
        foreach ($_alparts as $_part) {
            if (strpos($_part, ';') !== false) {
                $_scoresplit = explode(';', $_part, 2);
                $_score = explode('=', $_scoresplit[1], 2);
                $_langscores[$_scoresplit[0]] = $_score[1];
                
                // if the requested lang is dashed, also allow more general form
                if (strpos($_scoresplit[0], '-') !== false) {
                    $_lang = substr($_scoresplit[0], 0, strpos($_scoresplit[0], '-'));
                    $_langscores[$_lang] = $_score[1]; 
                }
            } else {
                $_langscores[$_part] = 1;
                // if the requested lang is dashed, also allow more general form
                if (strpos($_part, '-') !== false) {
                    $_lang = substr($_part, 0, strpos($_part, '-'));
                    $_langscores[$_lang] = 1; 
                }
            }

        }
        arsort($_langscores, SORT_NUMERIC);
        
        foreach ($_langscores as $_lang => $_score) {
            foreach ($this->_supported_languages as $_valid_lang => $_mapping) {
                // O(n*m), sorry. But don't worry, the arrays are small.
                if (strpos(strtolower($_valid_lang), $_lang) === 0) return $_valid_lang;
            }
        }
        
        // if we get here we are really out of luck: just return the default
        return $this->default_language;
    }

    /**
     * Return an array of all valid languages.  This is safe to set() instead of publish().
     *
     * @param string names either 'english' or 'native' - how to return the full names
     * @param boolean includeAll whether to include an "All" option at the beginning of the array
     */
    static function getAllValidLanguages($names='english', $includeAll=false) {
        global $valid_languages;

        $localeDetails = new localeDetails();

        $xx_YY = array_keys($valid_languages);

        if ($includeAll) {
            array_unshift($xx_YY, 'all');
        }

        $locales = array();

        foreach ($xx_YY as $locale) {
            if ($locale == 'all') {
                $locales[$locale] = ___('general_languages_all_locales','All Locales');
            } else if ($names == 'english') {
                $locales[$locale] = $localeDetails->getEnglishNameForLocale($locale);
            } else if ($names == 'native') {
                $locales[$locale] = $localeDetails->getNativeNameForLocale($locale);
            }
        }

        return $locales;
        
    }
}
?>
