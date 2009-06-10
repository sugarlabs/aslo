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
 *   Justin Scott <fligtar@gmail.com>
 *   Wil Clouser <clouserw@mozilla.com>
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

class Tag extends AppModel
{
    var $name = 'Tag';
    var $hasAndBelongsToMany_full = array('Addon' =>
                                     array('className'  => 'Addon',
                                           'joinTable'  => 'addons_tags',
                                           'foreignKey' => 'tag_id',
                                           'associationForeignKey'=> 'addon_id')
                               );
    var $belongsTo = array('Addontype', 'Application');

    /**
     * Fields here will looked up in the translations table
     *
     * @var array
     * @access public
     */
    var $translated_fields = array('name', 'description');
    
    /**
     * Saves selected categories by only removing deleted categories and inserting
     * new categories rather than deleting all existing categories and adding all
     * back.
     * @param int $addon_id add-on id
     * @param array $data tag data from form
     */
    function saveCategories($addon_id, $data) {
        if (empty($data)) {
            return;
        }
        
        foreach ($data as $application_id => $selectedTags) {
            foreach ($selectedTags as $tag_id) {
                if (!empty($tag_id)) {
                    $newTags[] = $tag_id;
                }
            }
        }
        
        if (!empty($newTags)) {
            // Only delete tags that aren't still selected to retain additional
            // information, such as featured status
            $this->execute("DELETE FROM addons_tags WHERE addon_id={$addon_id} AND tag_id NOT IN (".implode(',', $newTags).")");
        }
        
        $_currentTags = $this->query("SELECT tag_id FROM addons_tags WHERE addon_id={$addon_id}");
        if (!empty($_currentTags)) {
            foreach ($_currentTags as $currentTag) {
                $currentTags[] = $currentTag['addons_tags']['tag_id'];
            }
        }
        
        // Insert tags that aren't already there
        foreach ($newTags as $tag_id) {
            if (empty($currentTags) || (!empty($currentTags) && !in_array($tag_id, $currentTags))) {
                $this->execute("INSERT INTO addons_tags (addon_id, tag_id) VALUES({$addon_id}, {$tag_id})");
            }
        }
    }
    
    /**
     * same as above method but backported to 3.2
     * @deprecate
     */
    function LEGACY_saveCategories($addon_id, $data) {
        if (empty($data)) {
            return;
        }
        
        $newTags = $data;
        
        if (!empty($newTags)) {
            // Only delete tags that aren't still selected to retain additional
            // information, such as featured status
            $this->execute("DELETE FROM addons_tags WHERE addon_id={$addon_id} AND tag_id NOT IN (".implode(',', $newTags).")");
        }
        
        $_currentTags = $this->query("SELECT tag_id FROM addons_tags WHERE addon_id={$addon_id}");
        if (!empty($_currentTags)) {
            foreach ($_currentTags as $currentTag) {
                $currentTags[] = $currentTag['addons_tags']['tag_id'];
            }
        }
        
        // Insert tags that aren't already there
        foreach ($newTags as $tag_id) {
            if (!in_array($tag_id, $currentTags)) {
                $this->execute("INSERT INTO addons_tags (addon_id, tag_id) VALUES({$addon_id}, {$tag_id})");
            }
        }
    }

}
?>
