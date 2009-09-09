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
 *   l.m.orchard <lorchard@mozilla.com>
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
class StatsComponent extends Object {
    var $controller;

    var $date_group_column_map = array(
        'date'  => '', 
        'month' => 'date_format(`date`, "month:%Y-%m") as `date_month`,',
        'week'  => 'date_format(`date`, "week:%X-%V") as `date_week`,'
    );
    
    var $date_group_by_map = array(
        'date'  => 'date', 
        'month' => 'date_month',
        'week'  => 'date_week'
    );

    /**
     * Map internal global_stats entries to public field names for CSV
     */
    var $site_stats_field_map = array(
        'addon_downloads_new'       => 'addons_downloaded',
        'addon_total_updatepings'   => 'addons_in_use',
        'addon_count_new'           => 'addons_created',
        'version_count_new'         => 'addons_updated',
        'user_count_new'            => 'users_created',
        'review_count_new'          => 'reviews_created',
        'collection_count_new'      => 'collections_created',
    );

    /**
     * stats where we want a max value rather than sum when grouping by date range
     */
    var $use_max_when_grouping = array(
        'addon_total_updatepings',
    );
    
    /**
     * Save a reference to the controller on startup
     * @param object &$controller the controller using this component
     */
    function startup(&$controller) {
        $this->controller =& $controller;

        if (!isset($this->controller->GlobalStat)) {
            loadModel('GlobalStat');
            $this->controller->GlobalStat =& new GlobalStat();
        }
    }

    function historicalPlot($addon_id, $plot, $date_grouping='date') {

        // Constrain the date grouping choice to valid set
        if (!array_key_exists($date_grouping, $this->date_group_column_map)) {
            $date_grouping = 'date';
        }
        $date_group_column = $this->date_group_column_map[$date_grouping];
        $date_group_by     = $this->date_group_by_map[$date_grouping];

        $model =& $this->controller->Addon;
        
        $csv = array();
        $totalCounts = array();
        $dynamicFields = array();
        $dates = array();
        
        // Warning: extensive use of ` ahead. We keenly named columns after
        // MySQL functions
        
        switch ($plot) {
            // Summary of downloads and update pings per day
            case 'summary':
                if ($data = $model->query("
                    SELECT
                        `download_counts`.`date`,
                        `download_counts`.`count`,
                        `update_counts`.`count`
                    FROM
                        `download_counts`
                            LEFT JOIN `update_counts` ON
                                `download_counts`.`date`=`update_counts`.`date` AND
                                `download_counts`.`addon_id`=`update_counts`.`addon_id`
                    WHERE
                        `download_counts`.`addon_id`={$addon_id} AND
                        `download_counts`.`date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ORDER BY
                        `download_counts`.`date`
                ", true)) {
                    foreach ($data as $date) {
                        $csv[] = array('date' => $date['download_counts']['date'],
                                       'downloads' => $date['download_counts']['count'],
                                       'updatepings' => $date['update_counts']['count']
                                   );
                    }
                }
                
                break;
            
            // Downloads per day
            case 'downloads':
                if ($data = $model->query("
                    SELECT `date`, {$date_group_column} sum(`count`) as `count` 
                    FROM `download_counts` 
                    WHERE `addon_id`={$addon_id} AND date != '0000-00-00' 
                    GROUP BY `{$date_group_by}`
                    ORDER BY `date`
                ", true)) {
                    foreach ($data as $download) {
                        $csv[] = array('date' => $download['download_counts']['date'],
                                       'count' => $download['0']['count']);
                    }
                }
                break;
            
            // Update pings per day
            case 'updatepings':
                // Since summing update pings per day isn't as useful as summing
                // the other data points, we're taking an average here instead.
                if ($data = $model->query("
                    SELECT `date`, {$date_group_column} AVG(`count`) as `count`
                    FROM `update_counts` 
                    WHERE `addon_id`={$addon_id} AND date != '0000-00-00' 
                    GROUP BY `{$date_group_by}`
                    ORDER BY `date`
                ", true)) {
                    foreach ($data as $download) {
                        $csv[] = array('date' => $download['update_counts']['date'],
                                       'count' => $download['0']['count']);
                    }
                }
                break;
            
            // Add-on versions in use per day
            case 'version':
                if ($data = $model->query("
                    SELECT `date`, {$date_group_column} `count`, `version` 
                    FROM `update_counts` 
                    WHERE `addon_id`={$addon_id} AND date != '0000-00-00' 
                    ORDER BY `date`
                ", true)) {
                    foreach ($data as $download) {
                        $date = $download['update_counts']['date'];
                        
                        // Add static fields for CSV
                        $dates[$date] = array(
                            'date'  => $date,
                            'count' => $download['update_counts']['count']
                        );

                        if ($date_group_by != 'date')
                            $dates[$date]['group_by'] = $download['0'][$date_group_by];
                        
                        // Loop through dynamic fields
                        $items = unserialize($download['update_counts']['version']);
                        if (!empty($items)) {
                            foreach ($items as $item => $count) {
                                // Update total count for sorting
                                if (empty($dynamicFields[$item]))
                                    $dynamicFields[$item] = $count;
                                else
                                    $dynamicFields[$item] += $count;
                                
                                $dates[$date]['dynamic'][$item] = $count;
                            }
                        }
                    }

                    if ($date_group_by != 'date')
                        $dates = $this->_groupAndSumDynamicFields($dates);

                }
                break;
            
            // Application versions in use per day
            case 'application':
                if ($data = $model->query("
                    SELECT `date`, {$date_group_column} `count`, `application` 
                    FROM `update_counts` 
                    WHERE `addon_id`={$addon_id} AND date != '0000-00-00' 
                    ORDER BY `date`
                ", true)) {
                    foreach ($data as $download) {
                        $date = $download['update_counts']['date'];
                        
                        // Add static fields for CSV
                        $dates[$date] = array(
                            'date'  => $date,
                            'count' => $download['update_counts']['count']
                        );

                        if ($date_group_by != 'date')
                            $dates[$date]['group_by'] = $download['0'][$date_group_by];
                        
                        // Loop through dynamic fields
                        $items = unserialize($download['update_counts']['application']);
                        if (!empty($items)) {
                            foreach ($items as $app => $versions) {
                                foreach ($versions as $version => $count) {
                                    // Combine app GUID with version
                                    $item = "{$app}/{$version}";
                                    
                                    // Update total count for sorting
                                    if (empty($dynamicFields[$item]))
                                        $dynamicFields[$item] = $count;
                                    else
                                        $dynamicFields[$item] += $count;
                                    
                                    $dates[$date]['dynamic'][$item] = $count;
                                }
                            }
                        }
                    }

                    if ($date_group_by != 'date')
                        $dates = $this->_groupAndSumDynamicFields($dates);

                }
                break;
            
            // Status of add-ons in use per day
            case 'status':
                if ($data = $model->query("
                    SELECT `date`, {$date_group_column} `count`, `status` 
                    FROM `update_counts` 
                    WHERE `addon_id`={$addon_id} AND date != '0000-00-00' 
                    ORDER BY `date`
                ", true)) {
                    foreach ($data as $download) {
                        $date = $download['update_counts']['date'];
                        
                        // Add static fields for CSV
                        $dates[$date] = array(
                            'date'  => $date,
                            'count' => $download['update_counts']['count']
                        );

                        if ($date_group_by != 'date')
                            $dates[$date]['group_by'] = $download['0'][$date_group_by];
                        
                        // Loop through dynamic fields
                        $items = unserialize($download['update_counts']['status']);
                        if (!empty($items)) {
                            foreach ($items as $item => $count) {
                                // Update total count for sorting
                                if (empty($dynamicFields[$item]))
                                    $dynamicFields[$item] = $count;
                                else
                                    $dynamicFields[$item] += $count;
                                
                                $dates[$date]['dynamic'][$item] = $count;
                            }
                        }
                    }

                    if ($date_group_by != 'date')
                        $dates = $this->_groupAndSumDynamicFields($dates);

                }
                break;
            
            // Operating Systems in use per day
            case 'os':
                if ($data = $model->query("
                    SELECT `date`, {$date_group_column} `count`, `os` 
                    FROM `update_counts` 
                    WHERE `addon_id`={$addon_id} AND date != '0000-00-00' 
                    ORDER BY `date`
                ", true)) {
                    foreach ($data as $download) {
                        $date = $download['update_counts']['date'];
                        
                        // Add static fields for CSV
                        $dates[$date] = array(
                            'date'  => $date,
                            'count' => $download['update_counts']['count']
                        );

                        if ($date_group_by != 'date')
                            $dates[$date]['group_by'] = $download['0'][$date_group_by];
                        
                        // Loop through dynamic fields
                        $items = unserialize($download['update_counts']['os']);
                        if (!empty($items)) {
                            foreach ($items as $item => $count) {
                                // Update total count for sorting
                                if (empty($dynamicFields[$item]))
                                    $dynamicFields[$item] = $count;
                                else
                                    $dynamicFields[$item] += $count;
                                    
                                $dates[$date]['dynamic'][$item] = $count;
                            }
                        }
                    }

                    if ($date_group_by != 'date')
                        $dates = $this->_groupAndSumDynamicFields($dates);

                }
                break;

            // Contribution $USD per day
            case 'contributions':
                if ($data = $model->query("
                    SELECT DATE(`created`) AS `date`, SUM(`amount`) AS `count` 
                    FROM `stats_contributions` 
                    WHERE `addon_id`={$addon_id} AND `transaction_id` IS NOT NULL AND `amount` > 0
                    GROUP BY `date`
                    HAVING `date` != '0000-00-00'
                ", true)) {
                    foreach ($data as $download) {
                        $csv[] = array('date' => $download['0']['date'],
                                       'count' => $download['0']['count']);
                    }
                }
                break;
        }

        // we have two varieties above: csv pre-built
        // in which case we are done now
        if (!empty($csv)) {
            return $csv;
        }
        // otherwise post-process the dates array for csv data

        arsort($dynamicFields);
        
        // Loop through dates and add each field to the CSV array
        // to save on memory, modify the dates array inplace rather
        // than create yet another monster array and doubling php's
        // memory footprint (see bug 513199)
        $keys = array_keys($dates);
        foreach ($keys as $date) {
            $items = $dates[$date];
            $record = array();

            // skip dates with missing data
            if (!array_key_exists('dynamic', $items)) {
                unset($dates[$date]);
                continue;
            }

            foreach ($items as $item => $value) {                
                // If item is a "normal" field like date or count, add it
                if ($item != 'dynamic') {
                    $record[$item] = $value;
                }
                // If it's the array of dynamic fields, go in pre-determined order
                else {
                    foreach ($dynamicFields as $dynamicField => $count) {
                        if (!empty($value[$dynamicField]))
                            $record[$dynamicField] = $value[$dynamicField];
                        else
                            $record[$dynamicField] = 0;
                    }
                }
            }
            
            $dates[$date] = $record;
        }

        // numeric keys please for csv
        return array_values($dates);
    }

    /**
     * Aggregate all counts and dynamic field counts using the value of 
     * group by fields.
     */
    function _groupAndSumDynamicFields($dates) {

        // Run through all the flat date-indexed results and aggregate the 
        // counts by the group_by field
        $agg = array();
        foreach ($dates as $date=>$stats) {
            $group_by = $stats['group_by'];

            if (!isset($agg[$group_by])) {
                // Empty aggregate, so start off with the current stats row.
                $agg[$group_by] = $stats;
            } else {
                // Add the current count to aggregate.
                $agg[$group_by]['count'] += $stats['count'];
                // Add all the dynamic field values to the aggregate.
                foreach ($stats['dynamic'] as $key=>$value) {
                    if (!isset($agg[$group_by]['dynamic'][$key])) {
                        // Empty aggregate value, use current value.
                        $agg[$group_by]['dynamic'][$key] = $value;
                    } else {
                        // Add current value to aggregate.
                        $agg[$group_by]['dynamic'][$key] += $value;
                    }
                }
            }
        }

        // Re-index the aggregates by the date field, return results.
        $new_dates = array();
        foreach ($agg as $group_by=>$stats) {
            unset($stats['group_by']);
            $new_dates[$stats['date']] = $stats;
        }
        return $new_dates;
    }
    
    function getSummary($addon_id, $include_contributions = false) {
        $json = array(
                      'downloads' => array(
                                'availableDates' => array()
                                             ),
                      'updatepings' => array(
                                'availableDates' => array(),
                                'plotFields' => array(
                                                      'version' => array(),
                                                      'application' => array(),
                                                      'status' => array(),
                                                      'os' => array()
                                                      )
                                             ),
                      'prettyNames' => array(
                                             'version' => ___('Add-on Version', 'statistics_longnames_version'),
                                             'application' => ___('Application', 'statistics_longnames_application'),
                                             'status' => ___('Add-on Status', 'statistics_longnames_status'),
                                             'os' => ___('Operating System', 'statistics_longnames_os'),
                                             'unknown' => ___('Unknown', 'statistics_longnames_unknown')
                                             ),
                      'shortNames' => array(
                                            'version' => ___('Ver'),
                                            'application' => ___('App'),
                                            'status' => ___('St'),
                                            'os' => ___('OS'),
                                            'unknown' => ___('Uk'),
                                            )
                      );
        $appShortnames = $this->controller->Amo->getApplicationName(null, true);
        foreach ($appShortnames as $appShortname) {
            $json['shortNames'][$appShortname['name']] = ucwords($appShortname['shortname']);
        }
        
        $appNames = $this->controller->Application->getGUIDList();
        $json['prettyNames'] = array_merge($json['prettyNames'], $appNames);
        
        // Downloads
        if ($data = $this->controller->Addon->query("SELECT date FROM `download_counts` WHERE `addon_id`={$addon_id} AND date != '0000-00-00' ORDER BY `date`", true)) {
            foreach ($data as $date) {
                // if date isn't recorded, record it
                if (!in_array($date['download_counts']['date'], $json['downloads']['availableDates']))
                    $json['downloads']['availableDates'][] = $date['download_counts']['date'];
            }
        }

        // Contributions
        if ($include_contributions) {
            $json['contributions'] = array('availableDates' => array());
            $data = $this->controller->Addon->query("SELECT DISTINCT DATE(`created`) AS `date` FROM `stats_contributions` WHERE `addon_id`={$addon_id} AND `transaction_id` IS NOT NULL AND `amount` > 0 ORDER BY `date`", true);
            if ($data) {
                foreach ($data as $date) {
                    $json['contributions']['availableDates'][] = $date['0']['date'];
                }
            }
        }
        
        // Update pings
        if ($data = $this->controller->Addon->query("SELECT * FROM `update_counts` WHERE `addon_id`={$addon_id} AND date != '0000-00-00' ORDER BY `date`", true)) {
            foreach ($data as $date) {
                // if date isn't recorded, record it
                if (!in_array($date['update_counts']['date'], $json['updatepings']['availableDates']))
                    $json['updatepings']['availableDates'][] = $date['update_counts']['date'];
                
                // record dynamic field names
                foreach (array_keys($json['updatepings']['plotFields']) as $dynamicField) {
                    $field = unserialize($date['update_counts'][$dynamicField]);
                    
                    if (!empty($field)) {
                        foreach ($field as $name => $count) {
                            if (!is_array($count)) {
                                // fields that are only 1 level deep
                                if (empty($json['updatepings']['plotFields'][$dynamicField][$name]))
                                    $json['updatepings']['plotFields'][$dynamicField][$name] = $count;
                                else
                                    $json['updatepings']['plotFields'][$dynamicField][$name] += $count;
                            }
                            else {
                                // applications are 2 levels deep
                                foreach ($count as $appVersion => $appcount) {
                                    // $name == app GUID
                                    // make sure app is added
                                    if (!array_key_exists($name, $json['updatepings']['plotFields'][$dynamicField]))
                                        $json['updatepings']['plotFields'][$dynamicField][$name] = array();
                                    
                                    // make sure app version is added
                                    if (empty($json['updatepings']['plotFields'][$dynamicField][$name][$appVersion]))
                                        $json['updatepings']['plotFields'][$dynamicField][$name][$appVersion] = $appcount;
                                    else
                                        $json['updatepings']['plotFields'][$dynamicField][$name][$appVersion] += $appcount;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // sort each field by total count
        foreach (array_keys($json['updatepings']['plotFields']) as $dynamicField) {
            if ($dynamicField == 'application') {
                foreach ($json['updatepings']['plotFields'][$dynamicField] as $app => $appversions) {
                    // Causing a warning because one of the elements is 0=>false
                    if (is_array($json['updatepings']['plotFields'][$dynamicField][$app]))
                        arsort($json['updatepings']['plotFields'][$dynamicField][$app]);
                }
            }
            else
                arsort($json['updatepings']['plotFields'][$dynamicField]);
        }
        
        return $json;
    }
    
    function getEventsApp($app) {
        if ($app == 'firefox') {
            vendor('product-details/history/firefoxHistory.class');
            $history = new firefoxHistory();
        }
        elseif ($app == 'thunderbird') {
            vendor('product-details/history/thunderbirdHistory.class');
            $history = new thunderbirdHistory();
        }
        
        $releases = $history->getReleaseDates();
        $xml = array();
        
        if (!empty($releases)) {
            foreach ($releases as $version => $date) {
                $title = sprintf(___('%1$s released'), ucwords($app)." {$version}");
                $xml[] = '<event start="'.date('M d Y H:i:s \G\M\TO', strtotime($date)).'" title="'.$title.'" />';
            }
        }
        
        return $xml;
    }
    
    function getEventsAddon($addon_id) {
        $xml = array();
        
        $name = $this->controller->Addon->getAddonName($addon_id);
        
        $versions = $this->controller->Version->findAll(
                                "Version.addon_id={$addon_id}",
                                array('Version.version', 'Version.created'));
        
        if (!empty($versions)) {
            foreach ($versions as $version) {
                $title = sprintf(___('%1$s created'), "{$name} {$version['Version']['version']}");
                $xml[] = '<event start="'.date('M d Y H:i:s \G\M\TO', strtotime($version['Version']['created'])).'" title="'.$title.'" />';
            }
        }
        
        return $xml;
    }

    /**
     * @return array Array of count results for the current day.
     */
    function getDailyStats() {

        $yesterday = date('Y-m-d', strtotime('yesterday'));
        $after = $yesterday.' 00:00:00';
        $before = $yesterday.' 23:59:59';

        $ret = array(
            'nomination' => '',
            'pending' => '',
            'flagged' => '',
            'reviews' => '',
            'dailyAddons' => '',
            'totalAddons' => '',
            'dailyVersions' => '',
            'dailyUsers' => '',
            'dailyImages' => '',
            'dailyDownloads' => '',
            'after' => $after,
            'before' => $before
        );

        $model =& $this->controller->Addon;

        if ($buf = $model->query("SELECT COUNT(*) as nomination FROM addons WHERE status=3", true)) {
            $ret['nomination'] = $buf[0][0]['nomination'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as pending FROM files WHERE status=2", true)) {
            $ret['pending'] = $buf[0][0]['pending'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as flagged FROM addons WHERE adminreview=1", true)) {
            $ret['flagged'] = $buf[0][0]['flagged'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as reviews FROM reviews WHERE editorreview=1", true)) {
            $ret['reviews'] = $buf[0][0]['reviews'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as total FROM addons WHERE created >= '{$after}' AND created <= '{$before}'", true)) {
            $ret['dailyAddons'] = $buf[0][0]['total'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as total FROM addons", true)) {
            $ret['totalAddons'] = $buf[0][0]['total'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as total FROM versions WHERE created >= '{$after}' AND created <= '{$before}'", true)) {
            $ret['dailyVersions'] = $buf[0][0]['total'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as total FROM users WHERE created >= '{$after}' AND created <= '{$before}'", true)) {
            $ret['dailyUsers'] = $buf[0][0]['total'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as total FROM reviews WHERE created >= '{$after}' AND created <= '{$before}'", true)) {
            $ret['dailyReviews'] = $buf[0][0]['total'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT COUNT(*) as total FROM previews WHERE created >= '{$after}' AND created <= '{$before}'", true)) {
            $ret['dailyImages'] = $buf[0][0]['total'];
            unset($buf);
        }

        if ($buf = $model->query("SELECT SUM(count) as count FROM download_counts WHERE date = '{$yesterday}'", true)) {
            $ret['dailyDownloads'] = $buf[0][0]['count'];
            unset($buf);
        }

        return $ret;
    }

    /**
     * @return array Array of count results
     */
    function getSiteStatsOverview() {

        $ret = array(
            'totalDownloads' => $this->controller->GlobalStat->getNamedCount('addon_total_downloads'),
            'totalInUse' => $this->controller->GlobalStat->getNamedCount('addon_total_updatepings'),
            'totalUsers' => $this->controller->GlobalStat->getNamedCount('user_count_total'),
            'totalReviews' => $this->controller->GlobalStat->getNamedCount('review_count_total'),
            'totalCollections' => $this->controller->GlobalStat->getNamedCount('collection_count_total'),
        );

        return $ret;
    }

    /**
     * @return array Array of count results
     */
    function getSiteStats($groupBy='date') {
        $stats = array();

        // Do not include today's counts since they are almost always incomplete.
        // This will result in a row of zeros when grouping by day or if today
        // is the first day of a week or month. 
        $endDate = date('Y-m-d', strtotime('-1 day'));

        switch ($groupBy) {
        case 'week':
            $interval = '+1 week';
            if (date('w') == 0) {
                // today is sunday
                $startDate = date('Y-m-d', strtotime('-53 weeks'));
            } else {
                $startDate = date('Y-m-d', strtotime('last sunday -53 weeks'));
            }

            foreach (array_keys($this->site_stats_field_map) as $name) {
                if (in_array($name, $this->use_max_when_grouping)) {
                    $stats[$name] = $this->controller->GlobalStat->getNamedWeeklyMax($name, $startDate, $endDate);
                } else {
                    $stats[$name] = $this->controller->GlobalStat->getNamedWeeklySum($name, $startDate, $endDate);
                }
            }
            break;

        case 'month':
            $interval = '+1 month';
            $startDate = date('Y-m-01', strtotime('-35 months'));

            foreach (array_keys($this->site_stats_field_map) as $name) {
                if (in_array($name, $this->use_max_when_grouping)) {
                    $stats[$name] = $this->controller->GlobalStat->getNamedMonthlyMax($name, $startDate, $endDate);
                } else {
                    $stats[$name] = $this->controller->GlobalStat->getNamedMonthlySum($name, $startDate, $endDate);
                }
            }
            break;

        case 'day':
        case 'date':
            $interval = '+1 day';
            $startDate = date('Y-m-d', strtotime('-89 days'));

            foreach (array_keys($this->site_stats_field_map) as $name) {
                $stats[$name] = $this->controller->GlobalStat->getNamedDaily($name, $startDate, $endDate);
            }
            break;

        default:
            return false;
        }

        // prep stats for csv output, filling in any missing days
        $results = array();
        $tsEnd = time();
        for ($ts = strtotime($startDate); $ts < $tsEnd; $ts = strtotime($interval, $ts)) {
            $dateKey = date('Y-m-d', $ts);
            $row = array('date' => $dateKey);
            foreach ($this->site_stats_field_map as $statKey => $csvField) {
                if (array_key_exists($dateKey, $stats[$statKey])) {
                    $row[$csvField] = $stats[$statKey][$dateKey];
                } else {
                    $row[$csvField] = 0;
                }
            }
            $results[] = $row;
        }
        $results = array_reverse($results);

        return $results;
    }

    /**
     * Calculate daily downloads for addons in a collection
     *
     * @param mixed collection_id or array of collection_ids
     * @param string $startDate 'YYYY-MM-DD' ('0000-00-00' as default)
     * @param string $endDate 'YYYY-MM-DD' (today as default)
     * @return array Array of Arrays:
     *         array(<addon_id> => array('<date1>' => count, '<date2>' => count, ...))
     */
    function getCollectionAddonDailyDownloads($collectionId, $startDate=null, $endDate=null) {

        // massage parameters
        if (!is_array($collectionId)) {
            $collectionId = array($collectionId);
        }
        $startDate = is_null($startDate) ? '0000-00-00' : date('Y-m-d', strtotime($startDate));
        $endDate = is_null($endDate) ? date('Y-m-d') : date('Y-m-d', strtotime($endDate));

        // fetch daily download counts for all addons in collection(s)
        $in_collections = '0';
        foreach ($collectionId as $id) {
            $in_collections .= ',' . intval($id);
        }

        $model =& $this->controller->GlobalStat;
        $rows = $model->query("
            SELECT `addon_id`, `date`, SUM(`count`) AS total
              FROM `stats_addons_collections_counts` AS `sacc`
             WHERE `collection_id` IN({$in_collections})
               AND `date` BETWEEN '{$startDate}' AND '{$endDate}'
               AND `date` != '0000-00-00'
             GROUP BY `addon_id`, `date`
        ");

        // fill in counts for addons/days found in query
        $results = array();
        $addon_id = 0;
        $downloads = array();
        foreach ($rows as $row) {
            $addonKey = $row['sacc']['addon_id'];
            $dateKey = $row['sacc']['date'];

            // starting a new addon
            if ($addonKey != $addon_id) {
                // save data for previous addon
                if ($addon_id != 0) {
                    $results[$addon_id] = $downloads;
                }
                $addon_id = $addonKey;
                $downloads = array();
            }

            $downloads[$dateKey] = $row[0]['total'];
        }
        // save data for the last addon in the result set
        if ($addon_id != 0) {
            $results[$addon_id] = $downloads;
        }

        // fill in any holes in the date range starting with
        // the first date having results (or startDate if specified)
        foreach ($results as &$result_row) {
            if (!empty($result_row) || $startDate != '0000-00-00') {
                if ($startDate == '0000-00-00') {
                    $keys = array_keys($result_row);
                    $tsStart = strtotime($keys[0]);
                } else {
                    $tsStart = strtotime($startDate);
                }
                $tsEnd = strtotime($endDate);

                for ($ts = $tsStart; $ts <= $tsEnd; $ts = strtotime('+1 day', $ts)) {
                    $dateKey = date('Y-m-d', $ts);
                    if (!array_key_exists($dateKey, $result_row)) {
                        $result_row[$dateKey] = 0;
                    }
                }

                // re-sort downloads by date keys
                ksort($result_row);
            }
        }

        return $results;
    }

    function getCollectionAddonTotalDownloads($collectionId) {
        $results = array();

        $collectionId = intval($collectionId);

        $model =& $this->controller->GlobalStat;

        $rows = $model->query("
            SELECT `addon_id`, SUM(`count`) AS total
              FROM stats_addons_collections_counts AS stats
             WHERE collection_id = '{$collectionId}'
             GROUP BY `addon_id`
        ");

        if ($rows) {
            foreach ($rows as $row) {
                $results[$row['stats']['addon_id']] = $row[0]['total'];
            }
        }

        return $results;
    }

    function getCollectionSubscriberSum($collectionId, $startDate=null, $endDate=null) {
        return $this->_getCollectionStatSum($collectionId, 'new_subscribers', $startDate, $endDate);
    }

    function getCollectionVotesUpSum($collectionId, $startDate=null, $endDate=null) {
        return $this->_getCollectionStatSum($collectionId, 'new_votes_up', $startDate, $endDate);
    }

    function getCollectionVotesDownSum($collectionId, $startDate=null, $endDate=null) {
        return $this->_getCollectionStatSum($collectionId, 'new_votes_down', $startDate, $endDate);
    }

    function getCollectionDownloadSum($collectionId, $startDate=null, $endDate=null) {

        if (!is_array($collectionId)) {
            $collectionId = array($collectionId);
        }

        $in_string = '0';
        foreach ($collectionId as $id) {
            $in_string .= ',' . intval($id);
        }

        $startDate = is_null($startDate) ? '0000-00-00' : date('Y-m-d', strtotime($startDate));
        $endDate = is_null($endDate) ? date('Y-m-d') : date('Y-m-d', strtotime($endDate));

        $model =& $this->controller->GlobalStat;

        $result = $model->query("
            SELECT IFNULL(SUM(`count`), 0) AS total
              FROM stats_addons_collections_counts
             WHERE collection_id IN({$in_string})
               AND `date` BETWEEN '{$startDate}' AND '{$endDate}'
               AND `date` != '0000-00-00'
        ");

        return empty($result) ? 0 : $result[0][0]['total'];
    }

    function getCollectionDailyStats($collectionId, $startDate=null, $endDate=null) {

        // massage parameters
        if (!is_array($collectionId)) {
            $collectionId = array($collectionId);
        }
        $startDate = is_null($startDate) ? '0000-00-00' : date('Y-m-d', strtotime($startDate));
        $endDate = is_null($endDate) ? date('Y-m-d') : date('Y-m-d', strtotime($endDate));

        // initialize results and a template for each row
        $results = array();
        $result_row = array(
            'date'             => 'YYYY-MM-DD',
            'subscribers'      => 0,
            'votes_up'         => 0,
            'votes_down'       => 0,
            'downloads'        => 0,
        );

        // fetch subscriber and vote counts
        $in_collections = '0';
        foreach ($collectionId as $id) {
            $in_collections .= ',' . intval($id);
        }
        $in_stats = "'new_subscribers','new_votes_up','new_votes_down'";

        $model =& $this->controller->GlobalStat;
        $rows = $model->query("
            SELECT `date`,
                   SUM( IF(`name` = 'new_subscribers',  `count`, 0) ) AS `new_subscribers`,
                   SUM( IF(`name` = 'new_votes_up',   `count`, 0) ) AS `new_votes_up`,
                   SUM( IF(`name` = 'new_votes_down', `count`, 0) ) AS `new_votes_down`
              FROM `stats_collections` AS `sc`
             WHERE `collection_id` IN({$in_collections})
               AND `name` IN({$in_stats})
               AND `date` BETWEEN '{$startDate}' AND '{$endDate}'
               AND `date` != '0000-00-00'
             GROUP BY `date`
        ");

        // fill in counts for days found in query
        foreach ($rows as $row) {
            $dateKey = $row['sc']['date'];
            if (!array_key_exists($dateKey, $results)) {
                $results[$dateKey] = $result_row;
                $results[$dateKey]['date'] = $dateKey;
            }
            $results[$dateKey]['subscribers'] = $row[0]['new_subscribers'];
            $results[$dateKey]['votes_up'] = $row[0]['new_votes_up'];
            $results[$dateKey]['votes_down'] = $row[0]['new_votes_down'];
        }

        // fetch download counts
        $rows = $model->query("
            SELECT `date`, SUM(`count`) AS total
              FROM `stats_addons_collections_counts` AS `sacc`
             WHERE `collection_id` IN({$in_collections})
               AND `date` BETWEEN '{$startDate}' AND '{$endDate}'
               AND `date` != '0000-00-00'
             GROUP BY `date`
        ");

        // fill in counts for days found in query
        foreach ($rows as $row) {
            $dateKey = $row['sacc']['date'];
            if (!array_key_exists($dateKey, $results)) {
                $results[$dateKey] = $result_row;
                $results[$dateKey]['date'] = $dateKey;
            }
            $results[$dateKey]['downloads'] = $row[0]['total'];
        }

        // fill in any holes in the date range starting with
        // the first date having results (or startDate if specified)
        if (!empty($results) || $startDate != '0000-00-00') {
            $keys = array_keys($results);
            $tsStart = ($startDate == '0000-00-00' ? strtotime($keys[0]): strtotime($startDate));
            $tsEnd = strtotime($endDate);

            for ($ts = $tsStart; $ts <= $tsEnd; $ts = strtotime('+1 day', $ts)) {
                $dateKey = date('Y-m-d', $ts);
                if (!array_key_exists($dateKey, $results)) {
                    $results[$dateKey] = $result_row;
                    $results[$dateKey]['date'] = $dateKey;
                }
            }
        }

        // sort by date and return 
        ksort($results);
        return array_values($results);
    }

    function _getCollectionStatSum($collectionId, $statName, $startDate=null, $endDate=null) {

        if (!is_array($collectionId)) {
            $collectionId = array($collectionId);
        }

        $in_string = '0';
        foreach ($collectionId as $id) {
            $in_string .= ',' . intval($id);
        }

        $startDate = is_null($startDate) ? '0000-00-00' : date('Y-m-d', strtotime($startDate));
        $endDate = is_null($endDate) ? date('Y-m-d') : date('Y-m-d', strtotime($endDate));

        $model =& $this->controller->GlobalStat;

        $result = $model->query("
            SELECT IFNULL(SUM(`count`), 0) AS total
              FROM stats_collections
             WHERE collection_id IN({$in_string})
               AND `name` = '{$statName}'
               AND `date` BETWEEN '{$startDate}' AND '{$endDate}'
               AND `date` != '0000-00-00'
        ");

        return empty($result) ? 0 : $result[0][0]['total'];
    }
}
?>
