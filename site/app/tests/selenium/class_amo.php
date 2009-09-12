<?php
require_once '/usr/local/PEAR/PHPUnit/Framework.php';
require_once '/usr/local/PEAR/PEAR/Exception.php';
require_once 'configurationAMO.php';

    class AMOfunctions
    {
      protected $configAMO;
      
      function __construct()
      {
      	$this->configAMO = new ConfigAMO();
      }
    	
    	public function login($userType,$selenium)
    	{
    		  $user;
    		  $user = $this->configAMO->getUserInfo($userType); 
    		  $selenium->open('/en-US/firefox/users/login');
    		  if($selenium->isElementPresent('link=Log out'))
    		    {
    		  	$selenium->click('link=Log out');
    		  	$selenium->waitForPageToLoad($this->configAMO->getDefaultWaitTime());
    		  	$selenium->open('/en-US/firefox/users/login');
    		    }
    		  $selenium->type("LoginEmail", $user['login']);
			  $selenium->type("LoginPassword", $user['pwd']);
			  $selenium->click("class=prominent");
			  $selenium->waitForPageToLoad($this->configAMO->getDefaultWaitTime());
			  if($selenium->isTextPresent($user['nick']) || $selenium->isTextPresent($user['fname']) )
				 echo "User ".$user['nick']." ".$user['fname']." successfully logged in\n";
			 else
			     exit("User ".$user['nick']." ".$user['fname']." unable to log in\n");
    		
    	} // end of function login
    	
    	public function logout($userType,$selenium)
    	{
    		$user;
    		$user = $this->configAMO->getUserInfo($userType); 
    		$selenium->click('link=Log out');
    		$selenium->waitForPageToLoad($this->configAMO->getDefaultWaitTime());
    		if($selenium->isElementPresent('link=Log in'))
    		   echo "User ".$user['nick']." logged out successfully\n";
    		else
    		   echo "User ".$user['nick']." unable to log out\n";
    	}
    	
    	public function goToAddon($addOnType,$addOnName,$selenium)
    	{
    		$addOnId = $this->configAMO->getAddOnId($addOnType,$addOnName);
    		$message = "Unable to open Add-on details page for Addon:$addOnName AddOnId:$addOnId\n";
    		
    		$selenium->open("/en-US/$addOnType/addon/$addOnId");
    		$title = $selenium->getTitle();
    		$addOnNameRegEx = '/'.$addOnName.'/';
    	
    		PHPUnit_Framework_Assert::assertRegExp($addOnNameRegEx,$title,$message);
    		
    		
    	} // end of function goToAddOn
    	
    	public function postReview($addOnType,$addOnName,$reviewTitle,$reviewRating,$reviewBody,$selenium)
    	{
    		  $selenium->click("link=*Detailed*Review*");
			  $selenium->waitForPageToLoad($this->configAMO->getDefaultWaitTime());
			  $selenium->type("ReviewTitle", $reviewTitle);
			  $selenium->click("link=".$reviewRating);
			  $selenium->type("ReviewBody", $reviewBody);
			  $selenium->click("class=amo-submit");
			  $selenium->waitForPageToLoad($this->configAMO->getDefaultWaitTime());
			  
    	}
    	
    	public function VerifyReview($userType,$addOnType,$addOnName,$reviewTitle,$reviewRating,$reviewBody,$selenium)
    	{
    		$titleRegExp = '/Reviews for '.$addOnName.'/';
    		$addOnId = $this->configAMO->getAddOnId($addOnType,$addOnName);
    		$message = "Unable to open Reviews page for Addon:$addOnName AddOnId:$addOnId\n";
    		$user    = $this->configAMO->getUserInfo($userType);
    		
    		$selenium->open("/en-US/$addOnType/reviews/display/$addOnId");
    		$selenium->click("link=*previous*review*".$user['nick']."*");
    		sleep(2);
    		PHPUnit_Framework_Assert::assertRegExp($titleRegExp,$selenium->getTitle(),$message);
    		 
    		//$bodyText = $selenium->getBodyText();	
    		   $this->VerifyText($reviewTitle,$selenium);	   
    	      $this->VerifyText($reviewTitle,$selenium);
    		   
    		
    		//PHPUnit_Framework_Assert::assertRegExp('/'.$reviewTitle.'/',$bodyText);
    		//PHPUnit_Framework_Assert::assertRegExp('/'.$reviewBody.'/',$bodyText);
    		
    		
    	}
    	
    	public function VerifyText($text,$selenium)
    	{
    		if($selenium->isTextPresent($text))
    		   echo "PASS: verified ".$text."\n";
    		else
    			echo "FAIL: Did not find ".$text."\n";
    			
    		   
    	}
    	
    	public function VerifyPageTitle($pattern,$actual_title,$selenium)
    	{	
    		$pattern = '/'.$pattern.'/';
    	
    		PHPUnit_Framework_Assert::assertRegExp($pattern,$actual_title);
    	}
    	
    	public function AddTag($tagText,$selenium)
    	{
    		$selenium->click("addatag");
            $selenium->type("newTag", $tagText);
            $selenium->click("addtagbutton");
    	}
     	
    } // end of class AMO
?>