<?php namespace JFusion\Plugins\joomla_ext;

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_Admin;

use \JRegistry;
use \Exception;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin Class for an external Joomla database.
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_joomla_ext extends Plugin_Admin
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

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
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('username, email')
				->from('#__users');

			$db->setQuery($query, $limitstart, $limit);
			$userlist = $db->loadObjectList();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
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
			Framework::raiseError($e, $this->getJname());
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
			Framework::raiseError($e, $jname);
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
	 * Function finds config file of integrated software and automatically configures the JFusion plugin
	 *
	 * @param string $softwarePath path to root of integrated software
	 *
	 * @return object JParam JParam objects with ne newly found configuration
	 * Now Joomla 1.6+ compatible
	 */
	function setupFromPath($softwarePath)
	{
		$configfile = $softwarePath . 'configuration.php';
		//joomla 1.6+ test
		$test_version_file = $softwarePath . 'includes' . DIRECTORY_SEPARATOR . 'version.php';

		$params = array();
		$lines = $this->readFile($configfile);
		if ($lines === false) {
			Framework::raiseWarning(Text::_('WIZARD_FAILURE') . ': ' . $configfile . ' ' . Text::_('WIZARD_MANUAL'));
			return false;
		} else {
			//parse the file line by line to get only the config variables
			//we can not directly include the config file as JConfig is already defined
			$config = array();
			foreach ($lines as $line) {
				if (strpos($line, '$')) {
					//extract the name and value, it was coded to avoid the use of eval() function
					// because from Joomla 1.6 the configuration items are declared public in tead of var
					// we just convert public to var
					$line = str_replace('public $', 'var $', $line);
					$vars = explode("'", $line);
					$names = explode('var', $vars[0]);
					if (isset($vars[1]) && isset($names[1])) {
						$name = trim($names[1], ' $=');
						$value = trim($vars[1], ' $=');
						$config[$name] = $value;
					}
				}
			}

			//Save the parameters into the standard JFusion params format
			$params['database_host'] = isset($config['host']) ? $config['host'] : '';
			$params['database_name'] = isset($config['db']) ? $config['db'] : '';
			$params['database_user'] = isset($config['user']) ? $config['user'] : '';
			$params['database_password'] = isset($config['password']) ? $config['password'] : '';
			$params['database_prefix'] = isset($config['dbprefix']) ? $config['dbprefix'] : '';
			$params['database_type'] = isset($config['dbtype']) ? $config['dbtype'] : '';
			$params['source_path'] = $softwarePath;

			//determine if this is 1.5 or 1.6+
			$params['joomlaversion'] = (file_exists($test_version_file)) ? '1.6' : '1.5';
		}
		return $params;
	}

	/**
	 * Checks if the software allows new users to register
	 *
	 * @return boolean True if new user registration is allowed, otherwise returns false
	 */
	public function allowRegistration()
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			//we want to output the usergroup name
			$query = $db->getQuery(true)
				->select('params')
				->from('#__extensions')
				->where('element = ' . $db->quote('com_users'));

			$db->setQuery($query);
			$params = $db->loadResult();

			$params = new JRegistry($params);
			// Return true if the 'allowUserRegistration' switch is enabled in the component parameters.
			return ($params->get('allowUserRegistration', false) ? true : false);
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			return false;
		}
	}
}
