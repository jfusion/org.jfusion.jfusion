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
/**
 * load the common Joomla JFusion plugin functions
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'model.joomlauser.php';

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
class JFusionUser_joomla_int extends JFusionJoomlaUser {
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
			$db = JFusionFactory::getDatabase($this->getJname());
			$JFusionUser = JFusionFactory::getUser($this->getJname());
			list($identifier_type, $identifier) = $JFusionUser->getUserIdentifier($userinfo, 'username', 'email');
			if ($identifier_type == 'username') {
				$query = $db->getQuery(true)
					->select('b.id as userid, b.activation, a.username, b.name, b.password, b.email, b.block, b.params')
					->from('#__users as b')
					->innerJoin('#__jfusion_users as a ON a.id = b.id');

				if ($this->params->get('case_insensitive')) {
					$query->where('LOWER(a.' . $identifier_type . ') = ' . $db->Quote(strtolower($identifier)));
				} else {
					$query->where('a.' . $identifier_type . ' = ' . $db->Quote($identifier));
				}
				//first check the JFusion user table if the identifier_type = username
				$db->setQuery($query);

				$result = $db->loadObject();
				if (!$result) {
					$query = $db->getQuery(true)
						->select('id as userid, activation, username, name, password, email, block, params')
						->from('#__users');

					if ($this->params->get('case_insensitive')) {
						$query->where('LOWER(' . $identifier_type . ') = ' . $db->Quote(strtolower($identifier)));
					} else {
						$query->where($identifier_type . ' = ' . $db->Quote($identifier));
					}
					//check directly in the joomla user table
					$db->setQuery($query);

					$result = $db->loadObject();
					if ($result) {
						//update the lookup table so that we don't have to do a double query next time
						$query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $result->userid . ', ' . $db->Quote($identifier) . ')';
						$db->setQuery($query);
						try {
							$db->execute();
						} catch (Exception $e) {
							JFusionFunction::raiseWarning($e, $this->getJname());
						}
					}
				}
			} else {
				$query = $db->getQuery(true)
					->select('id as userid, activation, username, name, password, email, block, params')
					->from('#__users')
					->where($identifier_type . ' = ' . $db->Quote($identifier));

				$db->setQuery($query);
				$result = $db->loadObject();
			}
			if ($result) {
				$query = $db->getQuery(true)
					->select('a.group_id, b.title as name')
					->from('#__user_usergroup_map as a')
					->innerJoin('#__usergroups as b ON a.group_id = b.id')
					->where('a.user_id = ' . $db->Quote($result->userid));

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
				if (strpos($result->password, ':') !== false) {
					$saltStart = strpos($result->password, ':');
					$result->password_salt = substr($result->password, $saltStart + 1);
					$result->password = substr($result->password, 0, $saltStart);
				} else {
					//prevent php notices
					$result->password_salt = '';
				}
				// Get the language of the user and store it as variable in the user object
				$user_params = new JRegistry($result->params);

				$result->language = $user_params->get('language', JFactory::getLanguage()->getTag());

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
			JFusionFunction::raiseError($e, $this->getJname());
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
		try {
			//generate the filtered integration username
			$db = JFusionFactory::getDatabase($this->getJname());
			$username_clean = $this->filterUsername($userinfo->username);
			$status['debug'][] = JText::_('USERNAME') . ': ' . $userinfo->username . ' -> ' . JText::_('FILTERED_USERNAME') . ':' . $username_clean;

			$query = $db->getQuery(true)
				->update('#__users')
				->set('username = ' . $db->quote($username_clean))
				->where('id = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			try {
				$db->execute();
				$status['debug'][] = JText::_('USERNAME_UPDATE') . ': ' . $username_clean;
			} catch (Exception $e) {
				$status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
			}

			//update the lookup table
			$query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $existinguser->userid . ', ' . $db->Quote($userinfo->username) . ')';
			$db->setQuery($query);
			$db->execute();

			$status['debug'][] = JText::_('USERNAME_UPDATE') . ': ' . $username_clean;
		} catch (Exception $e) {
			$status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
		}
	}

	/**
	 * Function that creates a new user account
	 *
	 * @param object $userinfo Object containing the new userinfo
	 * @param array  &$status  Array containing the errors and result of the function
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function createUser($userinfo, &$status)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		try {
			//get the default user group and determine if we are using simple or advanced
			//check to make sure that if using the advanced group mode, $userinfo->group_id exists
			if (empty($usergroups)) {
				throw new RuntimeException(JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING'));
			} else {
				//load the database
				$db = JFusionFactory::getDatabase($this->getJname());
				//joomla does not allow duplicate email addresses, check to see if the email is unique
				$query = $db->getQuery(true)
					->select('id as userid, username, email')
					->from('#__users')
					->where('email = ' . $db->Quote($userinfo->email));

				$db->setQuery($query);
				$existinguser = $db->loadObject();
				if (empty($existinguser)) {
					//apply username filtering
					$username_clean = $this->filterUsername($userinfo->username);
					//now we need to make sure the username is unique in Joomla

					$query = $db->getQuery(true)
						->select('id')
						->from('#__users')
						->where('username=' . $db->Quote($username_clean));

					$db->setQuery($query);
					while ($db->loadResult()) {
						$username_clean.= '_';
						$query = $db->getQuery(true)
							->select('id')
							->from('#__users')
							->where('username=' . $db->Quote($username_clean));

						$db->setQuery($query);
					}
					$status['debug'][] = JText::_('USERNAME') . ':' . $userinfo->username . ' ' . JText::_('FILTERED_USERNAME') . ':' . $username_clean;
					//create a Joomla password hash if password_clear is available
					if (!empty($userinfo->password_clear)) {
						jimport('joomla.user.helper');
						$userinfo->password_salt = JUserHelper::genRandomPassword(32);
						$userinfo->password = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt);
						$password = $userinfo->password . ':' . $userinfo->password_salt;
					} else {
						//if password_clear is not available, store hashed password as is and also store the salt if present
						if (isset($userinfo->password_salt)) {
							$password = $userinfo->password . ':' . $userinfo->password_salt;
						} else {
							$password = $userinfo->password;
						}
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
					$query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $createdUser->id . ', ' . $db->Quote($username) . ')';
					$db->setQuery($query);
					try {
						$db->execute();
					} catch (Exception $e) {
						JFusionFunction::raiseWarning($e, $this->getJname());
					}

					//check to see if the user exists now
					$joomla_user = $this->getUser($userinfo);
					if ($joomla_user) {
						//report back success
						$status['userinfo'] = $joomla_user;
						$status['debug'][] = JText::_('USER_CREATION');
					} else {
						$status['error'][] = JText::_('COULD_NOT_CREATE_USER');
					}
				} else {
					//Joomla does not allow duplicate emails report error
					$status['debug'][] = JText::_('USERNAME') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->username . ' -> ' . $userinfo->username;
					$status['error'][] = JText::_('EMAIL_CONFLICT') . '. UserID: ' . $existinguser->userid . ' JFusionPlugin: ' . $this->getJname();
					$status['userinfo'] = $existinguser;
				}
			}
		} catch (Exception $e) {
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $e->getMessage();
		}
		return $status;
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
		    ->where('username = ' . $db->Quote($username), 'OR')
		    ->where('LOWER(username) = ' . $db->Quote(strtolower($userinfo->email)));

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
		        ->where('username  = ' . $db->Quote($username));

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
		    $mainframe = JFactory::getApplication();
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
	 * @param bool $fire_user_plugins needs more detail
	 *
	 * @return string updates are passed on into the $status array
	 */
	public function updateUsergroup($userinfo, &$existinguser, &$status, $fire_user_plugins = true)
	{
		try {
			$usergroups = $this->getCorrectUserGroups($userinfo);
			//make sure the group exists
			if (empty($usergroups)) {
				throw new RuntimeException(JText::_('GROUP_UPDATE_ERROR') . ': ' . JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
			} else {
				$db = JFusionFactory::getDatabase($this->getJname());
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
					->where('user_id = ' . $db->Quote($existinguser->userid));

				$db->setQuery($query);

				$db->execute();

				foreach ($usergroups as $group) {
					$temp = new stdClass;
					$temp->user_id = $existinguser->userid;
					$temp->group_id = $group;

					$db->insertObject('#__user_usergroup_map', $temp);
				}
				$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode(',', $existinguser->groups) . ' -> ' .implode(',', $usergroups);
				if ($fire_user_plugins) {
					//Fire the onAfterStoreUser event
					$updated = new JUser($existinguser->userid);
					$dispatcher->trigger('onAfterStoreUser', array($updated->getProperties(), false, true, ''));
				}
			}
		} catch (Exception $e) {
			$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $e->getMessage();
		}
		return $status;
	}
}
