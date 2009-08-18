<?php
/*
 * License: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * Contributor(s):
 *   Dave Dash <dd@mozilla.com> (Original Author) 
 */

uses('sanitize');
vendor('sphinx/addonsSearch');
require_once('api_controller.php');

class Api15Controller extends ApiController
{

    public $name = 'Api15';

    public $newest_api_version = 1.5;   
    
    public function search($term) {
        $this->layout = 'rest'; 
        $as = new AddonSearch($this->Addon);
        try {
            list($matches, $total_results) = $as->query($term);
        }
        catch (AddonSearchException $e) {
            header("HTTP/1.1 503 Service Unavailable", true, 503);

            if (DEBUG > 0) {
                $this->publish('error', $e->getMessage());
            } else {
                $this->publish('error', "The search system is temporarily unavailable.");
            }
            
            $this->render();
            exit;
        }
        
        $this->_getAddons($matches);
        
        // var_dump($matches);
        // var_dump($this->viewVars['addonsdata']);
        $this->publish('api_version', $this->api_version); 
        $this->publish('guids', array_flip($this->Application->getGUIDList()));
        $this->publish('app_names', $app_names = $this->Application->getIDList()); 
        $this->publish('total_results', $total_results); 
        $this->publish('os_translation', $this->os_translation);   
        $this->publish('addonsdata', $this->viewVars['addonsdata']);
    }
}
