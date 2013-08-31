<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Elgg
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin Class for Elgg
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Elgg
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_elgg extends JFusionAdmin
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'elgg';
    }

    /**
     * @return string
     */
    function getTablename() {
        return 'users_entity';
    }

    /**
     * @param $path
     * @return array|bool
     */
    function loadSetup($path) {
        //generate the destination file
        if (substr($path, -1) != DIRECTORY_SEPARATOR) {
            $myfile = $path . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'settings.php';
        } else {
            $myfile = $path . 'engine' . DIRECTORY_SEPARATOR . 'settings.php';
        }
        $config = array();
        //check if the file exists
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
            JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': '.$myfile. ' ' . JText::_('WIZARD_MANUAL'), $this->getJname());
        } else {
            //parse the file line by line to get only the config variables
	        foreach ($lines as $line) {
		        $parts = explode('=', $line);
		        if (isset($parts[0]) && isset($parts[1])) {
			        $key = trim(preg_replace('/[^\n]*\$CONFIG->/ ', '', $parts[0]));
			        $value = trim(str_replace(array('"', '\'', ';'), '', $parts[1]));
			        $config[$key] = $value;
		        }
	        }
        }
        return $config;
    }

    /**
     * @param string $path
     * @return array
     */
    function setupFromPath($path) {
        $config = JFusionAdmin_elgg::loadSetup($path);
        $params = array();
        if (!empty($config)) {
            //save the parameters into array
            $params = array();
            $params['database_host'] = $config['dbhost'];
            $params['database_name'] = $config['dbname'];
            $params['database_user'] = $config['dbuser'];
            $params['database_password'] = $config['dbpass'];
            $params['database_prefix'] = $config['dbprefix'];
            $params['database_type'] = 'mysql';
            $params['source_path'] = $path;
        }
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
    function getUserList($limitstart = 0, $limit = 0) {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('username, email')
			    ->from('#__users_entity');

		    $db->setQuery($query,$limitstart,$limit);
		    //getting the results
		    $userlist = $db->loadObjectList();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $userlist = array();
	    }
        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount() {
	    try {
	        //getting the connection to the db
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__users_entity');

	        $db->setQuery($query);
	        //getting the results
	        return $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    return 0;
	    }
    }

    /**
     * @return array
     */
    function getUsergroupList() {
        //NOT IMPLEMENTED YET!
        $default_group = new stdClass;
        $default_group->name = 'user';
        $default_group->id = '1';
        $UsergroupList[] = $default_group;
        return $UsergroupList;
    }

    /**
     * @return string
     */
    function getDefaultUsergroup() {
        //Only seems to be 2 usergroups in elgg (without any acl setup): Administrator, and user.  So just return 'user'
        return 'user';
    }

    /**
     * @return bool
     */
    function allowRegistration() {
        include_once $this->params->get('source_path') . DIRECTORY_SEPARATOR . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
        // Get variables
        global $CONFIG;
        $result = true;
	    if (isset($CONFIG->allow_registration) && $CONFIG->allow_registration == 'false') {
		    $result = false;
	    } else if (isset($CONFIG->disable_registration) && $CONFIG->disable_registration == 'true') {
			$result = false;
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
}
