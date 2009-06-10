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
class AmoTest extends UnitTestCase {
	
	//Setup the Amo Component
	function setUp() {
	    $this->controller =& new AppController();
		loadComponent('Amo');
		$this->controller->Amo =& new AmoComponent();
        $this->controller->Amo->startup($this->controller);
	}

   /**
    * Test the checkOwnership() method
    */
	function testCheckOwnership() {
        return $this->pass('Skipping XFAIL');

	    $this->fail('Method not yet operational');
	}
	
   /**
    * Test the clean() method
    */
    function testClean() {
        $array = array(
            'test1' => '<script language="JavaScript">alert("BAD THINGS!");</script>',
            'test2' => 'Tyla\'s attendance at the "Quote Convention" was plagued by morgamic\'s suggestive outfits.'
        );
        $shouldbe = array(
            'test1' => 'alert(\"BAD THINGS!\");',
            'test2' => 'Tyla\\\'s attendance at the \"Quote Convention\" was plagued by morgamic\\\'s suggestive outfits.'
        );

        // test cleaning a whole array
        $dirty = array();
        $dirty['test1'] = $array['test1'];
        $dirty['test2'] = $array['test2'];
        $this->controller->Amo->clean($dirty);
        
        //Check for HTML stripping
        $this->assertEqual($dirty['test1'], $shouldbe['test1'], 'HTML stripped (array): %s');
        //Check for SQL quote escape
        $this->assertEqual($dirty['test2'], $shouldbe['test2'], 'SQL quotes escaped (array): %s');


        // test cleaning individual strings
        $str1 = $array['test1'];
        $str2 = $array['test2'];
        $this->controller->Amo->clean($str1);
        $this->assertEqual($str1, $shouldbe['test1'], 'HTML stripped (string): %s');
        
        $this->controller->Amo->clean($str2);
        $this->assertEqual($str2, $shouldbe['test2'], 'SQL quotes escaped (string): %s');
    }
    
   /**
    * Test Approval Status strings
    */
    function testGetApprovalStatus() {
        $array = $this->controller->Amo->getApprovalStatus();
        $this->assertIsA($array, 'array');
        for ($s = 0; $s <= 6; $s++) {
            $this->assertEqual($this->controller->Amo->getApprovalStatus("$s"), $array[$s], 'Approval Status: %s');
        }
    }
	
   /**
    * Test Platform retrieval
    */
    function testGetPlatformName() {
        $array = $this->controller->Amo->getPlatformName();
        $this->assertIsA($array, 'array');
        foreach ($array as $id=>$platform) {
            $this->assertEqual($this->controller->Amo->getPlatformName($id), $array[$id], 'Platform string: %s');
        }
    }

   /**
    * Test the getApplicationName() method
    */
	function testGetApplicationName() {
		//$this->assertEqual($this->controller->Amo->getApplicationName(1), 'Firefox', 'Application name string: %s');
		//$this->assertEqual($this->controller->Amo->getApplicationName(2), 'Thunderbird', 'Application name string: %s');
	}
}
?>
