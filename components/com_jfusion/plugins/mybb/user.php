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
     * @return object
     */
    function &getUser($userinfo) {
        //get the identifier
        list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.username', 'a.email');
        // Get user info from database
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT a.uid as userid, a.username, a.usergroup, a.username as name, a.email, a.password, a.salt as password_salt, a.usergroup as activation, b.isbannedgroup as block FROM #__users as a INNER JOIN #__usergroups as b ON a.usergroup = b.gid WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);
        $db->setQuery($query);
        $result = $db->loadObject();
        if ($result) {
            //Check to see if user needs to be activated
            if ($result->usergroup == 5) {
                jimport('joomla.user.helper');
                $result->activation = JUserHelper::genRandomPassword(32);
            } else {
                $result->activation = null;
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
        return 'mybb';
    }
    function deleteUser($userinfo) {
        //TODO: create a function that deletes a user
        
    }
    function destroySession($userinfo, $options) {
        $params = JFusionFactory::getParams($this->getJname());
        $cookiedomain = $params->get('cookie_domain');
        $cookiepath = $params->get('cookie_path', '/');
        //Set cookie values
        $expires = mktime(12, 0, 0, 1, 1, 1990);
        if (!$cookiepath) {
            $cookiepath = "/";
        }
        // Clearing Forum Cookies
        $remove_cookies = array('mybb', 'mybbuser', 'mybbadmin');
        if ($cookiedomain) {
            foreach ($remove_cookies as $name) {
                @setcookie($name, '', $expires, $cookiepath, $cookiedomain);
            }
        } else {
            foreach ($remove_cookies as $name) {
                @setcookie($name, '', $expires, $cookiepath);
            }
        }
        $status['error'] = false;
        return $status;
    }
    function createSession($userinfo, $options) {
        $status = array();
        $status['error'] = array();
        $status['debug'] = array();
        //do not create sessions for blocked users
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
            return $status;
        }
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
        $cookiepath = str_replace(array("\n", "\r"), "", $cookiepath);
        $cookiedomain = str_replace(array("\n", "\r"), "", $cookiedomain);
        JFusionFunction::addCookie($name, $value, $expires, $cookiepath, $cookiedomain, false, $httponly);
        $status = array();
        $status['debug'][] = JText::_('NAME') . '=' . $name . ', ' . JText::_('VALUE') . '=' . substr($value, 0, 6) . '********, ' . JText::_('COOKIE_PATH') . '=' . $cookiepath . ', ' . JText::_('COOKIE_DOMAIN') . '=' . $cookiedomain;
        return $status;
    } 
    function filterUsername($username) {
        //TODO: no username filtering implemented yet
        return $username;
    }
    function blockUser($userinfo, &$existinguser, &$status) {
        $db = JFusionFactory::getDatabase($this->getJname());
        $user = new stdClass;
        $user->uid = $existinguser->userid;
        $user->gid = 7;
        $user->oldgroup = $existinguser->usergroup;
        $user->admin = 1;
        $user->dateline = time();
        $user->bantime = '---';
        $user->reason = 'JFusion';
        $user->lifted = 0;
        //now append the new user data
        if (!$db->insertObject('#__banned', $user, 'uid')) {
            //return the error
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
            return;
        }
        //change its usergroup
        $query = 'UPDATE #__users SET usergroup = 7 WHERE uid = ' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            //return the error
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
            return;
        }
        $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
    }
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
            return;
        }
        //check the oldgroup
        if (empty($oldgroup)) {
            $params = JFusionFactory::getParams($this->getJname());
            $oldgroup = $params->get('usergroup');
        }
        //restore the usergroup
        $query = 'UPDATE #__users SET usergroup = ' . (int)$oldgroup . ' WHERE uid = ' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            //return the error
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
            return;
        }
        $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
    }
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
    function createUser($userinfo, &$status) {
        //found out what usergroup should be used
        $db = JFusionFactory::getDatabase($this->getJname());
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup = $params->get('usergroup');
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
            $user->usergroup = 2;
        } elseif (!empty($userinfo->block)) {
            $user->usergroup = 7;
        } else {
            $user->usergroup = $usergroup;
        }
        //now append the new user data
        if (!$db->insertObject('#__users', $user, 'uid')) {
            //return the error
            $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
            return;
        } else {
            //return the good news
            $status['debug'][] = JText::_('USER_CREATION');
            $status['userinfo'] = $this->getUser($userinfo);
            return;
        }
    }
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
    function activateUser($userinfo, &$existinguser, &$status) {
        //found out what usergroup should be used
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup = $params->get('usergroup');
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
