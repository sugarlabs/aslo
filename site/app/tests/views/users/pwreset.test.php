<?php

class PwresetTest extends WebTestHelper {

    function PwresetTest() {
        $this->WebTestCase("Views->Users->pwreset Tests");
    }

    function setUp() {
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Users', $this);

        $id = 5;
        $this->resetcode = $this->controller->User->setResetCode($id);
        $this->getAction("/users/pwreset/{$id}/".$this->resetcode);
    }

    function testRemoraPage() {
        // just checks if the page works or not
        $this->assertWantedPattern('/Mozilla Add-ons/i', "pattern detected");
    }

    function testEmbeddedResetCode() {
        $this->assertNoPattern("@users/login\?to=.*{$this->resetcode}@", 'Embedded in login link?');
        $this->assertNoPattern("@advanced-search-toggle.*{$this->resetcode}@", 'Embedded in advanced search toggle?');
    }

    function testCacheHeader() {
        $this->assertHeader('Cache-Control', 'no-store, must-revalidate, post-check=0, pre-check=0, private, max-age=0');
        $this->assertHeader('Pragma', 'private');
    }
}
