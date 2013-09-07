<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team- Henk Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');


/**
 * JFusion Admin Class for Moodle 1.8+
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team - Henk Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAdmin_wordpress extends JFusionAdmin
{
	/**
	 * @var $helper JFusionHelper_wordpress
	 */
	var $helper;

	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'wordpress';
	}

    /**
     * @return string
     */
    function getTablename() {
		return 'users';
	}

    /**
     * @param JDatabaseDriver $db
     * @param string $database_prefix
     * @return array
     */
    function getUsergroupListWPA($db, $database_prefix) {
	    $query = $db->getQuery(true)
		    ->select('option_value')
		    ->from('#__options')
		    ->where('option_name = ' . $db->quote($database_prefix.'user_roles'));

		$db->setQuery($query);
		$roles_ser = $db->loadResult();
		$roles = unserialize($roles_ser);
		$keys = array_keys($roles);
		$usergroups=array();
		$count= count($keys);
		for($i=0;$i < $count;$i++) {
			$group = new stdClass;
			$group->id = $i;
			$group->name = $keys[$i];
			$usergroups[$i] = $group;
		}
		return $usergroups;
	}

    /**
     * @param string $forumPath
     * @return array|bool
     */
    function setupFromPath($forumPath) {
		//check for trailing slash and generate file path
		if (substr($forumPath, -1) == DIRECTORY_SEPARATOR) {
			$myfile = $forumPath . 'wp-config.php';
		} else {
			$myfile = $forumPath . DIRECTORY_SEPARATOR . 'wp-config.php';
		}
        $params = array();
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
			JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': '.$myfile. ' ' . JText::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
		} else {
			//parse the file line by line to get only the config variables
			//			$file_handle = fopen($myfile, 'r');
            $table_prefix = '';
	        foreach ($lines as $line) {
		        if (strpos(trim($line), 'define') === 0) {
			        eval($line);
		        }
		        if (strpos(trim($line), '$table_prefix') === 0) {
			        eval($line);
		        }
	        }

			//save the parameters into array
			$params['database_host'] = DB_HOST;
			$params['database_name'] = DB_NAME;
			$params['database_user'] = DB_USER;
			$params['database_password'] = DB_PASSWORD;
			$params['database_prefix'] = $table_prefix;
			$params['database_type'] = 'mysql';
			$params['source_path'] = $forumPath;
			$params['database_charset'] = DB_CHARSET;
			$driver = 'mysql';
			$options = array('driver' => $driver, 'host' => $params['database_host'], 'user' => $params['database_user'],
                        'password' => $params['database_password'], 'database' => $params['database_name'],
                        'prefix' => $params['database_prefix']);
			$db = JDatabaseDriver::getInstance($options );

			//Find the url to Wordpress
	        $query = $db->getQuery(true)
		        ->select('option_value')
		        ->from('#__options')
		        ->where('option_name = ' . $db->quote('siteurl'));

			$db->setQuery($query);
			$params['source_url'] = $db-> loadResult();
			if (substr($params['source_url'], -1) != '/') {
                //no slashes found, we need to add one
                $params['source_url'] = $params['source_url'] . '/' ;
			}

			// now get the default usergroup
			// Cannot user
	        $query = $db->getQuery(true)
		        ->select('option_value')
		        ->from('#__options')
		        ->where('option_name = ' . $db->quote('default_role'));

			$db->setQuery($query);
			$default_role=$db->loadResult();
			
			$userGroupList = $this->getUsergroupListWPA($db,$table_prefix);
			$params['usergroup']='0';
			foreach ($userGroupList as $usergroup) {
				if($usergroup->name == $default_role){
					$params['usergroup'] = $usergroup->id;
					break;
				}
			}
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
				->select('user_login as username, user_email as email')
				->from('#__users');

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
			    ->from('#__users');

			$db->setQuery($query);
			//getting the results
			$no_users = $db->loadResult();
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		    $no_users = 0;
		}
		return $no_users;
	}

	/**
     * @return array
     */
    function getUsergroupList() {
		$usergroups = $this->helper->getUsergroupListWP();
		return $usergroups;
	}

	/**
     * @return string
     */
    function getDefaultUsergroup() {
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
        $usergroup_id = null;
        if(!empty($usergroups)) {
            $usergroup_id = $usergroups[0];
        }
		return $this->helper->getUsergroupNameWP($usergroup_id);
	}

	/**
     * @return bool
     */
    function allowRegistration() {
	    $result = false;
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('option_value')
			    ->from('#__options')
			    ->where('option_name = ' . $db->quote('users_can_register'));

		    $db->setQuery($query);
		    $auths = $db->loadResult();

		    $result = ($auths=='1');
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
	    return $result;
	}


    /**
     * @return bool
     */
    function allowEmptyCookiePath() {
		return true;
	}

    /**
     * @return bool
     */
    function allowEmptyCookieDomain() {
		return true;
	}

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ???
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
		return true;
	}
}
