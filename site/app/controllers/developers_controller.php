<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/e
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is addons.mozilla.org site.
 *
 * The Initial Developer of the Original Code is
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *    Frederic Wenzel <fwenzel@mozilla.com>
 *    RJ Walsh <rwalsh@mozilla.com>
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
require_once('Archive/Zip.php');

/**
 * Returns $object[$name], or $default if that's not set.
 *
 * If $name is a string of dot-separated names like 'foo.bar.baz',
 * $object['foo']['bar']['baz'] will be returned.  If any name
 * along the way is not set, $default will be returned.
 *
 * If you want to fetch a name with embedded dots, look elsewhere.
 */
function getitem($object, $name, $default=null) {
    $split = explode('.', $name, 2);
    if (count($split) == 2) {
        list($a, $b) = $split;
        return isset($object[$a]) ? getitem($object[$a], $b, $default)
                                  : $default;
    } else {
        return isset($object[$name]) ? $object[$name] : $default;
    }
}

class DevelopersController extends AppController
{
    var $name = 'Developers';
    var $uses = array('Addon', 'Addontype', 'Application', 'Approval', 'Appversion', 'BlacklistedGuid', 'Category',
        'EditorSubscription', 'Eventlog', 'File', 'License', 'Platform', 'Preview', 'Review',
        'Tag', 'TestCase', 'TestGroup', 'TestResult', 'Translation', 'User', 'Version');
    var $components = array('Amo', 'Developers', 'Editors', 'Email', 'Error',
        'Image', 'Opensearch', 'Paypal', 'Rdf', 'Src', 'Validation', 'Versioncompare');

    var $helpers = array('Html', 'Javascript', 'Ajax', 'Link', 'Listing', 'Localization', 'Form');
    var $addVars = array(); //variables accessible to all additem steps

   /**
    * Require login for all actions
    */
    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        // beforeFilter() is apparently called before components are initialized. Cake++
        $this->Amo->startup($this);
        $this->Amo->checkLoggedIn();

        // Clean post data
        $this->Amo->clean($this->data);

        $this->layout = 'mozilla';
        $this->pageTitle = ___('Developer Tools', 'devcp_pagetitle').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);

        $this->cssAdd = array('developers', 'validation');
        $this->publish('cssAdd', $this->cssAdd);

        $this->jsAdd = array('developers', 'json', 'jquery-ui/jqModal.js');
        $this->publish('jsAdd', $this->jsAdd);

        $this->publish('expand_categories', true);

        $this->breadcrumbs = array(___('Developer Tools', 'devcp_pagetitle') => '/developers/index');
        $this->publish('breadcrumbs', $this->breadcrumbs);

        $this->publish('subpagetitle', ___('Developer Tools', 'devcp_pagetitle'));

        global $native_languages;
        $this->set('nativeLanguages', $native_languages);

        // disable query caching so devcp changes are visible immediately
        foreach ($this->uses as $_model) {
            $this->$_model->caching = false;
        }

        // Default "My Add-ons" sidebar data
        $session = $this->Session->read('User');
        $this->publish('all_addons', $this->Addon->getAddonsByUser($session['id']));

        // Include the dev_agreement column on developer pages.
        array_push($this->Addon->default_fields, 'dev_agreement');
    }

    /**
    * Developer Dashboard
    */
    function dashboard() {
        $session = $this->Session->read('User');

        $addon_ids = $this->Addon->getAddonsByUser($session['id']);
        $addons = array();

        if (!empty($addon_ids)) {
            foreach ($addon_ids as $addon_id => $addon_name) {
                $addon = $this->Addon->getAddon($addon_id, array('default_fields', 'latest_version'));
                if (!empty($addon['Version'][0])) {
                        $files = $this->File->findAll("File.version_id={$addon['Version'][0]['id']}");

                        if (!empty($files)) {
                            foreach ($files as $file) {
                                $addon['Version'][0]['File'][] = $file['File'];
                            }
                        }
                }

                $addon['Addon']['updatepings'] = $this->Addon->getMostRecentUpdatePingCount($addon_id);

                $addons[] = $addon;
            }
        }

        $this->publish('addons', $addons);
        $this->publish('statuses', $this->Amo->getStatusNames());
        $this->publish('addontypes', $this->Addontype->getNames());

        $this->render('dashboard');
    }

    function json($action, $additional = '', $file = '') {
        switch ($action) {
            case 'fileupload':
                $data = $this->_validateUpload($additional);
                if ($data['error'] == 1) {
                    $json = $data;
                } else {
                    $json = array(
                        'file_id' => -1,
                        'file_name' => $data['File']['db']['filename'],
                        'addon_type' => $data['Addon']['addontype_id'],
                        'uploadtype' => $additional
                    );
                }
                $this->publish('encapsulate', true);
                break;

            case 'complete':
                if ($additional == 'new') {
                    $json = $this->_newAddonFromFile($file);
                }
                elseif ($additional == 'update') {
                    $json = $this->_updateAddonFromFile($additional, $file);
                }
                elseif ($additional == 'file') {
                    $json = $this->_updateAddonFromFile($additional, $file);
                }
                if ($json['error'] == 0) {
                    $this->_saveCachedData($json['file_id'], $file);
                }
                $this->publish('encapsulate', true);
                break;

            case 'verifyauthor':
                $json = $this->_verifyAuthor($_GET['email']);
                break;
        }

        $this->set('json', $json);
        $this->render('json', 'ajax');
    }

    /**
     * Handler for add-on-centric actions
     * @param string $action action to take
     * @param int $addon_id add-on id, if necessary
     * @param string $additional additional parameter (e.g. subaction)
     */
    function addon($action, $addon_id = 0, $additional = null) {
        if (!empty($addon_id)) {
            // Make sure user has some permissions to view this add-on
            $role = $this->Amo->getAuthorRole($addon_id);
            if (empty($role)) {
                $this->Amo->accessDenied();
            }

            $addon_name = $this->Addon->getAddonName($addon_id);
            if ($addon_name !== false) {
                $this->publish('author_role', $role);
                $this->publish('addon_name', $addon_name);
                $this->publish('addon_id', $addon_id);
            }
            else {
                $this->flash(___('Add-on not found!'), '/developers/dashboard');
                return;
            }
        }

        switch ($action) {
            case 'details':
                $this->setAction('_addonDetails', $addon_id);
                break;

            case 'edit':
                $this->setAction('_editAddon', $addon_id, $additional);
                break;

            case 'status':
                $this->setAction('_changeAddonStatus', $addon_id, $additional);
                break;

            case 'submit':
                $this->setAction('_submitAddon');
                break;
        }
    }

    /**
     * Shows add-on details
     */
    function _addonDetails($addon_id) {
        $this->publish('action', 'details');

        $this->render('addon_details');
    }

    /**
     * Displays uploader for submitting add-ons
     */
    function _submitAddon() {
        $this->publish('type', 'new');
        $this->publish('hasAgreement', false);

        $this->render('uploader');
    }

    /**
     * Called via AJAX to handle creation of a new add-on
     */
    function _newAddonFromFile($filename = '') {
        
        // Grab the data from our cache, and then de-serialize it
        $this->Amo->clean($filename);
        $data = $this->Addon->query("SELECT value FROM `test_results_cache` WHERE `key` = '{$filename}' AND `test_case_id` = -1");
        $data = $data[0]['test_results_cache']['value'];
        $this->Amo->unclean($data);
        $data = unserialize($data);

        // Insert new add-on row
        $this->Addon->id = 0;
        $this->Addon->save($data['Addon']);
        $data['Addon']['id'] = $this->Addon->getLastInsertId();

        // Add user as author
        $session = $this->Session->read('User');
        $this->Addon->saveAuthor($data['Addon']['id'], $session['id']);

        // Save License
        $license_id = $this->Developers->saveLicense(
             $this->data['License'],
             getitem($this->data, 'License.text'),
             getitem($this->params, 'form.data.License'));
        $this->Addon->saveField('dev_agreement', 1);

        // Add Version
        $this->Version->id = 0;
        $data['Version']['addon_id'] = $data['Addon']['id'];
        $data['Version']['license_id'] = $license_id;
        $this->Version->save($data['Version']);
        $data['Version']['id'] = $this->Version->getLastInsertId();

        // Save appversions
        if (!empty($data['appversions'])) {
            foreach ($data['appversions'] as $appversion) {
                $this->Version->addCompatibleApp($data['Version']['id'], $appversion['application_id'], $appversion['min'], $appversion['max']);
            }
        }
        
        // Add Files
        $data['File']['db']['version_id'] = $data['Version']['id'];
        $platforms = $data['File']['db']['platform_id'];
        foreach ($platforms as $platform_id) {
            $this->File->id = 0;
            $data['File']['db']['platform_id'] = $platform_id;
            $validate = $this->Developers->moveFile($data);
            if (is_string($validate)) {
                // If a string is returned, there was an error
                return $this->Error->getJSONforError($validate);
            }
            $data['File']['db']['filename'] = $validate['filename'];
            $this->File->save($data['File']['db']);
            $file_id = $this->File->id;
        }
        // Remove temp file
        $tempFile = $data['File']['details']['path'];
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        return array(
            'error' => 0,
            'uploadtype' => 'new',
            'addon_id' => $data['Addon']['id'],
            'version_id' => $data['Version']['id'],
            'file_id' => $file_id
            );
    }

    /**
     * Called via AJAX to handle updating of an add-on
     * @param string $type whether an update or new file
     */
    function _updateAddonFromFile($type = '', $filename = '') {

        // Grab the data from our cache, and then de-serialize it
        $this->Amo->clean($filename);
        $data = $this->Addon->query("SELECT `value` FROM `test_results_cache` WHERE `key` = '{$filename}' AND `test_case_id` = -1");        
        $data = $data[0]['test_results_cache']['value'];
        $this->Amo->unclean($data);
        $data = unserialize($data);

        $addon_id = $data['Addon']['id'];
        $version_id = $data['Version']['id'];  // This will fail if there's a new version, but this is OK

        // Make sure user has upload permissions
        $role = $this->Amo->getAuthorRole($addon_id);
        if (empty($role) || $role < AUTHOR_ROLE_DEV) {
            return $this->Error->getJSONforError(___('You do not have sufficient privileges to update this add-on.'));
        }

        $addon = $this->Addon->findById($addon_id);

        if ($type == 'update') {

            // Save License
            if ($addon['Addon']['dev_agreement'] == true) {
                // If we already have an agreement, we didn't show the license
                // picker, so use the previously selected license.
                global $valid_status;
                $old_id = $this->Version->getVersionByAddonId($addon_id, $valid_status);
                $oldVersion = $this->Version->findById($old_id);
                $license_id = $oldVersion['Version']['license_id'];
            } else {
                $license_id = $this->Developers->saveLicense(
                    $this->data['License'],
                    getitem($this->data, 'License.text'),
                    getitem($this->params, 'form.data.License'));
            }
            $this->Addon->save(array('Addon' => array('id' => $addon_id,
                                                      'dev_agreement' => 1)));

            // Add Version
            $this->Version->id = 0;
            $data['Version']['addon_id'] = $addon_id;
            $data['Version']['license_id'] = $license_id;
            $this->Version->save($data['Version']);
            $version_id = $this->Version->getLastInsertId();

            // If add-on is public, cancel any pending files
            if ($addon['Addon']['status'] == STATUS_PUBLIC) {
                $this->Addon->execute("UPDATE files SET status = ".STATUS_SANDBOX." WHERE files.version_id IN (SELECT id FROM versions WHERE versions.addon_id={$addon_id}) AND files.status = ".STATUS_PENDING);
            }

            // Save appversions
            if (!empty($data['appversions'])) {
                foreach ($data['appversions'] as $appversion) {
                    $this->Version->addCompatibleApp($version_id, $appversion['application_id'], $appversion['min'], $appversion['max']);
                }
            }

            // notify subscribed editors of update (if any)
            $this->Editors->updateNotify($addon['Addon']['id'], $version_id);
        }

        // Add Files
        $data['File']['db']['version_id'] = $version_id;
        $platforms = $data['File']['db']['platform_id'];

        // Make trusted add-ons public
        if ($addon['Addon']['trusted'] == 1) {
            $data['File']['db']['status'] = STATUS_PUBLIC;
        }
        elseif ($addon['Addon']['status'] == STATUS_PUBLIC) {
            $data['File']['db']['status'] = STATUS_PENDING;
        }
        else {
            $data['File']['db']['status'] = STATUS_SANDBOX;
        }

        foreach ($platforms as $platform_id) {
            $this->File->id = 0;
            $data['File']['db']['platform_id'] = $platform_id;
            $validate = $this->Developers->moveFile($data);
            if (is_string($validate)) {
                // If a string is returned, there was an error
                return $this->Error->getJSONforError($validate);
            }
            $data['File']['db']['filename'] = $validate['filename'];
            
            $this->File->save($data['File']['db']);
            $file_id = $this->File->id;
        }

        // Remove temp file
        $tempFile = $data['File']['details']['path'];
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        $pending = $this->Addon->query("SELECT COUNT(*) AS pending FROM files WHERE status=".STATUS_PENDING." GROUP BY status");
        $pendingCount = (!empty($pending[0][0]['pending']) ? ($pending[0][0]['pending'] - 1) : 0);

        return array(
            'error' => 0,
            'uploadtype' => $type,
            'addon_id' => $addon_id,
            'version_id' => $version_id,
            'file_id' => $file_id,
            'version' => (string) $data['Version']['version'],
            'status' => $data['File']['db']['status'],
            'queuecount' => $pendingCount
        );
    }

    /**
     * Saves the data that was cached during asynchronous validation
     */
    function _saveCachedData($file_id, $filename = '') {
        $this->Amo->clean($filename);
        $data = $this->Addon->execute("SELECT * FROM `test_results_cache` WHERE `key` = '{$filename}' AND `test_case_id` != -1");

        $query = 'INSERT INTO `test_results` (`result`, `line`, `filename`, `message`, `file_id`, `test_case_id`) VALUES ';
        $sql = array();
        if (!empty($data)) {
            foreach ($data as $info) {
                $info = $info['test_results_cache'];
                $results = $info['value'];
                $this->Amo->unclean($results);
                $results = unserialize($results);
                $case = $info['test_case_id'];
                
                if (!empty($results)) {
                    foreach ($results as $result) {
                        $this->Amo->clean($result);
                        $sql[] = "({$result['result']}, {$result['line']}, '{$result['filename']}', '{$result['message']}', {$file_id}, {$case})";
                    }
                }
            }
         
            $query .= implode(', ', $sql);
            $this->Addon->execute($query);
        }
    }

    /**
     * Validates the file upload for all types of uploads
     */
    function _validateUpload($additional = '') {
        // This will store all data to be saved
        $addon = array();

        // Make sure a file was uploaded
        if (empty($_FILES['file']['name'])) {
            return $this->Error->getJSONforError(___('Please upload a file.'));
        }

        // Detect add-on type based on file
        $addon['Addon']['addontype_id'] = $this->Developers->detectAddontype($_FILES['file']);
        if (empty($addon['Addon']['addontype_id'])) {
            // Default to extension if add-on type undetectable. If this isn't
            // an add-on at all, it will be caught later with extension checks.
            $addon['Addon']['addontype_id'] = ADDON_EXTENSION;
        }

        // Validate file upload for basic errors and get some info
        $validate = $this->Developers->validateFile($_FILES['file'], $addon);
        if (is_string($validate)) {
            // If a string is returned, there was an error
            return $this->Error->getJSONforError($validate);
        }
        else {
            // If an array is returned, there were no errors
            $addon['File']['details'] = $validate;
            $addon['File']['db'] = array(
                'platform_id' => !empty($this->data['File']['platform_id']) ? $this->data['File']['platform_id'] : array(PLATFORM_ALL),
                'size' => $validate['size'],
                'filename' => $validate['filename'],
                'hash' => $validate['hash'],
                'status' => STATUS_SANDBOX,
                'datestatuschanged' => $this->Amo->getNOW()
            );
        }
        // Run validation tests here for inital group
        $allResults = array();
        if (!$this->Validation->runTest($validate['filename'],  1, $allResults)) {
            // If things failed, find out why 
            foreach ($allResults as $result) {
                if ($result['TestResult']['result'] == TEST_FAIL) {
                    return $this->Error->getJSONforError(sprintf(___('The add-on failed a validation test: %s'), $result['TestResult']['message']));
                }
            }
        }
        
        // Clear duplicates
        $filename = $validate['filename'];
        $this->Amo->clean($filename);
        $this->Addon->execute("DELETE FROM `test_results_cache` WHERE `key` = '{$filename}'");

        // Parse install.rdf file if not a search plugin
        if ($addon['Addon']['addontype_id'] != ADDON_SEARCH) {
            // Extract install.rdf from xpi or jar
            $zip = new Archive_Zip($addon['File']['details']['path']);
            $extraction = $zip->extract(array('extract_as_string' => true, 'by_name' => array('install.rdf')));

            $fileContents = $extraction[0]['content'];

            // Use RDF Component to parse install.rdf
            $manifestData = $this->Rdf->parseInstallManifest($fileContents);
            if (isset($manifestData['errors'])) {
                return $this->Error->getJSONforError($manifestData['errors'][0]);
            }

            // Clean manifest data
            $this->Amo->clean($manifestData);

            // Last minute add-on type correction
            if ($manifestData['type'] == 8) {
                $addon['Addon']['addontype_id'] = ADDON_LPAPP;
            }
            elseif ($manifestData['type'] == 4) {
                $addon['Addon']['addontype_id'] = ADDON_THEME;
            }

            $addon['Addon']['guid'] = $manifestData['id'];
            $addon['Addon']['name'] = $manifestData['name']['en-US'];
            $addon['Addon']['summary'] = $manifestData['description']['en-US'];
            $addon['Addon']['homepage'] = $manifestData['homepageURL'];
            $addon['Version']['version'] = $manifestData['version'];

            // Validate target applications
            $addon['appversions'] = $this->Developers->validateTargetApplications($manifestData['targetApplication']);
        }
        elseif ($addon['Addon']['addontype_id'] == ADDON_SEARCH) {
            // Get search engine properties
            $search = $this->Opensearch->parse($addon['File']['details']['path']);

            $addon['Addon']['name'] = $search->name;
            $addon['Addon']['summary'] = $search->description;
            $addon['Version']['version'] = date('Ymd');

            // Clean search engine data
            $this->Amo->clean($addon);
        }

        // Unbind add-ons
        $this->Addon->unbindFully();

        // For non-search-engines
        if ($additional == 'new') {
            if ($addon['Addon']['addontype_id'] != ADDON_SEARCH) {
                // Make sure GUID doesn't exist already
                if ($existing = $this->Addon->findAll(array('Addon.guid' => $addon['Addon']['guid']), array('guid'))) {
                    return $this->Error->getJSONforError(sprintf(___('This add-on ID (%1$s) already exists in the database. If this is your add-on, you can <a href="%2$s">upload a new version</a>.'), $addon['Addon']['guid'], $this->url("/developers/versions/add/{$existing[0]['Addon']['id']}")));
                }
            }
        } else {
            
            $addon_id = $this->data['Addon']['id'];
            $existing = $this->Addon->getAddon($addon_id, array('default_fields'));
            if ($existing['Addon']['addontype_id'] != ADDON_SEARCH) {
                // Make sure GUID matches add-on ID
                if ($addon['Addon']['guid'] != $existing['Addon']['guid']) {
                    return $this->Error->getJSONforError(sprintf(___('The add-on GUID used in this file (%1$s) does not match the existing GUID for this add-on (%2$s).'), $addon['Addon']['guid'], $existing['Addon']['guid']));
                }
            }

            if ($additional == 'update') {

                // Make sure version doesn't exist already
                $vcheck = $this->Version->find("Version.addon_id={$addon_id} AND Version.version='{$addon['Version']['version']}'");
                if (!empty($vcheck)) {
                    return $this->Error->getJSONforError(sprintf(___('The version number uploaded (%1$s) already exists for this add-on. If you are trying to add another file to this version, <a href="%2$s">click here</a>.'), $addon['Version']['version'], $this->url('/developers/versions/addfile/'.$vcheck['Version']['id'])));
                }
                
            } else if ($additional == 'file') {
                
                $version_id = $this->data['Version']['id'];
                
                // Make sure version id belongs to this add-on
                $vcheck = $this->Version->find("Version.id={$version_id} AND Version.addon_id={$addon_id}");
                if (empty($vcheck)) {
                    return $this->Error->getJSONforError(sprintf(___('The specified version (%1$s) does not belong to this add-on (%2$s).'), $version_id, $addon_id));
                }
                
                // Make sure version number matches
                if ($vcheck['Version']['version'] != $addon['Version']['version']) {
                    return $this->Error->getJSONforError(sprintf(___('The uploaded version number (%1$s) does not match the existing version number (%2$s).'), $addon['Version']['version'], $vcheck['Version']['version']));
                }

            }
        }

        $addon['error'] = 0;
        
        // Save some additional data for later
        $addon['Addon']['id'] = $this->data['Addon']['id'];
        $addon['Version']['id'] = $this->data['Version']['id'];
        $addon['License'] = $this->data['License'];
        $addon['Version.License.text'] = getitem($this->data, 'Version.License.text');
        $addon['form.data.Version.License'] = getitem($this->params, 'form.data.Version.License');

        // Save this data for insertion if/when things pass
        $data = serialize($addon);
        $this->Amo->clean($data);
        
        $filename = $addon['File']['db']['filename'];
        $this->Amo->clean($filename);
        $this->TestResult->execute("INSERT INTO `test_results_cache` (`date`, `key`, `test_case_id`, `value`) VALUES (NOW(), '{$filename}', -1, '{$data}')");

        return $addon;
    }

    function _verifyAuthor($email) {
        $this->Amo->clean($email);

        $result = $this->User->findByEmail($email);

        if (!empty($result)) {
            return array(
                'error' => 0,
                'id' => $result['User']['id'],
                'displayname' => "{$result['User']['firstname']} {$result['User']['lastname']} ({$result['User']['email']})"
            );
        }
        else {
            return $this->Error->getJSONforError(___('No account found for that email address.'));
        }
    }

    /**
     * Handler for subactions of editing an add-on
     * @param int $addon_id the add-on id
     * @param string $action the subaction to edit
     */
    function _editAddon($addon_id = 0, $action = null) {
        // Make sure add-on ID was passed
        if (empty($addon_id)) {
            $this->flash(___('Add-on not found!'), '/developers', 6);
            return;
        }

        $this->publish('action', 'edit');
        $this->publish('subaction', $action);

        switch ($action) {
            case 'properties':
                $this->setAction('_editAddonProperties', $addon_id);
                break;

            case 'descriptions':
                $this->setAction('_editAddonDescriptions', $addon_id);
                break;

            case 'categories':
                $this->setAction('_editAddonCategories', $addon_id);
                break;

            case 'authors':
                $this->setAction('_editAddonAuthors', $addon_id);
                break;

            case 'tags':
                $this->setAction('_editAddonTags', $addon_id);
                break;

            case 'profile':
                $this->setAction('_editProfile', $addon_id);
                break;

            case 'contributions':
                $this->setAction('_editContributions', $addon_id);
                break;

            default:
                $this->render('addon_edit');
                break;
        }

        return;
    }

    function _editProfile($addon_id) {
        // Save translations if POST data
        if (!empty($this->data['Addon']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_DEV) {
            $this->Addon->saveTranslations($addon_id,
                                           $this->params['form']['data']['Addon'],
                                           $this->data['Addon']);
            // flush cached add-on objects
            if (QUERY_CACHE)
                $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

            $this->publish('updated', true);
        }

        $translations = $this->Addon->getAllTranslations($addon_id);
        $has_profile = count($translations['the_reason']) + count($translations['the_future']) > 0;
        $addon = $this->Addon->findById($addon_id);

        $this->set('translations', $translations);
        $this->set('has_profile', $has_profile);
        $this->set('addon', $addon);
        return $this->render('addon_edit_profile');
    }

    function _editContributions($addon_id) {
        $this->Addon->id = $addon_id;

        if (!empty($this->data)) {
            if (isset($this->data['Addon']['paypal_id'])) {
                $this->_checkPaypalID($addon_id, $this->data['Addon']['paypal_id']);
            }

            // convert local decimal separators to point (bug 503033)
            $locale_info = localeconv();
            $this->data['Addon']['suggested_amount'] = str_replace(
                array($locale_info['decimal_point'], $locale_info['mon_decimal_point']),
                '.', $this->data['Addon']['suggested_amount']);

            if ($this->Addon->validates($this->data)) {
                $this->Addon->save($this->data);

                // flush cached add-on objects
                if (QUERY_CACHE)
                    $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

                $this->redirect("/developers/addon/edit/{$addon_id}/contributions");
            }
        }

        $addon = $this->Addon->getAddon($addon_id, array('list_details', 'contrib_details'));
        $a = $addon['Addon'];
        $this->set('addon', $addon);
        $this->set('a', $a);

        $translations = $this->Addon->getAllTranslations($addon_id);
        $has_profile = count($translations['the_reason']) + count($translations['the_future']) > 0;
        $show_intro = (empty($this->data) && empty($a['paypal_id']) && empty($a['suggested_amount']) || !$has_profile);
        $this->set('has_profile', $has_profile);
        $this->set('show_intro', $show_intro);

        if (empty($this->data)) {
            $this->data = $addon;
        }

        $this->set('paypal_disabled', $this->Config->getValue('paypal_disabled'));
        return $this->render('addon_edit_contributions');
    }

    function _checkPaypalID($addon_id, $paypal_id) {
        list($success, $response) = $this->Paypal->createButton($paypal_id);

        if (!$success) {
            $this->Addon->validationErrors['paypal_id'] = $response['L_LONGMESSAGE0'];
        }
    }

    /**
     * Edit Add-on Properties
     * @param int $addon_id the add-on id
     */
    function _editAddonProperties($addon_id) {
        // Save translations if POST data
        if (!empty($this->data['Addon']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_DEV) {
            // Split localized fields from other fields
            list($localizedFields, $unlocalizedFields) = $this->Addon->splitLocalizedFields($this->data['Addon']);

            // Handle icon before non-db fields are stripped
            if (!empty($unlocalizedFields['icon']['name'])) {
                $iconData = $this->Developers->validateIcon($unlocalizedFields['icon']);
                if (is_string($iconData)) {
                    $errors['icon'] = $iconData;
                }
                else {
                    $unlocalizedFields = array_merge($unlocalizedFields, $iconData);
                }
            }
            elseif (!empty($unlocalizedFields['deleteIcon'])) {
                // Delete icon if requested
                $unlocalizedFields['icontype'] = '';
                $unlocalizedFields['icondata'] = '';
            }

            // Make sure only allowed fields are saved
            $allowedFields = array('defaultlocale', 'viewsource', 'prerelease',
                                   'sitespecific', 'externalsoftware', 'binary',
                                   'icondata', 'icontype');

            // If an admin, allow additional fields
            if ($this->SimpleAcl->actionAllowed('Admin', 'ConfigureAnyAddon', $this->Session->read('User'))) {
                $allowedFields = array_merge($allowedFields, array(
                    'addontype_id', 'trusted', 'target_locale', 'locale_disambiguation', 'guid'
                ));
            }

            $unlocalizedFields = $this->Addon->stripFields($unlocalizedFields, $allowedFields);

            // Make sure all checkbox fields have values
            $checkboxes = array('prerelease', 'sitespecific', 'externalsoftware',
                                'binary');
            foreach ($checkboxes as $checkbox) {
                if (!isset($unlocalizedFields[$checkbox])) {
                    $unlocalizedFields[$checkbox] = 0;
                }
            }

            $this->Addon->id = $addon_id;
            $this->Addon->saveTranslations($addon_id, $this->params['form']['data']['Addon'], $localizedFields);
            $this->Addon->save($unlocalizedFields);

            if (empty($errors)) {
                $this->publish('success', true);
            }
            else {
                $this->publish('errors', $errors);
            }
        }

        $translations = $this->Addon->getAllTranslations($addon_id);
        $this->set('translations', $translations);

        $addon = $this->Addon->findById($addon_id);
        $this->set('addon', $addon);

        $this->set('addontypes', $this->Addontype->getNames());

        $this->render('addon_edit_properties');
    }

    /**
     * Edit Add-on Descriptions
     * @param int $addon_id the add-on id
     */
    function _editAddonDescriptions($addon_id) {
        // Save translations if POST data
        if (!empty($this->data['Addon']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_DEV) {
            $this->Addon->saveTranslations($addon_id, $this->params['form']['data']['Addon'], $this->data['Addon']);
            // flush cached add-on objects
            if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

            $this->publish('success', true);
        }

        $translations = $this->Addon->getAllTranslations($addon_id);
        $this->set('translations', $translations);

        $addon = $this->Addon->findById($addon_id);
        $this->set('addon', $addon);

        $this->render('addon_edit_descriptions');
    }

    function _editAddonCategories($addon_id) {
        // Save categories if POST data
        if (!empty($this->data['Category']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_DEV) {
            $this->Category->saveCategories($addon_id, $this->data['Category']);
            // flush cached add-on objects
            if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

            $this->publish('success', true);
        }

        $addon = $this->Addon->findById($addon_id);

        if ($addon['Addon']['addontype_id'] == ADDON_SEARCH) {
            // Search engines don't have supported applications
            $supportedApps = array(
                0 => array(
                        'Application' => array(
                            'id' => APP_FIREFOX
                        )
                    )
            );
        }
        else {
            // Get all applications the add-on has ever supported
            $supportedApps = $this->Addon->getApplicationsEverSupported($addon_id);
        }

        // All categories for add-on's type and supported applications
        $categoryDescriptions = array();
        $sortedCategories = array();
        if (!empty($supportedApps)) {
            foreach ($supportedApps as $supportedApp) {
                $categories = $this->Category->findAll("Category.addontype_id={$addon['Addon']['addontype_id']} AND Category.application_id={$supportedApp['Application']['id']}");

                $sorted = array();
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        $sorted[$category['Category']['id']] = $category['Translation']['name']['string'];
                        $categoryDescriptions[$category['Category']['id']] = $category['Translation']['description']['string'];
                    }
                    asort($sorted);
                }

                $sortedCategories[$supportedApp['Application']['id']] = $sorted;
            }
        }

        $this->set('sortedCategories', $sortedCategories);
        $this->set('categoryDescriptions', $categoryDescriptions);

        // Currently selected categories
        $currentCategories = array();
        if (!empty($addon['Category'])) {
            foreach ($addon['Category'] as $category) {
                $currentCategories[] = $category['id'];
            }
        }
        $this->publish('currentCategories', $currentCategories);

        $this->publish('applications', $this->Application->getIDList());

        // The "Other" category for each application that has one
        if ($addon['Addon']['addontype_id'] == ADDON_SEARCH) {
            $otherCategories = array(
                1 => 82
            );
        }
        else {
            $otherCategories = array(
                1 => 73,
                59 => 49,
                18 => 50,
            );
        }
        $this->publish('otherCategories', $otherCategories);

        $this->render('addon_edit_categories');
    }

    function _editAddonTags($addon_id) {
        $this->publish('jsAdd', array('tags.js'));

        // Save tags if POST data
        if (!empty($this->data['Tag']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_DEV) {
			// Add tags here


            // flush cached add-on objects
            if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

            $this->publish('success', true);
        }

        $addon_data = $this->Addon->getAddon($addon_id, array('all_tags', 'authors'));
        $this->publish('addon_data',$addon_data);

        // MAke the tag list, passing in this addon and the currently logged in user
        $loggedIn = $this->Session->check('User')? true : false;
        $this->set('loggedIn', $loggedIn);
        if ($loggedIn) { $user=$this->Session->read('User'); } else { $user=null; }

        // Get all tags
        $tags = $this->Tag->makeTagList($addon_data, $user, $this->SimpleAcl->actionAllowed('Admin', 'DeleteAnyTag', $user));

        $this->publish('userTags', $tags['userTags']);
        $this->publish('developerTags', $tags['developerTags']);
        $this->publish('addon_id', $addon_data['Addon']['id']);

          $this->render('addon_edit_tags');
    }





    /**
     * Edit Add-on Authors
     * @param int $addon_id the add-on id
     */
    function _editAddonAuthors($addon_id) {

        // Save authors if POST data
        if (!empty($this->data['addons_users']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_OWNER) {
            // Start a transaction
            $this->Addon->begin();

            // Clear current authors
            $this->Addon->clearAuthors($addon_id);

            // Add back authors
            $position = 1;
            foreach ($this->data['addons_users'] as $user_id => $fields) {
                $this->Amo->clean($user_id);
                $allowedRoles = array(AUTHOR_ROLE_OWNER, AUTHOR_ROLE_DEV, AUTHOR_ROLE_VIEWER);

                $role = $fields['role'];
                $role = in_array($role, $allowedRoles) ? $role : AUTHOR_ROLE_OWNER;
                $listed = !empty($fields['listed']) ? 1 : 0;

                $this->Addon->saveAuthor($addon_id, $user_id, $role, $listed, $position);
                $position++;
            }

            // Commit the transaction
            $this->Addon->commit();

            $this->publish('success', true);
        }

        $authors = $this->Addon->getAuthors($addon_id, false);
        $this->publish('authors', $authors);

        $this->render('addon_edit_authors');
    }

    /**
     * Change Add-on Status
     * @param int $addon_id the add-on id
     */
    function _changeAddonStatus($addon_id, $action = '') {
        $this->publish('action', 'status');

        if (!empty($action)) {
            $this->Addon->id = $addon_id;
            if (!$this->_addonStatusAction($action)) {
                return;
            }
        }

        $addon = $this->Addon->findById($addon_id, array('id', 'addontype_id', 'inactive', 'trusted', 'status', 'higheststatus'), null, -1);
        $this->set('addon', $addon);
        $this->publish('statuses', $this->Amo->getStatusNames());

        $this->publish('criteria', $this->_checkCriteria($addon_id));

        $nominated = $this->Addon->query("SELECT COUNT(*) AS nominated FROM addons WHERE status=".STATUS_NOMINATED." GROUP BY status");
        $this->publish('nominationCount', !empty($nominated[0][0]['nominated']) ? ($nominated[0][0]['nominated'] - 1) : 0);

        $this->render('addon_status');
    }

    /**
     * Checks criteria for add-on completion and nomination
     * @param int $addon_id the add-on id
     */
    function _checkCriteria($addon_id) {
        $addon = $this->Addon->findById($addon_id);
        $previews = $this->Preview->findAllByAddon_id($addon_id);
        $versions = array();
        if (!empty($addon)) {
            foreach ($addon['Version'] as $version) {
                $versions[] = $version['id'];
            }
        }
        if (!empty($versions)) {
            $versions = implode(',', $versions);
            $reviews = $this->Review->findAll("Review.version_id IN ({$versions})");
        }

        $criteria = array();
        $criteria['name'] = !empty($addon['Translation']['name']['string']);
        $criteria['summary'] = !empty($addon['Translation']['summary']['string']);
        $criteria['description'] = !empty($addon['Translation']['description']['string']);
        $criteria['category'] = !empty($addon['Category']);
        $criteria['previews'] = !empty($previews);
        $criteria['prerelease'] = !empty($addon['Addon']['prerelease']) ? false : true;

        return $criteria;
    }

    /**
     * Handles actions for changing statuses
     * @param string $action the action
     */
    function _addonStatusAction($action) {
        $this->publish('subaction', $action);

        $addon = $this->Addon->findById($this->viewVars['addon_id'], array('id', 'addontype_id', 'nominationmessage', 'status', 'higheststatus'), null, -1);
        $this->publish('addon', $addon);

        // Complete an add-on
        if ($action == 'complete' && $addon['Addon']['status'] == STATUS_NULL) {
            $criteria = $this->_checkCriteria($this->viewVars['addon_id']);

            // Make sure criteria is fulfilled
            if (!$criteria['name'] || !$criteria['summary'] || !$criteria['description'] || !$criteria['category']) {
                return true;
            }

            $addonData = array('status' => STATUS_SANDBOX, 'higheststatus' => STATUS_SANDBOX);
            $this->Addon->save($addonData);
            $this->publish('success', true);
            if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon['Addon']['id']}");
            return true;
        }

        // Other actions
        if (!empty($_POST['confirmed']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_DEV) {
            if ($action == 'inactive') {
                $addonData = array('inactive' => 1);
                $this->Addon->save($addonData);
                $this->publish('success', true);
            }
            elseif ($action == 'active') {
                $addonData = array('inactive' => 0);
                $this->Addon->save($addonData);
                $this->publish('success', true);
            }
            elseif ($action == 'sandbox') {
                if ($addon['Addon']['status'] == STATUS_PUBLIC) {
                    $addonData = array('status' => STATUS_SANDBOX);
                    $this->Addon->save($addonData);
                    $this->publish('success', true);
                }
            }
            elseif ($action == 'public') {
                if ($addon['Addon']['higheststatus'] == STATUS_PUBLIC && $addon['Addon']['status'] == STATUS_SANDBOX) {
                    $addonData = array('status' => STATUS_PUBLIC);
                    $this->Addon->save($addonData);
                    $this->publish('success', true);
                }
            }
            elseif ($action == 'nominate') {
                if ($addon['Addon']['status'] == STATUS_SANDBOX) {
                    $criteria = $this->_checkCriteria($this->viewVars['addon_id']);

                    if ((in_array($addon['Addon']['addontype_id'], array(ADDON_EXTENSION, ADDON_THEME)) && !$criteria['previews']) || !$criteria['prerelease']) {
                        return true;
                    }

                    if (empty($this->data['Addon']['nominationmessage'])) {
                        $this->publish('errors', true);
                        $this->render('addon_status_nominate');
                        return false;
                    }
                    $addonData = array('status' => STATUS_NOMINATED, 'nominationmessage' => $this->params['form']['data']['Addon']['nominationmessage'], 'nominationdate' => date('Y-m-d H:i:s'));
                    $this->Addon->save($addonData);
                    $this->publish('success', true);

                    // notify subscribed editors of update
                    global $valid_status;
                    $version_id = $this->Version->getVersionByAddonId($addon['Addon']['id'], $valid_status);
                    $this->Editors->updateNotify($addon['Addon']['id'], $version_id);
                }
            }

            return true;
        }

        if ($action == 'nominate') {
            $this->render('addon_status_nominate');
        }
        else {
            $this->render('addon_status_confirm');
        }

        return false;
    }

    /**
     * Handler for version-centric actions
     * @param string $action the action (in some cases this may be an add-on id)
     * @param int $version_id the version id
     */
    function versions($action, $version_id = 0) {
        $this->publish('action', 'versions');
        $this->publish('subaction', $action);

        // Get version information and add-on id
        if (!empty($version_id) && $action != 'add') {
            $version = $this->Version->findById($version_id, array('Version.id', 'Version.addon_id', 'Version.version'), null, -1);
            $addon_id = $version['Version']['addon_id'];
        }
        elseif ($action == 'add') {
            $addon_id = $version_id;
        }
        else {
            $addon_id = $action;
        }

        if (!empty($addon_id)) {
            // Make sure user has some permissions to view this add-on
            $role = $this->Amo->getAuthorRole($addon_id);
            if (empty($role)) {
                if ($this->SimpleAcl->actionAllowed('Editors', 'ViewValidation', $this->Session->read('User')) &&  $action == 'validate') {
                    $role = AUTHOR_ROLE_DEV;
                } else {
                    $this->Amo->accessDenied();
                }
            }

            $this->publish('author_role', $role);
            $this->publish('addon_name', $this->Addon->getAddonName($addon_id));
            $this->publish('addon_id', $addon_id);
        }
        else {
            $this->flash(___('Add-on not found!'), '/developers', 6);
            return;
        }

        switch ($action) {
            case 'add':
                $this->setAction('_addVersion', $addon_id);
                break;

            case 'addfile':
                $this->setAction('_addVersion', $addon_id, $version);
                break;

            case 'delete':
                $this->setAction('_deleteVersion', $version);
                break;

            case 'edit':
                $this->setAction('_editVersion', $version);
                break;

            case 'validate':
                $this->setAction('_validateVersion', $version);
                break;

            default:
                $this->setAction('_versionsIndex', $addon_id);
                break;
        }
    }

    /**
     * Versions listing/index
     * @param int $addon_id the add-on id
     */
    function _versionsIndex($addon_id) {
        $addon = $this->Addon->getAddon($addon_id, array('default_fields'));
        $this->set('addon', $addon);

        $versions = $this->Version->findAll("Version.addon_id={$addon_id}", null, 'Version.created DESC');
        $this->set('versions', $versions);

        $this->publish('statuses', $this->Amo->getStatusNames());

        $this->render('versions');
    }

    /**
     * Add a Version
     * @param int $addon_id the add-on id
     * @param array $version the version info
     */
    function _addVersion($addon_id, $version = '') {
        $type = !empty($version) ? 'file' : 'update';
        $this->publish('type', $type);

        if (!empty($version)) {
            $this->publish('version_id', $version['Version']['id']);
            $this->publish('version', $version['Version']['version']);
        }

        $addon = $this->Addon->getAddon($addon_id, array('default_fields'));
        $this->publish('hasAgreement', $addon['Addon']['dev_agreement']);

        $this->render('uploader');
    }

    /**
     * Delete a Version
     * @param array $version the version info
     */
    function _deleteVersion($version) {
        $version_id = $version['Version']['id'];
        $addon_id = $version['Version']['addon_id'];

        // Make sure user has permission
        if ($this->viewVars['author_role'] < AUTHOR_ROLE_DEV) {
            $this->flash(___('You do not have privileges to delete versions or files.'), '/developers/versions/edit/'.$version_id, 6);
            return;
        }

        // Get all version info
        $version = $this->Version->findById($version_id);

        if (empty($version['File']) || !empty($_POST['confirmDelete'])) {
            // If there are no files, we can delete without confirmation
            $this->Developers->deleteVersion($version_id);
            $this->Developers->postDelete($addon_id);

            // flush cached add-on objects
            if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

            $this->publish('deleteSuccess', true);
            $this->publish('deletedVersion', $version['Version']['version']);
            $this->_versionsIndex($addon_id);
            return;
        }
        else {
            // If there are files or if user hasn't confirmed, show confirmation view
            $this->set('version', $version);
            $this->render('versions_delete');
        }
    }

    /**
     * Edit a Version
     * @param array $version the version info
     */
    function _editVersion($version) {
        $version_id = $version['Version']['id'];
        $addon_id = $version['Version']['addon_id'];

        // Save data if POST data
        if (!empty($this->data['Version']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_DEV) {
            // Save translated fields (only releasenotes)
            list($localizedFields, $unlocalizedFields) = $this->Version->splitLocalizedFields($this->data['Version']);
            $this->Version->saveTranslations($version_id, $this->params['form']['data']['Version'], $localizedFields);

            // Save Version fields (only approvalnotes)
            $this->Version->id = $version_id;
            $this->Version->save(array(
                'approvalnotes' => $unlocalizedFields['approvalnotes']
            ));

            // Save target apps
            if (!empty($this->data['Application'])) {
                foreach ($this->data['Application'] as $application_id => $app) {
                    if (!empty($app['delete'])) {
                        // Remove the app
                        $this->Version->removeCompatibleApp($version_id, $application_id);
                    }
                    if (!empty($app['new'])) {
                        // Add a new app
                        $this->Version->addCompatibleApp($version_id, $application_id, $app['min'], $app['max']);
                    }

                    if (empty($app['delete']) && empty($app['new'])) {
                        // Normal update
                        $this->Version->updateCompatibility($version_id, $application_id, $app['min'], $app['max']);
                    }
                }
            }

            // Save file fields (only platform and deletion)
            if (!empty($this->data['File'])) {
                $allowedFileIDs = $this->Version->getFileIDs($version_id);

                foreach ($this->data['File'] as $file_id => $fields) {
                    if (!in_array($file_id, $allowedFileIDs)) {
                        // Make sure the file ID belongs to this version
                        continue;
                    }

                    // Delete if requested, otherwise update platform
                    if (!empty($fields['delete'])) {
                        $this->Developers->deleteFile($file_id, $addon_id);
                        $this->Developers->postDelete($addon_id);
                    }
                    else {
                        $this->File->id = $file_id;
                        $this->File->save(array(
                            'platform_id' => $fields['platform_id']
                        ));
                    }
                }
            }

            // Save license
            $license_id = $this->Developers->saveLicense(
                 $data['License'],
                 $data['Version.License.text'],
                 $data['form.data.Version.License']);
            $this->Version->saveField('license_id', $license_id);

            // flush cached add-on objects
            if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

            $this->publish('success', true);
        }

        // Get all version info
        $version = $this->Version->findById($version_id);

        // Get add-on info
        $addon = $this->Addon->findById($addon_id);
        $this->set('addon', $addon);

        $this->set('version', $version);

        // Get target app info
        $this->publish('targetApps', $this->Version->getCompatibleAppIds($version_id));
        $possibleVersions = $this->Appversion->getAllVersions();
        if (!empty($possibleVersions)) {
            foreach ($possibleVersions as $k => $v) {
                $this->Versioncompare->sortAppversionArray($possibleVersions[$k]);
            }
        }
        $this->publish('possibleVersions', $possibleVersions);

        // Get all translations
        $translations = $this->Version->getAllTranslations($version_id);
        if (isset($version['Version']['license_id'])) {
            $trans = $this->License->getAllTranslations($version['Version']['license_id']);
            $translations['license_text'] = $trans['text'];
        } else {
            $translations['license_text'] = array();
        }

        $this->set('translations', $translations);

        // Other info
        $this->publish('applications', $this->Application->getNames());
        $this->publish('statuses', $this->Amo->getStatusNames());
        $this->publish('platforms', $this->Platform->getNames());

        $this->render('versions_edit');
    }

    /**
     * View or run validation tests for a version
     * @param array version the version info
     */
    function _validateVersion($version) {

        // Load in all the version info we need
        $version = $this->Version->findById($version['Version']['id']);
        $addon = $this->Addon->getAddon($version['Version']['addon_id'], array('list_details'));

        $fileIds = array();

        // Pull in the files, which also brings in test result data
        if (!empty($version['File'])) {
            foreach($version['File'] as $file) {
                $fileIds[] = $file['id'];
            }
        }
        $files = $this->File->findAll(array('File.id' => $fileIds));

        // Each file needs its own copy of the test results
        if (!empty($files)) {
            foreach ($files as $id => $file) {
                $files[$id]['groups'] = array();
                $files[$id]['counts'] = array(0,0,0);
            }
        }

        $test_groups = $this->TestGroup->getTestGroupsForAddonType($addon['Addon']['addontype_id']);

        $test_groupIds = array();
        // Use the test group ids to pull in the results
        if (!empty($test_groups)) {
            foreach($test_groups as $id => $group) {
                $test_groupIds[] = $group['TestGroup']['id'];
                $test_groups[$id]['counts'] = array(0,0,0);
                $test_groups[$id]['cases'] = array();
			}
		}
		$test_results = $this->TestResult->findAll(array('TestCase.test_group_id' => $test_groupIds, 'TestResult.file_id' => $fileIds), array('*'), 'TestCase.test_group_id');

        // Build a hierarchical view that cake just doesn't give
        // us to make life easier in the view
        if (!empty($test_results)) {
            foreach ($test_results as $result) {

                // First, match to the right file
                $file_id = $result['TestResult']['file_id'];
                foreach ($files as $f_id => $file) {
                    if ($file['File']['id'] == $file_id) {

                        // Next match the result to the right group
                        $test_group_id = $result['TestCase']['test_group_id'];
                        foreach ($test_groups as $group_id => $group) {
                            if ($group['TestGroup']['id'] == $test_group_id) {

                                // Insert this group into the file if needed
                                if (empty($file['groups'][$test_group_id])) {
                                    $files[$f_id]['groups'][$test_group_id] = $group;
                                }

                                // Insert the case into this view if we haven't seen it yet
                                $case_id = $result['TestCase']['id'];
                                if (empty($files[$f_id]['groups'][$test_group_id]['cases'][$case_id])) {
                                    $files[$f_id]['groups'][$test_group_id]['cases'][$case_id] = $result['TestCase'];
                                    $files[$f_id]['groups'][$test_group_id]['cases'][$case_id]['results'] = array();
                                }

                                // Grab the formatted result view, and then insert into the
                                $this->Validation->getResultPreview($result, $files[0]);
                                $files[$f_id]['groups'][$test_group_id]['cases'][$case_id]['results'][] = $result['TestResult'];
                                $files[$f_id]['groups'][$test_group_id]['counts'][$result['TestResult']['result']]++;
                                $files[$f_id]['counts'][$result['TestResult']['result']]++;
                            }
                        }
                    }
                }
            }
        }


        // Most all of the data is in this array
        $this->publish('files', $files);

        $this->publish('all_groups', $test_groups);

        $this->publish('version', $version);
        $this->publish('addon', $addon);
        $this->publish('validation_disabled',$this->Config->getValue('validation_disabled'));

        $this->render('versions_validate');
    }

    /**
     * Verifies the addon using the test cases given by the validation component
     * @param int $file_id the id of the file to verify
     * @param int $test_group_id the id of the test group to run
     * @param string $file_name the temporary file name, if applicable
     */
    function verify($file_id, $test_group_id, $addon_type, $file_name = '') {

        // Don't show the view if validation is disabled.  OK to return
        // nothing here, since this view is just the AJAX handle
        if ($this->Config->getValue('validation_disabled')) {
            return;
        }

        // Pull in the test group
        $test_group = $this->TestGroup->findById($test_group_id);

        // Grab the file to pass over to the view
        $this->File->cacheQueries = false;
        if ($file_id != -1) {
            $file = $this->File->findById($file_id);
        } else {
            $file = array(
                'File' => array( 
                    'id' => '',
                    'filename' => $file_name
                ),
                'Version' => array(
                    'id' => '',
                    'addon_id' => 'temp'  // This allows us to grab the temp name
                )
            );
            $file_id = $file_name;
        }

        // Do whatever tests were specified, then find the next tests
        // if we need to continue
        $next_tests = array();
        $all_results = array();
        if ($this->Validation->runTest($file_id, $test_group_id, $all_results)) {
            $next_tier = $test_group['TestGroup']['tier'] + 1;
            $conditions = array('TestGroup.tier' => $next_tier);
            $next_cat = $test_group['TestGroup']['category'];
            if ($test_group_id != 1)
                $conditions['TestGroup.category'] = $next_cat;

            $next_tests = $this->TestGroup->getTestGroupsForAddonType($addon_type, $conditions, array('id'));
        }

        // Load the results into the group and build the group/case/result hierarchy
        if (is_numeric($file_id)) {
            $results = $this->TestResult->findAll(array('TestCase.test_group_id' => $test_group_id, 'TestResult.file_id' => $file_id));    
        } else { 
            $results = $all_results;
        }

        // Total results for this test group
        $counts = array(0,0,0);
        $test_group['cases'] = array();

        // Build a hierarchical view that cake just doesn't give
        // us to make life easier in the view
        if (!empty($results)) {
            foreach ($results as $result) {
                $case_id = $result['TestCase']['id'];
                if (empty($test_group['cases'][$case_id])) {
                    $test_group['cases'][$case_id] = $result['TestCase'];
                    $test_group['cases'][$case_id]['results'] = array();
                }
                $this->Validation->getResultPreview($result, $file);
                $test_group['cases'][$case_id]['results'][] = $result['TestResult'];
                $counts[$result['TestResult']['result']]++;
            }
        }
        $test_group['counts'] = $counts;

        // We need a view to call renderElement, see
        // https://trac.cakephp.org/ticket/3132
        // This means we also pull in the HTML helper
        $view = new View($this, 'helpers');
        loadHelper('Html');
        $html = new HtmlHelper();

        // Render the result, then return it via json
        $this->_sanitizeArray($test_group);
        $testresult = $view->renderElement('developers/testresults_group',
                      array('file' => $file, 'group' => $test_group, 'html' => $html));

        // Render the statistics for display in the header
        $stats = $view->renderElement('developers/testresults_stats',
                 array('counts' => $counts, 'short' => true, 'multiline' => false, 'html' => $html));

        $json = array('result' => $testresult, 'file_id' => $file_id, 'test_group_id' => $test_group_id, 'next_tests' => $next_tests, 'stats' => $stats, 'stats_data' => $counts);

        $this->set('json', $json);
        $this->render('json', 'ajax');
    }

    /**
     * Renderts the total stats after a series of tests
     * @param int $passes the total passes
     * @param int $warns the total warns
     * @param int $fails the total fails
     */
    function teststats($passes, $warns, $fails) {
        // We need a view to call renderElement, see
        // https://trac.cakephp.org/ticket/3132
        // This means we also pull in the HTML helper
        $view = new View($this, 'helpers');
        loadHelper('Html');
        $html = new HtmlHelper();

        $stats = $view->renderElement('developers/testresults_stats',
                 array('counts' => array($passes, $warns, $fails), 'short' => false, 'multiline' => false, 'html' => $html));
        $json = array('stats' => $stats);
        
        $this->set('json', $json);
        $this->render('json', 'ajax');
    }

    /**
     * Handler for preview-centric actions
     * @param string $action the action
     * @param int $preview_id the preview id
     */
    function previews($action, $preview_id = 0) {
        $this->publish('action', 'previews');
        $this->publish('subaction', $action);

        // Get addon id
        if (!empty($preview_id)) {
            $preview = $this->Preview->findById($preview_id);
            $addon_id = $preview['Preview']['addon_id'];
        }
        else {
            $addon_id = $action;
        }

        // Make sure user has some permissions to view this add-on
        $role = $this->Amo->getAuthorRole($addon_id);
        if (empty($role)) {
            $this->Amo->accessDenied();
        }

        $this->publish('author_role', $role);
        $this->publish('addon_name', $this->Addon->getAddonName($addon_id));
        $this->publish('addon_id', $addon_id);

        switch ($action) {
            case 'add':
                $this->setAction('_addPreview', $addon_id);
                break;

            case 'delete':
                $this->setAction('_deletePreview', $preview);
                break;

            case 'edit':
                $this->setAction('_editPreview', $preview);
                break;

            default:
                $this->setAction('_previewsIndex', $addon_id);
                break;
        }
    }

    function _previewsIndex($addon_id) {
        // If post data is present, dispatch accordingly
        if (!empty($this->data['Preview']) && $this->viewVars['author_role'] >= AUTHOR_ROLE_DEV) {
            $messages = array('success', 'errors');

            // Check if we're adding any previews
            if (!empty($this->data['Preview']['New'])) {
                $addReturn = $this->_addPreviews($addon_id);
                $messages = array_merge_recursive($messages, $addReturn);
            }

            // Check if we're replacing any previews
            if (!empty($this->data['Preview']['Replace'])) {
                $replaceReturn = $this->_addPreviews($addon_id);
                $messages = array_merge_recursive($messages, $replaceReturn);
            }

            // Save translated fields (only caption)
            foreach ($this->data['Preview'] as $preview_id => $fields) {
                if (!is_numeric($preview_id)) continue;

                list($localizedFields, $unlocalizedFields) = $this->Preview->splitLocalizedFields($fields);
                $this->Preview->saveTranslations($preview_id, $this->params['form']['data']['Preview'][$preview_id], $localizedFields);
            }

            // Check if we're deleting any previews
            if (!empty($this->data['Preview']['Delete'])) {
                $deleteReturn = $this->_deletePreviews($addon_id);
                $messages = array_merge_recursive($messages, $deleteReturn);
            }

            // Update the highlighted preview
            $this->Preview->saveHighlight($addon_id, $this->data['Preview']['highlight']);

            // flush cached add-on objects
            if (QUERY_CACHE) $this->Addon->Cache->markListForFlush("addon:{$addon_id}");

            // inform about cache lag, if any of the changes were successful
            if (!empty($messages['success'])) $messages['success'][] = ___('Please note that some changes may take several hours to appear in all areas of the website.');

            $this->publish('messages', $messages);
        }

        // Get add-on previews
        $previews = $this->Preview->findAllByAddon_id($addon_id);
        $this->set('previews', $previews);

        $translations = array();

        if (!empty($previews)) {
            foreach ($previews as $preview) {
                $translations[$preview['Preview']['id']] = $this->Preview->getAllTranslations($preview['Preview']['id']);
            }
        }
        $this->set('translations', $translations);

        $addon = $this->Addon->findById($addon_id);
        $this->set('addon', $addon);

        $this->render('previews');
    }

    function _addPreviews($addon_id) {
        $return = array();

        // Get IDs of existing previews
        $existing = $this->Preview->getIDsForAddon($addon_id);

        // Loop through each new preview
        foreach ($this->data['Preview']['New']['name'] as $id => $name) {
            if (empty($name)) continue;

            $tmp_name = $this->data['Preview']['New']['tmp_name'][$id];

            $previewData = array('addon_id' => $addon_id,
                                 'filedata' => file_get_contents($tmp_name),
                                 'filetype' => $this->data['Preview']['New']['type'][$id],
                                 'highlight' => 0,
                                 'thumbtype' => 'image/png'
                                 );

            // Check for allowed file extensions
            $extension = strtolower(substr($name, strrpos($name, '.')));
            if (!in_array($extension, $this->Developers->imageExtensions)) {
                $return['errors'][] = sprintf(___('File %1$s has an invalid extension (%2$s). Allowed extensions: %3$s'), $name, $extension, implode(', ', $this->Developers->imageExtensions));
                continue;
            }

            // Get image dimensions
            list($sourceWidth, $sourceHeight) = getimagesize($tmp_name);

            // Generate thumbnail (200 x 150)
            $previewData['thumbdata'] = $this->Developers->resizeImage($previewData['filedata'], $sourceWidth, $sourceHeight, 200, 150);

            // Resize preview if too large (700 x 525)
            if ($sourceWidth > 700 || $sourceHeight > 525) {
                $previewData['filedata'] = $this->Developers->resizeImage($previewData['filedata'], $sourceWidth, $sourceHeight, 700, 525);
                $previewData['filetype'] = 'image/png';
            }

            if (in_array($id, $existing)) {
                // Replacing existing preview
                $this->Preview->id = $id;
            }
            else {
                // Adding new preview
                $this->Preview->id = 0;
            }

            // Save preview to db
            if ($this->Preview->save($previewData)) {
                if (in_array($id, $existing))
                    $return['success'][] = sprintf(___('Preview %1$s was replaced with file %2$s successfully.'), $id, $name);
                else
                    $return['success'][] = sprintf(___('File %s was uploaded successfully. You can add a caption below.'), $name);
            }
            else
                $return['errors'][] = sprintf(___('File %s could not be saved to the database. Please try again.'), $name);
        }

        return $return;
    }

    function _deletePreviews($addon_id) {
        $return = array();

        // Get IDs of existing previews
        $existing = $this->Preview->getIDsForAddon($addon_id);

        // Loop through each preview
        foreach ($this->data['Preview']['Delete'] as $id => $delete) {
            if ($delete !== 'true') continue;

            // Delete the preview
            $this->Preview->id = $id;
            if ($this->Preview->delete())
                $return['success'][] = sprintf(___('Preview %s has been deleted successfully.'), $id);
            else
                $return['errors'][] = sprintf(___('Preview %s could not be deleted from the database. Please try again.'), $id);
        }

        return $return;
    }

    /**
     * Discuss a review request with an editor
     */
    function discuss($infoid) {
        global $valid_status;

        $inforequest = $this->Approval->findById($infoid);
        if (empty($inforequest)) {
            $this->flash(___('Add-on not found!'), '/developers/index');
            return;
        }
        // Make sure user has some permissions to view this add-on
        $session = $this->Session->read('User');
        $isEditor = $this->SimpleAcl->actionAllowed('Editors', '*', $session);
        $role = $this->Amo->getAuthorRole($inforequest['Approval']['addon_id']);
        if (!$isEditor && empty($role)) $this->Amo->accessDenied();

        $this->publish('inforequest', $inforequest);

        $addon = $this->Addon->getAddon($inforequest['Approval']['addon_id'], array('authors'));
        $this->publish('addonName', $addon['Translation']['name']['string']);

        $versionid = $this->Version->getVersionByAddonId($addon['Addon']['id'], $valid_status);
        $version = $this->Version->findById($versionid, null, null, -1);
        $this->publish('versionno', $version['Version']['version']);

        // grab replies
        $replies = $this->Approval->findAll(array('reply_to' => $infoid), null, 'Approval.created');
        $this->publish('replies', $replies);

        if (!empty($this->data)) {
            $session = $this->Session->read('User');

            //Auto-detect addontype if necessary
            if ($this->data['Addon']['addontype_id'] == 0) {
                $this->data['Addon']['addontype_id'] = $this->Developers->detectAddontype($this->data['File']['file1']);
                $this->publish('autoDetected', $this->Addontype->getName($this->data['Addon']['addontype_id']));
            }

            //Make sure addontype is allowed
            $allowedAddonTypes = $this->Developers->getAllowedAddonTypes(false, $this->SimpleAcl->actionAllowed('*', '*', $this->Session->read('User')));
            if (!array_key_exists($this->data['Addon']['addontype_id'], $allowedAddonTypes)) {
                $this->Error->addError(___('Invalid add-on type'));
            }

            //Validate files
            $this->Developers->validateFiles();

            // reply submitted
            $approvalData = array(
                'user_id' => $session['id'],
                'reviewtype' => 'info',
                'action' => 0,
                'reply_to' => $infoid,
                'addon_id' => $addon['Addon']['id'],
                'comments' => $this->data['Approval']['comments']
            );
            if (true === $this->Approval->save($approvalData)) {
                $this->set('success', true);

                // add this to the replies set
                $replies[] = $this->Approval->findById($this->Approval->getLastInsertID());
                $this->publish('replies', $replies);

                // send email to all authors and the editor, but not the current user
                $recipients = array();
                foreach ($addon['User'] as &$user) $recipients[] = $user['email'];
                $recipients[] = $inforequest['User']['email'];
                foreach ($replies as &$reply) $recipients[] = $reply['User']['email'];
                $recipients = array_diff(array_unique($recipients), array($session['email'])); // remove current user

                $emailInfo = array(
                    'name' => $addon['Translation']['name']['string'],
                    'infoid' => $infoid,
                    'sender' => $session['firstname'].' '.$session['lastname'],
                    'comments' => $this->data['Approval']['comments'],
                    'version' => !empty($version) ? $version['Version']['version'] : ''
                );
                $this->publish('info', $emailInfo, false);
                $this->Email->template = '../editors/email/inforequest_reply';
                $this->Email->subject = sprintf('Mozilla Add-ons: %s %s', $emailInfo['name'], $emailInfo['version']);
                foreach ($recipients as &$recipient) {
                    $this->Email->to = $recipient;
                    $this->Email->send();
                }
            }
        }
        $this->render();
    }

    /**
     * Endpoint: /developers/contributions/example/(passive|after|roadblock)
     *
     * Displays an image and caption showing the workflows of different
     * contribution annoyance levels.  Mostly intended for xhr modal dialog,
     * but can also be a barebones fallback for non-js.
     */
    function contributions($ignored='/example/', $example) {
        // Need separate cases for the text so gettext can see each one.
        switch ($example) {
        case 'passive':
            $text = ___('A new section is added to your add-on\'s page and Developer Profile requesting a contribution from your users.');
            break;
        case 'after':
            $text = ___('When users begin downloading this add-on they are taken to your Developer Profile, where they are asked to make a contribution.');
            break;
        case 'roadblock':
            $text = ___('When users try to install this add-on they are taken to your Developer Profile first, where they will be asked to make a contribution. Users must click the install button again to complete the installation.');
            break;
        default:
            return $this->cakeError('error404');
        }
        $this->set('text', $text);
        $this->set('image', $example);
        $this->render('contrib_example');
    }
}
?>
