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

class AddonCollection extends AppModel
{
    var $name = "AddonCollection";
    var $useTable = 'addons_collections';
    var $belongsTo = array('Addon', 'Collection');
    var $recursive = -1;
    var $translated_fields = array(
        'comments'
    );

    /**
     * Delete any addon/collection associations by addon_id and collection_id
     *
     * @param int $addon_id
     * @param int $collection_id
     * @param int $user_id optional: only delete the association if $user_id was the publisher.
     * @return bool success (false on error or when nothing was deleted)
     */
    function deleteByAddonIdAndCollectionId($addon_id, $collection_id, $user_id = null) {
        if (!is_numeric($addon_id)) return false;
        if (!is_numeric($collection_id)) return false;
        if (!empty($user_id) && !is_numeric($user_id)) return false;

        $sql = "DELETE FROM addons_collections WHERE addon_id={$addon_id} AND collection_id={$collection_id}";
        if (!empty($user_id)) $sql .= " AND user_id = {$user_id}";

        $res = $this->query($sql);
        return (!($res === false || $this->getAffectedRows() == 0));
    }

    /**
     * Get a list of add-ons belonging to this collection
     *
     * @param int $collection_id Collection ID
     * @return array list of add-ons
     */
    function getAddonsFromCollection($collection_id) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $collection_id = $db->value($collection_id);
        $this->unbindFully();
        $res = $this->findAll(array('collection_id' => $collection_id));
        if (!empty($res)) {
            loadModel('Addon');
            loadModel('User');
            if (empty($this->Addon)) $this->Addon =& new Addon();
            if (empty($this->User)) $this->User =& new User();
            foreach ($res as &$row) {
                $row['Addon'] = $this->Addon->getAddon($row['AddonCollection']['addon_id']);
                $user = $this->User->findById($row['AddonCollection']['user_id']);
                $row['User'] = $user['User'];
            }
        }
        return $res;
    }

    /**
     * set the publisher comment for a specific add-on in a collection (for now,
     * en-US only)
     *
     * @param int $collection_id
     * @param int $addon_id
     * @param string $comment comment to be saved
     * @return bool success
     */
    function setComment($collection_id, $addon_id, $comment) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $collection_id = $db->value($collection_id);
        $addon_id = $db->value($addon_id);
        $comment = $db->value($comment);

        $row = $this->query("SELECT comments FROM addons_collections "
            ."WHERE collection_id = {$collection_id} AND addon_id = {$addon_id}");
        if (empty($row)) return false; // not found or access denied

        $this->begin(); // transactions are our friends

        if (!empty($row[0]['addons_collections']['comments'])) {
            $id = $row[0]['addons_collections']['comments'];
        } else {
            // generate a new primary key id
            $db->execute('UPDATE translations_seq SET id=LAST_INSERT_ID(id+1);');
            $_res = $db->execute('SELECT LAST_INSERT_ID() AS id FROM translations_seq;');
            if ($_row = $db->fetchRow()) {
                $id = $_row[0]['id'];
            } else {
                $this->rollback();
                return false;
            }
        }
        // delete all existing comments
        $db->execute("UPDATE translations SET localized_string = NULL WHERE id = {$id}");

        // insert the new one, if applicable (en-US only)
        if (!empty($comment)) {
            $db->execute("INSERT INTO translations (id, locale, localized_string, created) "
                ."VALUES ({$id}, 'en-US', {$comment}, NOW()) "
                ."ON DUPLICATE KEY UPDATE localized_string=VALUES(localized_string), modified=VALUES(created);");
        }
        // link comments field to translations
        $res = $db->execute("UPDATE addons_collections SET comments = {$id} "
            ."WHERE collection_id = {$collection_id} AND addon_id = {$addon_id}");
        if (false !== $res) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * is an add-on part of a given collection?
     *
     * @param int addon_id
     * @param int collection_id
     * @return bool true if addon is in collection, false otherwise
     */
    function isAddonInCollection($addon_id, $collection_id) {
        if (!is_numeric($collection_id) || !is_numeric($addon_id)) return null;
        $res = $this->query("SELECT addon_id FROM addons_collections WHERE addon_id = {$addon_id} AND collection_id = {$collection_id};");
        return (!empty($res));
    }

    /**
     * get most popular collections that a given add-on is part of
     * @param int $addon_id
     * @param int $limit (optional) max. amount of collections returned, default 3
     * @param string $app (optional) application ID to restrict search to, default all
     * @return array collection ids
     */
    function getPopularCollectionsForAddon($addon_id, $limit = 3, $app = null, $includeprivate = false) {
        if (!is_numeric($addon_id)) return false;

        if (is_numeric($limit) && $limit > 0)
            $_lim = " LIMIT {$limit}";
        else
            $_lim = '';

        if (is_numeric($app) && $app > 0) {
            $_where = " AND c.application_id = {$app}";
        } else {
            $_where = '';
        }

        if (!$includeprivate) {
            $_where .= ' AND listed=1';
        }

        $colls = array();
        $res = $this->query(
            "SELECT collection_id "
            ."FROM addons_collections AS ac "
            ."INNER JOIN collections AS c ON (c.id = ac.collection_id) "
            ."WHERE addon_id = {$addon_id}{$_where} "
            ."ORDER BY c.subscribers DESC"
            .$_lim.";");
        foreach ($res as &$row) {
            $colls[] = $row['ac']['collection_id'];
        }
        return $colls;
    }

    /**
     * count amount of collections that a given add-on is in
     * @param int $addon_id
     * @return int collection count >= 0, false in case of error
     * @param string $app (optional) application ID to restrict search to, default all
     */
    function getCollectionCountForAddon($addon_id, $app = null) {
        if (!is_numeric($addon_id)) return false;

        if (is_numeric($app) && $app > 0) {
            $_join = " INNER JOIN collections AS c ON (c.id = ac.collection_id AND c.application_id = {$app})";
        } else {
            $_join = '';
        }

        $res = $this->query(
            "SELECT COUNT(ac.collection_id) AS cnt "
            ."FROM addons_collections AS ac "
            . $_join
            ."WHERE ac.addon_id = {$addon_id};");
        if (!$res) return false;
        return $res[0][0]['cnt'];
    }

}
