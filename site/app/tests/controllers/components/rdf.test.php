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
class RdfTest extends UnitTestCase {
	
	//Setup the RDF Component
	function setUp() {
		loadComponent('Rdf');
		$this->Rdf =& new RdfComponent();
	}

   /**
    * Test the RDF parser
    */
    function testRDF() {
        $fileContents = file_get_contents(TEST_DATA.'/test-install.rdf');
        $manifestData = $this->Rdf->parseInstallManifest($fileContents);
        
	    //Make sure the XML returns an array of parsed data
		$this->assertIsA($manifestData, 'array', 'manifestData returns an array');

	    //Make sure the XML generates an error if invalid
		$badResults = $this->Rdf->parseInstallManifest($manifestData." this is so not valid xml");
		$this->assertIsA($badResults, 'string', 'Invalid XML returns parse error');

	    //Make sure the parsed addon name is correct
		$this->assertEqual($manifestData['name']['en-US'], 'Test Extension', 'Parse name: %s');

        //Make sure the parsed addon GUID is correct
        $this->assertEqual($manifestData['id'], '{B17C1C5A-04B1-11DB-9804-B622A1EF5496}', 'Parse ID: %s');
        
        //Make sure the parsed addon version is correct
        $this->assertEqual($manifestData['version'], '1.0', 'Parse version: %s');
        
        //Make sure the parsed addon GUID is correct
        $this->assertEqual($manifestData['description']['en-US'], 'Test description of the test extension.', 'Parse en-US description: %s');

        //Make sure the parsed addon target applications are correct
        $this->assertEqual($manifestData['targetApplication']['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}']['minVersion'], '1.5', 'Parse minVersion: %s');
        
        //Make sure the parsed addon target applications are correct
        $this->assertEqual($manifestData['targetApplication']['{ec8030f7-c20a-464f-9b0e-13a3a9e97384}']['maxVersion'], '3.0a1', 'Parse maxVersion: %s');
    }
}
?>
