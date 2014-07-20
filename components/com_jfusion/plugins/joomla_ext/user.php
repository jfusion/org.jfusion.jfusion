<?php namespace JFusion\Plugins\joomla_ext;

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;

use Joomla\Registry\Registry;
use \RuntimeException;
use \Exception;
use \stdClass;

defined('_JEXEC') or die('Restricted access');

//require the standard joomla user functions
jimport('joomla.user.helper');
/**
 * JFusion User Class for an external Joomla database
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
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
	 * gets the userinfo from the JFusion integrated software. Definition of object:
	 *
	 * @param Userinfo $userinfo contains the object of the user
	 *
	 * @return null|Userinfo userinfo Object containing the user information
	 */
	public function getUser(Userinfo $userinfo)
	{
		$user = null;
		try {
			$db = Factory::getDatabase($this->getJname());

			list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'username', 'email', 'id');

			$query = $db->getQuery(true)
				->select('id as userid, activation, username, name, password, email, block, params')
				->from('#__users')
				->where($identifier_type . ' = ' . $db->quote($identifier));
			$db->setQuery($query);

			$result = $db->loadObject();

			if ($result) {
				$query = $db->getQuery(true)
					->select('a.group_id, b.title as name')
					->from('#__user_usergroup_map as a')
					->innerJoin('#__usergroups as b ON a.group_id = b.id')
					->where('a.user_id = ' . $db->quote($result->userid));

				$db->setQuery($query);
				$groupList = $db->loadObjectList();
				if ($groupList) {
					foreach ($groupList as $group) {
						$result->groups[] = $group->group_id;
						$result->groupnames[] = $group->name;

						if ( !isset($result->group_id) || $group->group_id > $result->group_id) {
							$result->group_id = $group->group_id;
							$result->group_name =  $group->name;
						}
					}
				} else {
					$result->groups = array();
					$result->groupnames = array();
				}

				//split up the password if it contains a salt
				//note we cannot use explode as a salt from another software may contain a colon which messes Joomla up
				$result->password_salt = null;
				if (substr($result->password, 0, 4) == '$2y$') {
					// BCrypt passwords are always 60 characters, but it is possible that salt is appended although non standard.
					$result->password = substr($result->password, 0, 60);
				} else {
					if (strpos($result->password, ':') !== false) {
						list($result->password, $result->password_salt) = explode(':', $result->password, 2);
					}
				}

				// Get the language of the user and store it as variable in the user object
				$user_params = new Registry($result->params);

				$result->language = $user_params->get('language', Factory::getLanguage()->getTag());

				//unset the activation status if not blocked
				if ($result->block == 0) {
					$result->activation = null;
				}
				//unset the block if user is inactive
				if (!empty($result->block) && !empty($result->activation)) {
					$result->block = false;
				}

				//check to see if CB is installed and activated and if so update the activation and ban accordingly
				$query = $db->getQuery(true)
					->select('enabled')
					->from('#__extensions')
					->where('name LIKE ' . $db->quote('%com_comprofiler%'));

				$db->setQuery($query);
				$cbenabled = $db->loadResult();

				if (!empty($cbenabled)) {
					$query = $db->getQuery(true)
						->select('confirmed, approved, cbactivation')
						->from('#__comprofiler')
						->where('user_id = ' . $result->userid);

					$db->setQuery($query);
					$cbresult = $db->loadObject();

					if (!empty($cbresult)) {
						if (empty($cbresult->confirmed) && !empty($cbresult->cbactivation)) {
							$result->activation = $cbresult->cbactivation;
							$result->block = false;
						} elseif (empty($cbresult->confirmed) || empty($cbresult->approved)) {
							$result->block = true;
						}
					}
				}
				$user = new Userinfo($this->getJname());
				$user->bind($result);
			}
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
		}
		return $user;
	}

	/**
	 * Function that updates username
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function updateUsername(Userinfo $userinfo, Userinfo &$existinguser)
	{
		//generate the filtered integration username
		$db = Factory::getDatabase($this->getJname());
		$username_clean = $this->filterUsername($userinfo->username);
		$this->debugger->add('debug', Text::_('USERNAME') . ': ' . $userinfo->username . ' -> ' . Text::_('FILTERED_USERNAME') . ':' . $username_clean);

		$query = $db->getQuery(true)
			->update('#__users')
			->set('username = ' . $db->quote($username_clean))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', Text::_('USERNAME_UPDATE') . ': ' . $username_clean);
	}

	/**
	 * @param Userinfo $userinfo
	 */
	function doCreateUser(Userinfo $userinfo)
	{
		$this->debugger->add('debug', Text::_('NO_USER_FOUND_CREATING_ONE'));
		try {
			$this->createUser($userinfo);
			$this->debugger->set('action', 'created');
		} catch (Exception $e) {
			$this->debugger->add('error', Text::_('USER_CREATION_ERROR') . $e->getMessage());
		}
	}

	/**
	 * Function that creates a new user account
	 *
	 * @param Userinfo $userinfo Object containing the new userinfo
	 *
	 * @throws RuntimeException
	 */
	public function createUser(Userinfo $userinfo)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		//get the default user group and determine if we are using simple or advanced
		//check to make sure that if using the advanced group mode, $userinfo->group_id exists
		if (empty($usergroups)) {
			throw new RuntimeException(Text::_('ERROR_CREATE_USER') . ' ' . Text::_('USERGROUP_MISSING'));
		} else {
			//load the database
			$db = Factory::getDatabase($this->getJname());
			//joomla does not allow duplicate email addresses, check to see if the email is unique
			$query = $db->getQuery(true)
				->select('id as userid, username, email')
				->from('#__users')
				->where('email = ' . $db->quote($userinfo->email));

			$db->setQuery($query);
			$existinguser = $db->loadObject();
			if (empty($existinguser)) {
				//apply username filtering
				$username_clean = $this->filterUsername($userinfo->username);
				//now we need to make sure the username is unique in Joomla

				$query = $db->getQuery(true)
					->select('id')
					->from('#__users')
					->where('username=' . $db->quote($username_clean));

				$db->setQuery($query);
				while ($db->loadResult()) {
					$username_clean.= '_';
					$query = $db->getQuery(true)
						->select('id')
						->from('#__users')
						->where('username=' . $db->quote($username_clean));

					$db->setQuery($query);
				}
				$this->debugger->add('debug', Text::_('USERNAME') . ': ' . $userinfo->username . ' ' . Text::_('FILTERED_USERNAME') . ': ' . $username_clean);

				//create a Joomla password hash if password_clear is available
				if (!empty($userinfo->password_clear)) {
					/**
					 * @ignore
					 * @var $auth JFusionAuth_joomla_ext
					 */
					$auth = Factory::getAuth($this->getJname());
					$password = $auth->hashPassword($userinfo);
				}  else if (isset($userinfo->password_salt)) {
					$password = $userinfo->password . ':' . $userinfo->password_salt;
				} else {
					$password = $userinfo->password;
				}

				//find out what usergroup the new user should have
				//the $userinfo object was probably reconstructed in the user plugin and autoregister = 1
				$isadmin = false;
				if (isset($usergroups[0])) {
					$isadmin = (in_array (7 , $usergroups, true) || in_array (8 , $usergroups, true)) ? true : false;
				} else {
					$usergroups = array(2);
				}

				$user = new stdClass();
				$user->id = null;
				$user->name = $userinfo->name;
				$user->username = $username_clean;
				$user->password = $password;
				$user->email = $userinfo->email;
				$user->block = $userinfo->block;
				$user->activation = $userinfo->activation;
				$user->sendEmail = 0;
				$user->registerDate = date('Y-m-d H:i:s', time());

				$db->insertObject('#__users', $user, 'id');

				foreach ($usergroups as $group) {
					$newgroup = new stdClass;
					$newgroup->group_id = (int)$group;
					$newgroup->user_id = (int)$user->user_id;
					$newgroup->group_leader = 0;
					$newgroup->user_pending = 0;

					$db->insertObject('#__user_usergroup_map', $newgroup);
				}
				//check to see if the user exists now
				$joomla_user = $this->getUser($userinfo);
				if ($joomla_user) {
					//report back success
					$this->debugger->set('userinfo', $joomla_user);
					$this->debugger->add('debug', Text::_('USER_CREATION'));
				} else {
					throw new RuntimeException(Text::_('COULD_NOT_CREATE_USER'));
				}
			} else {
				//Joomla does not allow duplicate emails report error
				$this->debugger->add('debug', Text::_('USERNAME') . ' ' . Text::_('CONFLICT') . ': ' . $existinguser->username . ' -> ' . $userinfo->username);
				$this->debugger->set('userinfo', $existinguser);
				throw new RuntimeException(Text::_('EMAIL_CONFLICT') . '. UserID: ' . $existinguser->userid . ' JFusionPlugin: ' . $this->getJname());
			}
		}
	}

    /**
     * @param Userinfo $userinfo
     * @return array
     */
    function deleteUser(Userinfo $userinfo) {
	    //get the database ready
	    $db = Factory::getDatabase($this->getJname());
	    //setup status array to hold debug info and errors
	    $status = array('error' => array(), 'debug' => array());
	    $userid = $userinfo->userid;

	    $query = $db->getQuery(true)
		    ->delete('#__users')
		    ->where('id = ' . (int)$userid);

	    $db->setQuery($query);
	    $db->execute();

	    $query = $db->getQuery(true)
		    ->delete('#__user_profiles')
		    ->where('user_id = ' . (int)$userid);

	    $db->setQuery($query);
	    $db->execute();

	    $query = $db->getQuery(true)
		    ->delete('#__user_usergroup_map')
		    ->where('user_id = ' . (int)$userid);

	    $db->setQuery($query);
	    $db->execute();

	    $status['debug'][] = Text::_('USER_DELETION') . ': ' . $userinfo->username;
        return $status;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $options) {
        $status = $this->curlLogout($userinfo, $options, $this->params->get('logout_type'));
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
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = Text::_('FUSION_BLOCKED_USER');
        } else {
            $status = $this->curlLogin($userinfo, $options, $this->params->get('brute_force'));
        }
        return $status;
    }

	/**
	 * Function that updates usergroup
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		//make sure the group exists
		if (empty($usergroups)) {
			throw new RuntimeException(Text::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
		} else {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->delete('#__user_usergroup_map')
				->where('user_id = ' . $db->quote($existinguser->userid));
			$db->setQuery($query);

			$db->execute();

			foreach ($usergroups as $group) {
				$temp = new stdClass;
				$temp->user_id = $existinguser->userid;
				$temp->group_id = $group;

				$db->insertObject('#__user_usergroup_map', $temp);
			}
			$this->debugger->add('debug', Text::_('GROUP_UPDATE') . ': ' . implode(',', $existinguser->groups) . ' -> ' .implode(',', $usergroups));
		}
	}

	/**
	 * Function that updates the user email
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function updateEmail(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('email = ' . $db->quote($userinfo->email))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
	}

	/**
	 * Function that updates the user password
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @throws Exception
	 * @return string updates are passed on into the $status array
	 */
	public function updatePassword(Userinfo $userinfo, Userinfo &$existinguser)
	{
		if (strlen($userinfo->password_clear) > 55) {
			throw new Exception(Text::_('JLIB_USER_ERROR_PASSWORD_TOO_LONG'));
		} else {
			/**
			 * @ignore
			 * @var $auth JFusionAuth_joomla_ext
			 */
			$auth = Factory::getAuth($this->getJname());
			$password = $auth->hashPassword($userinfo);

			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__users')
				->set('password = ' . $db->quote($password))
				->where('id = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$this->debugger->add('debug', Text::_('PASSWORD_UPDATE')  . ': ' . substr($password, 0, 6) . '********');
		}
	}

	/**
	 * Function that blocks user
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function blockUser(Userinfo $userinfo, Userinfo &$existinguser)
	{
		//block the user
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('block = 1')
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
	}

	/**
	 * Function that unblocks user
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function unblockUser(Userinfo $userinfo, Userinfo &$existinguser)
	{
		//unblock the user
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('block = 0')
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
	}

	/**
	 * Function that activates user
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function activateUser(Userinfo $userinfo, Userinfo &$existinguser)
	{
		//unblock the user
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('block = 0')
			->set('activation = ' . $db->quote(''))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	}

	/**
	 * Function that inactivates user
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function inactivateUser(Userinfo $userinfo, Userinfo &$existinguser)
	{
		//unblock the user
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('block = 1')
			->set('activation = ' . $db->quote($userinfo->activation))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	}

	/**
	 * filters the username to remove invalid characters
	 *
	 * @param string $username contains username
	 *
	 * @return string filtered username
	 */
	public function filterUsername($username)
	{
		//check to see if additional username filtering need to be applied
		$added_filter = $this->params->get('username_filter');
		if ($added_filter && $added_filter != $this->getJname()) {
			$JFusionPlugin = Factory::getUser($added_filter);
			$filteredUsername = $JFusionPlugin->filterUsername($username);
		}
		//make sure the filtered username isn't empty
		$username = (!empty($filteredUsername)) ? $filteredUsername : $username;
		//define which characters which Joomla forbids in usernames
		$trans = array('&#60;' => '_', '&lt;' => '_', '&#62;' => '_', '&gt;' => '_', '&#34;' => '_', '&quot;' => '_', '&#39;' => '_', '&#37;' => '_', '&#59;' => '_', '&#40;' => '_', '&#41;' => '_', '&amp;' => '_', '&#38;' => '_', '<' => '_', '>' => '_', '"' => '_', '\'' => '_', '%' => '_', ';' => '_', '(' => '_', ')' => '_', '&' => '_');
		//remove forbidden characters for the username
		$username = strtr($username, $trans);
		//make sure the username is at least 2 characters long
		while (strlen($username) < 2) {
			$username.= '_';
		}
		return $username;
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @return boolean return true if changed
	 */
	function doUserLanguage(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$changed = false;
		//Update the user language in the one existing from an other plugin
		if (!empty($userinfo->language) && !empty($existinguser->language) && $userinfo->language != $existinguser->language) {
			try {
				$this->updateUserLanguage($userinfo, $existinguser);
				$existinguser->language = $userinfo->language;
				$this->debugger->add('debug', Text::_('LANGUAGE_UPDATED') . ' : ' . $existinguser->language . ' -> ' . $userinfo->language);

				$existinguser->language = $userinfo->language;
				$changed = true;
			} catch (Exception $e) {
				$this->debugger->add('error', Text::_('LANGUAGE_UPDATED_ERROR') . ' ' . $e->getMessage());
			}
		} else {
			//return a debug to inform we skipped this step
			$this->debugger->add('debug', Text::_('LANGUAGE_NOT_UPDATED'));
		}
		return $changed;
	}

	/**
	 * Update the language user in his account when he logs in Joomla or
	 * when the language is changed in the frontend
	 *
	 * @see JFusionJoomlaUser::updateUser
	 * @see JFusionJoomlaPublic::setLanguageFrontEnd
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 */
	public function updateUserLanguage(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$db = Factory::getDatabase($this->getJname());
		$params = new Registry($existinguser->params);
		$params->set('language', $userinfo->language);

		$query = $db->getQuery(true)
			->update('#__users')
			->set('params = ' . $db->quote($params->toString()))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);

		$db->execute();
		$this->debugger->add('debug', Text::_('LANGUAGE_UPDATE') . ' ' . $existinguser->language);
	}
}
