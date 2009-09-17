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
 *    Wil Clouser <wclouser@mozilla.com>
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

vendor('sphinx/addonsSearch');
vendor('zxtm-api/moz_zxtmapi.class');

class AdminController extends AppController
{
    var $name = 'Admin';

    var $uses = array('Addon', 'Addonlog', 'Addontype', 'Application', 'Approval', 'Appversion', 'BlacklistedGuid', 'Category', 'Cannedresponse', 'Collection', 'CollectionFeatures', 'CollectionPromo', 'Eventlog', 'Feature', 'File', 'Group', 'HubEvent', 'HubPromo', 'Platform', 'Tag', 'Translation', 'User', 'Version', 'Memcaching');
    var $components = array('Amo', 'Audit', 'Developers', 'Error', 'Hub', 'Versioncompare', 'Pagination');
    var $helpers = array('Html', 'Javascript', 'Pagination');

    //These defer to their own access checks
    var $aclExceptions = array('index', 'summary',
                               'addonLookup', 'userLookup',
                               'addontypes', 'categories', 'platforms', 'responses');

   /**
    * Require login for all actions
    */
    function beforeFilter() {
        //beforeFilter() is apparently called before components are initialized. Cake++
        $this->Amo->startup($this);
        
        $this->Amo->checkLoggedIn();
        
        //Clean post data
        $this->Amo->clean($this->data, false); 

        $this->layout = 'mozilla';
        $this->pageTitle = 'Mozilla Add-ons :: Admin Control Panel';
	
        $this->cssAdd = array('admin', 'developers');
        $this->set('cssAdd', $this->cssAdd);
        
        $this->set('jsAdd', array('developers',
                                  'jquery-compressed.js',
                                  'jquery.autocomplete.pack.js'));
        $this->set('suppressJQuery', 1);
        
        $this->breadcrumbs = array('Admin Control Panel' => '/admin/index');
        $this->set('breadcrumbs', $this->breadcrumbs);

        $this->set('subpagetitle', 'Admin Control Panel');
        
        // disable query caching so devcp changes are visible immediately
        foreach ($this->uses as $_model) {
            $this->$_model->caching = false;
        }
        
        //Get flagged add-on count
        $flagged = $this->Addon->query("SELECT COUNT(*) FROM addons WHERE adminreview=1");
        $this->set('flaggedCount', $flagged[0][0]['COUNT(*)']);

        // Used for Feature Manager
            global $valid_languages;

            //First, see if locale was submitted by form
            if (!empty($_GET['userlang']) && array_key_exists($_GET['userlang'], $valid_languages)) {
                define('USERLANG', $_GET['userlang']);
            }
            elseif (!empty($_GET['userlang']) && $_GET['userlang'] == 'Unspecified') {
                define('USERLANG', null);
            }
            //Next, try looking in the session
            elseif ($lsession = $this->Session->read('Features')) {
                define('USERLANG', $lsession['userlang']);
            }
            
            if (!defined('USERLANG')) {
                define('USERLANG', LANG);
            }

            //See if application was submitted by form (this is an ID)
            if (!empty($_GET['userapp'])) {
                define('USERAPP', $_GET['userapp']);
            }
            //Next, try looking in the session
            elseif ($lsession = $this->Session->read('Features')) {
                define('USERAPP', $lsession['userapp']);
            }
            
            if (!defined('USERAPP')) {
                define('USERAPP', 1); // Firefox
            }

            $this->Session->write('Features', array('userlang' => USERLANG, 'userapp' => USERAPP));
    }
    
   /**
    * Index - Show summary
    */
    function index() {
        $this->summary();
    }
    
   /**
    * Admin Summary
    */
    function summary() {
        if (!$this->SimpleAcl->actionAllowed('Admin', '%', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }
        
        $this->cssAdd[] = 'summary';
        $this->publish('cssAdd', $this->cssAdd);
        
        //Most translations
        $topLocales = $this->Translation->query("SELECT locale, COUNT(*) as total FROM translations GROUP BY locale ORDER BY total DESC LIMIT 5");
        $this->set('topLocales', $topLocales);
        
        //Last 24 hours
        $timestamp = date('Y-m-d H:i:s', (time() - 86400));
        $last24['newAddons'] = $this->Addon->query("SELECT COUNT(*) FROM addons WHERE created >= '{$timestamp}'");
        $last24['updatedAddons'] = $this->Addon->query("SELECT COUNT(*) FROM addons WHERE modified >= '{$timestamp}'");
        $last24['versions'] = $this->Addon->query("SELECT COUNT(*) FROM versions WHERE created >= '{$timestamp}'");
        $last24['users'] = $this->Addon->query("SELECT COUNT(*) FROM users WHERE created >= '{$timestamp}'");
        $last24['reviews'] = $this->Addon->query("SELECT COUNT(*) FROM reviews WHERE created >= '{$timestamp}'");
        $this->set('last24', $last24);
        
        //Counts
        $count['extensions'] = $this->Addon->query("SELECT COUNT(*) FROM addons WHERE addontype_id=".ADDON_EXTENSION);
        $count['themes'] = $this->Addon->query("SELECT COUNT(*) FROM addons WHERE addontype_id=".ADDON_THEME);
        $count['dictionaries'] = $this->Addon->query("SELECT COUNT(*) FROM addons WHERE addontype_id=".ADDON_DICT);
        $count['searchengines'] = $this->Addon->query("SELECT COUNT(*) FROM addons WHERE addontype_id=".ADDON_SEARCH);
        $now = time();
        $count['activeSessions'] = $this->Addon->query("SELECT COUNT(*) FROM cake_sessions WHERE expires > {$now}");
        $this->set('count', $count);
        
        //Recent activity
        $logs = $this->Eventlog->findAll(array('type' => 'admin'), null, 'Eventlog.created DESC', 5);
        $logs = $this->Audit->explainLog($logs);
        $this->set('logs', $logs);
        
        $this->set('page', 'summary');
        $this->render('summary');
    }

   /**
    * Add-on Manager
    */
    function addons($action = '', $id = 0, $file_id = 0) {
        $this->breadcrumbs['Add-on Manager'] = '/admin/addons';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($id)) {
            $this->Amo->clean($id);
            $this->Addon->id = $id;
            
            if ($action == 'status') {
                $this->_addonStatus($id);
                return;
            }
            elseif ($action == 'hash') {
                $this->_addonHash($id, $file_id);
                return;
            }
        }
        
        if (!empty($_GET['q'])) {
            $q = $_GET['q'];
            $this->Amo->clean($q);
            
            //See if query has an add-on id in brackets
            if (preg_match('/\[(\d+)\]/', $q, $matches)) {
                $this->Addon->id = $matches[1];
                $this->_addonStatus($this->Addon->id);
                return;
            }
            //Find add-on by exact name. If not found, use next closest
            elseif ($addon = $this->Addon->query("SELECT addons.id FROM addons LEFT JOIN translations ON addons.name=translations.id WHERE translations.locale='".LANG."' AND translations.localized_string='{$q}' LIMIT 1")) {
                $this->data = '';
                $this->Addon->id = $addon[0]['addons']['id'];
                $this->_addonStatus($this->Addon->id);
                return;
            }
            elseif ($addon = $this->Addon->query("SELECT addons.id FROM addons LEFT JOIN translations ON addons.name=translations.id WHERE translations.locale='".LANG."' AND translations.localized_string LIKE '%{$q}%' LIMIT 1")) {
                $this->data = '';
                $this->Addon->id = $addon[0]['addons']['id'];
                $this->_addonStatus($this->Addon->id);
                return;  
            }
        }
        
        $this->set('page', 'addons');
        $this->render('addons');
    }
    
   /**
    * Manage add-on statuses
    */
    function _addonStatus($id) {
        if (!empty($this->data)) {
            //Fetch add-on to determine if we need to log a status change
            $addon = $this->Addon->getAddon($id);

            $this->Addon->save($this->data['Addon']);
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'addon_status', 'status', $id, $this->data['Addon']['status']);
            //Log addon action if that status has changed
            if ($this->data['Addon']['status'] != $addon['Addon']['status']) {
                $this->Addonlog->logChangeStatus($this, $id, $this->data['Addon']['status']);
            }
            
            if (!empty($this->data['File'])) {
                foreach ($this->data['File']['id'] as $k => $file_id) {
                    $this->File->id = $file_id;
                    $file = $this->File->read();
                    if ($file['File']['status'] != $this->data['File']['status'][$k]) {
                        // status was changed
                        $this->File->save(array(
                            'status' => $this->data['File']['status'][$k],
                            'datestatuschanged' => $this->Amo->getNOW()
                            ));
                    
                        // If public, move to public rsync area
                        if ($this->data['File']['status'][$k] == STATUS_PUBLIC) {
                            $this->Amo->copyFileToPublic($id, $file['File']['filename']);
                        }
                    }
                }
            }
            
            $this->flash('Statuses updated!', '/admin/addons/status/'.$id);
            return;            
        }
        
        $platforms = $this->Amo->getPlatformName();
        $this->set('platforms', $platforms);
        
        $statuses = $this->Amo->getApprovalStatus();
        $this->set('addonStatuses', array(
                              STATUS_NULL => $statuses[STATUS_NULL],
                              STATUS_SANDBOX => $statuses[STATUS_SANDBOX],
                              STATUS_NOMINATED => $statuses[STATUS_NOMINATED],
                              STATUS_PUBLIC => $statuses[STATUS_PUBLIC],
                              STATUS_DISABLED => $statuses[STATUS_DISABLED]
                             ));
        $this->set('fileStatuses', array(
                              STATUS_NULL => $statuses[STATUS_NULL],
                              STATUS_SANDBOX => $statuses[STATUS_SANDBOX],
                              STATUS_PENDING => $statuses[STATUS_PENDING],
                              STATUS_PUBLIC => $statuses[STATUS_PUBLIC],
                              STATUS_DISABLED => $statuses[STATUS_DISABLED]
                             ));
        
        if ($addon = $this->Addon->read()) {
            foreach ($addon['Version'] as $k => $version) {
                $files = $this->File->findAllByVersion_id($version['id']);
                $addon['Version'][$k]['File'] = $files;
            }
        }
        
        $this->set('addon', $addon);
        
        $this->set('page', 'addons');
        $this->render('addons_status');
    }
    
   /**
    * Recalculate file hash and size
    */
    function _addonHash($id, $file_id) {
        $this->File->id = $file_id;
        $file = $this->File->read();
        
        $file = REPO_PATH.'/'.$id.'/'.$file['File']['filename'];
        if (file_exists($file)) {
            $size = round(filesize($file)/1024, 0); //in KB
            $hash = 'sha256:'.hash_file("sha256", $file);
            
            $this->File->save(array('size' => $size, 'hash' => $hash));
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'file_recalchash', null, $file_id);
            
            $this->flash('File hash and size recalculated!', '/admin/addons/status/'.$id);
        }
        else {
            $this->flash('File does not exist.', '/admin/addons/status/'.$id);
        }
        return;
    }
    
   /**
    * Application Manager
    */
    function applications($action = '', $id = 0) {
        $this->breadcrumbs['Application Manager'] = '/admin/applications';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($action)) {
            $this->Amo->clean($id);
            $this->Amo->clean($_POST);
            
            if ($action != 'versions') {
                if ($action == 'edit') {
                    $this->_applicationEdit($id);
                }
                elseif ($action == 'create') {
                    $this->_applicationCreate();
                }
                return;
            }
            
            //Add new version
            if (!empty($_POST['add'])) {
                $version = $_POST['app'.$id.'_new'];
                if (!empty($version)) {
                    $version_int = AddonsSearch::convert_version($version);
                    $this->Appversion->execute("INSERT INTO appversions (application_id, version, version_int) VALUES('{$id}', '{$version}', '{$version_int}')");
                    
                    //Log admin action
                    $appversion_id = mysql_insert_id();
                    $this->Eventlog->log($this, 'admin', 'appversion_create', null, $appversion_id, $version, null, $id);

                    //Log addon action
                    $this->Addonlog->logAddAppversion($this, $id, $appversion_id, $version);
                    
                    $this->flash('Version successfully added!', '/admin/applications');
                    return;
                }
                else {
                    $this->Error->addError('Cannot add empty version.');
                }
            }
            
            //Remove version
            if (!empty($_POST['remove'])) {
                $vid = $_POST['app'.$id.'_remove'];
                if (!empty($vid)) {
                    //Pull appversion for log
                    $this->Appversion->id = $vid;
                    $appversion = $this->Appversion->read();
                    
                    $count = $this->Appversion->query("SELECT COUNT(*) FROM applications_versions WHERE min='{$vid}' OR max='{$vid}'");
                    
                    if ($count[0][0]['COUNT(*)'] == 0) {
                        $this->Appversion->execute("DELETE FROM appversions WHERE id='{$vid}' LIMIT 1");   
                        
                        //Log admin action
                        $this->Eventlog->log($this, 'admin', 'appversion_delete', null, $vid, null, $appversion['Appversion']['version'], $id);
                        
                        $this->flash('Version successfully removed!', '/admin/applications');
                    }
                    else {
                        $this->flash('Cannot delete application version: files still associated with it.', '/admin/applications');
                    }
                    return;
                }
                else {
                    $this->Error->addError('Please select a version to remove.');
                }
            }
        }
        
        if ($applications = $this->Application->findAll(null, null, null, null, null, -1)) {
            foreach ($applications as $k => $application) {
                $appversions = $this->Appversion->findAllByApplication_id($application['Application']['id'], null, null, null);
                $this->Versioncompare->sortAppversionArray($appversions);
                $applications[$k]['Appversions'] = $appversions;
            }
        }
        
        $this->set('applications', $applications);
        $this->set('errors', $this->Error->errors);
        $this->set('page', 'applications');
        $this->render('applications');
    }
    
   /**
    * Edit Applications
    */
    function _applicationEdit($id) {
        $this->breadcrumbs['Edit Application'] = '/admin/applications/edit/'.$id;
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->Application->id = $id;
        
        if (!empty($this->data)) {
            $this->Application->save($this->data['Application']);
            
            //Save translated fields (name, shortname)
            $this->Developers->saveTranslations($this->data, array('Application'));
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'application_edit', null, $id);
            
            $this->flash('Application updated!', '/admin/applications');
            return;
        }
        
        $application = $this->Application->findById($this->Application->id, null, null, -1);
        
        $this->set('application', $application);
        
        $localizedFields = array(
                                'name' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Application Name',
                                                    'model'       => 'Application',
                                                    'field'       => 'name',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                ),
                                'shortname' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Application Shortname',
                                                    'model'       => 'Application',
                                                    'field'       => 'shortname',
                                                    'attributes'  => array()
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Application->setLang($key, $this);
            $appL = $this->Application->findById($this->Application->id, null, null, -1);

            foreach ($appL['Translation'] as $field => $translation) {
                if ($translation['locale'] == $key) {
                    $info[$key][$field] = $translation['string'];
                }
                else {
                    $info[$key][$field] = '';
                }
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'applications');
        $this->render('applications_edit');    
    }
    
   /**
    * Create Application
    */
    function _applicationCreate() {
        $this->breadcrumbs['Create Application'] = '/admin/applications/create';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($this->data)) {
            $this->Application->save($this->data['Application']);
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'application_create', null, $this->Application->getLastInsertID());
            
            //Save translated fields (name, description)
            $this->Developers->saveTranslations($this->data, array('Application'));
            
            $this->flash('Application created!', '/admin/applications');
            return;  
        }

        $localizedFields = array(
                                'name' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Application Name',
                                                    'model'       => 'Application',
                                                    'field'       => 'name',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                ),
                                'shortname' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Application Shortname',
                                                    'model'       => 'Application',
                                                    'field'       => 'shortname',
                                                    'attributes'  => array()
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Application->setLang($key, $this);

            foreach ($this->Application->translated_fields as $field) {
                $info[$key][$field] = '';
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'applications');
        $this->render('applications_create');    
    }

   /**
    * Collections Manager
    */
    function collections($action='', $subaction='', $id=0) {
        switch($action) {
            case 'promobox':
                $this->_collectionsPromoBox();
                break;
            case 'promoboxstructure':
                $this->_collectionsPromoBoxStructure($subaction, $id);
                break;
            default:
                $this->set('page', 'collections');
                $this->render('collections');
                break;
        }
    }

    function _collectionsPromoBox() {
        if (!empty($_POST)) {
            switch ($_POST['action']) {
                case 'add':
                    global $valid_languages;
                    // It's easiest just to split out the stuff we need here and pass it into a custom function.
                    if (preg_match('/\[(\d+)\]/', $this->data['Collection']['name'], $matches)) {
                        $_collection_id = $matches[1];
                    } else {
                        $this->Error->addError('Collection ID must be specified in brackets.');
                    }

                    if (in_array($_POST['locale'], array_keys($valid_languages))) {
                        $_locale = $_POST['locale'];
                    } else if ($_POST['locale'] == 'all') {
                        $_locale = '';
                    } else {
                        $this->Error->addError('Invalid locale specified.');
                    }

                    if (in_array($_POST['titletagline'], array_keys($this->CollectionFeatures->getTitlesAndTaglinesById()))) {
                        $_titletagline = $_POST['titletagline'];
                    } else {
                        $this->Error->addError('Invalid Title/TagLine specified.');
                    }

                    if ($this->Error->noErrors()) {
                        $this->CollectionPromo->promoteCollection($_collection_id, $_titletagline, $_locale);
                        $this->data = array(); // reset so it doesn't prefill the box again
                        $this->publish('changeSuccess', true);
                    }
                    break;
                case 'delete':
                    list($_collection_id, $_titletagline, $_locale) = explode('--',$_POST['target']);
                    $_locale = ($_locale == 'all') ? '' : $_locale;

                    if ($this->CollectionPromo->demoteCollection($_collection_id, $_titletagline, $_locale) === false) {
                        $this->Error->addError('Failed to demote collection.');
                    } else {
                        $this->publish('changeSuccess', true);
                    }
                    break;
            }
        }

        $this->set('titles_and_taglines', $this->CollectionFeatures->getTitlesAndTaglinesById());
        $this->publish('promoted_collections', $this->CollectionPromo->findAll());
        $this->set('locales', LANGUAGE_CONFIG::getAllValidLanguages('english',true));

        $this->set('errors', $this->Error->errors);
        $this->set('page', 'collections');
        $this->set('subpage', 'promobox');
        $this->render('collections_promobox');
    }

    function _collectionsPromoBoxStructure($action = '', $id = 0) {
        //Part of the Lists permission
        if (!$this->SimpleAcl->actionAllowed('Admin', 'lists', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }

        $this->breadcrumbs['Collections Features'] = '/admin/collections/promoboxstructure';
        $this->set('breadcrumbs', $this->breadcrumbs);

        if (!empty($action)) {
            $this->Amo->clean($id);
            
            if ($action == 'edit') {
                $this->_collectionFeatureEdit($id);
                return;
            }
            elseif ($action == 'create') {
                $this->_collectionFeatureCreate($id);
                return;
            }
        }
        
        $collection_features = $this->CollectionFeatures->findAll(null, null, null, null, null, -1);
        
        $this->set('collection_features', $collection_features);
        $this->set('page', 'collections');
        $this->set('subpage', 'promobox');
        $this->render('collection_features');
    }

    function _collectionFeatureEdit($id) {
        $this->breadcrumbs['Edit Collection Features'] = '/admin/collections/promoboxstructure/edit'.$id;
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->CollectionFeatures->id = $id;

        if (!empty($this->data)) {
            //Delete
            if (!empty($_POST['delete'])) {
                //Retrieve platform to store name in log
                $feature = $this->CollectionFeatures->findById($this->CollectionFeatures->id, null, null, -1);
                
                $this->CollectionFeatures->execute("DELETE FROM collection_features WHERE id='{$id}'");
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'collection_feature_delete', null, $id, null, $feature['Translation']['title']['string']);
                
                $this->flash('Feature deleted!', '/admin/collections/promoboxstructure');
                return;
            }
            //Edit
            else {
                //Save translated fields (name, description)
                $this->Developers->saveTranslations($this->data, array('CollectionFeatures'));
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'collection_feature_edit', null, $id);
                
                $this->flash('Feature updated!', '/admin/collections/promoboxstructure');
                return;
            }
        }
        
        $feature = $this->CollectionFeatures->findById($this->CollectionFeatures->id, null, null, -1);
        
        $this->set('feature', $feature);

        $localizedFields = array(
                                'title' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Title',
                                                    'model'       => 'CollectionFeatures',
                                                    'field'       => 'title',
                                                    'attributes'  => array( 'size' => 40)
                                ),
                                'tagline' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Tag Line',
                                                    'model'       => 'CollectionFeatures',
                                                    'field'       => 'tagline',
                                                    'attributes'  => array( 'size' => 40)
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->CollectionFeatures->setLang($key, $this);
            $featureL = $this->CollectionFeatures->read();

            foreach ($featureL['Translation'] as $field => $translation) {
                if ($translation['locale'] == $key) {
                    $info[$key][$field] = $translation['string'];
                }
                else {
                    $info[$key][$field] = '';
                }
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'collections');
        $this->set('subpage', 'promobox');
        $this->render('collection_features_edit');
    }
    function _collectionFeatureCreate($id) {
        $this->breadcrumbs['Create Collection Features'] = '/admin/collections/promoboxstructure/create';
        $this->set('breadcrumbs', $this->breadcrumbs);

        if (!empty($this->data)) {
            //Save translated fields (title, tagline)
            $this->Developers->saveTranslations($this->data, array('CollectionFeatures'));
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'collection_feature_create', null, $this->Tag->getLastInsertID());
            
            $this->flash('Feature created!', '/admin/collections/promoboxstructure');
            return;  
        }
        
        $localizedFields = array(
                                'title' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Title',
                                                    'model'       => 'CollectionFeatures',
                                                    'field'       => 'title',
                                                    'attributes'  => array( 'size' => 40)
                                ),
                                'tagline' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Tag Line',
                                                    'model'       => 'CollectionFeatures',
                                                    'field'       => 'tagline',
                                                    'attributes'  => array( 'size' => 40)
                                )
                   );
                
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->CollectionFeatures->setLang($key, $this);

            foreach ($this->CollectionFeatures->translated_fields as $field) {
                $info[$key][$field] = '';
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'collections');
        $this->set('subpage', 'promobox');
        $this->render('collection_features_create');
    }

   /**
    * Developers Hub Manager
    */
    function developershub($action='', $subaction='', $id=0) {
        $this->breadcrumbs['Developers Hub Manager'] = '/admin/developershub';
        $this->set('breadcrumbs', $this->breadcrumbs);

        switch($action) {
            case 'events':
                $this->_developershubEvents($subaction, $id);
                break;
            case 'newsfeed':
                $this->_developershubNewsfeed($subaction, $id);
                break;
            case 'promoboxes':
                $this->_developershubPromoBoxes($subaction, $id);
                break;
            default:
                $this->set('page', 'developershub');
                $this->set('subpage', '');
                $this->render('developershub');
                break;
        }
    }

   /**
    * Developers Hub PromoBox Manager
    */
    function _developershubPromoBoxes($action = '', $id = 0) {
        $this->breadcrumbs['Promo Boxes'] = '/admin/developershub/promoboxes';
        $this->set('breadcrumbs', $this->breadcrumbs);

        switch ($action) {
        case 'create':
            $this->_developershubPromoBoxesCreate();
            return;
        case 'edit':
            $this->Amo->clean($id);
            $this->_developershubPromoBoxesEdit($id);
            return;
        }
            
        $promos = $this->HubPromo->findAll(null, null, 'HubPromo.modified DESC', null, null, -1);

        $this->set('promos', $promos);
        $this->set('visibilities', HubPromo::$visibilities);
        $this->set('page', 'developershub');

        $this->set('subpage', 'promoboxes');
        $this->render('developershub_promoboxes');
    }

   /**
    * Create Hub PromoBox
    */
    function _developershubPromoBoxesCreate() {
        $this->breadcrumbs['Create Developers Hub PromoBox'] = '/admin/developershub/promoboxes/create';
        $this->set('breadcrumbs', $this->breadcrumbs);

        if (!empty($this->data)) {
            $this->HubPromo->save($this->data['HubPromo']);

            //Save translated fields (heading, body)
            $this->Developers->saveTranslations($this->data, array('HubPromo'));
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'hubpromo_create', null, $this->HubPromo->getLastInsertID());
            
            $this->flash('PromoBox created!', '/admin/developershub/promoboxes');
            return;  
        }
        
        $localizedFields = array(
                                'heading' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Heading',
                                                    'model'       => 'HubPromo',
                                                    'field'       => 'heading',
                                                    'attributes'  => array( 'size' => 40)
                                ),
                                'body' => array(
                                                    'type'        => 'textarea',
                                                    'display'     => 'Body',
                                                    'model'       => 'HubPromo',
                                                    'field'       => 'body',
                                                    'attributes'  => array('cols' => 40, 'rows' => 6)
                                )
                   );
                
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->HubPromo->setLang($key, $this);

            foreach ($this->HubPromo->translated_fields as $field) {
                $info[$key][$field] = '';
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        //Options for visibility select box
        $this->set('visibilities', HubPromo::$visibilities);

        $this->set('page', 'developershub');
        $this->set('subpage', 'promoboxes');
        $this->render('developershub_promoboxes_create');
    }

   /**
    * Edit or Delete Hub PromoBox
    */
    function _developershubPromoBoxesEdit($id) {
        $this->breadcrumbs['Edit Developers Hub PromoBox'] = '/admin/developershub/promoboxes/edit/'.$id;
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->HubPromo->id = $id;
        
        if (!empty($this->data)) {
            //Delete
            if (!empty($_POST['delete'])) {
                $promo = $this->HubPromo->read();
                $this->HubPromo->delete($id);
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'hubpromo_delete', null, $id, null, $promo['Translation']['heading']['string']);
                
                $this->flash('PromoBox deleted successfully.', '/admin/developershub/promoboxes');
                return;
            }
            //Edit
            else {
                $this->HubPromo->save($this->data['HubPromo']);
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'hubpromo_edit', null, $id);
                
                //Save translated fields (name, description)
                $this->Developers->saveTranslations($this->data, array('HubPromo'));
                
                $this->flash('PromoBox updated!', '/admin/developershub/promoboxes');
                return;
            }
        }
        
        $promobox = $this->HubPromo->read();
        
        $this->set('promobox', $promobox);
        
        $localizedFields = array(
                                'heading' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Heading',
                                                    'model'       => 'HubPromo',
                                                    'field'       => 'heading',
                                                    'attributes'  => array( 'size' => 40)
                                ),
                                'body' => array(
                                                    'type'        => 'textarea',
                                                    'display'     => 'Body',
                                                    'model'       => 'HubPromo',
                                                    'field'       => 'body',
                                                    'attributes'  => array('cols' => 40, 'rows' => 6)
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->HubPromo->setLang($key, $this);
            $promoL = $this->HubPromo->read();

            foreach ($promoL['Translation'] as $field => $translation) {
                if ($translation['locale'] == $key) {
                    $info[$key][$field] = $translation['string'];
                }
                else {
                    $info[$key][$field] = '';
                }
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        //Options for visibility select box
        $this->set('visibilities', HubPromo::$visibilities);

        $this->set('page', 'developershub');
        $this->set('subpage', 'promoboxes');
        $this->render('developershub_promoboxes_edit');    
    }

   /**
    * Developers Hub Event Manager
    */
    function _developershubEvents($action = '', $id = 0) {
        $this->breadcrumbs['Events'] = '/admin/developershub/events';
        $this->set('breadcrumbs', $this->breadcrumbs);

        switch ($action) {
        case 'create':
            $this->_developershubEventCreate();
            return;
        case 'edit':
            $this->Amo->clean($id);
            $this->_developershubEventEdit($id);
            return;
        }

        // Handle mass event deletion
        if (!empty($this->data['DeleteEvents'])) {
            $this->_developershubDeleteEvents();
            return;
        }
            
        $events = $this->HubEvent->findAll(null, null, 'HubEvent.date DESC', null, null, -1);

        $this->dontsanitize[] = 'date';
        $this->publish('events', $events);
        $this->set('page', 'developershub');
        $this->set('subpage', 'events');
        $this->render('devhub_events');
    }

   /**
    * Delete Hub Events
    */
    function _developershubDeleteEvents() {
        $event_ids = array();
        if (!empty($this->data['DeleteEvents'])) {
            foreach ($this->data['DeleteEvents'] as $event_id => $val) {
                if ($val === '1') {
                    $event_ids[] = $event_id;
                }
            }
        }

        if (count($event_ids) > 0) {
            // Delete events
            $this->Amo->clean($event_ids); 
            $in_string = "'" . implode("','", $event_ids) . "'";
            $this->HubEvent->execute("DELETE FROM hubevents WHERE id IN({$in_string})");

            // Log admin action
            foreach ($event_ids as $event_id) {
                $this->Eventlog->log($this, 'admin', 'hubevent_delete', null, $event_id);
            }

            $this->flash('Events deleted!', '/admin/developershub/events');

        } else {
            $this->flash('No events selected for deletion.', '/admin/developershub/events');
        }
    }

   /**
    * Create Hub Event
    */
    function _developershubEventCreate() {
        $this->breadcrumbs['Create Developers Hub Event'] = '/admin/developershub/events/create';
        $this->set('breadcrumbs', $this->breadcrumbs);

        if (!empty($this->data)) {
            // save() does sql escaping, however data was already cleaned/escaped in beforeFilter.
            // Unclean to prevent double-escaping (*sigh*)
            $this->data['HubEvent'] = $this->Amo->unclean($this->data['HubEvent']);
            if ($this->HubEvent->save($this->data['HubEvent'])) {
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'hubevent_create', null, $this->HubEvent->getLastInsertID());
                $this->flash('Event created!', '/admin/developershub/events');
                return;  

            } else {
                $this->Error->addError('Error creating event. Please correct any invalid fields.');
            }
        }
        
        $this->set('errors', $this->Error->errors);
        $this->set('page', 'developershub');
        $this->set('subpage', 'events');
        $this->render('devhub_events_create_edit');
    }

   /**
    * Edit Hub Event
    */
    function _developershubEventEdit($id) {
        $this->breadcrumbs['Edit Developers Hub Event'] = '/admin/developershub/events/edit/'.$id;
        $this->set('breadcrumbs', $this->breadcrumbs);

        $this->HubEvent->id = $id;

        if (empty($this->data)) {
            $this->data = $this->HubEvent->read();
            if (empty($this->data)) {
                header('HTTP/1.1 404 Not Found');
                $this->flash('Event not found', '/admin/developershub/events');
                return;
            }

        } else {
            // save() does sql escaping, however data was already cleaned/escaped in beforeFilter.
            // Unclean to prevent double-escaping (*sigh*)
            $this->data['HubEvent'] = $this->Amo->unclean($this->data['HubEvent']);
            if ($this->HubEvent->save($this->data['HubEvent'])) {
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'hubevent_edit', null, $id);
                $this->flash('Event saved!', '/admin/developershub/events');
                return;  

            } else {
                $this->Error->addError('Error saving event. Please correct any invalid fields.');
            }
        }

        $this->publish('id', $id);
        $this->set('errors', $this->Error->errors);
        $this->set('page', 'developershub');
        $this->set('subpage', 'events');
        $this->render('devhub_events_create_edit');
    }

   /**
    * Developers Hub News Feed Manager
    */
    function _developershubNewsfeed() {
        $this->breadcrumbs['News Feed'] = '/admin/developershub/newsfeed';
        $this->set('breadcrumbs', $this->breadcrumbs);

        // Handle story creation
        if (!empty($this->data['CreateStory'])) {
            $this->Addonlog->logCustomHtml($this, $this->data['CreateStory']['html']);
            $this->flash('Story created!', '/admin/developershub/newsfeed');
            return;
        }

        // Handle mass story deletion
        if (!empty($this->data['DeleteStories'])) {
            $log_ids = array();

            foreach ($this->data['DeleteStories'] as $log_id => $val) {
                if ($val === '1') {
                    $log_ids[] = $log_id;
                }
            }

            if (count($log_ids) > 0) {
                // Delete stories (aka Addonlogs)
                $this->Amo->clean($log_ids); 
                $in_string = "'" . implode("','", $log_ids) . "'";
                $this->Addonlog->execute("DELETE FROM addonlogs WHERE id IN({$in_string})");
                $this->flash('Stories deleted!', '/admin/developershub/newsfeed');
                return;
            }
        }
            
        // There shouldn't be many global log entries and they get flushed after
        // 3 months anyway. This lazy approach just shows a bunch of records instead
        // of doing proper paging...
        $stories = $this->Hub->getNewsForAddons(array(), 'all', array('show' => 9999));

        $this->set('stories', $stories);
        $this->set('page', 'developershub');
        $this->set('subpage', 'newsfeed');
        $this->render('devhub_newsfeed');
    }
    
   /**
    * Category Manager
    */
    function categories($action = '', $id = 0) {
        //Part of the Lists permission
        if (!$this->SimpleAcl->actionAllowed('Admin', 'lists', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }
        
        $this->breadcrumbs['Category Manager'] = '/admin/categories';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $applications = array('All');
        $_applications = $this->Amo->getApplicationName();
        if (!empty($_applications)) {
            foreach ($_applications as $app_id => $app_name) {
                $applications[$app_id] = $app_name;
            }
        }
        $this->set('applications', $applications);
        
        $addontypes = $this->Addontype->getNames();
        $this->set('addontypes', $addontypes);
        
        if (!empty($action)) {
            $this->Amo->clean($id);
            
            if ($action == 'edit') {
                $this->_categoryEdit($id);
                return;
            }
            elseif ($action == 'create') {
                $this->_categoryCreate($id);
                return;
            }
        }
        
        $categories = $this->Category->findAll();
        
        foreach ($categories as $k => $category) {
            $categories[$k]['application'] = !empty($applications[$category['Category']['application_id']]) ? $applications[$category['Category']['application_id']] : 'All';
            $categories[$k]['addontype'] = $addontypes[$category['Category']['addontype_id']];
            
            $count = $this->Category->query("SELECT COUNT(*) FROM addons_categories WHERE category_id='{$category['Category']['id']}'");
            $categories[$k]['count'] = $count[0][0]['COUNT(*)'];
        }
        
        $this->set('categories', $categories);
        $this->set('page', 'lists');
        $this->set('subpage', 'categories');
        $this->render('categories');
    }
    
   /**
    * Edit Categories
    */
    function _categoryEdit($id) {
        $this->breadcrumbs['Edit Category'] = '/admin/categories/edit/'.$id;
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->Category->id = $id;
        
        if (!empty($this->data)) {
            //Delete
            if (!empty($_POST['delete'])) {
                //Retrieve category to store name in log
                $category = $this->Category->read();
                
                $this->Group->execute("DELETE FROM addons_categories WHERE category_id='{$id}'");
                $this->Group->execute("DELETE FROM categories WHERE id='{$id}'");
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'category_delete', null, $id, null, $category['Translation']['name']['string']);
                
                $this->flash('Category deleted successfully.', '/admin/categories');
                return;
            }
            //Edit
            else {
                // Must manually set application id to null if "All" is selected
                if (empty($this->data['Category']['application_id']))
                    $this->data['Category']['application_id'] = NULL;
                    
                $this->Category->save($this->data['Category']);
                $this->Category->execute("UPDATE categories SET weight='".$this->data['Category']['weight']."' WHERE id='{$id}'");				
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'category_edit', null, $id);
                
                //Save translated fields (name, description)
                $this->Developers->saveTranslations($this->data, array('Category'));
                
                $this->flash('Category updated!', '/admin/categories');
                return;
            }
        }
        
        $category = $this->Category->read();
        
        $this->set('category', $category);
        
        $localizedFields = array(
                                'name' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Category Name',
                                                    'model'       => 'Category',
                                                    'field'       => 'name',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                ),
                                'description' => array(
                                                    'type'        => 'textarea',
                                                    'display'     => 'Category Description',
                                                    'model'       => 'Category',
                                                    'field'       => 'description',
                                                    'attributes'  => array(
                                                                        'cols' => 60,
                                                                        'rows' => 3
                                                                      )
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Category->setLang($key, $this);
            $categoryL = $this->Category->read();

            foreach ($categoryL['Translation'] as $field => $translation) {
                if ($translation['locale'] == $key) {
                    $info[$key][$field] = $translation['string'];
                }
                else {
                    $info[$key][$field] = '';
                }
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'lists');
        $this->set('subpage', 'categories');
        $this->render('categories_edit');    
    }
    
   /**
    * Create Categories
    */
    function _categoryCreate() {
        $this->breadcrumbs['Create Category'] = '/admin/categories/create';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($this->data)) {
            // Must manually set application id to null if "All" is selected
            if (empty($this->data['Category']['application_id']))
                $this->data['Category']['application_id'] = NULL;
            
            $this->Category->save($this->data['Category']);
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'category_create', null, $this->Category->getLastInsertID());
            
            //Save translated fields (name, description)
            $this->Developers->saveTranslations($this->data, array('Category'));
            
            $this->flash('Category created!', '/admin/categories');
            return;  
        }

        $localizedFields = array(
                                'name' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Category Name',
                                                    'model'       => 'Category',
                                                    'field'       => 'name',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                ),
                                'description' => array(
                                                    'type'        => 'textarea',
                                                    'display'     => 'Category Description',
                                                    'model'       => 'Category',
                                                    'field'       => 'description',
                                                    'attributes'  => array(
                                                                        'cols' => 60,
                                                                        'rows' => 3
                                                                      )
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Category->setLang($key, $this);

            foreach ($this->Category->translated_fields as $field) {
                $info[$key][$field] = '';
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'lists');
        $this->set('subpage', 'categories');
        $this->render('categories_create');    
    }

   /**
    * Tags Manager
    */
    function tags($action = '', $id = 0) {
        //Part of the Lists permission
        if (!$this->SimpleAcl->actionAllowed('Admin', 'lists', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }
        
        $this->breadcrumbs['Tag Manager'] = '/admin/tags';
        $this->set('breadcrumbs', $this->breadcrumbs);
                
        if (!empty($action)) {
            $this->Amo->clean($id);
            
            if ($action == 'delete') {
                $this->_tagDelete($id);
                return;
            }
            elseif ($action == 'blacklist') {
                $this->_tagBlacklist($id);
                return;
            }
            elseif ($action == 'unblacklist') {
                $this->_tagUnBlacklist($id);
                return;
            }            
        }
        
    //    $tags = $this->Tag->findAll();
        $criteria=NULL;
        $this->Pagination->modelClass='Tag';
        
        list($order,$limit,$page) = $this->Pagination->init($criteria, array('modelClass'=>'Tag')); // Added
       // $order=str_replace('Addon','Tag', $order);
        $tags = $this->Tag->findAll($criteria, NULL, $order, $limit, $page); // Extra parameters added 
                
        $this->set('tags', $tags);
        $this->set('page', 'lists');
        $this->set('subpage', 'tags');
        $this->render('tags');
    }
 
	function _tagBlacklist($tag_id) {
        if (!$this->SimpleAcl->actionAllowed('Admin', 'lists', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }
        $this->Tag->blacklistTag($tag_id);
       $this->redirect('/admin/tags');
	}

	function _tagUnBlacklist($tag_id) {
        if (!$this->SimpleAcl->actionAllowed('Admin', 'lists', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }
        $this->Tag->unblacklistTag($tag_id);
       $this->redirect('/admin/tags');
	}   

   /**
    * Platform Manager
    */
    function platforms($action = '', $id = 0) {
        //Part of the Lists permission
        if (!$this->SimpleAcl->actionAllowed('Admin', 'lists', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }
        
        $this->breadcrumbs['Platform Manager'] = '/admin/platforms';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($action)) {
            $this->Amo->clean($id);
            
            if ($action == 'edit') {
                $this->_platformEdit($id);
                return;
            }
            elseif ($action == 'create') {
                $this->_platformCreate($id);
                return;
            }
        }
        
        $platforms = $this->Platform->findAll(null, null, null, null, null, -1);
        
        $this->set('platforms', $platforms);
        $this->set('page', 'lists');
        $this->set('subpage', 'platforms');
        $this->render('platforms');
    }
    
   /**
    * Edit Platforms
    */
    function _platformEdit($id) {
        $this->breadcrumbs['Edit Platform'] = '/admin/platforms/edit/'.$id;
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->Platform->id = $id;
        
        if (!empty($this->data)) {
            //Delete
            if (!empty($_POST['delete'])) {
                // Check for existing files
                $files = $this->File->findCount(array('File.platform_id' => $this->Platform->id));
                if ($files > 0) {
                    $this->flash(sprintf(___('Cannot delete platform: There are %s files that must be deleted.'), $files), '/admin/platforms');
                    return;
                }

                //Retrieve platform to store name in log
                $platform = $this->Platform->findById($this->Platform->id, null, null, -1);
                
                $this->Group->execute("DELETE FROM platforms WHERE id='{$id}'");
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'platform_delete', null, $id, null, $platform['Translation']['name']['string']);
                
                $this->flash('Platform deleted successfully.', '/admin/platforms');
                return;
            }
            //Edit
            else {
                //Save translated fields (name, description)
                $this->Developers->saveTranslations($this->data, array('Platform'));
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'platform_edit', null, $id);
                
                $this->flash('Platform updated!', '/admin/platforms');
                return;
            }
        }
        
        $platform = $this->Platform->findById($this->Platform->id, null, null, -1);
        
        $this->set('platform', $platform);
        
        $localizedFields = array(
                                'name' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Name',
                                                    'model'       => 'Platform',
                                                    'field'       => 'name',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                ),
                                'shortname' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Shortname',
                                                    'model'       => 'Platform',
                                                    'field'       => 'shortname',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Platform->setLang($key, $this);
            $platformL = $this->Platform->read();

            foreach ($platformL['Translation'] as $field => $translation) {
                if ($translation['locale'] == $key) {
                    $info[$key][$field] = $translation['string'];
                }
                else {
                    $info[$key][$field] = '';
                }
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'lists');
        $this->set('subpage', 'platforms');
        $this->render('platforms_edit');    
    }
    
   /**
    * Create Platforms
    */
    function _platformCreate() {
        $this->breadcrumbs['Create Platform'] = '/admin/platforms/create';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($this->data)) {
            //Save translated fields (name, description)
            $this->Developers->saveTranslations($this->data, array('Platform'));
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'platform_create', null, $this->Category->getLastInsertID());
            
            $this->flash('Platform created!', '/admin/platforms');
            return;  
        }
        
        $localizedFields = array(
                                'name' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Name',
                                                    'model'       => 'Platform',
                                                    'field'       => 'name',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                ),
                                'shortname' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Shortname',
                                                    'model'       => 'Platform',
                                                    'field'       => 'shortname',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                )
                   );
                
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Category->setLang($key, $this);

            foreach ($this->Category->translated_fields as $field) {
                $info[$key][$field] = '';
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'lists');
        $this->set('subpage', 'platforms');
        $this->render('platforms_create');    
    }

   /**
    * Feature Manager
    */
    function features($action = '', $id = 0) {
        global $valid_languages;
        $this->breadcrumbs['Feature Manager'] = '/admin/features';
        $this->set('breadcrumbs', $this->breadcrumbs);

        if (!empty($action)) {

            switch ($action) {
                case 'update':
                    if (!empty($_POST['save']) && is_numeric($id)) {

                        $_feature['id'] = $id;
                        $_feature['start'] = $_POST['feature'.$id.'_startdate'];
                        $_feature['end'] = $_POST['feature'.$id.'_enddate'];


                        if ($this->Feature->save($_feature)) {
                            //Log admin action
                            $this->Eventlog->log($this, 'admin', 'feature_edit', null, $id);
                            
                            $this->flash('Feature modified', '/admin/features');
                            return;
                        } else {
                            $this->Error->addError('Could not modify feature (invalid data?)');
                        }
                    }
                    if (!empty($_POST['remove']) && is_numeric($id)) {
                        //Fetch feature details for logging
                        $feature = $this->Feature->findById($id, array('id', 'addon_id'), null, -1);

                        $this->Feature->execute("DELETE FROM features WHERE id='{$id}' LIMIT 1");

                        //Log admin action
                        $this->Eventlog->log($this, 'admin', 'feature_remove', null, $id, null, $id);

                        //Log addon action
                        if ($this->Feature->getAffectedRows() > 0) {
                            $this->Addonlog->logRemoveRecommended($this, $feature['Feature']['addon_id']);
                        }
                        
                        $this->flash("Feature successfully removed ({$id})", '/admin/features');
                        return;

                    }
                    break;
                case 'create':
                    if (!empty($_POST['add'])) {
                        if (preg_match('/\[(\d+)\]/', $_POST['q'], $matches)) {
                            $this->data['Feature']['addon_id'] = $matches[1];
                        } else {
                            $this->Feature->invalidate('id');
                            $this->Error->addError('Add-on ID must be specified in brackets.');
                        }
                        $_addon = $this->Addon->getAddon($this->data['Feature']['addon_id']);
                        if (!isset($_addon['Addon']['status']) || $_addon['Addon']['status'] != STATUS_PUBLIC) {
                            $this->Feature->invalidate('id');
                            $this->Error->addError('Only public add-ons may be added.');
                        }
                        if (empty($this->data['Feature']['start'])) {
                            $this->data['Feature']['start'] = date('Y-m-d H:i:s');
                        }
                        if (empty($this->data['Feature']['end'])) {
                            $this->data['Feature']['end'] = date('Y-m-d H:i:s', strtotime('+6 months'));
                        }
                        if (USERLANG == 'Unspecified') {
                            $this->data['Feature']['locale'] = null;
                        } else {
                            $this->data['Feature']['locale'] = USERLANG;
                        }

                        $this->data['Feature']['application_id'] = USERAPP;

                        if ($this->Feature->save($this->data)) {
                                //Log admin action
                                $this->Eventlog->log($this, 'admin', 'feature_add', null, $this->Feature->getLastInsertId());

                                //Log addon action
                                $this->Addonlog->logAddRecommended($this, $_addon['Addon']['id']);
                                
                                $this->flash('Feature added!', '/admin/features');
                                return;
                        } else {
                            $this->Error->addError('Could not add feature (invalid data?)');
                        }
                    }
                    break;
            }
        }

        $features = $this->Feature->findAll(array('locale' => USERLANG, 'application_id' => USERAPP), null, null, null, null, -1);
        $applications = $this->Application->findAll(array('supported' => 1), null, null, null, null, -1);

        foreach ($features as $key => $feature) {
            $features[$key]['Addon']['name'] = $this->Addon->getAddonName($feature['Feature']['addon_id']);
        }

        $this->set('page', 'features');
        $this->set('features', $features);
        $this->set('locales', $valid_languages);
        $this->set('applications', $applications);
        $this->set('errors', $this->Error->errors);
        $this->render('features');
    }
    
   /**
    * Group Manager
    */
    function groups($action = '', $id = 0) {
        $this->breadcrumbs['Group Manager'] = '/admin/groups';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($action)) {
            $this->Amo->clean($id);
            $this->Amo->clean($_POST);
            
            if ($action != 'members') {
                if ($action == 'edit') {
                    $this->_groupEdit($id);
                }
                elseif ($action == 'create') {
                    $this->_groupCreate();
                }
                return;
            }
            
            //Add new member
            if (!empty($_POST['add'])) {
                $email = $_POST['group'.$id.'_new'];
                if ($user = $this->User->findByEmail($email)) {
                    $this->User->execute("INSERT INTO groups_users (group_id, user_id) VALUES('{$id}', '{$user['User']['id']}')");
                    
                    //Log admin action
                    $this->Eventlog->log($this, 'admin', 'group_addmember', null, $id, $user['User']['id']);
                    
                    $this->flash("{$user['User']['email']} successfully added to group {$id}", '/admin/groups');
                    return;
                }
                else {
                    $this->Error->addError('Could not find user '.$email);
                }
            }
            
            //Remove member
            if (!empty($_POST['remove'])) {
                $uid = $_POST['group'.$id.'_remove'];
                $this->User->execute("DELETE FROM groups_users WHERE group_id='{$id}' AND user_id='{$uid}' LIMIT 1");
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'group_removemember', null, $id, null, $uid);
                
                $this->flash("User successfully removed from group {$id}", '/admin/groups');
                return;
            }
        }
        
        $groups = $this->Group->findAll();
        
        $this->set('groups', $groups);
        $this->set('errors', $this->Error->errors);
        $this->set('page', 'groups');
        $this->render('groups');
    }

   /**
    * Edit group
    */
    function _groupEdit($id) {
        $this->breadcrumbs['Edit Group'] = '/admin/groups/edit/'.$id;
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->Group->id = $id;
        
        if ($id == 1) {
            $this->Error->addError('Group 1 cannot be modified.');
            
            //Log security action
            $this->Eventlog->log($this, 'security', 'modify_locked_group', null, $id);
        }
        elseif (!empty($this->data)) {
            //Delete group
            if (!empty($_POST['delete'])) {
                //Get group info for log
                $group = $this->Group->read();
                
                $this->Group->execute("DELETE FROM groups_users WHERE group_id='{$id}'");
                $this->Group->execute("DELETE FROM groups WHERE id='{$id}'");
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'group_delete', null, $id, null, $group['Group']['name']);
                
                $this->flash('Group deleted successfully.', '/admin/groups');
                return;
            }
            //Edit group
            elseif (!empty($this->data['Group']['name']) && !empty($this->data['Group']['rules'])) {
                if ($this->Group->save($this->data['Group'])) {
                    //Log admin action
                    $this->Eventlog->log($this, 'admin', 'group_edit', null, $id);
                    
                    $this->flash('Group settings saved!', '/admin/groups');
                    return;
                }
                else {
                    $this->Error->addError('Validation error.');
                }
            }
            else {
                $this->Error->addError('All fields are required.');
            }
        }
        
        $group = $this->Group->read();
        
        $this->set('group', $group);
        $this->set('errors', $this->Error->errors);
        $this->set('page', 'groups');
        $this->render('groups_edit');
    }

   /**
    * Create group
    */
    function _groupCreate() {
        $this->breadcrumbs['Create Group'] = '/admin/groups/create';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($this->data)) {
            if (!empty($this->data['Group']['name']) && !empty($this->data['Group']['rules'])) {
                if ($this->Group->save($this->data['Group'])) {
                    //Log admin action
                    $this->Eventlog->log($this, 'admin', 'group_create', null, $this->Group->getLastInsertID());
                    
                    $this->flash('Group created!', '/admin/groups');
                    return;
                }
                else {
                    $this->Error->addError('Validation error.');
                }
            }
            else {
                $this->Error->addError('All fields are required.');
            }
        }

        $this->set('errors', $this->Error->errors);
        $this->set('page', 'groups');
        $this->render('groups_create');
    }

    
   /**
    * Guid Manager
    */
    function guids() {
        $this->breadcrumbs['Guid Manager'] = '/admin/guids';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        // Two options - removing selected guids or adding new guids, newline delimited
        if (!empty($this->data) && !empty($this->data['BlacklistedGuid'])) {
            if (!empty($this->data['BlacklistedGuid']['new'])) {

                // Adding new guids, newline delimted.  We read from post because
                // Cake replaces \n with a literal '\n' 
                $new = preg_split('/[\n\r]+/', $_POST['data']['BlacklistedGuid']['new']);
                $guids = array();
                foreach ($new as $guid) {
                    $guids[] = trim($guid);
                }
                $this->Amo->clean($guids); 
                $guids = implode('\', NOW()), (\'', $guids);
 
                // One big query since we don't want to pound the DB
                $query = 'REPLACE INTO `blacklisted_guids` (`guid`, `created`) VALUES (\'' . $guids . '\', NOW());';
                $this->BlacklistedGuid->execute($query);
                $this->data['BlacklistedGuid']['new'] = '';

            } else {

                // Removing current guids, same idea as above with one big query
                $guids = array();
                foreach ($this->data['BlacklistedGuid'] as $guid => $remove) {
                    $this->Amo->clean($guid);
                    if ($remove) $guids[] = $guid;
                }
                if (!empty($guids)) {
                    $this->BlacklistedGuid->execute('DELETE FROM `blacklisted_guids` WHERE `guid` IN (\'' . implode("', '", $guids) . '\');');
                }
            }
        }

        $guids = $this->BlacklistedGuid->findAll('', array(), 'guid');

        $this->set('guids', $guids);
        $this->set('errors', $this->Error->errors);
        $this->set('page', 'guids');
        $this->render('guids');
    }

    
   /**
    * Response Manager
    */
    function responses($action = '', $id = 0) {
        //Part of the Lists permission
        if (!$this->SimpleAcl->actionAllowed('Admin', 'lists', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }
        
        $this->breadcrumbs['Response Manager'] = '/admin/responses';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($action)) {
            $this->Amo->clean($id);
            
            if ($action == 'edit') {
                $this->_responseEdit($id);
                return;
            }
            elseif ($action == 'create') {
                $this->_responseCreate($id);
                return;
            }
        }
        
        $responses = $this->Cannedresponse->findAll();
        
        $this->set('responses', $responses);
        $this->set('page', 'lists');
        $this->set('subpage', 'responses');
        $this->render('responses');
    }
    
   /**
    * Edit Responses
    */
    function _responseEdit($id) {
        $this->breadcrumbs['Edit Responses'] = '/admin/responses/edit/'.$id;
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->Cannedresponse->id = $id;
        
        if (!empty($this->data)) {
            //Delete
            if (!empty($_POST['delete'])) {
                //Get info for log
                $response = $this->Cannedresponse->read();
                
                $this->Group->execute("DELETE FROM cannedresponses WHERE id='{$id}'");
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'response_delete', null, $id, null, $response['Translation']['name']['string']);
                
                $this->flash('Response deleted successfully.', '/admin/responses');
                return;
            }
            //Edit
            else {
                //Save translated fields (name, description)
                $this->Developers->saveTranslations($this->data, array('Cannedresponse'));
                
                //Log admin action
                $this->Eventlog->log($this, 'admin', 'response_edit', null, $id);
                
                $this->flash('Response updated!', '/admin/responses');
                return;
            }
        }
        
        $response = $this->Cannedresponse->read();
        
        $this->set('response', $response);
        
        $localizedFields = array(
                                'name' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Response Name',
                                                    'model'       => 'Cannedresponse',
                                                    'field'       => 'name',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                ),
                                'response' => array(
                                                    'type'        => 'textarea',
                                                    'display'     => 'Response',
                                                    'model'       => 'Cannedresponse',
                                                    'field'       => 'response',
                                                    'attributes'  => array(
                                                                        'cols' => 60,
                                                                        'rows' => 3
                                                                      )
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Cannedresponse->setLang($key, $this);
            $crL = $this->Cannedresponse->read();

            foreach ($crL['Translation'] as $field => $translation) {
                if ($translation['locale'] == $key) {
                    $info[$key][$field] = $translation['string'];
                }
                else {
                    $info[$key][$field] = '';
                }
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'lists');
        $this->set('subpage', 'responses');
        $this->render('responses_edit');    
    }
    
   /**
    * Create Categories
    */
    function _responseCreate() {
        $this->breadcrumbs['Create Response'] = '/admin/responses/create';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        if (!empty($this->data)) {
            //Save translated fields (name, response)
            $this->Developers->saveTranslations($this->data, array('Cannedresponse'));
            
            //Log admin action
            $this->Eventlog->log($this, 'admin', 'response_create', null, $this->Cannedresponse->getLastInsertID());
            
            $this->flash('Response created!', '/admin/responses');
            return;  
        }

        $localizedFields = array(
                                'name' => array(
                                                    'type'        => 'input',
                                                    'display'     => 'Response Name',
                                                    'model'       => 'Cannedresponse',
                                                    'field'       => 'name',
                                                    'attributes'  => array(
                                                                      'size' => 40
                                                                    )
                                ),
                                'response' => array(
                                                    'type'        => 'textarea',
                                                    'display'     => 'Response',
                                                    'model'       => 'Cannedresponse',
                                                    'field'       => 'response',
                                                    'attributes'  => array(
                                                                        'cols' => 60,
                                                                        'rows' => 3
                                                                      )
                                )
                   );
                   
        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Cannedresponse->setLang($key, $this);

            foreach ($this->Cannedresponse->translated_fields as $field) {
                $info[$key][$field] = '';
            }
        }
        
        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => 'en-US',
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        
        $this->set('page', 'lists');
        $this->set('subpage', 'responses');
        $this->render('responses_create');    
    }
    
   /**
    * Display logs
    * @param int $id log id to view
    */
    function logs($id = 0) {
        $this->breadcrumbs['Log Manager'] = '/admin/logs';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        //if id passed, show details for that id
        if (!empty($id)) {
            $this->_logDetails($id);
            return;
        }

        // Default conditions are the current month
        $monthStart = date('Y-m-01');
        $conditions = array("Eventlog.created >= '{$monthStart} 00:00:00'");
        $startDate = $monthStart;
        $endDate = ___('YYYY-MM-DD');
        $filter = '';

        // If user has specified own conditions, use those
        if (!empty($this->params['url']['start'])) {
            $startTime = strtotime($this->params['url']['start']);
            if ($startTime !== false && $startTime != -1) {
                $conditions = array("Eventlog.created >= FROM_UNIXTIME('{$startTime}')");
                $startDate = $this->params['url']['start'];
            }
        }
        if (!empty($this->params['url']['end'])) {
            $endTime = strtotime($this->params['url']['end']);
            if ($endTime !== false && $endTime != -1) {
                $conditions[] = "Eventlog.created < FROM_UNIXTIME('".strtotime('+1 day', $endTime)."')";
                $endDate = $this->params['url']['end'];
            }
        }
        if (!empty($this->params['url']['filter'])) {
            $filter = $this->params['url']['filter'];
            $filterParts = explode(':', $filter);
            $conditions['type'] = $filterParts[0];
            
            if ($filterParts[1] != '*') {
                $conditions['action'] = $filterParts[1];
            }
        }

        // set up pagination
        list($order,$limit,$page) = $this->Pagination->init($conditions, null,
            array('modelClass'=>'Eventlog', 'show'=>50, 'sortby'=>'created', 'direction'=>'DESC'));

        $logs = $this->Eventlog->findAll($conditions, null, $order, $limit, $page);
        $logs = $this->Audit->explainLog($logs);
        
        $this->set('logs', $logs);
        $this->publish('startDate', $startDate);
        $this->publish('endDate', $endDate);
        $this->publish('filter', $filter);
        
        $this->set('page', 'logs');
        $this->render('logs');
    }
    
   /**
    * Display log details
    * @param int $id the log id
    */
    function _logDetails($id) {
        $this->Eventlog->id = $id;
        $entry = $this->Eventlog->read();
        
        $this->set('entry', $entry);
        
        $this->set('page', 'logs');
        $this->render('log_details');
    }
    
    /**
     * Display server statistics.
     */
    function serverstatus() {
        $this->breadcrumbs['Server Status'] = '/admin/serverstatus';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $data = array();

        // Get cache servers statistics.
        $data['memcache'] = $this->Memcaching->getExtendedStats();

        $this->set('data', $data);
        $this->set('page', 'serverstatus');
        $this->render('serverstatus');
    }

    /**
     * Flush memcache.
     * This connects to each server individually and flushes them manually.
     * Should be used sparingly.
     */
    function serverflush() {
        if ($this->Memcaching->flush()) {
            $this->flash('Memcache flushed!', '/admin/serverstatus');
        } else {
            $this->flash('Memcache could NOT be flushed!', '/admin/serverstatus');
        }
    }
    
    function config() {
        $this->breadcrumbs['Site Config'] = '/admin/config';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $config = $this->Config->getConfig();
        
        if (!empty($this->data)) {
            $sessionConfig = $this->Session->read('Config');
            
            if (!empty($this->data['Config'])) {
                if ($this->data['Session']['rand'] == $sessionConfig['rand']) {
                    //Because of the sensitivity, we will only save fields that have changed.
                    foreach ($this->data['Config'] as $key => $value) {
                        if ($config[$key] != $value) {
                            //There has been a change
                            
                            $this->Config->save(array('key' => $key, 'value' => $value));
                            
                            //Log admin action
                            $this->Eventlog->log($this, 'admin', 'config', $key, 0, $value, $config[$key]);
                        }
                    }
                    
                    $this->flash('Config updated!', '/admin/config');
                }
                else {
                    $this->flash('There was a problem with your authentication. Please try again.', '/admin/config');
                }
                return;
            }
            
            if (!empty($this->data['User']['password'])) {
                $session = $this->Session->read('User');
                if ($this->User->checkPassword($session, $this->data['User']['password'])) {
                    $this->set('config', $config);
                    $this->set('rand', $sessionConfig['rand']);
                    
                    $this->set('page', 'config');
                    $this->render('config');
                    return;
                }
                else {
                    //Log failure
                    $this->Eventlog->log($this, 'security', 'reauthentication_failure', null, 1, null, null, 'Site Config');
                    
                    //Log user out
                    $this->Session->stop();
                    $this->flash('Incorrect password! You have been logged out.', '/');
                    return;
                }
            }
        }
        
        $this->set('page', 'config');
        $this->render('config_login');
    }

    function variables() {

        $_constants = get_defined_constants(true);
        $_constants = $_constants['user'];
        ksort($_constants);

        $_constants['ANON_BIND_PW']          = '--removed--';
        $_constants['DB_USER']               = '--removed--';
        $_constants['DB_PASS']               = '--removed--';
        $_constants['SHADOW_DB_USER']        = '--removed--';
        $_constants['SHADOW_DB_PASS']        = '--removed--';
        $_constants['TEST_DB_USER']          = '--removed--';
        $_constants['TEST_DB_PASS']          = '--removed--';
        $_constants['CAKE_SESSION_STRING']   = '--removed--';
        $_constants['RECAPTCHA_PRIVATE_KEY'] = '--removed--';
        $_constants['PAYPAL_PASSWORD']       = '--removed--';
        $_constants['PAYPAL_BN']             = '--removed--';
        $_constants['PAYPAL_SIGNATURE']      = '--removed--';

        $this->set('constants', $_constants);

        $this->set('page', 'variables');
        $this->set('subpage', '');
        $this->render('variables');
    }
    
   /**
    * List Manager
    */
    function lists() {
        $this->breadcrumbs['List Manager'] = '/admin/lists';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->set('page', 'lists');
        $this->set('subpage', '');
        $this->render('lists');
    }
    
   /**
    * Flagged add-ons
    */
    function flagged() {
        $this->breadcrumbs['Flagged Add-ons'] = '/admin/flagged';
        $this->set('breadcrumbs', $this->breadcrumbs);
        
        $this->cssAdd[] = 'editors';
        $this->set('cssAdd', $this->cssAdd);
        
        // Process any unflag requests
        if (!empty($this->data)) {
            $unflagged = 0;
            foreach ($this->data['Addon']['unflag'] as $addon_id) {
                $this->Addon->id = $addon_id;
                if ($this->Addon->save(array('adminreview' => 0)))
                    $unflagged++;
            }
            $this->publish('unflagged', $unflagged);
        }
        
        //Pull any add-ons that have adminreview=1
        $flagged = $this->Addon->findAllByAdminreview(1,
                                                    array('Addon.id', 'Addon.name', 'Addon.defaultlocale',
                                                          'Addon.created'
                                                          ), 'Addon.created DESC', null, null, 0);
        if (!empty($flagged)) {
            foreach ($flagged as $k => $addon) {
                $version = $this->Version->findByAddon_id($addon['Addon']['id'],
                    array('Version.id', 'Version.addon_id', 'Version.version'),
                    'Version.created DESC');
                if (!$version) $version = array();
                $flagged[$k] = array_merge_recursive($flagged[$k], $version);
                
                $history = $this->Approval->find(
                    "Approval.addon_id={$addon['Addon']['id']} AND
                    ((Approval.reviewtype='nominated' AND Approval.action=".STATUS_NOMINATED.") OR
                    (Approval.reviewtype='pending' AND Approval.action=".STATUS_PENDING."))",
                    null, 'Approval.created DESC');
                if (!$history) $history = array();
                $flagged[$k] = array_merge_recursive($flagged[$k], $history);
            }
        }
        $this->set('addons', $flagged);
        
        $this->publish('addontypes', $this->Addontype->getNames());
        
        $this->set('page', 'flagged');
        $this->render('flagged_queue');
    }

   /**
    * User Manager
    */
    function users($user_id = 0, $type = 'edit') {
        $this->breadcrumbs['User Manager'] = '/admin/users';
        $this->set('breadcrumbs', $this->breadcrumbs);
		$this->set('suppressJQuery', 0);

        if (!empty($user_id)) {
            $this->Amo->clean($user_id);
            $this->User->id = $user_id;
            
            if (!empty($_POST)) {
                switch ($type) {
                case 'delete':
                    switch ($_POST['deletetype']) {
                    case 'all':
                        // check if user owns add-ons
                        if ($this->User->getAddonCount($user_id) > 0) {
                            $this->flash('You cannot delete a user who owns add-ons!', "/admin/users/{$user_id}");
                            return;
                        }
                        
                        $this->User->bindModel(array('hasMany' => array('Review' => $this->User->hasMany_full['Review'])));
                        $success = $this->User->del($user_id, true);
                        if ($success) {
                            $this->flash('User successfully deleted!', "/admin/users/");
                            return;
                        }
                        break;
                    
                    case 'anon':
                        $success = $this->User->anonymize($user_id);
                        if ($success) {
                            $this->flash('User account successfully made anonymous!', "/admin/users/{$user_id}");
                            return;
                        }
                        break;
                    
                    default:
                        $this->flash('Please choose a deletion type!', "/admin/users/{$user_id}");
                        return;
                    }
                    
                    if (!$success) {
                        $this->flash('Error deleting user!', "/admin/users/{$user_id}");
                        return;
                    }
                    break;
                
                case 'edit':
                default:
                    if ($this->data['User']['removepicture'] == 1) {
                        $this->data['User']['picture_data'] = null;
                        $this->data['User']['picture_type'] = '';
                    }

                    // Picture fields.
                    if (!empty($this->data['User']['picture_data']['name'])) {
                        $fileinfo = $this->Developers->validatePicture($this->data['User']['picture_data']);
                        if (is_array($fileinfo)) {
                            $this->data['User']['picture_data'] = $fileinfo['picture_data'];
                            $this->data['User']['picture_type'] = $fileinfo['picture_type'];
                        }
                    } else {
                        // Don't mess with the picture data
                        unset($this->data['User']['picture_data']);
                    }

                    $this->User->save($this->data['User']);

					// save author "about me"
					list($localizedFields, $unlocalizedFields) = $this->User->splitLocalizedFields($this->data['User']);
					$this->User->saveTranslations($user_id, $this->params['form']['data']['User'], $localizedFields);
                    
                    //Log admin action
                    $this->Eventlog->log($this, 'admin', 'user_edit', null, $user_id);
                
                    $this->flash('User info updated!', "/admin/users/{$user_id}");
                    return;
                }
                
            } else {
                $user = $this->User->read();
				// grab translated fields
				$translations = $this->User->getAllTranslations($user_id);
				$this->set('translations', $translations);
            }
        }
        elseif (!empty($_GET['q'])) {
            $q = $_GET['q'];
            $this->Amo->clean($q);
            
            if (!$user = $this->User->findByEmail($q)) {
                $this->flash('E-mail not found.', '/admin/users');
                return;
            }
			// grab translated fields
			$translations = $this->User->getAllTranslations($user['User']['id']);
			$this->set('translations', $translations);
        }
        $this->set('page', 'users');
        
        if (!empty($user)) {
            $this->publish('user', $user);
            $this->render('users_edit');
        }
        else {
            $this->render('users');
        }
    }
    
   /**
    * AJAX User lookup
    */
    function userLookup() {
        if (!$this->SimpleAcl->actionAllowed('Admin', '%', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
        }
        
        $text = $_REQUEST['q'];
        $this->Amo->clean($text);
        
        if ($users = $this->User->query("SELECT id, firstname, lastname, email FROM users WHERE email LIKE '%{$text}%'")) {
            foreach ($users as $user) {
                $results[] = "{$user['users']['email']}|{$user['users']['firstname']} {$user['users']['lastname']}";
            }
        }
        
        $this->set('results', $results);
        $this->render('userlookup', 'ajax');
    }
    
   /**
    * AJAX Add-on lookup
    */
    function addonLookup() {
        if (!($this->SimpleAcl->actionAllowed('Admin', '%', $this->Session->read('User')) ||
                $this->SimpleAcl->actionAllowed('Editor', '*', $this->Session->read('User'))) ) {
            $this->Amo->accessDenied();
        }

        global $valid_status;
        
        $text = $_REQUEST['q'];
        $this->Amo->clean($text);
        $results = array();

        $_query = "SELECT addons.id, translations.localized_string, addons.status 
            FROM addons LEFT JOIN translations ON addons.name=translations.id 
            WHERE translations.locale='".LANG."' AND translations.localized_string LIKE '%{$text}%'";
        if (array_key_exists('s', $_REQUEST) && in_array($_REQUEST['s'], $valid_status)) {
            $_query .= " AND addons.status={$_REQUEST['s']}";
        }
        $_query .= " ORDER BY translations.localized_string";
        
        //Check if an add-on id
        if (preg_match('/\[(\d+)\]/', $text, $matches)) {
            $addon = $this->Addon->findById($matches[1], array('Addon.name', 'Addon.status'));
            $results[] = "{$addon['Translation']['name']['string']}|ID: {$matches[1]}; Status: ".$this->Amo->getApprovalStatus($addon['Addon']['status'])."|{$addon['Translation']['name']['string']} [{$matches[1]}]";
        } elseif ($addons = $this->Addon->query($_query)) {
            foreach ($addons as $addon) {
                $results[] = "{$addon['translations']['localized_string']}|ID: {$addon['addons']['id']}; Status: ".$this->Amo->getApprovalStatus($addon['addons']['status'])."|{$addon['translations']['localized_string']} [{$addon['addons']['id']}]";
            }
        }
        
        $this->set('results', $results);
        $this->render('userlookup', 'ajax');
    }

   /**
    * AJAX Collection lookup.  Matches either name or nickname
    */
    function collectionLookup() {
        if (!$this->SimpleAcl->actionAllowed('Admin', '%', $this->Session->read('User')) ||
            !$this->SimpleAcl->actionAllowed('Editor', '*', $this->Session->read('User')) ) {
            $this->Amo->accessDenied();
        }
        global $app_shortnames;

        $text = $_REQUEST['q'];
        $this->Amo->clean($text);
        $results = array();

        $_query = "SELECT 
                    collections.id, translations.localized_string, collections.nickname, collections.application_id
                   FROM collections LEFT JOIN translations ON collections.name=translations.id 
                   WHERE (translations.locale='".LANG."' 
                   AND translations.localized_string LIKE '%{$text}%')
                   OR collections.nickname LIKE '%{$text}%'
                   ORDER BY translations.localized_string";
        
        if ($collections = $this->Collection->query($_query)) {
            foreach ($collections as $collection) {
                $_application = array_search($collection['collections']['application_id'], $app_shortnames);
                $results[] = "{$collection['translations']['localized_string']}|ID: {$collection['collections']['id']}|App: {$_application}|nickname: {$collection['collections']['nickname']};";
            }
        }
        
        $this->set('results', $results);
        $this->render('userlookup', 'ajax');
    }

    /**
     *  Flush URLs from a Zeus Load Balancer
     */
    function zeusflush() {
        if (!empty($this->data) && !empty($this->data['Zeus']['flushlist'])) {
            $_success = $_failure = array();
            global $zxtm_config;
            $zxtm_config['wsdl_module'] = 'System.Cache.wsdl';

            // I'm replacing the \r and exploding on the literal \n here.  Apparently these are converted
            // from real newlines to literal characters now?  wtf.
            $flushlist = str_replace('\r', '', $this->data['Zeus']['flushlist']);
            $flushlist = array_unique(explode('\n', $flushlist));

            try {
                $zeus = new moz_zxtmapi($zxtm_config);

                foreach ($flushlist as $url) {
                    $url = trim($url);

                    if (strpos($url, '*') === false) {
                        $zeus->flushObjectByUrl($url);
                    } else {
                        $zeus->flushObjectByPattern($url);
                    }

                }

            } catch (Exception $e) {
                $this->flash("Failed to flush.  The error was: {$e->getMessage()}", '/admin/serverstatus');
                return;
            }

            $_message = "Guess what?  Zeus doesn't tell us if anything succeeded or failed.  So hey, it might have worked!";

            $this->flash($_message, '/admin/serverstatus', 0);
            return;
        }
        $this->flash("You need to enter the URLs to flush.", '/admin/serverstatus');
        return;
    }
}
?>
