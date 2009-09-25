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
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Wil Clouser <wclouser@mozilla.com> (Original Author)
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

/**
 * AMO Auth plug-in for phpBB3; based on db
 *
 * @package login
 */

/**
* @ignore
*/
if (!defined('IN_PHPBB')) {
	exit;
}

/**
* Init function.  Just tries to connect to the db with supplied credentials.
*/
function init_amo() {
    global $user;

    $amodb = _auth_amo_connect_database();

    if ($amodb->connect_error) {
        return $user->lang['ERR_CONNECTING_SERVER'].' '.$amodb->connect_error;

    }
    $amodb->close();
}

/**
* Login function
*/
function login_amo(&$username, &$password) {

    // apparently phpbb doesn't believe in include_once
    if (!function_exists('user_add')) {
        global $phpbb_root_path, $phpEx;
        include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
    }

    // This is fallback because I locked myself out of the database a lot when writing this.  In theory we can whack this, but if the AMO db dies or
    // something like that, we will be locked out of the forum system completely.  Seems unlikely, but if it happens it would probably be nice
    // to have this.
    if ($username == 'admin') {
        include_once 'auth_db.php';
        return login_db($username, $password);
    }

    global $db, $user;

    $anonymous_user = array('user_id' => ANONYMOUS);
    $amouser = array();

	// do not allow empty password
	if (!$password) {
		return array(
			'status'	=> LOGIN_ERROR_PASSWORD,
			'error_msg'	=> 'NO_PASSWORD_SUPPLIED',
            'user_row'	=> $anonymous_user
		);
	}

	if (!$username) {
		return array(
			'status'	=> LOGIN_ERROR_USERNAME,
			'error_msg'	=> 'LOGIN_ERROR_USERNAME',
            'user_row'	=> $anonymous_user
		);
	}

    $amodb = _auth_amo_connect_database();

    if (is_string($amodb)) {
		return array(
			'status'     => LOGIN_ERROR_EXTERNAL_AUTH,
			'error_msg'  => 'GENERAL_ERROR'.' '.$amodb,
            'user_row'	 => $anonymous_user
		);
    }


    $_sql = 'SELECT id, email, password, nickname, confirmationcode FROM users WHERE nickname=?';

    if ($_stmt = $amodb->prepare($_sql)) {
        $_stmt->bind_param('s', $username);
        $_stmt->execute();
        $_stmt->bind_result($amouser['id'], $amouser['email'], $amouser['password'], $amouser['nickname'], $amouser['confirmationcode']);
        $_stmt->fetch();
        $_stmt->close();
    }
    $amodb->close();

    if ($amouser['id'] == 0) {
        return array(
            'status'	=> LOGIN_ERROR_USERNAME,
            'error_msg'	=> 'LOGIN_ERROR_USERNAME',
            'user_row'	=> $anonymous_user
        );
    }

    if (empty($amouser['nickname'])) {
        return array(
            'status'		=> LOGIN_ERROR_USERNAME,
            'error_msg'		=> 'AMO_NO_NICKNAME',
            'user_row'		=> $anonymous_user
        );
    }

    if (!empty($amouser['confirmationcode'])) {
        return array(
            'status'		=> LOGIN_ERROR_ACTIVE,
            'error_msg'		=> 'ACTIVE_ERROR',
            'user_row'		=> $anonymous_user
        );
    }

    if (!_auth_amo_check_password($password, $amouser['password'])) {
        return array(
            'status'		=> LOGIN_ERROR_PASSWORD,
            'error_msg'		=> 'LOGIN_ERROR_PASSWORD',
            'user_row'		=> $anonymous_user
        );
    } else {
        // Everything is good on the AMO side.  Let's make sure it's all good on the PHPBB side.
        
        $sql ='SELECT user_id, username, user_password, user_passchg, user_email, user_type FROM '.USERS_TABLE." WHERE user_id = '".$db->sql_escape(utf8_clean_string($amouser['id']))."'";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        // The user already exists in the phpbb database.  Make sure they're valid, update anything needed, and log them in
        if ($row) {
            // User inactive...
            if ($row['user_type'] == USER_INACTIVE || $row['user_type'] == USER_IGNORE) {
                return array(
                    'status'		=> LOGIN_ERROR_ACTIVE,
                    'error_msg'		=> 'ACTIVE_ERROR',
                    'user_row'		=> $row,
                );
            }

            // Check if they've changed their name or email on the AMO side.  If they have, update them in phpbb.
            if (($row['username'] != $amouser['nickname']) || ($row['user_email'] != $amouser['email'])) {
                $sql =' UPDATE '.USERS_TABLE.'
                        SET username="'.$db->sql_escape(utf8_clean_string($amouser['nickname'])).'", user_email="'.$db->sql_escape(utf8_clean_string($amouser['email'])).'"
                        WHERE user_id = "'.$db->sql_escape(utf8_clean_string($amouser['id'])).'"';
                $db->sql_query($sql);
            }

            // Sync groups from AMO to phpbb
            _auth_amo_sync_groups($amouser['id']);

            // Successful login... set user_login_attempts to zero...
            return array(
                'status'		=> LOGIN_SUCCESS,
                'error_msg'		=> false,
                'user_row'		=> $row,
            );
        } else {
            // Everyone is happy, but the user doesn't exist in phpbb yet.  That means we'll need to create the row.  Normally phpbb
            // can do this automatically if you return LOGIN_SUCCESS_CREATE_PROFILE here, however, I want to do some special group stuff
            // so we get to do it ourselves
        
            // Check if it's a valid username as far as phpbb is concerned.  This is pretty lenient with USERNAME_CHARS_ANY but it will prevent stuff like single quotes
            if (($ret = validate_username($amouser['nickname'])) !== false) {
                return array(
                    'status'	=> LOGIN_ERROR_USERNAME,
                    'error_msg'	=> $ret,
                    'user_row'	=> $anonymous_user
                );
            }
            
            // retrieve default group id
            $sql = 'SELECT group_id FROM '.GROUPS_TABLE." WHERE group_name = '".$db->sql_escape('REGISTERED')."' AND group_type = ".GROUP_SPECIAL;
            $result = $db->sql_query($sql);
            $group = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if (!$group) {
                trigger_error('NO_GROUP');
            }

            // generate user account data
            $new_user_row = array(
                'user_id'       => $amouser['id'],
                'user_type'		=> USER_NORMAL,
                'group_id'		=> (int) $group['group_id'],
                'user_ip'		=> $user->ip,
                'username'		=> $amouser['nickname'],
                'user_password'	=> phpbb_hash(mt_rand(1000,100000)),// Why does phpbb want to cache the password locally?
                'user_email'	=> $amouser['email'],
            );

            if ($id = user_add($new_user_row)) {
                // We've got a user id.  phpbb doesn't have a way to add more than 1 group when creating a user so we have to do that afterwards
                _auth_amo_sync_groups($amouser['id']);

                return array(
                    'status'		=> LOGIN_SUCCESS,
                    'error_msg'		=> false,
                    'user_row'		=> $new_user_row,
                );
            }

            // Something went wrong.  Return general error and anonymous user.
            return array(
                'status'		=> LOGIN_ERROR_EXTERNAL_AUTH,
                'error_msg'		=> 'GENERAL_ERROR'.' Failed to create new user',
                'user_row'		=> array('user_id' => ANONYMOUS),
            );
        }
    }
}

function acp_amo(&$new) {
	$tpl = '
	<div style="background-color:#fed;">
        <h4>AMO Configuration</h4>
        <div style="padding: 0px 0px 15px 15px;">
        <dl>
            <dt><label for="amo_hostname">AMO Hostname:</label><br /><span>AMO Database (can be a slave, only needs read-only)</span></dt>
            <dd><input type="text" id="amo_hostname" size="40" name="config[amo_hostname]" value="' . $new['amo_hostname'] . '" /></dd>
        </dl>
        <dl>
            <dt><label for="amo_username">AMO Username:</label><br /><span>Username</span></dt>
            <dd><input type="text" id="amo_username" size="40" name="config[amo_username]" value="' . $new['amo_username'] . '" /></dd>
        </dl>
        <dl>
            <dt><label for="amo_password">AMO Password:</label><br /><span>Password</span></dt>
            <dd><input type="password" id="amo_password" size="40" name="config[amo_password]" value="' . $new['amo_password'] . '" /></dd>
        </dl>
        <dl>
            <dt><label for="amo_database">AMO Database:</label><br /><span>Name of the database</span></dt>
            <dd><input type="text" id="amo_database" size="40" name="config[amo_database]" value="' . $new['amo_database'] . '" /></dd>
        </dl>
        </div>
    </div>
    ';
	// These are fields required in the config table
	return array(
		'tpl'		=> $tpl,
		'config'	=> array('amo_hostname', 'amo_username', 'amo_password', 'amo_database')
	);
}

/**
 * Private AMO functions
 */
function _auth_amo_connect_database() {
    global $config;

    return @new mysqli($config['amo_hostname'], $config['amo_username'], $config['amo_password'], $config['amo_database']);
}

function _auth_amo_check_password($rawPassword, $encPassword) {
    if (empty($encPassword)) {
        return false;
    }
    list($algo, $salt, $storedPassword) = split('\$', $encPassword);
    if (!in_array($algo, hash_algos())) {
        return false;
    }
    $hashedPassword = hash($algo, $salt.$rawPassword);
    // Check isset to make sure the split worked.
    return isset($storedPassword) && $storedPassword == $hashedPassword;
}

function _auth_amo_sync_groups($user_id) {
    global $db;

    if ($result = $db->sql_query('SELECT group_id, LOWER(group_name) as group_name FROM '.GROUPS_TABLE)) {
        $phpbb_groups = array();
        while ($row = $db->sql_fetchrow($result)) {
            $phpbb_groups[$row['group_id']] = $row['group_name'];
        }

        $user_groups = group_memberships(false, $user_id);

        $amodb = _auth_amo_connect_database();

        $_sql = 'SELECT group_id, name, rules FROM groups_users JOIN groups ON groups.id = groups_users.group_id WHERE user_id=?';

        if ($_stmt = $amodb->prepare($_sql)) {
            $_stmt->bind_param('i', $user_id);
            $_stmt->execute();
            $_stmt->bind_result($amousergroups['id'], $amousergroups['name'], $amousergroups['rules']);
            while ($_stmt->fetch()) {
                // phpbb uses the full word
                if ($amousergroups['name'] == 'Admins')
                    $amousergroups['name'] = 'Administrators';

                $_group_id = false;

                // Does the group exist in phpbb?
                if (group_validate_groupname(false,$amousergroups['name']) !== false) {

                    // phpbb has no built in function to get a group id from group name. fail.
                    $_group_id = array_search(utf8_strtolower($amousergroups['name']), $phpbb_groups);
                } else {
                    group_create($_group_id,GROUP_CLOSED,$amousergroups['name'], '', array());
                }

                if ($_group_id) {
                    $adduser = true;
                    foreach ($user_groups as $id => $group) {
                        // Does the user already exist in the group?
                        if ($group['group_id'] == $_group_id) {
                            // Remove the group from our list, it's no longer of interest.
                            unset($user_groups[$id]);
                            $adduser = false;
                            break;
                        }
                    }
                    if ($adduser)
                        group_user_add($_group_id, array($user_id));
                }
            }
            $_stmt->close();

            // If there is anything left in this array the user was removed from groups on AMO and should be 
            // removed from the groups here as well
            if (is_array($user_groups) && count($user_groups)) {
                foreach ($user_groups as $group) {
                    if ($group['group_name'] == 'Registered users') // Ignore phpbb built-in
                        continue;
                    group_user_del($group['group_id'], array($user_id));
                }
            }

        }
    }
}


?>
