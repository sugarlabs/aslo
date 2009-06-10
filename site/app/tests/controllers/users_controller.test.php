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
 *   Frederic Wenzel <fwenzel@mozilla.com> (Original Author)
 *   Michael Morgan <morgamic@mozilla.com>
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

class UsersTest extends UnitTestCase {
    
    /**
     * Test user data
     */
    var $testdata = array(
        'email' => 'test@example.com',
        'password' => 'topsecret',
        'confirmpw' => 'topsecret',
        'firstname' => 'John',
        'lastname' => 'Doe',
        'nickname' => 'Johnnie',
        'emailhidden' => 1,
        'sandboxshown' => 1,
        'homepage' => 'http://mozilla.org',
    );

    /**
     * Register Test user
     * @access private
     */
    function _registerTestUser() {
        $this->controller->params['controller'] = 'Users';
        $this->controller->data['User'] = $this->testdata;
        $this->helper->callControllerAction($this->controller, 'register', $this);
        return $this->controller->User->getLastInsertId();
    }

    /**
     * Set up the users controller
     */
    function testLoad() {
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Users', $this);
        $this->helper->mockComponents($this->controller, $this);
        $this->controller->User->cacheQueries = false; // important! Otherwise cake doesn't realize when we changed the user data.
        $this->controller->User->caching = false; // important! Otherwise memcache will break stuff.

        // Prevent exceptions in other parts of the world.
        $this->controller->Amo->setReturnValue('getApplicationVersions',
                                               array(1 =>  array(1.5, 2.0, 3.0),
                                                     18 => array(1.5, 2.0, 3.0)));
        $this->controller->params['url'] = array();
    }

    /**
     * Will a new user show up in the user table when the registration data
     * is entered?
     */
    function testRegistration() {
        // register and send confirmation email
        $this->controller->Email =& new MockEmailComponent();
        $this->controller->Email->expectOnce('send', false, 'Send confirmation email');
        $id = $this->_registerTestUser();
        $this->controller->Email->tally();

        // resend confirmation email on request
        $this->controller->Email =& new MockEmailComponent();
        $this->controller->Email->expectOnce('send', false, 'Resend confirmation email on user request');
        $this->helper->callControllerAction($this->controller, 'verify', $this, array($id, 'resend'));
        $this->controller->Email->tally();

        $storeddata = $this->controller->User->findById($id);
        
        // user created, at all?
        $this->assertNotEqual($storeddata['User']['id'], 0, 'User creation');

        // data correctly stored?
        foreach (array_keys($this->testdata) as $field) {
            if ($field == 'password' || $field == 'confirmpw') continue;
            $this->assertEqual($this->testdata[$field], $storeddata['User'][$field], "Data for $field stored");
        }

        $validPassword = $this->controller->User->_checkPassword(
            $this->testdata['password'], $storeddata['User']['password']);
        $this->assertTrue($validPassword, "Data for password stored");

        $this->assertTrue(preg_match('/^[0-9a-f]{32}$/', strtolower($storeddata['User']['confirmationcode'])), 'Creating confirmation code');
        
        // delete user record, if it was created before
        if ($this->controller->User->id != 0) @$this->controller->User->del();
    }

    /**
     * Does an already taken username get rejected from re-registering?
     */
    function testDuplicateUserRegistration() {
        $this->_registerTestUser();
        // try adding the same user again
        $this->_registerTestUser();
        
        $usersfound = $this->controller->User->find("User.email = '{$this->testdata['email']}'");
        $this->assertTrue(isset($usersfound['User']['id']), 'No duplicate user generation');
        
        foreach ($usersfound as $singleuser) {
            @$this->controller->User->del($singleuser['User']['id']);
        }
    }

    /*
     * Does the generated confirmation code unlock the user account?
     */
    function testConfimationCodeWorks() {
        $id = $this->_registerTestUser();
        
        $data = $this->controller->User->findById($id);
        
        $this->helper->callControllerAction($this->controller, 'verify', $this, array($id, $data['User']['confirmationcode']));
        
        $this->controller->User->data = null;
        $data = $this->controller->User->findById($id);

        $this->assertEqual($data['User']['confirmationcode'], '', 'Confirmation code removed after verification');

        @$this->controller->User->del($id);
    }

    /*
     * Does an invalid confirmation code not unlock the account?
     */
    function testInvalidConfimationCodeRejected() {
        $this->_registerTestUser();
        $origdata = $this->controller->User->read();

        $this->helper->callControllerAction($this->controller, 'verify', $this, array($origdata['User']['id'], 'some random wrong code'));
        
        $data = $this->controller->User->findById($origdata['User']['id']);

        $this->assertEqual($data['User']['confirmationcode'], 
            $origdata['User']['confirmationcode'], 'Confirmation code not to be removed on invalid confirmation attempt');

        @$this->controller->User->del($data['User']['id']);
    }

    /**
     * Do not allow sending an additional confirmation email to a user
     * who's already confirmed.
     */
    function testNoConfirmationEmailResendForConfirmedUsers() {
        $this->controller->Email =& new MockEmailComponent();
        $this->controller->Email->expectNever('send', 'Not sending confirmation email to confirmed user');
        // user id is our login user and therefore confirmed
        $this->helper->callControllerAction($this->controller, 'verify', $this, array('5', 'resend'));
        // re-instanciate the email component so it doesn't react to later
        // calls of send
        $this->controller->Email =& new MockEmailComponent();
    }
    
    /**
     * Can I not log in when the account was not confirmed yet?
     */
    function testUnconfirmedAccountLogin() {
        // re-mock session component to clear previous expectations
        $this->controller->Session =& new MockSessionComponent();
        $this->_registerTestUser();

        $this->controller->data = array();
        $this->controller->data['Login']['email'] = $this->testdata['email'];
        $this->controller->data['Login']['password'] = $this->testdata['password'];

        // no session should be generated
        $this->controller->Session->expectNever('write', 'Login with unconfirmed user account');
        $this->helper->callControllerAction($this->controller, 'login', $this);
        $this->controller->Session->tally();
        
        @$this->controller->User->del($this->controller->User->getLastInsertId());
    }

    /**
     * Can I reset the password with the right code?
     */
    function testPasswordResetPossible() {
        $bill = $this->controller->User->findByEmail('bill@ms.com');
        $oldpw = $bill['User']['password'];
        $data['password'] = 'my_new_password';
        $data['confirmpw'] = 'my_new_password';

        // Start the reset process so we can get a valid reset code.
        $resetCode = $this->controller->User->setResetCode($bill['User']['id']);

        $this->controller->data['User'] = $data;
        $this->helper->callControllerAction($this->controller, 'pwreset', $this, array($bill['User']['id'], $resetCode));

        $newdata = $this->controller->User->find("User.email = 'bill@ms.com'");

        $validPassword = $this->controller->User->_checkPassword(
            'my_new_password' , $newdata['User']['password']);
        $this->assertTrue($validPassword, 'Password reset');

        // reset the pw
        $this->controller->User->id = $bill['User']['id'];
        $this->controller->User->saveField('password', $oldpw);
    }

    /**
     * Can I NOT reset the password with the wrong code?
     */
    function testPasswordResetWithIncorrectCode() {
        $bill = $this->controller->User->findByEmail('bill@ms.com');
        $oldpw = $bill['User']['password'];
        $data['password'] = 'my_new_password';
        $data['confirmpassword'] = 'my_new_password';

        $this->controller->data['User'] = $data;
        $this->helper->callControllerAction($this->controller, 'pwreset', $this, array($bill['User']['id'], md5('random, wrong password reset code')));

        $newdata = $this->controller->User->findByEmail('bill@ms.com');
        $this->assertNotEqual($newdata['User']['password'], md5('my_new_password'), 'Password reset');

        $this->controller->User->id = $bill['User']['id'];
        $this->controller->User->saveField('password', $oldpw);
    }

    /**
     * Can I log in and out with the right password
     * (is my session generated and destroyed?)
     */
    function testLoginLogoutPossible() {
        $username = 'nobody@mozilla.org';
        $pw = 'test';
        
        $this->controller->data = array();
        $this->controller->data['Login']['email'] = $username;
        $this->controller->data['Login']['password'] = $pw;

        // re-mock session component to clear previous expectations
        $this->controller->Session =& new MockSessionComponent();
        $this->controller->Session->expectOnce('write', false, 'Login attempt');
        $this->helper->callControllerAction($this->controller, 'login', $this);
        $this->controller->Session->tally();

        // re-mock session component to clear previous expectations
        $this->controller->Session =& new MockSessionComponent();
        $this->controller->Session->expectNever('write', 'Logout');
        $this->helper->callControllerAction($this->controller, 'logout', $this);
        $this->controller->Session->tally();
    }

    /**
     * Is the login rejected with the wrong password?
     */
    function testLoginRejectWrongPassword() {
        $this->controller->data = array();
        $this->controller->data['User']['email'] = 'bill@ms.com';
        $this->controller->data['User']['password'] = 'notmypassword';
        
        $this->helper->callControllerAction($this->controller, 'login', $this);
        $this->assertFalse($this->controller->Session->check('User'), 'Login with wrong pw');
    }

    /**
     * Is the login rejected for a wrong user?
     */
    function testLoginRejectWrongUser() {
        $this->controller->data = array();
        $this->controller->data['User']['email'] = 'idonotexist@example.com';
        $this->controller->data['User']['password'] = 'mypassword';
        
        $this->helper->callControllerAction($this->controller, 'login', $this);
        $this->assertFalse($this->controller->Session->check('User'), 'Login with wrong user');
    }

    // @todo user editing tests

    /**
     * Test that a corresponding ARO was created with a new user account.
     */
    function testUserAroCreated() {
        return false;
    }
    
    /**
     * Test to see if a user actually has access to an Add-on ACO.
     */
     function testAddonAccess() {
        return false;
     }

    /**
     * Test to see if a user had edit access on the Tags ACO.
     */
    function testEditTagAccess() {
        return false;
    }

    /**
     * Test admin membership.
     */
    function testIsAdmin() {
        // Grant admin rights to a test user.
        
        // Test to see if that user is now an admin.
        return false;
    }

    /**
     * Test that access is denied when a user doens't have an aro->aco entry.
     */
    function testAccessDenied() {
        return false;
    }
}
?>
