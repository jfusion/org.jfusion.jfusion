<?php

/**
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MyBB
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 * 
// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion User Class for MyBB
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MyBB
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_mybb extends JFusionUser {
    /**
     * @param object $userinfo
     * @return null|object
     */
    function getUser($userinfo) {
        //get the identifier
        list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.username', 'a.email');
        // Get user info from database
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT a.uid as userid, a.username, a.usergroup as group_id, a.username as name, a.email, a.password, a.salt as password_salt, a.usergroup as activation, b.isbannedgroup as block FROM #__users as a LEFT OUTER JOIN #__usergroups as b ON a.usergroup = b.gid WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);
        $db->setQuery($query);
        $result = $db->loadObject();
        if ($result) {
            //Check to see if user needs to be activated
            if ($result->group_id == 5) {
                jimport('joomla.user.helper');
                $result->activation = JUserHelper::genRandomPassword(32);
            } else {
                $result->activation = null;
            }
            $result->groups = array($result->group_id);
        }
        return $result;
    }
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'mybb';
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());
        $params = JFusionFactory::getParams($this->getJname());
        $cookiedomain = $params->get('cookie_domain');
        $cookiepath = $params->get('cookie_path', '/');
        //Set cookie values
        $expires = -3600;
        if (!$cookiepath) {
            $cookiepath = '/';
        }
        // Clearing Forum Cookies
        $remove_cookies = array('mybb', 'mybbuser', 'mybbadmin');
        if ($cookiedomain) {
            foreach ($remove_cookies as $name) {
                $status['debug'][] = JFusionFunction::addCookie($name,  '', $expires, $cookiepath, '', $cookiedomain);
            }
        } else {
            foreach ($remove_cookies as $name) {
                $status['debug'][] = JFusionFunction::addCookie($name,  '', $expires, $cookiepath, '');
            }
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
            //get cookiedomain, cookiepath (theIggs solution)
            $params = JFusionFactory::getParams($this->getJname());
            $cookiedomain = $params->get('cookie_domain', '');
            $cookiepath = $params->get('cookie_path', '/');
            //get myBB uid, loginkey
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'SELECT uid, loginkey FROM #__users WHERE username=' . $db->Quote($userinfo->username);
            $db->setQuery($query);
            $user = $db->loadObject();
            // Set cookie values
            $name = 'mybbuser';
            $value = $user->uid . '_' . $user->loginkey;
            $httponly = true;
            if (isset($options['remember'])) {
                if ($options['remember']) {
                    // Make the cookie expire in a years time
                    $expires = 60 * 60 * 24 * 365;
                } else {
                    // Make the cookie expire in 30 minutes
                    $expires = 60 * 30;
                }
            } else {
                //Make the cookie expire in 30 minutes
                $expires = 60 * 30;
            }
            $cookiepath = str_replace(array("\n", "\r"), '', $cookiepath);
            $cookiedomain = str_replace(array("\n", "\r"), '', $cookiedomain);
            $status['debug'][] = JFusionFunction::addCookie($name, $value, $expires, $cookiepath, $cookiedomain, false, $httponly ,true);
        }
        return $status;
    }

    /**
     * @TODO: no username filtering implemented yet
     *
     * @param string $username
     * @return string
     */
    function filterUsername($username) {
        return $username;
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function blockUser($userinfo, &$existinguser, &$status) {
        $db = JFusionFactory::getDatabase($this->getJname());
        $user = new stdClass;
        $user->uid = $existinguser->userid;
        $user->gid = 7;
        $user->oldgroup = $existinguser->groups[0];
        $user->admin = 1;
        $user->dateline = time();
        $user->bantime = '---';
        $user->reason = 'JFusion';
        $user->lifted = 0;
        //now append the new user data
        if (!$db->insertObject('#__banned', $user, 'uid')) {
            //return the error
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            //change its usergroup
            $query = 'UPDATE #__users SET usergroup = 7 WHERE uid = ' . (int)$existinguser->userid;
            $db->setQuery($query);
            if (!$db->query()) {
                //return the error
                $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
            } else {
                $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
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
    function unblockUser($userinfo, &$existinguser, &$status) {
        $db = JFusionFactory::getDatabase($this->getJname());
        //found out what the old usergroup was
        $query = 'SELECT oldgroup from #__banned WHERE uid =' . (int)$existinguser->userid;;
        $db->setQuery($query);
        $oldgroup = $db->loadResult();
        //delete the ban
        $query = 'DELETE FROM #__banned WHERE uid = ' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            //return the error
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            //check the oldgroup
            if (empty($oldgroup)) {
                $params = JFusionFactory::getParams($this->getJname());
                $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
                if (!empty($usergroups)) {
                    $oldgroup = $usergroups[0];
                }
            }
            if (empty($oldgroup)) {
                $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . ": " . JText::_('USERGROUP_MISSING');
            } else {
                //restore the usergroup
                $query = 'UPDATE #__users SET usergroup = ' . (int)$oldgroup . ' WHERE uid = ' . (int)$existinguser->userid;
                $db->setQuery($query);
                if (!$db->query()) {
                    //return the error
                    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
                } else {
                    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
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
    function updatePassword($userinfo, &$existinguser, &$status) {
        jimport('joomla.user.helper');
        $existinguser->password_salt = JUserHelper::genRandomPassword(6);
        $existinguser->password = md5(md5($existinguser->password_salt) . md5($userinfo->password_clear));
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET password =' . $db->Quote($existinguser->password) . ', salt = ' . $db->Quote($existinguser->password_salt) . ' WHERE uid =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
        }
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status) {

        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('USERGROUP_MISSING');
        } else {
            $usergroup = $usergroups[0];
            //update the usergroup
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'UPDATE #__users SET usergroup = ' . $usergroup . ' WHERE uid  = ' . (int)$existinguser->userid;
            $db->setQuery($query);
            if (!$db->Query()) {
                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
            } else {
                $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
            }
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
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING');
        } else {
            $usergroup = $usergroups[0];
            $username_clean = $this->filterUsername($userinfo->username);
            //prepare the variables
            $user = new stdClass;
            $user->uid = null;
            $user->username = $username_clean;
            $user->email = $userinfo->email;
            jimport('joomla.user.helper');
            if (isset($userinfo->password_clear)) {
                //we can update the password
                $user->salt = JUserHelper::genRandomPassword(6);
                $user->password = md5(md5($user->salt) . md5($userinfo->password_clear));
                $user->loginkey = JUserHelper::genRandomPassword(50);
            } else {
                $user->password = $userinfo->password;
                if (!isset($userinfo->password_salt)) {
                    $user->salt = JUserHelper::genRandomPassword(6);
                } else {
                    $user->salt = $userinfo->password_salt;
                }
                $user->loginkey = JUserHelper::genRandomPassword(50);
            }
            if (!empty($userinfo->activation)) {
                $user->usergroup = $params->get('activationgroup');
            } elseif (!empty($userinfo->block)) {
                $user->usergroup = 7;
            } else {
                $user->usergroup = $usergroup;
            }
            //now append the new user data
            if (!$db->insertObject('#__users', $user, 'uid')) {
                //return the error
                $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
            } else {
                //return the good news
                $status['debug'][] = JText::_('USER_CREATION');
                $status['userinfo'] = $this->getUser($userinfo);
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
    function updateEmail($userinfo, &$existinguser, &$status) {
        //we need to update the email
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET email =' . $db->Quote($userinfo->email) . ' WHERE uid =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
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
        //found out what usergroup should be used
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ": " . JText::_('USERGROUP_MISSING');
        } else {
            $usergroup = $usergroups[0];
            //update the usergroup
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'UPDATE #__users SET usergroup = ' . $usergroup . ' WHERE uid  = ' . (int)$existinguser->userid;
            $db->setQuery($query);
            if (!$db->Query()) {
                $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
            } else {
                $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
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
    function inactivateUser($userinfo, &$existinguser, &$status) {
        //found out what usergroup should be used
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup = $params->get('activationgroup');
        //update the usergroup
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET usergroup = ' . (int)$usergroup . ' WHERE uid  = ' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->Query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }
}
