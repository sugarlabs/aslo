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

class AddonTest extends WebTestHelper {

    function AddonTest() {
        $this->WebTestCase("Views->Addons->Display Tests");
        loadModel('Addon');
        loadModel('Category');
        loadModel('Version');
    }

    function setUp() {
        $this->id = 7;//$_GET['id'];

        $model =& new Addon();
        $model->caching = false;

        $categoryModel =& new Category();
        $categoryModel->caching = false;

        $versionModel =& new Version();
        $versionModel->caching = false;

        $this->data = $model->find("Addon.id=$this->id", null , null , 2);
        $this->data['Version'] = $versionModel->findAll("Version.addon_id=$this->id", null, "Version.created DESC", 0);
        //get category l10n data
        foreach ($this->data['Category'] as $categorykey => $categoryvalue) {
            if ($categorykey == 0)
                $related_category_query = "Category.id='${categoryvalue['id']}'";
            else
                $related_category_query = $related_category_query . " OR Category.id ='${categoryvalue['id']}'";    
        }
        $this->categoryData = $categoryModel->findAll($related_category_query);
        
        $this->getAction("/addon/" . $this->id);

        global $TestController;
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Addons', $this);
        $this->controller->base = $TestController->base;
        loadComponent('Image');
        $this->controller->Image =& new ImageComponent();
        $this->controller->Image->startup($this->controller);
    }

    function testRemoraPage() {
        //just checks if the page works or not
        $this->assertWantedPattern('/Mozilla Add-ons/i', "pattern detected");
    }

    function testDisplay() {
        // Title
        $this->title = sprintf(_('addons_display_pagetitle'), $this->data['Translation']['name']['string']). ' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->assertTitle($this->title);
        // Author
        $username = $this->data['User'][0]['nickname'];
        $userid = $this->data['User'][0]['id'];
        $this->actionPath = $this->actionPath(""); 
        $this->authorPattern = "@<h4 class=\"author\">by +<a href=\"{$this->actionPath}/user/{$userid}\"  class=\"profileLink\">{$username}</a> ?</h4>@";
        $this->assertWantedPattern($this->authorPattern, htmlentities($this->authorPattern));

        //@TODO Size: Figure out some way to use the Number Helper in this test
        //$this->wantedPattern = "#<span>\(" . $this->data['Version'][0]['File'][0]['size'] . "KB\)</span>#";
        //$this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));

        // categories
        foreach ($this->categoryData as $category) {
            $this->wantedPattern = "@<li><a href=\"[^\"]+\"( )*>" . $category['Translation']['name']['string'] . "</a></li>@";
            $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        }		        
        // are reviews displayed?
        $this->wantedPattern = "@"._('addons_display_header_reviews')."@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));

        $this->wantedPattern = "@It works but not well.@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
    }

    function testVersion() {
        $this->getAction("/addon/" . $this->id);
        phpQuery::newDocument($this->_browser->getContent());

        // Check the version detail area.
        $version_link = pq('h5#version-detail a');
        $version = $this->data['Version'][0]['Version']['version'];
        $this->assertEqual($version_link->text(), sprintf(_('addon_display_header_version'), $version));
        $link = sprintf('addons/versions/%s#version-%s', $this->id, $version);
        $this->assertEqual($version_link->attr('href'), $this->controller->url($link));

        $span = pq('h5#version-detail span');
        $created = strtotime($this->data['Version'][0]['Version']['created']);
        $this->assertEqual($span->attr('title'), strftime(_('datetime'), $created));
        $this->assertEquiv($span->text(), '— '.strftime(_('date'), $created));

        $notes = $this->data['Version'][0]['Translation']['releasenotes']['string'];
        $this->assertEquiv(pq('#release-notes')->text(), $notes);

        // check the version at the top title
        $this->wantedPattern = "#" . $this->data['Version'][0]['Version']['version'] . "#";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));

        // check if previous versions link is displayed
        $link = $this->controller->url('addons/versions/'.$this->id);
        $text = ___('addons_display_see_all_versions');
        $this->assertLinkLocation($link, $text);
    }

    function testReviews() {
        $this->getAction("/addon/" . $this->id);
        phpQuery::newDocument($this->_browser->getContent());

        $link = pq('a.more-info');
        $this->assertEqual($link->attr('href'),
                           $this->actionPath('').'/reviews/display/'.$this->id);
    }

    function testIcon() {
        $this->getAction("/addon/" . $this->id);
        phpQuery::newDocument($this->_browser->getContent());

        $img = pq('#addon-summary .name img.addon-icon');
        $this->assertEqual($img->attr('src'), $this->controller->Image->getAddonIconURL($this->id));
    }

    function testPreviewsWithMoreImages() {
        // Check the preview images for addon 7.  It has 2 in total.
        $this->getAction("/addon/" . $this->id);
        phpQuery::newDocument($this->_browser->getContent());
        $previews = $this->controller->Preview->findAllByAddonId($this->id,
                                                                 array('id', 'addon_id', 'caption'),
                                                                 'highlight desc');

        // Check the main preview image.
        $this->checkImage($previews[0], '.preview-img a', '.preview-img img');

        // Check the More Images section.
        $this->assertEqual(pq('#addon-info h4:first')->text(), ___('addons_display_more_images'));
        $this->checkImage($previews[1], '.addon-images li a', '.addon-images li a img');

        //$this->getAction("/addon/" . $this->id);
    }

    function testPreviewsWithoutMoreImages() {
        // Addon 9 only has one preview image.
        $id = 9;
        $this->getAction("/addon/" . $id);
        phpQuery::newDocument($this->_browser->getContent());
        $previews = $this->controller->Preview->findAllByAddonId($id,
                                                                 array('id', 'addon_id', 'caption'),
                                                                 'highlight desc');
        // Check the main preview image.
        $this->checkImage($previews[0], '.preview-img a', '.preview-img img');
        // No More Images section.
        $this->assertNotEqual(pq('#addon-info h4:first')->text(), ___('addons_display_more_images'));
        $this->assertEqual(pq('#addon-info .addon-images')->size(), 0);
    }

    function testPreviewsDefaultImage() {
        // Addon 4022 only has no preview images.
        $id = 4022;
        $this->getAction("/addon/" . $id);
        phpQuery::newDocument($this->_browser->getContent());
        $this->assertEqual(pq('.preview-img img')->attr('src'), $this->controller->base.'/img/no-preview.png');
        // No More Images section.
        $this->assertNotEqual(pq('#addon-info h4:first')->text(), ___('addons_display_more_images'));
        $this->assertEqual(pq('#addon-info .addon-images')->size(), 0);
    }

    function checkImage($preview, $link_selector, $image_selector) {
        list($thumb, $full, $caption) = $this->_previewData($preview);
        $link = pq($link_selector);
        $image = pq($image_selector);
        $this->assertEqual($link->attr('href'), $full);
        $this->assertEqual($link->attr('title'), $caption);
        $this->assertEqual($image->attr('src'), $thumb);
    }

    function _previewData($preview) {
        $thumb = $this->controller->Image->getPreviewURL($preview['Preview']['id']);
        $full = $this->controller->Image->getPreviewURL($preview['Preview']['id'], 'full');
        $caption = $preview['Translation']['caption']['string'];
        return array($thumb, $full, $caption);
    }

    /**
     * bug 412580 was a bug about some UTF-8 characters breaking out HTML sanitization.
     * Make sure this does not happen anymore.
     */
    function testSanitization() {
        $this->wantedPattern = '@sanitization of signs like &amp; and &quot;@';
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
    }
}
?>
