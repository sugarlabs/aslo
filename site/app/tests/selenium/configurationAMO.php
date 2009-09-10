<?php

	 class ConfigAMO
	 {
	 	protected $defaultWaitTime = 50000;
	 	protected $user        = array(
			 	                       'adm'=>array('login'=>'asad_chingari@yahoo.com','pwd'=>'1234','fname'=>null,'nick'=>'vish_moz'),
			 	                       'non_adm'=>array('login'=>'vishal82@hotmail.com','pwd'=>'1234','fname'=>'Vish','nick'=>'vish_non_adm')
			 	                      );
	    protected $addOnsArray = array('firefox'=>array(
						                                   'FoxyProxy'=>array('id'=>'2464'),
						                                   'Greasemonkey'=>array('id'=>'748'),
	                                                       'Read It Later'=>array('id'=>'7661')
			                                           ),
		                               'thunderbird'=>array(
			                                                'ThunderBrowse'=>array('id'=>'5373'),
			                                                'ThreadBubble'=>array('id'=>'5362')
		                                           
		                                          
		                                               )
	                                  );
	                                  
	    protected $reviewPostMsg = 'Your review was saved successfully';
	 	                      
	 	public function getDefaultWaitTime()
	 	 {
	 		return $this->defaultWaitTime;
	 	 }
	 	
	    public function getUserInfo($key)
	 	 {
	 		return $this->user[$key];
	 	 }
	 	 
	 	public function getAddOnId($app,$name)
	 	{
	 	  return $this->addOnsArray[$app][$name]['id'];	
	 	}
	 	
	 	public function getReviewPostMsg()
	 	{
	 		return $this->reviewPostMsg;
	 	}
	 } // end of class ConfigAMO
?>