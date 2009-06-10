<?php

class AddonModelTest extends UnitTestCase {

    function AddonModelTest() {
        loadModel('Addon');
        $this->Addon = new Addon();
    }

    function testGetAddonsForAuthors() {
        $this->assertEqual($this->Addon->getAddonsForAuthors(array(4)),
                           array(6));
        $this->assertEqual(sort($this->Addon->getAddonsForAuthors(array(1, 3))),
                           array(1, 2, 3, 4, 5));
    }
}
?>
