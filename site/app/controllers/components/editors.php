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
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
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
class EditorsComponent extends Object {
    var $controller;
    
   /**
    * Save a reference to the controller on startup
    * @param object &$controller the controller using this component
    */
    function startup(&$controller) {
        $this->controller =& $controller;
    }
    
   /**
    * Process review for a nominated add-on.
    * Update add-on, approval, and file info and send emails
    * @param array $addon add-on information
    * @param array $data POST data
    */
    function reviewNominatedAddon($addon, $data) {
        //Make sure add-on is actually nominated
        if ($addon['Addon']['status'] != STATUS_NOMINATED) {
            $this->controller->Error->addError(___('This add-on has not been nominated.'));
            return false;
        }
        
        $this->controller->Addon->id = $addon['Addon']['id'];
        $addonData = array();
        
        //Get most recent version
        $version = $this->controller->Version->findByAddon_id($this->controller->Addon->id, null, 'Version.created DESC');

        if ($data['Approval']['ActionField'] == 'public') {
            $addonData['status'] = STATUS_PUBLIC;
            $addonData['higheststatus'] = STATUS_PUBLIC;
        }
        elseif ($data['Approval']['ActionField'] == 'sandbox') {
            $addonData['status'] = STATUS_SANDBOX;
        }
        elseif ($data['Approval']['ActionField'] == 'superreview') {
            $addonData['adminreview'] = 1;
            $addonData['status'] = STATUS_NOMINATED;
        }
        else {
            $this->controller->Error->addError(___('Please select a review action.'));
            return false;
        }
        
        if (empty($data['Approval']['comments'])) {
            $this->controller->Error->addError(___('Please enter review comments.'));
            return false;
        }
        
        $session = $this->controller->Session->read('User');
        
        $approvalData = array('user_id' => $session['id'],
                              'reviewtype' => 'nominated',
                              'action' => $addonData['status'],
                              'addon_id' => $this->controller->Addon->id,
                              'comments' => $data['Approval']['comments'],
                              'file_id' => $version['File'][0]['id']
                             );
        
        if ($this->controller->Error->noErrors()) {
            if ($data['Approval']['ActionField'] == 'public') {
                //Make files of most recent version public
                if (!empty($version['File'])) {
                    foreach ($version['File'] as $file) {
                        $this->controller->File->id = $file['id'];
                        $fileData = array('status' => STATUS_PUBLIC, 'datestatuschanged' => $this->controller->Amo->getNOW());
                        $this->controller->File->save($fileData);
                        
                        // Move to public rsync repo
                        $file = $this->controller->File->read();
                        $this->controller->Amo->copyFileToPublic($approvalData['addon_id'], $file['File']['filename']);
                    }
                }
            }
            
            $this->controller->Approval->save($approvalData);
            $ok = $this->controller->Addon->save($addonData);

            // Log addon action
            if ($ok && empty($addonData['adminreview'])) {
                $this->controller->Addonlog->logChangeStatus($this->controller, $addon['Addon']['id'], $addonData['status']);
            }
        }
        else {
            return false;
        }
        
        if (!empty($addon['User'])) {
            foreach ($addon['User'] as $user) {
                $authors[] = $user['email'];
            }
        }
        
        $emailInfo = array('name' => $addon['Translation']['name']['string'],
                           'id' => $this->controller->Addon->id,
                           'reviewer' => $session['firstname'].' '.$session['lastname'],
                           'email' => implode(', ', $authors),
                           'comments' => $data['Approval']['comments'],
                           'version' => !empty($version) ? $version['Version']['version'] : ''
                           );
        
        $this->controller->set('info', $emailInfo);
        
        if ($data['Approval']['ActionField'] != 'superreview') {
            $this->controller->Email->template = 'email/nominated/'.$data['Approval']['ActionField'];
            $this->controller->Email->to = $emailInfo['email'];
            $this->controller->Email->subject = sprintf('Mozilla Add-ons: %s Nomination', $emailInfo['name']);
        }
        else {
            $this->controller->Email->template = 'email/superreview';
            $this->controller->Email->to = 'amo-senior-editors@mozilla.org';
            //Doesn't need to be localized
            $this->controller->Email->subject = "Super-review requested: {$emailInfo['name']}";  
        }
        $result = $this->controller->Email->send();
        
        return true;
    }

   /**
    * Process review for pending files
    * Update approval and file info and send emails
    * @param array $addon add-on information
    * @param array $data POST data
    */
    function reviewPendingFiles($addon, $data) {
        if (empty($data['Approval']['File'])) {
            $this->controller->addError(___('Please select at least one file to review.', 'editor_review_error_no_files'));
            return false;
        }
            
        $this->controller->Addon->id = $addon['Addon']['id'];
        $fileData = array('datestatuschanged' => $this->controller->Amo->getNOW());

        //Get most recent version
        $version = $this->controller->Version->findByAddon_id($this->controller->Addon->id, null, 'Version.created DESC');

        if ($data['Approval']['ActionField'] == 'public') {
            $fileData['status'] = STATUS_PUBLIC;
        }
        elseif ($data['Approval']['ActionField'] == 'sandbox') {
            $fileData['status'] = STATUS_SANDBOX;
        }
        elseif ($data['Approval']['ActionField'] == 'superreview') {
            $addonData = array('adminreview' => 1);
            $fileData['status'] = STATUS_PENDING;
        }
        else {
            $this->controller->Error->addError(___('Please select a review action.'));
            return false;
        }
        
        if (empty($data['Approval']['comments'])) {
            $this->controller->Error->addError(___('Please enter review comments.'));
            return false;
        }

        if (empty($data['Approval']['applications'])) {
            $this->controller->Error->addError(___('Please enter the applications you tested.'));
            return false;
        }
        
        if (empty($data['Approval']['os'])) {
            $this->controller->Error->addError(___('Please enter the operating systems you tested.'));
            return false;
        }
        
        $session = $this->controller->Session->read('User');
        $platforms = $this->controller->Amo->getPlatformName();
        $files = array();
        
        // Loop through checked files
        foreach ($data['Approval']['File'] as $file_id) {
            if ($file_id > 0) {
                $this->controller->File->id = $file_id;
                $file = $this->controller->File->read();
                
                // Make sure file is pending review
                if ($file['File']['status'] != STATUS_PENDING) {
                    $this->controller->Error->addError(___('This file is not pending review.'));
                    return false;
                }
                
                $approvalData = array('user_id' => $session['id'],
                                      'reviewtype' => 'pending',
                                      'action' => $fileData['status'],
                                      'addon_id' => $this->controller->Addon->id,
                                      'file_id' => $file_id,
                                      'comments' => $data['Approval']['comments'],
                                      'os' => $data['Approval']['os'],
                                      'applications' => $data['Approval']['applications']
                                     );
                
                if ($this->controller->Error->noErrors()) {
                    // Save approval log and new file status
                    $this->controller->Approval->save($approvalData);
                    $ok = $this->controller->File->save($fileData);

                    // Log addon action
                    if ($ok) {
                        switch ($fileData['status']) {
                        case STATUS_PUBLIC:
                            $this->controller->Addonlog->logApproveVersion($this->controller, $addon['Addon']['id'], $version['Version']['id'], $version['Version']['version']);
                            break;

                        case STATUS_SANDBOX:
                            $this->controller->Addonlog->logRetainVersion($this->controller, $addon['Addon']['id'], $version['Version']['id'], $version['Version']['version']);
                            break;

                        case STATUS_PENDING:
                            if (!empty($addonData['adminreview'])) {
                                $this->controller->Addonlog->logEscalateVersion($this->controller, $addon['Addon']['id'], $version['Version']['id'], $version['Version']['version']);
                            }
                            break;
                        }
                    }
                    
                    // Move to public rsync repo
                    if ($fileData['status'] == STATUS_PUBLIC) {
                        $this->controller->Amo->copyFileToPublic($approvalData['addon_id'], $file['File']['filename']);
                    }
                    
                    if (!empty($addonData)) {
                        $this->controller->Addon->save($addonData);
                    }
                    
                    $files[] = $addon['Translation']['name']['string'].' '.$version['Version']['version'].' - '.$platforms[$file['File']['platform_id']];
                }
                else {
                    return false;
                }
            }
        }
        
        if (!empty($addon['User'])) {
            foreach ($addon['User'] as $user) {
                $authors[] = $user['email'];
            }
        }
        
        $emailInfo = array('name' => $addon['Translation']['name']['string'],
                           'id' => $this->controller->Addon->id,
                           'reviewer' => $session['firstname'].' '.$session['lastname'],
                           'email' => implode(', ', $authors),
                           'comments' => $data['Approval']['comments'],
                           'os' => $data['Approval']['os'],
                           'apps' => $data['Approval']['applications'],
                           'version' => !empty($version) ? $version['Version']['version'] : '',
                           'files' => $files
                           );
        $this->controller->set('info', $emailInfo);
        
        if ($data['Approval']['ActionField'] != 'superreview') {
            $this->controller->Email->template = 'email/pending/'.$data['Approval']['ActionField'];
            $this->controller->Email->to = $emailInfo['email'];
            $this->controller->Email->subject = sprintf('Mozilla Add-ons: %s %s', $emailInfo['name'], $emailInfo['version']);
        }
        else {
            $this->controller->Email->template = 'email/superreview';
            $this->controller->Email->to = 'amo-senior-editors@mozilla.org';
            //Doesn't need to be localized
            $this->controller->Email->subject = "Super-review requested: {$emailInfo['name']}";  
        }
        $result = $this->controller->Email->send();
        
        return true;
    }
    
    /**
     * Request more information from an author regarding an update/nomination
     * request
     */
    function requestInformation($addon, $data) {
        global $valid_status;
        
        // store information request
        $session = $this->controller->Session->read('User');
        foreach($data['Approval']['File'] as $_fid) {
            if ($_fid > 0) {
                $file_id = $_fid;
                break;
            }
        }
        $approvalData = array(
            'user_id' => $session['id'],
            'reviewtype' => 'info',
            'action' => 0,
            'addon_id' => $addon['Addon']['id'],
            'comments' => $data['Approval']['comments']
        );
        $this->controller->Approval->save($approvalData);
        $infoid = $this->controller->Approval->getLastInsertID();
        
        // send email to all authors
        $authors = array();
        foreach ($addon['User'] as &$user) $authors[] = $user['email'];
        
        $versionid = $this->controller->Version->getVersionByAddonId($addon['Addon']['id'], $valid_status);
        $version = $this->controller->Version->findById($versionid, null, null, -1);

        // log addon action
        $this->controller->Addonlog->logRequestVersion($this->controller, $addon['Addon']['id'], $versionid, $version['Version']['version']);
        
        $emailInfo = array(
            'name' => $addon['Translation']['name']['string'],
            'infoid' => $infoid,
            'reviewer' => $session['firstname'].' '.$session['lastname'],
            'comments' => $data['Approval']['comments'],
            'version' => !empty($version) ? $version['Version']['version'] : ''
        );
        $this->controller->publish('info', $emailInfo, false);
        $this->controller->Email->template = 'email/inforequest';
        $this->controller->Email->to = implode(', ', $authors);
        $this->controller->Email->subject = sprintf('Mozilla Add-ons: %s %s', $emailInfo['name'], $emailInfo['version']);
        $this->controller->Email->send();
    }

    /**
     * Post a new comment to a version by an editor
     * @param int $versionId version ID
     * @param array $data POST data
     * @return int id of new comment on success, false on error
     */
    function postVersionComment($versionId, $data) {
        $returnId = false;

        $session = $this->controller->Session->read('User');

        $commentData = $data['Versioncomment'];
        $commentData['version_id'] = $versionId;
        $commentData['user_id'] = $session['id'];

        // validation
        if (empty($commentData['subject'])) {
            $this->controller->Error->addError(___('Comment subject is required'));
        }
        if (empty($commentData['comment'])) {
            $this->controller->Error->addError(___('Comment body is required'));
        }

        // cake does not turn '' into NULL
        if ($commentData['reply_to'] === '') {
            $commentData['reply_to'] = null;
        }

        if ($this->controller->Error->noErrors()) {
            if ($this->controller->Versioncomment->save($commentData)) {
                $returnId = $this->controller->Versioncomment->id;
            } else {
                $this->controller->Error->addError(___('Failed to save comment'));
            }
        }

        return $returnId;
    }


    /**
     * Notify subscribed editors of a new comment posted to a thread
     * @param int $commentId id of new comment
     * @param int $rootId id of thread's root comment
     * @return void
     */
    function versionCommentNotify($commentId, $rootId) {
        $comment = $this->controller->Versioncomment->findById($commentId);
        $userIds = $this->controller->Versioncomment->getSubscribers($rootId);

        // nothing to send or nobody to send it to
        if (empty($comment) || empty($userIds)) { return; }

        // fetch details
        $addon = $this->controller->Addon->getAddon($comment['Version']['addon_id']);
        $this->controller->User->bindOnly('Group'); // Groups are needed for the ACL check
        $subscribers = $this->controller->User->findAllById($userIds);

        // send out notification email(s)
        $emailInfo = array(
            'addon' => $addon['Translation']['name']['string'],
            'version' => $comment['Version']['version'],
            'versionid' => $comment['Version']['id'],
            'commentid' => $commentId,
            'subject' => $comment['Versioncomment']['subject'],
            'author' => "{$comment['User']['firstname']} {$comment['User']['lastname']}",
        );
        $this->controller->publish('info', $emailInfo, false);
        
        // load the spam cannon
        $this->controller->Email->template = '../editors/email/notify_version_comment';
        $this->controller->Email->subject = "[AMO] {$emailInfo['subject']}; {$emailInfo['addon']} Review {$emailInfo['versionid']}";
        
        // fire away...
        foreach ($subscribers as &$subscriber) {
            // ... unless subscriber is no longer an editor
            if (!$this->controller->SimpleAcl->actionAllowed('Editors', 'review', $subscriber)) {
                continue;
            }
            $this->controller->Email->to = $subscriber['User']['email'];
            $result = $this->controller->Email->send();
        }
    }
    
    /**
     * Jump to specific item in queue
     * redirects to review page if item was found, to queue otherwise
     * @param string $listtype 'nominated' or 'pending'
     * @param int $rank list entry to jump to
     * @return void
     */
    function redirectByQueueRank($listtype, $rank) {
        switch($listtype) {
        case 'nominated':
        case 'pending':
            $rank = intval($rank);
            $offset = ($rank > 0) ? $rank - 1 : 0;
            $sql = $this->buildQueueFilterQuery($listtype);
            $queue_sql = "SELECT `Version`.`id`
                            {$sql['FROM']}
                            {$sql['JOIN']}
                            {$sql['WHERE']}
                            {$sql['ORDER']}
                            LIMIT 1 OFFSET {$offset}";

            if ($result = $this->controller->Addon->query($queue_sql)) {
                $review_id = $result[0]['Version']['id'];
                $this->controller->redirect("/editors/review/{$review_id}?num={$rank}");
                return;
            }
            break;
        
        default:
            return false;
        }
        
        // if we did not find anything, redirect to queue
        $this->controller->redirect("/editors/queue/{$listtype}");
    }
    
    /**
     * Notify subscribed editors of an add-on's update
     * @param int $addonid ID of add-on that was updated
     * @param int $versionid ID of the add-on's new version
     */
    function updateNotify($addonid, $versionid) {
        $_ids = $this->controller->EditorSubscription->getSubscribers($addonid);
        if (empty($_ids)) return;
        $subscribers = $this->controller->User->findAllById($_ids, null, null, null, null, -1);
        
        $addon = $this->controller->Addon->getAddon($addonid);
        $version = $this->controller->Version->findById($versionid, null, null, null, null, -1);
        
        // send out notification email(s)
        $emailInfo = array(
            'id' => $addonid,
            'name' => $addon['Translation']['name']['string'],
            'versionid' => $versionid,
            'version' => $version['Version']['version']
        );
        $this->controller->publish('info', $emailInfo, false);
        
        $this->controller->Email->template = '../editors/email/notify_update';
        $this->controller->Email->subject = sprintf('Mozilla Add-ons: %s Updated', $emailInfo['name']);
        
        foreach ($subscribers as &$subscriber) {
            $this->controller->Email->to = $subscriber['User']['email'];
            $result = $this->controller->Email->send();
            // unsubscribe user from further updates
            $this->controller->EditorSubscription->cancelUpdates($subscriber['User']['id'], $addonid);
        }
        
    }

    /**
     * Determine if the specified queue is filterable
     * Queues currently filterable are: 'nominated' and 'pending'
     * @param string $queue name of queue
     * @return bool
     */
    function isFilterableQueue($queue) {
        return in_array($queue, array('pending', 'nominated'));
    }

    /**
     * Build query components for filtering the specified queue
     * Returns empty array for unfilterable queues
     * @param string $queue name of queue
     * @return array array('FROM'=>string, 'JOIN'=>string, 'WHERE'=>string, 'ORDER'=>string)
     * @TODO: for cake >=1.2, return compatible 'joins' and 'conditions' arrays
     */
    function buildQueueFilterQuery($queue='pending') {
        if (!$this->isFilterableQueue($queue)) {
            return array();
        }

        // Setup query components
        $base_components = $this->baseQueueFilterQuery($queue);
        $from = $base_components['FROM'];
        $joins = $base_components['JOIN'];
        $where = $base_components['WHERE'];
        $order = 'ORDER BY';

        // Fetch and apply filter
        if ($filter = $this->getQueueFilter($queue)) {
            $this->controller->Amo->clean($filter, false);

            if (isset($filter['AddonOrAuthor']) && strlen($filter['AddonOrAuthor']) > 0) {
                // search addons.name (localized), addons.supportemail (localized) and users.email
                $where .= "\nAND (`Version`.`addon_id` IN(
                                SELECT `a`.`id` FROM `addons` AS `a`
                                LEFT JOIN `translations` AS `ntr_l` ON
                                    (`ntr_l`.`id`=`a`.`name` AND `ntr_l`.`locale`='".LANG."')
                                LEFT JOIN `translations` AS `ntr_en` ON
                                    (`ntr_en`.`id`=`a`.`name` AND `ntr_en`.`locale`=`a`.`defaultlocale`)
                                LEFT JOIN `translations` AS `etr_l` ON
                                    (`etr_l`.`id`=`a`.`supportemail` AND `etr_l`.`locale`='".LANG."')
                                LEFT JOIN `translations` AS `etr_en` ON
                                    (`etr_en`.`id`=`a`.`supportemail` AND `etr_en`.`locale`=`a`.`defaultlocale`)
                                WHERE
                                    IFNULL(`ntr_l`.`localized_string`, `ntr_en`.`localized_string`)
                                        LIKE '%{$filter['AddonOrAuthor']}%'
                                    OR IFNULL(`etr_l`.`localized_string`, `etr_en`.`localized_string`)
                                        LIKE '%{$filter['AddonOrAuthor']}%'

                            ) OR `Version`.`addon_id` IN(
                                SELECT `a`.`id` FROM `addons` AS `a`
                                LEFT JOIN `addons_users` AS `au` ON (`a`.`id`=`au`.`addon_id`)
                                LEFT JOIN `users` AS `u` ON (`au`.`user_id`=`u`.`id`)
                                WHERE
                                    `au`.`role` IN(".AUTHOR_ROLE_ADMINOWNER.","
                                                .AUTHOR_ROLE_ADMIN.","
                                                .AUTHOR_ROLE_OWNER.","
                                                .AUTHOR_ROLE_DEV.")
                                    AND `u`.`email` LIKE '%{$filter['AddonOrAuthor']}%'
                            ))";
            }

            if (!empty($filter['Application'])) {
                $joins .= "\nLEFT JOIN `applications_versions`
                    ON (`Version`.`id`=`applications_versions`.`version_id`)";
                $where .= "\nAND (
                    `applications_versions`.`application_id`='{$filter['Application']}')";

                if (!empty($filter['MaxVersion'])) {
                    $where .= "\nAND (`applications_versions`.`max`='{$filter['MaxVersion']}')";
                }
            }

            if (!empty($filter['SubmissionAge'])) {
                $age = $filter['SubmissionAge'];
                $age_op = '=';  // exact match by default

                if (substr($age, -1) == '+') {
                    // values like '10+' magically turn into a '>=10' comparison
                    $age_op = '>=';
                    $age = substr($age, 0, -1);
                } elseif ($age === '1') {
                    // make '1 day' include less than 1 as well
                    $age_op = '<=';
                }

                if ($queue == 'pending') {
                    $where .= "\nAND (TIMESTAMPDIFF(DAY, `Version`.`created`, NOW()){$age_op}'{$age}')";
                } elseif ($queue == 'nominated') {
                    $where .= "\nAND (TIMESTAMPDIFF(DAY, `Addon`.`nominationdate`, NOW()){$age_op}'{$age}')";
                }
            }

            if (!empty($filter['Addontype'])) {
                if (is_array($filter['Addontype'])) {
                    $filter_vals = $filter['Addontype'];
                } else {
                    $filter_vals = array($filter['Addontype']);
                }

                $where .= "\nAND (`Addon`.`addontype_id` IN('"
                                            . implode("','", $filter_vals) ."'))";
            }

            if (!empty($filter['Platform'])) {
                if (is_array($filter['Platform'])) {
                    $filter_vals = $filter['Platform'];
                } else {
                    $filter_vals = array($filter['Platform']);
                }

                // only available to pending queue for now
                if ($queue == 'pending') {
                    $where .= "\nAND (`File`.`platform_id` IN('"
                                                . implode("','", $filter_vals) ."'))";
                }
            }

            if (isset($filter['AdminFlag'])) {
                $where .= "\nAND (`Addon`.`adminreview` = '{$filter['AdminFlag']}')";
            }

        }
        //End apply filter

        // Sorting
        $theSort = $this->getQueueSort($queue);
        switch ($theSort['sortby']) {
        case 'name':
            $joins .= "\nLEFT JOIN `translations` AS `tr_l` ON
                            (`tr_l`.`id` = `Addon`.`name` AND `tr_l`.`locale` = '".LANG."')"
                     ."\nLEFT JOIN `translations` AS `tr_en` ON
                            (`tr_en`.`id` = `Addon`.`name` AND `tr_en`.`locale` = `Addon`.`defaultlocale`)";
            $order .= ' IFNULL(tr_l.localized_string, tr_en.localized_string)';
            $order .= strtoupper($theSort['direction'] == 'DESC') ? ' DESC' : ' ASC';
            break;

        case 'type':
            $joins .= "\nLEFT JOIN addontypes AS `Addontype` ON
                            (`Addon`.`addontype_id`=`Addontype`.`id`)"
                     ."\nLEFT JOIN translations AS `tr_l` ON
                            (`tr_l`.`id` = `Addontype`.`name` AND `tr_l`.`locale` = '".LANG."')"
                     ."\nLEFT JOIN translations AS tr_en ON
                            (`tr_en`.`id` = `Addontype`.`name` AND `tr_en`.`locale` = 'en-US')";
            $order .= ' IFNULL(`tr_l`.`localized_string`, `tr_en`.`localized_string`)';
            $order .= strtoupper($theSort['direction'] == 'DESC') ? ' DESC' : ' ASC';
            // secondary sort by age
            if ($queue == 'pending') {
                $order .= ', `Version`.`created` ASC';
            } else {
                $order .= ', `Addon`.`nominationdate` ASC';
            }
            break;

        case 'age':
        default:
            if ($queue == 'pending') {
                $order .= ' `Version`.`created`';
            } else {
                $order .= ' `Addon`.`nominationdate`';
            }
            $order .= strtoupper($theSort['direction'] == 'DESC') ? ' DESC' : ' ASC';
            break;
        }

        return array('FROM'=>$from, 'JOIN'=>$joins, 'WHERE'=>$where, 'ORDER'=>$order);
    }

    /**
     * Return base query components for filtering the specified queue
     * Returns empty array for unfilterable queues
     * @param string $queue name of queue
     * @return array array('FROM'=>string, 'JOIN'=>string, 'WHERE'=>string)
     * @TODO: for cake >=1.2, return compatible 'joins' and 'conditions' arrays
     */
    function baseQueueFilterQuery($queue='pending') {
        if (!$this->isFilterableQueue($queue)) {
            return array();
        }

        // Setup query components
        if ($queue == 'pending') {
            $from = "FROM `files` AS `File`";
            $joins = "LEFT JOIN `versions` AS `Version` ON (`File`.`version_id`=`Version`.`id`)";
            $joins .= "\nINNER JOIN `addons` AS `Addon` ON (`Version`.`addon_id`=`Addon`.`id`)";
            $where = "WHERE `File`.`status`='".STATUS_PENDING."'";

        } elseif ($queue == 'nominated') {
            $from = "FROM `addons` AS `Addon`";
            $joins = "INNER JOIN `versions` AS `Version` ON (`Addon`.`id`=`Version`.`addon_id`)";
            $where = "WHERE `Addon`.`status`='".STATUS_NOMINATED."'";

            // This makes sure we get only the most recent version
            // "Find the version where no other version exists for the same addon and a greater creation date"
            // http://stackoverflow.com/questions/157459/problem-joining-on-the-highest-value-in-mysql-table
            $joins .= "\nLEFT JOIN `versions` AS `v2` ON (
                                `Version`.`addon_id`=`v2`.`addon_id` AND
                                `Version`.`created`<`v2`.`created`)";
            $where .= "\nAND (`v2`.`id` IS NULL)";
        }

        return array('FROM'=>$from, 'JOIN'=>$joins, 'WHERE'=>$where);
    }

    /**
     * Get the active filter for the specified queue
     * @param string $queue name of queue
     * @return array filter array or empty array if none exists
     */
    function getQueueFilter($queue) {
        if (!$this->isFilterableQueue($queue)) {
            return array();
        }

        $filter = array();
        $queue_filters = $this->controller->Session->read('editor_queue_filters');
        if (isset($queue_filters[$queue])) {
            $filter = $queue_filters[$queue];
        }
        return $filter;
    }

    /**
     * Set the active filter for the specified queue
     * Only known fields are saved into the filter
     * @param string $queue name of queue
     * @return array empty array, or valid saved filter
     */
    function setQueueFilter($queue, $new_filter) {
        if (!$this->isFilterableQueue($queue)) {
            return array();
        }

        // known filter fields
        $filter_fields = array('Addontype', 'Application', 'MaxVersion',
                                'Platform', 'SubmissionAge', 'AddonOrAuthor', 'AdminFlag');
        $filter = array();

        if (is_array($new_filter)) {
            foreach ($new_filter as $k => $val) {
                // only save known filter fields
                if (($val !== '') && in_array($k, $filter_fields)) {
                    $filter[$k] = $val;
                }
            }
        }

        // get or create array of filters
        $queue_filters = $this->controller->Session->read('editor_queue_filters');
        if (!is_array($queue_filters)) {
            $queue_filters = array();
        }

        if ($filter) {
            // update filter
            $queue_filters[$queue] = $filter;
        } else {
            // clear filter
            unset($queue_filters[$queue]);
        }

        // save all filters
        $this->controller->Session->write('editor_queue_filters', $queue_filters);

        return $filter;
    }

    /**
     * Determine if the specified queue is sortable
     * @param string $queue name of queue
     * @return bool
     */
    function isSortableQueue($queue) {
        return in_array($queue, array('pending', 'nominated'));
    }

    /**
     * Get current sort order parameters for specified queue
     * @param string $queue name of queue
     * @param string $default return default filter instead of saved - defaults to false
     * @return array
     */
    function getQueueSort($queue, $default=false) {
        if (!$this->isSortableQueue($queue)) {
            return array();
        }

        $defaults = array(
                        'pending'   => array('sortby'=>'age', 'direction'=>'ASC'),
                        'nominated' => array('sortby'=>'age', 'direction'=>'ASC'),
                    );

        if (!$default) {
            $queue_sorts = $this->controller->Session->read('editor_queue_sorts');
            if (isset($queue_sorts[$queue])) {
                return $queue_sorts[$queue];
            }
        }

        return $defaults[$queue];
    }

    /**
     * Set sort order parameters on specified queue
     * @param string $queue
     * @param string $sortby
     * @param string $direction
     * @return bool
     */
    function setQueueSort($queue, $sortby='default', $direction='default') {
        if (!$this->isSortableQueue($queue)) {
            return array();
        }

        // valid sorts for the queues, each sort containing default directions
        $validSorts = array(
                        'pending' => array(
                                        'age' => array('direction'=>'ASC'),
                                        'type' => array('direction'=>'ASC'),
                                        'name' => array('direction'=>'ASC'),
                                    ),
                        'nominated' => array(
                                        'age' => array('direction'=>'ASC'),
                                        'type' => array('direction'=>'ASC'),
                                        'name' => array('direction'=>'ASC'),
                                    ),
                    );

        $queueSorts = $this->controller->Session->read('editor_queue_sorts');
        if (!is_array($queueSorts)) {
            $queueSorts = array();
        }

        // reset to default
        if ($sortby == 'default') {
            unset($queueSorts[$queue]);
            $this->controller->Session->write('editor_queue_sorts', $queueSorts);

        // set to a known sort
        } elseif (array_key_exists($sortby, $validSorts[$queue])) {
            $newSort = $validSorts[$queue][$sortby];
            $newSort['sortby'] = $sortby;
            // use default direction unless...
            if (in_array(strtoupper($direction), array('DESC', 'ASC'))) {
                $newSort['direction'] = strtoupper($direction);
            }
            $queueSorts[$queue] = $newSort;
            $this->controller->Session->write('editor_queue_sorts', $queueSorts);

        // unknown sort
        } else {
            return false;
        }

        return true;
    }
}
?>
