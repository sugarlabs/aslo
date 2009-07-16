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
 *   Wil Clouser <clouserw@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
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

class ReviewsController extends AppController
{
    var $name = 'Reviews';
    var $layout = 'mozilla';
    var $uses = array('Addon', 'Eventlog', 'Review', 'Translation', 'Version', 'ReviewsModerationFlag');
    var $components = array('Amo', 'Pagination', 'Session');
    var $helpers = array('Html', 'Link', 'Localization', 'Pagination', 'Time');
    var $namedArgs = true;
    var $beforeFilter = array('checkCSRF', 'getNamedArgs', '_checkSandbox', 'checkAdvancedSearch');

    var $securityLevel = 'low';

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
    }
    
    /**
     * Display/add reviews
     */
    function display($id = null) {
        global $valid_status;
        
        $this->Amo->clean($id);
        $format = (isset($this->namedArgs['format']) ? $this->namedArgs['format'] : 'html');
    
        if (!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'addon_id'), '/', 3);
            return;
        }
        
        $this->Addon->bindOnly('Version');
        $addon = $this->Addon->findById($id);
        if (empty($addon)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }
        $this->publish('addon', $addon);
        
        // user logged in?
        $user = $this->Session->read('User');
        $this->publish('loggedin', !empty($user));
        if (!empty($user)) {
            // does user have a review already?
            $user_revcount = $this->Review->findCount("Review.user_id = {$user['id']} AND Review.version_id = {$addon['Version'][0]['id']}", null, null, null, null, 0);
            $this->publish('hasreview', ($user_revcount > 0));

            // is user an admin?
            $isadmin = $this->SimpleAcl->actionAllowed('Admin', 'EditAnyAddon', $user);
            $this->publish('isAdmin', $isadmin);

            // is user author of this addon?
            $isauthor = $this->Amo->checkOwnership($id, $addon['Addon'], true);
            $this->publish('isAuthor', $isauthor);
        } else {
            $this->publish('hasreview', false);
            $this->publish('isAdmin', false);
            $this->publish('isAuthor', false);
        }
        
        // can delete reviews?
        $this->publish('canDelete', $this->SimpleAcl->actionAllowed('Editors', 'DeleteAnyReview', $user));
        
        $this->status = $valid_status;

        $reviews_flagged = array();
        $others_counts = array();
        $reviews = array();
        
        // get all version ids for this addon
        $_versions = $this->Version->findAll(array('Version.addon_id' => $id),
            'Version.id', null, null, null, -1);
        $_version_ids = array();
        foreach ($_versions as $_version) $_version_ids[] = $_version['Version']['id'];
       
        if (!empty($_versions)) {
            $criteria = array(
                'Review.version_id' => $_version_ids,
                'Review.reply_to IS NULL'
            );
            
            $_review_ids = array();
            $others_counts = array();

            $this->Review->unbindfully();

            if (isset($_GET['user_id'])) {

                // Given a user_id parameter, load reviews only for that user.
                $_reviews = $this->Review->findAll(array_merge($criteria, array(
                    'Review.user_id' => $_GET['user_id']
                )), 'Review.id', 'Review.created DESC', NULL, 0, -1);
                $_review_ids = array();
                foreach($_reviews as $_id) 
                    $_review_ids[] = $_id['Review']['id'];

                if (isset($_GET['bare'])) {
                    // In the Ajax use case, avoid using a layout wrapper.
                    $this->layout = NULL;
                }
                if (isset($_GET['skip_first'])) {
                    // Skip the first review given the ?skip_first option.
                    array_shift($_review_ids);
                }

                $this->Pagination->total = count($_review_ids);
                list($order,$limit,$page) = 
                    $this->Pagination->init($criteria);

            } else {

                // Count and fetch reviews for the addon, only the latest per user.
                $this->Pagination->total =
                    $this->Review->countLatestReviewsForAddon($id);
                list($order,$limit,$page) = 
                    $this->Pagination->init($criteria);
                $_latest_reviews = 
                    $this->Review->findLatestReviewsForAddon($id, $limit, $page);

                foreach($_latest_reviews as $_r) {
                    $_id = $_r['id'];
                    $_review_ids[] = $_id;
                    $others_counts[$_id] = $_r['others_count'];
                }

            }

            $reviews = $this->Review->getReviews($_review_ids);
            
            // fetch possible developer replies
            foreach ($reviews as $_rid => $_review) {
                $reply = $this->Review->find(array(
                    'Review.reply_to' => $_review['Review']['id'],
                    ), "Review.id");
                if (!empty($reply)) {
                    $reply = $this->Review->getReviews($reply['Review']['id']);
                    $reviews[$_rid]['Review']['reply'] = $reply[0];
                }
            }

            // Fetch reviews flagged for moderation by this user, if any.
            if (!empty($user)) {
                $_flags = $this->ReviewsModerationFlag->findAll(array(
                    'ReviewsModerationFlag.user_id' => $user['id'],
                    'Review.version_id' => $_version_ids
                ));
                foreach ($_flags as $flag) {
                    $reviews_flagged[$flag['ReviewsModerationFlag']['review_id']] = 
                        $flag['ReviewsModerationFlag'];
                }
            }

        }

        $this->publish('reviews_flagged', $reviews_flagged);
        $this->publish('reviews_others_counts', $others_counts);
        $this->publish('reviews', $reviews);
            
        $_title = sprintf(_('addon_review_pagetitle'), $addon['Translation']['name']['string']);

        if ($format != 'rss') {
            $this->pageTitle = $_title.' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
            $this->publish('rssAdd', array("/reviews/display/{$id}/format:rss"));
            $this->publish('breadcrumbs', array(
                $addon['Translation']['name']['string'] => "/addon/{$addon['Addon']['id']}",
                $_title => "/reviews/display/$id"
            ));
            $this->publish('review_flag_reasons', $this->ReviewsModerationFlag->reasons);
            $this->render();
            return;
        } else {
            $this->publish('rss_title', $_title);
            $this->publish('rss_description', $_title);
            $this->render('rss/reviews', 'rss');
            return;
        }
    }

    /**
     * Add a new review
     */
    function add($id) {
        global $valid_status;
        
        $this->Amo->clean($id);
        $this->Amo->checkLoggedIn(); // must be logged in
        
        $this->publish('cssAdd', array('forms'));
        $this->set('reviewRating', 0);
        
        if (!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'addon_id'), '/', 3);
            return;
        }
        
        $addon = $this->Addon->findById($id);
        if (empty($addon)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }

        $isauthor = $this->Amo->checkOwnership($id, $addon['Addon'], true);
        if($isauthor) {
            $this->flash(_('error_addon_selfreview'), '/', 3);
        }

        $this->publish('addon', $addon);
        $_title = sprintf(_('addon_review_pagetitle'), $addon['Translation']['name']['string']);
        $this->pageTitle = $_title .' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        
        // fetch user object from session
        $user = $this->Session->read('User');
        
        
        $this->publish('breadcrumbs', array(
            $addon['Translation']['name']['string'] => "/addon/{$addon['Addon']['id']}",
            $_title => "/reviews/add/$id"
            ));
            
        // add/edit review if submitted
        if (isset($this->data['Review'])) {
            $old_title = $this->data['Review']['title'];
            $old_body =  $this->data['Review']['body'];
            $this->Amo->clean($this->data['Review']);
            
            // validate rating
            if ($this->data['Review']['rating'] < 0 || $this->data['Review']['rating'] > 5) {
                $this->Review->invalidate('rating');
                return;
            }

            $this->data['Review']['version_id'] = $this->Version->getVersionByAddonId($addon['Addon']['id'], $valid_status); // add version id to data array
            $this->data['Review']['user_id'] = $user['id'];
            $this->data['Review']['editorreview'] = 0; // auto-approve review

            // if id is set, check if it's valid
            if ($this->data['Review']['id'] !== 0) {
                $oldreview = $this->Review->find("Version.addon_id = {$id} AND Review.user_id = {$user['id']}");
                if (!isset($oldreview['Review']['id']) || $oldreview['Review']['id'] === $this->data['Review']['id'])
                    $this->Review->invalidate('id');
            }
            
            if ($this->Review->save($this->data)) {
                $this->Review->updateBayesianRating(array($id));
                $this->render('review_added');
                return;
            } else {
                $this->data['Review']['title'] = $old_title;
                $this->data['Review']['body'] = $old_body;
                $this->publish('errorMessage', true);
            }
        } else {
            // edit a previous review if present
            $oldreview = $this->Review->find("Version.addon_id = {$id} AND Review.user_id = {$user['id']}");
            if (!empty($oldreview)) {
                $this->data['Review'] = $oldreview['Review'];
                $this->set('reviewRating', $oldreview['Review']['rating']);
                // drop in localized strings
                if ($oldreview['Translation']['title']['locale'] == LANG) {
                    $this->data['Review']['title'] = htmlentities($oldreview['Translation']['title']['string']);
                    $this->data['Review']['body'] = htmlentities($oldreview['Translation']['body']['string']);
                } else {
                    $this->data['Review']['title'] = '';
                    $this->data['Review']['body'] = '';
                }
                $this->publish('editreview', true);
            }
        }
    }

    /**
     * Developer reply to a review
     *
     * @param int review ID to reply to
     */
    function reply($id) {
        $this->Amo->clean($id);
        $this->Amo->checkLoggedIn(); // must be logged in
        
        $this->publish('cssAdd', array('forms'));

        if (!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'addon_id'), '/', 3);
            return;
        }
        
        // find review we're replying to (only where reply_to is null, i.e. no replies to replies)
        $review = $this->Review->find("Review.id = $id AND Review.reply_to IS NULL", null, null, 2);
        if (empty($review)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }
        $this->publish('reply_to', $review);

        $version = $this->Version->findById($review['Review']['version_id']);
        $addon = $this->Addon->findById($version['Version']['addon_id']);
        if (empty($addon)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }
        $this->publish('addon', $addon);

        $_title = sprintf(_('addon_review_pagetitle'), $addon['Translation']['name']['string']);
        $this->pageTitle = $_title .' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        
        // fetch user object from session
        $user = $this->Session->read('User');

        // only authors are allowed to reply to reviews
        if (!$this->Amo->checkOwnership($addon['Addon']['id'], $addon['Addon'])) {
            $this->flash(_('error_access_denied'), '/', 3);
            return;
        }
        
        $this->publish('breadcrumbs', array(
            $addon['Translation']['name']['string'] => "/addon/{$addon['Addon']['id']}",
            $_title => "/reviews/display/".$addon['Addon']['id']
            ));
            
        // if the developer already replied to this, fetch the reply
        $oldreply = $this->Review->find(array('Review.reply_to' => $id));

        // add review if submitted
        if (isset($this->data['Review'])) {
            $this->Amo->clean($this->data['Review']);
            
            $this->data['Review']['version_id'] = $review['Version']['id']; // add version id to data array
            $this->data['Review']['user_id'] = $user['id'];
            $this->data['Review']['editorreview'] = 0; // auto-approve replies
            $this->data['Review']['rating'] = null;

            // set reply id
            if (!empty($oldreply))
                $this->data['Review']['id'] = $oldreply['Review']['id'];
            else
                unset($this->data['Review']['id']);
            // set review id we're replying to
            $this->data['Review']['reply_to'] = $id;

            if ($this->Review->save($this->data)) {
                $this->render('review_added');
                return;
            } else {
                $this->publish('errorMessage', true);
            }
        } else {
            // edit a previous reply if present
            if (!empty($oldreply)) {
                $this->data['Review'] = $oldreply['Review'];
                // drop in localized strings
                if ($oldreply['Translation']['title']['locale'] == LANG
                    && $oldreply['Translation']['title']['locale'] == LANG) {
                    $this->data['Review']['title'] = $oldreply['Translation']['title']['string'];
                    $this->data['Review']['body'] = $oldreply['Translation']['body']['string'];
                } else {
                    $this->data['Review']['title'] = '';
                    $this->data['Review']['body'] = '';
                }
                $this->publish('editreview', true);
            }
        }
        $this->render('add');
    }
    
    function delete($id) {
        // disable query caching
        foreach ($this->uses as $_model) {
            $this->$_model->caching = false;
        }
        
        // Make sure user has access
        if (!$this->SimpleAcl->actionAllowed('Editors', 'DeleteAnyReview', $this->Session->read('User'))) {
            $this->Amo->accessDenied();
            return;
        }
        
        $this->Review->id = $id;
        
        $review = $this->Review->findById($id, null, null, 2);
        $review['Addon'] = $this->Addon->findById($review['Version']['addon_id'], array('id', 'name'), null, -1);
        
        $_title = sprintf(_('addon_review_pagetitle'), $review['Addon']['Translation']['name']['string']);
        $this->pageTitle = $_title .' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        
        $this->publish('breadcrumbs', array(
                $review['Addon']['Translation']['name']['string'] => "/addon/{$review['Addon']['Addon']['id']}",
                $_title => "/reviews/display/{$review['Addon']['Addon']['id']}"
        ));
        
        if (!empty($_POST['yes'])) {
            //Pull review for log (in en-US)
            $this->Review->setLang('en-US', $this);
            $reviewInfo = $this->Review->read();
            $this->Review->setLang(LANG, $this);
            
            $reviewArray = array('title' => $reviewInfo['Translation']['title']['string'],
                                 'body' => $reviewInfo['Translation']['body']['string']);
            //Log editor action
            $this->Eventlog->log($this, 'editor', 'review_delete', null, $id, null, null, serialize($reviewArray));
            
            $this->Review->delete();
            
            // update average ratings
            debug($review['Addon']['Addon']['id']);
            $this->Review->updateBayesianRating(array($review['Addon']['Addon']['id']));
            
            $this->flash(_('addon_review_deleted_successfully'), "/reviews/display/{$review['Version']['addon_id']}");
            return;
        }
        
        if (!empty($_POST['no'])) {
            $this->redirect("/reviews/display/{$review['Version']['addon_id']}");
            return;
        }
        
        $this->publish('review', $review);
        
        $this->render('delete', 'mozilla');
    }
    
    /**
     * Flag a review as inappropriate.
     * 
     * (if called as .../ajax, this is an ajax action)
     */
    function flag($ajax = null) {
        $this->publish('ajaxreply', $ajax);
        if (!isset($this->data['Review']['id']) || !is_numeric($this->data['Review']['id'])) {
            header('HTTP/1.1 400 Bad Request');
            if (!$ajax)
                $this->flash(_('error_missing_argument'), "/");
            else {
                $this->publish('msg', _('error_missing_argument'));
                $this->render('flag', 'ajax');
            }
            return;
        }
        // must be logged in to flag something
        if (!$this->Session->check('User')) {
            if (!$ajax)
                $this->Amo->checkLoggedIn();
            else {
                $this->publish('msg', _('error_access_denied'));
                $this->render('flag', 'ajax');
            }
            return;
        }

        $error     = FALSE;
        $user      = $this->Session->read('User');
        $reviewid  = $this->data['Review']['id'];
        $flag_name = $this->data['ReviewsModerationFlag']['flag_name'];

        // Check for freeform notes, forcing the flag to 'other' if present.
        $flag_notes = @$this->data['ReviewsModerationFlag']['flag_notes'];
        if ($flag_notes) $flag_name = 'review_flag_reason_other';

        // Ensure that the incoming flag reason is one of the defined set 
        if (!array_key_exists($flag_name, $this->ReviewsModerationFlag->reasons)) {
            
            // Discard the flag if it's not in the defined set.
            $flag_name = '';
            $error = TRUE;

        } else {
            
            // Attempt to fetch an existing flag for this user and 
            // this review.
            $this->ReviewsModerationFlag->unbindFully();
            $user_flag = $this->ReviewsModerationFlag->find(array(
                'ReviewsModerationFlag.user_id'   => $user['id'],
                'ReviewsModerationFlag.review_id' => $reviewid
            ));

            if (!$user_flag) {
                // No flag found for this user and review, so start 
                // preparing a fresh record.
                $user_flag = array(
                    'ReviewsModerationFlag' => array(
                        'user_id'   => $user['id'],
                        'review_id' => $reviewid,
                        'created'   => date('Y-m-d h:i:s', time())
                    )
                );
            }

            $user_flag['ReviewsModerationFlag']['flag_name'] = 
                $flag_name;
            $user_flag['ReviewsModerationFlag']['modified'] = 
                date('Y-m-d h:i:s', time());

            // Accept only between 10 and 100 characters of free form flag notes,
            // when the 'other' flag is chosen.
            if ($flag_name == 'review_flag_reason_other') {
                if (!$flag_notes || strlen($flag_notes) < 10 || strlen($flag_notes) > 100) {
                    $error = TRUE;
                    $this->publish('msg',  sprintf( ___('addon_review_flag_error_other_length',
                        'Problem flagging review: Notes for flagged reviews are limited to between ' . 
                        '10 and 100 characters; your character length was %s.'), 
                        strlen($flag_notes) ) );
                } else {
                    $user_flag['ReviewsModerationFlag']['flag_notes'] = $flag_notes;
                }
            }

            if (!$error) 
                $this->ReviewsModerationFlag->save($user_flag);
        }
        
        // mark review for editor approval
        $this->Review->id = $reviewid;
        if (!$error && $this->Review->saveField('editorreview', 1)) {
            $this->publish('msg', ___('review_flag_success', 
                'Thanks; this review has been flagged for editor approval.'));
        } else {
            if (!isset($this->viewVars['msg']))
                $this->publish('msg', ___('review_flag_error', 
                    'Error flagging this review!'));
        }
        
        if (!$ajax) {
            $review = $this->Review->findById($reviewid);
            if (!empty($review)) {
                $version = $this->Version->findById($review['Review']['version_id'], 'Version.addon_id');
                $addon = $this->Addon->findById($version['Version']['addon_id']);
                $this->publish('addon', $addon);
            } else
                $this->publish('addon', false);
            $this->render();
        } else {
            if ($error) header('HTTP/1.1 400 Bad Request');
            $this->render('flag', 'ajax');
        }
    }
}

?>
