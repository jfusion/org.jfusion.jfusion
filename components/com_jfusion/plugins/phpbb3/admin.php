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
    function getTablename()
    {
        return 'users';
    }

    /**
     * @param string $softwarePath
     *
     * @return array
     */
    function setupFromPath($softwarePath)
    {
	    $myfile = $softwarePath . 'config.php';

        $params = array();
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
            JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . JText::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        } else {
            //parse the file line by line to get only the config variables
            $config = array();
	        foreach ($lines as $line) {
		        if (strpos($line, '$') === 0) {
			        //extract the name and value, it was coded to avoid the use of eval() function
			        $vars = explode("'", $line);
			        $name = trim($vars[0], ' $=');
			        $value = trim($vars[1], ' $=');
			        $config[$name] = $value;
		        }
	        }

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
		        $db = JDatabaseDriver::getInstance($options);

		        if (!$db) {
			        JFusionFunction::raiseWarning(JText::_('NO_DATABASE'), $this->getJname());
			        return false;
		        } else {
			        $query = $db->getQuery(true)
				        ->select('config_name, config_value')
				        ->from('#__config')
				        ->where('config_name IN (\'script_path\', \'cookie_path\', \'server_name\', \'cookie_domain\', \'cookie_name\', \'allow_autologin\')');

			        $db->setQuery($query);
			        $rows = $db->loadObjectList();
			        foreach ($rows as $row) {
				        $config[$row->config_name] = $row->config_value;
			        }
			        //store the new found parameters
			        $params['cookie_path'] = isset($config['cookie_path']) ? $config['cookie_path'] : '';
			        $params['cookie_domain'] = isset($config['cookie_domain']) ? $config['cookie_domain'] : '';
			        $params['cookie_prefix'] = isset($config['cookie_name']) ? $config['cookie_name'] : '';
			        $params['allow_autologin'] = isset($config['allow_autologin']) ? $config['allow_autologin'] : '';
			        $params['source_path'] = $softwarePath;
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
		        JFusionFunction::raiseWarning(JText::_('NO_DATABASE') . ' ' . $e->getMessage(), $this->getJname());
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
    function getUserList($limitstart = 0, $limit = 0)
    {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('username_clean as username, user_email as email, user_id as userid')
			    ->from('#__users')
		        ->where('user_email NOT LIKE ' . $db->quote(''))
			    ->where('user_email IS NOT null');

		    $db->setQuery($query, $limitstart, $limit);
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
    function getUserCount()
    {
	    try {
		    //getting the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__users')
			    ->where('user_email NOT LIKE ' . $db->quote(''))
			    ->where('user_email IS NOT null');

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
    function getUsergroupList()
    {
	    try {
		    //get the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('group_id as id, group_name as name')
			    ->from('#__groups');

		    $db->setQuery($query);
		    //getting the results
		    return $db->loadObjectList();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    return array();
	    }
    }

    /**
     * @return string|array
     */
    function getDefaultUsergroup()
    {
	    try {
		    $usergroup = JFusionFunction::getUserGroups($this->getJname(), true);

		    if ($usergroup !== null) {
			    //we want to output the usergroup name
			    $db = JFusionFactory::getDatabase($this->getJname());

			    if (!isset($usergroup->groups)) {
				    $usergroup->groups = array($usergroup->defaultgroup);
			    } else if (!in_array($usergroup->defaultgroup, $usergroup->groups)) {
				    $usergroup->groups[] = $usergroup->defaultgroup;
			    }
			    $group = array();
			    foreach ($usergroup->groups as $g) {
				    $query = $db->getQuery(true)
					    ->select('group_name')
					    ->from('#__groups')
					    ->where('group_id = ' . $db->quote($g));

				    $db->setQuery($query);
				    $group[] = $db->loadResult();
			    }
		    } else {
			    $group = '';
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $group = '';
	    }
	    return $group;
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
	    $result = false;
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('config_value')
			    ->from('#__config')
			    ->where('config_name = ' . $db->quote('require_activation'));

		    $db->setQuery($query);
		    //getting the results
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
     * @param string $url
     * @param int $itemid
     *
     * @return string
     */
    function generateRedirectCode($url, $itemid)
    {
	    try {
		    $cookie_name = $this->params->get('cookie_prefix') . '_u';
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
		    $allow_mods = $this->params->get('mod_ids');
		    if (!empty($allow_mods)) {
			    //get a userlist of mod ids
			    $db = JFusionFactory::getDatabase($this->getJname());

			    $query = $db->getQuery(true)
				    ->select('b.user_id, a.group_name')
				    ->from('#__groups as a')
			        ->innerJoin('#__user_group as b ON a.group_id = b.group_id')
				    ->where('a.group_name = ' . $db->quote('GLOBAL_MODERATORS'))
				    ->where('a.group_name = ' . $db->quote('ADMINISTRATORS'));

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
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    return '';
	    }
    }

	/**
	 * @param $action
	 *
	 * @return int
	 */
	function redirectMod($action)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getModFile('common.php', $error, $reason);
		switch($action) {
			case 'reenable':
			case 'disable':
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
				if ($action == 'disable') {
					break;
				}
			case 'enable':
				$joomla_url = JFusionFactory::getParams('joomla_int')->get('source_url');
				$joomla_itemid = $this->params->get('redirect_itemid');

				//check to see if all vars are set
				if (empty($joomla_url)) {
					JFusionFunction::raiseWarning(JText::_('MISSING') . ' Joomla URL', $this->getJname());
				} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
					JFusionFunction::raiseWarning(JText::_('MISSING') . ' ItemID', $this->getJname());
				} else if (!$this->isValidItemID($joomla_itemid)) {
					JFusionFunction::raiseWarning(JText::_('MISSING') . ' ItemID ' . JText::_('MUST BE') . ' ' . $this->getJname(), $this->getJname());
				} else if ($error == 0) {
					//get the joomla path from the file
					jimport('joomla.filesystem.file');
					$file_data = file_get_contents($mod_file);
					$redirect_code = $this->generateRedirectCode($joomla_url, $joomla_itemid);
					$search = '/\<\?php/si';
					$replace = '<?php' . $redirect_code;

					$file_data = preg_replace($search, $replace, $file_data);
					JFile::write($mod_file, $file_data);
				}
				break;
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
    function showRedirectMod($name, $value, $node, $control_name)
    {
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
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'disable')">{$disable}</a>
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'reenable')">{$update}</a>
HTML;
        } else {
            $text = JText::_('REDIRECTION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'enable')">{$enable}</a>
HTML;
        }
	    return $output;
    }

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return mixed|string
     */
    function showAuthMod($name, $value, $node, $control_name)
    {
	    try {
		    //do a database check to avoid fatal error with incorrect database settings
		    $db = JFusionFactory::getDatabase($this->getJname());

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
			    $query = $db->getQuery(true)
				    ->select('config_value')
				    ->from('#__config')
				    ->where('config_name = ' . $db->quote('auth_method'));

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
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('disableAuthMod')">{$disable}</a>
HTML;
			    return $output;
		    } else {
			    $text = JText::_('AUTHENTICATION_MOD') . ' ' . JText::_('DISABLED') . ': ' . $reason;
			    $enable = JText::_('MOD_ENABLE');
			    $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('enableAuthMod')">{$enable}</a>
HTML;
			    return $output;
		    }
	    } catch (Exception $e) {
            return $e->getMessage();
	    }
    }

    function enableAuthMod()
    {
        $error = 0;
        $reason = '';
        $auth_file = $this->getModFile('includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php', $error, $reason);
        //see if the auth mod file exists
        if (!file_exists($auth_file)) {
            jimport('joomla.filesystem.file');
            $copy_file = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'auth_jfusion.php';
            JFile::copy($copy_file, $auth_file);
        }
	    if (file_exists($auth_file)) {
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

				    $query = $db->getQuery(true)
					    ->select('config_value')
					    ->from('#__config')
					    ->where('config_name = ' . $db->quote('auth_method'));

				    $db->setQuery($query);
				    $auth_method = $db->loadResult();
				    if ($auth_method != 'jfusion') {
					    $query = $db->getQuery(true)
						    ->update('#__config')
						    ->set('config_value = ' . $db->quote('jfusion'))
						    ->where('config_name  = ' . $db->quote('auth_method'));

					    $db->setQuery($query);
					    $db->execute();
				    }
			    } catch (Exception $e) {
				    //there was an error saving the parameters
				    JFusionFunction::raiseWarning($e, $this->getJname());
			    }
		    } else {
			    try {
				    //safety catch to make sure we use phpBB default to prevent lockout from phpBB
				    $db = JFusionFactory::getDatabase($this->getJname());

				    $query = $db->getQuery(true)
					    ->update('#__config')
					    ->set('config_value = ' . $db->quote('db'))
					    ->where('config_name  = ' . $db->quote('auth_method'));

				    $db->setQuery($query);
				    $db->execute();
			    } catch (Exception $e) {
				    //there was an error saving the parameters
				    JFusionFunction::raiseWarning($e, $this->getJname());
			    }
		    }
		    //clear the config cache so that phpBB recognizes the change
		    $this->clearConfigCache();
	    } else {
		    JFusionFunction::raiseWarning('FAILED_TO_COPY_AUTHFILE' . $auth_file, $this->getJname());
	    }
    }

    /**
     * @return bool
     */
    function disableAuthMod()
    {
        $return = true;
	    try {
		    //check to see if the mod is enabled
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__config')
			    ->set('config_value = ' . $db->quote('db'))
			    ->where('config_name  = ' . $db->quote('auth_method'));

		    $db->setQuery($query);
		    $db->execute();

		    //remove the file as well to allow for updates of the auth mod content
		    $source_path = $this->params->get('source_path');
		    $auth_file = $source_path . 'includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php';
		    if (file_exists($auth_file)) {
			    jimport('joomla.filesystem.file');
			    if (!JFile::delete($auth_file)) {
				    throw new RuntimeException('Cant delete file: ' . $auth_file);
			    }
		    }

		    //clear the config cache so that phpBB recognizes the change
		    $cleared = $this->clearConfigCache();
		    if (!$cleared) {
			    throw new RuntimeException('Cash not cleared!');
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseWarning($e, $this->getJname());
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
    function showQuickMod($name, $value, $node, $control_name)
    {
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
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('quickMod', 'disable')">{$disable}</a>
HTML;
            return $output;
        } else {
            $text = JText::_('QUICKTOOLS') . ' ' . JText::_('DISABLED') . ': ' . $reason;
            $enable = JText::_('MOD_ENABLE');
            $output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('quickMod', 'enable')">{$enable}</a>
HTML;
            return $output;
        }
    }

	/**
	 * @param $action
	 *
	 * @return int
	 */
	function quickMod($action)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getModFile('mcp.php', $error, $reason);
		switch($action) {
			case 'reenable':
			case 'disable':
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
				if ($action == 'disable') {
					break;
				}
			case 'enable':
				if ($error == 0) {
					//get the joomla path from the file
					jimport('joomla.filesystem.file');
					$file_data = file_get_contents($mod_file);
					$search = '/\$action \= request_var/si';
					$replace = 'global $action; $action = request_var';
					$file_data = preg_replace($search, $replace, $file_data);
					JFile::write($mod_file, $file_data);
				}
				break;
		}
		return $error;
	}

    /**
     * @return bool
     */
    function clearConfigCache()
    {
        $source_path = $this->params->get('source_path');
        $cache = $source_path . 'cache' . DIRECTORY_SEPARATOR . 'data_global.php';
        if (file_exists($cache)) {
            jimport('joomla.filesystem.file');
            return JFile::delete($cache);
        }
        return true;
    }

    /**
     * @return array
     */
    function uninstall()
    {
        $return = true;
        $reasons = array();

        $error = $this->disableAuthMod();
        if (!$error) {
            $reasons[] = JText::_('AUTH_MOD_UNINSTALL_FAILED');
            $return = false;
        }

        //doesn't really matter if the quick mod is not disabled so don't return an error
        $this->quickMod('disable');

        $error = $this->redirectMod('disable');
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

	/**
	 * create the render group function
	 *
	 * @return string
	 */
	function getRenderGroup()
	{
		$jname = $this->getJname();

		JFusionFunction::loadJavascriptLanguage(array('MAIN_USERGROUP', 'MEMBERGROUPS'));
		$js = <<<JS
		JFusion.renderPlugin['{$jname}'] = function(index, plugin, pair) {
			var usergroups = JFusion.usergroups[plugin.name];

			var div = new Element('div');

			// render default group
			div.appendChild(new Element('div', {'html': Joomla.JText._('MAIN_USERGROUP')}));

		    var defaultselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][defaultgroup]',
		    	'id': 'usergroups_'+plugin.name+index+'defaultgroup'
		    });

			jQuery(document).on('change', '#usergroups_'+plugin.name+index+'defaultgroup', function() {
                var value = this.get('value');

				jQuery('#'+'usergroups_'+plugin.name+index+'groups'+' option').each(function() {
					if (jQuery(this).attr('value') == value) {
						jQuery(this).prop('selected', false);
						jQuery(this).prop('disabled', true);

						jQuery(this).trigger('chosen:updated').trigger('liszt:updated');
	                } else if (jQuery(this).prop('disabled') === true) {
						jQuery(this).prop('disabled', false);
						jQuery(this).trigger('chosen:updated').trigger('liszt:updated');
					}
				});
			});

		    Array.each(usergroups, function (group) {
			    var options = {'value': group.id,
					            'html': group.name};

		        if (pair && pair.defaultgroup && pair.defaultgroup == group.id) {
					options.selected = 'selected';
		        }

				defaultselect.appendChild(new Element('option', options));
		    });
		    div.appendChild(defaultselect);


			// render default member groups
			div.appendChild(new Element('div', {'html': Joomla.JText._('MEMBERGROUPS')}));


		    var membergroupsselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][groups][]',
		    	'multiple': 'multiple',
		    	'id': 'usergroups_'+plugin.name+index+'groups'
		    });


		    Array.each(usergroups, function (group, i) {
			    var options = {'value': group.id,
					            'html': group.name};

		        if (pair && pair.defaultgroup == group.id) {
					options.disabled = 'disabled';
		        } else if (!pair && i === 0) {
		        	options.disabled = 'disabled';
		        } else {
		            if (pair && pair.groups && pair.groups.contains(group.id)) {
						options.selected = 'selected';
		        	}
		        }

				membergroupsselect.appendChild(new Element('option', options));
		    });
		    div.appendChild(membergroupsselect);
		    return div;
		};
JS;
		return $js;
	}
}
