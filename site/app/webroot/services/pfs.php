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
 *      Vladimir Vukicevic <vladimir@pobox.com>
 *      Doron Rosenberg <doronr@us.ibm.com>
 *      Johnny Stenback <jst@mozilla.org>
 *      Mike Morgan <morgamic@mozilla.com>
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
 * Plugin Finder Service
 *
 * The purpose of this script is to determine a matching plugin based on mime-type
 * for an embedded HTML object, then return the correct information for a corresponding plugin.
 * 
 * @package remora 
 * @subpackage docs
 * @TODO figure out database structure for plugin addontype
 * @TODO clean this ____ up and make the script depend on the database
 *
 */

/**
 * Set variables.  We are allowing these to come in since they are compared to regular
 * expressions eventually anyway.  So yes, we are aware they are coming from $_GET.
 */
$mimetype = isset($_GET['mimetype']) ? $_GET['mimetype'] : null;
$reqTargetAppGuid = isset($_GET['appID']) ? $_GET['appID'] : null;
$reqTargetAppVersion = isset($_GET['appVersion']) ? $_GET['appVersion'] : null;
$clientOS = isset($_GET['clientOS']) ? $_GET['clientOS'] : null;
$chromeLocale = isset($_GET['chromeLocale']) ? $_GET['chromeLocale'] : null;

// Set the version string, which is stored in appRelease, not appVersion (see bug 433615)
$appRelease = isset($_GET['appRelease']) ? $_GET['appRelease'] : null;

/**
 * Only execute if we have proper variables passed from the client.
 */
if (!empty($mimetype) &&
    !empty($reqTargetAppGuid) &&
    !empty($reqTargetAppVersion) &&
    !empty($clientOS) &&
    !empty($chromeLocale)) {

    /**
     * Figure out what plugins we've got, and what plugins we know where
     * to get.
     */
    $name = '';
    $guid = '-1';
    $version = '';
    $iconUrl = '';
    $XPILocation = '';
    $InstallerLocation = '';
    $InstallerHash = '';
    $InstallerShowsUI = 'true';
    $manualInstallationURL = '';
    $licenseURL = '';
    $needsRestart = 'true';

    /**
     * Begin our huge and embarrassing if-else statement.
     */
    if (($mimetype == 'application/x-shockwave-flash' ||
         $mimetype == 'application/futuresplash') &&
         preg_match('/^(Win|(PPC|Intel) Mac OS X|Linux.+i\d86)|SunOs/i', $clientOS)) {
        // We really want the regexp for Linux to be /Linux(?! x86_64)/ but
        // for now we can't tell 32-bit linux appart from 64-bit linux, so
        // feed all x86_64 users the flash player, even if it's a 32-bit
        // plugin

        // We've got flash plugin installers for Win and Linux (x86),
        // present those to the user, and for Mac users, tell them where
        // they can go to get the installer.

        $name = 'Adobe Flash Player';
        $manualInstallationURL = 'http://www.adobe.com/go/getflashplayer';

        // Don't use a https URL for the license here, per request from
        // Macromedia.
        if ($chromeLocale != 'ja-JP') {
            $licenseURL = 'http://www.adobe.com/go/eula_flashplayer';
        } else {
            $licenseURL = 'http://www.adobe.com/go/eula_flashplayer_jp';
        }

        if (preg_match('/^Windows NT 6\.0/', $clientOS) && preg_match('/^3\.5.*/', $appRelease)) {
            $guid = '{4cfaef8a-a6c9-41a0-8e6f-967eb8f49143}';
            $XPILocation = null;
            $licenseURL = null;
            $version = '10.0.32';
            $InstallerHash = 'sha256:f158f44911146b61f0dea0851fb35cdc812f0786297ba11745fba32a4f8b06d2';
            $InstallerLocation = 'http://fpdownload2.macromedia.com/pub/flashplayer/current/FP_PL_PFS_INSTALLER.exe';
            $iconUrl = 'http://fpdownload2.macromedia.com/pub/flashplayer/current/fp_win_installer.ico';
            $needsRestart = 'false';
            $InstallerShowsUI = 'false';
        } else if (preg_match('/^Win/', $clientOS)) {
            $guid = '{4cfaef8a-a6c9-41a0-8e6f-967eb8f49143}';
            $XPILocation = 'http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-win.xpi';
            $InstallerShowsUI = 'false';
        } else if (preg_match('/^Linux/', $clientOS)) {
            $guid = '{7a646d7b-0202-4491-9151-cf66fa0722b2}';
            $XPILocation = 'http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-linux.xpi';
            $InstallerShowsUI = 'false';
        } else if (preg_match('/^(PPC|Intel) Mac OS X/', $clientOS)) {
            $guid = '{89977581-9028-4be0-b151-7c4f9bcd3211}';
            $XPILocation = 'http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-mac.xpi';
        } else if (preg_match('/^SunOs/i', $clientOS)) {
            $guid = '{0ae66efd-e183-431a-ab51-3af2c278a3dd}';
            if (preg_match('/sun4u/i', $clientOS)) {
                $XPILocation = 'http://download.macromedia.com/pub/flashplayer/xpi/current/flashplayer-solaris-sparc.xpi';
            } else {
                $XPILocation = 'http://download.macromedia.com/pub/flashplayer/xpi/current/flashplayer-solaris-x86.xpi';
            }
        } 
    } elseif ($mimetype == 'application/x-director' &&
              preg_match('/^(Win|PPC Mac OS X)/', $clientOS)) {
        $name = 'Macromedia Shockwave Player';
        $manualInstallationURL = 'http://www.adobe.com/go/getshockwave/';
        $version = '10.1';

        // Even though the shockwave installer is not a silent installer, we
        // need to show its EULA here since we've got a slimmed down
        // installer that doesn't do that itself.
        if ($chromeLocale != 'ja-JP') {
            $licenseURL = 'http://www.adobe.com/go/eula_shockwaveplayer';
        } else {
            $licenseURL = 'http://www.adobe.com/go/eula_shockwaveplayer_jp';
        }

        if (preg_match('/^Win/', $clientOS)) {
            $guid = '{45f2a22c-4029-4209-8b3d-1421b989633f}';

            if ($chromeLocale == 'ja-JP') {
                $XPILocation = 'https://www.macromedia.com/go/xpi_shockwaveplayerj_win';
            } else {
                $XPILocation = 'https://www.macromedia.com/go/xpi_shockwaveplayer_win';
            }
        } else if (preg_match('/^PPC Mac OS X/', $clientOS)) {
            $guid = '{49141640-b629-4d57-a539-b521c4a99eff}';

            if ($chromeLocale == 'ja-JP') {
                $XPILocation = 'https://www.macromedia.com/go/xpi_shockwaveplayerj_macosx';
            } else {
                $XPILocation = 'https://www.macromedia.com/go/xpi_shockwaveplayer_macosx';
            }
        }
    } elseif (($mimetype == 'audio/x-pn-realaudio-plugin' ||
	           $mimetype == 'audio/x-pn-realaudio') &&
               preg_match('/^(Win|Linux|PPC Mac OS X)/', $clientOS)) {
        $name = 'Real Player';
        $version = '10.5';
        $manualInstallationURL = 'http://www.real.com';

        if (preg_match('/^Win/', $clientOS)) {
            $XPILocation = 'http://forms.real.com/real/player/download.html?type=firefox';
            $guid = '{d586351c-cb55-41a7-8e7b-4aaac5172d39}';
        } else {
            $guid = '{269eb771-59de-4702-9209-ca97ce522f6d}';
        }
    } elseif (preg_match('/^(application\/(sdp|x-(mpeg|rtsp|sdp))|audio\/(3gpp(2)?|AMR|aiff|basic|mid(i)?|mp4|mpeg|vnd\.qcelp|wav|x-(aiff|m4(a|b|p)|midi|mpeg|wav))|image\/(pict|png|tiff|x-(macpaint|pict|png|quicktime|sgi|targa|tiff))|video\/(3gpp(2)?|flc|mp4|mpeg|quicktime|sd-video|x-mpeg))$/', $mimetype) &&
	          preg_match('/^(Win|PPC Mac OS X)/', $clientOS)) {
        //
        // Well, we don't have a plugin that can handle any of those
        // mimetypes, but the Apple Quicktime plugin can. Point the user to
        // the Quicktime download page.
        //

        $name = 'Apple Quicktime';
        $guid = '{a42bb825-7eee-420f-8ee7-834062b6fefd}';
        $InstallerShowsUI = 'true';
        $manualInstallationURL = 'http://www.apple.com/quicktime/download/';
    } elseif (preg_match('/^application\/x-java-((applet|bean)(;jpi-version=1\.5|;version=(1\.(1(\.[1-3])?|(2|4)(\.[1-2])?|3(\.1)?|5)))?|vm)$/', $mimetype) &&
	          preg_match('/^(Win|Linux|PPC Mac OS X)/', $clientOS)) {
        // We serve up the Java plugin for the following mimetypes:
        //
        // application/x-java-vm
        // application/x-java-applet;jpi-version=1.5
        // application/x-java-bean;jpi-version=1.5
        // application/x-java-applet;version=1.3
        // application/x-java-bean;version=1.3
        // application/x-java-applet;version=1.2.2
        // application/x-java-bean;version=1.2.2
        // application/x-java-applet;version=1.2.1
        // application/x-java-bean;version=1.2.1
        // application/x-java-applet;version=1.4.2
        // application/x-java-bean;version=1.4.2
        // application/x-java-applet;version=1.5
        // application/x-java-bean;version=1.5
        // application/x-java-applet;version=1.3.1
        // application/x-java-bean;version=1.3.1
        // application/x-java-applet;version=1.4
        // application/x-java-bean;version=1.4
        // application/x-java-applet;version=1.4.1
        // application/x-java-bean;version=1.4.1
        // application/x-java-applet;version=1.2
        // application/x-java-bean;version=1.2
        // application/x-java-applet;version=1.1.3
        // application/x-java-bean;version=1.1.3
        // application/x-java-applet;version=1.1.2
        // application/x-java-bean;version=1.1.2
        // application/x-java-applet;version=1.1.1
        // application/x-java-bean;version=1.1.1
        // application/x-java-applet;version=1.1
        // application/x-java-bean;version=1.1
        // application/x-java-applet
        // application/x-java-bean
        //
        //
        // We don't have a Java plugin to offer here, but Sun's got one for
        // Windows. For other platforms we know where to get one, point the
        // user to the JRE download page.
        //

        $name = 'Java Runtime Environment';
        $version = '';
        $manualInstallationURL = 'http://java.com/firefoxjre';
        $InstallerShowsUI = 'false';

        // For now, send Vista users to a manual download page.
        //
        // This is a temp fix for bug 366129 until vista has a non-manual
        // solution.
        if (preg_match('/^Windows NT 6\.0/', $clientOS)) {
            $guid = '{fbe640ef-4375-4f45-8d79-767d60bf75b8}';
            $InstallerLocation = 'http://java.com/firefoxjre_exe';
            $InstallerHash = 'sha1:89a78d34a36d7e25cc32b1a507a2cd6fb87dd40a';
            $needsRestart = 'false';
        } elseif (preg_match('/^Win/', $clientOS)) {
            $guid = '{92a550f2-dfd2-4d2f-a35d-a98cfda73595}';
            $InstallerLocation = 'http://java.com/firefoxjre_exe';
            $InstallerHash = 'sha1:89a78d34a36d7e25cc32b1a507a2cd6fb87dd40a';
            $XPILocation = 'http://java.com/jre-install.xpi';
        } else {
            $guid = '{fbe640ef-4375-4f45-8d79-767d60bf75b8}';
        }
    } elseif (($mimetype == 'application/pdf' ||
               $mimetype == 'application/vnd.fdf' ||
               $mimetype == 'application/vnd.adobe.xfdf' ||
               $mimetype == 'application/vnd.adobe.xdp+xml' ||
               $mimetype == 'application/vnd.adobe.xfd+xml') &&
               preg_match('/^(Win|PPC Mac OS X|Linux(?! x86_64))/', $clientOS)) {
        $name = 'Adobe Acrobat Plug-In';
        $guid = '{d87cd824-67cb-4547-8587-616c70318095}';
        $manualInstallationURL = 'http://www.adobe.com/products/acrobat/readstep.html';
    } elseif ($mimetype == 'application/x-mtx' &&
              preg_match('/^(Win|PPC Mac OS X)/', $clientOS)) {
        $name = 'Viewpoint Media Player';
        $guid = '{03f998b2-0e00-11d3-a498-00104b6eb52e}';
        $manualInstallationURL = 'http://www.viewpoint.com/pub/products/vmp.html';
    } elseif (preg_match('/^(application\/(asx|x-(mplayer2|ms-wmp))|video\/x-ms-(asf(-plugin)?|wm(p|v|x)?|wvx)|audio\/x-ms-w(ax|ma))$/', $mimetype)) {
        // We serve up the Windows Media Player plugin for the following mimetypes:
        //
        // application/asx
        // application/x-mplayer2
        // audio/x-ms-wax
        // audio/x-ms-wma
        // video/x-ms-asf
        // video/x-ms-asf-plugin
        // video/x-ms-wm
        // video/x-ms-wmp
        // video/x-ms-wmv
        // video/x-ms-wmx
        // video/x-ms-wvx
        //
        // For all windows users who don't have the WMP 11 plugin, give them a
        // link for it.
        if (preg_match('/^Win/', $clientOS)) {
            $name = 'Windows Media Player';
            $version = '11';
            $guid = '{cff1240a-fd24-4b9f-8183-ccd96e5300d0}';
            $manualInstallationURL = 'http://port25.technet.com/pages/windows-media-player-firefox-plugin-download.aspx';

        // For OSX users -- added Intel to this since flip4mac is a UB.
        // Contact at MS was okay w/ this, plus MS points to this anyway.
        } elseif (preg_match('/^(PPC|Intel) Mac OS X/', $clientOS)) {
            $name = 'Flip4Mac';
            $version = '2.1';
            $guid = '{cff0240a-fd24-4b9f-8183-ccd96e5300d0}';
            $manualInstallationURL = 'http://www.flip4mac.com/wmv_download.htm';
        }
    } elseif ($mimetype == 'application/x-xstandard' && preg_match('/^(Win|PPC Mac OS X)/', $clientOS)) {
        $name = 'XStandard XHTML WYSIWYG Editor';
        $guid = '{3563d917-2f44-4e05-8769-47e655e92361}';
        $iconUrl = 'http://xstandard.com/images/xicon32x32.gif';
        $XPILocation = 'http://xstandard.com/download/xstandard.xpi';
        $InstallerShowsUI = 'false';
        $manualInstallationURL = 'http://xstandard.com/download/';
        $licenseURL = 'http://xstandard.com/license/';
    } elseif ($mimetype == 'application/x-dnl' && preg_match('/^Win/', $clientOS)) {
        $name = 'DNL Reader';
        $guid = '{ce9317a3-e2f8-49b9-9b3b-a7fb5ec55161}';
        $version = '5.5';
        $iconUrl = 'http://digitalwebbooks.com/reader/dwb16.gif';
        $XPILocation = 'http://digitalwebbooks.com/reader/xpinst.xpi';
        $InstallerShowsUI = 'false';
        $manualInstallationURL = 'http://digitalwebbooks.com/reader/';
    } elseif ($mimetype == 'application/x-videoegg-loader' && preg_match('/^Win/', $clientOS)) {
        $name = 'VideoEgg Publisher';
        $guid = '{b8b881f0-2e07-11db-a98b-0800200c9a66}';
        $iconUrl = 'http://videoegg.com/favicon.ico';
        $XPILocation = 'http://update.videoegg.com/Install/Windows/Initial/VideoEggPublisher.xpi';
        $InstallerShowsUI = 'true';
        $manualInstallationURL = 'http://www.videoegg.com/';
    } elseif ($mimetype == 'video/divx' && preg_match('/^Win/', $clientOS)) {
        $name = 'DivX Web Player';
        $guid = '{a8b771f0-2e07-11db-a98b-0800200c9a66}';
        $iconUrl = 'http://images.divx.com/divx/player/webplayer.png';
        $XPILocation = 'http://download.divx.com/player/DivXWebPlayer.xpi';
        $InstallerShowsUI = 'false';
        $licenseURL = 'http://go.divx.com/plugin/license/';
        $manualInstallationURL = 'http://go.divx.com/plugin/download/';
    } elseif ($mimetype == 'video/divx' && preg_match('/^(PPC|Intel) Mac OS X/', $clientOS)) {
        $name = 'DivX Web Player';
        $guid = '{a8b771f0-2e07-11db-a98b-0800200c9a66}';
        $iconUrl = 'http://images.divx.com/divx/player/webplayer.png';
        $XPILocation = 'http://download.divx.com/player/DivXWebPlayerMac.xpi';
        $InstallerShowsUI = 'false';
        $licenseURL = 'http://go.divx.com/plugin/license/';
        $manualInstallationURL = 'http://go.divx.com/plugin/download/';
    }
    // End ridiculously huge and embarrassing if-else block.

}
// End our PFS block.



/**
 * Set up our plugin array based on what we've found.
 */
$plugin = array();
$plugin['mimetype'] = !empty($mimetype) ? $mimetype : '-1';
$plugin['name'] = !empty($name) ? $name : '-1';
$plugin['guid'] = !empty($guid) ? $guid : '-1';
$plugin['version'] = !empty($version) ? $version : null;
$plugin['iconUrl'] = !empty($iconUrl) ? $iconUrl : null;
$plugin['XPILocation'] = !empty($XPILocation) ? $XPILocation : null;
$plugin['InstallerLocation'] = !empty($InstallerLocation) ? $InstallerLocation: null;
$plugin['InstallerHash'] = !empty($InstallerHash) ? $InstallerHash : null;
$plugin['InstallerShowsUI'] = !empty($InstallerShowsUI) ? $InstallerShowsUI : null;
$plugin['manualInstallationURL'] = !empty($manualInstallationURL) ? $manualInstallationURL : null;
$plugin['licenseURL'] = !empty($licenseURL) ? $licenseURL : null;
$plugin['needsRestart'] = !empty($needsRestart) ? $needsRestart : 'true';

/**
 * If we have Firefox 3.0.x on windows, force fallback to the manual install URL.
 *
 * This is a one-off fix for bug 433592.
 */
if (!empty($appRelease) && preg_match('/^(3\.0|2\.0).*/', $appRelease) && preg_match('/^Windows NT 6\.0/', $clientOS)) {
    $plugin['XPILocation'] = null;
    $plugin['InstallerLocation'] = null;
    $plugin['InstallerHash'] = null;
    $plugin['licenseURL'] = null;
}



/**
 * XML OUTPUT
 *
 * If we're here, we've set our $plugin array (for better or worse) and it has
 * at least set indexes for all output (no notices).
 *
 * Default values like -1 for name/guid will tell the browser that PFS doesn't know about that mime-type.
 */

// Encode $plugin data for XML output.
$xml = array();
foreach ($plugin as $key=>$val) {
    $xml[$key] = htmlentities($val,ENT_QUOTES,'UTF-8');
}

header('Content-type: text/xml');
echo <<<XML
<?xml version="1.0"?>
<RDF:RDF xmlns:RDF="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:pfs="http://www.mozilla.org/2004/pfs-rdf#">

<RDF:Description about="urn:mozilla:plugin-results:{$xml['mimetype']}">
 <pfs:plugins><RDF:Seq>
  <RDF:li resource="urn:mozilla:plugin:{$xml['guid']}"/>
 </RDF:Seq></pfs:plugins>
</RDF:Description>

<RDF:Description about="urn:mozilla:plugin:{$xml['guid']}">
 <pfs:updates><RDF:Seq>
  <RDF:li resource="urn:mozilla:plugin:{$xml['guid']}:{$xml['version']}"/>
 </RDF:Seq></pfs:updates>
</RDF:Description>

<RDF:Description about="urn:mozilla:plugin:{$xml['guid']}:{$xml['version']}">
 <pfs:name>{$xml['name']}</pfs:name>
 <pfs:requestedMimetype>{$xml['mimetype']}</pfs:requestedMimetype>
 <pfs:guid>{$xml['guid']}</pfs:guid>
 <pfs:version>{$xml['version']}</pfs:version>
 <pfs:IconUrl>{$xml['iconUrl']}</pfs:IconUrl>
 <pfs:InstallerLocation>{$xml['InstallerLocation']}</pfs:InstallerLocation>
 <pfs:InstallerHash>{$xml['InstallerHash']}</pfs:InstallerHash>
 <pfs:XPILocation>{$xml['XPILocation']}</pfs:XPILocation>
 <pfs:InstallerShowsUI>{$xml['InstallerShowsUI']}</pfs:InstallerShowsUI>
 <pfs:manualInstallationURL>{$xml['manualInstallationURL']}</pfs:manualInstallationURL>
 <pfs:licenseURL>{$xml['licenseURL']}</pfs:licenseURL>
 <pfs:needsRestart>{$xml['needsRestart']}</pfs:needsRestart>
</RDF:Description>

</RDF:RDF>
XML;
?>
