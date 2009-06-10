<?php
/* SVN FILE: $Id: session.php 6305 2008-01-02 02:33:56Z phpnut $ */
/**
 * Short description for file.
 *
 * Long description for file
 * Patches: 2 MOZILLA patches
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright 2005-2008, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright		Copyright 2005-2008, Cake Software Foundation, Inc.
 * @link				http://www.cakefoundation.org/projects/info/cakephp CakePHP(tm) Project
 * @package			cake
 * @subpackage		cake.cake.libs.controller.components
 * @since			CakePHP(tm) v 0.10.0.1232
 * @version			$Revision: 6305 $
 * @modifiedby		$LastChangedBy: phpnut $
 * @lastmodified	$Date: 2008-01-01 20:33:56 -0600 (Tue, 01 Jan 2008) $
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 */
/**
 * Session Component.
 *
 * Session handling from the controller.
 *
 * @package		cake
 * @subpackage	cake.cake.libs.controller.components
 *
 */
class SessionComponent extends CakeSession {
/**
 * Used to determine if methods implementation is used, or bypassed
 *
 * @var boolean
 * @access private
 */
	var $__active = true;
/**
 * Used to determine if Session has been started
 *
 * @var boolean
 * @access private
 */
	var $__started = false;
/**
 * Used to determine if request are from an Ajax request
 *
 * @var boolean
 * @access private
 */
	var $__bare = 0;

    var $__controller = null;
/**
 * Class constructor
 *
 * @param string $base The base path for the Session
 */
	function __construct($base = null) {
		if ((!defined('AUTO_SESSION') || AUTO_SESSION === true)
/** BEGIN MOZILLA PATCH **/
             || isset($_COOKIE[CAKE_SESSION_COOKIE])) {
/** END MOZILLA PATCH **/
			parent::__construct($base);
		} else {
			$this->__active = false;
		}
	}
/**
 * Initializes the component, gets a reference to Controller::$param['bare'].
 *
 * @param object $controller A reference to the controller
 * @access public
 */
	function initialize(&$controller) {
		if (isset($controller->params['bare'])) {
			$this->__bare = $controller->params['bare'];
		}

        $this->__controller = $controller;
	}
/**
 * Startup method.
 *
 * @param object $controller Instantiating controller
 * @access public
 */
	function startup(&$controller) {
		if ($this->__started === false) {
			$this->__start();
		}
	}
/**
 * Starts Session on if 'Session.start' is set to false in core.php
 *
 * @param string $base The base path for the Session
 * @access public
 */
	function activate($base = null) {
		if ($this->__active === true) {
			return;
		}
		parent::__construct($base);
		$this->__active = true;
	}
/**
 * Used to write a value to a session key.
 *
 * In your controller: $this->Session->write('Controller.sessKey', 'session value');
 *
 * @param string $name The name of the key your are setting in the session.
 * 							This should be in a Controller.key format for better organizing
 * @param string $value The value you want to store in a session.
 * @access public
 */
	function write($name, $value = null) {
		if ($this->__active === true) {
			$this->__start();
			if (is_array($name)) {
				foreach ($name as $key => $value) {
					if (parent::write($key, $value) === false) {
						return false;
					}
				}
				return true;
			}
			if (parent::write($name, $value) === false) {
				return false;
			}
			return true;
		}
		return false;
	}
/**
 * Used to read a session values for a key or return values for all keys.
 *
 * In your controller: $this->Session->read('Controller.sessKey');
 * Calling the method without a param will return all session vars
 *
 * @param string $name the name of the session key you want to read
 * @return mixed value from the session vars
 * @access public
 */
	function read($name = null) {
		if ($this->__active === true) {
			$this->__start();
			return parent::read($name);
		}
		return false;
	}
/**
 * Used to delete a session variable.
 *
 * In your controller: $this->Session->del('Controller.sessKey');
 *
 * @param string $name the name of the session key you want to delete
 * @return boolean true is session variable is set and can be deleted, false is variable was not set.
 * @access public
 */
	function del($name) {
		if ($this->__active === true) {
			$this->__start();
			return parent::del($name);
		}
		return false;
	}
/**
 * Wrapper for SessionComponent::del();
 *
 * In your controller: $this->Session->delete('Controller.sessKey');
 *
 * @param string $name the name of the session key you want to delete
 * @return boolean true is session variable is set and can be deleted, false is variable was not set.
 * @access public
 */
	function delete($name) {
		if ($this->__active === true) {
			$this->__start();
			return $this->del($name);
		}
		return false;
	}
/**
 * Used to check if a session variable is set
 *
 * In your controller: $this->Session->check('Controller.sessKey');
 *
 * @param string $name the name of the session key you want to check
 * @return boolean true is session variable is set, false if not
 * @access public
 */
	function check($name) {
		if ($this->__active === true) {
			$this->__start();
			return parent::check($name);
		}
		return false;
	}
/**
 * Used to determine the last error in a session.
 *
 * In your controller: $this->Session->error();
 *
 * @return string Last session error
 * @access public
 */
	function error() {
		if ($this->__active === true) {
			$this->__start();
			return parent::error();
		}
		return false;
	}
/**
 * Used to set a session variable that can be used to output messages in the view.
 *
 * In your controller: $this->Session->setFlash('This has been saved');
 *
 * Additional params below can be passed to customize the output, or the Message.[key]
 *
 * @param string $message Message to be flashed
 * @param string $layout Layout to wrap flash message in
 * @param array $params Parameters to be sent to layout as view variables
 * @param string $key Message key, default is 'flash'
 * @access public
 */
	function setFlash($message, $layout = 'default', $params = array(), $key = 'flash') {
		if ($this->__active === true) {
			$this->__start();
			$this->write('Message.' . $key, compact('message', 'layout', 'params'));
		}
	}
/**
 * Used to renew a session id
 *
 * In your controller: $this->Session->renew();
 *
 * @access public
 */
	function renew() {
		if ($this->__active === true) {
			$this->__start();
			parent::renew();
		}
	}
/**
 * Used to check for a valid session.
 *
 * In your controller: $this->Session->valid();
 *
 * @return boolean true is session is valid, false is session is invalid
 * @access public
 */
	function valid() {
		if ($this->__active === true) {
			$this->__start();
			return parent::valid();
		}
		return false;
	}
/**
 * Used to destroy sessions
 *
 * In your controller: $this->Session->destroy();
 *
 * @access public
 */
	function destroy() {
		if ($this->__active === true) {
			$this->__start();
			parent::destroy();
		}
	}
/**
 * Returns Session id
 *
 * If $id is passed in a beforeFilter, the Session will be started
 * with the specified id
 *
 * @param $id string
 * @return string
 * @access public
 */
	function id($id = null) {
		return parent::id($id);
	}
/**
 * Starts Session if SessionComponent is used in Controller::beforeFilter(),
 * or is called from
 * MOZILLA PATCH: I added the check for $_COOKIE being set, and the calls to
 * checkValid().
 *
 * @access private
 */
	function __start(){
        // Only start a session if our cookie is set.  see start() for more details.
        if (isset($_COOKIE[CAKE_SESSION_COOKIE])) {
            //echo 'cookie is set';
            if ($this->__started === false) {
                if ($this->__bare === 0) {
                    if (!$this->id() && parent::start()) {
                        $this->__started = true;
                        $this->checkValid();
                    } else {
                        $this->__started = parent::start();
                        $this->checkValid();
                    }
                }
            }
        }
		return $this->__started;
	}

/** BEGIN MOZILLA PATCH  (@author clouserw@mozilla.com)**/
/**
 * Manually start a session.  CakePHP wants to start a session for every visitor which
 * is nuts.  In __start() we only activate the session starting code if the AMO
 * cookie is set.  That means that we need to set the cookie manually for the
 * sessions we do want - that's this function.
 */
    function start($expireTime) {
        // the strip_plugin() stuff is taken from cake/libs/controller/component.php.
        // Session is a special case in cake and gets special init info.  Without
        // this we end up setting a cookie with '/' for a path and trying to destory
        // a cookie with a different path.  If all parameters aren't the same,
        // the browser won't destroy the cookie.
        $this->activate(strip_plugin($this->__controller->base, $this->__controller->plugin) . '/');
        $this->__started = parent::start($expireTime);
        return $this->__started;
    }

/**
 * Manually end a session.  When a user logs out, CakePHP doesn't delete the cookie.
 * Since the cookie is set when they come back, normally they would automatically get a new 
 * session (from the __start()) function.  I added the checkValid() calls in
 * __start() to fix this.
 */
    function stop() {
        if ($this->__active === true) {
            $this->destroy();
            $this->__active = false;
            return true;
        }
        return false;
    }

/**
 * Verify a session can be traced to an actual user.  CakePHP checks for a valid
 * session, but it doesn't actually make sure it's connected to a user.  This means
 * anyone could make a cookie named CAKE_SESSION_COOKIE and it would be considered valid.
 * This function invalidates the cookie if it's not connected to a user.
 */
    function checkValid() {
        parent::_checkValid();

        if ($this->valid) {
            if ($this->read('User') == null) {
                $this->stop();
            }
        }

    }
/** END MOZILLA PATCH **/

}
?>
