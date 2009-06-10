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
 *   l.m.orchard <lorchard@mozilla.com>
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

class ApiAuthToken extends AppModel
{
    var $name = "ApiAuthToken";

    var $belongsTo = array(
        'User' => array(
            'className'  => 'User',
            'conditions' => '',
            'order'      => '',
            'foreignKey' => 'user_id'
        ),
    );

    var $belongsTo_full = array(
        'User' => array(
            'className'  => 'User',
            'conditions' => '',
            'order'      => '',
            'foreignKey' => 'user_id'
        ),
    );

    var $validate = array(
        'user_id' => VALID_NOT_EMPTY
    );
    
    /**
     * Generate and return a new random token value.
     *
     * @return string
     */
    function generateTokenValue()
    {
        mt_srand((double)microtime()*10000);
        return md5(uniqid(rand(), true));
    }

    /**
     * Generate a hash of siginificant user profile data.  Currently, this 
     * includes email and password - changes in either causes a change in 
     * hash.
     *
     * @param string  User ID
     * @return string User profile hash
     */
    function buildUserProfileHash($user)
    {
        return md5(join(' ', array(
            $user['User']['email'],
            $user['User']['password']
        )));
    }

    /**
     * Before saving, set a token, UA hash, and profile hash if not all 
     * available.
     */
    function beforeSave() {
        if (empty($this->data['ApiAuthToken']['token'])) {
            $this->data['ApiAuthToken']['token'] = 
                $this->generateTokenValue();
        }
        if (empty($this->data['ApiAuthToken']['user_agent_hash'])) {
            $this->data['ApiAuthToken']['user_agent_hash'] = 
                md5(env('HTTP_USER_AGENT'));
        }
        if (empty($this->data['ApiAuthToken']['user_profile_hash'])) {
            $user = $this->User->findById(
                $this->data['ApiAuthToken']['user_id']
            );
            $this->data['ApiAuthToken']['user_profile_hash'] = 
                $this->buildUserProfileHash($user);
        }
        return parent::beforeSave();
    }

    /**
     * Attempt to look up a user for a given auth token.
     * The user agent hash and user profile hash are verified against any 
     * record found by token string before returning a user record.
     *
     * @param  string Auth token string
     * @param  string Optional user agent string, pulled from environment if null
     * @return array  Authenticated user details
     */
    function getUserForAuthToken($auth_token, $user_agent=null)
    {
        if (strpos($auth_token, 'http') === 0) {
            $auth_token = substr($auth_token, strrpos($auth_token, '/')+1);
        }
        if (empty($user_agent)) {
            $user_agent = env('HTTP_USER_AGENT');
        }
        $conditions = array(
            'ApiAuthToken.token' => $auth_token,
            'ApiAuthToken.user_agent_hash' => md5($user_agent)
        );
        $row = $this->find($conditions);

        if (empty($row)) {
            // No token found for the token string and user agent hash, 
            // so return empty handed.
            return null;
        }

        $expected_hash = 
            $this->buildUserProfileHash($row);
        $token_hash =
            $row['ApiAuthToken']['user_profile_hash'];

        if ($token_hash != $expected_hash) {
            // The profile hash didn't match, so delete the token
            // and return null.  Assumes user changed profile details, 
            // so this token should be destroyed since it will never
            // be valid again.
            $this->delete($row['ApiAuthToken']['id']);
            return null;
        }

        return $row['User'];
    }

    /**
     * Delete a token for the given user ID, also performing a user agent check 
     * on top of the auth user check.
     *
     * @param  string  User ID
     * @param  string  Token string value
     * @param  string  optional user agent string
     * @return boolean Whether or not the deletion was successful.
     */
    function deleteByUserIdAndToken($user_id, $auth_token, $user_agent=null)
    {
        if (empty($user_agent)) {
            $user_agent = env('HTTP_USER_AGENT');
        }
        $conditions = array(
            'ApiAuthToken.user_id' => $user_id,
            'ApiAuthToken.token'=> $auth_token,
            'ApiAuthToken.user_agent_hash' => md5($user_agent)
        );
        $row = $this->find($conditions);

        if (empty($row)) {
            return null;
        }

        $this->delete($row['ApiAuthToken']['id']);
        return true;
    }

}
