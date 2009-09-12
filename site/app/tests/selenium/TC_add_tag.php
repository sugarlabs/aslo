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
		$userAdm  = 'adm';
		$addOnType = 'firefox';
		$addOnToTest = 'Read It Later';
		$randomNum = rand(1,5);
		$tagName = "testSelRC_".$randomNum;
		
		$this->selenium->open('/');
		$amoObj = new AMOfunctions();
		$amoObj->login($userType,$this->selenium);
		
		// first, remove any eisting tags from current user
		$count = 1;
		while( $this->selenium->isElementPresent("tagid") && $count<8 )
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
		
		// remove all the tags added by current user
	   $count = 1;
		while($count<6 )
	 	     {
	 	      $this->selenium->click('tagid'); 
	 	      sleep(1);
	 	      $count++;
	 	      }
	 	      
	 	// add one more tag which will we deleted by an admin user
	 	   $amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		   $amoObj->AddTag($tagName,$this->selenium);
		   sleep(1);
		   $amoObj->VerifyText($tagName,$this->selenium);
		   $amoObj->logout($userType,$this->selenium);
		   
		// log in with admin account, delete all tags added by non-adm user
		// and add a tag which a non-adm user will try to delete
		   $amoObj->login($userAdm,$this->selenium);
		   $amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		
		// first, remove any existing tags from any user
		$count = 1;
		while($this->selenium->isElementPresent("tagid") && $count<8)
	 	     {
	 	      $this->selenium->click('tagid'); 
	 	      sleep(1);
	 	      $count++;
	 	      }
	 	      
		// Add a tag
		$randomNum = rand(1,5);
		$tagName2 = "testSelRC_".$randomNum;
		$amoObj->AddTag($tagName2,$this->selenium);
		sleep(1);
		$amoObj->VerifyText($tagName2,$this->selenium);
		$amoObj->logout($userAdm,$this->selenium);
	
		// login with non-adm account & try to delete the
		// tag added by a adm.
		
		$amoObj->login($userType,$this->selenium);
		$amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
	    $count = 1;
		while( $this->selenium->isElementPresent("class=tagid") && $count<5 )
	 	     {
	 	      $this->selenium->click('tagid'); 
	 	      sleep(1);
	 	      $count++;
	 	      }
	 	$amoObj->VerifyText($tagName2,$this->selenium);
		$amoObj->logout($userType,$this->selenium);
		    
	} // end of function testTag()

}
?>
