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
 *   Justin Scott <fligtar@mozilla.com>
 *   Wil Clouser <clouserw@mozilla.com>
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

/*
   Whitelist redirect add-on installation service (originally bug 400046)
   
   To work properly, an allowed site should link to this service like so:
     https://addons.mozilla.org/services/install.php?addon_id={addon_id}
   
   If the add-on is not hosted on AMO, it should be given an addon_key instead.
   
   Properties of the add-on entries:
        name [required]: Pretty name to display in xpinstall dialog and div
        xpi: url of the xpi [xpi OR link is required]
        link: url of the manual installation page [xpi OR link is required]
        hash: the file hash for installTrigger
        icon: the icon URL for xpinstall dialog
        referrers: array of allowed referrer regex
   
   XPI links must either be SSL or use a hash to verify that the user is getting the add-on they're supposed to.

   @todo if this is going to become widely used or expanded it should have some l10n work done
   
*/
define('REGEX_MOZILLA', '/^https?:\/\/[^\/]*\.?mozilla\.(com|org)\/?.*/');
define('REGEX_LOCALHOST', '/https?:\/\/[^\/]*\.?localhost.*/');
define('REGEX_PERSONAS', '/https?:\/\/[^\/]*\.?getpersonas\.com\/?.*/');

// If an add-on's referrers property is set, defaults will not be used unless specified
$default_referrers = array('document.referrer.match('.REGEX_MOZILLA.')',
                           'document.referrer.match('.REGEX_LOCALHOST.')');

$addons = array(
        /* Firefox Updated Page add-ons */
            'glubble' =>   array(
                    'name' => 'Glubble',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/5881',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/5881'
            ),
            'googletoolbar' => array(
                    'name' => 'Google Toolbar',
                    'link' => 'http://tools.google.com/tools/firefox/toolbar/FT3/intl/en/install.html'
            ),
            
            // Stumbleupon also used in Firefox 3 Get Personal
            138 => array(
                    'name' => 'StumbleUpon',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/138',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/138'
            ),
            
            // Foxytunes also used in Firefox 3 Get Personal
            219 => array(
                    'name' => 'Foxytunes',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/219',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/219'
            ),
            
            // Forecastfox also used in Firefox 3 Get Personal
            398 => array(
                    'name' => 'Forecastfox',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/398',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/398'
            ),
            424 => array(
                    'name' => 'Wizz RSS News Reader',
                    'link' => 'https://addons.mozilla.org/en-US/firefox/addons/policy/0/424/19068',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/424'
            ),
            1407 => array(
                    'name' => 'Clipmarks',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/1407'
            ),
            
            // Foxmarks also used in Firefox 3 Get Personal
            2410 => array(
                    'name' => 'Foxmarks',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/2410',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/2410'
            ),
            3348 => array(
                    'name' => 'Pronto Shopping Messenger',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/3348'
            ),
            3945 => array(
                    'name' => 'Fotofox',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/3945'
            ),
        
        /* Firefox 3 Get Personal page */
            // Forecastfox (see above)
            
            5202 => array(
                    'name' => 'eBay Companion',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/5202/platform:5/',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/5202'
            ),
            
            // Stumbleupon (see above)
            
            // Foxmarks (see above)
            
            // Foxytunes (see above)
        
        /* Other */
            'personas' => array(
                    'name' => 'Personas for Firefox',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/10900',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/10900/1236031798',
                    'referrers' => array_merge($default_referrers, array(
                            'document.referrer.match('.REGEX_PERSONAS.')'
                    ))
            ),
            
            'weave' => array(
                    'name' => 'Weave',
                    'xpi' => 'https://addons.mozilla.org/en-US/firefox/downloads/latest/10868',
                    'icon' => 'https://addons.mozilla.org/en-US/firefox/images/addon_icon/10868/1236131155'
            )
    );


    // If we have an addon_id, use it. Otherwise, look for an addon_key.
    if (!empty($_GET['addon_id'])) {
        $addon = $addons[$_GET['addon_id']];
    } elseif (!empty($_GET['addon_key'])) {
        $addon = $addons[$_GET['addon_key']];
    }

    // We have a link to another page where the add-on is available.  Redirect them
    // to that page.
    if (!empty($addon['link'])) {
        header("Location: {$addon['link']}");
        exit;
    }

    // There was no external link, and there is no xpi information - they've asked
    // for an invalid or unavailable xpi. Send a 404.
    if (empty($addon['xpi'])) {
        header('HTTP/1.0 404 Not Found', true, 404);
        exit;
    }
    

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>AMO Install Service</title>
<script language="JavaScript" type="text/javascript" src="/js/__utm.js"></script>
<script type="text/javascript">
    function install() {
        if (<?=implode(' || ', (!empty($addon['referrers']) ? $addon['referrers'] : $default_referrers))?>) {
            var params = {
            <?php
              echo "'".htmlentities($addon['name'])."': {";
              echo "URL: '".htmlentities($addon['xpi'])."',";
              echo !empty($addon['icon']) ? "IconURL: '".htmlentities($addon['icon'])."'," : '';
              echo !empty($addon['hash']) ? "Hash: '".htmlentities($addon['hash'])."'," : '';
            ?>
                    toString: function () { return this.URL; }
              }
            };
            InstallTrigger.install(params, goBack);
        } else {
            window.location.href = 'https://addons.mozilla.org/';
        }
    }

    function goBack() {
        history.back();
    }
</script>

<style type="text/css">
    .manualinstall {
        text-align: center;
        color: #b94413;
        font-family:arial,verdana,sans-serif;
        padding: 20px;
        font-size: 17px;
    }
    .manualinstall a {
        color: #b94413;
        font-weight: bold;
    }
</style>
</head>

<body onload="install()">
    <div class="manualinstall">
        If you are not prompted to install <?=$addon['name']?> in a moment, please <a href="<?=(!empty($addon['xpi']) ? $addon['xpi'] : $addon['link'])?>">click here</a>.
    </div>
</body>
</html>

