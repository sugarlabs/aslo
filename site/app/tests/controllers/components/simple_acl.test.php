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
 * Contributor(s):
 *      Mike Morgan <morgamic@mozilla.com> (Original Developer)
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
class SimpleAclTest extends UnitTestCase {
	
    /**
     * Set up our required models and components.
     */
	function setUp() {
        loadController('Groups');
	    $this->controller =& new GroupsController();

        loadComponent('Session');
        $this->controller->Session =& new SessionComponent();
        $this->controller->Session->startup($this->controller);

        loadComponent('SimpleAuth');
		$this->controller->SimpleAuth =& new SimpleAuthComponent();
        $this->controller->SimpleAuth->startup($this->controller);

		loadComponent('SimpleAcl');
		$this->controller->SimpleAcl =& new SimpleAclComponent();
        $this->controller->SimpleAcl->startup($this->controller);
	}

    /**
     * Test action allowed.
     */
    function testActionAllowed() {
        $this->controller->SimpleAuth->setActiveUser(6,true);
        $this->assertTrue($this->controller->SimpleAcl->actionAllowed('Editors','*',$this->controller->SimpleAuth->getActiveUser()), 'User editor@test has access to Editors:* via group permissions.');

        $this->controller->SimpleAuth->setActiveUser(5,true);
        $this->assertTrue($this->controller->SimpleAcl->actionAllowed('*','*',$this->controller->SimpleAuth->getActiveUser()), 'User nobody@mozilla.org has access to Groups:* via group permissions.');
    }

    /**
     * Test permission denied.
     */
    function testPermissionDenied() {
        $this->controller->SimpleAuth->setActiveUser(7,true);
        $this->assertFalse($this->controller->SimpleAcl->actionAllowed('Editors','*',$this->controller->SimpleAuth->getActiveUser()), 'User user@test does not have access to Editors:* via group permissions.');
    }

    /**
     * Test aclException.
     */
    function testAclException() {
        $this->controller->SimpleAuth->setActiveUser(7,true);
        $this->assertFalse($this->controller->SimpleAcl->actionAllowed('Groups','add',$this->controller->SimpleAuth->getActiveUser()), 'User user@test does not have access to Groups:add.');

        $this->controller->aclExceptions = array('add');
        $this->assertTrue($this->controller->SimpleAcl->actionAllowed('Groups','add',$this->controller->SimpleAuth->getActiveUser()), 'User user@test has access to Groups:add after adding aclException.');
    }

    /**
     * Test disabled.
     */
    function testDisabled() {
        $this->controller->SimpleAuth->setActiveUser(7,true);
        $this->assertFalse($this->controller->SimpleAcl->actionAllowed('Groups','*',$this->controller->SimpleAuth->getActiveUser()), 'User user@test does not have access to Groups:add.');

        // Load a controller and make sure ACL is disabled.
        loadController('Groups');
	    $this->otherController =& new GroupsController();

        loadComponent('Session');
        $this->otherController->Session =& new SessionComponent();
        $this->otherController->Session->startup($this->otherController);

        loadComponent('SimpleAuth');
		$this->otherController->SimpleAuth =& new SimpleAuthComponent();
        $this->otherController->SimpleAuth->enabled = false;
        $this->otherController->SimpleAuth->startup($this->otherController);

		loadComponent('SimpleAcl');
		$this->otherController->SimpleAcl =& new SimpleAclComponent();
        $this->otherController->SimpleAuth->enabled = false;
        $this->otherController->SimpleAcl->startup($this->otherController);

        // First, we show that no user exists, which means the system won't even check for permissions on load unless it's explicit.
        $this->assertEqual(null,$this->otherController->SimpleAuth->activeUser, 'No active user was ever set by SimpleAuth when it was disabled.');

        // Second, we verify that because no SimpleAuth user was instantiated, access checks will fail when attempted against the default active user.
        $this->assertFalse($this->otherController->SimpleAcl->actionAllowed('Groups','*'), 'Default active user does not have access to Groups:add when SimpleAcl is disabled.');

        // However, we still want the ability to check again if we set an explicit user.
        $this->controller->SimpleAuth->setActiveUser(5,true);
        $this->assertTrue($this->otherController->SimpleAcl->actionAllowed('Groups','*',$this->controller->SimpleAuth->getActiveUser()), 'You can still check the access of a user that is explicitly set and chekced manually.');
    }
}
?>
