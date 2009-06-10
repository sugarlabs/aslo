<?php
/* SVN FILE: $Id: test_manager.php,v 1.1.1.1 2006/08/14 23:54:57 sancus%off.net Exp $ */
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
 *            Mike Shaver aka the_decider <shaver@mozilla.org>
 *
 * Portions modifiied from WACT Test Suite
 * Author(s): Harry Fuecks
 *            Jon Ramsey
 *            Jason E. Sweat
 *            Franco Ponticelli
 *            Lorenzo Alberton
 *
 *  Licensed under The Open Group Test Suite License
 *  Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @author       Larry E. Masters aka PhpNut <phpnut@gmail.com>
 * @copyright    Copyright (c) 2006, Larry E. Masters Shorewood, IL. 60431
 * @link         http://www.phpnut.com/projects/
 * @package      tests
 * @subpackage   tests.lib
 * @since        CakePHP Test Suite v 1.0.0.0
 * @version      $Revision: 1.1.1.1 $
 * @modifiedby   $LastChangedBy: dho $
 * @lastmodified $Date: 2006/08/14 23:54:57 $
 * @license      http://www.opensource.org/licenses/opengroup.php The Open Group Test Suite License
 */
/**
 * Short description for class.
 *
 * @package    tests
 * @subpackage tests.lib
 * @since      CakePHP Test Suite v 1.0.0.0
 */

DATABASE_CONFIG::useTestConfig();

class TestManager {
	var $_testExtension = array('.test.php', '.test.thtml', '.test.thtml.php');

	function runAllTests(&$reporter) {
		$manager =& new TestManager();

		$testCases =& $manager->_getTestFileList(TESTS);

		$test =& new GroupTest('All Test Cases');

		foreach ($testCases as $testCase) {
			$test->addTestFile($testCase);
		}
		        
		$test->run($reporter);
	}
	
    function runDirectory($directory, &$reporter) {
		$manager =& new TestManager();

		$testCases =& $manager->_getTestFileList(TESTS.$directory);

		$test =& new GroupTest('Directory Test: '.$directory);

		foreach ($testCases as $testCase) {
			$test->addTestFile($testCase);
		}
		        
		$test->run($reporter);
	}

	function runGroup($group, &$reporter) {

        $manager =& new TestManager();

        $testCases =& $group['cases'];

        $test =& new GroupTest('Group Test: '.$group['name']);

        foreach ($testCases as $testCase) {
            if (strpos($testCase, '*') !== false) {
                $testCase = str_replace('/*', '', $testCase);
                $dirCases = $manager->_getTestFileList(TESTS.$testCase);
                foreach ($dirCases as $dirCase) {
                    $test->addTestFile($dirCase);
                }
            }
            else {
                $test->addTestFile(TESTS.$testCase);
            }
        }
        
        $test->addTestFile(TESTS.'global.test.php');
                
        $test->run($reporter);
    }

	function runTestCase($testCaseFile, &$reporter) {
		$manager =& new TestManager();

	    $testCaseFileWithPath = TESTS.$testCaseFile;
		if (! file_exists($testCaseFileWithPath)) {
			trigger_error("Test case {$testCaseFile} cannot be found", E_USER_ERROR);
		}
		$test =& new GroupTest("Test Case: " . $testCaseFile);
        $test->addTestFile($testCaseFileWithPath);
		if ($testCaseFile != 'global.test.php') {
		    $test->addTestFile(TESTS.'global.test.php');
		}
		$test->run($reporter);
	}

	function addTestCasesFromDirectory(&$groupTest, $directory = '.') {
		$manager =& new TestManager();
		$testCases =& $manager->_getTestFileList($directory);
		foreach ($testCases as $testCase) {
			$groupTest->addTestFile($testCase);
		}
	}

	function &getTestCaseList($directory = '') {
        if (empty($directory)) {
            $directory = TESTS;
        }
		$manager =& new TestManager();
		$testCases =& $manager->_getTestCaseList($directory);

        sort($testCases);
		return $testCases;
	}

	function &_getTestCaseList($directory = '.') {
		$fileList =& $this->_getTestFileList($directory);
		$testCases = array();
		foreach ($fileList as $testCaseFile) {
			$testCases[$testCaseFile] = str_replace($directory . DS, '', $testCaseFile);
		}
		return $testCases;
	}

	function &_getTestFileList($directory = '.') {
		$return = $this->_getRecursiveFileList($directory, array(&$this, '_isTestCaseFile'));
		return $return;
	}

	function &_getRecursiveFileList($directory = '.', $fileTestFunction) {
		$dh = opendir($directory);
		if (! is_resource($dh)) {
			trigger_error("Couldn't open {$directory}", E_USER_ERROR);
		}

		$fileList = array();
		while ($file = readdir($dh)) {
			$filePath = $directory . DIRECTORY_SEPARATOR . $file;
			if (0 === strpos($file, '.')) {
				continue;
			}

			if (is_dir($filePath)) {
				$fileList = array_merge($fileList, $this->_getRecursiveFileList($filePath, $fileTestFunction));
			}
			if ($fileTestFunction[0]->$fileTestFunction[1]($file)) {
				$fileList[] = $filePath;
			}
		}
		closedir($dh);
		return $fileList;
	}

	function _isTestCaseFile($file) {
        foreach ($this->_testExtension as $extension) {
            if ($this->_hasExpectedExtension($file, $extension)) {
                return true;
            }
        }
        return false;
	}

	function _hasExpectedExtension($file, $extension) {
		return $extension == strtolower(substr($file, (0 - strlen($extension))));
	}
}
?>
