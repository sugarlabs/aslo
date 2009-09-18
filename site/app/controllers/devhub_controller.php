<?php

class DevHubController extends AppController {

    var $name = 'DevHub';
    var $uses = array('Addon', 'Addonlog', 'Application', 'BlogPost', 'Category', 'Collection', 'HowtoVote', 'HubEvent', 'HubPromo', 'User');
    var $components = array('Hub', 'Image', 'Pagination');
    var $helpers = array('Html', 'Link', 'Localization', 'Pagination');

    function beforeFilter() {
        /* These are public pages. */
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        $this->layout = 'amo2009';
        $this->pageTitle = ___('Add-on Developer Hub').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);

        $this->cssAdd = array('amo2009/developers');
        $this->publish('cssAdd', $this->cssAdd);
        $this->jsAdd = array('developers', 'amo2009/developers');
        $this->publish('jsAdd', $this->jsAdd);

        if ($this->Session->check('User')) {
            $user = $this->Session->read('User');
            $this->publish('all_addons', $this->Addon->getAddonsByUser($user['id']));
        } else {
            $this->publish('all_addons', array());
        }
    }

    /**
     * Developer Hub
     */
    function hub() {
        // The Hub recognizes two audiences:
        //   developers => anyone logged in and who has 1 or more add-ons
        //   visitors => everyone else (logged in or not)
        $session = $this->Session->read('User');
        $all_addons = (empty($session) ? array() : $this->Addon->getAddonsByUser($session['id']));
        $is_developer = !empty($all_addons);

        if(isset($_GET['addon']) && isset($all_addons[$_GET['addon']])) {
            $feed['type'] = 'addon';
            $feed['feed'] = array();
            $feed['feed'] = $this->Hub->getNewsForAddons(array($_GET['addon']));
            $feed['addon'] = $all_addons[$_GET['addon']];
            $active_addon_id = $_GET['addon'];
        } else {
            $feed['type'] = 'full';
            $feed['feed'] = $this->Hub->getNewsForAddons(array_keys($all_addons));
            $feed['addon'] = false;
            $active_addon_id = false;
        }

        // Ajax requests get add-on news only
        if ($this->isAjax()) {
            print $this->renderElement('amo2009/hub/promo_feed', array(
                'feed' => $feed['feed'],
                'limit' => 4,
                'addon_id' => $active_addon_id,
                'addon' => $feed['addon'],
            ));
            return;
        }

        $blog_posts = $this->BlogPost->findAll(NULL, NULL,
            "BlogPost.date_posted DESC");
        $events = $this->HubEvent->findAll('HubEvent.date >= CURDATE()', NULL,
            "HubEvent.date ASC");

        if ($is_developer) {
            $promos = $this->HubPromo->getDeveloperPromos();
        } else {
            $promos = $this->HubPromo->getVisitorPromos();
        }

        $this->dontsanitize[] = 'date';
        $this->publish('events', $events);
        $this->set('active_addon_id', $active_addon_id); 
        $this->set('feed', $feed);
        $this->set('is_developer', $is_developer);
        $this->set('blog_posts', $blog_posts);
        $this->set('addons', $all_addons);
        $this->set('promos', $promos);
        $this->set('bodyclass', 'inverse');
        $this->render('hub');
    }

    /**
     * Add-on news feed
     */
    function feed($addon_id='all') {
        // The Hub recognizes two audiences:
        //   developers => anyone logged in and who has 1 or more add-ons
        //   visitors => everyone else (logged in or not)
        $session = $this->Session->read('User');
        $addons = (empty($session) ? array() : $this->Addon->getAddonsByUser($session['id']));

        // fetch specified add-on and check permission
        if (is_numeric($addon_id)) {
            $addon_name = $this->Addon->getAddonName($addon_id);

            // add-on not found
            if (empty($addon_name)) {
                header('HTTP/1.1 404 Not Found');
                $this->set('error', ___('Add-on not found!'));

            // admin can view any add-on feed
            } else if (!isset($addons[$addon_id]) && $this->SimpleAcl->actionAllowed('Admin', 'ViewAnyAddonFeed', $session)) {
                $addons[$addon_id] = $addon_name;

            // permission denied - must be a developer of this add-on
            } else if (!isset($addons[$addon_id])) {
                $this->set('error', ___('You do not have access to that add-on.'));
            }
        }

        $is_developer = !empty($addons);

        $filters = array(
            'collections' => ___('Collections'),
            'reviews'     => ___('Reviews'),
            'approvals'   => ___('Approvals'),
            'updates'     => ___('Updates'),
        );
        $filter = isset($this->params['url']['filter']) ? $this->params['url']['filter'] : '';
        $filter = array_key_exists($filter, $filters) ? $filter : '';

        // single add-on feed
        if (is_numeric($addon_id)) {
            $feed_title = sprintf(___('News Feed for %1$s'), $addon_name);
            if (isset($addons[$addon_id])) {
                $feed = $this->Hub->getNewsForAddons(array($addon_id), $filter);
            } else {
                $feed = array();
            }

        // my add-on's feed
        } else {
            $addon_id = 'all';
            $feed_title = ___('News Feed for My Add-ons');
            if (!empty($addons)) {
                $feed = $this->Hub->getNewsForAddons(array_keys($addons), $filter);
            } else {
                $feed = array();
            }
        }

        $this->pageTitle = $feed_title.' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');

        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers/',
            ___('News Feeds') => '/developers/feed/all',
        ));

        $this->set('feed_title', $feed_title);
        $this->set('feed', $feed);
        $this->set('is_developer', $is_developer);
        $this->set('addons', $addons);
        $this->publish('addon_id', $addon_id);
        $this->publish('filter', $filter);
        $this->publish('filters', $filters);
        $this->render('feed');
    }


    function howto_list() {
        $this->layout = 'amo2009';
        $this->pageTitle = ___('How-to Library').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers/'));

        $this->publish('categories', $this->Hub->categories);

        return $this->render('howto_list');
    }

    function howto_detail($page) {
        if (!array_key_exists($page, $this->Hub->categories_slugs)) {
            $this->flash(___('Page not found'), '/developers/docs/how-to');
        }

        $category = $this->Hub->categories_slugs[$page];

        $this->pageTitle = $category->title.' :: '.___('How-to Library').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');

        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers/',
            ___('How-to Library') => '/developers/docs/how-to/',
            ));

        if ($this->Session->check('User')) {
            $user = $this->Session->read('User');
            $this->publish('user', $this->User->getUser($user['id'], array('votes')));
        } else {
            $this->publish('user', null);
        }

        $votes = $this->HowtoVote->getVotes($category->get_ids());

        $this->publish('votes', $votes);
        $this->publish('category', $category);
        $this->publish('categories', $this->Hub->categories);

        return $this->render('howto_detail');
    }

    function howto_vote($id, $direction) {
        $this->Amo->checkLoggedIn();
        $db =& ConnectionManager::getDataSource($this->Addon->useDbConfig);
        $clean_id = $db->value($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($id)) {
                $this->flash(sprintf(___('Missing argument: %s'), 'id'), '/', 3);
                return;
            }

            $directions = array('up' => 1, 'down' => -1, 'cancel' => 0);

            if (!array_key_exists($direction, $directions)) {
                $this->flash(___('Access Denied'), '/developers/docs/how-to/', 3);
            }

            $user = $this->Session->read('User');
            $vote = $directions[$direction];
            if ($vote == 0) {
                $sql = "DELETE FROM howto_votes
                        WHERE user_id={$user['id']}
                          AND howto_id={$clean_id}";
            } else {
                $sql = "REPLACE INTO howto_votes (howto_id, user_id, vote, created)
                        VALUE ({$clean_id}, {$user['id']}, {$vote}, NOW())";
            }

            $result = $this->HowtoVote->execute($sql);
            $this->HowtoVote->purge($id);
            $this->User->purge($user['id']);

            if ($this->isAjax()) {
                // Show me that shiny 200 OK.
                $this->publish('json', array());
                return $this->render('json', 'ajax');
            }
        }

        return $this->redirect('/developers/docs/how-to/'.$_POST['category'], 302);
    }

    function policy_list() {
        $this->layout = 'amo2009';
        $this->pageTitle = ___('Add-on Policies').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers/'));

        $this->publish('policies', $this->Hub->policies);

        $this->render('policy_list');
    }

    function policy_detail($policy) {
        if (!array_key_exists($policy, $this->Hub->policies_slugs)) {
            $this->redirect('/developers/docs/policies', null, true, false);
            return;
        } else {
            $policy = $this->Hub->policies_slugs[$policy];
        }

        $this->publish('policy', $policy);
        $this->publish('policies', $this->Hub->policies);

        $this->pageTitle = implode(' :: ', array($policy->title, ___('Add-on Policies'),
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME), ___('Developer Hub')));
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers',
            ___('Add-on Policies') => '/developers/docs/policies'
            ));

        $this->render('policy_detail');
    }

    /**
     * API & Language Reference
     */
    function api_reference() {
        $this->set('bodyclass', 'docs_reference inverse');
        $this->render('api_reference');
    }

    /**
     * Case Studies
     */
    function case_studies_list() {
        $this->layout = 'amo2009';
        $this->pageTitle = ___('Case Studies').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers/'));

        foreach ($this->Hub->casestudies as &$study) {
            $study->addon = $this->Addon->getAddon($study->addonid, array('authors'));
        }
        unset($study);
        $this->publish('casestudies', $this->Hub->casestudies);

        $this->render('case_studies_list');
    }

    /**
     * Individual case studies
     */
    function case_studies_detail($study) {
        if (!array_key_exists($study, $this->Hub->casestudies_slugs)) {
            $this->redirect('/developers/docs/case-studies', null, true, false);
            return;
        } else {
            $study = $this->Hub->casestudies_slugs[$study];
            $study->addon = $this->Addon->getAddon($study->addonid, array('authors', 'default_fields'));
        }
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers',
            ___('Case Studies') => '/developers/docs/case-studies'
            ));

        $this->publish('casestudies', $this->Hub->casestudies);
        $this->publish('study', $study);
        $this->render('case_studies_detail');
    }

    /**
     * Search results
     */
    function search() {
        if (isset($_GET['q'])) $this->publish('query', $_GET['q']);
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers'
            ));
        $this->pageTitle = ___('Search Results').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
        $this->render('search');
    }

    /**
     * Newsletter
     */
    function newsletter() {
        $this->pageTitle = ___('about:addons Newsletter').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers'
            ));
        $this->render('newsletter');
    }
    
    /**
     * Getting Started
     */
    function gettingstarted() {
        $this->pageTitle = ___('Getting Started').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers'
            ));
        $this->render('gettingstarted');
    }
}
