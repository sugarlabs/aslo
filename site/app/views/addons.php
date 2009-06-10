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

class AddonsView extends View
{
    var $caching = QUERY_CACHE; // query caching enabled?

    // add to the next array any elements you  want cached. The array after the => are default query string parameters to add to the renderElement parameters
    // (this was mainly done so that the calls to advanced search wouldn't need to be rewritten from the code from before element caching,
    // the hard case are query strings parameters used to toggle advanced search when JS is off ).
    var $cacheableElts = array("search" => array('adv', 'nor'), "categories" => array()); 
				
    var $helpers = array('AddonsHtml');

    function __construct(&$controller) {
        if ($this->caching) {
            loadModel('Memcaching');
            $this->Cache = new Memcaching();
        }
        return parent::__construct($controller);
    }
				
    /**
     * loads helpers as defined by the regular View class, but replaces
     * the original HtmlHelper by our custom AddonsHtmlHelper
     */
    function &_loadHelpers(&$loaded, $helpers) {
        $helpers[] = 'AddonsHtml';
        $helpers[] = 'Javascript';
        $loaded = parent::_loadHelpers($loaded, $helpers);
        
        $loaded['Html'] =& $loaded['AddonsHtml']; // replace Html helper

        return $loaded;
    }

    /**
     * generate a cache key for memcaching queries
     * @param string additional key uniqueness factors (SQL query etc)
     */
    function _cachekey($key = '', $params) {
        // attach some unique factors to the key
        $key .= LANG.':'.APP_ID.':';
        foreach ($params as $k => $v){
            $key .= $k.serialize($v);
        }
        return MEMCACHE_PREFIX.md5($key);
    }

    function renderElement($name, $params = array()) {      					
        if ($this->caching && array_key_exists($name, $this->cacheableElts)) {

            // see comment by definition of cacheableElts above. This adds default query string parameters to the $params array
            foreach ($this->cacheableElts[$name] as $query_param) { 
                if (isset($this->controller->params['url'][$query_param]) && !isset($params[$query_param]) ) {
                    $params[$query_param] = $this->controller->params['url'][$query_param];		
                }			
            }		
            
            $cachekey = $this->_cachekey('element:'.$name, $params);
            if ($cached = $this->Cache->get($cachekey)) {
	            return $cached;
            }
        }
        
        $result = parent::renderElement($name, $params);
        
        if ($this->caching && !empty($cachekey)) {
            // cache it if it's not yet cached
            $res = $this->Cache->set($cachekey, $result);
        }
        // and return the result
        return $result;							
    }
}
?>
