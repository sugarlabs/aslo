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

class AddonVersionsTest extends WebTestHelper {
    var $addonid = null;

    function AddonVersionsTest() {
        $this->WebTestCase("Views->Addons->Previous Versions Tests");
    }

    function setUp() {
        $this->getAction("/addons/versions/{$this->addonid}");

        global $TestController;
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Addons', $this);
        $this->controller->base = $TestController->base;

        // disable query caching so devcp changes are visible immediately
        foreach ($this->controller->uses as $_model) {
            $this->controller->$_model->caching = false;
        }

    }
}

/**
 * Tests for a regular, enabled add-on's previous versions page
 */
class AddonEnabledVersionsTest extends AddonVersionsTest {
    var $addonid = 7;

    function testRemoraPage() {
        // just checks if the page works or not
        $this->assertWantedPattern('/Mozilla Add-ons/i', "pattern detected");
    }

    function testRssLink() {
        // RSS feed linked in header?
        $pattern = '#<link rel="alternate" type="application/rss\+xml" title="[^"]*" href="[\w\d-_/]*/addons/versions/[\d]+/format:rss">#';
        $this->assertPattern($pattern, htmlentities($pattern));
    }

    function testPreviews() {
        $addon = $this->controller->Addon->findById($this->addonid);
        
        // "Careful" warning
        $pattern = '#<h3>'.___('Be Careful With Old Versions').'</h3>#';
        $this->assertPattern($pattern, htmlentities($pattern));
        $pattern = '#<p>'.___('These versions are displayed for reference and testing purposes. You should always use the latest version of an add-on.').'</p>#';
        $this->assertPattern($pattern, htmlentities($pattern));
        
        // Version strings for all versions
        foreach ($addon['Version'] as $version) {
            $pattern = "@<h3>Version " . $version['version'] . ' <span title="' . strftime(___('%B %e, %Y, %I:%M %p'), strtotime($version['created'])) . '">&mdash; ' . strftime(___('%B %e, %Y'), strtotime($version['created'])) . "</span> &mdash; .*</h3>@";
            $this->assertPattern($pattern, htmlentities($pattern));
        }

        // link back
        $this->assertLink(sprintf(___('Back to %1$s...'), $addon['Translation']['name']['string']), 'link back to addon page');
    }

    /**
     * Test if "version-1.0"-style anchor IDs are present so people can
     * use it as a permalink to a specific version.
     */
    function testPermaLinkAnchors() {
        $addon = $this->controller->Addon->findById($this->addonid);
        
        foreach($addon['Version'] as $version) {
            $pattern = '#<div +[^>]*id="version-'.$version['version'].'"[^>]*>#';
            $this->assertPattern($pattern, htmlentities($pattern));
        }
    }
}

/**
 * Tests for a disabled add-on's versions page
 */
class AddonDisabledVersionsTest extends AddonVersionsTest {
    var $addonid = 3716;

    /**
     * Test if version page is _not_ shown for disabled add-ons
     */
    function testDisabledAddonVersionsPage() {
        $this->assertText(___('Add-on not found!'), "disabled add-on's versions page");
    }
}
?>
