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

if (!class_exists('JFusionWordpressHelper')) {
	require_once 'wordpresshelper.php';
}
class JFusionAdmin_wordpress extends JFusionAdmin
{
	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'wordpress';
	}

	function getTablename() {
		return 'users';
	}
	
	function getUsergroupListWPA($db) {
		$query = "SELECT option_value FROM #__options WHERE option_name = 'wp_user_roles'";
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
	
	

	function setupFromPath($forumPath) {
		//check for trailing slash and generate file path
		if (substr($forumPath, -1) == DS) {
			$myfile = $forumPath . 'wp-config.php';
		} else {
			$myfile = $forumPath . DS . 'wp-config.php';
		}

		if (($file_handle = @fopen($myfile, 'r')) === false) {
			JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
			$result = false;
			return $result;
		} else {
			//parse the file line by line to get only the config variables
			//			$file_handle = fopen($myfile, 'r');
			while (!feof($file_handle)) {
				$line = fgets($file_handle);
				if (strpos(trim($line), 'define') === 0) {
					eval($line);
				}
				if (strpos(trim($line), '$table_prefix') === 0) {
					eval($line);
				}
			}
			fclose($file_handle);
			//save the parameters into array
			$params = array();
			$params['database_host'] = DB_HOST;
			$params['database_name'] = DB_NAME;
			$params['database_user'] = DB_USER;
			$params['database_password'] = DB_PASSWORD;
			$params['database_prefix'] = $table_prefix;
			$params['database_type'] = "mysql";
			$params['source_path'] = $forumPath;
			$params['database_charset'] = DB_CHARSET;
			$driver = 'mysql';
			$options = array('driver' => $driver, 'host' => $params['database_host'], 'user' => $params['database_user'],
                        'password' => $params['database_password'], 'database' => $params['database_name'],
                        'prefix' => $params['database_prefix']);
			$db =& JDatabase::getInstance($options );

			//Find the url to Wordpress
			$query = "SELECT option_value FROM #__options WHERE option_name = 'siteurl'";
			$db->setQuery($query);
			$params['source_url'] = $db-> loadResult();
			if (substr($params['source_url'], -1) == '/') {
				$params['source_url'] = $params['source_url'];
			} else {
				//no slashes found, we need to add one
				$params['source_url'] = $params['source_url'] . '/' ;
			}

			// now get the default usergroup
			// Cannot user
			$query = "SELECT option_value FROM #__options WHERE option_name = 'default_role'";
			$db->setQuery($query);
			$default_role=$db->loadResult();
			
			$userGroupList = $this->getUsergroupListWPA($db);
			$params['usergroup']='0';
			foreach ($userGroupList as $usergroup) {
				if($usergroup->name == $default_role){
					$params['usergroup'] = $usergroup->id;
					break;
				}
			}
			return $params;
		}

	}

	function getUserList($start = 0, $count = '')
	{
		//getting the connection to the db
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT user_login as username, user_email as email from #__users';
		if(!empty($count)){
			$query .= ' LIMIT ' . $start . ', ' .$count;
		}
		$db->setQuery($query );

		//getting the results
		$userlist = $db->loadObjectList();
		return $userlist;
	}

	function getUserCount() {
		//getting the connection to the db
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT count(*) from #__users';
		$db->setQuery($query);
		//getting the results
		$no_users = $db->loadResult();
		return $no_users;
	}

	function getUsergroupList() {
		$usergroups = JFusionWordpressHelper::getUsergroupListWP();
		return $usergroups;
	}

	function getDefaultUsergroup() {
		$params = JFusionFactory::getParams($this->getJname());
		$usergroup_id = $params->get('usergroup');
		return JFusionWordpressHelper::getUsergroupNameWP($usergroup_id);
	}

	function allowRegistration() {
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT option_value FROM #__options WHERE option_name = 'users_can_register'";
		$db->setQuery($query);
		$auths = $db->loadResult();
		if (empty($auths)) {
			$result = false;
			return $result;
		} else {
			return ($auths=="1");
		}
	}


	function allowEmptyCookiePath() {
		return true;
	}
	function allowEmptyCookieDomain() {
		return true;
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
		return 'JNO';
	}
}
