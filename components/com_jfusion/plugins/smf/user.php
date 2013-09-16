<?php

/**
 * file containing user function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractuser.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jplugin.php';
/**
 * JFusion User Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractuser.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_smf extends JFusionUser
{
    /**
     * get user
     *
     * @param object $userinfo holds the new user data
     *
     * @access public
     *
     * @return null|object
     */
    function getUser($userinfo)
    {
	    try {
		    //get the identifier
		    list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.memberName', 'a.emailAddress');
		    // initialise some objects
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('a.ID_MEMBER as userid, a.memberName as username, a.realName as name, a.emailAddress as email, a.passwd as password, a.passwordSalt as password_salt, a.validation_code as activation, a.is_activated, null as reason, a.lastLogin as lastvisit, a.ID_GROUP as group_id, a.ID_POST_GROUP as postgroup, a.additionalGroups')
			    ->from('#__members as a')
		        ->where($identifier_type . ' = ' . $db->Quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();
		    if ($result) {
			    if ($result->group_id == 0) {
				    $result->group_name = 'Default Usergroup';
			    } else {
				    $query = $db->getQuery(true)
					    ->select('groupName')
					    ->from('#__membergroups')
					    ->where('ID_GROUP = ' . (int)$result->group_id);

				    $db->setQuery($query);
				    $result->group_name = $db->loadResult();
			    }
			    $result->groups = array($result->group_id);
			    $result->groupnames = array($result->group_name);

			    if (!empty($result->additionalGroups)) {
				    $groups = explode(',', $result->additionalGroups);

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
				    ->select('ID_BAN_GROUP, expire_time')
				    ->from('#__ban_groups')
				    ->where('name = ' . $db->quote($result->username));

			    $db->setQuery($query);
			    $expire_time = $db->loadObject();
			    if ($expire_time) {
				    if ($expire_time->expire_time == '' || $expire_time->expire_time > time()) {
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
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'smf';
    }

    /**
     * delete user
     *
     * @param object $userinfo holds the new user data
     *
     * @access public
     *
     * @return array
     */
    function deleteUser($userinfo)
    {
	    try {
	        //setup status array to hold debug info and errors
	        $status = array('error' => array(),'debug' => array());
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->delete('#__members')
			    ->where('memberName = ' . $db->quote($userinfo->username));

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
			    ->select('MAX(ID_MEMBER) as ID_MEMBER')
			    ->from('#__members')
			    ->where('is_activated = 1');

		    $db->setQuery($query);
		    $resultID = $db->loadObject();
		    if (!$resultID) {
			    //return the error
			    $status['error'][] = JText::_('USER_DELETION_ERROR');
		    } else {
			    $query = $db->getQuery(true)
				    ->select('realName as name')
				    ->from('#__members')
				    ->where('ID_MEMBER = ' . $db->quote($resultID->ID_MEMBER));

			    $db->setQuery($query, 0 , 1);
			    $resultName = $db->loadObject();
			    if (!$resultName) {
				    //return the error
				    $status['error'][] = JText::_('USER_DELETION_ERROR');
			    } else {
				    $query = 'REPLACE INTO #__settings (variable, value) VALUES (\'latestMember\', ' . $resultID->ID_MEMBER . '), (\'latestRealName\', ' . $db->quote($resultName->name) . ')';
				    $db->setQuery($query);
				    $db->execute();

				    $status['debug'][] = JText::_('USER_DELETION') . ' ' . $userinfo->username;
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . $e->getMessage();
	    }
        return $status;
    }

    /**
     * destroy session
     *
     * @param object $userinfo holds the new user data
     * @param array  $options  Status array
     *
     * @access public
     *
     * @return array
     */
    function destroySession($userinfo, $options)
    {
        $status = array('error' => array(),'debug' => array());
	    try {
		    $status['debug'][] = JFusionFunction::addCookie($this->params->get('cookie_name'), '', 0, $this->params->get('cookie_path'), $this->params->get('cookie_domain'), $this->params->get('secure'), $this->params->get('httponly'));

		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->delete('#__log_online')
			    ->where('ID_MEMBER = ' . $userinfo->userid);

		    $db->setQuery($query, 0 , 1);
		    $db->execute();
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * create session
     *
     * @param object $userinfo holds the new user data
     * @param array  $options  options
     *
     * @access public
     *
     * @return array
     */
    function createSession($userinfo, $options)
    {
        $status = array('error' => array(),'debug' => array());
        //do not create sessions for blocked users
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
            $status = JFusionJplugin::createSession($userinfo, $options, $this->getJname(), $this->params->get('brute_force'));
        }
        return $status;
    }

    /**
     * filterUsername
     *
     * @param string $username holds the new user data
     *
     * @access public
     *
     * @return string
     */
    function filterUsername($username)
    {
        //no username filtering implemented yet
        return $username;
    }

    /**
     * updatePassword
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
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
			    ->set('passwordSalt = ' . $db->quote($existinguser->password_salt))
			    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

	        $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password,0,6) . '********';
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR')  . $e->getMessage();
	    }
    }

    /**
     * updateUsername
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function updateUsername($userinfo, &$existinguser, &$status)
    {
    }

    /**
     * updateEmail
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
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
			    ->set('emailAddress = ' . $db->quote($userinfo->email))
			    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

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
		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
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
				    ->set('ID_GROUP = ' . $db->quote($usergroup->defaultgroup));

			    if ($this->params->get('compare_postgroup', false) ) {
				    $query->set('ID_POST_GROUP = ' . $db->quote($usergroup->postgroup));
			    }
			    if ($this->params->get('compare_membergroups', true) ) {
				    $query->set('additionalGroups = ' . $db->quote(join(',',$usergroup->groups)));
			    }
			    $query->where('ID_MEMBER = ' . (int)$existinguser->userid);

			    $db->setQuery($query);
			    $db->execute();

			    $groups = $usergroup->groups;
			    $groups[] = $usergroup->defaultgroup;

			    $existinggroups = $existinguser->groups;
			    $existinggroups[] = $existinguser->group_id;

			    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinggroups) . ' -> ' . implode (' , ', $groups);
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $e->getMessage();
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
		$usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
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
     * blockUser
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function blockUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $ban = new stdClass;
		    $ban->ID_BAN_GROUP = null;
		    $ban->name = $existinguser->username;
		    $ban->ban_time = time();
		    $ban->expire_time = null;
		    $ban->cannot_access = 1;
		    $ban->cannot_register = 0;
		    $ban->cannot_post = 0;
		    $ban->cannot_login = 0;
		    $ban->reason = 'You have been banned from this software. Please contact your site admin for more details';
		    //now append the new user data
		    $db->insertObject('#__ban_groups', $ban, 'ID_BAN_GROUP');

		    $ban_item = new stdClass;
		    $ban_item->ID_BAN_GROUP = $ban->ID_BAN_GROUP;
		    $ban_item->ID_MEMBER = $existinguser->userid;
		    $db->insertObject('#__ban_items', $ban_item, 'ID_BAN');

		    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * unblock user
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
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
			    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * activate user
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
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
			    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();
		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }

    }

    /**
     * deactivate user
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
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
			    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Creates a new user
     *
     * @param object $userinfo holds the new user data
     * @param array  &$status  Status array
     *
     * @access public
     *
     * @return void
     */
    function createUser($userinfo, &$status)
    {
	    try {
		    //we need to create a new SMF user
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException('USERGROUP_MISSING');
		    } else {
			    $usergroup = $usergroups[0];

			    if (!isset($usergroup->groups)) {
				    $usergroup->groups = array();
			    }

			    //prepare the user variables
			    $user = new stdClass;
			    $user->ID_MEMBER = null;
			    $user->memberName = $userinfo->username;
			    $user->realName = $userinfo->name;
			    $user->emailAddress = $userinfo->email;
			    if (isset($userinfo->password_clear)) {
				    $user->passwd = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
				    $user->passwordSalt = substr(md5(rand()), 0, 4);
			    } else {
				    $user->passwd = $userinfo->password;
				    if (!isset($userinfo->password_salt)) {
					    $user->passwordSalt = substr(md5(rand()), 0, 4);
				    } else {
					    $user->passwordSalt = $userinfo->password_salt;
				    }
			    }
			    $user->posts = 0;
			    $user->dateRegistered = time();
			    if ($userinfo->activation) {
				    $user->is_activated = 0;
				    $user->validation_code = $userinfo->activation;
			    } else {
				    $user->is_activated = 1;
				    $user->validation_code = '';
			    }
			    $user->personalText = '';
			    $user->pm_email_notify = 1;
			    $user->hideEmail = 1;
			    $user->ID_THEME = 0;

			    $user->ID_GROUP = $usergroup->defaultgroup;
			    $user->additionalGroups = join(',', $usergroup->groups);
			    $user->ID_POST_GROUP = $usergroup->postgroup;

			    $db->insertObject('#__members', $user, 'ID_MEMBER');
			    //now append the new user data

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

			    $query = 'REPLACE INTO #__settings (variable, value) VALUES (\'latestMember\', ' . $user->ID_MEMBER . '), (\'latestRealName\', ' . $db->quote($userinfo->name) . ')';
			    $db->setQuery($query);
			    $db->execute();

			    //return the good news
			    $status['debug'][] = JText::_('USER_CREATION');
			    $status['userinfo'] = $this->getUser($userinfo);
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USER_CREATION_ERROR') . $e->getMessage();
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
