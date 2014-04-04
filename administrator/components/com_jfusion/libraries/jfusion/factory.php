<?php

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

// no direct access
defined('_JEXEC') or die('Restricted access');

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
class JFusionFactory
{
	/**
	 * Global database object
	 *
	 * @var    JDatabaseDriver
	 * @since  11.1
	 */
	public static $database = null;

	/**
	 * Global application object
	 *
	 * @var    JApplicationCms
	 * @since  11.1
	 */
	public static $application = null;

	/**
	 * Global configuration object
	 *
	 * @var    JRegistry
	 * @since  11.1
	 */
	public static $config = null;

	/**
	 * Global document object
	 *
	 * @var    JDocument
	 * @since  11.1
	 */
	public static $document = null;

	/**
	 * Global language object
	 *
	 * @var    JLanguage
	 * @since  11.1
	 */
	public static $language = null;

	/**
	 * Container for JDate instances
	 *
	 * @var    array
	 * @since  11.3
	 */
	public static $dates = array();

    /**
     * Gets an Fusion front object
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return JFusionPublic object for the JFusion plugin
     */
    public static function &getPublic($jname)
    {
        static $instances;
        if (!isset($instances)) {
	        $instances = array();
        }
        //only create a new plugin instance if it has not been created before
        if (!isset($instances[$jname])) {
            $filename = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'public.php';
            if (file_exists($filename)) {
                //load the plugin class itself
	            require_once $filename;
                $class = 'JFusionPublic_' . $jname;
            } else {
                $class = 'JFusionPublic';
            }
	        $instances[$jname] = new $class;
        }
	    return $instances[$jname];
    }
    /**
     * Gets an Fusion front object
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return JFusionAdmin object for the JFusion plugin
     */
    public static function &getAdmin($jname)
    {
        static $instances;
        if (!isset($instances)) {
	        $instances = array();
        }
        //only create a new plugin instance if it has not been created before
        if (!isset($instances[$jname])) {
            $filename = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'admin.php';
            if (file_exists($filename)) {
                //load the plugin class itself
                $jn = $jname;
	            require_once $filename;
                $jname = $jn; // (stop gap bug #: some plugins seems to alter $jname, have to find put why
                $class = 'JFusionAdmin_' . $jname;
            } else {
                $class = 'JFusionAdmin';
            }
	        $instances[$jname] = new $class;
        }
        return $instances[$jname];
    }

    /**
     * Gets an Authentication Class for the JFusion Plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return JFusionAuth JFusion Authentication class for the JFusion plugin
     */
    public static function &getAuth($jname)
    {
        static $instances;
        if (!isset($instances)) {
	        $instances = array();
        }
        //only create a new authentication instance if it has not been created before
        if (!isset($instances[$jname])) {
            $filename = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'auth.php';
            if (file_exists($filename)) {
                //load the plugin class itself
	            require_once $filename;
                $class = 'JFusionAuth_' . $jname;
            } else {
                $class = 'JFusionAuth';
            }
	        $instances[$jname] = new $class;
        }
	    return $instances[$jname];
    }

    /**
     * Gets an User Class for the JFusion Plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return JFusionUser JFusion User class for the JFusion plugin
     */
    public static function &getUser($jname)
    {
        static $instances;
        if (!isset($instances)) {
	        $instances = array();
        }
        //only create a new user instance if it has not been created before
        if (!isset($instances[$jname])) {
            $filename = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'user.php';
            if (file_exists($filename)) {
                //load the plugin class itself
	            require_once $filename;
                $class = 'JFusionUser_' . $jname;
            } else {
                $class = 'JFusionUser';
            }
	        $instances[$jname] = new $class;
        }
	    return $instances[$jname];
    }

    /**
     * Gets a Forum Class for the JFusion Plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return JFusionForum JFusion Thread class for the JFusion plugin
     */
    public static function &getForum($jname)
    {
        static $instances;
        if (!isset($instances)) {
	        $instances = array();
        }
        //only create a new thread instance if it has not been created before
        if (!isset($instances[$jname])) {
            $filename = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'forum.php';
            if (file_exists($filename)) {
                //load the plugin class itself
	            require_once $filename;
                $class = 'JFusionForum_' . $jname;
            } else {
                $class = 'JFusionForum';
            }
	        $instances[$jname] = new $class;
        }
	    return $instances[$jname];
    }

    /**
     * Gets a Helper Class for the JFusion Plugin which is only used internally by the plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JFusionHelper JFusion Helper class for the JFusion plugin
     */
    public static function &getHelper($jname)
    {
        static $instances;
        if (!isset($instances)) {
	        $instances = array();
        }
        //only create a new thread instance if it has not been created before
        if (!isset($instances[$jname])) {
            $filename = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'helper.php';
            if (file_exists($filename)) {
                //load the plugin class itself
	            require_once $filename;
                $class = 'JFusionHelper_' . $jname;
	            $instances[$jname] = new $class;
            } else {
	            $instances[$jname] = false;
            }
        }
	    return $instances[$jname];
    }

    /**
     * Gets an Database Connection for the JFusion Plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return JDatabaseDriver Database connection for the JFusion plugin
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
     * @return JRegistry JParam object for the JFusion plugin
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
	 * @return JRegistry JParam object for the JFusion plugin
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
        //$parametersInstance = new JRegistry('', $file );
        //now load params without XML files, as this creates overhead when only values are needed
        $parametersInstance = new JRegistry($params);

        if (!is_object($parametersInstance)) {
	        throw new RuntimeException(JText::_('NO_FORUM_PARAMETERS'));
        }
        return $parametersInstance;
    }
    /**
     * Acquires a database connection to the database of the software integrated by JFusion
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return JDatabaseDriver database object
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
            if ($driver != 'mysql' && $driver != 'mysqli') {
                //invalid driver
	            throw new RuntimeException(JText::_('INVALID_DRIVER'));
            } else {
	            $options = array('driver' => $driver, 'host' => $host, 'user' => $user, 'password' => $password, 'database' => $database, 'prefix' => $prefix);

	            jimport('joomla.database.database');
	            jimport('joomla.database.table');

	            $db = JDatabaseDriver::getInstance($options);

	            //add support for UTF8
	            $db->setQuery('SET names ' . $db->quote($charset));
	            $db->execute();

	            //get the debug configuration setting
	            $db->setDebug(JFusionFactory::getConfig()->get('debug'));
            }
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
	    $db = JFusionFactory::getDBO();

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
     * Returns an object of the specified parser class
     * @param string $type
     *
     * @return BBCode_Parser of parser class
     */
    public static function &getCodeParser($type = 'bbcode') {
        static $instance;

	    if (!isset($instance)) {
		    $instance = array();
	    }

        if (empty($instance[$type])) {
            switch ($type) {
                case 'bbcode':
                    if (!class_exists('BBCode_Parser')) {
	                    require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models'. DIRECTORY_SEPARATOR . 'parsers' . DIRECTORY_SEPARATOR . 'nbbc.php';
                    }
	                $instance[$type] = new BBCode_Parser;
                    break;
                default:
	                $instance[$type] = false;
                    break;
            }
        }
        return $instance[$type];
    }
    
    /**
     * Gets an JFusion cross domain cookie object
     *
     * @return JFusionCookies object for the JFusion cookies
     */
    public static function &getCookies() {
    	static $instance;
    	//only create a new plugin instance if it has not been created before
    	if (!isset($instance)) {
		    $instance = new JFusionCookies(static::getParams('joomla_int')->get('secret'));
    	}
    	return $instance;
    }

	/**
	 * @param string $jname
	 *
	 * @return JFusionDebugger
	 */
	public static function &getDebugger($jname)
	{
		static $instances;

		if (!isset($instances)) {
			$instances = array();
		}

		if (!isset($instances[$jname])) {
			$instances[$jname] = new JFusionDebugger();
		}
		return $instances[$jname];
	}




























	/**
	 * Get a database object.
	 *
	 * Returns the global {@link JDatabaseDriver} object, only creating it if it doesn't already exist.
	 *
	 * @return  JDatabaseDriver
	 *
	 * @see     JDatabaseDriver
	 * @since   11.1
	 */
	public static function getDbo()
	{
		if (!self::$database)
		{
			/*
			 * TODO CREATE DBO
			 */
		}
		return self::$database;
	}

	/**
	 * Get a application object.
	 *
	 * Returns the global {@link JApplicationCms} object, only creating it if it doesn't already exist.
	 *
	 * @param   mixed   $id      A client identifier or name.
	 * @param   array   $config  An optional associative array of configuration settings.
	 * @param   string  $prefix  Application prefix
	 *
	 * @return  JApplicationCms object
	 *
	 * @see     JApplication
	 * @since   11.1
	 * @throws  Exception
	 */
	public static function getApplication($id = null, array $config = array(), $prefix = 'J')
	{
		if (!self::$application)
		{
			throw new Exception('Application Instantiation Error', 500);
			/**
			 * TODO CREATE Application
			 */
		}

		return self::$application;
	}

	/**
	 * Get a configuration object
	 *
	 * Returns the global {@link JRegistry} object, only creating it if it doesn't already exist.
	 *
	 * @param   string  $file       The path to the configuration file
	 * @param   string  $type       The type of the configuration file
	 * @param   string  $namespace  The namespace of the configuration file
	 *
	 * @return  JRegistry
	 *
	 * @see     JRegistry
	 * @since   11.1
	 */
	public static function getConfig($file = null, $type = 'PHP', $namespace = '')
	{
		if (!self::$config)
		{
			/**
			 * TODO CREATE Config
			 */
		}
		return self::$config;
	}

	/**
	 * Get a document object.
	 *
	 * Returns the global {@link JDocument} object, only creating it if it doesn't already exist.
	 *
	 * @return  JDocument object
	 *
	 * @see     JDocument
	 * @since   11.1
	 */
	public static function getDocument()
	{
		if (!self::$document)
		{
			/**
			 * TODO CREATE Config
			 */
		}

		return self::$document;
	}

	/**
	 * Get a language object.
	 *
	 * Returns the global {@link JLanguage} object, only creating it if it doesn't already exist.
	 *
	 * @return  JLanguage object
	 *
	 * @see     JLanguage
	 * @since   11.1
	 */
	public static function getLanguage()
	{
		if (!self::$language)
		{
			/**
			 * TODO CREATE Config
			 */
		}
		return self::$language;
	}

	/**
	 * Return the {@link JDate} object
	 *
	 * @param   mixed  $time      The initial time for the JDate object
	 * @param   mixed  $tzOffset  The timezone offset.
	 *
	 * @return  JDate object
	 *
	 * @see     JDate
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
					$classname = 'JDate';
				}
			}
			else
			{
				// No tag, so default to JDate
				$classname = 'JDate';
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
}
