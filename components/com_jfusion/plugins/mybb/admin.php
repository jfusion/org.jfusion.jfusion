<?php

/**
 * file containing administrator function for the jfusion plugin
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MyBB
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin Class for MyBB
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MyBB
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_mybb extends JFusionAdmin 
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'mybb';
    }

    /**
     * @return string
     */
    function getTablename() {
        return 'users';
    }

    /**
     * @param string $forumPath
     * @return array
     */
    function setupFromPath($forumPath) {
        //check for trailing slash and generate config file path
        if (substr($forumPath, -1) != DS) {
            $forumPath.= DS;
        }
        $myfile = $forumPath . 'inc' . DS . 'config.php';

        $params = array();
        //include config file
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
        } else {
            $config = array();
            include_once($myfile);
            $params['database_type'] = $config['database']['type'];
            $params['database_host'] = $config['database']['hostname'];
            $params['database_user'] = $config['database']['username'];
            $params['database_password'] = $config['database']['password'];
            $params['database_name'] = $config['database']['database'];
            $params['database_prefix'] = $config['database']['table_prefix'];
            $params['source_path'] = $forumPath;
            //find the source url to mybb
            $driver = $params['database_type'];
            $host = $params['database_host'];
            $user = $params['database_user'];
            $password = $params['database_password'];
            $database = $params['database_name'];
            $prefix = $params['database_prefix'];
            $options = array('driver' => $driver, 'host' => $host, 'user' => $user, 'password' => $password, 'database' => $database, 'prefix' => $prefix);
            $bb = JDatabase::getInstance($options);
            $query = 'SELECT value FROM #__settings WHERE name = \'bburl\'';
            $bb->setQuery($query);
            $bb_url = $bb->loadResult();
            if (substr($bb_url, -1) != DS) {
                $bb_url.= DS;
            }
            $params['source_url'] = $bb_url;
            $query = 'SELECT value FROM #__settings WHERE name=\'cookiedomain\'';
            $bb->setQuery($query);
            $cookiedomain = $bb->loadResult();
            $params['cookie_domain'] = $cookiedomain;
            $query = 'SELECT value FROM #__settings WHERE name=\'cookiepath\'';
            $bb->setQuery($query);
            $cookiepath = $bb->loadResult();
            $params['cookie_path'] = $cookiepath;
        }
        return $params;
    }

    /**
     * Returns the a list of users of the integrated software
     *
     * @param int $limitstart start at
     * @param int $limit number of results
     *
     * @return array
     */
    function getUserList($limitstart = 0, $limit = 0) {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT username, email from #__users';
        $db->setQuery($query,$limitstart,$limit);
        $userlist = $db->loadObjectList();
        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__users';
        $db->setQuery($query);
        //getting the results
        return $db->loadResult();
    }

    /**
     * @return array
     */
    function getUsergroupList() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT gid as id, title as name FROM #__usergroups';
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
    }

    /**
     * @return string
     */
    function getDefaultUsergroup() {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
        $usergroup_id = null;
        if(!empty($usergroups)) {
            $usergroup_id = $usergroups[0];
        }
        //we want to output the usergroup name
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT title from #__usergroups WHERE gid = ' . (int)$usergroup_id;
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * @return bool
     */
    function allowRegistration() {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT value FROM #__settings  WHERE name =\'disableregs\'';
        $db->setQuery($query);
        $disableregs = $db->loadResult();
        if ($disableregs == '0') {
            $result = true;
        } else {
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
		return 'JNO';
	}

    /**
     * do plugin support multi usergroups
     *
     * @return bool
     */
    function isMultiGroup()
    {
        return false;
    }
}
