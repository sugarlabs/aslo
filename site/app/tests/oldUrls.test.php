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
 * Mozilla Corporation (shaver@mozilla.com).
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *    Frederic Wenzel <fwenzel@mozilla.com>
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

class OldUrlsTest extends WebTestHelper {
    var $apps = array("firefox", "thunderbird", "sunbird", "seamonkey");

    function testAddonId() {
        foreach ($this->apps as $app) {
            $path = $this->rawPath("/{$app}/9/");
            $this->getPath($path);
            $this->assertPattern('/MicroHunter/', 'Legacy support for /' . $app . '/$addonId/ (' . $path . ')');
        }
    }
    
    function testAddonIdIsRedirect() {
        foreach ($this->apps as $app) {
            $this->setMaximumRedirects(0);
            $this->getPath($this->rawPath("/{$app}/9/"));
            $this->assertHeader('Location', $this->rawURI("/{$app}/addon/9"));
        }
    }

    function testAuthorId() {
        foreach ($this->apps as $app) {
            $path = $this->rawPath("/{$app}/5/author");
            $this->getPath($path);
            $this->assertPattern('/User Profile/', 'Legacy support for /' . $app . '/$authorId/author/ (' . $path . ')');
        }
    }
    
    function testAuthorIdIsRedirect() {
        foreach ($this->apps as $app) {
            $this->setMaximumRedirects(0);
            $this->getPath($this->rawPath("/{$app}/5/author"));
            $this->assertHeader('Location', $this->rawURI("/{$app}/user/5"));
        }
    }
    
    function testLegacyRSSFeeds() {
        $addontypes = array('extensions', 'themes');
        $sortorders = array('popular', 'updated', 'rated');
        foreach ($this->apps as $app) {
            foreach ($addontypes as $type) {
                foreach ($sortorders as $order) {
                    $path = $this->rawPath("/rss/{$app}/{$type}/{$order}");
                    $this->getPath($path);
                    $this->assertPattern('/rss version="2.0"/', "Legacy support for {$path}");
                }
            }
        }
    }
}
?>
