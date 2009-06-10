<?php
/**
* Original file from http://cakephp.org/pastes/show/8803bd09150cb65cc7da63f92cdbc828
* Modifications by Justin Scott <fligtar@gmail.com>
*/

class UnitTestHelper
{
    var $previouslyCalled = array(); //called actions go here once called once
    
    /**
     * Loops through the $uses array of a Controller and mocks all used Models
     * and also allows additional methods for them to be pased via $params, (as
     * well as to set names for the generated mocks).
     *
     * @param unknown_type $Controller
     * @param mixed $testCase
     * @param unknown_type $params
     */
    function mockModels(&$Controller, &$testCase, $params = array())
    {
        if ($Controller->uses===false)
        {
            $model = Inflector::singularize($Controller->name);
            
            /**
             * If our uses array is empty CakePHP will always assume a default model.
             * So let's check if it exists.
             */
            $testCase->assertTrue(class_exists(strtolower($model)), 'Model "'.$model.'" exists');
            
            return;
        }
        
        /**
         * If we have Models to mock let's loop throw them
         */
        if (is_array($Controller->uses))
        {
            foreach ($Controller->uses as $model)
            {
                /**
                 * Check if the Models we use exist (CakePHP automatically includes them for us)
                 */
                $modelExists = $testCase->assertTrue(class_exists(strtolower($model)), 'Model "'.$model.'" exists');
                
                if ($modelExists)
                {
                    /**
                     * If we have customized Model Mocks to generate, set the vars for it
                     */
                    if (isset($params[$model]['name']))
                        $class = $params[$model]['name'];
                    else 
                        $class = false;
    
                    if (isset($params[$model]['methods']))
                        $methods = $params[$model]['methods'];
                    else 
                        $methods = false;
                    
                    /**
                     * Create the Model Mock
                     */
                    Mock::generate($model, $class, $methods);
                    
                    /**
                     * Add the Mock to the Controller
                     */
                    $mockModel = 'Mock'.$model;
                    $Controller->{$model} =& new $mockModel;
                }
            }
        }
    }

    /**
     * Loads the components for a controller
     * @param unknown_type $controller
     * @param unkown-type $testCase
     */
    function mockComponents(&$controller, &$testCase) {
        if(is_array($controller->components)) {
            foreach($controller->components as $component) {
                 loadComponent($component);
                 $componentClass = $component.'Component';
                 $testCase->assertTrue(class_exists($componentClass), 'Component "'.$component.'" exists');
                 Mock::generate($componentClass, false,  false);
                 $mockComponent = 'Mock'.$componentClass;
                 $controller->{$component} =& new $mockComponent;
                 /*if(method_exists($controller->{$component}, 'startup')) {
                     $controller->{$component}->startup($controller);
                     call_user_func(array($componentClass, 'startup'), $controller);
                 }*/
            }
        }
    }
    
    /**
     * Returns a fully initialized Controller
     *
     * @param unknown_type $controller
     * @param unknown_type $testCase
     * @param unknown_type $app
     * @return unknown
     */
    function getController($controller, &$testCase, $app = 'app')
    {
        $this->loadController($controller, $testCase, $app);
        
        $className = $controller.'Controller';
        
        // intercept flash() and redirect() calls from Controller
        if (!class_exists('Mock'.$className)) {
            eval('  class Mock'.$className.' extends '.$className.' {
                        function flash($msg,$url,$pause=1) {}
                        function redirect($url,$status=null) {}
                    }');
        }
        
        $mockControllerName = 'Mock'.$className;
        
        $Controller =& new $mockControllerName();
        $Controller->constructClasses();
        return $Controller;
    }
    
    /**
     * Calls a Controller action and returns the function return value or the output
     * if existing.
     *
     * @param object $Controller
     * @param string $action
     * @param object $testCase
     * @param array $params     
     * @param string $app
     * @return mixed
     */
    function callControllerAction(&$Controller, $action, &$testCase, $params = array(), $app = 'app')
    {        
        $methods = get_class_methods($Controller);
        
        //Cut down on spam
        if (!array_key_exists($Controller->name, $this->previouslyCalled) || (array_key_exists($Controller->name, $this->previouslyCalled) && !in_array($action, $this->previouslyCalled[$Controller->name]))) {
            $testCase->assertTrue(in_array($action, $methods), $Controller->name.'Controller::'.$action.'() exists');
            $this->previouslyCalled[$Controller->name][] = $action;
        }

        if (!is_array($params))
            $params = array();
            
        $Controller->action = $action;

        /**
         * By creating our own AppError class we can catch Object::cakeError() calls and
         * output them so we can throw an exception later on!
         */
        if (!class_exists('AppError'))
        {
            eval('class AppError
                  {
                      function AppError($method)
                      {
                           echo "error:$method";
                      }
                  }');
        }
        
        ob_start();
        $return = call_user_func_array(array(&$Controller, $action), $params);
        $output = @ob_get_clean();
        
        if ($Controller->autoRender)
        {
            ob_start();
            $Controller->render();
            $output = @ob_get_clean();
        }

        /*
         * @TODO This is ugly:
         * Print SimpleTest error messages if there were any in the output.
         */
        $outputarray = explode("\n", $output);
        $outputarray = preg_grep('/^<div class="(pass|fail)">.*<\/div>$/', $outputarray);
        foreach ($outputarray as $errorline)
            print $errorline."\n";
        
        /**
         * When running our TestCase rendering an action might causes some error notices
         * from the Sessions object that is trying to send headers.
         * 
         * Since there is nothing we can do to prevent those, we are going to filter them
         * out and add the $realErrors back into the queue later on.
         */
        $queue = &SimpleTest::getContext()->get('SimpleErrorQueue');
        $realErrors = array();
       
        while ($error = $queue->extract())
        {
            if (!preg_match('/headers already sent/i', $error[1]))
            {
                $realErrors[] = $error;
                break;
            }
        }
        
        /**
         * Add the real errors back to the queue
         */
        foreach ($realErrors as $error)
        {
            call_user_func_array(array(&$queue, 'add'), $error);
        }
        
        $match = null;
        if (preg_match('/^error:(.+)$/', $output, $match))
        {
            trigger_error('CakePHP Error: '.$match[1], E_USER_WARNING);
        }
        
        if (empty($output))
            return $return;
        else 
            return $output;
    }
  
    /**
     * Loads a controller named $controller. If the User passes an instance of the $testCase
     * that this call is being made from, an automatic assertion for the existence of this
     * Controller happens.
     *
     * @param string $controller
     * @param object $testCase
     * @param string $app
     */
    function loadController($controller, &$testCase, $app = 'app')
    {
        // do not try to load a "dummy" controller.
        if ($controller == 'Dummy') return;
        
        $controllerFile = CONTROLLERS.strtolower($controller).'_controller.php';
        $controllerExists = file_exists($controllerFile);

        $testCase->assertTrue($controllerExists, 'Load Controller "'.$controller.'Controller"');

        if ($controllerExists)
            loadController($controller);
    }

    /**
     * Log ourselves in (i.e. mock the session component results)
     */
    function login(&$controller) {
        $userdata = array(
                    'id' => 5,
                    'email' => 'nobody@mozilla.org',
                    'password' => '098f6bcd4621d373cade4e832627b4f6',
                    'firstname' => 'Andrei',
                    'lastname' => 'Hajdukewycz',
                    'nickname' => 'Sancus',
                    'emailhidden' => 0,
                    'homepage' => 'http://www.worldofwarcraft.com',
                    'confirmationcode' => '',
                    'created' => '2006-09-28 08:57:24',
                    'modified' => '2006-09-28 08:57:24'
                    );
        $controller->Session->setReturnValue('read', $userdata, 'User');
    }

    /**
     * Log out
     */
    function logout(&$controller) {
        $controller->Session->setReturnvalue('read', null, 'User');
    }

    /**
     * Compares two arrays to see if they contain the same values.  Returns TRUE or FALSE.
     * useful for determining if a record or block of data was modified (perhaps by user input)
     * prior to setting a "date_last_updated" or skipping updating the db in the case of no change.
     *
     * From some random comment on php.net!
     *
     * @param array $a1
     * @param array $a2
     * @return boolean
     */
    function array_compare_recursive($a1, $a2)
    {
        if (!(is_array($a1) and (is_array($a2)))) return false;
        
        if (!count($a1) == count($a2)) {
            return false; // arrays don't have same number of entries
        }
        
        foreach ($a1 as $key => $val) {
            if (!array_key_exists($key, $a2)) {
                // uncomparable array keys don't match
                return false;
                
            } elseif (is_array($val) and is_array($a2[$key])) {
                // if both entries are arrays then compare recursive
                if (!$this->array_compare_recursive($val,$a2[$key])) return false;
                
            } elseif (!($val === $a2[$key])) {
                // compare entries must be of same type.
                return false;
            }
        }
        return true; // $a1 === $a2
    }

}

?>
