<?php

class VersioncommentModelTest extends UnitTestCase {

    function VersioncommentModelTest() {
        loadModel('Versioncomment');
        $this->Versioncomment = new Versioncomment();
    }

    function testGetThreadRoot() {
        $root = $this->Versioncomment->getThreadRoot(1, 2);
        $this->assertEqual($root['Versioncomment']['id'], 1, 'Expected root versioncomment of thread');
    }

    function testGetThreadTree() {
        $comments = $this->Versioncomment->getThreadTree(1);
        $this->assertEqual($comments[0]['Versioncomment']['id'], 1, 'Expected root versioncomment of thread');
        $this->assertEqual($comments[1]['depth'], 1, 'Expected depth of first reply');
    }
}
?>
