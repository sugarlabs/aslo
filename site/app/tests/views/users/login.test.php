<?php

class LoginTest extends WebTestHelper {

    function LoginTest() {
        $this->WebTestCase("Views->Users->login Tests");
    }

    function setUp() {
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Users', $this);
    }

    function testBug502472() {
        // XSS test
        $this->getAction("/users/login?to=%22%3E%3Cscript%3Ealert%28%22xss%22%29%3C/script%3E");

        $this->assertNoPattern('@<script>alert\("xss"\)</script>@i', 'Assert No XSS');
        $this->assertWantedPattern('@&quot;&gt;&lt;script&gt;alert\(&quot;xss&quot;\)&lt;/script&gt;@i', 'Assert Pattern is entitized');
    }
}
