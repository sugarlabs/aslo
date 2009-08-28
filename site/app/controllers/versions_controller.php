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
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Justin Scott <fligtar@mozilla.com> (Original Author)
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

class VersionsController extends AppController
{
    var $name = 'Versions';
    var $uses = array('Addon', 'License', 'Translation', 'Version');
    var $components = array('Amo', 'Pagination');

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
    }

    function license($version_id) {
        $version = $this->Version->findById($version_id);
        $compat_apps = $this->Version->getCompatibleApps($version['Version']['id']);
        $redirect = $this->Amo->_app_redirect($compat_apps);
        if ($redirect) {
            $this->redirect("{$redirect}/versions/license/{$version_id}", null, true, false);
            return;
        }

        $addon = $this->Addon->getAddon($version['Version']['addon_id']);
        $license_text = $this->License->getText($version['Version']['license_id']);
        $this->set('version', $version);
        $this->publish('addon', $addon);
        $this->publish('license_text', $license_text);

        // set up view, then render
        $this->layout = 'amo2009';
        $this->pageTitle = sprintf(___('Source code license for %1$s %2$s'),
            $addon['Translation']['name']['string'], $version['Version']['version'])
            .' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);
    }

    /**
     * Displays a version's releasenotes in the form requested for updateInfoURLs
     * See http://developer.mozilla.org/en/docs/Extension_Versioning%2C_Update_and_Compatibility
     * @param int $version_id version's id
     * @param string $locale locale of the client
     */
    function updateInfo($version_id, $locale = '') {
        /**
         * If the client's locale is different from our auto-detected locale, it's
         * probably not supported by AMO, but just in case, we see if it is and
         * switch appropriately.
         */
        if (!empty($locale) && $locale != LANG) {
            global $supported_languages;
            if (array_key_exists($locale, $supported_languages)) {
                $this->Version->setLang($locale, $this);
            }
        }

        $version = $this->Version->findById($version_id, array('Version.releasenotes'), null, -1);

        $updateInfo = $version[0]['releasenotes'];

        $this->publish('updateInfo', $updateInfo);
        $this->render('update_info', 'ajax');
    }

}

?>
