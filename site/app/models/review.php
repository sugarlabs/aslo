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
 *   Les Orchard <lorchard@mozilla.com>
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

class Review extends AppModel
{
    var $name = 'Review';
    var $belongsTo = array('Version' =>
                         array('className'  => 'Version',
                               'conditions' => '',
                               'order'      => '',
                               'foreignKey' => 'version_id'
                         ),
                         'User' =>
                         array('className'  => 'User',
                               'conditions' => '',
                               'order'      => '',
                               'foreignKey' => 'user_id'
                         )
                   );
    var $validate = array(
        'rating' => VALID_NUMBER,
        'title'  => VALID_NOT_EMPTY,
        'body'   => VALID_NOT_EMPTY
    );

    var $translated_fields = array(
        'title',
        'body'
    );
    
    /**
     * For a given list of review ids, return them with all translations
     * that are available for each review.
     *
     * @param array $reviewids List of ids for the reviews to be returned
     * @param bool $includeAddon include the add-on ID each review belongs to in the result set?
     */
    function getReviews($reviewids, $includeAddon = false) {
        global $rtl_languages;
        
        // disable automatic translation so that translations don't get fetched twice
        $trfields_old = $this->translated_fields;
        $this->translated_fields = array();
        
        if (empty($reviewids)) return array(); // nothing to do
        if (!is_array($reviewids)) $reviewids = array($reviewids);
        
        // This needs to be a manual query because of bug 442208.
        $reviews = $this->query("
            SELECT 
                `Review`.`id`, `Review`.`version_id`, `Review`.`body`, `Review`.`title`, Review.reply_to,
                `Review`.`created`, `Review`.`rating`, `User`.`id`, `User`.`nickname`,
                `User`.`firstname`, `User`.`lastname` FROM `reviews` AS `Review` LEFT JOIN
                `versions` AS `Version` ON (`Review`.`version_id` = `Version`.`id`) LEFT JOIN
                `users` AS `User` ON (`Review`.`user_id` = `User`.`id`) WHERE `Review`.`id` IN
                (".implode(',', $reviewids).") ORDER BY FIELD(`Review`.`id`,".implode(',', $reviewids).") ASC
        ");
        
        if (!empty($reviews)) {
            // we need the Translation model to pull in reviews in all locales
            loadModel('Translation');
            $this->Translation =& new Translation();
            foreach ($reviews as $_id => $_review) {
                $reviews[$_id]['Translation'] = array();
                // for each translated field, fetch all its translations.
                foreach ($trfields_old as $field) {
                    $translations = $this->Translation->findAll(array(
                        'Translation.id' => $_review['Review'][$field],
                        'Translation.localized_string IS NOT NULL'
                        ), null, "FIELD(Translation.locale,'".$this->getLang()."') DESC");
                    
                    // add the translations found to the reviews array
                    if (!empty($translations)) {
                        foreach($translations as $_trans) {
                            $_temp = array();
                            $_temp['string'] = $_trans['Translation']['localized_string'];
                            $_temp['locale'] = $_trans['Translation']['locale'];
                            $_temp['textdir'] = in_array($_trans['Translation']['locale'], $rtl_languages) ? 'rtl' : 'ltr';
                            if (empty($_trans['Translation']['locale']) || $this->getLang() == $_trans['Translation']['locale']) {
                                $_temp['locale_html'] = '';
                            } else {
                                $_temp['locale_html'] = ' lang="'.$_trans['Translation']['locale'].'" dir="'.$_temp['textdir'].'" ';
                            }
                            if (!empty($_trans['Translation']['locale']))
                                $reviews[$_id]['Translation'][$_temp['locale']][$field] = $_temp;
                        }
                    }
                }
                
                // include add-on this review belongs to, if applicable
                if ($includeAddon) {
                    if (!isset($this->Version)) {
                        loadModel('Version');
                        $this->Version =& new Version();
                    }
                    $_addonid = $this->Version->find("Version.id = {$_review['Review']['version_id']}",
                        'Version.addon_id');
                    $reviews[$_id]['Review']['addon_id'] = $_addonid['Version']['addon_id'];
                }
            }
        }
        // reset automatic translation
        $this->translated_fields = $trfields_old;
        return $reviews;
    }

    /**
     * For a given addon, return the count of users who submitted reviews.
     *
     * @param  numeric The addon ID
     * @return numeric Number of users with submitted reviews
     */
    function countLatestReviewsForAddon($addon_id) {

        // Prevent SQL injection here with a quick numeric check on the parameters.
        if (!is_numeric($addon_id))
            return array();

        $rows = $this->query("
            SELECT 
               COUNT(DISTINCT reviews.user_id) AS count 
            FROM 
               reviews
            INNER JOIN 
               versions ON reviews.version_id = versions.id 
            WHERE 
               reviews.reply_to IS NULL AND 
               reviews.version_id=versions.id AND 
               versions.addon_id=$addon_id 
        ");

        return ( $rows ) ? $rows[0][0]['count'] : 0;
    }

    /**
     * For a given addon, build a list of latest reviews per user in reverse
     * chronological order.  Only the ID of the latest single review for each 
     * user will be returned, pared with a count of earlier reviews also 
     * submitted by the user for the given addon.
     *
     * @param numeric The addon ID
     * @param numeric Optional limit per page of results
     * @param numeric Optional page within set of results
     * @return array  List of review records with 'id' and 'others_count'
     */
    function findLatestReviewsForAddon($addon_id, $limit=10, $page=1) {

        // Prevent SQL injection here with a quick numeric check on the parameters.
        if (!is_numeric($addon_id) || !is_numeric($limit) || !is_numeric($page)) 
            return array();

        /** 
         * This custom MySQL query uses some GROUP BY sleight of hand to come
         * up with only the latest review submitted for each user, while sorting
         * the whole list by that latest review's creation date.  A count of
         * earlier submitted reviews is also derived.
         */
        $rows = $this->query("
            SELECT 
               SUBSTRING_INDEX( GROUP_CONCAT( r.id ORDER BY r.created DESC SEPARATOR ';'), ';', 1 ) AS latest_id, 
               SUBSTRING_INDEX( GROUP_CONCAT( r.created ORDER BY r.created DESC SEPARATOR ';'), ';', 1 ) AS latest_created, 
               COUNT(r.id)-1 AS others_count 
            FROM 
               reviews r
            INNER JOIN 
               versions v ON r.version_id = v.id 
            WHERE 
               r.reply_to IS NULL AND 
               v.addon_id=$addon_id
            GROUP BY r.user_id 
            ORDER BY latest_created DESC 
            LIMIT $limit OFFSET " . ( $limit * max(0, $page - 1) )
        );

        // Simplify the DB rows for easier use in the controller.
        $results = array();
        foreach ($rows as $row) {
            $results[] = array(
                'id' => 
                    $row[0]['latest_id'],
                'others_count' => 
                    $row[0]['others_count']
            );
        }
        return $results;
    }
    
    /**
     * Update the bayesian rating (cf. bug 477343) for a single (or multiple) add-on(s).
     * Also updates average rating and total review count.
     *
     * Note that similar code exists in the reviews/ratings sections of bin/maintenance.php
     *
     * @param array addonids add-on IDs whose bayesian ratings to update.
     * @return boolean success
     */
    function updateBayesianRating($addonids = array()) {
        if (empty($addonids)) return false;
        
        // get average review count and average rating
        $rows = $this->query("
            SELECT AVG(a.cnt) AS avg_cnt 
            FROM (
                SELECT COUNT(*) AS cnt
                FROM reviews AS r
                INNER JOIN versions AS v ON (r.version_id = v.id)
                WHERE reply_to IS NULL
                    AND rating > 0
                GROUP BY v.addon_id
                ) AS a
        ");
        $avg_num_votes = $rows[0][0]['avg_cnt'];
        
        $rows = $this->query("
            SELECT AVG(a.addon_rating) AS avg_rating 
            FROM (
                SELECT AVG(rating) AS addon_rating
                FROM reviews AS r
                INNER JOIN versions AS v ON (r.version_id = v.id)
                WHERE reply_to IS NULL
                    AND rating > 0
                GROUP BY v.addon_id
                ) AS a
        ");
        $avg_rating = $rows[0][0]['avg_rating'];
        
        // update total review count and average rating
        $this->query("
            UPDATE addons AS a
            INNER JOIN (
                SELECT
                    versions.addon_id as addon_id,
                    COUNT(*) as count,
                    AVG(rating) as avg_rating
                FROM reviews 
                INNER JOIN versions ON reviews.version_id = versions.id 
                WHERE reviews.reply_to IS NULL
                    AND versions.addon_id IN (".(implode(',',$addonids)).")
                    AND reviews.rating > 0
                GROUP BY versions.addon_id 
            ) AS c ON (a.id = c.addon_id)
            SET a.totalreviews = c.count,
            a.averagerating = ROUND(c.avg_rating, 2)
            WHERE a.id IN (".(implode(',',$addonids)).")
        ");
        
        // calculate and store bayesian rating
        $this->query("
            UPDATE addons AS a
            SET a.bayesianrating =
                IF (a.totalreviews > 0, (
                    ( ({$avg_num_votes} * {$avg_rating}) + (a.totalreviews * a.averagerating) ) /
                    ({$avg_num_votes} + a.totalreviews)
                ), 0)
            WHERE a.id IN (".(implode(',',$addonids)).")
        ");
        return true;
    }

}
?>
