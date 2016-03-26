<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion User Class for phpBB3
 * For detailed descriptions on these functions please check JFusionUser
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_phpbb31 extends JFusionUser
{
	/**
	 * @var $helper JFusionHelper_phpbb31
	 */
	var $helper;

	/**
	 * @param object $userinfo
	 * @return null|object
	 */
	function getUser($userinfo) {
		try {
			//get the identifier
			list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.username', 'a.user_email');
			// Get a database object
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('a.user_id as userid, a.username as name, a.username as username, a.user_email as email, a.user_password as password, null as password_salt, a.user_actkey as activation, a.user_inactive_reason as reason, a.user_lastvisit as lastvisit, a.group_id, b.group_name, a.user_type, a.user_avatar, a.user_avatar_type')
				->from('#__users as a')
				->join('LEFT OUTER', '#__groups as b ON a.group_id = b.group_id')
				->where($identifier_type . ' = ' . $db->quote($identifier));

			$db->setQuery($query);
			$result = $db->loadObject();
			if ($result) {
				//prevent anonymous user accessed
				if ($result->username == 'anonymous') {
					$result = null;
				} else {
					$query = $db->getQuery(true)
						->select('ug.group_id as group_id, g.group_name')
						->from('#__user_group as ug')
						->join('LEFT OUTER', '#__groups as g ON ug.group_id = g.group_id')
						->where('ug.user_id = ' . $db->quote($result->userid));

					$db->setQuery($query);
					$groups = $db->loadObjectList();

					$result->groups = array();
					$result->groupnames = array();
					if ($groups) {
						foreach ($groups as $group) {
							$result->groups[] = $group->group_id;
							$result->groupnames[] = $group->group_name;
						}
					}

					//Check to see if they are banned
					$query = $db->getQuery(true)
						->select('ban_userid')
						->from('#__banlist')
						->where('ban_userid = ' . (int)$result->userid);

					$db->setQuery($query);
					if ($db->loadObject()) {
						$result->block = 1;
					} else {
						$result->block = 0;
					}
					//if no inactive reason is set clear the activation code
					if ($result->user_type == 1) {
						//user is inactive
						if (empty($result->activation)) {
							//user not active generate a random code
							jimport('joomla.user.helper');
							$result->activation = JUserHelper::genRandomPassword(13);
						}
					} else {
						//active user, make sure no activation code is set
						$result->activation = '';
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
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'phpbb31';
	}

	/**
	 * @param object $userinfo
	 * @param array $options
	 *
	 * @return array
	 */
	function destroySession($userinfo, $options)
	{
		$status = array('error' => array(), 'debug' => array());
		try {
			$db = JFusionFactory::getDatabase($this->getJname());
			//get the cookie parameters
			$phpbb_cookie_name = $this->params->get('cookie_prefix');
			$phpbb_cookie_path = $this->params->get('cookie_path');
			$secure = $this->params->get('secure', false);
			$httponly = $this->params->get('httponly', true);
			//baltie cookie domain fix
			$phpbb_cookie_domain = $this->params->get('cookie_domain');
			if ($phpbb_cookie_domain == 'localhost' || $phpbb_cookie_domain == '127.0.0.1') {
				$phpbb_cookie_domain = '';
			}
			//update session time for the user into user table
			$query = $db->getQuery(true)
				->update('#__users')
				->set('user_lastvisit = ' . time())
				->where('user_id = ' . (int)$userinfo->userid);

			$db->setQuery($query);
			try {
				$db->execute();
			} catch (Exception $e) {
				$status['debug'][] = 'Error could not update the last visit field ' . $e->getMessage();
			}

			//delete the cookies
			$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_u', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly);
			$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_sid', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly);
			$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_k', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly);

			$_COOKIE[$phpbb_cookie_name . '_u'] = '';
			$_COOKIE[$phpbb_cookie_name . '_sid'] = '';
			$_COOKIE[$phpbb_cookie_name . '_k'] = '';
			//delete the database sessions
			$query = $db->getQuery(true)
				->delete('#__sessions')
				->where('session_user_id = ' . (int)$userinfo->userid);

			$db->setQuery($query);
			$db->execute();

			$query = $db->getQuery(true)
				->delete('#__sessions_keys')
				->where('user_id = ' . (int)$userinfo->userid);

			$db->setQuery($query);
			$db->execute();

			$status['debug'][] = 'Deleted the session';
		} catch (Exception $e) {
			$status['debug'][] = 'Error could not delete the session:' . $e->getMessage();
		}
		return $status;
	}

	/**
	 * @param object $userinfo
	 * @param array $options
	 * @return array
	 */
	function createSession($userinfo, $options) {
		$status = array('error' => array(), 'debug' => array());
		try {
			//do not create sessions for blocked users
			if (!empty($userinfo->block) || !empty($userinfo->activation)) {
				throw new RuntimeException(JText::_('FUSION_BLOCKED_USER'));
			} else {
				$jdb = JFusionFactory::getDatabase($this->getJname());
				$userid = $userinfo->userid;
				if ($userid && !empty($userid) && ($userid > 0)) {
					//check if we need to let phpbb3 handle the login
					$login_type = $this->params->get('login_type');
					if ($login_type != 1 && !function_exists('deregister_globals')) {
						//let phpbb3 handle login
						$source_path = $this->params->get('source_path');
						if (file_exists($source_path . 'common.php')) {
							//set the current directory to phpBB3
							chdir($source_path);
							/* set scope for variables required later */
							global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template, $phpbb_hook, $module, $mode;
							global $SID, $_SID, $_EXTRA_URL, $request, $phpbb_container, $phpbb_filesystem, $symfony_request, $phpbb_dispatcher;
							if (!defined('UTF8_STRLEN')) {
								define('UTF8_STRLEN', true);
							}
							if (!defined('UTF8_CORE')) {
								define('UTF8_CORE', true);
							}
							if (!defined('UTF8_CASE')) {
								define('UTF8_CASE', true);
							}
							if (!defined('IN_PHPBB')) {
								define('IN_PHPBB', true);
							}

							$phpbb_root_path = $source_path;
							$phpEx = 'php';

							include_once $source_path . 'common.php';

							//get phpbb3 session object
							$user->session_begin();
							$auth->acl($user->data);

							//perform the login
							if ($options['remember']) {
								$remember = true;
							} else {
								$remember = false;
							}
							$result = $auth->login($userinfo->username, $userinfo->password_clear, $remember, 1, 0);
							if ($result['status'] == LOGIN_SUCCESS) {
								$status['debug'][] = JText::_('CREATED') . ' ' . JText::_('PHPBB') . ' ' . JText::_('SESSION');
							} else {
								$status['debug'][] = JText::_('ERROR') . ' ' . JText::_('PHPBB') . ' ' . JText::_('SESSION');
							}
							//change the current directory back to Joomla.
							chdir(JPATH_SITE);

							if ($request) {
								$request->enable_super_globals();
							}
						} else {
							throw new RuntimeException(JText::sprintf('UNABLE_TO_FIND_FILE', 'common.php'));
						}
					} else {
						jimport('joomla.user.helper');
						$session_key = JFusionFunction::getHash(JUserHelper::genRandomPassword(32));
						//Check for admin access
						$query = $jdb->getQuery(true)
							->select('b.group_name')
							->from('#__user_group as a')
							->innerJoin('#__groups as b ON a.group_id = b.group_id')
							->where('b.group_name = ' . $jdb->quote('ADMINISTRATORS'))
							->where('a.user_id = ' . (int)$userinfo->userid);

						$jdb->setQuery($query);
						$usergroup = $jdb->loadResult();
						if ($usergroup == 'ADMINISTRATORS') {
							$admin_access = 1;
						} else {
							$admin_access = 0;
						}
						$phpbb_cookie_name = $this->params->get('cookie_prefix');
						if ($phpbb_cookie_name) {
							//get cookie domain from config table
							$phpbb_cookie_domain = $this->params->get('cookie_domain');
							if ($phpbb_cookie_domain == 'localhost' || $phpbb_cookie_domain == '127.0.0.1') {
								$phpbb_cookie_domain = '';
							}
							//get cookie path from config table
							$phpbb_cookie_path = $this->params->get('cookie_path');
							//get autologin perm
							$phpbb_allow_autologin = $this->params->get('allow_autologin');
							$jautologin = 0;
							//set the remember me option if set in Joomla and is allowed per config
							if (isset($options['remember']) && !empty($phpbb_allow_autologin)) {
								$jautologin = $options['remember'] ? 1 : 0;
							}

							$create_persistant_cookie = false;
							if (!empty($phpbb_allow_autologin)) {
								//check for a valid persistent cookie
								$persistant_cookie = ($phpbb_allow_autologin) ? JFusionFactory::getApplication()->input->cookie->get($phpbb_cookie_name . '_k', '') : '';
								if (!empty($persistant_cookie)) {
									$query = $jdb->getQuery(true)
										->select('user_id')
										->from('#__sessions_keys')
										->where('key_id = ' . $jdb->quote(md5($persistant_cookie)));

									$jdb->setQuery($query);
									$persistant_cookie_userid = $jdb->loadResult();
									if ($persistant_cookie_userid == $userinfo->userid) {
										$status['debug'][] = JText::_('SKIPPED_CREATING_PERSISTANT_COOKIE');
										$create_persistant_cookie = false;
										//going to assume that since a persistent cookie exists, $options['remember'] was originally set
										//$options['remember'] does not get set if Joomla remember me plugin reinitiated the login
										$jautologin = 1;
									}
								} else {
									$create_persistant_cookie = true;
								}
							}

							if ($jautologin) {
								$query = $jdb->getQuery(true)
									->select('config_value')
									->from('#__config')
									->where('config_name = ' . $jdb->quote('max_autologin_time'));

								$jdb->setQuery($query);
								$max_autologin_time = $jdb->loadResult();
								$expires = ($max_autologin_time) ? 86400 * (int) $max_autologin_time : 31536000;
							} else {
								$expires = 31536000;
							}
							$secure = $this->params->get('secure', false);
							$httponly = $this->params->get('httponly', true);
							$session_start = time();
							//Insert the session into sessions table
							$session_obj = new stdClass;
							$session_obj->session_id = substr($session_key, 0, 32);
							$session_obj->session_user_id = $userid;
							$session_obj->session_last_visit = $userinfo->lastvisit;
							$session_obj->session_start = $session_start;
							$session_obj->session_time = $session_start;
							$session_obj->session_ip = $_SERVER['REMOTE_ADDR'];
							$session_obj->session_browser = $_SERVER['HTTP_USER_AGENT'];
							$session_obj->session_page = 0;
							$session_obj->session_autologin = $jautologin;
							$session_obj->session_admin = $admin_access;

							$jdb->insertObject('#__sessions', $session_obj);

							//Set cookies
							$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_u', $userid, $expires, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly);
							$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_sid', $session_key, $expires, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly, true);

							//Force the values into the $_COOKIE variable just in case Joomla remember me plugin fired this in which the cookie will not be available until after the browser refreshes.  This will hopefully trick phpBB into thinking the cookie is present now and thus handle sessions correctly when in frameless mode
							$_COOKIE[$phpbb_cookie_name . '_u'] = $userid;
							$_COOKIE[$phpbb_cookie_name . '_sid'] = $session_key;

							// Remember me option?
							if ($jautologin > 0 && $create_persistant_cookie) {
								$key_id = substr(md5($session_key . microtime()), 4, 16);
								//Insert the session key into sessions_key table
								$session_key_ins = new stdClass;
								$session_key_ins->key_id = md5($key_id);
								$session_key_ins->user_id = $userid;
								$session_key_ins->last_ip = $_SERVER['REMOTE_ADDR'];
								$session_key_ins->last_login = $session_start;
								$jdb->insertObject('#__sessions_keys', $session_key_ins);

								$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_k', $key_id, $expires, $phpbb_cookie_path, $phpbb_cookie_domain, $secure, $httponly, true);
								$_COOKIE[$phpbb_cookie_name . '_k'] = $key_id;
							}
						} else {
							throw new RuntimeException(JText::_('INVALID_COOKIENAME'));
						}
					}
				} else {
					throw new RuntimeException(JText::_('INVALID_USERID'));
				}
			}
		} catch (Exception $e) {
			$status['error'][] = $e->getMessage();
		}
		return $status;
	}

	/**
	 * @param string $username
	 * @return string
	 */
	function filterUsername($username) {
		$username_clean = $this->helper->utf8_clean_string($username);
		//die($username . ':' . $username_clean);
		return $username_clean;
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
		 * @var $auth JFusionAuth_phpbb3
		 */
		$auth = JFusionFactory::getAuth($this->getJname());
		$existinguser->password = $auth->HashPassword($userinfo->password_clear);

		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('user_password = ' . $db->quote($existinguser->password))
			->where('user_id = ' . (int)$existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function updateUsername($userinfo, &$existinguser, &$status) {
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

		$query = $db->getQuery(true)
			->update('#__users')
			->set('user_email = ' . $db->quote($userinfo->email))
			->where('user_id = ' . (int)$existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array  $status
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	public function updateUsergroup($userinfo, &$existinguser, &$status) {
		$usergroups = $this->getCorrectUserGroups($userinfo);
		if (empty($usergroups)) {
			throw new RuntimeException(JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
		} else {
			$usergroup = $usergroups[0];

			if (!isset($usergroup->groups)) {
				$usergroup->groups = array($usergroup->defaultgroup);
			} else if (!in_array($usergroup->defaultgroup, $usergroup->groups)) {
				$usergroup->groups[] = $usergroup->defaultgroup;
			}

			$db = JFusionFactory::getDatabase($this->getJname());
			$user = new stdClass;
			$user->user_id = $existinguser->userid;
			$user->group_id = $usergroup->defaultgroup;
			$user->user_colour = '';
			//clear out cached permissions so that those of the new group are generated
			$user->user_permissions = '';
			//update the user colour, avatar, etc to the groups if applicable
			$query = $db->getQuery(true)
				->select('group_colour, group_rank, group_avatar, group_avatar_type, group_avatar_width, group_avatar_height')
				->from('#__groups')
				->where('group_id = ' . $user->group_id);

			$db->setQuery($query);
			$group_attribs = $db->loadAssoc();
			if (!empty($group_attribs)) {
				foreach($group_attribs AS $k => $v) {
					// If we are about to set an avatar or rank, we will not overwrite with empty, unless we are not actually changing the default group
					if ((strpos($k, 'group_avatar') === 0 || strpos($k, 'group_rank') === 0) && !$group_attribs[$k])
					{
						continue;
					}
					$user->{str_replace('group_', 'user_', $k)} = $v;
				}
			}

			//set the usergroup in the user table
			$db->updateObject('#__users', $user, 'user_id');

			try {
				//remove the old usergroup for the user in the groups table
				$query = $db->getQuery(true)
					->delete('#__user_group')
					->where('user_id = ' . (int)$existinguser->userid);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$this->debugger->add('error', JText::_('GROUP_UPDATE_ERROR') . ' ' . $e->getMessage());
			}

			foreach($usergroup->groups as $group) {
				$newgroup = new stdClass;
				$newgroup->group_id = (int)$group;
				$newgroup->user_id = (int)$existinguser->userid;
				$newgroup->group_leader = 0;
				$newgroup->user_pending = 0;

				$db->insertObject('#__user_group', $newgroup);
			}

			try {
				//update correct group colors where applicable
				$query = $db->getQuery(true)
					->update('#__forums')
					->set('forum_last_poster_colour = ' . $db->quote($user->user_colour))
					->where('forum_last_poster_id = ' . (int)$existinguser->userid);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$this->debugger->add('error', JText::_('GROUP_UPDATE_ERROR') . ' ' . $e->getMessage());
			}

			try {
				//update correct group colors where applicable
				$query = $db->getQuery(true)
					->update('#__topics')
					->set('topic_first_poster_colour = ' . $db->quote($user->user_colour))
					->where('topic_poster = ' . (int)$existinguser->userid);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$this->debugger->add('error', JText::_('GROUP_UPDATE_ERROR') . ' ' . $e->getMessage());
			}

			try {
				$query = $db->getQuery(true)
					->update('#__topics')
					->set('topic_last_poster_colour = ' . $db->quote($user->user_colour))
					->where('topic_last_poster_id = ' . (int)$existinguser->userid);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$this->debugger->add('error', JText::_('GROUP_UPDATE_ERROR') . ' ' . $e->getMessage());
			}

			$query = $db->getQuery(true)
				->select('config_value')
				->from('#__config')
				->where('config_name = ' . $db->quote('newest_user_id'));

			$db->setQuery($query);
			$newest_user_id = $db->loadResult();
			if ($newest_user_id == $existinguser->userid) {
				try {
					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = ' . $db->quote($user->user_colour))
						->where('config_name = ' . $db->quote('newest_user_id'));

					$db->setQuery($query);
					$db->execute();
				} catch (Exception $e) {
					$this->debugger->add('error', JText::_('GROUP_UPDATE_ERROR') . ' ' . $e->getMessage());
				}
			}
			//log the group change success
			$this->debugger->add('debug', JText::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . implode(' , ', $usergroup->groups));
		}
	}

	/**
	 * @param object &$userinfo
	 * @param object &$existinguser
	 * @param array &$status
	 *
	 * @return bool
	 */
	function executeUpdateUsergroup(&$userinfo, &$existinguser, &$status)
	{
		$update_groups = false;
		$usergroups = $this->getCorrectUserGroups($userinfo);
		$usergroup = $usergroups[0];

		$groups = (isset($usergroup->groups)) ? $usergroup->groups : array();

		//check to see if the default groups are different
		if ($usergroup->defaultgroup != $existinguser->group_id ) {
			$update_groups = true;
		} else {
			if (count($existinguser->groups) != count($groups)) {
				$update_groups = true;
			} else {
				foreach ($groups as $gid) {
					if (!in_array($gid, $existinguser->groups)) {
						$update_groups = true;
						break;
					}
				}
			}

		}

		if ($update_groups) {
			$this->updateUsergroup($userinfo, $existinguser, $status);
		}

		return $update_groups;
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function blockUser($userinfo, &$existinguser, &$status) {
		//block the user
		$db = JFusionFactory::getDatabase($this->getJname());

		$ban = new stdClass;
		$ban->ban_userid = $existinguser->userid;
		$ban->ban_start = time();

		$db->insertObject('#__banlist', $ban);

		$this->debugger->add('debug', JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function unblockUser($userinfo, &$existinguser, &$status) {
		//unblock the user
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = $db->getQuery(true)
			->delete('#__banlist')
			->where('ban_userid = ' . (int)$existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function activateUser($userinfo, &$existinguser, &$status) {
		//activate the user
		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('user_type = 0')
			->set('user_inactive_reason = 0')
			->set('user_actkey = ' . $db->quote(''))
			->where('user_id = ' . (int)$existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function inactivateUser($userinfo, &$existinguser, &$status) {
		//set activation key
		$db = JFusionFactory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__users')
			->set('user_type = 1')
			->set('user_inactive_reason = 1')
			->set('user_actkey = ' . $db->quote($userinfo->activation))
			->where('user_id = ' . (int)$existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->add('debug', JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	}

	/**
	 * @param object $userinfo
	 * @param array $status
	 *
	 * @return void
	 */
	function createUser($userinfo, &$status) {
		try {
			//found out what usergroup should be used
			$db = JFusionFactory::getDatabase($this->getJname());
			$update_block = $this->params->get('update_block');
			$update_activation = $this->params->get('update_activation');
			$usergroups = $this->getCorrectUserGroups($userinfo);
			if (empty($usergroups)) {
				throw new RuntimeException(JText::_('USERGROUP_MISSING'));
			} else {
				$usergroup = $usergroups[0];

				if (!isset($usergroup->groups)) {
					$usergroup->groups = array($usergroup->defaultgroup);
				} else if (!in_array($usergroup->defaultgroup, $usergroup->groups)) {
					$usergroup->groups[] = $usergroup->defaultgroup;
				}

				$username_clean = $this->filterUsername($userinfo->username);

				$query = $db->getQuery(true)
					->select('user_id as userid, username as username')
					->from('#__users')
					->where('username_clean = ' . $db->quote($username_clean));

				$db->setQuery($query);
				$result = $db->loadObject();
				if ($result) {
					//username taken
					throw new RuntimeException('username taken: ' . $result->username . ' : ' . $username_clean . ' : ' . $result->userid);
				} else if ($username_clean == 'anonymous') {
					//prevent anonymous user being created
					throw new RuntimeException('reserved username');
				} else {
					//prepare the variables
					$user = new stdClass;
					$user->user_id = null;
					$user->username = $userinfo->username;
					$user->username_clean = $username_clean;
					if (isset($userinfo->password_clear)) {
						/**
						 * @ignore
						 * @var $auth JFusionAuth_phpbb3
						 */
						$auth = JFusionFactory::getAuth($this->getJname());
						$user->user_password = $auth->HashPassword($userinfo->password_clear);
					} else {
						$user->user_password = $userinfo->password;
					}
					$user->user_email = strtolower($userinfo->email);
					$user->user_email_hash = crc32(strtolower($userinfo->email)) . strlen($userinfo->email);
					$user->group_id = $usergroup->defaultgroup;
					$user->user_permissions = '';
					$user->user_allow_pm = 1;
					$user->user_actkey = '';
					$user->user_ip = '';
					$user->user_regdate = time();
					$user->user_passchg = time();
					$user->user_options = 230207;
					if (!empty($userinfo->activation) && $update_activation) {
						$user->user_inactive_reason = 1;
						$user->user_actkey = $userinfo->activation;
						$user->user_type = 1;
					} else {
						$user->user_inactive_reason = 0;
						$user->user_type = 0;
					}
					$user->user_inactive_time = 0;
					$user->user_lastmark = time();
					$user->user_lastvisit = 0;
					$user->user_lastpost_time = 0;
					$user->user_lastpage = '';
					$user->user_posts = 0;
					$user->user_colour = '';
					$user->user_avatar = '';
					$user->user_avatar_type = 0;
					$user->user_avatar_width = 0;
					$user->user_avatar_height = 0;
					$user->user_new_privmsg = 0;
					$user->user_unread_privmsg = 0;
					$user->user_last_privmsg = 0;
					$user->user_message_rules = 0;
					$user->user_emailtime = 0;
					$user->user_notify = 0;
					$user->user_notify_pm = 1;
					$user->user_allow_pm = 1;
					$user->user_allow_viewonline = 1;
					$user->user_allow_viewemail = 1;
					$user->user_allow_massemail = 1;
					$user->user_sig = '';
					$user->user_sig_bbcode_uid = '';
					$user->user_sig_bbcode_bitfield = '';
					//Find some default values

					$query = $db->getQuery(true)
						->select('config_name, config_value')
						->from('#__config')
						->where('config_name IN (\'board_timezone\', \'default_dateformat\', \'default_lang\', \'default_style\', \'board_dst\', \'rand_seed\')');

					$db->setQuery($query);
					$rows = $db->loadObjectList();
					$config = array();
					foreach ($rows as $row) {
						$config[$row->config_name] = $row->config_value;
					}
					$user->user_timezone = $config['board_timezone'];
					$user->user_dateformat = $config['default_dateformat'];
					$user->user_lang = $config['default_lang'];
					$user->user_style = $config['default_style'];
					$user->user_full_folder = - 4;
					$user->user_notify_type = 0;
					//generate a unique id
					jimport('joomla.user.helper');
					$user->user_form_salt = JUserHelper::genRandomPassword(13);

					//update the user colour, avatar, etc to the groups if applicable
					$query = $db->getQuery(true)
						->select('group_colour, group_rank, group_avatar, group_avatar_type, group_avatar_width, group_avatar_height')
						->from('#__groups')
						->where('group_id = ' . $usergroup->defaultgroup);

					$db->setQuery($query);
					$group_attribs = $db->loadAssoc();
					if (!empty($group_attribs)) {
						foreach($group_attribs AS $k => $v) {
							if (!empty($v)) {
								$user->{str_replace('group_', 'user_', $k)} = $v;
							}
						}
					}

					$db->insertObject('#__users', $user, 'user_id');

					foreach($usergroup->groups as $group) {
						$newgroup = new stdClass;
						$newgroup->group_id = (int)$group;
						$newgroup->user_id = (int)$user->user_id;
						$newgroup->group_leader = 0;
						$newgroup->user_pending = 0;

						$db->insertObject('#__user_group', $newgroup);
					}

					//update the total user count
					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = config_value + 1')
						->where('config_name = ' . $db->quote('num_users'));

					$db->setQuery($query);
					$db->execute();

					//update the newest username
					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = ' . $db->quote($userinfo->username))
						->where('config_name = ' . $db->quote('newest_username'));

					$db->setQuery($query);
					$db->execute();

					//update the newest userid
					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = ' . (int)$user->user_id )
						->where('config_name = ' . $db->quote('newest_user_id'));

					$db->setQuery($query);
					$db->execute();

					//get the username color
					if (!empty($user->user_colour)) {
						//set the correct new username color
						$query = $db->getQuery(true)
							->update('#__config')
							->set('config_value = ' . $db->quote($user->user_colour))
							->where('config_name = ' . $db->quote('newest_user_colour'));

						$db->setQuery($query);
						$db->execute();
					}
					if (!empty($userinfo->block) && $update_block) {
						try {
							$ban = new stdClass;
							$ban->ban_userid = $user->user_id;
							$ban->ban_start = time();

							$db->insertObject('#__banlist', $ban);
						} catch (Exception $e) {
							throw new RuntimeException(JText::_('BLOCK_UPDATE_ERROR') . ': ' . $e->getMessage());
						}
					}
					//return the good news
					$this->debugger->add('debug', JText::_('USER_CREATION'));
					$this->debugger->set('userinfo', $this->getUser($userinfo));
				}
			}
		} catch (Exception $e) {
			$this->debugger->add('error', JText::_('ERROR_CREATE_USER') . ' ' . $e->getMessage());
		}
	}

	/**
	 * @param object $userinfo
	 * @return array
	 */
	function deleteUser($userinfo) {
		//setup status array to hold debug info and errors
		$status = array('error' => array(), 'debug' => array());
		try {
			//retreive the database object
			$db = JFusionFactory::getDatabase($this->getJname());
			//set the userid
			$user_id = $userinfo->userid;
			// Before we begin, we will remove the reports the user issued.

			$report_posts = $report_topics = array();
			try {
				$query = $db->getQuery(true)
					->select('r.post_id, p.topic_id')
					->from('#__reports r, #__posts p')
					->where('r.user_id = ' . (int)$user_id)
					->where('p.post_id = r.post_id');

				$db->setQuery($query);
				$results = $db->loadObjectList();
				if ($results) {
					foreach ($results as $row) {
						$report_posts[] = $row->post_id;
						$report_topics[] = $row->topic_id;
					}
				}
			} catch (RuntimeException $e) {
				throw new RuntimeException('Error Could not retrieve reported posts/topics by user ' . $user_id . ': ' . $e->getMessage());
			}

			if (sizeof($report_posts)) {
				$report_posts = array_unique($report_posts);
				$report_topics = array_unique($report_topics);
				// Get a list of topics that still contain reported posts

				$keep_report_topics = array();
				try {
					$query = $db->getQuery(true)
						->select('DISTINCT topic_id')
						->from('#__posts')
						->where('topic_id IN (' . implode(', ', $report_topics) . ')')
						->where('post_id IN (' . implode(', ', $report_posts) . ')')
						->where('post_reported = 1');

					$db->setQuery($query);
					$results = $db->loadObjectList();
					if ($results) {
						foreach ($results as $row) {
							$keep_report_topics[] = $row->topic_id;
						}
					}
				} catch (Exception $e) {
					$status['error'][] = 'Error Could not retrieve a list of topics that still contain reported posts by user ' . $user_id . ': ' . $e->getMessage();
				}

				if (sizeof($keep_report_topics)) {
					$report_topics = array_diff($report_topics, $keep_report_topics);
				}
				unset($keep_report_topics);
				// Now set the flags back

				try {
					$query = $db->getQuery(true)
						->update('#__posts')
						->set('post_reported = 0')
						->where('post_id IN (' . implode(', ', $report_posts) . ')');

					$db->setQuery($query);
					$db->execute();
				} catch (Exception $e) {
					$status['error'][] = 'Error Could not update post reported flag: ' . $e->getMessage();
				}

				if (sizeof($report_topics)) {
					try {
						$query = $db->getQuery(true)
							->update('#__topics')
							->set('topic_reported = 0')
							->where('topic_id IN (' . implode(', ', $report_topics) . ')');

						$db->setQuery($query);
						$db->execute();
					} catch (Exception $e) {
						$status['error'][] = 'Error Could not update topics reported flag: ' . $e->getMessage();
					}
				}
			}

			try {
				// Remove reports
				$query = $db->getQuery(true)
					->delete('#__reports')
					->where('user_id = ' . (int)$user_id);
				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not delete reports by user ' . $user_id . ': ' . $e->getMessage();
			}

			//update all topics started by and posts by the user to anonymous
			$post_username = (!empty($userinfo->name)) ? $userinfo->name : $userinfo->username;
			try {
				$query = $db->getQuery(true)
					->update('#__forums')
					->set('forum_last_poster_id = 1')
					->set('forum_last_poster_name = ' . $db->quote($post_username))
					->set('forum_last_poster_colour = ' . $db->quote(''))
					->where('forum_last_poster_id = ' . $user_id);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not update forum last poster for user ' . $user_id . ': ' . $e->getMessage();
			}

			try {
				$query = $db->getQuery(true)
					->update('#__posts')
					->set('poster_id = 1')
					->set('post_username = ' . $db->quote($post_username))
					->where('poster_id = ' . $user_id);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not update posts by user ' . $user_id . ': ' . $e->getMessage();
			}

			try {
				$query = $db->getQuery(true)
					->update('#__posts')
					->set('post_edit_user = 1')
					->where('post_edit_user = ' . $user_id);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not update edited posts by user ' . $user_id . ': ' . $e->getMessage();
			}

			try {
				$query = $db->getQuery(true)
					->update('#__topics')
					->set('topic_poster = 1')
					->set('topic_first_poster_name = ' . $db->quote($post_username))
					->set('topic_first_poster_colour = ' . $db->quote(''))
					->where('topic_poster = ' . $user_id);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not update topics by user ' . $user_id . ': ' . $e->getMessage();
			}

			try {
				$query = $db->getQuery(true)
					->update('#__topics')
					->set('topic_last_poster_id = 1')
					->set('topic_last_poster_name = ' . $db->quote($post_username))
					->set('topic_last_poster_colour = ' . $db->quote(''))
					->where('topic_last_poster_id = ' . $user_id);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not update last topic poster for user ' . $user_id . ': ' . $e->getMessage();
			}

			// Since we change every post by this author, we need to count this amount towards the anonymous user
			$query = $db->getQuery(true)
				->select('user_posts')
				->from('#__users')
				->where('user_id = ' . $user_id);

			$db->setQuery($query);
			$user_posts = $db->loadResult();
			// Update the post count for the anonymous user
			if ($user_posts > 0) {
				try {
					$query = $db->getQuery(true)
						->update('#__users')
						->set('user_posts = user_posts + ' . $user_posts)
						->where('user_id = 1');

					$db->setQuery($query);
					$db->execute();
				} catch (Exception $e) {
					$status['error'][] = 'Error Could not update the number of posts for anonymous user: ' . $e->getMessage();
				}
			}
			$table_ary = array('users', 'user_group', 'topics_watch', 'forums_watch', 'acl_users', 'topics_track', 'topics_posted', 'forums_track', 'profile_fields_data', 'moderator_cache', 'drafts', 'bookmarks');
			foreach ($table_ary as $table) {
				try {
					$query = $db->getQuery(true)
						->delete('#__' . $table)
						->where('user_id = ' . $user_id);

					$db->setQuery($query);
					$db->execute();
				} catch (Exception $e) {
					$status['error'][] = 'Error Could not delete records from ' . $table . ' for user ' . $user_id . ': ' . $e->getMessage();
				}
			}

			$undelivered_msg = $undelivered_user = array();
			try {
				// Remove any undelivered mails...
				$query = $db->getQuery(true)
					->select('msg_id, user_id')
					->from('#__privmsgs_to')
					->where('author_id = ' . $user_id)
					->where('folder_id = -3');

				$db->setQuery($query);
				$results = $db->loadObjectList();
				if ($results) {
					foreach ($results as $row) {
						$undelivered_msg[] = $row->msg_id;
						$undelivered_user[$row->user_id][] = true;
					}
				}
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not retrieve undeliverd messages to user ' . $user_id . ': ' . $e->getMessage();
			}

			if (!empty($undelivered_msg)) {
				try {
					$query = $db->getQuery(true)
						->delete('#__privmsgs')
						->where('msg_id (' . implode(', ', $undelivered_msg) . ')');

					$db->setQuery($query);
					$db->execute();
				} catch (Exception $e) {
					$status['error'][] = 'Error Could not delete private messages for user ' . $user_id . ': ' . $e->getMessage();
				}
			}

			try {
				$query = $db->getQuery(true)
					->delete('#__privmsgs_to')
					->where('author_id = ' . $user_id)
					->where('folder_id = -3');

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not delete private messages that are in no folder from user ' . $user_id . ': ' . $e->getMessage();
			}

			try {
				// Delete all to-information
				$query = $db->getQuery(true)
					->delete('#__privmsgs_to')
					->where('user_id = ' . $user_id);

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not delete private messages to user ' . $user_id . ': ' . $e->getMessage();
			}

			try {
				// Set the remaining author id to anonymous - this way users are still able to read messages from users being removed
				$query = $db->getQuery(true)
					->update('#__privmsgs_to')
					->set('author_id = 1')
					->where('author_id = ' . $user_id);
				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not update rest of private messages for user ' . $user_id . ' to anonymous: ' . $e->getMessage();
			}

			try {
				$query = $db->getQuery(true)
					->update('#__privmsgs')
					->set('author_id = 1')
					->where('author_id = ' . $user_id);
				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				$status['error'][] = 'Error Could not update rest of private messages for user ' . $user_id . ' to anonymous: ' . $e->getMessage();
			}

			foreach ($undelivered_user as $_user_id => $ary) {
				if ($_user_id == $user_id) {
					continue;
				}
				try {
					$query = $db->getQuery(true)
						->update('#__users')
						->set('user_new_privmsg = user_new_privmsg - ' . sizeof($ary))
						->set('user_unread_privmsg = user_unread_privmsg - ' . sizeof($ary))
						->where('user_id = '. $_user_id);

					$db->setQuery($query);
					$db->execute();
				} catch (Exception $e) {
					$status['error'][] = 'Error Could not update the number of PMs for user ' . $_user_id . ' for user ' . $user_id . ' was deleted: ' . $e->getMessage();
				}
			}
			//update the total user count
			$query = $db->getQuery(true)
				->update('#__config')
				->set('config_value = config_value - 1')
				->where('config_name = ' . $db->quote('num_users'));

			$db->setQuery($query);
			$db->execute();

			//check to see if this user was the newest user
			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from('#__config')
				->where('config_name = ' . $db->quote('newest_user_id'))
				->where('config_value = ' . $db->quote($user_id));

			$db->setQuery($query);
			if ($db->loadResult()) {
				//retrieve the new newest user
				$query = $db->getQuery(true)
					->select('user_id, username, user_colour')
					->from('#__users')
					->where('user_regdate = (SELECT MAX(user_regdate) FROM #__users)');

				$db->setQuery($query);
				$newest_user = $db->loadObject();
				if ($newest_user) {
					//update the newest username
					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = ' . $db->quote($newest_user->username))
						->where('config_name = ' . $db->quote('newest_username'));

					$db->setQuery($query);
					$db->execute();

					//update the newest userid
					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = ' . $newest_user->user_id)
						->where('config_name = ' . $db->quote('newest_user_id'));

					$db->setQuery($query);
					$db->execute();

					//set the correct new username color
					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = ' . $db->quote($newest_user->user_colour))
						->where('config_name = ' . $db->quote('newest_user_colour'));

					$db->setQuery($query);
					$db->execute();
				}
			}
			$status['debug'][] = JText::_('USER_DELETION') . ' ' . $user_id;
		} catch (Exception $e) {
			$status['error'][] = JText::_('USER_DELETION_ERROR') . $e->getMessage();
		}
		return $status;
	}

	/**
	 * @param bool $keepalive
	 *
	 * @return int
	 */
	function syncSessions($keepalive = false) {
		$return = 0;
		try {
			$debug = (defined('DEBUG_SYSTEM_PLUGIN') ? true : false);

			$login_type = $this->params->get('login_type');
			if ($login_type == 1) {
				if ($debug) {
					JFusionFunction::raiseNotice('syncSessions called', $this->getJname());
				}

				$options = array();
				$options['action'] = 'core.login.site';

				//phpbb variables
				$phpbb_cookie_prefix = $this->params->get('cookie_prefix');
				$mainframe = JFusionFactory::getApplication();
				$userid_cookie_value = $mainframe->input->cookie->get($phpbb_cookie_prefix . '_u', '');
				$sid_cookie_value = $mainframe->input->cookie->get($phpbb_cookie_prefix . '_sid', '');
				$phpbb_allow_autologin = $this->params->get('allow_autologin');
				$persistant_cookie = ($phpbb_allow_autologin) ? $mainframe->input->cookie->get($phpbb_cookie_prefix . '_k', '') : '';
				//joomla variables
				$JUser = JFactory::getUser();
				if (JPluginHelper::isEnabled ('system', 'remember')) {
					jimport('joomla.utilities.utility');
					$hash = JFusionFunction::getHash('JLOGIN_REMEMBER');
					$joomla_persistant_cookie = $mainframe->input->cookie->get($hash, '', 'raw');
				} else {
					$joomla_persistant_cookie = '';
				}

				if (!$JUser->get('guest', true)) {
					//user logged into Joomla so let's check for an active phpBB session

					if (!empty($phpbb_allow_autologin) && !empty($persistant_cookie) && !empty($sid_cookie_value)) {
						//we have a persistent cookie set so let phpBB handle the session renewal
						if ($debug) {
							JFusionFunction::raiseNotice('persistant cookie enabled and set so let phpbb handle renewal', $this->getJname());
						}
					} else {
						if ($debug) {
							JFusionFunction::raiseNotice('Joomla user is logged in', $this->getJname());
						}

						//check to see if the userid cookie is empty or if it contains the anonymous user, or if sid cookie is empty or missing
						if (empty($userid_cookie_value) || $userid_cookie_value == '1' || empty($sid_cookie_value)) {
							if ($debug) {
								JFusionFunction::raiseNotice('has a guest session', $this->getJname());
							}
							//find the userid attached to Joomla userid
							$joomla_userid = $JUser->get('id');
							$userlookup = JFusionFunction::lookupUser($this->getJname(), $joomla_userid);
							//get the user's info
							if (!empty($userlookup)) {
								$db = JFusionFactory::getDatabase($this->getJname());

								$query = $db->getQuery(true)
									->select('username_clean AS username, user_email as email')
									->from('#__users')
									->where('user_id = ' . $userlookup->userid);

								$db->setQuery($query);
								$user_identifiers = $db->loadObject();
								$userinfo = $this->getUser($user_identifiers);
							}

							if (!empty($userinfo) && (!empty($keepalive) || !empty($joomla_persistant_cookie))) {
								if ($debug) {
									JFusionFunction::raiseNotice('keep alive enabled or Joomla persistant cookie found, and found a valid phpbb3 user so calling createSession', $this->getJname());
								}
								//enable remember me as this is a keep alive function anyway
								$options['remember'] = 1;
								//create a new session

								try {
									$status = $this->createSession($userinfo, $options);
									if ($debug) {
										JFusionFunction::raise('notice', $status, $this->getJname());
									}
								} catch (Exception $e) {
									JfusionFunction::raiseError($e, $this->getJname());
								}
								//signal that session was changed
								$return = 1;
							} else {
								if ($debug) {
									JFusionFunction::raiseNotice('keep alive disabled or no persistant session found so calling Joomla\'s destorySession', $this->getJname());
								}
								$JoomlaUser = JFusionFactory::getUser('joomla_int');

								$userinfo = new stdClass;
								$userinfo->id = $JUser->id;
								$userinfo->username = $JUser->username;
								$userinfo->name = $JUser->name;
								$userinfo->email = $JUser->email;
								$userinfo->block = $JUser->block;
								$userinfo->activation = $JUser->activation;
								$userinfo->groups = $JUser->groups;
								$userinfo->password = $JUser->password;
								$userinfo->password_clear = $JUser->password_clear;

								$options['clientid'][] = '0';

								try {
									$status = $JoomlaUser->destroySession($userinfo, $options);
									if ($debug) {
										JFusionFunction::raise('notice', $status, $this->getJname());
									}
								} catch (Exception $e) {
									JFusionFunction::raiseError($e, $JoomlaUser->getJname());
								}
							}
						} else {
							if ($debug) {
								JFusionFunction::raiseNotice('user logged in', $this->getJname());
							}
						}
					}
				} elseif ((!empty($sid_cookie_value) || !empty($persistant_cookie)) && $userid_cookie_value != '1') {
					$db = JFusionFactory::getDatabase($this->getJname());
					$query = $db->getQuery(true)
						->select('b.group_name')
						->from('#__users as a')
						->join('LEFT OUTER', '#__groups as b ON a.group_id = b.group_id')
						->where('a.user_id = ' . $db->quote($userid_cookie_value));

					$db->setQuery($query);
					$group_name = $db->loadresult();
					if ($group_name !== 'BOTS') {
						if ($debug) {
							JFusionFunction::raiseNotice('Joomla has a guest session', $this->getJname());
						}
						//the user is not logged into Joomla and we have an active phpBB session
						if (!empty($joomla_persistant_cookie)) {
							if ($debug) {
								JFusionFunction::raiseNotice('Joomla persistant cookie found so let Joomla handle renewal', $this->getJname());
							}
						} elseif (empty($keepalive)) {
							if ($debug) {
								JFusionFunction::raiseNotice('Keep alive disabled so kill phpBBs session', $this->getJname());
							}
							//something fishy or person chose not to use remember me so let's destroy phpBBs session
							$phpbb_cookie_name = $this->params->get('cookie_prefix');
							$phpbb_cookie_path = $this->params->get('cookie_path');
							//baltie cookie domain fix
							$phpbb_cookie_domain = $this->params->get('cookie_domain');
							if ($phpbb_cookie_domain == 'localhost' || $phpbb_cookie_domain == '127.0.0.1') {
								$phpbb_cookie_domain = '';
							}
							//delete the cookies
							$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_u', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
							$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_sid', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
							$status['debug'][] = $this->addCookie($phpbb_cookie_name . '_k', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
							$return = 1;
						} elseif ($debug) {
							JFusionFunction::raiseNotice('Keep alive enabled so renew Joomla\'s session', $this->getJname());
						} else {
							if (!empty($persistant_cookie)) {
								$query = $db->getQuery(true)
									->select('user_id')
									->from('#__sessions_keys')
									->where('key_id = ' . $db->quote(md5($persistant_cookie)));

								if ($debug) {
									JFusionFunction::raiseNotice('Using phpBB persistant cookie to find user', $this->getJname());
								}
							} else {
								$query = $db->getQuery(true)
									->select('session_user_id')
									->from('#__sessions')
									->where('session_id = ' . $db->quote($sid_cookie_value));

								if ($debug) {
									JFusionFunction::raiseNotice('Using phpBB sid cookie to find user', $this->getJname());
								}
							}
							$db->setQuery($query);
							$userid = $db->loadresult();
							$userlookup = JFusionFunction::lookupUser($this->getJname(), $userid, false);
							if (!empty($userlookup)) {
								if ($debug) {
									JFusionFunction::raiseNotice('Found a phpBB user so attempting to renew Joomla\'s session.', $this->getJname());
								}
								//get the user's info
								$jdb = JFactory::getDBO();

								$query = $jdb->getQuery(true)
									->select('username, email')
									->from('#__users')
									->where('id = ' . $userlookup->id);

								$jdb->setQuery($query);
								$user_identifiers = $jdb->loadObject();
								$JoomlaUser = JFusionFactory::getUser('joomla_int');
								$userinfo = $JoomlaUser->getUser($user_identifiers);
								if (!empty($userinfo)) {
									global $JFusionActivePlugin;
									$JFusionActivePlugin = $this->getJname();

									try {
										$status = $JoomlaUser->createSession($userinfo, $options);
										if ($debug) {
											JFusionFunction::raise('notice', $status, $JoomlaUser->getJname());
										}
									} catch (Exception $e) {
										JfusionFunction::raiseError($e, $JoomlaUser->getJname());
									}
									//no need to signal refresh as Joomla will recognize this anyway
								}
							}
						}
					}
				}
			} else {
				if ($debug) {
					JFusionFunction::raiseNotice('syncSessions do not work in this login mode.', $this->getJname());
				}
			}
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		}
		return $return;
	}

	/**
	 * Function That find the correct user group index
	 *
	 * @param stdClass $userinfo
	 *
	 * @return int
	 */
	function getUserGroupIndex($userinfo)
	{
		$index = 0;

		$master = JFusionFunction::getMaster();
		if ($master) {
			$mastergroups = JFusionFunction::getUserGroups($master->name);

			foreach ($mastergroups as $key => $mastergroup) {
				if ($mastergroup) {
					$found = true;

					if (!isset($mastergroup->groups)) {
						$mastergroup->groups = array($mastergroup->defaultgroup);
					} else if (!in_array($mastergroup->defaultgroup, $mastergroup->groups)) {
						$mastergroup->groups[] = $mastergroup->defaultgroup;
					}

					//check to see if the default groups are different
					if ($mastergroup->defaultgroup != $userinfo->group_id ) {
						$found = false;
					} else {
						//check to see if member groups are different
						if (count($userinfo->groups) != count($mastergroup->groups)) {
							$found = false;
						} else {
							foreach ($mastergroup->groups as $gid) {
								if (!in_array($gid, $userinfo->groups)) {
									$found = false;
									break;
								}
							}
						}
					}
					if ($found) {
						$index = $key;
						break;
					}
				}
			}
		}
		return $index;
	}
}