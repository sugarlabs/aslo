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
 *  RJ Walsh <rwalsh@mozilla.com>
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

class DevelopersValidateVersionTest extends WebTestHelper {
    var $id;
    var $model;

    function setUp() {

        //Developer pages require login
        $this->login();
        
        $this->id = 1;
        $this->model =& new Version();
        $this->model->caching = false; // Make sure caching is off.

        $this->model->id = $this->id;
        $this->getAction('/developers/versions/validate/' . $this->id);

    }

    function testRemoraPage() {
        //just checks if the page works or not
        $this->assertWantedPattern('/Validate/i', 'Header detected');
    }

    function testDisplay() {
        // Title
        $this->assertTitle('Developer Hub :: Add-ons for Firefox', 'Title populated: %s');

        // Get some bad data
        $this->getAction('/developers/verify/' . $this->id . '/2');
        file_put_contents(NETAPP_STORAGE . '/validate-1/bad', '<iframe>');
        $this->getAction('/developers/verify/' . $this->id . '/2');

        // Make sure we never get bad HTML from test results
        $this->assertPattern('/&lt;iframe/i', 'Iframes are escaped');

        // Make sure pattern matches are always terminated
        $this->assertNoPattern('/Matched Pattern: "\/(?!")/', 'No mismatched patterns');
    }
}
?>
