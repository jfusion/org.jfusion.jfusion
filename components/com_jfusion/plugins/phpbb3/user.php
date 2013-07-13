<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion User Class for phpBB3
 * For detailed descriptions on these functions please check the model.abstractuser.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_phpbb3 extends JFusionUser
{
    /**
     * @param object $userinfo
     * @return null|object
     */
    function getUser($userinfo) {
        //get the identifier
        list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.username_clean', 'a.user_email');
        // Get a database object
        $db = JFusionFactory::getDatabase($this->getJname());
        //make the username case insensitive
        if ($identifier_type == 'a.username_clean') {
            $identifier = $this->filterUsername($identifier);
        }
        $query = 'SELECT a.user_id as userid, a.username as name, a.username_clean as username, a.user_email as email, a.user_password as password, null as password_salt, a.user_actkey as activation, a.user_inactive_reason as reason, a.user_lastvisit as lastvisit, a.group_id, b.group_name, a.user_type, a.user_avatar, a.user_avatar_type ' . 'FROM #__users as a LEFT OUTER JOIN #__groups as b ON a.group_id = b.group_id ' . 'WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);
        $db->setQuery($query);
        $result = $db->loadObject();
        if ($result) {
            //prevent anonymous user accessed
            if ($result->username == 'anonymous') {
                $result = null;
            } else {
                $result->groups = array($result->group_id);
                $result->groupnames = array($result->group_name);

                //Check to see if they are banned
                $query = 'SELECT ban_userid FROM #__banlist WHERE ban_userid =' . (int)$result->userid;
                $db->setQuery($query);
                if ($db->loadObject()) {
                    $result->block = 1;
                } else {
                    $result->block = 0;
                }
                //if no inactive reason is set clear the activation code
                if ($result->user_type == 1) {
                    //user is inactive
                    if (empty($result->activation)) {
                        //user not active generate a random code
                        jimport('joomla.user.helper');
                        $result->activation = JUserHelper::genRandomPassword(13);
                    }
                } else {
                    //active user, make sure no activation code is set
                    $result->activation = '';
                }
            }
        }
        return $result;
    }
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'phpbb3';
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());
        $db = JFusionFactory::getDatabase($this->getJname());
        //get the cookie parameters
        $params = JFusionFactory::getParams($this->getJname());
        $phpbb_cookie_name = $params->get('cookie_prefix');
        $phpbb_cookie_path = $params->get('cookie_path');
        $secure = $params->get('secure',false);
        $httponly = $params->get('httponly',true);
        //baltie cookie domain fix
        $phpbb_cookie_domain = $params->get('cookie_domain');
        if ($phpbb_cookie_domain == 'localhost' || $phpbb_cookie_domain == '127.0.0.1') {
            $phpbb_cookie_domain = '';
        }
        //update session time for the user into user table
        $query = 'UPDATE #__users SET user_lastvisit =' . time() . ' WHERE user_id =' . (int)$userinfo->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['debug'][] = 'Error could not update the last visit field ' . $db->stderr();
        }
        //delete the cookies
        $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_u', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly);
        $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_sid', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly);
        $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_k', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly);

        $_COOKIE[$phpbb_cookie_name . '_u'] = '';
        $_COOKIE[$phpbb_cookie_name . '_sid'] = '';
        $_COOKIE[$phpbb_cookie_name . '_k'] = '';
        //delete the database sessions
        $query = 'DELETE FROM #__sessions WHERE session_user_id =' . (int)$userinfo->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error: Could not delete session in database ' . $db->stderr();
            return $status;
        }
        $query = 'DELETE FROM #__sessions_keys WHERE user_id =' . (int)$userinfo->userid;
        $db->setQuery($query);
        if ($db->query()) {
            $status['debug'][] = 'Deleted the session key';
        } else {
            $status['debug'][] = 'Error could not delete the session key:' . $db->stderr();
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array
     */
    function createSession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());
        //do not create sessions for blocked users
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
            $db = JFusionFactory::getDatabase($this->getJname());
            $userid = $userinfo->userid;
            if ($userid && !empty($userid) && ($userid > 0)) {
                $params = JFusionFactory::getParams($this->getJname());
                //check if we need to let phpbb3 handle the login
                $login_type = $params->get('login_type');
                if ($login_type != 1 && !function_exists('deregister_globals')) {
                    //let phpbb3 handle login
                    $source_path = $params->get('source_path');
                    //combine the path and filename
                    if (substr($source_path, -1) != DS) {
                        $source_path .= DS;
                    }

                    //set the current directory to phpBB3
                    chdir($source_path);
                    /* set scope for variables required later */
                    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template, $phpbb_hook, $module, $mode;
                    if (!defined('UTF8_STRLEN')) {
                        define('UTF8_STRLEN', true);
                    }
                    if (!defined('UTF8_CORE')) {
                        define('UTF8_CORE', true);
                    }
                    if (!defined('UTF8_CASE')) {
                        define('UTF8_CASE', true);
                    }
                    if (!defined('IN_PHPBB')) {
                        define('IN_PHPBB', true);
                    }

                    $phpbb_root_path = $source_path;
                    $phpEx = 'php';

                    include_once $source_path . 'common.php';

                    //get phpbb3 session object
                    $user->session_begin();
                    $auth->acl($user->data);

                    //perform the login
                    if ($options['remember']) {
                        $remember = true;
                    } else {
                        $remember = false;
                    }
                    $result = $auth->login($userinfo->username, $userinfo->password_clear, $remember, 1, 0);
                    if ($result['status'] == LOGIN_SUCCESS) {
                        $status['debug'][] = JText::_('CREATED') . ' ' . JText::_('PHPBB') . ' ' . JText::_('SESSION');
                    } else {
                        $status['debug'][] = JText::_('ERROR') . ' ' . JText::_('PHPBB') . ' ' . JText::_('SESSION');
                    }
                    //change the current directory back to Joomla.
                    chdir(JPATH_SITE);
                } else {
                    jimport('joomla.user.helper');
                    $session_key = JUtility::getHash(JUserHelper::genRandomPassword(32));
                    //Check for admin access
                    $query = 'SELECT b.group_name FROM #__user_group as a INNER JOIN #__groups as b ON a.group_id = b.group_id WHERE b.group_name = \'ADMINISTRATORS\' and a.user_id = ' . (int)$userinfo->userid;
                    $db->setQuery($query);
                    $usergroup = $db->loadResult();
                    if ($usergroup == 'ADMINISTRATORS') {
                        $admin_access = 1;
                    } else {
                        $admin_access = 0;
                    }
                    $phpbb_cookie_name = $params->get('cookie_prefix');
                    if ($phpbb_cookie_name) {
                        //get cookie domain from config table
                        $phpbb_cookie_domain = $params->get('cookie_domain');
                        if ($phpbb_cookie_domain == 'localhost' || $phpbb_cookie_domain == '127.0.0.1') {
                            $phpbb_cookie_domain = '';
                        }
                        //get cookie path from config table
                        $phpbb_cookie_path = $params->get('cookie_path');
                        //get autologin perm
                        $phpbb_allow_autologin = $params->get('allow_autologin');
                        $jautologin = 0;
                        //set the remember me option if set in Joomla and is allowed per config
                        if (isset($options['remember']) && !empty($phpbb_allow_autologin)) {
                            $jautologin = $options['remember'] ? 1 : 0;
                        }

                        $create_persistant_cookie = false;
                        if (!empty($phpbb_allow_autologin)) {
                            //check for a valid persistent cookie
                            $persistant_cookie = ($phpbb_allow_autologin) ? JRequest::getVar($phpbb_cookie_name . '_k', '', 'cookie') : '';
                            if (!empty($persistant_cookie)) {
                                $query = 'SELECT user_id FROM #__sessions_keys WHERE key_id = ' . $db->Quote(md5($persistant_cookie));
                                $db->setQuery($query);
                                $persistant_cookie_userid = $db->loadResult();
                                if ($persistant_cookie_userid == $userinfo->userid) {
                                    $status['debug'][] = JText::_('SKIPPED_CREATING_PERSISTANT_COOKIE');
                                    $create_persistant_cookie = false;
                                    //going to assume that since a persistent cookie exists, $options['remember'] was originally set
                                    //$options['remember'] does not get set if Joomla remember me plugin reinitiated the login
                                    $jautologin = 1;
                                }
                            } else {
                                $create_persistant_cookie = true;
                            }
                        }

                        if ($jautologin) {
                            $query = 'SELECT config_value FROM #__config WHERE config_name = \'max_autologin_time\'';
                            $db->setQuery($query);
                            $max_autologin_time = $db->loadResult();
                            $expires = ($max_autologin_time) ? 86400 * (int) $max_autologin_time : 31536000;
                        } else {
                            $expires = 31536000;
                        }
                        $secure = $params->get('secure',false);
                        $httponly = $params->get('httponly',true);
                        $session_start = time();
                        //Insert the session into sessions table
                        $session_obj = new stdClass;
                        $session_obj->session_id = substr($session_key, 0, 32);
                        $session_obj->session_user_id = $userid;
                        $session_obj->session_last_visit = $userinfo->lastvisit;
                        $session_obj->session_start = $session_start;
                        $session_obj->session_time = $session_start;
                        $session_obj->session_ip = $_SERVER['REMOTE_ADDR'];
                        $session_obj->session_browser = $_SERVER['HTTP_USER_AGENT'];
                        $session_obj->session_page = 0;
                        $session_obj->session_autologin = $jautologin;
                        $session_obj->session_admin = $admin_access;
                        if (!$db->insertObject('#__sessions', $session_obj)) {
                            //could not save the user
                            $status['error'][] = JText::_('ERROR_CREATE_SESSION') . $db->stderr();
                        } else {
                            //Set cookies
                            $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_u', $userid, $expires, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly);
                            $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_sid', $session_key, $expires, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly, true);

                            //Force the values into the $_COOKIE variable just in case Joomla remember me plugin fired this in which the cookie will not be available until after the browser refreshes.  This will hopefully trick phpBB into thinking the cookie is present now and thus handle sessions correctly when in frameless mode
                            $_COOKIE[$phpbb_cookie_name . '_u'] = $userid;
                            $_COOKIE[$phpbb_cookie_name . '_sid'] = $session_key;

                            // Remember me option?
                            if ($jautologin > 0 && $create_persistant_cookie) {
                                $key_id = substr(md5($session_key . microtime()),4,16);
                                //Insert the session key into sessions_key table
                                $session_key_ins = new stdClass;
                                $session_key_ins->key_id = md5($key_id);
                                $session_key_ins->user_id = $userid;
                                $session_key_ins->last_ip = $_SERVER['REMOTE_ADDR'];
                                $session_key_ins->last_login = $session_start;
                                if (!$db->insertObject('#__sessions_keys', $session_key_ins)) {
                                    //could not save the session_key
                                    $status['error'][] = JText::_('ERROR_CREATE_USER') . $db->stderr();
                                } else {
                                    $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_k', $key_id, $expires, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly, true);
                                    $_COOKIE[$phpbb_cookie_name . '_k'] = $key_id;
                                }
                            }
                        }
                    } else {
                        //could not find a valid userid
                        $status['error'][] = JText::_('INVALID_COOKIENAME');
                    }
                }
            } else {
                //could not find a valid userid
                $status['error'][] = JText::_('INVALID_USERID');
            }
        }
        return $status;
    }

    /**
     * @param string $username
     * @return string
     */
    function filterUsername($username) {
        /**
         * @ignore
         * @var $helper JFusionHelper_phpbb3
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $username_clean = $helper->utf8_clean_string($username);
        //die($username . ':' . $username_clean);
        return $username_clean;
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status) {
        /**
         * @ignore
         * @var $auth JFusionAuth_phpbb3
         */
        $auth = JFusionFactory::getAuth($this->getJname());
        $existinguser->password = $auth->HashPassword($userinfo->password_clear);

        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET user_password =' . $db->Quote($existinguser->password) . ', user_pass_convert = 0 WHERE user_id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateUsername($userinfo, &$existinguser, &$status) {
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status) {
        //we need to update the email
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET user_email =' . $db->Quote($userinfo->email) . ' WHERE user_id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status) {
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST');
        } else {
            $usergroup = $usergroups[0];
            $db = JFusionFactory::getDatabase($this->getJname());
            $user = new stdClass;
            $user->user_id = $existinguser->userid;
            $user->group_id = $usergroup;
            $user->user_colour = '';
            //clear out cached permissions so that those of the new group are generated
            $user->user_permissions = '';
            //update the user colour, avatar, etc to the groups if applicable
            $query = 'SELECT group_colour, group_rank, group_avatar, group_avatar_type, group_avatar_width, group_avatar_height FROM #__groups WHERE group_id = '.$user->group_id;
            $db->setQuery($query);
            $group_attribs = $db->loadAssoc();
            if (!empty($group_attribs)) {
                foreach($group_attribs AS $k => $v) {
                    // If we are about to set an avatar or rank, we will not overwrite with empty, unless we are not actually changing the default group
                    if ((strpos($k, 'group_avatar') === 0 || strpos($k, 'group_rank') === 0) && !$group_attribs[$k])
                    {
                        continue;
                    }
                    $user->{str_replace('group_', 'user_', $k)} = $v;
                }
            }

            //set the usergroup in the user table
            if (!$db->updateObject('#__users', $user, 'user_id')) {
                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
            } else {
                //remove the old usergroup for the user in the groups table
                $query = 'DELETE FROM #__user_group WHERE group_id = ' . (int)$existinguser->group_id . ' AND user_id = ' . (int)$existinguser->userid;
                $db->setQuery($query);
                if (!$db->query()) {
                    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                }

                //if the user was in the newly registered group, remove the registered group as well
                $query = 'SELECT group_id, group_name FROM #__groups WHERE group_name IN (\'NEWLY_REGISTERED\',\'REGISTERED\') AND group_type = 3';
                $db->setQuery($query);
                $groups = $db->loadObjectList('group_name');
                if ($existinguser->group_id == $groups['NEWLY_REGISTERED']->group_id) {
                    $query = 'DELETE FROM #__user_group WHERE group_id = ' . (int)$groups['REGISTERED']->group_id . ' AND user_id = ' . (int)$existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        //return the error
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                        return;
                    }
                }

                //add the user in the groups table
                $query = 'INSERT INTO #__user_group (group_id, user_id ,group_leader, user_pending) VALUES (' . (int)$usergroup . ', ' . (int)$existinguser->userid . ',0,0)';
                $db->setQuery($query);
                if (!$db->query()) {
                    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                } else {
                    if ($usergroup == $groups['NEWLY_REGISTERED']->group_id) {
                        //we need to also add the user to the regular registered group or they may find themselves groupless
                        $query = 'INSERT INTO #__user_group (group_id, user_id, group_leader, user_pending) VALUES (' . $groups['REGISTERED']->group_id . ',' . (int)$existinguser->userid . ', 0,0 )';
                        $db->setQuery($query);
                        if (!$db->query()) {
                            //return the error
                            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                            return;
                        }
                    }

                    //update correct group colors where applicable
                    $query = 'UPDATE #__forums SET forum_last_poster_colour = ' . $db->Quote($user->user_colour) . ' WHERE forum_last_poster_id = ' . (int)$existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        //return the error
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                    }

                    $query = 'UPDATE #__topics SET topic_first_poster_colour = ' . $db->Quote($user->user_colour) . ' WHERE topic_poster = ' . (int)$existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        //return the error
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                    }

                    $query = 'UPDATE #__topics SET topic_last_poster_colour = ' . $db->Quote($user->user_colour) . ' WHERE topic_last_poster_id = ' . (int)$existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        //return the error
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                    }

                    $query = 'SELECT config_value FROM #__config WHERE config_name = \'newest_user_id\'';
                    $db->setQuery($query);
                    $newest_user_id = $db->loadResult();
                    if ($newest_user_id == $existinguser->userid) {
                        $query = 'UPDATE #__config SET config_value = ' . $db->Quote($user->user_colour) . ' WHERE config_name = \'newest_user_id\'';
	                    $db->setQuery($query);
                        if (!$db->query()) {
                            //return the error
                            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                        }
                    }

                    //log the group change success
                    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
                }
            }
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function blockUser($userinfo, &$existinguser, &$status) {
        //block the user
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'INSERT INTO #__banlist (ban_userid, ban_start) VALUES (' . (int)$existinguser->userid . ',' . time() . ')';
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function unblockUser($userinfo, &$existinguser, &$status) {
        //unblock the user
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'DELETE FROM #__banlist WHERE ban_userid=' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function activateUser($userinfo, &$existinguser, &$status) {
        //activate the user
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET user_type = 0, user_inactive_reason =0, user_actkey = \'\'  WHERE user_id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function inactivateUser($userinfo, &$existinguser, &$status) {
        //set activation key
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET user_type = 1, user_inactive_reason = 1, user_actkey =' . $db->Quote($userinfo->activation) . ' WHERE user_id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }

    /**
     * @param object $userinfo
     * @param array $status
     *
     * @return void
     */
    function createUser($userinfo, &$status) {
    	//found out what usergroup should be used
        $db = JFusionFactory::getDatabase($this->getJname());
        $params = JFusionFactory::getParams($this->getJname());
        $update_block = $params->get('update_block');
        $update_activation = $params->get('update_activation');
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING');
        } else {
            $usergroup = $usergroups[0];
            $username_clean = $this->filterUsername($userinfo->username);

            //prevent anonymous user being created
            if ($username_clean == 'anonymous'){
                $status['error'][] = 'reserved username';
            } else {
                //prepare the variables
                $user = new stdClass;
                $user->id = null;
                $user->username = $userinfo->username;
                $user->username_clean = $username_clean;
                if (isset($userinfo->password_clear)) {
                    /**
                     * @ignore
                     * @var $auth JFusionAuth_phpbb3
                     */
                    $auth = JFusionFactory::getAuth($this->getJname());
                    $user->user_password = $auth->HashPassword($userinfo->password_clear);
                } else {
                    $user->user_password = $userinfo->password;
                }
                $user->user_pass_convert = 0;
                $user->user_email = strtolower($userinfo->email);
                $user->user_email_hash = crc32(strtolower($userinfo->email)) . strlen($userinfo->email);
                $user->group_id = $usergroup;
                $user->user_permissions = '';
                $user->user_allow_pm = 1;
                $user->user_actkey = '';
                $user->user_ip = '';
                $user->user_regdate = time();
                $user->user_passchg = time();
                $user->user_options = 895;
                if (!empty($userinfo->activation) && $update_activation) {
                    $user->user_inactive_reason = 1;
                    $user->user_actkey = $userinfo->activation;
                    $user->user_type = 1;
                } else {
                    $user->user_inactive_reason = 0;
                    $user->user_type = 0;
                }
                $user->user_inactive_time = 0;
                $user->user_lastmark = time();
                $user->user_lastvisit = 0;
                $user->user_lastpost_time = 0;
                $user->user_lastpage = '';
                $user->user_posts = 0;
                $user->user_colour = '';
                $user->user_occ = '';
                $user->user_interests = '';
                $user->user_avatar = '';
                $user->user_avatar_type = 0;
                $user->user_avatar_width = 0;
                $user->user_avatar_height = 0;
                $user->user_new_privmsg = 0;
                $user->user_unread_privmsg = 0;
                $user->user_last_privmsg = 0;
                $user->user_message_rules = 0;
                $user->user_emailtime = 0;
                $user->user_notify = 0;
                $user->user_notify_pm = 1;
                $user->user_allow_pm = 1;
                $user->user_allow_viewonline = 1;
                $user->user_allow_viewemail = 1;
                $user->user_allow_massemail = 1;
                $user->user_sig = '';
                $user->user_sig_bbcode_uid = '';
                $user->user_sig_bbcode_bitfield = '';
                //Find some default values
                $query = 'SELECT config_name, config_value FROM #__config WHERE config_name IN(\'board_timezone\', \'default_dateformat\', \'default_lang\', \'default_style\', \'board_dst\', \'rand_seed\')';
                $db->setQuery($query);
                $rows = $db->loadObjectList();
                $config = array();
                foreach ($rows as $row) {
                    $config[$row->config_name] = $row->config_value;
                }
                $user->user_timezone = $config['board_timezone'];
                $user->user_dateformat = $config['default_dateformat'];
                $user->user_lang = $config['default_lang'];
                $user->user_style = $config['default_style'];
                $user->user_dst = $config['board_dst'];
                $user->user_full_folder = - 4;
                $user->user_notify_type = 0;
                //generate a unique id
                jimport('joomla.user.helper');
                $user->user_form_salt = JUserHelper::genRandomPassword(13);

                //update the user colour, avatar, etc to the groups if applicable
                $query = 'SELECT group_colour, group_rank, group_avatar, group_avatar_type, group_avatar_width, group_avatar_height FROM #__groups WHERE group_id = '.$usergroup;
                $db->setQuery($query);
                $group_attribs = $db->loadAssoc();
                if (!empty($group_attribs)) {
                    foreach($group_attribs AS $k => $v) {
                        if (!empty($v)) {
                            $user->{str_replace('group_', 'user_', $k)} = $v;
                        }
                    }
                }

                //now append the new user data
                if (!$db->insertObject('#__users', $user, 'id')) {
                    //return the error
                    $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                } else {
                    //now create a user_group entry
                    $query = 'INSERT INTO #__user_group (group_id, user_id, group_leader, user_pending) VALUES (' . $usergroup . ',' . (int)$user->id . ', 0,0 )';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        //return the error
                        $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                    } else {
                        //is this group the newly registered group?
                        $query = 'SELECT group_id, group_name FROM #__groups WHERE group_name IN (\'NEWLY_REGISTERED\',\'REGISTERED\') AND group_type = 3';
                        $db->setQuery($query);
                        $groups = $db->loadObjectList('group_name');
                        if ($usergroup == $groups['NEWLY_REGISTERED']->group_id) {
                            //we need to also add the user to the regular registered group or they may find themselves groupless
                            $query = 'INSERT INTO #__user_group (group_id, user_id, group_leader, user_pending) VALUES (' . $groups['REGISTERED']->group_id . ',' . (int)$user->id . ', 0,0 )';
                            $db->setQuery($query);
                            if (!$db->query()) {
                                //return the error
                                $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                                return;
                            }
                        }

                        //update the total user count
                        $query = 'UPDATE #__config SET config_value = config_value + 1 WHERE config_name = \'num_users\'';
                        $db->setQuery($query);
                        if (!$db->query()) {
                            //return the error
                            $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                        } else {
                            //update the newest username
                            $query = 'UPDATE #__config SET config_value = ' . $db->Quote($userinfo->username) . ' WHERE config_name = \'newest_username\'';
                            $db->setQuery($query);
                            if (!$db->query()) {
                                //return the error
                                $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                            } else {
                                //update the newest userid
                                $query = 'UPDATE #__config SET config_value = ' . (int)$user->id . ' WHERE config_name = \'newest_user_id\'';
                                $db->setQuery($query);
                                if (!$db->query()) {
                                    //return the error
                                    $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                                } else {
                                    //get the username color
                                    if (!empty($user->user_colour)) {
                                        //set the correct new username color
                                        $query = 'UPDATE #__config SET config_value = ' . $db->Quote($user->user_colour) . ' WHERE config_name = \'newest_user_colour\'';
                                        $db->setQuery($query);
                                        if (!$db->query()) {
                                            //return the error
                                            $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                                        }
                                    }
                                    if (!empty($userinfo->block) && $update_block) {
                                        $query = 'INSERT INTO #__banlist (ban_userid, ban_start) VALUES (' . (int)$user->id . ',' . time() . ')';
                                        $db->setQuery($query);
                                        if (!$db->query()) {
                                            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
                                        } else {
                                            $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $userinfo->block;
                                        }
                                    }
                                    //return the good news
                                    $status['debug'][] = JText::_('USER_CREATION');
                                    $status['userinfo'] = $this->getUser($userinfo);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param object $userinfo
     * @return array
     */
    function deleteUser($userinfo) {
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());
        //retreive the database object
        $db = JFusionFactory::getDatabase($this->getJname());
        //set the userid
        $user_id = $userinfo->userid;
        // Before we begin, we will remove the reports the user issued.
        $query = 'SELECT r.post_id, p.topic_id
            FROM #__reports r, #__posts p
            WHERE r.user_id = ' . (int)$user_id . '
                AND p.post_id = r.post_id';
        $db->setQuery($query);
        $report_posts = $report_topics = array();
        if ($db->query()) {
            $results = $db->loadObjectList();
            if ($results) {
                foreach ($results as $row) {
                    $report_posts[] = $row->post_id;
                    $report_topics[] = $row->topic_id;
                }
                //$status['debug'][] = 'Retrieved all reported posts/topics by user '.$user_id;
            }
        } elseif ($db->stderr()) {
            $status['error'][] = 'Error Could not retrieve reported posts/topics by user '.$user_id.': '.$db->stderr();
            return $status;
        }
        if (sizeof($report_posts)) {
            $report_posts = array_unique($report_posts);
            $report_topics = array_unique($report_topics);
            // Get a list of topics that still contain reported posts
            $query = 'SELECT DISTINCT topic_id
                FROM #__posts
                WHERE topic_id IN (' . implode(', ', $report_topics) . ')
                    AND post_reported = 1
                    AND post_id IN (' . implode(', ', $report_posts) . ')';
            $db->setQuery($query);
            $keep_report_topics = array();
            if ($db->query()) {
                $results = $db->loadObjectList();
                if ($results) {
                    foreach ($results as $row) {
                        $keep_report_topics[] = $row->topic_id;
                    }
                    //$status['debug'][] = 'Sorted through reported topics by user '.$user_id.' to keep.';
                }
            } else {
                $status['error'][] = 'Error Could not retrieve a list of topics that still contain reported posts by user '.$user_id.': '.$db->stderr();
            }
            if (sizeof($keep_report_topics)) {
                $report_topics = array_diff($report_topics, $keep_report_topics);
            }
            unset($keep_report_topics);
            // Now set the flags back
            $query = 'UPDATE #__posts
                SET post_reported = 0
                WHERE post_id IN (' . implode(', ', $report_posts) . ')';
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = 'Error Could not update post reported flag: '.$db->stderr();
            } else {
                //$status['debug'][] = 'Updated reported posts flag.';
            }
            if (sizeof($report_topics)) {
                $query = 'UPDATE #__topics
                    SET topic_reported = 0
                    WHERE topic_id IN (' . implode(', ', $report_topics) . ')';
                $db->setQuery($query);
                if (!$db->query()) {
                    $status['error'][] = 'Error Could not update topics reported flag: '.$db->stderr();
                } else {
                    //$status['debug'][] = 'Updated reported topics flag.';
                }
            }
        }
        // Remove reports
        $query = 'DELETE FROM #__reports WHERE user_id = ' . (int)$user_id;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not delete reports by user '.$user_id.': '.$db->stderr();
        } else {
            //$status['debug'][] = 'Deleted reported posts/topics by user '.$user_id;
        }
        //update all topics started by and posts by the user to anonymous
        $post_username = (!empty($userinfo->name)) ? $userinfo->name : $userinfo->username;
        $query = 'UPDATE #__forums
            SET forum_last_poster_id = 1, forum_last_poster_name = ' . $db->Quote($post_username) . ", forum_last_poster_colour = ''
            WHERE forum_last_poster_id = $user_id";
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not update forum last poster for user '.$user_id.': '.$db->stderr();
        } else {
            //$status['debug'][] = 'Updated last poster to anonymous if last post was by user '.$user_id;
        }
        $query = 'UPDATE #__posts
            SET poster_id = 1, post_username = ' . $db->Quote($post_username) . '
            WHERE poster_id = '.$user_id;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not update posts by user '.$user_id.': '.$db->stderr();
        } else {
            //$status['debug'][] = 'Updated posts to be from anonymous if posted by user '.$user_id;
        }
        $query = 'UPDATE #__posts
            SET post_edit_user = 1
            WHERE post_edit_user = '.$user_id;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not update edited posts by user '.$user_id.': '.$db->stderr();
        } else {
            //$status['debug'][] = 'Updated edited posts to be from anonymous if edited by user '.$user_id;
        }
        $query = 'UPDATE #__topics
            SET topic_poster = 1, topic_first_poster_name = ' . $db->Quote($post_username) . ', topic_first_poster_colour = \'\'
            WHERE topic_poster = '.$user_id;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not update topics by user '.$user_id.': '.$db->stderr();
        } else {
            //$status['debug'][] = 'Updated topics to be from anonymous if started by user '.$user_id;
        }
        $query = 'UPDATE #__topics
            SET topic_last_poster_id = 1, topic_last_poster_name = ' . $db->Quote($post_username) . ', topic_last_poster_colour = \'\'
            WHERE topic_last_poster_id = '.$user_id;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not update last topic poster for user '.$user_id.': '.$db->stderr();
        } else {
            //$status['debug'][] = 'Updated topic last poster to be anonymous if set as user '.$user_id;
        }
        // Since we change every post by this author, we need to count this amount towards the anonymous user
        $query = 'SELECT user_posts FROM #__users WHERE user_id = '.$user_id;
        $db->setQuery($query);
        $user_posts = $db->loadResult();
        // Update the post count for the anonymous user
        if ($user_posts > 0) {
            $query = 'UPDATE #__users
                SET user_posts = user_posts + '.$user_posts.
                ' WHERE user_id = 1';
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = 'Error Could not update the number of posts for anonymous user: '.$db->stderr();
            } else {
                //$status['debug'][] = 'Updated post count for anonymous user.';
            }
        }
        $table_ary = array('users', 'user_group', 'topics_watch', 'forums_watch', 'acl_users', 'topics_track', 'topics_posted', 'forums_track', 'profile_fields_data', 'moderator_cache', 'drafts', 'bookmarks');
        foreach ($table_ary as $table) {
            $query = 'DELETE FROM #__'.$table.
                ' WHERE user_id = '.$user_id;
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = 'Error Could not delete records from '.$table.' for user '.$user_id.': '.$db->stderr();
            } else {
                //$status['debug'][] = 'Deleted records from '.$table.' for user '.$user_id;
            }
        }
        // Remove any undelivered mails...
        $query = 'SELECT msg_id, user_id
            FROM #__privmsgs_to
            WHERE author_id = ' . $user_id . '
                AND folder_id = -3';
        $db->setQuery($query);
        $undelivered_msg = $undelivered_user = array();
        if ($db->query()) {
            $results = $db->loadObjectList();
            if ($results) {
                foreach ($results as $row) {
                    $undelivered_msg[] = $row->msg_id;
                    $undelivered_user[$row->user_id][] = true;
                }
                //$status['debug'][] = 'Retrieved undelivered private messages from user '.$user_id;
            }
        } else {
            $status['error'][] = 'Error Could not retrieve undeliverd messages to user '.$user_id.': '.$db->stderr();
        }
        if (sizeof($undelivered_msg)) {
            $query = 'DELETE FROM #__privmsgs
                WHERE msg_id (' . implode(', ', $undelivered_msg) . ')';
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = 'Error Could not delete private messages for user '.$user_id.': '.$db->stderr();
            } else {
                //$status['debug'][] = 'Deleted undelivered private messages from user '.$user_id;
            }
        }
        $query = 'DELETE FROM #__privmsgs_to
            WHERE author_id = ' . $user_id . '
                AND folder_id = -3';
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not delete private messages that are in no folder from user '.$user_id.': '.$db->stderr();
        } else {
            //$status['debug'][] = 'Deleted private messages that are in no folder from user '.$user_id;
        }
        // Delete all to-information
        $query = 'DELETE FROM #__privmsgs_to
            WHERE user_id = ' . $user_id;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not delete private messages to user '.$user_id.': '.$db->stderr();
        } else {
            //$status['debug'][] = 'Deleted private messages sent to user '.$user_id;
        }
        // Set the remaining author id to anonymous - this way users are still able to read messages from users being removed
        $query = 'UPDATE #__privmsgs_to
            SET author_id = 1
            WHERE author_id = ' . $user_id;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not update rest of private messages for user '.$user_id.' to anonymous: '.$db->stderr();
        } else {
            //$status['debug'][] = 'Updated the author to anonymous for the rest of the PMs in the "to" table if originally sent by user '.$user_id;
        }
        $query = 'UPDATE #__privmsgs
            SET author_id = 1
            WHERE author_id = ' . $user_id;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = 'Error Could not update rest of private messages for user '.$user_id.' to anonymous: '.$db->stderr();
        } else {
            //$status['debug'][] = 'Updated the author to anonymous for the rest of the PMs in the main PM table if originally sent by user '.$user_id;
        }
        foreach ($undelivered_user as $_user_id => $ary) {
            if ($_user_id == $user_id) {
                continue;
            }
            $query = 'UPDATE #__users
                SET user_new_privmsg = user_new_privmsg - ' . sizeof($ary) . ',
                    user_unread_privmsg = user_unread_privmsg - ' . sizeof($ary) . '
                WHERE user_id = ' . $_user_id;
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = 'Error Could not update the number of PMs for user '.$_user_id.' for user '.$user_id.' was deleted: '.$db->stderr();
            } else {
                //$status['debug'][] = 'Updated the the number of PMs for user '.$_user_id.' since user '.$user_id.' was deleted.';
            }
        }
        //update the total user count
        $query = 'UPDATE #__config SET config_value = config_value - 1 WHERE config_name = \'num_users\'';
        $db->setQuery($query);
        if (!$db->query()) {
            //return the error
            $status['error'][] = JText::_('USER_DELETION_ERROR') . $db->stderr();
            return $status;
        }
        //check to see if this user was the newest user
        $query = 'SELECT COUNT(*) FROM #__config WHERE config_name = \'newest_user_id\' AND config_value = '.$db->Quote($user_id);
        $db->setQuery($query);
        if ($db->loadResult()) {
            //retrieve the new newest user
            $query = 'SELECT user_id, username, user_colour FROM #__users WHERE user_regdate = (SELECT MAX(user_regdate) FROM #__users)';
            $db->setQuery($query);
            $newest_user = $db->loadObject();
            if ($newest_user) {
                //update the newest username
                $query = 'UPDATE #__config SET config_value = ' . $db->Quote($newest_user->username) . ' WHERE config_name = \'newest_username\'';
                $db->setQuery($query);
                if (!$db->query()) {
                    //return the error
                    $status['error'][] = JText::_('USER_DELETION_ERROR') . $db->stderr();
                    return $status;
                }
                //update the newest userid
                $query = 'UPDATE #__config SET config_value = ' . $newest_user->user_id . ' WHERE config_name = \'newest_user_id\'';
                $db->setQuery($query);
                if (!$db->query()) {
                    //return the error
                    $status['error'][] = JText::_('USER_DELETION_ERROR') . $db->stderr();
                    return $status;
                }
                //set the correct new username color
                $query = 'UPDATE #__config SET config_value = ' . $db->Quote($newest_user->user_colour) . ' WHERE config_name = \'newest_user_colour\'';
                $db->setQuery($query);
                if (!$db->query()) {
                    //return the error
                    $status['error'][] = JText::_('USER_DELETION_ERROR') . $db->stderr();
                    return $status;
                }
            }
        }
        $status['debug'][] = JText::_('USER_DELETION'). ' ' . $user_id;
        return $status;
    }

    /**
     * @param bool $keepalive
     *
     * @return int
     */
    function syncSessions($keepalive = false) {
        $return = 0;
        $debug = (defined('DEBUG_SYSTEM_PLUGIN') ? true : false);

	    $params = JFusionFactory::getParams($this->getJname());

	    $login_type = $params->get('login_type');
	    if ($login_type == 1) {
	        if ($debug) {
	            JError::raiseNotice('500','phpbb3 syncSessions called');
	        }

	        $options = array();
	        $options['action'] = 'core.login.site';

	        //phpbb variables
	        $phpbb_cookie_prefix = $params->get('cookie_prefix');
	        $userid_cookie_value = JRequest::getVar($phpbb_cookie_prefix . '_u', '', 'cookie');
	        $sid_cookie_value = JRequest::getVar($phpbb_cookie_prefix . '_sid', '', 'cookie');
	        $phpbb_allow_autologin = $params->get('allow_autologin');
	        $persistant_cookie = ($phpbb_allow_autologin) ? JRequest::getVar($phpbb_cookie_prefix . '_k', '', 'cookie') : '';
	        //joomla variables
	        $JUser = JFactory::getUser();
	        if (JPluginHelper::isEnabled ( 'system', 'remember' )) {
	            jimport('joomla.utilities.utility');
	            $hash = JUtility::getHash('JLOGIN_REMEMBER');
	            $joomla_persistant_cookie = JRequest::getString($hash, '', 'cookie', JREQUEST_ALLOWRAW | JREQUEST_NOTRIM);
	        } else {
	            $joomla_persistant_cookie = '';
	        }

	        if (!$JUser->get('guest', true)) {
	            //user logged into Joomla so let's check for an active phpBB session

	            if (!empty($phpbb_allow_autologin) && !empty($persistant_cookie) && !empty($sid_cookie_value)) {
	                //we have a persistent cookie set so let phpBB handle the session renewal
	                if ($debug) {
	                    JError::raiseNotice('500', 'phpbb persistant cookie enabled and set so let phpbb handle renewal');
	                }
	            } else {
	                if ($debug) {
	                    JError::raiseNotice('500','Joomla user is logged in');
	                }

	                //check to see if the userid cookie is empty or if it contains the anonymous user, or if sid cookie is empty or missing
	                if (empty($userid_cookie_value) || $userid_cookie_value == '1' || empty($sid_cookie_value)) {
	                    if ($debug) {
	                        JError::raiseNotice('500','phpbb3 has a guest session');
	                    }
	                    //find the userid attached to Joomla userid
	                    $joomla_userid = $JUser->get('id');
	                    $userlookup = JFusionFunction::lookupUser($this->getJname(), $joomla_userid);
	                    //get the user's info
	                    if (!empty($userlookup)) {
	                        $db = JFusionFactory::getDatabase($this->getJname());
	                        $query = 'SELECT username_clean AS username, user_email as email FROM #__users WHERE user_id = '.$userlookup->userid;
	                        $db->setQuery($query);
	                        $user_identifiers = $db->loadObject();
	                        $userinfo = $this->getUser($user_identifiers);
	                    }

	                    if (!empty($userinfo) && (!empty($keepalive) || !empty($joomla_persistant_cookie))) {
	                        if ($debug) {
	                            JError::raiseNotice('500','keep alive enabled or Joomla persistant cookie found, and found a valid phpbb3 user so calling createSession');
	                        }
	                        //enable remember me as this is a keep alive function anyway
	                        $options['remember'] = 1;
	                        //create a new session
	                        $status = $this->createSession($userinfo, $options);

	                        if ($debug) {
	                            JFusionFunction::raiseWarning('500',$status);
	                        }

	                        //signal that session was changed
	                        $return = 1;
	                    } else {
	                        if ($debug) {
	                            JError::raiseNotice('500','keep alive disabled or no persistant session found so calling Joomla\'s destorySession');
	                        }
	                        $JoomlaUser = JFusionFactory::getUser('joomla_int');

		                    $userinfo = new stdClass;
		                    $userinfo->id = $JUser->id;
		                    $userinfo->username = $JUser->username;
		                    $userinfo->name = $JUser->name;
		                    $userinfo->email = $JUser->email;
		                    $userinfo->block = $JUser->block;
		                    $userinfo->activation = $JUser->activation;
		                    $userinfo->groups = $JUser->groups;
		                    $userinfo->password = $JUser->password;
		                    $userinfo->password_clear = $JUser->password_clear;

	                        $options['clientid'][] = '0';
	                        $status = $JoomlaUser->destroySession($userinfo, $options);
	                        if ($debug) {
	                            JFusionFunction::raiseWarning('500',$status);
	                        }
	                    }
	                } else {
	                    if ($debug) {
	                        JError::raiseNotice('500','phpBB user logged in');
	                    }
	                }
	            }
	        } elseif ((!empty($sid_cookie_value) || !empty($persistant_cookie)) && $userid_cookie_value != '1') {
	            if ($debug) {
	                JError::raiseNotice('500','Joomla has a guest session');
	            }
	            //the user is not logged into Joomla and we have an active phpBB session
	            if (!empty($joomla_persistant_cookie)) {
	                if ($debug) {
	                    JError::raiseNotice('500','Joomla persistant cookie found so let Joomla handle renewal');
	                }
	            } elseif (empty($keepalive)) {
	               if ($debug) {
	                    JError::raiseNotice('500','Keep alive disabled so kill phpBBs session');
	                }
	                //something fishy or person chose not to use remember me so let's destroy phpBBs session
	                $params = JFusionFactory::getParams($this->getJname());
	                $phpbb_cookie_name = $params->get('cookie_prefix');
	                $phpbb_cookie_path = $params->get('cookie_path');
	                //baltie cookie domain fix
	                $phpbb_cookie_domain = $params->get('cookie_domain');
	                if ($phpbb_cookie_domain == 'localhost' || $phpbb_cookie_domain == '127.0.0.1') {
	                    $phpbb_cookie_domain = '';
	                }
	                //delete the cookies
	                $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_u', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
	                $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_sid', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
	                $status['debug'][] = JFusionFunction::addCookie($phpbb_cookie_name . '_k', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
	                $return = 1;
	            } elseif ($debug) {
	                JError::raiseNotice('500','Keep alive enabled so renew Joomla\'s session');
	            } else {
	                $db = JFusionFactory::getDatabase($this->getJname());
	                if (!empty($persistant_cookie)) {
	                    $query = 'SELECT user_id FROM #__sessions_keys WHERE key_id = ' . $db->Quote(md5($persistant_cookie));
	                    if ($debug) {
	                        JError::raiseNotice('500','Using phpBB persistant cookie to find user');
	                    }
	                } else {
	                    $query = 'SELECT session_user_id FROM #__sessions WHERE session_id = ' . $db->Quote($sid_cookie_value);
	                    if ($debug) {
	                        JError::raiseNotice('500','Using phpBB sid cookie to find user');
	                    }
	                }
	                $db->setQuery($query);
	                $userid = $db->loadresult();
	                $userlookup = JFusionFunction::lookupUser($this->getJname(), $userid, false);
	                if (!empty($userlookup)) {
	                    if ($debug) {
	                        JError::raiseNotice('500','Found a phpBB user so attempting to renew Joomla\'s session.');
	                    }
	                    //get the user's info
	                    $jdb = JFactory::getDBO();
	                    $query = 'SELECT username, email FROM #__users WHERE id = '.$userlookup->id;
	                    $jdb->setQuery($query);
	                    $user_identifiers = $jdb->loadObject();
	                    $JoomlaUser = JFusionFactory::getUser('joomla_int');
	                    $userinfo = $JoomlaUser->getUser($user_identifiers);
	                    if (!empty($userinfo)) {
	                        global $JFusionActivePlugin;
	                        $JFusionActivePlugin = $this->getJname();
	                        $status = $JoomlaUser->createSession($userinfo, $options);
	                        if ($debug) {
	                            JFusionFunction::raiseWarning('500',$status);
	                        }
	                        //no need to signal refresh as Joomla will recognize this anyway
	                    }
	                }
	            }
	        }
	    } else {
		    if ($debug) {
			    JError::raiseNotice('500','phpbb3 syncSessions do not work in this login mode.');
		    }
	    }
        return $return;
    }
}