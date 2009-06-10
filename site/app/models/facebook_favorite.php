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
 *   Justin Scott <fligtar@mozilla.com> (Original Author)
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

class FacebookFavorite extends AppModel
{
    var $name = 'FacebookFavorite';
    var $tableName = 'facebook_favorites';

    var $belongsTo = array('Addon' =>
                         array('className'   => 'Addon',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'addon_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         )
                  );
    
    var $userFavorites = array();
    
   /**
    * Checks if the add-on is a favorite of the user.
    * Can optionally pull from cached list of favorites
    */
    function isFavorite($fbUser, $addon_id, $cache = false) {
        if ($cache) {
            if (empty($this->userFavorites)) {
                $this->getFavoriteIds($fbUser, true);
            }
            
            return in_array($addon_id, $this->userFavorites);
        }
        else {
            return $this->query("SELECT id FROM facebook_favorites WHERE addon_id='{$addon_id}' AND fb_user='{$fbUser}'");
        }
    }
    
   /**
    * Adds add-on as a favorite for the user
    */
    function addFavorite($fbUser, $addon_id, $imported = false) {
        $isImported = ($imported == true) ? 1 : 0;
        return $this->execute("INSERT INTO facebook_favorites (fb_user, addon_id, imported, created) VALUES('{$fbUser}', '{$addon_id}', {$isImported}, NOW())");
    }
    
   /**
    * Removes add-on as a favorite for the user
    */
    function removeFavorite($fbUser, $addon_id) {
        return $this->execute("DELETE FROM facebook_favorites WHERE addon_id='{$addon_id}' AND fb_user='{$fbUser}'");
    }
    
   /**
    * Gets add-on ids of favorite add-ons for the user
    */
    function getFavoriteIds($fbUser, $cache = false) {
        // If caching, check to see if already cached
        if ($cache && !empty($this->userFavorites)) {
            return $this->userFavorites;
        }
        
        $favorites = array();
        if ($addons = $this->query("SELECT addon_id FROM facebook_favorites WHERE fb_user='{$fbUser}'")) {
            foreach ($addons as $addon) {
                $favorites[] = $addon['facebook_favorites']['addon_id'];
            }
        }
        
        // If caching, save
        if ($cache) {
            $this->userFavorites = $favorites;
        }
        
        return $favorites;
    }
    
    function getFavoriteList($fbUser, $order = 'translations.localized_string') {
        return $this->query("SELECT
                                addons.id,
                                addons.icontype,
                                addons.modified,
                                facebook_favorites.imported,
                                translations.localized_string AS name
                             FROM facebook_favorites
                             INNER JOIN addons ON addons.id=facebook_favorites.addon_id
                             INNER JOIN translations ON addons.name=translations.id
                             WHERE
                                fb_user='{$fbUser}' AND
                                translations.locale='en-US'
                             ORDER BY {$order}");
    }
    
    function getDetailedFavoriteList($fbUser, $page = 0, $order = 'translations_name.localized_string') {
        if (!empty($page)) {
            $start = ($page - 1) * RESULTS_PER_PAGE;
            $limit = "LIMIT {$start}, ".RESULTS_PER_PAGE;
        }
        else
            $limit = '';
        
        return $this->query("SELECT
                                addons.id,
                                addons.icontype,
                                translations_name.localized_string AS name,
                                translations_summary.localized_string AS summary,
                                (SELECT COUNT(*) FROM previews WHERE previews.addon_id=addons.id) AS pcount
                             FROM facebook_favorites
                             INNER JOIN addons ON addons.id=facebook_favorites.addon_id
                             INNER JOIN translations AS translations_name ON addons.name=translations_name.id
                             INNER JOIN translations AS translations_summary ON addons.summary=translations_summary.id
                             WHERE
                                fb_user='{$fbUser}' AND
                                translations_name.locale='en-US' AND
                                translations_summary.locale='en-US'
                             ORDER BY {$order}
                             {$limit}
                             ", true);
    }
    
    function countFavorites($fbUser) {
        $addons = $this->query("SELECT COUNT(*) AS num FROM facebook_favorites WHERE fb_user='{$fbUser}'");
        return $addons[0][0]['num'];
    }
    
    function getFriendFavorites($friends, $page = 0) {
        if (!empty($page)) {
            $start = ($page - 1) * RESULTS_PER_PAGE;
            $limit = "LIMIT {$start}, ".RESULTS_PER_PAGE;
        }
        else
            $limit = '';
        
        return $this->query("SELECT
                            addons.id,
                            COUNT(*) AS fcount,
                            translations_name.localized_string AS name,
                            translations_summary.localized_string AS summary,
                            GROUP_CONCAT(facebook_favorites.fb_user) AS friends,
                            (SELECT COUNT(*) FROM previews WHERE previews.addon_id=addons.id) AS pcount
                        FROM facebook_favorites
                        INNER JOIN addons ON addons.id=facebook_favorites.addon_id
                        INNER JOIN translations AS translations_name ON addons.name=translations_name.id
                        INNER JOIN translations AS translations_summary ON addons.summary=translations_summary.id
                        WHERE
                            translations_name.locale='en-US' AND
                            translations_summary.locale='en-US' AND
                            facebook_favorites.fb_user IN ({$friends})
                        GROUP BY
                            addons.id
                        ORDER BY
                            fcount DESC,
                            addons.totaldownloads DESC
                        {$limit}
                        ", true);
    }
    
    function countFriendFavorites($friends) {
        $addons = $this->query("SELECT COUNT(*) AS num FROM facebook_favorites WHERE fb_user IN ({$friends}) GROUP BY addon_id", true);
        return count($addons);
    }

}
?>
