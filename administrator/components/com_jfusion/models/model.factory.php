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

require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'defines.php';

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
class JfusionParam extends JParameter
{
    function set($key, $value = '', $group = '_default')
    {
        return $this->setValue($group . '.' . $key, $value);
    }
}

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
     * Gets an Fusion front object
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JFusionPlugin JFusionPlugin object for the JFusion plugin
     */
    public static function &getPublic($jname)
    {
        static $public_instances;
        if (!isset($public_instances)) {
            $public_instances = array();
        }
        //only create a new plugin instance if it has not been created before
        if (!isset($public_instances[$jname])) {
            //load the Abstract Public Class
            include_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractpublic.php';

            $filename = JFUSION_PLUGIN_PATH . DS . $jname . DS . 'public.php';
            if (file_exists($filename)) {
                //load the plugin class itself
                include_once $filename;
                $class = "JFusionPublic_" . $jname;
            } else {
                $class = "JFusionPublic";
            }
            $public_instances[$jname] = new $class;
            return $public_instances[$jname];
        } else {
            return $public_instances[$jname];
        }
    }
    /**
     * Gets an Fusion front object
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JFusionPlugin JFusionPlugin object for the JFusion plugin
     */
    public static function &getAdmin($jname)
    {
        static $admin_instances;
        if (!isset($admin_instances)) {
            $admin_instances = array();
        }
        //only create a new plugin instance if it has not been created before
        if (!isset($admin_instances[$jname])) {
            //load the Abstract Admin Class
            include_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractadmin.php';

            $filename = JFUSION_PLUGIN_PATH . DS . $jname . DS . 'admin.php';
            if (file_exists($filename)) {
                //load the plugin class itself
                $jn = $jname;
                include_once $filename;
                $jname = $jn; // (stop gap bug #: some plugins seems to alter $jname, have to find put why
                $class = "JFusionAdmin_" . $jname;
            } else {
                $class = "JFusionAdmin";
            }
            $admin_instances[$jname] = new $class;
            return $admin_instances[$jname];
        } else {
            return $admin_instances[$jname];
        }
    }

    /**
     * Gets an Authentication Class for the JFusion Plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JFusionAuth JFusion Authentication class for the JFusion plugin
     */
    public static function &getAuth($jname)
    {
        static $auth_instances;
        if (!isset($auth_instances)) {
            $auth_instances = array();
        }
        //only create a new authentication instance if it has not been created before
        if (!isset($auth_instances[$jname])) {
            //load the Abstract Auth Class
            include_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractauth.php';
            $filename = JFUSION_PLUGIN_PATH . DS . $jname . DS . 'auth.php';
            if (file_exists($filename)) {
                //load the plugin class itself
                include_once $filename;
                $class = "JFusionAuth_" . $jname;
            } else {
                $class = "JFusionAuth";
            }
            $auth_instances[$jname] = new $class;
            return $auth_instances[$jname];

        } else {
            return $auth_instances[$jname];
        }
    }

    /**
     * Gets an User Class for the JFusion Plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JFusionUser JFusion User class for the JFusion plugin
     */
    public static function &getUser($jname)
    {
        static $user_instances;
        if (!isset($user_instances)) {
            $user_instances = array();
        }
        //only create a new user instance if it has not been created before
        if (!isset($user_instances[$jname])) {
            //load the User Public Class
            include_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractuser.php';
            $filename = JFUSION_PLUGIN_PATH . DS . $jname . DS . 'user.php';
            if (file_exists($filename)) {
                //load the plugin class itself
                include_once $filename;
                $class = "JFusionUser_" . $jname;
            } else {
                $class = "JFusionUser";
            }
            $user_instances[$jname] = new $class;
            return $user_instances[$jname];

        } else {
            return $user_instances[$jname];
        }
    }

    /**
     * Gets a Forum Class for the JFusion Plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JFusionForum JFusion Thread class for the JFusion plugin
     */
    public static function &getForum($jname)
    {
        static $forum_instances;
        if (!isset($forum_instances)) {
            $forum_instances = array();
        }
        //only create a new thread instance if it has not been created before
        if (!isset($forum_instances[$jname])) {
            //load the Abstract Forum Class
            include_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractforum.php';
            $filename = JFUSION_PLUGIN_PATH . DS . $jname . DS . 'forum.php';
            if (file_exists($filename)) {
                //load the plugin class itself
                include_once $filename;
                $class = "JFusionForum_" . $jname;
            } else {
                $class = "JFusionForum";
            }
            $forum_instances[$jname] = new $class;
            return $forum_instances[$jname];
        } else {
            return $forum_instances[$jname];
        }
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
        static $helper_instances;
        if (!isset($helper_instances)) {
            $helper_instances = array();
        }
        //only create a new thread instance if it has not been created before
        if (!isset($helper_instances[$jname])) {
            $filename = JFUSION_PLUGIN_PATH . DS . $jname . DS . 'helper.php';
            if (file_exists($filename)) {
                //load the plugin class itself
                include_once $filename;
                $class = "JFusionHelper_" . $jname;
                $helper_instances[$jname] = new $class;
                return $helper_instances[$jname];
            } else {
                $return = false;
                return $return;
            }
        } else {
            return $helper_instances[$jname];
        }
    }

    /**
     * Gets an Database Connection for the JFusion Plugin
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JDatabase Database connection for the JFusion plugin
     */
    public static function &getDatabase($jname)
    {
        static $database_instances;
        if (!isset($database_instances)) {
            $database_instances = array();
        }
        //only create a new database instance if it has not been created before
        if (!isset($database_instances[$jname])) {
            $database_instances[$jname] = & JFusionFactory::createDatabase($jname);
            return $database_instances[$jname];
        } else {
            return $database_instances[$jname];
        }
    }

    /**
     * Gets an Parameter Object for the JFusion Plugin
     *
     * @param string  $jname name of the JFusion plugin used
     * @param boolean $reset switch to force a recreate of the instance
     *
     * @return object JParam JParam object for the JFusion plugin
     */
    public static function &getParams($jname, $reset = false)
    {
        static $params_instances;
        if (!isset($params_instances)) {
            $params_instances = array();
        }
        //only create a new parameter instance if it has not been created before
        if (!isset($params_instances[$jname]) || $reset) {
            $params_instances[$jname] = & JFusionFactory::createParams($jname);
            return $params_instances[$jname];
        } else {
            return $params_instances[$jname];
        }
    }

    /**
     * creates new param object
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JParam JParam object for the JFusion plugin
     */
    public static function &createParams($jname)
    {
        jimport('joomla.html.parameter');
        //get the current parameters from the jfusion table
        $db = JFactory::getDBO();
        $query = 'SELECT params from #__jfusion WHERE name = ' . $db->Quote($jname);
        $db->setQuery($query);
        $serialized = $db->loadResult();
        //get the parameters from the XML file
        //$file = JFUSION_PLUGIN_PATH .DS. $jname . DS.'jfusion.xml';
        //$parametersInstance = new JParameter('', $file );
        //now load params without XML files, as this creates overhead when only values are needed
        $parametersInstance = new JParameter('');
        //apply the stored valued
        if ($serialized) {
            $params = unserialize(base64_decode($serialized));
            if (is_array($params)) {
                foreach ($params as $key => $value) {
                    if (is_array($value)) {
                        $value = serialize($value);
                    }
                    $parametersInstance->set($key, $value);
                }
            }
        }
        if (!is_object($parametersInstance)) {
            JError::raiseError(500, JText::_('NO_FORUM_PARAMETERS'));
        }
        return $parametersInstance;
    }
    /**
     * Acquires a database connection to the database of the software integrated by JFusion
     *
     * @param string $jname name of the JFusion plugin used
     *
     * @return object JDatabase
     */
    public static function &createDatabase($jname)
    {
        //check to see if joomla DB is requested
        if ($jname == 'joomla_int') {
            $db = JFactory::getDBO();
            return $db;
        }
        //get the debug configuration setting
        $conf = JFactory::getConfig();
        $debug = $conf->getValue('config.debug');
        //get config values
        $conf = JFactory::getConfig();
        $params = & JFusionFactory::getParams($jname);
        //prepare the data for creating a database connection
        $host = $params->get('database_host');
        $user = $params->get('database_user');
        $password = $params->get('database_password');
        $database = $params->get('database_name');
        $prefix = $params->get('database_prefix','');
        $driver = $params->get('database_type');
        $debug = $conf->getValue('config.debug');
        $charset = $params->get('database_charset', 'utf8');
        //added extra code to prevent error when $driver is incorrect
        if ($driver != 'mysql' && $driver != 'mysqli') {
            //invalid driver
            JError::raiseWarning(0, JText::_('INVALID_DRIVER'));
            $result = false;
            return $result;
        }
        //create an options variable that contains all database connection variables
        $options = array('driver' => $driver, 'host' => $host, 'user' => $user, 'password' => $password, 'database' => $database, 'prefix' => $prefix);
        //make sure the database model is loaded
        jimport('joomla.database.database');
        jimport('joomla.database.table');
        //create the actual connection
        include_once dirname(__FILE__) . DS . $driver . '.php';
        if ($driver == 'mysql') {
            $jfusionDatabase = new JFusionMySQL($options);
        } else {
            $jfusionDatabase = new JFusionMySQLi($options);
        }
        if (!method_exists($jfusionDatabase, 'Query')) {
            JError::raiseWarning(0, JText::_('NO_DATABASE'));
            $result = false;
            return $result;
        } else {
        	if($jfusionDatabase->getErrorNum()) {
        		JError::raiseWarning(0, JText::_('DATABASE_ERROR') . ': ' . $jfusionDatabase->getErrorMsg());
        		$result = false;
        		return $result;
        	}        	
            //add support for UTF8
            $jfusionDatabase->Execute('SET names ' . $jfusionDatabase->quote($charset));
            //support debugging
            $jfusionDatabase->debug($debug);
            return $jfusionDatabase;
        }
    }
        /**
     * Returns a an object array with all active plugins
     * @return array of objects
     */
    public static function getPlugins() {
        static $jfusion_plugins;
        if (!isset($jfusion_plugins)) {
            $db = JFactory::getDBO();
            $query = 'SELECT id, name, status, dual_login from #__jfusion WHERE status = 1 AND (master = 1 OR slave = 1)';
            $db->setQuery($query);
            $jfusion_plugins = $db->loadObjectList();
        }
        return $jfusion_plugins;
    }
    /**
     * Gets the jnode_id for the JFusion Plugin
     * @param string $jname name of the JFusion plugin used
     * @return string jnodeid for the JFusion Plugin
     */
    public static function getPluginNodeId($jname) {
        $params = JFusionFactory::getParams($jname);
        $source_url = $params->get('source_url');
        return strtolower(rtrim(parse_url($source_url, PHP_URL_HOST) . parse_url($source_url, PHP_URL_PATH), '/'));
    }
    /**
     * Gets the plugin name for a JFusion Plugin given the jnodeid
     * @param string $jnodeid jnodeid to use
     * @return string jname name for the JFusion Plugin, empty if no plugin found
     */
    public static function getPluginNameFromNodeId($jnode_id) {
        $result = '';
        //$jid = $jnode_id;
        $plugins = JFusionFactory::getPlugins();
        foreach($plugins as $plugin) {
            $id = rtrim(JFusionFactory::getPluginNodeId($plugin->name), '/');
            if (strcasecmp($jnode_id, $id) == 0) {
                $result = $plugin->name;
                break;
            }
        }
        return $result;
    }
    /**
     * Returns an object of the specified parser class
     * @param $type
     * @return object of parser class
     */
    public static function &getCodeParser($type = 'bbcode') {
        static $jfusion_code_parsers;

        if (!is_array($jfusion_code_parsers)) {
            $jfusion_code_parsers = array();
        } 

        if (empty($jfusion_code_parsers[$type])) {
            switch ($type) {
                case 'bbcode':
                    if (!class_exists('BBCode_Parser')) {
                        include_once 'parsers'.DS.'nbbc.php';
                    }
                    $jfusion_code_parsers[$type] = new BBCode_Parser;
                    break;
                default:
                    $jfusion_code_parsers[$type] = false;
                    break;
            }
        }

        return $jfusion_code_parsers[$type];
    }
    
    /**
     * Gets an JFusion cross domain cookie object
     * @return object JFusionCookies JFusionCookies object for the JFusion cookies
     */
    public static function &getCookies() {
    	static $instances;
    	//only create a new plugin instance if it has not been created before
    	if (!isset($instances)) {
    		//  load the Abstract Public Class
    		require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.cookie.php');
    		$instances = new JFusionCookies();
    	}
    	return $instances;
    }
}
