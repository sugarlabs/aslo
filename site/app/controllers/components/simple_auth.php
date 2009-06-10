<?php
/**
 * Created: Sun Sep 17 14:44:50 CEST 2006
 * 
 * DESCRIPTION
 * 
 * PHP versions 4 and 5
 *
 * Copyright (c) Felix GeisendÃ¶rfer <info@fg-webdesign.de>
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright       Copyright (c) 2006, Felix GeisendÃ¶rfer. 
 * @link            http://www.fg-webdesign.de/
 * @link            http://www.thinkingphp.org/ 
 * @license         http://www.opensource.org/licenses/mit-license.php The MIT License
 */

class SimpleAuthComponent extends Object 
{
    /**
     * You can set this to false in your AppController's beforeFilter to deactivate this Component
     *
     * @var boolean
     */
    var $enabled = true;

    /**
     * If you use a different Model for your users table, change this value in your
     * AppController's beforeFilter.
     *
     * @var string
     */
    var $userModel = 'User';
    
    /**
     * If you use a different Model for your groups table, change this value in your
     * AppController's beforeFilter.
     *
     * @var string
     */
    var $groupModel = 'Group';
        
    /**
     * Name of the field a User can be uniquely identified besides the id field.
     *
     * @var string
     */
    var $userIdentifier = 'email';
    
    /**
     * The $userIdentifier (name) of the default user which *has* to exist in the 
     * $userModel and is used if there is no active (logged in) user yet as the default
     * account.
     *
     * @var string
     */
    var $defaultUser = DEFAULT_ACL_USER;

    /**
     * In case the $defaultUser couldn't be found can can provide a callback that handles this
     * situation or leave this empty in which case a fatal php error will show up (the one
     * already mentioned on the variable above).
     *
     * @var mixed
     */
    var $criticalErrorCallback = null; 
    
    /**
     * Contains all data associated to the active user
     *
     * @var array
     */
    var $activeUser = null;
    
    /**
     * Contains the reference to the Controller.
     *
     * @var object
     */
    var $Controller;
    
    function startup(&$Controller)
    {
        $this->Controller = &$Controller;
        
        // If the component got disabled, exit.
        if ($this->enabled===false)
            return;
        
        // In case a cakeError is raised, this will make sure our class continues to work
        if (!isset($this->Controller->{$this->userModel}))
            $this->Controller->constructClasses();        
            
        // Get the activeUser (array)
        $this->activeUser = $this->getActiveUser();

        // If no activeUser, not even the default one, could be found, raise an error
        if (empty($this->activeUser))
        {
            // In case there is no registered Callback, print a simple php error message.
            if (empty($this->criticalErrorCallback))
            {                
                trigger_error('Unable to find a user account "'.$this->defaultUser.'". Permission Denied for security reasons.', E_USER_ERROR);                
                
                // This exit should not be needed, but since I'm not sure if there is a way you can mess things up in php.ini this stays here
                exit; 
            }            
            else // Otherwise, call the callback function and return
                return call_user_func_array($this->criticalErrorCallback, array($controller, $action, &$this));
        }
    }
    
    function getActiveUser()
    {    
        // If we already have an activeUser set, return it
        if (!empty($this->activeUser))
            return $this->activeUser;

        // In case a cakeError is raised, this will make sure our class continues to work
        if (!isset($this->Controller->{$this->userModel}))
            $this->Controller->constructClasses();        
    
        // See if the activeUserId is stored in our session and fetch the User for it if so
        if ($activeUserId=$this->Controller->Session->read($this->userModel.'.id')) 
            $user = $this->Controller->{$this->userModel}->findById($activeUserId);

        // If no activeUserId was set, or it couldn't be found, fall back to the defaultUser account
        if (!isset($user) || empty($user))
            $user = $this->Controller->{$this->userModel}->find(array($this->userModel.'.'.$this->userIdentifier => $this->defaultUser));
        
        return $user;
    }
    
    function setActiveUser($id, $refresh = false)
    {
        // If no $refresh is required, check if $id already is the active User
        if (($refresh==false) && !empty($this->activeUser) && ($this->activeUser[$this->userModel]['id']==$id))
            return true;

        // If a numeric $id was given, find the corresponding user
        if (is_numeric($id))
            $user = $this->Controller->{$this->userModel}->findById($id);
        else // Or if not, find the default User
            $user = $this->Controller->{$this->userModel}->find(array($this->userModel.'.'.$this->userIdentifier => $this->defaultUser));
        
        // If we couldn't find any User to make active return false
        if (empty($user))
            return false;
            
        // Set the activeUser for this class
        $this->activeUser = $user;
        
        // And save our activeUser to the Session
        $this->Controller->Session->write($this->userModel.'.id', $user[$this->userModel]['id']);
        
        // Job complete
        return true;
    }
}

?>
