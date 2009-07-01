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
 *   Justin Scott <fligtar@gmail.com>
 *   Wil Clouser <wclouser@mozilla.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *   Mike Morgan <morgamic@mozilla.com>
 *   Chris Pollett <cpollett@gmail.com>
 *   Ryan Doherty <rdoherty@mozilla.com>
 *   Laura Thomson <lthomson@mozilla.com>
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
class AddonsController extends AppController
{
    var $name = 'Addons';

    var $beforeFilter = array('checkCSRF', 'getNamedArgs', '_checkSandbox', 'checkAdvancedSearch');
    var $uses = array('Addon', 'AddonCollection', 'AddonCategory', 'Addontype', 'Application', 'CollectionFeatures', 'Feature', 'File', 'GlobalStat', 'License', 'Platform', 'Preview', 'Category', 'Translation', 'Review', 'Version', 'Collection', 'CollectionPromo','Tag');
    var $components = array('Amo', 'Image', 'Pagination', 'Paypal', 'Session', 'Userfunc');
    var $helpers = array('Html', 'Link', 'Time', 'Localization', 'Ajax', 'Number', 'Pagination');
    var $namedArgs = true;

    var $securityLevel = 'low';

    var $link_sharing_services;

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        if (array_key_exists('addons-author-addons-select', $this->params['url']) && ctype_digit($this->params['url']['addons-author-addons-select'])) {
            redirectWithNewLocaleAndExit(array('addon',$this->params['url']['addons-author-addons-select']));
        }

        // Set of available link sharing services with associated labels and
        // submission URL templates.
        // @TODO: Move this to a model class when share counts are enabled in DB
        $this->link_sharing_services = array(

            // see: http://digg.com/tools/integrate#3
            'digg' => array(
                'label' => ___('addons_share_label_digg', 'Digg this!'),
                'url' => 'http://digg.com/submit?url={URL}&title={TITLE}&bodytext={DESCRIPTION}&media=news&topic=tech_news'
            ),

            // see: http://www.facebook.com/share_options.php
            'facebook' => array(
                'label' => ___('addons_share_label_facebook', 'Post to Facebook'),
                'url' => 'http://www.facebook.com/share.php?u={URL}&t={TITLE}'
            ),

            // see: http://delicious.com/help/savebuttons
            'delicious' => array(
                'label' => ___('addons_share_label_delicious', 'Add to Delicious'),
                'url' => 'http://delicious.com/save?url={URL}&title={TITLE}&notes={DESCRIPTION}'
            ),

            // see: http://www.myspace.com/posttomyspace
            'myspace' => array(
                'label' => ___('addons_share_label_myspace', 'Post to MySpace'),
                'url' => 'http://www.myspace.com/index.cfm?fuseaction=postto&t={TITLE}&c={DESCRIPTION}&u={URL}&l=1'
            ),

            // see: http://friendfeed.com/embed/link
            'friendfeed' => array(
                'label' => ___('addons_share_label_friendfeed', 'Share on FriendFeed'),
                'url' => 'http://friendfeed.com/?url={URL}&title={TITLE}'
            )

        );

    }

    /**
     * Redirect from amo to paypal, saving statistics first.
     *
     * We look for some GET params:
     *   type: only 'suggested' is recognized; the donation will be
     *         the add-on's suggested amount
     *   source: string tracking which page the donation is coming from
     */
    function contribute($addon_id) {
        if ($this->Config->getValue('paypal_disabled')) {
            return $this->flash(___('error_paypal_disabled'), '/', 3);
        }

        $this->Addon->unbindFully();
        $addon = $this->Addon->findById($addon_id);
        if (empty($addon)) {
            return $this->flash(_('error_addon_notfound'), '/', 3);
        }

        if (!$this->Addon->acceptContributions($addon)) {
            return $this->flash(___('error_addon_no_contributions'), '/', 3);
        }

        $type = isset($_GET['type']) ? $_GET['type'] : null;
        $source = isset($_GET['source']) ? $_GET['source'] : null;

        $a = $addon['Addon'];
        $amount = ($type === 'suggested' && !empty($a['suggested_amount']))
                    ? $a['suggested_amount'] : null;

        $db =& ConnectionManager::getDataSource($this->Addon->useDbConfig);
        $sql = "INSERT INTO stats_contributions
                (addon_id, amount, source, annoying, created)
                VALUES
                ({$db->value($addon_id)}, {$db->value($amount)},
                 {$db->value($source)}, {$a['annoying']},
                  NOW())";
        $this->Addon->execute($sql);

        $this->Paypal->contribute($a['paypal_id'],
                                  sprintf(___('addon_contribute_item'),
                                          $addon['Translation']['name']['string']),
                                  SITE_URL . $this->url('/addon/'.$a['id']),
                                  $amount);
    }

    function developers($addon_id) {
        foreach ($this->Addon->getAuthors($addon_id) as $a) {
            $authors[] = $this->User->getUser($a['User']['id'], array('addons',));
        }
        foreach ($authors as $a) {
            foreach ($a['Addon'] as $addon) {
                $other_addons[$addon['Addon']['id']] = $addon;
            }
        }
        $this->set('authors', $authors);
        $this->set('num_authors', count($authors));
        $this->set('multiple', count($authors) > 1);
        $this->set('addon', $this->Addon->getAddon($addon_id, array('contrib_details')));
        $this->set('other_addons', $other_addons);
        $this->publish('breadcrumbs', array(
            sprintf(___('addons_home_pagetitle'), APP_PRETTYNAME) => '/',
            $addon['Translation']['name']['string'] => '/addon/'.$addon['Addon']['id'],
        ));
    }

    /**
     * Share an addon with a link sharing service.
     * @param int $id the id of the addon
     */
    function share($id = null) {
        global $valid_status;

        $_conditions = array(
            'Addon.id' => $id,
            'Addon.inactive' => 0,
            'Addon.addontype_id' => array(
                ADDON_EXTENSION, ADDON_THEME, ADDON_DICT,
                ADDON_SEARCH, ADDON_LPAPP, ADDON_PLUGIN
            ),
            'Addon.status' => $valid_status
        );
        $addon = $this->Addon->find($_conditions, null , null , 1);

        $service = @$this->link_sharing_services[$_GET['service']];

        // Panic if either the addon or the sharing service is not found.
        if (empty($addon) || empty($service)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }

        $this->publish('addon_id', $id);

        // Build a suitable link title based on the addon name, version, and
        // the site title.
        $title =
            sprintf(
                _('addons_display_pagetitle'),
                $addon['Translation']['name']['string'].' '.
                $addon['Version'][0]['version']
            ) .
            ' :: '.
            sprintf(
                _('addons_home_pagetitle'),
                APP_PRETTYNAME
            );

        $this->publish('share_title', $title);

        // Come up with description text from the summary
        $this->publish('description', $addon['Translation']['summary']['string']);

        $this->publish('service_url', $service['url']);

        $this->layout = 'ajax';
    }

    /**
    * Display an addon
    * @param int $id the id of the addon
    */
    function display($id = null) {
        global $valid_status;
        $this->publish('jsAdd', array('jquery-ui/ui.lightbox','jquery.autocomplete.pack.js','tags.js'));        
        $this->publish('cssAdd', array('jquery-lightbox','autocomplete'));
        $this->layout = 'amo2009';
        $this->set('bodyclass', 'inverse');

        $this->forceShadowDb();
        $this->Amo->clean($id);

        $this->publish('bigHeader', true);
        $this->publish('bigHeaderText', sprintf(_('addons_home_header_details'), APP_PRETTYNAME));

        $loggedIn = $this->Session->check('User')? true : false;
        $this->set('loggedIn', $loggedIn);
        if ($loggedIn) { $user=$this->Session->read('User'); } 

        if (!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'addon_id'), '/', 3);
            return;
        }

        $_conditions = array(
            'Addon.id' => $id,
            'Addon.inactive' => 0,
            'Addon.addontype_id' => array(ADDON_EXTENSION, ADDON_THEME, ADDON_DICT, ADDON_SEARCH, ADDON_LPAPP, ADDON_PLUGIN),
            'Addon.status' => $valid_status
            );

        $this->Addon->bindOnly('User', 'Version', 'Tag', 'AddonCategory');
        $addon_data = $this->Addon->find($_conditions, null , null , 1);

        if (empty($addon_data)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }

        // sandbox check
        if ($addon_data['Addon']['status'] != STATUS_PUBLIC) {
            $this->publish('addonStatus', STATUS_SANDBOX);
            $this->status = $valid_status;
        }

        $_latest_version = $this->Version->getVersionByAddonId($addon_data['Addon']['id'], $this->status);
        if ($_latest_version > 0) {
            $version = $this->Version->findAllById($_latest_version, null, "Version.created DESC", 0);
            $addon_data['Version'] = $version;
            $this->publish('hasversion', true);
            $compat_apps = $this->Version->getCompatibleApps($_latest_version);
            $this->publish('compatible_apps', array_slice($compat_apps, 0, 1));
            $addon_data['compatible_apps'] = $compat_apps;
        } else {
            $this->publish('hasversion', false);
        }

        // if the latest version is incompatible with the current app, redirect
        // to a the first valid, compatible version.
        if (!empty($compat_apps)) {
            $is_compatible = false;
            foreach ($compat_apps as $app) {
                if ($app['Application']['application_id'] == APP_ID) {
                    $is_compatible = true;
                    break;
                }
            }
            if (!$is_compatible) {
                global $app_shortnames;
                $targetapp = array_search($compat_apps[0]['Application']['application_id'], $app_shortnames);
                $this->redirect("/{$targetapp}/addon/{$id}", null, true, false);
                return;
            }
        }

        // TODO: Look up the current share totals for this addon.
        // $share_counts = $this->ShareCount->getTotalCountsForAddon(
        //     $addon_data['Addon']['id']
        // );
        // $this->set('link_sharing_counts', $share_counts);

        // Not using publish() here because this will all be app constants,
        // localized strings with placeholders, or counts from the DB.
        $this->set('link_sharing_services', $this->link_sharing_services);

        if($loggedIn) {
          $isauthor = $this->Amo->checkOwnership($id, $addon_data['Addon'], true);
          $this->publish('isAuthor', $isauthor);
        }
        else {
          $this->publish('isAuthor', false);
        }

        // get other addons for the author(s)
        foreach ($addon_data['User'] as $_user)
            $_userids[] = $_user['id'];
        $_addonids = $this->Addon->getAddonsForAuthors($_userids);

        if (!empty($_addonids)) {
            // re-fetch the addons to get the translations too
            $_addoncriteria = array(
                'Addon.id' => $_addonids,
                'Addon.inactive' => 0,
                'Addon.status' => $valid_status
            );
            $authorAddons = $this->Addon->findAll($_addoncriteria, null, 'Translation.name');
        } else {
            $authorAddons = array();
        }
        $this->publish('authorAddons',$authorAddons);

        if (in_array($addon_data['Addon']['addontype_id'], array(ADDON_PLUGIN))) {
            $this->redirect('/browse/type:' . $addon_data['Addon']['addontype_id']);
        }

        $this->publish('previews', $this->Preview->findAllByAddonId($id, array('id', 'addon_id', 'caption'), 'highlight desc'));
        $this->publish('addon', $addon_data);
        $this->publish('addonIconPath', $this->Image->getAddonIconURL($id), false);
        $this->publish('addonPreviewPath', $this->Image->getHighlightedPreviewURL($id));
        $this->pageTitle = sprintf(_('addons_display_pagetitle'), $addon_data['Translation']['name']['string']). ' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);

        // get the categories that are related to the addon, so that they have translation data
        $_related_category_ids = array();
        foreach ($addon_data['AddonCategory'] as $categoryvalue){
            $_related_category_ids[] = $categoryvalue['category_id'];
        }

        if (!empty($_related_category_ids))
            $related_categories = $this->Category->findAll("Category.id IN (".implode(',', $_related_category_ids).") AND (Category.application_id = ".APP_ID." OR Category.application_id IS NULL)");
        else
            $related_categories = array();

        $this->publish('relatedCategories', $related_categories);

        

        // Make the tag list, passing in this addon and the currently logged in user
        if (!$loggedIn) { $user = null; }
        $tags = $this->Tag->makeTagList($addon_data, $user);
        $this->publish('tags', $tags);
        $this->publish('userTags', $tags['userTags']);
        $this->publish('developerTags', $tags['developerTags']);   
        $this->publish('addon_id', $addon_data['Addon']['id']);
             

        // The platforms section is necessary because of CakePHP bug #1183 (https://trac.cakephp.org/ticket/1183).  We
        // need the translated strings in the model to offer the right platform to users.
        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('platforms', $platforms);

        // Grab the latest 3 reviews by ID, one per user.
        $review_ids = array();
        $_latest_reviews =
            $this->Review->findLatestReviewsForAddon($addon_data['Addon']['id'], 3, 1);
        foreach($_latest_reviews as $_r)
            $review_ids[] = $_r['id'];

        // Fetch the actual reviews from IDs
        if (!empty($review_ids))
            $reviews = $this->Review->getReviews($review_ids);
        else
            $reviews = array();

        $this->publish('reviews', $reviews);
        $this->publish('review_count', empty($reviews) ?  0 : $addon_data['Addon']['totalreviews']);

        // does user have a review already?
        $user = $this->Session->read('User');
        if (!empty($user) && $_latest_version > 0) {
            $user_revcount = $this->Review->findCount("Review.user_id = {$user['id']} AND Review.version_id = {$_latest_version}");
            $this->publish('hasreview', ($user_revcount>0));
        } else {
            $this->publish('hasreview', false);
        }

        // find collections this add-on is in
        $pop_collections = array();
        $_pop_coll_ids = $this->AddonCollection->getPopularCollectionsForAddon($id, 3, APP_ID);
        if (!empty($_pop_coll_ids)) {
            $pop_collections = $this->Collection->findAllById($_pop_coll_ids,
                array('id', 'uuid', 'nickname', 'name'),
                'FIELD(Collection.id,'.implode(',', $_pop_coll_ids).')', null, null, -1);
        }
        $this->publish('pop_collections', $pop_collections);
        $collection_count = $this->AddonCollection->getCollectionCountForAddon($id, APP_ID);
        $this->publish('collection_count', $collection_count, false);

        // Fetch user's collections if logged in
        $userCollections = false;
        if (!empty($user)) {
            $userCollectionIds = $this->User->getCollections($user['id'], APP_ID, array($id));
            $userCollections = $this->Collection->findAll(array(
                'Collection.id' => $userCollectionIds,
                'Collection.collection_type' => '<> '.Collection::COLLECTION_TYPE_AUTOPUBLISHER
                ), array('id', 'name', 'uuid', 'nickname'));
            $this->publish('userCollections', $userCollections);

            // if an addon was just added to a collection, display a success message
            if (false !== ($coll_uuid = $this->Session->read('collection_addon_added'))) {
                $collection_id = $this->Collection->getIdForUUID($coll_uuid);
                $coll_addon_added = $this->Collection->findById($collection_id, null, null, -1);
                $this->Session->delete('collection_addon_added');
                $this->publish('coll_addon_added', $coll_addon_added);
            }
        }

        // Collapse categories menu
        $this->publish('collapse_categories', true);
        $this->publish('breadcrumbs', array(
            sprintf(___('addons_home_pagetitle'), APP_PRETTYNAME) => '/',
        ));
    }

    /**
     * Display the home page for the entire site.
     */
    function home() {
        $this->forceShadowDb();

        $this->layout='amo2009';
        $this->pageTitle = sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);

        $this->publish('stats_downloaded',
            $this->GlobalStat->getNamedCount('addon_total_downloads'));
        $this->publish('stats_inuse',
            $this->GlobalStat->getNamedCount('addon_total_updatepings'));

        $this->publish('teaser_collection_promos', $this->_findTeaserCollections());
        $this->publish('teaser_collections_categories', $this->CollectionFeatures->getTitlesAndTaglinesById());
        $this->publish('popular_collections', $this->_findPopularCollections());
        $this->publish('promoted_collections', $this->_findCollectionPromoList(true));

        list($featured_type, $featured_addons) = $this->_getFeatured();
        $this->publish('featured_type',   $featured_type);
        $this->publish('featured_addons', $featured_addons);

        // The platforms section is necessary because of CakePHP bug #1183
        // (https://trac.cakephp.org/ticket/1183).  We need the translated
        // strings in the model to offer the right platform to users.
        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('platforms', $platforms);

        $this->publish('baseurl', $this->base);
        $this->publish('bigHeader', true);
        $this->publish('bigHeaderText', sprintf(_('addons_home_header_details'), APP_PRETTYNAME));

        // add rss links to global feeds
        $this->publish('rssAdd', array(
            array('/browse/type:1/cat:all/format:rss?sort=newest', _('rss_newestaddons')),
            array('/browse/type:1/cat:all/format:rss?sort=updated', ___('rss_updatedaddons', 'Updated Add-ons')),
            array('/browse/type:1/cat:all/format:rss?sort=popular', ___('rss_popularaddons', 'Popular Add-ons')),
            array('/recommended/format:rss', _('rss_featuredaddons')),
        ));
    }

    /* Used to populate the homepage addons by xhr. */
    function ajaxy() {
        list($featured_type, $featured_addons) = $this->_getFeatured();
        $this->publish('featured_type',   $featured_type);
        $this->publish('featured_addons', $featured_addons);
        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('platforms', $platforms);
    }

    function _getFeatured() {
        global $app_listedtypes;

        $associations = array(
            'single_category', 'all_categories', 'authors', 'compatible_apps', 'files',
            'latest_version', 'list_details'
        );
        $list_num = 5;

        $featured_type = isset($_GET['featured']) ?
            $_GET['featured'] : 'recommended';
        switch ($featured_type) {
            case 'popular':
                $featured_addon_ids = $this->Addon->getAddonsFromCategory(
                    STATUS_PUBLIC, $app_listedtypes[APP_ID], 'all', 'popular',
                    'DESC', $list_num, 1, '', false
                );
                break;
            case 'added':
                $featured_addon_ids = $this->Addon->getAddonsFromCategory(
                    STATUS_PUBLIC, $app_listedtypes[APP_ID], 'all', 'newest',
                    'DESC', $list_num, 1, '', false
                );
                break;
            case 'updated':
                $featured_addon_ids = $this->Addon->getAddonsFromCategory(
                    STATUS_PUBLIC, $app_listedtypes[APP_ID], 'all', 'updated',
                    'DESC', $list_num, 1, '', false
                );
                break;
            case 'recommended':
            default:
                $featured_addon_ids = $this->Addon->getRecommendedAddons($list_num);
                break;
        }
        $featured_addons = $this->Addon->getAddonList($featured_addon_ids, $associations);

        return array($featured_type, $featured_addons);
    }

    /**
     * Assemble collections for the teaser section of the home page.
     *
     * @return array
     */
    function _findTeaserCollections() {

        if (APP_ID != APP_FIREFOX) {

            // FYF collections for teaser are only appropriate for Firefox.
            return array();

        } else {
            $promoCatList = $this->_findCollectionPromoList();
            $teaser_collections = array();

            $associations = array(
                'single_category', 'all_categories', 'authors', 'compatible_apps', 'files',
                'latest_version', 'list_details'
            );

            foreach($promoCatList as $index => $collectionId) {
                $addons = $this->Addon->getAddonsFromCollection($collectionId, 'RAND()', null, 3);
                $teaser_collections[$index] = $this->Addon->getAddonList($addons, $associations);
            }

            return $teaser_collections;
        }

    }

    /**
     * Assemble list of collections promoted per promotion category
     *
     * @return array
    **/
    function _findCollectionPromoList($bindFully = false) {
        $collectionPromos = $this->CollectionPromo->findAll();
        $promoCats = $this->CollectionFeatures->getTitlesAndTaglinesById();
        $promoCatList = array();

        //Doing this to merge collection ids into promotion categories and allow locale-specific selections to override
        foreach ($promoCats as $pid => $pc) {
            $collection = false;

            if(isset($collectionPromos[LANG][$pid])) {
                $collection = $collectionPromos[LANG][$pid];
            } elseif(isset($collectionPromos['all'][$pid])) {
                $collection = $collectionPromos['all'][$pid];
            }

            if($collection) {
                $id = array_keys($collection);
                $id = $id[0];

                if($bindFully) {
                    $promoCatList[$pid] = $this->Collection->findById($id);
                } else {
                    $promoCatList[$pid] = $id;
                }
            }
        }

        return $promoCatList;
    }





    /**
     * Fetch top 5 popular collections by subscriber count, return a
     * stripped-down data structure for use by view.
     *
     * @return array
     */
    function _findPopularCollections() {

        // Unbind and re-bind with just users and addons, then look up top 5
        // popular collections in descending order.
        $this->Collection->unbindFully();
        $this->Collection->bindModel(array(
            'hasAndBelongsToMany' => array(
                'Users' => $this->Collection->hasAndBelongsToMany_full['Users'],
                'Addon' => $this->Collection->hasAndBelongsToMany_full['Addon']
            )
        ));
        $collections = $this->Collection->findAll(array(
            'Collection.listed' => 1,
            'Collection.application_id' => APP_ID
        ), null, 'Collection.subscribers DESC', 5);

        // Reduce the results from the model to the minimal set needed
        // by the view, because escaping is expensive.
        $pop_collections = array();
        foreach ($collections as $c) {
            $authors = array();
            foreach ($c['Users'] as $u) {
                $authors[] = array(
                    'id'        => $u['id'],
                    'firstname' => $u['firstname'],
                    'lastname'  => $u['lastname'],
                    'nickname'  => $u['nickname']
                );
            }
            $pop_collections[] = array(
                'uuid'         => $c['Collection']['uuid'],
                'nickname'     => $c['Collection']['nickname'],
                'authors'      => $authors,
                'icon_url'     => $this->Image->getCollectionIconURL($c['Collection']['id']),
                'name'         => $c['Translation']['name']['string'],
                'description'  => $c['Translation']['description']['string'],
                'subscribers'  => $c['Collection']['subscribers'],
                'addons_count' => count($c['Addon'])
            );
        }

        return $pop_collections;
    }

    /**
     * Build a grid of thumbnails and names, mainly for themes.
     */
    function _browseThumbs() {
        global $valid_status;

        // Get the type of addon, defaulting to themes
        $addontype = isset($this->namedArgs['type']) ?
            $this->namedArgs['type'] : ADDON_THEME;

        // Get the addon category, defaulting to 'all'
        $category = isset($this->namedArgs['cat']) ?
            $this->namedArgs['cat'] : 'all';

        $this->Category->unbindFully();
        $this_category = $this->Category->findById($category);

        // show experimental add-ons?
        if (isset($this->params['url']['exp'])) {
            /* experimental add-ons requested */
            $show_exp = true;
            $this->Userfunc->toggleSandboxPref(true); // store preference
        } elseif (isset($this->params['url']['show'])) {
            $show_exp = false;
            $this->Userfunc->toggleSandboxPref(false); // store preference
        } elseif ($this->Session->check('User')) {
            /* read preference */
            $sessionuser = $this->Session->read('User');
            $show_exp = ($sessionuser['sandboxshown'] == 1);
        } else {
            /* default to experimental add-ons not shown */
            $show_exp = false;
        }
        $this->set('show_exp', $show_exp);
        $displaystatuses = ($show_exp ? $valid_status : array(STATUS_PUBLIC));

        // fetch a list of all subcategories
        $subcats = $this->Amo->getCategories(APP_ID, $addontype);

        // fetch counts for all categories
        $subcat_totals = $this->Addon->countAddonsInAllCategories(
            $displaystatuses, $addontype
        );
        $all_total = $this->Addon->countAddonsInCategory(
            $displaystatuses, $addontype
        );
        $subcat_totals['all'] = $all_total;

        // determine list sort order
        if (isset($this->params['url']['sort']))
            $sort_by = $this->params['url']['sort'];
        elseif (isset($this->namedArgs['sort']))
            $sort_by = $this->namedArgs['sort'];
        else
            $sort_by = '';

        $allowed_sort_by = array(
            'name', 'updated', 'newest', 'popular', 'rated'
        );
        if (!in_array($sort_by, $allowed_sort_by)) {
            $sort_by = 'updated';
        }

        switch ($sort_by) {
            case 'popular':
            case 'updated':
            case 'newest':
            case 'rated':
                $sort_dir = 'desc';
                break;
            case 'name':
            default:
                $sort_dir = 'asc';
                break;
        }

        // initialize pagination component
        $this->Pagination->total     = $subcat_totals[$category];
        $this->Pagination->sortBy    = $sort_by;
        $this->Pagination->direction = $sort_dir;
        list($_order,$_limit,$_page) = $this->Pagination->init();

        $addons = $this->Addon->getAddonsByCategory(
            null, $displaystatuses, $addontype, $category,
            $sort_by, $sort_dir, $_limit, $_page, '', true
        );

        $this->set('type',     $addontype);
        $this->set('this_category', $this_category);

        $this->publish('addons',        $addons);
        $this->publish('show_limit',    $_limit);
        $this->publish('sort_by',       $sort_by);
        $this->publish('subcats',       $subcats);
        $this->publish('all_total',     $all_total);
        $this->publish('subcat_totals', $subcat_totals);

        $format = (isset($this->namedArgs['format']) ? $this->namedArgs['format'] : 'html');

        $this->set('content_wide', true); // display 2 features next to each other

        $this->publish('collapse_categories', true);

        switch($addontype) {
            case ADDON_THEME:
                $this->pageTitle = sprintf(___('addons_browse_categories_header_theme'), $this_category['Translation']['name']['string'], APP_PRETTYNAME);
                break;
            default:
                $this->pageTitle = sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        }

        $this->publish('bigHeader', true);
        $this->publish('bigHeaderText', sprintf(_('addons_home_header_details'), APP_PRETTYNAME));

        $this->layout = 'mozilla';

        $this->render('browse_thumbs');
    }

    /**
     * Category listing and landing pages.
     */
    function browse() {
        global $app_listedtypes, $hybrid_categories;

        $this->forceShadowDb();
        if (!isset($this->namedArgs['type'])) {
            // @TODO throw a 404 error
            $this->redirect('/');
            return;
        }
        $type = $this->namedArgs['type'];

        // allow addontype pages only for applicable types
        if (!in_array($type, $app_listedtypes[APP_ID])) {
            // @TODO throw some error
            $this->redirect('/');
            break;
        }

        if (!isset($this->namedArgs['cat'])) {
            switch ($this->namedArgs['type']) {
            case ADDON_SEARCH:
                $this->_searchengines();
                break;
            /* @TODO: Do something with the plugin page; note, if the plugin pages
             * are re-enabled, we need to make sure they are added to the
             * app_listedtypes array in constants.php too.*/
            case ADDON_PLUGIN: // undeleted this -cpollett
                $this->_plugins();
                break;

            case ADDON_DICT:
                $this->_dictionaries();
                break;
            case ADDON_THEME:
                $this->_themes();
                break;
            default:
                // @TODO throw some error
                $this->redirect('/');
                break;
            }
            return;
        }

        /* redirect category hybrids to respective addontype page
         * (unless we have selected a full listing page (?sort=something)) */
        $cat = $this->namedArgs['cat'];
        if (isset($hybrid_categories[APP_ID][$cat])
            && !isset($_GET['sort']) && !isset($this->namedArgs['sort'])) {
            $this->redirect("/browse/type:{$hybrid_categories[APP_ID][$cat]}");
            return;
        }

        /* display generic cat landing page or full category listing page */
        if (isset($_GET['sort']) || isset($this->namedArgs['sort'])) {


            if ($type == ADDON_THEME && (empty($this->namedArgs['format']) || $this->namedArgs['format']!='rss')) {
                return $this->_browseThumbs();
            }

            // category listing page
            $this->_browseAddonsInCategory();
        } else {
            // category landing page
            $this->_categoryLanding();
        }
    }

    function _buildMinimalAddonDetails($addons) {
        $view_data = array();

        foreach ($addons as $addon) {
            $addon_id = $addon['Addon']['id'];

            $r = array(
                'icon_url' =>
                    $this->Image->getAddonIconURL($addon_id),
                'preview_url' =>
                    $this->Image->getHighlightedPreviewURL($addon_id),
                'version' =>
                    $addon['Version'][0]['version']
            );

            $a_fields = array(
                'id','averagerating','created','weeklydownloads',
            );
            foreach ($a_fields as $field)
                $r[$field] = $addon['Addon'][$field];

            $t_fields = array('name','summary');
            foreach ($t_fields as $field)
                $r[$field] = $addon['Translation'][$field]['string'];

            $u_fields = array('id', 'firstname', 'lastname', 'nickname');
            $r['authors'] = array();
            if (!empty($addon['User'])) foreach ($addon['User'] as $idx=>$user) {
                foreach ($u_fields as $field)
                    $r['authors'][$idx][$field] = $user[$field];
            }

            $r['latestversion'] = $addon['Version'][0]['version'];

            $view_data[] = $r;
        }

        return $view_data;
    }

    /**
     * Generic landing page for a specific add-on category
     *
     * Relevant URL format: /browse/type:1/cat:12
     *
     * @access private
     */
    function _categoryLanding() {
        global $valid_status, $app_listedtypes;

        $associations = array(
            'single_category', 'all_categories', 'authors', 'compatible_apps', 'files',
            'latest_version', 'list_details'
        );

        $valid_status = array(STATUS_PUBLIC);

        $format = $this->setLayoutForFormat();

        $addontype = $this->namedArgs['type'];
        $this->Amo->clean($addontype);
        $this->publish('type_id', $addontype);

        $category = $this->namedArgs['cat'];
        $this->Amo->clean($category);
        $this->publish('cat_id', $category);

        $this->Category->unbindFully();
        $this_category = $this->Category->findById($category);
        $this->publish('this_category', $this_category);

        // Build a minimal set of addon details for publishing to view.
        $_feat_ids = $this->AddonCategory->getRandomAddons($category, true,  6);
        if (count($_feat_ids) > 0) {
            $_order_by = 'FIELD(Addon.id, '.implode(',', $_feat_ids).')';
        } else {
            $_order_by = 'Addon.id';
        }
        $featureAddons = $this->Addon->getAddonList($_feat_ids, $associations);

        $this->publish('featured_addons', $featureAddons);

        $list_num = 10;

        $pop_addon_ids = $this->Addon->getAddonsFromCategory(
            STATUS_PUBLIC, $app_listedtypes[APP_ID], $category,
            'popular', 'DESC', $list_num, 1, '', false
        );
        $pop_addons = $this->_buildMinimalAddonDetails(
            $this->Addon->getAddonList($pop_addon_ids, $associations)
        );

        $this->publish('popular_addons', $pop_addons);

        $new_addon_ids = $this->Addon->getAddonsFromCategory(
            STATUS_PUBLIC, $app_listedtypes[APP_ID], $category, 'newest',
            'DESC', $list_num, 1, '', false
        );
        $new_addons = $this->_buildMinimalAddonDetails(
            $this->Addon->getAddonList($new_addon_ids, $associations)
        );

        $this->publish('new_addons', $new_addons);

        $upd_addon_ids = $this->Addon->getAddonsFromCategory(
            STATUS_PUBLIC, $app_listedtypes[APP_ID], $category,
            'rated', 'DESC', $list_num, 1, '', false
        );
        $upd_addons = $this->_buildMinimalAddonDetails(
            $this->Addon->getAddonList($upd_addon_ids, $associations)
        );

        $this->publish('updated_addons', $upd_addons);

        // fetch all platforms
        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('platforms', $platforms);

        $this->set('content_wide', false); // display features next to each other
        $this->publish('collapse_categories', false);

        // set layout details
        $this->publish('bigHeader', true);
        $this->publish('bigHeaderText',
            sprintf(_('addons_home_header_details'), APP_PRETTYNAME));

        $this->pageTitle = $this_category['Translation']['name']['string']. " :: " .
            sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('rssAdd', array(
            "/browse/type:{$addontype}/cat:{$category}/format:rss?sort=updated"
        ));
        $this->render('category_landing');
    }

    /**
     * Add-on listing page for a specific category
     *
     * Relevant URL format: /browse/type:1/cat:15?sort=name
     *
     * @access private
     */
    function _browseAddonsInCategory() {
        global $valid_status, $app_listedtypes;

        $addontype = $this->namedArgs['type'];
        $category = $this->namedArgs['cat'];
        $format = $this->setLayoutForFormat();

        // type:1 && cat:all shows a global add-ons list (not extensions only)
        if ($addontype == ADDON_EXTENSION && $category == 'all') {
            $this_category = array();
            $addontype = $app_listedtypes[APP_ID];
        } else {
            $this->Category->unbindFully();
            $this_category = $this->Category->findById($category);
        }

        // determine list sort order
        if (isset($this->params['url']['sort']))
            $sort_by = $this->params['url']['sort'];
        elseif (isset($this->namedArgs['sort']))
            $sort_by = $this->namedArgs['sort'];
        else
            $sort_by = '';
        $allowed_sort_by = array('name', 'updated', 'newest', 'popular', 'rated');
        if (!in_array($sort_by, $allowed_sort_by)) {
            // default sort order
            $sort_by = 'updated';
        }
        switch ($sort_by) {
        case 'popular':
        case 'updated':
        case 'newest':
        case 'rated':
            $sort_dir = 'desc';
            break;
        case 'name':
        default:
            $sort_dir = 'asc';
            break;
        }

        // show experimental add-ons?
        if (isset($this->params['url']['exp'])) {
            /* experimental add-ons requested */
            $show_exp = true;
            $this->Userfunc->toggleSandboxPref(true); // store preference
        } elseif (isset($this->params['url']['show'])) {
            /* intentionally disabled */
            // XXX: checking for "show" to be set is cheesy but the only way
            // to determine if the button was intentionally disabled or if we
            // just followed a link
            $show_exp = false;
            $this->Userfunc->toggleSandboxPref(false); // store preference
        } elseif ($this->Session->check('User')) {
            /* read preference */
            $sessionuser = $this->Session->read('User');
            $show_exp = ($sessionuser['sandboxshown'] == 1);
        } else {
            /* default to experimental add-ons not shown */
            $show_exp = false;
        }
        $this->set('show_exp', $show_exp);
        $displaystatuses = ($show_exp ? $valid_status : array(STATUS_PUBLIC));

        if ($format != 'rss') {
            // initialize pagination component
            $this->Pagination->total = $this->Addon->countAddonsInCategory(
                $displaystatuses, $addontype, $category);
            $this->Pagination->sortBy = $sort_by;
            $this->Pagination->direction = $sort_dir;
            list($_order,$_limit,$_page) = $this->Pagination->init();
        } else {
            // display the 20 most recently changed addons
            $_order = '';
            $_limit = 20;
            $_page = 1;
        }

        // get enough addons for one page.
        $addons = $this->Addon->getAddonsByCategory(null, $displaystatuses,
            $addontype, $category, $sort_by, $sort_dir, $_limit, $_page, '', true);
        if ($category!='all' && empty($this_category) || empty($addons)) {
            $this->flash(_('error_browse_no_addons'), '/browse/type:' . $addontype, 3);
            return;
        }
        $this->publish('addons', $addons);

        // get platforms (if we are not in RSS mode)
        if ($format != 'rss') {
            $this->Platform->unbindFully();
            $platforms = $this->Platform->findAll();
            $this->publish('platforms', $platforms);
        }

        // get other categories list (or all, if this is a complete list)
        $_categories = $this->Category->query("SELECT DISTINCT t.id FROM categories AS t "
            ."INNER JOIN addons_categories AS at ON (t.id = at.category_id) "
            ."WHERE ".($category == 'all'?'1':"t.id <> '{$category}'")." "
            ."AND t.addontype_id = '{$addontype}' AND t.application_id = " . APP_ID . ";"
            );
        $category_list = array();
        if (!empty($_categories)) {
            $_category_ids = array();
            foreach($_categories as $_category) $_category_ids[] = $_category['t']['id'];
            $this->Category->unbindFully();
            $category_list = $this->Category->findAllById($_category_ids);
            // sort categories by name
            $_category_names = array();
            foreach($category_list as $_category) $_category_names[] = $_category['Translation']['name']['string'];
            array_multisort($_category_names, SORT_ASC, $category_list);
        }

        // set data available to view
        $this->publish('this_category', $this_category);
        $this->set('type', $addontype);
        $this->publish('categoryList', $category_list);

        // set layout details and render view
        if ($category == 'all') {
            switch ($sort_by) {
                case 'popular': $_title = ___('browse_addons_popular'); break;
                case 'updated': $_title = ___('browse_addons_updated'); break;
                case 'newest': $_title = ___('browse_addons_newest'); break;
                case 'rated': $_title = ___('browse_addons_rated'); break;
                case 'name': $_title = ___('browse_addons_name'); break;
                default: $_title = ''; break;
            }
        } else {
            $_title = sprintf(_('addons_browse_browse_category'), $this_category['Translation']['name']['string']);
        }
        if ($format != 'rss') {
            $this->pageTitle = $_title . " :: " . sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
            $this->publish('subpagetitle', $_title);

            // preserve GET variables in RSS feed URLs
            $_rss_get = array();
            foreach($_GET as $_getkey => $_getitem)
                if ($_getkey != 'url') $_rss_get[] = urlencode($_getkey).'='.urlencode($_getitem);
            $this->publish('rssAdd', array("/browse/type:{$this->namedArgs['type']}/cat:{$category}/format:rss?".implode('&', $_rss_get)));

            $this->set('collapse_categories', true);

            $this->render('browse');
        } else {
            // RSS feed
            $this->publish('sort_by', $sort_by, false);
            $this->publish('rss_title', $_title . " :: " .  sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME));
            $this->publish('rss_description', '');
            $this->render('rss/addons');
        }
    }

    /**
     * search tools / search engines hybrid landing page
     * @access private
     */
    function _searchengines() {
        global $hybrid_categories, $valid_status;

        $valid_status = array(STATUS_PUBLIC);

        $format = (isset($this->namedArgs['format']) ? $this->namedArgs['format'] : 'html');

        // fetch the category belonging to this hybrid page
        $category = array_search(ADDON_SEARCH, $hybrid_categories[APP_ID]);
        if ($category) {
            $this->Category->unbindfully();
            $this_category = $this->Category->findById($category);
        } else {
            $this_category = null;
        }
        $this->publish('this_category', $this_category);

        // fetch a list of all subcategories
        $subcats = $this->Amo->getCategories(APP_ID, ADDON_SEARCH);
        $this->publish('subcats', $subcats);

        // make subcategory ID list to grab recommendations from
        $subcat_ids = array();
        foreach ($subcats as $subcat)
            $subcat_ids[] = $subcat['Category']['id'];
        // add hybrid category for possible other recommendations
        $subcat_ids[] = $this_category['Category']['id'];

        // fetch up to 4 recommended add-ons
        $_feat_ids = $this->AddonCategory->getRandomAddons($subcat_ids, true, 4);

        if (!empty($_feat_ids)) {
            $associations = array(
                'single_category', 'all_categories', 'authors', 'compatible_apps', 'files',
                'latest_version', 'list_details'
            );
            $featureAddons = $this->Addon->getAddonList($_feat_ids, $associations);
        } else {
            $featureAddons = array();
        }
        $this->publish('featureAddons', $featureAddons);

        // fetch all platforms
        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('platforms', $platforms);

        if ($format != 'rss') {
            $this->set('content_wide', true); // display 2 features next to each other
            $this->set('collapse_categories', true);
            $this->pageTitle = _('addons_searchengines_pagetitle').' :: '
                .sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
            $this->publish('bigHeader', true);
            $this->publish('bigHeaderText', sprintf(_('addons_home_header_details'), APP_PRETTYNAME));
            $this->layout='mozilla';
            //$this->publish('rssAdd', array("/browse/type:".ADDON_SEARCH."/format:rss"));

            $this->render('searchengines');
        } else {
            // RSS feed
            $this->publish('rss_title', _('addons_searchengines_pagetitle'));
            $this->publish('rss_description', '');
            $this->render('rss/searchengines', 'rss');
        }
    }

    /**
     * page to display plugins, which is static for now
     */
    function _plugins() {
        $this->layout = 'mozilla';
        $this->pageTitle = _('addons_plugins_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('subpagetitle', sprintf(_('addons_plugins_main_header'), APP_PRETTYNAME));
        $this->render('plugins');
        return;
    }

    /**
     * dictionaries / language tools landing page
     */
    function _dictionaries() {
        global $valid_status, $native_languages;

        $format = (isset($this->namedArgs['format']) ? $this->namedArgs['format'] : 'html');

        // get list of target locales
        $conditions = array(
            'Addon.addontype_id' => array(ADDON_DICT, ADDON_LPAPP),
            'Addon.status' => STATUS_PUBLIC,
            'Addon.inactive' => 0
        );
        $this->Addon->unbindfully();
        $target_locales_raw = $this->Addon->findAll($conditions, "DISTINCT(LOWER(`target_locale`)) as 'target_locale'");
        $target_locales = array();
        foreach ($target_locales_raw as $tloc) $target_locales[] = $tloc[0]['target_locale'];
        unset($target_locales_raw);

        // get list of dictionaries and language packs for each target locale
        $dicts = array();
        foreach($target_locales as $tloc) {
            // prepare results array for this target locale
            $dicts[$tloc] = array(
                ADDON_DICT => array(),
                ADDON_LPAPP  => array()
            );

            // get addons for this target locale
            $conditions = array(
                'LOWER(Addon.target_locale)' => $tloc,
                'Addon.addontype_id' => array(ADDON_DICT, ADDON_LPAPP),
                'Addon.status' => STATUS_PUBLIC,
                'Addon.inactive' => 0
            );
            $this->Addon->unbindfully();
            $dicts_raw = $this->Addon->findAll($conditions, null, null, null, null, 2);

            $_dict_ids = array();
            foreach ($dicts_raw as $_dict)
                $_dict_ids[] = $_dict['Addon']['id'];
            $tloc_dicts = array();
            $this->Addon->unbindFully();
            $associations = array(
                'single_category', 'all_categories', 'authors', 'compatible_apps', 'files',
                'latest_version', 'list_details'
            );
            $tloc_dicts = $this->Addon->getAddonList($_dict_ids, $associations);
            foreach ($tloc_dicts as $dict) { // add add-ons to results array
                if (empty($dict['File'])) continue;

                // purge add-ons incompatible with this app
                $compat = $this->Version->getCompatibleApps($dict['Version'][0]['id']);
                $thisapp_compat = false;
                foreach ($compat as $compat_app)
                    $thisapp_compat = $thisapp_compat || ($compat_app['Application']['application_id'] == APP_ID);
                if (!$thisapp_compat) continue;

                $dicts[$tloc][$dict['Addon']['addontype_id']][] = $dict;
            }
            unset($dict, $compat, $thisapp_compat, $compat_app);

            /* determine the effective display name for this target locale */
            $locale_parts = explode('-', strtolower(str_replace('_', '-', $tloc)));
            // normalize region part
            if (isset($locale_parts[1])) $locale_parts[1] = strtoupper($locale_parts[1]);
            $normalized_locale = implode('-', $locale_parts);

            $displayname = false;
            while (true) {
                $locale = strtolower(implode('-', $locale_parts));
                if (in_array($locale, array_map('strtolower', array_keys($native_languages)))) {
                    $_temp = array_change_key_case($native_languages, CASE_LOWER);
                    $displayname = $_temp[$locale]['English'];
                    $localname = $_temp[$locale]['native'];
                    break;
                }
                /* shorten locale code if possible, then try again */
                array_pop($locale_parts);
                if (empty($locale_parts)) break;
            }
            if (!$displayname) {
                /* no locale found, use add-on name and add locale code */
                if (!empty($dicts[$tloc][ADDON_DICT])) {
                    $_dict = $dicts[$tloc][ADDON_DICT][0];
                } elseif (!empty($dicts[$tloc][ADDON_LPAPP])) {
                    $_dict = $dicts[$tloc][ADDON_LPAPP][0];
                } else {
                    // empty locale? shouldn't happen, drop it.
                    unset($dicts[$tloc]);
                    continue;
                }
                $displayname = $_dict['Translation']['name']['string']." ({$normalized_locale})";
                $localname = '';
                unset($_dict);
            }
            /* if we had to shorten the locale, add the code to the local
             * name to disambiguate different regional dialects
             */
            if (!empty($localname) && strtolower($locale) != strtolower($normalized_locale))
                $localname .= " ({$normalized_locale})";
            /* store values in result array for view to use */
            $dicts[$tloc]['displayname'] = $displayname;
            $dicts[$tloc]['localname'] = $localname;
        }
        // sort dictionary list by effective display name
        uasort($dicts, create_function('$a,$b', 'return strcasecmp($a["displayname"],$b["displayname"]);'));
        $this->publish('dicts', $dicts);

        // fetch all platforms
        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('platforms', $platforms);

        // set layout details
        $this->pageTitle = _('langtools_header_dicts_and_langpacks') .' :: '
            . sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('bigHeader', true);
        $this->publish('bigHeaderText', sprintf(_('addons_home_header_details'), APP_PRETTYNAME));
        $this->layout = 'mozilla';
        $this->set('collapse_categories', true);

        $this->render('dictionaries');
    }

    /**
     * Themes/Interface Customizations landing page
     */
    function _themes() {
        global $valid_status;

        $valid_status = array(STATUS_PUBLIC);

        $format = (isset($this->namedArgs['format']) ? $this->namedArgs['format'] : 'html');

        // fetch a list of all subcategories
        $subcats = $this->Amo->getCategories(APP_ID, ADDON_THEME);
        $this->publish('subcats', $subcats);

        // make subcategory ID list to grab recommendations from
        $subcat_ids = array();
        foreach ($subcats as $subcat)
            $subcat_ids[] = $subcat['Category']['id'];

        $_feat_ids = $this->AddonCategory->getRandomAddons($subcat_ids, true, 4);

        if (!empty($_feat_ids)) {
            $associations = array(
                'single_category', 'all_categories', 'authors', 'compatible_apps', 'files',
                'latest_version', 'list_details'
            );
            $featureAddons = $this->Addon->getAddonList($_feat_ids, $associations);
        } else {
            $featureAddons = array();
        }
        $this->publish('featureAddons', $featureAddons);

        // fetch all platforms
        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('platforms', $platforms);

        // set layout details
        $this->set('content_wide', true); // display 2 features next to each other
        $this->set('collapse_categories', true);
        $this->pageTitle = sprintf(___('addons_browse_all_themes_title'), APP_PRETTYNAME);
        $this->publish('bigHeader', true);
        $this->publish('bigHeaderText', sprintf(_('addons_home_header_details'), APP_PRETTYNAME));
        $this->layout = 'mozilla';

        $this->render('themes_landing');
    }

    /**
     * page with recommended addons
     */
    function recommended() {

        $this->forceShadowDb();

        //override sandbox, recommended page is only public
        $this->publish('addonStatus', array(STATUS_PUBLIC));
        $this->status = array(STATUS_PUBLIC);

        $criteria = "Feature.start < NOW() AND Feature.end > NOW() AND Feature.application_id ='" . APP_ID . "' AND "
            ."(Feature.locale = '" . LANG . "' or Feature.locale IS NULL)";
        if (isset($this->namedArgs['format']) && $this->namedArgs['format'] == 'rss') {
            $isrss = true;
            $order = "Addon.name ASC";
        } else {
            $isrss = false;
            $order = "RAND()";
        }

        $_addon_ids = array();


        if(isset($this->namedArgs['cat']))
        {
           $category = $this->namedArgs['cat'];
           $this->Amo->clean($category);
           $criteria = "feature > 0 AND category_id='".$category."'";
           $featAddons = $this->AddonCategory->findAll($criteria);

            foreach ($featAddons as $_addon)
               $_addon_ids[] = $_addon['AddonCategory']['addon_id'];

        } else {
            $featAddons = $this->Feature->findAll($criteria);
            foreach ($featAddons as $_addon)
               $_addon_ids[] = $_addon['Addon']['id'];
        }
        if (!empty($_addon_ids)) {
            $associations = array(
                'single_category', 'all_categories', 'authors', 'compatible_apps', 'files',
                'latest_version', 'list_details'
            );
            $featAddons = $this->Addon->getAddonList($_addon_ids, $associations);
        } else {
            $featAddons = array();
        }


        if (!$isrss) {
            // get platforms (if we are not in RSS mode)
            $this->Platform->unbindFully();
            $platforms = $this->Platform->findAll();
            $this->publish('platforms', $platforms);

            $this->layout='mozilla';
            $this->pageTitle = _('addons_recommended_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
            $this->publish('addons', $featAddons);
            $this->publish('rssAdd', array('/recommended/format:rss'));
            $this->publish('subpagetitle', _('addons_recommended_title'));
            $this->render();
        } else {
            $this->publish('addons', $featAddons);
            $this->publish('rss_title', _('addons_recommended_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME));
            $this->publish('rss_description', _('addons_recommended_introduction'));
            $this->render('rss/addons', 'rss');
        }
    }

    /**
     * page to display eula prior to installation
     */
    function policy($lightbox, $addon_id, $file_id=null) {
        $this->Amo->clean($lightbox);
        $this->Amo->clean($addon_id);
        $this->Amo->clean($file_id);

        $this->layout='amo2009';

        if (!$addon_id || !is_numeric($addon_id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'addon_id'), '/', 3);
            return;
        }

        $this->Addon->unbindFully();
        $this->Addon->bindModel(
            array(
                'hasAndBelongsToMany' => array(
                    'Category' => array(
                        'className' => 'Category',
                        'joinTable'  => 'addons_categories',
                        'foreignKey' => 'addon_id',
                        'associationForeignKey'=> 'category_id'
                    ),
                    'User' => array(
                        'className'  => 'User',
                        'joinTable'  => 'addons_users',
                        'foreignKey' => 'addon_id',
                        'associationForeignKey'=> 'user_id'
                    )
                )
            )
        );

        $this_addon = $this->Addon->findById($addon_id);
        if (empty($this_addon)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }

        if (isset($file_id)) {
            $this->File->unbindFully();
            $this_file = $this->File->findById($file_id);
            $this_addon['File'] = array($this_file['File']);
            $this_addon['Version'] =  $this->Version->findAllById($this_file['File']['version_id'], null, null, 0);

            // is this the latest public version?
            if ($this_addon['Addon']['status'] == STATUS_PUBLIC) {
                $latest_version_id = $this->Version->getVersionByAddonId($addon_id, $this_addon['Addon']['status']);
                $this->publish('is_latest', ($latest_version_id === $this_addon['Version'][0]['Version']['id']), false);
            } else {
                $this->publish('is_latest', false, false);
            }

            $this->Platform->unbindFully();
            $platforms = $this->Platform->findAllById($this_file['File']['platform_id']);
            $this->publish('platforms', $platforms);
        }
        else {
            $this->publish('policy', 1);
        }
        // get the categories that are related to the addon, so that they have translation data
        $_related_category_ids = array();
        foreach ($this_addon['Category'] as $categoryvalue) {
            $_related_category_ids[] = $categoryvalue['id'];
        }
        $related_categories = $this->Category->findAll(array('Category.id' => $_related_category_ids, 'Category.application_id' => APP_ID));
        unset($_related_category_ids);


        $this->publish('relatedCategories', $related_categories);
        $this->publish('addon', $this_addon);
        $this->pageTitle = sprintf(_('addons_display_pagetitle'), $this_addon['Translation']['name']['string']). ' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);


    }

    /**
     * Display previews for an addon
     */
    function previews($id) {
        $this->Amo->clean($id);
        $this->layout = 'mozilla';

        if (!$id || !is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'addon_id'), '/', 3);
            return;
        }

        $addon_data = $this->Addon->find(array(
            'Addon.id' => $id,
            'Addon.inactive' => '0',
            'Addon.status' => array(STATUS_PUBLIC, STATUS_SANDBOX, STATUS_NOMINATED)),
            null , null , 1);
        if (empty($addon_data)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }
        if ($addon_data['Addon']['status'] != STATUS_PUBLIC && !$this->sandboxAccess) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }

        $previews = $this->Preview->findAllByAddon_Id($id, array('id', 'addon_id', 'caption'));
        $this->publish('previews', $previews);
        $this->publish('addon', $addon_data);
        $_title = sprintf(_('addons_previews_pagetitle'), $addon_data['Translation']['name']['string']);
        $this->pageTitle = $_title. ' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('subpagetitle', $_title);

        // Pretty previews
        $this->set('includeSlimbox', 1);
        $this->set('suppressJQuery', 1);

        $this->render();
    }

    /**
     * Display previously released versions of an addon
     */
    function versions($id) {
        global $valid_status;

        $this->Amo->clean($id);

        // unbind addon from all references but its authors
        $bindusers = array('hasAndBelongsToMany' => array('User' => $this->Addon->hasAndBelongsToMany['User']));
        $this->Addon->unbindFully();
        $this->Addon->bindModel($bindusers);
        $addon = $this->Addon->find(array('Addon.id'=>$id,
            'Addon.status'=>$valid_status, 'Addon.inactive'=>0), null, null,
            null, null, 0);
        if (empty($addon)) {
            $this->flash(_('error_addon_notfound'), '/', 3);
            return;
        }

        // show all valid (even experimental) statuses on versions page
        $version_list = $this->Version->getVersionIdsByAddonId($id, $valid_status);
        //reformat the returned versions array
        $version_ids = array();
		$comp_apps_by_id = array();
        foreach ($version_list as $single_id) {
            $cur_id = $single_id['Version']['id'];
            $version_ids[] = $cur_id;
            $compat_apps = $this->Version->getCompatibleApps($cur_id);
            $comp_apps_by_id[$cur_id] = array_slice($compat_apps, 0, 1);
        }

        if (!empty($version_ids)) {
            $versions = $this->Version->findAllById($version_ids, null, "Version.created DESC", null, null, 1);

            for($i =0 ; $i < count($versions); $i++) {
                $versions[$i]['Compatibility'] = $comp_apps_by_id[$versions[$i]['Version']['id'] ];
	        }
        }
        else
            $versions = array();

        $this->Platform->unbindFully();
        $platforms = $this->Platform->findAll();
        $this->publish('addon', $addon);
        $this->publish('versions', $versions);
        $this->publish('platforms', $platforms);

        $_title = sprintf(_('addons_versions_pagetitle'), $addon['Translation']['name']['string']);
        if (!isset($this->namedArgs['format']) || $this->namedArgs['format'] != 'rss') {
            $this->publish('addonIconPath', $this->Image->getAddonIconURL($id));
            $this->pageTitle = $_title. ' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
            $this->publish('subpagetitle', $_title);
            $this->publish('rssAdd', array("/addons/versions/{$id}/format:rss"));

            $this->publish('breadcrumbs', array(
                sprintf(___('addons_home_pagetitle'), APP_PRETTYNAME) => '/',
                $addon['Translation']['name']['string'] => "/addon/{$id}"
            ));
            $this->render();
        } else {
            $this->publish('rss_title', $_title);
            $this->publish('rss_description', _('addons_versions_history'));
            $this->render('rss/versions', 'rss');
        }
    }

    /**
     * provide global rss feeds (deprecated)
     */
    function rss($type='') {
        $this->Amo->clean($type);
        $type = strtolower($type);

        switch($type) {
        case 'newest':
            $this->redirect('/browse/type:1/cat:all/format:rss?sort=newest');
            return;
            break;

        default:
            $this->redirect('/');
            return;
        }
    }
    
    /**
     * Gets all the addons for this tag
     */
    function fortag($tag_id) {
    	$tag = $this->Tag->findById($tag_id);
    	$this->publish("tag_id", $tag['Tag']['id']);
    	$this->publish("tag_text", $tag['Tag']['tag_text']);
    	
    	$addons = $this->Addon->getAddonsByTag($tag_id);
    	$this->publish("addons", $addons);
    }
    

}

?>
