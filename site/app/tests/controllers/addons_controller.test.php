<?php

class AddonsTest extends UnitTestCase {
 
    function testLoad() {
        $this->helper =& new UnitTestHelper();
        $this->controller = $this->helper->getController('Addons', $this);
        $this->helper->mockComponents($this->controller, $this);
    }

    function testDisplay() {
        $this->controller->status = array(STATUS_PUBLIC);
        $this->controller->params['controller'] = 'Addons';
        $output = $this->helper->callControllerAction($this->controller, 'display', $this, array(7));
        $this->assertWantedPattern('/Harvest MicroFormats from web pages/', $output);
    }

    function countOtherAddons($item, $key, $params) {
        $count = &$params[0];
       
        if (is_array($item)) {
            array_walk($item, array(&$this, 'countOtherAddons'), $params);
        } else {
            if ($key === 'summary' && !($item == 'Harvest MicroFormats from web pages with the click of a button.' || $item == '' || $item == 'en-US' || $item == 53)) {
                $count++;
            }
        }
       
    }
   
    /**
     * This tests that no addons other than #7 have data loaded by checking for "summary" data
     */
    function testNoExcessiveRetrieval() {
        $this->helper->callControllerAction($this->controller, 'display', $this, array(7));
        $count = 0;
        array_walk($this->controller->viewVars, array(&$this, 'countOtherAddons'), array(&$count));
        
        $this->assertEqual($count, 0, "no other addons loaded: %s");
    }

    function testSandboxedVersionsNotListed() {
        $this->helper->callControllerAction($this->controller, 'display', $this, array(4022));
        $this->assertEqual($this->controller->viewVars['addon']['Version'][0]['File'][0]['status'], STATUS_PUBLIC, 'only showing public versions: %s');
    }
    function testSandboxVersionsExcludedInPublicVersionList() {
        $count = 0;
        $this->helper->callControllerAction($this->controller, 'versions', $this, array(4022));
        foreach ($this->controller->viewVars['versions'] as $version) {
            foreach ($version['File'] as $file) {
                if ($file['status'] != STATUS_PUBLIC)
                    $count++;
            }
        }
        $this->assertEqual($count, 0, 'sandboxed versions in public list: %s');
    }
    function testNoSandboxDisplayForVersions() {
        $this->helper->callControllerAction($this->controller, 'versions', $this, array(4023));
        $this->assertTrue(empty($this->controller->viewVars['versions']));
    }
    function nyi_testSandboxedVersionsListedWhenViewingSandbox() {
        
    }

    /**
     * Test if the different addon list sort orders are correct
     */
    function testListSortOrders() {
        $all_expected_results = array(
            'name'      => array(4022, 7, 4021),
            'popular'   => array(7, 4021, 4022),
            'updated'   => array(4021, 4022, 7),
            'rated'     => array(4022, 7, 4021),
            'newest'    => array(4022, 4021, 7),
        );

        $this->controller->Pagination->setReturnvalue('init', array(0, 10, 1));
        $this->controller->status = array(STATUS_PUBLIC);
        $this->controller->sandboxAccess = false;

        foreach (array_keys($all_expected_results) as $sort_by) {
            $expected_results = $all_expected_results[$sort_by];
            $num_results = count($expected_results);
            
            // prepare controller, then fetch its results
            $url = '/'.LANG.'/'.APP_SHORTNAME."/browse/type:1/cat:all/sort:{$sort_by}";
            $this->controller->params['url']['url'] = $url;
            $this->controller->namedArgs = array('type' => '1', 'cat'=>'all', 'sort'=>$sort_by);
            $this->controller->set('paging', array(
                'sortBy' => $sort_by,
                'direction' => '',
                'pageCount' => 1,
                'url' => $url,
                'showLimits' => true,
                'maxPages' => 10,
                'paramSeperator' => '/',
                'show' => 10,
                'page' => 1,
                'total' => count($expected_results),
                'resultsPerPage' => array(5),
                'paramStyle' => 'get',
                'ajaxDivUpdate' => 'content',
                ));
            $this->helper->callControllerAction($this->controller, 'browse', $this);
            
            $addons = $this->controller->viewVars['addons'];
            $this->assertEqual(count($addons), $num_results, $sort_by . ' sorting: received the correct number of addons');
            foreach ($addons as $addon) {
                $expected_id = array_shift($expected_results);
                $this->assertEqual($addon['Addon']['id'], $expected_id, $sort_by . " sorting: correct addon id {$addon['Addon']['id']} received");
            }
        }
    }

}

?>
