<?php

class DevHubController extends AppController {

    var $name = 'DevHub';
    var $uses = array('Addon', 'BlogPost', 'HubPromo', 'User');
    var $components = array('Hub');
    var $helpers = array('Html', 'Link', 'Localization');

    function beforeFilter() {
        /* These are public pages. */
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        $this->layout = 'amo2009';
        $this->pageTitle = ___('Add-on Developer Hub').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);

        $this->cssAdd = array('amo2009/developers');
        $this->publish('cssAdd', $this->cssAdd);
        $this->jsAdd = array('developers');
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
        $blog_posts = $this->BlogPost->findAll(NULL, NULL,
            "BlogPost.date_posted DESC");

        if ($is_developer) {
            $promos = $this->HubPromo->getDeveloperPromos();
        } else {
            $promos = $this->HubPromo->getVisitorPromos();
        }

        $this->set('blog_posts', $blog_posts);
        $this->set('promos', $promos);
        $this->set('bodyclass', 'inverse');
        $this->render('hub');
    }

    function howto_list() {
        $this->layout = 'amo2009';
        $this->pageTitle = ___('How-to Library').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
        $this->publish('breadcrumbs', array(___('Developer Hub') => '/developers/'));

        $this->publish('categories', $this->Hub->categories);

        return $this->render('howto_list');
    }

    function howto_detail($page) {
        if (!array_key_exists($page, $this->Hub->categories_slugs)) {
            $this->flash(___('Page not found'), '/developers/docs/how-to');
        }

        $category = $this->Hub->categories_slugs[$page];

        $this->pageTitle = $category->title.' :: '.___('How-to Library').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');

        $this->publish('breadcrumbs',
                        array(___('Developer Hub') => '/developers/',
                              ___('How-to Library') => '/developers/docs/how-to/',
                        ));

        $this->publish('category', $category);
        $this->publish('categories', $this->Hub->categories);

        return $this->render('howto_detail');
    }

    function policy_list() {
        $this->layout = 'amo2009';
        $this->pageTitle = ___('Add-on Policies').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
        $this->publish('breadcrumbs', array(___('Developer Hub') => '/developers/'));

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
        $this->publish('breadcrumbs', array(___('Developer Hub') => '/developers/'));

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
}
