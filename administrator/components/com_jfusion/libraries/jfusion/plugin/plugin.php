<?php namespace JFusion\Plugin;

	/**
	 * Abstract plugin class
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
use JFusion\Factory;
use \JFusion\Debugger\Debugger;
use \JFusion\Registry\Registry;

use \Exception;
use \ReflectionMethod;

defined('_JEXEC') or die('Restricted access');

/**
 * Abstract interface for all JFusion plugin implementations.
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.orgrg
 */
class Plugin
{
	static protected $language = array();
	static protected $status = array();

	/**
	 * @var Registry
	 */
	var $params;

	/**
	 * @var Debugger
	 */
	var $debugger;

	/**
	 *
	 */
	function __construct()
	{
		Factory::getLanguage()->load('com_jfusion', JFUSIONPATH_ADMINISTRATOR);
		Factory::getLanguage()->load('com_jfusion', JFUSIONPATH_SITE);

		$jname = $this->getJname();
		if (!empty($jname)) {
			//get the params object
			$this->params = & Factory::getParams($jname);
			$this->debugger = & Factory::getDebugger($jname);

			if (!isset(static::$language[$jname])) {
				$db = Factory::getDBO();
				$query = $db->getQuery(true)
					->select('name, original_name')
					->from('#__jfusion')
					->where('name = ' . $db->quote($jname), 'OR')
					->where('original_name = ' . $db->quote($jname));

				$db->setQuery($query);
				$plugins = $db->loadObjectList();
				if ($plugins) {
					$loaded = false;
					foreach($plugins as $plugin) {
						$name = $plugin->original_name ? $plugin->original_name : $plugin->name;
						if (!$loaded) {
							Factory::getLanguage()->load('com_jfusion.plg_' . $name, JFUSIONPATH_ADMINISTRATOR);
							$loaded = true;
						}
						static::$language[$jname] = true;
						if ($plugin->original_name) {
							static::$language[$plugin->original_name] = true;
						}
					}
				}
			}
		} else {
			$this->params = new Registry();
		}
	}

	/**
	 * returns the name of this JFusion plugin
	 *
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return '';
	}

	/**
	 * Function to check if a method has been defined inside a plugin like: setupFromPath
	 *
	 * @param $method
	 *
	 * @return bool
	 */
	final public function methodDefined($method) {
		$name = get_class($this);

		//if the class name is the abstract class then return false
		$abstractClassNames = array('JFusion\Plugin\Plugin_Admin',
			'JFusion\Plugin\Plugin_Auth',
			'JFusion\Plugin\Plugin_Forum',
			'JFusion\Plugin\Plugin_Public',
			'JFusion\Plugin\Plugin_User',
			'JFusion\Plugin\Plugin');
		$return = false;
		if (!in_array($name, $abstractClassNames)) {
			try {
				$m = new ReflectionMethod($this, $method);
				$classname = $m->getDeclaringClass()->getName();
				if ($classname == $name || !in_array($classname, $abstractClassNames)) {
					$return = true;
				}
			} catch (Exception $e) {
				$return = false;
			}
		}
		return $return;
	}

	/**
	 * Checks to see if the JFusion plugin is properly configured
	 *
	 * @return boolean returns true if plugin is correctly configured
	 */
	final public function isConfigured()
	{
		$jname = $this->getJname();
		$result = false;

		if (!empty($jname)) {
			if (!isset(static::$status[$jname])) {
				$db = Factory::getDBO();
				$query = $db->getQuery(true)
					->select('status')
					->from('#__jfusion')
					->where('name = ' . $db->quote($jname));

				$db->setQuery($query);
				$result = $db->loadResult();
				if ($result == '1') {
					$result = true;
				} else {
					$result = false;
				}
				static::$status[$jname] = $result;
			} else {
				$result = static::$status[$jname];
			}
		}
		return $result;
	}

	/**
	 * @param array &$status
	 *
	 * @return array
	 */
	final public function mergeStatus(&$status) {
		if (!empty($status['error']) || !empty($status['debug'])) {
			$this->debugger->merge($status);
		}
		$status = array('error' => array(), 'debug' => array());
	}
}
