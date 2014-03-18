<?php

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
class JFusionUser_joomla_int extends JFusionUser {
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'joomla_int';
    }

	/**
	 * gets the userinfo from the JFusion integrated software. Definition of object:
	 *
	 * @param object $userinfo contains the object of the user
	 *
	 * @return null|object userinfo Object containing the user information
	 */
	public function getUser($userinfo)
	{
		try {
			$db = \JFusion\Factory::getDatabase($this->getJname());
			$JFusionUser = \JFusion\Factory::getUser($this->getJname());
			list($identifier_type, $identifier) = $JFusionUser->getUserIdentifier($userinfo, 'username', 'email');
			if ($identifier_type == 'username') {
				$query = $db->getQuery(true)
					->select('b.id as userid, b.activation, a.username, b.name, b.password, b.email, b.block, b.params')
					->from('#__users as b')
					->innerJoin('#__jfusion_users as a ON a.id = b.id');

				if ($this->params->get('case_insensitive')) {
					$query->where('LOWER(a.' . $identifier_type . ') = ' . $db->quote(strtolower($identifier)));
				} else {
					$query->where('a.' . $identifier_type . ' = ' . $db->quote($identifier));
				}
				//first check the JFusion user table if the identifier_type = username
				$db->setQuery($query);

				$result = $db->loadObject();
				if (!$result) {
					$query = $db->getQuery(true)
						->select('id as userid, activation, username, name, password, email, block, params')
						->from('#__users');

					if ($this->params->get('case_insensitive')) {
						$query->where('LOWER(' . $identifier_type . ') = ' . $db->quote(strtolower($identifier)));
					} else {
						$query->where($identifier_type . ' = ' . $db->quote($identifier));
					}
					//check directly in the joomla user table
					$db->setQuery($query);

					$result = $db->loadObject();
					if ($result) {
						//update the lookup table so that we don't have to do a double query next time
						$query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $result->userid . ', ' . $db->quote($identifier) . ')';
						$db->setQuery($query);
						try {
							$db->execute();
						} catch (Exception $e) {
							\JFusion\Framework::raiseWarning($e, $this->getJname());
						}
					}
				}
			} else {
				$query = $db->getQuery(true)
					->select('id as userid, activation, username, name, password, email, block, params')
					->from('#__users')
					->where($identifier_type . ' = ' . $db->quote($identifier));

				$db->setQuery($query);
				$result = $db->loadObject();
			}
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
				$user_params = new JRegistry($result->params);

				$result->language = $user_params->get('language', \JFusion\Factory::getLanguage()->getTag());

				//unset the activation status if not blocked
				if ($result->block == 0) {
					$result->activation = '';
				}
				//unset the block if user is inactive
				if (!empty($result->block) && !empty($result->activation)) {
					$result->block = 0;
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
							$result->block = 0;
						} elseif (empty($cbresult->confirmed) || empty($cbresult->approved)) {
							$result->block = 1;
						}
					}
				}
			}
		} catch (Exception $e) {
			\JFusion\Framework::raiseError($e, $this->getJname());
			$result = null;
		}
		return $result;
	}

	/**
	 * Function that updates username
	 *
	 * @param object $userinfo      Object containing the new userinfo
	 * @param object &$existinguser Object containing the old userinfo
	 * @param array  &$status       Array containing the errors and result of the function
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function updateUsername($userinfo, &$existinguser, &$status)
	{
		//generate the filtered integration username
		$db = \JFusion\Factory::getDatabase($this->getJname());
		$username_clean = $this->filterUsername($userinfo->username);
		$this->debugger->add('debug', JText::_('USERNAME') . ': ' . $userinfo->username . ' -> ' . JText::_('FILTERED_USERNAME') . ':' . $username_clean);

		$query = $db->getQuery(true)
			->update('#__users')
			->set('username = ' . $db->quote($username_clean))
			->where('id = ' . $db->quote($existinguser->userid));
		$db->setQuery($query);
		$db->execute();

		//update the lookup table
		$query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $existinguser->userid . ', ' . $db->quote($userinfo->username) . ')';
		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('USERNAME_UPDATE') . ': ' . $username_clean);
	}

	/**
	 * @param stdClass $userinfo
	 */
	function doCreateUser($userinfo)
	{
		$this->debugger->add('debug', JText::_('NO_USER_FOUND_CREATING_ONE'));
		try {
			$this->createUser($userinfo, $status);
			$this->debugger->set('action', 'created');
		} catch (Exception $e) {
			$this->debugger->add('error', JText::_('USER_CREATION_ERROR') . $e->getMessage());
		}
	}

	/**
	 * Function that creates a new user account
	 *
	 * @param object $userinfo Object containing the new userinfo
	 * @param array  &$status  Array containing the errors and result of the function
	 *
	 * @throws RuntimeException
	 */
	public function createUser($userinfo, &$status)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		//get the default user group and determine if we are using simple or advanced
		//check to make sure that if using the advanced group mode, $userinfo->group_id exists
		if (empty($usergroups)) {
			throw new RuntimeException(JText::_('USERGROUP_MISSING'));
		} else {
			//load the database
			$db = \JFusion\Factory::getDatabase($this->getJname());
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
				$this->debugger->add('debug', JText::_('USERNAME') . ': ' . $userinfo->username . ' ' . JText::_('FILTERED_USERNAME') . ': ' . $username_clean);

				//create a Joomla password hash if password_clear is available
				$userinfo->password_salt = JUserHelper::genRandomPassword(32);
				if (!empty($userinfo->password_clear)) {
					/**
					 * @ignore
					 * @var $auth JFusionAuth_joomla_int
					 */
					$auth = \JFusion\Factory::getAuth($this->getJname());
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

				$createdUser = $instance->getProperties();
				$createdUser = (object)$createdUser;
				//update the user's group to the correct group if they are an admin
				if ($isadmin) {
					$createdUser->userid = $createdUser->id;
					$this->updateUsergroup($userinfo, $createdUser, $status, false);
				}
				//create a new entry in the lookup table
				//if the credentialed username is available (from the auth plugin), store it; otherwise store the $userinfo username
				$username = (!empty($userinfo->credentialed_username)) ? $userinfo->credentialed_username : $userinfo->username;
				$query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $createdUser->id . ', ' . $db->quote($username) . ')';
				$db->setQuery($query);
				try {
					$db->execute();
				} catch (Exception $e) {
					\JFusion\Framework::raiseWarning($e, $this->getJname());
				}

				//check to see if the user exists now
				$joomla_user = $this->getUser($userinfo);
				if ($joomla_user) {
					//report back success
					$this->debugger->set('userinfo', $joomla_user);
					$this->debugger->add('debug', JText::_('USER_CREATION'));
				} else {
					throw new RuntimeException(JText::_('COULD_NOT_CREATE_USER'));
				}
			} else {
				//Joomla does not allow duplicate emails report error
				$this->debugger->add('debug', JText::_('USERNAME') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->username . ' -> ' . $userinfo->username);
				$this->debugger->set('userinfo', $existinguser);
				throw new RuntimeException(JText::_('EMAIL_CONFLICT') . '. UserID: ' . $existinguser->userid . ' JFusionPlugin: ' . $this->getJname());
			}
		}
	}

    /**
     * @param object $userinfo
     *
     * @return array
     */
    function deleteUser($userinfo) {
        //get the database ready
        $db = JFactory::getDBO();
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

	        $query = $db->getQuery(true)
		        ->delete('#__jfusion_users')
		        ->where('id = ' . (int)$userid);

	        $db->setQuery($query);
            $db->execute();
            $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
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
                $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
            } else {
                //could not find user and return an error
                $status['error'][] = JText::_('ERROR_DELETE') . $username;
            }
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function createSession($userinfo, $options) {
        $status = array('error' => array(), 'debug' => array());
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
	        jimport('joomla.user.helper');
	        $instance = JUser::getInstance();

	        // If _getUser returned an error, then pass it back.
	        if (!$instance->load($userinfo->userid)) {
		        $status['error'][] = JText::_('FUSION_ERROR_LOADING_USER');
	        } else {
		        // If the user is blocked, redirect with an error
		        if ($instance->get('block') == 1) {
			        $status['error'][] = JText::_('JERROR_NOLOGIN_BLOCKED');
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
				        $status['error'][] = JText::_('JERROR_LOGIN_DENIED');
			        } else {
				        // Mark the user as logged in
				        $instance->set('guest', 0);

				        // Register the needed session variables
				        $session = JFactory::getSession();
				        $session->set('user', $instance);

				        // Update the user related fields for the Joomla sessions table.
				        try {
					        $db = JFactory::getDBO();

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
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options) {
	    if (!isset($options['clientid'])) {
		    $mainframe = \JFusion\Factory::getApplication();
		    if ($mainframe->isAdmin()) {
		        $options['clientid'] = array(1);
		    } else {
		        $options['clientid'] = array(0);
		    }
		} elseif (!is_array($options['clientid'])) {
		    //J1.6+ does not pass clientid as an array so let's fix that
		    $options['clientid'] = array($options['clientid']);
		}

	    if ($userinfo->id) {
		    $my = JFactory::getUser();
		    if ($my->id == $userinfo->id) {
			    // Hit the user last visit field
			    $my->setLastVisit();
			    // Destroy the php session for this user
			    $session = JFactory::getSession();
			    $session->destroy();
		    }
		    //destroy the Joomla session but do so directly based on what $options is
		    $table = JTable::getInstance('session');
		    $table->destroy($userinfo->id, $options['clientid']);
	    }
        return array();
    }

	/**
	 * Function that updates usergroup
	 *
	 * @param object $userinfo          Object containing the new userinfo
	 * @param object &$existinguser     Object containing the old userinfo
	 * @param array  &$status           Array containing the errors and result of the function
	 * @param bool   $fire_user_plugins needs more detail
	 *
	 * @throws RuntimeException
	 */
	public function updateUsergroup($userinfo, &$existinguser, &$status, $fire_user_plugins = true)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		//make sure the group exists
		if (empty($usergroups)) {
			throw new RuntimeException(JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
		} else {
			$db = \JFusion\Factory::getDatabase($this->getJname());
			$dispatcher = JEventDispatcher::getInstance();

			jimport('joomla.user.helper');

			if ($fire_user_plugins) {
				// Get the old user
				$old = new JUser($existinguser->userid);
				//Fire the onBeforeStoreUser event.
				JPluginHelper::importPlugin('user');
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
			$this->debugger->add('debug', JText::_('GROUP_UPDATE') . ': ' . implode(',', $existinguser->groups) . ' -> ' .implode(',', $usergroups));
			if ($fire_user_plugins) {
				//Fire the onAfterStoreUser event
				$updated = new JUser($existinguser->userid);
				$dispatcher->trigger('onAfterStoreUser', array($updated->getProperties(), false, true, ''));
			}
		}
	}

	/**
	 * Function that updates the user email
	 *
	 * @param object $userinfo      Object containing the new userinfo
	 * @param object &$existinguser Object containing the old userinfo
	 * @param array  &$status       Array containing the errors and result of the function
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function updateEmail($userinfo, &$existinguser, &$status)
	{
		$db = \JFusion\Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('email = ' . $db->quote($userinfo->email))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
	}

	/**
	 * Function that updates the user password
	 *
	 * @param object $userinfo      Object containing the new userinfo
	 * @param object &$existinguser Object containing the old userinfo
	 * @param array  &$status       Array containing the errors and result of the function
	 *
	 * @throws Exception
	 * @return string updates are passed on into the $status array
	 */
	public function updatePassword($userinfo, &$existinguser, &$status)
	{
		if (strlen($userinfo->password_clear) > 55)
		{
			throw new Exception(JText::_('JLIB_USER_ERROR_PASSWORD_TOO_LONG'));
		} else {
			/**
			 * @ignore
			 * @var $auth JFusionAuth_joomla_int
			 */
			$auth = \JFusion\Factory::getAuth($this->getJname());
			$password = $auth->hashPassword($userinfo);

			$db = \JFusion\Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__users')
				->set('password = ' . $db->quote($password))
				->where('id = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$this->debugger->add('debug', JText::_('PASSWORD_UPDATE')  . ': ' . substr($password, 0, 6) . '********');
		}
	}

	/**
	 * Function that blocks user
	 *
	 * @param object $userinfo      Object containing the new userinfo
	 * @param object &$existinguser Object containing the old userinfo
	 * @param array  &$status       Array containing the errors and result of the function
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function blockUser($userinfo, &$existinguser, &$status)
	{
		//block the user
		$db = \JFusion\Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('block = 1')
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
	}

	/**
	 * Function that unblocks user
	 *
	 * @param object $userinfo      Object containing the new userinfo
	 * @param object &$existinguser Object containing the old userinfo
	 * @param array  &$status       Array containing the errors and result of the function
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function unblockUser($userinfo, &$existinguser, &$status)
	{
		//unblock the user
		$db = \JFusion\Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('block = 0')
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
	}

	/**
	 * Function that activates user
	 *
	 * @param object $userinfo      Object containing the new userinfo
	 * @param object &$existinguser Object containing the old userinfo
	 * @param array  &$status       Array containing the errors and result of the function
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function activateUser($userinfo, &$existinguser, &$status)
	{
		//unblock the user
		$db = \JFusion\Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('block = 0')
			->set('activation = ' . $db->quote(''))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	}

	/**
	 * Function that inactivates user
	 *
	 * @param object $userinfo      Object containing the new userinfo
	 * @param object &$existinguser Object containing the old userinfo
	 * @param array  &$status       Array containing the errors and result of the function
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function inactivateUser($userinfo, &$existinguser, &$status)
	{
		//unblock the user
		$db = \JFusion\Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('block = 1')
			->set('activation = ' . $db->quote($userinfo->activation))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
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
			$JFusionPlugin = \JFusion\Factory::getUser($added_filter);
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
	 * @param stdClass $userinfo
	 * @param stdClass $existinguser
	 *
	 * @return boolean return true if changed
	 */
	function doUserLanguage($userinfo, &$existinguser)
	{
		$changed = false;
		//Update the user language in the one existing from an other plugin
		if (!empty($userinfo->language) && !empty($existinguser->language) && $userinfo->language != $existinguser->language) {
			try {
				$this->updateUserLanguage($userinfo, $existinguser, $status);
				$existinguser->language = $userinfo->language;
				$this->debugger->add('debug', JText::_('LANGUAGE_UPDATED') . ' : ' . $existinguser->language . ' -> ' . $userinfo->language);

				$existinguser->language = $userinfo->language;
				$changed = true;
			} catch (Exception $e) {
				$this->debugger->add('error', JText::_('LANGUAGE_UPDATED_ERROR') . ' ' . $e->getMessage());
			}
		} else {
			//return a debug to inform we skipped this step
			$this->debugger->add('debug', JText::_('LANGUAGE_NOT_UPDATED'));
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
	 * @param object $userinfo      Object containing the new userinfo
	 * @param object &$existinguser Object containing the old userinfo
	 * @param array  &$status       Array containing the errors and result of the function
	 */
	public function updateUserLanguage($userinfo, &$existinguser, &$status)
	{
		$db = \JFusion\Factory::getDatabase($this->getJname());
		$params = new JRegistry($existinguser->params);
		$params->set('language', $userinfo->language);

		$query = $db->getQuery(true)
			->update('#__users')
			->set('params = ' . $db->quote($params->toString()))
			->where('id = ' . $db->quote($existinguser->userid));

		$db->setQuery($query);

		$db->execute();
		$this->debugger->add('debug', JText::_('LANGUAGE_UPDATE') . ' ' . $existinguser->language);
	}
}
