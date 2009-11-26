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
 * Portions created by the Initial Developer are Copyright (C) 2007
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
class AuditComponent extends Object {
    var $controller;

   /**
    * Save a reference to the controller on startup
    * @param object &$controller the controller using this component
    */
    function startup(&$controller) {
        $this->controller =& $controller;
    }

    /**
     * Helper for creating an HTML link.
     */
    function link($title, $url) {
        return "<a href=\"{$this->controller->url($url)}\">{$title}</a>";
    }

    /**
     * Helper for creating a link where the title is the end of the URL.
     * NOTE: $urlBase should have a trailing slash.
     */
    function linkTitle($id, $urlBase) {
        return $this->link($id, $urlBase.$id);
    }

    function explainLog($logs) {
        $newLog = array();

        if (!empty($logs)) {
            foreach ($logs as $log) {
                $userInfo = $this->controller->User->getUser($log['Eventlog']['user_id']);
                $name = trim($userInfo['User']['display_name']);
                if(empty($name)) $name = $userInfo['User']['email'];

                $user = $this->link($name, '/users/info/'.$log['Eventlog']['user_id']);

                switch ($log['Eventlog']['type']) {
                    case 'admin':
                        switch ($log['Eventlog']['action']) {
                            case 'addon_status':
                                $status = $this->controller->Amo->getApprovalStatus($log['Eventlog']['added']);
                                $addonInfo = $this->controller->Addon->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $addon = $this->link($addonInfo['Translation']['name']['string'], '/admin/addons/status/'.$log['Eventlog']['changed_id']);

                                $entry = sprintf(___('%1$s changed the status of %2$s to %3$s'), $user, $addon, $status);
                                break;

                            case 'file_recalchash':
                                $entry = sprintf(___('%1$s recalculated the hash for file %2$s'), $user, $log['Eventlog']['changed_id']);
                                break;

                            case 'application_create':
                            case 'application_edit':
                                $applicationInfo = $this->controller->Application->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $application = $this->link($applicationInfo['Translation']['name']['string'], '/admin/applications');

                                if ($log['Eventlog']['action'] == 'application_create') {
                                    $entry = sprintf(___('%1$s created application %2$s'), $user, $application);
                                }
                                elseif ($log['Eventlog']['action'] == 'application_edit') {
                                    $entry = sprintf(___('%1$s edited application %2$s'), $user, $application);
                                }
                                break;

                            case 'appversion_create':
                            case 'appversion_delete':
                                $applicationInfo = $this->controller->Application->findById($log['Eventlog']['notes'], null, null, -1);
                                $application = $this->link($applicationInfo['Translation']['name']['string'], '/admin/applications');

                                if ($log['Eventlog']['action'] == 'appversion_create') {
                                    $entry = sprintf(___('%1$s created version %2$s for %3$s'), $user, $log['Eventlog']['added'], $application);
                                }
                                elseif ($log['Eventlog']['action'] == 'appversion_delete') {
                                    $entry = sprintf(___('%1$s deleted version %2$s for %3$s'), $user, $log['Eventlog']['removed'], $application);
                                }
                                break;

                            case 'category_create':
                            case 'category_edit':
                                $categoryInfo = $this->controller->Category->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $category = $this->link($categoryInfo['Translation']['name']['string'], '/admin/categories');

                                if ($log['Eventlog']['action'] == 'category_create') {
                                    $entry = sprintf(___('%1$s created tag %2$s'), $user, $category);
                                }
                                elseif ($log['Eventlog']['action'] == 'category_edit') {
                                    $entry = sprintf(___('%1$s edited category %2$s'), $user, $category);
                                }
                                break;

                            case 'category_delete':
                                $entry = sprintf(___('%1$s deleted category %2$s (ID %3$s)'), $user, $log['Eventlog']['removed'], $log['Eventlog']['changed_id']);
                                break;

                            case 'platform_create':
                            case 'platform_edit':
                                $platformInfo = $this->controller->Platform->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $platform = $this->link($platformInfo['Translation']['name']['string'], '/admin/platforms');

                                if ($log['Eventlog']['action'] == 'platform_create') {
                                    $entry = sprintf(___('%1$s created platform %2$s'), $user, $platform);
                                }
                                elseif ($log['Eventlog']['action'] == 'platform_edit') {
                                    $entry = sprintf(___('%1$s edited platform %2$s'), $user, $platform);
                                }
                                break;

                            case 'platform_delete':
                                $entry = sprintf(___('%1$s deleted platform %2$s (ID %3$s)'), $user, $log['Eventlog']['removed'], $log['Eventlog']['changed_id']);
                                break;

                            case 'feature_add':
                            case 'feature_edit':
                                $featureInfo = $this->controller->Feature->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $entry = sprintf(___('%1$s changed a feature for %2$s locale'), $user, $featureInfo['Feature']['locale']);
                                break;

                            case 'feature_remove':
                                $entry = sprintf(___('%1$s removed feature %2$s'), $user, $log['Eventlog']['removed']);
                                break;

                            case 'group_create':
                            case 'group_edit':
                                $groupInfo = $this->controller->Group->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $group = $this->link($groupInfo['Group']['name'], '/admin/groups');

                                if ($log['Eventlog']['action'] == 'group_create') {
                                    $entry = sprintf(___('%1$s created group %2$s'), $user, $group);
                                }
                                elseif ($log['Eventlog']['action'] == 'group_edit') {
                                    $entry = sprintf(___('%1$s edited group %2$s'), $user, $group);
                                }
                                break;

                            case 'group_delete':
                                $entry = sprintf(___('%1$s deleted group %2$s (ID %3$s)'), $user, $log['Eventlog']['removed'], $log['Eventlog']['changed_id']);
                                break;

                            case 'group_addmember':
                            case 'group_removemember':
                                $groupInfo = $this->controller->Group->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $group = $this->link($groupInfo['Group']['name'], '/admin/groups');

                                if ($log['Eventlog']['action'] == 'group_addmember') {
                                    $memberInfo = $this->controller->User->getUser($log['Eventlog']['added']);
                                    $name = trim($memberInfo['User']['display_name']);
                                    if(empty($name)) $name = $memberInfo['User']['email'];

                                    $member = $this->link($name, '/admin/users/'.$log['Eventlog']['added']);

                                    $entry = sprintf(___('%1$s added %2$s to group %3$s'), $user, $member, $group);
                                }
                                elseif ($log['Eventlog']['action'] == 'group_removemember') {
                                    $memberInfo = $this->controller->User->getUser($log['Eventlog']['removed']);
                                    if (!isset($memberInfo['User']))
                                        $member = $log['Eventlog']['removed'];
                                    else {
                                    $name = trim($memberInfo['User']['display_name']);
                                    if(empty($name)) $name = $memberInfo['User']['email'];

                                    $member = $this->link($name, '/admin/users/'.$log['Eventlog']['removed']);
                                    }

                                    $entry = sprintf(___('%1$s removed %2$s from group %3$s'), $user, $member, $group);
                                }
                                break;

                            case 'response_create':
                            case 'response_edit':
                                $responseInfo = $this->controller->Cannedresponse->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $response = $this->link($responseInfo['Translation']['name']['string'], '/admin/responses');

                                if ($log['Eventlog']['action'] == 'response_create') {
                                    $entry = sprintf(___('%1$s created response %2$s'), $user, $response);
                                }
                                elseif ($log['Eventlog']['action'] == 'response_edit') {
                                    $entry = sprintf(___('%1$s edited response %2$s'), $user, $response);
                                }
                                break;

                            case 'response_delete':
                                $entry = sprintf(___('%1$s deleted response %2$s (ID %3$s)'), $user, $log['Eventlog']['removed'], $log['Eventlog']['changed_id']);
                                break;

                            case 'config':
                                $entry = sprintf(___('%1$s changed config \'%2$s\' from \'%3$s\' to \'%4$s\''), $user, $log['Eventlog']['field'], $log['Eventlog']['removed'], $log['Eventlog']['added']);
                                break;

                            case 'user_edit':
                                $userInfo = $this->controller->User->getUser($log['Eventlog']['changed_id']);
                                $name = trim($userInfo['User']['display_name']);
                                if(empty($name)) $name = $userInfo['User']['email'];

                                $userLink = $this->link($name, '/admin/users/'.$log['Eventlog']['changed_id']);

                                $entry = sprintf(___('%1$s edited %2$s\'s user information'), $user, $userLink);
                                break;

                            default:
                                $entry = sprintf(___('%1$s committed unknown admin action %2$s to ID %3$s'), $user, $log['Eventlog']['action'], $log['Eventlog']['changed_id']);
                                break;
                        }
                        break;

                    case 'editor':

                        switch ($log['Eventlog']['action']) {
                            case 'feature_add':
                                $addonLink = $this->linkTitle($log['Eventlog']['added'], '/addon/');
                                $entry = sprintf(___('%1$s added addon %2$s to feature list'), $user, $addonLink);
                                break;

                            case 'feature_remove':
                                $addonLink = $this->linkTitle($log['Eventlog']['removed'], '/addon/');
                                $entry = sprintf(___('%1$s removed addon %2$s from feature list'), $user, $addonLink);
                                break;

                            case 'feature_locale_change':
                                $addonLink = $this->linkTitle($log['Eventlog']['changed_id'], '/addon/');
                                $entry = sprintf(___('%1$s changed locales for addon %2$s on feature list'), $user, $addonLink);
                                break;

                            case 'review_approve':
                                $entry = sprintf(___('%1$s approved review %2$s'), $user, $log['Eventlog']['changed_id']);
                                break;

                            case 'review_delete':
                                if ($this->controller->SimpleAcl->actionAllowed('Admin', 'logs', $this->controller->Session->read('User'))) {
                                    $entry = sprintf(___('%1$s deleted review %2$s'), $user, $this->link($log['Eventlog']['changed_id'], "/admin/logs/{$log['Eventlog']['id']}"));
                                }
                                else {
                                    $entry = sprintf(___('%1$s deleted review %2$s'), $user, $log['Eventlog']['changed_id']);
                                }
                                break;

                            default:
                                $entry = sprintf(___('%1$s committed unknown editor action %2$s to ID %3$s'), $user, $log['Eventlog']['action'], $log['Eventlog']['changed_id']);
                                break;
                        }
                        break;

                    case 'l10n':
                        switch ($log['Eventlog']['action']) {
                            case 'update_applications':
                                $entry = sprintf(___('%1$s updated application translations for %2$s'), $user, $this->linkTitle($log['Eventlog']['notes'], "/localizers/applications/?userlang="));
                                break;

                            case 'update_categories':
                                $entry = sprintf(___('%1$s updated categories for %2$s'), $user, $this->linkTitle($log['Eventlog']['notes'], "/localizers/categories/?userlang="));
                                break;

                            case 'update_platforms':
                                $entry = sprintf(___('%1$s updated platform translations for %2$s'), $user, $this->linkTitle($log['Eventlog']['notes'], "/localizers/platforms/?userlang="));
                                break;
                            case 'update_blog':
                                $entry = sprintf(___('%1$s updated blog post translations for %2$s'), $user, $this->linkTitle($log['Eventlog']['notes'], "/localizers/platforms/?userlang="));
                                break;
                            default:
                                $entry = sprintf(___('%1$s committed unknown action %2$s for %3$s'), $user, $log['Eventlog']['action'], $log['Eventlog']['notes']);
                                break;
                        }
                        break;

                    case 'security':
                        switch ($log['Eventlog']['action']) {
                            case 'reauthentication_failure':
                                $entry = sprintf(___('%1$s failed to re-authenticate to access %2$s.'), $user, $log['Eventlog']['notes']);
                                break;

                            case 'modify_locked_group':
                                $groupInfo = $this->controller->Group->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $group = $this->link($groupInfo['Group']['name'], '/admin/groups');

                                $entry = sprintf(___('%1$s attempted to modify locked group %2$s'), $user, $group);
                                break;

                            case 'modify_other_locale':
                                $entry = sprintf(___('%1$s attempted to modify translations in %2$s without permission'), $user, $log['Eventlog']['notes']);
                                break;

                            default:
                                $entry = sprintf(___('%1$s committed unknown security action %2$s to ID %3$s'), $user, $log['Eventlog']['action'], $log['Eventlog']['changed_id']);
                                break;
                        }
                        break;

                    case 'user':
                        switch ($log['Eventlog']['action']) {
                            case 'group_associated':
                                $groupInfo = $this->controller->Group->findById($log['Eventlog']['changed_id'], null, null, -1);
                                $group = $this->link($groupInfo['Group']['name'], '/admin/groups');
                                $entry = sprintf(___('%1$s associated themselves with %2$s'), $user, $group);
                                break;
                        }
                        break;

                    default:
                        break;
                }

                $newLog[] = array(
                                  'time' => $log['Eventlog']['created'],
                                  'entry' => $entry
                                 );
            }
        }

        return $newLog;
    }
}
?>
