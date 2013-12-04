<?php

/**
* @package JFusion_SMF
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion User Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * @package JFusion_SMF
 */
class JFusionUser_smf2 extends JFusionUser {

    /**
     * @param object $userinfo
     *
     * @return null|object
     */
    function getUser($userinfo)
    {
	    try {
		    //get the identifier
		    list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.member_name', 'a.email_address');

		    // initialise some objects
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('a.id_member as userid, a.member_name as username, a.real_name as name, a.email_address as email, a.passwd as password, a.password_salt as password_salt, a.validation_code as activation, a.is_activated, NULL as reason, a.last_login as lastvisit, a.id_group as group_id, a.id_post_group as postgroup, a.additional_groups')
			    ->from('#__members as a')
		        ->where($identifier_type . ' = ' . $db->Quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();

		    if ($result) {
			    if ($result->group_id == 0) {
				    $result->group_name = 'Default Usergroup';
			    } else {
				    $query = $db->getQuery(true)
					    ->select('group_name')
					    ->from('#__membergroups')
					    ->where('id_group = ' . $result->group_id);

				    $db->setQuery($query);
				    $result->group_name = $db->loadResult();
			    }
			    $result->groups = array($result->group_id);
			    $result->groupnames = array($result->group_name);

			    if (!empty($result->additional_groups)) {
				    $groups = explode(',', $result->additional_groups);

				    foreach($groups as $group) {
					    $query = $db->getQuery(true)
						    ->select('groupName')
						    ->from('#__membergroups')
						    ->where('ID_GROUP = ' . (int)$group);

					    $db->setQuery($query);
					    $result->groups[] = $group;
					    $result->groupnames[] = $db->loadResult();
				    }
			    }

			    //Check to see if they are banned
			    $query = $db->getQuery(true)
				    ->select('id_ban_group, expire_time')
				    ->from('#__ban_groups')
				    ->where('name = "' . $result->username . '"');

			    $db->setQuery($query);
			    $expire_time = $db->loadObject();
			    if ($expire_time) {
				    if ($expire_time->expire_time == '' || $expire_time->expire_time > time() ) {
					    $result->block = 1;
				    } else {
					    $result->block = 0;
				    }
			    } else {
				    $result->block = 0;
			    }

			    if ($result->is_activated == 1) {
				    $result->activation = '';
			    }
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = null;
	    }
        return $result;
    }

    /**
     * @return string
     */
    function getJname()
    {
        return 'smf2';
    }

    /**
     * @param object $userinfo
     *
     * @return array
     */
    function deleteUser($userinfo)
    {
	    try {
	        //setup status array to hold debug info and errors
	        $status = array('error' => array(), 'debug' => array());
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->delete('#__members')
			    ->where('member_name = ' . $db->quote($userinfo->username));

			$db->setQuery($query);
		    $db->execute();

		    //update the stats
		    $query = $db->getQuery(true)
			    ->update('#__settings')
			    ->set('value = value - 1')
			    ->where('variable = ' . $db->quote('totalMembers'));

		    $db->setQuery($query);
		    $db->execute();

		    $query = $db->getQuery(true)
			    ->select('MAX(id_member) as id_member')
			    ->from('#__members')
			    ->where('is_activated = 1');

		    $db->setQuery($query);
		    $resultID = $db->loadObject();
		    if (!$resultID) {
			    //return the error
			    $status['error'][] = JText::_('USER_DELETION_ERROR');
		    } else {
			    $query = $db->getQuery(true)
				    ->select('real_name as name')
				    ->from('#__members')
				    ->where('id_member = ' . $db->quote($resultID->id_member));

			    $db->setQuery($query, 0 , 1);
			    $resultName = $db->loadObject();
			    if (!$resultName) {
				    //return the error
				    $status['error'][] = JText::_('USER_DELETION_ERROR');
			    } else {
				    $query = 'REPLACE INTO #__settings (variable, value) VALUES (\'latestMember\', ' . $resultID->id_member . '), (\'latestRealName\', ' . $db->quote($resultName->name) . ')';
				    $db->setQuery($query);
				    $db->execute();

				    $status['debug'][] = JText::_('USER_DELETION') . ' ' . $userinfo->username;
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' .  $e->getMessage();
	    }
		return $status;
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
	        $status['debug'][] = $this->addCookie($this->params->get('cookie_name'), '', 0, $this->params->get('cookie_path'), $this->params->get('cookie_domain'), $this->params->get('secure'), $this->params->get('httponly'));

		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->delete('#__log_online')
			    ->where('id_member = ' . $userinfo->userid);

		    $db->setQuery($query, 0, 1);
		    $db->execute();
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
		return $status;
     }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array|string
     */
    function createSession($userinfo, $options)
    {
        $status = array('error' => array(), 'debug' => array());
		//do not create sessions for blocked users
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
		} else {
            $status = $this->curlLogin($userinfo, $options, $this->params->get('brute_force'));
        }
		return $status;
    }

    /**
     * @param string $username
     *
     * @return string
     */
    function filterUsername($username)
    {
        //no username filtering implemented yet
        return $username;
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status)
    {
	    try {
	        $existinguser->password = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
	        $existinguser->password_salt = substr(md5(rand()), 0, 4);
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__members')
			    ->set('passwd = ' . $db->quote($existinguser->password))
			    ->set('password_salt = ' . $db->quote($existinguser->password_salt))
			    ->where('id_member = ' . (int)$existinguser->userid);

	        $db = JFusionFactory::getDatabase($this->getJname());
	        $db->setQuery($query);

		    $db->execute();
		    $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR')  . $e->getMessage();
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
	    try {
		    //we need to update the email
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__members')
			    ->set('email_address = ' . $db->quote($userinfo->email))
			    ->where('id_member = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $e->getMessage();
	    }
    }
    
    /**
     * updateUsergroup
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status)
    {
	    try {
	        //get the usergroup and determine if working in advanced or simple mode

		    $usergroups = $this->getCorrectUserGroups($userinfo);
	        if (empty($usergroups)) {
		        throw new RuntimeException(JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
	        } else {
		        $usergroup = $usergroups[0];

		        if (!isset($usergroup->groups)) {
			        $usergroup->groups = array();
		        }

				$db = JFusionFactory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->update('#__members')
			        ->set('id_group = ' . $db->quote($usergroup->defaultgroup));

		        if ($this->params->get('compare_postgroup', false) ) {
			        $query->set('id_post_group = ' . $db->quote($usergroup->postgroup));
		        }
		        if ($this->params->get('compare_membergroups', true) ) {
			        $query->set('additional_groups = ' . $db->quote(join(',', $usergroup->groups)));
		        }
		        $query->where('id_member = ' . (int)$existinguser->userid);



				$db->setQuery($query);
		        $db->execute();

		        $groups = $usergroup->groups;
		        $groups[] = $usergroup->defaultgroup;

		        $existinggroups = $existinguser->groups;
		        $existinggroups[] = $existinguser->group_id;

		        $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinggroups) . ' -> ' . implode(' , ', $groups);
	        }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $e->getMessage();
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
		} else if ($this->params->get('compare_postgroup', false) && $usergroup->postgroup != $existinguser->postgroup ) {
			$update_groups = true;
		} elseif ($this->params->get('compare_membergroups', true)) {
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
    function blockUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $ban = new stdClass;
		    $ban->id_ban_group = NULL;
		    $ban->name = $existinguser->username;
		    $ban->ban_time = time();
		    $ban->expire_time = NULL;
		    $ban->cannot_access = 1;
		    $ban->cannot_register = 0;
		    $ban->cannot_post = 0;
		    $ban->cannot_login = 0;
		    $ban->reason = 'You have been banned from this software. Please contact your site admin for more details';

		    //now append the new user data
		    try {
			    $db->insertObject('#__ban_groups', $ban, 'id_ban_group' );
		    } catch (Exception $e) {
			    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
		    }

		    $ban_item = new stdClass;
		    $ban_item->id_ban_group = $ban->id_ban_group;
		    $ban_item->id_member = $existinguser->userid;
		    $db->insertObject('#__ban_items', $ban_item, 'id_ban' );
		    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function unblockUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->delete('#__ban_groups')
			    ->where('name = ' . $db->quote($existinguser->username));

		    $db->setQuery($query);
		    $db->execute();

		    $query = $db->getQuery(true)
			    ->delete('#__ban_items')
			    ->where('id_member = ' . $existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function activateUser($userinfo, &$existinguser, &$status)
    {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__members')
			    ->set('is_activated = 1')
			    ->set('validation_code = ' . $db->quote(''))
			    ->where('id_member = ' . (int)$existinguser->userid);

	        $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function inactivateUser($userinfo, &$existinguser, &$status)
    {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__members')
			    ->set('is_activated = 0')
			    ->set('validation_code = ' . $db->quote($userinfo->activation))
			    ->where('id_member = ' . (int)$existinguser->userid);

	        $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
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
		    //we need to create a new SMF user
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $usergroups = $this->getCorrectUserGroups($userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
		    } else {
			    $usergroup = $usergroups[0];

			    if (!isset($usergroup->groups)) {
				    $usergroup->groups = array();
			    }

			    //prepare the user variables
			    $user = new stdClass;
			    $user->id_member = NULL;
			    $user->member_name = $userinfo->username;
			    $user->real_name = $userinfo->name;
			    $user->email_address = $userinfo->email;

			    if (isset($userinfo->password_clear)) {
				    $user->passwd = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
				    $user->password_salt = substr(md5(rand()), 0, 4);
			    } else {
				    $user->passwd = $userinfo->password;

				    if (!isset($userinfo->password_salt)) {
					    $user->password_salt = substr(md5(rand()), 0, 4);
				    } else {
					    $user->password_salt = $userinfo->password_salt;
				    }
			    }

			    $user->posts = 0 ;
			    $user->date_registered = time();

			    if ($userinfo->activation){
				    $user->is_activated = 0;
				    $user->validation_code = $userinfo->activation;
			    } else {
				    $user->is_activated = 1;
				    $user->validation_code = '';
			    }

			    $user->personal_text = '';
			    $user->pm_email_notify = 1;
			    $user->hide_email = 1;
			    $user->id_theme = 0;

			    $user->id_group = $usergroup->defaultgroup;
			    $user->additional_groups = join(',', $usergroup->groups);
			    $user->id_post_group = $usergroup->postgroup;

			    //now append the new user data
			    $db->insertObject('#__members', $user, 'id_member' );

			    //update the stats

			    $query = $db->getQuery(true)
				    ->update('#__settings')
				    ->set('value = value + 1')
				    ->where('variable = ' . $db->quote('totalMembers'));

			    $db->setQuery($query);
			    $db->execute();

			    $date = strftime('%Y-%m-%d');

			    $query = $db->getQuery(true)
				    ->update('#__log_activity')
				    ->set('registers = registers + 1')
				    ->where('date = ' . $db->quote($date));


			    $db->setQuery($query);
			    $db->execute();

			    $query = 'REPLACE INTO #__settings (variable, value) VALUES (\'latestMember\', ' . $user->id_member . '), (\'latestRealName\', ' . $db->quote($userinfo->name) . ')';
			    $db->setQuery($query);
			    $db->execute();

			    //return the good news
			    $status['debug'][] = JText::_('USER_CREATION');
			    $status['userinfo'] = $this->getUser($userinfo);
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USER_CREATION_ERROR') . ': ' . $e->getMessage();
	    }
    }

	/**
	 * Function That find the correct user group index
	 *
	 * @param array $mastergroups
	 * @param stdClass $userinfo
	 *
	 * @return int
	 */
	function getUserGroupIndex($mastergroups, $userinfo)
	{
		$index = 0;

		$groups = array();
		if ($userinfo) {
			if (isset($userinfo->groups)) {
				$groups = $userinfo->groups;
			} elseif (isset($userinfo->group_id)) {
				$groups[] = $userinfo->group_id;
			}
		}

		foreach ($mastergroups as $key => $mastergroup) {
			if ($mastergroup) {
				$found = true;
				//check to see if the default groups are different
				if ($mastergroup->defaultgroup != $userinfo->group_id ) {
					$found = false;
				} else {
					if ($this->params->get('compare_postgroup', false) && $mastergroup->postgroup != $userinfo->postgroup ) {
						//check to see if the display groups are different
						$found = false;
					} else {
						if ($this->params->get('compare_membergroups', true) && isset($mastergroup->membergroups)) {
							//check to see if member groups are different
							if (count($userinfo->groups) != count($mastergroup->membergroups)) {
								$found = false;
								break;
							} else {
								foreach ($mastergroup->membergroups as $gid) {
									if (!in_array($gid, $userinfo->groups)) {
										$found = false;
										break;
									}
								}
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
		return $index;
	}
}
