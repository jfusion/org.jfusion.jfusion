<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage eFront
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2009 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractuser.php';
if (!class_exists('JFusionEfrontHelper')) {
   require_once 'efronthelper.php';
}

/**
 * JFusion Admin Class for eFront 3.5+
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage eFront
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2009 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */



class JFusionAdmin_efront extends JFusionAdmin
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
//remember        fb($params,__FILE__.'-'.__LINE__.":".'params');

    function getJname()
    {
        return 'efront';
    }
    function getTablename() {
        return 'users';
    }
    function setupFromPath($forumPath) {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DS) {
            $myfile = $forumPath . 'libraries'. DS. 'configuration.php';
        } else {
            $myfile = $forumPath . DS .'libraries'. DS .'configuration.php';
        }
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
            return false;
        } else {
            //parse the file line by line to get only the config variables
            $file_handle = fopen($myfile, 'r');
            while (!feof($file_handle)) {
                $line = trim(fgets($file_handle));
                if (strpos($line, 'define') !== false) {
                        if (strpos($line, 'define') == 0){
                            eval($line);
                        }    
                }
            }
            fclose($file_handle);
            // need more defines from eFront
            if (substr($forumPath, -1) == DS) {
                $myfile = $forumPath . 'libraries'. DS. 'globals.php';
            } else {
                $myfile = $forumPath . DS .'libraries'. DS .'globals.php';
            }
            if (($file_handle = @fopen($myfile, 'r')) === false) {
                JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
                return false;
            } else {
                //parse the file line by line to get only the config variables
                $file_handle = fopen($myfile, 'r');
                while (!feof($file_handle)) {
                    $line = trim(fgets($file_handle));
                    if (strpos($line, 'define') !== false) {
                    	if (strpos($line, 'define') == 0){
                            eval($line);
                    	}    
                    }
                }
            }    
            fclose($file_handle);
            //save the parameters into array
            $params = array();
            $params['database_host'] = G_DBHOST;
            $params['database_name'] = G_DBNAME;
            $params['database_user'] = G_DBUSER;
            $params['database_password'] = G_DBPASSWD;
            $params['database_type'] = G_DBTYPE;
            $params['source_path'] = $forumPath;
            $params['usergroup'] = '0'; #make sure we do not assign roles with more capabilities automatically
            $params['md5_key'] = G_MD5KEY;
            $params['uploadpath'] = G_UPLOADPATH;
            return $params;
        }
    }

    /**
     * @return array
     */
    function getUserList() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT login AS username, email from #__users';
        $db->setQuery($query);
        //getting the results
        $userlist = $db->loadObjectList();
        return $userlist;
    }
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());

        // eFront does not have a single user id field in its userdatabase.
        // jFusion needs one, so add it here. This routine runs once
        // when configuring the eFront plugin 
        // Also we need an indication that the module initialisation neds to be performed for this user
        // because we cannot run this from outside eFront (unless we load the whole framework on top of Joomla)
        $tableFields = $db->getTableFields('users',false);
        if (!array_key_exists('id',$tableFields['users']))
        {
            $query = "ALTER TABLE users ADD id int(11) NOT null AUTO_INCREMENT FIRST, ADD UNIQUE (id)";
            $db->Execute($query);
        }
        if (!array_key_exists('need_mod_init',$tableFields['users']))
        {
            $query = "ALTER TABLE users ADD need_mod_init int(11) NOT null DEFAULT 0";
            $db->Execute($query);
        }
        $query = 'SELECT count(*) from #__users';
        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList() {
         return JFusionEfrontHelper::getUsergroupList();
    }
   function getDefaultUsergroup() {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup_id = $params->get('usergroup');
        return JFusionEfrontHelper::groupIdToName($usergroup_id);
    }
    function allowRegistration() {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT value FROM #__configuration WHERE name = 'signup'";
        $db->setQuery($query);
        $signup = $db->loadResult();
        if ($signup == 0){
                    $result = false;
            return $result;
        } else {
            $result = true;
            return $result;
        }
    }

    /**
     * @return bool
     */
    function allowEmptyCookiePath() {
        return true;
    }
    function allowEmptyCookieDomain() {
        return true;
    }
    function debugConfigExtra() {
        // see if we have an api user in Magento
        $jname = $this->getJname();
        $db = JFusionFactory::getDataBase($this->getJname());
        // check if we have valid parameters  for apiuser and api key
        $params = JFusionFactory::getParams($this->getJname());
        $apiuser = $params->get('apiuser');
        $apikey = $params->get('apikey');
        if (!$apiuser || !$apikey) {
                JError::raiseWarning(0, $jname . '-plugin: ' . JText::_('EFRONT_NO_API_DATA'));
        } else {
            //check if the apiuser and apikey are valid
            $query = 'SELECT password FROM #__users WHERE login = ' . $db->Quote($apiuser);
            $db->setQuery($query);
            $api_key = $db->loadResult();
            $md5_key = $params->get('md5_key');
            $params_hash = md5($apikey.$md5_key);
            if ($params_hash != $api_key) {
                JError::raiseWarning(0, $jname . '-plugin: ' . JText::_('EFRONT_WRONG_APIUSER_APIKEY_COMBINATION'));
            }
        }
        // we need to have the curl library installed
        if (!extension_loaded('curl')) {
            JError::raiseWarning(0, $jname . ': ' . JText::_('CURL_NOTINSTALLED'));
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
		return 'JNO';
	}    
}