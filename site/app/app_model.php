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
 *   Frederic Wenzel <fwenzel@mozilla.com> (Original Author)
 *   Justin Scott <fligtar@gmail.com>
 *   Mike Shaver <shaver@mozilla.org>
 *   Wil Clouser <clouserw@mozilla.com>
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

class AppModel extends Model
{
    var $translate = true;  // run dl10n magic?
    var $translationReplace = true; // replace translation indexes with translations
    var $Cache; // holds the cache object
    var $caching = QUERY_CACHE; // query caching enabled?
    var $default_fields = null; // if none are selected, which fields will we fetch for this model? Null means "all".

    /**
     * Constructor
     */
    function __construct($id = false, $table = null, $ds = null) {
        if ($this->caching) {
            loadModel('Memcaching');
            $this->Cache = new Memcaching();
        }
        return parent::__construct($id, $table, $ds);
    }

    /**
     * This function will dynamically join translations into the current find operation,
     * according to whichever fields it finds in $this->translated_fields.
     * Please also see: http://wiki.mozilla.org/Update:Remora_Localization
     *
     * @param array queryData as defined in cake's Model->findAll()
     * @return boolean will always return true
     */
    function beforeFind(&$queryData) {

        // Tell the user they are bad because they don't have a model name.
        if (!isset($this->name)) {
            trigger_error('No model name was found for class: '.$get_class($this).'.', E_NOTICE);
        }

        if (!$this->translate) return true; // don't do anything if translation was deactivated

        // This will build a finderQuery for the translations, and bind our current model to the translations table on the fly
        if (isset($this->translated_fields) && is_array($this->translated_fields)) {

            // Allow querying for a locale other than currently set
            $lang = $this->getLang();

            // fallback language is usually English. Some models have special
            // fallback options, however, so we are handling them here.
            switch ($this->name) {
            case 'Addon':
                $fb_locale = '`Addon`.`defaultlocale`';
                break;
            case 'Collection':
                $fb_locale = '`Collection`.`defaultlocale`';
                break;
            default:
                $fb_locale = "'en-US'";
                break;
            }

            // These parts are separated due to the way the query is built
            $_query = '';
            $_joins = array();

            // Generate a field list just like Cake would do it, so that we
            // know which translations to join in.
            // If the user didn't give us a field list, we use the default field
            // list set for this Model. We have to have cake
            // generate the list for us now, because once we set a fields
            // array, Cake won't select any other fields anymore than the ones
            // we request.
            if (!empty($queryData['fields']))
                $_fields = $queryData['fields'];
            else
                $_fields = $this->default_fields;

            // if it's a string only, wrap it into an array so all the
            // following array magic works with it as well
            if (is_string($_fields)) $_fields = array($_fields);
            $db =& ConnectionManager::getDataSource($this->useDbConfig);
            $_fields = $db->fields($this, $this->name, $_fields);

            foreach ($this->translated_fields as $field) {
                // only handle translatable fields that are actually selected
                if (false === $pos = array_search("`{$this->name}`.`{$field}`", $_fields)) {
                    continue;
                }

                // for each translated field, we select the localized string,
                // automatically falling back to en-US if nothing is found.
                // We also fetch the locale, which will be the requested
                // locale if found and en-US in case of fallback.
                // naming is {fieldname} and {fieldname}_locale resp., which
                // means, fallback is transparent.
                $_select = "IFNULL(`tr_{$field}`.localized_string, `fb_{$field}`.localized_string) AS `{$field}`";

                // replace the translation id with the translation unless explicitly opted out of
                if ($this->translationReplace === false) {
                    // append the translation
                    $_fields[] = $_select;
                } else {
                    // replace the translation index field
                    $_fields[$pos] = $_select;
                }
                // add the respective locale field to the end of the list
                // (that is: the requested locale if the localized string
                // is not null, otherwise the fallback locale)
                $_fields[] = "IF(!ISNULL(`tr_{$field}`.localized_string), `tr_{$field}`.locale, `fb_{$field}`.locale) AS `{$field}_locale`";


                // Our query design requires us to join on the same table repeatedly.
                // Each join requires a different table name, so we're actually
                // calling our tables the same things as the fields. We're also
                // creating a string for the fallback versions (usually en-US).
                // The requested locale has the prefix "tr_" (as "translation")
                // and the fallback has the prefix "fb_".
                $_joins[] = "LEFT JOIN translations AS `tr_{$field}` ON (`{$this->name}`.`{$field}` = `tr_{$field}`.id AND `tr_{$field}`.locale='{$lang}')";
                $_joins[] = "LEFT JOIN translations AS `fb_{$field}` ON (`{$this->name}`.`{$field}` = `fb_{$field}`.id AND `fb_{$field}`.locale={$fb_locale})";
            }

            // if we didn't actually translate anything, return now.
            if (empty($_joins)) return true;

            // magically replace translated fields in query conditions by
            // their more complicated counterparts, since MySQL apparently
            // can't back-reference the "AS" names from the where clause.
            $queryData['conditions'] = $this->_resolveTranslation($queryData['conditions']);
            $queryData['order'] = $this->_resolveTranslation($queryData['order']);

            // add our translation join magic to the query that's about to be executed
            $queryData['joins'] = array_merge($queryData['joins'], $_joins);
            $queryData['fields'] = $_fields;
        }

        // If this is false, the find won't execute.
        // DO return $queryData here. Changing the array by reference up there
        // does not imply PHP4 actually keeping it changed after we return. :(
        return $queryData;
    }

    /**
     * Take a condition or "order by" string and replace Translation.* by
     * the fallback queries.
     */
    function _resolveTranslation($subject) {
        $patterns = array(
            '/`?Translation`?\.([\w_]+)_locale/', // query locales first, because they match the second pattern as well!
            '/`?Translation`?\.([\w_]+)/'
            );
        $replacements = array(
            'IF(!ISNULL(`tr_\\1`.`localized_string`), `tr_\\1`.`locale`, `fb_\\1`.`locale`)',
            'IFNULL(`tr_\\1`.`localized_string`, `fb_\\1`.`localized_string`)'
            );
        if (is_array($subject)) {
            foreach($subject as $key => $value) {
                $new_key = $this->_resolveTranslation($key);
                $new_value = $this->_resolveTranslation($value);
                // set the new replaced value
                $subject[$new_key] = $new_value;
                // if the key changed, remove the old one
                if ($key != $new_key) {
                    unset($subject[$key]);
                }
            }
            return $subject;

        } else {
            return preg_replace($patterns, $replacements, $subject);
        }
    }

    /**
     * findAll(), checking for cached result objects.
     */
    function findAll($conditions = null, $fields = null, $order = null, $limit = null, $page = 1, $recursive = null) {
        if ($this->caching && isset($this->name)) {
            $cachekey = func_get_args();
            $cachekey = $this->_cachekey('findAll:'.serialize($cachekey));
            // if this was already cached, return it immediately
            if (false !== $cached = $this->Cache->get($cachekey)) {
                // Reset any bind/unbind Model calls, as would happen on a
                // normal query.
                $this->__resetAssociations();
                return $cached;
            }
        }

        // else fetch the result normally
        $result = parent::findAll($conditions, $fields, $order, $limit, $page, $recursive);
        if ($this->caching && $result !== false) {
            // cache it
            $this->Cache->set($cachekey, $result);
        }
        // and return the result
        return $result;
    }

    /**
     * query(), checking for cached result objects (only on select queries,
     * of course).
     * Note: If you execute multiple queries in one line with a select query
     * first, followed by some writing (insert or so), this *will* break.
     * Don't do this.
     */
    function query($query = null, $use_shadow_database = false, $cakeCaching = true) {
        if ($this->caching
            && is_string($query)
            && (0 === strpos(strtolower(ltrim($query)), 'select'))
            && isset($this->name)) {

            $cachekey = $this->_cachekey('query:'.$query);
            if ($cached = $this->Cache->get($cachekey)) return $cached;
        }

        if ($use_shadow_database && !defined('SHADOW_DISABLED')) {
            $this->useDbConfig = 'shadow';
            $result = parent::query($query, $cakeCaching);
            $this->useDbConfig = 'default';
        } else {
            $result = parent::query($query, $cakeCaching);
        }

        if ($this->caching && !empty($cachekey) && $result !== false) {
            // cache it (if it's a select query, otherwise $cachekey
            // would be empty)
            $res = $this->Cache->set($cachekey, $result);
        }
        // and return the result
        return $result;
    }

    /**
     * generate a cache key for memcaching queries
     * @param string additional key uniqueness factors (SQL query etc)
     * @deprecated since 4.0.1
     */
    function _cachekey($key = '') {
        // attach some unique factors to the key
        $key .= $this->name.':';
        $key .= LANG.':'.APP_ID.':';

        // serialize the bound model names
        $params = array('belongsTo', 'hasMany', 'hasAndBelongsToMany');
        foreach ($params as $param)
            $key .= serialize(array_keys($this->$param));

        return MEMCACHE_PREFIX.md5($key);
    }


   /**
    * Allowed querying for a locale other than currently set.
    * Gets locale set by setLang()
    * @return string $lang The language code to use
    */
    function getLang() {
        if (!empty($this->useLang)) {
            $lang = $this->useLang;
        }
        else {
            $lang = LANG;
        }

        return $lang;
    }

   /**
    * Sets current language to use in queries
    * @param string $lang The language code to use
    * @param object &$controller Reference to the controller
    * @return boolean true
    */
    function setLang($lang, &$controller = null) {
        $this->useLang = $lang;

        if (isset($controller) && is_object($controller->Translation)) {
            $controller->Translation->useLang = $lang;
        }

        return true;
    }

    /**
     * extended field validation: allow arbitrary validation functions
     * to use, add 'fieldname' => VALID_NOT_EMPTY or similar to $this->$validate,
     * then add a method clean_fieldname($input) which in the case of invalidity
     * calls $this->invalidate('fieldname') or amends $this->validationErrors.
     *
     * @param array $data data to be validated, $this->data by default
     * @return array validationError, array() if none
     */
    function invalidFields($data=array()) {
        if (!$this->beforeValidate()) {
            return false;
        }
        parent::invalidFields($data);
        if (empty($data)) {
            $data = $this->data;
        }
        foreach (array_keys($this->validate) as $field) {
            $func = 'clean_'.$field;
            if (method_exists($this, $func) && isset($data[$this->name][$field])) {
                call_user_func(array($this, $func), $data[$this->name][$field]);
            }
        }
        return $this->validationErrors;
    }

    /**
     * validation shortcut: maximum field length
     */
    function maxLength($field, $input, $max, $msg) {
        if (strlen($input) > $max) {
            $this->validationErrors[$field] = $msg;
        }
    }

    var $hasMany_full = array();
    var $hasAndBelongsToMany_full = array();
    var $belongsTo_full = array();

    function bindFully() {
        $this->bindModel(array('hasMany' => $this->hasMany_full,
                               'hasAndBelongsToMany' => $this->hasAndBelongsToMany_full,
                               'belongsTo' => $this->belongsTo_full));
    }

    /**
     * Index associations by model name.
     *
     * @return array $model => ($association, $definition)
     */
    function _bindings() {
        $assoc = array('belongsTo', 'hasOne', 'hasMany', 'hasAndBelongsToMany');
        $relations = array();
        foreach ($assoc as $a) {
            if (isset($this->$a)) {
                foreach ($this->$a as $rel => $def) {
                    $relations[$rel] = array($a, $def);
                }
            }
        }
        return $relations;
    }

    /**
     * Unbinds all models, then rebinds only the models passed as arguments.
     * >>> $this->Addon->bindOnly('Users', 'Framlings')
     * @param mixed [Model,...]
     */
    function bindOnly() {
        // Make sure all the associations are available before introspection.
        $this->__resetAssociations();
        $bindings = $this->_bindings();
        $this->unbindFully();

        $models = func_get_args();
        foreach ($models as $model) {
            list($assoc, $def) = $bindings[$model];
            $this->bindModel(array($assoc => array($model => $def)));
        }
    }

    function unbindFully() {
        $unbind = array();
        foreach ($this->belongsTo as $model=>$info) {
            $unbind['belongsTo'][] = $model;
        }
        foreach ($this->hasOne as $model=>$info) {
            $unbind['hasOne'][] = $model;
        }
        foreach ($this->hasMany as $model=>$info) {
            $unbind['hasMany'][] = $model;
        }
        foreach ($this->hasAndBelongsToMany as $model=>$info) {
            $unbind['hasAndBelongsToMany'][] = $model;
        }
        $this->unbindModel($unbind);
    }

   /**
    * Updates a table without requiring a primary key
    * @param mixed $update Array of fields and values to update or the update string
    * @param mixed $where Array of fields and values to match or the where string
    * @param int $limit Limit of rows to affect
    */
    function update($update, $where, $limit = 0) {
        //Return if no fields to update or if no where clause
        if (empty($update) || empty($where)) {
            return false;
        }

        $db =& ConnectionManager::getDataSource($this->useDbConfig);

        //Create update string from array
        if (is_array($update)) {
            foreach ($update as $field => $value) {
                if (empty($updateQry)) {
                    $updateQry = "`{$field}`='{$value}'";
                }
                else {
                    $updateQry .= ", `{$field}`='{$value}'";
                }
            }
        }
        elseif (is_string($update)) {
            $updateQry = $update;
        }

        //Create where clause from array
        if (is_array($where)) {
            foreach ($where as $field => $value) {
                if (empty($whereQry)) {
                    $whereQry = "`{$field}`='{$value}'";
                }
                else {
                    $whereQry .= " AND `{$field}`='{$value}'";
                }
            }
        }
        elseif (is_string($where)) {
            $whereQry = $where;
        }

        $limitQry = empty($limit) ? '' : " LIMIT {$limit}";

        return $db->execute("UPDATE ".$db->name($db->fullTableName($this))." SET {$updateQry} WHERE {$whereQry}{$limitQry}");
    }

    /**
     * save dynamically localized strings to translation table
     * before storing actual data
     */
    function beforeSave() {
        if (!isset($this->translated_fields) || empty($this->translated_fields)) {
            return true;
        } else {
            // we don't need this magic if none of the translated fields are to be saved
            $_tr_fields_tobestored = array_intersect($this->translated_fields, array_keys($this->data[$this->name]));
            if (empty($_tr_fields_tobestored)) return true;
        }

        // copy the data we intend to save
        $data = $this->data;

        // Allow querying for a locale other than currently set
        $lang = $this->getLang();

        // Make sure translation ids are returned
        $this->translationReplace = false;

        // start a transaction
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $this->begin();

        if ($this->exists()) {
            // editing an existing record
            $olddata = $this->findById($this->id);
        } else {
            $olddata = false;
        }

        // try to store all translated strings
        $errors = false;
        foreach ($this->translated_fields as $tr_field) {
            if (!isset($data[$this->name][$tr_field])) continue;

            // remove existing translation if empty
            $_remove = (empty($data[$this->name][$tr_field]));

            // see if there is a row for us to update, otherwise we'll insert a new one
            if ($olddata && !empty($olddata[$this->name][$tr_field])) {
                $_res = $db->execute("SELECT * FROM translations WHERE id = {$olddata[$this->name][$tr_field]} AND locale = '{$lang}';");
                $_update = ($_res !== false && ($db->lastNumRows() > 0));
                $_trans_id = $olddata[$this->name][$tr_field];
            } else {
                // if we would remove it anyway, don't make a new ID
                if ($_remove) {
                    unset($data[$this->name][$tr_field]);
                    continue;
                }

                $_update = false;
                // generate a new primary key id
                $db->execute('UPDATE translations_seq SET id=LAST_INSERT_ID(id+1);');
                $_res = $db->execute('SELECT LAST_INSERT_ID() AS id FROM translations_seq;');
                if ($_row = $db->fetchRow()) {
                    $_trans_id = $_row[0]['id'];
                } else {
                    $_trans_id = false;
                }
            }

            // don't create a new but empty translation
            if (!$_update && $_remove) {
                unset($data[$this->name][$tr_field]);
                continue;
            }
            if ($_update) {
                // update an existing translation
                if ($_remove) {
                    $_string = 'NULL';
                } else {
                    $_string = "'{$data[$this->name][$tr_field]}'";
                }
                $_res = $db->execute("UPDATE translations SET "
                    ."localized_string = {$_string}, "
                    ."modified = NOW() "
                    ."WHERE id = {$_trans_id} AND locale = '{$lang}';");
                $this->commit();
            } else {
                // insert a new translation
                $sql = "INSERT INTO translations (id, locale, localized_string, created) VALUES "
                    ."({$_trans_id}, '{$lang}', '{$data[$this->name][$tr_field]}', NOW());";
                $_res = $db->execute($sql);
            }

            // errors? don't go on
            if ($_res === false) {
                $errors = true;
                break;
            }

            // replace localized string by localization id in data to be saved
            $data[$this->name][$tr_field] = $_trans_id;
        }

        // return to default
        $this->translationReplace = true;
        // if something went wrong, roll back
        if ($errors) {
            $this->rollback();
            return false;
        } else {
            $this->data = $data;
            return true;
        }
    }

    /**
     * after saving successfully, commit the transaction
     */
    function afterSave() {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        if ($db->_transactionStarted) {
            return $this->commit();
        } else {
            return true;
        }
    }

    /**
     * start a transaction
     */
    function begin() {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        if (!$db->_transactionStarted) {
            if ($db->execute("START TRANSACTION") !== false) {
                $db->_transactionStarted = true;
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * rollback a transaction
     */
    function rollback() {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        if ($db->_transactionStarted) {
            $db->_transactionStarted = false;
            return ($db->execute("ROLLBACK") !== false);
        }
        return false;
    }

    /**
     * commit a transaction
     */
    function commit() {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        if ($db->_transactionStarted) {
            $db->_transactionStarted = false;
            return ($db->execute("COMMIT") !== false);
        }
        return false;
    }

    /**
     * Gets translations for all locales for the specific record and fields
     * @param int $id the primary key to pull from
     * @param array $fields optional array of fields to pull
     * @param bool $includeIDs whether to return the translation ids as well
     * @return array translations
     */
    function getAllTranslations($id, $fields = array(), $includeNULL = false, $returnIDs = false, $cache = false) {
        // If no fields passed, use all localizable fields in this model
        if (empty($fields) || !is_array($fields)) {
            $fields = $this->translated_fields;
        }

        $translations = array();

        // Pull the translation ids for the selected fields
        $tableInfo = $this->query("SELECT ".implode($fields, ', ')." FROM {$this->table} AS {$this->name} WHERE {$this->name}.id={$id}", $cache, $cache);
        if (!empty($tableInfo)) {
            foreach ($tableInfo[0][$this->name] as $field => $translation_id) {
                // If there's a translation id, add it to list to pull
                if (!empty($translation_id)) {
                    $translation_ids[$field] = $translation_id;
                }

                $translations[$field] = array();
            }
        }

        // Pull translations for all ids
        if (!empty($translation_ids)) {
            $where = $includeNULL ? '' : ' AND Translation.localized_string IS NOT NULL';
            $results = $this->query("SELECT * FROM translations AS Translation WHERE Translation.id IN (".implode($translation_ids, ',')."){$where}", $cache, $cache);
            if (!empty($results)) {
                foreach ($results as $result) {
                    $field = array_search($result['Translation']['id'], $translation_ids);
                    $translations[$field][$result['Translation']['locale']] = $result['Translation']['localized_string'];
                }
            }
        }
        else {
            // No translations found
            if ($returnIDs) {
                return array($translations, array());
            }
            else {
                return $translations;
            }
        }

        if ($returnIDs) {
            return array($translations, $translation_ids);
        }
        else {
            return $translations;
        }
    }

    /**
     * Save translations for new, updated, and deleted locales.
     * This should only be used for mass updating and is more efficient
     * for mass updating than normal save()ing
     * @param int $id id to update
     * @param array $rawData array of translation data not escaped
     * @param array $data array of translation data escaped
     */
    function saveTranslations($id, $rawData, $data) {
        // Pull all existing translations for fields to save
        $fields = array_keys($data);
        list($existing, $translation_ids) = $this->getAllTranslations($id, $fields, true, true);

        // Handle updated and deleted translations
        if (!empty($existing)) {
            foreach ($existing as $field => $translations) {
                if (!empty($translations)) {
                    foreach ($translations as $locale => $translation) {
                        if (isset($data[$field][$locale]) && empty($data[$field][$locale]) && !empty($translation)) {
                            // Translation was deleted
                            $this->execute("UPDATE translations SET localized_string=NULL, modified=NOW() WHERE id={$translation_ids[$field]} AND locale='{$locale}'");
                        }
                        elseif (!empty($rawData[$field][$locale]) && $rawData[$field][$locale] != $translation) {
                            // If not the same, the translation was updated
                            $this->execute("UPDATE translations SET localized_string='{$data[$field][$locale]}', modified=NOW() WHERE id={$translation_ids[$field]} AND locale='{$locale}'");
                        }
                        // Else, no changes

                        unset($data[$field][$locale]);
                    }
                }
            }
        }

        // Handle new translations
        if (!empty($data)) {
            foreach ($data as $field => $translations) {
                if (!empty($translations)) {
                    foreach ($translations as $locale => $translation) {
                        if (!empty($translation)) {
                            // Add new locale
                            if (!empty($translation_ids[$field])) {
                                // Translation id already set for this field
                                $this->execute("INSERT INTO translations (id, locale, localized_string, created) VALUES({$translation_ids[$field]}, '{$locale}', '{$data[$field][$locale]}', NOW())");
                            }
                            else {
                                // Translation id not yet set for this field, so we hand off to normal beforeSave() magic
                                if (in_array($field, $this->translated_fields)) {
                                    $this->setLang($locale);
                                    $this->id = $id;
                                    $save = array($field => $data[$field][$locale]);
                                    $this->save($save, false);
                                    $this->setLang(LANG);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Validate localized fields from translation box
     * @param array $data unescaped translation data
     * @return bool all data validated
     */
    function validateTranslations($data) {
        foreach ($data as $field => $translations) {
            if (!in_array($field, $this->translated_fields)) continue;
            foreach ($translations as $locale => $translation) {
                $this->invalidFields(array($this->name => array($field => $translation)));
                if (!empty($this->validationErrors)) return false;
            }
        }
        return true;
    }

    /**
     * Separates an array of data into localized fields and unlocalized fields
     */
    function splitLocalizedFields($data) {
        $localizedFields = array();
        $unlocalizedFields = array();

        if (!empty($data)) {
            foreach ($data as $field => $value) {
                if (in_array($field, $this->translated_fields)) {
                    $localizedFields[$field] = $value;
                }
                else {
                    $unlocalizedFields[$field] = $value;
                }
            }
        }

        return array($localizedFields, $unlocalizedFields);
    }

    /**
     * Strips fields that aren't in the specified whitelist
     */
    function stripFields($data, $allowedFields) {
        $safe = array();
        if (!empty($data)) {
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $safe[$field] = $value;
                }
            }
        }

        return $safe;
    }

}
?>
