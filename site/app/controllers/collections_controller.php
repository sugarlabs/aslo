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
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Ryan Doherty <rdoherty@mozilla.com>
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
class CollectionsController extends AppController
{
    var $name = 'Collections';
    var $beforeFilter = array('checkCSRF', 'getNamedArgs', '_checkSandbox', 'checkAdvancedSearch');
    var $uses = array('Addon', 'AddonCollection', 'Addonlog', 'Application', 'Collection', 'File',
        'Platform', 'Preview', 'Translation', 'Version');
    var $components = array('Amo', 'CollectionsListing', 'Developers', 'Error', 'Helper', 'Httplib', 'Image', 'Pagination', 'Session');
    var $actionHelpers = array('Html');
    var $helpers = array('Html', 'Link', 'Listing', 'Time', 'Localization', 'Pagination', 'Number', 'Form', 'InstallButton');
    var $exceptionCSRF = array("/collections/install");
    var $namedArgs = true;

    var $securityLevel = 'low';

    function beforeFilter() {
        $this->layout='mozilla';
        $this->publish('collapse_categories', true);
        $this->publish('collectionSearch', true);
        $this->pageTitle = 'Collections' . " :: " . sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);
        $this->publish('jsAdd', array('jquery.autocomplete.pack.js'), false);
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        // disable query caching so devcp changes are visible immediately
        if ($this->Session->check('User')) {
            foreach ($this->uses as $_model) {
                $this->$_model->caching = false;
            }
        }

        $this->publish('jsAdd', array('amo2009/collections', 'jquery-ui/jqModal.js'));
    }

    function index() {
        /* TODO: preserve get params */
        $this->redirect('/collections/editors_picks');
    }

    function _listing($collections, $pagination_options=array()) {
        if (!empty($collections[0]['Collection']['id'])) {
            $ids = array();
            foreach ($collections as $c) $ids[] = $c['Collection']['id'];
        } else {
            $ids = $collections;
        }

        $collections = $this->CollectionsListing->fetchPage($ids, array('users'),
                                                            $pagination_options);
        list($sort_opts, $sortby) = $this->CollectionsListing->sorting();

        $this->publish('sort_opts', $sort_opts);
        $this->publish('sortby', $sortby);
        $this->publish('collections', $collections);
        $this->set('tabs', $this->_collectionTabs());

        if ($this->Session->check('User')) {
            $user = $this->Session->read('User');
            $this->publish('user', $this->User->getUser($user['id'], array('votes')));
        } else {
            $this->publish('user', null);
        }

        // if a collection was just deleted, show success message
        $this->publish('collection_deleted', $this->Session->delete('collection_deleted'), false);

        if (empty($this->viewVars['breadcrumbs'])) {
            $this->publish('breadcrumbs', array(
                sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ));
        }

        $this->render('listing');
    }

    function _collectionTabs() {
        $tabs = array(
            array('href' => 'editors_picks',
                  'text' => ___('Editor\'s Picks')),
            array('href' => 'popular',
                  'text' => ___('Popular', 'collections_index_li_popular')),
        );

        if ($this->Session->check('User')) {
            $tabs = array_merge($tabs, array(
                array('href' => 'mine',
                      'text' => ___('My Collections')),
                array('href' => 'favorites',
                      'text' => ___('My Favorites')),
            ));
        }
        return $tabs;
    }


    function editors_picks() {
        $this->set('selected', 'editors_picks');
        $conditions = array('Collection.collection_type' => Collection::COLLECTION_TYPE_EDITORSPICK,
                            'Collection.listed' => 1);
        $this->Collection->unbindFully();
        $collections = $this->Collection->findAll($conditions, 'Collection.id');
        $this->_listing($collections);
    }

    function popular() {
        $this->set('selected', 'popular');
        $this->Collection->unbindFully();
        $collections = $this->Collection->findAll(array('Collection.listed' => 1),
                                                  'Collection.id');
        $pagination = array('sortBy' => 'subscribers DESC');
        $this->_listing($collections, $pagination);
    }

    function mine() {
        $this->Amo->checkLoggedIn();

        $this->set('selected', 'mine');
        $this->set('filler', sprintf(___('<p>You haven\'t created any collections yet. Collections are easy to create and fill with your favorite add-ons.  <a href=\'%1$s\'>Try it out</a>!</p>'),
                                     $this->Html->url('/collections/add')));

        $user = $this->Session->read('User');
        $collections = $this->Collection->execute(
            "SELECT Collection.id
            FROM collections AS Collection JOIN collections_users
            ON Collection.id = collections_users.collection_id
            WHERE collections_users.user_id = " . $user['id']);
        $this->_listing($collections);
    }

    function favorites() {
        $this->Amo->checkLoggedIn();
        $this->set('selected', 'favorites');
        $this->set('filler', sprintf(___('<p><strong>You don\'t have any favorite collections yet.</strong></p> <p>Collections you mark as favorites can be quickly accessed from this page, and will appear in the <a href=\'%1$s\'>Add-on Collector</a> if you\'ve installed it.</p>'),
                                     $this->Html->url('/pages/collector')));

        $user = $this->Session->read('User');
        $collections = $this->Collection->execute(
            "SELECT Collection.id
             FROM collections AS Collection JOIN collection_subscriptions
             ON Collection.id = collection_subscriptions.collection_id
             WHERE collection_subscriptions.user_id = " . $user['id']);
        $this->_listing($collections);
    }

    function addon($id) {
        if (!$id || !is_numeric($id)) {
            $this->flash(sprintf(___('Missing argument: %s'), 'addon_id'), '/', 3);
            return;
        }
        $addon = $this->Addon->getAddon($id);
        $collections = $this->AddonCollection->getPopularCollectionsForAddon(
            $id, null, APP_ID);

        $this->set('hide_listing_header', true);
        $this->publish('list_header', sprintf(___('Collections containing %1$s'),
            $addon['Translation']['name']['string']));
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Collections', 'collections_breadcrumb') => '/collections',
            $addon['Translation']['name']['string'] => "/addon/{$addon['Addon']['id']}"
        ));

        $this->_listing($collections);
    }

    /**
     * Creates a collection if POSTed to, otherwise shows a collection creation form
     */
    function add() {
        $this->Amo->checkLoggedIn(); // must be logged in

        // view setup
        $this->layout = 'amo2009'; // TODO: remove this when the entire controller is amo2009-based
        $this->set('bodyclass', 'inverse collections-page');
        $this->publish('jsAdd', array('jquery.autocomplete.pack.js'));
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Collections', 'collections_breadcrumb') => '/collections'
        ));

        // pick initially selected add-ons both from the URI and possible form choices
        $initial_addons = $_init_ids = $_form_ids = array();
        if (!empty($this->params['url']['addons'])) {
            $_init_ids = explode(',', $this->params['url']['addons']);
        }
        if (!empty($this->params['form']['addons'])) {
            $_form_ids = $this->params['form']['addons'];
        }
        $_addon_ids = array_unique(array_merge($_init_ids, $_form_ids));
        unset($_init_ids, $_form_ids);

        if (!empty($_addon_ids)) {
            foreach ($_addon_ids as &$_addon_id) {
                if (!is_numeric($_addon_id)) continue;
                $_addon = $this->Addon->getAddon($_addon_id);
                if (empty($_addon)) continue;
                $initial_addons[] = array(
                    'id' => $_addon['Addon']['id'],
                    'name' => $_addon['Translation']['name']['string'],
                    'preview' => $this->Image->getAddonIconURL($_addon_id)
                );
            }
            unset($_addon_id);
        }
        $this->publish('initial_addons', $initial_addons);

        if (isset($this->data['Collection'])) {
            // clean up whitespace
            $this->data['Collection']['name'] = trim($this->data['Collection']['name']);
            $this->data['Collection']['description'] = trim($this->data['Collection']['description']);

            $user = $this->Session->read('User');
            $this->data['Collection']['user_id'] = $user['id'];
            $this->data['Collection']['application_id'] = APP_ID; // defaults to current app
            $this->data['Collection']['defaultlocale'] = LANG; // defaults to current lang

            $data = $this->data['Collection'];
            $this->Amo->clean($data, false);
            if ($this->Collection->save($data)) {
                $collectionid = $this->Collection->id; // new collection id
                $_coll = $this->Collection->findById($collectionid, array('Collection.uuid'));

                $this->Collection->addUser($this->Collection->id, $user['id'], COLLECTION_ROLE_ADMIN);

                if (!empty($this->params['form']['addons'])) {
                    // add-ons preselected
                    $this->Amo->clean($this->params['form']['addons']);
                    foreach ($this->params['form']['addons'] as &$addon) {
                        $this->Collection->addAddonToCollection($collectionid, $user['id'], $addon);

                        // only log when adding to a public collection
                        if (!empty($this->data['Collection']['listed'])) {
                            $this->Addonlog->logAddToCollection($this, $addon, $collectionid, $this->data['Collection']['name']);
                        }
                    }
                }

                $this->Session->write('collection_created', $collectionid);
                $this->redirect("/collections/view/{$_coll['Collection']['uuid']}");
                return;
            } else {
                $this->set('form_errors', true);
            }
        }
    }

    /**
     * Non-JS only: Adds an add-on to a collection, then redirects to display page
     */
    function addtocollection() {
        $this->Amo->checkLoggedIn(); // must be logged in

        if (empty($this->data['addon_id']) || empty($this->data['collection_uuid'])) {
            $this->flash(sprintf(___('Missing argument: %s'), 'addon_id or collection_id'), '/', 3);
            return;
        }

        // create new collection if requested
        if ($this->data['collection_uuid'] == 'new') {
            if (is_array($this->data['addon_id']))
                $addonids = implode(',', $this->data['addon_id']);
            else
                $addonids = $this->data['addon_id'];
            $addonids = urlencode($addonids);
            $this->redirect("/collections/add/?addons={$addonids}");
            return;
        }

        $this->Amo->clean($this->data);
        $addon_id = $this->data['addon_id'];
        $collection_id = $this->Collection->getIdForUUID($this->data['collection_uuid']);
        if (!is_numeric($addon_id) || !$collection_id) {
            $this->flash(sprintf(___('Missing argument: %s'), 'addon_id or collection_id'), '/', 3);
            return;
        }
        $user = $this->Session->read('User');
        $added = $this->Collection->addAddonToCollection($collection_id, $user['id'], $addon_id);

        if ($added) {
            // only log when adding to a public collection
            $coll = $this->Collection->findById($id, array('name', 'listed'), null, -1);
            if ($coll['Collection']['listed']) {
                $this->Addonlog->logAddToCollection($this, $addon_id, $collection_id, $coll['Translation']['name']['string']);
            }
        }

        // go to add-on's display page and display success message
        $this->Session->write('collection_addon_added', $this->data['collection_uuid']);
        $this->redirect("/addon/{$addon_id}");
        return;
    }

    /** 
     * Share Collection
     */
    function share($uuid = null) {
        if (!$uuid) {
            $this->flash(sprintf(___('Missing argument: %s'), 'collection_id'), '/', 3);
            return;
        }

        $this->Collection->unbindFully();
        $id = $this->Collection->getIdForUuidOrNickname($uuid);
        if (!$id) {
            $this->flash(___('Collection not found!'), '/', 3);
            return;
        }
        $_conditions['Collection.id'] = $id;
        $collection = $this->Collection->find($_conditions, null, null, 1);

        $service = @$this->Amo->link_sharing_services[$_GET['service']];

        // Panic if either the addon or the sharing service is not found.
        if (empty($collection) || empty($service)) {
            $this->flash(___('Collection not found!'), '/', 3);
            return;
        }

        $this->publish('collection_uuid', $uuid);

        // Build a suitable link title based on the addon name, version, and
        // the site title.
        $title =
            sprintf(
                ___('%s'),
                $collection['Translation']['name']['string']
            ) .
            ' :: '.
            sprintf(
                ___('Add-ons for %1$s'),
                APP_PRETTYNAME
            );

        $this->publish('share_title', $title);

        // Come up with description text from the summary
        $this->publish('description', $addon['Translation']['description']['string']);
        $this->publish('service_url', $service['url']);

        $this->layout = 'ajax';
    }
    
    function _getSortedAddons($collection_id) {
        $sort_options = array(
            'date-added' => ___('Date Added'),
            'name' => ___('Name', 'collections_detail_sort_name'),
            'popularity' => ___('Popularity', 'collections_detail_sort_popularity')
        );

        // Fetch #1.  What's in the collection?
        $addonIds = $this->Addon->getAddonsFromCollection($collection_id);

        // Set up pagination
        $this->Pagination->total = count($addonIds);
        $this->Pagination->show = 15;
        list($order, $limit, $page) = $this->Pagination->init();

        // Default sorting is by date added to Collection.
        if (isset($_GET['sortby']) && array_key_exists($_GET['sortby'], $sort_options)) {
            $sortby = $_GET['sortby'];
        } else {
            $sortby = 'date-added';
        }

        // Fetch #2.  Sort and fetch paged addon ids.
        if ($sortby == 'date-added') {
            $field = 'addons_collections.added DESC';
            $extra = 'JOIN addons_collections ON Addon.id = addons_collections.addon_id';
            $pagedIds = $this->Addon->sorted($addonIds, $field, $limit, $page, $extra);
        } else {
            if ($sortby == 'popularity') {
                $field = 'weeklydownloads DESC';
            } else if ($sortby == 'name') {
                $field = 'name';
            }
            $pagedIds = $this->Addon->sorted($addonIds, $field, $limit, $page);
        }

        // Fetch #3!  Pull useful addon data this time.
        $addons = $this->Addon->getAddonList($pagedIds,array(
            'all_categories', 'authors', 'compatible_apps', 'files', 'latest_version',
            'list_details'));

        foreach($addons as &$addon) {
            $a = &$addon['Addon'];
            // Get publish details for each add-on
            $publishDetails = $this->Addon->getCollectionPublishDetails($a['id'], $collection_id);
            $a = array_merge($a, $publishDetails);
        }

        return array($addons, $sort_options, $sortby);
    }

    function view($uuid = NULL) {
        if (!$uuid) {
            $this->flash(sprintf(___('Missing argument: %s'), 'collection_id'), '/', 3);
            return;
        }

        $id = $this->Collection->getIdForUuidOrNickname($uuid);
        if (!$id) {
            $this->flash(___('Collection not found!'), '/', 3);
            return;
        }
        $_conditions['Collection.id'] = $id;

        $collection = $this->Collection->getCollection($id, array('users'));

        list($addons, $sort_options, $sortby) = $this->_getSortedAddons($collection['Collection']['id']);

        $this->publish('addons', $addons);
        $this->publish('collection', $collection);
        $this->publish('sort_options', $sort_options);
        $this->publish('sortby', $sortby);

        $this->set('link_sharing_services', $this->Amo->link_sharing_services);

        $this->pageTitle = $collection['Translation']['name']['string'] . " :: " . sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);

        // User-specific stuff.
        if ($this->Session->check('User')) {
            $user = $this->Session->read('User');
            $is_subscribed = $this->Collection->isSubscribed($id, $user['id']);
            if ($is_subscribed) {
                $action = $this->Collection->getUnsubscribeUrl();
            } else {
                $action = $this->Collection->getSubscribeUrl();
            }
            $this->publish('user', $this->User->getUser($user['id'], array('votes')));
            $this->publish('is_subscribed', $is_subscribed);
            $this->publish('subscribe_action', $action);
            $this->_getUserRights($user, $id);
        } else {
            // Use 0 as a dummy user.
            $this->publish('user', null);
            $this->_getUserRights(array('id' => 0), $id);
        }

        // was the collection just created? show success message
        $collection_created = $this->Session->read('collection_created');
        if ($collection_created == $id) $this->Session->delete('collection_created');
        $this->publish('collection_created', ($collection_created == $id));

        $rss_url = sprintf('/collection/%s?format=rss', $collection['Collection']['uuid']);
        $this->publish('rssAdd', array(
            array($rss_url,
                  sprintf(___('%s Collection'),
                          $collection['Translation']['name']['string']))
        ));

        if (isset($_GET['format']) && $_GET['format'] == 'rss') {
            $this->publish('atom_self', $rss_url);
            $this->publish('rss_title', $collection['Translation']['name']['string']);
            $this->publish('rss_description', $collection['Translation']['description']['string']);
            return $this->render('rss/collection', 'rss');
        }

        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Collections', 'collections_breadcrumb') => '/collections'
        ));

        $this->render('detail');
    }

    function vote($uuid, $direction) {
        $this->Amo->checkLoggedIn();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$uuid) {
                $this->flash(sprintf(___('Missing argument: %s'), 'collection_id'), '/', 3);
                return;
            }

            $collection_id = $this->Collection->getIdForUuidOrNickname($uuid);
            if (!$collection_id) {
                $this->flash(___('Collection not found!'), '/', 3);
                return;
            }

            $directions = array('up' => 1, 'down' => -1, 'cancel' => 0);

            if (!array_key_exists($direction, $directions)) {
                $this->flash(___('Access Denied'), '/collection/'.$uuid, 3);
            }

            $user = $this->Session->read('User');
            $vote = $directions[$direction];

            if ($vote == 0) {
                $sql = "DELETE FROM collections_votes
                        WHERE user_id={$user['id']}
                          AND collection_id={$collection_id}";
            } else {
                $sql = "REPLACE INTO collections_votes (collection_id, user_id, vote, created)
                        VALUE ({$collection_id}, {$user['id']}, {$vote}, NOW())";
            }

            $result = $this->Collection->execute($sql);
            $this->Collection->purge($collection_id);
            $this->User->purge($user['id']);

            if ($this->isAjax()) {
                // We need to flush everything now so the user sees their
                // updated vote.
                $this->Config->Cache->flushMarkedLists();

                // We don't really need users, but let's take advantage of caching elsewhere!
                $collection = $this->Collection->getCollection($collection_id, array('users'));
                $c = $collection['Collection'];
                print $this->renderElement('amo2009/collections/barometer', array(
                    'up' => $c['upvotes'], 'down' => $c['downvotes'],
                    'collection' => $collection,
                    'user' => $this->User->getUser($user['id'], array('votes')),
                ));
                return;
            }
        }

        // If it wasn't a post, or was a post but not ajax, we jump.
        return $this->redirect('/collection/'.$uuid, 302);
    }

    /**
     * Subscribes user to the collection
     */
    function subscribe($ajax = null) {
        $this->_subscribe_unsubscribe($ajax);
    }

    /**
     * Unsubscribe a user from a collection
     */
    function unsubscribe($ajax = null) {
        $this->_subscribe_unsubscribe($ajax);
    }

    /**
     * Combined function for subscribing/unsubscribing. Action is determined by
     * $this->action.
     * @access private
     * @param string $ajax undefined or 'ajax' for no-frills rendering
     * @return bool render()ed successfully?
     */
    function _subscribe_unsubscribe($ajax = null) {
        $this->Amo->checkLoggedIn(); // must be logged in

        $this->publish('is_ajax', ($ajax == 'ajax'));

        if (!in_array($this->action, array('subscribe', 'unsubscribe'))) {
            $this->flash(___('Access Denied'), '/', 3);
            return;
        }

        if (empty($this->params['form']['uuid'])) { // uuid needs to be POSTed
            $this->flash(sprintf(___('Missing argument: %s'), 'uuid'), '/', 3);
            return;
        }
        $uuid = $this->params['form']['uuid'];
        $id = $this->Collection->getIdForUuidOrNickname($uuid);
        if (!$id || !is_numeric($id)) {
            $this->set('success', false);
            return $this->render('subscribe');
        }

        $user = $this->Session->read('User');
        if ($this->action == 'subscribe') {
            $result = $this->Collection->subscribe($id, $user['id']);
        } else {
            $result = $this->Collection->unsubscribe($id, $user['id']);
        }
        $collection = $this->Collection->findById($id, array('id', 'name'), null, -1);
        $this->set('success', $result);

        $this->publish('collection', $collection);

        // set up view and render result
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Collections', 'collections_breadcrumb') => '/collections'
        ));
        return $this->render('subscribe');
    }

    /**
     * Edit collection
     */
    function edit($uuid = null) {
        $this->Amo->checkLoggedIn(); // must be logged in

        // disable query caching so changes are visible immediately
        $this->Collection->caching = false;

        if (empty($uuid)) {
            $this->flash(sprintf(___('Missing argument: %s'), 'collection_id'), '/', 3);
            return;
        }
        $id = $this->Collection->getIdForUuidOrNickname($uuid);
        if (!$id) {
            $this->flash(___('Collection not found!'), '/', 3);
            return;
        }

        // access rights
        $user = $this->Session->read('User');
        $this->publish('user', $user);
        $rights = $this->_getUserRights($user, $id);
        if (!($rights['writable'] || $rights['isadmin'])) {
            $this->flash(___('Access Denied'), '/', 3);
            return;
        }

        if (!empty($this->data)) {
            // Delete collection?
            if (isset($this->data['action']) && $this->data['action'] == 'delete-coll') {
                if (!$rights['atleast_manager']) {
                    $this->flash(___('Access Denied'), '/', 3);
                    return;
                }
                $this->Collection->delete($id);
                $this->Session->write('collection_deleted', true);
                $this->redirect("/collections");
                return;
            }

            // regular save
            $success = $this->_saveCollectionEdit($id, $rights);

            if ($success) {
                // grab updated collection nickname/uuid
                $updated = $this->Collection->findById($id, array('nickname', 'uuid'));
                $this->publish('collection_saved', true, false);
                if (empty($updated['Collection']['nickname']))
                    $this->publish('collection_url', "/collection/{$updated['Collection']['uuid']}");
                else
                    $this->publish('collection_url', "/collection/{$updated['Collection']['nickname']}");
            } else {
                $this->set('form_errors', true);
            }
        }

        // get collection data for display (and publish to view)
        $this->_getCollectionDataForView($id, $user);

        // view setup
        $this->layout = 'amo2009'; // TODO: remove this when the entire controller is amo2009-based
        $this->set('bodyclass', 'inverse collections-page');
        $this->publish('jsAdd', array('jquery-ui/ui.core.min', 'jquery-ui/ui.tabs.min',
            'jquery.autocomplete.pack.js'));
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Collections', 'collections_breadcrumb') => '/collections'
        ));
    }

    /**
     * get collection data for collection edit page (and publish it to view)
     * @param int $id collection ID
     * @return void
     * @access private
     */
    function _getCollectionDataForView($id, $user) {
        $this->Collection->unbindModel(array('hasAndBelongsToMany'=>array('Addon')));
        $collection = $this->Collection->findById($id);
        $this->data['Collection'] = $collection;

        // translations
        $translations = $this->Collection->getAllTranslations($id);
        $this->publish('translations', $translations);

        // addons
        $addons = $this->AddonCollection->getAddonsFromCollection($id);
        $this->publish('addons', $addons);
        $addons_noscript = array();
        foreach ($addons as &$addon) {
            $addons_noscript[$addon['AddonCollection']['addon_id']] = $addon['Addon']['Translation']['name']['string'];
        }
        $this->publish('addons_noscript', $addons_noscript);

        // collection icon
        $this->publish('iconurl', $this->Image->getCollectionIconURL($id), false);

        // prepare applications
        global $app_shortnames, $app_prettynames;
        $appoptions = array();
        foreach ($app_shortnames as $sn => &$no) {
            $appoptions[$no] = $app_prettynames[$sn];
        }
        $this->publish('appoptions', $appoptions, false);

        // prepare collection types
        $this->publish('collection_types', array(
            Collection::COLLECTION_TYPE_NORMAL => ___('Normal'),
            Collection::COLLECTION_TYPE_AUTOPUBLISHER => ___('Auto-publisher'),
            Collection::COLLECTION_TYPE_EDITORSPICK => ___('Editor\'s Pick')
        ), false);

        // get existing publishers and managers
        $publishers = $this->Collection->getUsers($id,
            array(COLLECTION_ROLE_PUBLISHER), array($user['id']));
        $publishers_noscript = $managers_noscript = array();
        foreach ($publishers as &$p) {
            $publishers_noscript[$p['User']['id']] = $p['User']['email'];
        }
        $managers = $this->Collection->getUsers($id,
            array(COLLECTION_ROLE_ADMIN), array($user['id']));
        foreach ($managers as &$m) {
            $managers_noscript[$m['User']['id']] = $m['User']['email'];
        }
        $this->publish('publishers', $publishers);
        $this->publish('publishers_noscript', $publishers_noscript);
        $this->data['Publishers']['p_onlyme'] = (int)empty($publishers);
        $this->publish('managers', $managers);
        $this->publish('managers_noscript', $managers_noscript);
        $this->data['Managers']['m_onlyme'] = (int)empty($managers);
    }

    /**
     * save collection data from edit page
     *
     * @param int $id Collection ID
     * @param array $rights user rights array from _getUserRights()
     * @return bool successfully saved?
     * @access private
     */
    function _saveCollectionEdit($id, $rights) {
        $disallowed_fields = array('uuid', 'access', 'subscribers',
            'created', 'modified', 'downloads');
        if (!$rights['isadmin']) $allowed_fields[] = 'collection_type';
        $collectiondata = $this->Amo->filterFields($this->data['Collection'],
            array(), $disallowed_fields);
        $this->Collection->id = $id;

        list($localizedFields, $unlocalizedFields) = $this->Collection->splitLocalizedFields($collectiondata);
        // clean up whitespace
        foreach ($localizedFields as $field => &$translations) {
            foreach ($translations as $lang => &$l_string) {
                $l_string = trim($l_string);
                $this->params['form']['data']['Collection'][$field][$lang] = $l_string;
            }
        }

        $this->Amo->clean($localizedFields, false);
        $valid_translations = $this->Collection->validateTranslations($localizedFields);
        if ($valid_translations) {
            $this->Collection->saveTranslations($id, $this->params['form']['data']['Collection'], $localizedFields);
        }

        // handle icon removal/upload
        if (!empty($this->data['Icon']['delete'])) {
            $unlocalizedFields['icontype'] = '';
            $unlocalizedFields['icondata'] = null;
        } elseif (!empty($_FILES['icon']['name'])) {
            $iconData = $this->Developers->validateIcon($_FILES['icon']);
            if (is_array($iconData)) {
                $unlocalizedFields = array_merge($unlocalizedFields, $iconData);
            }
        }

        // field post-processing
        if (!isset($unlocalizedFields['listed'])) $unlocalizedFields['listed'] = 0;
        if (!empty($unlocalizedFields['nickname']))
            $unlocalizedFields['nickname'] = preg_replace(INVALID_COLLECTION_NICKNAME_CHARS,
                '_', mb_strtolower(trim($unlocalizedFields['nickname'])));

        if ($success = $this->Collection->save($unlocalizedFields)) {
            // some info about this collection for logging later on
            $coll = $this->Collection->findById($id, array('name', 'listed'), null, -1);

            // save noscript data
            if ($rights['atleast_manager']) {
                // save users
                $this->_saveCollectionEditUserData($id, 'Publishers');
                $this->_saveCollectionEditUserData($id, 'Managers');
            } // at least manager

            // add-ons
            // remove old add-ons
            if (!empty($this->data['Addons']['delete'])) {
                foreach ($this->data['Addons']['delete'] as &$_aid) {
                    $ok = $this->AddonCollection->deleteByAddonIdAndCollectionId($_aid, $id, ($rights['atleast_manager'] ? null : $user['id']));

                    // only log when removing from a public collection
                    if ($ok && $coll['Collection']['listed']) {
                        $this->Addonlog->logRemoveFromCollection($this, $_aid, $id, $coll['Translation']['name']['string']);
                    }
                }
            }
            // add new add-ons
            if (!empty($this->params['form']['q'])) {
                $_aids = explode(',', $this->params['form']['q']);
                foreach ($_aids as &$_aid) {
                    $_aid = trim($_aid);
                    if (!empty($_aid)) {
                        $_addon = $this->Addon->getAddon($_aid);
                        if (!empty($_addon) && in_array($_addon['Addon']['status'], $valid_status)) {
                            $ok = $this->Collection->addAddonToCollection($id, $user['id'], $_aid);

                            // only log when adding to a public collection
                            if ($ok && $coll['Collection']['listed']) {
                                $this->Addonlog->logAddToCollection($this, $_aid, $id, $coll['Translation']['name']['string']);
                            }
                        }
                    }
                }
            }
        }
        return $success;
    }

    /**
     * save user data (managers/publishers) for collection edit
     * @param int $id collection ID
     * @param string $type "Publishers" or "Managers"
     * @return void
     * @access private
     */
    function _saveCollectionEditUserData($id, $type) {
        switch ($type) {
        case 'Publishers':
            $onlyme_fieldid = 'p_onlyme';
            $role = COLLECTION_ROLE_PUBLISHER;
            break;
        case 'Managers':
            $onlyme_fieldid = 'm_onlyme';
            $role = COLLECTION_ROLE_ADMIN;
            break;
        default:
            return;
        }

        if (isset($this->data[$type][$onlyme_fieldid]) && $this->data[$type][$onlyme_fieldid]) {
            // remove all existing users with this role, except ourselves
            $user = $this->Session->read('User');
            $this->Collection->removeAllUsersByRoleExcept($id, $role, $user['id']);
        } else {
            // remove old users
            if (!empty($this->data[$type]['delete'])) {
                foreach ($this->data[$type]['delete'] as &$_pid) {
                    $this->Collection->removeUser($id, $_pid);
                }
            }
            // save new users
            if (!empty($this->data[$type]['new'])) {
                $_emails = explode(',', $this->data[$type]['new']);
                foreach ($_emails as &$_em) {
                    $_em = trim($_em);
                    if (empty($_em)) continue;
                    $_uid = $this->User->findByEmail($_em, array('id'));
                    if (empty($_uid)) continue;
                    $this->Collection->addUser($id, $_uid['User']['id'], $role);
                }
            }
            // don't publish them back to the view
            $this->data[$type]['new'] = '';
        }
    }

    /**
     * get user rights for a specific collection
     * @param array $user array from user model
     * @param int $collection_id
     * @return array of booleans ('writable', 'isadmin', 'atleast_manager', 'role')
     * @access private
     */
    function _getUserRights($user, $collection_id) {
        $can_write = $this->Collection->isWritableByUser($collection_id, $user['id']);
        $isadmin = $this->SimpleAcl->actionAllowed('Admin', 'EditAnyCollection', $user);
        $role = $this->Collection->getUserRole($collection_id, $user['id']);
        $writable = ($isadmin || $can_write);
        $atleast_manager = ($isadmin || $role == COLLECTION_ROLE_ADMIN);

        $this->publish('writable', $writable, false);
        $this->publish('isadmin', $isadmin, false);
        $this->publish('atleast_manager', $atleast_manager, false);
        $this->publish('role', $role, false);

        return array(
            'writable'          => $writable,
            'isadmin'           => $isadmin,
            'atleast_manager'   => $atleast_manager,
            'role'              => $role
        );
    }


    /*** Fashion Your Firefox ***/

    /**
     * Special "interactive collections" page for first-time users
     */
    function interactive() {
        // this is Firefox only, for now.
        if (APP_ID != APP_FIREFOX) {
            $this->redirect('/');
            exit();
        }

        // XXX: for accessibility (bug 462411), we use a hand-bundled accordion
        // + core jquery UI file. Once jquery UI is updated to post-1.6.2rc2,
        // the regular jquery UI accordion should be used (cf. jquery ui bug
        // 3553, http://ui.jquery.com/bugs/ticket/3553)
        $this->set('jsAdd', array('jquery-ui/jq-ui-162rc2-accordion-bundle-a11y.min.js', 'jquery-ui/jqModal.js'));
        $this->set('cssAdd', array('collection-style'));

        $addonIds = $this->Addon->getCategorizedAddonsFromCollection(1); // special collection ID
        $addons = array();
        foreach ($addonIds as $catid => $cataddons) {
            $addons[$catid] = $this->Addon->getAddonList($cataddons, array('files', 'latest_version', 'list_details'));
        }
        $this->publish('addons', $addons);

        // prepare view, then render
        $this->publish('suppressHeader', true, false);
        $this->publish('suppressLanguageSelector', true, false);
        $this->publish('suppressCredits', true, false);
        $this->pageTitle = 'Fashion Your Firefox';
    }

    /**
     * installation dialog for collections
     * @param string $method 'html' (with layout) or 'ajax' (without)
     */
    function install($method = 'html') {
        $addons = array();
        if (!empty($_POST['addon'])) {
            $addons = $this->Addon->getAddonList($_POST['addon'], array(
            'compatible_apps', 'files', 'latest_version', 'list_details'));

            // XXX: ugly hack allowing signed add-ons to be installed separately
            // due to bug 462108 and 453545. The DB doesn't know which ones are
            // signed or not so we need to hardcode them here.
            $signed_addons = array(
                5579, // Cooliris
                3615, // Delicious
                8384, // Digg
                5202, // Ebay
                2410, // Foxmarks
                1512 // LinkedIn
            );
            foreach ($addons as &$addon) {
                $addon['Addon']['signed_xpi'] = in_array($addon['Addon']['id'], $signed_addons);
            }
        }
        $this->publish('addons', $addons);

        // fetch all platforms
        $this->Platform->unbindFully();
        $platforms_all = $this->Platform->findAll();
        $platforms = array();
        foreach ($platforms_all as $pf) {
            $platforms[$pf['Platform']['id']] = $pf['Translation']['name']['string'];
        }
        $this->publish('platforms', $platforms);

        // prepare and display view
        $this->pageTitle = 'Collections' . " :: " . sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);
        $is_ajax = ($method=='ajax');
        $this->publish('is_ajax', $is_ajax, false);
        $this->publish('suppressHeader', true, false);
        $this->publish('suppressLanguageSelector', true, false);
        if ($is_ajax) {
            $this->layout = null;
            $this->render('install', false);
        } else {
            $this->render();
        }
    }

    /**
     * "Success!" screen
     */
    function success() {
        if (isset($_GET['i'])) {
            $installed = explode(',', $_GET['i']);
            $installed = array_filter($installed, 'is_numeric');
            if (!empty($installed)) {
                $addons = $this->Addon->getAddonList($installed);
            } else {
                $addons = array();
            }
        } else {
            $addons = array();
        }
        $this->publish('addons', $addons);

        $this->set('cssAdd', array('collection-style'));
        $this->publish('suppressHeader', true, false);
        $this->publish('suppressLanguageSelector', true, false);
        $this->publish('suppressCredits', true, false);
        $this->pageTitle = 'Fashion Your Firefox';
    }

    /*** END Fashion Your Firefox ***/


    /*** AJAX Handlers ***/

    /**
     * AJAX action for looking up add-ons to add to a collection
     */
    function addonLookup() {
        global $valid_status;

        // expect a GET variable containing the search string
        $name = (!empty($_GET['q']) ? $_GET['q'] : '');
        $this->Amo->clean($name);

        // search conditions
        $conditions = array(
            'Addon.status' => $valid_status,
            'Addon.inactive' => 0
        );
        if (mb_strlen($name) >= 4) { // fuzzy matching on long names only
            $conditions['Translation.name'] = "LIKE %{$name}%";
        } else {
            $conditions['Translation.name'] = $name;
        }

        $this->Addon->unbindFully();
        $addons = $this->Addon->findAll($conditions,
            array('Addon.id', 'Addon.name'), 'Translation.name');
        if (!empty($addons)) {
            foreach ($addons as &$_addon) {
                // add icons
                $_addon['Addon']['iconpath'] = $this->Image->getAddonIconURL($_addon['Addon']['id']);
            }
        } else {
            $addons = false;
        }

        $this->publish('addons', $addons);
        $this->render('ajax/addon_lookup', 'ajax');
    }

    /**
     * central JSON dispatcher for AJAX requests
     */
    function json($action, $additional = '') {
        switch ($action) {
            case 'nickname':
                $json = $this->_checkNickname();
                break;

            case 'user':
                $json = $this->_handleUser($additional);
                break;

            case 'addon':
                $json = $this->_handleAddon($additional);
                break;

            default: $json = array(); break;
        }

        $this->publish('json', $json, false);
        $this->render('json', 'ajax');
    }

    /**
     * AJAX: check if collection nickname is already used
     */
    function _checkNickname() {
        $this->Amo->checkLoggedIn(); // must be logged in
        if (empty($this->params['url']['nickname'])) {
            return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'nickname'));
        }
        $nickname = preg_replace(INVALID_COLLECTION_NICKNAME_CHARS, '_',
            mb_strtolower(trim($this->params['url']['nickname'])));
        if ($nickname != mb_strtolower($this->params['url']['nickname']))
            return array(
                'error' => 1,
                'error_message' => ___('Your nickname contained invalid characters and was corrected. Please try again.'),
                'nickname' => $nickname
            );

        $taken = $this->Collection->isNicknameTaken($this->params['url']['nickname']);
        return array(
            'error' => 0,
            'nickname' => $nickname,
            'taken' => (int)$taken
        );
    }

    /**
     * AJAX: Add / remove user to/from this collection's roles
     */
    function _handleUser($action) {
        if (empty($action)) return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'action'));
        if (empty($this->params['form']['collection_id'])) return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'collection_id'));

        $this->Amo->checkLoggedIn(); // must be logged in

        $collection_id = $this->params['form']['collection_id'];
        $role = @$this->params['form']['role'];
        $email = @$this->params['form']['email'];
        $user_id = @$this->params['form']['user_id'];

        $user = $this->Session->read('User');
        $rights = $this->_getUserRights($user, $collection_id);
        if (!$rights['atleast_manager']) return $this->Error->getJSONforError(___('Access Denied'));

        switch ($action) {
        case 'add':
            if (empty($email)) return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'email'));
            $roles = array('publishers' => COLLECTION_ROLE_PUBLISHER, 'managers' => COLLECTION_ROLE_ADMIN);
            if (empty($role)) return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'role'));

            $newuser = $this->User->findByEmail($email, array('id'));
            if (!empty($newuser)) {
                if ($this->Collection->getUserRole($collection_id, $newuser['User']['id']) === false) {
                    $this->Collection->addUser($collection_id, $newuser['User']['id'], $roles[$role]);
                    return array('id' => $newuser['User']['id'], 'email' => $email);
                } else {
                    return $this->Error->getJSONforError(___('Users can only have one role at a time.  Please remove the user from any existing roles before continuing.'));
                }
            } else {
                return $this->Error->getJSONforError(___('User not found!'));
            }
            break;

        case 'del':
            if (empty($user_id))
                return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'user_id'));
            if ($this->Collection->removeUser($collection_id, $user_id) !== false) {
                return array('id' => $user_id);
            } else {
                return $this->Error->getJSONforError(___('User not found!'));
            }
            break;

        default:
            return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'action'));
        }
    }

    /**
     * AJAX: Add / remove add-on to/from this collection
     * @param string action one of add, del, savecomment
     * @param int collection_id (optional)
     * @param string collection_uuid (optional)
     *     Either collection_id or collection_uuid must be specified, but not both.
     * @param int addon_id
     */
    function _handleAddon($action) {
        global $valid_status;

        if (empty($action)) return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'action'));
        if (empty($this->params['form']['addon_id']) || !is_numeric($this->params['form']['addon_id'])) return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'addon_id'));
        if (!empty($this->params['form']['collection_id']) && is_numeric($this->params['form']['collection_id'])) {
            $collection_id = $this->params['form']['collection_id'];
        } elseif (!empty($this->params['form']['collection_uuid']) &&
            ($collection_id = $this->Collection->getIdForUuidOrNickname($this->params['form']['collection_uuid'])) > 0) {
            // no-op
        } else {
            return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'collection_id'));
        }

        $this->Amo->checkLoggedIn(); // must be logged in

        $addon_id = $this->params['form']['addon_id'];

        $user = $this->Session->read('User');
        $rights = $this->_getUserRights($user, $collection_id);

        if (!($rights['writable'] || $rights['isadmin'])) return $this->Error->getJSONforError(___('Access Denied'));

        // collection details used for logging
        $coll = $this->Collection->findById($collection_id, array('name', 'listed'), null, -1);

        switch ($action) {
        case 'add':
            if ($this->AddonCollection->isAddonInCollection($addon_id, $collection_id))
                return $this->Error->getJSONforError(___('Add-on already exists!'));
            $addon = $this->Addon->getAddon($addon_id);
            if (empty($addon) || !in_array($addon['Addon']['status'], $valid_status))
                return $this->Error->getJSONforError(___('Add-on not found!'));
            if (false !== $this->Collection->addAddonToCollection($collection_id, $user['id'], $addon_id)) {
                // only log when adding to a public collection
                if ($coll['Collection']['listed']) {
                    $this->Addonlog->logAddToCollection($this, $addon_id, $collection_id, $coll['Translation']['name']['string']);
                }

                return array(
                    'id' => $addon_id,
                    'name' => $addon['Translation']['name']['string'],
                    'iconURL' => $this->Image->getAddonIconURL($addon_id),
                    'date' => strftime(___('%B %e, %Y'), mktime()),
                    'publisher' => $this->Html->linkUserFromModel($user)
                );
            } else {
                return $this->Error->getJSONforError(___('Error saving add-on!'));
            }
            break;

        case 'del':
            if (!$rights['isadmin'] && !$rights['atleast_manager']) {
                // publisher's own add-on?
                $res = $this->AddonCollection->deleteByAddonIdAndCollectionId($addon_id, $collection_id, $user['id']);
            } else {
                $res = $this->AddonCollection->deleteByAddonIdAndCollectionId($addon_id, $collection_id);
            }
            if ($res) {
                // only log when removing from a public collection
                if ($coll['Collection']['listed']) {
                    $this->Addonlog->logRemoveFromCollection($this, $addon_id, $collection_id, $coll['Translation']['name']['string']);
                }

                return array('id' => $addon_id);
            } else {
                return $this->Error->getJSONforError(___('Error deleting add-on!'));
            }
            break;

        case 'savecomment':
            if (!isset($this->params['form']['comment'])) return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'comment'));
            $comment = strip_tags(trim($this->params['form']['comment']));

            $addon = $this->AddonCollection->find(array('addon_id'=>$addon_id, 'collection_id'=>$collection_id), array('user_id'));
            if (empty($addon)) return $this->Error->getJSONforError(___('Add-on not found!'));
            if (!$rights['isadmin'] && !$rights['atleast_manager']) {
                // publisher's own add-on?
                if ($addon['AddonCollection']['user_id'] != $user['id']) return $this->Error->getJSONforError(___('Access Denied'));
            }
            if (!$this->AddonCollection->setComment($collection_id, $addon_id, $comment)) {
                return $this->Error->getJSONforError(___('Error saving comment!'));
            } else {
                return array(
                    'addon_id' => $addon_id,
                    'comment'  => $comment
                );
            }
            break;

        default:
            return $this->Error->getJSONforError(sprintf(___('Missing argument: %s'), 'action'));
        }
    }
}
