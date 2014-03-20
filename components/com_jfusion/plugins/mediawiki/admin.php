<?php namespace JFusion\Plugins\mediawiki;

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

class Admin extends \JFusion\Plugin\Plugin_Admin
{
	/**
	 * @var $helper JFusionHelper_mediawiki
	 */
	var $helper;

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
     * @param string $softwarePath
     *
     * @return array
     */
    function setupFromPath($softwarePath)
    {
        $myfile = $softwarePath . 'LocalSettings.php';
        $params = array();
         //try to open the file
        if ( !file_exists($myfile) ) {
            \JFusion\Framework::raiseWarning(Text::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        } else {
            $wgDBserver = $wgDBtype = $wgDBname = $wgDBuser = $wgDBpassword = $wgDBprefix = '';

            $paths = $this->helper->includeFramework($softwarePath);
            $IP = $softwarePath;
            foreach($paths as $path) {
                include($path);
            }

            $params['database_host'] = $wgDBserver;
            $params['database_type'] = $wgDBtype;
            $params['database_name'] = $wgDBname;
            $params['database_user'] = $wgDBuser;
            $params['database_password'] = $wgDBpassword;
            $params['database_prefix'] = $wgDBprefix;
            $params['source_path'] = $softwarePath;

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
	    try {
		    // initialise some objects
		    $db = \JFusion\Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('user_name as username, user_email as email')
			    ->from('#__user');

		    $db->setQuery($query, $limitstart, $limit);
		    $userlist = $db->loadObjectList();

		    return $userlist;
	    } catch (Exception $e) {
		    \JFusion\Framework::raiseError($e, $this->getJname());
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
		    $db = \JFusion\Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__user');

		    $db->setQuery($query);

		    //getting the results
		    return $db->loadResult();
	    } catch (Exception $e) {
		    \JFusion\Framework::raiseError($e, $this->getJname());
		    return 0;
	    }
    }

    /**
     * @return array
     */
    function getUsergroupList()
    {
        $wgGroupPermissions = $this->helper->getConfig('wgGroupPermissions');

        $usergrouplist = array();
        foreach($wgGroupPermissions as $key => $value) {
        	if ($key != '*') {
 				$group = new stdClass;
        		$group->id = $key;
        		$group->name = $key;
        		$usergrouplist[] = $group;
        	}
        }
        return $usergrouplist;
    }

    /**
     * @return string|array
     */
    function getDefaultUsergroup()
    {
	    $usergroups = \JFusion\Framework::getUserGroups($this->getJname(), true);
	    if ($usergroups !== null) {
		    return $usergroups;
	    } else {
		    return '';
	    }
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
		$wgGroupPermissions = $this->helper->getConfig('wgGroupPermissions');
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

