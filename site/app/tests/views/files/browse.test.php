
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
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   RJ Walsh <rwalsh@mozilla.com>
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

class DevelopersBrowseTest extends WebTestHelper {

    function ownAddon() {
        // We need to be able to login a non-admin developer
        global $TestController;
        $TestController->User->execute('INSERT INTO addons_users VALUES (1, 2, 5, 1, 0)');
    }

    function tearDown() {
        // Reset the data we changed
        global $TestController;
        $user = $TestController->User->id = 2;
        $TestController->User->saveField('confirmationcode', '2312');
        $TestController->User->execute('DELETE FROM addons_users WHERE addon_id = 1 AND user_id = 2');
    }

   /**
    * Logs in with developer info
    */
    function loginDeveloper() {
        global $TestController;
        $TestController->User->id = 2;
        $TestController->User->saveField('confirmationcode', '');

        $username = 'fligtar@gmail.com';
        $password = 'test';

        $path = $this->actionURI('/users/login');
        $data = array(
                    'data[Login][email]' => $username,
                    'data[Login][password]' => $password
                );

        $this->post($path, $data);
        $this->assertNoUnwantedText(___('Wrong username or password!'), 'Logged in with developer account');
    }

    function testAdminBrowsing() {
        // Check for the admin
        $this->login();
        $this->getAction("/files/browse/1");

        // Just load the page using the default action
        $this->assertWantedPattern('/File Browser/i', 'Header detected');
        $this->assertWantedPattern('/install.rdf/i', 'File names');
    }

    function testDeveloper() {
        // Login with different privileges, file 11 belongs to add-on 1
        $this->loginDeveloper();
        $this->ownAddon();
        $this->getAction("/files/browse/11");

        // The developer should be able to see the add-on as well
        $this->assertWantedPattern('/File Browser/i', 'Header detected');
        $this->assertWantedPattern('/install.rdf/i', 'File names');
    }

    function testGeneralBrowsing() {
        // Make sure that general source viewing is not allowed
        $this->loginDeveloper();
        $this->getAction('/files/browse/11');
        $this->assertWantedPattern('/This add-on is not viewable here/i', 'Access denied');

        global $TestController;
        $TestController->Addon->id = 1;
        $TestController->Addon->saveField('viewsource', 1);

        $this->getAction('/files/browse/11');

        // Source viewing should allow the add-on to be viewed
        $this->assertWantedPattern('/File Browser/i', 'Header detected');
        $this->assertWantedPattern('/install.rdf/i', 'File names');

        $TestController->Addon->saveField('viewsource', 0);
    }
}
?>

