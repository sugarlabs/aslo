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
class DevelopersComponent extends Object {
    var $controller;
    var $imageExtensions = array('.png', '.jpg', '.gif');

    var $uploadErrors = array();
    
   /**
    * Save a reference to the controller on startup
    * @param object &$controller the controller using this component
    */
    function startup(&$controller) {
        $this->controller =& $controller;

        $this->uploadErrors = array(
                    '1' => _('devcp_error_http_maxupload'),
                    '2' => _('devcp_error_http_maxupload'),
                    '3' => _('devcp_error_http_incomplete'),
                    '4' => _('devcp_error_http_nofile')
                );
    }
    
   /**
    * Make sure at least one but no more than 5 categories selected
    * @param array $categories post data of selected categories
    */
    function validateCategories($categories) {
        $errors =& $this->controller->Error;
        
        //Must have at least one category selected, but no more than 5
        if (empty($categories)) {
            $errors->addError(_('devcp_error_one_category'), 'Category/Category');
            $this->controller->Category->invalidate('Category');
            return false;
        }
        elseif (count($categories) > 5) {
            $errors->addError(_('devcp_error_five_categories'), 'Category/Category');
            $this->controller->Category->invalidate('Category');
            return false;
        }
        
        return true;
    }
    
   /**
    * Remove duplicates and make sure at least one user selected
    * @param array &$users post data of selected users
    */
    function validateUsers(&$users) {
        $errors =& $this->controller->Error;
    
        //Remove deleted and duplicate entries from authors
        $authors = array();
        if (!empty($users)) {
            foreach($users as $user) {
                if ($user != "" && !in_array($user, $authors)) {
                    $authors[] = $user;
                }
            }
        }
        $users = $authors;

        //Make sure there is at least one author
        if (empty($users)) {
            $errors->addError(_('devcp_error_one_user'), 'User/User');
            $this->controller->User->invalidate('User');
            return false;
        }
        
        return true;
    }
    
   /**
    * Save users to addons_users table. For some reason, Cake refuses to save
    * this properly the normal way.
    * @param array $users The users
    */
    function saveUsers($users) {
        $this->controller->User->execute("DELETE FROM addons_users WHERE addon_id={$this->controller->Addon->id}");
        
        if (!empty($users)) {
            foreach ($users as $user) {
                $this->controller->User->execute("INSERT INTO addons_users (addon_id, user_id) VALUES({$this->controller->Addon->id}, {$user})");
            }
        }
        
        return true;
    }
    
   /**
    * Get all categories for an addontype
    * @param int $addontypeId the addontype ID
    * @param array $applicationIds the ids of supported applications
    * @return array $categories the categories
    */
    function getCategories($addontypeId, $applicationIds) {
        //Get categories based on addontype
        $applicationIdQry = !empty($applicationIds) ? "Category.application_id IN (".implode(', ', $applicationIds).") OR" : '';

        // Override for search engines.  They have no application restrictions (bug 417727)
        if ($addontypeId == ADDON_SEARCH) {
            $applicationIdQry = 'Category.application_id IS NOT NULL OR';
        }
        
        $categoriesQry = $this->controller->Category->findAll("Category.addontype_id='{$addontypeId}' AND ({$applicationIdQry} Category.application_id IS NULL)");

        if ($categoriesQry) {
            // show (APP) behind name?
            $add_apps = (is_array($applicationIds) && count($applicationIds)>1);
            if ($add_apps) {
                global $app_shortnames, $app_prettynames;
                $appnames = array();
                foreach($applicationIds as $app) {
                    $sn = array_search($app,$app_shortnames);
                    if ($sn!==false)
                        $appnames[$app] = $app_prettynames[$sn];
                }
            }
            
            foreach ($categoriesQry as $k => $v) {
                $categories['names'][$v['Category']['id']] = $v['Translation']['name']['string'];
                if ($add_apps && !is_null($v['Category']['application_id']))
                    $categories['names'][$v['Category']['id']] .= " ({$appnames[$v['Category']['application_id']]})";
                $categories['descriptions'][] = $v['Category']['id'].': "'.addslashes($v['Translation']['description']['string']).'"';
            }
        }
        
        if (!empty($categories)) {
            asort($categories['names']);
            return $categories;
        }
        
        return array();
    }
    
   /**
    * Get all selected categories in order of post data, existing data, default
    * @param array $currentCategories currently selected categories
    * @return array $selectedCategories the selected categories
    */
    function getSelectedCategories($currentCategories) {
        //post data
        if (!empty($this->controller->data['Category']['Category'])) {
            foreach($this->controller->data['Category']['Category'] as $category) {
                $selectedCategories[] = $category;
            }
        }
        //current data
        elseif (!empty($currentCategories)) {
            foreach ($currentCategories as $category) {
                $selectedCategories[] = $category['id'];
            }
        }
        //default data
        else {
            $selectedCategories = array();
        }
        
        return $selectedCategories;
    }

   /**
    * Get authors in order of post data, existing data, default
    * @param array $currentUsers currently selected users
    * @param boolean $defaultToSession whether to default to current user
    * @return array $authors the authors
    */
    function getAuthors($currentUsers, $defaultToSession = true) {
        //post data
        if (!empty($this->controller->data['User']['User']) && !in_array('0', $this->controller->data['User']['User'])) {
            foreach($this->controller->data['User']['User'] as $user) {
                if ($user != "") {
                    $this->controller->User->id = $user;
                    $userinfo = $this->controller->User->read();
                    $authors[$user] = $userinfo['User']['firstname'].' '.$userinfo['User']['lastname'].' ['.$userinfo['User']['email'].']';
                }
            }
        }
        //current users
        elseif (!empty($currentUsers)) {
            foreach ($currentUsers as $user) {
                $authors[$user['id']] = $user['firstname'].' '.$user['lastname'].' ['.$user['email'].']';
            }
        }
        //default to logged in
        elseif ($defaultToSession == true) {
            //default to current user
            $session = $this->controller->Session->read('User');
            $authors[$session['id']] = $session['firstname'].' '.$session['lastname'].' ['.$session['email'].']';
        }
        //default to empty
        else {
            $authors = array();
        }

        return $authors;
    }
    
   /**
    * Detect addontype based on file information
    * @param array $file array of PHP file info
    * @return int addontype id
    */
    function detectAddontype($file) {            
        $extension = substr($file['name'], strrpos($file['name'], '.'));
        switch ($extension) {
            case '.xpi':
                // Dictionaries have a .dic file in the dictionaries directory
                $zip = new Archive_Zip($file['tmp_name']);
                $dicFile = $zip->extract(array('extract_as_string' => true, 'by_preg' => "/dictionaries\/.+\.dic/i")); 

                // if the .dic file is present, it is a dictionary, otherwise it's an extension
                if (count($dicFile) > 0) {
                    return ADDON_DICT;
                }
                else {
                    return ADDON_EXTENSION;
                }
                break;
            
            case '.jar':
                return ADDON_THEME;
                break;
            
            case '.xml':
                return ADDON_SEARCH;
                break;
            
            default:
                return 0;
                break;
        }
    }
    
   /**
    * Validate the uploaded files
    * @deprecated
    */
    function validateFiles() {
        $errors =& $this->controller->Error;
        
        //Make sure the first file was uploaded
        if (empty($this->controller->data['File']['file1']['name'])) {
            $errors->addError(_('devcp_error_upload_file'), 'File/file1');
            $this->controller->File->invalidate('file1');
            return false;
        }

        $errorCount = 0;
                
        //Loop through the files
        for ($f = 1; $f <= 4; $f++) {
            $file = (!empty($this->controller->data['File']['file'.$f])) ? $this->controller->data['File']['file'.$f] : array();

            if (!empty($file['name'])) {
                //File 4 is the icon file
                if ($f != 4) {
                    //Make sure platform selected
                    if (empty($this->controller->data['File']['platform_id'.$f])) {
                        $errors->addError(_('devcp_error_no_platform'), 'File/platform_id'.$f);
                        $this->controller->File->invalidate('platform_id'.$f);
                        $errorCount++;
                    }
                
                    //Validate the file
                    $files[$f] = $this->validateFile($this->controller->data['File']['file'.$f], $this->controller->data);
                }
                else {
                    //Validate the image
                    $files[$f] = $this->validateIcon($this->controller->data['File']['file'.$f]);
                }
                
                //If an array is not returned, an error occurred
                if (!is_array($files[$f])) {
                    $errors->addError($files[$f], 'File/file'.$f);
                    $this->controller->File->invalidate('file'.$f);
                    $errorCount++;
                }
                else {
                    if ($f != 4) {
                        $files[$f]['platform_id'] = $this->controller->data['File']['platform_id'.$f];
                    }
                }
            }
        }

        //Collect all file errors before returning
        if ($errorCount > 0) {
            return false;
        }
        else {
            foreach ($files as $f => $fileInfo) {
                $this->controller->addVars['file'.$f] = $fileInfo;
            }
            return true;
        }
    }
    
   /**
    * Validate a file for basic problems with the upload
    * @param array $file PHP info on the file
    * @param array $addon data already saved about the add-on
    * @return array if no errors
    * @return string if error
    */
    function validateFile($file, $addon) {
        $allowedExtensions = $this->getAllowedExtensions($addon['Addon']['addontype_id']);
        
        // Check for file upload errors
        if (!empty($file['error'])) {
            return $this->uploadErrors[$file['error']];
        }

        // Set file properties to be used later
        $fileInfo['filename'] = $file['name'];
        $fileInfo['size'] = round($file['size']/1024, 0); // in KB
        $fileInfo['extension'] = substr($file['name'], strrpos($file['name'], '.'));
        $fileInfo['hash'] = 'sha256:'.hash_file("sha256", $file['tmp_name']);
        
        // Check for file extension match
        if (!in_array(strtolower($fileInfo['extension']), $allowedExtensions)) {
            return sprintf(_('devcp_error_file_extension'), $fileInfo['extension'], implode(', ', $allowedExtensions));
        }

        // Move temporary file to repository
        $uploadedFile = $this->controller->Amo->unclean($file['tmp_name']);
        $tempLocation = REPO_PATH.'/temp/'.$fileInfo['filename'];

        // Make sure file doesn't overwrite anything
        if (file_exists($tempLocation)) {
            $fileInfo['filename'] = str_replace('0.', '', microtime()).$fileInfo['extension'];
            $tempLocation = REPO_PATH.'/temp/'.$fileInfo['filename'];
        }  

        if (move_uploaded_file($uploadedFile, $tempLocation)) {
            chmod($tempLocation, 0644);
            $fileInfo['path'] = $tempLocation;
        }
        else {
            return _('devcp_error_move_file');
        }
        
        return $fileInfo;
    }

   /**
    * Validate the icon file
    * @param array $file the icon file array
    */
    function validateIcon($icon) {
        
        // Check for file upload errors
        if (!empty($icon['error'])) {
            return $this->uploadErrors[$icon['error']];
        }
        
        // Check for file extension match
        $extension = substr($icon['name'], strrpos($icon['name'], '.'));
        if (!in_array(strtolower($extension), $this->imageExtensions)) {
            return sprintf(_('devcp_error_icon_extension'), $extension, implode(', ', $this->imageExtensions));
        }
        
        $fileInfo['icondata'] = file_get_contents($icon['tmp_name']);
        $fileInfo['icontype'] = $icon['type'];
        
        // Get icon size
        list($sourceWidth, $sourceHeight) = getimagesize($icon['tmp_name']);
        
        // Resize to 32x32
        $fileInfo['icondata'] = $this->resizeImage($fileInfo['icondata'], $sourceWidth, $sourceHeight, 32, 32);
        
        return $fileInfo;
    }

   /**
    * Validate the user's picture file
    * @param array $file the picture file array
    */
    function validatePicture($picture) {
        
        // Check for file upload errors
        if (!empty($picture['error'])) {
            return $this->uploadErrors[$picture['error']];
        }
        
        // Check for file extension match
        $extension = substr($picture['name'], strrpos($picture['name'], '.'));
        if (!in_array(strtolower($extension), $this->imageExtensions)) {
            return sprintf(_('devcp_error_icon_extension'), $extension, implode(', ', $this->imageExtensions));
        }
        
        $fileInfo['picture_data'] = file_get_contents($picture['tmp_name']);
        $fileInfo['picture_type'] = $picture['type'];
        
        // Get picture size
        list($sourceWidth, $sourceHeight) = getimagesize($picture['tmp_name']);
        
        // Resize to 200x200 which is the largest size we use
        $fileInfo['picture_data'] = $this->resizeImage($fileInfo['picture_data'], $sourceWidth, $sourceHeight, 200, 200);
        
        return $fileInfo;
    }
    
    /**
     * Resizes an image to specified size
     * @param string $sourceData the image data
     * @param int $sourceWidth original width
     * @param int $sourceHeight original height
     * @param int $newWidth width of the new image
     * @param int $newHeight height of the new image
     * @return string new image data
     */
    function resizeImage($sourceData, $sourceWidth, $sourceHeight, $newWidth = 200, $newHeight = 150) {
        $sourceImage = imagecreatefromstring($sourceData);
        imagesavealpha($sourceImage, true);
        
        // Determine width/height aspect ratio
        $sourceRatio = $sourceWidth / $sourceHeight;
        $newRatio = $newWidth / $newHeight;
        
        if ($newRatio > $sourceRatio) {
            $newWidth = $newHeight * $sourceRatio;
        }
        else {
            $newHeight = $newWidth / $sourceRatio;
        }
        
        // Only make image smaller, not larger
        if ($newWidth >= $sourceWidth && $newHeight >= $sourceHeight) {
            $newImage = $sourceImage;
        } else {
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Make a new transparent image and turn off alpha blending to keep the alpha channel
            $background = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagecolortransparent($newImage, $background);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        }
        
        // Output new image to buffer to save and clear it
        ob_start();
        imagepng($newImage);
        $newData = ob_get_contents();
        ob_end_clean();
        
        @imagedestroy($sourceImage);
        @imagedestroy($newImage);
        
        return $newData;
    }
    
   /**
    * Get allowed file extensions based on addontype
    * @param int $addontype the addontype
    * @return array $allowed allowed extensions
    */
    function getAllowedExtensions($addontype) {
        switch ($addontype) {
            case ADDON_EXTENSION: $allowed = array('.xpi');
                                  break;
            case ADDON_THEME:     $allowed = array('.jar', '.xpi');
                                  break;
            case ADDON_DICT:      $allowed = array('.xpi');
                                  break;
            case ADDON_SEARCH:    $allowed = array('.xml');
                                  break;
            case ADDON_LPAPP:     $allowed = array('.xpi');
                                  break;
            case ADDON_LPADDON:   $allowed = array('.xpi');
                                  break;
            default:              $allowed = array();
                                  break;
        }
        return $allowed;
    }
    
   /**
    * Validate the install.rdf manifest data
    * @param array $manifestData the manifest contents
    * @return string if error
    * @return boolean true if no error
    */
    function validateManifestData($manifestData) {
        // If the data is a string, it is an error message
        if (is_string($manifestData)) {
            return sprintf(_('devcp_error_manifest_parse'), $manifestData);
        }

        // Check if install.rdf has an updateURL
        if (isset($manifestData['updateURL'])) {
            return _('devcp_error_updateurl');
        }

        // Check if install.rdf has an updateKey
        if (isset($manifestData['updateKey'])) {
            return _('devcp_error_updatekey');
        }

        // Check the GUID for existence
        if (!isset($manifestData['id'])) {
            return _('devcp_error_no_guid');
        }
        
        // Validate GUID
        if (!preg_match('/^(\{[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\}|[a-z0-9-\._]*\@[a-z0-9-\._]+)$/i', $manifestData['id'])) {
            return sprintf(_('devcp_error_invalid_guid'), $manifestData['id']);
        }
        
        // Make sure GUID is not an application's GUID
        if ($this->controller->Application->findByGuid($manifestData['id'])) {
            return _('devcp_error_guid_application');
        }

        // Make sure version has no spaces
        if (!isset($manifestData['version']) || preg_match('/.*\s.*/', $manifestData['version'])) {
            return _('devcp_error_invalid_version_spaces');
        }
        
        // Validate version
        if (!preg_match('/^\d+(\+|\w+)?(\.\d+(\+|\w+)?)*$/', $manifestData['version'])) {
            return _('devcp_error_invalid_version');
        }
        
        return true;
    }
    
   /**
    * Validate the target applications
    * @param array $targetApps the targetApps from install.rdf
    * @return string if error
    * @return array if no errors
    */
    function validateTargetApplications($targetApps) {
        $noMozApps = true;
        $versionErrors = array();

        if (count($targetApps) > 0) {
            $i = 0;
            
            // Iterate through each target app and find it in the DB
            foreach ($targetApps as $appKey => $appVal) {
                if ($matchingApp = $this->controller->Application->find(array('guid' => $appKey), null, null, -1)) {
                    $return[$i]['application_id'] = $matchingApp['Application']['id'];
                    
                    // Mark as Moz-app if supported
                    if ($matchingApp['Application']['supported'] == 1) {
                        $noMozApps = false;
                    }
                    
                    // Check if the minVersion is valid
                    $matchingMinVers = $this->controller->Appversion->find("application_id={$matchingApp['Application']['id']} AND version='{$appVal['minVersion']}'", null, null, -1);
                    
                    if (empty($matchingMinVers)) {
                        $versionErrors[] = sprintf(_('devcp_error_invalid_appversion'), $appVal['minVersion'], $matchingApp['Translation']['name']['string']);
                    }
                    elseif (strpos($appVal['minVersion'], '*') !== false) {
                        $versionErrors[] = sprintf(_('devcp_error_invalid_minversion'), $appVal['minVersion'], $matchingApp['Translation']['name']['string']);
                    }
                    else {
                        $return[$i]['min'] = $matchingMinVers['Appversion']['id'];
                    }

                    // Check if the maxVersion is valid
                    $matchingMaxVers = $this->controller->Appversion->find("application_id={$matchingApp['Application']['id']} AND version='{$appVal['maxVersion']}'", null, null, -1);
                    if (empty($matchingMaxVers)) {
                        $versionErrors[] = sprintf(_('devcp_error_invalid_appversion'), $appVal['maxVersion'], $matchingApp['Translation']['name']['string']);
                    }
                    else {
                        $return[$i]['max'] = $matchingMaxVers['Appversion']['id'];
                    }
                    $i++;
                }
            }
        }
        
        $validAppReference = sprintf(_('devcp_error_appversion_reference_link'), '<a href="'.$this->controller->url('/pages/appversions').'">'._('devcp_error_appversion_reference_link_text').'</a>');

        // Must have at least one Mozilla app
        if ($noMozApps === true) {
            return _('devcp_error_mozilla_application').'<br />'.$validAppReference;
        }

        // Max/min version errors
        if (count($versionErrors) > 0) {
            $errorStr = implode($versionErrors, '<br />');
            return _('devcp_error_install_manifest').'<br />'.$errorStr.'<br />'.$validAppReference;
        }
        
        return $return;
    }
    
    /**
     * Renames and moves a file out of temp repository
     * @param array $data array of data in model format
     */
    function moveFile($data) {
        $fileUpdates = array();
        $applications = $this->controller->Application->getShortNames();
        $platforms = $this->controller->Platform->getShortNames();
        
        // Construct new filename with name, version, supported apps, and OS
        $filename = preg_replace(INVALID_FILENAME_CHARS, '_', $data['Addon']['name']);
        
        $filename .= '-'.$data['Version']['version'];
        
        if ($data['Addon']['addontype_id'] != ADDON_SEARCH) {
            $filename .= '-';
            $appString = '';
            foreach ($data['appversions'] as $appversion) {
                if ($appString != "") {   
                    $appString .= '+'.$applications[$appversion['application_id']];
                }
                else {
                    $appString = $applications[$appversion['application_id']];
                }
            }
            $filename .= $appString;
            
            if ($data['File']['db']['platform_id'] != PLATFORM_ALL) {
                $filename .= '-'.$platforms[$data['File']['db']['platform_id']];
            }
        }
        
        $filename .= $data['File']['details']['extension'];
        $filename = strtolower($filename);
        
        // File paths
        $currentPath = $data['File']['details']['path'];
        $dirPath = REPO_PATH.'/'.$data['Addon']['id'];
        $newPath = $dirPath.'/'.$filename;
        
        // Create directory if necessary
        if (!file_exists($dirPath)) {
            if (!mkdir($dirPath)) {
                return sprintf(_('devcp_error_moving_file'), $data['File']['db']['filename']);
            }
        }
        
        // Move file
        if (file_exists($currentPath)) {
            // Bail if the file exists. See bug 470652 for a rough explanation
            if (file_exists($newPath)) {
                return sprintf(___('devcp_error_file_exists'), $filename);
            }
            // We must copy instead of rename now in case there are other platforms
            if (!copy($currentPath, $newPath)) {
                return sprintf(_('devcp_error_moving_file'), $data['File']['db']['filename']);
            }
            $fileUpdates['filename'] = $filename;
        }
        else {
            return sprintf(_('devcp_error_moving_file'), $data['File']['db']['filename']);
        }
        
        // Copy file to rsync area if public
        if ($data['File']['db']['status'] == STATUS_PUBLIC) {
            $this->controller->Amo->copyFileToPublic($data['Addon']['id'], $filename);
        }
        
        return $fileUpdates;
    }
    
   /**
    * Create new file name and move files from temp to approval
    * @param array $version version information
    * @deprecated since 3.5
    */
    function moveFiles($version, $addontype_id) {
        $errors =& $this->controller->Error;
        $fileUpdates = array();
        $applications = $this->controller->Amo->getApplicationName(null, true);
        $this->controller->Addon->id = $version['Version']['addon_id'];
        $addon = $this->controller->Addon->read();
        
        // Construct new filename with name, version, supported apps, and OS
        $baseFilename = preg_replace(INVALID_FILENAME_CHARS, '_', $addon['Translation']['name']['string']);
        
        if ($addontype_id != ADDON_SEARCH) {
            $baseFilename .= '-'.$version['Version']['version'].'-';
            $appString = '';
            foreach ($version['Application'] as $app) {
                if ($appString != "") {   
                    $appString .= '+'.$applications[$app['id']]['shortname'];
                }
                else {
                    $appString = $applications[$app['id']]['shortname'];
                }
            }
            $baseFilename .= $appString;
    
            //Get platforms with shortnames
            $platforms = $this->controller->Amo->getPlatformName('', true);
        }

        foreach ($version['File'] as $file) {
            $newFilename = $baseFilename;
            $extension = substr($file['filename'], strrpos($file['filename'], '.'));
            if ($file['platform_id'] != 1 && $addontype_id != ADDON_SEARCH) {
                $newFilename .= '-'.$platforms[$file['platform_id']]['shortname'];
            }
            $newFilename .= $extension;
            $newFilename = strtolower($newFilename);

            //File paths
            $currentPath = REPO_PATH.'/temp/'.$file['filename'];
            $dirPath = REPO_PATH.'/'.$addon['Addon']['id'];
            $newPath = $dirPath.'/'.$newFilename;

            //Create directory if necessary
            if (!file_exists($dirPath)) {
                if (!mkdir($dirPath)) {
                    $errors->addError(sprintf(_('devcp_error_moving_file'), $file['filename']));
                }
            }

            //Move file
            if (file_exists($currentPath)) {
                //Delete file if one already exists
                if (file_exists($newPath)) {
                    unlink($newPath);
                }
                if (!rename($currentPath, $newPath)) {
                    $errors->addError(sprintf(_('devcp_error_moving_file'), $file['filename']));
                }
                $fileUpdates[$file['id']]['filename'] = $newFilename;
            }
            else {
                $errors->addError(sprintf(_('devcp_error_moving_file'), $file['filename']));
            }
        }
        
        return $fileUpdates;
    }
    
   /**
    * Determine if all required fields are set in order to skip reviewing add-on info
    */
    function noReviewRequired() {
    
        if ($this->controller->addVars['newAddon'] == true) {
            return false;
        }
        else {
            return true;
        }
    }
    
    /**
     * To be run after a file is deleted to ensure nominated add-ons
     * have files
     */
    function postDelete($addon_id) {
        $addon = $this->controller->Addon->findById($addon_id, array('status'), null, -1);
        $file = $this->controller->File->query("SELECT File.id FROM files AS File INNER JOIN versions as Version ON Version.id = File.version_id AND Version.addon_id = {$addon_id}");

        if ($addon['Addon']['status'] == STATUS_NOMINATED && empty($file)) {
            $addonData = array('status' => STATUS_SANDBOX);
            $this->controller->Addon->id = $addon_id;
            $this->controller->Addon->save($addonData);
        }
    }
    
    /**
     * Deletes a file from disk and database
     * ENSURE USER HAS ALL NECESSARY PERMISSIONS BEFORE USING THIS METHOD
     * @param int $file_id file id to delete
     * @param int $addon_id add-on id the file belongs to
     */
    function deleteFile($file_id, $addon_id) {
        $file = $this->controller->File->findById($file_id);
        
        // Delete files from disk
        $path = "/{$addon_id}/{$file['File']['filename']}";
        if (defined('REPO_PATH') && file_exists(REPO_PATH.$path)) {
            unlink(REPO_PATH.$path);
        }
        if (defined('PUBLIC_STAGING_PATH') && file_exists(PUBLIC_STAGING_PATH.$path)) {
            unlink(PUBLIC_STAGING_PATH.$path);
        }
        
        // Delete approvals
        $this->controller->File->execute("DELETE FROM approvals WHERE file_id='{$file_id}'");
        
        // Delete file
        $this->controller->File->execute("DELETE FROM files WHERE id='{$file_id}' LIMIT 1");
    }
    
   /**
    * Deletes a version along with all dependent files, reviews, etc
    * ENSURE USER HAS ALL NECESSARY PERMISSIONS BEFORE USING THIS METHOD
    * @param int $version_id version id to delete
    */
    function deleteVersion($version_id) {
        // Pull version info without translations
        $this->controller->Version->translationReplace = false;
        $version = $this->controller->Version->findById($version_id);
        $this->controller->Version->translationReplace = true;
        
        // Get translation ids of any translated fields of versions
        $translation_ids = array();
        if (!empty($this->controller->Version->translated_fields)) {
            foreach ($this->controller->Version->translated_fields as $translatedField) {
                if (!empty($version['Version'][$translatedField])) {
                    $translation_ids[] = $version['Version'][$translatedField];
                }
            }
        }
        
        // Delete any files
        if (!empty($version['File'])) {
            foreach ($version['File'] as $file) {
                $this->deleteFile($file['id'], $version['Version']['addon_id']);
            }
        }
        
        // Delete applications_versions rows
        $this->controller->Version->execute("DELETE FROM applications_versions WHERE version_id={$version_id}");
        
        // Delete reviews
        $review_ids = array();
        if (!empty($this->controller->Review->translated_fields)) {
            foreach ($this->controller->Review->translated_fields as $translatedField) {
                if (!empty($version['Review'])) {
                    foreach ($version['Review'] as $review) {
                        $review_ids[] = $review['id'];
                        if (!empty($review[$translatedField])) {
                            $translation_ids[] = $review[$translatedField];
                        }
                    }
                }
            }
        }
        
        if (!empty($review_ids)) {
            foreach ($review_ids as $review_id) {
                $this->controller->Review->execute("DELETE FROM reviewratings WHERE review_id={$review_id}");
                $this->controller->Review->execute("DELETE FROM reviews WHERE id={$review_id}");
            }
        }
        
        // Delete version
        $this->controller->Version->execute("DELETE FROM versions WHERE id={$version_id}");
        
        // Delete translations
        if (!empty($translation_ids)) {
            $this->controller->Version->execute("DELETE FROM translations WHERE id IN (".implode(',', $translation_ids).")");
        }
    }

   /**
    * Delete an addon, along with its versions, files, reviews, previews,
    * favorites, features, categories, and translations
    * @param int $id the add-on id
    * @return boolean
    */
    function deleteAddon($id) {
        //Double-check permissions
        if (!$this->controller->Amo->checkOwnership($id)) {
            return false;
        }

        $this->controller->Addon->id = $id;
        $addon = $this->controller->Addon->read();

        //Get translation ids of any translated fields
        if (!empty($this->controller->Addon->translated_fields)) {
            foreach ($this->controller->Addon->translated_fields as $translatedField) {
                if (!empty($addon['Addon'][$translatedField])) {
                    $translationIds[] = $addon['Addon'][$translatedField];
                }
            }
        }

        //Loop through and delete versions
        if (!empty($addon['Version'])) {
            foreach ($addon['Version'] as $version) {
                $this->deleteVersion($version['id']);
            }
        }

        //Delete addons_categories rows
        $this->controller->Addon->execute("DELETE FROM addons_categories WHERE addon_id='{$id}'");

        //Delete addons_users rows
        $this->controller->Addon->execute("DELETE FROM addons_users WHERE addon_id='{$id}'");

        //Delete favorites
        $this->controller->Addon->execute("DELETE FROM favorites WHERE addon_id='{$id}'");

        //Delete features
        $this->controller->Addon->execute("DELETE FROM features WHERE addon_id='{$id}'");

        //Delete previews
        $this->controller->Addon->execute("DELETE FROM previews WHERE addon_id='{$id}'");

        //Loop through reviews
        if (!empty($addon['Review'])) {
            foreach ($addon['Review'] as $review) {
                //Delete review ratings
                $this->controller->Addon->execute("DELETE FROM reviewratings WHERE review_id='{$review['id']}'");

                //Delete review
                $this->controller->Addon->execute("DELETE FROM reviews WHERE id='{$review['id']}'");
            }
        }       

        //Delete add-on
        $this->controller->Addon->execute("DELETE FROM addons WHERE id='{$id}' LIMIT 1");

        //Delete translations
        if (!empty($translationIds)) {
            $this->controller->Addon->execute("DELETE FROM translations WHERE id IN (".implode(',', $translationIds).")");
        }

        return true;
    }

   /**
    * Save localebox-formatted translations to their appropriate locales
    * Post data is in the format:
    *     [Locales] => Array
    *         (
    *             [0] => en-US
    *             [1] => de
    *         )
    *     [Addon] => Array
    *         (
    *             [name] => Array
    *                 (
    *                      [0] => English Name
    *                      [1] => German Name
    *                 )
    *         )
    * So we convert that into an array like:
    *     [en-US] => Array
    *         (
    *             [Addon] => Array
    *                  (
    *                      [name] => English Name
    *                  )
    *         )
    * and save it.
    * @param array $data The post data to save
    * @param array $models The models to process
    */
    function saveTranslations($data, $models = array()) {
        if (empty($models)) {
            $models = array('Addon', 'Preview', 'Version');
        }

        $translations = array();
        $errors = 0;
        
        if (!empty($data['Locales'])) {
            foreach ($data['Locales'] as $id => $locale) {
                // Reformat each model's array
                foreach ($models as $model) {
                    if (!empty($data[$model])) {
                        foreach ($data[$model] as $field => $values) {
                            if (!in_array($field, $this->controller->{$model}->translated_fields)) continue;
                            $translations[$locale][$model][$field] = $values[$id];
                        }
                    }
                }
            }

            // Update translations
            if (!empty($translations)) {
                foreach ($translations as $locale => $translation) {
                    foreach ($models as $model) {
                        if (!empty($translation[$model])) {
                            $this->controller->{$model}->setLang($locale, $this->controller);
                            $theData = $this->controller->Amo->filterFields($translation[$model], array(),
                                           array('id', 'guid', 'status'));
                            if (!empty($theData)) {
                                //Save without validation (validation causes problems!)
                                if (!$this->controller->{$model}->save($theData, false)) {
                                    $errors++;
                                }
                            }
                        }
                    }
                }
            }

            // Reset langs
            foreach ($models as $model) {
                $this->controller->{$model}->setLang(LANG, $this->controller);
            }

            if (!empty($errors)) {
                return false;
            }
            else {
                return true;
            }
        }
        else {
            return false;
        }
    }

   /**
    * Strip localized fields from post data
    * @param array $data The post data to strip
    */
    function stripLocalized($data) {

        if (!empty($data['Addon'])) {
            foreach ($this->controller->Addon->translated_fields as $field) {
                unset($data['Addon'][$field]);
            }
        }

        if (!empty($data['Version'])) {
            foreach ($this->controller->Version->translated_fields as $field) {
                unset($data['Version'][$field]);
            }
        }
        
        unset($data['Locales']);

        return $data;
    }

   /**
    * Highlight another preview because the current is being removed/deleted
    * @param array $preview The current preview data
    * @param array $addon The current addon data
    */
    function highlightNextPreview($preview, $addon) {
        $oldId = $this->controller->Preview->id;

        foreach ($addon['Preview'] as $prev) {
            if ($prev['id'] != $preview['Preview']['id']) {
                $this->controller->Preview->id = $prev['id'];
                $this->controller->Preview->save(array('highlight' => 1));
                break;
            }
        }

        $this->controller->Preview->id = $oldId;
    }

   /**
    * Remove highlight from highlighted previews
    * @param int $addon_id Add-on's id
    */
    function unhighlightOtherPreviews($addon_id) {
        $this->controller->Preview->execute("UPDATE previews SET highlight=0 WHERE addon_id='{$addon_id}'");
    }
    
    function addPreview($addon_id, $data) {
        $previewData = array('addon_id' => $addon_id,
                             'filedata' => file_get_contents($data['File']['tmp_name']),
                             'filetype' => $data['File']['type'],
                             'highlight' => $data['highlight'],
                             'thumbtype' => 'image/png'
                             );
        
        //Check for allowed file extensions
        $allowedImage = array('.png', '.jpg', '.gif');
        $extension = substr($data['File']['name'], strrpos($data['File']['name'], '.'));
        if (!in_array($extension, $allowedImage)) {
            $errors =& $this->controller->Error;
            $errors->addError(sprintf(_('devcp_error_preview_extension'), $extension, implode(', ', $allowedImage)), 'main');
            return false;
        }
        
        list($sourceWidth, $sourceHeight) = getimagesize($data['File']['tmp_name']);
        
        //Generate thumbnail (200 x 150) if necessary
        if ($sourceHeight < 150 && $sourceWidth < 200) {
            $previewData['thumbdata'] = $previewData['filedata'];
        }
        else {
            $previewData['thumbdata'] = $this->resizeImage($previewData['filedata'], $sourceWidth, $sourceHeight, 200, 150);
        }
        
        //Resize preview if too large (700 x 525)
        if ($sourceWidth > 700 || $sourceHeight > 525) {
            $previewData['filedata'] = $this->resizeImage($previewData['filedata'], $sourceWidth, $sourceHeight, 700, 525);
            $previewData['filetype'] = 'image/png';
        }
        
        /*
        //Debug preview adjustments
        $full = fopen(REPO_PATH.'/full.png', 'wb');
        fwrite($full, $previewData['filedata']);
        fclose($full);
        
        $new = fopen(REPO_PATH.'/new.png', 'wb');
        fwrite($new, $previewData['thumbdata']);
        fclose($new);
        
        echo '<img src="../../../files/full.png">';
        echo '<img src="../../../files/new.png">';
        //pr($previewData);
        */
        
        return $previewData;
    }
    
   /**
    * Determine file status based on submission information
    * @param array $addon Addon informaiton
    * @return int $fileStatus the file status
    */
    function determineFileStatus($addon) {
        //If a trusted public add-on, go to sandbox unless specified public
        if ($addon['trusted'] == 1 && $addon['status'] == STATUS_PUBLIC) {
            if (!empty($this->controller->data['File']['status'])) {
                if ($this->controller->data['File']['status'] == 'public') {
                    $fileStatus = STATUS_PUBLIC;
                }
                else {
                    $fileStatus = STATUS_SANDBOX;
                }
            }
            elseif ($version = $this->controller->Version->read()) {
                $fileStatus = $version['File'][0]['status'];
            }
            else {
                $fileStatus = STATUS_SANDBOX;
            }
        }
        //If an update to an untrusted public add-on, STATUS_PENDING
        elseif ($addon['status'] == STATUS_PUBLIC) {
            $fileStatus = STATUS_PENDING;
        }
        //In all other cases (new add-on and update to non-public add-on), STATUS_SANDBOX
        else {
            $fileStatus = STATUS_SANDBOX;
        }
        
        return $fileStatus;
    }
    
    function getAllowedAddonTypes($autoDetect, $isAdmin) {
        $addontypes = array(
                        ADDON_EXTENSION => $this->controller->Addontype->getName(ADDON_EXTENSION),
                        ADDON_THEME     => $this->controller->Addontype->getName(ADDON_THEME),
                        ADDON_DICT => $this->controller->Addontype->getName(ADDON_DICT),
                        ADDON_LPAPP => $this->controller->Addontype->getName(ADDON_LPAPP)
                      );
        
        if ($autoDetect == true) {
            $addontypes[0] = _('devcp_additem_addontype_autodetect');
        }
        
        if ($isAdmin == true) {
            $addontypes[ADDON_SEARCH] = $this->controller->Addontype->getName(ADDON_SEARCH);
        }
        
        ksort($addontypes);
        
        return $addontypes;
    }

    function getLicenses($version_id=null) {
        if ($version_id != null) {
            $version = $this->controller->Version->findById($version_id);
            $version = $version['Version'];
            $license = $this->controller->License->findById($version['license_id']);
        }

        // Add 'Please Choose...' only if no license has been selected.
        // if (!isset($version['license_id'])) {
        //     $licenses['null'] = array(
        //         'name' => ___('devcp_uploader_please_choose'),
        //         'selected' => True);
        // }

        // Grab all the pre-approved licenses.
        foreach ($this->controller->License->getNamesAndUrls() as $num => $builtin) {
            $licenses['builtin_'.$num] = array(
                'name' => $builtin['name'],
                'url'  => $builtin['url'],
                'selected' => isset($license) && (string)$num === $license['License']['name']);
        }

        // The trans array holds translations for all the custom licenses we'll
        // be displaying.  `other` starts off empty, for creating new licenses.
        $trans['other']['text']['en-US'] = '';

        if ($version_id != null) {
            // Find all the custom licenses in use by this add-on.
            $q = "SELECT Version.version, Version.license_id
                  FROM versions AS Version INNER JOIN licenses AS License
                    ON Version.license_id = License.id
                  WHERE Version.addon_id = {$version['addon_id']}
                    AND Version.license_id IS NOT NULL
                    AND License.name = -1
                  GROUP BY License.id
                  ORDER BY Version.id DESC";
            foreach ($this->controller->Version->execute($q) as $existing) {
                $existing = $existing['Version'];
                $t = ___('devcp_license_existing');
                $val = 'existing_'.$existing['license_id'];
                $licenses[$val] = array(
                    'name' => sprintf($t, $version['addon_id'], $existing['version']),
                    'selected' => $existing['license_id'] == $version['license_id']);
                $trans[$val] = $this->controller->License->getAllTranslations($existing['license_id']);
            }
        }
        // did we select any of those?
        $noSelections = true;
        
        foreach ($licenses AS $license) {
            if ($license['selected']) {
                $noSelections = false;
                break;
            }
        }
        
        $licenses['other'] = array('name' => ___('devcp_uploader_option_other'),
                                   'selected' => $noSelections);
        return array($licenses, $trans);
    }

    function saveLicense($licenseData, $text, $params) {
        $License = $this->controller->License;
        if ($licenseData['name'] != 'null') {
            $license = $licenseData['name'];
            // If the license is pre-approved, we prefixed the id with builtin_.
            if (preg_match('/^builtin_(\d+)$/', $license, $matches)) {
                $license_id = $License->getBuiltin($matches[1]);
            } else if ($license == 'other' ||
                       preg_match('/^existing_(\d+)$/', $license, $matches)) {
                // If it's 'other', we need to create a new license.
                if ($license == 'other') {
                    $data['License']['name'] = -1;
                    $License->save($data);
                    $license_id = $License->getLastInsertId();
                } else {
                    $license_id = $matches[1];
                }
                // Save any changed translation text.
                $localized['text'] = $text;
                $License->saveTranslations($license_id, $params, $localized);
            }
            return $license_id;
        }
    }
}
?>
