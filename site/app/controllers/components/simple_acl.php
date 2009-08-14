<?php

/**
 * Created: Sun Sep 17 10:51:46 CEST 2006
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
class SimpleAclComponent extends Object 
{
    var $Controller;

    /**
     * You can set this to false in your AppController's beforeFilter to deactivate this Component
     *
     * @var boolean
     */
    var $enabled = true;
    
    /**
     * Here you can register a callback in case the User tries to access an action he's not
     * allowed to access.
     *
     * @var mixed
     */
    var $actionDeniedCallback = null;      
    
    /**
     * If $this->activeUser is empty, this component will be accessed to get the activeUser
     * data. The component must support a function getActiveUser();.
     * 
     * I would recommend you to use my SimpleAuth component for this job ; ).
     *
     * @var string
     */
    var $authComponent = 'SimpleAuth';
    
    /**
     * Can contain the current User data
     *
     * @var unknown_type
     */
    var $activeUser = null;

    function startup(&$Controller)
    {        
        $this->Controller = &$Controller;

        // The name of the Controller and action we want to check permission for
        $controller = $this->Controller->name;
        $action     = $this->Controller->action;

        // If the component got disabled, exit.
        if ($this->enabled===false)
            return;        

        if (empty($this->activeUser) && (isset($this->Controller->{$this->authComponent})))
            $this->activeUser = $this->Controller->{$this->authComponent}->getActiveUser();
                
        // Check if there is an action and if we are (not) allowed to access it.
        if (!empty($this->Controller->action) && !$this->actionAllowed($controller, $action))
        {
            // If there is no actionDeniedCallback defined, invoke a simple default error template (errors/permission_denied.ctp)
            if (!$this->actionDeniedCallback)
            {
                header('HTTP/1.1 401 Unauthorized');
                $this->Controller->set(compact('controller', 'action'));
            
                $this->Controller->layout = 'mozilla';
                $this->Controller->pageTitle = ___('Access Denied') . ' :: ' . sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);
                $this->Controller->set('breadcrumbs', ___('Access Denied'));
                $this->Controller->set('subpagetitle', ___('Access Denied'));
                $this->Controller->viewPath = 'errors';            
                $this->Controller->render('error401');

                // Exit here, because we're effing done.
                exit;

            }
            else // Call the actionDeniedCallback
                return call_user_func_array($this->actionDeniedCallback, array($controller, $action, &$this));
        }                
    }
    
    function actionAllowed($controller, $action, $user = null)
    {
        // People are not allowed by default.
        $allowed = false;

        // If our controller action is in the exceptions list, return true so the page can execute freely.
        if (!empty($this->Controller->aclExceptions) && is_array($this->Controller->aclExceptions) && in_array($action,$this->Controller->aclExceptions)) {
            return true;
        }

        if (empty($user) && !empty($this->activeUser))
            $user = $this->activeUser;

        if (isset($user[$this->Controller->{$this->authComponent}->groupModel]) && is_array($user[$this->Controller->{$this->authComponent}->groupModel]))
        {
            foreach ($user[$this->Controller->{$this->authComponent}->groupModel] as $group) {

                // If we find at least one group that grants access, we can set groupAllowed to true and break the loop.
                // We've found what we're looking for.
                if ($this->requestAllowed($controller, $action, $group['rules'])) {
                    $allowed = true;
                    break;
                }
            }
        }
        
        return $allowed;
    }
    
    /**
     * This function decides whether a given $objects's $property can be accessed based
     * on a list of $rules. If the list of $rules is empty or doesn't match $object/$property
     * the decision is made based on the $default value.
     *
     * @param string $object
     * @param string $property
     * @param string $rules
     * @param boolean $allowedDefault
     * @return boolean
     */
    function requestAllowed($object, $property, $rules, $default = false)
    {
        // The default value to return if no rule matching $object/$property can be found
        $allowed = $default;
        
        // This Regex converts a string of rules like "objectA:actionA,objectB:actionB,..." into the array $matches.
        preg_match_all('/([^:,]+):([^,:]+)/is', $rules, $matches, PREG_SET_ORDER);
        foreach ($matches as $match)
        {
            list($rawMatch, $allowedObject, $allowedProperty) = $match;
            
            $allowedObject = str_replace('*', '.*', $allowedObject);
            $allowedProperty = str_replace('*', '.*', $allowedProperty);
            
            if (substr($allowedObject, 0, 1)=='!')
            {
                $allowedObject = substr($allowedObject, 1);
                $negativeCondition = true;
            }
            else 
                $negativeCondition = false;
            
            if (preg_match('/^'.$allowedObject.'$/i', $object) &&
                ($property == '%' ||
                preg_match('/^'.$allowedProperty.'$/i', $property)))
            {
                if ($negativeCondition)
                    $allowed = false;
                else 
                    $allowed = true;
            }
        }        
        return $allowed;
    }
}

?>
