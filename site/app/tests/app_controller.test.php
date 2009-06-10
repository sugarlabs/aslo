<?php
// dummy controller, along with associated dummy model
class DummyController extends AppController { var $uses = array(); }

class AppControllerTest extends UnitTestCase {
 
    function testLoad() {
        $this->helper =& new UnitTestHelper();
        
        // Create dummy controller
        $this->controller =& $this->helper->getController('Dummy', $this);
    }
    
    /**
     * test array sanitization, values only (default)
     */
    function testSanitizeArrayValuesOnly() {
        $testarray = array('abc' => array('some"quotes' => "a'b",
                                          'more' => 'quot"es'));
        $sanitized = array('abc' => array('some"quotes' => 'a&#039;b',
                                          'more' => 'quot&quot;es'));
        
        $this->controller->publish('testarray', $testarray);
        
        $result = $this->controller->viewVars['testarray'];
        $this->assertTrue($this->helper->array_compare_recursive($result, $sanitized),
            'array sanitization, default: values only');
    }
    
    /**
     * test array sanitization, values and keys
     */
    function testSanitizeArrayValuesAndKeys() {
        $testarray = array('abc' => array('some"quotes' => "a'b",
                                          'more' => 'quot"es'));
        $sanitized = array('abc' => array('some&quot;quotes' => 'a&#039;b',
                                          'more' => 'quot&quot;es'));
        
        $this->controller->publish('testarray', $testarray, true, true);
        
        $result = $this->controller->viewVars['testarray'];
        $this->assertTrue($this->helper->array_compare_recursive($result, $sanitized),
            'array sanitization, values and keys');
    }
    
    /**
     * does sanitization leave an empty array intact?
     */
    function testPublishEmptyArray() {
        $this->controller->publish('testarray', array());
        $result = $this->controller->viewVars['testarray'];
        $this->assertTrue(is_array($result) && empty($result), 'empty published array is preserved.');
    }
    
    /**
     * does sanitization work on simple strings?
     */
    function testPublishString() {
        $simplestring = 'blah"blah\'blah';
        $sanitized = 'blah&quot;blah&#039;blah';
        $this->controller->publish('teststring', $simplestring);
        $result = $this->controller->viewVars['teststring'];
        $this->assertEqual($result, $sanitized, 'simple string sanitization');
    }
    
    /**
     * is the array left untouched when desired?
     */
    function testSanitizeDisabled() {
        $testarray = array('abc' => array('some"quotes' => "a'b",
                                          'more' => 'quot"es'));
        $this->controller->publish('testarray', $testarray, false);
        
        $result = $this->controller->viewVars['testarray'];
        $this->assertTrue($this->helper->array_compare_recursive($result, $testarray),
            'array sanitization can be disabled');
    }
}

?>
