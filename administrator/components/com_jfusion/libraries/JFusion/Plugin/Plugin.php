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

use JFusion\Factory;
use JFusion\Debugger\Debugger;
use Joomla\Event\Event;
use Joomla\Language\Text;
use Joomla\Registry\Registry;

use \Exception;
use \ReflectionMethod;

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
	 * @var string
	 */
	private $instance = null;

	/**
	 * @var string
	 */
	private $name = null;

	/**
	 * @param string $instance instance name of this plugin
	 */
	function __construct($instance)
	{
		$this->instance = $instance;

		$responce = Factory::getDispatcher()->triggerEvent(new Event('onLanguageLoadFramework'));

		$jname = $this->getJname();
		if (!empty($jname)) {
			//get the params object
			$this->params = &Factory::getParams($jname);
			$this->debugger = &Factory::getDebugger($jname);

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
							$pluginevent = new Event('onLanguageLoadPlugin');
							$pluginevent->addArgument('jname', $name);

							Factory::getDispatcher()->triggerEvent($pluginevent);
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
	final public function getJname()
	{
		return $this->instance;
	}

	/**
	 * returns the name of this JFusion plugin, using the "namespace" part of the plugin class
	 *
	 * @return string name of current JFusion plugin
	 */
	final public function getName()
	{
		if (!$this->name) {
			$this->name = get_class($this);

			list ($jfusion, $plugins, $name) = explode('\\', get_class($this));

			$this->name = $name;
		}
		return $this->name;
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

		/**
		 * TODO: FIX ISSUE DETERMINE IF A FUNCTION EXISTS OR NOT for Platform_XXX_Platform
		 */
		//if the class name is the abstract class then return false
		$abstractClassNames = array('JFusion\Plugin\Plugin_Admin',
			'JFusion\Plugin\Plugin_Auth',
			'JFusion\Plugin\Plugin_Front',
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
	 * Function returns the path to the modfile
	 *
	 * @param string $filename file name
	 * @param int    &$error   error number
	 * @param string &$reason  error reason
	 *
	 * @return string $mod_file path and file of the modfile.
	 */
	function getPluginFile($filename, &$error, &$reason)
	{
		//check to see if a path is defined
		$path = $this->params->get('source_path');
		if (empty($path)) {
			$error = 1;
			$reason = Text::_('SET_PATH_FIRST');
		}
		//check for trailing slash and generate file path
		if (substr($path, -1) == DIRECTORY_SEPARATOR) {
			$mod_file = $path . $filename;
		} else {
			$mod_file = $path . DIRECTORY_SEPARATOR . $filename;
		}
		//see if the file exists
		if (!file_exists($mod_file) && $error == 0) {
			$error = 1;
			$reason = Text::_('NO_FILE_FOUND');
		}
		return $mod_file;
	}
}
