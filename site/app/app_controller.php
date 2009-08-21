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

class AppController extends Controller
{
    var $components = array('Amo','SimpleAuth','SimpleAcl');
    var $uses = array('Config', 'User', 'Group', 'Addontype', 'Platform');
    var $view = 'Addons';

    // allow named arguments, default on
    var $namedArgs = true;
    var $argSeparator = ":";
    var $beforeFilter = array('checkCSRF', 'getNamedArgs', 'checkAdvancedSearch');

    /**
     * Used to determine the current security level for the class
     *
     * @var string 'high' or 'low'
     */
    var $securityLevel = 'medium';
    
    /**
     * array keys not to be sanitized when using publish()
     */
    var $dontsanitize = array('icondata', 'locale','locale_html', 'created', 
        'modified', 'datestatuschanged', 'DateLastActive', 
        'dateadded', 'filedata', 'thumbdata', 'picture_data');

    function __construct() {
        parent::__construct();

        if (DEV) {
            // Not using && to help with APC caching, but I don't know
            // if it actually helps.  voodoo++
            if (array_key_exists('X-Amo-Test', getallheaders())){
                DATABASE_CONFIG::useTestConfig();
                // In downloads_controller.test we check that the returned
                // content matches a file on disk.  If we're appending query
                // logs and microtime to the page, those aren't going to match.
                Configure::getInstance()->debug = 0;
                define('NO_MICROTIME', 1);
            }
        }
        $this->setSecurityLevel($this->securityLevel);
    }

    function startup() {
        $this->SimpleAuth->startup($this);
        $this->SimpleAcl->startup($this);
    }

    /**
    * Enables sandbox access when requested, via the status named argument.
    * add "_checkSandbox" to beforeFilter array to enable sandbox context
    * in a controller
    */
    
    function _checkSandbox() {

        // The current status according the to the controller.
        // In places, this is pulled and used in IN() clauses,
        // so this should always be an array, if though it's annoying.
        $this->status = array(STATUS_PUBLIC);

        // Whether or not a user has agreed to the sandbox terms,
        // according to their session data.  Default is false.
        $this->sandboxAccess = false;
        
        if ($this->Session->check('User')) {
            $sessionuser = $this->Session->read('User');

            if (isset($sessionuser['sandboxshown']) && $sessionuser['sandboxshown'] == 1) {
                $this->sandboxAccess = true;
            }
        }             
        
        if (isset($this->namedArgs['status']) && $this->namedArgs['status'] == STATUS_SANDBOX) {
            if (!$this->Session->check('User')) {
                $target_url = str_replace(LANG . '/' . APP_SHORTNAME . '/','',$this->params['url']['url']);
                $this->redirect('/users/login?to=' . urlencode($target_url) . "&m=2");
                return;
            }
            if ($this->sandboxAccess)
                $this->status = array(STATUS_SANDBOX, STATUS_PENDING, STATUS_NOMINATED);
        }

        // This is either PUBLIC or SANDBOX and is used for view switching.
        // Since morgamic is a jerk we have to check the status array.
        if (in_array(STATUS_SANDBOX, $this->status)) {
            $this->set('addonStatus', STATUS_SANDBOX);
        } else {
            $this->set('addonStatus', STATUS_PUBLIC);
        }

        // This is a boolean for our controllers.
        $this->set('sandboxAccess', $this->sandboxAccess);
    }
    
    /**
     * Checks for 'advancedsearch=true' in url to show advanced search (non-js fallback)
     */
    function checkAdvancedSearch() {
        $advancedSearch = false;

        if(isset($this->params['url']['advancedsearch']) && $this->params['url']['advancedsearch'] == 1) {
            $advancedSearch = true;
        }
        
        $this->set('advancedSearch', $advancedSearch);
        return true;
    }
    
    /**
     * locale-aware redirect function
     */
    function redirect($url, $status = null, $addLocale = true, $addApp = true) {
        $oldBase = $this->base;
        if ($addLocale) $this->base = $this->_getLocaleBase();
        if ($addApp) $this->base .= '/' . APP_SHORTNAME;
    
        $ret = parent::redirect($url, $status);
        
        $this->base = $oldBase;
        return $ret;
    }
    
    /**
     * locale-aware flash function
     */
    function flash($message, $url, $pause = null, $addLocale = true, $addApp = true) {
        $oldBase = $this->base;
        if ($addLocale) $this->base = $this->_getLocaleBase();
        if ($addApp) $this->base .= '/' . APP_SHORTNAME;
    
        $ret = parent::flash($message, $url, $pause);
        
        $this->base = $oldBase;
        return $ret;
    }
    
    /**
     * locale-aware url function
     */
    function url($url) {
        if ($url[0] != '/') {
            $url = '/'.$url;
        }
        return $this->base.'/'.LANG.'/'.APP_SHORTNAME.$url;
    }

    /**
     * locale-aware referer
     */
    function referer($default = null, $local = false, $addLocale = true, $addApp = true) {
        $oldRoot = $this->webroot;
        if ($addLocale) $this->webroot = $this->_getLocaleWebRoot();
        if ($addApp) $this->webroot .= '/' . APP_SHORTNAME;
    
        $ret = parent::referer($default, $local);
        
        $this->webroot = $oldRoot;
        return $ret;
    }

    /**
     * A callback function to populate the namedArgs array if activated
     * This should be triggered in the beforeFilter
     *
     * method suggested in http://bakery.cakephp.org/articles/view/129
     * 
     * @return TRUE always
     */
    function getNamedArgs() {
        $doNamedArgs = $this->namedArgs;
        $this->namedArgs = array();

        if ($doNamedArgs) {
            if (!empty($this->params['pass'])) {
                foreach ($this->params['pass'] as $param) {
                    if (strpos($param, $this->argSeparator)) {
                        list($name, $val) = explode($this->argSeparator, $param, 2);
                        $this->namedArgs[$name] = $val;
                    }
                }
            }
        }
        //check for sandbox status in the url
        if (!empty($this->params['url']['status']) && empty($this->namedArgs['status']))
            $this->namedArgs['status'] = $this->params['url']['status'];
            
        $this->Amo->clean($this->namedArgs);

        return true;
    } 

    /**
     * checks to make sure POSTed data has a hidden field sessionCheck as
     * defined in:
     *
     * @see AddonsHtmlHelper::hiddenSession
     *
     * this is used to guard against cross-site request forgeries. We don't
     * rely on cake stuff as this had been causing session issues.
     * 
     * This method should be added to any new controller whose $beforeFilter
     * overrides the default one above to ensure CSRF detection is done. 
     *
     * For posted data where a session is not yet established use the
     * array $exceptionCSRF to explicitly create an array of allowed
     * URLs which you do not want checkCSRF to apply to.    
     */
    function checkCSRF() {
        global $csrf_old_session_id;
        
        if ($_SERVER['REQUEST_METHOD'] != 'POST') return;
        
        if (isset($this->exceptionCSRF)) {
            foreach ($this->exceptionCSRF as $exception) {
                if (stristr($_SERVER['REQUEST_URI'], $exception))
                    return;
            }
        }
        $sessionuser = $this->Session->read('User');
        $id = $sessionuser['id'];
        
        $current_epoch = (int)(time()/CAKE_SESSION_TIMEOUT);
        // this is to mitigate against where a session starts at an epoch boundary:
        $previous_epoch = $current_epoch - 1;
        
        // if our ID was regenerated during session spin-up, we check against the previous value
        // see bug 458763
        if (!empty($csrf_old_session_id))
            $session_id = $csrf_old_session_id;
        else
            $session_id = session_id();
        
        $currentMd5 = md5($session_id.$id.$current_epoch);
        $previousMd5 = md5($session_id.$id.$previous_epoch);
        
        if (!isset($_POST['sessionCheck']) ||
            ($_POST['sessionCheck'] != $currentMd5 && $_POST['sessionCheck'] != $previousMd5)) {
            
            header('HTTP/1.1 400 Bad Request');
            $this->flash( ___('There are errors in this form. Please correct them and resubmit.'), '/' , 3); //error string is a little non-informative
            exit();
        } 
    }

    /**
     * get $this->base with locale included
     */
    function _getLocaleBase() {
        $base = $this->base;
        $base .= '/'.LANG;
        return $base;
    }

    /**
     * get $this->webroot with locale included
     */
    function _getLocaleWebRoot() {
        $webroot = $this->webroot;
        $webroot .= LANG.'/'; // not the trailing slash, as opposed to base
        return $webroot;
    }
    
    function setLayoutForFormat($default = 'mozilla') {
        if (array_key_exists('format', $this->namedArgs) &&
            $this->namedArgs['format'] == 'rss') {
            $this->layout = 'rss';
        } else {
            $this->layout = $default;
        }
        return $this->layout;
    }
    
    function disableCache() {
        header('Cache-Control: no-store, must-revalidate, post-check=0, pre-check=0, private, max-age=0');
        header('Pragma: private');
    }

    function forceCache() {
        header('Cache-Control: public, max-age=' . HOUR);
        header('Last-modified: ' . gmdate("D, j M Y H:i:s", time()) . " GMT");
        header('Expires: ' . gmdate("D, j M Y H:i:s", time() + HOUR) . " GMT");
    }
    
    /**
     * set() replacement that automatically santitzes (html-encodes) data
     * and passes it to the view, to avoid repetitive and error-prone manual
     * data sanitization in the view or controllers.
     *
     * @param string viewvar Variable name to be made available in the view
     * @param mixed array or string data to be assigned to the variable name
     * @param bool sanitizeme do data sanitization on the value before setting it?
     * @param bool sanitizekeys clean array keys also?
     * @return void
     */
    function publish($viewvar, $value, $sanitizeme = true, $sanitizekeys = false) {
        if ($sanitizeme)
            $this->_sanitizeArray($value, $sanitizekeys);
        $this->set($viewvar, $value);
    }
    
    /**
     * beforeRender callback. Sanitizes Cake's built-in data array containing
     * form data, as it is automatically pushed back to the view unsanitized,
     * circumventing the publish() function.
     */
    function beforeRender() {
       $this->set('AmoCategories', $this->Amo->getNavCategories());
       $this->set('AmoVersions', $this->Amo->getApplicationVersions());
       $this->set('AmoPlatforms', $this->Platform->getNames());
       $this->set('AmoAddonTypes', $this->Addontype->getNames());
        
        // User name for Welcome message
        if ($session = $this->Session->read('User')) {
            if (!empty($session['firstname']))
                $welcomeName = $session['firstname'];
            elseif (!empty($session['nickname']))
                $welcomeName = $session['nickname'];
            elseif (!empty($session['lastname']))
                $welcomeName = $session['lastname'];
            else
                $welcomeName = '';
            
            $this->publish('welcomeName', $welcomeName);
        }

        if (isset($this->data))
            $this->_sanitizeArray($this->data, false);

        return parent::beforeRender();
    }
    
    /**
     * afterFilter callback.Called after every action is completed.
     * Flushes object cache objects if necessary.
     */
    function afterFilter() {
        if (!QUERY_CACHE || !is_object($this->Config->Cache))
            return parent::afterFilter();
        
        global $flush_lists;
        if (!empty($flush_lists))
            $this->Config->Cache->flushMarkedLists();
        
        return parent::afterFilter();
    }
    
    /**
     * html-encode an array, recursively
     * 
     * @param mixed the data array (or string) to be html-encoded (by reference)
     * @param bool clean the array keys as well?
     * @return void
     */
    function _sanitizeArray(&$data, $cleankeys = false) {
        global $sanitize_patterns;

        if (is_array($data)) {
            if (empty($data)) return; // prevents removal of empty arrays
            // recurse through the array to get all values
            foreach ($data as $key => $value) {
                // @todo This if() statement is a temporary solution until we come up with
                // a better way of excluding fields from being sanitized.  This
                // particular array keeps the translations locale strings from
                // becoming entities
                if (!in_array($key, $this->dontsanitize, true)) {
                    $this->_sanitizeArray($data[$key], $cleankeys);
                }
            }
            
            // change the keys if necessary
            if ($cleankeys) {
                $keys = array_keys($data);
                $this->_sanitizeArray($keys, false);
                $data = array_combine($keys, array_values($data));
            }
            
        } elseif (is_string($data)) {
            // encode the string
            if (!empty($data)) {
                $data = iconv('UTF-8', 'UTF-8//IGNORE', $data);
                $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                $data = preg_replace($sanitize_patterns['patterns'], $sanitize_patterns['replacements'], $data);
            }
        }
        // otherwise, we don't do anything (with ints or null etc.).
    }

    /**
     * When CAKE_SECURITY is set to high, cake will automatically set
     * session.referer_check to the current host.  This is good for some of our
     * pages, but not good for others.  Since the Session component is
     * automatically-included-no-matter-what, we can't override that, so we'll change
     * the ini setting ourselves here.  Default is high, but we'll override it in all
     * the controllers that can use a more relaxed level.
     *
     * @param string level to set the security at, 'low' or 'high'
     * @return void
     */
    function setSecurityLevel($level) {
        if (defined('CAKE_SECURITY')) return;
        switch ($level) {

            case 'low':
                    define('CAKE_SECURITY', 'low');
                    break;

            case 'medium':
                    define('CAKE_SECURITY', 'medium');
                    break;

            case 'high':
            default:
                    define('CAKE_SECURITY', 'high');
                    break;
        }
    }

    /**
     * Looks at the request headers to see if
     *   HTTP_X_REQUESTED_WITH: XMLHttpRequest
     * is set.
     *
     * @return boolean
     */
    function isAjax() {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
    }
    
    /**
     * Forces use of the shadowDb.
     */
    function forceShadowDb() {
        foreach ($this->uses as $model) {
            $this->{$model}->useDbConfig = 'shadow';
            $this->setToShadow($this->{$model});
        }
    }

    /**
     * Sets a passed model to recursively use the shadow db.
     */
    function setToShadow(&$m) {
        $m->useDbConfig = 'shadow';
        if (is_array($m->__associations) and !empty($m->__associations)) {
            foreach ($m->__associations as $association) {
                foreach ($m->{$association} as $boundModel) {
                    $_n = $boundModel['className'];
                    $m->$_n->useDbConfig = 'shadow';
                }
            }
        }
    }

    /**
     * Call renderElement straght from a controller.
     *
     * If you're printing this out, leave $autoRender set to False so Cake does
     * try rendering any unnecessary views.  If you just want to get the string
     * content, set $autoRender to True so Cake keeps on truckin'.
     *
     * @params same as renderElement in a view
     * @return string of rendered content
     */
    function renderElement($path, $vars=array(), $autoRender=False) {
        $view = new View($this, 'helpers');
        loadHelper('AddonsHtml');
        $vars['html'] = new AddonsHtmlHelper();
        $vars['html']->base = $this->base;
        $this->autoRender = $autoRender;
        return $view->renderElement($path, $vars);
    }
}
?>
