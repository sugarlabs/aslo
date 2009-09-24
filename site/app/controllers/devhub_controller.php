<?php

vendor('mailchimp/api');
vendor('phorms/phorms');
vendor('fizzypop/inc/Project/Extension');
vendor('fizzypop/inc/ProjectZip/Extension');

class DevHubController extends AppController {

    var $name = 'DevHub';
    var $uses = array('Addon', 'Addonlog', 'Application', 'Appversion', 'BlogPost', 'Category', 'Collection', 'HowtoVote', 'HubEvent', 'HubPromo', 'HubRssKey', 'Memcaching', 'User');
    var $components = array('Httplib', 'Hub', 'Image', 'Pagination', 'Versioncompare');
    var $helpers = array('Html', 'Link', 'Localization', 'Pagination', 'Time');
    var $exceptionCSRF = array('/developers/tools/builder', '/developers/community/newsletter/');

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
            $feed['addon'] = htmlspecialchars($all_addons[$_GET['addon']]);
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
            $this->_publishFeedRss($all_addons, $session['id']);
        } else {
            $promos = $this->HubPromo->getVisitorPromos();
        }

        $this->dontsanitize[] = 'date';
        $this->publish('events', $events);
        $this->publish('active_addon_id', $active_addon_id);
        $this->set('feed', $feed);
        $this->publish('is_developer', $is_developer);
        $this->set('blog_posts', $blog_posts);
        $this->publish('addons', $all_addons);
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

        // Any feed url can be turned into RSS by appending a secret key
        if (!empty($this->params['url']['privaterss'])) {
            return $this->_feedRss($addon_id, $this->params['url']['privaterss']);
        }

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

        if (!empty($session)) {
            $this->_publishFeedRss($addons, $session['id'], $addon_id, $filter);
        }

        $this->publish('feed_title', $feed_title);
        $this->set('feed', $feed);
        $this->publish('is_developer', $is_developer);
        $this->publish('addons', $addons);
        $this->publish('addon_id', $addon_id);
        $this->publish('filter', $filter);
        $this->publish('filters', $filters);
        $this->render('feed');
    }


    /**
     * Publish rss_url and rssAdd auto-discovery for a user
     *
     * @param array $addons array(addon_id => addon_name, ...)
     * @param int $user_id
     * @param mixed $addon_id or 'all'
     * @param string $filter
     */
    function _publishFeedRss($addons, $user_id, $addon_id='all', $filter='') {
        $rssAdd = array();

        if ($rss_key = $this->HubRssKey->getKeyForUser($user_id)) {
            $rssAdd[] = array("/developers/feed/all?privaterss={$rss_key}",
                                ___('News Feed for My Add-ons'));

            if ($addon_id == 'all') {
                $rss_url = "/developers/feed/{$addon_id}?";
                $rss_url .= ($filter ? "filter={$filter}&" : '') . "privaterss={$rss_key}";
                $this->publish('rss_url', $rss_url);
            }
        }

        foreach ($addons as $id => $name) {
            if ($rss_key = $this->HubRssKey->getKeyForAddon($id)) {
                $rssAdd[] = array("/developers/feed/{$id}?privaterss={$rss_key}",
                                    sprintf(___('News Feed for %1$s'), $name));

                if ($addon_id == $id) {
                    $rss_url = "/developers/feed/{$addon_id}?";
                    $rss_url .= ($filter ? "filter={$filter}&" : '') . "privaterss={$rss_key}";
                    $this->publish('rss_url', $rss_url);
                }
            }
        }

        $this->publish('rssAdd', $rssAdd);
    }


    /**
     * Add-on news feed RSS
     *
     * @param mixed $addon_id or 'all' for user addon feed
     * @param string $rss_key 'privaterss' parameter
     */
    function _feedRss($addon_id, $rss_key) {
        $this->layout = 'rss';

        $key_ok = false;
        $user_id = false;

        if (is_numeric($addon_id)) {
            if ($addon_id && ($addon_id == $this->HubRssKey->getAddonForKey($rss_key))) {
                $key_ok = true;
                $addons[$addon_id] = $this->Addon->getAddonName($addon_id);
            }
        } else if ($addon_id == 'all') {
            if ($user_id = $this->HubRssKey->getUserForKey($rss_key)) {
                $key_ok = true;
                $addons = $this->Addon->getAddonsByUser($user_id);
            }
        }

        $filters = array(
            'collections' => ___('Collections'),
            'reviews'     => ___('Reviews'),
            'approvals'   => ___('Approvals'),
            'updates'     => ___('Updates'),
        );
        $filter = isset($this->params['url']['filter']) ? $this->params['url']['filter'] : '';
        $filter = array_key_exists($filter, $filters) ? $filter : '';

        // RSS feed always gets the 20 newest items
        $page_options = array(
            'page' => 1,
            'show' => 20,
            'privateParameters' => array('page', 'show', 'sortBy', 'direction'),
        );

        // Start the presses
        if ($key_ok) {
            $feed = $this->Hub->getNewsForAddons(array_keys($addons), $filter, $page_options, true);
            if ($user_id) {
                $rss_title = ___('News Feed for My Add-ons');
            } else {
                $rss_title = sprintf(___('News Feed for %1$s'), $addons[$addon_id]);
            }
            $rss_description = $filter ? $filters[$filter] : '';

        // Invalid key gets an empty feed
        } else {
            $feed = array();
            $rss_title = '';
            $rss_description = '';
        }

        $this->set('feed', $feed);
        $this->publish('rss_title', $rss_title);
        $this->publish('rss_description', $rss_description);
        $this->render('feed_rss');
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
        $this->pageTitle = ___('Case Studies').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME).' :: '.___('Developer Hub');
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

        $this->publish('newsletter_subscribed', $this->Session->delete('newsletter_subscribed'));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $address = $_POST['address'];
            $mailchimp = new MCAPI(MAILCHIMP_API_KEY);
            /* The last param is some stupid thing required by mailchimp. */
            $result = $mailchimp->listSubscribe(ABOUT_ADDONS_LIST_ID,
                                                $address, array(''));
            if ($mailchimp->errorCode) {
                $this->publish('mailchimp_error', $mailchimp->errorMessage);
            } else {
                /* If the User isn't logged in, we can't send them messages.
                 * Anonymous people get POST pages, logged-in users GET pages.
                 */
                if ($this->Session->check('User')) {
                    $this->Session->write('newsletter_subscribed', True);
                    return $this->redirect('/developers/community/newsletter/');
                } else {
                    $this->publish('newsletter_subscribed', True);
                }
            }
        }

        $feeds = array();
        $feed = $this->_getFeed(ABOUT_ADDONS_FEED_URL);
        $feedCount = min(5, count($feed->channel->item));
        for ($i = 0; $i < $feedCount; $i++) {
            $item = $feed->channel->item[$i];
            $feeds[] = array('title' => $item->title,
                             'link' => $item->link,
                             'date' => $item->pubDate);
        }

        $this->publish('feeds', $feeds);
        return $this->render('newsletter');
    }

    /* Fetch and parse the feed at $url, caching the result for $expires seconds. */
    function _getFeed($url, $expires=3600 /* seconds */) {
        $key = 'feed:' . $url;
        $feed = $this->Memcaching->get($key);
        if (!$feed) {
            list($feed, $info) = $this->Httplib->get($url);
            $this->Memcaching->set($key, $feed, null, $expires);
        }
        return simplexml_load_string($feed);
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

    function builder($hash=null) {
        $this->pageTitle = implode(' :: ', array(___('Add-on Builder'),
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME), ___('Developer Hub')));

        $this->publish('breadcrumbs', array(
            sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME) => '/',
            ___('Developer Hub') => '/developers'
            ));

        $form = new BuilderForm(Phorm::GET, false, $this->_builder_data($hash));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = new BuilderForm(Phorm::POST, false);
            if ($form->is_valid()) {
                /* This is the data we'll be passing to fizzypop. */
                $data = array(
                    'id' => $form->clean['id'],
                    'package' => $form->clean['package'],
                    'name' => $form->clean['name'],
                    'version' => $form->clean['version'],
                    'description' => $form->clean['description'],
                    'author' => $form->clean['author'],
                    'contributors' => array_map('trim', split("\n", $form->clean['contributors'])),
                    'targets' => $form->_targets(),
                    // close your eyes
                    'ui' => array_combine(
                        $form->clean['ui'],
                        array_fill(0, count($form->clean['ui']), true)
                    ),
                );

                $db =& ConnectionManager::getDataSource($this->Addon->useDbConfig);
                $serialized = serialize($data);
                $hash = md5($serialized);  // Hashes are the new black!
                $this->Addon->execute(
                    "INSERT INTO fizzypop (hash, serialized, created)
                     VALUE ({$db->value($hash)}, {$db->value($serialized)}, NOW())");
                return $this->redirect("/developers/tools/builder/success/{$hash}");
            }
        }

        foreach ($form->fields as $field) {
            $field->addClass('builder-input');
        }
        $this->publish('form', $form);

        return $this->render('builder');
    }

    function builder_success($hash) {
        $db =& ConnectionManager::getDataSource($this->Addon->useDbConfig);

        $q = $this->Addon->execute("SELECT serialized FROM fizzypop
                                    WHERE hash={$db->value($hash)}");
        if (empty($q)) {
            return $this->cakeError('error404');
        }
        $addonData = unserialize($q[0]['fizzypop']['serialized']);

        $this->publish('package', $addonData['package']);
        $this->publish('hash', $hash);
        return $this->render('builder_success');
    }

    function builder_download($hash) {
        $db =& ConnectionManager::getDataSource($this->Addon->useDbConfig);

        $q = $this->Addon->execute("SELECT serialized FROM fizzypop
                                    WHERE hash={$db->value($hash)}");
        if (empty($q)) {
            return $this->cakeError('error404');
        }
        $data = unserialize($q[0]['fizzypop']['serialized']);

        // Tell Cake to keep quiet, no auto-rendering.
        $this->autoRender = False;
        try {
            $z = new AddonZip(new Project_Extension($data));
            $z->finish();
        } catch (Exception $e) {
            echo 'There was a problem: '. $e->getMessage();
            return;
        }
    }

    /* Extract the serialized fizzypop structure, undoing some transformations
     * for fizzypop to turn it back into a form.
     */
    function _builder_data($hash) {
        if (empty($hash)) {
            return array();
        }
        $db =& ConnectionManager::getDataSource($this->Addon->useDbConfig);
        $q = $this->Addon->execute("SELECT serialized FROM fizzypop
                                    WHERE hash={$db->value($hash)}");
        if (empty($q)) {
            return $this->cakeError('error404');
        }
        $data = unserialize($q[0]['fizzypop']['serialized']);

        $data['ui'] = array_keys($data['ui']);

        foreach ($data['targets'] as $app => $vals) {
            $data['applications'][] = $app;
            $data[$app.'_min'] = $vals['minVersion'];
            $data[$app.'_max'] = $vals['maxVersion'];
        }
        $data['contributors'] = join("\n", $data['contributors']);
        return $data;
    }
}

class OptionsWithHelpField extends OptionsField {

    function get_widget() {
        return new OptionGroupWithHelpWidget($this->choices);
    }
}

class OptionGroupWithHelpWidget extends OptionGroupWidget {

    var $template = '<li><label class="checkbox-label">%s %s</label>
                         <p class="help checkbox-help">%s</p></li>';

    function serialize($value, $attributes=array()) {
        $html = array();

        foreach ($this->options as $actual => $details) {
            list($display, $help) = $details;
            $option = new CheckBoxWidget(in_array($actual, $value));
            $html[] = sprintf($this->template, htmlentities($display),
                              $option->html($actual, $attributes), $help);
        }
        return '<ul>'.join('', $html).'</ul>';
    }
}

class BuilderForm extends Phorm {

    function define_fields() {
        $size = 44;
        // I don't know why, but inputs and textareas calculate their size differently.
        $cols = $size - 2;
        $max_length = 255;
        $rows = 4;


        $this->name = new TextField(___('Add-on Name'), $size, $max_length);
        $this->name->help_text = ___('Give your add-on a name. The most successful add-ons give some indication of their function in their name.');
        $this->description = new LargeTextField(___('Description'), $rows, $cols);
        $this->description->help_text = ___('Briefly describe your add-on in one sentence. This appears in the Add-ons Manager.');
        $this->version = new TextField(___('Add-on Version'), 8, $max_length);
        $this->version->help_text = ___('Enter your initial version number. Depending on the number of releases and your preferences, this is usually 0.1 or 1.0');
        $this->id = new TextField(___('Unique ID'), $size, $max_length);
        $this->id->help_text = ___('Each add-on requires a unique ID in the form of a UUID or an email address, such as addon-name@developer.com. The email address does not have to be valid.');
        $this->package = new TextField(___('Package Name'), $size, $max_length);
        $this->package->help_text = ___('The package name of your add-on used within the browser. This should be a short form of its name, for example, Test Extension might be testextension.');

        $this->author = new TextField(___("Author's Name"), $size, $max_length);
        $this->author->help_text = ___('Enter the name of the person or entity to be listed as the author of this add-on.');

        $this->contributors = new LargeTextField(___('Other Contributors'), $rows, $cols);
        $this->contributors->help_text = ___('Enter the names of any other contributors to this extension, one per line.');

        global $app_prettynames;
        $this->applications = new OptionsField(___('Applications'), $app_prettynames);
        $this->appversions = $this->_appversions();

        $this->ui = new OptionsWithHelpField(___('Features'), array(
            'about' => array(___('About Dialog'), ___('Creates a standard About dialog for your extension')),
            'options' => array(___('Preferences Dialog'), ___('Creates an example Preferences window')),
            'toolbar' => array(___('Toolbar'), ___('Creates an example toolbar for your extension')),
            'toolbarbutton' => array(___('Toolbar Button'), ___('Creates an example button on the browser toolbar')),
            'mainmenu' => array(___('Main Menu Command'), ___('Creates an item on the Tools menu')),
            'contextmenu' => array(___('Context Menu Command'), ___('Creates a context menu item for images')),
            'sidebar' => array(___('Sidebar Support'), ___('Creates an example sidebar panel')),
        ));

        $required = array('name', 'version', 'id', 'package', 'author', 'applications');
        foreach ($required as $r) {
            $this->_require($this->$r);
        }
    }

    function _require($field) {
        $field->validators[] = '__required_field';
        $field->addClass('required');
    }

    function _appversions() {
        global $app_prettynames, $app_shortnames;
        $Appversion = new Appversion();
        $VersionCompare = new VersioncompareComponent();

        $rv = array();

        $versions = $Appversion->getAllVersions();
        foreach ($app_prettynames as $app => $name) {
            $min_choices = min_versions($versions[$app_shortnames[$app]]);
            $max_choices = $versions[$app_shortnames[$app]];

            // Hey, let's sort in place like it's 1985!
            $VersionCompare->sortAppversionArray($min_choices);
            $VersionCompare->sortAppversionArray($max_choices);

            $min_choices = array_reverse($min_choices);
            $max_choices = array_reverse($max_choices);

            $min = $app.'_min';
            $max = $app.'_max';
            $this->$min = new SelectField($name, array_combine($min_choices, $min_choices),
                                          array(), array('name' => $app.'_min'));
            $this->$max = new SelectField($name, array_combine($max_choices, $max_choices),
                                          array(), array('name' => $app.'_max'));
            $rv[$app]['min'] = $this->$min;
            $rv[$app]['max'] = $this->$max;
        }

        return $rv;
    }

    function _targets() {
        global $app_prettynames, $app_shortnames;
        $Application = new Application();
        $targets = array();
        foreach ($this->clean['applications'] as $app) {
            $targets[$app]['enabled'] = True;
            $targets[$app]['minVersion'] = $this->clean[$app.'_min'];
            $targets[$app]['maxVersion'] = $this->clean[$app.'_max'];
            $targets[$app]['name'] = $app_prettynames[$app];

            $Application->unbindFully();
            $x = $Application->findById($app_shortnames[$app], array('guid'));
            $targets[$app]['id'] = $x['Application']['guid'];
        }
        return $targets;
    }
}

/* Remove versions like 3.5.* because those shouldn't be min versions. */
function min_versions($versions) {
    $rv = array();
    foreach ($versions as $v) {
        if (!strpos($v, '*')) {
            $rv[] = $v;
        }
    }
    return $rv;
}

function __required_field($value) {
    if (empty($value)) {
        throw new ValidationError(___('This field is required.'));
    }
}

class AddonZip extends ProjectZip_Extension {

    function getFilename() {
        $name = $this->template->data['package'];
        $safe_name = preg_replace(INVALID_FILENAME_CHARS, '_', $name);
        return $safe_name.'.zip';
    }

    function finish($send_headers=True) {
        // check zip status
        if ($this->numFiles == 0) {
            throw new Exception('No files found in zip file');
        } // end if no files
        if ($this->status != 0) {
            throw new Exception('Invalid zip status: '.
                $this->getStatusString());
        } // end if invalid status
        if (!is_readable($this->tempfile)) {
            throw new Exception('Unable to read zip file');
        } // end if invalid tempfile

        $this->close();

        if ($send_headers) {
            $headers = array(
                'Content-Type' => 'application/x-zip',
                'Content-Disposition' => "attachment; filename=\"{$this->getFilename()}\"",
                'Content-Transfer-Encoding' => 'binary',
                'Content-Length' => filesize($this->tempfile),
            );
            foreach ($headers as $key => $value) {
                header("{$key}: {$value}");
            }
            readfile($this->tempfile);
            unlink($this->tempfile);
        }
    }
}
