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
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Andrei Hajdukewycz <sancus@off.net> (Original Author)
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

class AddonHomeTest extends WebTestHelper {
    
    function AddonHomeTest() {
        $this->WebTestCase("Views->Addons->Home Tests");
    }

    function setUp() {
        
        $this->getAction("");
        
        global $TestController;
        loadComponent('Image');
        $this->Image =& new ImageComponent();
        $this->Image->startup($TestController);
    }

    function testRemoraHome() {
        //just checks if the page works or not
        $this->assertWantedPattern('/Mozilla Add-ons/i', "pattern detected");
    }

    function testTitle() {
        $this->title = sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->assertTitle($this->title);
    }
    
    function testAddons() {
        $homepage = $this->_browser->getContent();
        $homepage = explode("\n", $homepage);

        $addon = 0;
        foreach ($homepage as $line) {
            if ($line = strstr($line, "/addon/")) {
                $line = strip_tags($line);
                sscanf($line, "/addon/%d\" >%s", $this->addonid[$addon], $this->addonName[$addon]);
                $addon++;
				break;//in 3.2 test only get the first one since now do have many featured addon (in 3.0 only 1)
            }
        }
        $this->assertEqual($addon, 1);        
    }
    function testLoadData() {
        foreach ($this->addonid as $addon_id) {
            if ($addon_id == $this->addonid[0]) {
                $select_string = "Addon.id = $addon_id";
            }
            else
                $select_string = $select_string . " OR Addon.id = $addon_id";
        }
        $this->model =& new Addon();
        $this->model->caching = false;
        $this->assertTrue($this->addon_data = $this->model->findAll($select_string, null, null, 3, null, 1));
    }
    
    function testAddonNames() {
        
        foreach ($this->addon_data as $addon_data) {
            $this->assertTrue(in_array($addon_data['Translation']['name']['string'], $this->addonName));
        }
    }
    function testAuthors() {
        foreach ($this->addon_data as $addon_data) {
            $wantedPattern = "#>" . $addon_data['User'][0]['firstname'] ." ".$addon_data['User'][0]['lastname'] . "</a></h5>#";
            $this->assertWantedPattern($wantedPattern, htmlentities($wantedPattern));
        }
    }   
    
    function xFailIcon() {
        $wantedPattern = "#<img src=\"" . $this->Image->getAddonIconURL($this->addonid[0]) . "\" alt=\"" . $this->addon_data[0]['Addon']['name'] .  "\" />#";
        $this->assertWantedPattern($wantedPattern, htmlentities($wantedPattern));
    }
}
?>
