<?php namespace JFusion;

	/**
	 * Factory model that can generate any jfusion objects or classes
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

use JFusion\Cookies\Cookies;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseFactory;
use Joomla\Registry\Registry;
use JFusion\Debugger\Debugger;
use Joomla\Language\Language;
use Joomla\Language\Text;
use Joomla\Date\Date;
use Joomla\Event\Dispatcher;
use JFusion\Application\Application;
use JFusion\Session\Session;
use JFusion\Router\Router;


use JFusion\Plugin\Plugin_Front;
use JFusion\Plugin\Plugin_Admin;
use JFusion\Plugin\Plugin_Auth;
use JFusion\Plugin\Plugin_User;
use JFusion\Plugin\Plugin_Forum;


use \RuntimeException;
use \DateTimeZone;


/**
 * Custom parameter class that can save array values
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
jimport('joomla.html.parameter');

/**
 * Singleton static only class that creates instances for each specific JFusion plugin.
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class Factory
{
	/**
	 * Global database object
	 *
	 * @var    DatabaseDriver
	 * @since  11.1
	 */
	public static $database = null;

	/**
	 * Global application object
	 *
	 * @var    Application
	 * @since  11.1
	 */
	public static $application = null;

	/**
	 * @var    Session  The session object.
	 * @since  11.3
	 */
	public static $session;

	/**
	 * Global configuration object
	 *
	 * @var    Registry
	 * @since  11.1
	 */
	public static $config = null;

	/**
	 * Global language object
	 *
	 * @var    Language
	 * @since  11.1
	 */
	public static $language = null;

	/**
	 * Container for Date instances
	 *
	 * @var    array[Date]
	 * @since  11.3
	 */
	public static $dates = array();

	/**
	 * Container for Dispatcher instances
	 *
	 * @var    Dispatcher
	 * @since  11.3
	 */
	public static $dispatcher = null;

	/**
	 * Gets an Fusion front object
	 *
	 * @param string $instance name of the JFusion plugin used
	 *
	 * @return string object for the JFusion plugin
	 */
	public static function &getNameFromInstance($instance)
	{
		static $namnes;
		if (!isset($namnes)) {
			$namnes = array();
		}
		//only create a new plugin instance if it has not been created before
		if (!isset($namnes[$instance])) {
			$db = static::getDbo();

			$query = $db->getQuery(true)
				->select('original_name')
				->from('#__jfusion')
				->where('name = ' . $db->quote($instance));

			$db->setQuery($query);
			$name = $db->loadResult();
			if ($name) {
				$instance = $name;
			}
			$namnes[$instance] = $instance;
		}
		return $namnes[$instance];
	}

	/**
	 * Gets an Fusion front object
	 *
	 * @param string $instance name of the JFusion plugin used
	 *
	 * @return Plugin_Front object for the JFusion plugin
	 */
	public static function &getFront($instance)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}
		//only create a new plugin instance if it has not been created before
		if (!isset($instances[$instance])) {
			$name = static::getNameFromInstance($instance);

			$class = '\JFusion\Plugins\\'.$name.'\Front';
			if (!class_exists($class)) {
				$class = '\JFusion\Plugin\Plugin_Front';
			}
			$instances[$instance] = new $class($instance);
		}
		return $instances[$instance];
	}
	/**
	 * Gets an Fusion front object
	 *
	 * @param string $instance name of the JFusion plugin used
	 *
	 * @return Plugin_Admin object for the JFusion plugin
	 */
	public static function &getAdmin($instance)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}
		//only create a new plugin instance if it has not been created before
		if (!isset($instances[$instance])) {
			$name = static::getNameFromInstance($instance);
			$class = '\JFusion\Plugins\\'.$name.'\Admin';
			if (!class_exists($class)) {
				$class = '\JFusion\Plugin\Plugin_Admin';
			}
			$instances[$instance] = new $class($instance);
		}
		return $instances[$instance];
	}

	/**
	 * Gets an Authentication Class for the JFusion Plugin
	 *
	 * @param string $instance name of the JFusion plugin used
	 *
	 * @return Plugin_Auth JFusion Authentication class for the JFusion plugin
	 */
	public static function &getAuth($instance)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}
		//only create a new authentication instance if it has not been created before
		if (!isset($instances[$instance])) {
			$name = static::getNameFromInstance($instance);

			$class = '\JFusion\Plugins\\'.$name.'\Auth';
			if (!class_exists($class)) {
				$class = '\JFusion\Plugin\Plugin_Auth';
			}
			$instances[$instance] = new $class($instance);
		}
		return $instances[$instance];
	}

	/**
	 * Gets an User Class for the JFusion Plugin
	 *
	 * @param string $instance name of the JFusion plugin used
	 *
	 * @return Plugin_User JFusion User class for the JFusion plugin
	 */
	public static function &getUser($instance)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}
		//only create a new user instance if it has not been created before
		if (!isset($instances[$instance])) {
			$name = static::getNameFromInstance($instance);

			$class = '\JFusion\Plugins\\'.$name.'\User';
			if (!class_exists($class)) {
				$class = '\JFusion\Plugin\Plugin_User';
			}
			$instances[$instance] = new $class($instance);
		}
		return $instances[$instance];
	}

	/**
	 * Gets a Forum Class for the JFusion Plugin
	 *
	 * @param string $platform
	 * @param string $instance name of the JFusion plugin used
	 *
	 * @throws \RuntimeException
	 * @return Plugin\Plugin JFusion Thread class for the JFusion plugin
	 */
	public static function &getPlayform($platform, $instance)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}

		$platform = ucfirst(strtolower($platform));

		//only create a new thread instance if it has not been created before
		if (!isset($instances[$platform][$instance])) {
			$name = static::getNameFromInstance($instance);

			$class = '\JFusion\Plugins\\'.$name.'\Platform_' . $platform. '_Platform';
			if (!class_exists($class)) {
				$class = '\JFusion\Plugin\Platform_' . $platform;
			}
			if (!class_exists($class)) {
				throw new RuntimeException('Platform Class Platform_' . $platform . ' do not Exsist');
			}
			$instances[$platform][$instance] = new $class($instance);
		}
		return $instances[$platform][$instance];
	}

	/**
	 * Gets a Helper Class for the JFusion Plugin which is only used internally by the plugin
	 *
	 * @param string $instance name of the JFusion plugin used
	 *
	 * @return object JFusionHelper JFusion Helper class for the JFusion plugin
	 */
	public static function &getHelper($instance)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}
		//only create a new thread instance if it has not been created before
		if (!isset($instances[$instance])) {
			$name = static::getNameFromInstance($instance);

			$class = '\JFusion\Plugins\\'.$name.'\Helper';
			if (!class_exists($class)) {
				$instances[$instance] = false;
			} else {
				$instances[$instance] = new $class($instance);
			}
		}
		return $instances[$instance];
	}

	/**
	 * Gets an Database Connection for the JFusion Plugin
	 *
	 * @param string $jname name of the JFusion plugin used
	 *
	 * @return DatabaseDriver Database connection for the JFusion plugin
	 * @throws  RuntimeException
	 */
	public static function &getDatabase($jname)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}
		//only create a new database instance if it has not been created before
		if (!isset($instances[$jname])) {
			$instances[$jname] = static::createDatabase($jname);
		}
		return $instances[$jname];
	}

	/**
	 * Gets an Parameter Object for the JFusion Plugin
	 *
	 * @param string  $jname name of the JFusion plugin used
	 * @param boolean $reset switch to force a recreate of the instance
	 *
	 * @return Registry JParam object for the JFusion plugin
	 */
	public static function &getParams($jname, $reset = false)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}
		//only create a new parameter instance if it has not been created before
		if (!isset($instances[$jname]) || $reset) {
			$instances[$jname] = static::createParams($jname);
		}
		return $instances[$jname];
	}

	/**
	 * creates new param object
	 *
	 * @param string $jname name of the JFusion plugin used
	 *
	 * @throws RuntimeException
	 * @return Registry JParam object for the JFusion plugin
	 */
	public static function &createParams($jname)
	{
		jimport('joomla.html.parameter');
		//get the current parameters from the jfusion table
		$db = self::getDBO();

		$query = $db->getQuery(true)
			->select('params')
			->from('#__jfusion')
			->where('name = ' . $db->quote($jname));

		$db->setQuery($query);
		$params = $db->loadResult();
		//get the parameters from the XML file
		//$file = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'jfusion.xml';
		//$parametersInstance = new Registry('', $file );
		//now load params without XML files, as this creates overhead when only values are needed
		$parametersInstance = new Registry($params);

		if (!is_object($parametersInstance)) {
			throw new RuntimeException(Text::_('NO_FORUM_PARAMETERS'));
		}
		return $parametersInstance;
	}
	/**
	 * Acquires a database connection to the database of the software integrated by JFusion
	 *
	 * @param string $jname name of the JFusion plugin used
	 *
	 * @return DatabaseDriver database object
	 * @throws  RuntimeException
	 */
	public static function &createDatabase($jname)
	{
		//check to see if joomla DB is requested
		if ($jname == 'joomla_int') {
			$db = self::getDBO();
		} else {
			//get config values
			$params = static::getParams($jname);
			//prepare the data for creating a database connection
			$host = $params->get('database_host');
			$user = $params->get('database_user');
			$password = $params->get('database_password');
			$database = $params->get('database_name');
			$prefix = $params->get('database_prefix', '');
			$driver = $params->get('database_type');
			$charset = $params->get('database_charset', 'utf8');
			//added extra code to prevent error when $driver is incorrect

			$options = array('driver' => $driver, 'host' => $host, 'user' => $user, 'password' => $password, 'database' => $database, 'prefix' => $prefix);

			$db = DatabaseFactory::getInstance()->getDriver($driver, $options);

			//add support for UTF8
			$db->setQuery('SET names ' . $db->quote($charset));
			$db->execute();

			//get the debug configuration setting
			$db->setDebug(self::getConfig()->get('debug'));
		}
		return $db;
	}

	/**
	 * returns array of plugins depending on the arguments
	 *
	 * @param string $criteria the type of plugins to retrieve Use: master | slave | both
	 * @param boolean $joomla should we exclude joomla_int
	 * @param boolean $active only active plugins
	 *
	 * @return array plugin details
	 */
	public static function getPlugins($criteria = 'both', $joomla = false, $active = true)
	{
		static $instances;
		if (!isset($instances)) {
			$instances = array();
		}
		$db = self::getDBO();

		$query = $db->getQuery(true)
			->select('id, name, status, dual_login')
			->from('#__jfusion');

		switch ($criteria) {
			case 'slave':
				$query->where('slave = 1');
				break;
			case 'master':
				$query->where('master = 1 AND status = 1');
				break;
			case 'both':
				$query->where('(slave = 1 OR master = 1)');
				break;
		}
		$key = $criteria . '_' . $joomla . '_' . $active;
		if (!isset($instances[$key])) {
			if (!$joomla) {
				$query->where('name NOT LIKE ' . $db->quote('joomla_int'));
			}
			if ($active) {
				$query->where('status = 1');
			}

			$db->setQuery($query);
			$instances[$key] = $db->loadObjectList();
		}
		return $instances[$key];
	}

	/**
	 * Gets the jnode_id for the JFusion Plugin
	 * @param string $jname name of the JFusion plugin used
	 * @return string jnodeid for the JFusion Plugin
	 */
	public static function getPluginNodeId($jname) {
		$params = static::getParams($jname);
		$source_url = $params->get('source_url');
		return strtolower(rtrim(parse_url($source_url, PHP_URL_HOST) . parse_url($source_url, PHP_URL_PATH), '/'));
	}
	/**
	 * Gets the plugin name for a JFusion Plugin given the jnodeid
	 * @param string $jnode_id jnodeid to use
	 *
	 * @return string jname name for the JFusion Plugin, empty if no plugin found
	 */
	public static function getPluginNameFromNodeId($jnode_id) {
		$result = '';
		//$jid = $jnode_id;
		$plugins = static::getPlugins('both', true);
		foreach($plugins as $plugin) {
			$id = rtrim(static::getPluginNodeId($plugin->name), '/');
			if (strcasecmp($jnode_id, $id) == 0) {
				$result = $plugin->name;
				break;
			}
		}
		return $result;
	}

	/**
	 * Gets an JFusion cross domain cookie object
	 *
	 * @return Cookies object for the JFusion cookies
	 */
	public static function &getCookies() {
		static $instance;
		//only create a new plugin instance if it has not been created before
		if (!isset($instance)) {
			$instance = new Cookies(static::getParams('joomla_int')->get('secret'));
		}
		return $instance;
	}

	/**
	 * @param string $jname
	 *
	 * @return Debugger
	 */
	public static function &getDebugger($jname)
	{
		static $instances;

		if (!isset($instances)) {
			$instances = array();
		}

		if (!isset($instances[$jname])) {
			$instances[$jname] = new Debugger();
		}
		return $instances[$jname];
	}




























	/**
	 * Get a database object.
	 *
	 * Returns the global {@link Driver} object, only creating it if it doesn't already exist.
	 *
	 * @return  DatabaseDriver
	 */
	public static function getDbo()
	{
		if (!self::$database)
		{
			//get config values
			$conf = self::getConfig();

			//prepare the data for creating a database connection
			$conf = self::getConfig();

			$host = $conf->get('host');
			$user = $conf->get('user');
			$password = $conf->get('password');
			$database = $conf->get('db');
			$prefix = $conf->get('dbprefix');
			$driver = $conf->get('dbtype');
			$debug = $conf->get('debug');
			//added extra code to prevent error when $driver is incorrect

			$options = array('driver' => $driver, 'host' => $host, 'user' => $user, 'password' => $password, 'database' => $database, 'prefix' => $prefix);

			self::$database = DatabaseFactory::getInstance()->getDriver($driver, $options);

			//get the debug configuration setting
			self::$database->setDebug(self::getConfig()->get('debug'));
		}
		return self::$database;
	}

	/**
	 * Get a application object.
	 *
	 * Returns the global {@link JApplicationCms} object, only creating it if it doesn't already exist.
	 *
	 * @return  Application object
	 */
	public static function getApplication()
	{
		if (!self::$application)
		{
			self::$application = Application::getInstance();
		}
		return self::$application;
	}

	/**
	 * Get a configuration object
	 *
	 * Returns the global {@link Registry} object, only creating it if it doesn't already exist.
	 *
	 * @return  Registry
	 *
	 * @see     Registry
	 * @since   11.1
	 */
	public static function getConfig()
	{
		return self::$config;
	}

	/**
	 * Get a language object.
	 *
	 * Returns the global {@link JLanguage} object, only creating it if it doesn't already exist.
	 *
	 * @return  Language object
	 *
	 * @see     Language
	 * @since   11.1
	 */
	public static function getLanguage()
	{
		if (!self::$language)
		{
			$conf = self::getConfig();
			$locale = $conf->get('language');
			$debug = $conf->get('debug_lang');
			self::$language = Language::getInstance($locale, $debug);
		}
		return self::$language;
	}

	/**
	 * Return the {@link JDate} object
	 *
	 * @param   mixed  $time      The initial time for the JDate object
	 * @param   mixed  $tzOffset  The timezone offset.
	 *
	 * @return  Date object
	 *
	 * @see     Date
	 * @since   11.1
	 */
	public static function getDate($time = 'now', $tzOffset = null)
	{
		static $classname;
		static $mainLocale;

		$language = self::getLanguage();
		$locale = $language->getTag();

		if (!isset($classname) || $locale != $mainLocale)
		{
			// Store the locale for future reference
			$mainLocale = $locale;

			if ($mainLocale !== false)
			{
				$classname = str_replace('-', '_', $mainLocale) . 'Date';

				if (!class_exists($classname))
				{
					// The class does not exist, default to JDate
					$classname = 'Date';
				}
			}
			else
			{
				// No tag, so default to JDate
				$classname = 'Date';
			}
		}

		$key = $time . '-' . ($tzOffset instanceof DateTimeZone ? $tzOffset->getName() : (string) $tzOffset);

		if (!isset(self::$dates[$classname][$key]))
		{
			self::$dates[$classname][$key] = new $classname($time, $tzOffset);
		}

		$date = clone self::$dates[$classname][$key];

		return $date;
	}

	/**
	 * Get a dispatcher object
	 *
	 * Returns the global {@link Dispatcher} object, only creating it if it doesn't already exist.
	 *
	 * @return  Dispatcher
	 *
	 * @see     Dispatcher
	 */
	public static function getDispatcher()
	{
		if (!self::$dispatcher)
		{
			self::$dispatcher = new Dispatcher();
		}
		return self::$dispatcher;
	}

	/**
	 * Method to get the application session object.
	 *
	 * @return  Session  The session object
	 *
	 * @since   11.3
	 */
	public static function getSession()
	{
		if (!self::$session)
		{
			self::$session = Session::getInstance();
		}
		return self::$session;
	}

	/**
	 * Method to get the application session object.
	 *
	 * @return  Router  The session object
	 *
	 * @since   11.3
	 */
	public static function getRouter()
	{
		if (!self::$session)
		{
			self::$session = Router::getInstance();
		}
		return self::$session;
	}
}
