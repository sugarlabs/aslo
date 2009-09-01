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
 *   Mike Morgan <morgamic@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
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
 * Load the Mozilla locale details class from our product-details external.
 */
vendor('product-details/localeDetails.class');
$localeDetails = new localeDetails();

/**
 * This is declared global because we need it in routes.php.  This is the main
 * language array!  If you have a language you want to add, this is the place to do
 * it.  Make sure you add the appropriate /\.(m|p)o/ files as well under /locale/
 */
global $valid_languages;
global $supported_languages;
global $rtl_languages;

/**
 * This array is in the form:
 *      $original  =>  $mapping
 * Where $original is what is in the url, and $mapping is the locale on your server.
 * For example, the URL moz.com/de/browse/21  would make $original="de".  If you have
 * a "de" locale on your server, awesome, make $mapping="de" and you're done.  If you
 * want it to map to de_DE or de_DE.utf8, then change the mapping to be one of those.
 * You can see what locales you have installed on your server with `localedef
 * --list-archive`.  If you pick a mapping that doesn't exist, your pages will come
 * out in your default language (set in the LANGUAGE_CONFIG class).  Also note the
 * mapping uses underscores not dashes - chances are good this is what you want.
 */
$supported_languages = array(
    'ar'    => 'ar_EG.utf8',
    'ca'    => 'ca_ES.utf8',
    'cs'    => 'cs_CZ.utf8',
    'da'    => 'da_DK.utf8',
    'de'    => 'de_DE.utf8',
    'en-US' => 'en_US.utf8',
    'el'    => 'el_GR.utf8',
    'es-ES' => 'es_ES.utf8',
    'eu'    => 'eu_ES.utf8',
    'fa'    => 'fa_IR.utf8',
    'fi'    => 'fi_FI.utf8',
    'fr'    => 'fr_FR.utf8',
    'ga-IE' => 'ga_IE.utf8',
    'he'    => 'he_IL.utf8',
    'hu'    => 'hu_HU.utf8',
    'id'    => 'id_ID.utf8',
    'it'    => 'it_IT.utf8',
    'ja'    => 'ja_JP.utf8',
    'ko'    => 'ko_KR.utf8',
    'mn'    => 'mn_MN.utf8',
    'nl'    => 'nl_NL.utf8',
    'pl'    => 'pl_PL.utf8',
    'pt-BR' => 'pt_BR.utf8',
    'pt-PT' => 'pt_PT.utf8',
    'ro'    => 'ro_RO.utf8',
    'ru'    => 'ru_RU.utf8',
    'sk'    => 'sk_SK.utf8',
    'sq'    => 'sq_AL.utf8',
    'sv-SE' => 'sv_SE.utf8',
    'uk'    => 'uk_UA.utf8',
    'vi'    => 'vi_VN.utf8',
    'zh-CN' => 'zh_CN.utf8',
    'zh-TW' => 'zh_TW.utf8'
);

// Languages that work, but to which we won't send a user ourselves (dropdown, lang sniffing)
$valid_languages = array(
    'cy'      => 'cy_GB.utf8',
    'sr'      => 'sr_CS.utf8',
    'sr-Latn' => 'sr_CS.utf8',
    'tr'      => 'tr_TR.utf8'
);

/**
 * If a supported language is displayed right to left, add it to this array.
 */
$rtl_languages = array( 'ar', 'fa', 'fa-IR', 'he' );

/**
 * Windows uses ISO Alpha-3 locales found here:
 * http://msdn.microsoft.com/library/default.asp?url=/library/en-us/vclib/html/_crt_language_strings.asp
 * http://msdn.microsoft.com/library/default.asp?url=/library/en-us/vclib/html/_crt_country_strings.asp
 * http://www.microsoft.com/globaldev/reference/winxp/langtla.mspx
 * or http://www.unicode.org/onlinedat/countries.html
 * and http://unicode.org/onlinedat/languages.html
 * So we redeclare the array using those if WINDOWS is set in bootstrap
 */
if (defined('WINDOWS')) {
    $supported_languages = array(
        // ar
        //'ca' => '', // I couldn't find this language
        'cs'    => 'csy_CZE',
        'da'    => 'dan_DNK',
        'de'    => 'deu_DEU',
        'en-US' => 'usa_USA',
        'el'    => 'ell_GRC',
        'es-ES' => 'esp_ESP',
        'eu'    => 'baq_ESP',
        // fa
        'fi'    => 'fin_FIN',
        'fr'    => 'fra_FRA',
        //'ga-IE' => '', // Not sure
        'he'    => 'heb', // someone with windows double check?
        'id'    => 'ind_IND',
        'it'    => 'ita_ITA',
        'ja'    => 'jpn_JPN',
        'ko'    => 'kor_KOR',
        'mn'    => 'mon_MNG',
        'nl'    => 'nld_NLD',
        'pl'    => 'plk_POL',
        'pt-BR' => 'ptb_BRA',
        'pt-PT' => 'ptb_PRT',
        'ro'    => 'rou_ROU',
        'ru'    => 'rus_RUS',
        'sk'    => 'sky_SVK',
        'sq'    => 'sqi_ALB',
        //'sv-SE' => '' // unknown
        'uk'    => 'ukr_UKR',
        'zh-CN' => 'chs_CHN',
        'zh-TW' => 'cht_TWN'
    );

    $valid_languages = array(
        //cy
        //sr
        //sr-Latn
        'tr'    => 'trk_TUR',
        //'vi'    => 'vi_VN.utf8'
    );

    global $language_returns;

   /**
    * We also need to have language returns which are returned upon successful
    * loading of the language.
    * http://msdn2.microsoft.com/en-us/library/system.globalization.cultureinfo(VS.80).aspx
    */
    $language_returns = array(
        // ar
        //'ca' => '', // I couldn't find this language
        //cy
        'Czech_Czech Republic'  => 'cs',
        'Danish_Denmark'        => 'da_DK',
        'Dutch_Netherlands'     => 'nl',
        'German_Germany'        => 'de',
        'English_United States' => 'en_US',
        'Spanish_Spain'         => 'es_ES',
        'Greek'                 => 'el', // someone with windows double check?
        //'ga-IE' => '', // Not sure
        'Basque_Basque'         => 'eu',
        'Finnish_Finland'       => 'fi',
        'French_France'         => 'fr',
        'Hebrew'                => 'he', // someone with windows double check?
        'Indonesian_Indonesia'  => 'id',
        'Italian_Italy'         => 'it',
        'Japanese_Japan'        => 'ja',
        'Korean_Korea'          => 'ko',
        'Mongolian_Mongolia'    => 'mn',
        'Polish_Poland'         => 'pl',
        'Portuguese_Brazil'     => 'pt_BR',
        'Portuguese_Portugal'   => 'pt_PT',
        'Romanian_Romania'      => 'ro',
        'Russian_Russia'        => 'ru',
        'Slovak_Slovakia'       => 'sk',
        'Albanian_Albania'      => 'sq',
        'Turkish_Turkey'        => 'tr',
        'Ukranian_Ukraine'      => 'uk',
        //vi
        'Chinese_China'         => 'zh_CN',
        'Chinese_Taiwan'        => 'zh_TW'
    );
}

// If we're not on preview or DEBUG isn't set then remove languages that aren't ready to be
// supported on the site.  This lets localizers preview their languages but prevents them from
// appearing on the live site.
if (! (DEBUG || $_SERVER['HTTP_HOST'] == 'preview.addons.mozilla.org') ) {
    $valid_languages = array();
}

$valid_languages = array_merge($supported_languages, $valid_languages);

/**
 * This is declared global so it can be used in views.  It is used specifically in the
 * site footer and developer/localizer/admin pages.
 */
global $native_languages;
$native_languages = $localeDetails->languages;

/**
 * Set the default internal encoding for multi-byte string functions.
 * This way we can safely leave out the optional encoding parameter
 * for all mb_* function calls.
 */
mb_internal_encoding('UTF-8');

?>
