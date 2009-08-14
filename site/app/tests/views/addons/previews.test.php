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
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Frederic Wenzel <fwenzel@mozilla.com> (Original Author)
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

class AddonPreviewsTest extends WebTestHelper {
    var $addonid = 7;

    function AddonPreviewsTest() {
        $this->WebTestCase("Views->Addons->Preview Images Tests");
    }

    function setUp() {
        $this->getAction("/addons/previews/{$this->addonid}");

        global $TestController;
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Addons', $this);
        $this->controller->base = $TestController->base;
    }

    function testRemoraPage() {
        // just checks if the page works or not
        $this->assertWantedPattern('/Mozilla Add-ons/i', "pattern detected");
    }

    function testPreviews() {
        $addon = $this->controller->Addon->findById($this->addonid);
        
        // Title
        $this->title = sprintf(_('addons_previews_pagetitle'), $addon['Translation']['name']['string']) .' :: '. sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);
        $this->assertTitle($this->title);
        
        // Header
        $pattern = '#'.sprintf(_('addons_previews_pagetitle'), $addon['Translation']['name']['string']).'#';
        $this->assertPattern($pattern, htmlentities($pattern));

        // preview pic
        $pattern = '#<a href="[^"]*/images/preview/'.$this->addonid.'/1" '
            .'rel="lightbox\[previews\]" '
            .'title="[^"]*">'
            .'<img src="[^"]*/images/addon_preview/'.$this->addonid.'/1"'
            .' alt="" ></a>#';
        $this->assertPattern($pattern, htmlentities($pattern));
        
        // link back
        $this->assertLink(sprintf(___('Back to %1$s...'), $addon['Translation']['name']['string']), 'link back to addon page');
    }
}
?>
