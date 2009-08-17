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
   * Mike Morgan <morgamic@mozilla.com>.
   * Portions created by the Initial Developer are Copyright (C) 2006
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
class TagsControllerTest extends UnitTestCase {

    /**
     * Setup the Tags Controller
     */
    function setUp() {
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Tags', $this);
        $this->helper->mockComponents($this->controller, $this);
        
        loadModel('Addon');
        $this->Addon =& new Addon();
        $this->Addon->caching = false;
        $this->Addon->cacheQueries = false;

        $this->controller->Session =& new MockSessionComponent();
    }

    function _addTag($addonid, $tagid, $userid) {
        $this->Addon->addTag($addonid,$tagid,$userid);

        $_res = $this->Addon->execute("SELECT tag_id FROM users_tags_addons WHERE user_id={$userid} and tag_id={$tagid} and addon_id={$addonid}");

        $this->assertEqual(count($_res), 1, 'Tag successfully added');

    }
    
    function _removeTag($addonid, $tagid) {
        $this->Addon->removeTagFromAddon($tagid, $addonid);

        $_res = $this->Addon->execute("SELECT tag_id FROM users_tags_addons WHERE tag_id={$tagid} and addon_id={$addonid}");

        $this->assertEqual($_res, null, 'Tag successfully removed');
    }

    function _doesTagExist($addonid, $tagid, $userid) {
        $_res = $this->Addon->execute("SELECT tag_id FROM users_tags_addons WHERE user_id={$userid} and tag_id={$tagid} and addon_id={$addonid}");

        if (count($_res) > 0) {
            return true;
        }

        return false;
    }
    
    function _getTagId($tag_text) {
        $id = null;
        $_res = $this->Addon->execute("SELECT id FROM tags where tag_text='$tag_text'");
        if (count($_res) == 1) {
            $id = $_res[0]['Tag']['id'];
        }
    
        return $id;
    }
    
    function _isTagBlacklisted($id) {
        $_res = $this->Addon->execute("SELECT blacklisted from tags where id={$id}");
        if (count($_res) == 1) {
            if ($_res[0]['Tag']['blacklisted'] == 1) {
                return true;
            }
        }
    
        return false;
    }
    
    function _tearDownTag($tag_text) {
        $id = $this->_getTagId($tag_text);

        //cascade delete
        if ($id) {
            $this->Addon->execute("DELETE FROM users_tags_addons WHERE tag_id={$id}");
            $this->Addon->execute("DELETE FROM tag_stat WHERE tag_id={$id}");
            $this->Addon->execute("DELETE FROM tags WHERE id={$id}");
        }
    }

    function _tagMatches($id, $text) {
        $_res = $this->Addon->execute("SELECT id FROM tags WHERE id = {$id} AND tag_text = '{$text}'");

        $this->assertTrue(count($_res) > 0, 'Tag text matches expected value');
    }

    function _countTags() {
        $_res = $this->Addon->execute("SELECT count(*) AS `count` FROM `tags`");

        return $_res[0][0]['count'];
    }

    function testRemove() {
        $this->helper->login($this->controller);

        // We're user id 5.  Try to remove a tag we create
        $this->_addTag(4,1,5); // regular user
        $_POST['addonid'] = 4;
        $_POST['tagid'] = 1;
        $this->controller->remove();
        $this->assertFalse($this->_doesTagExist(4,1,5), 'Tag removed as owner');

        // Try to remove a tag we didn't create (bug 501828)
        $this->_addTag(2,1,8); // regular user
        $_POST['addonid'] = 2;
        $_POST['tagid'] = 1;
        $this->assertTrue(($this->controller->remove()===false), 'Remove() rejects invalid remove attempts');
        $this->assertTrue($this->_doesTagExist(2,1,8), 'Tag not removed when user is not an owner');

        // Create a tag as a normal user, attempt to delete it as a developer
        $this->_addTag(7,1,1); // regular user
        $_POST['addonid'] = 7;
        $_POST['tagid'] = 1;
        $this->controller->remove(); // we're a developer for add-on id 7
        $this->assertFalse($this->_doesTagExist(7,1,1), 'Tag removed when user is a developer');

        // Create a tag on an add-on as ourselves, add it to another add-on as another user, try to remove it from other add-on as ourselves
        $this->_addTag(6,1,5); // regular user
        $this->_addTag(5,1,4); // regular user
        $_POST['addonid'] = 5;
        $_POST['tagid'] = 1;
        $this->assertTrue(($this->controller->remove()===false), 'Remove() rejects invalid remove attempts');
        $this->assertTrue($this->_doesTagExist(5,1,4), 'Tag not removed when user is not an owner');
    }
    
    function testAdd() {
        $this->helper->login($this->controller);
        $tag='testtag';
    
        // We're user id 5. Try to add a tag to someone else's addon
        $this->_tearDownTag($tag);
        $_POST['addonid'] = 4;
        $_POST['newTag'] = $tag;
        $this->controller->add();
        $id = $this->controller->Tag->getLastInsertID();
        $this->assertTrue($this->_doesTagExist(4,$id,5), 'Tag added as user');
        $this->_removeTag(4, $id);
    
        // Try to add a tag to own addon (as a developer)
        $this->_tearDownTag($tag);
        $_POST['addonid'] = 5; //addon 5's owner is user 5
        $_POST['newTag'] = $tag;
        $this->controller->add();
        $id = $this->controller->Tag->getLastInsertID();
        $this->assertTrue($this->_doesTagExist(5,$id,5), 'Tag added as developer');
        $this->_removeTag(5, $id);
    
        // Add a tag with quotes (spaces)
        $this->_tearDownTag('tag with spaces');
        $_POST['addonid'] = 4;
        $_POST['newTag'] = '"tag with spaces"';
        $this->controller->add();
        $id = $this->controller->Tag->getLastInsertID();
        $this->assertTrue($this->_doesTagExist(4,$id,5), 'Tag with quotes added');
        $this->_removeTag(4, $id);
    
        //Try adding multiple tags
        $last_id = $id; //using the last known insert id (from the previous test)
        $tags='testtag1,testtag2,  "testtag 3"';
        $this->_tearDownTag('testtag1');
        $this->_tearDownTag('testtag2');
        $this->_tearDownTag('testtag 3');
        $_POST['addonid'] = 4;
        $_POST['newTag'] = $tags;
        $this->controller->add();
        $id = $this->controller->Tag->getLastInsertID();
        $success_count = 0;
        for ($i = $last_id + 1; $i <= $id; $i++) {
            if ($this->_doesTagExist(4,$i,5)) $success_count++;
            $this->_removeTag(4, $i);
        }
        $this->assertTrue($success_count == 3, 'Multiple tags added');
    }

    function testAddBadInput() {
        $this->helper->login($this->controller);
            
        // Try some bad input - extra spaces
        $_POST['addonid'] = 4; 
        $_POST['newTag'] = '   spaces     are    trimmed   ';
        $this->_tearDownTag('spaces are trimmed');
        $this->controller->add();
        $id = $this->controller->Tag->getLastInsertID();
        $this->assertTrue($this->_doesTagExist(4,$id,5), 'Tag added with spaces trimmed');
        $this->_tagMatches($id, 'spaces are trimmed');
        $this->_removeTag(4, $id);

        // Try blank single tag
        $_POST['addonid'] = 4;
        $_POST['newTag'] = '';
        $count = $this->_countTags();
        $this->controller->add();
        $this->assertEqual($count, $this->_countTags(), 'Empty tags are skipped');

        // Try blank tags, just commas
        $_POST['addonid'] = 4;
        $_POST['newTag'] = '  ,   ,,,  ';
        $count = $this->_countTags();
        $this->controller->add();
        $this->assertEqual($count, $this->_countTags(), 'Tags are trimmed, and empty tags are skipped');

        // Try bad addon id
        $_POST['addonid'] = '';
        $this->assertFalse($this->controller->add(), 'No add-on id returns false');

    }

    function testRemoveBadInput() {
        
        $_POST['addonid'] = '';
        $_POST['tagid'] = 1;
        $this->assertFalse($this->controller->remove(), 'Removing empty add-on id returns false');

        $_POST['addonid'] = 4;
        $_POST['tagid'] = '';
        $this->assertFalse($this->controller->remove(), 'Removing empty tag id returns false');
    }
    
    function testAddAndBlacklist() {
        $this->helper->login($this->controller);
        $tag='blacklisttag';
    
        // We're user id 5. Add a blacklist tag
        $this->_tearDownTag($tag);
        $_POST['newTag'] = $tag;
        $this->controller->addAndBlacklist();
        $id = $this->controller->Tag->getLastInsertID();
        $this->assertTrue($this->_isTagBlacklisted($id), 'Blacklist tag successfully added');
        $this->_tearDownTag($tag);
    }
}
?>
