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
 * Frederic Wenzel <fwenzel@mozilla.com>
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
class DevelopersTest extends UnitTestCase {
	
	//Setup the Developers Component
	function setUp() {
	    $this->controller =& new AppController();
		loadComponent('Developers');
		$this->controller->Developers =& new DevelopersComponent();
		$this->controller->Developers->startup($this->controller);
		loadComponent('Error');
		$this->controller->Error =& new ErrorComponent();
		$this->controller->Error->startup($this->controller);
		loadComponent('Amo');
		$this->controller->Amo =& new AmoComponent();
		$this->controller->Amo->startup($this->controller);
	}

   /**
    * Test the validateCategories() method
    */
	function testValidateCategories() {
	    $this->controller->Category =& new Category();
	    
	    //Test selecting valid number of categories
	    $this->assertTrue($this->controller->Developers->validateCategories(array('1', '2', '3', '4', '5')), 'Select valid number of categories (return true)');
		$this->assertEqual($this->controller->Error->errors['Category/Category'], null, 'Select valid number of categories (error string)');
		
	    //Test selecting no categories
	    $this->assertFalse($this->controller->Developers->validateCategories(array()), 'Must select at least one category (return false)');
		$this->assertEqual($this->controller->Error->errors['Category/Category'], 'Please select at least one category.', 'Must select at least one category (error string): %s');
		
		//Test selecting many categories
	    $this->assertFalse($this->controller->Developers->validateCategories(array('1', '2', '3', '4', '5', '6')), 'Select no more than 5 categories (return false)');
		$this->assertEqual($this->controller->Error->errors['Category/Category'], 'Please select no more than five categories.', 'Select no more than 5 categories (error string): %s');
	}
	
   /**
    * Test the validateUsers() method
    */
	function testValidateUsers() {
	    $this->controller->User =& new User();
	    
	    //Test removing duplicate authors
		$array = array('1', '2', '1');
		$this->assertTrue($this->controller->Developers->validateUsers($array), 'At least one author (return true)');
		$this->assertEqual(count($array), 2, 'Duplicate authors removed');
		
	    //Test selecting no authors
	    $array = array();
	    $this->assertFalse($this->controller->Developers->validateUsers($array), 'Must select at least one author (return false)');
		$this->assertEqual($this->controller->Error->errors['User/User'], 'There must be at least one author for this add-on.', 'Must select at least one author (error string): %s');		
	}
	
   /**
    * Test the getCategories() method
    */
	function testGetCategories() {
	    $this->controller->Category =& new Category();
	    
	    //Get all categories for extensions
	    $testArray = $this->controller->Developers->getCategories(ADDON_EXTENSION, array(1));
		$this->assertIsA($testArray, 'Array', 'Retrieved categories for addon type');
	}

   /**
    * Test the getSelectedCategories() method
    */
	function testGetSelectedCategories() {
	    $this->controller->Category =& new Category();
	    
	    //Test order of selected categories - none selected
	    $testArray = $this->controller->Developers->getSelectedCategories(null);
		$this->assertTrue(empty($testArray), 'No selected categories');
		
		//Current categories in database
		$currentCategories = array(
		                array(
		                    'id' => '15',
		                    'name' => 'Test'
		                )
		               );
		$testArray = $this->controller->Developers->getSelectedCategories($currentCategories);
		$this->assertTrue(in_array(15, $testArray), 'Database selected categories');
		
		//Post data selected categories
		$this->controller->data['Category']['Category'][] = 20;
		$testArray = $this->controller->Developers->getSelectedCategories($currentCategories);
		$this->assertTrue(in_array(20, $testArray), 'POST data selected categories');
		
	}
	
   /**
    * Test the getAuthors() method
    */
	function testGetAuthors() {
	    $this->controller->User =& new User();
	    
	    //Test order of selected users - none selected
	    $testArray = $this->controller->Developers->getAuthors(null, false);
		$this->assertTrue(empty($testArray), 'No selected users');
		
		//Current users in database
		$currentUsers = array(
		                array(
		                    'id' => '15',
		                    'firstname' => 'Filliam',
		                    'lastname' => 'Muffman',
		                    'email' => 'fmuffman@excite.com'
		                )
		               );
		$testArray = $this->controller->Developers->getAuthors($currentUsers, false);
		$this->assertEqual($testArray[15], 'Filliam Muffman [fmuffman@excite.com]', 'Database selected users');
		
		//Post data selected users
		$this->controller->data['User']['User'][] = 20;
		$testArray = $this->controller->Developers->getAuthors($currentUsers, false);
		$this->assertTrue($testArray[20], 'POST data selected users');
		
	}

   /**
    * Resets the file information to a valid state and clears the errors from previous instance
    */
    function setupValidateFiles() {
        $this->controller->Error->resetErrors();
               
        $this->controller->data['Addon']['addontype_id'] = ADDON_EXTENSION;
        $this->controller->data['File']['file1']['name'] = 'extension-works.xpi';
        $this->controller->data['File']['file1']['tmp_name'] = TEST_DATA.'/extension-works.xpi';
        $this->controller->data['File']['file1']['size'] = 0;
        $this->controller->data['File']['file1']['error'] = 0;
        $this->controller->data['File']['platform_id1'] = '1';
    }
    
   /**
    * Test the validateFiles() method
    */
    function testValidateFiles() {
    
        $this->controller->File =& new File();

        //Make sure file is uploaded
        $this->setupValidateFiles();
        $this->controller->data['File']['file1']['name'] = '';
        $this->controller->Developers->validateFiles();
        $this->assertEqual($this->controller->Error->errors['File/file1'], 'Please upload a file.', 'No file uploaded: %s');
        
        //Make sure platform selected and file extensions are checked    
        $this->setupValidateFiles();
        
        $this->controller->data['File']['platform_id1'] = '';
        $this->controller->data['File']['file1']['name'] = 'badextension.exe';
        $this->controller->Developers->validateFiles();
        $this->assertEqual($this->controller->Error->errors['File/platform_id1'], 'No platform selected', 'No platform selected: %s');
        $this->assertEqual($this->controller->Error->errors['File/file1'], 'That file extension (.exe) is not allowed for the selected add-on type. Please use one of the following: .xpi', 'Disallowed file extension: %s');

        //HTTP errors - file upload size (http)
        $this->setupValidateFiles();
        $this->controller->data['File']['file1']['error'] = 1;
        $this->controller->Developers->validateFiles();
        $this->assertEqual($this->controller->Error->errors['File/file1'], 'Exceeds maximum upload size', 'HTTP File Error: HTTP max upload size: %s');
        
        //HTTP errors - file upload size (php)
        $this->setupValidateFiles();
        $this->controller->data['File']['file1']['error'] = 2;
        $this->controller->Developers->validateFiles();
        $this->assertEqual($this->controller->Error->errors['File/file1'], 'Exceeds maximum upload size', 'HTTP File Error: PHP max upload size: %s');
        
        //HTTP errors - incomplete transfer
        $this->setupValidateFiles();
        $this->controller->data['File']['file1']['error'] = 3;
        $this->controller->Developers->validateFiles();
        $this->assertEqual($this->controller->Error->errors['File/file1'], 'Incomplete transfer', 'HTTP File Error: Incomplete transfer: %s');
        
        //HTTP errors - no file uploaded
        $this->setupValidateFiles();
        $this->controller->data['File']['file1']['error'] = 4;
        $this->controller->Developers->validateFiles();
        $this->assertEqual($this->controller->Error->errors['File/file1'], 'No file uploaded', 'HTTP File Error: No file uploaded: %s');
        
        //Could not move file
        $this->setupValidateFiles();
        $this->controller->Developers->validateFiles();
        $this->assertEqual($this->controller->Error->errors['File/file1'], 'Could not move file');
    }


    /**
    * Test validateIcon() icon method incl. the image data it produces
    */
    function testValidateIcon() {
        $allowedImage = array('.png', '.jpg', '.gif');
        $fileErrors = array('1' => _('devcp_error_http_maxupload'),
                            '2' => _('devcp_error_http_maxupload'),
                            '3' => _('devcp_error_http_incomplete'),
                            '4' => _('devcp_error_http_nofile')
                      );

        /* test vector for extension tests */
        $extTestVec = array(
            'base-image' => sprintf(_('devcp_error_icon_extension'), 'base-image', implode(', ', $allowedImage)),
            'base-image.xpi' => sprintf(_('devcp_error_icon_extension'), '.xpi', implode(', ', $allowedImage))
        );

        /* test image */
        $fileSize = filesize(TEST_DATA.'/base-icon.png');
        $baseFile = array(
            'name' => 'base-image.png',
            'tmp_name' => TEST_DATA.'/base-icon.png',
            'size' => $fileSize,
            'type'=> 'image/png',
            'error' => 0
        );

        /* test error processing */
        for ($i = 1; $i < 5; $i++) {
            $file = $baseFile;
            $file['error'] = $i;
            $resized = $this->controller->Developers->validateIcon($file, $fileErrors, $allowedImage);
            $this->assertEqual($resized, $fileErrors[$i], '%s');
        }

        /* test allowed extensions */
        foreach ($extTestVec as $name => $err) {
            $file = $baseFile;
            $file['name'] = $name;
            $resized = $this->controller->Developers->validateIcon($file, $fileErrors, $allowedImage);
            $this->assertEqual($resized, $err, '%s');
        }

        /* test correct output */
        $file = $baseFile;

        $resized = $this->controller->Developers->validateIcon($file, $fileErrors, $allowedImage);
        $this->assertFalse(empty($resized) || empty($resized['icondata']), "failed to load image");

        // XXX: better compare pixel-wise, but not sure how to achieve it at this point
        // $fp = fopen(TEST_DATA.'/resized-icon.png', 'wb'); fwrite($fp, $resized['icondata']); fclose($fp);
        $cmp = file_get_contents(TEST_DATA.'/resized-icon.png');
        $this->assertEqual($resized['icondata'], $cmp, "resized icon data doesn't match provided image data");
    }

   /**
    * Test the getAllowedExtensions() method
    */
    function testGetAllowedExtensions() {
        $allowed = $this->controller->Developers->getAllowedExtensions(ADDON_EXTENSION);
        $this->assertTrue(in_array('.xpi', $allowed), '.xpi files allowed for extensions');
        $this->assertFalse(in_array('.jar', $allowed), '.jar files not allowed for extensions');
        
        $allowed = $this->controller->Developers->getAllowedExtensions(ADDON_THEME);
        $this->assertTrue(in_array('.xpi', $allowed), '.xpi files allowed for themes');
        $this->assertTrue(in_array('.jar', $allowed), '.jar files allowed for themes');
        
        $allowed = $this->controller->Developers->getAllowedExtensions(ADDON_SEARCH);
        $this->assertTrue(in_array('.xml', $allowed), '.xml files allowed for search plugins');
        $this->assertFalse(in_array('.xpi', $allowed), '.xpi files not allowed for search plugins');
    }
    
   /**
    * Start each test with a valid install.rdf copy and add errors after
    */
    function setupValidateManifestData() {
        $fileContents = file_get_contents(TEST_DATA.'/test-install.rdf');
        $manifestData = $this->Rdf->parseInstallManifest($fileContents);
        
        return $manifestData;
    }
    
   /**
    * Test the validateManifestData() method
    */
    function testValidateManifestData() {
        loadComponent('Rdf');
        $this->Rdf =& new RdfComponent();
        $this->controller->Application =& new Application();
        loadModel('BlacklistedGuid');
        $this->controller->BlacklistedGuid =& new BlacklistedGuid();
        
        //Test a valid manifest file
        $manifestData = $this->setupValidateManifestData();
        $this->assertTrue($this->controller->Developers->validateManifestData($manifestData), 'Valid manifest data (return true)');
        $this->assertEqual($this->controller->Error->errors['main'], '', 'Valid manifest data (error string)');
        
        //GUID of an application not allowed
        $manifestData = $this->setupValidateManifestData();
        $manifestData['id'] = '{ec8030f7-c20a-464f-9b0e-13a3a9e97384}';
        $return = $this->controller->Developers->validateManifestData($manifestData);
        $this->assertEqual($return, 'The ID of this add-on is already used by an application.', 'Using an application GUID: %s');
        
        //Invalid GUID
        $manifestData = $this->setupValidateManifestData();
        $manifestData['id'] = '{B17C1C5A-04B1-11DB-9804-B622A1EF5}';
        $return = $this->controller->Developers->validateManifestData($manifestData);
        $this->assertEqual($return, 'The ID of this add-on is invalid: {B17C1C5A-04B1-11DB-9804-B622A1EF5}', 'Invalid GUID: %s');
        
        //Invalid version
        $manifestData = $this->setupValidateManifestData();
        $manifestData['version'] = 'Bad Version';
        $return = $this->controller->Developers->validateManifestData($manifestData);
        $this->assertEqual($return, 'The version of this add-on is invalid: versions cannot contain spaces.', 'Invalid Version: %s');
        
        //Invalid version - strange
        $manifestData = $this->setupValidateManifestData();
        $manifestData['version'] = '1#$$3k';
        $return = $this->controller->Developers->validateManifestData($manifestData);
        $this->assertEqual($return, 'The version of this add-on is invalid: please see the <a href="http://developer.mozilla.org/en/docs/Toolkit_version_format">specification</a>', 'Invalid Version: %s');
        
        //updateURL present
        $manifestData = $this->setupValidateManifestData();
        $manifestData['updateURL'] = 'http://addons.mozilla.org';
        $return = $this->controller->Developers->validateManifestData($manifestData);
        $this->assertEqual($return, 'Add-ons cannot use an external updateURL. Please remove this from install.rdf and try again.', 'updateURL present: %s');
       
        //updateKey present
        $manifestData = $this->setupValidateManifestData();
        $manifestData['updateKey'] = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDK426erD';
        $return = $this->controller->Developers->validateManifestData($manifestData);
        $this->assertEqual($return, 'Add-ons cannot use an updateKey. Please remove this from install.rdf and try again.', 'updateKey present: %s');
 
        //Parse error (any string = parse error)
        $manifestData = 'Something was wrong!';
        $return = $this->controller->Developers->validateManifestData($manifestData);
        $this->assertEqual($return, 'The following error occurred while parsing install.rdf: Something was wrong!', 'Parse error: %s');

    }
    
   /**
    * Setup valid target applications
    */
    function setupValidateTargetApplications() {
        $this->controller->Error->errors['main'] = '';
        $targetApps = array(
                        '{ec8030f7-c20a-464f-9b0e-13a3a9e97384}' => array(
                                                                        'minVersion' => '1.5',
                                                                        'maxVersion' => '3.0a1'
                                                                    ),
                        '{3550f703-e582-4d05-9a08-453d09bdfdc6}' => array(
                                                                        'minVersion' => '1.5',
                                                                        'maxVersion' => '1.5'
                                                                    )
                      );
                      
        return $targetApps;
    }
    
   /**
    * Test validateTargetApplications() method
    */
    function testValidateTargetApplications() {
        $this->controller->Application =& new Application();
        $this->controller->Appversion =& new Appversion();
    
        //Valid target applications
        $targetApps = $this->setupValidateTargetApplications();
        $this->assertIsA($this->controller->Developers->validateTargetApplications($targetApps), 'array', 'Valid target applications');
        
        //Invalid versions
        $targetApps = $this->setupValidateTargetApplications();
        $targetApps['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}']['minVersion'] = '42';
        $targetApps['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}']['maxVersion'] = '1337';
        $return = $this->controller->Developers->validateTargetApplications($targetApps);
        $this->assertEqual($return, 'The following errors were found in install.rdf:<br />42 is not a valid version for Firefox<br />1337 is not a valid version for Firefox<br />Please see <a href="/en-US/firefox/pages/appversions">this page</a> for reference.', 'Invalid versions: %s');

        //Only Flock
        $targetApps = $this->setupValidateTargetApplications();
        unset($targetApps['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}']);
        unset($targetApps['{3550f703-e582-4d05-9a08-453d09bdfdc6}']);
        $return = $this->controller->Developers->validateTargetApplications($targetApps);
        $this->assertEqual($return, 'You must have at least one valid Mozilla target application.<br />Please see <a href="/en-US/firefox/pages/appversions">this page</a> for reference.', 'No Mozilla applications: %s');
        
        //No applications at all
        $targetApps = array();
        $return = $this->controller->Developers->validateTargetApplications($targetApps);
        $this->assertEqual($return, 'You must have at least one valid Mozilla target application.<br />Please see <a href="/en-US/firefox/pages/appversions">this page</a> for reference.', 'No applications at all: %s');
        
        //Unknown application
        $targetApps = $this->setupValidateTargetApplications();
        $targetApps['{12345-12345}'] = array(
                                                                    'minVersion' => '1.0',
                                                                    'maxVersion' => '2.0'
                                                                );
        $this->assertIsA($this->controller->Developers->validateTargetApplications($targetApps), 'array', 'Unknown application');
    }
}
?>
