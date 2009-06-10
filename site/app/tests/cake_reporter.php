<?php
/* SVN FILE: $Id: cake_reporter.php,v 1.1.1.1 2006/08/14 23:54:57 sancus%off.net Exp $ */
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
 * @subpackage   tests.libs
 * @since        CakePHP Test Suite v 1.0.0.0
 * @version      $Revision: 1.1.1.1 $
 * @modifiedby   $LastChangedBy: phpnut $
 * @lastmodified $Date: 2006/08/14 23:54:57 $
 * @license      http://www.opensource.org/licenses/opengroup.php The Open Group Test Suite License
 */
/**
 * Short description for class.
 *
 * @package    tests
 * @subpackage tests.libs
 * @since      CakePHP Test Suite v 1.0.0.0
 */
class CakeHtmlReporter extends HtmlReporter {
    var $testName;
    var $output = '';
    var $header;
    
/**
 *    Does nothing yet. The first output will
 *    be sent on the first test start. For use
 *    by a web browser.
 *    @access public
 */
	function CakeHtmlReporter($character_set = 'ISO-8859-1') {
		parent::HtmlReporter($character_set);
	}
/**
 *    Paints the top of the web page setting the
 *    title to the name of the starting test.
 *    @param string $test_name      Name class of test.
 *    @access public
 */
	function paintHeader($test_name) {
	    $this->testName = $test_name;
	    $this->header = '<div class="name">'.$test_name.'</div><br>';
	}

/**
 * Creates link to SVN for file this test tests
 */
    function SVNLink($test_name = '') {
        if (empty($test_name)) {
            $test_name = $this->testName;
            if (preg_match('/Test Case: (\S+)/i', $test_name, $matches)) {
                $path = $matches[1];
            }
        }
        else {
            $path = str_replace(TESTS, '', $test_name);
        }

        if (!empty($path)) {
            if (strpos($path, 'views') !== false) {
                $name = str_replace('.test.php', '.thtml', $path);
            }
            else {
                $name = str_replace('.test', '', $path);
            }

            $link = '<a class="svn" href="http://svn.mozilla.org/addons/trunk/site/app/'.$name.'">Test Subject</a>';
            return $link;
        }
    }

    function paintGroupStart($test_name, $size) {
        parent::paintGroupStart($test_name, $size);
        
        //If it has a space, it's very likely the header, so don't show it
        if (strpos($test_name, ' ') !== false) {
            return;
        }
        
        $header = str_replace(TESTS, '', $test_name);
        $header = str_replace('//', '/', $header);
        
        $this->output .= '<div class="groupdivider">';
        $this->output .= '<div class="header">'.$header.'</div>';
        $this->output .= '<div class="victim">'.$this->SVNLink($test_name).'</div>';
        $this->output .= '</div>';
    }
    
/**
 * Paints the end of the test with a summary of
 * the passes and failures.
 *  @param string $test_name Name class of test.
 * @access public
 *
 */
	function paintFooter($test_name) {
    	$color = ($this->getFailCount() + $this->getExceptionCount() > 0 ? 'red' : 'green');
    	$results = '<div id="results" style="padding: 8px; margin-top: 1em; background-color: '.$color.'; color: white;">';
    	$results .= $this->getTestCaseProgress() . '/' . $this->getTestCaseCount();
    	$results .= ' test cases complete: ';
    	$results .= '<strong>' . $this->getPassCount() . '</strong> passes, ';
    	$results .= '<strong>' . $this->getFailCount() . '</strong> fails and ';
    	$results .= '<strong>' . $this->getExceptionCount() . '</strong> exceptions.';
    	$results .= '</div>';
    	
    	$this->output = $this->header.$results.$this->output;
    	$this->output .= $results;
	}
	
	function paintPass($message) {
        parent::paintPass($message);
        if (!empty($_SESSION['Tests']['Passes']) && $_SESSION['Tests']['Passes'] === false) {
            return;
        }
        
        $breadcrumb = $this->getTestList();
        $this->output .= '<div class="pass"><span class="pass">PASS</span>: ';
        $this->output .= $breadcrumb[3].' -> ';
        $this->output .= $this->parseMessage($message);
		$this->output .= '</div>';
    }

	function paintFail($message) {
		SimpleReporter::paintFail($message);
        $this->output .= '<div class="fail"><span class="fail">FAIL</span>: ';
        $breadcrumb = $this->getTestList();
        if (isset($_GET['group'])) {
            $this->output .= $breadcrumb[2].' -> ';
        }
        $this->output .= $breadcrumb[3].' -> ';
        $this->output .= $this->parseMessage($message);
        $this->output .= '</div>';
	}

    function paintError($message) {
        SimpleReporter::paintError($message);
        $this->output .= '<div class="fail"><span class="fail">EXCEPTION</span>: ';
        $breadcrumb = $this->getTestList();
        if (isset($_GET['group'])) {
           $this->output .= $breadcrumb[2].' -> ';
        }
        $this->output .= $breadcrumb[3].' -> ';
        $this->output .= $this->parseMessage($message);
        $this->output .= '</div>';
    }

	function parseMessage($message) {
		if (preg_match('/(.*) at \['.str_replace(DS, '\\'.DS, TESTS).'(\S+) (line (\d+))\]/i', $message, $matches)) {
            $memusage = function_exists('memory_get_usage') ? memory_get_usage() : ''; // some platforms don't have that
            $message = $matches[1].' @ <a href="http://svn.mozilla.org/addons/trunk/site/app/tests/'.$matches[2].'#'.$matches[4].'">'.$matches[3].'</a> ('.$memusage.')<br>';
		}
		return $message;
	}

}

?>
