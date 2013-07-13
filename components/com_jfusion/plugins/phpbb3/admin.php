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

    /**
     * @return string
     */
    function getTablename() {
        return 'users';
    }

    /**
     * @param string $forumPath
     * @return array
     */
    function setupFromPath($forumPath) {
        //check for trailing slash and generate file path
        if (substr($forumPath, -1) == DIRECTORY_SEPARATOR) {
            $myfile = $forumPath . 'config.php';
        } else {
            $myfile = $forumPath . DIRECTORY_SEPARATOR . 'config.php';
        }
        $params = array();
        if (($file_handle = @fopen($myfile, 'r')) === false) {
            JFusionFunction::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
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
            $params['database_host'] = isset($config['dbhost']) ? $config['dbhost'] : '';
            $params['database_name'] = isset($config['dbname']) ? $config['dbname'] : '';
            $params['database_user'] = isset($config['dbuser']) ? $config['dbuser'] : '';
            $params['database_password'] = isset($config['dbpasswd']) ? $config['dbpasswd'] : '';
            $params['database_prefix'] = isset($config['table_prefix']) ? $config['table_prefix'] : '';
            $params['database_type'] = isset($config['dbms']) ? $config['dbms'] : '';
            //create a connection to the database
            $options = array('driver' => $params['database_type'], 'host' => $params['database_host'], 'user' => $params['database_user'], 'password' => $params['database_password'], 'database' => $params['database_name'], 'prefix' => $params['database_prefix']);
            //Get configuration settings stored in the database
	        try {
		        $vdb = JDatabaseDriver::getInstance($options);

		        if (!$vdb) {
			        JFusionFunction::raiseWarning(0, JText::_('NO_DATABASE'));
			        return false;
		        } else {
			        $query = 'SELECT config_name, config_value FROM #__config WHERE config_name IN (\'script_path\', \'cookie_path\', \'server_name\', \'cookie_domain\', \'cookie_name\', \'allow_autologin\')';
			        $vdb->setQuery($query);
			        $rows = $vdb->loadObjectList();
			        foreach ($rows as $row) {
				        $config[$row->config_name] = $row->config_value;
			        }
			        //store the new found parameters
			        $params['cookie_path'] = isset($config['cookie_path']) ? $config['cookie_path'] : '';
			        $params['cookie_domain'] = isset($config['cookie_domain']) ? $config['cookie_domain'] : '';
			        $params['cookie_prefix'] = isset($config['cookie_name']) ? $config['cookie_name'] : '';
			        $params['allow_autologin'] = isset($config['allow_autologin']) ? $config['allow_autologin'] : '';
			        $params['source_path'] = $forumPath;
		        }
		        $params['source_url'] = '';
		        if (isset($config['server_name'])) {
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
		        }
	        } catch (Exception $e) {
		        JFusionFunction::raiseWarning(0, JText::_('NO_DATABASE') . ' '. $e->getMessage());
		        return false;
	        }
        }
        //return the parameters so it can be saved permanently
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
        $query = 'SELECT username_clean as username, user_email as email, user_id as userid from #__users WHERE user_email NOT LIKE \'\' and user_email IS NOT null';
        $db->setQuery($query, $limitstart, $limit);
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
        $query = 'SELECT count(*) from #__users WHERE user_email NOT LIKE \'\' and user_email IS NOT null ';
        $db->setQuery($query);
        //getting the results
        $no_users = $db->loadResult();
        return $no_users;
    }

    /**
     * @return array
     */
    function getUsergroupList() {
        //get the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT group_id as id, group_name as name from #__groups;';
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
    }

    /**
     * @return string
     */
    function getDefaultUsergroup() {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
        $usergroup_id = null;
        if(!empty($usergroups)) {
            $usergroup_id = $usergroups[0];
        }
        //we want to output the usergroup name
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT group_name from #__groups WHERE group_id = ' . (int)$usergroup_id;
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * @return bool
     */
    function allowRegistration() {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT config_value FROM #__config WHERE config_name = \'require_activation\'';
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

    /**
     * @param string $url
     * @param int $itemid
     *
     * @return string
     */
    function generateRedirectCode($url, $itemid) {
        $params = JFusionFactory::getParams($this->getJname());
        $cookie_name = $params->get('cookie_prefix') . '_u';
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
$joomla_url = \'' . $url . '\';
$joomla_itemid = ' . $itemid . ';
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
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'SELECT b.user_id, a.group_name FROM #__groups as a INNER JOIN #__user_group as b ON a.group_id = b.group_id WHERE a.group_name = \'GLOBAL_MODERATORS\' or a.group_name = \'ADMINISTRATORS\'';
            $db->setQuery($query);
            $mod_list = $db->loadObjectList();
            $mod_array = array();
            foreach ($mod_list as $mod) {
                if (!isset($mod_array[$mod->user_id])) {
                    $mod_array[$mod->user_id] = $mod->user_id;
                }
            }
            $mod_ids = implode(',', $mod_array);
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
//JFUSION REDIRECT END';
        return $redirect_code;
    }

    /**
     * @return mixed
     */
    function enableRedirectMod() {
        $params = JFusionFactory::getParams($this->getJname());
        $joomla_params = JFusionFactory::getParams('joomla_int');
        $joomla_url = $joomla_params->get('source_url');
        $joomla_itemid = $params->get('redirect_itemid');

        //check to see if all vars are set
        if (empty($joomla_url)) {
            JFusionFunction::raiseWarning(0, JText::_('MISSING') . ' Joomla URL');
        } else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
            JFusionFunction::raiseWarning(0, JText::_('MISSING') . ' ItemID');
        } else if (!$this->isValidItemID($joomla_itemid)) {
            JFusionFunction::raiseWarning(0, JText::_('MISSING') . ' ItemID '. JText::_('MUST BE'). ' ' . $this->getJname());
        } else {
            $error = $this->disableRedirectMod();
            $reason = '';
            $mod_file = $this->getModFile('common.php', $error, $reason);
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
     * @return int
     */
    function disableRedirectMod() {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('common.php', $error, $reason);
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
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return string
     */
    function showRedirectMod($name, $value, $node, $control_name) {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('common.php', $error, $reason);
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
            <a href="javascript:void(0);" onclick="return JFusion.module('disableRedirectMod')">{$disable}</a>
            <a href="javascript:void(0);" onclick="return JFusion.module('enableRedirectMod')">{$update}</a>
HTML;
            return $output;
        } else {
            $text = JText::_('REDIRECTION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.module('enableRedirectMod')">{$enable}</a>
HTML;
            return $output;
        }
    }

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return mixed|string
     */
    function showAuthMod($name, $value, $node, $control_name) {
        //do a database check to avoid fatal error with incorrect database settings
        $db = JFusionFactory::getDatabase($this->getJname());
        if (!method_exists($db, 'setQuery')) {
            return JText::_('NO_DATABASE');
        }
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = file_get_contents($mod_file);
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
        if ($error == 0) {
            //return success
            $text = JText::_('AUTHENTICATION_MOD') . ' ' . JText::_('ENABLED');
            $disable = JText::_('MOD_DISABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.module('disableAuthMod')">{$disable}</a>
HTML;
            return $output;
        } else {
            $text = JText::_('AUTHENTICATION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.module('enableAuthMod')">{$enable}</a>
HTML;
            return $output;
        }
    }
    function enableAuthMod() {
        $error = 0;
        $reason = '';
        $auth_file = $this->getModFile('includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php', $error, $reason);
        //see if the auth mod file exists
        if (!file_exists($auth_file)) {
            jimport('joomla.filesystem.file');
            $copy_file = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'auth_jfusion.php';
            JFile::copy($copy_file, $auth_file);
        }
        //get the joomla path from the file
        jimport('joomla.filesystem.file');
        $file_data = file_get_contents($auth_file);
        //compare it with our joomla path
        if (preg_match_all('/JFUSION_PATH/', $file_data, $matches)) {
            $file_data = preg_replace('/JFUSION_JNAME/', $this->getJname(), $file_data);
	        $file_data = preg_replace('/JFUSION_PATH/', JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion', $file_data);
            JFile::write($auth_file, $file_data);
        }

        //only update the database if the file now exists
        if (file_exists($auth_file)) {
	        try {
		        //check to see if the mod is enabled
		        $db = JFusionFactory::getDatabase($this->getJname());
		        $query = 'SELECT config_value FROM #__config WHERE config_name = \'auth_method\'';
		        $db->setQuery($query);
		        $auth_method = $db->loadResult();
		        if ($auth_method != 'jfusion') {
			        $query = 'UPDATE #__config SET config_value = \'jfusion\' WHERE config_name = \'auth_method\'';
			        $db->setQuery($query);
			        $db->execute();
		        }
	        } catch (Exception $e) {
		        //there was an error saving the parameters
		        JFusionFunction::raiseWarning(0, $e->getMessage());
	        }
        } else {
	        try {
		        //safety catch to make sure we use phpBB default to prevent lockout from phpBB
		        $db = JFusionFactory::getDatabase($this->getJname());
		        $query = 'UPDATE #__config SET config_value = \'db\' WHERE config_name = \'auth_method\'';
		        $db->setQuery($query);
		        $db->execute();
	        } catch (Exception $e) {
			    //there was an error saving the parameters
			    JFusionFunction::raiseWarning(0, $e->getMessage());
		    }
        }
        //clear the config cache so that phpBB recognizes the change
        $this->clearConfigCache();
    }

    /**
     * @return bool
     */
    function disableAuthMod() {
        $return = true;
        //check to see if the mod is enabled
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__config SET config_value = \'db\' WHERE config_name = \'auth_method\'';
        $db->setQuery($query);
        if (!$db->execute()) {
            //there was an error saving the parameters
            JFusionFunction::raiseWarning(0, $db->stderr());
            $return = false;
        }
        //remove the file as well to allow for updates of the auth mod content
        $params = JFusionFactory::getParams($this->getJname());
        $path = $params->get('source_path');
        if (substr($path, -1) == DIRECTORY_SEPARATOR) {
            $auth_file = $path . 'includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php';
        } else {
            $auth_file = $path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php';
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

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return string
     */
    function showQuickMod($name, $value, $node, $control_name) {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('mcp.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = file_get_contents($mod_file);
            preg_match_all('/global \$action/', $file_data, $matches);
            //compare it with our joomla path
            if (!isset($matches[0][0])) {
                $error = 1;
                $reason = JText::_('MOD') . ' ' . JText::_('DISABLED');
            }
        }
        //add the javascript to enable buttons
        if ($error == 0) {
            //return success
            $text = JText::_('QUICKTOOLS') . ' ' . JText::_('ENABLED');
            $disable = JText::_('MOD_DISABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.module('disableQuickMod')">{$disable}</a>
HTML;
            return $output;
        } else {
            $text = JText::_('QUICKTOOLS') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.module('enableQuickMod')">{$enable}</a>
HTML;
            return $output;
        }
    }
    function enableQuickMod() {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('mcp.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = file_get_contents($mod_file);
            $search = '/\$action \= request_var/si';
            $replace = 'global $action; $action = request_var';
            $file_data = preg_replace($search, $replace, $file_data);
            JFile::write($mod_file, $file_data);
        }
    }

    /**
     * @return int
     */
    function disableQuickMod() {
        $error = 0;
        $reason = '';
        $mod_file = $this->getModFile('mcp.php', $error, $reason);
        if ($error == 0) {
            //get the joomla path from the file
            jimport('joomla.filesystem.file');
            $file_data = file_get_contents($mod_file);
            $search = '/global \$action\;/si';
            $file_data = preg_replace($search, '', $file_data);
            if (!JFile::write($mod_file, $file_data)) {
                $error = 1;
            }
        }
        return $error;
    }

    /**
     * @return bool
     */
    function clearConfigCache() {
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');
        $cache = $source_path . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data_global.php';
        if (file_exists($cache)) {
            jimport('joomla.filesystem.file');
            return JFile::delete($cache);
        }
        return true;
    }

    /**
     * @return array
     */
    function uninstall() {
        $return = true;
        $reasons = array();

        $error = $this->disableAuthMod();
        if (!$error) {
            $reasons[] = JText::_('AUTH_MOD_UNINSTALL_FAILED');
            $return = false;
        }

        //doesn't really matter if the quick mod is not disabled so don't return an error
        $error = $this->disableQuickMod();

        $error = $this->disableRedirectMod();
        if (!empty($error)) {
           $reasons[] = JText::_('REDIRECT_MOD_UNINSTALL_FAILED');
           $return = false;
        }

        return array($return, $reasons);
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
		return 'DEPENDS';
	}    
}
