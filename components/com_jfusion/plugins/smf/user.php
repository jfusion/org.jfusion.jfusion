<?php

/**
 * file containing user function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractuser.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';
/**
 * JFusion User Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractuser.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_smf extends JFusionUser
{
    /**
     * get user
     *
     * @param object $userinfo holds the new user data
     *
     * @access public
     *
     * @return null|object
     */
    function getUser($userinfo)
    {
        //get the identifier
        list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.memberName', 'a.emailAddress');
        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT a.ID_MEMBER as userid, a.memberName as username, a.realName as name, a.emailAddress as email, a.passwd as password, a.passwordSalt as password_salt, a.validation_code as activation, a.is_activated, null as reason, a.lastLogin as lastvisit, a.ID_GROUP as group_id ' . 'FROM #__members as a ' . 'WHERE ' . $identifier_type . '=' . $db->Quote($identifier);
        $db->setQuery($query);
        $result = $db->loadObject();
        if ($result) {
            if ($result->group_id == 0) {
                $result->group_name = 'Default Usergroup';
            } else {
                $query = 'SELECT groupName FROM #__membergroups WHERE ID_GROUP = ' . (int)$result->group_id;
                $db->setQuery($query);
                $result->group_name = $db->loadResult();
            }
            $result->groups = array($result->group_id);
            $result->groupnames = array($result->group_name);

            //Check to see if they are banned
            $query = 'SELECT ID_BAN_GROUP, expire_time FROM #__ban_groups WHERE name= ' . $db->quote($result->username);
            $db->setQuery($query);
            $expire_time = $db->loadObject();
            if ($expire_time) {
                if ($expire_time->expire_time == '' || $expire_time->expire_time > time()) {
                    $result->block = 1;
                } else {
                    $result->block = 0;
                }
            } else {
                $result->block = 0;
            }
            if ($result->is_activated == 1) {
                $result->activation = '';
            }
        }
        return $result;
    }

    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'smf';
    }

    /**
     * delete user
     *
     * @param object $userinfo holds the new user data
     *
     * @access public
     *
     * @return array
     */
    function deleteUser($userinfo)
    {
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'DELETE FROM #__members WHERE memberName = ' . $db->quote($userinfo->username);
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $db->stderr();
        } else {
            //update the stats
            $query = 'UPDATE #__settings SET value = value - 1     WHERE variable = \'totalMembers\' ';
            $db->setQuery($query);
            if (!$db->query()) {
                //return the error
                $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $db->stderr();
            } else {
                $query = 'SELECT MAX(ID_MEMBER) as ID_MEMBER FROM #__members WHERE is_activated = 1';
                $db->setQuery($query);
                $resultID = $db->loadObject();
                if (!$resultID) {
                    //return the error
                    $status['error'][] = JText::_('USER_DELETION_ERROR') . $db->stderr();
                } else {
                    $query = 'SELECT realName as name FROM #__members WHERE ID_MEMBER = ' . $db->quote($resultID->ID_MEMBER) . ' LIMIT 1';
                    $db->setQuery($query);
                    $resultName = $db->loadObject();
                    if (!$resultName) {
                        //return the error
                        $status['error'][] = JText::_('USER_DELETION_ERROR') . $db->stderr();
                    } else {
                        $query = 'REPLACE INTO #__settings (variable, value) VALUES (\'latestMember\', ' . $resultID->ID_MEMBER . '), (\'latestRealName\', ' . $db->quote($resultName->name) . ')';
                        $db->setQuery($query);
                        if (!$db->query()) {
                            //return the error
                            $status['error'][] = JText::_('USER_DELETION_ERROR') . $db->stderr();
                        } else {
                            $status['debug'][] = JText::_('USER_DELETION') . ' ' . $userinfo->username;
                        }
                    }
                }
            }
        }
        return $status;
    }

    /**
     * destroy session
     *
     * @param object $userinfo holds the new user data
     * @param array  $options  Status array
     *
     * @access public
     *
     * @return array
     */
    function destroySession($userinfo, $options)
    {
        $status = array('error' => array(),'debug' => array());
        //        $status = JFusionJplugin::destroySession($userinfo, $options,$this->getJname());
        $params = JFusionFactory::getParams($this->getJname());
        $status['debug'][] = JFusionFunction::addCookie($params->get('cookie_name'), '', 0, $params->get('cookie_path'), $params->get('cookie_domain'), $params->get('secure'), $params->get('httponly'));

	    $db = JFusionFactory::getDatabase($this->getJname());
	    $query = 'DELETE FROM #__log_online WHERE ID_MEMBER = '.$userinfo->userid.' LIMIT 1';
	    $db->setQuery($query);
	    $db->query();
        return $status;
    }

    /**
     * create session
     *
     * @param object $userinfo holds the new user data
     * @param array  $options  options
     *
     * @access public
     *
     * @return array
     */
    function createSession($userinfo, $options)
    {
        $status = array('error' => array(),'debug' => array());
        //do not create sessions for blocked users
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
            $params = JFusionFactory::getParams($this->getJname());
            $status = JFusionJplugin::createSession($userinfo, $options, $this->getJname(),$params->get('brute_force'));
        }
        return $status;
    }

    /**
     * filterUsername
     *
     * @param string $username holds the new user data
     *
     * @access public
     *
     * @return string
     */
    function filterUsername($username)
    {
        //no username filtering implemented yet
        return $username;
    }

    /**
     * updatePassword
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status)
    {
        $existinguser->password = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
        $existinguser->password_salt = substr(md5(rand()), 0, 4);
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__members SET passwd = ' . $db->quote($existinguser->password) . ', passwordSalt = ' . $db->quote($existinguser->password_salt) . ' WHERE ID_MEMBER  = ' . (int)$existinguser->userid;
        $db = JFusionFactory::getDatabase($this->getJname());
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
        }
    }

    /**
     * updateUsername
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function updateUsername($userinfo, &$existinguser, &$status)
    {
    }

    /**
     * updateEmail
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status)
    {
        //we need to update the email
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__members SET emailAddress =' . $db->quote($userinfo->email) . ' WHERE ID_MEMBER =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
        }
    }

    /**
     * updateUsergroup
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status)
    {
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST');
        } else {
            $usergroup = $usergroups[0];
            
			$db = JFusionFactory::getDatabase($this->getJname());
			$query = 'UPDATE #__members SET ID_GROUP =' . $db->quote($usergroup) . ' WHERE ID_MEMBER =' . (int)$existinguser->userid;
			$db->setQuery($query);
			if (!$db->query()) {
				$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
			} else {
				$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
			}
        }
    }

    /**
     * blockUser
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function blockUser($userinfo, &$existinguser, &$status)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $ban = new stdClass;
        $ban->ID_BAN_GROUP = null;
        $ban->name = $existinguser->username;
        $ban->ban_time = time();
        $ban->expire_time = null;
        $ban->cannot_access = 1;
        $ban->cannot_register = 0;
        $ban->cannot_post = 0;
        $ban->cannot_login = 0;
        $ban->reason = 'You have been banned from this software. Please contact your site admin for more details';
        //now append the new user data
        if (!$db->insertObject('#__ban_groups', $ban, 'ID_BAN_GROUP')) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        }
        $ban_item = new stdClass;
        $ban_item->ID_BAN_GROUP = $ban->ID_BAN_GROUP;
        $ban_item->ID_MEMBER = $existinguser->userid;
        if (!$db->insertObject('#__ban_items', $ban_item, 'ID_BAN')) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        }
    }

    /**
     * unblock user
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function unblockUser($userinfo, &$existinguser, &$status)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'DELETE FROM #__ban_groups WHERE name = ' . $db->quote($existinguser->username);
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        }
        $query = 'DELETE FROM #__ban_items WHERE ID_MEMBER = ' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        }
    }

    /**
     * activate user
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function activateUser($userinfo, &$existinguser, &$status)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__members SET is_activated = 1, validation_code = \'\' WHERE ID_MEMBER  = ' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }

    /**
     * deactivate user
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function inactivateUser($userinfo, &$existinguser, &$status)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__members SET is_activated = 0, validation_code = ' . $db->Quote($userinfo->activation) . ' WHERE ID_MEMBER  = ' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }

    /**
     * Creates a new user
     *
     * @param object $userinfo holds the new user data
     * @param array  &$status  Status array
     *
     * @access public
     *
     * @return void
     */
    function createUser($userinfo, &$status)
    {
        //we need to create a new SMF user
        $db = JFusionFactory::getDatabase($this->getJname());
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');

        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('ERROR_CREATING_USER') . ": " . JText::_('USERGROUP_MISSING');
        } else {
            //prepare the user variables
            $user = new stdClass;
            $user->ID_MEMBER = null;
            $user->memberName = $userinfo->username;
            $user->realName = $userinfo->name;
            $user->emailAddress = $userinfo->email;
            if (isset($userinfo->password_clear)) {
                $user->passwd = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
                $user->passwordSalt = substr(md5(rand()), 0, 4);
            } else {
                $user->passwd = $userinfo->password;
                if (!isset($userinfo->password_salt)) {
                    $user->passwordSalt = substr(md5(rand()), 0, 4);
                } else {
                    $user->passwordSalt = $userinfo->password_salt;
                }
            }
            $user->posts = 0;
            $user->dateRegistered = time();
            if ($userinfo->activation) {
                $user->is_activated = 0;
                $user->validation_code = $userinfo->activation;
            } else {
                $user->is_activated = 1;
                $user->validation_code = '';
            }
            $user->personalText = '';
            $user->pm_email_notify = 1;
            $user->hideEmail = 1;
            $user->ID_THEME = 0;

            $user->ID_GROUP = $usergroups[0];
            $user->ID_POST_GROUP = $params->get('userpostgroup', 4);
            //now append the new user data
            if (!$db->insertObject('#__members', $user, 'ID_MEMBER')) {
                //return the error
                $status['error'] = JText::_('USER_CREATION_ERROR') . ': ' . $db->stderr();
            } else {
                //update the stats
                $query = 'UPDATE #__settings SET value = value + 1     WHERE variable = \'totalMembers\' ';
                $db->setQuery($query);
                if (!$db->query()) {
                    //return the error
                    $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                } else {
                    $date = strftime('%Y-%m-%d');
                    $query = 'UPDATE #__log_activity SET registers = registers + 1 WHERE date = \'' . $date . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        //return the error
                        $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                    } else {
                        $query = 'REPLACE INTO #__settings (variable, value) VALUES (\'latestMember\', ' . $user->ID_MEMBER . '), (\'latestRealName\', ' . $db->quote($userinfo->name) . ')';
                        $db->setQuery($query);
                        if (!$db->query()) {
                            //return the error
                            $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                        } else {
                            //return the good news
                            $status['debug'][] = JText::_('USER_CREATION');
                            $status['userinfo'] = $this->getUser($userinfo);
                        }
                    }
                }
            }
        }
    }

    /**
     * Keep alive function called by system plugin to keep session alive
     *
     * @access public
     *
     * @param bool $keepalive
     *
     * @return int False on Error
     */
    function syncSessions($keepalive = false)
    {
    	return 0;
        /*
        //retrieve the smf cookie name
        $params = JFusionFactory::getParams($this->getJname());
        $cookie_name = $params->get('cookie_name');
        $cookie_value = isset($_COOKIE[$cookie_name]) ? $_COOKE[$cookie_name] : '';
        $JUser = JFactory::getUser();
        if (!$JUser->get('guest', true)) {
            //JError::raiseNotice(0, 'joomla logged in');
            //user logged into Joomla so let's check for an active SMF session
            if (empty($cookie_value)) {
                //JError::raiseNotice(0, 'smf logged out:' . $cookie_name . ','.$cookie_value);
                //no SMF session present.
                //Since we can not recreate it due to license issues, logout from joomla instead
                $mainframe = JFactory::getApplication();
                $mainframe->logout();
                $session = JFactory::getSession();
                $session->close();
                return 1;
            } else {
                //JError::raiseNotice(0, 'smf logged in:' . $cookie_name . ','.$cookie_value);

            }
        } else {
            //JError::raiseNotice(0, 'joomla logged out');
            if (!empty($cookie_value)) {
                //JError::raiseNotice(0, 'smf logged in:' . $cookie_name . ','.$cookie_value);
                //the user is not logged into Joomla and we have an active SMF session
                //destroy the SMF session
                $params = JFusionFactory::getParams($this->getJname());
                JFusionFunction::addCookie($params->get('cookie_name'), '', 0, $params->get('cookie_path'), $params->get('cookie_domain'), $params->get('secure'), $params->get('httponly'));
                return 1;
            } else {
                //JError::raiseNotice(0, 'smf logged out:' . $cookie_name . ','.$cookie_value);

            }
        }
        return 1;
        */
    }
}
