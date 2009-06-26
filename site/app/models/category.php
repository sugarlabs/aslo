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

class Category extends AppModel
{
    var $name = 'Category';
    var $hasAndBelongsToMany_full = array('Addon' =>
                                     array('className'  => 'Addon',
                                           'joinTable'  => 'addons_categories',
                                           'foreignKey' => 'category_id',
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
     * @param array $data category data from form
     */
    function saveCategories($addon_id, $data) {
        if (empty($data)) {
            return;
        }
        
        foreach ($data as $application_id => $selectedCategories) {
            foreach ($selectedCategories as $category_id) {
                if (!empty($category_id)) {
                    $newCategories[] = $category_id;
                }
            }
        }
        
        if (!empty($newCategories)) {
            // Only delete categories that aren't still selected to retain additional
            // information, such as featured status
            $this->execute("DELETE FROM addons_categories WHERE addon_id={$addon_id} AND category_id NOT IN (".implode(',', $newCategories).")");
        }
        
        $_currentCategories = $this->query("SELECT category_id FROM addons_categories WHERE addon_id={$addon_id}");
        if (!empty($_currentCategories)) {
            foreach ($_currentCategories as $currentCategory) {
                $currentCategories[] = $currentCategory['addons_categories']['category_id'];
            }
        }
        
        // Insert categories that aren't already there
        foreach ($newCategories as $category_id) {
            if (empty($currentCategories) || (!empty($currentCategories) && !in_array($category_id, $currentCategories))) {
                $this->execute("INSERT INTO addons_categories (addon_id, category_id) VALUES({$addon_id}, {$category_id})");
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
        
        $newCategories = $data;
        
        if (!empty($newCategories)) {
            // Only delete categories that aren't still selected to retain additional
            // information, such as featured status
            $this->execute("DELETE FROM addons_categories WHERE addon_id={$addon_id} AND category_id NOT IN (".implode(',', $newCategories).")");
        }
        
        $_currentCategories = $this->query("SELECT category_id FROM addons_categories WHERE addon_id={$addon_id}");
        if (!empty($_currentCategories)) {
            foreach ($_currentCategories as $currentCategory) {
                $currentCategories[] = $currentCategory['addons_categories']['category_id'];
            }
        }
        
        // Insert categories that aren't already there
        foreach ($newCategories as $category_id) {
            if (!in_array($category_id, $currentCategories)) {
                $this->execute("INSERT INTO addons_categories (addon_id, category_id) VALUES({$addon_id}, {$category_id})");
            }
        }
    }

}
?>
