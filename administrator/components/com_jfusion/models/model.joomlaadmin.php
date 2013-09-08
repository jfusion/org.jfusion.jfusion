<?php

/**
 * Model for joomla actions
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';

/**
 * Common Class for Joomla JFusion plugins
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionJoomlaAdmin extends JFusionAdmin
{
	/**
	 * returns the name of user table of integrated software
	 *
	 * @return string table name
	 */
	public function getTablename()
	{
		return 'users';
	}

	/**
	 * Returns the a list of users of the integrated software
	 *
	 * @param int $limitstart start at
	 * @param int $limit number of results
	 *
	 * @return array List of usernames/emails
	 */
	public function getUserList($limitstart = 0, $limit = 0)
	{
		try {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('username, email')
				->from('#__users');

			$db->setQuery($query,$limitstart,$limit);
			$userlist = $db->loadObjectList();
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			$userlist = array();
		}
		return $userlist;
	}
	/**
	 * Returns the the number of users in the integrated software. Allows for fast retrieval total number of users for the usersync
	 *
	 * @return integer Number of registered users
	 */
	public function getUserCount()
	{
		try {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('count(*)')
				->from('#__users');

			$db->setQuery($query);
			//getting the results
			return $db->loadResult();
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			return 0;
		}
	}

	/**
	 * Returns the a list of usersgroups of the integrated software
	 *
	 * @return array List of usergroups
	 */
	public function getUsergroupList()
	{
		try {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('id, title as name')
				->from('#__usergroups');

			$db->setQuery($query);
			//getting the results
			return $db->loadObjectList();
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			return array();
		}
	}

	/**
	 * Function used to display the default usergroup in the JFusion plugin overview
	 *
	 * @return string|array Default usergroup name
	 */
	public function getDefaultUsergroup()
	{
		try {
			$db = JFusionFactory::getDatabase($this->getJname());
			$usergroups = JFusionFunction::getUserGroups($this->getJname(), true);

			if ($usergroups !== null) {
				$group = array();
				foreach($usergroups as $usergroup) {
					$query = $db->getQuery(true)
						->select('title')
						->from('#__usergroups')
						->where('id = ' . $usergroup);

					$db->setQuery($query);
					$group[] = $db->loadResult();
				}
			} else {
				$group = '';
			}
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			$group = '';
		}
		return $group;
	}

	/**
	 * Function used get usergroup name by id
	 *
	 * @param string $jname jfusion plugin name
	 * @param int $gid joomla group id
	 *
	 * @return string Default usergroup name
	 */
	function getUsergroupName($jname,$gid)
	{
		try {
			$db = JFusionFactory::getDatabase($jname);

			//we want to output the usergroup name

			$query = $db->getQuery(true)
				->select('title')
				->from('#__usergroups')
				->where('id = ' . $gid);

			$db->setQuery($query);
			$group = $db->loadResult();
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $jname);
			$group = '';
		}
		return $group;
	}

	/**
	 * Checks if the software allows new users to register
	 *
	 * @return boolean True if new user registration is allowed, otherwise returns false
	 */
	public function allowRegistration()
	{
		try {
			if ($this->getJname() == 'joomla_int') {
				$params = JComponentHelper::getParams('com_users');
			} else {
				$db = JFusionFactory::getDatabase($this->getJname());

				//we want to output the usergroup name
				$query = $db->getQuery(true)
					->select('params')
					->from('#__extensions')
					->where('element = ' . $db->quote('com_users'));

				$db->setQuery($query);
				$params = $db->loadResult();

				$params = new JRegistry($params);
			}
			// Return true if the 'allowUserRegistration' switch is enabled in the component parameters.
			return ($params->get('allowUserRegistration') ? true : false);
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			return false;
		}
	}

	/**
	 * @return string UNKNOWN or JNO or JYES or ??
	 */
	function requireFileAccess()
	{
		return 'JNO';
	}

	/**
	 * do plugin support multi usergroups
	 *
	 * @return bool
	 */
	function isMultiGroup()
	{
		return true;
	}
}