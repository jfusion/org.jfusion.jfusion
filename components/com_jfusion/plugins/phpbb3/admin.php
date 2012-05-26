<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Admin Class for phpBB3
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_phpbb3 extends JFusionAdmin
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'phpbb3';
    }
    function getTablename() {
        return 'users';
    }
    function setupFromPath($forumPath) {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DS) {
            $myfile = $forumPath . 'config.php';
        } else {
            $myfile = $forumPath . DS . 'config.php';
        }
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
            $result = false;
            return $result;
        } else {
            //parse the file line by line to get only the config variables
            $file_handle = fopen($myfile, 'r');
            $config = array();
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                if (strpos($line, '$') === 0) {
                    //extract the name and value, it was coded to avoid the use of eval() function
                    $vars = explode("'", $line);
                    $name = trim($vars[0], ' $=');
                    $value = trim($vars[1], ' $=');
                    $config[$name] = $value;
                }
            }
            fclose($file_handle);
            //save the parameters into array
            $params = array();
            $params['database_host'] = $config['dbhost'];
            $params['database_name'] = $config['dbname'];
            $params['database_user'] = $config['dbuser'];
            $params['database_password'] = $config['dbpasswd'];
            $params['database_prefix'] = $config['table_prefix'];
            $params['database_type'] = $config['dbms'];
            //create a connection to the database
            $options = array('driver' => $config['dbms'], 'host' => $config['dbhost'], 'user' => $config['dbuser'], 'password' => $config['dbpasswd'], 'database' => $config['dbname'], 'prefix' => $config['table_prefix']);
            //Get configuration settings stored in the database
            $vdb = & JDatabase::getInstance($options);
            $query = "SELECT config_name, config_value FROM #__config WHERE config_name IN ('script_path', 'cookie_path', 'server_name', 'cookie_domain', 'cookie_name', 'allow_autologin');";
            if (JError::isError($vdb) || !$vdb) {
                JError::raiseWarning(0, JText::_('NO_DATABASE'));
                $result = false;
                return $result;
            } else {
                $vdb->setQuery($query);
                $rows = $vdb->loadObjectList();
                foreach ($rows as $row) {
                    $config[$row->config_name] = $row->config_value;
                }
                //store the new found parameters
                $params['cookie_path'] = $config['cookie_path'];
                $params['cookie_domain'] = $config['cookie_domain'];
                $params['cookie_prefix'] = $config['cookie_name'];
                $params['allow_autologin'] = $config['allow_autologin'];
                $params['source_path'] = $forumPath;
            }
            //check for trailing slash
            if (substr($config['server_name'], -1) == '/' && substr($config['script_path'], 0, 1) == '/') {
                //too many slashes, we need to remove one
                $params['source_url'] = $config['server_name'] . substr($config['script_path'], 1);
            } else if (substr($config['server_name'], -1) == '/' || substr($config['script_path'], 0, 1) == '/') {
                //the correct number of slashes
                $params['source_url'] = $config['server_name'] . $config['script_path'];
            } else {
                //no slashes found, we need to add one
                $params['source_url'] = $config['server_name'] . '/' . $config['script_path'];
            }
            //return the parameters so it can be saved permanently
            return $params;
        }
    }
    function getUserList($limitstart = null, $limit = null) {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT username_clean as username, user_email as email, user_id as userid from #__users WHERE user_email NOT LIKE \'\' and user_email IS NOT null';
        if (!empty($limit)) {
            $db->setQuery($query, $limitstart, $limit);
        } else {
            $db->setQuery($query);
        }
        //getting the results
        $userlist = $db->loadObjectList();
        return $userlist;
    }
    function getUserCount() {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__users WHERE user_email NOT LIKE \'\' and user_email IS NOT null ';
        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }
    function getUsergroupList() {
        //get the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT group_id as id, group_name as name from #__groups;';
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
    }
    function getDefaultUsergroup() {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup_id = $params->get('usergroup');
        //we want to output the usergroup name
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT group_name from #__groups WHERE group_id = ' . (int)$usergroup_id;
        $db->setQuery($query);
        return $db->loadResult();
    }
    function allowRegistration() {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT config_value FROM #__config WHERE config_name = 'require_activation'";
        $db->setQuery($query);
        //getting the results
        $new_registration = $db->loadResult();
        if ($new_registration == 3) {
            $result = false;
            return $result;
        } else {
            $result = true;
            return $result;
        }
    }
    function generateRedirectCode() {
        $params = JFusionFactory::getParams($this->getJname());
        $joomla_params = JFusionFactory::getParams('joomla_int');
        $cookie_name = $params->get('cookie_prefix') . '_u';
        $joomla_url = $joomla_params->get('source_url');
        $joomla_itemid = $params->get('redirect_itemid');
        //check to see if all vars are set
        if (empty($joomla_url)) {
            JError::raiseWarning(0, JText::_('MISSING') . ' Joomla URL');
            return '';
        }
        if (empty($joomla_itemid)) {
            JError::raiseWarning(0, JText::_('MISSING') . ' ItemID');
            return '';
        }
        //create the new redirection code
        $redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
if (!empty($_COOKIE[\'' . $cookie_name . '\']))
{
    $current_userid = $_COOKIE[\'' . $cookie_name . '\'];
} else {
    $current_userid = \'\';
}
$joomla_url = \'' . $joomla_url . '\';
$joomla_itemid = ' . $joomla_itemid . ';
$file = $_SERVER[\'SCRIPT_NAME\'];
$break = Explode(\'/\', $file);
$pfile = $break[count($break) - 1];

$jfile = \'\';
if (isset($_GET[\'jfile\'])) {
     $jfile = $_GET[\'jfile\'];
}
    ';
        $allow_mods = $params->get('mod_ids');
        if (!empty($allow_mods)) {
            //get a userlist of mod ids
            $db = & JFusionFactory::getDatabase($this->getJname());
            $query = "SELECT b.user_id, a.group_name FROM #__groups as a INNER JOIN #__user_group as b ON a.group_id = b.group_id WHERE a.group_name = 'GLOBAL_MODERATORS' or a.group_name = 'ADMINISTRATORS'";
            $db->setQuery($query);
            $mod_list = $db->loadObjectList();
            $mod_array = array();
            foreach ($mod_list as $mod) {
                if (!isset($mod_array[$mod->user_id])) {
                    $mod_array[$mod->user_id] = $mod->user_id;
                }
            }
            $mod_ids = implode(",", $mod_array);
            $redirect_code.= '
$mod_ids = array(' . $mod_ids . ');
if (!defined(\'_JEXEC\') && !defined(\'ADMIN_START\') && !defined(\'IN_MOBIQUO\') && $pfile != \'file.php\' && $jfile != \'file.php\' && $pfile != \'feed.php\' && $jfile != \'feed.php\' && !in_array($current_userid, $mod_ids))';
        } else {
            $redirect_code.= '
if (!defined(\'_JEXEC\') && !defined(\'ADMIN_START\') && !defined(\'IN_MOBIQUO\') && $pfile != \'file.php\' && $jfile != \'file.php\' && $pfile != \'feed.php\' && $jfile != \'feed.php\')';
        }
        $redirect_code.= '
{
    $jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $_SERVER[\'QUERY_STRING\'];
    header(\'Location: \' . $jfusion_url);
}
//JFUSION REDIRECT END
';
        return $redirect_code;
    }
    function enableRedirectMod() {
        $error = 0;
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('common.php', $error, $reason);
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
            if (empty($redirect_code)) {
                return;
            }
            $search = '/\<\?php/si';
            $replace = '<?php' . $redirect_code;
            $file_data = preg_replace($search, $replace, $file_data);
            JFile::write($mod_file, $file_data);
        }
    }
    function disableRedirectMod() {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('common.php', $error, $reason);
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
    function outputJavascript() {
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
    function showRedirectMod($name, $value, $node, $control_name) {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('common.php', $error, $reason);
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
    function show_auth_mod($name, $value, $node, $control_name) {
        //do a database check to avoid fatal error with incorrect database settings
        $db = JFusionFactory::getDatabase($this->getJname());
        if (!method_exists($db, 'setQuery')) {
            return JText::_('NO_DATABASE');
        }
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('includes' . DS . 'auth' . DS . 'auth_jfusion.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = JFile::read($mod_file);
            if(preg_match_all('/define\(\'JPATH_BASE\'\,(.*)\)/', $file_data, $matches)) {
       			//compare it with our joomla path
	            if ($matches[1][0] != '\'' . JPATH_SITE . '\'') {
	                $error = 1;
	                $reason = JText::_('PATH') . ' ' . JText::_('INVALID');
	            }
            }
        }
        if ($error == 0) {
            //check to see if the mod is enabled
            $query = 'SELECT config_value FROM #__config WHERE config_name = \'auth_method\'';
            $db->setQuery($query);
            $auth_method = $db->loadResult();
            if ($auth_method != 'jfusion') {
                $error = 1;
                $reason = JText::_('MOD_NOT_ENABLED');
            }
        }
        //add the javascript to enable buttons
        $this->outputJavascript();
        if ($error == 0) {
            //return success
            $output = '<img src="components/com_jfusion/images/check_good.png" height="20px" width="20px">' . JText::_('AUTHENTICATION_MOD') . ' ' . JText::_('ENABLED');
            $output.= ' <a href="javascript:void(0);" onclick="return auth_mod(\'disable_auth_mod\')">' . JText::_('MOD_DISABLE') . '</a>';
            return $output;
        } else {
            $output = '<img src="components/com_jfusion/images/check_bad.png" height="20px" width="20px">' . JText::_('AUTHENTICATION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $output.= ' <a href="javascript:void(0);" onclick="return auth_mod(\'enable_auth_mod\')">' . JText::_('MOD_ENABLE') . '</a>';
            return $output;
        }
    }
    function enable_auth_mod() {
        $error = 0;
        $reason = '';
        $auth_file = $this->getModFile('includes' . DS . 'auth' . DS . 'auth_jfusion.php', $error, $reason);
        //see if the auth mod file exists
        if (!file_exists($auth_file)) {
            jimport('joomla.filesystem.file');
            $copy_file = JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'auth_jfusion.php';
            JFile::copy($copy_file, $auth_file);
        }
        //get the joomla path from the file
        jimport('joomla.filesystem.file');
        $file_data = JFile::read($auth_file);
        preg_match_all('/define\(\'JPATH_BASE\'\,(.*)\)/', $file_data, $matches);
        //compare it with our joomla path
        if ($matches[1][0] != '\'' . JPATH_SITE . '\'') {
            $file_data = preg_replace('/define\(\'JPATH_BASE\'\,(.*)\)/', 'define(\'JPATH_BASE\',\'' . JPATH_SITE . '\')', $file_data);
            $file_data = preg_replace('/\$JFusionActivePlugin \=(.*?)\;/', '$JFusionActivePlugin =\'' . $this->getJname() . '\';', $file_data);
            JFile::write($auth_file, $file_data);
        }

        //only update the database if the file now exists
        if (file_exists($auth_file)) {
            //check to see if the mod is enabled
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'SELECT config_value FROM #__config WHERE config_name = \'auth_method\'';
            $db->setQuery($query);
            $auth_method = $db->loadResult();
            if ($auth_method != 'jfusion') {
                $query = 'UPDATE #__config SET config_value = \'jfusion\' WHERE config_name = \'auth_method\'';
                $db->setQuery($query);
                if (!$db->query()) {
                    //there was an error saving the parameters
                    JError::raiseWarning(0, $db->stderr());
                }
            }
        } else {
            //safety catch to make sure we use phpBB default to prevent lockout from phpBB
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'UPDATE #__config SET config_value = \'db\' WHERE config_name = \'auth_method\'';
            $db->setQuery($query);
            if (!$db->query()) {
                //there was an error saving the parameters
                JError::raiseWarning(0, $db->stderr());
            }
        }
        //clear the config cache so that phpBB recognizes the change
        $this->clearConfigCache();
    }
    function disable_auth_mod() {
        $return = true;
        //check to see if the mod is enabled
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__config SET config_value = \'db\' WHERE config_name = \'auth_method\'';
        $db->setQuery($query);
        if (!$db->query()) {
            //there was an error saving the parameters
            JError::raiseWarning(0, $db->stderr());
            $return = false;
        }
        //remove the file as well to allow for updates of the auth mod content
        $params = JFusionFactory::getParams($this->getJname());
        $path = $params->get('source_path');
        if (substr($path, -1) == DS) {
            $auth_file = $path . 'includes' . DS . 'auth' . DS . 'auth_jfusion.php';
        } else {
            $auth_file = $path . DS . 'includes' . DS . 'auth' . DS . 'auth_jfusion.php';
        }
        if (file_exists($auth_file)) {
            jimport('joomla.filesystem.file');
            if (!JFile::delete($auth_file)) {
                $return = false;
            }
        }

        //clear the config cache so that phpBB recognizes the change
        $cleared = $this->clearConfigCache();
        if (!$cleared) {
            $return = false;
        }

        return $return;
    }
    function show_quick_mod($name, $value, $node, $control_name) {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('mcp.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = JFile::read($mod_file);
            preg_match_all('/global \$action/', $file_data, $matches);
            //compare it with our joomla path
            if (!isset($matches[0][0])) {
                $error = 1;
                $reason = JText::_('MOD') . ' ' . JText::_('DISABLED');
            }
        }
        //add the javascript to enable buttons
        $this->outputJavascript();
        if ($error == 0) {
            //return success
            $output = '<img src="components/com_jfusion/images/check_good.png" height="20px" width="20px">' . JText::_('QUICKTOOLS') . ' ' . JText::_('ENABLED');
            $output.= ' <a href="javascript:void(0);" onclick="return auth_mod(\'disable_quick_mod\')">' . JText::_('MOD_DISABLE') . '</a>';
            return $output;
        } else {
            $output = '<img src="components/com_jfusion/images/check_bad.png" height="20px" width="20px">' . JText::_('QUICKTOOLS') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $output.= ' <a href="javascript:void(0);" onclick="return auth_mod(\'enable_quick_mod\')">' . JText::_('MOD_ENABLE') . '</a>';
            return $output;
        }
    }
    function enable_quick_mod() {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('mcp.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = JFile::read($mod_file);
            $search = '/\$action \= request_var/si';
            $replace = 'global $action; $action = request_var';
            $file_data = preg_replace($search, $replace, $file_data);
            JFile::write($mod_file, $file_data);
        }
    }
    function disable_quick_mod() {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('mcp.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = JFile::read($mod_file);
            $search = '/global \$action\;/si';
            $file_data = preg_replace($search, '', $file_data);
            if (!JFile::write($mod_file, $file_data)) {
                $error = 1;
            }
        }
        return $error;
    }
    function clearConfigCache() {
        $params = & JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        $cache = $source_path . DS . 'cache' . DS . 'data_global.php';
        if (file_exists($cache)) {
            jimport('joomla.filesystem.file');
            return JFile::delete($cache);
        }
        return true;
    }

    function uninstall() {
        $return = true;
        $reasons = array();

        $error = $this->disable_auth_mod();
        if (!$error) {
            $reasons[] = JText::_('AUTH_MOD_UNINSTALL_FAILED');
            $return = false;
        }

        //doesn't really matter if the quick mod is not disabled so don't return an error
        $error = $this->disable_quick_mod();

        $error = $this->disableRedirectMod();
        if (!empty($error)) {
            $reasons[] = JText::_('REDIRECT_MOD_UNINSTALL_FAILED');
            $return = false;
        }
        return array($return, $reasons);
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
		return 'DEPENDS';
	}    
}
