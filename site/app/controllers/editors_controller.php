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
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *      Wil Clouser <clouserw@gmail.com>
 *      Frederic Wenzel <fwenzel@mozilla.com>
 *      Les Orchard <lorchard@mozilla.com>
 *      Cesar Oliveira <a.sacred.line@gmail.com>
 *      Scott McCammon <smccammon@mozilla.com>
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

class EditorsController extends AppController
{
    var $name = 'Editors';
    var $uses = array('Addon', 'AddonCategory', 'Addontype', 'Application', 'Approval',
        'Appversion', 'Cannedresponse', 'EditorSubscription', 'Eventlog', 'Favorite',
        'File', 'Platform', 'Review', 'ReviewsModerationFlag', 'Category', 'Translation',
        'User', 'Version', 'Versioncomment', 'TestGroup', 'TestResult');
    var $components = array('Amo', 'Audit', 'Developers', 'Editors', 'Email', 'Error', 'Image', 'Markdown', 'Pagination');
    var $helpers = array('Html', 'Javascript', 'Ajax', 'Listing', 'Localization', 'Pagination');

   /**
    * Require login for all actions
    */
    function beforeFilter() {
        //beforeFilter() is apparently called before components are initialized. Cake++
        $this->Amo->startup($this);
        $this->Editors->startup($this);

        $this->Amo->checkLoggedIn();

        $this->layout = 'mozilla';
        $this->pageTitle = ___('Editor Tools', 'editors_pagetitle').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);

        $this->cssAdd = array('editors', 'admin', 'validation');
        $this->publish('cssAdd', $this->cssAdd);

        $this->jsAdd = array('jquery-compressed.js',
                                'jquery.autocomplete.pack.js',
                                'jquery.tablesorter.min.js',
                                'jquery.flot.js',
                                'jquery.sparkline.min.js',
                                'editors');
        $this->publish('jsAdd', $this->jsAdd);

        $this->breadcrumbs = array(___('Editor Tools', 'editors_pagetitle') => '/editors/index');
        $this->publish('breadcrumbs', $this->breadcrumbs);
        $this->publish('suppressJQuery', 1);

        $this->publish('subpagetitle', ___('Editor Tools', 'editors_pagetitle'));

        // disable query caching so devcp changes are visible immediately
        foreach ($this->uses as $_model) {
            $this->$_model->caching = false;
        }

        //Get counts
        $count['pending'] = $this->_getCount('pending');
        $count['nominated'] = $this->_getCount('nominated');
        $count['reviews'] = $this->_getCount('reviews');
        $this->publish('count', $count);
    }

   /**
    * Index
    */
    function index() {
        $this->summary();
    }

   /**
    * Summary
    */
    function summary() {
        $this->cssAdd[] = 'summary';
        $this->publish('cssAdd', $this->cssAdd);

        //Total reviews
        $totalReviews = $this->Approval->query("SELECT users.firstname, users.lastname, COUNT(*) as total FROM approvals LEFT JOIN users ON users.id=approvals.user_id GROUP BY approvals.user_id ORDER BY total DESC LIMIT 5");
        $this->set('totalReviews', $totalReviews);

        //Reviews this month
        $monthStart = date('Y-m-01');
        $monthReviews = $this->Approval->query("SELECT users.firstname, users.lastname, COUNT(*) as total FROM approvals LEFT JOIN users ON users.id=approvals.user_id WHERE approvals.created >= '{$monthStart} 00:00:00' GROUP BY approvals.user_id ORDER BY total DESC LIMIT 5");
        $this->set('monthReviews', $monthReviews);

        //New editors
        $newEditors = $this->Eventlog->query("SELECT users.firstname, users.lastname, eventlog.created FROM eventlog LEFT JOIN users ON eventlog.added=users.id WHERE eventlog.type='admin' AND eventlog.action='group_addmember' AND eventlog.changed_id='2' ORDER BY eventlog.created DESC LIMIT 5");
        $this->set('newEditors', $newEditors);

        //Recent activity
        $logs = $this->Eventlog->findAll(array('type' => 'editor'), null, 'Eventlog.created DESC', 5);
        $logs = $this->Audit->explainLog($logs);
        $this->set('logs', $logs);

        $this->set('page', 'summary');
        $this->render('summary');
    }

   /**
    * Review queue
    */
    function queue($mode = 'pending') {
        //If queues are disabled, show appropriate error
        if ($this->Config->getValue('queues_disabled') == 1 && !$this->SimpleAcl->actionAllowed('*', '*', $this->Session->read('User'))) {
            $this->flash(___('All review queues are currently disabled. Please check back at a later time.'), '/', 3);
            return;
        }

        // if num=... argument is set, jump to specific item in queue
        if (isset($this->params['url']['num']) && is_numeric($this->params['url']['num']))
            $this->Editors->redirectByQueueRank($mode, $this->params['url']['num']);

        $this->publish('collapse_categories', true);

        $this->Amo->clean($mode);
        $this->breadcrumbs[___('Review Queue')] = '/editors/queue';
        $this->publish('breadcrumbs', $this->breadcrumbs);
        $this->publish('subpagetitle', ___('Review Queue'));

        $this->publish('mode', $mode);

        // Setup queue filter
        if (array_key_exists('filter', $this->params['form'])) {
            // set a new filter on this queue
            $filter = $this->Editors->setQueueFilter($mode, $this->data['Filter']);

        } elseif (array_key_exists('clear', $this->params['form'])) {
            // clear existing filter on this queue
            $filter = $this->Editors->setQueueFilter($mode, null);

            // clear sorting
            $this->Editors->setQueueSort($mode, 'default');

        } else {
            // fetch existing filter
            $filter = $this->Editors->getQueueFilter($mode);
        }

        // Handle changes to sorting
        if (isset($this->params['url']['sort'])) {
            if (isset($this->params['url']['dir'])) {
                $this->Editors->setQueueSort($mode, $this->params['url']['sort'], $this->params['url']['dir']);
            } else {
                $this->Editors->setQueueSort($mode, $this->params['url']['sort']);
            }
        }

        // Build the queue
        if ($mode == 'pending' || $mode == 'nominated') {
            $addons = $this->_buildQueue($mode);
        }
        elseif ($mode == 'reviews') {
            $this->_reviews($this->_getCount('reviews'));
            return;
        }
        else {
            $this->redirect('/editors');
            return;
        }

        //Setup filter form fields
        $selected = array('Application'=>'', 'MaxVersion'=>'', 'AdminFlag'=>'',
                        'SubmissionAge'=>'', 'Addontype'=>'', 'Platform'=>'',);
        if (is_array($filter)) {
            foreach ($filter as $k => $val) {
                if (array_key_exists($k, $selected)) {
                    $selected[$k] = $val;
                }
            }
        }

        $addonOrAuthor = isset($filter['AddonOrAuthor']) ? $filter['AddonOrAuthor'] : '';

        $maxVersions = array();
        if (!empty($filter['Application'])) {
            $app_versions = $this->Appversion->findAllByApplicationId($filter['Application'],
                                array('Appversion.id', 'Appversion.version'), 'Appversion.version DESC');
            foreach ($app_versions as $av) {
                $maxVersions[$av['Appversion']['id']] = $av['Appversion']['version'];
            }
        }

        $submissionAges = array('1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5',
                                '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10+' => '10+');
        $platforms = $this->Amo->getPlatformName();
        $applications = $this->Amo->getApplicationName();
        $flags = array('0'=>___('no'), '1'=>___('yes'));

        $filtered = !empty($filter);
        $filterChanged = $filtered && array_key_exists('filter', $this->params['form']);
        $filteredCount = $this->_getCount($mode, true);

        $sortOpts = $this->Editors->getQueueSort($mode);

        // raw values for selectTags
        $this->set('selected', $selected);
        $this->set('platforms', $platforms);
        $this->set('addontypes', $this->Addontype->getNames());
        $this->set('applications', $applications);
        $this->set('maxVersions', $maxVersions);
        $this->set('submissionAges', $submissionAges);
        $this->set('flags', $flags);

        $this->publish('addonOrAuthor', $addonOrAuthor);
        $this->publish('filtered', $filtered);
        $this->publish('filterChanged', $filterChanged);
        $this->publish('filteredCount', $filteredCount);
        $this->publish('sortBy', $sortOpts['sortby']);
        $this->publish('sortDir', $sortOpts['direction']);

        $this->publish('mode', $mode);
        $this->publish('addons', $addons);
        $this->render('queue');
    }

   /**
    * Review a specific version
    * @param int $id The version id
    */
    function review($id) {
        $this->Amo->clean($id);
        $this->publish('subpagetitle', ___('Review Add-on'));
        $this->breadcrumbs[___('Review Add-on')] = '/editors/review/'.$id;
        $this->publish('breadcrumbs', $this->breadcrumbs);
        $this->publish('collapse_categories', true);
        $this->cssAdd[] = '../vendors/markitup/skins/simple/style';
        $this->cssAdd[] = '../vendors/syntaxhighlighter/styles/shCore';
        $this->cssAdd[] = '../vendors/syntaxhighlighter/styles/shThemeDefault';
        $this->publish('cssAdd', $this->cssAdd);

        $this->jsAdd[] = '../vendors/markitup/jquery.markitup.pack.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shCore.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushCss.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushDiff.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushJScript.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushPlain.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushSql.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushXml.js';
        $this->publish('jsAdd', $this->jsAdd);

        $this->set('motd', $this->_get_motd());

        //Bind necessary models
        $this->User->bindFully();
        $this->Addontype->bindFully();
        $this->Version->bindFully();
        $this->Addon->bindFully();
        $this->Versioncomment->bindFully();

        if (!$version = $this->Version->findById($id, null, null, 1)) {
            $this->flash(___('Version not found!'), '/editors/queue');
            return;
        }

        if (!$addon = $this->Addon->findById($version['Version']['addon_id'])) {
            $this->flash(___('Add-on not found!'), '/editors/queue');
            return;
        }

        //Make sure user is not an author (or is an admin)
        $session = $this->Session->read('User');
        if (!$this->SimpleAcl->actionAllowed('*', '*', $session)) {
            foreach ($addon['User'] as $author) {
                if ($author['id'] == $session['id']) {
                    $this->flash(___('Self-reviews are not allowed.'), '/editors/queue');
                    return;
                }
            }
        }

        if (!empty($this->data)) {
            //pr($this->data);
            if (isset($this->data['Versioncomment'])) {
                // new editor comment
                $commentId = $this->Editors->postVersionComment($id, $this->data);
            }
            elseif ($this->data['Approval']['ActionField'] == 'info') {
                // request more information
                $this->Editors->requestInformation($addon, $this->data);
            }
            elseif ($this->data['Approval']['Type'] == 'nominated') {
                $this->Editors->reviewNominatedAddon($addon, $this->data);
            }
            else {
                $this->Editors->reviewPendingFiles($addon, $this->data);
            }

            if ($this->Error->noErrors()) {
                if (isset($this->data['Versioncomment'])) {
                    // autosubscribe to notifications
                    $threadRoot = $this->Versioncomment->getThreadRoot($id, $commentId);
                    if ($threadRoot) {
                        $this->Versioncomment->subscribe($threadRoot['Versioncomment']['id'], $session['id']);
                    }

                    // notify subscribed editors of post
                    $this->Editors->versionCommentNotify($commentId, $threadRoot['Versioncomment']['id']);

                    // propagate queue rank (if any) to the redirect
                    $redirectUrl = "/editors/review/{$id}";
                    if (isset($this->params['url']['num']) && is_numeric($this->params['url']['num'])) {
                        $redirectUrl .= "?num={$this->params['url']['num']}";
                    }
                    $redirectUrl .= "#editorComment{$commentId}";

                    $this->flash(___('Comment successfully posted'), $redirectUrl);
                    return;
                }

                // if editor chose to be reminded of the next upcoming update, save this
                if ($this->data['Approval']['subscribe'])
                    $this->EditorSubscription->subscribeToUpdates($session['id'], $addon['Addon']['id']);

                $this->flash(___('Review successfully processed.'), '/editors/queue/'.$this->data['Approval']['Type']);
                return;
            }
        }

        $this->pageTitle = $addon['Translation']['name']['string'] . ' :: ' . $this->pageTitle;

        if (!empty($addon['Category'])) {
            foreach ($addon['Category'] as $category) {
                $categories[] = $category['id'];
            }
            $addon['Categories'] = $this->Category->findAll("Category.id IN (".implode(', ', $categories).")");
        }
        else
            $addon['Categories'] = array();

        $platforms = $this->Amo->getPlatformName();

        //get min/max versions
        if ($targetApps = $this->Amo->getMinMaxVersions($id)) {
            foreach ($targetApps as $targetApp) {
                $appName = $targetApp['translations']['localized_string'];
                $addon['targetApps'][$appName]['min'] = $targetApp['min']['version'];
                $addon['targetApps'][$appName]['max'] = $targetApp['max']['version'];
            }
        }

        $fileIds = array();
        if (!empty($version['File'])) {
            $version['pendingCount'] = 0;
            foreach ($version['File'] as $k => $file) {
                $fileIds[] = $file['id'];
                $version['File'][$k]['counts'] = array(0,0,0);
                $version['File'][$k]['groups'] = array();

                if ($file['status'] == STATUS_PENDING) {
                    $version['File'][$k]['disabled'] = 'false';
                    $version['pendingCount']++;
                }
                else {
                    $version['File'][$k]['disabled'] = 'true';
                }
            }
        }

        if ($responses = $this->Cannedresponse->findAll()) {
            foreach ($responses as $response) {
                $cannedresponses[$response['Translation']['response']['string']] = $response['Translation']['name']['string'];
            }
            $this->publish('cannedresponses', $cannedresponses);
        }

        $this->publish('jsLocalization', array( 'action' => ___('Review Action'),
                                            'comments' => ___('Review Comments'),
                                            'os' => ___('Tested Operating Systems'),
                                            'applications' => ___('Tested Application'),
                                            'errors' => ___('Please complete the following fields:'),
                                            'files' => ___('Please select at least one file to review.', 'editors_error_review_one_file')
                                    ));

        // Validation results
        $test_groups = $this->TestGroup->getTestGroupsForAddonType($addon['Addon']['addontype_id']);
        if (!empty($test_groups)) {
            foreach ($test_groups as &$group) {
                $group['counts'] = array(0,0,0);
            }
        }

        $fileIds = implode(', ', $fileIds);

        $counts = $this->TestResult->query("SELECT `test_group_id`, `result`, `file_id`, count(*) as `count` FROM `test_results` INNER JOIN `test_cases` ON `test_case_id` = `test_cases`.`id` WHERE `file_id` IN ({$fileIds}) GROUP BY `test_group_id`, `result`, `file_id`");

        if (!empty($counts)) {
            foreach ($counts as $count) {

                if (!empty($version['File'])) {
                    foreach ($version['File'] as &$file) {

                        $file['counts'][$count['test_results']['result']] += $count[0]['count'];

                        if (!empty($test_groups)) {
                            foreach ($test_groups as $group_id => &$group) {
                                if ($group['TestGroup']['id'] == $count['test_cases']['test_group_id']) {
                                    $group['counts'][$count['test_results']['result']] = $count[0]['count'];
                                    $file['groups'][$group['TestGroup']['id']] = $group;
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->publish('test_groups', $test_groups);

        //Review History
        if ($history = $this->Approval->findAll(array('Approval.addon_id' => $addon['Addon']['id'], 'reply_to IS NULL'))) {
            foreach ($history as $k => &$hist) {
                if (!empty($hist['File']['id'])) {
                    $vLookup = $this->Version->findById($hist['File']['version_id'], array('Version.version'));
                    $history[$k] = array_merge_recursive($history[$k], $vLookup);
                }

                // add replies to information requests
                if ($hist['Approval']['reviewtype'] == 'info') {
                    $hist['replies'] = $this->Approval->findAll(array('Approval.reply_to' => $hist['Approval']['id']), null, 'Approval.created');
                }

                // add editor comment count
                if (!empty($hist['Version']['id'])) {
                    $hist['commentCount'] = $this->Versioncomment->getCommentCount($hist['Version']['id']);
                }
            }
            unset($hist); // PHP bug 35106
        }

        //pr($history);

        //Editor Comments
        $comments = $this->Versioncomment->getThreadTree($version['Version']['id']);
        $comments = $this->Markdown->htmlForKey($comments, 'comment');

        // skip sanitizing comment fields - they were handled by markdown
        $this->dontsanitize[] = 'comment';
        $this->publish('comments', $comments);
        array_pop($this->dontsanitize);

        //pr($comments);

        if ($addon['Addon']['status'] == STATUS_NOMINATED) {
            $reviewType = 'nominated';
        } else {
            $reviewType = 'pending';
        }

        // count of filtered queue
        $filtered = (true && $this->Editors->getQueueFilter($reviewType));
        $filteredCount = $this->_getCount($reviewType, true);

        // rank in nomination/update queue
        if (isset($this->params['url']['num']) && is_numeric($this->params['url']['num']))
            $queueRank = $this->params['url']['num'];
        else
            $queueRank = false;
        $this->publish('queueRank', $queueRank);

        $this->publish('has_public', $this->File->getLatestFileByAddonId($addon['Addon']['id']) != 0);
        $this->publish('addon', $addon);
        $this->publish('version', $version);
        $this->publish('platforms', $platforms);
        $this->publish('addontypes', $this->Addontype->getNames());
        $this->publish('addontype', $addon['Addon']['addontype_id']);
        $this->publish('approval', $this->Amo->getApprovalStatus());
        $this->publish('history', $history);
        $this->publish('errors', $this->Error->errors);
        $this->publish('reviewType', $reviewType, false);
        $this->publish('filtered', $filtered);
        $this->publish('filteredCount', $filteredCount);
        $this->publish('subscriptions', $this->Versioncomment->getSubscriptionsByUser($session['id']));
        $this->publish('jsLocalization', array(
            'editors_review_bold' => ___('Bold'),
            'editors_review_italics' => ___('Italics'),
            'editors_review_unordered_lists' => ___('Unordered List'),
            'editors_review_ordered_lists' => ___('Ordered List'),
            'editors_review_block_quotes' => ___('Block Quote'),
            'editors_review_code_blocks' => ___('Code Block'),
            'editors_review_code_text' => ___('Plain Text'),
            'editors_review_code_html' => ___('HTML'),
            'editors_review_code_css' => ___('CSS'),
            'editors_review_code_javascript_xul' => ___('Javascript / XUL'),
            'editors_review_code_diff' => ___('Diff / Patch'),
            'editors_review_code_sql' => ___('SQL'),
            'editors_markdown_preview' => ___('Preview'),
            'editors_review_comment_help_heading' => ___('Comment Help'),
            'editors_syntax_view_source' => ___('View Source'),
            'editors_syntax_print' => ___('Print'),
            'editors_syntax_about' => ___('About', 'editors_syntax_about'),
        ));

        $this->render('review');
    }

   /**
    * Reads the approval file
    * @param int $id The file id
    */
    function file($id) {
        $this->Amo->clean($id);
        $this->File->id = $id;

        if (!$file = $this->File->read()) {
            $this->flash(___('File not found!'), '/editors/queue');
        }

        $this->Addon->id = $file['Version']['addon_id'];
        $this->Version->id = $file['Version']['id'];

        $filename = $file['File']['filename'];
        $file = REPO_PATH.'/'.$this->Addon->id.'/'.$filename;

        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . filesize($file));
            header('Content-Disposition: attachment; filename=' . $filename);

            readfile($file);
        }
        else {
            $this->flash(sprintf(___('File error: %s does not exist.'), $file), '/editors/review/'.$this->Version->id);
        }
        exit;
    }


    /**
     * Performance reports jump off point
     * Handles report selection, and user parameter
     */
    function performance($mode = '') {
        $session = $this->Session->read('User');

        //Senior Editors can generate reports on anyone
        $isSenior = $this->SimpleAcl->actionAllowed('Admin', 'EditAnyAddon', $session);
        if ($isSenior && !empty($this->params['url']['user'])) {
            $user = $this->User->findByEmail($this->params['url']['user']);
            if (empty($user)) {
                header('HTTP/1.1 404 Not Found');
                $this->flash(___('User not found'), "/editors/performance/{$mode}");
                return;
            }
        } else {
            $user = $this->User->findById($session['id']);
        }

        //Chart AJAX
        // @TODO: enable this and make charts use ajax to load new data
        if (false && $mode == 'chartData') {
            $summary = !empty($this->params['url']['sum']) ? $this->params['url']['sum'] : '';

            if ($summary == 'month') {
                $data = $this->_performanceSummaryByMonth($user['User']['id'], 12);
            }
            elseif ($summary == 'cat') {
                $year = null;
                $month = null;
                if (!empty($this->params['url']['year'])) {
                    $year = intval($this->params['url']['year']);
                }
                if (!empty($this->params['url']['month'])) {
                    $month = intval($this->params['url']['month']);
                }
                $data = $this->_performanceSummaryByCategory($user['User']['id'], $year, $month);
            }

            $this->set('json', $data);
            $this->render('ajax/json', 'ajax');
            return;
        }

        // display name (or email if not set)
        $userName = trim("{$user['User']['firstname']} {$user['User']['lastname']}");
        if ($userName == '') {
            $userName = $user['User']['email'];
        }

        $this->publish('mode', $mode);
        $this->publish('user', $user);
        $this->publish('userName', $userName);
        $this->publish('showUserLookup', $isSenior);
        $this->publish('editors', $isSenior ? $this->_recentEditors() : array());
        $this->publish('collapse_categories', true);
        $this->publish('subpagetitle', ___('Performance Reports'));
        $this->set('page', 'performance');

        //Standard text report
        if ($mode == '') {
            $this->_performanceTable($user['User']['id']);

        //Charts
        } elseif ($mode == 'charts') {
            $this->_performanceCharts($user['User']['id']);

        } else {
            $this->redirect('/editors/performance');
        }
        return;
    }


    /**
     * Generate a detailed performance report with weekly totals and team averages
     * @param int $userId user to generate report for
     */
    function _performanceTable($userId) {
        // Initialize report data
        $myApprovals = array();
        $myTotal = 0;
        $teamTotal = 0;
        $teamSize = 0;
        $teamAverage = 0;
        $weeklyTotals = array();
        $myMtdTotal = 0;
        $teamMtdTotal = 0;
        $teamMtdAverage = 0;
        $myYtdTotal = 0;
        $teamYtdTotal = 0;
        $teamYtdSize = 0;
        $teamYtdAverage = 0;

        //Default conditions are the current month
        $ytdEndTime = strtotime('tomorrow');
        $startDate = date('Y-m-01');
        $startTime = strtotime($startDate);
        $endDate = ___('YYYY-MM-DD');
        $endTime = $ytdEndTime;

        //If user has specified own conditions, use those
        if (!empty($this->params['url']['start'])) {
            $ts = strtotime($this->params['url']['start']);
            if ($ts !== false && $ts != -1) {
                $startTime = $ts;
                $startDate = $this->params['url']['start'];
            }
        }
        if (!empty($this->params['url']['end'])) {
            $ts = strtotime($this->params['url']['end']);
            if ($ts !== false && $ts != -1) {
                $endTime = strtotime('+1 day', $ts);
                $endDate = $this->params['url']['end'];
            }
        }

        //Initialize weekly data
        $week = array('from' => $startTime,
                        'to' => min(strtotime('next Monday', $startTime), $endTime)-1,
                        'myTotal' => 0, 'teamTotal' => 0, 'teamAverage' => 0);
        while ($week['from'] < $endTime) {
            $weeklyKey = $this->_makeYearWeekKey($week['from']);
            $weeklyTotals[$weeklyKey] = $week;

            $week = array('from' => $week['to']+1,
                            'to' => min(strtotime('+7 day', $week['to']), $endTime-1),
                            'myTotal' => 0, 'teamTotal' => 0, 'teamAverage' => 0);
        }

        //Fetch approvals over specified date range
        $conditions = array("Approval.created >= FROM_UNIXTIME('{$startTime}')",
                            "Approval.created < FROM_UNIXTIME('{$endTime}')");

        $order = '`Approval`.`created` ASC';
        if ($approvals = $this->Approval->findAll($conditions, null, $order)) {
            foreach ($approvals as $k => $approval) {
                $weeklyKey = $this->_makeYearWeekKey(strtotime($approval['Approval']['created']));
                if (!array_key_exists($weeklyKey, $weeklyTotals)) {
                    // this should never happen, but better to let there be obvious gaps in
                    // weekly totals than seemingly legit (but probably bogus) counts
                    continue;
                }

                //Fetch addon details for approvals by the report user
                if ($approval['User']['id'] == $userId) {
                    $approval['Addon'] = $this->Addon->getAddon($approval['Approval']['addon_id'],
                                                                array('list_details'));
                    $myApprovals[] = $approval;
                    $myTotal++;
                    $weeklyTotals[$weeklyKey]['myTotal']++;
                }
                $teamTotal++;
                $weeklyTotals[$weeklyKey]['teamTotal']++;
            }
        }

        /* add formatting to aid in debugging
        foreach ($weeklyTotals as $k => $w) {
            $weeklyTotals[$k]['formatted'] =
                date('Y-m-d H:i:s', $w['from']).' - '.date('Y-m-d H:i:s', $w['to']);
        }
        pr($weeklyTotals); /**/

        // YTD and MTD calculations
        $sql = "SELECT MONTH(`Approval`.`created`) AS `month`, `User`.`id`, COUNT(*) AS `total`
                  FROM `approvals` AS `Approval`
                  LEFT JOIN `users` AS `User` ON (`User`.`id`=`Approval`.`user_id`)
                 WHERE `Approval`.`created` >= '".date('Y')."-01-01 00:00:00'
                   AND `Approval`.`created` < FROM_UNIXTIME('{$ytdEndTime}')
                 GROUP BY `month`, `Approval`.`user_id`";
        if ($results = $this->Approval->query($sql)) {
            $thisMonth = date('m');
            foreach ($results as $row) {
                $teamYtdTotal += $row[0]['total'];
                $teamMtdTotal += ($row[0]['month'] == $thisMonth) ? $row[0]['total'] : 0;
                if ($row['User']['id'] == $userId) {
                    $myYtdTotal += $row[0]['total'];
                    $myMtdTotal += ($row[0]['month'] == $thisMonth) ? $row[0]['total'] : 0;
                }
            }
        }

        //Calculate averages
        $teamSize = $this->_teamSize($endTime);
        if ($teamSize) {
            $teamAverage = $teamTotal / $teamSize;
            foreach ($weeklyTotals as $k => $week) {
                $weeklyTotals[$k]['teamAverage'] = $week['teamTotal'] / $teamSize;
            }
        }
        $teamYtdSize = $this->_teamSize($ytdEndTime);
        if ($teamYtdSize) {
            $teamYtdAverage = $teamYtdTotal / $teamYtdSize;
            $teamMtdAverage = $teamMtdTotal / $teamYtdSize;
        }

        // Publish and render
        $this->publish('startDate', $startDate);
        $this->publish('endDate', $endDate);

        $this->publish('addonTypes', $this->Addontype->getNames());
        $this->publish('myApprovals', $myApprovals);
        $this->publish('myTotal', $myTotal);
        $this->publish('teamAverage', $teamAverage);
        $this->publish('weeklyTotals', $weeklyTotals);
        $this->publish('myMtdTotal', $myMtdTotal);
        $this->publish('teamMtdAverage', $teamMtdAverage);
        $this->publish('myYtdTotal', $myYtdTotal);
        $this->publish('teamYtdAverage', $teamYtdAverage);

        $this->render('performance_table');
    }


    /**
     * Generate charts showing yearly activity and category breakdowns
     * @param int $userId user to generate report for
     */
    function _performanceCharts($userId) {
        $byMonthData = $this->_performanceSummaryByMonth($userId, 12);
        $teamSize = $this->_teamSize(time());

        // category breakdown can be for an entire year, or a specific
        // month and year
        $year = date('Y');
        $month = null;
        if (!empty($this->params['url']['year'])) {
            $year = intval($this->params['url']['year']);
        }
        if (!empty($this->params['url']['month'])) {
            $month = intval($this->params['url']['month']);
        }
        $byCatData = $this->_performanceSummaryByCategory($userId, $year, $month);

        // points for x-axis labels (javascript)
        $monthlyTicks = array();
        foreach ($byMonthData['labels'] as $i => $label) {
            $this->_sanitizeArray($label);
            $monthlyTicks[] = "[{$i},'{$label}']";
        }

        // points for user activity (javascript)
        $userPoints = array();
        foreach ($byMonthData['usercount'] as $i => $n) {
            $userPoints[] = "[{$i},{$n}]";
        }

        // points for team activity (javascript)
        $teamPoints = array();
        foreach ($byMonthData['teamcount'] as $i => $n) {
            $n = $n / $teamSize;
            $teamPoints[] = "[{$i},{$n}]";
        }

        // pie chart labels (javascript)
        $sliceLabels = array();
        foreach ($byCatData['labels'] as $i => $label) {
            $this->_sanitizeArray($label);
            $sliceLabels[] = "'{$label}'";
        }

        // pie chart date title
        if ($month) {
            $pieTitleDate = strftime('%B %Y', mktime(12,0,0,$month,1,$year));
        } else {
            $pieTitleDate = $year;
        }

        // months for filter select
        $monthSelect = array(''=>'');
        for ($n = 1; $n<=12; $n++) {
            $monthSelect[$n] = strftime('%B', mktime(12,0,0,$n,1));
        }

        $this->set('monthlyTicksJS', implode(',', $monthlyTicks));
        $this->set('monthlyUserPointsJS', implode(',', $userPoints));
        $this->set('monthlyTeamPointsJS', implode(',', $teamPoints));
        $this->set('sliceLabelsJS', implode(',', $sliceLabels));
        $this->set('userPieDataJS', implode(',', $byCatData['usercount']));
        $this->set('teamPieDataJS', implode(',', $byCatData['teamcount']));
        $this->publish('year', $year);
        $this->publish('month', $month);
        $this->publish('monthSelect', $monthSelect);
        $this->publish('pieTitleDate', $pieTitleDate);
        $this->render('performance_charts');
    }


    /**
     * Generate data for monthly summary of user and team activity
     * @param int $userId user to generate report for
     * @param int $months number of months
     * @param int $endMonth last month of report (default current month)
     * @param int $endYear year of last month of report (default current year)
     * @return array
     */
    function _performanceSummaryByMonth($userId, $months=12, $endMonth=null, $endYear=null) {
        $user = $this->User->findById($userId);
        $data = array(
                    'email'     => $user['User']['email'],
                    'firstname' => $user['User']['firstname'],
                    'lastname'  => $user['User']['lastname'],
                    'labels'    => array(),
                    'usercount' => array(),
                    'teamcount' => array());

        if (is_null($endMonth)) {
            $endMonth = date('n');
        }
        if (is_null($endYear)) {
            $endYear = date('Y');
        }

        $endTime = strtotime(sprintf('%04d-%02d-01 00:00:00 +1 month', $endYear, $endMonth));
        $startTime = strtotime(sprintf('-%d month', $months), $endTime);

        $sql = "SELECT DATE_FORMAT(`Approval`.`created`, '%Y-%m') AS yearmonth,
                       `Approval`.`created`, `User`.`id`, COUNT(*) AS `total`
                  FROM `approvals` AS `Approval`
                  LEFT JOIN `users` AS `User` ON (`User`.`id`=`Approval`.`user_id`)
                 WHERE `Approval`.`created` >= FROM_UNIXTIME('{$startTime}')
                   AND `Approval`.`created` < FROM_UNIXTIME('{$endTime}')
                 GROUP BY yearmonth, `Approval`.`user_id`";

        $results = $this->Approval->query($sql);
        foreach ($results as $row) {
            $label = strftime('%b %Y', strtotime($row['Approval']['created']));
            if (!in_array($label, $data['labels'])) {
                $data['labels'][] = $label;
                $data['teamcount'][] = 0;
                $data['usercount'][] = 0;
            }
            $i = count($data['labels']) - 1;
            $data['teamcount'][$i] += $row[0]['total'];
            if ($row['User']['id'] == $userId) {
                $data['usercount'][$i] += $row[0]['total'];
            }
        }

        return $data;
    }


    /**
     * Generate data for extension category breakdown summary for user and team
     * @param int $userId user to generate report for
     * @param int $startTime timestamp
     * @param int $endTime timestamp
     * @return array
     */
    function _performanceSummaryExtension($userId, $startTime, $endTime) {
        // ids of similar categories (across different apps)
        // the counts will be merged together and the first id will determine
        // the category name to display
        $category_merge = array(
            // 'other' ids should match those used in
            // DevelopersController::_editAddonCategories
            'other'               => array(73, 49, 50),

            'bookmarks'           => array(22, 51),
            'download management' => array(5, 42),
            'language support'    => array(55, 76, 37, 69),
            'photos music video'  => array(38, 56),
            'privacy security'    => array(12, 46, 66),
            'rss news blogging'   => array(39, 57, 1),
            'search tools'        => array(13, 47),
            'web development'     => array(4, 41),
        );

        // 'other' categories are special - they will only be counted if they
        // are the only category associated with an add-on
        $otherId = $category_merge['other'][0];

        // fetch all extension categories
        $this->Category->unbindFully();
        $cats = $this->Category->findAll("addontype_id='".ADDON_EXTENSION."'", null, 'Category.id');
        $catCounts = array();
        foreach ($cats as $cat) {
            $catCounts[$cat['Category']['id']] = array(
                'label' => $cat['Translation']['name']['string'],
                'usercount' => 0,
                'teamcount' => 0
            );
        }

        // sum approvals by addon, category, and user
        $sql = "SELECT `Approval`.`addon_id`, `AddonCategory`.`category_id`, `Approval`.`user_id`, COUNT(*) AS `total`
                  FROM `approvals` AS `Approval`
                 INNER JOIN `addons` AS `Addon` ON (`Addon`.`id`=`Approval`.`addon_id`)
                 INNER JOIN `addons_categories` AS `AddonCategory` ON (`AddonCategory`.`addon_id`=`Addon`.`id`)
                 WHERE `Approval`.`created` >= FROM_UNIXTIME('{$startTime}')
                   AND `Approval`.`created` < FROM_UNIXTIME('{$endTime}')
                   AND `Addon`.`addontype_id` = '".ADDON_EXTENSION."'
                 GROUP BY `Approval`.`addon_id`, `AddonCategory`.`category_id`, `Approval`.`user_id`";

        $approvals = $this->Approval->query($sql);

        // "other" tracking
        $nonOtherCount = $lastAddon = $userOther = $teamOther = 0;

        foreach ($approvals as $appr) {
            $catId = $appr['AddonCategory']['category_id'];

            if ($appr['Approval']['addon_id'] !== $lastAddon) {
                // we just finished an add-on
                // now include "other" totals if no other categories were tallied
                if ($nonOtherCount == 0) {
                    $catCounts[$otherId]['usercount'] += $userOther;
                    $catCounts[$otherId]['teamcount'] += $teamOther;
                }

                // reset "other" tracking
                $nonOtherCount = $userOther = $teamOther = 0;
                $lastAddon = $appr['Approval']['addon_id'];
            }

            // defer counting "other" until we know if it is the add-on's only category
            if (in_array($catId, $category_merge['other'])) {
                if ($appr['Approval']['user_id'] == $userId) {
                    $userOther += $appr[0]['total'];
                }
                $teamOther += $appr[0]['total'];

            // always count non-other category totals
            } else {
                $nonOtherCount++;
                if ($appr['Approval']['user_id'] == $userId) {
                    $catCounts[$catId]['usercount'] += $appr[0]['total'];
                }
                $catCounts[$catId]['teamcount'] += $appr[0]['total'];
            }
        }
        // other counts for the last add-on in the query results
        if ($nonOtherCount == 0) {
            $catCounts[$otherId]['usercount'] += $userOther;
            $catCounts[$otherId]['teamcount'] += $teamOther;
        }

        // merge results of similar categories
        foreach ($category_merge as $merge) {
            $keepId = $merge[0];
            for ($i = 1; $i < count($merge); $i++) {
                // combine!
                $catCounts[$keepId]['usercount'] += $catCounts[$merge[$i]]['usercount'];
                $catCounts[$keepId]['teamcount'] += $catCounts[$merge[$i]]['teamcount'];

                // whack!
                unset($catCounts[$merge[$i]]);
            }
        }

        // package up final results all nice and pretty
        $results = array('labels'=>array(), 'usercount'=>array(), 'teamcount'=>array());
        foreach ($catCounts as $arr) {
            $results['labels'][] = $arr['label'];
            $results['usercount'][] = $arr['usercount'];
            $results['teamcount'][] = $arr['teamcount'];
        }

        return $results;
    }


    /**
     * Generate data for category breakdown summary for user and team (includes addon-types)
     * @param int $userId user to generate report for
     * @param int $year year (default: current year)
     * @param int $month month to summarize (default: generate data for entire year)
     * @return array
     */
    function _performanceSummaryByCategory($userId, $year=null, $month=null) {
        $user = $this->User->findById($userId);
        $data = array(
                    'email'     => $user['User']['email'],
                    'firstname' => $user['User']['firstname'],
                    'lastname'  => $user['User']['lastname'],
                    'labels'    => array(),
                    'usercount' => array(),
                    'teamcount' => array());

        // treat each add-on types as a category - except extensions
        // they get broken down further later on
        $addonTypes = $this->Addontype->getNames();
        $addonTypeKeys = array();
        foreach ($addonTypes as $key => $val) {
            if ($key == ADDON_EXTENSION) {
                continue;
            }
            $addonTypeKeys[] = $key;
            $data['labels'][] = $val;
            $data['usercount'][] = 0;
            $data['teamcount'][] = 0;
        }

        // single month or year summary breakdown by category
        // default date range is current year
        if (is_null($year)) {
            $year = date('Y');
        }

        if ($month > 0) {
            $startTime = strtotime(sprintf('%d-%02d-01 00:00:00', $year, $month));
            $endTime = strtotime('+1 month', $startTime);
        } else {
            $startTime = strtotime(sprintf('%d-01-01 00:00:00', $year));
            $endTime = strtotime('+1 year', $startTime);
        }

        // sum approvals by user and add-on type
        $sql = "SELECT `Addon`.`addontype_id`, `User`.`id`, COUNT(*) AS `total`
                  FROM `approvals` AS `Approval`
                  LEFT JOIN `users` AS `User` ON (`User`.`id`=`Approval`.`user_id`)
                  LEFT JOIN `addons` AS `Addon` ON (`Addon`.`id`=`Approval`.`addon_id`)
                 WHERE `Approval`.`created` >= FROM_UNIXTIME('{$startTime}')
                   AND `Approval`.`created` < FROM_UNIXTIME('{$endTime}')
                   AND `Addon`.`addontype_id` != '".ADDON_EXTENSION."'
                 GROUP BY `Approval`.`user_id`, `Addon`.`addontype_id`";

        $results = $this->Approval->query($sql);
        foreach ($results as $row) {
            $i = array_search($row['Addon']['addontype_id'], $addonTypeKeys);
            if ($i === false) {
                continue; // approvals should always have a known addontype ?
            }
            $data['teamcount'][$i] += $row[0]['total'];
            if ($row['User']['id'] == $userId) {
                $data['usercount'][$i] += $row[0]['total'];
            }
        }

        // merge in extension category summary
        $extCatCounts = $this->_performanceSummaryExtension($userId, $startTime, $endTime);
        $data['labels'] = array_merge($data['labels'], $extCatCounts['labels']);
        $data['usercount'] = array_merge($data['usercount'], $extCatCounts['usercount']);
        $data['teamcount'] = array_merge($data['teamcount'], $extCatCounts['teamcount']);

        // sort all data by category name for an orderly legend display
        array_multisort($data['labels'], SORT_ASC, SORT_STRING, $data['usercount'], $data['teamcount']);

        return $data;
    }


   /**
    * AJAX Add-on and Author email lookup
    */
    function addonAndAuthorLookup($queue='pending') {
        $text = $_REQUEST['q'];
        $this->Amo->clean($text, false);
        $addonsAndEmails = array();

        if (strlen($text) > 0 && in_array($queue, array('pending', 'nominated'))) {
            // Use the base filter components to limit results to those in the queue
            $sql_base = $this->Editors->baseQueueFilterQuery($queue);

            // search localized addon names
            $sql = "SELECT DISTINCT IFNULL(`tr_l`.`localized_string`, `tr_en`.`localized_string`) AS `lname`
                    {$sql_base['FROM']}
                    {$sql_base['JOIN']}
                    LEFT JOIN `translations` AS `tr_l` ON
                        (`tr_l`.`id`=`Addon`.`name` AND `tr_l`.`locale`='".LANG."')
                    LEFT JOIN `translations` AS `tr_en` ON
                        (`tr_en`.`id`=`Addon`.`name` AND `tr_en`.`locale`=`Addon`.`defaultlocale`)
                    {$sql_base['WHERE']}
                        AND IFNULL(`tr_l`.`localized_string`, `tr_en`.`localized_string`) LIKE '%{$text}%'
                    ORDER BY `lname` ASC";

            $results = $this->Version->query($sql);
            if (!empty($results)) {
                foreach ($results as $row) {
                    $addonsAndEmails[] = $row[0]['lname'];
                }
            }

            // search localized addon support emails
            $emails = array();
            $sql = "SELECT IFNULL(`tr_l`.`localized_string`, `tr_en`.`localized_string`) AS `lemail`
                    {$sql_base['FROM']}
                    {$sql_base['JOIN']}
                    LEFT JOIN `translations` AS `tr_l` ON
                        (`tr_l`.`id`=`Addon`.`supportemail` AND `tr_l`.`locale`='".LANG."')
                    LEFT JOIN `translations` AS `tr_en` ON
                        (`tr_en`.`id`=`Addon`.`supportemail` AND `tr_en`.`locale`=`Addon`.`defaultlocale`)
                    {$sql_base['WHERE']}
                        AND IFNULL(`tr_l`.`localized_string`, `tr_en`.`localized_string`) LIKE '%{$text}%'";

            $results = $this->Version->query($sql);
            if (!empty($results)) {
                foreach ($results as $row) {
                    $emails[] = $row[0]['lemail'];
                }
            }

            // search author emails
            $sql = "SELECT `User`.`email`
                    {$sql_base['FROM']}
                    {$sql_base['JOIN']}
                    LEFT JOIN `addons_users` AS `au` ON (`Addon`.`id`=`au`.`addon_id`)
                    LEFT JOIN `users` AS `User` ON (`au`.`user_id`=`User`.`id`)
                    {$sql_base['WHERE']}
                        AND `au`.`role` IN(".AUTHOR_ROLE_ADMINOWNER.","
                                        .AUTHOR_ROLE_ADMIN.","
                                        .AUTHOR_ROLE_OWNER.","
                                        .AUTHOR_ROLE_DEV.")
                        AND `User`.`email` LIKE '%{$text}%'";

            $results = $this->Version->query($sql);
            if (!empty($results)) {
                foreach ($results as $row) {
                    $emails[] = $row['User']['email'];
                }
            }

            // sort, dedup, and merge
            sort($emails);
            $emails = array_unique($emails);
            $addonsAndEmails = array_merge($addonsAndEmails, $emails);
        }

        $this->set('addonsAndEmails', $addonsAndEmails);
        $this->render('ajax/addon_and_author_lookup', 'ajax');
    }

    /**
     * AJAX action for looking up appversions for the specified app
     * @param int $app_id The application id
     */
    function appversionLookup($app_id) {
        $this->Amo->clean($app_id);
        $results = $this->Appversion->findAllByApplicationId($app_id,
                            array('Appversion.id', 'Appversion.version'), 'Appversion.version DESC');
        $appversions = array();
        foreach ($results as $av) {
            $appversions[] = $av['Appversion'];
        }

        $this->publish('appversions', $appversions);
        $this->render('ajax/appversion_lookup', 'ajax');
    }


    /**
     * Subscribes user to an editor discussion thread
     */
    function threadsubscribe($ajax = null) {
        $this->_thread_subscribe_unsubscribe($ajax);
    }

    /**
     * Unsubscribe user from an editor discussion thread
     */
    function threadunsubscribe($ajax = null) {
        $this->_thread_subscribe_unsubscribe($ajax);
    }

    /**
     * Combined function for subscribing/unsubscribing. Action is determined by
     * $this->action.
     * @access private
     * @param string $ajax undefined or 'ajax' for no-frills rendering
     * @return bool render()ed successfully?
     */
    function _thread_subscribe_unsubscribe($ajax = null) {

        // check action
        if (!in_array($this->action, array('threadsubscribe', 'threadunsubscribe'))) {
            header('HTTP/1.1 400 Bad Request');
            $this->flash(___('Access Denied'), '/editors/queue');
            return;
        }

        // get posted comment_id
        if (empty($this->params['form']['comment_id'])) { // id needs to be POSTed
            header('HTTP/1.1 400 Bad Request');
            $this->flash(sprintf(___('Missing argument: %s'), 'comment_id'), '/editors/queue');
            return;
        }
        $id = $this->params['form']['comment_id'];

        // lookup comment
        $comment = $this->Versioncomment->findById($id);
        if (empty($comment)) {
            header('HTTP/1.1 404 Not Found');
            $this->flash(___('Invalid comment'), '/editors/queue');
            return;
        }

        // only allow subscribing to head of the thread
        if (! empty($comment['Versioncomment']['reply_to'])) {
            header('HTTP/1.1 400 Bad Request');
            $this->flash(___('Comment does not start a thread'),
                            "/editors/review/{$comment['Version']['id']}", 3);
            return;
        }

        $user = $this->Session->read('User');

        // subscribe
        if ($this->action == 'threadsubscribe') {
            // make sure user is not an author (or is an admin) of the comment's addon
            if (!$this->SimpleAcl->actionAllowed('*', '*', $user)) {
                $addon = $this->Addon->getAddon($comment['Version']['addon_id'], array('authors'));
                if (!empty($addon['User'])) {
                    foreach ($addon['User'] as $author) {
                        if ($author['id'] == $user['id']) {
                            header('HTTP/1.1 401 Unauthorized');
                            $this->flash(___('Self-reviews are not allowed.'), '/editors/queue');
                            return;
                        }
                    }
                }
            }

            $result = $this->Versioncomment->subscribe($id, $user['id'], true);
            $message = ($result ? ___('Subscription Succeeded')
                                : ___('Subscription Failed'));

        // unsubscribe
        } else {
            $result = $this->Versioncomment->unsubscribe($id, $user['id']);
            $message = ($result ? ___('Unsubscription Succeeded')
                                : ___('Unsubscription Failed'));
        }

        // results!
        if ($ajax == 'ajax') {
            $this->set('json', array('success'=>$result));
            $this->render('ajax/json', 'ajax');
        } else {
            $this->flash($message, "/editors/review/{$comment['Version']['id']}"
                                    ."#editorComment{$comment['Versioncomment']['id']}");
        }
    }


    /**
     * Count the number of (possibly filtered) items in the specified queue
     * @param string $queue
     * @param bool $useFilter (default=false)
     * @return int
     * @todo possibly cache results
     */
    function _getCount($queue, $useFilter=false) {
        $result = null;

        if ($useFilter && $this->Editors->buildQueueFilterQuery($queue)) {
            $sql = $this->Editors->buildQueueFilterQuery($queue);
            $result = $this->Addon->query(
                "SELECT COUNT(*) {$sql['FROM']} {$sql['JOIN']} {$sql['WHERE']}");

        } elseif ($queue == 'pending') {
            $result = $this->File->query(
                "SELECT COUNT(*) FROM `files` WHERE `status`=".STATUS_PENDING." GROUP BY `status`");

        } elseif ($queue == 'nominated') {
            $result = $this->Addon->query(
                "SELECT COUNT(*) FROM `addons` WHERE `status`=".STATUS_NOMINATED." GROUP BY `status`");

        } elseif ($queue == 'reviews') {
            $result = $this->Review->query(
                "SELECT COUNT(*) FROM `reviews` WHERE `editorreview`=1 GROUP BY `editorreview`");
        }

        $count = !empty($result) ? $result[0][0]['COUNT(*)'] : 0;

        return $count;
    }

    /**
     * Fetch an array of (possibly filtered) addons for the specified queue
     * @param string $queue
     * @return array
     */
    function _buildQueue($queue) {
        if (!in_array($queue, array('pending', 'nominated'))) {
            return array();
        }

        $sql = $this->Editors->buildQueueFilterQuery($queue);

        // Setup pagination
        $this->Pagination->total = $this->_getCount($queue, true);

        $_pagination_options = array();
        if ($queue == 'pending') {
            if (!array_key_exists('show', $_GET) && $this->Session->read('editor_queue_pending_show')) {
                $_pagination_options['show'] = $this->Session->read('editor_queue_pending_show');
            } else {
                // If $_GET['show'] exists it overrides this in the pagination component
                $_pagination_options['show'] = 50;
            }
            list($not_used,$_limit,$_page) = $this->Pagination->init(null, null, $_pagination_options);
            $this->Session->write('editor_queue_pending_show', $_limit);

        } else {
            if (!array_key_exists('show', $_GET) && $this->Session->read('editor_queue_nominated_show')) {
                $_pagination_options['show'] = $this->Session->read('editor_queue_nominated_show');
            } else {
                // If $_GET['show'] exists it overrides this in the pagination component
                $_pagination_options['show'] = 50;
            }
            list($not_used,$_limit,$_page) = $this->Pagination->init(null,null,$_pagination_options);
            $this->Session->write('editor_queue_nominated_show', $_limit);
        }

        $_offset = ($_page > 0) ? $_limit * ($_page-1) : 0; // no negative offsets, thank you

        $extra_fields = '';
        if ($queue == 'pending') {
            $extra_fields .= ", `File`.`id`, `File`.`platform_id`";
        }

        // Fetch the queue
        $queue_sql = "SELECT
                            `Version`.`id`,
                            `Version`.`addon_id`,
                            `Version`.`version`,
                            `Version`.`created`,
                            `Version`.`modified`
                            {$extra_fields}
                        {$sql['FROM']}
                        {$sql['JOIN']}
                        {$sql['WHERE']}
                        {$sql['ORDER']}
                        LIMIT {$_limit} OFFSET {$_offset}";

        $addons = $this->Version->query($queue_sql);

        //Merge in Addon details
        if (!empty($addons)) {
            foreach ($addons as $k => $addon) {
                $addon = $this->Addon->findById($addon['Version']['addon_id'],
                                            array('Addon.id',
                                                  'Addon.name', 'Addon.defaultlocale',
                                                  'Addon.addontype_id', 'Addon.prerelease',
                                                  'Addon.sitespecific', 'Addon.externalsoftware',
                                                  'Addon.adminreview',
                                                  'Addon.created', 'Addon.nominationdate'), '', 0);
                $addons[$k] = array_merge_recursive($addons[$k], $addon);
            }
        }

        $platforms = $this->Amo->getPlatformName();
        $applications = $this->Amo->getApplicationName();

        //make modifications to the queue array
        if (!empty($addons)) {
            foreach ($addons as $k => $addon) {
                //get min/max versions
                if ($targetApps = $this->Amo->getMinMaxVersions($addon['Version']['id'])) {
                    foreach ($targetApps as $targetApp) {
                        $appName = $targetApp['translations']['localized_string'];
                        $addons[$k]['targetApps'][$appName]['min'] = $targetApp['min']['version'];
                        $addons[$k]['targetApps'][$appName]['max'] = $targetApp['max']['version'];
                    }
                }

                //Age
                if ($queue == 'pending') {
                    $age = time() - strtotime($addon['Version']['created']);
                }
                elseif ($queue == 'nominated') {
                    $age = time() - strtotime($addon['Addon']['created']);
                    $nominationage = time() - strtotime($addon['Addon']['nominationdate']);
                    $addons[$k]['nominationage'] = $this->_humanizeAge($nominationage);
                }

                $addons[$k]['age'] = $this->_humanizeAge($age);

                //Generate any additional notes
                $addons[$k]['notes'] = array();

                //Platform-specific?
                if (!empty($addon['Version'][0]['File'][0]['platform_id']) && $addon['Version'][0]['File'][0]['platform_id'] != 1) {
                    $os = array();
                    foreach ($addon['Version'][0]['File'] as $file) {
                        $os[] = $platforms[$file['platform_id']];
                    }
                    $addons[$k]['notes'][] = sprintf(___('%s only'), implode(', ', $os));
                }
                elseif (!empty($addon['File']['platform_id']) && $addon['File']['platform_id'] != 1) {
                    $addons[$k]['notes'][] = sprintf(___('%s only'), $platforms[$addon['File']['platform_id']]);
                }

                //Featured?
                //@TODO

                //Site specific?
                if ($addon['Addon']['sitespecific'] == 1) {
                    $addons[$k]['notes'][] = ___('Site Specific');
                }
                //Pre-release?
                if ($addon['Addon']['prerelease'] == 1) {
                    $addons[$k]['notes'][] = ___('Pre-release');
                }
                //External software?
                if ($addon['Addon']['externalsoftware'] == 1) {
                    $addons[$k]['notes'][] = ___('External Software');
                }
            }
        }
        //pr($addons);

        return $addons;
    }

   /**
    * Moderated Reviews Queue
    */
    function _reviews($count) {
        if (!empty($this->data)) {
            foreach ($this->data['Reviews'] as $k => $review) {
                if ($review != 'skip') {
                    preg_match("/^review(\d+)$/", $k, $matches);
                    $this->Review->id = intval($matches[1]);

                    if ($review == 'approve') {
                        //Log editor action
                        $this->Eventlog->log($this, 'editor', 'review_approve', null, $this->Review->id);

                        $this->Review->save(array('editorreview' => 0));
                        // HACK: not sure how this is done without deleteAll()
                        $reviews_flags = $this->ReviewsModerationFlag->query(
                            'DELETE FROM reviews_moderation_flags WHERE review_id='.$this->Review->id
                        );
                    }
                    elseif ($review == 'delete') {
                        //Pull review for log
                        $this->Review->setLang('en-US', $this);
                        $review = $this->Review->read();
                        $this->Review->setLang(LANG, $this);

                        $reviewArray = array('title' => $review['Translation']['title']['string'],
                                             'body' => $review['Translation']['body']['string']);
                        //Log editor action
                        $this->Eventlog->log($this, 'editor', 'review_delete', null, $this->Review->id, null, null, serialize($reviewArray));

                        // HACK: not sure how this is done without deleteAll()
                        $reviews_flags = $this->ReviewsModerationFlag->query(
                            'DELETE FROM reviews_moderation_flags WHERE review_id='.$this->Review->id
                        );
                        $this->Review->delete();
                    }
                }
            }

            $this->flash(___('Reviews processed successfully!'), '/editors/queue/reviews');
            return;
        }

        $criteria = array('Review.editorreview' => '1');

        // initialize pagination
        $this->Pagination->total = $count;
        if (!array_key_exists('show', $_GET) && $this->Session->read('editor_queue_reviews_show')) {
            // Have to modify $_GET because pagination component pulls directly from it
            $_GET['show'] = $this->Session->read('editor_queue_reviews_show');
        }
        list($order,$limit,$page) = $this->Pagination->init($criteria);
        $this->Session->write('editor_queue_reviews_show', $limit);

        $_reviews = $this->Review->findAll($criteria, "Review.id", 'Review.modified ASC', $limit, $page, -1);
        $_review_ids = array();
        foreach ($_reviews as $_id) $_review_ids[] = $_id['Review']['id'];
        unset($_reviews);
        $reviews = $this->Review->getReviews($_review_ids, true);
        foreach ($reviews as $k => $review) {
            $reviews[$k]['Addon'] = $this->Addon->findById($review['Review']['addon_id'], array('id', 'name'), null, -1);
            if (!empty($review['Review']['reply_to'])) {
                $_replyto = $this->Review->getReviews($review['Review']['reply_to']);
                if (!empty($_replyto))
                    $reviews[$k]['Review']['reply_to'] = $_replyto[0];
            }
        }
        unset($_replyto);

        // Gather and collate all available flags per review on the page.
        // NOTE: User info isn't included here, keeping things anonymous.
        $reviews_flags = array();
        $reviews_flags_notes = array();
        $this->ReviewsModerationFlag->unbindFully();
        $_reviews_flags = $this->ReviewsModerationFlag->findAll(array(
            'ReviewsModerationFlag.review_id' => $_review_ids
        ));
        foreach ($_reviews_flags as $flag) {
            $review_id = $flag['ReviewsModerationFlag']['review_id'];
            $flag_name = $flag['ReviewsModerationFlag']['flag_name'];

            // Count the occurrences of each flag per review, building
            // the data structure as we go.
            if (!isset($reviews_flags[$review_id]))
                $reviews_flags[$review_id] = array();
            if (!isset($reviews_flags[$review_id][$flag_name]))
                $reviews_flags[$review_id][$flag_name] = 1;
            else
                $reviews_flags[$review_id][$flag_name] += 1;

            // Collect freeform notes in a separate list.
            if ($flag_name == 'review_flag_reason_other') {
                if (!isset($reviews_flags_notes[$review_id]))
                    $reviews_flags_notes[$review_id] = array();
                $reviews_flags_notes[$review_id][] =
                    $flag['ReviewsModerationFlag']['flag_notes'];
            }
        }

        $this->publish('reviews', $reviews);
        $this->publish('reviews_flags', $reviews_flags);
        $this->publish('reviews_flags_notes', $reviews_flags_notes);
        $this->publish('review_flag_reasons',
            $this->ReviewsModerationFlag->reasons);

        $this->render('reviews_queue');
    }

   /**
    * Featured Add-ons
    * params are for ajax callbacks
    */
    function featured($command=null, $ajax=null) {
        $this->Amo->clean($this->data);

        switch($command) {
            case 'add':
                if (preg_match('/\[(\d+)\]/', $this->data['Addon']['id'], $matches)) {
                    $this->data['Addon']['id'] = $matches[1];
                }

                if (!is_numeric($this->data['Addon']['id']) || !is_numeric($this->data['Category']['id'])) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(___('Failed to add feature.'), '/editors/featured');
                    return;
                }

                $_addon = $this->Addon->getAddon($this->data['Addon']['id']);
                if ($_addon['Addon']['status'] != STATUS_PUBLIC) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(___('Failed to edit feature.'), '/editors/featured');
                    return;
                }

                // If the add-on isn't in the category, we'll add it.
                $_new_feature_query = "REPLACE INTO addons_categories (addon_id, category_id, feature) VALUES ( '{$this->data['Addon']['id']}', '{$this->data['Category']['id']}', 1)";

                if ($this->AddonCategory->query($_new_feature_query)) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(___('Failed to add feature.'), '/editors/featured');
                } else {
                    $this->Eventlog->log($this, 'editor', 'feature_add', '', $this->data['Addon']['id'], $this->data['Addon']['id']);
                    $this->flash(___('Successfully added feature.'), '/editors/featured', 3);
                }

                return;
            case 'edit':
                global $valid_languages;

                if (!empty($this->data['AddonCategory']['feature_locales'])) {
                    if (count(array_diff(explode(',',$this->data['AddonCategory']['feature_locales']), array_keys($valid_languages))) > 0) {
                        header('HTTP/1.1 400 Bad Request');
                        $this->flash(___('One or more locales are invalid.'), '/editors/featured');
                        return;
                    }
                }

                if (!is_numeric($this->data['Addon']['id']) || !is_numeric($this->data['Category']['id']) || preg_match('/[^A-Za-z,-]/',$this->data['AddonCategory']['feature_locales'])) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(___('Failed to edit feature.'), '/editors/featured');
                    return;
                }

                $this->Eventlog->log($this, 'editor', 'feature_locale_change', 'feature-locales', $this->data['Addon']['id']);

                // Reorder the locales
                $_locales = array_unique(explode(',', $this->data['AddonCategory']['feature_locales']));
                sort($_locales);
                $this->data['AddonCategory']['feature_locales'] = implode(',',$_locales);

                $_edit_feature_query = "UPDATE addons_categories
                                        SET feature_locales='{$this->data['AddonCategory']['feature_locales']}'
                                        WHERE addon_id='{$this->data['Addon']['id']}'
                                        AND category_id='{$this->data['Category']['id']}'";

                if ($this->AddonCategory->query($_edit_feature_query)) {
                    header('HTTP/1.1 400 Bad Request');
                    $this->flash(___('Failed to edit feature.'), '/editors/featured');
                } else {
                    $this->flash(___('Successfully edited feature.'), '/editors/featured', 3);
                }
                return;

            case 'remove':
                if (is_numeric($this->data['Category']['id']) && is_numeric($this->data['Addon']['id'])) {

                    $this->Eventlog->log($this, 'editor', 'feature_remove', null, $this->data['Addon']['id'], null, $this->data['Addon']['id']);

                    // Neither query() nor execute() return success from a DELETE call, even when the row is deleted. wtf.
                    $this->AddonCategory->execute("DELETE FROM `addons_categories` WHERE addon_id='{$this->data['Addon']['id']}' AND category_id='{$this->data['Category']['id']}' AND feature=1 LIMIT 1");

                    // Assume we succeeded
                    $this->flash(___('Successfully removed feature.'), '/editors/featured', 3);
                    return;
                }

                header('HTTP/1.1 400 Bad Request');
                $this->flash(___('Failed to remove feature.'), '/editors/featured');

                return;

            default:
                break;
        }

        // Setup title and breadcrumbs
        $this->breadcrumbs[___('Featured Add-ons', 'editors_featured_addons_pagetitle')] = '/editors/featured';
        $this->publish('breadcrumbs', $this->breadcrumbs);
        $this->publish('subpagetitle', ___('Featured Add-ons', 'editors_featured_addons_pagetitle'));

        // Get all featured Addons
        $features = $this->AddonCategory->findAllByFeature(1, array('addon_id'));
        $_addon_ids = $addons_by_category = array();

        if (!empty($features)) {
            foreach ($features as $feature) { $_addon_ids[] = $feature['AddonCategory']['addon_id']; }
            $_addon_ids = array_unique($_addon_ids);

            // Big ol' array
            $this->Addon->bindOnly('AddonCategory');
            $features = $this->Addon->findAll(array('Addon.id' => $_addon_ids), array('Addon.id', 'Addon.name', 'Addon.addontype_id'), 'Translation.name');

            foreach ($features as $feature) {
                // Dump them into the array sorted by category
                foreach ($feature['AddonCategory'] as $attributes) {
                    if ($attributes['feature'] == 1) {
                        // override the AddonCategory array for the view.  Even though an add-on will have multiple categories, we only want one for this view
                        $feature['AddonCategory'] = array( 0 => $attributes );

                        $addons_by_category[$attributes['category_id']][] = $feature;
                    }

                }

            }
        }

        // Reorganize the categories so it's easier to use them in the view.  TheLittleThingsWearMeDown++ :(
        $categories = array();
        foreach ($this->Category->findAll('', null, array('Category.application_id', 'Category.addontype_id', 'Translation.name')) as $category) {
            $categories[$category['Category']['id']] = $category;
        }

        $this->set('applications', $this->Amo->getApplicationName());
        $this->set('addontypes', $this->Addontype->getNames());
        $this->set('categories', $categories);
        $this->set('mode', 'featured');
        $this->publish('addons_by_category', $addons_by_category);
        $this->render('featured');

    }

   /**
    * Generates a preview of posted markdown data
    */
    function markdown() {
        $this->cssAdd[] = '../vendors/syntaxhighlighter/styles/shCore';
        $this->cssAdd[] = '../vendors/syntaxhighlighter/styles/shThemeDefault';
        $this->publish('cssAdd', $this->cssAdd);

        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shCore.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushCss.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushDiff.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushJScript.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushPlain.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushSql.js';
        $this->jsAdd[] = '../vendors/syntaxhighlighter/scripts/shBrushXml.js';
        $this->publish('jsAdd', $this->jsAdd);

        $markdownHtml = '';
        if (!empty($this->data['markdown'])) {
            $markdownHtml = $this->Markdown->html($this->data['markdown']);
        }
        // Unescaped html is required here. We are relying on hiddenSession/checkCSRF
        // and the Markdown component to keep things safe
        $this->set('markdownHtml', $markdownHtml);
        $this->render('markdown', 'ajax_with_css');
    }

   /**
    * Display logs
    */
    function logs() {
        $this->breadcrumbs[___('Event Log', 'editorcp_logs_page_heading')] = '/editors/logs';
        $this->set('breadcrumbs', $this->breadcrumbs);

        //Default conditions are the current month
        $monthStart = date('Y-m-01');
        $conditions = array("Eventlog.created >= '{$monthStart} 00:00:00'");
        $startDate = $monthStart;
        $endDate = ___('YYYY-MM-DD');
        $filter = '';

        //If user has specified own conditions, use those
        if (!empty($this->params['url']['start'])) {
            $startTime = strtotime($this->params['url']['start']);
            if ($startTime !== false && $startTime != -1) {
                $conditions = array("Eventlog.created >= FROM_UNIXTIME('{$startTime}')");
                $startDate = $this->params['url']['start'];
            }
        }
        if (!empty($this->params['url']['end'])) {
            $endTime = strtotime($this->params['url']['end']);
            if ($endTime !== false && $endTime != -1) {
                $conditions[] = "Eventlog.created < FROM_UNIXTIME('".strtotime('+1 day', $endTime)."')";
                $endDate = $this->params['url']['end'];
            }
        }
        if (!empty($this->params['url']['filter'])) {
            $filter = $this->params['url']['filter'];
            $filterParts = explode(':', $filter);
            $conditions['type'] = $filterParts[0];

            if ($filterParts[1] != '*') {
                $conditions['action'] = $filterParts[1];
            }
        }
        $conditions['type'] = 'editor';

        // set up pagination
        list($order,$limit,$page) = $this->Pagination->init($conditions, null,
            array('modelClass'=>'Eventlog', 'show'=>50, 'sortby'=>'created', 'direction'=>'DESC'));

        $logs = $this->Eventlog->findAll($conditions, null, $order, $limit, $page);
        $logs = $this->Audit->explainLog($logs);

        $this->set('logs', $logs);
        $this->publish('startDate', $startDate);
        $this->publish('endDate', $endDate);
        $this->publish('filter', $filter);

        $this->publish('filterOptions', array(
                '' => '',
                'editor:review_approve' => ___('Approved reviews'),
                'editor:review_delete' => ___('Deleted reviews')
        ));

        $this->set('page', 'logs');
        $this->render('logs');
    }

    function reviewlog() {
        //Default conditions are the current month
        $monthStart = date('Y-m-01');
        $conditions = "Approval.created >= '{$monthStart} 00:00:00'";
        $startdate = $monthStart;
        $enddate = ___('YYYY-MM-DD');

        //If user has specified own conditions, use those
        if (!empty($this->params['url']['start'])) {
            $start_time = strtotime($this->params['url']['start']);
            $end_time = strtotime($this->params['url']['end']);
            if ($start_time !== false && $start_time != -1) {
                $conditions = array(
                    "Approval.created >= FROM_UNIXTIME('{$start_time}')"
                );
                $startdate = $this->params['url']['start'];

                if ($end_time !== false && $end_time != -1) {
                    $conditions[] = "Approval.created < FROM_UNIXTIME('".strtotime('+1 day', $end_time)."')";
                    $enddate = $this->params['url']['end'];
                }
            }
        }

        // set up pagination
        list($order,$limit,$page) = $this->Pagination->init($conditions, null,
            array('modelClass'=>'Approval', 'show'=>50));

        $approvals = $this->Approval->findAll($conditions, null, $order, $limit, $page);
        foreach ($approvals as $k => $approval) {
            $approvals[$k]['Addon'] = $this->Addon->getAddon($approval['Approval']['addon_id']);
        }

        $this->publish('approvals', $approvals);
        $this->publish('startdate', $startdate);
        $this->publish('enddate', $enddate);

        $this->set('page', 'reviewlog');
        $this->render('reviewlog');
    }

    /* Humanizes a Unix Timestamp */
    function _humanizeAge($age) {
        $humanized = '';

        //days
        if ($age >= (60*60*24*2)) {
            $humanized = sprintf(n___('%s day', '%s days', floor($age/(60*60*24))), floor($age/(60*60*24)));
        }
        //1 day
        elseif ($age >= (60*60*24)) {
            $humanized = ___('1 day');
        }
        //hours
        elseif ($age >= (60*60*2)) {
            $humanized = sprintf(n___('%s hour', '%s hours', floor($age/(60*60))), floor($age/(60*60)));
        }
        //hour
        elseif ($age >= (60*60)) {
            $humanized = ___('1 hour');
        }
        //minutes
        elseif ($age > 60) {
            $humanized = sprintf(n___('%s minute', '%s minutes', floor($age/60)), floor($age/60));
        }
        //minute
        else {
            $humanized = ___('1 minute');
        }

        return $humanized;
    }

    /* Generate a sortable key from the given timestamp in the form of 'YYYY-WW' */
    function _makeYearWeekKey($timestamp) {
        $year = date('Y', $timestamp);
        $week = date('W', $timestamp);

        // the end of December is often part of the first week for the following year
        if ($week == '01' && date('m', $timestamp) == '12') {
            $year++;
        }
        return "{$year}-{$week}";
    }

    /* Approximate team size at a point in time */
    function _teamSize($timestamp) {
        $this->Amo->clean($timestamp);

        //  Count the number of unique users that submitted a review during
        //  the 60 days leading up to the time specified.
        $teamSize = 0;
        $sql = "SELECT COUNT(DISTINCT `Approval`.`user_id`) AS `teamSize`
                  FROM `approvals` AS `Approval`
                 WHERE `Approval`.`created` < FROM_UNIXTIME('{$timestamp}')
                   AND `Approval`.`created` >= FROM_UNIXTIME('".strtotime('-60 day', $timestamp)."')";
        if ($results = $this->Approval->query($sql)) {
            $teamSize = $results[0][0]['teamSize'];
        }
        return $teamSize;
    }

    /* Fetch emails of all editors active in the last reviewDays days */
    function _recentEditors($reviewDays=90) {
        $this->Amo->clean($reviewDays);

        $sql = "SELECT DISTINCT `User`.`email`
                  FROM `approvals` AS `Approval`
                 INNER JOIN `users` AS `User` ON (`User`.`id`=`Approval`.`user_id`)
                 WHERE `Approval`.`created` >= FROM_UNIXTIME('".strtotime("-{$reviewDays} day")."')
                 ORDER BY `User`.`email` ASC";

        $editors = array();
        if ($results = $this->Approval->query($sql)) {
            foreach ($results as $user) {
                $editors[] = $user['User']['email'];
            }
        }

        return $editors;
    }

    /**
     * Admin page for the Message of the Day
     */
    function motd() {
        // User must have the Editors:motd ACL to use this page
        if (!$this->SimpleAcl->actionAllowed('Admin', 'EditorsMOTD', $this->Session->read('User'))) 
            $this->Amo->accessDenied();

        // If the form was submitted, set the MOTD
        if (!empty($this->data['MOTD'])) {
            $this->_set_motd($this->data['MOTD']['message']);
        }

        $motd = $this->_get_motd();
        $preview = empty($motd) ? "The MOTD is currently empty, and will not be shown to editors.  This is only a preview." : $motd;

        $this->set('preview', $preview);
        $this->set('motd', $motd);
        return $this->render('motd_admin');
    }

    function _set_motd($motd = '') {
        $motd = !$motd ? '' : trim($motd);
        return $this->Config->save(array('key' => 'editors_review_motd', 'value' => $motd));
    }

    function _get_motd() {
        $motd = $this->Config->getValue('editors_review_motd');
        return empty($motd) ? false : $motd;
    }
}

?>
