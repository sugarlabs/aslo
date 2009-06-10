<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is licensed under the MIT license (see below).
 *
 * The Initial Developer of the Original Code is R. Wong.
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Frederic Wenzel <fwenzel@mozilla.com>
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK ***** */

/*****************************************************************************
The MIT License

Copyright (c) 2007 R. Wong (Lick)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*****************************************************************************/

// Get "recaptchalib.php" from http://code.google.com/p/recaptcha/downloads/list?q=label:phplib-Latest 
// and place it in "vendors/recaptcha".
vendor('recaptcha/recaptchalib');


class RecaptchaComponent extends Object
{
    var $controller = true;
    var $disableStartup = true;
    
    var $enabled = false;
    
    function __construct() {
        if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED
            && defined('RECAPTCHA_PRIVATE_KEY') && defined('RECAPTCHA_PUBLIC_KEY'))
            $this->enabled = true;
            
        return parent::__construct();
    }

    function display()
    {
        if (!$this->enabled) return '';
        
        return recaptcha_get_html(RECAPTCHA_PUBLIC_KEY);
    }
    

    function is_valid($form)
    {
        if (!$this->enabled) return false;
        
        // grab remote IP through forwarded-for header when served by cache
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $client_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $client_ip = trim($client_ips[0]);
        } else {
            $client_ip = $_SERVER['REMOTE_ADDR'];
        }
        
        if (isset($form['recaptcha_challenge_field']) &&
            isset($form['recaptcha_response_field']) )
        {
            $resp = recaptcha_check_answer(
                RECAPTCHA_PRIVATE_KEY, 
                $client_ip,
                $form['recaptcha_challenge_field'], 
                $form['recaptcha_response_field']
            );

            if ($resp->is_valid)
                return true;
        }
        
        return false;
    }
}


/*****************************************************************************
Example:

class ExampleController extends AppController
{
    var $uses = array();
    var $components = array('Recaptcha');
    var $autoLayout = false;
    
    function index()
    {
        $message = 'Invalid reCAPTCHA.';
        
        if (isset($this->params['form']) && $this->Recaptcha->is_valid($this->params['form']) )
        {
            $message = 'Valid reCAPTCHA!';
        }
        
        $this->set('message', $message);
        $this->set('recaptcha', $this->Recaptcha->display());
    }
}
*****************************************************************************/

?>
