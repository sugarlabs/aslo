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

class GroupsControllerTestCase extends UnitTestCase {

    var $testgroup = array(
        'id' => '10000',
        'name' => 'testgroup',
        'rules' => '*:*'
    );

    function testLoad() {
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Groups', $this);
        $this->helper->mockComponents($this->controller, $this);
        $this->controller->params['controller'] = 'Users';
        $this->controller->Group->cacheQueries = false;

        // Make sure we aren't caching when testing edits.
        $this->controller->Group->caching = false;
        $this->controller->User->caching = false;
    }

    function testAdd() {
        $this->controller->data = $this->testgroup;
        $this->helper->callControllerAction($this->controller, 'add', $this);
        $this->id = $this->controller->Group->getLastInsertId();
        $this->assertTrue($this->controller->Group->findById($this->id), 'Test group was added successfully.');
    }

    function testEdit() {
        $this->controller->data = $this->testgroup;
        $this->controller->data['name'] = 'othertestgroup';
        $this->helper->callControllerAction($this->controller, 'edit', $this);
        $group = $this->controller->Group->findById($this->id);
        $this->assertEqual($group['Group']['name'],'othertestgroup');
    }

    function testDelete() {
        $this->helper->callControllerAction($this->controller, 'delete', $this, array('id'=>$this->id));
        $group = $this->controller->Group->findById($this->id);
        $this->assertFalse($group,'Test group was deleted successfully.');
    }
}
?>
