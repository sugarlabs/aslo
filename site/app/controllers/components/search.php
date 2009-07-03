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
 *   Justin Scott <fligtar@mozilla.com>
 *   Wil Clouser <wclouser@mozilla.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *   Laura Thomson <lthomson@mozilla.com>
 *   Chris Pollett <cpollett@gmail.com>
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

class SearchComponent extends Object {
    
    var $controller;
    
   /**
    * Save a reference to the controller on startup
    * @param object &$controller the controller using this component
    */
    function startup(&$controller) {
        $this->controller =& $controller;
    }
    
    /**
     * An associative array holding the tables/fields to search (by default)
     *
     * Example:
     *   $search_fields = array(
     *                     array(
     *                           'table'     => 'Addon',
     *                           'field'     => 'name',
     *                           'priority'  => 8,
     *                           'localized' => false
     *                           ),
     *                     array(
     *                           'table'     => 'Addon',
     *                           'field'     => 'description',
     *                           'priority'  => 5,
     *                           'localized' => true
     *                           )
     *                      );
     *
     * @var array
     */
    var $search_fields = array(
        /** 
         * these priorities are totally arbitrary
         * @todo currently, searching for anything but addons will likely break
         */
        array(
              'table' => 'Addon',
              'field' => 'name',
              'priority' => 8,
              'localized' => true
              ),
        array(
              'table' => 'Addon',
              'field' => 'description',
              'priority' => 5,
              'localized' => true
              ),
        array(
              'table' => 'Addon',
              'field' => 'summary',
              'priority' => 5,
              'localized' => true
              )
    );

    /**
     * RegEx to split searches at spaces, respecting quotes
     */
    var $search_term_regex = '/([^" ]+)|(?:"([^"]*)")/';
    
    /**
     * The core search function.  (This was named 'find' but that caused problems
     * with simpletest)
     *
     * The parameters after the fourth are typically supplied by the advance search form. 
     *
     * Note: For $atype and $platform it is assumed that -1 won't be used for an addon or platform id.
     *
     * @todo write tests for advance search
     * @todo we need to be able to specify what fields to search
     *
     * @param string terms to search for - this string is now applied on tags as well, with tags having a weight of 1.5 
     * @param $tagFilter - used to filter the results further by exactly a tag (used by the side nav 
     *                     tag links after results are returned.  Clicking on the link performs the same 
     *                     search again but does a filter on tags to get all the addons in the previous result that have this tag)
     * @param $searchTagsOnly - flag used to indicate if search should be performed on tags only
     * @param string type of addon to search for, used by API
     * @param int category to search in (0 means no restriction)
     * @param lver, hver - version range addon version should intersect with
     * @param vfuz - make the matching of addon range fuzzy. (so 3.0 behaves more like 3.0*, this is used because with JS enabled 
     *               adv search form only gives approximate version ranges.
     * @param atype is the addon type for advanced search
     * @param platform is the platform id internal to Remora 
     * @param lup is the latest update time for advanced search
     * @param sort is used to specify sort order for advanced search
     * @param locale controls whether we search within only the current locale and en-US (faster) or all locales
     * @return array of information about results (modified cake results)
     */
    function search($terms, $tagFilter=null, $searchTagsOnly = false, $searchtype=NULL, $category=0, $status=NULL, 
                    $lver = -1, $hver = -1, $vfuz =false, $atype = ADDON_ANY,
                    $platform= PLATFORM_ANY, $lup = "", $sort = "", $locale=false ) {
        global $valid_status, $hybrid_categories, $app_listedtypes;
        
        if (isset($status)) {
            switch ($status) {
                case STATUS_PUBLIC:
                case STATUS_SANDBOX:
                case STATUS_PENDING:
                case STATUS_NOMINATED:
                    $sql_status = $status;
                    break;
                default:
                    $sql_status = implode(',',$valid_status);
                    break;
            } 
        } else {
            $sql_status = implode(',',$valid_status); 
        }
 
        /* prepare SQL query */
        
        // fields to search in
        $fields = array('name', 'summary', 'description');
        $_termarray = array();

        // first prepare text terms
        if (is_string($terms) && !empty($terms)) {
            // UTF-8 aware case-insensitive search
            $terms = mb_strtolower($terms, 'UTF-8');

            // split string into single terms
            preg_match_all($this->search_term_regex, $terms, $_termmatches);
        
            // remove quotes around split terms and sanitize them
            foreach ($_termmatches[0] as $term) {
                $term = trim($term, ' "');
                $term = $this->controller->Sanitize->sql($term);
                if ($term) $_termarray[] = $term;
            }
            // now strip duplicates
            $_termarray = array_unique($_termarray);
            $_search_termarray = array();
            foreach ($_termarray as $term) {
                if (false !== strpos($term, ' ')) $term = '"'.$term.'"'; // enclose "literal phrases" in quotes
                $_search_termarray[] = '+'.$term."*";
            }           
            
            if( $searchTagsOnly == true ) {
				 // for tag searching we need to remove all whitespace since whitespace is ignored
				$tagTerm = str_replace(" ", "", $terms);				
	            $text_score = " MATCH(a.tags) AGAINST ('".$tagTerm."') ";
	            $boolean_score =  " MATCH(a.tags) AGAINST ('".$tagTerm."' IN BOOLEAN MODE)";
				            	
            } else {
	            $tagTerm = str_replace(" ", "", $terms);
	            $text_score = " MATCH(a.".implode(', a.',$fields).") AGAINST ('".implode(" ", $_termarray)."') +  1.5 * MATCH(a.tags) AGAINST ('".$tagTerm."') " ;
	            $boolean_score =  " ( MATCH(a.".implode(', a.',$fields).") AGAINST ('".implode(" ", $_search_termarray)."' IN BOOLEAN MODE) OR MATCH(a.tags) AGAINST ('+".$tagTerm."*' IN BOOLEAN MODE) ) ";
	            if( $tagFilter != null && !empty($tagFilter)) {
	            	$boolean_score .= " AND MATCH(a.tags) AGAINST ('".str_replace(" ", "", $tagFilter)."' IN BOOLEAN MODE) ";
	            }
            }
            
            
        
        } else { //in this case enumerate all addons. this allows advanced search to act as a filter
            $text_score = "TRUE";
            $boolean_score = "TRUE";
        }
        
        // now initialize compoents of SQL query
        $_selects = $_orderby = $_joins = $_where = array();
        
        $_orderby[] = '(LOWER(a.name) =  \''.$this->controller->Sanitize->sql($terms).'\') DESC'; 
        $_orderby[] = '(a.status='.STATUS_PUBLIC.') DESC'; // show public add-ons first
        $_orderby[] = "(a.name LIKE '%".implode(' ', $_termarray)."%') DESC"; // sort exact name hits first
        
        if (!$locale) {
            $_matches = "(a.locale = '".LANG."' OR a.locale = 'en-US' ) AND ";
        }
        $_matches .= $boolean_score;
        
        foreach ($fields as $field) {
            // select strings
            $_selects[] = "a.".$field;
        }
        if ($text_score !== "TRUE") {
            $_selects[] = $text_score." AS text_score";
            $_orderby[] = 'text_score DESC';
        }
        switch ($searchtype) {
        case ADDON_EXTENSION:
            $_addon_types = ADDON_EXTENSION;
            break;
        case ADDON_THEME:
            $_addon_types = ADDON_THEME;
            break;
        case ADDON_PLUGIN:
            $_addon_types = ADDON_PLUGIN;
            break;
        case ADDON_DICT:
            $_addon_types = array(ADDON_DICT, ADDON_LPAPP, ADDON_LPADDON);
            break;
        case ADDON_SEARCH:
            $_addon_types = ADDON_SEARCH;
            break;
        case ADDON_API:
            $_addon_types = array(ADDON_EXTENSION, ADDON_THEME);
            break;
        default:
            // do not show anything but extensions and themes to API users
            // unless explicitly asked for
            if (in_array($searchtype, $app_listedtypes[APP_ID]))
                $_addon_types = $searchtype;
            else {
                $_addon_types = array(ADDON_EXTENSION, ADDON_THEME, ADDON_SEARCH, ADDON_DICT, ADDON_LPAPP, ADDON_LPADDON);
            }
        }
        if (!is_array($_addon_types)) $_addon_types = array($_addon_types);
        
        // override the odd-on type if the advanced search parameter $atype sent. Notice am assuming -1 not an add-on type
        if ($atype != -1) $_addon_types = array($atype);
        
        // restrict by category if necessary
        if ($category > 0) {
            if (!isset($hybrid_categories[APP_ID][$category])) {
                // regular category restriction
                $_joins[] = "INNER JOIN addons_categories AS acategories ON (acategories.addon_id = id AND acategories.category_id = '{$category}')";
            } else {
                // hybrid category
                $_hybrid_type = $hybrid_categories[APP_ID][$category];
                if (!in_array($_hybrid_type, $_addon_types)) $_addon_types[] = $_hybrid_type;
                
                $_joins[] = "LEFT JOIN addons_categories AS acategories ON (acategories.addon_id = id AND acategories.category_id = '{$category}')";
                $_where[] = "(a.addontype = ".$_hybrid_type." OR acategories.category_id IS NOT NULL)";
            }
        }
        
        //set the last update criteria for advanced search
        $_last_update = ($lup == "") ? "" : " AND TO_DAYS(v.created) > TO_DAYS(CURDATE() ".$lup.") ";
        
        // per-application only (for all but search engines)
        $_selects[] = " v.created AS created ";
        $_joins[]  = ' INNER JOIN `versions_summary` AS v ON (v.addon_id = a.id) ';
        $_app_compat = ' v.application_id = '.APP_ID.' ';

        // prepare a check for platform type (for advanced search)
        if ($platform != PLATFORM_ANY) {
            $_joins[] = "INNER JOIN files ON (files.platform_id IN (".PLATFORM_ALL.", $platform) AND v.version_id = files.version_id)";
        }

        /* Prepare a check for application versions (for advanced search).
           It is hard to directly compare versions in SQL (versionCompare is a recursive method) so we are relying on preprocessing magic.
           Suppose we are looking for addons in the version interval [A, B]. We first compute all the version ID that are contained
           in this interval. Call this $range_wanted. We also find the ids of the intervals (-infty, A) as $below_wanted, and (B, infty)
           as $above_wanted. Let v.min and v.max be the version range of a particular addon. We check that one of the following 
           happens: (1) v.min is in $below_wanted and v.max is in $above_wanted, so the range of the addon includes $range_wanted, or (2)
           one of v.min or v.max is in $range_wanted. Each of the three array is used in SQL in clauses which are probably implemented
           by mysql with in-memory hash tables so should be fast checks.
        */
        if ($lver != -1 && $hver != -1 ) {
            $range_wanted = array();
            $below_wanted = array();
            $above_wanted = array();
            $all_appversions =  $this->controller->Amo->getVersionIdsByApp(APP_ID);
            
            $lver = $this->controller->Sanitize->sql($lver);
            $hver = $this->controller->Sanitize->sql($hver);
            
            $fuz = "";
            if (isset($this->params['url']['vfuz']) && $this->params['url']['vfuz'] == true)
                $fuz = "*";
            
            $vcompare = $this->controller->Versioncompare;

            foreach($all_appversions as $appversion => $appvid) {
                // because of version fuzziness may be used, the three cases below are not necessarily disjoint
                if ($vcompare->compareVersions($appversion, $lver) < 0) { 
                    $below_wanted[] = $appvid;
                }
                if (($vcompare->compareVersions($lver, $appversion) <= 0 || $vcompare->compareVersions($lver.$fuz, $appversion) <= 0 || $lver == 'any') &&
                   ($vcompare->compareVersions($appversion, $hver) <= 0 || $vcompare->compareVersions($appversion, $hver.$fuz) <= 0 || $hver == 'any')) {
                    $range_wanted[] = $appvid; //note we want the version id's not the version numbers
                }
                if ($vcompare->compareVersions($appversion, $lver) > 0) { 
                    $above_wanted[] = $appvid;
                }
            }
            
            $_ver_string = "('".implode("', '", $range_wanted) ."') ";
            $vcheck = " v.min IN ".$_ver_string." OR v.max IN ".$_ver_string;
            $_ver_string = "('".implode("', '", $below_wanted) ."') ";
            $_ver_string2 = "('".implode("', '", $above_wanted) ."') ";
            $vcheck .= " OR (v.min IN ".$_ver_string." AND v.max IN ".$_ver_string2.")";
            
            if (in_array(ADDON_SEARCH, $_addon_types) ) {
               $vcheck = "(a.addontype = ".ADDON_SEARCH." OR ".$vcheck.")";
            }
            $_where[] = $vcheck;
        }
        
            //set the last update criteria for advanced search
            if ($lup != "") {
                $_where[] = " TO_DAYS(v.created) > TO_DAYS(CURDATE() ".$lup.") ";
            }

        // add order-by clauses for advanced search
        switch($sort) {
            case 'averagerating':
            case 'weeklydownloads':
                array_unshift($_orderby, "a.$sort DESC"); 
                break;
            case 'name':
                array_unshift($_orderby, "a.name ASC");
                break;
            case 'newest':
                // ordering by newest version for advanced search means joining the versions table
                array_unshift($_orderby, "created DESC");
                break;
        }
        
        if (!in_array(ADDON_SEARCH, $_addon_types))
            $_where[] = $_app_compat;
        elseif (count($_addon_types) > 1) // mixed types (search + other)
            $_where[] = '(a.addontype = '.ADDON_SEARCH." OR {$_app_compat})";
        // else: only search engines => do not restrict by application

        $sql = "SELECT DISTINCT a.id, " . implode(', ', $_selects)
            ." FROM text_search_summary AS a " . implode(' ', $_joins)
            ." WHERE $_matches "
                ."AND a.addontype IN (" . implode(',', $_addon_types) . ") "
                ."AND a.status IN(".$sql_status.") AND a.inactive = 0 "
                .(empty($_where) ? '' : 'AND ('.implode(' AND ', $_where).') ')
            ."ORDER BY ".implode(', ', $_orderby);

        // query the db and return the ids found
        $_results = $this->controller->Addon->query($sql, true);
		
        $_result_ids = array();
        foreach ($_results as $_result) $_result_ids[] = $_result['a']['id'];
        return $_result_ids;
    }


    /**
     * The collection search function.
     *
     * @param string terms to search for
     * @param sort is used to specify sort order
     * @param locale controls whether we search within only the current locale and en-US (faster) or all locales
     * @return array of collection ids
     */
    function searchCollections($terms, $sort='', $locale=false) {
        // fields to search in
        $fields = array('name', 'description');
        $_termarray = array();

        // first prepare text terms
        if (is_string($terms) && !empty($terms)) {
            // UTF-8 aware case-insensitive search
            $terms = mb_strtolower($terms, 'UTF-8');

            // split string into single terms
            preg_match_all($this->search_term_regex, $terms, $_termmatches);
        
            // remove quotes around split terms and sanitize them
            foreach ($_termmatches[0] as $term) {
                $term = trim($term, ' "');
                $term = $this->controller->Sanitize->sql($term);
                if ($term) $_termarray[] = $term;
            }
            // now strip duplicates
            $_termarray = array_unique($_termarray);
            $_search_termarray = array();
            foreach ($_termarray as $term) {
                if (false !== strpos($term, ' ')) $term = '"'.$term.'"'; // enclose "literal phrases" in quotes
                $_search_termarray[] = '+'.$term."*";
            }
            
            $text_score = " MATCH(c.".implode(', c.',$fields).") AGAINST ('".implode(" ", $_termarray)."')";
            $boolean_score =  " MATCH(c.".implode(', c.',$fields).") AGAINST ('".implode(" ", $_search_termarray)."' IN BOOLEAN MODE)";
            
        } else { //in this case enumerate all collections. this allows advanced search to act as a filter
            $text_score = "TRUE";
            $boolean_score = "TRUE";
        }
        
        // now initialize compoents of SQL query
        $_selects = $_orderby = $_where = array();
        
        $_orderby[] = '(LOWER(c.name) =  \''.$this->controller->Sanitize->sql($terms).'\') DESC'; 
        $_orderby[] = "(c.name LIKE '%".implode(' ', $_termarray)."%') DESC"; // sort exact name hits first
        
        if (!$locale) {
            $_matches = "(c.locale = '".LANG."' OR c.locale = 'en-US' ) AND ";
        }
        $_matches .= $boolean_score;
        
        foreach ($fields as $field) {
            // select strings
            $_selects[] = "c.".$field;
        }
        if ($text_score !== "TRUE") {
            $_selects[] = $text_score." AS text_score";
            $_orderby[] = 'text_score DESC';
        }

        $_where[] = "`Collection`.`application_id`='".APP_ID."'";
        $_where[] = "`Collection`.`listed`='1'";

        // sorting
        switch($sort) {
            case 'all':
                array_unshift($_orderby, "`Collection`.`subscribers` DESC"); 
                break;
            case 'weekly':
                array_unshift($_orderby, "`Collection`.`weekly_subscribers` DESC"); 
                break;
            case 'monthly':
                array_unshift($_orderby, "`Collection`.`monthly_subscribers` DESC"); 
                break;
            case 'newest':
                array_unshift($_orderby, "`Collection`.`created` DESC");
                break;
        }

        // build and run query
        $sql = "SELECT DISTINCT `c`.`id`, " . implode(', ', $_selects) . "
                FROM `collections_search_summary` AS `c`
                INNER JOIN `collections` AS `Collection` ON (`Collection`.`id` = `c`.`id`)
                WHERE {$_matches}
                    ".(empty($_where) ? '' : 'AND ('.implode(' AND ', $_where).') ')."
                ORDER BY ".implode(', ', $_orderby);
                
		          
                
        $_results = $this->controller->Addon->query($sql, true);

        // return the ids found
        $_result_ids = array();
        foreach ($_results as $_result) {
            $_result_ids[] = $_result['c']['id'];
        }
        return $_result_ids;
    }
}
?>
