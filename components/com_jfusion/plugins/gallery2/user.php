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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractuser.php';

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
class JFusionUser_gallery2 extends JFusionUser
{
	/**
	 * @var $helper JFusionHelper_gallery2
	 */
	var $helper;

    /**
     * @param object $userinfo
     *
     * @return null|object
     */
    function getUser($userinfo) {
        // get the username
        if (is_object($userinfo)) {
            $username = $userinfo->username;
        } else {
            $username = $userinfo;
        }

	    $this->helper->loadGallery2Api(false);
        list($ret, $g2_user) = GalleryCoreApi::fetchUserByUserName($username);
        if ($ret) {
            return null;
        } else {
            return $this->_getUser($g2_user);
        }
    }

    /**
     * @param $g2_user
     *
     * @return object
     */
    function &_getUser($g2_user) {
        $userinfo = new stdClass;
        $userinfo->userid = $g2_user->id;
        $userinfo->name = $g2_user->fullName;
        $userinfo->username = $g2_user->userName;
        $userinfo->email = $g2_user->email;
        $userinfo->password = $g2_user->hashedPassword;
        $userinfo->password_salt = substr($g2_user->hashedPassword, 0, 4);
        list($ret, $groups) = GalleryCoreApi::fetchGroupsForUser($g2_user->id); //,1, 2);
        $userinfo->groups = array();
        $userinfo->groupnames = array();
        if (!$ret) {
            foreach ($groups as $id => $name) {
                $userinfo->groups[] = $id;
                $userinfo->group_id = $id;

                $userinfo->groupnames[] = $name;
                $userinfo->group_name = $name;
            }
        }
        /**
         * @TODO Research if and in how to detect blocked Users
         */
        $userinfo->block = 0; //(0 if allowed to access site, 1 if user access is blocked)
        //Not found jet
        $userinfo->registerdate = null;
        $userinfo->lastvisitdate = null;
        //Not activated users are saved separated so not to set. (PendingUser)
        $userinfo->activation = null;
        return $userinfo;
    }
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'gallery2';
    }

    /**
     * @param string $username
     *
     * @return string
     */
    function filterUsername($username) {
        /**
         * @TODO Implement User filtering
         */
        return $username;
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options) {
	    $this->helper->loadGallery2Api(false);
        GalleryInitSecondPass();
        GalleryEmbed::logout();
        GalleryEmbed::done();
        $status = array('error' => array(),'debug' => array());
        return $status;        
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @param bool $framework
     *
     * @return array
     */
    function createSession($userinfo, $options, $framework = true) {
        $status = array('error' => array(),'debug' => array());
        
        if ($framework) {
	        $this->helper->loadGallery2Api(false);
        }
        global $gallery;
        //Code is taken from GalleryEmbed::checkActiveUser function
        $session = & $gallery->getSession();
        $activeUserId = $session->getUserId();
        if ($activeUserId !== $userinfo->userid) {
            /* Logout the existing user from Gallery */
	        /**
	         * @ignore
	         * @var $ret GalleryStatus
	         */
            if (!empty($activeUserId)) {
                list($ret, $anonymousUserId) = GalleryCoreApi::getAnonymousUserId();
                if ($ret) {
                    $status['error'][] = $ret->getErrorMessage();
                    return $status;
                } else {
                    /* Can't use getActiveUser() since it might not be set at this point */
                    $activeGalleryUserId = $gallery->getActiveUserId();
                    if ($anonymousUserId != $activeGalleryUserId) {
                        list($ret, $activeUser) = GalleryCoreApi::loadEntitiesById($activeGalleryUserId, 'GalleryUser');
                        if ($ret) {
                            $status['error'][] = $ret->getErrorMessage();
                            return $status;
                        } else {
                            $event = GalleryCoreApi::newEvent('Gallery::Logout');
                            $event->setEntity($activeUser);
                            list($ret, $ignored) = GalleryCoreApi::postEvent($event);
                            if ($ret) {
                                $status['error'][] = $ret->getErrorMessage();
                                return $status;
                            }
                        }
                    }
                    $ret = $session->reset();
                    if ($ret) {
                        $status['error'][] = $ret->getErrorMessage();
                        return $status;
                    }
                }
            }
            //Code is particularly taken from the GalleryEmbed::login function
            list($ret, $user) = GalleryCoreApi::fetchUserByUserName($userinfo->username);
            if ($ret) {
                $status['error'][] = $ret->getErrorMessage();
            } else {
                //Login the Current User
                $gallery->setActiveUser($user);
                //Save the Session
                $session = & $gallery->getSession();
                $phpVm = $gallery->getPhpVm();
                //Set Site admin if necessary
                list($ret, $isSiteAdmin) = GalleryCoreApi::isUserInSiteAdminGroup($user->id);
                if ($ret) {
                    $status['error'][] = $ret->getErrorMessage();
                } else {
                    if ($isSiteAdmin) {
                        $session->put('session.siteAdminActivityTimestamp', $phpVm->time());
                    }
                    $session->regenerate();
                    $session = & $gallery->getSession();
                    /* Touch this session - Done for WhoIsOnline*/
                    $session->put('touch', time());
                    $session->save();
                    //Close GalleryApi
                    if ($framework) {
                        GalleryEmbed::done();
                    }
                }
            }
        }
        return $status;
    }

    /**
     * @param object $userinfo
     *
     * @return array
     */
    function deleteUser($userinfo) {
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());
        $username = $userinfo->username;
	    /**
	     * @ignore
	     * @var $user GalleryUser
	     * @var $ret GalleryStatus
	     */
	    $this->helper->loadGallery2Api(false);
        //Fetch GalleryUser
        list($ret, $user) = GalleryCoreApi::fetchUserByUserName($username);
        if ($ret) {
            $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;
        } else {
            //Get Write Lock
            list($ret, $lockId) = GalleryCoreApi::acquireWriteLock($user->getId());
            if ($ret) {
                $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;
            } else {
                //Delete User name
                $ret = $user->delete();
                if ($ret) {
                    $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;
                } else {
                    //Release Lock
                    $ret = GalleryCoreApi::releaseLocks($lockId);
                    if ($ret) {
                        $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $userinfo->username;
                    } else {
                        $status['debug'][] = JText::_('USER_DELETION') . ' ' . $userinfo->username;
                    }
                }
            }
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array &$status
     *
     * @return void
     */
    function createUser($userinfo, &$status) {
	    $this->helper->loadGallery2Api(false);
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('ERROR_CREATE_USER') . ": " . JText::_('USERGROUP_MISSING');
        } else {
	        /**
	         * @ignore
	         * @var $user GalleryUser
	         */
	        list($ret, $user) = GalleryCoreApi::newFactoryInstance('GalleryEntity', 'GalleryUser');
            if ($ret) {
                $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . $userinfo->username;
            } else {
                if (!isset($user)) {
                    $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . $this->getJname(). ' : ' . $userinfo->username;
                }
                $ret = $user->create($userinfo->username);
                if ($ret) {
                    $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . $this->getJname(). ' : ' . $userinfo->username;
                } else {
                    $testcrypt = $userinfo->password;
                    if (isset($userinfo->password_clear)) {
                        $testcrypt = GalleryUtilities::md5Salt($userinfo->password_clear);
                    }
	                $user->setHashedPassword($testcrypt);
	                $user->setUserName($userinfo->username);
	                $user->setEmail($userinfo->email);
	                $user->setFullName($userinfo->name);
                    $ret = $user->save();
                    if ($ret) {
                        $status['error'][] = JText::_('ERROR_CREATE_USER') . ' '.$this->getJname().' : ' . $userinfo->username;
                    } else {
                        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
                        foreach ($usergroups as $group) {
                            $ret = GalleryCoreApi::addUserToGroup($user->id, (int)$group);
                            if ($ret) {
                                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $group;
                            }
                        }
                        $status['userinfo'] = $this->_getUser($user);
                        if (empty($status['error'])) {
                            $status['action'] = 'created';
                        }
                    }
                }
            }
        }
        GalleryEmbed::done();
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status) {
	    $this->helper->loadGallery2Api(false);
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('USERGROUP_MISSING');
        } else {
            foreach($existinguser->groups as $group) {
                if (!in_array($group, $usergroups, true)) {
                    $ret = GalleryCoreApi::removeUserFromGroup($existinguser->userid, (int)$group);
                    if ($ret) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . implode (' , ', $usergroups);
                        return;
                    }
                }
            }
            foreach($usergroups as $group) {
                if (!in_array($group, $existinguser->groups, true)) {
                    $ret = GalleryCoreApi::addUserToGroup($existinguser->userid, (int)($group));
                    if ($ret) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . implode (' , ', $usergroups);
                        return;
                    }
                }
            }
            $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . implode (' , ', $usergroups);
        }
        GalleryEmbed::done();
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
         * @var $user GalleryUser
         * @var $ret GalleryStatus
         */
	    $this->helper->loadGallery2Api(false);
        //find out if the user already exists
        list(, $user) = GalleryCoreApi::fetchUserByUserName($userinfo->username);
        // Initialise some variables
        //Set Write Lock
        list($ret,) = GalleryCoreApi::acquireWriteLock($user->getId());
        if ($ret) {
            $status['error'][] = $ret->getErrorMessage();
        }
        //Check Password
        $changed = false;
        if (isset($userinfo->password_clear) && !empty($userinfo->password_clear)) {
            $testcrypt = GalleryUtilities::md5Salt($userinfo->password_clear, $user->hashedPassword);
            if ($testcrypt != $user->hashedPassword) {
	            $user->setHashedPassword($testcrypt);
                $changed = true;
            } else {
                $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ':' . JText::_('PASSWORD_VALID');
            }
        } else {
            $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
        }
        if ($changed) {
            $ret = $user->save();
            if ($ret) {
                $status['error'][] = $ret->getErrorMessage();
            }
        }
        GalleryEmbed::done();
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status) {
	    /**
	     * @ignore
	     * @var $user GalleryUser
	     * @var $ret GalleryStatus
	     */
	    $this->helper->loadGallery2Api(false);
        //find out if the user already exists
        list(, $user) = GalleryCoreApi::fetchUserByUserName($userinfo->username);
        // Initialise some variables
        //Set Write Lock
        list($ret,) = GalleryCoreApi::acquireWriteLock($user->getId());
        if ($ret) {
            $status['error'][] = $ret->getErrorMessage();
        } else {
            //Set new Email
	        $user->setEmail($userinfo->email);
            //Save to DB
            $ret = $user->save();
            if ($ret) {
                $status['error'][] = $ret->getErrorMessage();
            }
        }
        GalleryEmbed::done();
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function blockUser($userinfo, &$existinguser, &$status) {
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function unblockUser($userinfo, &$existinguser, &$status) {
    }

    /**
     * @param $userinfo
     * @param &$existinguser
     * @param &$status
     *
     * @return void
     */
    function activeUser($userinfo, &$existinguser, &$status) {
    }

    /**
     * @param $userinfo
     * @param &$existinguser
     * @param &$status
     *
     * @return void
     */
    function inactiveUser($userinfo, &$existinguser, &$status) {
    }
}
