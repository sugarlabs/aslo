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
 *   Andrei Hajdukewycz <sancus@off.net> (Original Author)
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

class DownloadsController extends AppController
{
    var $name = 'Downloads';
    var $beforeFilter = array('getNamedArgs', '_checkSandbox');
    var $uses = array('Addon', 'Download', 'File', 'Translation');
    var $components = array('Amo', 'Session');
    var $namedArgs = true;

    var $securityLevel = 'low';

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
    }
    
    function file($id = null, $type = 'xpi') {
        
        if (empty($this->namedArgs['update']))
            $this->namedArgs['update'] = null;
        
        $this->Amo->clean($id);
        $this->layout=null;
        if (!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'file_id'), '/', 3);
            return;
        }
        
        $file_data = $this->File->FindbyId($id);
        
        if (!empty($file_data)) {
            
            $this->Addon->unbindFully();
            $addon_data = $this->Addon->FindbyId($file_data['Version']['addon_id']);
            
            $file_loc = REPO_PATH . '/' . $file_data['Version']['addon_id'] . '/' . $file_data['File']['filename'];
                   
            // If add-on is in sandbox, make sure sandbox is enabled. If disabled, make sure admin or author.
            // If _GET['confirmed'] exists, then a user confirmed a sandbox download via JS, bug 441739
            if (($addon_data['Addon']['status'] == STATUS_SANDBOX ||
                 $addon_data['Addon']['status'] == STATUS_NOMINATED ||
                 $file_data['File']['status'] == STATUS_PENDING)
                 && !$this->Session->check('User') && !isset($_GET['confirmed'])) {
                
                $target_url = str_replace(LANG . '/' . APP_SHORTNAME . '/','',$this->params['url']['url']);
                $this->redirect('/users/login?to=' . urlencode($target_url) . "&m=1");
                return;
            } elseif ($addon_data['Addon']['status'] == STATUS_DISABLED &&
                !$this->Amo->checkOwnership($addon_data['Addon']['id'])) {
                
                $this->flash(_('downloads_disable_warning'), '/', 3);
                return;
            }
        } else {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }
        
        if (file_exists($file_loc))
            $this->set('fileLoc', $file_loc);    
        else {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }
        
        // force local delivery for non-browser apps
        global $browser_apps;
        if (!in_array(APP_ID, $browser_apps) || $type == 'attachment') {
            $forceLocal = true;
            $this->set('attachment', true);
        } else {
            $forceLocal = false;
            $this->set('attachment', false);
        }
        
        $this->set('fileName', $file_data['File']['filename']);
        
        if (!DEV && !$forceLocal && $addon_data['Addon']['status'] == STATUS_PUBLIC
            && $file_data['File']['status'] == STATUS_PUBLIC
            && (strtotime($file_data['File']['datestatuschanged']) <= strtotime('-'.MIRROR_DELAY.' minutes'))) {
            // serve file from releases mirror only if we are not in dev mode
            // and if the file is public and sufficient time has passed to allow
            // its propagation to the mirror system
            $this->forceCache();
            $this->redirect(FILES_HOST . '/' . $addon_data['Addon']['id'] . '/' . $file_data['File']['filename']);
            return;    
        } else {
            // serve file locally
            $this->disableCache();
            $this->render('file');
        }
    }
    
    /**
     * Retrieves public file for latest version of an add-on, regardless of compatibility
     * Example URL: /downloads/latest/1865/type:attachment/platform:5/
     * @param int $addon_id the add-on id
     */
    function latest($addon_id) {
        $platform_id = (!empty($this->namedArgs['platform']) && is_numeric($this->namedArgs['platform'])) ? $this->namedArgs['platform'] : null;
        
        $type = !empty($this->namedArgs['type']) ? $this->namedArgs['type'] : 'xpi';
        
        // Get the id of the latest public file
        $file_id = $this->File->getLatestFileByAddonId($addon_id, $platform_id);
        $file_data = $this->File->findById($file_id);
        
        if (!empty($file_id)) {
            // Use normal download method if file is found
            $target = "/downloads/file/{$file_id}/{$type}/{$file_data['File']['filename']}";
            if (count($this->params['url']) > 1) { // re-append query string
                $getvars = array();
                foreach ($this->params['url'] as $k => $v) {
                    if ($k == 'url') continue;
                    $getvars[] = "$k=$v";
                }
                $qs = implode(',', $getvars);
                $target .= "?$qs";
            }
            $this->redirect($target);
        }
        else {
            // File wasn't found
            $this->flash(_('error_addon_notfound'), '/', 3);
        }
    }
}   

?>
