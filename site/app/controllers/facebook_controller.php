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

define('RESULTS_PER_PAGE', 8);

class FacebookController extends AppController
{
    var $name = 'Facebook';
    var $beforeFilter = array('checkCSRF', 'getNamedArgs');
    var $uses = array('Addon', 'Application', 'FacebookData', 'FacebookFavorite', 'FacebookSession', 'FacebookUser', 'File', 'Preview', 'Category', 'User', 'Version');
    var $components = array('Amo', 'Image', 'Newsfeed', 'Search');
    var $helpers = array('Html', 'Facebook');

    var $securityLevel = 'low';
    
   /**
    * Initialize API and permission checks
    */
    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
        
        $this->Amo->startup($this);
        // Require API to be enabled
        if ((!defined('FB_ENABLED') || FB_ENABLED == 'false')) {
            $this->Amo->accessDenied();
            exit;
        }
        
        vendor('facebook'.DS.'facebook');
        
        // New facebook API
        $this->facebook = new Facebook(FB_API_KEY, FB_API_SECRET);
        $this->fbUser = $this->facebook->get_loggedin_user();
        
        $this->set('fbUser', $this->fbUser);
        
        $publicActions = array('app', 'outage', 'faq', 'wallpaper', 'updatenotes');
        if (!in_array($this->action, $publicActions)) {
            $this->facebook->require_add();
            
            /*/ While in development, require user to be in the Mozilla or Facebook networks
            if (!$this->_inMozillaOrFacebookNetworks()) {
                die("<fb:error><fb:message>Coming soon!</fb:message>Sorry, this application isn't available for public use quite yet. Please check back soon!</fb:error>");
            }*/
        }
        
        if ($this->action != 'app') {
            $this->FacebookUser->updateActivity($this->fbUser);
        }
        
        // Use shadow db for all add-on related reading
        $this->Addon->useDbConfig = 'shadow';
        $this->File->useDbConfig = 'shadow';
        $this->Version->useDbConfig = 'shadow';
        $this->FacebookFavorite->caching = false;
    }
    
   /**
    * Main page
    */
    function index() {
	return $this->home();
    }
    
   /**
    * Home
    */
    function home($dialog = '') {
        // Get friends
        $friends = implode(', ', $this->facebook->api_client->friends_get());
        
        // Get favorites
        $favorites = $this->FacebookFavorite->getFavoriteIds($this->fbUser, true);
        
        // Get newsfeed
        $newsfeed = $this->Newsfeed->generate($this->fbUser, $friends, $favorites);
        $this->set('newsfeed', $newsfeed);
        
        // Get new add-on count since last favorite
        $newAddonCount = $this->Newsfeed->getNewAddonCount($this->fbUser);
        $this->set('newAddonCount', $newAddonCount);
        
        // If user just added the app, show getting started message
        if ($dialog == 'gettingstarted') {
            $successMessage = 'Rock Your Firefox helps you discover new add-ons and share them with your friends by keeping track of your favorite add-ons. Add-ons you mark as favorites will be designated and recommended to your friends.<br><br>';
            $successMessage .= 'If you already have add-ons installed in Firefox, you can <a href="'.FB_URL.'/import?ref=gs">import them as favorites</a>.';
            $successMessage .= ' If not, you can <a href="'.FB_URL.'/browse?ref=gs">start browsing!</a>';
            $this->set('successTitle', 'Getting Started');
            $this->set('successMessage', $successMessage);
            $this->set('justAdded', true);
        }
        
        // If user invited friends, show confirmation
        if ($dialog == 'invited') {
            $successMessage = 'Your invitations have been sent.';
            $this->set('successTitle', 'Invitations Sent!');
            $this->set('successMessage', $successMessage);
        }
        
        $this->set('page', 'home');
        $this->render('home', 'facebook');
    }
    
   /**
    * Post-add and post-remove
    */
    function app($action) {
        if ($action == 'add') {
            $this->FacebookUser->add($this->fbUser);
            
            $this->FacebookData->updateData('add', $this->fbUser, $this->facebook->api_client);
            
            $this->home('gettingstarted');
        }
        elseif ($action == 'remove') {
            $this->FacebookUser->remove($this->fbUser);
            
            $this->FacebookData->updateData('remove', $this->fbUser, $this->facebook->api_client);
        }
    }
    
   /**
    * View a specific addon in a display window
    * @param int $addon_id the add-on id to display
    */
    function view($addon_id) {
        $this->Amo->clean($addon_id);
        
        if (empty($this->isFavorite)) {
            $this->isFavorite = $this->FacebookFavorite->isFavorite($this->fbUser, $addon_id);
        }
        
        $this->Addon->id = $addon_id;
        $addon = $this->Addon->read();
        
        foreach ($addon['User'] as $author) {
            $authors[] = "{$author['firstname']} {$author['lastname']}";
        }
        $this->set('addon_authors', $authors);
        
        $previews = $this->Preview->findAll("Preview.addon_id='{$addon_id}'", array('caption'));
        $this->set('previewCount', count($previews));
        $this->set('previewCaptions', $previews);
        
        // Get friends
        $friends = implode(', ', $this->facebook->api_client->friends_get());
        $addon['friendRecommended'] = $this->FacebookFavorite->findCount("FacebookFavorite.fb_user IN ({$friends}) AND FacebookFavorite.addon_id={$addon['Addon']['id']}");
        $addon['allRecommended'] = $this->FacebookFavorite->findAll("FacebookFavorite.addon_id={$addon['Addon']['id']}", null, "RAND()");
        
        $version_id = $this->Version->getVersionByAddonId($addon['Addon']['id']);
        $version = $this->Version->findById($version_id);
        
        $this->set('addon', $addon);
        $this->set('version', $version);
        $this->set('id', $addon_id);
        $this->set('isFavorite', $this->isFavorite);
        $this->set('page', 'view');
        $this->render('view', 'facebook');
    }
    
   /**
    * Import
    */
    function import() {
        $this->set('key', $this->FacebookSession->generateKey($this->fbUser));
        
        $this->set('page', 'import');
        $this->render('import_info', 'facebook');
    }
    
   /**
    * Search add-ons
    */
    function search() {
        $type = !empty($this->namedArgs['type']) ? $this->namedArgs['type'] : 'none';
        $page = !empty($this->namedArgs['page']) ? $this->namedArgs['page'] : 1;
        $current = array('type' => $type,
                         'page' => $page);
        $this->set('current', $current);
    
        uses('sanitize');
        $this->Sanitize = new Sanitize();
        
        if ($results = $this->Search->search($_GET['q'])) {
            $results = implode(',', $results);
            $criteria = "Addon.id IN (".$results.") AND Addon.status=".STATUS_PUBLIC." AND Addon.inactive=0";
            
            $typeCriteria = '';
            if ($type == ADDON_EXTENSION || $type == ADDON_THEME) {
                $typeCriteria = " AND Addon.addontype_id={$type}";
            }
            elseif ($type == 'friends') {
                //something
            }
            
            $addons = $this->Addon->findAll($criteria.$typeCriteria, null, 'FIELD(Addon.id,'.$results.')', RESULTS_PER_PAGE, $page);
            
            $count['total'] = $this->Addon->findCount("{$criteria} AND (Addon.addontype_id=".ADDON_EXTENSION." OR Addon.addontype_id=".ADDON_THEME.")");
            $count['extensions'] = $this->Addon->findCount("{$criteria} AND Addon.addontype_id=".ADDON_EXTENSION);
            $count['themes'] = $this->Addon->findCount("{$criteria} AND Addon.addontype_id=".ADDON_THEME);
            $count['friends'] = 0;
            $count['pages'] = ceil($count['total'] / RESULTS_PER_PAGE);
            
            // Get friends
            $friends = implode(', ', $this->facebook->api_client->friends_get());
            foreach ($addons as $k => $addon) {
                $addons[$k]['FacebookFavorite'] = $this->FacebookFavorite->findAll("FacebookFavorite.fb_user IN ({$friends}) AND FacebookFavorite.addon_id={$addon['Addon']['id']}");
                $addons[$k]['previewCount'] = $this->Preview->findCount("Preview.addon_id='{$addon['Addon']['id']}'");
            }
        }
        else {
            $addons = array();
            $count = array('total' => 0,
                           'extensions' => 0,
                           'themes' => 0,
                           'friends' => 0,
                           'pages' => 0);
        }
        
        $this->set('addons', $addons);
        $this->set('count', $count);
        
        $this->Amo->clean($_GET['q']);
        $this->publish('q', stripslashes($_GET['q']));
        
        $this->set('page', 'search');
        $this->render('search', 'facebook');
   }
   
   /**
    * Browse add-ons via named parameters
    */
    function browse() {
        $this->Amo->clean($_GET);
        
        if (isset($_GET['sort']))
            $this->namedArgs['sort'] = $_GET['sort'];
            
        if (isset($_GET['cat']))
            $this->namedArgs['cat'] = $_GET['cat'];
            
        $sort = !empty($this->namedArgs['sort']) ? $this->namedArgs['sort'] : 'popular';
        $type = !empty($this->namedArgs['type']) ? $this->namedArgs['type'] : 'none';
        $cat = !empty($this->namedArgs['cat']) ? $this->namedArgs['cat'] : 'all';
        $fbUser = !empty($this->namedArgs['fid']) ? $this->namedArgs['fid'] : '';
        $page = !empty($this->namedArgs['page']) ? $this->namedArgs['page'] : 1;
        $order = ($sort == 'name') ? 'ASC' : 'DESC';
        
        $current = array('sort' => $sort,
                         'type' => $type,
                         'cat' => $cat,
                         'page' => $page,
                         'order' => $order,
                         'fbUser' => $fbUser);
        $this->set('current', $current);
        
        if ($type == 'none')
            $currentType = 'total';
        elseif ($type == ADDON_EXTENSION)
            $currentType = 'extensions';
        elseif ($type == ADDON_THEME)
            $currentType = 'themes';
        elseif ($type == 'friends')
            $currentType = 'friends';
        
        if ($type == 'friends') {
            // Get friends
            $friends = implode(', ', $this->facebook->api_client->friends_get());
        }
        else
            $friends = '';
        
        if ($type == 'none' || $type == 'friends') {
            $type = array(ADDON_EXTENSION, ADDON_THEME);
        }
        
        $this->Addon->bindFully();
        if ($addons = $this->Addon->getAddonsByCategory(null, array(STATUS_PUBLIC), $type, $cat, $sort, $order, RESULTS_PER_PAGE, $page, $friends)) {
            if (empty($friends)) {
                // Get friends
                $friends = implode(', ', $this->facebook->api_client->friends_get());
            }
            
            $count['total'] = $this->Addon->countAddonsInCategory(array(STATUS_PUBLIC), array(ADDON_EXTENSION, ADDON_THEME), $cat);
            $count['extensions'] = $this->Addon->countAddonsInCategory(array(STATUS_PUBLIC), array(ADDON_EXTENSION), $cat);
            $count['themes'] = $this->Addon->countAddonsInCategory(array(STATUS_PUBLIC), array(ADDON_THEME), $cat);
            $count['friends'] = $this->Addon->countAddonsInCategory(array(STATUS_PUBLIC), array(ADDON_EXTENSION, ADDON_THEME), $cat, $friends);
            $count['pages'] = ceil($count[$currentType]/ RESULTS_PER_PAGE);
            
            foreach ($addons as $k => $addon) {
                if (!empty($friends))
                    $addons[$k]['FacebookFavorite'] = $this->FacebookFavorite->findAll("FacebookFavorite.fb_user IN ({$friends}) AND FacebookFavorite.addon_id={$addon['Addon']['id']}");
                $addons[$k]['previewCount'] = $this->Preview->findCount("Preview.addon_id='{$addon['Addon']['id']}'");
            }
        }
        else {
            $addons = array();
        }
        
        if ($type == ADDON_EXTENSION || (is_array($type) && in_array(ADDON_EXTENSION, $type))) {
            // Extension categories
            if ($extension_categories = $this->Category->findAll(array('application_id' => APP_ID, 'addontype_id' => ADDON_EXTENSION))) {
                foreach ($extension_categories as $extension_category) {
                    $categories[$extension_category['Category']['id']] = (is_array($type) ? 'Extensions: ' : '').$extension_category['Translation']['name']['string'];
                }
            }
        }
        if ($type == ADDON_THEME || (is_array($type) && in_array(ADDON_THEME, $type))) {
            // Theme categories
            if ($theme_categories = $this->Category->findAll(array('application_id' => APP_ID, 'addontype_id' => ADDON_THEME))) {
                foreach ($theme_categories as $theme_category) {
                    $categories[$theme_category['Category']['id']] = (is_array($type) ? 'Themes: ' : '').$theme_category['Translation']['name']['string'];
                }
            }
        }
        
        $categories[0] = ' Filter by Category';
        asort($categories);
        $this->set('categories', $categories);
        
        $this->set('addons', $addons);
        $this->set('count', $count);
        $this->set('page', 'browse');
        $this->render('browse', 'facebook');
    }
    
   /**
    * Manage Favorites
    * @param string $action what action to take with addon id $id
    * @param int $id the addon_id to add/remove as favorite
    */
    function favorite($action, $id, $return_to = 'favorites') {
        $this->Amo->clean($id);
        
        $addon = $this->Addon->findById($id, array('name', 'summary'));
        
        // Add a new favorite
        if ($action == 'add') {
            if ($this->FacebookFavorite->isFavorite($this->fbUser, $id)) {
                $this->set('errorTitle', 'Could not add favorite');
                $this->set('errorMessage', "<a href=\"".FB_URL."/view/{$id}\">{$addon[0]['name']}</a> is already on your favorites list.");
            }
            else {
                $this->FacebookFavorite->addFavorite($this->fbUser, $id);
                $this->isFavorite = true;
                
                $this->set('successTitle', 'Favorite Added');
                $this->set('successMessage', "<a href=\"".FB_URL."/view/{$id}\">{$addon[0]['name']}</a> has been successfully added to your favorite add-ons list and will now appear in your <a href=\"http://www.facebook.com/profile.php?id={$this->fbUser}\">profile</a>.");
                
                $pCount = $this->Preview->findCount("addon_id = '{$id}'");
                
                // Publish feed story
                $this->_publishStory($id, $addon[0]['name'], $addon[0]['summary'], $pCount);
                
                // Update profile FBML
                $this->_updateProfileFBML();
            }
        }
        // Remove a favorite
        elseif ($action == 'remove') {
            if (!$this->FacebookFavorite->isFavorite($this->fbUser, $id)) {
                $this->set('errorTitle', 'Could not remove favorite');
                $this->set('errorMessage', "<a href=\"".FB_URL."/view/{$id}\">{$addon[0]['name']}</a> is not on your favorites list.");
            }
            else {
                $this->FacebookFavorite->removeFavorite($this->fbUser, $id);
                $this->isFavorite = false;
                
                $this->set('successTitle', 'Favorite Removed');
                $this->set('successMessage', "<a href=\"".FB_URL."/view/{$id}\">{$addon[0]['name']}</a> has been successfully removed from your favorite add-ons list. (<a href=\"".FB_URL."/favorite/add/{$id}\">Undo?</a>)");
                
                // Update profile FBML
                $this->_updateProfileFBML();
            }
        }
        
        if ($return_to == 'favorites') {
            $this->favorites('mine');
        }
        elseif ($return_to = 'addon') {
            $this->view($id);
        }
        
        return;
    }

   /**
    * View Favorites
    */
    function favorites($action = 'mine', $id = 0, $view = 'all') {
        $this->Amo->clean($id);
        
        // If added is set, user imported add-ons to favorites
        if (!empty($_GET['added'])) {
            $this->Amo->clean($_GET['added']);
            $added = explode(',', $_GET['added']);

            $this->set('successTitle', 'Favorite'.(count($added) != 1 ? 's' : '').' Imported');

            $addonNames = $this->Addon->findAll("Addon.id IN ({$_GET['added']})", array('Addon.id', 'Addon.name', 'Addon.summary'), null, null, null, -1);
            
            // If there's only one imported favorite, show the normal feed story
            if (count($added) == 1) {
              $this->set('successMessage', "<a href=\"".FB_URL."/view/{$addonNames[0]['Addon']['id']}\">{$addonNames[0]['Translation']['name']['string']}</a> has been successfully added to your favorite add-ons list and will now appear in your <a href=\"http://www.facebook.com/profile.php?id={$this->fbUser}\">profile</a>.");
                
              // Publish feed story
              $pCount = $this->Preview->findCount("addon_id = '{$addonNames[0]['Addon']['id']}'");
              $this->_publishStory($addonNames[0]['Addon']['id'], $addonNames[0]['Translation']['name']['string'], $addonNames[0]['Translation']['summary']['string'], $pCount);
            }
            // If there's more than one imported favorite, show a combined story
            else {
              $this->set('successMessage', count($added)." add-ons have been successfully added to your favorite add-ons list and will now appear in your <a href=\"http://www.facebook.com/profile.php?id={$this->fbUser}\">profile</a>.");
                
              // Publish feed story
              $this->_publishImportStory($addonNames);
            }

            // Update profile FBML
            $this->_updateProfileFBML();
        }
        
        $page = !empty($this->namedArgs['page']) ? $this->namedArgs['page'] : 1;
        
        $fbFriends = implode(', ', $this->facebook->api_client->friends_get());
        
        if ($action == 'addon') {
            $addon = $this->Addon->findById($id);
            $this->set('addon', $addon);
            
            if ($view == 'all') {
                $all = array();
                if ($all_q = $this->FacebookFavorite->findAllByAddon_id($id, null, 'RAND()')) {
                    foreach ($all_q as $all_r) {
                        $all[] = $all_r['FacebookFavorite']['fb_user'];
                    }
                }
                $this->set('all', $all);
            }
            
            $friends = array();
            if ($friends_q = $this->FacebookFavorite->findAll("addon_id='{$id}' AND fb_user IN ({$fbFriends})", null, 'RAND()')) {
                foreach ($friends_q as $friends_r) {
                    $friends[] = $friends_r['FacebookFavorite']['fb_user'];
                }
            }
            $this->set('friends', $friends);
            
        }
        elseif ($action == 'user') {
            if ($addons = $this->FacebookFavorite->getDetailedFavoriteList($id, $page)) {
                $this->set('addons', $addons);
            }
            $total = $this->FacebookFavorite->countFavorites($id);
            $this->set('count', array('total' => $total, 'pages' => ceil($total / RESULTS_PER_PAGE)));
            $this->set('user_id', $id);
        }
        elseif ($action == 'mine') {
            if ($addons = $this->FacebookFavorite->getFavoriteList($this->fbUser)) {
                $this->set('addons', $addons);
            }
        }
        elseif ($action == 'friends') {
            if ($addons = $this->FacebookFavorite->getFriendFavorites($fbFriends, $page)) {
                $this->set('addons', $addons);
            }
            $total = $this->FacebookFavorite->countFriendFavorites($fbFriends);
            $this->set('count', array('total' => $total, 'pages' => ceil($total / RESULTS_PER_PAGE)));
        }
        
        $this->set('current', array('page' => $page));
        $this->set('action', $action);
        $this->set('view', $view);
        $this->set('page', 'favorites');
        $this->render('favorites', 'facebook');
    }
    
   /**
    * Display outage page
    */
    function outage() {
        $this->render('outage', 'ajax');
    }
    
   /**
    * Invite friends to add the app
    */
    function invite() {
        $exclude = array();
        
        // Get friends who have added application to exclude
        $friends_added = $this->facebook->api_client->fql_query("SELECT uid FROM user WHERE has_added_app=1 and uid IN (SELECT uid2 FROM friend WHERE uid1 = {$this->fbUser})");
        if (!empty($friends_added)) {
            foreach ($friends_added as $friend) {
                $exclude[] = $friend['uid'];
            }
            $exclude = implode(',', $exclude);
        }
        
        $this->publish('exclude', $exclude);
        
        $this->render('invite', 'facebook');    
    }
    
   /**
    * Share a specific add-on with a specific friend
    */
    function share($action = 'form') {
        if ($action == 'submit') {
            if (!empty($_POST['addon_id'])) {
                $addon_id = $_POST['addon_id'];
                $this->Amo->clean($addon_id);
                
                $addon = $this->Addon->findById($addon_id);
                $addon['previewCount'] = $this->Preview->findCount("Preview.addon_id='{$addon['Addon']['id']}'");
                $this->set('addon', $addon);
            }
            else
                $this->set('error', 'No add-on was selected.');
        }
        elseif ($action == 'form') {
            if ($favorites = $this->FacebookFavorite->getFavoriteList($this->fbUser)) {
                $this->set('favorites', $favorites);
            }
        }
        
        $this->set('action', $action);
        $this->render('share', 'ajax');
    }
    
   /**
    * FAQ
    */
    function faq() {
        $this->set('page', 'faq');
        $this->render('faq', 'facebook');
    }
    
   /**
    * Update Notes
    */
    function updatenotes() {
        $this->set('page', 'updatenotes');
        $this->render('updatenotes', 'facebook');
    }
    
   /**
    * Wallpaper
    */
    function wallpaper() {
        $this->set('page', 'wallpaper');
        $this->render('wallpaper', 'facebook');
    }
    
    /* Utility Functions */
    
   /**
    * Update FBML for profile box
    */
    function _updateProfileFBML() {
        $fbml = '';
        
        if ($addons = $this->FacebookFavorite->getFavoriteList($this->fbUser, 'RAND()')) {
            $fbml = '<fb:subtitle seeallurl="'.FB_URL.'/favorites/user/'.$this->fbUser.'?ref=pbsa">';
            $fbml .= '<fb:wide>Displaying '.(count($addons) >= 5 ? '5' : count($addons)).'</fb:wide>';
            $fbml .= '<fb:narrow>'.(count($addons) >= 3 ? '3' : count($addons)).'</fb:narrow>';
            $fbml .= ' of <a href="'.FB_URL.'/favorites/user/'.$this->fbUser.'?ref=pbc">'.count($addons).' add-on'.(count($addons) != 1 ? 's' : '').'</a>.</fb:subtitle>';
            $fbml .= '<table><tr>';
            foreach ($addons as $k => $addon) {
                if ($k > 4) continue;
                if ($k == 3)
                    $fbml .= '<fb:wide>';
                $fbml .= '<td width="60" style="text-align: center;"><a href="'.FB_URL.'/view/'.$addon['addons']['id'].'?ref=pba">';
                $fbml .= '<img src="'.FB_IMAGE_SITE.$this->Image->getAddonIconURL($addon['addons']['id']).'" border=0></a>';
                $fbml .= '<br/><a href="'.FB_URL.'/view/'.$addon['addons']['id'].'?ref=pba">'.$addon['translations']['name'].'</a></td>';
                if ($k == 4)
                    $fbml .= '</fb:wide>';
            }
            $fbml .= '</tr></table>';
        }
        
        try {
            $this->facebook->api_client->profile_setFBML($fbml);
        } catch(Exception $e) {
            error_log("_updateProfileFBML [{$this->fbUser}]: ".$e->getMessage());
        }
    }
    
   /**
    * Publish newsfeed story
    */
    function _publishStory($addon_id, $addon_name, $addon_summary, $pCount) {
        try {
            //$feed_title = 'rocked <fb:pronoun uid="'.$this->fbUser.'" useyou="false" possessive="true"/> Firefox with <a href="'.FB_URL.'/view/'.$addon_id.'?ref=nf">'.$addon_name.'</a>.';
            $feed_body = $this->_trimSummary($addon_summary, 200);
            if ($pCount > 0)
                $preview = FB_IMAGE_SITE.'/images/addon_preview/'.$addon_id.'/1';
            else
                $preview = FB_IMAGE_SITE.'/img/facebook/no-preview.png';
            $preview_link = FB_URL.'/view/'.$addon_id.'?ref=nfi';
            
            $title_template = '{actor} rocked <fb:if-multiple-actors>their<fb:else><fb:pronoun uid="{uid}" possessive="true" useyou="false" /></fb:else></fb:if-multiple-actors> Firefox with <a href="{addon_url}">{addon_name}</a>.';
            $title_data = '{"addon_name": "'.$addon_name.'", "addon_url": "'.FB_URL.'/view/'.$addon_id.'?ref=nf", "uid": "'.$this->fbUser.'"}';
            $body_template = '{description}';
            $body_data = '{"description": "'.$feed_body.'"}';
            
            //$this->facebook->api_client->feed_publishActionOfUser($feed_title, $feed_body, $preview, $preview_link);
            //$this->facebook->api_client->feed_publishStoryToUser($feed_title, $feed_body, $preview, $preview_link);
            $this->facebook->api_client->feed_publishTemplatizedAction(
                                    $this->fbUser, $title_template, $title_data,
                                    $body_template, $body_data, null,
                                    $preview, $preview_link);
        } catch(Exception $e) {
            error_log("_publishStory [{$this->fbUser}]: ".$e->getMessage());
        }
    }
    
   /**
    * Publish import story
    */
    function _publishImportStory($addon_names) {
        try {
            //$feed_title = 'rocked <fb:pronoun uid="'.$this->fbUser.'" useyou="false" possessive="true"/> Firefox with <a href="'.FB_URL.'/favorites/user/'.$this->fbUser.'?ref=nf">'.count($addon_names).' add-ons</a>.';
            //$feed_body = '<fb:name uid="'.$this->fbUser.'" firstnameonly="true" linked="false" shownetwork="false" useyou="false" /> now recommends ';
            $addons = array();
            
            foreach ($addon_names as $addon_name) {
                $addons[] = '<a href="'.FB_URL.'/view/'.$addon_name['Addon']['id'].'">'.$addon_name['Translation']['name']['string'].'</a>';
            }
            //$feed_body .= implode(', ', $addons);
            
            $title_template = '{actor} rocked <fb:if-multiple-actors>their<fb:else><fb:pronoun uid="{uid}" possessive="true" useyou="false" /></fb:else></fb:if-multiple-actors> Firefox with <a href="{url}">{count} add-ons</a>.';
            $title_data = '{"count": "'.count($addon_names).'", "url": "'.FB_URL.'/favorites/user/'.$this->fbUser.'?ref=nf", "uid": "'.$this->fbUser.'"}';
            $body_template = '{actor} now recommends {addons}';
            $body_data = '{"addons": "'.addslashes(implode(', ', $addons)).'"}';
            
            //$this->facebook->api_client->feed_publishActionOfUser($feed_title, $feed_body);
            //$this->facebook->api_client->feed_publishStoryToUser($feed_title, $feed_body);
            $this->facebook->api_client->feed_publishTemplatizedAction(
                                    $this->fbUser, $title_template, $title_data,
                                    $body_template, $body_data, '');
        } catch(Exception $e) {
            error_log("_publishImportStory [{$this->fbUser}]: ".$e->getMessage());
        }
    }
    
   /**
    * Check if user is in the Mozilla or Facebook network
    */
    function _inMozillaOrFacebookNetworks() {
        $fql = $this->facebook->api_client->fql_query("SELECT affiliations, first_name FROM user WHERE uid='{$this->fbUser}'");
        if (!empty($fql[0]['affiliations'])) {
            foreach ($fql[0]['affiliations'] as $network) {
                if ($network['name'] == 'Mozilla' || $network['name'] == 'Facebook') {
                    return true;
                }
            }
        }
        
        return false;
    }

   /**
    * Checks if a user is in the admin group
    */
    function _inAdminGroup() {
        $members = $this->facebook->api_client->groups_getMembers(2651677376);
        
        if (is_array($members) && in_array($this->fbUser, $members['members'])) {
            return true;
        }
        
        return false;
    }
    
   /**
    * Checks if a user is using a supported browser
    */
    function _usingSupportedBrowser() {
        $allowedBrowser = false;
        $allowedUserAgents = array('Firefox', 'Minefield', 'BonEcho', 'GranParadiso', 'Shiretoko');
        foreach ($allowedUserAgents as $allowedUserAgent) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], $allowedUserAgent) !== false) {
                $allowedBrowser = true;
                break;
            }
        }
        
        $disallowedUserAgents = array('Flock');
        foreach ($disallowedUserAgents as $disallowedUserAgent) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], $disallowedUserAgent) !== false) {
                $allowedBrowser = false;
                break;
            }
        }
        
        return $allowedBrowser;
    }
    
   /**
    * Trims a string to a specified number of characters and attempts to break
    * without cutting off words.
    * @param string $summary
    * @param int $maxLength
    */
    function _trimSummary($summary, $maxLength = 250) {
        // Only do work if we need to
        if (strlen($summary) > $maxLength) {
            $summary = substr($summary, 0, $maxLength);
            
            // If we broke on punctuation or a space, we're done
            $allowedBreaks = array('.', ' ', '!', '?', ')');
            if (!in_array($summary[$maxLength - 1], $allowedBreaks)) {
                // If we didn't, we need to break on the last space and add an elipsis
                $lastSpace = strrpos($summary, ' ');
                $summary = substr($summary, 0, $lastSpace).'...';
            }
        }
        
        return $summary;
    }
    
   /**
    * Admin tools
    */
    function admin($action = '') {
        if (!$this->_inAdminGroup()) {
            die('Access denied.');
        }
        
        if ($action == 'flushCache') {
            $urls = array(
                          SITE_URL.'/css/facebook.php',
                          SITE_URL.'/css/facebook.php?'.date('Ymd'),
                          SITE_URL.'/facebook/outage'
                        );
            try {
                foreach ($urls as $url) {
                    $this->facebook->api_client->fbml_refreshRefUrl($url);
                }
            } catch(Exception $e) {
                die("Error: {$e}");
            }
            $this->set('urls', $urls);
        }
        elseif ($action == 'activeUsers') {
        
            $this->set('activeUsers', array(
                            'last_1d'   => $this->FacebookUser->getUsersInInterval('lastactivity', '1 DAY'),
                            'last_1h'   => $this->FacebookUser->getUsersInInterval('lastactivity', '1 HOUR'),
                            'last_10m'  => $this->FacebookUser->getUsersInInterval('lastactivity', '10 MINUTE'),
                            'last_5m'   => $this->FacebookUser->getUsersInInterval('lastactivity', '5 MINUTE'),
                            'last_1m'   => $this->FacebookUser->getUsersInInterval('lastactivity', '1 MINUTE'),
                            'last_30s'  => $this->FacebookUser->getUsersInInterval('lastactivity', '30 SECOND'),
                            'last_1s'   => $this->FacebookUser->getUsersInInterval('lastactivity', '1 SECOND'),
                            'add_1d'    => $this->FacebookUser->getUsersInInterval('added', '1 DAY'),
                            'remove_1d' => $this->FacebookUser->getUsersInInterval('removed', '1 DAY'),
                            'total'     => $this->FacebookUser->getUsersTotal(),
                            'ever'      => $this->FacebookUser->getUsersEver()
                            ));
        }
        
        $this->set('action', $action);
        
        $this->render('admin', 'facebook');
    }
}

?>
