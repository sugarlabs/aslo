<?php

class ImageTest extends WebTestHelper {
    function setUp() {
        global $TestController;
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Addons', $this);
        $this->controller->base = $TestController->base;
        loadComponent('Image');
        $this->controller->Image =& new ImageComponent();
        $this->controller->Image->startup($this->controller);	
    }
    
    function testAddonIcon() {
        $url = $this->controller->Image->getAddonIconURL(7);
        //The models tests change the modified time, so the timestamp isn't
        //predictable; thus, only checking that the numbers are there.
        $wantedPattern = "#".$this->actionPath('/images/addon_icon/7/\d{10}')."#";
        UnitTestCase::assertPattern($wantedPattern, $url, htmlentities($wantedPattern));
        $this->getPath($url);
        $this->assertResponse('200');
        $this->assertMime('image/gif');
    }
    
    function testAddonThumbnail() {
        $url = $this->controller->Image->getHighlightedPreviewURL(7);
        $this->assertEqual($url, $this->actionPath('/images/t/1/1159459234'));
        $this->getPath($url);
        $this->assertResponse('200');
        $this->assertMime('image/png');
    }
    
    function testAddonPreview() {
        $url = $this->controller->Image->getHighlightedPreviewURL(7, 'full');
        $this->assertEqual($url, $this->actionPath('/images/p/1/1159459234'));
        $this->getPath($url);
        $this->assertResponse('200');
        $this->assertMime('image/png');
    }
}

?>
