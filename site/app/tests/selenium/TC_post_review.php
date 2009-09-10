<?php

require_once 'Testing/Selenium.php';
require_once 'PHPUnit/Framework/TestCase.php';
require_once 'class_amo.php';

class TC_post_review extends PHPUnit_Framework_TestCase
{
	private $selenium;
	
	public function setUp()
	 {
		$this->selenium = new Testing_Selenium("*firefox", "https://preview.addons.mozilla.org");
		$this->selenium->start();
		$this->selenium->setContext('Post a Review');
		$this->selenium->windowMaximize();
		//$this->selenium->setSpeed(10000);
	 }
	
	//  public function tearDown()
	//     {
	//         $this->selenium->stop();
	//     }
	
	public function testPostReview()
	{
		$userType = 'non_adm';
		$addOnType = 'firefox';
		$addOnToTest = 'FoxyProxy';
		$reviewNum = rand();
		$reviewTitle = "TestSelRC#".$reviewNum;
		$reviewBody = "this is a SelRC automated review #".$reviewNum;
		$reviewPostMsg = 'Your review was saved successfully';
		$reviewRating = rand(1,5);
		$filePath = dirname(__FILE__).'/IOfiles/long_review.txt';
		$fileHandle = fopen($filePath,'r',TRUE) or exit("Unable to open file!");
	    $contents   = fread($fileHandle,filesize($filePath));
		$this->selenium->open('/');
		$amoObj = new AMOfunctions();
		$amoObj->login($userType,$this->selenium);
		
		// +ve test. Post a regular size review
		$amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		$amoObj->postReview($addOnType,$addOnToTest,$reviewTitle,$reviewRating,$reviewBody,$this->selenium);
		$amoObj->VerifyText($reviewPostMsg,$this->selenium);
		$amoObj->VerifyReview($userType,$addOnType,$addOnToTest,$reviewTitle,$reviewRating,$reviewBody,$this->selenium);
		sleep(2);
		
		// -ve test. Post a huge text
		$amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		$amoObj->postReview($addOnType,$addOnToTest,$reviewTitle,$reviewRating,$contents,$this->selenium);
		$amoObj->VerifyText($reviewPostMsg,$this->selenium);
	    $amoObj->VerifyReview($userType,$addOnType,$addOnToTest,$reviewTitle,$reviewRating,$contents,$this->selenium);	    
	    sleep(2);
	    fclose($fileHandle);
	    
	    //-ve test. Post a blank review and test for the error msg
	    $blank_review = '';
	    $error_msg = 'There are errors in this form';
	    $amoObj->goToAddon($addOnType,$addOnToTest,$this->selenium);
		$amoObj->postReview($addOnType,$addOnToTest,$reviewTitle,$reviewRating,$blank_review,$this->selenium);
	    $this->selenium->waitForPageToLoad('30000');
		$amoObj->VerifyText($error_msg,$this->selenium);
	
		
		
	}

}
?>
