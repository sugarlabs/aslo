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

class InstallTest extends WebTestHelper {

    function InstallTest() {
        $this->WebTestCase("Views->Elements->Install Element Tests");
    }

    function setUp() {
        $this->id = 7;
        $this->actionPath = $this->actionPath("");
        loadModel('Addon');
        $model =& new Addon();
        $model->caching = false;
        $this->data = $model->find("Addon.id=$this->id", null , null , 2);
    }

    function testDisplayInstall() {
        //get display page and test eula link
        $this->getAction("/addon/" . $this->id);
        // commented out by clouserw on 2009-08-13.  I'm converting to normal .po files and I don't know what this
        // is, but it doesn't look like $installMessage is doing anything anyway.  we can revisit later.
        //$installMessage = sprintf(_('a_install'), "", "");
        if (!empty($this->data['Translation']['eula']['string'])) {
            // install link
            $this->wantedPattern = "@<p class=\"install-button platform-ALL\">\s*<a href=\"{$this->actionPath}/@";
			/* suspect action not quite right for this test to work as originally was below:
			/downloads/file/{$this->id}/[^\"]+\" +id=\"installTrigger\d+\" +addonName=\"[^\"]+\"[^>]*><span><span><span><strong>Accept and Install</strong></span></span></span></a></p>*/
            $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
            // anti-xss platform link script
            $this->wantedPattern = "@<script type=\"text/javascript\">setTimeout\(function\(\) {fixPlatformLinks\('\d+', document.getElementById\('installTrigger\d+'\).getAttribute\('addonName'\)\);addCompatibilityHints\([^)]+\);},0\);</script>@";
            $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        }

        // test remaining things on the policy page
        $this->getAction("/addons/policy/0/{$this->id}/{$this->data['Version'][0]['File'][0]['id']}");
        $installMessage = ___('Accept and Install');

        //test filenames matches with database
        $this->wantedPattern = "@<a href=\"{$this->actionPath}/downloads/latest/{$this->id}/addon-{$this->id}-latest.xpi\"@";
        $this->assertWantedPattern($this->wantedPattern, "install url matches: ".htmlentities($this->wantedPattern));

        //test add-on name matches in install trigger
        $this->wantedPattern = "@addonName=\"{$this->data['Translation']['name']['string']}\"@";
        $this->assertWantedPattern($this->wantedPattern, "Add-on name matches: {$this->data['Translation']['name']['string']}");

        //test add-on icon link for install trigger
        $this->wantedPattern = "@addonIcon=\"{$this->actionPath}/images/addon_icon/{$this->id}/\d{10}\"@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));

        //test add-on hash matches with db @TODO: Download file and verify hash
        $this->wantedPattern = "@addonHash=\"{$this->data['Version'][0]['File'][0]['hash']}\"@";
        $this->assertWantedPattern($this->wantedPattern, "Addon Hash Match: {$this->data['Version'][0]['File'][0]['hash']}");
    }
}
?>
