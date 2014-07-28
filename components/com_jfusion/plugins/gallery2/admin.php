<?php namespace JFusion\Plugins\gallery2;

/**
 * file containing administrator function for the jfusion plugin
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2 
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

use \Exception;
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion plugin class for Gallery2
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2 
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
     * @return string
     */
    function getTablename()
    {
        return 'User';
    }

    /**
     * @param string $softwarePath
     *
     * @return array
     */
    function setupFromPath($softwarePath)
    {
	    $myfile = $softwarePath . 'config.php';

        $params = array();
        $config = array();
        //try to open the file
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
            Framework::raise(LogLevel::WARNING, Text::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
            //get the default parameters object
        } else {
            //parse the file line by line to get only the config variables
	        foreach ($lines as $line) {
		        if (strpos($line, '$storeConfig') === 0) {
			        preg_match('/.storeConfig\[\'(.*)\'\] = (.*);/', $line, $matches);
			        $name = trim($matches[1], " '");
			        $value = trim($matches[2], " '");
			        $config[$name] = $value;
		        }
		        if (strpos($line, '$gallery->setConfig') === 0) {
			        preg_match('/.gallery->setConfig\(\'(.*)\',(.*)\)/', $line, $matches);
			        $name = trim($matches[1], " '");
			        $value = trim($matches[2], " '");
			        $config[$name] = $value;
		        }
	        }
            $params['database_host'] = $config['hostname'];
            $params['database_type'] = $config['type'];
            $params['database_name'] = $config['database'];
            $params['database_user'] = $config['username'];
            $params['database_password'] = $config['password'];
            $params['database_prefix'] = $config['tablePrefix'];
            $params['source_url'] = str_replace('main.php', '', $config['baseUri']);
            $params['cookie_name'] = '';
            $params['source_path'] = $softwarePath;
            if (!in_array($params['database_type'], array('mysql', 'mysqli'))) {
                if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
                    $params['database_type'] = 'mysql';
                } else {
                    $params['database_type'] = 'mysqli';
                }
            }
        }
        //Save the parameters into the standard JFusion params format
        return $params;
    }

    /**
     * Get a list of users
     *
     * @param int $limitstart
     * @param int $limit
     *
     * @return array
     */
    function getUserList($limitstart = 0, $limit = 0)
    {
	    try {
	        // initialise some objects
	        $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('g_userName as username, g_email as email, g_id as userid')
			    ->from('#__User')
			    ->where('g_id != 5');

	        $db->setQuery($query, $limitstart, $limit);
	        $userlist = $db->loadObjectList();
	    } catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $userlist = array();
		}
        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount()
    {
	    try {
	        //getting the connection to the db
	        $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__User')
			    ->where('g_id != 5');

	        $db->setQuery($query);
	        //getting the results
	        $no_users = $db->loadResult();
	    } catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $no_users = 0;
		}
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList()
    {
	    //getting the connection to the db
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('g_id as id, g_groupName as name')
		    ->from('#__Group')
		    ->where('g_id != 4');

	    $db->setQuery($query);
	    //getting the results
	    return $db->loadObjectList();
    }
    /**
     * @return array
     */
    function getDefaultUsergroup()
    {
	    $usergroups = Framework::getUserGroups($this->getJname(), true);

	    $group = array();
	    if ($usergroups !== null) {
		    $db = Factory::getDatabase($this->getJname());

		    foreach($usergroups as $usergroup) {
			    $query = $db->getQuery(true)
				    ->select('g_groupName')
				    ->from('#__Group')
				    ->where('g_id = ' . $db->quote((int)$usergroup));

			    $db->setQuery($query);
			    $group[] = $db->loadResult();
		    }
	    }
	    return $group;
    }
    /**
     * @return bool
     */
    function allowRegistration()
    {
	    $result = false;
	    try {
	        $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('g_active')
			    ->from('#__PluginMap')
			    ->where('g_pluginType = ' . $db->quote('module'))
			    ->where('g_pluginId = ' . $db->quote('register'));

	        $db->setQuery($query);
	        $new_registration = $db->loadResult();
		    if ($new_registration != 0) {
			    $result = true;
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
	    return $result;
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
