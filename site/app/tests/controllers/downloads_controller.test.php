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

class DownloadsTest extends WebTestHelper {

    function testLoad() {
        $this->WebTestCase("Download Tests");
        loadModel('Download');
        $this->Download =& new Download();
        $this->Download->caching = false; // Make sure we set this to false so count tests work.
        loadModel('File');
        $this->File =& new File();
        $this->File->caching = false;
    }

    function testNoCacheHeaders() {
        $action = $this->actionURI("/downloads/file/9/");
        $this->get($action);
        $this->assertHeader('Cache-Control', 'no-store, must-revalidate, post-check=0, pre-check=0, private, max-age=0', "cache-control header for {$action}");
        $this->assertHeader('Pragma', 'private', "Pragma private header for {$action}");
    }
    
    function testNoSandboxForYou() {
        $this->getAction("/downloads/file/25982/");
        $this->assertWantedPattern("@Add-on not found@", "Sandbox Denied without Login?");
    }

    function assertFileDownload($file_id, $action, $message) {
        $this->getAction($action);
        $file_data = $this->File->findById($file_id);
        $file_loc = REPO_PATH . '/' . $file_data['Version']['addon_id'] . '/' . $file_data['File']['filename'];
        $this->assertEqual(md5_file($file_loc), md5($this->_browser->getContent()), $message);
    }

    function testDownloads() {
        $this->assertFileDownload(9, '/downloads/file/9/', 'Extension download works.');
        $this->assertFileDownload(8, '/downloads/file/8/a9.src', 'Search download works.');
        $this->assertFileDownload(23559, '/downloads/file/23559', 'Dictionary download works.');
    }
}
?>
