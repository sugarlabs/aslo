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
 *   Mike Morgan <morgamic@mozilla.com>
 *   Andrei Hajdukewycz <sancus@off.net>
 *   Justin Scott <fligtar@mozilla.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *   Les Orchard <lorchard@mozilla.com>
 *   Wil Clouser <wclouser@mozilla.com>
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
 * Maintenance script for addons.mozilla.org.
 *
 * The purpose of this document is to perform periodic tasks that should not be
 * done everytime a download occurs in install.php.  This should reduce
 * unnecessary DELETE and UPDATE queries and lighten the load on the database
 * backend.
 *
 * This script should not ever be accessed over HTTP, and instead run via cron.
 * Only sysadmins should be responsible for operating this script.
 *
 * @package amo
 * @subpackage bin
 */


// Before doing anything, test to see if we are calling this from the command
// line.  If this is being called from the web, HTTP environment variables will
// be automatically set by Apache.  If these are found, exit immediately.
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

require_once('database.class.php');

/**
 * If you want to change some data, use $db->write.  It talks to the master db.
 * If you're just reading, use $db->read.  It talks to a slave.
 */


/**
 *  * Get time as a float.
 *   * @return float
 *    */
function getmicrotime() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

// Start our timer.
$start = getmicrotime();

// New database class
$db = new Database();

// Get our action.
$action = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';

// Used to count stats.  This should be a global but was moved inside the add-ons controller
// temporarily apparently.  Also see comment in the add-ons controller above the assignment of
// the similarly named variable.
$link_sharing_services = array('digg','facebook','delicious','myspace','friendfeed','twitter');

// Perform specified task.  If a task is not properly defined, exit.
switch ($action) {

    /**
     * Update weekly addon counts.
     */
    case 'weekly':
        // Lock stats dashboard
        $db->lockStats();

        $seven_day_counts = array();

        $addons_sql = "SELECT id FROM addons";
        debug("Retrieving all add-on ids...");
        $addons_result = $db->read($addons_sql);

        $affected_rows = mysql_num_rows($addons_result);

        if ($affected_rows > 0 ) {
            while ($row = mysql_fetch_array($addons_result)) {
                $seven_day_counts[$row['id']] = 0;
            }
        }

        // Get 7 day counts from the download table.
        $seven_day_count_sql = "
            SELECT
                download_counts.addon_id as addon_id,
                SUM(download_counts.count) as seven_day_count
            FROM
                `download_counts`
            WHERE
                `date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY
                download_counts.addon_id
            ORDER BY
                download_counts.addon_id
        ";

        debug('Retrieving seven-day counts from `download_counts` ...');
        $seven_day_count_result = $db->read($seven_day_count_sql);

        $affected_rows = mysql_num_rows($seven_day_count_result);

        if ($affected_rows > 0 ) {
            while ($row = mysql_fetch_array($seven_day_count_result)) {
                $seven_day_counts[$row['addon_id']] = ($row['seven_day_count']>0) ? $row['seven_day_count'] : 0;
            }

            debug('Updating seven day counts in `main` ...');

            foreach ($seven_day_counts as $id=>$seven_day_count) {
                $seven_day_count_update_sql = "
                    UPDATE `addons` SET `weeklydownloads`='{$seven_day_count}' WHERE `id`='{$id}'
                ";

                $seven_day_count_update_result =
                $db->write($seven_day_count_update_sql);
            }
        }

        // Unlock stats dashboard
        $db->unlockStats();
    break;

    /**
     * Update total addon counts.
     */
    case 'total':
        // Lock stats dashboard
        $db->lockStats();

        // Get total counts from the download table.
        $total_count_sql = "
            SELECT
                download_counts.addon_id as addon_id,
                AVG(download_counts.count) as avg_count,
                SUM(download_counts.count) as total_count
            FROM
                `download_counts`
            GROUP BY
                download_counts.addon_id
            ORDER BY
                download_counts.addon_id
        ";

        debug('Retrieving total counts from `download_counts` ...');
        $total_count_result = $db->read($total_count_sql);

        $affected_rows = mysql_num_rows($total_count_result);

        if ($affected_rows > 0 ) {
            $counts = array();
            while ($row = mysql_fetch_array($total_count_result)) {
                $counts[$row['addon_id']]['total']   = ($row['total_count'] > 0) ? $row['total_count'] : 0;
                $counts[$row['addon_id']]['average'] = ($row['avg_count'] > 0) ? $row['avg_count'] : 0;
            }

            foreach ($counts as $id => $count) {
                $total_count_update_sql = "
                    UPDATE `addons` SET `totaldownloads`='{$count['total']}', `average_daily_downloads`='{$count['average']}' WHERE `id`='{$id}'
                ";

                $total_count_update_result =
                $db->write($total_count_update_sql);
            }
        }

        // Unlock stats dashboard
        $db->unlockStats();
    break;

    case 'ADU':
        // Lock stats dashboard
        $db->lockStats();

        // Get total counts from the download table.
        $adu_count_sql = "
            SELECT
                update_counts.addon_id as addon_id,
                AVG(update_counts.count) as avg_count
            FROM
                `update_counts`
            GROUP BY
                update_counts.addon_id
            ORDER BY
                update_counts.addon_id
        ";

        debug('Retrieving ADU counts from `update_counts` ...');
        $adu_count_result = $db->read($adu_count_sql);

        $affected_rows = mysql_num_rows($adu_count_result);

        if ($affected_rows > 0 ) {
            $counts = array();
            while ($row = mysql_fetch_array($adu_count_result)) {
                $counts[$row['addon_id']] = ($row['avg_count'] > 0) ? $row['avg_count'] : 0;
            }

            foreach ($counts as $id => $count) {
                $adu_count_update_sql = "
                    UPDATE `addons` SET `average_daily_users`='{$count}'  WHERE `id`='{$id}'
                ";

                $adu_count_update_result =
                $db->write($adu_count_update_sql);
            }
        }

        // Unlock stats dashboard
        $db->unlockStats();
    break;

    /**
     * Garbage collection for all records that are older than 8 days.
     */
    case 'gc':
        debug('Starting garbage collection...');
        $affected_rows = 0;

        debug('Cleaning up sessions table...');
        $session_sql = "
            DELETE FROM
                `cake_sessions`
            WHERE
                `expires` < DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        ";
        $session_result = $db->write($session_sql);
        $affected_rows += mysql_affected_rows($db->write);

        debug('Cleaning up sharing services...');
        $sharing_sql = "
            DELETE FROM
                `stats_share_counts`
            WHERE
                service NOT IN ('".implode("','",$link_sharing_services)."')
            ";
        $db->write($sharing_sql);
        $affected_rows += mysql_affected_rows($db->write);

        debug('Cleaning up test results cache...');
        $results_sql = "
            DELETE FROM
                `test_results_cache`
            WHERE
                date < DATE_SUB(CURDATE(), INTERVAL 1 HOUR)
            ";
        $db->write($sharing_sql);
        $affected_rows += mysql_affected_rows($db->write);

        debug('Cleaning up the Test Results extraction cache...');
        $location  = escapeshellarg(NETAPP_STORAGE);
        $result = shell_exec('find '.$location.' -maxdepth 1 -name "validate-*" -mtime +7 -type d -exec rm -rf \'{}\' \;');
        if (!empty($result)) {
            foreach ($result as $line) {
                debug($line);
            }
        }

        // Paypal only keeps retrying to verify transactions for up to 3 days.  If we still have an
        // unverified transaction after 6 days, we might as well get rid of it.
        debug('Cleaning up outdated contributions statistics...');
        $stats_sql = "
            DELETE FROM
                `stats_contributions`
            WHERE
                `transaction_id` IS NULL
            AND
                created < DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            ";
        $db->write($stats_sql);
        $affected_rows += mysql_affected_rows($db->write);

    break;



    /**
     * Copy all public files to the public repository.
     * If files already exist, overwrite them.
     */
    case 'publish_files':
        debug('Starting public file copy...');

        $files_sql = "
            SELECT DISTINCT
                addons.id as addon_id,
                files.filename as filename
            FROM
                versions
            INNER JOIN addons ON versions.addon_id = addons.id AND addons.status = 4 AND addons.inactive = 0
            INNER JOIN files ON files.version_id = versions.id AND files.status = 4
            ORDER BY
                addons.id DESC
        ";

        // Get file names and IDs of all files to copy.
        $files_result = $db->read($files_sql);

        $affected_rows = 0;

        while ($row = mysql_fetch_array($files_result)) {
            // For each valid file, copy it from REPO_PATH to STAGING_PUBLIC_PATH.
            if (copyFileToPublic($row['addon_id'],$row['filename'],true)) {
                debug('Copy SUCCEEDED for add-on '.$row['addon_id'].' file '.$row['filename']);
            } else {
                debug('Copy FAILED for add-on '.$row['addon_id'].' file '.$row['filename'], true);
            }
        }

    break;



    /**
     * Get review totals and update addons table.
     */
    case 'reviews':
        debug('Starting review total updates...');

        $reviews_sql = "
            UPDATE addons AS a
            INNER JOIN (
                SELECT
                    versions.addon_id as addon_id,
                    COUNT(*) as count
                FROM reviews
                INNER JOIN versions ON reviews.version_id = versions.id
                WHERE reviews.reply_to IS NULL
                    AND reviews.rating > 0
                GROUP BY versions.addon_id
            ) AS c ON (a.id = c.addon_id)
            SET a.totalreviews = c.count
        ";
        $reviews_result = $db->write($reviews_sql);

        $affected_rows = mysql_affected_rows();

    break;


    case 'user_ratings':
        debug("Updating user ratings...");

        global $valid_status;
        $status = join(",", $valid_status);

        $ooh = "
          UPDATE users INNER JOIN (
              SELECT
                addons_users.user_id as user_id,
                AVG(rating) as avg_rating
              FROM reviews
                INNER JOIN versions
                INNER JOIN addons_users
                INNER JOIN addons
              ON reviews.version_id = versions.id
                AND addons.id = versions.addon_id
                AND addons_users.addon_id = addons.id
              WHERE reviews.reply_to IS NULL
                AND reviews.rating > 0
                AND addons.status IN ({$status})
              GROUP BY addons_users.user_id
          ) AS J ON (users.id = J.user_id)
          SET users.averagerating = ROUND(J.avg_rating, 2)
        ";
        $db->write($ooh);
        $affected_rows = mysql_affected_rows();
    break;


    /**
     * Get average ratings and update addons table.
     */
    case 'ratings':
        debug('Updating average ratings...');
        $rating_sql = "
            UPDATE addons AS a
            INNER JOIN (
                SELECT
                    versions.addon_id as addon_id,
                    AVG(rating) as avg_rating
                FROM reviews
                INNER JOIN versions ON reviews.version_id = versions.id
                WHERE reviews.reply_to IS NULL
                    AND reviews.rating > 0
                GROUP BY versions.addon_id
            ) AS c ON (a.id = c.addon_id)
            SET a.averagerating = ROUND(c.avg_rating, 2)
        ";
        $rating_result = $db->write($rating_sql);

        debug('Updating bayesian ratings...');
        // get average review count and average rating
        $rows = $db->read("
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
        $row = mysql_fetch_array($rows);
        $avg_num_votes = $row['avg_cnt'];

        $rows = $db->read("
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
        $row = mysql_fetch_array($rows);
        $avg_rating = $row['avg_rating'];

        // calculate and store bayesian rating
        $rating_sql = "
            UPDATE addons AS a
            SET a.bayesianrating =
                IF (a.totalreviews > 0, (
                    ( ({$avg_num_votes} * {$avg_rating}) + (a.totalreviews * a.averagerating) ) /
                    ({$avg_num_votes} + a.totalreviews)
                ), 0)
        ";
        $rating_result = $db->write($rating_sql);

        $affected_rows = mysql_affected_rows();

    break;



    /**
     * Delete user accounts that have not been confirmed for two weeks
     */
    case 'unconfirmed':
        debug("Removing user accounts that haven't been confirmed for two weeks...");
        $unconfirmed_sql = "
            DELETE users
            FROM users
            LEFT JOIN addons_users on users.id = addons_users.user_id
            WHERE created < DATE_SUB(CURDATE(), INTERVAL 2 WEEK)
            AND confirmationcode != ''
            AND addons_users.user_id IS NULL
        ";
        $res = $db->write($unconfirmed_sql);

        $affected_rows = mysql_affected_rows();
    break;



    /**
     * Delete password reset codes that have expired.
     */
    case 'expired_resetcode':
        debug("Removing reset codes that have expired...");
        $db->write("UPDATE users
                    SET resetcode=DEFAULT,
                        resetcode_expires=DEFAULT
                    WHERE resetcode_expires < NOW()");
        $affected_rows = mysql_affected_rows();
    break;



    /**
     * Update addon-collection download totals.
     */
    case 'addons_collections_total':
        debug("Starting addon_collection totals update...");
        $addons_collections_total = "
            UPDATE
                addons_collections AS ac
            INNER JOIN (
                SELECT
                    stats.addon_id as addon_id,
                    stats.collection_id as collection_id,
                    SUM(stats.count) AS sum
                FROM
                    stats_addons_collections_counts AS stats
                GROUP BY
                    stats.addon_id, stats.collection_id
            ) AS j ON (ac.addon_id = j.addon_id AND
                       ac.collection_id = j.collection_id)
            SET
                ac.downloads = j.sum
        ";
        $db->write($addons_collections_total);
        $affected_rows = mysql_affected_rows();
    break;



    /**
     * Update collection downloads total.
     */
    case 'collections_total':
        debug("Starting collection totals update...");
        $collections_sql = "
            UPDATE
                collections AS c
            INNER JOIN (
                SELECT
                    stats.collection_id AS collection_id,
                    SUM(stats.count) AS sum
                FROM
                    stats_collections_counts AS stats
                GROUP BY
                    stats.collection_id
            ) AS j ON (c.id = j.collection_id)
            SET
                c.downloads = j.sum
        ";
        $db->write($collections_sql);
        $affected_rows = mysql_affected_rows();
    break;

    case 'collections_ratings':
        debug('Starting collection rating calculations...');
        /* Multiplying by the log of the total number of votes so collections
         * with lots of votes have precedence over those with a few votes, but
         * taking the log so the mulitplier isn't ridiculous.
         */
        $db->write("
            UPDATE collections
            SET rating=
              IFNULL(
                CAST(upvotes - downvotes AS SIGNED) * LN(upvotes + downvotes),
                0)
        ");
        $affected_rows = mysql_affected_rows();


    /**
     * Update share count totals.
     */
    case 'share_count_totals':
        debug("Starting share count totals update...");

        $affected_rows = 0;
        $rows = $db->read("SELECT addon_id, SUM(count) as sum, service from stats_share_counts GROUP BY addon_id, service");
        while ($row = mysql_fetch_array($rows)) {
            if (in_array($row['service'], $link_sharing_services)) {
                $db->write("REPLACE INTO stats_share_counts_totals (addon_id,service,count) VALUES ({$row['addon_id']}, '{$row['service']}',{$row['sum']})");
                $affected_rows++;
            }
        }
    break;


    /**
     * Update category counts for sidebar navigation
     */
    case 'category_totals':
        debug("Starting category counts update...");

        global $valid_status;
        $valid_status = join(',', $valid_status);

        // Modified query inspired by countAddonsInAllCategories()
        // in site/app/models/addon.php
        $tag_counts_sql = "
            UPDATE
                categories AS t
            INNER JOIN (
                SELECT
                    at.category_id,
                    COUNT(DISTINCT Addon.id) AS ct
                FROM
                    addons AS Addon
                INNER JOIN versions AS Version
                    ON (Addon.id = Version.addon_id)
                INNER JOIN applications_versions AS av
                    ON (av.version_id = Version.id)
                INNER JOIN addons_categories AS at
                    ON (at.addon_id = Addon.id)
                INNER JOIN files AS File
                    ON (Version.id = File.version_id
                        AND File.status IN ({$valid_status}))
                WHERE
                    Addon.status IN ({$valid_status})
                        AND Addon.inactive = 0
                GROUP BY at.category_id
            ) AS j ON (t.id = j.category_id)
            SET
                t.count = j.ct
        ";
        $db->write($tag_counts_sql);
        $affected_rows = mysql_affected_rows();
    break;




    /**
     * Update global stats counters
     */
    case 'global_stats':
        debug("Starting global stats update...");

        $affected_rows = 0;

        $date = date('Y-m-d');

        $stats = array(
            // Add-on downloads
            'addon_total_downloads'             => 'SELECT SUM(count) FROM download_counts',
            'addon_downloads_new'               => "SELECT IFNULL(SUM(count), 0) FROM download_counts WHERE date = '{$date}'",

            // Add-on counts
            'addon_count_public'                => 'SELECT COUNT(*) FROM addons WHERE status = 4 AND inactive = 0',
            'addon_count_pending'               => 'SELECT COUNT(*) FROM versions INNER JOIN files ON versions.id = files.version_id WHERE files.status = 2',
            'addon_count_experimental'          => 'SELECT COUNT(*) FROM addons WHERE status = 1 AND inactive = 0',
            'addon_count_nominated'             => 'SELECT COUNT(*) FROM addons WHERE status = 3 AND inactive = 0',
            'addon_count_new'                   => "SELECT COUNT(*) FROM addons WHERE DATE(created) = '{$date}'",

            // Version counts
            'version_count_new'                 => "SELECT COUNT(*) FROM versions WHERE DATE(created) = '{$date}'",

            // User counts
            'user_count_total'                  => 'SELECT COUNT(*) FROM users',
            'user_count_new'                    => "SELECT COUNT(*) FROM users WHERE DATE(created) = '{$date}'",

            // Review counts
            'review_count_total'                => 'SELECT COUNT(*) FROM reviews WHERE editorreview = 0',
            'review_count_new'                  => "SELECT COUNT(*) FROM reviews WHERE DATE(created) = '{$date}'",

            // Collection counts
            'collection_count_total'            => 'SELECT COUNT(*) FROM collections',
            'collection_count_new'              => "SELECT COUNT(*) FROM collections WHERE DATE(created) = '{$date}'",
            'collection_count_private'          => 'SELECT COUNT(*) FROM collections WHERE listed = 0',
            'collection_count_public'           => 'SELECT COUNT(*) FROM collections WHERE listed = 1',
            'collection_count_autopublishers'   => 'SELECT COUNT(*) FROM collections WHERE collection_type = 1',
            'collection_count_editorspicks'     => 'SELECT COUNT(*) FROM collections WHERE collection_type = 2',
            'collection_count_normal'           => 'SELECT COUNT(*) FROM collections WHERE collection_type = 0',
            'collection_addon_downloads'        => 'SELECT SUM(count) FROM stats_addons_collections_counts',

            // Add-on Collector
            'collector_total_downloads'         => 'SELECT SUM(count) FROM download_counts WHERE addon_id = 11950'
        );

        // Update all "total" stats that don't require a date
        foreach ($stats as $stat => $query) {
            debug("Updating {$stat}...");

            $db->write("REPLACE INTO global_stats (name, count, date) VALUES ('{$stat}', ({$query}), '{$date}')");

            $affected_rows += mysql_affected_rows();
        }

        // These stats are specific to the latest available metrics data import

        $date = 'SELECT MAX(date) FROM update_counts';

        $variable_date_stats = array(
            'addon_total_updatepings'           => "SELECT SUM(count) FROM update_counts WHERE date = ({$date})",
            'collector_updatepings'             => "SELECT count FROM update_counts WHERE addon_id = 11950 AND date = ({$date})"
        );

        foreach ($variable_date_stats as $stat => $query) {
            debug("Updating {$stat}...");

            $db->write("REPLACE INTO global_stats (name, count, date) VALUES ('{$stat}', ({$query}), ({$date}))");

            $affected_rows += mysql_affected_rows();
        }

    break;



    /**
     * Daily collection stats
     */
    case 'collection_stats':
        debug("Starting collection stats update...");

        $affected_rows = 0;
        $date = date('Y-m-d');

        $collection_stats = array(
            'new_subscribers' => "
                SELECT '{$date}', 'new_subscribers', collection_id, COUNT(*)
                  FROM collection_subscriptions
                 WHERE DATE(created) = '{$date}'
                 GROUP BY collection_id",

            'new_votes_up' => "
                SELECT '{$date}', 'new_votes_up', collection_id, COUNT(*)
                  FROM collections_votes
                 WHERE DATE(created) = '{$date}' AND vote = 1
                GROUP BY collection_id",

            'new_votes_down' => "
                SELECT '{$date}', 'new_votes_down', collection_id, COUNT(*)
                  FROM collections_votes
                 WHERE DATE(created) = '{$date}' AND vote = -1
                 GROUP BY collection_id",
        );

        foreach ($collection_stats as $stat => $query) {
            debug("Updating {$stat}...");

            $db->write("REPLACE INTO stats_collections (`date`, `name`, `collection_id`, `count`) {$query}");

            $affected_rows += mysql_affected_rows();
        }

    break;



    /**
     * Collection weekly and monthly subscriber counts
     */
    case 'collection_subscribers':
        debug("Starting collection subscriber update...");
        // Clear out existing data.
        $db->write("UPDATE collections
                    SET weekly_subscribers = 0, monthly_subscribers = 0");
        $woohoo = "
            UPDATE collections AS c
            INNER JOIN (
                SELECT
                    COUNT(collection_id) AS count,
                    collection_id
                FROM collection_subscriptions
                WHERE created >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY collection_id
            ) AS weekly ON (c.id = weekly.collection_id)
            INNER JOIN (
                SELECT
                    COUNT(collection_id) AS count,
                    collection_id
                FROM collection_subscriptions
                WHERE created >= DATE_SUB(CURDATE(), INTERVAL 31 DAY)
                GROUP BY collection_id
            ) AS monthly ON (c.id = monthly.collection_id)
            SET c.weekly_subscribers = weekly.count,
                c.monthly_subscribers = monthly.count
        ";
        $result = $db->write($woohoo);
        $affected_rows = mysql_affected_rows();
    break;

    case 'blog':
        debug("Starting blog post cache update");
        $feedURL = 'http://blog.mozilla.com/addons/category/developers/feed/';
        $blogXML = simplexml_load_file($feedURL);

        //Some basic error checking 
        if(!$blogXML) {
            debug('Could not fetch blog feed');
            exit;
        }

        if(count($blogXML->channel->item) < 5) {
            debug('Blog feed did not have minimum 5 posts, feed may be broken');
            exit;
        }
        
        $db->write('DELETE FROM blogposts');

        for($i=0; $i<=4; $i++) {
            $title = mysql_real_escape_string($blogXML->channel->item[$i]->title);
            $date = strftime("%Y-%m-%d %H:%M:%S",
                strtotime($blogXML->channel->item[$i]->pubDate));
            $permalink = mysql_real_escape_string($blogXML->channel->item[$i]->link);
            $db->write('INSERT INTO `blogposts` (`title`, `date_posted`,
            `permalink`) VALUES ("'.$title.'", "'.$date.'", "'.$permalink.'")');
        }
        
        $affected_rows = 5;
    break;


    /**
     * Unknown command.
     */
    default:
        debug('Command not found. Exiting ...', true);
        exit;
    break;
}
// End switch.



// How long did it take to run?
$exectime = getmicrotime() - $start;



// Display script output.
debug('Affected rows: '.$affected_rows);
debug('Time: '.$exectime);
debug('Exiting ...');



/**
* Copy a file to the rsync location for updates
* @param int $addon_id the add-on id
* @param string $filename the filename
* @param boolean $overwrite whether to overwrite the destination file
* @return boolean
*/
function copyFileToPublic($addon_id, $filename, $overwrite = true) {
    // Only copy if the path has been defined
    if (!defined('PUBLIC_STAGING_PATH')) {
        // return true because false indicates error
        debug("Public staging path doesn't exist", true);
        return false;
    }

    $currentFile = REPO_PATH."/{$addon_id}/{$filename}";
    $newDir = PUBLIC_STAGING_PATH."/{$addon_id}";
    $newFile = $newDir."/{$filename}";

    // Make sure source file exists
    if (!file_exists($currentFile)) {
        debug("Source file doesn't exist: {$currentFile}", true);
        return false;
    }

    // If we don't want to overwrite, make sure we don't
    if (!$overwrite && file_exists($newFile)) {
        // return true because this is not treated as an error
        return false;
    }

    // Make directory if necessary
    if (!file_exists($newDir)) {
        if (!mkdir($newDir)) {
            debug("Can't make a new directory: {$newDir}", true);
            return false;
        }
    }

    return copy($currentFile, $newFile);
}

/**
 * Give this function your output.  If the debug flag (in the database) is set or if the error is serious it will get printed
 *
 * @param string what to print
 * @param boolean if the error is fatal or not
 */
function debug($msg, $serious=false) {
    if (CRON_DEBUG || $serious) {
        echo "{$msg}\n";
    }
}

exit;
?>
