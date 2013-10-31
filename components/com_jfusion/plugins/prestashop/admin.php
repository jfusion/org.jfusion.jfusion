<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage PrestaShop
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */


// no direct access
defined('_JEXEC') or die('Restricted access');


/**
 * JFusion Admin Class for PrestaShop
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage PrestaShop
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */


class JFusionAdmin_prestashop extends JFusionAdmin
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'prestashop';
    }

    /**
     * @return string
     */
    function getTablename() {
        return 'customer';
    }

    /**
     * @param $storePath
     * @return array
     */
    function loadSetup($storePath) {
        //check for trailing slash and generate file path
        if (substr($storePath, -1) == DS) {
            $myfile = $storePath . 'config/settings.inc.php';
        } else {
            $myfile = $storePath . DS . 'config/settings.inc.php';
        }
        $config = array();
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
        } else {
            //parse the file line by line to get only the config variables
            $file_handle = fopen($myfile, 'r');
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                if (strpos($line, 'define(') === 0 && count($config) <= 8) {
                    /* extract the name and value, it was coded to avoid the use of eval() function */
                    // name
                    $vars_strt[0] = strpos($line, "'");
                    $vars_end[0] = strpos($line, "',");
                    $name = trim(substr($line, $vars_strt[0], $vars_end[0] - $vars_strt[0]), "'");     
                    // value
                    $vars_strt[1] = strpos($line, " '");
                    $vars_strt[1]++;
                    $vars_end[1] = strpos($line, "')");
                    $value = trim(substr($line, $vars_strt[1], $vars_end[1] - $vars_strt[1]), "'");
                    if($name == '_DB_TYPE_')
                    {
                     $value = strtolower($value);
                    }    
                    $config[$name] = $value;
                }
            }
	        fclose($file_handle);
	    }
        return $config;
	}

    /**
     * @param string $storePath
     * @return array
     */
    function setupFromPath($storePath) {
	    $config = JFusionAdmin_prestashop::loadSetup($storePath);
        $params = array();
        if (!empty($config)) {
            //save the parameters into array
            $params['database_host'] = $config['_DB_SERVER_'];
            $params['database_name'] = $config['_DB_NAME_'];
            $params['database_user'] = $config['_DB_USER_'];
            $params['database_password'] = $config['_DB_PASSWD_'];
            $params['database_prefix'] = $config['_DB_PREFIX_'];
            $params['database_type'] = $config['_DB_TYPE_'];
            $params['source_path'] = $storePath;
			$params['usergroup'] = 1;
			//return the parameters so it can be saved permanently
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
        $query = 'SELECT email as email, id_customer as userid from #__customer WHERE email NOT LIKE \'\' and email IS NOT null';
        $db->setQuery($query,$limitstart,$limit);
        //getting the results
        $userlist = $db->loadObjectList();
        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__customer WHERE email NOT LIKE \'\' and email IS NOT null';
        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList() {
	    /**
	     * @ignore
	     * @var $helper JFusionHelper_prestashop
	     */
	    $helper = JFusionFactory::getHelper($this->getJname());

        //get the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());

        //prestashop uses two group categories which are employees and customers, each have there own groups to access either the front or back end
        /*
          Customers only for this plugin
        */
        $query = 'SELECT id_group as id, name as name from #__group_lang WHERE id_lang = ' . $db->Quote($helper->getDefaultLanguage());
        $db->setQuery($query);
        //getting the results
		$result = $db->loadObjectList();
        return $result;
    }

    /**
     * @return string
     */
    function getDefaultUsergroup() {
	    $params = JFusionFactory::getParams($this->getJname());
	    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), null);
	    $usergroup = '';
	    if(!empty($usergroups)) {
		    /**
		     * @ignore
		     * @var $helper JFusionHelper_prestashop
		     */
		    $helper = JFusionFactory::getHelper($this->getJname());
		    $usergroup = $helper->getGroupName($usergroups[0]);
	    }
	    return $usergroup;
    }

    /**
     * @return bool
     */
    function allowRegistration() {
        //you cannot disable registration
            $result = true;
            return $result;
    }

    /**
     * @return bool
     */
    function allowEmptyCookiePath(){
		return true;
	}

    /**
     * @return bool
     */
    function allowEmptyCookieDomain(){
		return true;
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
