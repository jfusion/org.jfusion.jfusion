<?php

/**
 * @package JFusion_universal
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion User Class for universal
 * For detailed descriptions on these functions please check JFusionUser
 * @package JFusion_universal
 */
class JFusionUser_universal extends JFusionUser
{
	/**
	 * @var $helper JFusionHelper_universal
	 */
	var $helper;

	/**
	 * @param object $userinfo
	 *
	 * @return null|object
	 */
	function getUser($userinfo)
	{
		// initialise some objects
		$email = $this->helper->getFieldType('EMAIL');
		$username = $this->helper->getFieldType('USERNAME');
		$userid = $this->helper->getFieldType('USERID');
		$result = null;
		if ($userid) {
			//get the identifier
			list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, $username->field, $email->field);

			$db = JFusionFactory::getDatabase($this->getJname());

			$field = $this->helper->getQuery(array('USERID', 'USERNAME', 'EMAIL', 'REALNAME', 'PASSWORD', 'SALT', 'GROUP', 'ACTIVE', 'INACTIVE', 'ACTIVECODE', 'FIRSTNAME', 'LASTNAME'));

			$query = $db->getQuery(true)
				->select($field)
				->from('#__' . $this->helper->getTable())
				->where($identifier_type . ' = ' . $db->quote($identifier));

			$db->setQuery($query);
			$result = $db->loadObject();
			if ($result) {
				$result->activation = '';
				if (isset($result->firstname)) {
					$result->name = $result->firstname;
					if (isset($result->lastname)) {
						$result->name .= ' ' . $result->lastname;
					}
				}
				$result->block = 0;

				if ( isset($result->inactive) ) {
					$inactive = $this->helper->getFieldType('INACTIVE');
					if ($inactive->value->on == $result->inactive ) {
						$result->block = 1;
					}
				}
				if ( isset($result->active) ) {
					$active = $this->helper->getFieldType('ACTIVE');
					if ($active->value->on != $result->active ) {
						$result->block = 1;
					}
				}
				unset($result->inactive, $result->active);

				if (isset($result->group_id)) {
					$result->group_id = base64_encode($result->group_id);
				}

				$groupGroup = $this->helper->getFieldType('GROUP', 'group');
				$groupUserid = $this->helper->getFieldType('USERID', 'group');
				$groupTable = $this->helper->getTable('group');
				if ($groupGroup && $groupUserid && $groupTable) {
					$field = $this->helper->getQuery(array('GROUP'), 'group');

					$query = $db->getQuery(true)
						->select($field)
						->from('#__' . $groupTable)
						->where($groupUserid->field . ' = ' . $db->quote($result->userid));

					$db->setQuery($query);
					$groups = $db->loadObjectList();

					foreach($groups as $group) {
						if (!isset($result->group_id)) {
							$result->group_id = base64_encode($group->group_id);
						}
						$result->groups[] = base64_encode($group->group_id);
					}
				}
			}
		}
		return $result;
	}

	/**
	 * @return string
	 */
	function getJname()
	{
		return 'universal';
	}

	/**
	 * @param object $userinfo
	 *
	 * @return array
	 */
	function deleteUser($userinfo)
	{
		//setup status array to hold debug info and errors
		$status = array('error' => array(), 'debug' => array());
		try {
			$userid = $this->helper->getFieldType('USERID');
			if (!$userid) {
				$status['error'][] = JText::_('USER_DELETION_ERROR') . ': ' . JText::_('UNIVERSAL_NO_USERID_SET');
			} else {
				$db = JFusionFactory::getDatabase($this->getJname());

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
					$status['debug'][] = JText::_('USER_DELETION') . ': ' . $userinfo->username;
				}
			}
		} catch (Exception $e) {
			$status['error'][] = JText::_('USER_DELETION_ERROR') . ': ' . $e->getMessage();
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
		$cookie_backup = $_COOKIE;
		$_COOKIE = array();
		$_COOKIE['jfusionframeless'] = true;
		$status = $this->curlLogout($userinfo, $options, 'no_brute_force');
		$_COOKIE = $cookie_backup;
		$status['debug'][] = $this->addCookie($this->params->get('cookie_name'), '', 0, $this->params->get('cookie_path'), $this->params->get('cookie_domain'), $this->params->get('secure'), $this->params->get('httponly'));
		return $status;
	}

	/**
	 * @param object $userinfo
	 * @param array $options
	 *
	 * @return array|string
	 */
	function createSession($userinfo, $options) {
		$status = array('error' => array(), 'debug' => array());
		//do not create sessions for blocked users
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
			$status['error'][] = JText::_('FUSION_BLOCKED_USER');
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
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array  $status
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function updatePassword($userinfo, &$existinguser, &$status)
	{
		$db = JFusionFactory::getDatabase($this->getJname());
		$maped = $this->helper->getMap();

		$userid = $this->helper->getFieldType('USERID');
		$password = $this->helper->getFieldType('PASSWORD');
		if (!$userid) {
			throw new RuntimeException(JText::_('UNIVERSAL_NO_USERID_SET'));
		} elseif (!$password) {
			throw new RuntimeException(JText::_('UNIVERSAL_NO_PASSWORD_SET'));
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

			$status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function updateUsername($userinfo, &$existinguser, &$status)
	{

	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array $status
	 *
	 * @return void
	 */
	function updateEmail($userinfo, &$existinguser, &$status)
	{
		$userid = $this->helper->getFieldType('USERID');
		$email = $this->helper->getFieldType('EMAIL');
		if (!$userid) {
			$status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . ': ' . JText::_('UNIVERSAL_NO_USERID_SET');
		} else if (!$email) {
			$status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . ': ' . JText::_('UNIVERSAL_NO_EMAIL_SET');
		} else {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable())
				->set($email->field . ' = ' . $db->quote($userinfo->email))
				->where($userid->field . '=' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array  $status
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	public function updateUsergroup($userinfo, &$existinguser, &$status)
	{
		//get the usergroup and determine if working in advanced or simple mode
		$usergroups = $this->getCorrectUserGroups($userinfo);
		if (empty($usergroups)) {
			throw new RuntimeException(JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
		} else {
			$db = JFusionFactory::getDatabase($this->getJname());

			$userUserid = $this->helper->getFieldType('USERID');
			$userGroup = $this->helper->getFieldType('GROUP');
			if (isset($userUserid) && isset($userGroup)) {
				$userTable = $this->helper->getTable();
			}

			$groupUserid = $this->helper->getFieldType('USERID', 'group');
			$groupGroup = $this->helper->getFieldType('GROUP', 'group');
			if (isset($groupUserid) && isset($groupGroup)) {
				$groupTable = $this->helper->getTable('group');
			}

			if (!isset($userUserid) && !isset($groupUserid)) {
				$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . JText::_('NO_USERID_MAPPED');
			} else if (!isset($userGroup) && !isset($groupGroup)) {
				$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . JText::_('NO_GROUP_MAPPED');
			} else {
				$usergroup = $usergroups[0];
				if ($this->helper->isDualGroup()) {
					$usergroups = $usergroup->groups;
					$usergroup = $usergroup->defaultgroup;
				}
				if (isset($userTable)) {
					$query = $db->getQuery(true)
						->update('#__' . $userTable)
						->set($userGroup->field . ' = ' . $db->quote(base64_decode($usergroup)))
						->where($userUserid->field . '=' . $db->quote($existinguser->userid));

					$db->setQuery($query);
					$db->execute();

					$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . base64_decode($existinguser->group_id) . ' -> ' . base64_decode($usergroup);
				}
				if (isset($groupTable)) {
					$groupMap = $this->helper->getMap('group');

					$query = $db->getQuery(true)
						->delete('#__' . $groupTable)
						->where($groupUserid->field . ' = ' . $db->quote($userinfo->userid));

					foreach ($groupMap as $value) {
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
						$addGroup = new stdClass;
						foreach ($groupMap as $value) {
							$field = $value->field;
							foreach ($value->type as $type) {
								switch ($type) {
									case 'USERID':
										$addGroup->$field = $existinguser->userid;
										break;
									case 'GROUP':
										$addGroup->$field = base64_decode($usergroup);
										break;
									case 'DEFAULT':
										$addGroup->$field = $this->helper->getValue($value->fieldtype, $value->value, $userinfo);
										break;
								}
							}
						}
						$db->insertObject('#__' . $groupTable, $addGroup);

						$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . base64_decode($existinguser->group_id) . ' -> ' . base64_decode($usergroup);
					}
				}
			}
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
		if ($this->helper->isDualGroup()) {
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
		} else {
			$update_groups = parent::executeUpdateUsergroup($userinfo, $existinguser, $status);
		}
		return $update_groups;
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array  $status
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function blockUser($userinfo, &$existinguser, &$status)
	{
		$userid = $this->helper->getFieldType('USERID');
		$active = $this->helper->getFieldType('ACTIVE');
		$inactive = $this->helper->getFieldType('INACTIVE');

		if (!$userid) {
			throw new RuntimeException(JText::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$active && !$inactive) {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . JText::_('UNIVERSAL_NO_ACTIVE_OR_INACTIVE_SET');
		} else {
			$userStatus = null;
			if ($userinfo->block) {
				if ( isset($inactive) ) {
					$userStatus = $inactive->value->on;
				}
				if ( isset($active) ) {
					$userStatus = $active->value->off;
				}
			} else {
				if ( isset($inactive) ) {
					$userStatus = $inactive->value->off;
				}
				if ( isset($active) ) {
					$userStatus = $active->value->on;
				}
			}
			if ($userStatus != null) {
				$db = JFusionFactory::getDatabase($this->getJname());

				$query = $db->getQuery(true)
					->update('#__' . $this->helper->getTable())
					->set($active->field . ' = ' . $db->quote($userStatus))
					->where($userid->field . ' = ' . $db->quote($existinguser->userid));

				$db->setQuery($query);
				$db->execute();

				$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
			}
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array  $status
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function unblockUser($userinfo, &$existinguser, &$status)
	{
		$userid = $this->helper->getFieldType('USERID');
		$active = $this->helper->getFieldType('ACTIVE');
		$inactive = $this->helper->getFieldType('INACTIVE');
		if (!$userid) {
			throw new RuntimeException(JText::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$active && !$inactive) {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . JText::_('UNIVERSAL_NO_ACTIVE_OR_INACTIVE_SET');
		} else {
			$userStatus = null;
			if ( isset($inactive) ) $userStatus = $inactive->value->off;
			if ( isset($active) ) $userStatus = $active->value->on;

			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable())
				->set($active->field . ' = ' . $db->quote($userStatus))
				->where($userid->field . ' = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();
			$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array  $status
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function activateUser($userinfo, &$existinguser, &$status)
	{
		$userid = $this->helper->getFieldType('USERID');
		$activecode = $this->helper->getFieldType('ACTIVECODE');
		if (!$userid) {
			throw new RuntimeException(JText::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$activecode) {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . JText::_('UNIVERSAL_NO_ACTIVECODE_SET');
		} else {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable())
				->set($activecode->field . ' = ' . $db->quote($userinfo->activation))
				->where($userid->field . ' = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();

			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	/**
	 * @param object $userinfo
	 * @param object $existinguser
	 * @param array  $status
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	function inactivateUser($userinfo, &$existinguser, &$status)
	{
		$userid = $this->helper->getFieldType('USERID');
		$activecode = $this->helper->getFieldType('ACTIVECODE');
		if (!$userid) {
			throw new RuntimeException(JText::_('UNIVERSAL_NO_USERID_SET'));
		} else if (!$activecode) {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . JText::_('UNIVERSAL_NO_ACTIVECODE_SET');
		} else {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__' . $this->helper->getTable())
				->set($activecode->field . ' = ' . $db->quote($userinfo->activation))
				->where($userid->field . ' = ' . $db->quote($existinguser->userid));

			$db->setQuery($query);
			$db->execute();
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	/**
	 * @param object $userinfo
	 * @param array $status
	 *
	 * @return void
	 */
	function createUser($userinfo, &$status)
	{
		try {
			$usergroups = $this->getCorrectUserGroups($userinfo);
			if(empty($usergroups)) {
				throw new RuntimeException(JText::_('USERGROUP_MISSING'));
			} else {
				$usergroup = $usergroups[0];
				if ($this->helper->isDualGroup()) {
					$usergroups = $usergroup->groups;
					$usergroup = $usergroup->defaultgroup;
				}

				$userid = $this->helper->getFieldType('USERID');
				if(empty($userid)) {
					throw new RuntimeException(JText::_('UNIVERSAL_NO_USERID_SET'));
				} else {
					$password = $this->helper->getFieldType('PASSWORD');
					if(empty($password)) {
						throw new RuntimeException(JText::_('UNIVERSAL_NO_PASSWORD_SET'));
					} else {
						$email = $this->helper->getFieldType('EMAIL');
						if(empty($email)) {
							throw new RuntimeException(JText::_('UNIVERSAL_NO_EMAIL_SET'));
						} else {
							$user = new stdClass;
							$maped = $this->helper->getMap();
							$db = JFusionFactory::getDatabase($this->getJname());
							foreach ($maped as $value) {
								$field = $value->field;
								foreach ($value->type as $type) {
									switch ($type) {
										case 'USERID':
											$query = 'SHOW COLUMNS FROM #__' . $this->helper->getTable() . ' where Field = ' . $db->quote($field) . ' AND Extra like \'%auto_increment%\'';
											$db->setQuery($query);
											$fieldsList = $db->loadObject();
											if ($fieldsList) {
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
											if ($userinfo->block) {
												$user->$field = $value->value->off;
											} else {
												$user->$field = $value->value->on;
											}
											break;
										case 'INACTIVE':
											if ($userinfo->block) {
												$user->$field = $value->value->on;
											} else {
												$user->$field = $value->value->off;
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

							$groupTable = $this->helper->getTable('group');
							if (isset($groupTable)) {
								$groupUserid = $this->helper->getFieldType('USERID', 'group');
								$groupGroup = $this->helper->getFieldType('GROUP', 'group');
								if (!isset($groupUserid)) {
									$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . JText::_('NO_USERID_MAPPED');
								} else if (!isset($groupGroup)) {
									$status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . JText::_('NO_GROUP_MAPPED');
								} else {
									$groupMap = $this->helper->getMap('group');

									$addGroup = new stdClass;
									foreach ($usergroups as $usergroup) {
										foreach ($groupMap as $value) {
											$field = $value->field;
											foreach ($value->type as $type) {
												switch ($type) {
													case 'USERID':
														$field2 = $userid->field;
														$addGroup->$field = $user->$field2;
														break;
													case 'GROUP':
														$addGroup->$field = base64_decode($usergroup);
														break;
													case 'DEFAULT':
														$addGroup->$field = $this->helper->getValue($value->fieldtype, $value->value, $userinfo);
														break;
												}
											}
										}
										$db->insertObject('#__' . $groupTable, $addGroup, $groupUserid->field);
									}
								}
							}
							//return the good news
							$status['debug'][] = JText::_('USER_CREATION');
							$status['userinfo'] = $this->getUser($userinfo);
						}
					}
				}
			}
		} catch (Exception $e) {
			$status['error'][] = JText::_('USER_CREATION_ERROR') . ': ' . $e->getMessage();
		}
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

		if ($this->helper->isDualGroup()) {
			$master = JFusionFunction::getMaster();
			if ($master) {
				$mastergroups = JFusionFunction::getUserGroups($master->name);

				foreach ($mastergroups as $key => $mastergroup) {
					if ($mastergroup) {
						$found = true;
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
		} else {
			$index = parent::getUserGroupIndex($userinfo);
		}
		return $index;
	}
}
