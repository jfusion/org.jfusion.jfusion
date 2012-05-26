<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractuser.php';

/**
 * JFusion plugin class for Gallery2
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_gallery2 extends JFusionUser {
    function &getUser($userinfo) {
        // get the username
        if (is_object($userinfo)) {
            $username = $userinfo->username;
        } else {
            $username = $userinfo;
        }
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),false);
        list($ret, $g2_user) = GalleryCoreApi::fetchUserByUserName($username);
        if ($ret) {
            return false;
        } else {
            return $this->_getUser($g2_user);
        }
    }
    function &_getUser($g2_user) {
        $userinfo = new stdClass;
        $userinfo->userid = $g2_user->id;
        $userinfo->name = $g2_user->fullName;
        $userinfo->username = $g2_user->userName;
        $userinfo->email = $g2_user->email;
        $userinfo->password = $g2_user->hashedPassword;
        $userinfo->password_salt = substr($g2_user->hashedPassword, 0, 4);
        list($ret, $groups) = GalleryCoreApi::fetchGroupsForUser($g2_user->id); //,1, 2);
        //var_dump($groups);
        if (!$ret) {
            foreach ($groups as $group_id => $group_name) {
                $userinfo->group_id = $group_id;
                $userinfo->group_name = $group_name;
            }
        }
        //TODO: Research if and in how to detect blocked Users
        $userinfo->block = 0; //(0 if allowed to access site, 1 if user access is blocked)
        //Not found jet
        $userinfo->registerdate = null;
        $userinfo->lastvisitdate = null;
        //Not activated users are saved sepperated so not to set. (PendingUser)
        $userinfo->activation = null;
        return $userinfo;
    }
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'gallery2';
    }
    function filterUsername($username) {
        //TODO: Implement User filtering
        return $username;
    }
    function destroySession($userinfo, $options) {
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),true);
        GalleryInitSecondPass();
        GalleryEmbed::logout();
        GalleryEmbed::done();
        $status = array();
        return $status;        
    }
    function createSession($userinfo, $options, $framework = true) {
        $status = array();
        $status['debug'] = array();
        $status['error'] = array();
        
        if ($framework) {
            require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
            jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),true);
        }
        global $gallery;
        //Code is taken from GalleryEmbed::checkActiveUser function
        $session = & $gallery->getSession();
        $activeUserId = $session->getUserId();
        if ($activeUserId === $userinfo->userid) {
            return $status;
        }
        /* Logout the existing user from Gallery */
        if (!empty($activeUserId)) {
            list($ret, $anonymousUserId) = GalleryCoreApi::getAnonymousUserId();
            if ($ret) {
                $status['error'][] = $ret->getErrorMessage();
                return $status;
            }
            /* Can't use getActiveUser() since it might not be set at this point */
            $activeGalleryUserId = $gallery->getActiveUserId();
            if ($anonymousUserId != $activeGalleryUserId) {
                list($ret, $activeUser) = GalleryCoreApi::loadEntitiesById($activeGalleryUserId, 'GalleryUser');
                if ($ret) {
                    $status['error'][] = $ret->getErrorMessage();
                    return $status;
                }
                $event = GalleryCoreApi::newEvent('Gallery::Logout');
                $event->setEntity($activeUser);
                list($ret, $ignored) = GalleryCoreApi::postEvent($event);
                if ($ret) {
                    $status['error'][] = $ret->getErrorMessage();
                    return $status;
                }
            }
            $ret = $session->reset();
            if ($ret) {
                $status['error'][] = $ret->getErrorMessage();
                return $status;
            }
        }
        //Code is paticulary taken from the GalleryEmbed::login function
        list($ret, $user) = GalleryCoreApi::fetchUserByUserName($userinfo->username);
        if ($ret) {
            $status['error'][] = $ret->getErrorMessage();
            return $status;
        }
        //Login the Current User
        $gallery->setActiveUser($user);
        //Save the Session
        $session = & $gallery->getSession();
        $phpVm = $gallery->getPhpVm();
        //Set Siteadmin if necessarey
        list($ret, $isSiteAdmin) = GalleryCoreApi::isUserInSiteAdminGroup($user->id);
        if ($ret) {
            $status['error'][] = $ret->getErrorMessage();
            return $status;
        }
        if ($isSiteAdmin) {
            $session->put('session.siteAdminActivityTimestamp', $phpVm->time());
        }
        $ret = $session->regenerate();
        $session = & $gallery->getSession();
        /* Touch this session - Done for WhoIsOnline*/
        $session->put('touch', time());
        $ret = $session->save();
        //Close GalleryApi
        if ($framework) {
            GalleryEmbed::done();
        }
        return $status;
    }
    function deleteUser($userinfo) {
        //setup status array to hold debug info and errors
        $status = array();
        $status['debug'] = array();
        $status['error'] = array();
        $username = $userinfo->username;
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),true);
        //Fetch GalleryUser
        list($ret, $user) = GalleryCoreApi::fetchUserByUserName($username);
        if ($ret) {
         $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;       
           return $status;
        }
        //Get Write Lock
        list($ret, $lockId) = GalleryCoreApi::acquireWriteLock($user->getId());
        if ($ret) {
         $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;       
           return $status;
        }
        //Delete User name
        $ret = $user->delete();
        if ($ret) {
         $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;       
           return $status;
        }
        //Release Lock
        $ret = GalleryCoreApi::releaseLocks($lockId);
        if ($ret) {
         $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;       
           return $status;
        }
        $status['error'] = false;
		$status['debug'][] = JText::_('USER_DELETION') . ' ' . $userinfo->username;       
        return $status;
    }
    function createUser($userinfo, &$status) {
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),false);
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup = $params->get('usergroup');
        list($ret, $g2_user) = GalleryCoreApi::newFactoryInstance('GalleryEntity', 'GalleryUser');
        if ($ret) {
            $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;
            return $status;
        }
        if (!isset($g2_user)) {
            $status['error'][] = JText::_('ERROR_CREATING_USER') . ": ".$this->getJname()." : " . $userinfo->username;
        }
        $ret = $g2_user->create($userinfo->username);
        if ($ret) {
            $status['error'][] = JText::_('ERROR_CREATING_USER') . ": ".$this->getJname()." : " . $userinfo->username;
        }
        $testcrypt = $userinfo->password;
        if (isset($userinfo->password_clear)) {
            $testcrypt = GalleryUtilities::md5Salt($userinfo->password_clear);
        }
        $g2_user->setHashedPassword($testcrypt);
        $g2_user->setUserName($userinfo->username);
        $g2_user->setEmail($userinfo->email);
        $g2_user->setFullName($userinfo->name);
        $ret = $g2_user->save();
        if ($ret) {
            $status['error'][] = JText::_('ERROR_CREATING_USER') . ": ".$this->getJname()." : " . $userinfo->username;
        }
        if (substr($usergroup, 0, 2) != 'a:') {
            $ret = GalleryCoreApi::addUserToGroup($g2_user->id, (int)$usergroup);
            if ($ret) {
                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $existinguser->group_id . ' -> ' . $usergroups[$userinfo->group_id];
                return $status;
            }
        } else {
            $usergroups = unserialize($params->get('usergroup'));
            if (isset($usergroups[$userinfo->group_id])) {
                $ret = GalleryCoreApi::addUserToGroup($g2_user->id, (int)($usergroups[$userinfo->group_id]));
                if ($ret) {
                    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $existinguser->group_id . ' -> ' . $usergroups[$userinfo->group_id];
                    return $status;
                }
            }
        }
        GalleryEmbed::done();
        $status['userinfo'] = $this->_getUser($g2_user);
        if (empty($status['error'])) {
            $status['action'] = 'created';
        }
        return $status;
    }
    function updateUsergroup($userinfo, &$existinguser, &$status) {
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),false);
        //check to see if we have a group_id in the $userinfo, if not return
        if (!isset($userinfo->group_id)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID');
            return null;
        }
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = unserialize($params->get('usergroup'));
        if (isset($usergroups[$userinfo->group_id])) {
            if ($existinguser->group_id != 2 && $existinguser->group_id != 4) {
                $ret = GalleryCoreApi::removeUserFromGroup($existinguser->userid, $existinguser->group_id);
                if ($ret) {
                    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $existinguser->group_id . ' -> ' . $usergroups[$userinfo->group_id];
                    return;
                }
            }
            $ret = GalleryCoreApi::addUserToGroup($existinguser->userid, (int)($usergroups[$userinfo->group_id]));
            if ($ret) {
                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $existinguser->group_id . ' -> ' . $usergroups[$userinfo->group_id];
                return;
            }
        } else {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST');
        }
        GalleryEmbed::done();
    }
    function updatePassword($userinfo, &$existinguser, &$status) {
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),false);
        //find out if the user already exists
        list($ret, $g2_existinguser) = GalleryCoreApi::fetchUserByUserName($userinfo->username);
        // Initialise some variables
        $params = JFusionFactory::getParams($this->getJname());
        //Set Write Lock
        list($ret, $id) = GalleryCoreApi::acquireWriteLock($g2_existinguser->getId());
        if ($ret) {
            $status['error'][] = $ret->getErrorMessage();
        }
        //Check Password
        $changed = false;
        if (isset($userinfo->password_clear) && !empty($userinfo->password_clear)) {
            $testcrypt = GalleryUtilities::md5Salt($userinfo->password_clear, $g2_existinguser->hashedPassword);
            if ($testcrypt != $g2_existinguser->hashedPassword) {
                $g2_existinguser->setHashedPassword($testcrypt);
                $changed = true;
            } else {
                $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ':' . JText::_('PASSWORD_VALID');
            }
        } else {
            $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
        }
        if ($changed) {
            $ret = $g2_existinguser->save();
            if ($ret) {
                $status['error'][] = $ret->getErrorMessage();
            }
        }
        GalleryEmbed::done();
    }
    function updateEmail($userinfo, &$existinguser, &$status) {
        require JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'gallery2.php';
        jFusion_g2BridgeCore::loadGallery2Api($this->getJname(),false);
        //find out if the user already exists
        list($ret, $g2_existinguser) = GalleryCoreApi::fetchUserByUserName($userinfo->username);
        // Initialise some variables
        $params = JFusionFactory::getParams($this->getJname());
        //Set Write Lock
        list($ret, $id) = GalleryCoreApi::acquireWriteLock($g2_existinguser->getId());
        if ($ret) {
            $status['error'][] = $ret->getErrorMessage();
        } else {
            //Set new Email
            $g2_existinguser->setEmail($userinfo->email);
            //Save to DB
            $ret = $g2_existinguser->save();
            if ($ret) {
                $status['error'][] = $ret->getErrorMessage();
            }
        }
        GalleryEmbed::done();
    }
    function blockUser($userinfo, &$existinguser, &$status) {
    }
    function unblockUser($userinfo, &$existinguser, &$status) {
    }
    function activeUser($userinfo, &$existinguser, &$status) {
    }
    function inactiveUser($userinfo, &$existinguser, &$status) {
    }
}
