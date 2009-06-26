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

class DevelopersEditTest extends WebTestHelper {
    var $id;
    var $model;
    var $data;
    var $de_data;

    function setUp() {
        //Developer pages require login
        $this->login();
        
        $this->id = 7;
        loadModel('Addon');
        $this->model =& new Addon();
        $this->model->id = $this->id;
        // fetch English and German data
        $this->model->useLang = 'en-US';
        $this->data = $this->model->find("Addon.id=$this->id", null , null , 2);
        unset($this->data['Translation']['developercomments']);
        
        $this->model->useLang = 'de';
        $this->de_data = $this->model->find("Addon.id=$this->id", null , null , 2);
        unset($this->de_data['Translation']['developercomments']);
        
        $this->getAction("/developers/edit/" . $this->id);

        global $TestController;
    }

    function testRemoraPage() {
        //just checks if the page works or not
        $this->assertWantedPattern('/Edit/i', 'Header detected');
    }

    function testDisplay() {
        // Title
        $this->assertTitle('Developer Tools :: Firefox Add-ons');

        //Check fields
        $this->assertWantedPattern('/\<option value="12" +selected="selected"\>Organizer\<\/option\>/', 'Category selected');

        $this->assertText($this->data['User'][0]['firstname'].' '.$this->data['User'][0]['lastname'].' ['.$this->data['User'][0]['email'].']', 'Author populated');

        //Localized fields
        foreach ($this->data['Translation'] as $field => $translation) {
            $fieldName = ucwords(strtolower($field));
            $this->assertFieldById('Addon'.$fieldName.'_en_US', strval($translation['string']), $fieldName.' field (en-US) populated: %s');
            if ($this->de_data['Translation'][$field]['locale'] == 'de') {
                $this->assertFieldById('Addon'.$fieldName.'_de', $this->de_data['Translation'][$field]['string'], $fieldName.' field (de) populated: %s');
            }
        }

 
        
    }
}
?>
