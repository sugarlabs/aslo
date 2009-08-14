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
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
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
class AmoComponent extends Object {
    var $controller;
    var $platforms;
    var $applications;
    var $versionIds;
    var $navCategories;

   /**
    * Save a reference to the controller on startup
    * @param object &$controller the controller using this component
    */
    function startup(&$controller) {
        $this->controller =& $controller;
    }

   /**
    * Checks if user has permissions for an addon
    * @param int $id the add-on id
    * @param array $addonInfo array of add-on data so we don't have to pull it
    * @param bool $requireOwner whether we're checking for actual ownership
    */
    function checkOwnership($id, $addonInfo = array(), $requireOwner = false) {
        $session = $this->controller->Session->read('User');
        if (empty($session['id'])) return false;

        //Check if user is an admin
        if ($this->controller->SimpleAcl->actionAllowed('Admin', 'EditAnyAddon', $session) && !$requireOwner) {
            return true;
        }

        //See if add-on data was passed; if not, retrieve it
        if (empty($addonInfo['status'])) {
            $addon = $this->controller->Addon->findById($id, null, null, -1);
            $addonInfo = $addon['Addon'];
        }

        //Check if add-on is disabled
        if ($addonInfo['status'] == STATUS_DISABLED) {
            return false;
        }

        //check if user is an author of the add-on
        if ($this->controller->Addon->query("SELECT * FROM addons_users WHERE addon_id={$id} AND user_id={$session['id']}")) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Gets the author role of the current user for the given add-on
	 * @param int $addon_id id of the add-on
     */
    function getAuthorRole($addon_id) {
        $session = $this->controller->Session->read('User');
        if (empty($session['id'])) return AUTHOR_ROLE_NONE;

        // Get role from database
        $role = $this->controller->Addon->query("SELECT role FROM addons_users WHERE addon_id={$addon_id} AND user_id={$session['id']}");
        if (!empty($role)) {
            $role = $role[0]['addons_users']['role'];
        }

        // Check if user has permissions to edit any add-on
        if ($this->controller->SimpleAcl->actionAllowed('Admin', 'EditAnyAddon', $session)) {
            if ($role == AUTHOR_ROLE_OWNER) {
                return AUTHOR_ROLE_ADMINOWNER;
            }
            else {
                return AUTHOR_ROLE_ADMIN;
            }
        }

        // Check if add-on is disabled
        $status = $this->controller->Addon->findById($addon_id, array('Addon.status'), null, -1);
        if (!empty($status['Addon']['status'])) {
            if ($status['Addon']['status'] == STATUS_DISABLED) {
                return AUTHOR_ROLE_NONE;
            }
        }

        // If not an admin and not disabled, return db role if we found one
        if (!empty($role)) {
            return $role;
        }

        return AUTHOR_ROLE_NONE;
    }

   /**
    * Cleans an array or string for SQL and HTML, by reference
    *
    * @param mixed &$subject
    */
    function clean(&$subject, $stripTags = true) {
        if (isset($subject)) {
            if (is_array($subject)) {
                foreach ($subject as $k => $v) {
                    $this->clean($subject[$k], $stripTags);
                }
            }
            else {
                if ($stripTags)
                    $subject = strip_tags($subject);
                $subject = mysql_real_escape_string($subject);
            }
        }
    }

   /**
    * Strips slashes from subject in return (NOT by reference)
    * @param string $subject
    */
    function unclean($subject) {
        if (is_array($subject)) {
            $uncleaned = array();
            foreach ($subject as $k => $v) {
                $uncleaned[$k] = $this->unclean($v);
            }
        } else {
            $uncleaned = stripslashes($subject);
        }

        return $uncleaned;
    }

   /**
    * Ensures that any quotes are turned into entities
    * @param mixed &$subject
    */
    function convertQuotes(&$subject) {
        if (isset($subject)) {
            if (is_array($subject)) {
                foreach ($subject as $k => $v) {
                    $this->convertQuotes($subject[$k]);
                }
            }
            else {
                $subject = str_replace("'", "&#39;", $subject);
            }
        }
    }

   /**
    * Returns a string representation of the Approval Status id
    * @param int $status
    * @deprecated since 3.5 - use Amo->getStatusNames()
    */
    function getApprovalStatus($status = '') {
        //If a status id is specified, return the string
        if ($status != '') {
            switch ($status) {
                case STATUS_NULL:       $string = ___('Incomplete Version');
                                        break;
                case STATUS_SANDBOX:    $string = ___('In Sandbox');
                                        break;
                case STATUS_PENDING:    $string = ___('In Sandbox; Pending Review');
                                        break;
                case STATUS_NOMINATED:  $string = ___('In Sandbox; Public Nomination');
                                        break;
                case STATUS_PUBLIC:     $string = ___('Public');
                                        break;
                case STATUS_DISABLED:   $string = ___('Disabled');
                                        break;
                default:                $string = ___('Unknown');
                                        break;
            }
            return $string;
        }
        else {
            //If no status was passed, return an array of all statuses
            $array = array();
            for ($s = 0; $s <= 6; $s++) {
                //The id must be passed as a string
                $array[$s] = $this->getApprovalStatus("$s");
            }
            return $array;
        }
    }

    /**
     * Returns an array of possible add-on and file statuses and names
     */
    function getStatusNames() {
        return array(
            STATUS_NULL      => ___('Incomplete Version'),
            STATUS_SANDBOX   => ___('In Sandbox'),
            STATUS_PENDING   => ___('In Sandbox; Pending Review'),
            STATUS_NOMINATED => ___('In Sandbox; Public Nomination'),
            STATUS_PUBLIC    => ___('Public'),
            STATUS_DISABLED  => ___('Disabled')
        );
    }

   /**
    * Returns the name of a platform by Id, or an array of platforms
    * The purpose of this is to reduce unnecessary database queries
    * @param int $platform
    * @deprecated since 3.5 - use Platform->getNames()
    */
    function getPlatformName($platform = '', $shortnames = false) {
        //If platform id is set, get the name for it
        if ($platform != '') {
            //If the array of platforms was already retrieved, use it
            if (!empty($this->platforms)) {
                return $this->platforms[$platform];
            }
            //Otherwise, retrieve the array and save it
            else {
                $this->platforms = $this->getPlatformName();
                return $this->platforms[$platform];
            }
        }
        //If no id is set, return the array of platforms
        else {
            $model =& new Platform();
            $model->useDbConfig = 'shadow';
            $platforms = $model->findAll(null, null, null, null, null, -1);

            $platformArray = array();
            foreach ($platforms as $platform) {
                if ($shortnames == true) {
                    $platformArray[$platform['Platform']['id']]['name'] = $platform['Translation']['name']['string'];
                    $platformArray[$platform['Platform']['id']]['shortname'] = $platform['Translation']['shortname']['string'];
                }
                else {
                    $platformArray[$platform['Platform']['id']] = $platform['Translation']['name']['string'];
                }
            }

            return $platformArray;
        }
    }

   /**
    * Returns the name of an application by Id, or an array of applications
    * The purpose of this is to reduce unnecessary database queries
    * @param int $application
    */
    function getApplicationName($application = '', $shortnames = false) {
        //If application id is set, get the name for it
        if ($application != '') {
            //If the array of applications was already retrieved, use it
            if (!empty($this->applications)) {
                return $this->applications[$application];
            }
            //Otherwise, retrieve the array and save it
            else {
                $this->applications = $this->getApplicationName();
                return $this->applications[$application];
            }
        }
        //If no id is set, return the array of applications
        else {
            $model =& new Application();
            $model->useDbConfig = 'shadow';
            $applications = $model->findAll(null, null, null, null, null, -1);

            $applicationArray = array();
            foreach ($applications as $application) {
                if ($shortnames == true) {
                    $applicationArray[$application['Application']['id']]['name'] = $application['Translation']['name']['string'];
                    $applicationArray[$application['Application']['id']]['shortname'] = $application['Translation']['shortname']['string'];
                }
                else {
                    $applicationArray[$application['Application']['id']] = $application['Translation']['name']['string'];
                }
            }

            return $applicationArray;
        }
    }

   /**
    * Returns the versions of an application by Id, or an array versions for all applications
    * The purpose of this is again to reduce unnecessary database queries <br />
    * so is on practically every page (used in advance search in search view)
    * This code also has the side effect of memo-izing the version id's for each version of an application
    * @param int $application -- the application id to get versions for
    */
    function getApplicationVersions($application = '') {
        // If application id is set, get the name for it
        if ($application != '') {
            // If the array of applications was already retrieved, use it
            if (!empty($this->applications)) {
                return $this->applications[$application];
            }
            // Otherwise, retrieve the array and save it
            else {
                $this->applications = $this->getApplicationVersions();
                return $this->applications[$application];
            }
        }
        // If no id is set, return the array of applications
        else {
            $applicationModel =& new Application();
            $applicationModel->useDbConfig = 'shadow';

            loadComponent('Versioncompare');
            $versionCompare =& new VersioncompareComponent();

            $applicationModel->unbindModel(array('hasAndBelongsToMany' => array('Version'), 'hasMany' => array('Category')));
            $applications = $applicationModel->findAll('Application.supported=1', null, null, null, null, 2);
            $appvids = array();
            $versions = array();
            foreach ($applications as $application) {
                if (!empty($application['Appversion'])) {
                    $appversions = array();
                    //Change array structure for sorting
                    foreach ($application['Appversion'] as $appversion) {
                        $appversions[]['Appversion']['version'] = $appversion['version'];
                        $appvids[$appversion['application_id']][$appversion['version']] = $appversion['id'];
                    }
                    $versionCompare->sortAppversionArray($appversions);
                    foreach ($appversions as $appversion) {
                        $versions[$application['Application']['id']][] = $appversion['Appversion']['version'];
                    }
                }
            }
            $this->versionIds = $appvids;
            return $versions;
        }
    }

   /**
     * Returns the version => versionIds pairs corresponding to a particular application
     * It is used by the SearchController
     * @param $appid -- the application id of the application
     * @return -- arrays of version => versionIds pairs
     */
    function getVersionIdsByApp($appid) {
        if (!empty($this->versionIds)) {
            return $this->versionIds[$appid];
        } else {
            $this->getApplicationVersions();
            return $this->versionIds[$appid];
        }
    }

    /**
     * check if the user is logged in. If not, refer them to the login page,
     * optionally passing on where they wanted to go to in the first place.
     * @param string cake-relative path to refer to after login
     * @return mixed bool true if logged in, void otherwise
     */
    function checkLoggedIn($whereTo = '') {
        $session = $this->controller->Session->read('User');
        if (!empty($session)) {
            return true;
        } else {
            if ($whereTo) {
                $_get_part = $whereTo;
            } else {
                $_get_part = $this->controller->params['url']['url'];
                // strip locale and app
                $_get_part = preg_replace('|^' . LANG . '/' . APP_SHORTNAME . '|', '', $_get_part);
            }
            $_get_part = '?to='.urlencode($_get_part);
            $this->controller->redirect('/users/login'.$_get_part);
            exit;
        }
    }

   /**
    * Returns information on the min and max versions for a version because Cake
    * does not consider non-key fields in HasAndBelongsToMany tables.
    * @param int $version The version id
    * @return array
    * @deprecated since 3.5 - use Version->getCompatibleApps()
    */
    function getMinMaxVersions($version) {
        return $this->controller->Application->query("
            SELECT * FROM `applications_versions`
            LEFT JOIN `applications` ON `applications_versions`.`application_id`=`applications`.`id`
            JOIN `translations` ON `applications`.`name`=`translations`.`id`
            JOIN `appversions` AS `min` ON `applications_versions`.`min`=`min`.`id`
            JOIN `appversions` AS `max` ON `applications_versions`.`max`=`max`.`id`
            WHERE
                `applications_versions`.`version_id`='{$version}' AND
                `translations`.`locale`='en-US'
        ",true);
    }

   /**
    * Updates min/max version information manually
    * @param int $version The version id
    * @param array $data The array of targetApp information
    * @return boolean true
    * @deprecated since 3.5 - use Version->saveCompatibleApps()
    */
    function saveMinMaxVersions($version, $data) {
        if (!empty($data)) {
            foreach ($data['id'] as $id => $application_id) {
                $this->controller->Application->execute("
                    UPDATE `applications_versions` SET
                        `min`='{$data['minVersion'][$id]}',
                        `max`='{$data['maxVersion'][$id]}'
                    WHERE
                        `application_id`='{$application_id}' AND
                        `version_id`='{$version}'
                    LIMIT 1
                ");
            }
        }

        return true;
    }

   /**
    * Return the install trigger string for the specified addontype
    * @param int $addontype The addontype of the file
    * @param string $uri The url of the file
    * @param string $name The name of the item
    * @param string $icon The url of the icon
    * @param string $hash The hash of the file
    */
    function installTrigger($addontype, $uri, $name = '', $icon = '', $hash = '') {
        $xpi = array(ADDON_EXTENSION, ADDON_DICT, ADDON_LPAPP, ADDON_LPADDON);
        $chrome = array(ADDON_THEME);
        $search = array(ADDON_SEARCH);

        $uri = str_replace("'", "\'", $uri);
        $name = str_replace("'", "\'", $name);
        $icon = str_replace("'", "\'", $icon);
        $hash = str_replace("'", "\'", $hash);

        if (in_array($addontype, $xpi)) {
            return "InstallTrigger.install('{$uri}', '{$name}', '{$icon}', '{$hash}');";
        }
        elseif (in_array($addontype, $chrome)) {
            return "InstallTrigger.installChrome(InstallTrigger.SKIN, '{$uri}', '{$name}');";
        }
        elseif (in_array($addontype, $search)) {
            return "window.external.AddSearchProvider('{$uri}');";
        }
    }

   /**
    * Copies an array, returning only specifically allowed fields to be saved
    * @param array $array the array to filter
    * @param array $allowedFields the fields to be allowed
    * @param array $disallowedFields the fields disallowed
    * @return array
    */
    function filterFields($array, $allowedFields = array(), $disallowedFields = array()) {
        $newArray = array();

        if (!empty($array)) {
            foreach ($array as $k => $item) {
                if ((empty($allowedFields) || in_array($k, $allowedFields, true)) && !in_array($k, $disallowedFields, true)) {
                    if (is_array($item)) {
                        $newArray[$k] = $this->filterFields($item, $allowedFields, $disallowedFields);
                    }
                    else {
                        $newArray[$k] = $item;
                    }
                }
            }
        }

        return $newArray;
    }

    /**
     * @deprecated
     */
    function LEGACY_describeVersionStatus($files) {
        if (count($files) == 0) {
            return ___('No Files');
        }
        elseif (count($files) == 1) {
            return $this->getApprovalStatus($files[0]['File']['status']);
        }
        else {
            $statuses = array();
            foreach ($files as $file) {
                if (empty($statuses[$file['File']['status']])) {
                    $statuses[$file['File']['status']] = 1;
                }
                else {
                    $statuses[$file['File']['status']]++;
                }
            }
            if (count($statuses) == 1) {

            }

            return 'Multiple Files';
        }
    }

    function describeVersionStatus($files) {
        if (count($files) == 0) {
            return ___('No Files');
        }
        else {
            $statuses = $this->getStatusNames();
            $fileStatuses = array();
            $counts = array();

            foreach ($files as $file) {
                if (!empty($fileStatuses[$file['status']])) {
                    $fileStatuses[$file['status']]++;
                }
                else {
                    $fileStatuses[$file['status']] = 1;
                }
            }

            foreach ($fileStatuses as $status => $count) {
                $string = n___('%1$s %2$s file', '%1$s %2$s files', $count);
                $counts[] = sprintf($string, $count, $statuses[$status]);
            }

            return implode('; ', $counts);
        }
    }

    /**
     * Provide the validation status as a readable string
     * @param array $files the file array for the version, in model format
     * @return string the rendered view, using renderElement
     */
    function describeValidationStatus($files) {

        // No files means no test cases
        if (count($files) == 0) {
            return ___('No Test Results');
        }

        // We want all the results for all files
        $fileIds = array();
        foreach ($files as $file) {
            $fileIds[] = $file['id'];
        }

        $results = $this->controller->TestResult->findAll(array('file_id' => $fileIds));
        if (count($results) == 0) {
            return ___('No Test Results');
        }

        // Count the results
        $counts = array(0,0,0);
        foreach ($results as $test_result) {
            $counts[$test_result['TestResult']['result']]++;
        }

        // Create a view to render the testresults_stats element
        $view = new View($this->controller);
        return $view->renderElement('developers/testresults_stats', array('counts' => $counts, 'short' => false, 'multiline' => true));

    }

   /**
    * Returns the date in MySQL NOW() format
    */
    function getNOW() {
        return date('Y-m-d H:i:s');
    }

   /**
    * Copy a file to the rsync location for updates
    * @param int $addon_id the add-on id
    * @param string $filename the filename
    * @param boolean $overwrite whether to overwrite the destination file
    * @return boolean
    */
    function copyFileToPublic($addon_id, $filename, $overwrite = true) {
        // Only copy if the path has been defined
        if (!defined('PUBLIC_STAGING_PATH')) {
            // return true because false indicates error
            return true;
        }

        $currentFile = REPO_PATH."/{$addon_id}/{$filename}";
        $newDir = PUBLIC_STAGING_PATH."/{$addon_id}";
        $newFile = $newDir."/{$filename}";

        // Make sure source file exists
        if (!file_exists($currentFile)) {
            return false;
        }

        // If we don't want to overwrite, make sure we don't
        if (!$overwrite && file_exists($newFile)) {
            // return true because this is not treated as an error
            return false;
        }

        // Make directory if necessary
        if (!file_exists($newDir)) {
            if (!mkdir($newDir)) {
                return false;
            }
        }

        return copy($currentFile, $newFile);
    }

    function accessDenied() {
        header('HTTP/1.1 401 Unauthorized');

        $this->controller->layout = 'mozilla';
        $this->controller->pageTitle = ___('Access Denied') . ' :: ' . sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);
        $this->controller->set('breadcrumbs', ___('Access Denied'));
        $this->controller->set('subpagetitle', ___('Access Denied'));
        $this->controller->viewPath = 'errors';
        $this->controller->render('error401');

        exit;
    }

   /**
    * Logs detailed information to a specific logfile
    * @param string $message Log message
    * @param bool $dumpData whether to dump the controller's data in the entry
    * @deprecated
    */
    function detailedLog($message, $dumpData = true) {
        if (!defined('DETAILED_LOG_PATH')) {
            return false;
        }

        $logfile = DETAILED_LOG_PATH.'/'.date('Y-m-d');

        $fp = fopen($logfile, 'a');
        fwrite($fp, "[".date('r')."] (".php_uname('n').") {$message}\n");
        if ($dumpData) {
            fwrite($fp, print_r($this->controller->data, true)."\n");
        }
        fclose($fp);
    }


    /**
     * Get a list of categories in alphabetical order.
     */
    function getCategories($app=APP_ID,$type=ADDON_EXTENSION) {

        if (!isset($this->controller->Category)) {
            loadModel('Category');
            $this->controller->Category =& new Category();
            // for CakePHP 1.2 this would be:
            // $this->controller->loadModel('Category');
        }

        $this->controller->Category->unbindFully();

        return $this->controller->Category->findAll(
            array(
                'application_id' => $app,
                'addontype_id'   => $type
            ),
            null,
            'Category.weight, Translation.name'
        );
    }

    /**
     * Get categories/addon types list for global navigation menu
     * @return array Category list, style:
     * array(
     *   array(name=>"abc", link="browse/type:1/cat:1"),
     *   ...
     * )
     */
    function getNavCategories() {
        global $hybrid_categories, $app_listedtypes, $valid_status;

        if (!empty($this->navCategories)) return $this->navCategories;

        // addon type list to be added to regular categories list
        // get "Themes" category name
        if (!isset($this->controller->Addontype)) {
            loadModel('Addontype');
            $this->controller->Addontype = new Addontype();
        }

        $names =  $this->controller->Addontype->getNames();
        $_themes_name = $names[ADDON_THEME];

        $catlist = array(
            array('name' => ___('Dictionaries & Language Packs'),
                  'type' => ADDON_DICT,
                  'cat' => 0,
                  'weight' => 0),
            array('name' => $_themes_name,
                  'type' => ADDON_THEME,
                  'cat' => 0,
                  'weight' => 0)
        );

        // add plugins where appropriate
        if (in_array(ADDON_PLUGIN, $app_listedtypes[APP_ID])) {
            $catlist[] = array(
                'name' => ___('Plugins'),
                'type' => ADDON_PLUGIN,
                'cat' => 0,
                'weight' => 0,
                'count' => COUNT_ADDON_PLUGIN,
            );
        }

        // create two sort arrays that we can use with array_multisort later
        $_weights = array();
        $_names = array();
        foreach ($catlist as $_item) {
            $_weights[] = $_item['weight'];
            $_names[] = strtolower($_item['name']);
        }

        // add regular categories to list
        $categories = $this->getCategories();
        foreach ($categories as $_category) {
            /* support hybrid categories */
            if (isset($hybrid_categories[APP_ID][$_category['Category']['id']])) {
                $_type = $hybrid_categories[APP_ID][$_category['Category']['id']];
                $_cat = 0;
            } else {
                $_type = $_category['Category']['addontype_id'];
                $_cat = $_category['Category']['id'];
            }

            $_name = $_category['Translation']['name']['string'];
            $_weight = $_category['Category']['weight'];
            $_count = $_category['Category']['count'];

            // add item to results array
            $catlist[] = array(
                'name' => $_name,
                'type' => $_type,
                'cat' => $_cat,
                'weight' => $_weight,
                'count' => $_count
            );

            // add item to sort arrays too
            $_names[] = strtolower($_name);
            $_weights[] = $_weight;
        }

        // sort results array by weight and name, then return.
        array_multisort($_weights, SORT_ASC, SORT_NUMERIC,
                        $_names, SORT_ASC, SORT_STRING, $catlist);
        // TODO: Use memcache here?
        $this->navCategories = $catlist; // cache result for subsequent calls
        return $catlist;
    }
}
?>
