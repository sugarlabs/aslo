<?php

class PersonasController extends AppController {

    var $name = 'Personas';
    var $uses = array('Addon', 'Persona');
    var $components = array('Amo', 'Httplib', 'Pagination');
    var $helpers = array('Html', 'Pagination');

    function beforeFilter() {
        /* These are public pages. */
        $this->SimpleAuth->enabled = $this->SimpleAcl->enabled = false;
        $this->pageTitle = sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);

        $this->layout = 'amo2009';
        $this->publish('jsAdd', array('amo2009/personas.js'), false);

        $this->forceShadowDb();
    }

    function persona_list($_category=null) {
        list($sort_opts, $sortby) = $this->_personasSorting();

        list($category, $category_id, $categories) = $this->_personasCategory($_category);

        $personas = $this->_fetchPage($category_id, array('addon'));

        $this->publish('personas', $personas);
        $this->publish('sort_opts', $sort_opts);
        $this->publish('sortby', $sortby);

        $this->publish('category', $category);
        $this->publish('category_id', $category_id);
        $this->publish('categories', $categories);

        $this->pageTitle = ___('Browse all Personas').' :: '.$this->pageTitle;
        return $this->render('personas_list');
    }

    function _personasCategory($category) {
        list($app, $type) = array(APP_ID, ADDON_PERSONA);

        /* Check that $category is a valid category name. */
        $en_categories = $this->Persona->execute(
            "SELECT categories.id, T.localized_string AS name
             FROM categories INNER JOIN translations AS T
               ON (categories.name = T.id AND T.locale = 'en-US')
             WHERE application_id={$app} AND addontype_id={$type}");
        $names = array();
        foreach ($en_categories as $c) {
            list($n, $id) = array(strtolower($c['T']['name']), $c['categories']['id']);
            $names[$n] = $id;
            $names[$id] = $n;
        }

        if (is_null($category)) {
            $category_name = $category_id = 'all';
        } else {
            /* Normalize category name. */
            $_category = strtolower($category);
            if (!array_key_exists($_category, $names)) {
                return $this->cakeError('error404');
            } else {
                $category_name = $_category;
                $category_id = $names[$_category];

                /* If the category looks right but it's the wrong case. redirect. */
                if ($category !== $_category) {
                    unset($_GET['url']);
                    $params = $this->Httplib->urlify($_GET);
                    $qs = empty($params) ? '' :   '?'.$params;
                    return $this->redirect("/personas/{$_category}".$qs);
                }
            }
        }

        $categories = $this->Amo->getCategories($app, $type);

        /* Add an 'All' category. */
        $total = 0;
        foreach ($categories as &$c) {
            $total += $c['Category']['count'];
            $c['Category']['key'] = $names[$c['Category']['id']];
        }

        $all = array();
        $all['Category']['count'] = $total;
        $all['Category']['key'] = '';
        $all['Translation']['name']['string'] = ___('All');
        array_unshift($categories, $all);

        return array($category_name, $category_id, $categories);
    }

    function _fetchPage($category_id=null, $associations=array()) {
        list($sort_opts, $sortby) = $this->_personasSorting();
        $_sort = $sort_opts[$sortby];
        $order = $_sort['sort'].' '.$_sort['dir'];

        /* Pagination options. */
        $opts = array('show' => 21);

        /* Ignore $order because $sort_opts has all we need. */
        list($_, $limit, $page) = $this->Pagination->init(array(), array(), $opts);

        /* Get sorted and paginated addon ids. */
        $addon_ids = $this->Addon->getAddonsFromCategory(
            array(STATUS_PUBLIC), ADDON_PERSONA, $category_id, $_sort['sort'],
            $_sort['dir'], $limit, $page, '', true);

        /* Get the personas that belong to those addons. */
        $this->Persona->unbindFully();
        $paged = $this->Persona->findall(array('Persona.addon_id' => $addon_ids),
                                         array('Persona.addon_id', 'Persona.id'));

        $paged_ids = array();
        foreach ($paged as $p) $paged_ids[$p['Persona']['addon_id']] = $p['Persona']['id'];

        /* We have to re-sort by addon id to match the order of addon_ids. */
        $sorted_ids = array();
        foreach ($addon_ids as $id) $sorted_ids[] = $paged_ids[$id];

        return $this->Persona->getPersonaList($sorted_ids, $associations);
    }

    /**
     * Get localized sort options and the name of the selected sort.
     */
    function _personasSorting() {
        $default = 'popularity';
        $opts = array(
            'name' => array('text' => ___('Name'),
                            'sort' => 'name', 'dir' => 'asc'),
            'date' => array('text' => ___('Date'),
                            'sort' => 'newest', 'dir' => 'desc'),
            'popularity' => array('text' => ___('Popularity'),
                                  'sort' => 'adu', 'dir' => 'desc'),
        );
        $use_get = isset($_GET['sortby']) && array_key_exists($_GET['sortby'], $opts);
        $sortby = $use_get ? $_GET['sortby'] : $default;
        return array($opts, $sortby);
    }
}

