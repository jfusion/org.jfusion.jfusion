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
 * JFusion Admin Class for elgg
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

    function getTablename() {
        return 'users_entity';
    }
    function loadSetup($path) {
        //generate the destination file
        if (substr($path, -1) != DS) {
            $myfile = $path . DS . 'engine' . DS . 'settings.php';
        } else {
            $myfile = $path . 'engine' . DS . 'settings.php';
        }
        //check if the file exists
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
            $result = false;
            return $result;
        } else {
            //parse the file line by line to get only the config variables
            $file_handle = fopen($myfile, 'r');
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                $parts = explode("=", $line);
                if (isset($parts[0]) && isset($parts[1])) {
                    $key = trim(preg_replace('/[^\n]*\$CONFIG->/ ', "", $parts[0]));
                    $value = trim(str_replace(array('"', "'", ";"), "", $parts[1]));
                    $config[$key] = $value;
                }
            }
            fclose($file_handle);
        }
        return $config;
    }
    function setupFromPath($path) {
        $config = JFusionAdmin_elgg::loadSetup($path);
        if (!empty($config)) {
            //save the parameters into array
            $params = array();
            $params['database_host'] = $config['dbhost'];
            $params['database_name'] = $config['dbname'];
            $params['database_user'] = $config['dbuser'];
            $params['database_password'] = $config['dbpass'];
            $params['database_prefix'] = $config['dbprefix'];
            $params['database_type'] = "mysql";
            $params['source_path'] = $path;
            return $params;
        }
    }
    function getUserList($start = 0, $count = '') {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT username, email from #__users_entity';
        if (!empty($count)) {
            $query.= ' LIMIT ' . $start . ', ' . $count;
        }
        $db->setQuery($query);
        //getting the results
        $userlist = $db->loadObjectList();
        return $userlist;
    }
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__users_entity';
        $db->setQuery($query);
        //getting the results
        return $db->loadResult();
    }
    function getUsergroupList() {
        //NOT IMPLEMENTED YET!
        $default_group = new stdClass;
        $default_group->name = "user";
        $default_group->id = "1";
        $UsergroupList[] = $default_group;
        return $UsergroupList;
    }
    function getDefaultUsergroup() {
        //Only seems to be 2 usergroups in elgg (without any acl setup): Administrator, and user.  So just return 'user'
        return 'user';
    }
    function allowRegistration() {
        $params = JFusionFactory::getParams($this->getJname());
        include_once $params->get('source_path') . DS . "engine" . DS . "start.php";
        // Get variables
        global $CONFIG;
        if (isset($CONFIG->disable_registration) && $CONFIG->disable_registration == 'true') {
			$result = false;
            return $result;
        } else {
            $result = true;
            return $result;
        }
    }
	/*
	 * do plugin support multi usergroups
	 * return UNKNOWN for unknown
	 * return JNO for NO
	 * return JYES for YES
	 * return ... ??
	 */
	function requireFileAccess()
	{
		return 'JYES';
	}    
}
