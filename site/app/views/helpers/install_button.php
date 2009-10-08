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
 *   Ryan Doherty <rdoherty@mozilla.com> (Original Author)
 *
 * Alternatively, the contents of this file may be used under the terms of
 *  either the GNU General Public License Version 2 or later (the "GPL"), or
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

class InstallButtonHelper extends Helper {

    /**
     * Helpers 
     *
     * @var array
     */
    var $helpers = array('Html');

    /**
     * Various configuration options for instal button
     *
     * @var array
     */ 
    var $options = null;

    /**
     * Local references to global variables
     *
     * @var array
     */
    var $browser_apps, $experimental_status, $valid_status;

    /**
     * The add-on to install
     *
     * @var array
     */
    var $addon;

    /**
     * Add-on flags for labeling install button 
     *
     * Potential values are 'recommended' and 'experimental'
     */
    var $defaultFlags = array();

    /**
     * Default button '+' image size
     *
     * @var string
     */
    var $defaultButtonSize = '8x9';

    /**
     * Unique id per install button because the same add-on 
     * can have multiple install buttons on a page (versions page)
     *
     * @var string
     */
    var $uniqueId;

    /**
     * Default level of contributions
     *
     * @var in
     */
    var $defaultContributionLevel = 0;
    /**
     * Basic setup, pulling in global variables that we'll need later
     *
     * @return null
     */

    function __construct() {
        global $browser_apps, $experimental_status, $valid_status;
        $this->browser_apps = $browser_apps;
        $this->experimental_status = $experimental_status;
        $this->valid_status = $valid_status;
        parent::__construct();
    }

    /**
     * Creates the epic install button. Expects an array of configuration
     * options that contains the add-on array and various other 
     * settings
     *
     * @param array $options all the options and add-on for the button
     * @return string the HTML & JS to display the button
     */
    function button($options) {
        if(empty($options)) return false;
        $this->options = $options;
        if(isset($options['addon'])) {
            $this->addon = $options['addon'];//shortcut for easier reference later
        }
        $this->generateUniqueId();
        return $this->render();
    }
    
    /*
     * Pulls together all the various HTML & JS to render the button
     * 
     * @return string the HTML & JS
     */ 
    function render() {
        $html = '';
        if(count($this->addonFiles()) < 1) {
            $html .= '<p class="install-button">'.___('This add-on is not available.').'</p>';
            return $html;
        }

        $html .= '<div id="install-'.$this->versionId().'" class="install install-container">';

        foreach($this->addonFiles() as $file) {      
            $html .= $this->installButton($file); 
            $html .= $this->installVersusDownloadJS($file);
        }

        $html .= $this->flagsHtml();

        $html .= '</div>';

        if($this->showThunderbirdInstructions()) {
            $html .= $this->thunderbirdInstructions();
        }

        $html .= $this->platformJS();

        if($this->showCompatibilityHints()) {
            $html .= $this->compatibilityHintsJs();
        }

        if($this->hijackInstall()) {
            $html .= $this->hijackInstallJs();
        }
        return $html;
    }

    /**
     * Hijack (prevent the install or redirect after install)
     * only if the contribution level is high enough
     *
     * @return boolean 
     */
    function hijackInstall() {
        return $this->contributionLevel() > Addon::CONTRIBUTIONS_PASSIVE;  
    }

    /**
     * Creates the appropriate JavaScript to hijack
     * the install process
     *
     * @return string the JavaScript
     */
    function hijackInstallJs() {
        $hijackJs = '<script type="text/javascript">';
        if ($annoying == Addon::CONTRIBUTIONS_AFTER) {
            /**
              * Hijack the install function so that it calls install,
              * then redirects to the developer's page.
              */

            $hijackJs .= "var hack = function(old) {
                return function() {
                    var ret = old.apply(undefined, arguments);
                    window.location += '/developers/post_install?confirmed=true';
                    // If something goes wrong, return a value
                    // so propagation stops.
                    return ret;
                };
        };
        install = hack(install);
        addEngine = hack(addEngine);";
        } elseif ($annoying == Addon::CONTRIBUTIONS_ROADBLOCK) {
            /**
              * This function attaches installTrigger to the install button;
              * Making it a no-op lets us redirect to the developer page.
              */
            $hijackJs .= "installButtonAttachInstallMethod = function(){};";
        } 
        $hijackJs .= "</script>";
        return $hijackJs;
    }

    /**
     * JavaScript calls to functions that determine compatibility
     * with the current browser
     *
     * @return string the JavaScript
     */
    function compatibilityHintsJs() {
        $fromVer = $toVer = null;

        foreach ($this->compatibleApps() as $app) {
            if ($app['Application']['application_id'] == APP_FIREFOX) {
                $fromVer = $app['Min_Version']['version'];
                $toVer = $app['Max_Version']['version'];
            }
        }

        if ($fromVer && $toVer) {
            $hintsJs = '<script type="text/javascript">
                setTimeout(function() { ';
                        if (!$this->isVersionsPage()) {
                        // show "ignore" link for logged in users only
                        $_loggedin = $this->loggedIn();
                        $hintsJs .= "addCompatibilityHints('{$this->addonId()}', '{$this->versionId()}', "
                        ."'{$fromVer}', '{$toVer}', '{$_loggedin}');";
                        } else {
                        // allow determining latest compatible version for version history page
                        $hintsJs .= "addons_history.addVersion('{$versionId}','{$fromVer}','{$toVer}');";
                        }
                        $hintsJs .= "}, 0);
                        </script>";
        }
        return $hintsJs;
    }

    /** 
     * Show compatibility hints only if the current app is 
     * Firefox and there are compatible apps
     *
     * @return boolean
     */
    function showCompatibilityHints() {
        $isFirefox = APP_ID == APP_FIREFOX;
        $notEmpty = count($this->compatibleApps());
        return ($isFirefox && $notEmpty);
    }


    /**
     * JavaScript calls for dealing with experimental and platform-specific
     * add-ons
     *
     * @return string javascript function calls
     */
    function platformJS() {
        $platformJs = '<script type="text/javascript">
            setTimeout(function() {'.
                    "initExpConfirm('{$this->versionId()}');
                    ";
                    if(!$this->isVersionsPage() && !$this->isPolicyPage()) {
                    $platformJs .= "fixPlatformLinks('{$this->versionId()}', '{$this->addonName()}');";
                    }
                    $platformJs .= "}, 0);
                    </script>";
                    return $platformJs;
    }

    /**
     * Determine if Thunderbird instructions should be show
     * when clicking the install button
     * 
     * @return boolean
     */ 
    function showThunderbirdInstructions() {
        if ($this->contributionLevel() != Addon::CONTRIBUTIONS_ROADBLOCK && 
                (strlen($this->addonEULA() == 0)) && $this->showInstructions() && 
                (APP_ID == APP_THUNDERBIRD || APP_ID == APP_SUNBIRD)){
            return true;
        } else {
            return false;
        }
    }

    /**
     * Instructions for how to install add-ons for Thunderbird
     *
     * @return string
     */
    function thunderbirdInstructions() {
        $installPopupName = "install-popup-" . $this->versionId();
        if (APP_ID == APP_THUNDERBIRD):
            $installTitle = ___('How to Install in Thunderbird');
        $installBody = ___('<ol><li>Download and save the file to your hard disk.</li><li>In Mozilla Thunderbird, open Add-ons from the Tools menu.</li><li>Click the Install button, and locate/select the file you downloaded and click "OK".</li></ol>');
        elseif (APP_ID == APP_SUNBIRD):
            $installTitle = ___('How to Install in Sunbird');
        $installBody = ___('<ol><li>Download and save the file to your hard disk.</li><li>In Mozilla Sunbird, open Add-ons from the Tools menu.</li><li>Click the Install button, and locate/select the file you downloaded and click "OK".</li></ol>');
        endif;

        $installInstructions = '<div class="app_install">
            <div id="'.$installPopupName.'" class="app_install-popup-container">
            <div class="app_install-popup">
            <div class="app_install-popup-inner">
            <h3>'.$installTitle.'</h3>
            '.$installBody.'
            </div>
            </div>
            </div>
            </div>
            <script type="text/javascript">';

        foreach ($this->addonFiles() as $file) {
            $installInstructions .='initDownloadPopup("'.$this->installTriggerName($file['id']).
                    '", "'.$installPopupName.'");';
        }

        $installInstructions .= '</script>';
        return $installInstructions;
    }

    /**
     * HTML for flags (recommended, experimental)
     *
     * @return string the flags wrapped in HTML
     */
    function flagsHtml() {   
        $flags = '';
        if (in_array('recommended', $this->flags())) {
            $flags = '<strong>'.___('recommended').'</strong>';
        } else if (in_array('experimental', $this->flags()) && $this->loggedIn()) { 
            $flags = '<strong>'.___('experimental').'</strong>';
        }
        return $flags;
    }

    /**
     * The *actual* install button ('Download now', 'Add to Firefox')
     *
     * @return string install button HTML
     */
    function installButton($file) {
        if($this->showSandboxConfirmation($file)) {
            return $this->sandboxButton($file);    
        } else {
            return $this->standardInstallButton($file);
        }
    }

    /**
     * JavaScript function calls to determine if the button should 
     * say 'Download' or 'Install'
     *
     * @return string the JavaScript function calls
     */
    function installVersusDownloadJS($file) {
        return '<script type="text/javascript">
            installVersusDownloadCheck("'.$this->installTriggerName($file['id']).'","'.
                    sprintf($this->buttonMessage(), $this->installPlatformString($file['platform_id'])).'", "'.
                    sprintf(___('Download Now %s'), $this->installPlatformString($file['platform_id'])).'");
        </script>';
    }

    /**
     * Button for sandboxed add-ons. Shows a checkbox for users to check
     * stating they know what they are doing
     *
     * @return string the sandboxed install button
     */ 
    function sandboxButton($file) {
        $button = '<div class="exp-loggedout">
            <div class="exp-confirm-install" style="display: none">
            <div class="exp-desc">
            <strong class="compatmsg">
            <input type="checkbox" name="confirm-'.$this->addonId().'" />'.
            sprintf(___('Let me install this experimental add-on. <a href="%1$s">What\'s this?</a>')
                    , $this->Html->url('/pages/faq#experimental-addons')).'
            </strong>
            </div>
            </div>

            <p class="install-button '.$this->platformClass($file['platform_id']).'" style="display: none">'.
            $this->installLink($file).'
            </p>
            <noscript>
            <p class="install-button '.$this->platformClass($file['platform_id']).'">';

        $login_url = $this->Html->login_url('/'.LANG.'/'.APP_SHORTNAME."/addon/{$this->addonId()}", false);
        $attributes = array('id' => $this->installTriggerName($file['id']),
                'addonName' => $this->addonName(),
                'title' => sprintf(___('Add %1$s to %2$s'), $this->addonName(), APP_PRETTYNAME));
        $button .= $this->Html->link('<span><span><span><strong>'
                .sprintf(___('Download Now %s'), $this->installPlatformString($file['platform_id']))
                .'</strong></span></span></span>',
                $login_url, $attributes, false, false).'</p>';

        $exp_addon_url = "/pages/faq#experimental-addons";

        $button .= sprintf(
                ___('<a href="%1$s">Log in</a> to install this experimental add-on. <a href="%2$s">Why</a>?'), 
                $this->Html->url($login_url), $this->Html->url($exp_addon_url)).'
            </noscript>
            </div>';
        return $button;
    }

    /**
     * Standard install button in all it's glory
     *
     * @return string the install button HTML
     */ 
    function standardInstallButton($file) {
        return '<p class="install-button '.$this->platformClass($file['platform_id']).'">
            '.$this->installLink($file).'</p>';
    }

    /**
     * Determine if we should show a sandbox confirmation 
     * Confirmation shown if:
     * 1) User is NOT logged in AND
     * 2) The add-on has an experimental status AND 
     * 3) It is current NOT the EULA page AND
     * 4) The user has not confirmed they aren't dumb ($_GET['confirmed'] parameter)
     */
    function showSandboxConfirmation($file) {
        if ($this->loggedIn() || !in_array($file['status'], $this->experimental_status) || 
                ($this->isPolicyPage() && isset($_GET['confirmed']))) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Options for the install link
     *
     * @return array various options
     */
    function linkOptions($file) {
        $linkOptions = null; 
        if ($this->addonType() != ADDON_SEARCH) {
            if (in_array(APP_ID, $this->browser_apps)) {
                // prepare link options for browser apps
                $linkOptions = array(
                        'id' => $this->installTriggerName($file['id']),
                        'title'     => sprintf(___('Add %1$s to %2$s'), $this->addonName(), APP_PRETTYNAME),
                        'addonName' => $this->addonName(),
                        'addonIcon' => $this->addonIconPath(),
                        'addonHash' => $file['hash'],
                        'jsInstallMethod' => 'browser_app_addon_install',
                        );
            } else {
                // prepare link options for non-browser apps
                $linkOptions = array('id' => $this->installTriggerName($file['id']),
                        'title'=>sprintf(___('Download %1$s'),$this->addonName()));
            }
        } else {
            /* prepare link options for search engines */
            $linkOptions = array(
                    'id'        => $this->installTriggerName($file['id']),
                    'title'     => sprintf(___('Add %1$s to %2$s'), $this->addonName(), APP_PRETTYNAME),
                    'engineURL' => FULL_BASE_URL . $this->Html->urlFile($file['id'], $file['filename'], $this->collectionUuid()),
                    // search engines use a special install method
                    'jsInstallMethod' => 'search_engine_install',
                    );
        }

        $linkOptions['class'] = 'button positive ' . $this->buttonClass();
        return $linkOptions;
    }


    /**
     * Creates the link to the install file, which changes
     * dependent on contribution level or EULA
     * 
     * @return string the link
     */
    function installLink($file) {
        if ($this->contributionLevel() == Addon::CONTRIBUTIONS_ROADBLOCK) {
            $install_button_html = $this->roadBlockLink($file);
        } else if (strlen($this->addonEULA()) == 0) {
            $install_button_html = $this->normalInstallLink($file);
        } else {
            $install_button_html = $this->eulaLink($file);
        }

        return $install_button_html;
    }

    /**
     * Creates a link to the EULA page for an add-on
     *
     * @return string the link
     */
    function eulaLink($file) {
        $linkOptions = $this->linkOptions($file);

        $eula_attributes = array('id' => $this->installTriggerName($file['id']),
                'class' => $linkOptions['class'],
                'addonName' => $this->addonName(),
                'title' => sprintf(___('Add %1$s to %2$s'), $this->addonName(), APP_PRETTYNAME),
                'isEULAPageLink' => 'true');

        $link_url = "/addons/policy/0/{$this->addonId()}/{$file['id']}";

        if (!is_null($this->collectionUuid())) {
            $link_url = $this->Html->appendParametersToUrl($link_url, 
                    array('collection_id' => $this->collectionUuid()));
        }

        if ($this->hasSrc()) {
            $link_url = $this->Html->appendParametersToUrl($link_url, array('src' => $this->src()));
        }

        $install_button_html = $this->Html->link(
                $this->installButtonImage().
                '<span class="install-button-text">'.
                sprintf(___('Download Now %s'), $this->installPlatformString($file['platform_id'])).'</span>',
                $link_url,
                $eula_attributes, false, false);

        return $install_button_html;
    }


    /**
     * Creates a normal install link to an add-on file
     *
     * @return string the link
     */
    function normalInstallLink($file) {
        $install_button_html = '';
        // wipe disallowed characters off the displayed filename
        $addon_filename = $this->Html->entities(preg_replace(
                    INVALID_FILENAME_CHARS, '_', $this->Html->unsanitize($file['filename'])));

        // if this is the latest public version, use perma-URL. Otherwise, link directly to file.
        $linktitle = $this->installButtonImage().
            '<span class="install-button-text">'.
            sprintf(___('Download Now %s'),$this->installPlatformString($file['platform_id'])).'</span>';

        if ($this->isLatest() && $file['status'] == STATUS_PUBLIC) {
            $latest_permalink = "/downloads/latest/{$this->addonId()}";
            if ($file['platform_id'] != PLATFORM_ALL) 
                $latest_permalink .= "/platform:{$file['platform_id']}";

            $file_id = $this->view->controller->File->getLatestFileByAddonId($this->addonId());
            $file_data = $this->view->controller->File->findById($file_id);
            $path_info = pathinfo($file_data['File']['filename']);

            $latest_permalink .= "/addon-{$this->addonId()}-latest.".$path_info['extension'];

            if ($this->fromCollection()) {
                $latest_permalink = $this->Html->appendParametersToUrl(
                        $latest_permalink, array('collection_id' => $this->collectionUuid()));
            }

            if ($this->hasSrc()) {
                $latest_permalink = $this->Html->appendParametersToUrl($latest_permalink, 
                        array('src' => $this->src()));
            }

            $install_button_html .= $this->Html->link($linktitle, $latest_permalink, 
                    $this->linkOptions($file));
        } else {
            $install_button_html .= $this->Html->linkFile($file['id'], $linktitle, null,
                    $this->linkOptions($file), false, $addon_filename, $this->collectionUuid());
        }

        return $install_button_html;
    }

    /**
     * Creates a download/install link that actually sends the user to a 
     * contributions roadblock
     *
     * @return string the link
     **/
    function roadBlockLink($file) {
        $linktitle = $this->installButtonImage().
            '<span class="install-button-text">'.
            sprintf(___('Download Now %s'),$this->installPlatformString($file['platform_id'])).'</span>';
        if ($this->fromCollection()) {
            $qs = '?collection_id='.$this->collectionUuid();
        } else {
            $qs = '';
        }
        $install_button_html = $this->Html->link($linktitle, 
                '/addon/'.$this->addonId().'/developers/roadblock'.$qs, $this->linkOptions($file));
        return $install_button_html;
    }

    /**
     * Creates a '+' image for the install button
     *
     * @return string the image tag
     */
    function installButtonImage() {
        return '<img src="'.$this->Html->url(
            "/img/amo2009/icons/buttons/plus-green-{$this->buttonSize()}.gif", null, false, false
            ).'" alt="" />';
    }

    /**
     * Creates the install trigger name used for
     * uniquely identifying each install button on the page
     * 
     * @param int $fileId
     * 
     * @return string the unique string to identify this button
     */
    function installTriggerName($fileId) {
        return "installTrigger".$fileId.$this->uniqueId;
    }

    /**
     * Finds the platform name for a specific platform id
     *
     * @param int $platformId
     *
     * @return string the platform name
     */ 
    function platformName($platformId) {
        $platformName = '';
        foreach($this->platforms() as $platform) { 
            if ($platform['Platform']['id'] == $platformId) {
                $platformName = $platform['Translation']['name']['string'];
            }
        }
        return $platformName;
    }

    /**
     * Creates the classname for the install button,
     * dependent on the platform name
     *
     * @param int $platformId
     * 
     * @return string the classname
     */ 
    function platformClass($platformId) {
        $platformClass = '';
        if(strlen($this->platformName($platformId)) > 0) {
            $platformClass = "platform-".$this->platformName($platformId);
        }
        return $platformClass;
    }

    /**
     * Create the install platform name to be used 
     * next to 'Add to Firefox', etc. 
     *
     * @param int $platformId
     * 
     * @return string the platform string "(OSX)" "(Windows)"
     */
    function installPlatformString($platformId) {
        $installPlatform = '';

        if($this->platformName($platformId) != "ALL") {
            $installPlatform = "(".$this->platformName($platformId).")";
        }
        return $installPlatform;
    }

    /**
     * Create the message for the install button:
     * "Download" vs. "Add to Firefox", etc
     * 
     * @return string the message
     */ 
    function buttonMessage() {
        $buttonMessage = '';
        if (!isset($this->options['buttonMessage'])) {
            if (!in_array(APP_ID, $this->browser_apps)) {
                $buttonMessage = ___('Download Now %s');
            } else {
                $buttonMessage = sprintf(___('Add to %1$s %2$s'), APP_PRETTYNAME, "%s");
            }
        } else {
            $buttonMessage = $this->options['buttonMessage'];
        }
        return $buttonMessage;
    }

    /**
     * Creates the add-on icon path for installation
     *
     * @return string the path
     */ 
    function addonIconPath() {
        $iconPath = '';

        if (isset($this->options['addonIconPath']) && !empty($this->options['addonIconPath'])) {
            $iconPath = $this->options['addonIconPath'];
        } elseif(empty($this->options['addonIconPath'])) {
            switch ($this->addonType()) {
                case ADDON_THEME:
                    $iconPath = $this->Html->urlImage(DEFAULT_THEME_ICON);
                    break;
                default:
                    $iconPath = $this->Html->urlImage(DEFAULT_ADDON_ICON);
                    break;
            }
        } else {
            $iconPath = $this->view->controller->Image->getAddonIconURL($this->addon['Addon']['id']);
        }

        return $iconPath;
    }

    /**
     * Helper function for determing if the current page
     * is the versions page
     *
     * @return boolean
     */
    function isVersionsPage() {
        return ($this->view->name == 'Addons' && $this->view->action == 'versions');  
    }

    /** 
     * Helper function for determining if the current page
     * is the policy page
     * 
     * @return boolean
     */
    function isPolicyPage() {
        return ($this->view->name == 'Addons' && $this->view->action == 'policy');
    }

    /** 
     * Helper function to check if a 'src' parameter
     * should be appended to the url
     *
     * @return boolean
     */
    function hasSrc() {
        return strlen($this->src()) > 0;
    }

    /**
     * Helper function to check if a 'collection_id' parameter
     * should be appended to the url
     *
     * @return boolean
     */
    function fromCollection() {
        return !is_null($this->collectionUuid());
    }

    /**
     * Below are various basic helper functions that check the 
     * $options array for values and if not set, return defaults
    **/
    function addonName() {
        return isset($this->options['addonName']) ? 
            $this->options['addonName'] :
            $this->addon['Translation']['name']['string'];
    }

    function addonId() {
        return isset($this->options['addonId']) ? 
            $this->options['addonId'] :
            $this->addon['Addon']['id'];
    }

    function addonFiles() {
        return isset($this->options['addonFiles']) ?
            $this->options['addonFiles'] :
            isset($this->addon['File']) ?
                $this->addon['File'] :
                null;
    }

    function addonEULA() {
        return isset($this->options['addonEULA']) ?
            $this->options['addonEULA'] :
            $this->addon['Translation']['eula']['string'];
    }

    function addonStatus() {
        return isset($this->options['addonStatus']) ?
            $this->options['addonStatus'] :
            $this->addon['Addon']['status'];
    }

    function isLatest() {
        return isset($this->options['is_latest']) ?
            $this->options['is_latest'] :
            ($this->addonStatus() == STATUS_PUBLIC);
    }

    function compatibleApps() {
        return isset($this->options['compatible_apps']) ?
            $this->options['compatible_apps'] :
            $this->addon['compatible_apps'];
    }

    function addonType() {
        return isset($this->options['addonType']) ?
            $this->options['addonType'] :
            $this->addon['Addon']['addontype_id'];
    }

    function buttonClass() {
        return isset($this->options['buttonClass']) ?
            $this->options['buttonClass'] :
            '';
    }

    function compatibleAppIds() {
        $appIds = array();
        foreach($this->compatibleApps() as $var => $val) {
            $appIds[] = $val['Application']['application_id'];
        }
        return $appIds;
    }

    function platforms() {
        $platforms = null;
        if(isset($this->options['platforms'])) {
            $platforms = $this->options['platforms'];
        } else {
            $this->view->controller->Platform->unbindFully();
            $platforms = $this->view->controller->Platform->findAll();
        }

        return $platforms;
    }

    function flags() {
        return isset($this->options['flags']) ?
            $this->options['flags'] :
            $this->defaultFlags;
    }

    function buttonSize() {
        return isset($this->options['buttonSize']) ?
            $this->options['buttonSize'] :
            $this->defaultButtonSize;
    }

    function contributionLevel() {
        return isset($this->options['annoying']) ?
            $this->options['annoying'] :
            $this->defaultContributionLevel;
    }

    function collectionUuid() {
        if(isset($this->options['collection_uuid']) && 
           strlen($this->options['collection_uuid']) > 0) {
          return $this->options['collection_uuid'];
        } elseif(isset($_GET['collection_id'])) {
          return htmlentities($_GET['collection_id']);
        } else {
          return null;
        }
    }

    function showInstructions() {
        return isset($this->options['showInstructions']) ?
            $this->options['showInstructions'] :
            true;
    }

    function loggedIn() {
        return $this->view->controller->Session->check('User');
    }

    function versionId() {
        $addonFiles = $this->addonFiles();
        return $addonFiles[0]['version_id'] . $this->uniqueId;
    }

    function generateUniqueId() {
        $this->uniqueId = '-' . dechex(mt_rand());
    }

    function src() {
        return isset($this->options['src']) ? 
            $this->options['src'] :
            '';
    }
}
?>
