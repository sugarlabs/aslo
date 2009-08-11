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
 *   Ryan Doherty <rdoherty@mozilla.com>
 *   l.m.orchard <lorchard@mozilla.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
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

class Collection extends AppModel
{
    var $name = "Collection";

    var $hasAndBelongsToMany = array(
        'Addon' => array(
            'className' => 'Addon',
            'joinTable' => 'addons_collections',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'addon_id'
        ),
        'Subscriptions' => array(
            'className' => 'User',
            'joinTable' => 'collection_subscriptions',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'user_id'
        ),
        'Users' => array(
            'className' => 'User',
            'joinTable' => 'collections_users',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'user_id'
        )
    );
    
    var $hasAndBelongsToMany_full = array(
        'Category' => array(
            'className'  => 'Category',
            'joinTable'  => 'collections_categories',
            'foreignKey' => 'collection_id',
            'associationForeignKey'=> 'category_id'
        ),
        'Addon' => array(
            'className' => 'Addon',
            'joinTable' => 'addons_collections',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'addon_id'
        ),
        'Subscriptions' => array(
            'className' => 'User',
            'joinTable' => 'collection_subscriptions',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'user_id'
        ),
        'Users' => array(
            'className' => 'User',
            'joinTable' => 'collections_users',
            'foreignKey' => 'collection_id',
            'associationForeignKey' => 'user_id'
        )
    );

    var $hasMany_full = array(
                         'Promo' =>
                         array('className'   => 'CollectionPromo',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'collection_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         )
                    );


    var $validate = array(
        'name'        => VALID_NOT_EMPTY,
        'description' => VALID_NOT_EMPTY
    );
    
    var $translated_fields = array(
        'name',
        'description',
    );

    /**
     * validate name field
     */
    function clean_name($input) {
        $msg = sprintf(___('collection_name_limit'), Collection::MAX_NAME_LENGTH);
        $this->maxLength('name', $input, Collection::MAX_NAME_LENGTH, $msg);
    }

    /**
     * validate description field
     */
    function clean_description($input) {
        $msg = sprintf(___('collection_description_limit'), Collection::MAX_DESC_LENGTH);
        $this->maxLength('description', $input, Collection::MAX_DESC_LENGTH, $msg);
    }

    /**
     * validate nickname field
     */
    function clean_nickname($input) {
        if (preg_match(INVALID_COLLECTION_NICKNAME_CHARS, $input)
            || $this->isNicknameTaken($input)) {
            $this->invalidate('nickname');
        }
    }

    const COLLECTION_TYPE_NORMAL = 0;
    const COLLECTION_TYPE_AUTOPUBLISHER = 1;
    const COLLECTION_TYPE_EDITORSPICK = 2;

    const MAX_NAME_LENGTH = 50;
    const MAX_DESC_LENGTH = 200;

    /**
     * Generates a pseudo-random UUID.
     * Slightly modified version of a function submitted to php.net:
     * http://us2.php.net/manual/en/function.com-create-guid.php#52354
     *
     * @access public
     */
    function uuid() {
        mt_srand((double)microtime()*10000);
        $charid = md5(uniqid(rand(), true));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
              . substr($charid, 8, 4).$hyphen
              . substr($charid,12, 4).$hyphen
              . substr($charid,16, 4).$hyphen
              . substr($charid,20,12);

        return $uuid;
    } 

    /**
     * Verifies a UUID's structure (not that it actually exists in our database).
     *
     * @param string uuid
     * @return boolean
     */
    function isValidUUID($uuid) {
        if (preg_match(VALID_UUID_REQ, $uuid) > 0) {
            return true;
        }
        return false;
    } 

    /**
     * Before saving, set a UUID if none yet set.
     */
    function beforeSave() {
        if (empty($this->id) && empty($this->data[$this->name]['id'])) {
            // If no ID set yet, assume this is a new record and give it a UUID
            $this->data[$this->name]['uuid'] = $this->uuid();
        }

        // if nickname is set, make sure it's unique
        if (!empty($this->data[$this->name]['nickname'])) {
            if ($this->isNicknameTaken($this->data[$this->name]['nickname'])) {
                $this->invalidate('nickname');
                return false;
            }
        } else {
            $this->data[$this->name]['nickname'] = null;
        }

        return parent::beforeSave();
    }

    /**
     * Subscribes a user to a collection
     *
     * @param int $id - the id of the collection
     * @param int $userId - the id of the user
     * @return bool success
     */
    function subscribe($id, $userId) {
        // Perform SQL escaping.
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $e_id     = $db->value($id); 
        $e_userId = $db->value($userId);

        $this->execute("
            INSERT IGNORE INTO collection_subscriptions
            (collection_id, user_id, created, modified)
            VALUES 
            ({$e_id}, {$e_userId}, NOW(), NOW())
        ");
        return ($this->getAffectedRows() > 0);
    }
    
    /**
     * Unsubscribe a user to a collection
     *
     * @param int $id - id of the collection
     * @param int $userId - id of user
     * @return bool success
     */
    function unsubscribe($id, $userId) {
        // Perform SQL escaping.
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $e_id     = $db->value($id); 
        $e_userId = $db->value($userId);

        $this->execute("
            DELETE FROM collection_subscriptions 
            WHERE 
                user_id = {$e_userId} AND
                collection_id = {$e_id}
        ");
        return ($this->getAffectedRows() > 0);
    }
    
    /**
     * Update the subscribers count for a collection.
     *
     * @param int $id - id of the collection
     */
    function _updateSubscribersCount($e_id) {
        return $this->execute("
            UPDATE collections 
            SET subscribers=(
                SELECT count(collection_id) 
                FROM collection_subscriptions 
                WHERE collection_id={$e_id}
            ) 
            WHERE id={$e_id}
        ");
    }
    
    /**
     * Add an add-on to a collection
     *
     * @param int $collectionId - collection id
     * @param int $addonId - add-on id
     */
    function addAddonToCollection($collection_id, $user_id, $addon_id) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        
        $collection_id = $db->value($collection_id);
        $user_id = $db->value($user_id);
        $addon_id = $db->value($addon_id);

        $ret = $this->execute("
            INSERT INTO addons_collections 
            (addon_id, user_id, collection_id, added) 
            VALUES 
            ({$addon_id}, {$user_id}, {$collection_id}, NOW())
        ");
        return $ret;
    }

    /* Update the addon count for collection $id. */
    function _updateAddonCount($id) {
        return $this->execute("
            UPDATE collections
            SET addonCount=(SELECT COUNT(*)
                            FROM addons_collections
                            WHERE collection_id={$id})
            WHERE id=${id}");
    }

    /**
     * Adds a user to a collection so they can edit it
     *
     * @param int $collectionId - id of the collection
     * @param int $userId - id of the user
     * @param int $role - role type
     */
    function addUser($collectionId, $userId, $role) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);

        $collectionId = $db->value($collectionId);
        $userId = $db->value($userId);
        $role = $db->value($role);

        return $this->execute("INSERT INTO collections_users (collection_id, user_id, role) VALUES ({$collectionId}, {$userId}, {$role})");
    }
    
    /**
     * Remove a user
     *
     * @param int $collectionId - id of the collection
     * @param int $userId - id of the user
     */
    function removeUser($collectionId, $userId) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);

        $collectionId = $db->value($collectionId);
        $userId = $db->value($userId);

        return $this->execute("DELETE FROM collections_users WHERE user_id = {$userId} AND collection_id={$collectionId}");
    }

    /**
     * Remove all user rights from a collection, by role
     *
     * @param int $collection_id
     * @param int $role user role to remove, for example COLLECTION_ROLE_ADMIN
     */
    function removeAllUsersByRole($collection_id, $role) {
        return $this->execute("DELETE FROM collections_users WHERE collection_id={$collection_id} AND role={$role};");
    }

    /**
     * Remove all user rights from a collection, by role
     *
     * @param int $collection_id
     * @param int $role user role to remove, for example COLLECTION_ROLE_ADMIN
     * @param int $userid user id, or array of user ids to exempt from deletion
     */
    function removeAllUsersByRoleExcept($collection_id, $role, $userid = array()) {
        if (!is_array($userid)) $userid = array($userid);
        return $this->execute(
            "DELETE FROM collections_users "
            ."WHERE collection_id={$collection_id} "
            ."AND role={$role} "
            ."AND user_id NOT IN (".implode(',', $userid).');');
    }

    /**
     * Deletes a collection
     *
     * @param int $id - collection id
     */
    function delete($id) {
        $this->execute("DELETE FROM collections_users WHERE collection_id = {$id}");
        $this->execute("DELETE FROM addons_collections WHERE collection_id = {$id}");
        $this->execute("DELETE FROM collections_categories WHERE collection_id = {$id}");
        $this->execute("DELETE FROM collection_subscriptions WHERE collection_id = {$id}");
        $this->execute("DELETE FROM collection_promos WHERE collection_id = {$id}");
        $this->execute("DELETE FROM collections WHERE id = {$id}");
        return true;
    }

    /**
     * returns the ids of all collections owned or managed by a given user
     * @param int $userid
     * @param mixed $roles (optional) array of allowed roles, defaults to "any role"
     * @return array collection ids, false on error
     */
    function getCollectionsByUser($userid, $roles = null) {
        if (!is_numeric($userid)) return false;

        $role_sql = '';
        if (!empty($role)) {
            if (is_scalar($roles)) $roles = array($roles);
            foreach ($roles as &$role) if (!is_numeric($role)) return false;
            $role_sql = ' AND role IN ('.implode(',', $roles).')';
        }

        $res = $this->query("SELECT collection_id FROM collections_users WHERE user_id = {$userid}{$role_sql}");
        if (empty($res)) return array();

        $ids = array();
        foreach ($res as &$row) $ids[] = $row['collections_users']['collection_id'];
        return $ids;
    }

    /**
     * returns the ids of all collections subscribed to by a given user
     * @param int $userid
     * @return array collection ids, false on error
     */
    function getSubscriptionsByUser($userid) {
        if (!is_numeric($userid)) return false;

        $res = $this->query("SELECT collection_id FROM collection_subscriptions WHERE user_id = {$userid}");
        if (empty($res)) return array();

        $ids = array();
        foreach ($res as &$row) $ids[] = $row['collection_subscriptions']['collection_id'];
        return $ids;
    }

    /**
     * Get a list of users and roles
     * 
     * @param int id of the collection
     * @param array (optional) list of roles for which users should be fetched
     * @param array (optional) list of user IDs to be excluded
     */
    function getUsers($collectionId, $roles=null, $exclude=null) {
        if (!is_numeric($collectionId)) return null;
        
        // Build SQL to look up user IDs and roles for collection
        $sql = "
            SELECT user_id, role 
            FROM collections_users 
            WHERE collection_id={$collectionId}
        ";

        // Add an IN clause if roles supplied.
        if (null !== $roles && is_array($roles)) {
            $s_roles = array();
            foreach ($roles as $role) if (is_numeric($role)) 
                $s_roles[] = $role;
            $sql .= " AND role IN ( ". join(',', $s_roles) . " )";
        }

        // Fetch the rows and map them to user IDs.
        $rows = $this->execute($sql);
        $user_map = array();
        foreach ($rows as $row) {
            if (is_array($exclude) && in_array($row['collections_users']['user_id'], $exclude)) continue;
            $user_map[$row['collections_users']['user_id']] = $row['collections_users'];
        }

        // Look up users with user IDs, merge the role info into each found.
        $users = $this->User->findAllById(array_keys($user_map));
        for ($i=0; $i<count($users); $i++) {
            // HACK: CollectionUser used in lieu of an actual model class.
            $users[$i]['CollectionUser'] =
                $user_map[$users[$i]['User']['id']];
        }

        return $users;
    }

    /**
     * Decide whether or not a given collection is writable by a user.
     * 
     * @param int id of the collection
     * @param int id of the user
     */
    function isWritableByUser($collection_id, $user_id) {
        if (!is_numeric($collection_id)) return null;
        if (!is_numeric($user_id)) return null;

        $role = $this->getUserRole($collection_id, $user_id);
        if ($role === false) return false; // no access rights
        return in_array($role, array(
            COLLECTION_ROLE_ADMIN, 
            COLLECTION_ROLE_PUBLISHER 
        ));
    }

    /**
     * Determine a user's role for a collection (admin, subscriber...).
     *
     * @param int $collection_id
     * @param int $user_id
     * @return role ID, false if none
     */
    function getUserRole($collection_id, $user_id) {
        if (!is_numeric($collection_id)) return null;
        if (!is_numeric($user_id)) return null;

        $rows = $this->execute("
            SELECT role
            FROM collections_users 
            WHERE 
                collection_id={$collection_id} AND
                user_id={$user_id}
        ");
        if (empty($rows)) return false;
        return (int)$rows[0]['collections_users']['role'];
    }

    /**
     * Look up the ID for a collection by UUID, less expensive than a full 
     * fetch.
     *
     * @param   string Collection UUID
     * @return  string Collection ID
     */
    function getIdForUuid($uuid) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $uuid = $db->value($uuid);
        $rows = $this->execute("
            SELECT id
            FROM collections
            WHERE uuid={$uuid}
        ");
        $id = null;
        if (!empty($rows[0])) {
            $id = $rows[0]['Collection']['id'];
        }
        return $id;
    }

    /**
     * Look up ID for a UUID or nickname.
     *
     * @param string UUID or nickname
     * @return int collection ID
     */
    function getIdForUuidOrNickname($uuid_or_nickname) {
        $id = null;
        if (strlen($uuid_or_nickname) == 36) { // possibly a UUID
            $id = $this->getIdForUuid($uuid_or_nickname);
        }
        if (is_null($id)) { // try nickname
            $db =& ConnectionManager::getDataSource($this->useDbConfig);
            $uuid_or_nickname = $db->value($uuid_or_nickname);
            $rows = $this->execute("
                SELECT id
                FROM collections
                WHERE nickname={$uuid_or_nickname}
            ");
            if (!empty($rows[0])) {
                $id = $rows[0]['Collection']['id'];
            }
        }
        return $id;
    }

    /**
     * Determine the last modified time for a collection, found either by
     * ID or UUID.  If a UUID is supplied, it's converted to an ID via 
     * query first.
     *
     * @param   string Collection ID
     * @param   string Collection UUID, replaces ID if supplied
     * @return  string Last modified date for collection and addons
     */
    function getLastModifiedForCollection($id=null, $uuid=null) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);

        if (null !== $uuid) {
            $id = $this->getIdForUUID($uuid);
        }

        $id = $db->value($id);

        $dates = array();

        $rows = $this->execute("
            SELECT added, modified
            FROM addons_collections
            WHERE collection_id={$id}
            ORDER BY added DESC
            LIMIT 1
        ");
        foreach ($rows as $row) {
            $dates[] = $row['addons_collections']['added'];
            $dates[] = $row['addons_collections']['modified'];
        }
            
        $rows = $this->execute("
            SELECT modified
            FROM collections
            WHERE id={$id} 
        ");
        foreach ($rows as $row) {
            $dates[] = $row['Collection']['modified'];
        }

        if (empty($dates)) return null;
        rsort($dates);
        return strtotime($dates[0]);
    }

    /**
     * is collection nickname taken yet?
     * @param string $nickname proposed nickname
     * @return bool true if nickname is occupied already, false otherwise
     */
    function isNicknameTaken($nickname) {
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $nickname = $db->value($nickname);
        $res = $this->query("SELECT id FROM collections WHERE nickname = {$nickname}");
        if (empty($res)) return false;
        if (empty($this->id))
            return (!empty($res));
        else
            return ($this->id != $res[0]['collections']['id']);
    }

    function isSubscribed($collection_id, $user_id) {
        $res = $this->execute("SELECT user_id FROM collection_subscriptions
                               WHERE user_id={$user_id}
                                 AND collection_id={$collection_id}");
        return !empty($res);
    }

    function getNickname($collection) {
        $c = $collection['Collection'];
        return isset($c['nickname']) ? $c['nickname'] : $c['uuid'];
    }

    function getDetailUrl($collection) {
        return '/collection/' .  $this->getNickname($collection);
    }

    function getSubscribeUrl($ajax=false) {
        return '/collections/subscribe/' . ($ajax === true ? 'ajax' : '');
    }

    function getUnsubscribeUrl($ajax=false) {
        return '/collections/unsubscribe/' . ($ajax === true ? 'ajax' : '');
    }

    /**
     * Get editor's picks for the current application.
     *
     * @param limit the number you want
     * @return array of collections
     */
    function getEditorPicks($limit=null) {
        $conditions = array(
            'Collection.collection_type' => Collection::COLLECTION_TYPE_EDITORSPICK,
            'Collection.application_id'  => APP_ID
        );
        $this->unbindFully();
        $collections = $this->findAll($conditions,null,'Collection.downloads DESC',$limit);
        return $collections;
    }
}
