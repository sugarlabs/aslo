<?php
/* SVN FILE: $Id: tests_controller.php,v 1.1 2006/08/16 07:10:51 sancus%off.net Exp $ */
/**
 * Short description for file.
 *
 * Long description for file
 *
 * PHP versions 4 and 5
 *
 * CakePHP Test Suite <https://trac.cakephp.org/wiki/Developement/TestSuite>
 * Copyright (c) 2006, Larry E. Masters Shorewood, IL. 60431
 * Author(s): Larry E. Masters aka PhpNut <phpnut@gmail.com>
 *            Justin Scott aka fligtar <fligtar@gmail.com> 
 *
 *  Licensed under The Open Group Test Suite License
 *  Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @author       Larry E. Masters aka PhpNut <phpnut@gmail.com>
 * @copyright    Copyright (c) 2006, Larry E. Masters Shorewood, IL. 60431
 * @link         http://www.phpnut.com/projects/
 * @package      cake
 * @subpackage   cake.app.controllers
 * @since        CakePHP Test Suite v 1.0.0.0
 * @version      $Revision: 1.1 $
 * @modifiedby   $LastChangedBy: phpnut $
 * @lastmodified $Date: 2006/08/16 07:10:51 $
 * @license      http://www.opensource.org/licenses/opengroup.php The Open Group Test Suite License
 */
/**
 * Short description for class.
 *
 * Long description for class
 *
 * @package    cake
 * @subpackage cake.app.controllers
 */
vendor('simpletest/reporter');
vendor('simpletest'.DS.'unit_tester', 'simpletest'.DS.'web_tester', 'simpletest'.DS.'mock_objects', 'simpletest'.DS.'xml', 'simpletest'.DS.'remote');
vendor('phpQuery/phpQuery');
require_once(TESTS.'test_manager.php');
require_once(TESTS.'groups.php');
require_once(TESTS.'cake_reporter.php');
require_once(TESTS.'bot_reporter.php');
require_once(TESTS.'test_helper_unit.php');
require_once(TESTS.'test_helper_web.php');
define('TEST_DATA', TESTS.'data');


/* XXX Copied from test_helper_web.php, shoot me. */

function hostPrefix() {
    $http = (!empty($_SERVER["HTTP_MOZ_REQ_METHOD"]) && $_SERVER["HTTP_MOZ_REQ_METHOD"] == 'HTTPS') ? 'https://' : 'http://';
    $uriBase = $http . $_SERVER['HTTP_HOST'];
    return $uriBase;
}

function actionPath($action) {
    return preg_replace('/\/tests.*/', $action, setUri());
}

/* end copying shame */

class TestsController extends AppController {
    var $uses = array('Addon', 'Addontype', 'Application', 'Approval', 'Appversion', 'Cannedresponse', 'Favorite', 'Feature', 'File',
 'Platform', 'Preview', 'Review', 'Reviewrating', 'Tag', 'Translation', 'User', 'Version');
     var $helpers = array('Html', 'Ajax', 'Javascript');
     var $aclExceptions = array('bot');
    // Disable permissions if we're in a DEV sandbox.
    function beforeFilter() {
        if (DEV == true) {
            $this->SimpleAcl->enabled = false;
            $this->SimpleAuth->enabled = false;
        }

        // disable query caching so devcp changes are visible immediately
        foreach ($this->uses as $_model) {
            $this->$_model->caching = false;
        }
    }

    function constructClasses() {
        global $TestController;
        $TestController = $this;
        parent::constructClasses();
    }

    function index() {      
        if (!empty($_GET['case'])) {
            $this->_case($_GET['case']);
        }
        elseif (!empty($_GET['directory'])) {
            $this->_directory($_GET['directory']);
        }
        else {
            $this->render('index', 'tests');
        }
    }

    function split_all() {
        function get_directory($path) {
            $split = explode('/', $path, 2);
            return $split[0];
        }

        $testlist = TestManager::getTestCaseList();
        $dirlist = array_unique(array_map('get_directory', $testlist));

        $test =& new GroupTest('All Test Cases (split)');
        $baseurl = hostPrefix() . actionPath("/tests/xml?");
        foreach ($dirlist as $dir) {
            $testurl = $baseurl;
            if (strpos($dir, '.') === false) {
                $testurl .= 'directory=';
            } else {
                $testurl .= 'case=';
            }
            $testurl .= urlencode($dir);			
            $test->addTestCase(new RemoteTestCase($testurl, $testurl . '&dry=1'));
        }

        $reporter = new CakeHtmlReporter();
        $reporter->sendNoCacheHeaders();
        ob_start();
        $test->run($reporter);
        $this->set('output', $reporter->output);
        $this->render('group', 'tests');		
    }

    function xml() {
        $reporter = new XmlReporter();
        /* in lieu of $reporter->sendNoCacheHeaders(); */
        if (! headers_sent()) {
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }

        if (!empty($_GET['dry'])) {
            $reporter->makeDry();
        }
        if (!empty($_GET['case'])) {
            TestManager::runTestCase($_GET['case'], $reporter);
        }
        elseif (!empty($_GET['directory'])) {
            TestManager::runDirectory($_GET['directory'], $reporter);			
        }
        elseif (!empty($_GET['group'])) {
            TestManager::runGroup($_GET['groups'][$_GET['group']], $reporter);
        }
        elseif (!empty($_GET['discover'])) {
            $this->render('discover', 'ajax');
        }
        exit();
    }

    function _case($case) {
        $reporter = new CakeHtmlReporter();
        $reporter->sendNoCacheHeaders();
        ob_start();

        TestManager::runTestCase($case, $reporter);
        $this->set('svn', $reporter->SVNLink());
        $this->set('output', $reporter->output);
        $this->render('case', 'tests');
    }
    
    function group($group) {
        $reporter = new CakeHtmlReporter();
        $reporter->sendNoCacheHeaders();
        ob_start();
        
        TestManager::runGroup($_GET['groups'][$group], $reporter);
        
        $this->set('output', $reporter->output);
        $this->render('group', 'tests');
    }
    
    function _directory($directory) {
        $reporter = new CakeHtmlReporter();
        $reporter->sendNoCacheHeaders();
        ob_start();
        
        TestManager::runDirectory($directory, $reporter);
        
        $this->set('output', $reporter->output);
        $this->render('group', 'tests');
    }
    
    function all() {
        $reporter = new CakeHtmlReporter();
        $reporter->sendNoCacheHeaders();
        ob_start();
        
        TestManager::runAllTests($reporter);
        
        $this->set('output', $reporter->output);
        $this->render('group', 'tests');
    }
    
    function bot() {
        $reporter = new BotReporter();
        $reporter->sendNoCacheHeaders();
        ob_start();
        
        TestManager::runAllTests($reporter);
        
        $this->set('output', $reporter->output);
        $this->render('case', 'ajax');    
    }
    
    function search() {
        $text = $_REQUEST['q'];
        $tests = TestManager::getTestCaseList();
        
        sort($tests);
        foreach ($tests as $test) {
            if (strpos($test, $text) !== false) {
                $results[] = $test;
            }
        }
        
        $this->set('results', $results);
        $this->render('search', 'ajax');
    }
    
    function passes($mode) {
        if ($mode == 'on') {
            $data = array('Passes' => true);
        }
        elseif ($mode == 'off') {
            $data = array('Passes' => false);
        }    
        $this->Session->write('Tests', $data);
        
        $referer = str_replace('&', '?', $_GET['r']);
        
        $this->redirect($referer);
    }
}
?>
