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
 *   Wil Clouser <wclouser@mozilla.com> (Original Author)
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

/**
 * This does not actually test the Translation MODEL, however
 * it tests if dynamically adding the translations to cake queries
 * works correctly.
 */
class TranslationTest extends UnitTestCase {

    var $addon_id = 7;
    var $translation;

    function TranslationTest() {
        loadModel('Addon');
    }

    function setUp()
    {
        $this->Category =& new Category();
    }

    /**
     * Make sure our data is rearranged correctly
     */
    function testAfterFind()
    {
        
        $_all_data = $this->Category->findAll();

        // General check - make sure we got data back
        $this->assertEqual($_all_data[0]['Translation']['name']['string'], 'Developer Tools');

        // Since en-US == LANG, this should be empty
        $this->assertEqual($_all_data[0]['Translation']['name']['locale_html'], '');

        // If we could override LANG, I'd make sure our fallbacks were working 
        // (ie. we request de, but only en-US is available).  People are anxious to see
        // this code though, so I'm committing.  -- clouserw

    }

    /**
     * If we store a translation first and then remove it, does fallback 
     * still work as expected?
     */
    function testFallBackWithEmptyTranslations() {
        $_id = $this->addon_id;

        $this->Addon = new Addon();
        $this->Addon->caching = false;
        $this->Addon->cacheQueries = false;
        $this->Addon->setLang('de');
        $oldAddonData = $this->Addon->find(array('Addon.id'=>$_id));
        // did we get a German name?
        $this->assertEqual($oldAddonData['Translation']['name']['locale'], 'de', 'Localized string fetching works');
        
        $emptyData = array('Addon' => array(
            'id' => $_id,
            'name' => ''));

        // replace it by an empty name
        $_res = $this->Addon->save($emptyData, false, array('id','name'));
        $this->assertTrue($_res, 'Storing an empty translation succeeds');

        // re-fetch it and test if fallback works
        $newAddonData = $this->Addon->find(array('Addon.id'=>$_id));
        $this->assertFalse(empty($newAddonData['Translation']['name']['string']), 'Re-fetched string is not empty');
        $this->assertNotEqual($newAddonData['Translation']['name']['string'], $oldAddonData['Translation']['name']['string'], 'Re-fetched string is different');
        $this->assertNotEqual($newAddonData['Translation']['name']['locale'], 'de', 'Fallback locale is different as expected');

        // fix the changed name
        $_restoredata = array('Addon' => array(
            'id' => $_id,
            'name' => $oldAddonData['Translation']['name']['string']));
        $_res = $this->Addon->save($_restoredata, null, array('id','name'));
        $this->assertTrue($_res, 'Restoring the previous translation succeeds');

        $newestAddonData = $this->Addon->find(array('Addon.id'=>$_id));
        $this->assertEqual($oldAddonData['Translation']['name']['string'], $newestAddonData['Translation']['name']['string'], 'Saved string is correct');
        $this->assertEqual($oldAddonData['Translation']['name']['locale'], 'de', 'Saved locale is correct');
    }

    /**
     * Make sure findCount() doesn't get hosed by the whole translation stuff
     */
    function testFindCountTransactionResistant() {
        $this->Addon = new Addon();
        $addoncount = $this->Addon->findCount(array('Addon.id'=>$this->addon_id));
        $this->assertTrue($addoncount > 0, 'findCount is resistant to translation code');
    }

}
?>
