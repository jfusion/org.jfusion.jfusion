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
 * For detailed descriptions on these functions please check JFusionAdmin
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
    function getTablename()
    {
        return 'users';
    }

    /**
     * @param string $softwarePath
     *
     * @return array
     */
    function setupFromPath($softwarePath)
    {
        $myfile = $softwarePath . 'inc' . DIRECTORY_SEPARATOR . 'config.php';

        $params = array();
        //include config file
        if (!file_exists($myfile)) {
            JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . JText::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        } else {
            $config = array();
            include_once($myfile);
            $params['database_type'] = $config['database']['type'];
            $params['database_host'] = $config['database']['hostname'];
            $params['database_user'] = $config['database']['username'];
            $params['database_password'] = $config['database']['password'];
            $params['database_name'] = $config['database']['database'];
            $params['database_prefix'] = $config['database']['table_prefix'];
            $params['source_path'] = $softwarePath;
            //find the source url to mybb
            $driver = $params['database_type'];
            $host = $params['database_host'];
            $user = $params['database_user'];
            $password = $params['database_password'];
            $database = $params['database_name'];
            $prefix = $params['database_prefix'];
            $options = array('driver' => $driver, 'host' => $host, 'user' => $user, 'password' => $password, 'database' => $database, 'prefix' => $prefix);
	        $db = JDatabaseDriver::getInstance($options);

	        $query = $db->getQuery(true)
		        ->select('value')
		        ->from('#__settings')
		        ->where('name = ' . $db->quote('bburl'));

	        $db->setQuery($query);
            $bb_url = $db->loadResult();
            if (substr($bb_url, -1) != DIRECTORY_SEPARATOR) {
                $bb_url.= DIRECTORY_SEPARATOR;
            }
            $params['source_url'] = $bb_url;

	        $query = $db->getQuery(true)
		        ->select('value')
		        ->from('#__settings')
		        ->where('name = ' . $db->quote('cookiedomain'));

	        $db->setQuery($query);
            $cookiedomain = $db->loadResult();
            $params['cookie_domain'] = $cookiedomain;

	        $query = $db->getQuery(true)
		        ->select('value')
		        ->from('#__settings')
		        ->where('name = ' . $db->quote('cookiepath'));

	        $db->setQuery($query);
            $cookiepath = $db->loadResult();
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
    function getUserList($limitstart = 0, $limit = 0)
    {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = $db->getQuery(true)
			    ->select('username, email')
			    ->from('#__users');

		    $db->setQuery($query, $limitstart, $limit);
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
    function getUserCount()
    {
	    try {
	        //getting the connection to the db
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__users');

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
    function getUsergroupList()
    {
	    //getting the connection to the db
	    $db = JFusionFactory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('gid as id, title as name')
		    ->from('#__usergroups');

	    $db->setQuery($query);
	    //getting the results
	    return $db->loadObjectList();
    }

    /**
     * @return string
     */
    function getDefaultUsergroup()
    {
	    $usergroups = JFusionFunction::getUserGroups($this->getJname(), true);

	    $group = '';
	    if ($usergroups !== null) {
		    //we want to output the usergroup name
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('title')
			    ->from('#__usergroups')
			    ->where('gid = ' . (int)$usergroups);

		    $db->setQuery($query);
		    $group = $db->loadResult();
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
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('value')
			    ->from('#__settings')
			    ->where('name = ' . $db->quote('disableregs'));

	        $db->setQuery($query);
	        $disableregs = $db->loadResult();
	        if ($disableregs == '0') {
	            $result = true;
	        }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
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
