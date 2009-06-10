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

class FacebookinstallController extends AppController
{
    var $name = 'Facebookinstall';
    var $uses = array('Addon', 'Application', 'FacebookDetected', 'FacebookFavorite', 'FacebookSession', 'File', 'Version');
    var $components = array('Amo', 'Image');
    var $exceptionCSRF = array('/facebookinstall/import');
    var $securityLevel = 'low';
    
   /**
    * Initialize API and permission checks
    */
    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
        
        $this->Amo->startup($this);
        $this->viewPath = 'facebook';
    }

   /**
    * Install
    */
    function install($file_id) {
        $this->Amo->clean($file_id);
        
        if (!$file = $this->File->findById($file_id)) {
            $this->redirect(FB_URL);
            return;
        }
        
        $addon = $this->Addon->findById($file['Version']['addon_id']);
        
        $this->set('file', $file);
        $this->set('addon', $addon);
        
        $this->render('install', 'ajax');
    }
    
   /**
    * Detect automatically installed add-ons to add as favorites
    */
    function import($fbUserSession, $action = '') {
        $this->Amo->clean($fbUser);
        if (!$fbUser = $this->FacebookSession->retrieveUser($fbUserSession)) {
            $this->redirect(FB_URL.'/import');
            return;
        }
        
        // If we are viewing detected results, delete the cookies and display results
        if ($action == 'results') {
            $this->set('detected', $this->FacebookDetected->getDetectedAddons($fbUser));
            setcookie('AMOfbUser', false, time() - 3600, '/', '.addons.mozilla.org');
            setcookie('AMOfbSequence', false, time() - 3600, '/', '.addons.mozilla.org');

        }
        // If the form has been submitted, save the new favorite add-ons
        elseif ($action == 'favorites') {
            $this->Amo->clean($_POST['addons']);
            if (!empty($_POST['addons'])) {
                foreach ($_POST['addons'] as $addon_id => $val) {
                    if (!$this->FacebookFavorite->isFavorite($fbUser, $addon_id, true)) {
                        $this->FacebookFavorite->addFavorite($fbUser, $addon_id, true);
                    }
                }
            }
            
            $this->redirect(FB_URL.'/favorites/?added='.implode(',', array_keys($_POST['addons'])));
            return;
        }
        // Default to setting the cookies before detection
        else {
            // Set cookie with user's facebook id
            setcookie('AMOfbUser', $fbUser, 0, '/', '.addons.mozilla.org');
            
            // Set cookie with unique detection sequence
            setcookie('AMOfbSequence', time(), 0, '/', '.addons.mozilla.org');
        }
        
        $this->set('action', $action);
        $this->set('fbUser', $fbUser);
        $this->set('fbUserSession', $fbUserSession);
        $this->render('import', 'ajax');
    }

}

?>
