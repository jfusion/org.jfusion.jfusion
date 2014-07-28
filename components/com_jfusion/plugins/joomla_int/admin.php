<?php namespace JFusion\Plugins\joomla_int;

/**
 * file containing administrator function for the jfusion plugin
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

use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_Admin;

use Psr\Log\LogLevel;
use \RuntimeException;
use \Exception;
use \JComponentHelper;

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin class for the internal Joomla database
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_int
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class Admin extends Plugin_Admin
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
	 * Function that checks if the plugin has a valid config
	 *
	 * @throws RuntimeException
	 * @return array result of the config check
	 */
	function checkConfig()
	{
		//for joomla_int check to see if the source_url does not equal the default
		$source_url = $this->params->get('source_url');
		if (empty($source_url)) {
			throw new RuntimeException(Text::_('EMPTY_URL'));
		}
		$status = array();
		$status['config'] = 1;
		$status['message'] = Text::_('GOOD_CONFIG');
		return $status;
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
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('username, email')
				->from('#__users');

			$db->setQuery($query, $limitstart, $limit);
			$userlist = $db->loadObjectList();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('count(*)')
				->from('#__users');

			$db->setQuery($query);
			//getting the results
			return $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			return 0;
		}
	}


	/**
	 * Function used to display the default usergroup in the JFusion plugin overview
	 *
	 * @return array Default usergroup name
	 */
	public function getDefaultUsergroup()
	{
		$db = Factory::getDatabase($this->getJname());
		$usergroups = Framework::getUserGroups($this->getJname(), true);

		$group = array();
		if ($usergroups !== null) {
			foreach($usergroups as $usergroup) {
				$query = $db->getQuery(true)
					->select('title')
					->from('#__usergroups')
					->where('id = ' . $usergroup);

				$db->setQuery($query);
				$group[] = $db->loadResult();
			}
		}
		return $group;
	}

	/**
	 * Returns the a list of usersgroups of the integrated software
	 *
	 * @return array List of usergroups
	 */
	public function getUsergroupList()
	{
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('id, title as name')
			->from('#__usergroups');

		$db->setQuery($query);
		//getting the results
		return $db->loadObjectList();
	}

	/**
	 * Function used get usergroup name by id
	 *
	 * @param string $jname jfusion plugin name
	 * @param int $gid joomla group id
	 *
	 * @return string Default usergroup name
	 */
	function getUsergroupName($jname, $gid)
	{
		try {
			$db = Factory::getDatabase($jname);

			//we want to output the usergroup name

			$query = $db->getQuery(true)
				->select('title')
				->from('#__usergroups')
				->where('id = ' . $gid);

			$db->setQuery($query);
			$group = $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $jname);
			$group = '';
		}
		return $group;
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

	/**
	 * @return bool do the plugin support multi instance
	 */
	function multiInstance()
	{
		return false;
	}

	/**
	 * Checks if the software allows new users to register
	 *
	 * @return boolean True if new user registration is allowed, otherwise returns false
	 */
	public function allowRegistration()
	{
		$params = JComponentHelper::getParams('com_users');
		// Return true if the 'allowUserRegistration' switch is enabled in the component parameters.
		return ($params->get('allowUserRegistration', false) ? true : false);
	}
}
