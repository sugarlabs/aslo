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
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Ryan Doherty <rdoherty@mozilla.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *   Jeff Balogh <jbalogh@mozilla.com>
 *   Scott McCammon <smccammon@mozilla.com>
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

class CollectionsListingComponent extends Object {
    
    var $controller;
    
   /**
    * Save a reference to the controller on startup
    * @param object &$controller the controller using this component
    */
    function startup(&$controller) {
        $this->controller =& $controller;
    }

    /**
     * Get localized sort options and current sort
     * @return array array(sortOptionsArray, sortBy)
     */
    function sorting() {
        $sort_default = 'weekly';
        $sort_opts = array(
            'weekly' => array(
                'text' => ___('Most popular this week'),
                'sort' => 'weekly_subscribers'),
            'monthly' => array(
                'text' => ___('Most popular this month'),
                'sort' => 'monthly_subscribers'),
            'all' => array(
                'text' => ___('Most popular all time'),
                'sort' => 'subscribers'),
            'newest' => array(
                'text' => ___('Newest', 'collections_index_option_newest'),
                'sort' => 'created'),
        );
        $sortby = isset($_GET['sortby']) ? $_GET['sortby'] : $sort_default;
        if (!array_key_exists($sortby, $sort_opts))
            $sortby = $sort_default;
        return array($sort_opts, $sortby);
    }

    /**
     * Fetch a sorted page of collections
     * @param array $ids array of collection ids
     * @param array array of pagination options
     * @return array Collections query result
     */
    function fetchPage($ids, $pagination_options=array()) {
        $conditions = array('Collection.id' => $ids);

        list($sort_opts, $sortby) = $this->sorting();

        $opts = array_merge(array('show' => 7, 'modelClass' => 'Collection'),
                            $pagination_options);
        $opts['sortBy'] = $sort_opts[$sortby]['sort'] . ' DESC';

        list($order, $limit, $page) = $this->controller->Pagination->init($conditions, array(), $opts);
        $this->controller->Collection->bindOnly('Users');

        return $this->controller->Collection->findAll($conditions, null, $order, $limit, $page);
    }

}
