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
class DevelopersControllerTest extends UnitTestCase {

	/**
	* Setup the Developers Controller
	*/
	function testLoad() {
		$this->helper = new UnitTestHelper();
		$this->controller = $this->helper->getController('Developers', $this);
		//$this->helper->mockModels($this->controller, $this);
		$this->helper->mockComponents($this->controller, $this);
	}

	/**
	* Tests aspects of step 1
	*/
	function testAddStep1() {     
        //Test that the RDF parser has been included
        $this->assertTrue(class_exists('Rdf_parser'), 'RDF parser included');
        
        //Setup so we don't have exceptions.
        $this->controller->data['Addon']['add_step1'] = true;
        $this->controller->data['File']['platform_id1'] = 1;
        $this->controller->data['File']['platform_id2'] = 1;
        $this->controller->data['File']['platform_id3'] = 1;
        $this->controller->data['File']['platform_id4'] = 1;
        $this->controller->data['Addon']['addontype_id'] = ADDON_EXTENSION;
        $this->controller->data['File']['file1']['size'] = 0;
        $this->controller->data['File']['file1']['error'] = 0;
        
        /*//Test for an extension without install.rdf
        $this->controller->data['File']['file1']['name'] = 'extension-noinstall.xpi';
        $this->controller->data['File']['file1']['tmp_name'] = TEST_DATA.'/extension-noinstall.xpi';
        $this->helper->callControllerAction($this->controller, 'add', $this);
        $this->assertEqual($this->controller->addVars['mainError'], 'No install.rdf present', 'Install Manifest: No install.rdf present: %s');*/

	}
}
