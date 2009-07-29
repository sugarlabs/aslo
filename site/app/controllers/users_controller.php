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
 * The Original Code is addons.mozilla.org site.
 *
 * The Initial Developer of the Original Code is
 * The Mozilla Foundation.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Andrei Hajdukewycz <sancus@off.net> (Original Author)
 *   Wil Clouser <clouserw@mozilla.com>
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

class UsersController extends AppController
{
    var $name = 'Users';
    var $uses = array('User', 'Addon', 'Collection', 'Eventlog', 'Review', 'Version', 'Versioncomment');
    var $components = array('Amo', 'Developers', 'Email', 'Image', 'Ldap', 'Session', 'Pagination', 'Recaptcha');
    var $helpers = array('Html', 'Link', 'Javascript');
    var $beforeFilter = array('checkCSRF', 'getNamedArgs', '_checkSandbox', 'checkAdvancedSearch');
    var $exceptionCSRF = array("/users/login", "/users/register", "/users/pwreset");	
    var $layout = 'amo2009';
    var $namedArgs = true;

    var $securityLevel = 'high';

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
        
        // Disable memcache for user queries except in public display page
        if ($this->action != 'info') {
            $this->User->caching = false;
        }
    }
    
    /**
     * Directly calling the user index page just forwards us to the most
     * userful place. For guests, that's the login page. For users, it's
     * their editing page.
     */
    function index() {
        if ($this->Session->check('User')) {
            // logged in
            $this->redirect('/users/edit');
            return;
            
        } else {
            // guest
            $this->redirect('/users/login');
            return;
        }
    }

    
    /**
     * Register a new user
     */
    function register() {
        // if we are logged in, go to the main page
        if ($this->Session->check('User')) {
            $this->redirect($this->referer('/', true));
            return;
        }

        $this->disableCache();
    
        $this->pageTitle = _('users_register_pagetitle'). ' :: '. sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        // $this->publish('cssAdd', array('forms'));
        $this->publish('breadcrumbs', array(_('users_register_pagetitle') => '/users/register'));
        $this->publish('subpagetitle', _('user_form_registration'));
        if (empty($this->data)) {
            $this->render();
            return;
        
        } else {
            // filter nickname characters
            $this->data['User']['nickname'] = $this->_filterNick($this->data['User']['nickname']);
            
            // check captcha
            if ($this->Recaptcha->enabled && (!isset($this->params['form']) ||
                !$this->Recaptcha->is_valid($this->params['form']))) {
                $this->User->invalidate('captcha');
            }
            
            $this->data['User']['confirmationcode'] = md5(mt_rand());
            
            $this->User->data = $this->data;
            $this->Amo->clean($this->User->data);
            // hash password(s)
            $this->User->data['User']['password'] = $this->User->createPassword($this->User->data['User']['password']);
            
            // compare passwords
            if ($this->data['User']['password'] !== $this->data['User']['confirmpw'])
                $this->User->invalidate('confirmpw');
            // no empty pw
            if ($this->data['User']['password'] == '')
                $this->User->invalidate('password');
            // email has to be unique
            $allemail = $this->User->findAll("email='{$this->User->data['User']['email']}'");
            if (!empty($allemail)) {
                $this->User->invalidate('email');
                $this->publish('error_email_notunique', true);
            }
            // if nickname is defined it has to be unique
            if (!$this->data['User']['nickname'] == '') {
                $allnicks = $this->User->findAll("nickname='{$this->User->data['User']['nickname']}'");
                if (!empty($allnicks))
                    $this->User->invalidate('nickname');
            }
            
            // any errors? Get out of here.
            if (!$this->User->save()) {
                $this->publish('errorMessage', true);
                $this->render();
                return;
            }
            
            // send confirmation email
            $this->_sendConfirmationCode($this->User->getLastInsertId());
            
            // show success message
            $this->render('register_complete');
            // @TODO enable default user access
        }
    }

    /**
     * Send the user his confirmation code by email
     * @param int user id
     * @return true on success
     */
    function _sendConfirmationCode($id) {
        $data = $this->User->findById($id);

        // don't allow sending an email to already confirmed users.
        if (empty($data['User']['confirmationcode'])) {
            $this->flash(_('error_user_already_confirmed'), '/', 3);
            return false;
        }
        
        $this->publish('userid', $id);
        $this->publish('data', $data);
        $this->Email->template = 'email/confirm';
        $this->Email->to = $data['User']['email'];
        $this->Email->subject = sprintf(_('user_email_confirm_subject'), APP_PRETTYNAME);
        $result = $this->Email->send();
        return true;
    }
    

    /**
     * To verify a user's email they get sent a verification link. This checks
     * if the accessed url is correct and unlocks the user.
     * @param int $id the user's id
     * @param string $code the verification code sent in the email
     */
    function verify($id = 0, $code = '') {
        $this->Amo->clean($id);
        $this->Amo->clean($code);
        
        if (!$id || !$code) {
            $this->flash(sprintf(_('error_missing_argument'), 'user_id or code'), '/', 3);
            return;
        }

        $thisuser = $this->User->findById($id);
        if (empty($thisuser)) {
            $this->flash(_('error_user_notfound'), '/', 3);
            return;
        }

        if ($code == 'resend') { // resend the confirmation code to the user's email address
            if (true === $this->_sendConfirmationCode($id))
                $this->flash(_('user_confirmationcode_resent'), '/', 3);
            return;
        }

        if ($code !== $thisuser['User']['confirmationcode']) {
            $this->flash(_('error_user_badconfirmationcode'), '/', 3);
            return;
        }

        // remove confirmation code from DB
        $this->User->id = $id;
        $this->User->saveField('confirmationcode', '');
        $this->flash(_('user_verified_okay'), '/users/login?to='.urlencode('/'), 3);
    }


    /**
     * Request a password reset. The user gets sent an email with a url. When they
     * access it, they can change their password.
     * @param int $id user id
     * @param string $code the password reset code sent in the email
     */
    function pwreset($id = 0, $code = '') {
        $this->Amo->clean($id);
        $this->Amo->clean($code);
        
        $this->pageTitle = _('users_pwreset_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('cssAdd', array('forms'));
        $this->publish('breadcrumbs', array(_('users_pwreset_pagetitle') => '/users/pwreset'));
        $this->publish('subpagetitle', _('user_pwreset_header'));
        if (!$id && !$code) {
            if (!isset($this->data['User']['email'])) {
                $this->render();    // display 'enter email' form
            
            } else {
            
                $thisuser = $this->User->findByEmail($this->data['User']['email']);
                if (empty($thisuser)) {
                    // user not found
                    $this->User->invalidate('email');
                    $this->render();
                } else {
                    // user found: send pw reset URL via email
                    $this->publish('data', $thisuser);
                    $resetCode = $this->User->setResetCode($thisuser['User']['id']);
                    $this->publish('resetcode', $resetCode);
                    $this->Email->template = 'email/pwreset';
                    $this->Email->to = $this->data['User']['email'];
                    $this->Email->subject = sprintf(_('user_email_pwreset_subject'), APP_PRETTYNAME);
                    $result = $this->Email->send();

                    $this->flash(_('user_pwreset_link_sent'), '/', 3);
                }
            }
            return;
        }

        // id and/or code was sent, make sure the page isn't cached.
        $this->disableCache();

        // Remove 'id/resetcode' from the URI so it doesn't get echoed.
        $_SERVER['REQUEST_URI'] = preg_replace('@pwreset/.*$@', 'pwreset/', $_SERVER['REQUEST_URI']);
        if (isset($this->params['url']['url'])) {
            $this->params['url']['url'] = preg_replace('@pwreset/.*$@', 'pwreset/', $this->params['url']['url']);
        }

        if (!$id || !$code) {
            $this->flash(sprintf(_('error_missing_argument'), 'user_id or code'), '/', 3);
            return;
        }

        $thisuser = $this->User->findById($id);
        if (empty($thisuser)) {
            $this->flash(_('error_user_notfound'), '/', 3);
            return;
        }

        if (!$this->User->checkResetCode($id, $code)) {
            // TODO: update message re: expiration
            $this->flash(_('error_user_badconfirmationcode'), '/', 3);
            return;
        }

        $this->publish('email', $thisuser['User']['email']);
        if (isset($this->data['User']['confirmpw'])) {
            // confirm passwords
            if ($this->data['User']['password'] !== $this->data['User']['confirmpw']) {
                $this->User->invalidate('confirmpw');
                $this->data['User']['password'] = '';
                $this->data['User']['confirmpw'] = '';
                $this->render();
                return;
            }
            if ($this->data['User']['password'] == '') {
                $this->User->invalidate('password');
                $this->data['User']['password'] = '';
                $this->data['User']['confirmpw'] = '';
                $this->render();
                return;
            }

            // store new pw
            $newpw = array();
            $newpw['User']['password'] = $this->User->createPassword($this->data['User']['password']);
            $this->User->id = $id;
            $this->User->save($newpw);
            // success
            $this->flash(_('user_pwreset_okay'), '/users/login', 3);
        }
    }
    

    /**
     * Give the user a log in form and actually log them in.
     */
    function login() {
        // clean up referer
        if (!isset($this->data['Login']['referer'])) {
            $referer = $this->referer('/', true);
        } else {
            $referer = html_entity_decode($this->data['Login']['referer']);
            // Don't need any referrers that have :// or newlines in them
            if (preg_match("/(:\/\/|\r|\n)/", $referer)) {
                $referer = '/'; // evil referer: forward to front page instead
            }
        }
        
        // if we are logged in, go to the main page
        if ($this->Session->check('User')) {
            $this->redirect($this->referer('/', true));
            return;
        }
    
        $this->pageTitle = _('users_login_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('cssAdd', array('forms'));
        $this->publish('subpagetitle', _('user_form_login'));
        $this->publish('loginerror', false);
        
        // by default, just give them a login screen
        if (!isset($this->data['Login']['email']) || !isset($this->data['Login']['password'])) {
            $this->render();
            return;
        }
        
        // if any field is empty, return error
        if (empty($this->data['Login']['email']) || empty($this->data['Login']['password'])) {
            $this->data['Login']['password'] = '';
            $this->publish('loginerror', true);
            $this->render();
            return;
        }

        $someone = $this->User->findByEmail($this->data['Login']['email']);
        if (!empty($someone['User']['id']) && $someone['User']['confirmationcode'] != '') {
            // user not confirmed yet
            $this->publish('userid', $someone['User']['id']);
            $this->publish('email', $someone['User']['email']);
            $this->render('activatefirst');
            return;
        }

        if ($this->User->checkPassword($someone['User'], $this->data['Login']['password'])) {
            
            //Set expiration to two weeks if they check 'remember me'
            $expirationTime = isset($this->data['Login']['remember']) ? 60*60*24*7*2 : 0;
            
            // correct credentials
            $someone['User']['Group'] = $someone['Group'];
            $this->Session->start($expirationTime);
            $this->Session->write('User', $someone['User']);
            
            // Set app cookie
            setcookie('AMOappName', APP_SHORTNAME, 0, '/');
            
            $this->redirect($referer, null, false, false);
            return;
            
        } else {
            $this->data['Login']['password'] = '';
            $this->publish('loginerror', true);
        }
    }
    

    /**
     * Log the user out (destroy their session and such).
     */
    function logout() {
        if (array_key_exists('to', $_GET)) {
            $_to = html_entity_decode($_GET['to']);
            if (preg_match("/(:\/\/|developers|editors|localizers|admin|users|\r|\n)/", $_to)) {
                $_to = '/';
            }
        }

        $_to = isset($_to) ? $_to : $this->referer('/', true);

        // remove our user from the session table
        if ($this->Session->valid())
            $this->Session->stop();

        $this->redirect($_to, null, false, false);
    }


    /**
     * Have the user edit their user details
     */
    function edit() {
        if (!$this->Session->check('User')) {
            $this->redirect('/users/login');
            return;
        }
        
        $sessionuser = $this->Session->read('User');
        $_current_user = $this->User->getUser($sessionuser['id']);

        $this->publish('user_id', $_current_user['User']['id']);
        
        $this->pageTitle = _('users_edit_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('cssAdd', array('forms', 'jquery-ui/flora/flora.tabs'));
        $this->publish('jsAdd', array('jquery-ui/ui.core.min', 'jquery-ui/ui.tabs.min'));

        $translations = $this->User->getAllTranslations($_current_user['User']['id']);
        $this->set('translations', $translations);

        if (empty($this->data)) {
            $this->publish('userAddons', $this->Addon->getAddonsByUser($_current_user['User']['id']));

            // jbalogh isn't going to let me live this one down, but let me explain! Cake doesn't respect us when we ask it not to cache queries
            // so when we turn off query caching in getUser() it still doesn't actually redo the query.  This means for that page load the image
            // isn't changed which means the picture_hash field isn't updated.  End result is the user updates their picture and on the first 
            // reload it isn't changed.  By appending this to the end of the image URL we get an uncached version iff they've updated it.
            $this->publish('_how_much_cake_sucks_on_a_scale_of_1_to_10', 11);

            // UNsubscribing from editor comments is always allowed even if
            // the user is no longer an editor
            $subscriptions = $this->Versioncomment->getSubscriptionsByUserDetailed($sessionuser['id']);
            $subscribed = array(); // checkbox data for html helper
            foreach ($subscriptions as $k => $val) {
                $subscriptions[$k]['Addon'] = $this->Addon->getAddon($val['Version']['addon_id']);
                $subscribed[$val['Versioncomment']['id']] = '1';
            }
            $this->publish('userVersionThreads', $subscriptions); // subscription details
            $this->data['Subscriptions'] = $subscribed;


            $this->data['User'] = $_current_user['User'];
            $this->data['User']['password'] = '';
            $this->render();
            return;
        }
        
        // build array for edited fields
        $changed = array();
        $changed['firstname'] = $this->data['User']['firstname'];
        $changed['lastname'] = $this->data['User']['lastname'];
        $changed['nickname'] = $this->data['User']['nickname'];
        $changed['emailhidden'] = $this->data['User']['emailhidden'];
        $changed['homepage'] = $this->data['User']['homepage'];
        $changed['location'] = $this->data['User']['location'];
        $changed['occupation'] = $this->data['User']['occupation'];
        $changed['display_collections'] = $this->data['User']['display_collections'];
        $changed['display_collections_fav'] = $this->data['User']['display_collections_fav'];

        // Picture fields.
        if (@$this->data['User']['removepicture'] == 1) {
            $changed['picture_data'] = null;
            $changed['picture_type'] = '';
        } else if ($this->data['User']['picture_data']['error'] != 4) {
            $fileinfo = $this->Developers->validatePicture($this->data['User']['picture_data']);
            if (is_array($fileinfo)) {
                $changed['picture_data'] = $fileinfo['picture_data'];
                $changed['picture_type'] = $fileinfo['picture_type'];
            } else {
                $this->User->invalidate('picture_data');
                $this->publish('picture_error', $fileinfo);//should use the Error component but the whole file needs to use it
            }
        } else {
            // default to the current data
            $changed['picture_data'] = $_current_user['User']['picture_data'];
            $changed['picture_type'] = $_current_user['User']['picture_type'];
        }

        if (!empty($this->data['User']['password']) &&
            !empty($this->data['User']['newpassword'])) {
            
            // trying to change the password
            if (!$this->User->checkPassword($_current_user['User'], $this->data['User']['password']))
                $this->User->invalidate('password');
            if ($this->data['User']['newpassword'] != $this->data['User']['confirmpw'])
                $this->User->invalidate('confirmpw');

            // store the new chosen pw to the "edited" array.
            // If we invalidated fields up here, it's not going to be
            // stored anyway.
            $changed['password'] = $this->User->createPassword($this->data['User']['newpassword']);
        }

        // nickname has to be unique
        if ($changed['nickname'] != '') {
            // filter nickname characters
            $this->data['User']['nickname'] = $this->_filterNick($this->data['User']['nickname']);
            
            $allnicks = $this->User->findAllByNickname($changed['nickname']);
            if (count($allnicks) > 1 ||
                (count($allnicks) == 1 && $allnicks[0]['User']['id'] != $_current_user['User']['id'])) {
                $this->User->invalidate('nickname');
            }
        }
        
        // email change?
        if (empty($this->data['User']['email'])) {
            $this->User->invalidate('email');
            $this->publish('error_email_empty', true);
            
        } elseif ($this->data['User']['email'] != $_current_user['User']['email']) {
            
            $newemail = $this->data['User']['email'];
            
            // check email format
            if (!preg_match(VALID_EMAIL, $newemail))
                $this->User->invalidate('email');
            
            // email has to be unique
            if ($this->User->findCount(array('User.email' => $newemail)) > 0) {
                $this->User->invalidate('email');
                $this->publish('error_email_notunique', true);
            }
        } else {
            $newemail = false;
        }
        
        // notifications
        $changed['notifycompat'] = array_key_exists('notifycompat', $this->data['User']) ? $this->data['User']['notifycompat'] : '';
        $changed['notifyevents'] = array_key_exists('notifyevents', $this->data['User']) ? $this->data['User']['notifyevents'] : '';
        
        // save it
        $this->User->id = $_current_user['User']['id'];
        $this->User->data['User'] = $changed;
        if (!$this->User->save()) {
            // wipe password fields before returning
            $this->data['User']['password'] = '';
            $this->data['User']['newpassword'] = '';
            $this->data['User']['confirmpw'] = '';
            // re-attach the email to the data so it can be displayed
            if (!empty($newemail))
                $this->data['User']['email'] = $newemail;
            else
                $this->data['User']['email'] = $_current_user['User']['email'];

            $this->publish('errorMessage', true);
            $this->publish('_how_much_cake_sucks_on_a_scale_of_1_to_10', 11);
            $this->render();
            return;
        }
        // Get the old user out of memcache so our form looks right
        if (QUERY_CACHE) $this->User->Cache->flushMarkedLists();
        // if we get here, the data was saved successfully
        
        // save author "about me"
        list($localizedFields, $unlocalizedFields) = $this->User->splitLocalizedFields($this->data['User']);
        $this->Amo->clean($localizedFields);
        $this->User->saveTranslations($_current_user['User']['id'], $this->params['form']['data']['User'], $localizedFields);

        // process versioncomment unsubscriptions
        // unsubscribing from editor comments is always allowed even if
        // the user is no longer an editor
        if (!empty($this->data['Subscriptions'])) {
            // get current subscriptions so we dont create any unecessary unsubscribe rows in the DB
            $subscriptions = $this->Versioncomment->getSubscriptionsByUser($sessionuser['id']);

            $thread_ids = array();
            foreach ($this->data['Subscriptions'] as $comment_id => $val) {
                if (in_array($comment_id, $subscriptions) && $val === '0') {
                    $thread_ids[] = $comment_id;
                }
            }
            $this->Versioncomment->unsubscribe($thread_ids, $sessionuser['id']);

            // requery remaining subscription details for display on success page
            $subscriptions = $this->Versioncomment->getSubscriptionsByUserDetailed($sessionuser['id']);
            $subscribed = array(); // checkbox data for html helper
            foreach ($subscriptions as $k => $val) {
                $subscriptions[$k]['Addon'] = $this->Addon->getAddon($val['Version']['addon_id']);
                $subscribed[$val['Versioncomment']['id']] = '1';
            }
            $this->publish('userVersionThreads', $subscriptions); // subscription details
            $this->data['Subscriptions'] = $subscribed;
        }

        // send out confirmation email if necessary
        if ($newemail !== false) {
            $this->set('newemail', $newemail);
            $this->set('userid', $_current_user['User']['id']);
            // generate email change code
            $changedata = array($_current_user['User']['email'], $newemail, time());
            $token = implode(',', $changedata);
            // hash with a secret to be able to check for validity later
            $secret = $this->_getSecret();
            $changecode = base64_encode($token) . md5($token.$secret);
            $this->set('changecode', $changecode);
            
            // send out the confirmation email
            $this->Email->template = 'email/emailchange';
            $this->Email->to = $newemail;
            $this->Email->subject = sprintf(___('user_emailchange_subject'), APP_PRETTYNAME);
            $result = $this->Email->send();
        }
        
        $newprofile = $this->User->getUser($_current_user['User']['id']);
        if (!empty($newprofile)) {
            $_new_session_user = $newprofile['User'];
            $_new_session_user['picture_data'] = $_new_session_user['picture_type'] = '';
            $this->Session->write('User', $_new_session_user);
            $this->publish('confirmation_message', _('user_profile_saved'));
        } else {
            // this should never happen, but anyway...
            $this->publish('confirmation_message', _('user_profile_edit_error'));
        }

        $this->data['User'] = $newprofile['User'];
        $this->data['User']['password'] = '';
        $this->publish('_how_much_cake_sucks_on_a_scale_of_1_to_10', rand(11,10000));
        $this->publish('confirmation_page', true);
        
        $this->render();
    }
    
    /**
     * Process an email change request
     * This URL sent to users by email to confirm their new address.
     * @param int id user id
     * @param string confirmcode Confirmation code (to be parsed and verified)
     */
    function emailchange($id = '') {
        $this->Amo->clean($id);
        if (!(int)$id || !$this->params['url']['code']) {
            $this->flash(sprintf(_('error_missing_argument'), 'user_id or code'), '/', 3);
            return;
        }
        $code = $this->params['url']['code'];
        
        // the arguments may not be empty and since an MD5 hash is 32 chars long
        // and a very short token takes 32 chars in base64, we need at least 64
        // characters, or anything bigger but mod 4 = 0 as base64 generates
        // multiples of four chars.
        if (strlen($code) < 64 || strlen($code)%4 != 0) {
            $this->publish('errormsg', _('error_user_badconfirmationcode'));
            $this->render();
            return;
        }
        
        // split code into token and hash
        $token = substr($code, 0, -32);
        $hash = substr($code, -32);
        
        // decode and hash-check the token
        $token = base64_decode($token);
        if (!$token || md5($token.$this->_getSecret()) != $hash) {
            $this->publish('errormsg', _('error_user_badconfirmationcode'));
            $this->render();
            return;
        }
        $changedata = explode(',', $token);
        if (!$changedata || count($changedata) != 3) {
            $this->publish('errormsg', _('error_user_badconfirmationcode'));
            $this->render();
            return;
        }
        $this->Amo->clean($changedata);
        $this->publish('newemail', $changedata[1]);
        
        // is the token expired (48 hours max)?
        if (time()-$changedata[2] > 48*60*60) {
            $this->publish('errormsg', ___('error_user_emailchange_expired'));
            $this->render();
            return;
        }
        
        $thisuser = $this->User->findById($id);
        if (empty($thisuser)) {
            $this->publish('errormsg', _('error_user_notfound'));
            $this->render();
            return;
        }
        
        // does old email still match?
        if ($thisuser['User']['email'] != $changedata[0]) {
            $this->publish('errormsg', _('error_user_badconfirmationcode'));
            $this->render();
            return;
        }
        
        // is new email address still unique?
        if ($this->User->findCount(array('User.email' => $changedata[1])) > 0) {
            $this->publish('errormsg', _('error_user_email_notunique'));
            $this->render();
            return;
        }
        
        // finally, change the mail address and return success message.
        if (!$this->User->save(array('User' => array(
            'id' => $id,
            'email' => $changedata[1]
            )))) {
            $this->publish('errormsg', _('error_user_badconfirmationcode'));
        }
        // fetch new user profile
        if ($this->Session->check('User')) {
            $currentprofile = $this->Session->read('User');
            if ($currentprofile['id'] == $id) {
                $newprofile = $this->User->find(array('User.id' => $id));
                if (!empty($newprofile)) {
                    $this->Session->write('User', $newprofile['User']);
                }
            }
        }
        $this->render();
    }

    /**
     * Show a user info page
     * @param int user id
     * @return void
     */
    function info($userid = null) {
        global $valid_status, $app_listedtypes;
        
        $this->Amo->clean($userid);
        
        if (!is_numeric($userid)) {
            $this->flash(sprintf(_('error_missing_argument'), 'user_id'), '/', 3);
            return;
        }
        $thisuser = $this->User->getUser($userid, array('addons', 'reviews'));
        if (empty($thisuser)) {
            $this->flash(_('error_user_notfound'), '/', 3);
            return;
        }

        // Remove the add-ons we don't want
        foreach ($thisuser['Addon'] as $key => $_addon) {
            // APP_FIREFOX supports all valid add-on types right now.  If that changes in the future, change this.
            if (   !in_array($_addon['Addon']['addontype_id'], $app_listedtypes[APP_FIREFOX])
                || !in_array($_addon['Addon']['status'], $valid_status)
                || ($_addon['Addon']['inactive'] != 0)) {
                    unset($thisuser['Addon'][$key]);
                }
        }
        
        foreach ($thisuser['Review'] as $key => &$_review) {
            // Remove the reviews we don't want
            if (!empty($_review['Review']['reply_to'])) {
                unset($thisuser['Review'][$key]);
                continue;
            }
            $_version = $this->Version->findById($_review['Review']['version_id'], 'Version.addon_id');
            if (!empty($_version)) {
                $_addon = $this->Addon->getAddon($_version['Version']['addon_id'], array('compatible_apps', 'latest_version'));
                $_review['Addon'] = $_addon;
            }
        }

        // get user's own and favorite collections, if they allowed that
        if ($thisuser['User']['display_collections']) {
            $coll_ids = $this->Collection->getCollectionsByUser($thisuser['User']['id']);
            $coll = $this->Collection->findAll(array('Collection.id'=>$coll_ids, 'listed'=>1),
                array('name', 'description', 'uuid', 'nickname', 'application_id'), 'Translation.name');
            $this->publish('coll', $coll);
        }
        if ($thisuser['User']['display_collections_fav']) {
            $coll_ids = $this->Collection->getSubscriptionsByUser($thisuser['User']['id']);
            $coll_fav = $this->Collection->findAll(array('Collection.id'=>$coll_ids, 'listed'=>1),
                array('name', 'description', 'uuid', 'nickname', 'application_id'), 'Translation.name');
            $this->publish('coll_fav', $coll_fav);
        }

        if (!empty($thisuser['User']['nickname']))
            $name = $thisuser['User']['nickname'];
        else
            $name = $thisuser['User']['firstname'].' '.$thisuser['User']['lastname'];
        $_title = sprintf(_('users_info_pagetitle'), $name);
        $this->pageTitle = $_title.' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('breadcrumbs', array($_title => "/user/{$userid}"));
        $this->publish('subpagetitle', $_title);
        $this->publish('profile', $thisuser);
        $this->render();
    }
    
    /**
     * (User-facing:) Delete a user account
     * Anonymizes a user account (does not delete the row off the database)
     */
    function delete() {
        $this->Amo->checkLoggedIn();
        
        $this->pageTitle = ___('users_delete_pagetitle', 'Delete User Account').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
        $this->publish('cssAdd', array('forms'));
        
        $deluser = $this->Session->read('User');
        $this->publish('useremail', $deluser['email']);
        $this->set('userid', $deluser['id']);
        
        if (!empty($this->data['User'])) {
            // deletion requested
            
            // checkbox checked?
            if (empty($this->data['User']['reallydelete'])) {
                $this->set('deleteerror', 'checkbox');
                return;
            }
            
            // password entered correctly?
            if (!$this->User->checkPassword($deluser, $this->data['User']['password'])) {
                $this->set('deleteerror', 'password');
                $this->data['User']['password'] = ''; // do not post back password
                return;
            }
            
            // disallow users that have add-ons to delete their accounts
            if ($this->User->getAddonCount($deluser['id']) > 0) {
                $this->set('deleteerror', 'addons');
                return;
            }
            
            // delete user (includes anonymizing reviews)
            if (!$this->User->anonymize($deluser['id'])) {
                // failure
                $this->set('deleteerror', 'unknown');
                return;
            } else {
                // success: wipe session, display success message
                $this->Session->stop();
                $this->set('success', true);
                return;
            }
        }
    }
    
    /**
     * Associate AMO account with an LDAP group
     * @param string $group The group name
     */
    function associateGroup($group) {
        // Make sure logged into AMO
        $this->Amo->checkLoggedIn("/users/associateGroup/{$group}");
        
        // Make sure entered LDAP info
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="Mozilla Corporation - LDAP Login"');
            header('HTTP/1.0 401 Unauthorized');
            $this->flash('You must authenticate to view this page.', '/', 3);
            return;
        }
        
        // Connect to LDAP
        if (!$this->Ldap->connect($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            $this->flash('Could not authenticate', '/', 3);
            return;
        }
        
        // Check if in group
        if (!$this->Ldap->hasAccount($_SERVER['PHP_AUTH_USER'], $group)) {
            $this->flash('Not a member of requested group', '/', 3);
            return;
        }
        
        $groups = array(
            '4247a32ebaed692a8d72cea6382f8c5b' => 38
        );
        
        $group_id = $groups[md5($group)];
        $user = $this->Session->read('User');
        
        if (!empty($group_id)) {
            // Make sure not already in group
            if (!empty($user['Group'])) {
                foreach ($user['Group'] as $user_group) {
                    if ($user_group['id'] == $group_id) {
                        $this->flash('You are already a member of this group!', '/', 3);
                        return;
                    }
                }
            }
            
            $this->User->execute("INSERT INTO groups_users (group_id, user_id) VALUES('{$group_id}', '{$user['id']}')");
            
            // Log user action
            $this->Eventlog->log($this, 'user', 'group_associated', null, $group_id);
            
            $this->flash("You have been successfully added to the {$group} group. You must log out and back in for this to take effect.", '/users/logout', 5);
        }
        
        return;
    }

    /**
     *
     */
    function picture($id) {
        if (!is_numeric($id)) {
            $this->flash(sprintf(_('error_missing_argument'), 'user_id'), '/', 3);
            return;
        }
        $user = $this->User->getUser($id);
        if (empty($user)) {
            $this->flash(_('error_user_notfound'), '/', 3);
            return;
        }

        if (!empty($user['User']['picture_data'])) {
            $this->Image->renderImage($user['User']['picture_data'], $user['User']['picture_type']);
        } else {
            $this->redirect(IMAGES_URL.'anon_user.png', 302, false, false);
        }
    }
    
    /**
     * return the secret to generate and validate email changes.
     * If necessary, generate it once.
     */
    function _getSecret() {
        $secret = $this->Config->getValue('emailchange_secret');
        if (empty($secret)) {
            // generate a secret for the first time
            $len = mt_rand(10, 255);
            $secret = '';
            while (strlen($secret) < $len)
                $secret .= md5(mt_rand());
            $secret = substr($secret, 0, $len);
            
            // store it in the DB for the future
            $this->Config->save(array('Config' => array(
                'key' => 'emailchange_secret',
                'value' => $secret)));
        }
        return $secret;
    }
    
    /**
     * Remove forbidden characters from nicknames
     */
    function _filterNick($nickname) {
        $nickname = str_replace(html_entity_decode('&#8203;', ENT_NOQUOTES, 'UTF-8'), '', $nickname); // zero-break space: bug 412694
        return trim($nickname);
    }

}

?>
