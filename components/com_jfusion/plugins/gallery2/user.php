<?php namespace JFusion\Plugins\gallery2;

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

use Exception;
use GalleryCoreApi;
use GalleryEmbed;
use GalleryStatus;
use GalleryUser;
use GalleryUtilities;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;

use \RuntimeException;
use \stdClass;

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
class User extends Plugin_User
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    /**
     * @param Userinfo $userinfo
     *
     * @return null|Userinfo
     */
    function getUser(Userinfo $userinfo) {
	    $this->helper->loadGallery2Api(false);
        list($ret, $g2_user) = GalleryCoreApi::fetchUserByUserName($userinfo->username);
        if ($ret) {
            return null;
        } else {
            return $this->_getUser($g2_user);
        }
    }

    /**
     * @param $userinfo
     *
     * @return Userinfo
     */
    function &_getUser($userinfo) {
	    $result = new stdClass;
	    $result->userid = $userinfo->id;
	    $result->name = $userinfo->fullName;
	    $result->username = $userinfo->userName;
	    $result->email = $userinfo->email;
	    $result->password = $userinfo->hashedPassword;
	    $result->password_salt = substr($userinfo->hashedPassword, 0, 4);
        list($ret, $groups) = GalleryCoreApi::fetchGroupsForUser($userinfo->id); //,1, 2);
	    $result->groups = array();
	    $result->groupnames = array();
        if (!$ret) {
            foreach ($groups as $id => $name) {
	            $result->groups[] = $id;
	            $result->group_id = $id;

	            $result->groupnames[] = $name;
	            $result->group_name = $name;
            }
        }
        /**
         * @TODO Research if and in how to detect blocked Users
         */
	    $result->block = false; //(0 if allowed to access site, 1 if user access is blocked)
        //Not found jet
	    $result->registerDate = null;
	    $result->lastvisitDate = null;
        //Not activated users are saved separated so not to set. (PendingUser)
	    $result->activation = null;

	    $user = new Userinfo($this->getJname());
	    $user->bind($result);
        return $user;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $options) {
	    $this->helper->loadGallery2Api(false);
	    GalleryInitSecondPass();
        GalleryEmbed::logout();
	    GalleryEmbed::done();
        $status = array('error' => array(), 'debug' => array());
        return $status;        
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function createSession(Userinfo $userinfo, $options) {
        $status = array('error' => array(), 'debug' => array());

        if (!isset($options['noframework'])) {
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
                            list($ret,) = GalleryCoreApi::postEvent($event);
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
                    if (!isset($options['noframework'])) {
                        GalleryEmbed::done();
                    }
                }
            }
        }
        return $status;
    }

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 * @return boolean returns true on success and false on error
	 */
    function deleteUser(Userinfo $userinfo) {
	    $deleted = false;
	    /**
	     * @ignore
	     * @var $user GalleryUser
	     * @var $ret GalleryStatus
	     */
	    $this->helper->loadGallery2Api(false);
        //Fetch GalleryUser
        list($ret, $user) = GalleryCoreApi::fetchUserByUserName($userinfo->username);
        if ($ret) {
	        throw new RuntimeException($userinfo->username . ': ' . $ret->getErrorMessage());
        } else {
            //Get Write Lock
            list($ret, $lockId) = GalleryCoreApi::acquireWriteLock($user->getId());
            if ($ret) {
	            throw new RuntimeException($userinfo->username . ': ' . $ret->getErrorMessage());
            } else {
                //Delete User name
                $ret = $user->delete();
                if ($ret) {
	                throw new RuntimeException($userinfo->username . ': ' . $ret->getErrorMessage());
                } else {
                    //Release Lock
                    $ret = GalleryCoreApi::releaseLocks($lockId);
                    if ($ret) {
	                    throw new RuntimeException($userinfo->username . ': ' . $ret->getErrorMessage());
                    } else {
	                    $deleted = true;
                    }
                }
            }
        }
        return $deleted;
    }

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \Exception
	 *
	 * @return Userinfo|null
	 */
    function createUser(Userinfo $userinfo) {
	    $this->helper->loadGallery2Api(false);
	    try {
		    $usergroups = $this->getCorrectUserGroups($userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
		    } else {
			    /**
			     * @ignore
			     * @var $user GalleryUser
			     */
			    list($ret, $user) = GalleryCoreApi::newFactoryInstance('GalleryEntity', 'GalleryUser');
			    if ($ret) {
				    throw new RuntimeException($userinfo->username);
			    } else {
				    if (!isset($user)) {
					    throw new RuntimeException($userinfo->username);
				    }
				    $ret = $user->create($userinfo->username);
				    if ($ret) {
					    throw new RuntimeException($userinfo->username);
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
						    throw new RuntimeException($userinfo->username);
					    } else {
						    foreach ($usergroups as $group) {
							    $ret = GalleryCoreApi::addUserToGroup($user->id, (int)$group);
							    if ($ret) {
								    throw new RuntimeException(Text::_('GROUP_UPDATE_ERROR') . ': ' . $ret->getErrorMessage());
							    }
						    }
						    GalleryEmbed::done();
						    return $this->_getUser($user);
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    GalleryEmbed::done();
		    throw $e;
	    }
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo &$existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
	{
	    $this->helper->loadGallery2Api(false);
        $usergroups = $this->getCorrectUserGroups($userinfo);
        if (empty($usergroups)) {
	        throw new RuntimeException(Text::_('USERGROUP_MISSING'));
        } else {
            foreach($existinguser->groups as $group) {
                if (!in_array($group, $usergroups, true)) {
                    $ret = GalleryCoreApi::removeUserFromGroup($existinguser->userid, (int)$group);
                    if ($ret) {
	                    throw new RuntimeException($ret->getErrorMessage());
                    }
                }
            }
            foreach($usergroups as $group) {
                if (!in_array($group, $existinguser->groups, true)) {
                    $ret = GalleryCoreApi::addUserToGroup($existinguser->userid, (int)($group));
                    if ($ret) {
	                    throw new RuntimeException($ret->getErrorMessage());
                    }
                }
            }
	        $this->debugger->addDebug(Text::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . implode(' , ', $usergroups));
        }
        GalleryEmbed::done();
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser) {
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
	        $this->debugger->addError($ret->getErrorMessage());
        }
        //Check Password
        $changed = false;
        if (isset($userinfo->password_clear) && !empty($userinfo->password_clear)) {
            $testcrypt = GalleryUtilities::md5Salt($userinfo->password_clear, $user->hashedPassword);
            if ($testcrypt != $user->hashedPassword) {
	            $user->setHashedPassword($testcrypt);
                $changed = true;
            } else {
	            $this->debugger->addDebug(Text::_('SKIPPED_PASSWORD_UPDATE') . ': ' . Text::_('PASSWORD_VALID'));
            }
        } else {
	        $this->debugger->addDebug(Text::_('SKIPPED_PASSWORD_UPDATE') . ': ' . Text::_('PASSWORD_UNAVAILABLE'));
        }
        if ($changed) {
            $ret = $user->save();
            if ($ret) {
	            $this->debugger->addError($ret->getErrorMessage());
            }
        }
        GalleryEmbed::done();
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo &$existinguser
     *
     * @return void
     */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser) {
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
	        $this->debugger->addError($ret->getErrorMessage());
        } else {
            //Set new Email
	        $user->setEmail($userinfo->email);
            //Save to DB
            $ret = $user->save();
            if ($ret) {
	            $this->debugger->addError($ret->getErrorMessage());
            }
        }
        GalleryEmbed::done();
    }
}
