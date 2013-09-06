<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractadmin.php';

/**
 * JFusion Admin Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_smf extends JFusionAdmin
{
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'smf';
    }

    /**
     * return table name
     *
     * @return string table name
     */
    function getTablename()
    {
        return 'members';
    }

    /**
     * setup plugin from path
     *
     * @param string $forumPath Source path user to find config files
     *
     * @return array
     */
    function setupFromPath($forumPath)
    {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DIRECTORY_SEPARATOR) {
            $myfile = $forumPath . 'Settings.php';
        } else {
            $myfile = $forumPath . DIRECTORY_SEPARATOR . 'Settings.php';
        }
        $params = array();
        //try to open the file
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
            JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': '.$myfile. ' ' . JText::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        } else {
            //parse the file line by line to get only the config variables
            $config = array();
	        foreach ($lines as $line) {
		        if (strpos($line, '$') === 0) {
			        $vars = explode("'", $line);
			        if (isset($vars[1]) && isset($vars[0])) {
				        $name = trim($vars[0], ' $=');
				        $value = trim($vars[1], ' $=');
				        $config[$name] = $value;
			        }
		        }
	        }

            $params['database_host'] = isset($config['db_server']) ? $config['db_server'] : '';
            $params['database_type'] = 'mysql';
            $params['database_name'] = isset($config['db_name']) ? $config['db_name'] : '';
            $params['database_user'] = isset($config['db_user']) ? $config['db_user'] : '';
            $params['database_password'] = isset($config['db_passwd']) ? $config['db_passwd'] : '';
            $params['database_prefix'] = isset($config['db_prefix']) ? $config['db_prefix'] : '';
            $params['source_url'] = isset($config['boardurl']) ? $config['boardurl'] : '';
            $params['cookie_name'] = isset($config['cookiename']) ? $config['cookiename'] : '';
            $params['source_path'] = $forumPath;
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
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('memberName as username, emailAddress as email')
			    ->from('#__members');

		    $db->setQuery($query,$limitstart,$limit);
		    $userlist = $db->loadObjectList();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
			$userlist = array();
	    }
        return $userlist;
    }

    /**
     * returns user count
     *
     * @return int user count
     */
    function getUserCount()
    {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__members');

		    $db->setQuery($query);
		    //getting the results
		    return $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    return 0;
	    }
    }

    /**
     * get default user group list
     *
     * @return array array with object with default user group list
     */
    function getUsergroupList()
    {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('ID_GROUP as id, groupName as name')
			    ->from('#__membergroups')
		        ->where('minPosts = -1');

		    $db->setQuery($query);
		    $usergrouplist = $db->loadObjectList();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $usergrouplist = array();
	    }
	    //append the default usergroup
	    $default_group = new stdClass;
	    $default_group->id = 0;
	    $default_group->name = 'Default User';
	    $usergrouplist[] = $default_group;

        return $usergrouplist;
    }

    /**
     * get default user group
     *
     * @return string object with default user group
     */
    function getDefaultUsergroup()
    {
	    $group = 'Default Usergroup';
	    try {
	        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
	        $usergroup_id = 0;
	        if(!empty($usergroups)) {
	            $usergroup_id = $usergroups[0];
	        }
	        if ($usergroup_id!=0) {
		        //we want to output the usergroup name
		        $db = JFusionFactory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->select('groupName')
			        ->from('#__membergroups')
			        ->where('ID_GROUP = ' . (int)$usergroup_id);

		        $db->setQuery($query);
		        $group = $db->loadResult();
	        }

	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		    $group = '';
		}
	    return $group;
    }

    /**
     * return list of post groups
     *
     * @return object with default user group
     */
    function getUserpostgroupList()
    {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('ID_GROUP as id, groupName as name')
			    ->from('#__membergroups')
			    ->where('minPosts != -1');

		    $db->setQuery($query);
		    return $db->loadObjectList();
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
		    return array();
	    }
    }

    /**
     * function  return if user can register or not
     *
     * @return boolean true can register
     */
    function allowRegistration()
    {
	    $result = false;
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('value')
			    ->from('#__settings')
			    ->where('variable = ' . $db->quote('registration_method'));

		    $db->setQuery($query);
		    $new_registration = $db->loadResult();
		    if ($new_registration != 3) {
			    $result = true;
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
	    return $result;
    }

    /**
     * regenerate redirect code
     *
     * @param string $url
     * @param int $itemid
     *
     * @return string output php redirect code
     */
    function generateRedirectCode($url, $itemid)
    {
        //create the new redirection code
        /*
        $pattern = \'#action=(login|admin|profile|featuresettings|news|packages|detailedversion|serversettings|theme|manageboards|postsettings|managecalendar|managesearch|smileys|manageattachments|viewmembers|membergroups|permissions|regcenter|ban|maintain|reports|viewErrorLog|optimizetables|detailedversion|repairboards|boardrecount|convertutf8|helpadmin|packageget)#\';
        */
        $redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \'' . $url . '\';
$joomla_itemid = ' . $itemid . ';
    ';
        $redirect_code.= '
if (!defined(\'_JEXEC\') && strpos($_SERVER[\'QUERY_STRING\'], \'dlattach\') === false && strpos($_SERVER[\'QUERY_STRING\'], \'verificationcode\') === false)';
        $redirect_code.= '
{
    $pattern = \'#action=(login|logout)#\';
    if ( !preg_match( $pattern , $_SERVER[\'QUERY_STRING\'] )) {
        $file = $_SERVER["SCRIPT_NAME"];
        $break = explode(\'/\', $file);
        $pfile = $break[count($break) - 1];
        $query = str_replace(\';\', \'&\', $_SERVER[\'QUERY_STRING\']);
        $jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $query;
        header(\'Location: \' . $jfusion_url);
        exit;
    }
}
//JFUSION REDIRECT END';
        return $redirect_code;
    }

    /**
     * Disable redirect mod
     *
     * @return void
     */
    function enableRedirectMod()
    {
        $joomla_params = JFusionFactory::getParams('joomla_int');
        $joomla_url = $joomla_params->get('source_url');
        $joomla_itemid = $this->params->get('redirect_itemid');

        //check to see if all vars are set
        if (empty($joomla_url)) {
            JFusionFunction::raiseWarning(JText::_('MISSING') . ' Joomla URL', $this->getJname());
        } else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
            JFusionFunction::raiseWarning(JText::_('MISSING') . ' ItemID', $this->getJname());
        } else if (!$this->isValidItemID($joomla_itemid)) {
            JFusionFunction::raiseWarning(JText::_('MISSING') . ' ItemID '. JText::_('MUST BE'). ' ' . $this->getJname(), $this->getJname());
        } else {
            $error = $this->disableRedirectMod();
            $reason = '';
            $mod_file = $this->getModFile('index.php', $error, $reason);
            if ($error == 0) {
                //get the joomla path from the file
                jimport('joomla.filesystem.file');
                $file_data = file_get_contents($mod_file);
                $redirect_code = $this->generateRedirectCode($joomla_url,$joomla_itemid);

                $search = '/\<\?php/si';
                $replace = '<?php' . $redirect_code;

                $file_data = preg_replace($search, $replace, $file_data);
                JFile::write($mod_file, $file_data);
            }
        }
    }

    /**
     * Disable redirect mod
     *
     * @return string
     */
    function disableRedirectMod()
    {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('index.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = file_get_contents($mod_file);
            $search = '/(\r?\n)\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/si';
            preg_match_all($search, $file_data, $matches);
            //remove any old code
            if (!empty($matches[1][0])) {
                $file_data = preg_replace($search, '', $file_data);
                if (!JFile::write($mod_file, $file_data)) {
                    $error = 1;
                }
            }
        }
        return $error;
    }

    /**
     * Called by Framework user for displaying and configuring usergroups
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string $node         node
     * @param string $control_name name of controller
     *
     * @return string html
     */
    function showRedirectMod($name, $value, $node, $control_name)
    {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('index.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = file_get_contents($mod_file);
            preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms', $file_data, $matches);
            //compare it with our joomla path
            if (empty($matches[1][0])) {
                $error = 1;
                $reason = JText::_('MOD_NOT_ENABLED');
            }
        }

        //add the javascript to enable buttons
        if ($error == 0) {
            //return success
            $text = JText::_('REDIRECTION_MOD') . ' ' . JText::_('ENABLED');
            $disable = JText::_('MOD_DISABLE');
            $update = JText::_('MOD_UPDATE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('disableRedirectMod')">{$disable}</a>
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('enableRedirectMod')">{$update}</a>
HTML;
            return $output;
        } else {
            $text = JText::_('REDIRECTION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('enableRedirectMod')">{$enable}</a>
HTML;
            return $output;
        }
    }

    /**
     * uninstall function is to disable verious mods
     *
     * @return array
     */
    function uninstall()
    {
    	$error = $this->disableRedirectMod();
    	if (!empty($error)) {
    	   $reason= JText::_('REDIRECT_MOD_UNINSTALL_FAILED');
    	   return array(false, $reason);
    	}

    	return array(true, '');
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

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JNO';
	}
}
