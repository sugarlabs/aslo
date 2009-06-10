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
class NewsfeedComponent extends Object {
    var $controller;
    
   /**
    * Save a reference to the controller on startup
    * @param object &$controller the controller using this component
    */
    function startup(&$controller) {
        $this->controller =& $controller;
    }
    
   /**
    * Generates add-on newsfeed for user
    */
    function generate($fbUser, $friends, $favorites, $days = 7) {
        $newsfeed = array();
        
        $newVersionsOfFavorites = $this->_getNewVersionsOfFavorites($favorites, $days);
        $friendFavoriteUpdates = $this->_getFriendFavoriteUpdates($friends, $days);
        $f_recommendations = $this->_getFriendRecommendations($friends, $favorites);
        $newUsers = $this->_getNewFriendUsers($friends, $days);
        //$s_recommendations = $this->_getSimilarRecommendations($fbUser, $favorites);
        
        // Include up to 2 new user stories
        $newsfeed = array_merge($newsfeed, $this->_pullStories($newUsers, 2));
        
        // Include up to 2 new version stories
        $newsfeed = array_merge($newsfeed, $this->_pullStories($newVersionsOfFavorites, 2));
        
        // Include 1 friend recommendation
        $newsfeed = array_merge($newsfeed, $this->_pullStories($f_recommendations, 1));
        
        // Fill the rest with favorite updates
        $newsfeed = array_merge($newsfeed, $this->_pullStories($friendFavoriteUpdates, (10 - count($newsfeed))));
        
        // randomize stories
        shuffle($newsfeed);
        
        return $newsfeed;
    }
    
    function _pullStories($stories, $number) {
        // If no stories, return none
        if (empty($stories)) {
            return array();
        }
        
        // If more stories requested than available, return all stories
        if (count($stories) < $number) {
            return $stories;
        }
        
        // Otherwise, pull the appropriate number of stories randomly
        $newsfeed = array();
        $rand = array_rand($stories, $number);
        if (is_array($rand)) {
            foreach ($rand as $key) {
                $newsfeed[] = $stories[$key];
            }
        }
        else {
            $newsfeed[] = $stories[$rand];
        }
        
        return $newsfeed;        
    }
    
   /**
    * Gets stories for any new versions of the user's favorite add-ons
    */
    function _getNewVersionsOfFavorites($favorites, $days) {
        // If no favorites, return
        if (empty($favorites))
            return array();
        
        $stories = array();
        
        $favorites_string = implode(',', $favorites);
        
        $results = $this->controller->Addon->query("
                SELECT
                    addons.id,
                    versions.version,
                    files.datestatuschanged,
                    translations.localized_string AS name
                FROM addons
                INNER JOIN versions ON addons.id=versions.addon_id
                INNER JOIN files ON versions.id=files.version_id
                INNER JOIN translations ON addons.name=translations.id
                WHERE
                    addons.id IN ({$favorites_string}) AND
                    files.status = 4 AND
                    translations.locale = 'en-US' AND
                    files.datestatuschanged >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                GROUP BY addons.id
                ORDER BY files.datestatuschanged DESC
            ", true);
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $stories[] = array(
                    'type' => 'story',
                    'icon' => 'newversion.png',
                    'story' => 'Version '.$result['versions']['version'].' of <a href="'.FB_URL.'/view/'.$result['addons']['id'].'?ref=afnv">'.$result['translations']['name'].'</a> is now available.',
                    'timestamp' => $result['files']['datestatuschanged']
                    );
            }
        }
        
        return $stories;
    }

   /**
    * Gets friends that recently added the app
    */
    function _getNewFriendUsers($friends, $days) {
        // If no friends, return
        if (empty($friends))
            return array();
        
        $stories = array();
        
        $results = $this->controller->FacebookFavorite->query("
                SELECT
                    fb_user
                FROM facebook_users
                WHERE
                    added >= DATE_SUB(NOW(), INTERVAL {$days} DAY) AND
                    fb_user IN ({$friends}) AND
                    removed = '0000-00-00 00:00:00'
                ORDER BY added DESC
                LIMIT 5                
            ", true);
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $stories[] = array(
                    'type' => 'story',
                    'icon' => 'addedfavorite.png',
                    'story' => '<a href="'.FB_URL.'/favorites/user/'.$result['facebook_users']['fb_user'].'"><fb:name uid="'.$result['facebook_users']['fb_user'].'" linked="false" shownetwork="false" /></a>  added the Rock Your Firefox application.'
                    );
            }
        }
        
        return $stories;
    }
    
   /**
    * Gets updates for any friend activity
    */
    function _getFriendFavoriteUpdates($friends, $days) {
        // If no friends, return
        if (empty($friends))
            return array();
        
        $stories = array();
        
        $results = $this->controller->FacebookFavorite->query("
                SELECT
                    addons.id,
                    GROUP_CONCAT(facebook_favorites.fb_user) AS friends,
                    facebook_favorites.created,
                    translations.localized_string AS name
                FROM facebook_favorites
                INNER JOIN addons ON facebook_favorites.addon_id=addons.id
                INNER JOIN translations ON translations.id=addons.name
                WHERE
                    facebook_favorites.created >= DATE_SUB(NOW(), INTERVAL {$days} DAY) AND
                    translations.locale = 'en-US' AND
                    facebook_favorites.fb_user IN ({$friends})
                GROUP BY addons.id
                ORDER BY facebook_favorites.created DESC
                LIMIT 5                
            ", true);
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $ffriends = explode(',', $result[0]['friends']);
                if (count($ffriends) == 1) {
                    $possessive = '<fb:pronoun uid="'.$ffriends[0].'" possessive="true" />';
                    $names = '<a href="'.FB_URL.'/favorites/user/'.$ffriends[0].'"><fb:name uid="'.$ffriends[0].'" linked="false" /></a>';
                }
                else {
                    $possessive = 'their';
                    if (count($ffriends) == 2) {
                        $names = '<a href="'.FB_URL.'/favorites/user/'.$ffriends[0].'"><fb:name uid="'.$ffriends[0].'" linked="false" /></a>';
                        $names .= ' and <a href="'.FB_URL.'/favorites/user/'.$ffriends[1].'"><fb:name uid="'.$ffriends[1].'" linked="false" /></a>';
                    }
                    else {
                        $names = '<a href="'.FB_URL.'/favorites/addon/'.$result['addons']['id'].'">'.count($ffriends).' friends</a>';
                    }
                }
                
                $stories[] = array(
                    'type' => 'story',
                    'icon' => 'addedfavorite.png',
                    'story' => $names.' added <a href="'.FB_URL.'/view/'.$result['addons']['id'].'?ref=aff">'.$result['translations']['name'].'</a> to '.$possessive.' favorite add-ons.',
                    'timestamp' => $result['facebook_favorites']['created']
                    );
            }
        }
        
        return $stories;
    }
    
   /**
    * Tries to recommend add-ons based on friends' favorites
    */
    function _getFriendRecommendations($friends, $favorites) {
        // If no friends, return
        if (empty($friends))
            return array();
        
        $stories = array();
        $favorites_string = implode(',', $favorites);
        
        $results = $this->controller->FacebookFavorite->query("
                    SELECT
                        addons.id,
                        COUNT(*) AS favorited,
                        translations_n.localized_string AS name,
                        translations_s.localized_string AS summary,
                        (SELECT COUNT(*) FROM previews WHERE previews.addon_id=addons.id) AS pcount
                    FROM facebook_favorites
                    INNER JOIN addons ON facebook_favorites.addon_id=addons.id
                    INNER JOIN translations AS translations_n ON translations_n.id=addons.name
                    INNER JOIN translations AS translations_s ON translations_s.id=addons.summary
                    WHERE
                        translations_n.locale = 'en-US' AND
                        translations_s.locale = 'en-US' AND
                        facebook_favorites.fb_user IN ({$friends}) AND
                        ".(!empty($favorites_string) ? "addons.id NOT IN ({$favorites_string}) AND" : "")."
                        addons.inactive = 0 AND
                        addons.status = ".STATUS_PUBLIC."
                    GROUP BY addons.id
                    ORDER BY favorited DESC
                    LIMIT 5
                    ", true);
        
        if (!empty($results)) {
            foreach ($results as $result) {
                if ($result[0]['favorited'] > 1) {
                    $stories[] = array(
                        'type' => 'fullstory',
                        'icon' => 'recommendation.png',
                        'story' => '<span class="title">Recommendation:</span> <a href="'.FB_URL.'/favorites/addon/'.$result['addons']['id'].'?ref=affr">'.$result[0]['favorited'].' of your friends</a> like <a href="'.FB_URL.'/view/'.$result['addons']['id'].'">'.$result['translations_n']['name'].'</a>. Have you tried it?',
                        'body' => $this->controller->_trimSummary($result['translations_s']['summary'], 200),
                        'image' => ($result[0]['pcount'] > 0 ? FB_IMAGE_SITE.'/images/addon_preview/'.$result['addons']['id'].'/1' : ''),
                        'image_url' => FB_URL.'/view/'.$result['addons']['id'].'?ref=affri'
                        );
                }
            }
        }
        
        return $stories;
    }
    
   /**
    * Tries to recommend add-ons based on similar users' favorites
    * As in, "people who had these add-ons favorited also liked ____"
    */
    /*function _getSimilarRecommendations($fbUser, $favorites) {
        // If no favorites, return
        if (empty($favorites))
            return array();
        
        $stories = array();
        $favorites_string = implode(',', $favorites);
        
        // Get top 2 favorited add-ons that user has favorited
        $top2 = $this->controller->FacebookFavorite->query("
                    SELECT
                        addon_id,
                        COUNT(*) AS favorited
                    FROM facebook_favorites
                    WHERE
                        addon_id IN ({$favorites_string})
                    GROUP BY addon_id
                    ORDER BY favorited DESC
                    LIMIT 2
                    ", true);
        
        pr($top2);
        
        // Find other users who have favorited all 2
        $similarUsers = $this->controller->FacebookFavorite->query("
                    SELECT
                        fb.fb_user
                    FROM facebook_favorites AS fb
                    LEFT JOIN facebook_favorites AS addon1 ON addon1.fb_user=fb.fb_user
                    LEFT JOIN facebook_favorites AS addon2 ON addon2.fb_user=fb.fb_user
                    WHERE
                        addon1.addon_id = {$top2[0]['facebook_favorites']['addon_id']} AND
                        addon2.addon_id = {$top2[1]['facebook_favorites']['addon_id']}
                    GROUP BY fb.fb_user
                    ", true);
        
        pr($similarUsers);

        if (!empty($results)) {
            foreach ($results as $result) {
                $stories[] = array(
                    'icon' => 'favorite_recommend.png',
                    'story' => '<b>Recommendation:</b> <a href="'.FB_URL.'/favorites/addon/'.$result['addons']['id'].'">'.$result[0]['favorited'].'</a> of your friends like <a href="'.FB_URL.'/view/'.$result['addons']['id'].'">'.$result['translations']['name'].'</a>. Have you tried it?',
                    'timestamp' => 0
                    );
            }
        }
        
        return $stories;
    }*/
    
   /**
    * Counts new add-ons since last favorite added
    */
    function getNewAddonCount($fbUser) {        
        // Get date of last added favorite
        $lastadded = $this->controller->FacebookFavorite->query("
                        SELECT
                            facebook_favorites.created
                        FROM facebook_favorites
                        WHERE
                            facebook_favorites.fb_user = '{$fbUser}'
                        ORDER BY facebook_favorites.created DESC
                        LIMIT 1
                    ");
        
        if (!empty($lastadded)) {
            $daysSince = (time() - strtotime($lastadded[0]['facebook_favorites']['created'])) / 86400;
            
            if (!empty($lastadded) && $daysSince >= 14) {
                // Get count of add-ons added after last favorite
                $count = $this->controller->Addon->query("
                            SELECT
                                COUNT(*) as num
                            FROM addons
                            WHERE
                                created > '{$lastadded[0]['facebook_favorites']['created']}'
                            ", true);
                    
                return 'There are '.$count[0][0]['num'].' new add-ons since you last added a favorite. <a href="'.FB_URL.'/browse?ref=afsb">Find your next favorite!</a>';
            }
        }
        
        return '';
    }
}
?>
