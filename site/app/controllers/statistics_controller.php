<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/e
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
 *    Justin Scott <fligtar@mozilla.com> (Original Author)
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

class StatisticsController extends AppController
{
    var $name = 'Statistics';
    var $uses = array('Addon', 'Addontype', 'Application', 'User', 'Version');
    var $components = array('Amo', 'Image', 'Stats');
    var $helpers = array('Html', 'Javascript', 'Listing', 'Localization', 'Statistics', 'Time');

    function __construct() {
        if (QUERY_CACHE) {
            $this->uses[] = 'Memcaching';
        }
        parent::__construct();
    }

   /**
    * Require login for all actions
    */
    function beforeFilter() {
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        // beforeFilter() is apparently called before components are initialized. Cake++
        $this->Amo->startup($this);
        
        // Clean post data
        $this->Amo->clean($this->data); 
        
        $this->layout = 'amo2009';
        $this->pageTitle = _('statistics_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        
        $this->cssAdd = array('stats/stats');
        $this->publish('cssAdd', $this->cssAdd);
        
        $this->jsAdd = array('stats/stats.js',);
        $this->publish('jsAdd', $this->jsAdd);
        
        $prescriptJS = "var statsURL = '".$this->url('/statistics/')."';";
        $this->set('prescriptJS', $prescriptJS);
        
        $this->breadcrumbs = array(_('statistics_pagetitle') => '/statistics/index');
        $this->publish('breadcrumbs', $this->breadcrumbs);
        
        $this->publish('subpagetitle', _('statistics_pagetitle'));
    }
    
   /**
    * Index
    */
    function index($addon_id = 0) {

        // If add-on id was specified, go to its statistics
        if (!empty($addon_id) || !empty($_GET['data']['Addon']['id'])) {
            if (!empty($addon_id))
                $this->addon($addon_id);
            elseif (!empty($_GET['data']['Addon']['id']))
                $this->addon($_GET['data']['Addon']['id']);
            
            return;
        }
        // If not, show the public overview

        $this->jsAdd = array(
            'jquery-compressed.js',
            'strftime-min-1.3.js',
            //'simile/amo-bundle.compressed.js',
            'simile/amo-bundle.js',
            'stats/dropdowns.js',
            'stats/colors.js',
            'stats/plot-data-table.js',
            'stats/stats.js',
            'stats/site-stats.js',
        );
        $this->publish('jsAdd', $this->jsAdd);
        $this->set('prescriptJS', "var Simile_urlPrefix = '{$this->base}/js/simile';");
        
        $this->cssAdd = array(
            'simile/bundle',
            'stats/stats',
            'stats/dropdowns',
        );
        $this->publish('cssAdd', $this->cssAdd);

        // Get site stats overview
        $this->publish('statsOverview', $this->_cachedStats('getSiteStatsOverview', array()));

        $this->publish('jsLocalization', array(
            'addons_downloaded' => ___('statistics_addons_downloaded', 'Add-ons Downloaded'),
            'addons_in_use' => ___('statistics_addons_inuse', 'Add-ons In Use'),
            'addons_created' => ___('statistics_addons_created', 'Add-ons Created'),
            'addons_updated' => ___('statistics_addons_updated', 'Add-ons Updated'),
            'users_created' => ___('statistics_users_created', 'Users Created'),
            'reviews_created' => ___('statistics_reviews_created', 'Reviews Created'),
            'collections_created' => ___('statistics_collections_created', 'Collections Created'),
            'statistics_js_groupby_selector_date' => ___('statistics_js_groupby_selector_date', 'Group by: Day'),
            'statistics_js_groupby_selector_week' => ___('statistics_js_groupby_selector_week', 'Group by: Week'),
            'statistics_js_groupby_selector_month' => ___('statistics_js_groupby_selector_month', 'Group by: Month'),
            'statistics_js_last_30days' => ___('statistics_js_last_30days', 'Show last: 30 days'),
            'statistics_js_last_60days' => ___('statistics_js_last_60days', 'Show last: 60 days'),
            'statistics_js_last_90days' => ___('statistics_js_last_90days', 'Show last: 90 days'),
            'statistics_js_last_18weeks' => ___('statistics_js_last_18weeks', 'Show last: 18 weeks'),
            'statistics_js_last_36weeks' => ___('statistics_js_last_36weeks', 'Show last: 36 weeks'),
            'statistics_js_last_54weeks' => ___('statistics_js_last_54weeks', 'Show last: 54 weeks'),
            'statistics_js_last_12months' => ___('statistics_js_last_12months', 'Show last: 12 months'),
            'statistics_js_last_24months' => ___('statistics_js_last_24months', 'Show last: 24 months'),
            'statistics_js_last_36months' => ___('statistics_js_last_36months', 'Show last: 36 months'),
            'date' => _('date'),
        ));

        // Get initial chart data (daily) - but check cache first
        $this->set('stats', $this->_cachedStats('getSiteStats', array('date')));

        // Build drop-down menu for Add-ons with viewable stats
        $session = $this->Session->read('User');
        if ($session) {
            $addons = $this->Addon->getAddonsByUser($session['id']);
        } else {
            $addons = array();
        }
        $this->publish('addons', $addons);
        
        // If user can access all add-on stats, pull all
        if ($this->SimpleAcl->actionAllowed('Admin', 'ViewAnyStats', $this->Session->read('User'))) {
            $otherAddons = $this->Addon->findAll(null, array('Addon.id', 'Addon.name'), null, null, null, -1);
        }
        else {
            // Otherwise, pull all public stats add-ons
            $otherAddons = $this->Addon->findAll("Addon.publicstats=1", array('Addon.id', 'Addon.name'), null, null, null, -1);
        }
        
        if (!empty($otherAddons)) {
            foreach ($otherAddons as $otherAddon) {
                $name = trim($otherAddon['Translation']['name']['string']);
                if (!empty($name))
                    $other_addons[$otherAddon['Addon']['id']] = substr($name, 0, 50);
            }
            asort($other_addons);
            
            $this->set('otherAddons', $other_addons);
        }
    }
    
   /**
    * Add-on Statistics
    */
    function addon($addon_id) {
        $this->Amo->clean($addon_id);
        $this->publish('addon_id', $addon_id);
        
        $this->Addon->id = $addon_id;
        $this->Addon->unbindFully();
        if (!$addon = $this->Addon->read()) {
            $this->flash(_('error_addon_notfound'), '/statistics/index');
            return;
        }
        
        if (isset($this->namedArgs['format']) && $this->namedArgs['format'] == 'rss')
            $rss = true;
        else
            $rss = false;
        
        // Make sure user has permission to view this add-on's stats
        if (!$this->_checkAccess($addon_id, $addon) && 
            !($rss && !empty($this->namedArgs['key']) && md5($addon['Addon']['created']) == $this->namedArgs['key'])) {
            $this->flash(_('devcp_error_addon_access_denied'), '/developers/index');
            return;
        }
        
        $this->jsAdd = array(
            //'simile/timeplot/timeplot-api.js?local',
            'jquery-compressed.js',
            //'simile/amo-bundle.compressed.js',
            'strftime-min-1.3.js',
            'simile/amo-bundle.js',
            'stats/stats.js',
            'stats/dropdowns.js',
            'stats/colors.js',
            'stats/plot-selection.js',
            'stats/plot-tables.js',
            'stats/plots.js'
            );
        $this->publish('jsAdd', $this->jsAdd);
        
        $this->cssAdd = array(
            'simile/bundle',
            'stats/stats',
            'stats/dropdowns'
        );
        $this->publish('cssAdd', $this->cssAdd);
        
        // We only show the RSS key if the user could access if it wasn't public
        if ($this->Amo->checkOwnership($addon_id, $addon, true) || $this->SimpleAcl->actionAllowed('Admin', 'ViewAnyStats', $this->Session->read('User')))
            $key = "/key:".md5($addon['Addon']['created']);
        else
            $key = '';
        $this->publish('key', $key);
        $this->publish('rssAdd', array("/statistics/addon/{$addon['Addon']['id']}/format:rss{$key}"));
        
        $this->publish('addon', $addon);
        $this->publish('addon_name', $addon['Translation']['name']['string']);
        
        $this->breadcrumbs[sprintf(_('statistics_title_addon_stats'), $addon['Translation']['name']['string'])] = '/statistics/addon/'.$addon_id;
        $this->publish('breadcrumbs', $this->breadcrumbs);
        
        $prescriptJS = "var Simile_urlPrefix = '".SITE_URL.$this->base.'/js/simile'."';";
        $this->set('prescriptJS', $prescriptJS);
        
        $session = $this->Session->read('User');
        $this->publish('email', $session['email']);
        $this->set('all_addons', empty($session['id']) ? 
		array() : $this->Addon->getAddonsByUser($session['id']));
        
        // Add-on icon
        $addonIcon = $this->Image->getAddonIconURL($addon_id);
        $this->publish('addonIcon', $addonIcon);
        
        // Data for overview
        $stats = array('totaldownloads' => 0);
        if (!$this->Config->getValue('stats_disabled') || $this->SimpleAcl->actionAllowed('*', '*', $this->Session->read('User'))) {
            if ($statsQry = $this->Addon->query("SELECT totaldownloads, weeklydownloads, average_daily_downloads, average_daily_users FROM addons WHERE id={$addon_id}", true)) {      
                $stats['totaldownloads'] = $statsQry[0]['addons']['totaldownloads'];
                $stats['avg_downloads']  = $statsQry[0]['addons']['average_daily_downloads'];
                $stats['weeklydownloads'] = $statsQry[0]['addons']['weeklydownloads'];
                $stats['avg_updatepings'] = $statsQry[0]['addons']['average_daily_users'];
            }
            if ($statsQry = $this->Addon->query("SELECT count, date FROM update_counts WHERE addon_id={$addon_id} ORDER BY date DESC LIMIT 2" ,true)) {
                $stats['last_updatepings'] = $statsQry[0]['update_counts']['count'];
                $stats['last_updatepings_date'] = $statsQry[0]['update_counts']['date'];
                $stats['previous_updatepings'] = $statsQry[1]['update_counts']['count'];
                $stats['previous_updatepings_date'] = $statsQry[1]['update_counts']['date'];
                $stats['updateping_change'] = (($stats['last_updatepings'] - $stats['previous_updatepings']) / $stats['previous_updatepings']) * 100;
            }
            if ($statsQry = $this->Addon->query("SELECT count, date FROM download_counts WHERE addon_id={$addon_id} ORDER BY date DESC LIMIT 1", true)) {
                $stats['last_downloads'] = $statsQry[0]['download_counts']['count'];
                $stats['last_downloads_date'] = $statsQry[0]['download_counts']['date'];
            }
            // Grouping by week is not quite what we want, since the current
            // week is going to be shorter than (the complete) last week.
            // If you know how to get (now, now - 7), (now - 8, now - 14) ranges
            // in a group by, feel free to improve this.
            $thisWeek = $this->Addon->query("SELECT AVG(count) as `count` FROM update_counts
                                             WHERE addon_id={$addon_id}
                                               AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $prevWeek = $this->Addon->query("SELECT AVG(count) as `count` FROM update_counts
                                             WHERE addon_id={$addon_id}
                                               AND date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                               AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)");
            if ($thisWeek && $prevWeek) {
                $thisWeek = $thisWeek[0][0]['count'];
                $prevWeek = $prevWeek[0][0]['count'];
                $stats['weekly_updatepings'] = $thisWeek;
                $stats['weekly_updatepings_change'] = $prevWeek > 0 ? (($thisWeek - $prevWeek) / $prevWeek) * 100 : 0;
            }
        }
        $this->set('stats', $stats);
        $this->pageTitle = $addon['Translation']['name']['string'].' :: '._('statistics_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        
        if (!$rss) {
            $this->publish('jsLocalization', array(
                    'date' => _('date'),
                    'statistics_js_dropdowns_removeplot' => _('statistics_js_dropdowns_removeplot'),
                    'statistics_js_dropdowns_none' => _('statistics_js_dropdowns_none'),
                    'statistics_js_download_csv' => ___('statistics_js_download_csv', 'View this table in CSV format'),
                    'statistics_js_groupby_selector_date' => ___('statistics_js_groupby_selector_date', 'Group by: Day'),
                    'statistics_js_groupby_selector_week' => ___('statistics_js_groupby_selector_week', 'Group by: Week'),
                    'statistics_js_groupby_selector_week_over_week' => ___('statistics_js_groupby_selector_week_over_week', 'Compare by: Week'),
                    'statistics_js_groupby_selector_month' => ___('statistics_js_groupby_selector_month', 'Group by: Month'),
                    'statistics_js_plotselection_selector_summary' => _('statistics_js_plotselection_selector_summary'),
                    'statistics_js_plotselection_selector_downloads' => _('statistics_js_plotselection_selector_downloads'),
                    'statistics_js_plotselection_selector_adu' => _('statistics_js_plotselection_selector_adu'),
                    'statistics_js_plotselection_selector_version' => _('statistics_js_plotselection_selector_version'),
                    'statistics_js_plotselection_selector_application' => _('statistics_js_plotselection_selector_application'),
                    'statistics_js_plotselection_selector_status' => _('statistics_js_plotselection_selector_status'),
                    'statistics_js_plotselection_selector_os' => _('statistics_js_plotselection_selector_os'),
                    'statistics_js_plotselection_selector_custom' => _('statistics_js_plotselection_selector_custom'),
                    'statistics_js_plotselection_foundinrange' => _('statistics_js_plotselection_foundinrange'),
                    'statistics_js_plotselection_options_count_name_checked' => _('statistics_js_plotselection_options_count_name_checked'),
                    'statistics_js_plotselection_options_count_name_unchecked' => _('statistics_js_plotselection_options_count_name_unchecked'),
                    'statistics_js_plotselection_options_count_tooltip' => _('statistics_js_plotselection_options_count_tooltip'),
                    'statistics_js_plotselection_options_events_firefox_name_checked' => _('statistics_js_plotselection_options_events_firefox_name_checked'),
                    'statistics_js_plotselection_options_events_firefox_name_unchecked' => _('statistics_js_plotselection_options_events_firefox_name_unchecked'),
                    'statistics_js_plotselection_options_events_firefox_tooltip' => _('statistics_js_plotselection_options_events_firefox_tooltip'),
                    'statistics_js_plotselection_options_events_addon_name_checked' => _('statistics_js_plotselection_options_events_addon_name_checked'),
                    'statistics_js_plotselection_options_events_addon_name_unchecked' => _('statistics_js_plotselection_options_events_addon_name_unchecked'),
                    'statistics_js_plotselection_options_events_addon_tooltip' => _('statistics_js_plotselection_options_events_addon_tooltip'),
                    'statistics_js_plotselection_options_addplot_name' => _('statistics_js_plotselection_options_addplot_name'),
                    'statistics_js_plotselection_options_addplot_tooltip' => _('statistics_js_plotselection_options_addplot_tooltip'),
                    'statistics_js_plotselection_options_resize_name_checked' => _('statistics_js_plotselection_options_resize_name_checked'),
                    'statistics_js_plotselection_options_resize_name_unchecked' => _('statistics_js_plotselection_options_resize_name_unchecked'),
                    'statistics_js_plotselection_options_resize_tooltip' => _('statistics_js_plotselection_options_resize_tooltip'),
                    'statistics_js_plotselection_options_csv_name' => _('statistics_js_plotselection_options_csv_name'),
                    'statistics_js_plotselection_options_csv_tooltip' => _('statistics_js_plotselection_options_csv_tooltip')
                ));
            
            $this->render('addon', 'amo2009');
        }
        else {
            $this->publish('rss_title', sprintf(_('statistics_title_addon_stats'), $addon['Translation']['name']['string']));
            $this->publish('rss_description', sprintf(_('statistics_rss_description'), $addon['Translation']['name']['string']));
            $this->render('rss/summary', 'rss');
        }
    }
    
    /**
     * CSV data for site stats
     */
    function sitecsv($plot=null) {
        if (!in_array($plot, array('date', 'week', 'month'))) {
            header('HTTP/1.1 404 Not Found');
            $this->flash(___('statistics_csv_not_found', 'CSV data not found'), "/statistics/");
            return;
        }

        $this->publish('plot', $plot);
        
        $csv = $this->_cachedStats('getSiteStats', array($plot));
        
        $this->set('csv', $csv);
        $this->render('csv', 'ajax');
    }
    
    /**
     * CSV data
     */
    function csv($addon_id, $plot) {
        $this->publish('addon_id', $addon_id);
        $this->publish('plot', $plot);
        
        $addon = $this->Addon->find("Addon.id={$addon_id}", null, null, -1);
        
        // Make sure user has permission to view this add-on's stats
        if (!$this->_checkAccess($addon_id, $addon)) {
            $this->Amo->accessDenied();
            return;
        }
        
        $csv = $this->Stats->historicalPlot($addon_id, $plot, @$_GET['group_by']);
        
        $this->set('csv', $csv);
        $this->render('csv', 'ajax');
    }
    
    /**
     * JSON data
     */
    function json($addon_id, $type) {
        $this->publish('addon_id', $addon_id);
        
        $addon = $this->Addon->find("Addon.id={$addon_id}", null, null, -1);
        
        // Make sure user has permission to view this add-on's stats
        if (!$this->_checkAccess($addon_id, $addon))
            return;
        
        if ($type == 'summary')
            $json = $this->Stats->getSummary($addon_id);
        
        $this->set('json', $json);
        $this->render('json', 'ajax');
    }
    
    /**
     * XML data
     */
    function xml($action, $type, $addon_id = 0) {
        $addon = $this->Addon->find("Addon.id={$addon_id}", null, null, -1);
        
        if ($action == 'events') {
            if ($type == 'addon') {
                $xml = $this->Stats->getEventsAddon($addon_id);
            }
            else {
                $xml = $this->Stats->getEventsApp($type);
            }
        }
        
        $this->set('xml', $xml);
        $this->render('xml', 'ajax');
    }
    
    /**
     * Change dashboard settings
     */
    function settings($addon_id) {
        $this->Amo->clean($addon_id);
        $this->publish('addon_id', $addon_id);
        
        // Disable query caching
        foreach ($this->uses as $_model) {
            $this->$_model->caching = false;
        }
        
        // Make sure user is owner in order to change this setting
        if (!$this->Amo->checkOwnership($addon_id, null, true)) {
            $this->flash(_('devcp_error_addon_access_denied'), '/statistics/addon/'.$addon_id);
            return;
        }
        
        $this->Addon->id = $addon_id;
        
        // Save data if POSTed
        if (!empty($this->data)) {
            $this->Addon->save($this->data);
            
            $this->redirect('/statistics/addon/'.$addon_id.'/?settings');
            return;
        }
        
        $addon = $this->Addon->read();
        $this->publish('addon', $addon);
        
        $session = $this->Session->read('User');
        $this->publish('all_addons', $this->Addon->getAddonsByUser($session['id']));
        
        $this->render('settings');
    }
    
    /**
     * Determines if a user has access to view the add-on's stats
     */
    function _checkAccess($addon_id, $addon) {
        // If add-on has opted-in to public stats, allow access
        if ($addon['Addon']['publicstats'] == 1)
            return true;
        
        // If not publicstats, make sure logged in
        if (!$this->Session->check('User'))
            return false;
        
        // If user can view any add-on's stats, allow access
        if ($this->SimpleAcl->actionAllowed('Admin', 'ViewAnyStats', $this->Session->read('User')))
            return true;
        
        // If user is the add-on's author, allow access
        if ($this->Amo->checkOwnership($addon_id, $addon))
            return true;
        
        // If no access yet, no access at all
        return false;
    }

    /**
     * Wraps caching around Stats component method calls
     *
     * @param string $statsMethod method name of Stats component
     * @param array $args aruments for $statsMethod
     * @param int $expiration (optional) time to live
     * @return mixed whatever Stats component returns
     */
    function _cachedStats($statsMethod, $args, $expiration=CACHE_PAGES_FOR) {
        $stats = false;

        if (isset($this->Memcaching)) {
            $cacheKey = MEMCACHE_PREFIX.md5($statsMethod . serialize($args));
            $stats = $this->Memcaching->get($cacheKey);
        }

        if ($stats === false) {
            $stats = call_user_func_array(array(&$this->Stats, $statsMethod), $args);
            if (isset($this->Memcaching)) {
                $this->Memcaching->set($cacheKey, $stats, $expiration);
            }
        }

        return $stats;
    }
    
}

?>
