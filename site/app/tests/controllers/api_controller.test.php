<?php

class ApiControllerTest extends WebTestHelper {

    function setUp() {

        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Api', $this);
        loadModel('Config');
        $this->Config =& new Config();
        $this->Config->caching = False;
        $this->Config->cacheQueries = False;

        // Store the old value so we can reset it in teardown.
        $this->_oldVal = $this->Config->getValue('api_disabled');
        // Make sure the api is disabled.
        $this->Config->save(array('key' => 'api_disabled',
                                  'value' => '1'));
        // Clear out the config cache.
        $this->Config->expire();
    }

    function tearDown() {
        $this->Config->save(array('key' => 'api_disabled',
                                  'value' => $this->_oldVal));
    }

    function testApiDisabled() {
        $this->assertEqual($this->Config->getValue('api_disabled'), 1);
        $this->getAction('/api/list_addons');
        $this->assertResponse('503');
    }
}
