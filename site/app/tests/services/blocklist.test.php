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
 * This class tests the blocklist service.  The purpose of the blocklist service
 * is to tell clients which add-ons are forbidden.
 *
 * The blocklist delivered as client-read RDF documents.  This class mainly
 * tests the RDF output for validity and accuracy based on corresponding
 * database contents.
 *
 * An example URI (wrapped for readability):
 *      https://addons.mozilla.org/blocklist.php?
 *          reqVersion=1&
 *          appGuid={ec8030f7-c20a-464f-9b0e-13a3a9e97384}&
 *          appVersion=2.0
 */

class BlocklistServiceTest extends UnitTestCase {
	
    var $_args;
    var $_data;
    var $_pluginData;

    function BlocklistServiceTest() {
        $this->UnitTestCase('Services->Blocklist');
        loadModel('Blitem');
        loadModel('Blapp');
        loadModel('Blplugin');
    }

    /**
     * Sets up default vars and required modules.
     */
	function setUp() {

        // Load RDF component.
        loadComponent('Rdf');

        $this->_args = array(
            'reqVersion'=>'1',
            'appGuid'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
            'appVersion'=>'1.5'
        );

        $model =& new Blitem();
        $this->_data = $model->findAll();

        $pl =& new Blplugin();
        $this->_pluginData = $pl->findAll();
	}

    /**
     * Build a blocklist URI based on _args.
     * @param array $args URI arguments
     * @return string resulting URI
     */
    function _buildUri($args=array()) {
        $_buf = array();
        foreach ($args as $key=>$val) {
            $_buf[] = $key.'='.$val;
        }
        return SERVICE_URL.'/blocklist.php?test=1&'.implode('&',$_buf);
    }

    /**
     * Retrieve XML document based on _args.
     * @param array $args URI arguments
     * @return string resulting XML document as a string
     */
    function _getXml($args=array()) {
        return trim(file_get_contents($this->_buildUri($args)));
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
     * Make sure there are no PHP errors.
     */
	function testNoErrors() {
		$this->assertNoErrors();
	}

    /**
     * No blocklist exists for maligned values.  Assert in each of
     * these cases that the resulting RDF is parsable.
     */
    function testNoBlocklistForBadInputs() {

        foreach ($this->_args as $key=>$val) {

            // Only tests reqVersion and appGuid because appVersion is not required.
            if ($key != 'appVersion') {

                // Store args in a temp array so we can fudge it up.
                $_tmp = $this->_args;

                // We want to mess up a value on purpose.
                $_tmp[$key] = '/?:LIKJPO(&O(*&_)(*_)(!*@#K';

                // Get the XML for a malformed URI.
                $_xml = $this->_getXml($_tmp);

                // The resulting document should be parsable.
                $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when '.$key.'='.$_tmp[$key]);

                // The resulting document should not contain blocklist items.
                $this->assertNoPattern('/.*emItems.*/', $_xml, 'No blocklist items were found.');
            }
        }
    }

    /**
     * No blocklist exists for missing values.  Assert in each of
     * these cases that the resulting RDF is parsable.
     */
    function testNoBlocklistForMissingInputs() {

        foreach ($this->_args as $key=>$val) {

            // Store args in a temp array so we can fudge it up.
            $_tmp = $this->_args;

            // We want to mess up a value on purpose.
            $_tmp[$key] = '';

            // Get the XML for a malformed URI.
            $_xml = $this->_getXml($_tmp);

            // The resulting document should be parsable.
            $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when '.$key.' is empty.');

            // The resulting document should not contain blocklist items.
            $this->assertNoPattern('/.*emItems.*/', $_xml, 'No blocklist items were found.');
        }
    }

    /**
     * Test that our expected test results are coming back.
     */
    function testBlocklistItemsExist() {

        // Get our test case XML.
        $_xml = $this->_getXml($this->_args); 

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document is parsable when blocklist items exist.');

        // Next, test
        $pattern = preg_quote('    <emItem id="'.$this->_data[0]['Blitem']['guid'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, $this->_data[0]['Blitem']['guid'] . ' found in blocklist XML.');

        $pattern = preg_quote('    <emItem id="'.$this->_data[2]['Blitem']['guid'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, $this->_data[2]['Blitem']['guid'] . ' found in blocklist XML.');

        $pattern = preg_quote('    <emItem id="'.$this->_data[3]['Blitem']['guid'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, $this->_data[3]['Blitem']['guid'] . ' found in blocklist XML.');

        $pattern = preg_quote('    <emItem id="'.$this->_data[4]['Blitem']['guid'].'" os="'.$this->_data[4]['Blitem']['os'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, $this->_data[4]['Blitem']['guid'] . ' found in blocklist XML.');

        $pattern = preg_quote('    <emItem id="'.$this->_data[5]['Blitem']['guid'].'"/>','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, $this->_data[5]['Blitem']['guid'] . ' found in blocklist XML.');

        $pattern = preg_quote('      <versionRange minVersion="'.$this->_data[0]['Blitem']['min'].'" maxVersion="'.$this->_data[0]['Blitem']['max'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'minVersion="'.$this->_data[0]['Blitem']['min'].'" maxVersion="'.$this->_data[0]['Blitem']['max'].'" found in blocklist XML.');

        $pattern = preg_quote('        <targetApplication id="'.$this->_data[0]['Blapp'][0]['guid'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'id="'.$this->_data[0]['Blapp'][0]['guid'].'"found in blocklist XML.');

        $pattern = preg_quote('           <versionRange minVersion="'.$this->_data[0]['Blapp'][0]['min'].'" maxVersion="'.$this->_data[0]['Blapp'][0]['max'].'"/>','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'minVersion="'.$this->_data[0]['Blapp'][0]['min'].'" maxVersion="'.$this->_data[0]['Blapp'][0]['max'].'" found in blocklist XML.');

        $pattern = preg_quote('      <versionRange minVersion="'.$this->_data[1]['Blitem']['min'].'" maxVersion="'.$this->_data[1]['Blitem']['max'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'minVersion="'.$this->_data[1]['Blitem']['min'].'" maxVersion="'.$this->_data[1]['Blitem']['max'].'" found in blocklist XML.');

        $pattern = preg_quote('      <versionRange minVersion="'.$this->_data[2]['Blitem']['min'].'" maxVersion="'.$this->_data[2]['Blitem']['max'].'"/>','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'minVersion="'.$this->_data[2]['Blitem']['min'].'" maxVersion="'.$this->_data[2]['Blitem']['max'].'" found in blocklist XML.');

        $pattern = preg_quote('    <match name="name" exp="'.$this->_pluginData[0]['Blplugin']['name'].'"/>','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'exp="'.$this->_pluginData[0]['Blplugin']['name'].'" found in blocklist XML.');

        $pattern = preg_quote('    <match name="description" exp="'.$this->_pluginData[0]['Blplugin']['description'].'"/>','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'exp="'.$this->_pluginData[0]['Blplugin']['description'].'" found in blocklist XML.');

        $pattern = preg_quote('    <match name="filename" exp="'.$this->_pluginData[0]['Blplugin']['filename'].'"/>','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'exp="'.$this->_pluginData[0]['Blplugin']['filename'].'" found in blocklist XML.');
    }

    /**
     * No blocklist exists for missing values.  Assert in each of
     * these cases that the resulting RDF is parsable.
     */
    function testPluginItemsVersionCheck() {

        // Store args in a temp array so we can fudge it up.
        $_tmp = $this->_args;

        // Test a version that is too low (outside lower bound of range).
        $_tmp['appVersion'] = '0.1';
        $_xml = $this->_getXml($_tmp);
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when version is too low ('.$_tmp['appVersion'].').');
        $this->assertNoPattern('/.*pluginItems.*/', $_xml, 'No plugin items were found when version is too low ('.$_tmp['appVersion'].').');

        // Test a version that is too high (outside upper bound of range).
        $_tmp['appVersion'] = '8.0';
        $_xml = $this->_getXml($_tmp);
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when version is too high ('.$_tmp['appVersion'].').');
        $this->assertNoPattern('/.*pluginItems.*/', $_xml, 'No plugin items were found when version is too high ('.$_tmp['appVersion'].').');

        // Test a valid version.
        $_tmp['appVersion'] = '1.5';
        $_xml = $this->_getXml($_tmp);
        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when version is valid ('.$_tmp['appVersion'].').');
        $this->assertPattern('/.*pluginItems.*/', $_xml, 'Plugin items were found when version is valid ('.$_tmp['appVersion'].').');
    }

    /**
     * Make sure there aren't null match elements in blocklist entries that have
     * NULL values in the database.
     */
    function testNoNullOrEmptyMatchElements() {

        // Store args in a temp array so we can fudge it up.
        $_tmp = $this->_args;

        $_xml = $this->_getXml($_tmp);

        $this->assertTrue($this->_isParsable($_xml), 'Document is parsable for match elements test.');
        $this->assertNoPattern('/.*<match name="name"\/>.*/', $_xml, '"name" match element without an exp attribute should not exist.');
        $this->assertNoPattern('/.*<match name="description"\/>.*/', $_xml, '"description" match element without an exp attribute should not exist.');
        $this->assertNoPattern('/.*<match name="filename"\/>.*/', $_xml, '"filename" match element without an exp attribute should not exist.');

        $this->assertNoPattern('/.*<match name="name" exp=""\/>.*/', $_xml, '"name" match element with an empty exp attribute should not exist.');
        $this->assertNoPattern('/.*<match name="description" exp=""\/>.*/', $_xml, '"description" match element with an empty exp attribute should not exist.');
        $this->assertNoPattern('/.*<match name="filename" exp=""\/>.*/', $_xml, '"filename" match element with an empty exp attribute should not exist.');
    }

    /**
     * Test that OS and XPCOMABI information can be displayed successfully.
     */
    function testOsAndXpcomabi() {
        $_tmp = $this->_args;

        $_xml = $this->_getXml($_tmp);

        $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when we have items and pluginitems with os/xpcomabi data.');

        $pattern = preg_quote('    <emItem id="'.$this->_data[4]['Blitem']['guid'].'" os="'.$this->_data[4]['Blitem']['os'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, $this->_data[4]['Blitem']['guid'] . ' has os info for '.$this->_data[4]['Blitem']['os'].' (multi-dimensional emItem).');

        $pattern = preg_quote('    <emItem id="'.$this->_data[6]['Blitem']['guid'].'" os="'.$this->_data[6]['Blitem']['os'].'"/>','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, $this->_data[6]['Blitem']['guid'] . ' has os info for '.$this->_data[6]['Blitem']['os'].' (one-line emItem).');

        $pattern = preg_quote('  <pluginItem xpcomabi="'.$this->_pluginData[4]['Blplugin']['xpcomabi'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'exp="'.$this->_pluginData[4]['Blplugin']['name'].'" found in blocklist XML with xpcomabi info for '.$this->_pluginData[4]['Blplugin']['xpcomabi'].'.');

        $pattern = preg_quote('  <pluginItem os="'.$this->_pluginData[5]['Blplugin']['os'].'">','/');
        $this->assertPattern('/.*'.$pattern.'.*/', $_xml, 'exp="'.$this->_pluginData[5]['Blplugin']['name'].'" found in blocklist XML with os info for '.$this->_pluginData[5]['Blplugin']['os'].'.');
    }

    /**
     * Tests that versionRanges are correctly included in pluginItems in the v3
     * schema.
     */
    function testPluginVersionRange() {
        $_tmp = $this->_args;
        $_xml = $this->_getXml($_tmp);

        $this->assertTrue($this->_isParsable($_xml), 'Schema 1 document is parsable for plugin versionRange tests.');
        $this->assertNoPattern('/<pluginItems>.*versionRange.*<\/pluginItems>/s', $_xml, 'No versionRanges exist in schema 1 result.');

        $_tmp['reqVersion'] = '2';
        $_xml = $this->_getXml($_tmp);

        $this->assertTrue($this->_isParsable($_xml), 'Schema 2 document is parsable for plugin versionRange tests.');
        $this->assertNoPattern('/<pluginItems>.*versionRange.*<\/pluginItems>/s', $_xml, 'No versionRanges exist in schema 2 result.');

        $_tmp['reqVersion'] = '3';
        $_xml = $this->_getXml($_tmp);

        $this->assertTrue($this->_isParsable($_xml), 'Schema 3 document is parsable for plugin versionRange tests.');
        $this->assertPattern('/<pluginItems>.*versionRange.*<\/pluginItems>/s', $_xml, 'versionRanges exist in schema 3 result.');

        $matches = array();
        preg_match_all('/<pluginItem.*?<\/pluginItem>/s', $_xml, $matches);
        $this->assertEqual(count($matches[0]), 6, 'Should have been 6 plugin items, actually saw ' . count($matches[0]));
        foreach ($matches[0] as $pluginItem) {
          $this->assertPattern('/<versionRange\s+minVersion="1.0"\s+maxVersion="2.0"\s*\/>/', $pluginItem, 'versionRange for plugin item should have contained the right versions.');
        }

        $_tmp['appVersion'] = '0.1';
        $_xml = $this->_getXml($_tmp);

        $this->assertPattern('/.*pluginItems.*/', $_xml, 'Should always include pluginItems in version 3 schema regardless of version being too low.');
        $matches = array();
        preg_match_all('/<pluginItem.*?<\/pluginItem>/s', $_xml, $matches);
        $this->assertEqual(count($matches[0]), 6, 'Should have been 6 plugin items when using a version that is too high, actually saw ' . count($matches[0]));

        $_tmp['appVersion'] = '8.0';
        $_xml = $this->_getXml($_tmp);

        $this->assertPattern('/.*pluginItems.*/', $_xml, 'Should always include pluginItems in version 3 schema regardless of version being too high.');
        $matches = array();
        preg_match_all('/<pluginItem.*?<\/pluginItem>/s', $_xml, $matches);
        $this->assertEqual(count($matches[0]), 6, 'Should have been 6 plugin items when using a version that is too high, actually saw ' . count($matches[0]));
    }
}
?>
