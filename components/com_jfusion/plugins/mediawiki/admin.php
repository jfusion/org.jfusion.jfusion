<?php

/**
* @package JFusion_mediawiki
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Admin Class for mediawiki 1.1.x
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * @package JFusion_mediawiki
 */

class JFusionAdmin_mediawiki extends JFusionAdmin {

    /**
     * @return string
     */
    function getJname()
    {
        return 'mediawiki';
    }

    /**
     * @return string
     */
    function getTablename()
    {
        return 'user';
    }

    /**
     * @param string $source_path
     *
     * @return array
     */
    function setupFromPath($source_path)
    {
        //check for trailing slash and generate file path
        if (substr($source_path, -1) == DS) {
            //remove it so that we can make it compatible with mediawiki MW_INSTALL_PATH
            $source_path = substr($source_path, 0, -1);
        }
        $myfile = $source_path . DS. 'LocalSettings.php';
        $params = array();
         //try to open the file
        if ( !file_exists($myfile) ) {
            JError::raiseWarning(500,JText::_('WIZARD_FAILURE'). ': '.$myfile.' ' . JText::_('WIZARD_MANUAL'));
        } else {
            $wgDBserver = $wgDBtype = $wgDBname = $wgDBuser = $wgDBpassword = $wgDBprefix = '';
            /**
             * @ignore
             * @var $helper JFusionHelper_mediawiki
             */
            $helper = JFusionFactory::getHelper($this->getJname());
            $paths = $helper->includeFramework($source_path);
            $IP = $source_path;
            foreach($paths as $path) {
                include($path);
            }

            $params['database_host'] = $wgDBserver;
            $params['database_type'] = $wgDBtype;
            $params['database_name'] = $wgDBname;
            $params['database_user'] = $wgDBuser;
            $params['database_password'] = $wgDBpassword;
            $params['database_prefix'] = $wgDBprefix;
            $params['source_path'] = $source_path;

            if (!empty($wgCookiePrefix)) {
                $params['cookie_name'] = $wgCookiePrefix;
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
        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT user_name as username, user_email as email from #__user';
        $db->setQuery($query,$limitstart,$limit);
        $userlist = $db->loadObjectList();

        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__user';
        $db->setQuery($query );

        //getting the results
        return $db->loadResult();
    }

    /**
     * @return array
     */
    function getUsergroupList()
    {
        /**
         * @ignore
         * @var $helper JFusionHelper_mediawiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $wgGroupPermissions = $helper->getConfig('wgGroupPermissions');

        $usergrouplist = array();
        foreach($wgGroupPermissions as $key => $value) {
        	if ( $key != '*' ) {
 				$group = new stdClass;
        		$group->id = $key;
        		$group->name = $key;
        		$usergrouplist[] = $group;
        	}
        }
        return $usergrouplist;
    }

    /**
     * @return string
     */
    function getDefaultUsergroup()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
        $usergroup_id = null;
        if(!empty($usergroups)) {
            $usergroup_id = $usergroups[0];
        }
        return $usergroup_id;
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
        /**
         * @ignore
         * @var $helper JFusionHelper_mediawiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
		$wgGroupPermissions = $helper->getConfig('wgGroupPermissions');
        if (is_array($wgGroupPermissions) && $wgGroupPermissions['*']['createaccount'] == true) {
            return true;
        } else {
            return false;
        }
    }
   
	/*
	 * do plugin support multi usergroups
	 * return UNKNOWN for unknown
	 * return JNO for NO
	 * return JYES for YES
	 * return ... ??
	 */
    /**
     * @return string
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

