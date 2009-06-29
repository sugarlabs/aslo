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

class Versioncomment extends AppModel
{
    var $name = "Versioncomment";

    var $belongsTo = array(
            'Version' => array(
                'className'  => 'Version',
                'conditions' => '',
                'order'      => '',
                'foreignKey' => 'version_id'
            ),
            'User' => array(
                'className'  => 'User',
                'conditions' => '',
                'order'      => '',
                'foreignKey' => 'user_id',
                'fields' => array('id','email','firstname','lastname','nickname')
            ),
    );

    var $belongsTo_full = array(
            'Version' => array(
                'className'  => 'Version',
                'conditions' => '',
                'order'      => '',
                'foreignKey' => 'version_id'
            ),
            'User' => array(
                'className'  => 'User',
                'conditions' => '',
                'order'      => '',
                'foreignKey' => 'user_id',
                'fields' => array('id','email','firstname','lastname','nickname')
            ),
    );

    var $hasAndBelongsToMany_full = array(
            'Subscriber' => array(
                'className' => 'User',
                'joinTable' => 'users_versioncomments',
                'associationForeignKey' => 'user_id',
                'foreignKey' => 'comment_id',
                'conditions' => 'users_versioncomments.subscribed=1',
                'fields' => array('id','email','firstname','lastname','nickname')
            ),
    );

    var $validate = array(
        'subject' => VALID_NOT_EMPTY,
        'comment' => VALID_NOT_EMPTY
    );


    /**
     * Return all comments for a version, sorted by thread and with depth information
     *
     * @param int $versionId - the id of a version
     * @return array of comments
     */
    function getThreadTree($versionId) {
        $comments = $this->findAll(array('Versioncomment.version_id' => $versionId),
                                    null, 'Versioncomment.reply_to, Versioncomment.created');
        $tree = array();
        foreach ($comments as &$node) {
            if (!is_null($node['Versioncomment']['reply_to'])) {
                // sorting by reply_to puts all the root nodes at top
                // once we stop seeing reply_to nulls, we're done with root nodes
                break;
            }

            // build a flattened tree for each root node
            array_splice($tree, count($tree), 0, $this->_depthFirstSearch($node, $comments, 0));
        }
        return $tree;
    }
    
    /**
     * Returns the comment at the root of the thread containing the specified comment
     *
     * @param int $versionId - the id of a version
     * @param int $commentId - the id of a comment
     * @return array or null if not found
     */
    function getThreadRoot($versionId, $commentId) {
        $root = null;

        // fetch all comments tied to this version
        $comments = $this->findAll(array('Versioncomment.version_id' => $versionId),
                                    null, 'Versioncomment.reply_to, Versioncomment.created', null, null, -1);

        // iteratively search for a node and its ancestors (yes, recursion would be prettier)
        $nextId = $commentId;
        while ($nextId) {
            $searchId = $nextId;
            $nextId = null;

            foreach ($comments as &$node) {
                if ($node['Versioncomment']['id'] == $searchId) {
                    // found the node...
                    if (empty($node['Versioncomment']['reply_to'])) {
                        return $node; // ...and we have the root!
                    } 

                    // ...and we start the search over for the next ancestor
                    $nextId = $node['Versioncomment']['reply_to'];
                    break;
                }
            }
        }
        return null;
    }

    /**
     * Returns the number comments in a version
     *
     * @param int $versionId - the id of a version
     * @return int
     */
    function getCommentCount($versionId) {
        if (!is_numeric($versionId)) return 0;

        $results = $this->query("SELECT COUNT(*) AS total
                                    FROM versioncomments
                                    WHERE version_id='{$versionId}'");

        return $results ? $results[0][0]['total'] : 0;
    }

    /**
     * Subscribes a user to a comment thread
     *
     * @param int $id - the id of a comment
     * @param int $userId - the id of the user
     * @param bool $force - subscribes unconditionally even if already unsubscribed
     * @return void
     */
    function subscribe($id, $userId, $force=false) {
        // Perform SQL escaping.
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $e_id     = $db->value($id); 
        $e_userId = $db->value($userId);

        // dont overwrite existing subscription
        if (!$force) {
            $rows = $this->query("SELECT *
                                    FROM users_versioncomments AS uvc
                                    WHERE user_id='{$e_userId}' AND comment_id='{$e_id}'");
            if (!empty($rows)) return;
        }

        $this->query("REPLACE INTO users_versioncomments
                        (user_id, comment_id, subscribed, created, modified)
                        VALUES ('{$e_userId}', '{$e_id}', 1, NOW(), NOW())");
    }
    
    /**
     * Unsubscribe a user from a comment thread
     *
     * @param int $id - id of the treads root comment
     * @param int $userId - id of user
     * @return void
     */
    function unsubscribe($id, $userId) {
        // Perform SQL escaping.
        $db =& ConnectionManager::getDataSource($this->useDbConfig);
        $e_id     = $db->value($id); 
        $e_userId = $db->value($userId);

        $this->query("REPLACE INTO users_versioncomments
                        (user_id, comment_id, subscribed, created, modified)
                        VALUES ('{$e_userId}', '{$e_id}', 0, NOW(), NOW())");
    }
    
    /**
     * Get the ids of all root comments (threads) subscribed to by a user
     * @param int $userId
     * @return array versioncomment ids, false on error
     */
    function getSubscriptionsByUser($userId) {
        if (!is_numeric($userId)) return false;

        $res = $this->query("SELECT comment_id FROM users_versioncomments WHERE user_id = {$userId}");
        if (empty($res)) return array();

        $ids = array();
        foreach ($res as &$row) $ids[] = $row['users_versioncomments']['comment_id'];
        return $ids;
    }

    /**
     * Get the ids of all users subscribed to a thread's root comment
     * @param int $commentId
     * @return array user ids, false on error
     */
    function getSubscribers($commentId) {
        if (!is_numeric($commentId)) return false;

        $res = $this->query("SELECT user_id FROM users_versioncomments
                                WHERE comment_id = {$commentId} AND subscribed = 1");
        if (empty($res)) return array();

        $ids = array();
        foreach ($res as &$row) $ids[] = $row['users_versioncomments']['user_id'];
        return $ids;
    }


    /**
     * Returns a comment and all decendents in its thread tree
     * Tree depth is added to each node in the returned results
     * @param array $node (by reference) - root comment
     * @param array $allNodes (by reference) - set of all comments to search
     * @param int $depth - depth of node
     * @return array - flattened comment tree
     */
    function _depthFirstSearch(&$node, &$allNodes, $depth) {
        if (array_key_exists('depth', $node)) {
            // we have already visited this node - shouldn't happen, but be safe
            return array();
        }
        $node['depth'] = $depth;
        $toReturn = array(&$node);
        foreach ($allNodes as &$subNode) {
            // look for descendants , recurse, and append to results
            if ($subNode['Versioncomment']['reply_to'] === $node['Versioncomment']['id']) {
                // the ugly php equivalent of python's somelist.extend(anotherlist)
                // "replace 0 items at the end of the array with all items in this other array"
                array_splice($toReturn, count($toReturn), 0,
                    $this->_depthFirstSearch($subNode, $allNodes, $depth+1));
            }
        }
        return $toReturn;
    }
}
