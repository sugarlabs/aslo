<?php

class AdminAddonsStatusTest extends WebTestHelper {

    function AdminConfigTest() {
        $this->WebTestCase('Views->Admin->Addons Status Test');
    }

    function setUp() {
        $this->login();
        $this->getAction('/admin/addons?q=[7]');
    }

    function testRemoraPage() {
        $this->assertWantedPattern('/Mozilla Add-ons/i', 'pattern detected');
        $this->assertResponse('200');
    }

    /**
     * There should be links for each version of the addon.
     */
    function testVersionLinks() {
        $this->assertLinkLocation('/editors/review/9', '9');
        $this->assertLinkLocation('/editors/review/1', '1');
    }
}
?>
