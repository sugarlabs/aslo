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
 *   Mike Morgan <morgamic@mozilla.com>
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

class Addon extends AppModel
{
    var $name = 'Addon';
    var $hasAndBelongsToMany = array('User' =>
                                      array('className'  => 'User',
                                            'joinTable'  => 'addons_users',
                                            'foreignKey' => 'addon_id',
                                            'associationForeignKey'=> 'user_id',
                                            'conditions' => 'addons_users.listed=1',
                                            'order' => 'addons_users.position'
                                      ),
                                     'Category' =>
                                       array('className'  => 'Category',
                                            'joinTable'  => 'addons_categories',
                                            'foreignKey' => 'addon_id',
                                            'associationForeignKey'=> 'category_id'
                                      ),
                                      'Collection' =>
                                       array('classname' => 'Collection',
                                       'joinTable' => 'addons_collections',
                                       'foreignKey' => 'addon_id',
                                       'associationForeignKey' => 'collection_id'),
                                       
                                       'Tag' => 
                                        array('className' => 'Tag', 
                                        'joinTable'  => 'users_tags_addons',
                                        'foreignKey' => 'addon_id',
                                        'associationForeignKey' => 'tag_id'
                                       )
                               );
    var $belongsTo = array('Addontype');
    var $hasMany = array('Version' =>
                         array('className'   => 'Version',
                               'conditions'  => '',
                               'order'       => 'Version.created DESC',
                               'limit'       => '',
                               'foreignKey'  => 'addon_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         ),
                         // see the addon_tag model for details
                         'AddonCategory' =>
                         array('classname'   => 'AddonCategory',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'addon_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         ),
                         'Feature' =>
                         array('classname'   => 'Feature',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'addon_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         ),
                         'Favorite' =>
                         array('classname'   => 'Favorite',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'addon_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         ),                                                  
                         'UserTagAddon' =>
                         	array('className' => 'UserTagAddon'
                         		
                         )
                  );

    var $hasMany_full = array(
                         'Preview' =>
                         array('className'   => 'Preview',
                               'conditions'  => '',
                               'order'       => 'Preview.highlight DESC',
                               'limit'       => '',
                               'foreignKey'  => 'addon_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         )
                    );


    var $translated_fields = array(
                'description',
                'developercomments',
                'eula',
                'supportemail',
                'supporturl',
                'homepage',
                'name',
                'privacypolicy',
                'summary',
                'the_future',
                'the_reason'
            );

    var $validate = array(
        'guid' => VALID_NOT_EMPTY,
        'name' => VALID_NOT_EMPTY,
        'addontype_id' => VALID_NUMBER,
        'defaultlocale' => VALID_NOT_EMPTY,
        'description' => VALID_NOT_EMPTY,
        'summary' => VALID_NOT_EMPTY,
        'supportemail' => VALID_EMAIL_OPT,
        'supporturl' => VALID_URL_OPT,
        'homepage' => VALID_URL_OPT,
        'suggested_amount' => VALID_MONEY
    );

    var $default_fields = array('id', 'guid', 'name', 'defaultlocale', 'addontype_id', 'status',
        'higheststatus', 'icontype', 'supportemail', 'supporturl', 'homepage', 'description', 'summary',
        'averagerating', 'weeklydownloads', 'totaldownloads', 'average_daily_downloads', 'average_daily_users',
        'totalreviews', 'developercomments', 'dev_agreement', 'inactive', 'trusted', 'viewsource', 'publicstats',
        'prerelease', 'adminreview', 'sitespecific', 'externalsoftware', 'binary',
        'eula', 'privacypolicy', 'nominationmessage', 'target_locale', 'locale_disambiguation',
        'paypal_id', 'suggested_amount', 'wants_contributions', 'annoying',
        'created', 'modified');

    const CONTRIBUTIONS_NONE = 0;
    const CONTRIBUTIONS_PASSIVE = 1;
    const CONTRIBUTIONS_AFTER = 2;
    const CONTRIBUTIONS_ROADBLOCK = 3;

    function clean_annoying($input) {
        if (CONTRIBUTIONS_ROADBLOCK < (int)$input) {
            $this->invalidate('annoying');
        }
    }

    /**
     * Get a single add-on, along with the desired associations
     * (uses the object-invalidation framework)
     * @param int $id add-on id to be fetched
     * @param array $associations list of additional items to return
     *
     * By convention, the associations array should be all lower case and sorted
     * alphabetically, to promote cache hits across pages.
     */
    function getAddon($id, $associations = array()) {
        global $valid_status;

        // if this object is cached, grab it from memcache
        $identifier = array("addon:$id", $associations);
        if (QUERY_CACHE && $cached = $this->Cache->readCacheObject($identifier)) {
            if (DEBUG >= 2) debug("addon $id was cached");
            return $cached;
        }

        // deactivate query caching
        $caching_was = $this->caching;
        $this->caching = false;

        // start with very basic fields and association list, then add more as desired
        $this->unbindFully();
        $fields = array('id', 'name', 'status');

        foreach ($associations as $association) {
            switch ($association) {
            case 'all_categories':
                // all categories this add-on is associated with
                $this->bindModel(array('hasMany' =>
                    array('AddonCategory' =>
                       array('className'  => 'AddonCategory',
                             'foreignKey' => 'addon_id'
                        ))));
                break;

            case 'all_tags':
                // all categories this add-on is associated with
                $this->bindModel(array('hasMany' =>
                    array('UserTagAddon' =>
                       array('className'  => 'UserTagAddon',
                        ))));
                $this->bindModel(array('hasAndBelongsToMany' =>
                    array('Tag' =>
                        array('className'  => 'Tag',
                              'joinTable'  => 'users_tags_addons',
                              'foreignKey' => 'addon_id',
                              'associationForeignKey'=> 'tag_id'
                        ))));
                break;

            case 'authors':
                // addon authors
                $this->bindModel(array('hasAndBelongsToMany' =>
                    array('User' =>
                        array('className'  => 'User',
                              'joinTable'  => 'addons_users',
                              'foreignKey' => 'addon_id',
                              'associationForeignKey'=> 'user_id',
                              'conditions' => 'addons_users.listed=1',
                              'order' => 'addons_users.position'
                        ))));
                break;

            case 'compatible_apps':
                // list of applications this add-on is compatible with
                break;

            case 'default_fields':
                //Replaces any fields with the default fields
                $fields = $this->default_fields;

            case 'files':
                // list of files for all versions returned (depends on lastversion
                // or allversions);
                loadModel('File');
                break;

            case 'latest_version':
                // latest public version for public add-ons, latest valid
                // version otherwise
                break;

            case 'list_details':
                // add-on details needed for a list item
                $fields = array_merge($fields, array('summary', 'eula', 'created', 'locale_disambiguation',
                    'weeklydownloads', 'addontype_id', 'averagerating', 'totalreviews'));
                break;

            case 'recommendations':
                // attach the top 5 recommended add-ons
            break;

            case 'single_category':
                // the first category this add-on is associated with
                $this->bindModel(array('hasMany' =>
                    array('AddonCategory' =>
                       array('className'  => 'AddonCategory',
                             'foreignKey' => 'addon_id',
                             'limit' => 1
                        ))));
                break;

            case 'single_tag':
                // all tags this add-on is associated with
                $this->bindModel(array('hasMany' =>
                    array('UserTagAddon' =>
                       array('className'  => 'UserTagAddon',
                        ))));
                $this->bindModel(array('hasAndBelongsToMany' =>
                    array('Tag' =>
                        array('className'  => 'Tag',
                              'joinTable'  => 'users_tags_addons',
                              'foreignKey' => 'addon_id',
                              'associationForeignKey'=> 'tag_id',
                             'limit' => 1
                        ))));
                break;

            case 'contrib_details':
                $fields = array_merge($fields, array('the_reason', 'the_future',
                    'annoying', 'wants_contributions', 'paypal_id', 'suggested_amount'));
                break;
                
            default:
                debug("Association $association not declared!");
                break;
            }
        }

        // get desired add-on from DB
        $addon = $this->findById($id, array_unique($fields));

        // add additional data
        if (in_array('latest_version', $associations)) {
            // pull in last version
            $this->Version->unbindFully();
            $this->Version->caching = false;
            $this->Version->useDbConfig = 'shadow';
            $buf = $this->Version->findAll(array(
                'Version.id' => $this->Version->getVersionByAddonId($id,
                    ($addon['Addon']['status']==STATUS_PUBLIC ? STATUS_PUBLIC : $valid_status))),
                array('Version.id', 'Version.version', 'Version.created'));
            if (!empty($buf[0]['Version'])) {
                $addon['Version'][0] = $buf[0]['Version'];

                if (in_array('compatible_apps', $associations)) {
                    /* get add-on app compatibility info for that version */
                    $addon['compatible_apps'] = $this->Version->getCompatibleApps($buf[0]['Version']['id']);
                }
            }
        }

        if (in_array('list_details', $associations)) {
            // is the addon recommended?
            $addon['Addon']['recommended'] = $this->is_recommended($id);
        }

        // files
        if (in_array('files', $associations) && in_array('latest_version', $associations)
            && !empty($addon['Version'])) {

            loadModel('File');
            $this->File =& new File();
            $this->File->unbindfully();
            $this->File->caching = false;
            $_files = $this->File->findAll("File.version_id = '{$addon['Version'][0]['id']}'");
            foreach ($_files as $_file)
                $addon['File'][] = $_file['File'];

            // date of addon status change
            if (!empty($addon['File'])) {
                if ($addon['Addon']['status'] == STATUS_PUBLIC && $addon['File'][0]['datestatuschanged'] > 0)
                    $addon['Addon']['datestatuschanged'] = $addon['File'][0]['datestatuschanged'];
                else
                    $addon['Addon']['datestatuschanged'] = $addon['File'][0]['created'];
            } else {
                $addon['Addon']['datestatuschanged'] = $addon['Version'][0]['created'];
            }
        }

        // add addon categories
        if ((in_array('all_categories', $associations) || in_array('single_category', $associations))
            && !empty($addon['AddonCategory'])) {

            $_category_ids = array();
            foreach ($addon['AddonCategory'] as $_category)
                $_category_ids[] = $_category['category_id'];
            $categories = array();
            if (!empty($_category_ids))
                $categories = $this->Category->findAll(array('Category.id' => $_category_ids));
            $addon['Category'] = $categories;
        }

        if (in_array('recommendations', $associations)) {
            $rec_ids = $this->execute("SELECT other_addon_id AS id
                                       FROM addon_recommendations as recs
                                       WHERE addon_id={$id}
                                       ORDER BY score DESC LIMIT 5");
            $addon['Recommendations'] = array();
            foreach ($rec_ids as $r) {
                $addon['Recommendations'][] = $this->getAddon($r['recs']['id']);
            }
        }

        // cache this object...
        if (QUERY_CACHE)
            $this->Cache->writeCacheObject($identifier, $addon, "addon:$id");

        // re-enable query caching
        $this->caching = $caching_was;

        // ... then hand it back to the caller
        return $addon;
    }

    /**
     * Get a list of add-ons by id, each with the given associations
     * uses the object invalidation framework
     */
    function getAddonList($ids, $associations = array()) {
        $result = array();
        foreach ($ids as $id) {
            $result[] = $this->getAddon($id, $associations);
        }
        return $result;
    }

    /**
     * Get addons in a category, sorted by name, popularity (weekly downloads)
     * or "recently updated" (last file approval timestamp).
     *
     * @return array list of matching add-on ids
     */
    function getAddonsFromCategory($status = array(STATUS_PUBLIC),
        $addontypes = ADDON_EXTENSION, $category = 'all', $sort_by = 'name',
        $direction = 'ASC', $limit = '5', $page = '1', $friends = '') {

        $this->unbindFully();

        $select_field = 'DISTINCT Addon.id';

        // make input data uniform
        if (!is_array($addontypes)) $addontypes = array($addontypes);
        if (!is_array($status)) $status = array($status);
        if ($page <= 0) $page = 1;

        // additional joins for sort order etc.
        $add_joins = $orderby = $limitclause = $groupby = $where = '';
        if ($category != 'all') {
            // if cat == all, don't worry about the category. Otherwise only select the chosen one.
            $add_joins .= "INNER JOIN addons_categories AS at ON (at.category_id = '{$category}' AND at.addon_id = Addon.id) ";
        }
        // only select add-ons that have any files to offer
        $add_joins .= "INNER JOIN files AS File ON (Version.id = File.version_id AND File.status IN (".implode(',',$status).")) ";

        // Facebook friends
        if (!empty($friends)) {
            $add_joins .= "INNER JOIN facebook_favorites AS ff ON ff.addon_id = Addon.id ";
            $where .= "AND ff.fb_user IN ({$friends}) ";
        }

        // additional joins etc per list type
        switch ($sort_by) {
        case 'name':
            $add_joins .= "LEFT JOIN translations AS tr_l ON (tr_l.id = Addon.name AND tr_l.locale = '".LANG."') "
                ."LEFT JOIN translations AS tr_en ON (tr_en.id = Addon.name AND tr_en.locale = `Addon`.`defaultlocale`) ";
            break;
        case 'updated':
            // for public addons: last date of a file being pushed public
            // for sandboxed addons: last file modification date
            $select_field .= ', IF(Addon.status = '.STATUS_PUBLIC.', '
                .'MAX(File.datestatuschanged), MAX(File.created)) '
                .'AS datestatuschanged';
            $groupby = 'GROUP BY Addon.id';
            break;
        }

        // translate sort_by into SQL ORDER BY
        if (!empty($sort_by)) {
            $orderby = 'ORDER BY ';
            switch ($sort_by) {
            case 'popular':
                $orderby .= 'Addon.weeklydownloads';
                break;
            case 'updated':
                $orderby .= 'datestatuschanged';
                break;
            case 'rated':
                $orderby .= 'Addon.bayesianrating';
                break;
            case 'newest':
                $orderby .= 'Addon.created';
                break;
            case 'name':
            default:
                $orderby .= 'IFNULL(tr_l.localized_string, tr_en.localized_string)'; break;
            }
            $orderby .= ' '.$direction;
        } else {
            $orderby = '';
        }

        if (!empty($page) && !empty($limit))
            $limitclause = "LIMIT ".(($page-1)*$limit).", {$limit}";
        else
            $limitclause = '';

        // If the search engine type is the only add-on we're looking for, we remove
        // the restriction on applications.  If the search engine type is in the list
        // with other types, it _won't work_ because there is no application
        // relationship for search engines.
        if (in_array(ADDON_SEARCH, $addontypes) && count($addontypes) == 1) {
            $sql = "SELECT {$select_field} FROM addons AS Addon "
                ."INNER JOIN versions AS Version ON (Addon.id = Version.addon_id) "
                .$add_joins
                ."WHERE Addon.addontype_id IN(".implode(',',$addontypes).") "
                ."AND Addon.status IN(".implode(',',$status).") "
                ."AND Addon.inactive = 0 "
                ."{$where} {$groupby} {$orderby} {$limitclause}";
        } else {
            $sql = "SELECT {$select_field} FROM addons AS Addon "
                ."INNER JOIN versions AS Version ON (Addon.id = Version.addon_id) "
                ."INNER JOIN applications_versions AS av ON (av.version_id = Version.id AND av.application_id = ".APP_ID.") "
                .$add_joins
                ."WHERE Addon.addontype_id IN(".implode(',',$addontypes).") "
                ."AND Addon.status IN(".implode(',',$status).") "
                ."AND Addon.inactive = 0 "
                ."{$where} {$groupby} {$orderby} {$limitclause}";
        }

        $addon_list = $this->query($sql,true);

        // if there are no results, we are done.
        if (empty($addon_list)) return $addon_list;

        // otherwise return the ids
        $addon_ids = array();
        foreach($addon_list as $id) $addon_ids[] = $id['Addon']['id'];
        return $addon_ids;
    }

    /**
     * Sort and optionally paginate the addons in $addon_ids.
     */
    function sorted($addon_ids, $field, $limit=null, $page=null, $extra='') {
        // Bail early if there isn't anything to sort.
        if (empty($addon_ids)) {
            return $addon_ids;
        }

        $join = $orderby = $direction = $limit_clause = '';

        $select = "SELECT DISTINCT Addon.id FROM addons AS Addon";

        $ids = implode(',', $addon_ids);
        $where = "WHERE Addon.id IN ({$ids})";

        if (strstr($field, ' ')) {
            list($field, $direction) = explode(' ', $field);
        }

        if (in_array($field, $this->translated_fields)) {
            $join = "LEFT JOIN translations AS tr_l
                       ON (tr_l.id = Addon.{$field} AND tr_l.locale = '".LANG."')
                     LEFT JOIN translations AS tr_en
                       ON (tr_en.id = Addon.{$field} AND tr_en.locale = `Addon`.`defaultlocale`) ";
            $orderby = "ORDER BY IFNULL(tr_l.localized_string, tr_en.localized_string)";
        } else if (!empty($extra)) {
            $join = $extra;
            $orderby = "ORDER BY {$field}";
        } else {
            $orderby = "ORDER BY Addon.{$field}";
        }

        if (isset($limit) && isset($page)) {
            $offset = ($page - 1) * $limit;
            $limit_clause .= "LIMIT {$limit} OFFSET {$offset}";
        }

        $sql = implode(' ', array($select, $join, $where, $orderby, $direction, $limit_clause));

        foreach($this->query($sql) as $a) $sorted[] = $a['Addon']['id'];

        return $sorted;
    }

    /**
     * Get a list of addon IDs from a collection
     * @param int $collectionId ID of the collection
     * @param string $orderBy field name to sort the list by
     * @param int $cat_id sub-category ID, null for all
     */
    function getAddonsFromCollection($collectionId = null, $orderBy = null, $cat_id = null, $limit = null) {
        global $valid_status;

        $this->unbindFully();

        $query = "SELECT DISTINCT(Addon.id) FROM addons AS Addon INNER JOIN versions AS Version ON (Addon.id = Version.addon_id) "
        ."INNER JOIN files AS File ON (Version.id = File.version_id AND File.status IN (".join(",",$valid_status).")) "
        ."INNER JOIN addons_collections AS ac ON (ac.addon_id = Addon.id AND ac.collection_id = $collectionId AND "
        .((is_null($cat_id))?'1':"ac.category='{$cat_id}'").") WHERE "
        ."Addon.status IN (".join(",",$valid_status).") AND Addon.inactive = 0";

        if (!is_null($orderBy)) {
            $query .= " ORDER BY {$orderBy}";
        }

        if (!is_null($limit)) {
            $query .= " LIMIT {$limit}";
        }


        $addon_list = $this->query($query, true);
        $addons_ids = array();
        if(!empty($addon_list)) {
            foreach($addon_list as $id) {
                $addons_ids[] = $id['Addon']['id'];
            }
        }

        return $addons_ids;
    }

    /**
     * Get a list of addon IDs from a category, divided into subcategories
     */
    function getCategorizedAddonsFromCollection($collectionId = null) {
        $subcatlist = $this->query('SELECT DISTINCT(ac.category) FROM addons_collections AS ac '
            ."WHERE ac.collection_id='{$collectionId}' ORDER BY ac.category");

        $result = array();
        if (empty($subcatlist)) return $result;
        foreach ($subcatlist as $cat) {
            $catid = $cat['ac']['category'];
            $result[$catid] = $this->getAddonsFromCollection($collectionId, 'name', $catid);
        }
        return $result;
    }

    /**
     * Get the number of addons in a specific category
     */
    function countAddonsInCategory($status = array(STATUS_PUBLIC),
        $addontypes = ADDON_EXTENSION, $category = 'all', $friends = '') {

        $rowcount = $this->getAddonsFromCategory($status, $addontypes, $category,
            null, null, null, null, $friends);
        return count($rowcount);
    }

    /**
     * Count all addons in all categories for a given type.
     */
    function countAddonsInAllCategories($status = array(STATUS_PUBLIC), $addontypes = array(ADDON_THEME)) {

        if (!is_array($status))
            $status = array($status);
        if (!is_array($addontypes))
            $addontypes = array($addontypes);

        // Construct the SQL query, stolen from getAddonsByCategory
        $sql = 'SELECT at.category_id, COUNT(DISTINCT Addon.id) AS co '
                .'FROM addons AS Addon '
                .'INNER JOIN versions AS Version ON (Addon.id = Version.addon_id) '
                .'INNER JOIN applications_versions AS av ON (av.version_id = Version.id AND av.application_id = '.APP_ID.') '
                .'INNER JOIN addons_categories AS at ON (at.addon_id = Addon.id)  '
                .'INNER JOIN files AS File ON (Version.id = File.version_id AND File.status IN ('.implode(',',$status).')) '
                .'WHERE Addon.addontype_id IN('.implode(',',$addontypes).') '
                .'AND Addon.status IN('.implode(',',$status).') '
                .'AND Addon.inactive = 0 '
                .'GROUP BY at.category_id';

        $rows = $this->query($sql, true);

        // Reduce the rows from the DB down to simple ID / count
        $addon_counts = array();
        foreach ($rows as $row) {
            $addon_counts[ $row['at']['category_id'] ] = $row[0]['co'];
        }

        return $addon_counts;
    }

   /**
    * Returns the name of the add-on with given id
    */
    function getAddonName($addon_id) {
        $addon = $this->find("Addon.id={$addon_id}", array('name'), null, 0);
        if ($addon)
            return $addon['Translation']['name']['string'];
        else
            return false;
    }

   /**
    * Returns the name and ids of all add-ons by the user
    */
    function getAddonsByUser($user_id) {
        $addons = array();
        $addon_ids = $this->query("SELECT DISTINCT addon_id FROM addons_users WHERE user_id={$user_id}");
        if (!empty($addon_ids)) {
            foreach ($addon_ids as $addon_id) {
                $addons[$addon_id['addons_users']['addon_id']] = $this->getAddonName($addon_id['addons_users']['addon_id']);
            }
        }

        asort($addons);

        return $addons;
    }

   /**
    * Returns an array of recommended add-ons' ids.
    * To be used with getAddonList()
    *
    * @param int $limit max. amount of results to return
    * @param string $order SQL order by clause
    */
    function getRecommendedAddons($limit=null, $order='RAND()') {
        $this->Feature->unbindFully();
        $criteria = "Feature.start < NOW() AND Feature.end > NOW() "
            ."AND Feature.application_id ='" . APP_ID . "' AND "
            ."(Feature.locale = '" . LANG . "' OR Feature.locale IS NULL)";
        $featAddons = $this->Feature->findAll($criteria, null, $order, $limit, 0);

        $_addon_ids = array();
        foreach ($featAddons as $_addon)
            $_addon_ids[] = $_addon['Feature']['addon_id'];

        return $_addon_ids;
    }

    /**
     * Is this add-on currently recommended?
     * @param int addon ID
     * @return bool addon is currently featured
     */
    function is_recommended($addon_id) {
        $this->Feature->unbindFully();
        $criteria = "Feature.addon_id = $addon_id AND "
            ."Feature.start < NOW() AND Feature.end > NOW() AND "
            ."Feature.application_id ='" . APP_ID . "' AND "
            ."(Feature.locale = '" . LANG . "' OR Feature.locale IS NULL)";

        $_rec = $this->Feature->findCount($criteria);

        return ($_rec >= 1);
    }

    /**
     * Get the most recent update ping count
     * This is temporary until we can store this in addons and update
     * it via cron
     */
    function getMostRecentUpdatePingCount($addon_id) {
        $count = $this->query("SELECT `count` FROM update_counts WHERE addon_id={$addon_id} ORDER BY `date` DESC LIMIT 1");

        return !empty($count) ? $count[0]['update_counts']['count'] : 0;
    }

    /**
     * Retrieves all details from addons_users for a particular add-on
     */
    function getAuthors($addon_id, $onlyListed = true) {
        $listedQry = $onlyListed ? 'AND addons_users.listed=1 ' : '';

        $authors = $this->query("SELECT addons_users.*, User.id, User.email, User.firstname, User.lastname, User.nickname FROM addons_users INNER JOIN users AS User ON addons_users.user_id = User.id WHERE addons_users.addon_id={$addon_id} {$listedQry}ORDER BY addons_users.position");

        return $authors;
    }

    /**
     * Removes all authors from an add-on
     */
    function clearAuthors($addon_id) {
        return $this->execute("DELETE FROM addons_users WHERE addon_id={$addon_id}");
    }

    /**
     * Saves an add-on author
     */
    function saveAuthor($addon_id, $user_id, $role = AUTHOR_ROLE_OWNER, $listed = 1, $position = 0) {
        return $this->execute("INSERT INTO addons_users (addon_id, user_id, role, listed, position) VALUES({$addon_id}, {$user_id}, {$role}, {$listed}, {$position})");
    }

    /**
     * Gets all applications ever supported by the add-on
     */
    function getApplicationsEverSupported($addon_id) {
        return $this->query("SELECT DISTINCT Application.* FROM addons AS Addon INNER JOIN versions AS Version on Version.addon_id=Addon.id INNER JOIN applications_versions AS av ON av.version_id=Version.id INNER JOIN applications AS Application ON Application.id=av.application_id WHERE Addon.id={$addon_id}");
    }

    /**
     * afterSave callback. Mark cached objects for flush.
     */
    function afterSave() {
        if (QUERY_CACHE) $this->Cache->markListForFlush("addon:{$this->id}");
        return parent::afterSave();
    }

    /**
     * Get the date the addon was added to a specific collection
     */
    function getCollectionPublishDetails($addon_id, $collectionId) {
        $this->unbindFully();
        
        // comments are en-US only
        $sql = "SELECT addons_collections.added, translations.localized_string as comment, users.id, users.firstname, users.lastname, users.nickname
                FROM addons_collections
                LEFT JOIN translations
                ON translations.id = addons_collections.comments AND translations.locale='en-US'
                INNER JOIN users
                ON users.id = addons_collections.user_id
                WHERE collection_id = {$collectionId} AND addon_id = {$addon_id}";
        $data = $this->query($sql);
        
        $details = array(
            'dateadded' => $data[0]['addons_collections']['added'],
            'publisher' => $data[0]['users'],
            'comment' => $data[0]['translations']['comment']
        );
        return $details;
    }

    /**
     * Return the ids of addons authored by any of the User ids.
     */
    function getAddonsForAuthors($author_ids) {
        $addon_id_sql = "SELECT DISTINCT addons.id
                         FROM addons
                         INNER JOIN addons_users
                         ON (addons.id=addons_users.addon_id AND
                             addons_users.user_id IN (".implode(', ', $author_ids)."))";
        foreach($this->query($addon_id_sql) as $addon)
            $addon_ids[] = $addon['addons']['id'];
        return $addon_ids;
    }

    /**
     * When an add-on was shared with others by email, increase the counter
     * accordingly.
     * @param int $addonid Add-on ID
     * @param int $number times shared
     * @return boolean success
     */
    function increaseShareCount($addonid, $number) {
        $sql = "UPDATE addons SET sharecount = sharecount + {$number} WHERE id = {$addonid};";
        return $this->execute($sql);
    }
    
    /**
     * adds a tag to an addon
     * -using saveAuthor() as an example
     * -trigger trg_tag_stat_inc will update tag_stat
     */
	function addTag($addonId, $tagId, $userId) {
        if (!(is_numeric($userId) && is_numeric($tagId) && is_numeric($addonId))) {
            return false;
        }
		$sql = "INSERT IGNORE INTO users_tags_addons set user_id = {$userId}, tag_id = {$tagId}, addon_id = {$addonId}, created = now()";
		$ret = $this->execute($sql);
	}
	
	/**
	 * -trigger trg_tag_stat_dec will update tag_stat
	 */
	function removeUserTagFromAddon($user_id, $tag_id, $addon_id) {
        if (!(is_numeric($user_id) && is_numeric($tag_id) && is_numeric($addon_id))) {
            return false;
        }
	
		$this->execute("DELETE FROM users_tags_addons where user_id={$user_id} AND tag_id={$tag_id} AND addon_id ={$addon_id}");
	}
	
	/**
	 * -trigger trg_tag_stat_dec will update tag_stat
	 */
	function removeTagFromAddon($tag_id, $addon_id) {
        if (!(is_numeric($tag_id) && is_numeric($addon_id))) {
            return false;
        }
		$this->execute("DELETE FROM users_tags_addons where tag_id={$tag_id} AND addon_id = {$addon_id}");
	}
		
	/**
	 * Gets all the tags for this addon
	 */
	function getTagsByAddon($addon_id) {
		$userTagAddons = $this->UserTagAddon->findAll(array('addon_id' => $addon_id));
		$tagIds = array();
		foreach ($userTagAddons as $uta) {
			$tagIds[] = $uta['UserTagAddon']['tag_id'];
		}
		
		return $this->Tag->findAllById($tagIds,null,"Tag.tag_text asc");
	} 
	
    /**
     * use bindOnly() before you use this function!
     */
	function getTagsByUserTagAddon($users_tags_addons) {
		$tagIds = array();
		foreach ($users_tags_addons as $uta) {
			$tagIds[] = $uta['UserTagAddon']['tag_id'];
		}
		
		if( count($tagIds) > 0)
			return $this->Tag->findAllById($tagIds);
		else {
			return array();
		}
		
	}
	
    /**
     * use Addon->bindOnly() before you call this function!
     */
	function getAddonsByUserTagAddon($users_tags_addons) {
		$addonIds = array();
		foreach ($users_tags_addons as $uta) {
			$addonIds[] = $uta['UserTagAddon']['addon_id'];
		}
		
		if( count($addonIds) > 0)
			return $this->findAllById($addonIds);
		else {
			return array();
		}
		
	}

    /**
     * use Addon->bindOnly() before you call this function!
     */
	function getTagsByUser($user_id) {
		$userTagAddons = $this->UserTagAddon->findAll(array('user_id' => $user_id));
		return $this->getTagsByUserTagAddon($userTagAddons);
	}
	
    /**
     * use Addon->bindOnly() before you call this function!
     */
    function getAddonsByTag($tag_id) {
    	$userTagAddons = $this->UserTagAddon->findAll(array('tag_id' => $tag_id));
    	return $this->getAddonsByUserTagAddon($userTagAddons);
    }
    
    function acceptContributions($addon) {
        $a = $addon['Addon'];
        $this->Config =& new Config();
        $config = $this->Config->getConfig();
        return (!$config['paypal_disabled'] && $a['wants_contributions'] &&
                !empty($a['paypal_id']));
    }

    /* * * * * * deprecated functions * * * * * */
    /**
     * Get addons in a category, sorted by name, popularity (weekly downloads)
     * or "recently updated" (last file approval timestamp).
     *
     * @deprecated since 4.0.1, use getAddonsFromCategory along with getAddonList instead
     */
    function getAddonsByCategory($fields = null, $status = array(STATUS_PUBLIC),
        $addontypes = ADDON_EXTENSION, $category = 'all', $sort_by = 'name',
        $direction = 'ASC', $limit = '5', $page = '1', $friends = '', $includeFiles = false) {

        $associations = array('all_categories', 'authors', 'compatible_apps',
            'latest_version', 'list_details');
        if ($includeFiles) {
            $associations[] = 'files';
            sort($associations);
        }
        // are we just counting the total rows?
        $counting = (is_string($fields) && strpos(low($fields), 'count') === 0);
        if (!$counting) {
            $ids = $this->getAddonsFromCategory($status, $addontypes, $category,
                $sort_by, $direction, $limit, $page, $friends);
            return $this->getAddonList($ids, $associations);
        } else {
            return $this->countAddonsInCategory($status, $addontypes, $category, $friends);
        }
    }
}
?>
