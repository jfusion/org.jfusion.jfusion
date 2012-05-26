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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractadmin.php';

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
     * @return mixed return false on failor and array if sucess
     */
    function setupFromPath($forumPath)
    {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DS) {
            $myfile = $forumPath . 'Settings.php';
        } else {
            $myfile = $forumPath . DS . 'Settings.php';
        }
        //try to open the file
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
            //get the default parameters object
            $params = JFusionFactory::getParams($this->getJname());
            return $params;
        } else {
            //parse the file line by line to get only the config variables
            $file_handle = fopen($myfile, 'r');
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                if (strpos($line, '$') === 0) {
                    $vars = explode("'", $line);
                    if (isset($vars[1]) && isset($vars[0])) {
                        $name = trim($vars[0], ' $=');
                        $value = trim($vars[1], ' $=');
                        $config[$name] = $value;
                    }
                }
            }
            fclose($file_handle);
            //Save the parameters into the standard JFusion params format
            $params = array();
            $params['database_host'] = $config['db_server'];
            $params['database_type'] = 'mysql';
            $params['database_name'] = $config['db_name'];
            $params['database_user'] = $config['db_user'];
            $params['database_password'] = $config['db_passwd'];
            $params['database_prefix'] = $config['db_prefix'];
            $params['source_url'] = $config['boardurl'];
            $params['cookie_name'] = $config['cookiename'];
            $params['source_path'] = $forumPath;
            return $params;
        }
    }

    /**
     * Get a list of users
     *
     * @return object with list of users
     */
    function getUserList()
    {
        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT memberName as username, emailAddress as email from #__members';
        $db->setQuery($query);
        $userlist = $db->loadObjectList();
        return $userlist;
    }

    /**
     * returns user count
     *
     * @return int user count
     */
    function getUserCount()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__members';
        $db->setQuery($query);
        //getting the results
        return $db->loadResult();
    }

    /**
     * get default user group list
     *
     * @return object with default user group list
     */
    function getUsergroupList()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT ID_GROUP as id, groupName as name FROM #__membergroups WHERE minPosts = -1';
        $db->setQuery($query);
        $usergrouplist = $db->loadObjectList();
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
     * @return object with default user group
     */
    function getDefaultUsergroup()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup_id = $params->get('usergroup');
        if ($usergroup_id == 0) {
            return "Default Usergroup";
        }
        //we want to output the usergroup name
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT groupName FROM #__membergroups WHERE ID_GROUP = ' . (int)$usergroup_id;
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * return list of post groups
     *
     * @return object with default user group
     */
    function getUserpostgroupList()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT ID_GROUP as id, groupName as name FROM #__membergroups WHERE minPosts != -1';
        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * function  return if user can register or not
     *
     * @return boolean true can register
     */
    function allowRegistration()
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT value FROM #__settings WHERE variable ='registration_method';";
        $db->setQuery($query);
        $new_registration = $db->loadResult();
        if ($new_registration == 3) {
            $result = false;
            return $result;
        } else {
            $result = true;
            return $result;
        }
    }

    /**
     * renerate redirect code
     *
     * @return string output php redirect code
     */
    function generateRedirectCode()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $joomla_params = JFusionFactory::getParams('joomla_int');
        $joomla_url = $joomla_params->get('source_url');
        $joomla_itemid = $params->get('redirect_itemid');
        //create the new redirection code
        /*
        $pattern = \'#action=(login|admin|profile|featuresettings|news|packages|detailedversion|serversettings|theme|manageboards|postsettings|managecalendar|managesearch|smileys|manageattachments|viewmembers|membergroups|permissions|regcenter|ban|maintain|reports|viewErrorLog|optimizetables|detailedversion|repairboards|boardrecount|convertutf8|helpadmin|packageget)#\';
        */
        $redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \'' . $joomla_url . '\';
$joomla_itemid = ' . $joomla_itemid . ';
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
        $jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $_SERVER[\'QUERY_STRING\'];
        header(\'Location: \' . $jfusion_url);
        exit;
    }
}
//JFUSION REDIRECT END
';
        return $redirect_code;
    }

    /**
     * Disable redirect mod
     *
     * @return void
     */
    function enableRedirectMod()
    {
        $error = 0;
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('index.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = JFile::read($mod_file);
            preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms', $file_data, $matches);
            //remove any old code
            if (!empty($matches[1][0])) {
                $search = '/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms';
                $file_data = preg_replace($search, '', $file_data);
            }
            $redirect_code = $this->generateRedirectCode();
            $search = '/\<\?php/si';
            $replace = '<?php' . $redirect_code;
            $file_data = preg_replace($search, $replace, $file_data);
            JFile::write($mod_file, $file_data);
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
            $file_data = JFile::read($mod_file);
            $search = '/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/si';
            $file_data = preg_replace($search, '', $file_data);
            if (!JFile::write($mod_file, $file_data)) {
                $error = 1;
            }
        }
        return $error;
    }

    /**
     * Output javascript
     *
     * @return void
     */
    function outputJavascript(){
        static $jsLoaded;
        if (empty($jsLoaded)) {
            $jsLoaded = 1;
            $js = <<<JS
            function auth_mod(action) {
                var form = document.adminForm;
                form.customcommand.value = action;
                form.action.value = 'apply';
                submitform('saveconfig');
            }
JS;
            $document = JFactory::getDocument();
            $document->addScriptDeclaration($js);
        }
    }

    /**
     * Called by Framework user for displaying and configuring usergroups
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string $node         node
     * @param string $control_name name of controler
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
            $file_data = JFile::read($mod_file);
            preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms', $file_data, $matches);
            //compare it with our joomla path
            if (empty($matches[1][0])) {
                $error = 1;
                $reason = JText::_('MOD_NOT_ENABLED');
            }
        }
        //add the javascript to enable buttons
        $this->outputJavascript();
        if ($error == 0) {
            //return success
            $output = '<img src="components/com_jfusion/images/check_good.png" height="20px" width="20px">' . JText::_('REDIRECTION_MOD') . ' ' . JText::_('ENABLED');
            $output.= ' <a href="javascript:void(0);" onclick="return auth_mod(\'disableRedirectMod\')">' . JText::_('MOD_DISABLE') . '</a>';
            $output.= ' <a href="javascript:void(0);" onclick="return auth_mod(\'enableRedirectMod\')">' . JText::_('MOD_UPDATE') . '</a>';
            return $output;
        } else {
            $output = '<img src="components/com_jfusion/images/check_bad.png" height="20px" width="20px">' . JText::_('REDIRECTION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $output.= ' <a href="javascript:void(0);" onclick="return auth_mod(\'enableRedirectMod\')">' . JText::_('MOD_ENABLE') . '</a>';
            return $output;
        }
    }

    /*
     * uninstall function is to disable verious mods
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
    
	/*
	 * do plugin support multi usergroups
	 */
	function isMultiGroup()
	{
		return false;
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
