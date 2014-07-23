<?php namespace JFusion\Plugins\universal;

/**
 * @package JFusion_universal
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
use JFusion\Factory;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;
use RuntimeException;
use stdClass;

defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion User Class for universal
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * @package JFusion_universal
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
	function getUser(Userinfo $userinfo)
	{
		// initialise some objects
		$email = $this->helper->getFieldType('EMAIL');
		$username = $this->helper->getFieldType('USERNAME');
		$userid = $this->helper->getFieldType('USERID');
		$user = null;
		if ($userid) {
			//get the identifier
			list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, $username->field, $email->field, $userid->field);

			$db = Factory::getDatabase($this->getJname());

			$field = $this->helper->getQuery(array('USERID', 'USERNAME', 'EMAIL', 'REALNAME', 'PASSWORD', 'SALT', 'GROUP', 'ACTIVE', 'INACTIVE', 'ACTIVECODE', 'FIRSTNAME', 'LASTNAME'));

			$query = $db->getQuery(true)
				->select($field)
				->from('#__' . $this->helper->getTable())
				->where($identifier_type . ' = ' . $db->quote($identifier));

			$db->setQuery($query);
			$result = $db->loadObject();
			if ($result ) {
				$result->activation = '';
				if (isset($result->firstname)) {
					$result->name = $result->firstname;
					if (isset($result->lastname)) {
						$result->name .= ' ' . $result->lastname;
					}
				}
				$result->block = false;

				if ( isset($result->inactive) ) {
					$inactive = $this->helper->getFieldType('INACTIVE');
					if ($inactive->value['on'] == $result->inactive ) {
						$result->block = true;
					}
				}
				if ( isset($result->active) ) {
					$active = $this->helper->getFieldType('ACTIVE');
					if ($active->value['on'] != $result->active ) {
						$result->block = true;
					}
				}
				unset($result->inactive, $result->active);

				$group = $this->helper->getFieldType('GROUP', 'group');
				$userid = $this->helper->getFieldType('USERID', 'group');
				$groupt = $this->helper->getTable('group');
				if ( !isset($result->group_id) && $group && $userid && $groupt ) {
					$field = $this->helper->getQuery(array('GROUP'), 'group');

					$query = $db->getQuery(true)
						->select($field)
						->from('#__' . $groupt)
						->where($userid->field . ' = ' . $db->quote($result->userid));

					$db->setQuery($query);
					$result2 = $db->loadObject();

					if ($result2) {
						$result->group_id = base64_encode($result2->group_id);
					}
				}
				$user = new Userinfo($this->getJname());
				$user->bind($result);
			}
		}
		return $user;
	}

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 * @return array
	 */
	function deleteUser(Userinfo $userinfo)
	{
		//setup status array to hold debug info and errors
		$status = array('error' => array(), 'debug' => array());
		$userid = $this->helper->getFieldType('USERID');
		if (!$userid) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_USERID_SET'));
		} else {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->delete('#__' . $this->helper->getTable())
				->where($userid->field . ' = ' . $db->quote($userinfo->userid));

			$db->setQuery($query);
			$db->execute();

			$group = $this->helper->getFieldType('GROUP', 'group');
			if ( isset($group) ) {
				$userid = $this->helper->getFieldType('USERID', 'group');

				$query = $db->getQuery(true)
					->delete('#__' . $this->helper->getTable('group'))
					->where($userid->field . ' = ' . $db->quote($userinfo->userid));

				$maped = $this->helper->getMap('group');
				foreach ($maped as $value) {
					$field = $value->field;
					foreach ($value->type as $type) {
						switch ($type) {
							case 'DEFAULT':
								if ($value->fieldtype == 'VALUE') {
									$query->where($field . ' = ' . $db->quote($value->value));
								}
								break;
						}
					}
				}
				$db->setQuery($query);
				$db->execute();
				$status['debug'][] = Text::_('USER_DELETION') . ': ' . $userinfo->username;
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
		$cookie_backup = $_COOKIE;
		$_COOKIE = array();
		$_COOKIE['jfusionframeless'] = true;
		$status = $this->curlLogout($userinfo, $options, 'no_brute_force');
		$_COOKIE = $cookie_backup;
		$status['debug'][] = $this->addCookie($this->params->get('cookie_name'), '', 0, $this->params->get('cookie_path'), $this->params->get('cookie_domain'), $this->params->get('secure'), $this->params->get('httponly'));
		return $status;
	}

	/**
	 * @param Userinfo $userinfo
	 * @param array $options
	 *
	 * @return array|string
	 */
	function createSession(Userinfo $userinfo, $options) {
		$status = array('error' => array(), 'debug' => array());
		//do not create sessions for blocked users
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
			$status['error'][] = Text::_('FUSION_BLOCKED_USER');
		} else {
			$cookie_backup = $_COOKIE;
			$_COOKIE = array();
			$_COOKIE['jfusionframeless'] = true;
			$status = $this->curlLogin($userinfo, $options, 'no_brute_force');
			$_COOKIE = $cookie_backup;
		}
		return $status;
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function updatePassword(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$db = Factory::getDatabase($this->getJname());
		$maped = $this->helper->getMap();

		$userid = $this->helper->getFieldType('USERID');
		$password = $this->helper->getFieldType('PASSWORD');
		if (!$userid) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_USERID_SET'));
		} elseif (!$password) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_PASSWORD_SET'));
		} else {
			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable());

			foreach ($maped as $value) {
				foreach ($value->type as $type) {
					switch ($type) {
						case 'PASSWORD':
							$query->set($value->field . ' = ' . $db->quote($this->helper->getHashedPassword($value->fieldtype, $value->value, $userinfo)));
							break;
						case 'SALT':
							if (!isset($userinfo->password_salt)) {
								$query->set($value->field . ' = ' . $db->quote($this->helper->getValue($value->fieldtype, $value->value, $userinfo)));
							} else {
								$query->set($value->field . ' = ' . $db->quote($existinguser->password_salt));
							}
							break;
					}
				}
			}

			$query->where($userid->field . ' = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$this->debugger->add('debug', Text::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
		}
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @return void
	 */
	function updateUsername(Userinfo $userinfo, Userinfo &$existinguser)
	{

	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
	function updateEmail(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$userid = $this->helper->getFieldType('USERID');
		$email = $this->helper->getFieldType('EMAIL');
		if (!$userid) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$email) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_EMAIL_SET'));
		} else {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable())
				->set($email->field . ' = ' . $db->quote($userinfo->email))
				->where($userid->field . '=' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$this->debugger->add('debug', Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
		}
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
	{
		//get the usergroup and determine if working in advanced or simple mode
		$usergroups = $this->getCorrectUserGroups($userinfo);
		if (empty($usergroups)) {
			throw new RuntimeException(Text::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
		} else {
			$db = Factory::getDatabase($this->getJname());

			$userid = $this->helper->getFieldType('USERID');
			$group = $this->helper->getFieldType('GROUP');

			if ( isset($group) && isset($userid) ) {
				$table = $this->helper->getTable();
				$type = 'user';
			} else {
				$table = $this->helper->getTable('group');
				$userid = $this->helper->getFieldType('USERID', 'group');
				$group = $this->helper->getFieldType('GROUP', 'group');
				$type = 'group';
			}
			if ( !isset($userid) ) {
				$this->debugger->add('debug', Text::_('GROUP_UPDATE') . ': ' . Text::_('NO_USERID_MAPPED'));
			} else if ( !isset($group) ) {
				$this->debugger->add('debug', Text::_('GROUP_UPDATE') . ': ' . Text::_('NO_GROUP_MAPPED'));
			} else if ($type == 'user') {
				$usergroup = $usergroups[0];

				$query = $db->getQuery(true)
					->update('#__' . $table)
					->set($group->field . ' = ' . $db->quote(base64_decode($usergroup)))
					->where($userid->field . '=' . $db->quote($existinguser->userid));

				$db->setQuery($query);
				$db->execute();

				$this->debugger->add('debug', Text::_('GROUP_UPDATE') . ': ' . base64_decode($existinguser->group_id) . ' -> ' . base64_decode($usergroup));
			} else {
				$maped = $this->helper->getMap('group');

				$query = $db->getQuery(true)
					->delete('#__' . $this->helper->getTable('group'))
					->where($userid->field . ' = ' . $db->quote($userinfo->userid));

				foreach ($maped as $value) {
					$field = $value->field;
					foreach ($value->type as $type) {
						switch ($type) {
							case 'DEFAULT':
								if ($value->fieldtype == 'VALUE') {
									$query->where($field . ' = ' . $db->quote($value->value));
								}
								break;
						}
					}
				}

				$db->setQuery($query);
				$db->execute();

				foreach ($usergroups as $usergroup) {
					$addgroup = new stdClass;
					foreach ($maped as $value) {
						$field = $value->field;
						foreach ($value->type as $type) {
							switch ($type) {
								case 'USERID':
									$addgroup->$field = $existinguser->userid;
									break;
								case 'GROUP':
									$addgroup->$field = base64_decode($usergroup);
									break;
								case 'DEFAULT':
									$addgroup->$field = $this->helper->getValue($value->fieldtype, $value->value, $userinfo);
									break;
							}
						}
					}
					$db->insertObject('#__' . $this->helper->getTable('group'), $addgroup );

					$this->debugger->add('debug', Text::_('GROUP_UPDATE') . ': ' . base64_decode($existinguser->group_id) . ' -> ' . base64_decode($usergroup));
				}
			}
		}
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function blockUser(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$userid = $this->helper->getFieldType('USERID');
		$active = $this->helper->getFieldType('ACTIVE');
		$inactive = $this->helper->getFieldType('INACTIVE');

		if (!$userid) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$active && !$inactive) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_ACTIVE_OR_INACTIVE_SET'));
		} else {
			$userStatus = null;
			if ($userinfo->block) {
				if ( isset($inactive) ) {
					$userStatus = $inactive->value['on'];
				}
				if ( isset($active) ) {
					$userStatus = $active->value['off'];
				}
			} else {
				if ( isset($inactive) ) {
					$userStatus = $inactive->value['off'];
				}
				if ( isset($active) ) {
					$userStatus = $active->value['on'];
				}
			}
			if ($userStatus != null) {
				$db = Factory::getDatabase($this->getJname());

				$query = $db->getQuery(true)
					->update('#__' . $this->helper->getTable())
					->set($active->field . ' = ' . $db->quote($userStatus))
					->where($userid->field . ' = ' . $db->quote($existinguser->userid));

				$db->setQuery($query);
				$db->execute();

				$this->debugger->add('debug', Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
			}
		}
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function unblockUser(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$userid = $this->helper->getFieldType('USERID');
		$active = $this->helper->getFieldType('ACTIVE');
		$inactive = $this->helper->getFieldType('INACTIVE');
		if (!$userid) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$active && !$inactive) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_ACTIVE_OR_INACTIVE_SET'));
		} else {
			$userStatus = null;
			if ( isset($inactive) ) $userStatus = $inactive->value['off'];
			if ( isset($active) ) $userStatus = $active->value['on'];

			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable())
				->set($active->field . ' = ' . $db->quote($userStatus))
				->where($userid->field . ' = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$this->debugger->add('debug', Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
		}
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function activateUser(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$userid = $this->helper->getFieldType('USERID');
		$activecode = $this->helper->getFieldType('ACTIVECODE');
		if (!$userid) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$activecode) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_ACTIVECODE_SET'));
		} else {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable())
				->set($activecode->field . ' = ' . $db->quote($userinfo->activation))
				->where($userid->field . ' = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$this->debugger->add('debug', Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
		}
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function inactivateUser(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$userid = $this->helper->getFieldType('USERID');
		$activecode = $this->helper->getFieldType('ACTIVECODE');
		if (!$userid) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$activecode) {
			throw new RuntimeException(Text::_('UNIVERSAL_NO_ACTIVECODE_SET'));
		} else {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable())
				->set($activecode->field . ' = ' . $db->quote($userinfo->activation))
				->where($userid->field . ' = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$this->debugger->add('debug', Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
		}
	}

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
	function createUser(Userinfo $userinfo)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		if(empty($usergroups)) {
			throw new RuntimeException(Text::_('USERGROUP_MISSING'));
		} else {
			$usergroup = $usergroups[0];

			$userid = $this->helper->getFieldType('USERID');
			if(empty($userid)) {
				throw new RuntimeException(Text::_('UNIVERSAL_NO_USERID_SET'));
			} else {
				$password = $this->helper->getFieldType('PASSWORD');
				if(empty($password)) {
					throw new RuntimeException(Text::_('UNIVERSAL_NO_PASSWORD_SET'));
				} else {
					$email = $this->helper->getFieldType('EMAIL');
					if(empty($email)) {
						throw new RuntimeException(Text::_('UNIVERSAL_NO_EMAIL_SET'));
					} else {
						$user = new stdClass;
						$maped = $this->helper->getMap();
						$db = Factory::getDatabase($this->getJname());
						foreach ($maped as $value) {
							$field = $value->field;
							foreach ($value->type as $type) {
								switch ($type) {
									case 'USERID':
										$query = 'SHOW COLUMNS FROM #__' . $this->helper->getTable() . ' where Field = ' . $db->quote($field) . ' AND Extra like \'%auto_increment%\'';
										$db->setQuery($query);
										$fieldslist = $db->loadObject();
										if ($fieldslist) {
											$user->$field = NULL;
										} else {
											$f = $this->helper->getQuery(array('USERID'));

											$query = $db->getQuery(true)
												->select($f)
												->from('#__' . $this->helper->getTable())
												->order('userid DESC');

											$db->setQuery($query, 0 , 1);
											$value = $db->loadResult();
											if (!$value) {
												$value = 1;
											} else {
												$value++;
											}
											$user->$field = $value;
										}
										break;
									case 'REALNAME':
										$user->$field = $userinfo->name;
										break;
									case 'FIRSTNAME':
										list($firstname,) = explode(' ', $userinfo->name , 2);
										$user->$field = $firstname;
										break;
									case 'LASTNAME':
										list(, $lastname) = explode(' ', $userinfo->name , 2);
										$user->$field = $lastname;
										break;
									case 'GROUP':
										$user->$field = base64_decode($usergroup);
										break;
									case 'USERNAME':
										$user->$field = $userinfo->username;
										break;
									case 'EMAIL':
										$user->$field = $userinfo->email;
										break;
									case 'ACTIVE':
										if ($userinfo->block){
											$user->$field = $value->value['off'];
										} else {
											$user->$field = $value->value['on'];
										}
										break;
									case 'INACTIVE':
										if ($userinfo->block){
											$user->$field = $value->value['on'];
										} else {
											$user->$field = $value->value['off'];
										}
										break;
									case 'PASSWORD':
										$user->$field = $this->helper->getHashedPassword($value->fieldtype, $value->value, $userinfo);
										break;
									case 'SALT':
										if (!isset($userinfo->password_salt)) {
											$user->$field = $this->helper->getValue($value->fieldtype, $value->value, $userinfo);
										} else {
											$user->$field = $userinfo->password_salt;
										}
										break;
									case 'DEFAULT':
										$val = isset($value->value) ? $value->value : null;
										$user->$field = $this->helper->getValue($value->fieldtype, $val, $userinfo);
										break;
								}
							}
						}
						//now append the new user data
						$db->insertObject('#__' . $this->helper->getTable(), $user, $userid->field );

						$group = $this->helper->getFieldType('GROUP');

						if ( !isset($group) ) {
							$groupuserid = $this->helper->getFieldType('USERID', 'group');
							$group = $this->helper->getFieldType('GROUP', 'group');
							if ( !isset($groupuserid) ) {
								$this->debugger->add('debug', Text::_('GROUP_UPDATE') . ': ' . Text::_('NO_USERID_MAPPED'));
							} else if ( !isset($group) ) {
								$this->debugger->add('debug', Text::_('GROUP_UPDATE') . ': ' . Text::_('NO_GROUP_MAPPED'));
							} else {
								$addgroup = new stdClass;

								$maped = $this->helper->getMap('group');
								foreach ($maped as $value) {
									$field = $value->field;
									foreach ($value->type as $type) {
										switch ($type) {
											case 'USERID':
												$field2 = $userid->field;
												$addgroup->$field = $user->$field2;
												break;
											case 'GROUP':
												$addgroup->$field = base64_decode($usergroup);
												break;
											case 'DEFAULT':
												$addgroup->$field = $this->helper->getValue($value->fieldtype, $value->value, $userinfo);
												break;
										}
									}
								}
								$db->insertObject('#__' . $this->helper->getTable('group'), $addgroup, $groupuserid->field);
							}
						}
						//return the good news
						$this->debugger->add('debug', Text::_('USER_CREATION'));
						$this->debugger->set('userinfo', $this->getUser($userinfo));
					}
				}
			}
		}
	}
}
