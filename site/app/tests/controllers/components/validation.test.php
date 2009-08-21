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
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   RJ Walsh <rwalsh@mozilla.com>
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
  
// Cache prefix
define('CACHE_PFX', NETAPP_STORAGE . '/validate-');

class ValidationTest extends UnitTestCase {
    
    //Setup the Developers Component
    function setUp() {
        $this->controller =& new AppController();
        loadComponent('Validation');
        $this->controller->Validation =& new ValidationComponent();
        $this->controller->Validation->startup($this->controller);
        loadComponent('Error');
        $this->controller->Error =& new ErrorComponent();
        $this->controller->Error->startup($this->controller);
        loadComponent('Amo');
        $this->controller->Amo =& new AmoComponent();
        $this->controller->Amo->startup($this->controller);
        $this->controller->Validation->Amo =& $this->controller->Amo;
        loadComponent('Opensearch');
        $this->controller->Validation->Opensearch =& new OpensearchComponent();
        loadComponent('Rdf');
        $this->controller->Validation->Rdf =& new RdfComponent();
        loadComponent('Versioncompare');
        $this->controller->Validation->Versioncompare =& new VersioncompareComponent();

        // Load in models
        $this->controller->File =& new File();
        $this->controller->TestResult =& new TestResult();
        $this->controller->TestCase =& new TestCase();
        $this->controller->TestGroup =& new TestGroup();
        $this->controller->Addon =& new Addon();
        $this->controller->Application =& new Application();
        $this->controller->Appversion =& new Appversion();
        loadModel('BlacklistedGuid');
        $this->controller->BlacklistedGuid =& new BlacklistedGuid();

        // Prime the cache
        $fileIds = array(1,3,11,12,13);
        foreach ($fileIds as $id) {
            $file = $this->controller->File->findById($id);
            $this->controller->Validation->_extract($file, 'by_preg', '//');
        }
        
    }
    
    /** 
     * Clears the cache after a test
     */
    function tearDown() {
        $fileIds = array(1,3,11,12,13);
        foreach ($fileIds as $id) {
            $this->controller->Validation->_deleteDir(CACHE_PFX . $id);
        }
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
        
        //Test a valid manifest file
        $manifestData = $this->setupValidateManifestData();
        $this->assertTrue($this->controller->Validation->validateManifestData($manifestData), 'Valid manifest data (return true)');
        $this->assertEqual($this->controller->Error->errors['main'], '', 'Valid manifest data (error string)');
        
        //GUID of an application not allowed
        $manifestData = $this->setupValidateManifestData();
        $manifestData['id'] = '{ec8030f7-c20a-464f-9b0e-13a3a9e97384}';
        $return = $this->controller->Validation->validateManifestData($manifestData);
        $this->assertEqual($return, 'The ID of this add-on is already used by an application.', 'Using an application GUID: %s');
        
        //Invalid GUID
        $manifestData = $this->setupValidateManifestData();
        $manifestData['id'] = '{B17C1C5A-04B1-11DB-9804-B622A1EF5}';
        $return = $this->controller->Validation->validateManifestData($manifestData);
        $this->assertEqual($return, 'The ID of this add-on is invalid: {B17C1C5A-04B1-11DB-9804-B622A1EF5}', 'Invalid GUID: %s');
        
        //Invalid version
        $manifestData = $this->setupValidateManifestData();
        $manifestData['version'] = 'Bad Version';
        $return = $this->controller->Validation->validateManifestData($manifestData);
        $this->assertEqual($return, 'The version of this add-on is invalid: versions cannot contain spaces.', 'Invalid Version: %s');
        
        //Invalid version - strange
        $manifestData = $this->setupValidateManifestData();
        $manifestData['version'] = '1#$$3k';
        $return = $this->controller->Validation->validateManifestData($manifestData);
        $this->assertEqual($return, 'The version of this add-on is invalid: please see the <a href="http://developer.mozilla.org/en/docs/Toolkit_version_format">specification</a>', 'Invalid Version: %s');
        
        //updateURL present
        $manifestData = $this->setupValidateManifestData();
        $manifestData['updateURL'] = 'http://addons.mozilla.org';
        $return = $this->controller->Validation->validateManifestData($manifestData);
        $this->assertEqual($return, 'Add-ons cannot use an external updateURL. Please remove this from install.rdf and try again.', 'updateURL present: %s');
       
        //updateKey present
        $manifestData = $this->setupValidateManifestData();
        $manifestData['updateKey'] = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDK426erD';
        $return = $this->controller->Validation->validateManifestData($manifestData);
        $this->assertEqual($return, 'Add-ons cannot use an updateKey. Please remove this from install.rdf and try again.', 'updateKey present: %s');
 
        //Parse error (any string = parse error)
        $manifestData = 'Something was wrong!';
        $return = $this->controller->Validation->validateManifestData($manifestData);
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
        $this->assertIsA($this->controller->Validation->validateTargetApplications($targetApps), 'array', 'Valid target applications');
        
        //Invalid versions
        $targetApps = $this->setupValidateTargetApplications();
        $targetApps['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}']['minVersion'] = '42';
        $targetApps['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}']['maxVersion'] = '1337';
        $return = $this->controller->Validation->validateTargetApplications($targetApps);
        $this->assertEqual($return, 'The following errors were found in install.rdf:<br />42 is not a valid version for Firefox<br />1337 is not a valid version for Firefox<br />Please see <a href="/en-US/firefox/pages/appversions">this page</a> for reference.', 'Invalid versions: %s');

        //Only Flock
        $targetApps = $this->setupValidateTargetApplications();
        unset($targetApps['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}']);
        unset($targetApps['{3550f703-e582-4d05-9a08-453d09bdfdc6}']);
        $return = $this->controller->Validation->validateTargetApplications($targetApps);
        $this->assertEqual($return, 'You must have at least one valid Mozilla target application.<br />Please see <a href="/en-US/firefox/pages/appversions">this page</a> for reference.', 'No Mozilla applications: %s');
        
        //No applications at all
        $targetApps = array();
        $return = $this->controller->Validation->validateTargetApplications($targetApps);
        $this->assertEqual($return, 'You must have at least one valid Mozilla target application.<br />Please see <a href="/en-US/firefox/pages/appversions">this page</a> for reference.', 'No applications at all: %s');
        
        //Unknown application
        $targetApps = $this->setupValidateTargetApplications();
        $targetApps['{12345-12345}'] = array(
                                                                    'minVersion' => '1.0',
                                                                    'maxVersion' => '2.0'
                                                                );
        $this->assertIsA($this->controller->Validation->validateTargetApplications($targetApps), 'array', 'Unknown application');
    }

    /**
     * Tests the runTest() method
     */
    function testRunTest() {
                                                                        
        // Verify failure on bad data
        $this->assertFalse($this->controller->Validation->runTest(1, -1), 'Test fails on bad test_group_id (return false)');        
        $this->assertFalse($this->controller->Validation->runTest(-1, 1), 'Test fails on bad file_id (return false)');

        // Verify pass on good data
        $this->assertTrue($this->controller->Validation->runTest(1, 1), 'Default addon passes tests: %s');
        
        // Verify that we get 3 passes
        $this->assertEqual(count($this->controller->TestResult->findAll(array('TestResult.file_id' => 1, 'TestCase.test_group_id' => 1, 'TestResult.result' => TEST_PASS))), 3, 'All three tests should pass: %s');

        // Verify failures on wrong test
        $this->controller->Validation->runTest(1, 31);
        $results = $this->controller->TestResult->findAll(array('TestResult.file_id' => 1, 'TestCase.test_group_id' => 31));
        
        $this->assertEqual(count($results), 3, 'Tests should fail on wrong test group type: %s');

    }

    /**
     * Tests the all_general_verifyExtension() method
     */
    function testAll_general_verifyExtension() {

        $file = $this->controller->File->findById(1);
        
        // Verify default extension
        $results = $this->controller->Validation->all_general_verifyExtension($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Default extension should have correct file type: %s');

        // Try some bad extension
        $file['File']['filename'] = 'bad.badExt';
        $results = $this->controller->Validation->all_general_verifyExtension($file);
        $fail = $this->controller->Validation->_resultFail(0, '', 'The extension does not match the type of the add-on.');
        $this->assertEqual($results, $fail, 'Bad extensions will return a fail result: %s');
    
        // Verify theme extension
        $file = $this->controller->File->findById(13);
        $results = $this->controller->Validation->all_general_verifyExtension($file);
        $this->assertEqual($results, $pass, 'Theme files should end in the .jar extension: %s');
    }

    /**
     * Tests the all_general_verifyInstallRDF() method
     */
    function testAll_general_verifyInstallRDF() {

        $file = $this->controller->File->findById(1);

        // Verify pass on default extension
        $results = $this->controller->Validation->all_general_verifyInstallRDF($file);
        $expected = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $expected, 'Default extension passes tests: %s');
        
        // Verify that duplicate ids generates an error
        $data = "<?xml version=\"1.0\"?>
<RDF xmlns=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"
    xmlns:em=\"http://www.mozilla.org/2004/em-rdf#\">
  <Description about=\"urn:mozilla:install-manifest\">
            <em:id>en-AU@dictionaries.addons.mozilla.org</em:id>
            <em:id>dup-id@dictionaries.addons.mozilla.org</em:id>
            <em:version>2.1.1</em:version>
  <em:targetApplication>
      <Description>
      <em:id>{92650c4d-4b8e-4d2a-b7eb-24ecf4f6b63a}</em:id>
      <em:minVersion>2.0a1</em:minVersion>
      <em:maxVersion>2.0a2</em:maxVersion>
      </Description>
  </em:targetApplication>
  <em:name>English (Australian) Dictionary</em:name>
  <em:description>I'm sick of all my favoUrite coloUrful language being marked incorrect.</em:description>
  <em:homepageURL>http://justcameron.com/incoming/en-au-dictionary/</em:homepageURL>
  </Description>
</RDF>";
        file_put_contents(CACHE_PFX . '1/install.rdf', $data);
        $results = $this->controller->Validation->all_general_verifyInstallRDF($file);
        $expected = $this->controller->Validation->_resultFail(0, 'install.rdf', 'RDF Parser error: the file contained a duplicate element: id');
        $this->assertEqual($results, $expected, 'Duplicate elements generate errors: %s');
        
        // Verify fail on missing install.rdf
        @unlink(CACHE_PFX . '1/install.rdf');

        $results = $this->controller->Validation->all_general_verifyInstallRDF($file);
        $expected = $this->controller->Validation->_resultFail(0, '', 'No install.rdf present.');
        $this->assertEqual($results, $expected, 'Missing install.rdf generates a fail result: %s');

    }

    /**
     * Tests the all_general_verifyFileTypes() method
     */
    function testAll_general_verifyFileTypes() {

        $file = $this->controller->File->findById(1);
        
        // Verify default extension
        $results = $this->controller->Validation->all_general_verifyFileTypes($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Default extension should have no blacklisted files: %s');
        
        // Make some bad data
        $extensions = array('.dll', '.exe', '.DYLIB', '.So', '.sH');
        foreach ($extensions as $ext) {
            touch(CACHE_PFX . '1/foo' . $ext);
        }

        $results = $this->controller->Validation->all_general_verifyFileTypes($file);
        $this->assertEqual(count($results), 5, 'All blacklisted file types should be flagged: %s');
        
        $expected = $this->controller->Validation->_result(TEST_WARN, 0, '', 'The add-on contains a file \'foo.exe\', which is a flagged type.');
        $this->assertEqual($results[0], $expected, 'Results are warnings mentioning the flagged type: %s');

    }

    /**
     * Tests the all_general_checkJSPollution() method
     */
    function testAll_general_checkJSPollution() {

        $file = $this->controller->File->findById(1);
        
        // Verify pass on default extension
        $results = $this->controller->Validation->all_general_checkJSPollution($file);
        $this->assertEqual($results, array(), 'Default extension skips tests: %s');

        // Give it some clean JS files that should pass
        $goodJS = "if (FOO == null) { var FOO = new Object();} 
                   FOO.BAR = {
                       baz: function() {
                           // This is all namespaced!
                       }
                   }";
        file_put_contents(CACHE_PFX . '1/good.js', $goodJS);

        $results = $this->controller->Validation->all_general_checkJSPollution($file);
        $expected = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $expected, 'Clean javascript files produce no errors: %s');

        // Give it some bad JS and verify failure
        $badJS = "var GVar;
                  const GConst = 1;
                  function GFunc() {
                      // These are all globals!
                  }";
        file_put_contents(CACHE_PFX . '1/bad.js', $badJS);
        
        $results = $this->controller->Validation->all_general_checkJSPollution($file);
        $this->assertEqual(count($results), 3, 'Test finds variables, constants and functions: %s');

        $expected = $this->controller->Validation->_result(TEST_WARN, 1, 'bad.js', 'The file contains a global variable: GVar');
        $this->assertEqual($results[0], $expected, 'Results are warnings mentioning the type of global and its name: %s');
    }

    /**
     * Tests the all_security_libraryChecksum() method
     */
    function testAll_security_libraryChecksum() {

        $file = $this->controller->File->findById(1);
        
        // Verify default extension
        $results = $this->controller->Validation->all_security_libraryChecksum($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Default Extension passes library tests: %s');

        // Use some test data to check the library checksum
        copy(TEST_DATA . '/remora-test-log.log', CACHE_PFX . '1/jquery-testing');

        $results = $this->controller->Validation->all_security_libraryChecksum($file);
        $this->assertEqual($results, $pass, 'Unmodified libraries pass the tests: %s');
     
        // Any addon that we don't have a hash for will not generate errors
        touch(CACHE_PFX . '1/jquery-doesnt-exist.js');
        
        $results = $this->controller->Validation->all_security_libraryChecksum($file);
        $this->assertEqual($results, $pass, 'Missing libraries still pass the tests: %s');

        // Make sure a modified file fails the test
        file_put_contents(CACHE_PFX . '1/jquery-1.3.1.min.js', 'Modified!');
        
        $results = $this->controller->Validation->all_security_libraryChecksum($file);
        $expected = $this->controller->Validation->_resultWarn(1, 'jquery-1.3.1.min.js', 'The add-on contains a file \'jquery-1.3.1.min.js\', which failed a library checksum');
        $this->assertEqual($results, $expected, 'Modified libraries fail the tests: %s');
    }

    /**
     * Tests the all_security_filterUnsafeJS() method
     */
    function testAll_security_filterUnsafeJS() {

        $file = $this->controller->File->findById(1);
        
        // Verify default extension
        $results = $this->controller->Validation->all_security_filterUnsafeJS($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Default extension should have no js problems: %s');
        
        // Get some bad JS!
        $badJs = "nsIProcess
                  .launch();
                  eval();
                  setInterval('with a string!');
                  setTimeout(\"double quotes!\");
                  <browser without-type-oh-no>
                  <iframe without-type-oh-no>
                  xpcnativewrappers=
                  evalInSandbox
                  mozIJSSubscriptLoader
                  wrappedJSObject";
        file_put_contents(CACHE_PFX . '1/bad.js', $badJs);
        
        $results = $this->controller->Validation->all_security_filterUnsafeJS($file);
        $this->assertEqual(count($results), 11, 'All flagged patters should generate warnings: %s');
        
        $expected = $this->controller->Validation->_result(TEST_WARN, 1, 'bad.js', 'Matched Pattern: "/nsIProcess/"');
        $this->assertEqual($results[0], $expected, 'Results are warnings with appropriate file and line numbers: %s');
    }

    /**
     * Tests the all_security_filterUnsafeSettings() method
     */
    function testAll_security_filterUnsafeSettings() {

        $file = $this->controller->File->findById(1);
        
        // Verify default extension
        $results = $this->controller->Validation->all_security_filterUnsafeSettings($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Default extension should have no unsafe settings: %s');
        
        // Get some bad JS!
        $badJs = "extensions.update.url
                  extensions.update.enabled
                  extensions.update.interval
                  extensions.addon-id.update.enabled
                  extensions.addon-id.update.url
                  extensions.blocklist.enabled
                  extensions.blocklist.url
                  extensions.blocklist.level
                  extensions.blocklist.interval";
        file_put_contents(CACHE_PFX . '1/bad.js', $badJs);
        
        $results = $this->controller->Validation->all_security_filterUnsafeSettings($file);
        $this->assertEqual(count($results), 9, 'All flagged patters should generate warnings: %s');
        
        $expected = $this->controller->Validation->_result(TEST_WARN, 1, 'bad.js', 'Matched Pattern: "/extensions\.update\.url/"');
        $this->assertEqual($results[0], $expected, 'Results are warnings with appropriate file and line numbers: %s');

    }

    /**
     * Tests the all_security_filterRemoteJS() method
     */
    function testAll_security_filterRemoteJS() {
        
        $file = $this->controller->File->findById(1);
        
        // Verify default extension
        $results = $this->controller->Validation->all_security_filterRemoteJS($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Default extension should have no remote JS: %s');
        
        // Make sure none also passes
        file_put_contents(CACHE_PFX . '1/bad.js', "-moz-binding: none;");
        $results = $this->controller->Validation->all_security_filterRemoteJS($file);
        $this->assertEqual($results, $pass, 'None is an allowed binding: %s');

        // Inject remote loading code
        file_put_contents(CACHE_PFX . '1/bad.js', "-moz-binding: non-chrome-url");
                
        $results = $this->controller->Validation->all_security_filterRemoteJS($file);
        $expected = $this->controller->Validation->_resultWarn(1, 'bad.js', 'Matched Pattern: "/-moz-binding:(?!\s*(url\s*\(\s*["\']?chrome:\/\/.*\/content\/|none))/"');
        $this->assertEqual($results, $expected, 'Results are warnings with appropriate file and line numbers: %s');
    }

    /**
     * Tests the all_l10n_checkCompleteness() method {
     */
    function testAll_l10n_checkCompleteness() {
        
        $file = $this->controller->File->findById(1);
        
        // Verify default extension is not localized
        $results = $this->controller->Validation->all_l10n_checkCompleteness($file);
        $expected = $this->controller->Validation->_resultFail(0, '', 'L10n test returned an error: ');
        $this->assertEqual($results, $expected, 'Default extension should have complete L10n: %s');

        $file = $this->controller->File->findById(3);

        // Advanced extension should have 4 incomplete locales
        $results = $this->controller->Validation->all_l10n_checkCompleteness($file);
        $this->assertEqual(count($results), 4, 'The advanced extension has 4 incomplete locales');

        $expected = $this->controller->Validation->_result(TEST_WARN, 0, '', 'The it-IT locale contains 7 unmodified translations');
        $this->assertEqual($results[0], $expected, 'Results are warnings conforming to a threshold: %s');
    }

    /**
     * Tests the dictionary_general_verifyFileLayout() method
     */
    function testDictionary_general_verifyFileLayout() {
        
        $file = $this->controller->File->findById(11);
        
        // Verify default dictionary 
        $results = $this->controller->Validation->dictionary_general_verifyFileLayout($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Default dictionary should pass file layout tests: %s');
        
        // Make sure all missing files are flagged
        $this->controller->Validation->_deleteDir(CACHE_PFX . '11');
        @mkdir(CACHE_PFX . '11');
        
        $results = $this->controller->Validation->dictionary_general_verifyFileLayout($file);
        $this->assertEqual(count($results), 3, 'All three missing types should be flagged: %s');

        $expected = $this->controller->Validation->_result(TEST_FAIL, 0, '', 'The add-on was missing a required file: install.rdf');
        $this->assertEqual($results[0], $expected, 'Results are failures mentioning dictionary and the missing file: %s');
        
    }

    /**
     * Tests the dictionary_security_checkInstallJS() method
     */
    function testDictionary_security_checkInstallJS() {

        // Grab the test dictionary - should pass the tests
        $file = $this->controller->File->findById(11);
        
        @unlink(CACHE_PFX . '11/install.js');
        
        $results = $this->controller->Validation->dictionary_security_checkInstallJS($file);
        $this->assertEqual($results, array(), 'Missing install.js produces no results: %s');

        // install.js should pass when it only contains valid functions
        $valid = "initInstall();
           cancelInstall();
           getFolder();
           addDirectory();
           performInstall();";
        file_put_contents(CACHE_PFX . '11/install.js', $valid);
        
        $results = $this->controller->Validation->dictionary_security_checkInstallJS($file);
        $expected = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $expected, 'Five whitelist functions are allowed: %s');

        // Bad functions in install.js get flagged
        $badjs = "functionNotAllowed();";
        file_put_contents(CACHE_PFX . '11/install.js', $badjs);
        
        $results = $this->controller->Validation->dictionary_security_checkInstallJS($file);
        $expected = $this->controller->Validation->_resultWarn(1, 'install.js', 'Install.js contains a function missing from the whitelist: functionNotAllowed');
        $this->assertEqual($results, $expected, 'Bad functions are flagged: %s');
    }

    /**
     * Tests the dictionary_general_checkExtraFiles() method
     */
    function testDictionary_general_checkExtraFiles() {

        // Grab the test dictionary - should pass the tests
        $file = $this->controller->File->findById(11);
            
        $results = $this->controller->Validation->dictionary_general_checkExtraFiles($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample dictionary has no extra files: %s');

        // Use another addon type to break extra files
        $file = $this->controller->File->findById(3);
        
        $results = $this->controller->Validation->dictionary_general_checkExtraFiles($file);
        $this->assertEqual(count($results), 3, 'Both files should be detected: %s');

        $expected = $this->controller->Validation->_result(TEST_WARN, 0, 'chrome.manifest', 'The file chrome.manifest does not appear to belong in this add-on');
        $this->assertEqual($results[0], $expected, 'Results are warnings mentining the offending file: %s');

    }

    /**
     * Tests the dictionary_general_checkSeaMonkeyFiles() method
     */
    function testDictionary_general_checkSeaMonkeyFiles() {
        
        // Sample extension doesn't support seamonkey
        $file = $this->controller->File->findById(1);
        
        $results = $this->controller->Validation->dictionary_general_checkSeaMonkeyFiles($file);
        $this->assertEqual($results, array(), 'Add-ons that dont support SeaMonkey generate no results: %s');

        // Sample Dictionary supports seamonkey
        $file = $this->controller->File->findById(11);
        
        $results = $this->controller->Validation->dictionary_general_checkSeaMonkeyFiles($file);
        $expected = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $expected, 'Supported add-ons generate pass results: %s');

        // Verify test is skipped on unsupported version
        $old = file_get_contents(CACHE_PFX . '11/install.rdf');
        $bad = "<?xml version=\"1.0\"?>
<RDF xmlns=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"
    xmlns:em=\"http://www.mozilla.org/2004/em-rdf#\">
  <Description about=\"urn:mozilla:install-manifest\">
            <em:id>en-AU@dictionaries.addons.mozilla.org</em:id>
            <em:version>2.1.1</em:version>
  <em:targetApplication>
      <Description>
      <em:id>{92650c4d-4b8e-4d2a-b7eb-24ecf4f6b63a}</em:id>
      <em:minVersion>2.0a1pre</em:minVersion>
      <em:maxVersion>2.0a2</em:maxVersion>
      </Description>
  </em:targetApplication>
  <em:name>English (Australian) Dictionary</em:name>
  <em:description>I'm sick of all my favoUrite coloUrful language being marked incorrect.</em:description>
  <em:homepageURL>http://justcameron.com/incoming/en-au-dictionary/</em:homepageURL>
  </Description>
</RDF>";
        file_put_contents(CACHE_PFX . '11/install.rdf', $bad);
        $results = $this->controller->Validation->dictionary_general_checkSeaMonkeyFiles($file);
        $this->assertEqual($results, array(), 'Version 2.x does not require install.js: %s');
        file_put_contents(CACHE_PFX . '11/install.rdf', $old);

        // Verify fail on missing install.js
        @unlink(CACHE_PFX . '11/install.js');
        
        $results = $this->controller->Validation->dictionary_general_checkSeaMonkeyFiles($file);
        $expected = $this->controller->Validation->_resultFail(0, '', 'The add-on was missing a required file: install.js');
        $this->assertEqual($results, $expected, 'Supported add-ons fail if missing install.js: %s');
    }

    /**
     * Tests the extension_security_checkGeolocation() method
     */
    function testExtension_security_checkGeolocation() {
        
        // Sample extension should pass the tests
        $file = $this->controller->File->findById(1);
        
        $results = $this->controller->Validation->extension_security_checkGeolocation($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample extension does not use geolocation: %s');

        // Inject some geolocation and verfiy matches
        file_put_contents(CACHE_PFX . '1/bad.js', "geolocation\nwifi");
                
        $results = $this->controller->Validation->extension_security_checkGeolocation($file);
        $this->assertEqual(count($results), 2, 'Both flagged patterns should match: %s');

        $expected = $this->controller->Validation->_result(TEST_WARN, 1, 'bad.js', 'Matched Pattern: "/geolocation/"');
        $this->assertEqual($results[0], $expected, 'Results are warnings with appropriate file and line numbers: %s');
    }

    /**
     * Tests the extension_security_checkConduit() method
     */
    function testExtension_security_checkConduit() {
        
        // Sample extension is not a conduit toolbar
        $file = $this->controller->File->findById(1);
        
        $results = $this->controller->Validation->extension_security_checkConduit($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample extension is not a conduit toolbar: %s');

        // Make it look like one!
        $conduit = "<?xml version=\"1.0\"?>

<RDF xmlns=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"
    xmlns:em=\"http://www.mozilla.org/2004/em-rdf#\">
  <Description about=\"urn:mozilla:install-manifest\">
            <em:id>conduit@toolbar</em:id>
            <em:version>1.2.3.4</em:version>
            <em:updateURL>https://hosting.conduit.com</em:updateURL>
  </Description>
</RDF>";
        file_put_contents(CACHE_PFX . '1/install.rdf', $conduit);
        
        mkdir(CACHE_PFX . '1/searchplugin');
        mkdir(CACHE_PFX . '1/components');
        mkdir(CACHE_PFX . '1/defaults');
        touch(CACHE_PFX . '1/searchplugin/conduit');
        touch(CACHE_PFX . '1/components/conduit');
        touch(CACHE_PFX . '1/defaults/default_radio_skin.xml');
        touch(CACHE_PFX . '1/version.txt');

        // Poison contents.rdf as well
        $conduit = "chrome:displayName=\"Conduit Toolbar\"
                    chrome:author=\"Conduit Ltd.\"
                    chrome:authorURL=\"http://www.conduit.com\"
                    chrome:description=\"More than just a toolbar.\"";
        file_put_contents(CACHE_PFX . '1/contents.rdf', $conduit);

        // Finally, poison chrome.manifest
        file_put_contents(CACHE_PFX . '1/chrome.manifest', 'reference: ebtoolbarstyle.css');

        // Verify that we catch all the failures
        $results = $this->controller->Validation->extension_security_checkConduit($file);
        $this->assertEqual(count($results), 10, 'The tests should catch all 10 conditions: %s');

        $expected = $this->controller->Validation->_result(TEST_FAIL, 0, 'install.rdf', 'The add-on appears to be a conduit toolbar due to its updateURL element.');
        $this->assertEqual($results[0], $expected, 'Results are failures: %s');
        
    }

    /**
     * Tests the langpack_general_verifyFileLayout() method
     */
    function testLangpack_general_verifyFileLayout() {
        
        // Sample langpack should validate
        $file = $this->controller->File->findById(12);
        
        $results = $this->controller->Validation->langpack_general_verifyFileLayout($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample Language pack has the correct layout: %s');

        // Verify that contents.rdf works instead of chrome.manifest
        @unlink(CACHE_PFX . '12/chrome.manifest');
        touch(CACHE_PFX . '12/contents.rdf');

        $results = $this->controller->Validation->langpack_general_verifyFileLayout($file);
        $this->assertEqual($results, $pass, 'Contents.rdf is OK instead of chrome.manifest: %s');

        // Make sure all missing files are flagged
        $this->controller->Validation->_deleteDir(CACHE_PFX . '12');
        @mkdir(CACHE_PFX . '12');
        
        $results = $this->controller->Validation->langpack_general_verifyFileLayout($file);
        $this->assertEqual(count($results), 3, 'All three missing types should be flagged: %s');

        $expected = $this->controller->Validation->_result(TEST_FAIL, 0, '', 'The add-on was missing a required file: install.rdf');
        $this->assertEqual($results[0], $expected, 'Results are failures mentioning language pack and the missing file: %s');

    }

    /**
     * Tests the langpack_general_checkExtraFiles() method
     */
    function testLangpack_general_checkExtraFiles() {
        
        // Grab the test language pack - should pass the tests
        $file = $this->controller->File->findById(12);
            
        $results = $this->controller->Validation->langpack_general_checkExtraFiles($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample language pack has no extra files: %s');

        // Use another addon type to break extra files
        $file = $this->controller->File->findById(11);
        
        $results = $this->controller->Validation->langpack_general_checkExtraFiles($file);
        $this->assertEqual(count($results), 6, 'Both files should be detected: %s');

        $expected = $this->controller->Validation->_result(TEST_WARN, 0, 'changelog.txt', 'The file changelog.txt does not appear to belong in this add-on');
        $this->assertEqual($results[0], $expected, 'Results are warnings mentining the offending file: %s');

    }

    /**
     * Tests the langpack_security_filterUnsafeHTML() method
     */
    function testLangpack_security_filterUnsafeHTML() {

        // Grab the test language pack - should pass the tests
        $file = $this->controller->File->findById(12);
            
        $results = $this->controller->Validation->langpack_security_filterUnsafeHTML($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample language pack should pass all tests: %s');

        // Poison it with some bad HTML
        $badHTML = "<script>
<object>
<embed>";
        file_put_contents(CACHE_PFX . '12/bad.xhtml', $badHTML);
        
        $results = $this->controller->Validation->langpack_security_filterUnsafeHTML($file);
        $this->assertEqual(count($results), 3, 'All three flags should be returned: %s');
        
        $expected = $this->controller->Validation->_result(TEST_WARN, 1, 'bad.xhtml', 'Matched Pattern: "/<script/i"');
        $this->assertEqual($results[0], $expected, 'Results are warnings listing the matched pattern: %s');
        
    }

    /**
     * Tests the langpack_security_checkRemoteLoading() method
     */
    function testLangpack_security_checkRemoteLoading() {
        
        // Grab the test language pack - should pass the tests
        $file = $this->controller->File->findById(12);
            
        $results = $this->controller->Validation->langpack_security_checkRemoteLoading($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample language pack should pass all tests: %s');

        // Stick some bad data in there 
        $badHTML = "<a href=\"non-chrome!\">
<img src='also-not-chrome'/>";
        file_put_contents(CACHE_PFX . '12/bad.xhtml', $badHTML);
        
        $results = $this->controller->Validation->langpack_security_checkRemoteLoading($file);
        $this->assertEqual(count($results), 2, 'Should catch both errors: %s');

        $expected = $this->controller->Validation->_result(TEST_WARN, 1, 'bad.xhtml', 'Matched Pattern: "/(href|src)=["\'](?!chrome:\/\/)/i"');
        $this->assertEqual($results[0], $expected, 'Results are warnings listing the relevant file and line number: %s');
        
    }

    /**
     * Tests the langpack_security_checkChromeManifest() method
     */
    function testLangpack_security_checkChromeManifest() {
        
        // Grab the test language pack - should pass the tests
        $file = $this->controller->File->findById(12);
            
        $results = $this->controller->Validation->langpack_security_checkChromeManifest($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample language pack should pass all tests: %s');

        // mess up the chrome.manifest
        $data = "# comments are OK
locale so is locale
override chrome://so-is/locale/this
not so much here!
override chrome://bad-url";
        file_put_contents(CACHE_PFX . '12/chrome.manifest', $data);

        $results = $this->controller->Validation->langpack_security_checkChromeManifest($file);
        $this->assertEqual(count($results), 2, 'Found both bad lines: %s');

        $expected = $this->controller->Validation->_result(TEST_WARN, 4, 'chrome.manifest', 'Matched Pattern: "/^(?!(#|locale |override(\s+chrome:\/\/.*\/locale\/[^\s]*)+))/"');
        $this->assertEqual($results[0], $expected, 'Results are warnings with appropriate line and file: %s');
    }


    /**
     * Tests the search_general_checkFormat() method
     */
    function testSearch_general_checkFormat() {
        
        // Create a sample search engine to pass
        $file = $this->controller->File->findById(4);
        $file['Version']['addon_id'] = 'temp';
        $file['File']['filename'] = 'test-search';
        $data = "<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearchdescription/1.1/\">
             <ShortName>OpenSearch Test</ShortName>
             <Alias>OST</Alias>
             <Description>This is a test plugin.</Description>
             <InputEncoding>UTF-8</InputEncoding>
             <Image width=\"16\" height=\"16\">data:image/x-icon;base64,</Image>
             <Url type=\"text/html\" method=\"get\" template=\"http://test.template.url/search/\">
               <Param name=\"q\" value=\"{searchTerms}\"/>
               <Param name=\"sourceid\" value=\"firefox\"/>
             </Url>
             </OpenSearchDescription>";
        file_put_contents(REPO_PATH . '/temp/test-search', $data);
        
        $results = $this->controller->Validation->search_general_checkFormat($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample search engine should validate: %s');

        // Use another addon to test for failure
        $file = $this->controller->File->findById(1);
        
        $results = $this->controller->Validation->search_general_checkFormat($file);
        $expected = $this->controller->Validation->_resultFail(0, '', 'The search engine could not be parsed according to the OpenSearch format.');
        $this->assertEqual($results, $expected, 'Results are failures if the search engine cannot be parsed: %s');

        unlink(REPO_PATH . '/temp/test-search');
    }

    /**
     * Tests the search_security_checkUpdateURL() method
     */
    function testSearch_security_checkUpdateURL() {

        // Sample search engine should pass
        $file = $this->controller->File->findById(4);
        $file['Version']['addon_id'] = 'temp';
        $file['File']['filename'] = 'test-search';
        $data = "<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearchdescription/1.1/\">
             <ShortName>OpenSearch Test</ShortName>
             <Alias>OST</Alias>
             <Description>This is a test plugin.</Description>
             <InputEncoding>UTF-8</InputEncoding>
             <Image width=\"16\" height=\"16\">data:image/x-icon;base64,</Image>
             <Url type=\"text/html\" method=\"get\" template=\"http://test.template.url/search/\">
               <Param name=\"q\" value=\"{searchTerms}\"/>
               <Param name=\"sourceid\" value=\"firefox\"/>
             </Url>
             </OpenSearchDescription>";
        file_put_contents(REPO_PATH . '/temp/test-search', $data);        
        
        $results = $this->controller->Validation->search_security_checkUpdateURL($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample search engine should not have an updateURL: %s');

        // Create a bad UpdateURL element
        $data = "<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearchdescription/1.1/\">
             <ShortName>OpenSearch Test</ShortName>
             <Alias>OST</Alias>
             <Description>This is a test plugin.</Description>
             <InputEncoding>UTF-8</InputEncoding>
             <Image width=\"16\" height=\"16\">data:image/x-icon;base64,</Image>
             <Url type=\"text/html\" method=\"get\" template=\"http://test.template.url/search/\">
               <Param name=\"q\" value=\"{searchTerms}\"/>
               <Param name=\"sourceid\" value=\"firefox\"/>
             </Url>
             <UpdateUrl>http://fake.update.url/</UpdateUrl>
             <UpdateInterval>7</UpdateInterval>
             <IconUpdateUrl>http://fake.icon.update.url/</IconUpdateUrl>
             </OpenSearchDescription>";
        file_put_contents(REPO_PATH . '/temp/test-search', $data);
        
        $results = $this->controller->Validation->search_security_checkUpdateURL($file);
        $expected = $this->controller->Validation->_resultFail(0, '', 'The search engine contains an updateURL element, which is not allowed.');
        $this->assertEqual($results, $expected, 'Results are failures if the search engine contains an updateURL: %s');

        unlink(REPO_PATH . '/temp/test-search');
    }

    /**
     * Tests the theme_general_verifyFileLayout() method
     */
    function testTheme_general_verifyFileLayout() {

        // Sample langpack should validate
        $file = $this->controller->File->findById(13);
        
        $results = $this->controller->Validation->theme_general_verifyFileLayout($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample theme has the correct layout: %s');

        // Make sure all missing files are flagged
        $this->controller->Validation->_deleteDir(CACHE_PFX . '13');
        @mkdir(CACHE_PFX . '13');
        
        $results = $this->controller->Validation->theme_general_verifyFileLayout($file);
        $this->assertEqual(count($results), 2, 'All two missing types should be flagged: %s');

        $expected = $this->controller->Validation->_result(TEST_FAIL, 0, '', 'The add-on was missing a required file: install.rdf');
        $this->assertEqual($results[0], $expected, 'Results are failures mentioning language pack and the missing file: %s');

    }

    /**
     * Tests the theme_security_checkChromeManifest() method
     */
    function testTheme_security_checkChromeManifest() {
       
        // Grab the test language pack - should pass the tests
        $file = $this->controller->File->findById(13);
            
        $results = $this->controller->Validation->theme_security_checkChromeManifest($file);
        $pass = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $pass, 'Sample theme should pass all tests: %s');

        // mess up the chrome.manifest
        $data = "# comments are OK
skin is ok
style is also
not so much here!";
        file_put_contents(CACHE_PFX . '13/chrome.manifest', $data);

        $results = $this->controller->Validation->theme_security_checkChromeManifest($file);
        $expected = $this->controller->Validation->_resultWarn(4, 'chrome.manifest', 'Matched Pattern: "/^(?!(#|skin |style ))/"');
        $this->assertEqual($results, $expected, 'Finds the bad line and generates a warning: %s');
        
    }

    /** 
     * Tests the getResultPreview() method
     */
    function testGetResultPreview() {

        // Load in the data we need
        $this->controller->Validation->runTest(1, 1);
        $file = $this->controller->File->findById(1);
        $result = $this->controller->TestResult->find(array('File.id' => 1));
        
        // Basic add-on should not generate a preview
        $this->controller->Validation->getResultPreview($result, $file);
        $this->assertFalse(array_key_exists('preview', $result['TestResult']), 'No file or line generates no preview: %s');
        
        // Give this some data to generate problems
        file_put_contents(CACHE_PFX . '1/bad.js', "\nvar bad;\n");

        // Clear any old results
        $this->controller->TestResult->deleteOldResults(1, 1);

        $this->controller->Validation->runTest(1, 1);
        $file = $this->controller->File->findById(1);
        $result = $this->controller->TestResult->find(array('File.id' => 1, 'TestResult.result' => TEST_WARN));

        // Failed test should generate a preview
        $this->controller->Validation->getResultPreview($result, $file);        
        $this->assertTrue(array_key_exists('preview', $result['TestResult']), 'Previews are generated on failed tests: %s');
        $this->assertEqual(count($result['TestResult']['preview']), 3, 'Previews provide context around data: %s');
    }

    /**
     * Tests the _checkExtraFiles() method
     */
    function test_checkExtraFiles() {
        
        // Some basic checks
        $file = $this->controller->File->findById(1);
        $results = $this->controller->Validation->_checkExtraFiles($file, array('/install\.rdf/'));
        $expected = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $expected, 'Default addon contains install.rdf: %s');
        
        $results = $this->controller->Validation->_checkExtraFiles($file, array());
        $expected = $this->controller->Validation->_resultWarn(0, 'install.rdf', 'The file install.rdf does not appear to belong in this add-on');
        $this->assertEqual($results, $expected, 'Empty whitelist returns every file: %s');
        
        // More advanced check for hunting add-on
        $file = $this->controller->File->findById(3);
        $results = $this->controller->Validation->_checkExtraFiles($file, array('/\.rdf$/', '/\.js$/'));
        $this->assertEqual(count($results), 3, 'Checking multiple items in whitelist: %s');

    }

    /**
     * Tests the _verifyFilesExist() method
     */
    function test_verifyFilesExist() {
        
        // Basic checks with empty/default parameters
        $file = $this->controller->File->findById(1);
        $results = $this->controller->Validation->_verifyFilesExist($file, array(), 'by_name', 'Extension');
        $this->assertEqual($results, array(), 'No names returns an empty result: %s');

        // Test by_name and by_preg
        $results = $this->controller->Validation->_verifyFilesExist($file, array('install.rdf'), 'by_name', 'Extension');
        $this->assertEqual($results, array(), 'Default add-on should contain install.rdf: %s');
        
        $results = $this->controller->Validation->_verifyFilesExist($file, array('/\.rdf$/i'), 'by_preg', 'Extension');
        $this->assertEqual($results, array(), 'Extracting by regex finds install.rdf: %s');

        // Verify failures on missing files
        $results = $this->controller->Validation->_verifyFilesExist($file, array('doesnt-exist'), 'by_name', 'Extension');
        $expected = $this->controller->Validation->_result(TEST_FAIL, 0, '', 'The add-on was missing a required file: doesnt-exist');
        $this->assertEqual($results[0], $expected, 'Results are failures mentioning the missing file: %s');

    }

    /**
     * Tests the _grepExtractedFiles() method
     */
    function test_grepExtractedFiles() {

        // Test some bad data to ensure no errors
        $results = $this->controller->Validation->_grepExtractedFiles(array(), array('/foo/'));
        $expected = $this->controller->Validation->_resultPass();
        $this->assertEqual($results, $expected, 'Empty files array returns pass result: %s');

        $file = $this->controller->File->findById(1);
        $extracted = $this->controller->Validation->_extract($file, 'by_preg', '//');
        $results = $this->controller->Validation->_grepExtractedFiles($extracted, array());
        $this->assertEqual($results, $expected, 'Empty patterns array returns pass result: %s');

        // Do some basic greps on the default add-on
        $results = $this->controller->Validation->_grepExtractedFiles($extracted, array('/MicroFarmer/'));
        $expected = $this->controller->Validation->_resultWarn(10, 'install.rdf', 'Matched Pattern: "/MicroFarmer/"');
        $this->assertEqual($results, $expected, 'Greps return warnings with the matched pattern: %s');

        // Make sure multiple patterns work
        $results = $this->controller->Validation->_grepExtractedFiles($extracted, array('/Firefox/', '/Thunderbird/'));
        $this->assertEqual(count($results), 2, 'There should be two results for the default extension: %s');
                
        $expected = $this->controller->Validation->_result(TEST_WARN, 15, 'install.rdf', 'Matched Pattern: "/Firefox/"');
        $this->assertEqual($results[0], $expected, 'The results are warnings with appropriate file and line: %s');

        // Test overriding parameters
        $results = $this->controller->Validation->_grepExtractedFiles($extracted, array('/Extension/'), TEST_PASS, 'Foo bar');
        $expected = $this->controller->Validation->_result(TEST_PASS, 10, 'install.rdf', 'Foo bar');
        $this->assertEqual($results[0], $expected, 'Overrding parameters: %s');
        
    }

    /**
     * Tests the _extract() method
     */
    function test_extract() {
        
        $file = $this->controller->File->findById(1);

        // Extract the default data
        $files = $this->controller->Validation->_extract($file, 'by_preg', '//');
        $this->assertEqual(count($files), 1, 'Default addon should extract 1 file: %s');
        
        // Verify caching is appening
        $this->assertTrue(file_exists(NETAPP_STORAGE . '/validate-1'), 'Verify caching (return true)');

        // Do some advanced regex extractions
        $file = $this->controller->File->findById(3);
        $files = $this->controller->Validation->_extract($file, 'by_preg', '/\.js$/i');
        
        $this->assertEqual(count($files), 4, 'Default add-on contains 4 JS files: %s');
        
        // Test bad extraction
        $files = $this->controller->Validation->_extract($file, 'by_name', 'this-doesnt-exist');
        $this->assertEqual($files, array(), 'Missing extractions should generate empty result set: %s');

    }

    /**
     * Tests the _findFiles() method
     */
    function test_findFiles() {

        // Get some test data
        @mkdir(NETAPP_STORAGE . '/foo');
        @mkdir(NETAPP_STORAGE . '/foo/bar');
        @touch(NETAPP_STORAGE . '/foo/bar/baz');
        @touch(NETAPP_STORAGE . '/foo/bing');
        @touch(NETAPP_STORAGE . '/foo/bing2');

        // Basic find
        $results = $this->controller->Validation->_findFiles(NETAPP_STORAGE . '/foo', '', array('/bing/'));
        $this->assertEqual($results, array('bing', 'bing2'), 'Finding files in the root folder: %s');

        // Find in directories
        $results = $this->controller->Validation->_findFiles(NETAPP_STORAGE . '/foo', '', array('/baz/'));
        $this->assertEqual($results, array('bar/baz'), 'Finding files in directories: %s');

        // Finding directory names
        $results = $this->controller->Validation->_findFiles(NETAPP_STORAGE . '/foo', '', array('/bar/'));
        $this->assertEqual($results, array('bar/baz'), 'Finding directory names: %s');
    }

    /**
     * Tests the _deleteDir() method
     */
    function test_deleteDir() {
        
        @mkdir(NETAPP_STORAGE . '/foo');
        @mkdir(NETAPP_STORAGE . '/foo/bar');
        @touch(NETAPP_STORAGE . '/foo/bar/baz');
        @touch(NETAPP_STORAGE . '/foo/bing');
        @touch(NETAPP_STORAGE . '/foo/bing2');

        $this->assertTrue($this->controller->Validation->_deleteDir(NETAPP_STORAGE . '/foo'));
        $this->assertFalse(file_exists(NETAPP_STORAGE . '/foo'));
    }

    /**
     * Tests the _passIfEmpty() method
     */
    function test_passIfEmpty() {

        // Array data is preserved
        $arr = array('Not', 'Empty');
        $this->assertEqual($this->controller->Validation->_passIfEmpty($arr), $arr, 'Non-empty array is unmodified: %s');
        
        // Empty array gives a pass result
        $expected = $this->controller->Validation->_resultPass();
        $this->assertEqual($this->controller->Validation->_passIfEmpty(array()), $expected, 'Empty array returns a pass result: %s');
        

    }

    /**
     * Tests the _result() method
     */
    function test_result() {

        $expected = array(
            'result' => TEST_PASS,
            'line' => 3456,
            'filename' => 'foo',
            'message' => 'bar'
        );
        $this->assertEqual($this->controller->Validation->_result(TEST_PASS, 3456, 'foo', 'bar'), $expected, 'Single result is a matching array: %s');

    }

    /**
     * Tests the _resultFail() method
     */
    function test_resultFail() {
        
        $expected = array(
            array(
                'result' => TEST_FAIL,
                'line' => 1234,
                'filename' => 'foo',
                'message' => 'bar'
            )
        );
        $this->assertEqual($this->controller->Validation->_resultFail(1234, 'foo', 'bar'), $expected, 'Fail result is a matching array: %s');

    }

    /**
     * Tests the _resultWarn() method
     */
    function test_resultWarn() {

        $expected = array(
            array(
                'result' => TEST_WARN,
                'line' => 5678,
                'filename' => 'foo',
                'message' => 'bar'
            )
        );
        $this->assertEqual($this->controller->Validation->_resultWarn(5678, 'foo', 'bar'), $expected, 'Warn result is a matching array: %s');

    }

    /**
     * Tests the _resultPass() method
     */
    function test_resultPass() {

        $expected = array(
            array(
                'result' => TEST_PASS,
                'line' => 0,
                'filename' => '',
                'message' => ''
            )
        );
        $this->assertEqual($this->controller->Validation->_resultPass(), $expected, 'Pass result is a matching array: %s');

    }   

}
?>
