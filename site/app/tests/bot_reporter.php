<?php
class BotReporter extends HtmlReporter {
    var $testName;
    var $show;
    var $output;
    
	function BotReporter($character_set = 'ISO-8859-1') {
		parent::HtmlReporter($character_set);
	}
	
	function paintHeader($test_name) {
	    $this->output .= '<fails>';
	}

	function paintFooter($test_name) {
	    $this->output .= '</fails><results>';
    	$this->output .= $this->getTestCaseProgress() . '/' . $this->getTestCaseCount();
    	$this->output .= ' test cases complete - ';
    	$this->output .= $this->getPassCount() . ' passes, ';
    	$this->output .= $this->getFailCount() . ' fails and ';
    	$this->output .= $this->getExceptionCount() . ' exceptions';
    	$this->output .= '</results>';
	}
	
	function paintPass($message) {
	    parent::paintPass($message);
    }

	function paintFail($message) {
		SimpleReporter::paintFail($message);
		$breadcrumb = $this->getTestList();
        $this->output .= "\n\t".$breadcrumb[3]." - ".$message;
	}

    function paintError($message) {
        SimpleReporter::paintError($message);
    }

}

?>
