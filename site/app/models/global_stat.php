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
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   l.m.orchard <lorchard@mozilla.com>
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

class GlobalStat extends AppModel
{
    var $name = "GlobalStat";
    var $useTable = 'global_stats';

    /**
     * Shortcut to fetch a named count for a given date. If no date is given,
     * will return the most recent available.
     *
     * @param  string name
     * @param  string date
     * @return numeric
     */
    function getNamedCount($name, $date = '') {
        $conditions = array('name' => $name);
        
        if (!empty($date)) {
            $conditions['date'] = $date;
        }
        
        $rv = $this->find($conditions, null, 'date DESC');
        
        if (empty($rv)) return 0;
        
        return $rv['GlobalStat']['count'];
    }
    
    /**
     * Fetches the most recent stat up to a certain date.
     *
     * @param  string name
     * @param  string date
     * @return array
     */
    function getUpdatepingsUpToDate($name, $date = '') {
        if (empty($date)) {
            $date = date('Y-m-d');
        }
        $conditions = "name = '{$name}' AND date <= '{$date}'";
        
        $rv = $this->find($conditions, null, 'date DESC');
        
        if (empty($rv)) return 0;
        
        return $rv['GlobalStat'];
    }

    /**
     * Fetch a single stat over an optional range of dates
     *
     * @param  string $name name of stat
     * @param  string $startDate (optional) start of date range
     * @param  string $endDate (optional) end of date range
     * @return array same format as getNamedWeeklySum and getNamedMonthlySum
     */
    function getNamedDaily($name, $startDate=null, $endDate=null) {
        $results = array();
        $conditions = array('name' => $name);
        if (!is_null($startDate)) {
            $conditions['date'] = ">= {$startDate}";
        }
        if (!is_null($endDate)) {
            $conditions['and'] = array('date' => "<= {$endDate}");
        }

        if ($rows = $this->findAll($conditions, array('date', 'count'), '`date` DESC')) {
            foreach ($rows as &$row) {
                $results[$row['GlobalStat']['date']] = $row['GlobalStat']['count'];
            }
        }
        return $results;
    }

    /**
     * Fetch weekly sums of a stat
     *
     * @param  string $name name of stat
     * @param  string $startDate (optional) start of date range
     * @param  string $endDate (optional) end of date range
     * @return array same format as getNamedDailySum and getNamedMonthlySum
     */
    function getNamedWeeklySum($name, $startDate=null, $endDate=null) {
        $weekStart = "DATE_ADD(`date`, INTERVAL(1-DAYOFWEEK(`date`)) DAY)";
        $aggregate = "SUM(`count`)";
        return $this->_getDateGroupedAggregate($weekStart, $aggregate, $name, $startDate, $endDate);
    }

    /**
     * Fetch monthly sums of a stat
     *
     * @param  string $name name of stat
     * @param  string $startDate (optional) start of date range
     * @param  string $endDate (optional) end of date range
     * @return array same format as getNamedDailySum and getNamedWeeklySum
     */
    function getNamedMonthlySum($name, $startDate=null, $endDate=null) {
        $monthStart = "DATE_FORMAT(`date`, '%Y-%m-01')";
        $aggregate = "SUM(`count`)";
        return $this->_getDateGroupedAggregate($monthStart, $aggregate, $name, $startDate, $endDate);
    }

    /**
     * Fetch weekly maximums of a stat
     *
     * @param  string $name name of stat
     * @param  string $startDate (optional) start of date range
     * @param  string $endDate (optional) end of date range
     * @return array same format as getNamedDailySum and getNamedMonthlySum
     */
    function getNamedWeeklyMax($name, $startDate=null, $endDate=null) {
        $weekStart = "DATE_ADD(`date`, INTERVAL(1-DAYOFWEEK(`date`)) DAY)";
        $aggregate = "MAX(`count`)";
        return $this->_getDateGroupedAggregate($weekStart, $aggregate, $name, $startDate, $endDate);
    }

    /**
     * Fetch monthly maximums of a stat
     *
     * @param  string $name name of stat
     * @param  string $startDate (optional) start of date range
     * @param  string $endDate (optional) end of date range
     * @return array same format as getNamedDailySum and getNamedWeeklySum
     */
    function getNamedMonthlyMax($name, $startDate=null, $endDate=null) {
        $monthStart = "DATE_FORMAT(`date`, '%Y-%m-01')";
        $aggregate = "MAX(`count`)";
        return $this->_getDateGroupedAggregate($monthStart, $aggregate, $name, $startDate, $endDate);
    }

    /**
     * Fetch sums of a stat grouped by a date expression
     *
     * @private
     * @param  string $groupBy the expression on the `date` field to group by
     * @param  string $aggregate an aggregate expression on the `count` field
     * @param  string $name name of stat
     * @param  string $startDate (optional) start of date range
     * @param  string $endDate (optional) end of date range
     * @return array
     */
    function _getDateGroupedAggregate($groupBy, $aggregate, $name, $startDate=null, $endDate=null) {
        $results = array();

        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $e_name = $db->value($name);

        $dateConditions = '';
        if (!is_null($startDate)) {
            $dateConditions .= " AND `date` >= " . $db->value($startDate);
        }
        if (!is_null($endDate)) {
            $dateConditions .= " AND `date` <= " . $db->value($endDate);
        }

        $sql = "SELECT {$groupBy} AS `datething`, {$aggregate} AS `total`
                  FROM `global_stats`
                 WHERE `name` = {$e_name}
                       {$dateConditions}
              GROUP BY `datething` DESC";

        if ($rows = $this->query($sql)) {
            foreach ($rows as &$row) {
                $results[$row[0]['datething']] = $row[0]['total'];
            }
        }
        return $results;
    }

}
