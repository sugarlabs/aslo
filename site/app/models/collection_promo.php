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
 *   Wil Clouser <wclouser@mozilla.com>
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

class CollectionPromo extends AppModel
{
    var $name = "CollectionPromo";
    var $useTable = 'collection_promos';
    var $belongsTo = array('Collection', 'CollectionFeatures');
    var $recursive = 1;
    var $translated_fields = array();

    /**
     * Will return an array of all promoted collections in the format:
     *
     *  [locale] =>
     *      [title_tagline_id] =>
     *          [collection_id] => 
     *              [name] => [collection_name]
     *              [application_id] => [application_id]
     *          [collection_id] =>
     *              [name] => [collection_name]
     *              [application_id] => [application_id]
     *      ...
     *
     *  @return array of results
     */
    function findAll() {
        $_query = "
            SELECT 
            collection_promos.id as promo_id,
            collection_promos.locale as promo_locale,
            collection_promos.collection_feature_id,
            collections.id as collection_id,
            collections.application_id as application_id,
            c_name.localized_string as collection_name
            FROM collections RIGHT JOIN collection_promos ON collections.id = collection_promos.collection_id
            LEFT JOIN translations as `c_name` ON collections.name = c_name.id and c_name.locale='en-US'
            ORDER BY application_id ASC
            ";
        $_result = $this->query($_query);

        $_return = array();

        foreach ($_result as $_res) {
            if (empty($_res['collection_promos']['promo_locale'])) {
                $_locale = 'all';
            } else {
                $_locale = $_res['collection_promos']['promo_locale'];
            }

            $_return[$_locale][$_res['collection_promos']['collection_feature_id']][$_res['collections']['collection_id']] = array('name' => $_res['c_name']['collection_name'], 'application_id' => $_res['collections']['application_id']);
            
        }

        return $_return;
    }


    /**
     * Promote a collection + tagline + locale in the database.
     *
     * @param int collection id
     * @param int titletagline id
     * @param string locale to add.  An empty locale means all locales.
     */
    function promoteCollection($collection_id, $titletagline, $locale) {
        global $valid_languages;
        if (!is_numeric($collection_id)) return false;
        if (!in_array($titletagline, array_keys($this->CollectionFeatures->getTitlesAndTaglinesById()))) return false;
        if (!(empty($locale) || in_array($locale, array_keys($valid_languages)))) return false;

        return $this->execute("INSERT INTO collection_promos (collection_id, locale, collection_feature_id, created) VALUES ({$collection_id}, '{$locale}', {$titletagline}, NOW())");
    }

    /**
     * Demote a collection + tagline + locale in the database.  This only demotes it if it was promoted to begin with.  It doesn't actually flag a collection as crappy. ;)
     *
     * @param int collection id
     * @param int titletagline id
     * @param string locale to add.  An empty locale means all locales.
     */
    function demoteCollection($collection_id, $titletagline, $locale) {
        global $valid_languages;
        if (!is_numeric($collection_id)) return false;
        if (!in_array($titletagline, array_keys($this->CollectionFeatures->getTitlesAndTaglinesById()))) return false;
        if (!(empty($locale) || in_array($locale, array_keys($valid_languages)))) return false;

        return $this->execute("DELETE FROM collection_promos WHERE collection_id={$collection_id} AND collection_feature_id={$titletagline} AND locale='{$locale}' LIMIT 1");
    }
}
