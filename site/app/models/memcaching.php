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
 * Mozilla Corporation.
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Frederic Wenzel <fwenzel@mozilla.com> (Original Author)
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

/**
 * This model is an interface to Memcache.
 * It's called Memcaching to not interfere with the actual Memcache class.
 */

class Memcaching extends Model {
    var $cache;             // holds the memcache object
    var $memcacheConnected; // did we find a valid memcache server?
    var $config;            // holds global memcache config

    function __construct() {
        global $memcache_config;
        $this->config = $memcache_config;

        if (class_exists('Memcache') && is_array($this->config))
            $this->cache = new Memcache();
        else
            return false;

        foreach ($this->config as $host=>$options) {
            if ($this->cache->addServer($host, $options['port'], $options['persistent'], $options['weight'], $options['timeout'], $options['retry_interval'])) {
                $this->memcacheConnected = true;
            }
        }

        if (!$this->memcacheConnected)
            error_log('Memcache Error: Unable to connect to memcache server.  Please check configuration and try again.');
    }

    /**
     * Get an item from the cache, if it exists
     * @return mixed item if found, else false
     */
    function get($key) {
        if (!$this->memcacheConnected) return false;
        return $this->cache->get($key);
    }

    /**
     * Store an item in the cache. Replaces an existing item.
     * @return bool success
     */
    function set($key, $var, $flag = null, $expire = CACHE_PAGES_FOR) {
        if (!$this->memcacheConnected) return false;
        return $this->cache->set($key, $var, $flag, $expire);
    }
    
    /**
     * Store an item in the cache. Returns false if the key is
     * already present in the cache.
     * @return bool success
     */
    function add($key, $var, $flag = null, $expire = CACHE_PAGES_FOR) {
        if (!$this->memcacheConnected) return false;
        return $this->cache->add($key, $var, $flag, $expire);
    }

    /**
     * Store an item in the cache. Returns false if the key did
     * NOT exist in the cache before.
     * @return bool success
     */
    function replace($key, $var, $flag = null, $expire = CACHE_PAGES_FOR) {
        if (!$this->memcacheConnected) return false;
        return $this->cache->replace($key, $var, $flag, $expire);
    }

    /**
     * Close the connection to _ALL_ cache servers
     * @return bool success
     */
    function close() {
        if (!$this->memcacheConnected) return false;
        return $this->cache->close();
    }

    /**
     * Delete something off the cache
     * @return bool success
     */
    function delete($key, $timeout = null) {
        if (!$this->memcacheConnected) return false;
        return $this->cache->delete($key, $timeout);
    }

    /**
     * Flush the cache
     * @return bool success
     */
    function flush() {
        foreach ($this->config as $server=>$params) {
            $m = new Memcache;
            if (!$m->connect($server,$params['port']) || !$m->flush()) {
                return false;
            }
            $m->close();
        }
        return true;
    }

    /**
     * Get server statistics.
     * return array
     */
    function getExtendedStats() {
        if (!$this->memcacheConnected) return false;
        return $this->cache->getExtendedStats();
    }
    
    
    /* * * Object-based memcaching * * */
    
    /**
     * get object from memcache
     * 
     * @param array $identifier unique object identifier
     * @return mixed cached object if found, false otherwise
     */
    function readCacheObject($identifier) {
        $cachekey = $this->_generateCacheKey($identifier);
        return $this->get($cachekey);
    }
    
    /**
     * write object to memcache, then add it to cache invalidation list(s)
     * 
     * @param array $identifier unique object identifier
     * @param array $data the object
     * @param array $invalidlists invalidation lists
     * @param int $expiration (optional) time-to-live
     * @return bool success
     */
    function writeCacheObject($identifier, $data, $invalidlists, $expiration = CACHE_PAGES_FOR) {
        $cachekey = $this->_generateCacheKey($identifier);
        $res = $this->set($cachekey, $data, null, $expiration);
        if (!$res) return false;
        
        $this->_generateExpirationIDs($invalidlists);
        // add this object to each expiration list
        foreach ($invalidlists as $listid) {
            $this->_addObjectToExpirationList($cachekey, $listid);
        }
        
        return true;
    }
    
    /**
     * add an object's cache key to an invalidation list so that it is removed
     * when that list is flushed
     * 
     * @param string $cachekey memcache ID of the object to put on the list
     * @param string $listid id of the invalidation list to put it on
     * @return void
     */
    function _addObjectToExpirationList($cachekey, $listid) {
        $memcache_listid = MEMCACHE_PREFIX.'expirationlist:'.$listid;
        // fetch list from memcache if present, otherwise make a new one
        $exp_list = $this->get($memcache_listid);
        if (!$exp_list || !is_array($exp_list)) $exp_list = array();
        
        // add object to list, make sure it's no duplicate, store it
        if (!in_array($cachekey, $exp_list)) {
            $exp_list[] = $cachekey;
            $res = $this->set($memcache_listid, $exp_list, null, 0);
            
            // in debug mode, display what we did
            if (DEBUG >= 2) debug("updated $listid, replaced with ".print_r($exp_list, true));
        }
    }
    
    /**
     * Mark a cache invalidation list for flushing (usually executed from afterSave())
     * We're not actually flushing the cache here because afterSave() is
     * called after every query.  Instead, we'll just mark this list as needing
     * flushed for the controller to pick up when we're done.
     *
     * @param string list id containing objects to be flushed
     * @return void
     */
    function markListForFlush($listid) {
        global $flush_lists;
        $memcache_listid = MEMCACHE_PREFIX.'expirationlist:'.$listid;
        $flush_lists[] = $memcache_listid;
        if (DEBUG >= 2) debug("marked $listid for flush");
    }
    
    /**
     * Flush cache lists that have been marked for flush (executed from
     * AppController::afterFilter())
     * 
     * @return void
     */
    function flushMarkedLists() {
        global $flush_lists;
        
        // If this isn't empty, it's holding the names of what we need to flush.
        if (empty($flush_lists)) return;
        
        foreach ($flush_lists as $val) {
            // get list from the cache and delete it
            $objects = $this->get($val);
            if (DEBUG >= 2) debug("flushing list $val");
            if (false === $objects) continue;
            $this->delete($val);
            
            // delete each cache object on the list
            foreach ($objects as $cacheobject) {
                $this->delete($cacheobject);
                if (DEBUG >= 2) debug("flushing object $cacheobject off list $val");
            }
        }
    }

    /**
     * generate unique key hash to store/retrieve objects to/from memcache
     *
     * @param mixed $identifier string or array capable of unmistakably identifying the object
     * @return string hash to be used as memcache ID
     */
    function _generateCacheKey($identifier) {
        // attach language and app
        $key = LANG.':'.APP_ID.':';
        // serialize the identifier
        $key .= serialize($identifier);
        
        return MEMCACHE_PREFIX.md5($key);
    }
    
    /**
     * (recursively and by reference) generate a list of cache expiration
     * identifiers from shorthand notation, i.e., transforms:
     *      array('addon' => array(1,2,3)) to
     *      array('addon:1', 'addon:2', 'addon:3')
     *
     * @param mixed $shorthand string or array containing a list of list ids, possibly in shorthand notation
     * @return void
     */
    function _generateExpirationIDs(&$shorthand) {
        if (is_string($shorthand)) {
            $shorthand = array($shorthand);
            return;
        }
        foreach ($shorthand as $key => $val) {
            if (is_string($val)) {
                continue;
            } elseif (is_array($val)) {
                // convert sub-array to strings
                $this->_generateExpirationIDs($val);
                // add strings to regular array
                foreach ($val as $subtext)
                    $shorthand[] = $key.':'.$subtext;
                unset($shorthand[$key]);
            }
            
        }
    }
}
?>
