<?php namespace JFusion\Plugins\mybb;
use Exception;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;

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
class User extends Plugin_User
{
    /**
     * @param Userinfo $userinfo
     *
*@return null|Userinfo
     */
    function getUser(Userinfo $userinfo) {
	    $user = null;
	    try {
		    //get the identifier
		    list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.username', 'a.email', 'a.uid');
		    // Get user info from database
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('a.uid as userid, a.username, a.usergroup as group_id, a.username as name, a.email, a.password, a.salt as password_salt, a.usergroup as activation, b.isbannedgroup as block')
			    ->from('#__users as a')
			    ->join('LEFT OUTER', '#__usergroups as b ON a.usergroup = b.gid')
			    ->where($identifier_type . ' = ' . $db->quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();
		    if ($result) {
			    //Check to see if user needs to be activated
			    if ($result->group_id == 5) {
				    jimport('joomla.user.helper');
				    $result->activation = Framework::genRandomPassword(32);
			    } else {
				    $result->activation = null;
			    }
			    $result->groups = array($result->group_id);

			    $user = new Userinfo($this->getJname());
			    $user->bind($result);
		    }
	    } catch (Exception $e) {
		    $user = null;
	    }
        return $user;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $options) {
        $status = array(LogLevel::ERROR => array(), LogLevel::DEBUG => array());

        $cookiedomain = $this->params->get('cookie_domain');
        $cookiepath = $this->params->get('cookie_path', '/');
        //Set cookie values
        $expires = -3600;
        if (!$cookiepath) {
            $cookiepath = '/';
        }
        // Clearing Forum Cookies
        $remove_cookies = array('mybb', 'mybbuser', 'mybbadmin');
        if ($cookiedomain) {
            foreach ($remove_cookies as $name) {
                $status[LogLevel::DEBUG][] = $this->addCookie($name,  '', $expires, $cookiepath, '', $cookiedomain);
            }
        } else {
            foreach ($remove_cookies as $name) {
                $status[LogLevel::DEBUG][] = $this->addCookie($name,  '', $expires, $cookiepath, '');
            }
        }
        return $status;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     * @return array
     */
    function createSession(Userinfo $userinfo, $options) {
        $status = array(LogLevel::ERROR => array(), LogLevel::DEBUG => array());
        //do not create sessions for blocked users
	    try {
	        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
	            $status[LogLevel::ERROR][] = Text::_('FUSION_BLOCKED_USER');
	        } else {
	            //get cookiedomain, cookiepath (theIggs solution)
	            $cookiedomain = $this->params->get('cookie_domain', '');
	            $cookiepath = $this->params->get('cookie_path', '/');
	            //get myBB uid, loginkey

		        $db = Factory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->select('uid, loginkey')
			        ->from('#__users')
			        ->where('username = ' . $db->quote($userinfo->username));

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
		        $status[LogLevel::DEBUG][] = $this->addCookie($name, $value, $expires, $cookiepath, $cookiedomain, false, $httponly , true);
	        }
	    } catch (Exception $e) {
		    $status[LogLevel::ERROR][] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo &$existinguser
     *
     * @return void
     */
    function blockUser(Userinfo $userinfo, &$existinguser) {
	    $db = Factory::getDatabase($this->getJname());
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

	    $db->insertObject('#__banned', $user, 'uid');

	    //change its usergroup
	    $query = $db->getQuery(true)
		    ->update('#__users')
		    ->set('usergroup = 7')
		    ->where('uid = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
    function unblockUser(Userinfo $userinfo, Userinfo &$existinguser) {
	    $db = Factory::getDatabase($this->getJname());
	    //found out what the old usergroup was

	    $query = $db->getQuery(true)
		    ->select('oldgroup')
		    ->from('#__banned')
		    ->where('uid = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $oldgroup = $db->loadResult();
	    //delete the ban
	    $query = $db->getQuery(true)
		    ->delete('#__banned')
		    ->where('uid = ' .  (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    //check the oldgroup
	    if (empty($oldgroup)) {
		    $usergroups = $this->getCorrectUserGroups($userinfo);
		    if (!empty($usergroups)) {
			    $oldgroup = $usergroups[0];
		    }
	    }
	    if (empty($oldgroup)) {
		    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
	    } else {
		    //restore the usergroup
		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('usergroup = ' . (int)$oldgroup)
			    ->where('uid = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
	    }
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser) {
	    jimport('joomla.user.helper');
	    $existinguser->password_salt = Framework::genRandomPassword(6);
	    $existinguser->password = md5(md5($existinguser->password_salt) . md5($userinfo->password_clear));
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__users')
		    ->set('password = ' . $db->quote($existinguser->password))
		    ->set('salt = ' . $db->quote($existinguser->password_salt))
		    ->where('uid = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
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
	    $usergroups = $this->getCorrectUserGroups($userinfo);
	    if (empty($usergroups)) {
		    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
	    } else {
		    $usergroup = $usergroups[0];
		    //update the usergroup
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('usergroup = ' . $usergroup)
			    ->where('uid = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $this->debugger->addDebug(Text::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . $usergroup);
	    }
    }

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 *
	 * @return Userinfo
	 */
    function createUser(Userinfo $userinfo) {
	    //found out what usergroup should be used
	    $db = Factory::getDatabase($this->getJname());
	    $usergroups = $this->getCorrectUserGroups($userinfo);
	    if (empty($usergroups)) {
		    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
	    } else {
		    $usergroup = $usergroups[0];
		    //prepare the variables
		    $user = new stdClass;
		    $user->uid = null;
		    $user->username = $userinfo->username;
		    $user->email = $userinfo->email;
		    jimport('joomla.user.helper');
		    if (isset($userinfo->password_clear)) {
			    //we can update the password
			    $user->salt = Framework::genRandomPassword(6);
			    $user->password = md5(md5($user->salt) . md5($userinfo->password_clear));
			    $user->loginkey = Framework::genRandomPassword(50);
		    } else {
			    $user->password = $userinfo->password;
			    if (!isset($userinfo->password_salt)) {
				    $user->salt = Framework::genRandomPassword(6);
			    } else {
				    $user->salt = $userinfo->password_salt;
			    }
			    $user->loginkey = Framework::genRandomPassword(50);
		    }
		    if (!empty($userinfo->activation)) {
			    $user->usergroup = $this->params->get('activationgroup');
		    } elseif (!empty($userinfo->block)) {
			    $user->usergroup = 7;
		    } else {
			    $user->usergroup = $usergroup;
		    }
		    //now append the new user data
		    $db->insertObject('#__users', $user, 'uid');

		    //return the good news
		    return $this->getUser($userinfo);
	    }
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser) {
	    //we need to update the email
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__users')
		    ->set('email = ' . $db->quote($userinfo->email))
		    ->where('uid = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
    function activateUser(Userinfo $userinfo, Userinfo &$existinguser) {
	    //found out what usergroup should be used
	    $usergroups = $this->getCorrectUserGroups($userinfo);
	    if (empty($usergroups)) {
		    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
	    } else {
		    $usergroup = $usergroups[0];
		    //update the usergroup
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('usergroup = ' . $usergroup)
			    ->where('uid = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	    }
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function inactivateUser(Userinfo $userinfo, Userinfo &$existinguser) {
	    //found out what usergroup should be used
	    $usergroup = $this->params->get('activationgroup');
	    //update the usergroup
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__users')
		    ->set('usergroup = ' . (int)$usergroup)
		    ->where('uid = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $status[LogLevel::DEBUG][] = Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
    }
}
