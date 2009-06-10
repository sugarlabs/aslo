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
 * The Initial Developer of the Original Code is The Mozilla Foundation.
 *
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *      Mike Morgan <morgamic@mozilla.com> (Original Author)
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

/**
 * This class tests the update service.  The purpose of the update service
 * is to notify clients when an add-on has an available update.
 *
 * Updates are delivered as client-read RDF documents.  This class mainly
 * tests the RDF output for validity and accuracy based on corresponding
 * database contents.
 *
 * An example URI (wrapped for readability):
 *      https://addons.mozilla.org/update/VersionCheck.php?
 *          reqVersion=1&
 *          id={19503e42-ca3c-4c27-b1e2-9cdb2170ee34}&
 *          version=1.0&
 *          maxAppVersion=2.0&
 *          status={on}&
 *          appID={ec8030f7-c20a-464f-9b0e-13a3a9e97384}&
 *          appVersion=2.0&
 *          appOS=Linux&
 *          appABI=1
 */

// Require update function file.
require_once(APP.'webroot/services/functions.php');

class UpdateServiceTest extends WebTestHelper {
	
    var $_args;
    var $_noUpdatesXml;

    function UpdateServiceTest() {
        $this->WebTestCase('Services->Update');
        loadModel('Addon');
        loadModel('Version');
    }

    /**
     * Sets up default vars and required modules.
     */
	function setUp() {

        // Load RDF component.
        loadComponent('Rdf');

        $this->_args = array(
            'reqVersion'=>'1',
            'id'=>'{11111111-1111-1111-1111-111111111111}',
            'version'=>'1.0',
            'maxAppVersion'=>'1.0',
            'status'=>'on',
            'appID'=>'{11111111-1111-1111-1111-111111111111}',
            'appVersion'=>'1.0',
            'appOS'=>'Linux',
            'appABI'=>'1',
            'test'=>'1'
        );

        $this->_noUpdatesXml=<<<NoUpdateXml
<?xml version="1.0"?>
<RDF:RDF xmlns:RDF="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:em="http://www.mozilla.org/2004/em-rdf#"></RDF:RDF>
NoUpdateXml;
        
        $this->id = 7;
        $this->model =& new Addon();
        $this->versionModel =& new Version();
        $this->data = $this->model->find("Addon.id=$this->id", null , null , 2);
        $this->data['Version'] = $this->versionModel->findAll("Version.addon_id=$this->id", null, "Version.created DESC", 0);
	}

    /**
     * Retrieve XML document based on _args.
     * @param array $args update URI arguments
     * @return string resulting XML document as a string
     */
    function _getXml($args=array()) {
        // Use the test db.
        $args['test'] = 1;
        return $this->get(SERVICE_URL.'/update.php',$args);
    }

    /**
     * Returns a boolean saying whether or not the XML passed was parsable.
     * @param string $xml an XML document
     * @return boolean true if parsable, false if not
     */
    function _isParsable($xml) {
        $rdf = new Rdf_parser();
        $rdf->rdf_parser_create(null);
        $rdf->rdf_set_user_data($data);
        $rdf->rdf_set_statement_handler(array('RdfComponent', 'mfStatementHandler'));
        $rdf->rdf_set_base("");

        return $rdf->rdf_parse($xml, strlen($xml), true);
    }

    /**
     * No update exists for maligned values.  Assert in each of
     * these cases that the resulting RDF is a "no updates" RDF.
     *
     * The "no updates" RDF should be parsable.
     */
    function testNoUpdateForBadInputs() {

        foreach ($this->_args as $key=>$val) {

            // Store args in a temp array so we can fudge it up.
            $_tmp = $this->_args;

            // We want to mess up a value on purpose.
            $_tmp[$key] = '/?:LIKJPO(&O(*&_)(*_)(!*@#K';

            // Get the XML for a malformed URI.
            $_xml = $this->_getXml($_tmp);

            // The resulting document should be parsable.
            $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when '.$key.'='.$_tmp[$key]);

            // The resulting document should be equal to our no updates xml.
            $this->assertEqual($this->_noUpdatesXml, $_xml, 'No update was offered.');
        }
    }

    /**
     * No update exists for missing values.  Assert in each of
     * these cases that the resulting RDF is a "no updates" RDF.
     *
     * The "no updates" RDF should be parsable.
     */
    function testNoUpdateForMissingInputs() {

        foreach ($this->_args as $key=>$val) {

            // Store args in a temp array so we can fudge it up.
            $_tmp = $this->_args;

            // We want to mess up a value on purpose.
            $_tmp[$key] = '';

            // Get the XML for a malformed URI.
            $_xml = $this->_getXml($_tmp);

            // The resulting document should be parsable.
            $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when '.$key.' is empty.');

            // The resulting document should be equal to our no updates xml.
            $this->assertEqual($this->_noUpdatesXml, $_xml, 'No update was offered.');
        }
    }

    /**
     * There should be no update for a platform that is not compatible.
     */
    function testNoUpdateForIncompatiblePlatform() {

        // Store args in a temp array so we can fudge it up.
        $_tmp = $this->_args;

        // We want to mess up a value on purpose.
        $_tmp['appOS'] = 'OS/2 Warp';

        // Get the XML for a malformed URI.
        $_xml = $this->_getXml($_tmp);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when appOS is '.$_tmp['appOS'].'.');

        // The resulting document should be equal to our no updates xml.
        $this->assertEqual($this->_noUpdatesXml, $_xml, 'No update was offered.');
    }

    /**
     * No update for an incompatible application version.
     */
    function testNoUpdateForIncompatibleApplicationVersion() {

        // Store args in a temp array so we can fudge it up.
        $_tmp = $this->_args;

        // We want to mess up a value on purpose.
        $_tmp['appVersion'] = '1.1';

        // Get the XML for a malformed URI.
        $_xml = $this->_getXml($_tmp);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when appVersion is '.$_tmp['appVersion'].'.');

        // The resulting document should be equal to our no updates xml.
        $this->assertEqual($this->_noUpdatesXml, $_xml, 'No update was offered.');
    }

    /**
     * Test to see that an update exists for an arbitrarily old version (v0.0).
     *
     * An update should be offered if the requesting client's version is older
     * or equal to the newest version in the database.
     */
    function testUpdateExistsForOldOrEqualVersion() {

        $this->_args = array(
            'reqVersion'=>'1',
            'id'=>'farming@microfarmer.org',
            'version'=>'0.1',
            'maxAppVersion'=>'1.0',
            'status'=>'on',
            'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
            'appVersion'=>'1.5',
            'appOS'=>'WINNT',
            'appABI'=>'1');

        // Get the XML for the default arguments.
        $_xml = $this->_getXml($this->_args);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when appVersion is '.$this->_args['appVersion'].'.');

        // The resulting document should give us an update.  We should test to see if the update was delivered.
        // To do this, we will test elements that should be in the resulting data.
        $this->assertPattern("/.*{$this->data['Addon']['guid']}.*/", "GUID {$this->data['Addon']['guid']} found in update XML.");

        $this->assertPattern("/.*{$this->data['Version'][0]['Version']['version']}.*/", "Latest version ({$this->data['Version'][0]['Version']['version']}) found in update XML.");

        $_fileUrl = FILES_HOST . '/' . $this->data['Addon']['id'] . '/' . $this->data['Version'][0]['File'][0]['filename'];
        UnitTestCase::assertPattern('/.*'.preg_quote($_fileUrl, '/').'.*/', $_xml, "File URL {$_fileUrl} found in update XML.");

        $wantedPattern = '#{ec8030f7-c20a-464f-9b0e-13a3a9e97384}#';
        $this->assertPattern($wantedPattern, 'Application GUID found in update XML.');
    }

    /**
     * Tests to make sure that updates are still available for a
     * platform-specific add-on.
     *
     * Our test case in this case is an add-on only available for Solaris.
     *
     * I also fudged the appOS to verify that it works for a fuzzy match not
     * exact match.
     */
    function testUpdateExistsForPlatformSpecificAddon() {

        $this->_args = array(
            'reqVersion'=>'1',
            'id'=>'hunter@farmerland.org',
            'version'=>'0.1',
            'maxAppVersion'=>'1.0',
            'status'=>'on',
            'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
            'appVersion'=>'1.5',
            'appOS'=>'SunOs',
            'appABI'=>'1');

        // Get custom add-on data.
        $buf = $this->model->find("Addon.id=9", null , null , 2);
        $buf['Version'] = $this->versionModel->findAll("Version.addon_id=9", null, "Version.created DESC", 0);

        // Get the XML for the default arguments.
        $_xml = $this->_getXml($this->_args);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when appVersion is '.$this->_args['appVersion'].'.');

        // The resulting document should give us an update.  We should test to see if the update was delivered.
        // To do this, we will test elements that should be in the resulting data.
        $this->assertPattern("/.*{$buf['Addon']['guid']}.*/", "GUID {$buf['Addon']['guid']} found in update XML.");

        $this->assertPattern("/.*{$buf['Version'][0]['Version']['version']}.*/", "Latest version ({$buf['Version'][0]['Version']['version']}) found in update XML.");

        $_fileUrl = FILES_HOST . '/' . $buf['Addon']['id'] . '/' . $buf['Version'][0]['File'][0]['filename'];
        UnitTestCase::assertPattern('/.*'.preg_quote($_fileUrl, '/').'.*/', $_xml, "File URL {$_fileUrl} found in update XML.");

        $wantedPattern = '#{ec8030f7-c20a-464f-9b0e-13a3a9e97384}#';
        $this->assertPattern($wantedPattern, 'Application GUID found in update XML.');
    }



    /**
     * Test that we don't get an update for a sandbox version not in the DB
     */
    function testNoUpdateForSandbox() {
        $this->_args = array(
            'reqVersion'=>'1',
            'id'=>'{A17C1C5A-04C1-11DB-9805-B632A1EF5496}',
            'version'=>'0.1',
            'maxAppVersion'=>'1.0',
            'status'=>'userEnabled',
            'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
            'appVersion'=>'1.5',
            'appOS'=>'WINNT',
            'appABI'=>'x86-msvc');

        // Get the XML for the default arguments.
        $_xml = $this->_getXml($this->_args);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable for sandbox add-on '.$this->_args['id'].'.');

        // The resulting document should be equal to our no updates xml.
        $this->assertEqual($this->_noUpdatesXml, $_xml, 'No update was offered for sandbox add-on.');
    }

    /**
     * Test that we get version compat info for a sandbox version in the DB
     */
    function testCompatInfoForSandbox() {
        $this->_args = array(
            'reqVersion'=>'1',
            'id'=>'{A17C1C5A-04C1-11DB-9805-B632A1EF5496}',
            'version'=>'1.0',
            'maxAppVersion'=>'1.0',
            'status'=>'userEnabled',
            'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
            'appVersion'=>'1.5',
            'appOS'=>'WINNT',
            'appABI'=>'x86-msvc');

        // Get the XML for the default arguments.
        $_xml = $this->_getXml($this->_args);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable for sandbox add-on '.$this->_args['id'].'.');

        // Make sure result has same version
        $this->assertPattern("/.*<em:version>1\.0<\/em:version>.*/", 'Version 1.0 returned compat info');

        // Make sure result has correct minVersion
        $this->assertPattern("/.*<em:minVersion>1.5<\/em:minVersion>.*/", 'minVersion returned 1.5');

        // Make sure result has correct maxVersion
        $this->assertPattern("/.*<em:maxVersion>3\.0a1<\/em:maxVersion>.*/", 'maxVersion returned 3.0a1');
    }

    /**
     * Test that we don't get a file that is in the sandbox in the update for a public add-on.
     */
    function testNoSandboxFileForPublicAddon() {
        $this->data = $this->model->find("Addon.id=4023", null , null , 2);
        $this->data['Version'] = $this->versionModel->findAll("Version.addon_id=4023", null, "Version.created DESC", 0);

        // Get the XML for the default arguments.
        $_xml = $this->_getXml($this->_args);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable for sandbox add-on '.$this->_args['id'].'.');

        // Assert that the sandbox file is not present.
        $_fileUrl = FILES_HOST . '/' . FILES_URL . '/' . $this->data['Version'][0]['File'][0]['id'];
        $this->assertNoPattern('/.*'.preg_quote($_fileUrl, '/').'.*/', "Sandbox file {$_fileUrl} not found in update XML.");
    }

    /**
     * Test that no upate is offered for an inactive add-on.
     */
    function xfail_testNoUpdateForInactiveAddon() {
        // Replace this with ID of an inactive addon.
        // There currently isn't SQL in there for it, but will add it post release.
        $this->data = $this->model->find("Addon.id=4023", null , null , 2);
        $this->data['Version'] = $this->versionModel->findAll("Version.addon_id=4023", null, "Version.created DESC", 0);

        // Get the XML for the default arguments.
        $_xml = $this->_getXml($this->_args);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable for inactive add-on '.$this->_args['id'].'.');

        // The resulting document should be equal to our no updates xml.
        $this->assertEqual($this->_noUpdatesXml, $_xml, 'No update was offered for an inactive add-on.');
    }

    /**
     * Test the results of get_os_id() for common platforms.
     */
    function testPlatforms() {
        $this->assertEqual(get_os_id('Linux'),PLATFORM_LINUX,'Linux detection works.');
        $this->assertEqual(get_os_id('FreeBSD'),PLATFORM_BSD,'BSD detection works.');
        $this->assertEqual(get_os_id('Darwin'),PLATFORM_MAC,'OS X detection works (Darwin).');
        $this->assertEqual(get_os_id('WINNT'),PLATFORM_WIN,'Windows detection works.');
        $this->assertEqual(get_os_id('SunOs'),PLATFORM_SUN,'Solaris detection works.');
    }
}
?>
