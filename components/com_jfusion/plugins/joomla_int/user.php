<?php namespace JFusion\Plugins\joomla_int;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaInt
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
use JEventDispatcher;
use JFactory;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;


use \Exception;
use Joomla\Registry\Registry;
use JTable;
use JUser;
use \RuntimeException;
use \stdClass;

defined('_JEXEC') or die('Restricted access');

//require the standard joomla user functions
jimport('joomla.user.helper');

/**
 * JFusion User Class for the internal Joomla database
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_int
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class User extends Plugin_User
{
	private $fireUserPlugins = true;

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
			throw new RuntimeException(Text::_('USERGROUP_MISSING'));
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
				$userinfo->password_salt = Framework::genRandomPassword(32);
				if (!empty($userinfo->password_clear)) {
					/**
					 * @ignore
					 * @var $auth Auth
					 */
					$auth = Factory::getAuth($this->getJname());
					$password = $auth->hashPassword($userinfo);
				} else if (isset($userinfo->password_salt)) {
					$password = $userinfo->password . ':' . $userinfo->password_salt;
				} else {
					$password = $userinfo->password;
				}

				$instance = new JUser();
				$instance->set('name', $userinfo->name);
				$instance->set('username', $username_clean);
				$instance->set('password', $password);
				$instance->set('email', $userinfo->email);
				$instance->set('block', $userinfo->block);
				$instance->set('activation', $userinfo->activation);
				$instance->set('sendEmail', 0);
				//find out what usergroup the new user should have
				//the $userinfo object was probably reconstructed in the user plugin and autoregister = 1
				$isadmin = false;
				if (isset($usergroups[0])) {
					$isadmin = (in_array (7 , $usergroups, true) || in_array (8 , $usergroups, true)) ? true : false;
				} else {
					$usergroups = array(2);
				}

				//work around the issue where joomla will not allow the creation of an admin or super admin if the logged in user is not a super admin
				if ($isadmin) {
					$usergroups = array(2);
				}

				$instance->set('usertype', 'deprecated');
				$instance->set('groups', $usergroups);

				//store the username passed into this to prevent the user plugin from attempting to recreate users
				$instance->set('original_username', $userinfo->username);
				// save the user
				global $JFusionActive;
				$JFusionActive = 1;
				$instance->save(false);

				$createdUser = (object)$instance->getProperties();

				$result = new stdClass();
				foreach($createdUser as $key => $value) {
					if ($key == 'id') {
						$result->userid = $value;
					} else {
						$result->$key = $value;
					}
				}

				$createdUser = new Userinfo($this->getJname());
				$createdUser->bind($result);

				//update the user's group to the correct group if they are an admin
				if ($isadmin) {
					$this->fireUserPlugins = false;
					$this->updateUsergroup($userinfo, $createdUser);
					$this->fireUserPlugins = true;
				}

				//check to see if the user exists now
				$joomla_user = $this->getUser($userinfo);
				if ($joomla_user) {
					//report back success
					$this->debugger->add('debug', Text::_('USER_CREATION'));
					$this->debugger->set('userinfo', $joomla_user);
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
	 *
	 * @throws \RuntimeException
	 * @return array
	 */
    function deleteUser(Userinfo $userinfo) {
	    /**
	     * TODO need to be changed as deleting the user is not correct
	     */

        //get the database ready
        $db = Factory::getDBO();
        //setup status array to hold debug info and errors
        $status = array('error' => array(), 'debug' => array());
        $username = $userinfo->username;
        //since the jfusion_user table will be updated to the user's email if they use it as an identifier, we must check for both the username and email

	    $query = $db->getQuery(true)
		    ->select('id')
		    ->from('#__jfusion_users')
		    ->where('username = ' . $db->quote($username), 'OR')
		    ->where('LOWER(username) = ' . $db->quote(strtolower($userinfo->email)));

        $db->setQuery($query);
        $userid = $db->loadResult();
	    if ($userid) {
            //this user was created by JFusion and we need to delete them from the joomla user and jfusion lookup table
            $user = JUser::getInstance($userid);
            $user->delete();

	        $query = $db->getQuery(true)
		        ->delete('#__jfusion_users_plugin')
		        ->where('id = ' . (int)$userid);

            $db->setQuery($query);
            $db->execute();
		    $status['debug'][] = Text::_('USER_DELETION') . ': ' . $username;
        } else {
            //this user was NOT create by JFusion. Therefore we need to delete it in the Joomla user table only

	        $query = $db->getQuery(true)
		        ->select('id')
		        ->from('#__users')
		        ->where('username  = ' . $db->quote($username));

            $db->setQuery($query);
            $userid = $db->loadResult();
            if ($userid) {
                //just in case
	            $query = $db->getQuery(true)
		            ->delete('#__jfusion_users_plugin')
		            ->where('id = ' . (int)$userid);

	            $db->setQuery($query);
                $db->execute();
                //delete it from the Joomla usertable
                $user = JUser::getInstance($userid);
                $user->delete();
	            $status['debug'][] = Text::_('USER_DELETION') . ': ' . $username;
            } else {
                //could not find user and return an error
	            throw new RuntimeException($username);
            }
        }
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
	        jimport('joomla.user.helper');
	        $instance = JUser::getInstance();

	        // If _getUser returned an error, then pass it back.
	        if (!$instance->load($userinfo->userid)) {
		        $status['error'][] = Text::_('FUSION_ERROR_LOADING_USER');
	        } else {
		        // If the user is blocked, redirect with an error
		        if ($instance->get('block') == 1) {
			        $status['error'][] = Text::_('JERROR_NOLOGIN_BLOCKED');
		        } else {
			        // Authorise the user based on the group information
			        if (!isset($options['group'])) {
				        $options['group'] = 'USERS';
			        }

			        if (!isset($options['action'])) {
				        $options['action'] = 'core.login.site';
			        }

			        // Check the user can login.
			        $result	= $instance->authorise($options['action']);
			        if (!$result) {
				        $status['error'][] = Text::_('JERROR_LOGIN_DENIED');
			        } else {
				        // Mark the user as logged in
				        $instance->set('guest', 0);

				        // Register the needed session variables
				        $session = JFactory::getSession();
				        $session->set('user', $instance);

				        // Update the user related fields for the Joomla sessions table.
				        try {
					        $db = Factory::getDBO();

					        $query = $db->getQuery(true)
						        ->update('#__session')
						        ->set('guest = ' . $db->quote($instance->get('guest')))
						        ->set('username = ' . $db->quote($instance->get('username')))
						        ->set('userid = ' . $db->quote($instance->get('id')))
						        ->where('session_id = ' . $db->quote($session->getId()));

					        $db->setQuery($query);
					        $db->execute();

					        // Hit the user last visit field
					        if ($instance->setLastVisit()) {
						        $status['debug'][] = 'Joomla session created';
					        } else {
						        $status['error'][] = 'Error Joomla session created';
					        }
				        } catch (Exception $e) {
					        $status['error'][] = $e->getMessage();
				        }
			        }
		        }
	        }
        }
        return $status;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $options) {
	    if (!isset($options['clientid'])) {
		    $mainframe = Factory::getApplication();
		    if ($mainframe->isAdmin()) {
		        $options['clientid'] = array(1);
		    } else {
		        $options['clientid'] = array(0);
		    }
		} elseif (!is_array($options['clientid'])) {
		    //J1.6+ does not pass clientid as an array so let's fix that
		    $options['clientid'] = array($options['clientid']);
		}

	    if ($userinfo->userid) {
		    $my = \JFactory::getUser();
		    if ($my->id == $userinfo->userid) {
			    // Hit the user last visit field
			    $my->setLastVisit();
			    // Destroy the php session for this user
			    JFactory::getSession()->destroy();
		    }
		    //destroy the Joomla session but do so directly based on what $options is
		    $table = JTable::getInstance('session');
		    $table->destroy($userinfo->userid, $options['clientid']);
	    }
        return array();
    }

	/**
	 * Function that updates usergroup
	 *
	 * @param Userinfo $userinfo          Object containing the new userinfo
	 * @param Userinfo &$existinguser     Object containing the old userinfo
	 *
	 * @throws RuntimeException
	 */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		//make sure the group exists
		if (empty($usergroups)) {
			throw new RuntimeException(Text::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
		} else {
			$db = Factory::getDatabase($this->getJname());
			$dispatcher = JEventDispatcher::getInstance();

			jimport('joomla.user.helper');

			if ($this->fireUserPlugins) {
				// Get the old user
				$old = new JUser($existinguser->userid);
				//Fire the onBeforeStoreUser event.
				\JPluginHelper::importPlugin('user');
				$dispatcher->trigger('onBeforeStoreUser', array($old->getProperties(), false));
			}

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
			if ($this->fireUserPlugins) {
				//Fire the onAfterStoreUser event
				$updated = new JUser($existinguser->userid);
				$dispatcher->trigger('onAfterStoreUser', array($updated->getProperties(), false, true, ''));
			}
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
			 * @var $auth Auth
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
