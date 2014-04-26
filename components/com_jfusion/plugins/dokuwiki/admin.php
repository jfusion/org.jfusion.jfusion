<?php namespace JFusion\Plugins\dokuwiki;

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFile;
use JFolder;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Filesystem\Path;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_Admin;
use \RuntimeException;
use \stdClass;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion admin class for DokuWiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class Admin extends Plugin_Admin
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

	/**
	 * check config
	 *
	 * @throws RuntimeException
	 * @return array status message
	 */
	function checkConfig()
	{
		$source_path = $this->params->get('source_path');

		$config = $this->helper->getConf($source_path);

		if ($config !== false) {
			if (!isset($config['authtype']) || $config['authtype'] != 'authmysql' && $config['authtype'] != 'authplain') {
				throw new RuntimeException(Text::_('UNSUPPORTED_AUTHTYPE') . ': ' . $config['authtype']);
			}
		} else {
			throw new RuntimeException(Text::_('WIZARD_FAILURE'));
		}
		return true;
	}

	/**
	 * setup plugin from path
	 *
	 * @param string $softwarePath Source path user to find config files
	 *
	 * @return array return array
	 */
	function setupFromPath($softwarePath)
	{
		//try to open the file
		$config = $this->helper->getConf($softwarePath);
		$params = array();
		if ($config === false) {
			Framework::raiseWarning(Text::_('WIZARD_FAILURE') . ': ' . $softwarePath . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
			return false;
		} else {
			if (isset($config['cookie_name'])) {
				$params['cookie_name'] = $config['cookie_name'];
			}
			if (isset($config['cookie_path'])) {
				$params['cookie_path'] = $config['cookie_path'];
			}
			if (isset($config['cookie_domain'])) {
				$params['cookie_domain'] = $config['cookie_domain'];
			}
			if (isset($config['cookie_seed'])) {
				$params['cookie_seed'] = $config['cookie_seed'];
			}
			if (isset($config['cookie_secure'])) {
				$params['cookie_secure'] = $config['cookie_secure'];
			}
			$params['source_path'] = $softwarePath;
		}
		return $params;
	}

	/**
	 * Function that checks if the plugin has a valid config
	 * jerror is used for output
	 *
	 * @return void
	 */
	function debugConfigExtra()
	{
		$conf = $this->helper->getConf();

		if ($conf) {
			if ($conf['authtype'] != 'authmysql' && $conf['authtype'] != 'authplain') {
				Framework::raiseError(Text::_('UNSUPPORTED_AUTHTYPE') . ': ' . $conf['authtype'], $this->getJname());
			}
		}
	}

	/**
	 * Get a list of users
	 *
	 * @param int $limitstart
	 * @param int $limit
	 *
	 * @return array array with object with list of users
	 */
	function getUserList($limitstart = 0, $limit = 0)
	{
		$list = $this->helper->auth->retrieveUsers($limitstart, $limit);
		$userlist = array();
		foreach ($list as $value) {
			$user = new stdClass;
			$user->email = isset($value['mail']) ? $value['mail'] : null;
			$user->username = isset($value['username']) ? $value['username'] : null;
			$userlist[] = $user;
		}
		return $userlist;
	}

	/**
	 * returns user count
	 *
	 * @return int user count
	 */
	function getUserCount()
	{
		return $this->helper->auth->getUserCount();
	}

	/**
	 * get default user group list
	 *
	 * @return array with default user group list
	 */
	function getUsergroupList()
	{
		$usergroupmap = $this->params->get('usergroupmap', 'user,admin');

		$usergroupmap = explode (',', $usergroupmap);
		$usergrouplist = array();
		if ( is_array($usergroupmap) ) {
			foreach ($usergroupmap as $value) {
				//append the default usergroup
				$default_group = new stdClass;
				$default_group->id = trim($value);
				$default_group->name = trim($value);
				$usergrouplist[] = $default_group;
			}
		}
		return $usergrouplist;
	}

	/**
	 * get default user group
	 *
	 * @return string|array with default user group
	 */
	function getDefaultUsergroup()
	{
		$usergroup = Framework::getUserGroups($this->getJname(), true);
		return $usergroup;
	}

	/**
	 * function  return if user can register or not
	 *
	 * @return boolean true can register
	 */
	function allowRegistration()
	{
		$conf = $this->helper->getConf();
		if (strpos($conf['disableactions'], 'register') !== false) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * do plugin support multi usergroups
	 *
	 * @return string UNKNOWN or JNO or JYES or ??
	 */
	function requireFileAccess()
	{
		return 'JYES';
	}

	/**
	 * @return bool do the plugin support multi instance
	 */
	function multiInstance()
	{
		return false;
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
