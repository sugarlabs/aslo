<?php

set_include_path(get_include_path() . PATH_SEPARATOR . './PEAR/');
require_once 'Testing/Selenium.php';
require_once 'PHPUnit/Framework/TestCase.php';

class AMOTest extends PHPUnit_Framework_TestCase
{
  private $selenium;
  
public function setUp()
{
//$this->selenium = new Testing_Selenium("*firefox", "https://preview.addons.mozilla.org");
$this->selenium = new Testing_Selenium("*firefox", "https://addons.mozilla.org");
$this->selenium->start();
$this->selenium->setTimeout("1200000");

}

public function testMyTestCase()
{
	$this->selenium->open("/");
	$this->selenium->windowMaximize(); 
	$this->selenium->click("link=Log in");
	$this->selenium->waitForPageToLoad("50000");
	$this->selenium->type("LoginEmail", "vkamdar@mozilla.com");
	//$this->selenium->type("LoginEmail", "asad_chingari@yahoo.com");
	$this->selenium->type("LoginPassword", "1234");
	  $this->selenium->click("class=amo-submit");
	//$this->selenium->fireEvent("//form[@id='login']/div[5]/button","blur");
	$this->selenium->waitForPageToLoad("50000");
	$this->selenium->open("/en-US/firefox/addon/590");
	$this->selenium->waitForPageToLoad("50000");
	// 
	// $x =  $this->selenium->isElementPresent("class=tagitem") ;
	//    echo $x;
	// 
	//    
	//  while( $this->selenium->isElementPresent("class=tagitem") )
// 	     {
// 	      $this->selenium->click("tagid"); 
// 	      }
// 	 
	 //   $tagNamePrefix = "tag";
// 	 
// 	for($i=81;$i<100;$i++)
// 	{
// 	 $this->selenium->click("addatag");
// 	 $this->selenium->type("newTag", $tagNamePrefix.$i);
// 	$this->selenium->click("addtagbutton");
// 	sleep(2);
// 	 }


}
}
?>
