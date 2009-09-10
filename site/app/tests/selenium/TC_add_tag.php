<?php

require_once 'Testing/Selenium.php';
require_once 'PHPUnit/Framework/TestCase.php';
require_once 'class_amo.php';

class TC_add_tag extends PHPUnit_Framework_TestCase
{
	private $selenium;
	
	public function setUp()
	 {
		$this->selenium = new Testing_Selenium("*firefox", "http://preview.addons.mozilla.org");
		$this->selenium->start();
		$this->selenium->setContext('Testing Tags');
		$this->selenium->windowMaximize();
	 }
	
	//  public function tearDown()
	//     {
	//         $this->selenium->stop();
	//     }
	
	public function testTag()
	{		
		$userType = 'non_adm';
		$addOnType = 'firefox';
		$addOnToTest = 'Read It Later';
		$randomNum = rand(1,5);
		$tagName = "testSelRC_".$randomNum;
		
		$this->selenium->open('/');
		$amoObj = new AMOfunctions();
		$amoObj->login($userType,$this->selenium);
		
		// first, remove any eisting tags from current user
		$count = 1;
		while( $this->selenium->isElementPresent("class=tagitem") && $count<6 )
	 	     {
	 	      $this->selenium->click('tagid'); 
	 	      sleep(1);
	 	      $count++;
	 	      }
	 	      
		// +ve test. Add a tag
		$amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		$amoObj->AddTag($tagName,$this->selenium);
		sleep(1);
		$amoObj->VerifyText($tagName,$this->selenium);
		$this->selenium->click('tagid');
		
		//-ve test. Add a tag with leading & trailing spaces
		$amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		$tagWithSpaces = " ".$tagName." ";
		$amoObj->AddTag($tagWithSpaces,$this->selenium);
		sleep(1);
		$amoObj->VerifyText($tagName,$this->selenium);
		$this->selenium->click('tagid');
		
		// -ve test. Add a duplicate tag
		$amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		$amoObj->AddTag($tagName,$this->selenium);
		
		// -ve test. Add a long tag
		$longTag = $tagName."_".$tagName."_".$tagName."_".$tagName;
		$amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		$amoObj->AddTag($longTag,$this->selenium);
		sleep(1);
		$amoObj->VerifyText($longTag,$this->selenium);
		$this->selenium->click('tagid');
		
		// -ve test. Add a tag with special chars
		$specialTag = '**&&^%$#@!~~???<,>.//}}';
		$amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		$amoObj->AddTag($specialTag,$this->selenium);
		sleep(1);
		//$amoObj->VerifyText($specialTag,$this->selenium);
		$this->selenium->click('tagid');
		
	   $count = 1;
		while( $this->selenium->isElementPresent("class=tagitem") && $count<6 )
	 	     {
	 	      $this->selenium->click('tagid'); 
	 	      sleep(1);
	 	      $count++;
	 	      }
	}

}
?>
