<?php

/**
 * Model for joomla actions
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * load the JFusion framework
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.curl.php';
/**
 * Common Class for Joomla JFusion plugins
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionJplugin
{
    /**
     * Generates an encrypted password based on the userinfo passed to this function
     *
     * @param object $userinfo userdata object containing the userdata
     *
     * @return string Returns generated password
     */
    public static function generateEncryptedPassword($userinfo)
    {
        jimport('joomla.user.helper');
        $crypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt);
        return $crypt;
    }

    /**
     * returns the name of user table of integrated software
     *
     * @return string table name
     */
    public static function getTablename()
    {
        return 'users';
    }

    /**
     * Returns the registration URL for the integrated software
     *
     * @param string $jname
     *
     * @return string registration URL
     */
    public static function getRegistrationURL($jname='joomla_int')
    {
        if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
            $url = 'index.php?option=com_users&view=registration';
        } else {
            $url = 'index.php?option=com_user&view=register';
        }
        return $url;
    }

    /**
     * Returns the lost password URL for the integrated software
     *
     * @param string $jname
     *
     * @return string lost password URL
     */
    public static function getLostPasswordURL($jname='joomla_int')
    {
        if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
            $url = 'index.php?option=com_users&view=reset';
        } else {
            $url = 'index.php?option=com_user&view=reset';
        }
        return $url;
    }

    /**
     * Returns the lost username URL for the integrated software
     *
     * @param string $jname
     *
     * @return string lost username URL
     */
    public static function getLostUsernameURL($jname='joomla_int')
    {
        if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
            $url = 'index.php?option=com_users&view=remind';
        } else {
            $url = 'index.php?option=com_user&view=remind';
        }
        return $url;
    }

    /**
     * Returns the a list of users of the integrated software
     *
     * @param string $jname jfusion plugin name
     * @param int $limitstart start at
     * @param int $limit number of results
     *
     * @return array List of usernames/emails
     */
    public static function getUserList($jname,$limitstart = 0, $limit = 0)
    {
        $db = JFusionFactory::getDatabase($jname);
        $query = 'SELECT username, email from #__users';
        $db->setQuery($query,$limitstart,$limit);
        $userlist = $db->loadObjectList();
        return $userlist;
    }
    /**
     * Returns the the number of users in the integrated software. Allows for fast retrieval total number of users for the usersync
     *
     * @param string $jname jfusion plugin name
     *
     * @return integer Number of registered users
     */
    public static function getUserCount($jname)
    {
        $db = JFusionFactory::getDatabase($jname);
        $query = 'SELECT count(*) from #__users';
        $db->setQuery($query);
        //getting the results
        return $db->loadResult();
    }

    /**
     * Returns the a list of usersgroups of the integrated software
     *
     * @param string $jname jfusion plugin name
     *
     * @return array List of usergroups
     */
    public static function getUsergroupList($jname)
    {
        $db = JFusionFactory::getDatabase($jname);
        if(JFusionFunction::isJoomlaVersion('1.6',$jname)){
            $query = 'SELECT id, title as name FROM #__usergroups';
        } else {
        	$query = 'SELECT id, name FROM #__core_acl_aro_groups WHERE name != \'ROOT\' AND name != \'USERS\'';
        }
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
    }

    /**
     * Function used to display the default usergroup in the JFusion plugin overview
     *
     * @param string $jname jfusion plugin name
     *
     * @return string Default usergroup name
     */
    public static function getDefaultUsergroup($jname)
    {
        $params = JFusionFactory::getParams($jname);
        $db = JFusionFactory::getDatabase($jname);
        if (JFusionFunction::isAdvancedUsergroupMode($jname)) {
            $group = JText::_('ADVANCED_GROUP_MODE');
        } else {
            if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                $usergroup_id = $params->get('usergroup', 2);
                //we want to output the usergroup name
                $query = 'SELECT title from #__usergroups WHERE id = ' . $usergroup_id;
                $db->setQuery($query);
                $group = $db->loadResult();
            } else {
                $usergroup_id = $params->get('usergroup', 18);
                //we want to output the usergroup name
                $query = 'SELECT name from #__core_acl_aro_groups WHERE id = ' . $usergroup_id;
                $db->setQuery($query);
                $group = $db->loadResult();
            }
        }
        return $group;
    }

    /**
     * Function used get usergroup name by id
     *
     * @param string $jname jfusion plugin name
     * @param int $gid joomla group id
     *
     * @return string Default usergroup name
     */
    function getUsergroupName($jname,$gid)
    {
        $params = JFusionFactory::getParams($jname);
        $db = JFusionFactory::getDatabase($jname);
        if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
			//we want to output the usergroup name
			$query = 'SELECT title from #__usergroups WHERE id = ' . $gid;
			$db->setQuery($query);
            $group = $db->loadResult();
        } else {
			//we want to output the usergroup name
			$query = 'SELECT name from #__core_acl_aro_groups WHERE id = ' . $gid;
			$db->setQuery($query);
			$group = $db->loadResult();
        }
        return $group;
    }

    /**
     * Checks if the software allows new users to register
     *
     * @param string $jname jfusion plugin name
     *
     * @return boolean True if new user registration is allowed, otherwise returns false
     */
    public static function allowRegistration($jname)
    {
        if ($jname == 'joomla_int') {
            $params = JComponentHelper::getParams('com_users');
        } else {
            $db = JFusionFactory::getDatabase($jname);

            if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                //we want to output the usergroup name
                $query = 'SELECT params from #__extensions WHERE element = \'com_users\'';
                $db->setQuery($query);
                $params = $db->loadResult();
            } else {
                //we want to output the usergroup name
                $query = 'SELECT params from #__components WHERE option = \'com_users\'';
                $db->setQuery($query);
                $params = $db->loadResult();
            }
            $params = new JParameter($params);
        }
		// Return true if the 'allowUserRegistration' switch is enabled in the component parameters.
		return ($params->get('allowUserRegistration') ? true : false);
    }

    /**
     * Function finds config file of integrated software and automatically configures the JFusion plugin
     *
     * @param string $path path to root of integrated software
     *
     * @return object JParam JParam objects with ne newly found configuration
     * Now Joomla 1.6+ compatible
     */
    public static function setupFromPath($path)
    {
        //check for trailing slash and generate file path
        if (substr($path, -1) == DS) {
            $configfile = $path . 'configuration.php';
            //joomla 1.6+ test
            $test_version_file = $path . 'includes' . DS . 'version.php';
        } else {
            $configfile = $path . DS . 'configuration.php';
            $test_version_file = $path . DS . 'includes' . DS . 'version.php';
        }
        $params = array();
        if (($file_handle = @fopen($configfile, 'r')) === false) {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $configfile " . JText::_('WIZARD_MANUAL'));
        } else {
            //parse the file line by line to get only the config variables
            //we can not directly include the config file as JConfig is already defined
            $file_handle = fopen($configfile, 'r');
            $config = array();
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                if (strpos($line, '$')) {
                    //extract the name and value, it was coded to avoid the use of eval() function
                    // because from Joomla 1.6 the configuration items are declared public in tead of var
                    // we just convert public to var
                    $line = str_replace('public $','var $',$line);
                	$vars = explode("'", $line);
                    $names = explode('var', $vars[0]);
                    if (isset($vars[1]) && isset($names[1])) {
                        $name = trim($names[1], ' $=');
                        $value = trim($vars[1], ' $=');
                        $config[$name] = $value;
                    }
                }
            }
            fclose($file_handle);

            //Save the parameters into the standard JFusion params format
            $params['database_host'] = isset($config['host']) ? $config['host'] : '';
            $params['database_name'] = isset($config['db']) ? $config['db'] : '';
            $params['database_user'] = isset($config['user']) ? $config['user'] : '';
            $params['database_password'] = isset($config['password']) ? $config['password'] : '';
            $params['database_prefix'] = isset($config['dbprefix']) ? $config['dbprefix'] : '';
            $params['database_type'] = isset($config['dbtype']) ? $config['dbtype'] : '';
            $params['source_path'] = $path;

            //determine if this is 1.5 or 1.6+
            $params['joomlaversion'] = (file_exists($test_version_file)) ? '1.6' : '1.5';
        }
        return $params;
    }
    /**
     * Common code for user.php
     *
     * @param object $userinfo userinfo
     * @param array $options  options
     * @param string $jname    jname
     * @param string $type    jname
     * @param array $curl_options_merge
     *
     * @return string nothing
     */
    public static function createSession($userinfo, $options, $jname, $type = 'brute_force',$curl_options_merge=array())
    {
        global $ch;
        global $cookiearr;
        global $cookies_to_set;
        global $cookies_to_set_index;
        $cookiearr = array();
        $cookies_to_set = array();
        $cookies = array();
        $cookie = array();
        $curl_options = array();
        $status = array('error' => array(),'debug' => array());
        $cookies_to_set_index = 0;
        $params = JFusionFactory::getParams($jname);
        $source_url = $params->get('source_url');
        $login_url = $params->get('login_url');
        //prevent user error by not supplying trailing forward slash
        if (substr($source_url, -1) != '/') {
            $source_url = $source_url . '/';
        }
        //prevent user error by preventing a heading forward slash
        ltrim($login_url, '/');
        $curl_options['post_url'] = $source_url . $login_url;

        //set some defaults for standard Joomla login modules
        if ($jname == 'joomla_ext') {
            $jv = $params->get('joomlaversion', '1.5');
            $default_loginform = ($jv == '1.5') ? 'form-login' : 'login-form';
        } else {
            $default_loginform = '';
        }

        $curl_options['formid'] = $params->get('loginform_id', $default_loginform);

        $login_identifier = $params->get('login_identifier', '1');
        $identifier = ($login_identifier === '2') ? 'email' : 'username';

        $curl_options['username'] = $userinfo->$identifier;
        $curl_options['password'] = $userinfo->password_clear;
        $integrationtype1 = $params->get('integrationtype');
        $curl_options['relpath']=  $params->get('relpath');
        $curl_options['hidden'] = $params->get('hidden');
        $curl_options['buttons'] = $params->get('buttons');
        $curl_options['override'] = $params->get('override');
        $curl_options['cookiedomain'] = $params->get('cookie_domain');
        $curl_options['cookiepath'] = $params->get('cookie_path');
        $curl_options['expires'] = $params->get('cookie_expires');
        $curl_options['input_username_id'] = $params->get('input_username_id');
        $curl_options['input_password_id'] = $params->get('input_password_id');
        $curl_options['secure'] = $params->get('secure');
        $curl_options['httponly'] = $params->get('httponly');
        $curl_options['verifyhost'] = 0; //$params->get('ssl_verifyhost');
        $curl_options['httpauth'] = $params->get('httpauth');
        $curl_options['httpauth_username'] = $params->get('curl_username');
        $curl_options['httpauth_password'] = $params->get('curl_password');

        // to prevent endless loops on systems where there are multiple places where a user can login
        // we post an unique ID for the initiating software so we can make a difference between
        // a user logging in or another jFusion installation, or even another system with reverse dual login code.
        // We always use the source url of the initializing system, here the source_url as defined in the joomla_int
        // plugin. This is totally transparent for the the webmaster. No additional setup is needed

        $my_ID = rtrim(parse_url(JURI::root(), PHP_URL_HOST).parse_url(JURI::root(), PHP_URL_PATH), '/');
        $curl_options['jnodeid'] = strtolower($my_ID);

        // For further simplifying setup we send also an indication if this system is a host. Other hosts should
        // only perform local joomla login when received this post. We define being a host if we have
        // at least one slave.

        $plugins = JFusionFactory::getPlugins('slave');
        if (count($plugins) > 2 ) {
            $jhost = true;
        } else {
            $jhost = false;
        }

        if ($jhost) {
            $curl_options['jhost'] = true;
        }
        if (!empty($curl_options_merge)) {
            $curl_options = array_merge($curl_options,$curl_options_merge);
        }

        // This check is just for Jfusion 1.x to support the reverse dual login function
        // We need to check if JFusion tries to create this session because of this integration
        // initiated a login by means of the reverse dual login extensions. Note that
        // if the curl routines are not used, the same check must be performed in the
        // create session routine in the user.php file of the plugin concerned.
        // In version 2.0 we will never reach this point as the user plugin will handle this
        $jnodeid = strtolower(JRequest::getVar('jnodeid'));
        if (!empty($jnodeid)){
        	if($jnodeid == JFusionFactory::getPluginNodeId($jname)) {
        		// do not create a session, this integration started the log in and the user is already logged in
                $status['debug'][]=JText::_('ALREADY_LOGGED_IN');
                return $status;
        	}
        }

        // correction of the integration type for Joomla Joomla using a sessionid in the logout form
        // for joomla 1.5 we need integration type 1 for login (LI) and 0 for logout (LO)
        // this is backward compatible
        // joomla 1.5  : use 3
        // joomla 1.6+ : use 1
        
        switch ($integrationtype1) {
        	case "0":				// LI = 0  LO = 0
        	case "2":				// LI = 0, LO = 1
        		$integrationtype = 0;
        		break;
        	case "1":				// LI = 1  LO = 1
        	case "3":				// LI = 1, LO = 0
        	default:
        		$integrationtype = 1;
        		break;
        }

        $curl_options['integrationtype'] = $integrationtype;
        
       
        // extra lines for passing curl options to other routines, like ambrasubs payment processor
        // we are using the super global $_SESSION to pass data in $_SESSION[$var]
        $var = 'curl_options';
        if(!array_key_exists($var,$_SESSION)) $_SESSION[$var]='';
        $_SESSION[$var]=$curl_options;
        $GLOBALS[$var]=&$_SESSION[$var];
        // end extra lines

        $type = strtolower($type);
        switch ($type) {
            case "url":
//              $status = JFusionCurl::RemoteLoginUrl($curl_options);
                $status['error'][]=JText::_('CURL_LOGINTYPE_NOT_SUPPORTED');
                break;
            case "brute_force":
        	   $curl_options['brute_force'] = $type;
                $status = JFusionCurl::RemoteLogin($curl_options);
        	   break;
            default:
                $status = JFusionCurl::RemoteLogin($curl_options);
        }
        $status['debug'][]=JText::_('CURL_LOGINTYPE').'='.$type;
        return $status;
    }

    /**
     * Function that automatically logs out the user from the integrated software
     *
     * @param object $userinfo contains the userinfo
     * @param array  $options  contains Array with the login options, such as remember_me
     * @param string $jname    jname
     * @param string $type     method of destruction
     * @param array $curl_options_merge
     *
     * @return array result Array containing the result of the session destroy
     */
    public static function destroySession($userinfo, $options, $jname, $type = 'brute_force',$curl_options_merge=array())
    {
        global $ch;
        global $cookiearr;
        global $cookies_to_set;
        global $cookies_to_set_index;
        $cookiearr = array();
        $cookies_to_set = array();
        $cookies = array();
        $cookie = array();
        $curl_options = array();
        $status = array('error' => array(),'debug' => array());
        $cookies_to_set_index = 0;

        $params = JFusionFactory::getParams($jname);
        $source_url = $params->get('source_url');
        $logout_url = $params->get('logout_url');
        //prevent user error by not supplying trailing forward slash
        if (substr($source_url, -1) != '/') {
        	$source_url = $source_url . '/';
        }
        //prevent user error by preventing a heading forward slash
        ltrim($logout_url, '/');
        $curl_options['post_url'] = $source_url . $logout_url;

        //set some defaults for standard Joomla login modules
        if ($jname == 'joomla_ext') {
            $jv = $params->get('joomlaversion', '1.5');
            $default_loginform = ($jv == '1.5') ? 'form-login' : 'login-form';
        } else {
            $default_loginform = '';
        }

        $curl_options['formid'] = $params->get('loginform_id', $default_loginform);
        $curl_options['username'] = $userinfo->username;
//        $curl_options['password'] = $userinfo->password_clear;
        $integrationtype1 = $params->get('integrationtype');
        $curl_options['relpath'] = $params->get('relpathl',$params->get('relpath',0));
        $curl_options['hidden'] = '1';
        $curl_options['buttons'] = '1';
        $curl_options['override'] = '';
        $curl_options['cookiedomain'] = $params->get('cookie_domain');
        $curl_options['cookiepath'] = $params->get('cookie_path');
        $curl_options['expires'] = time() - 30*60*60;
        $curl_options['input_username_id'] = $params->get('input_username_id');
        $curl_options['input_password_id'] = $params->get('input_password_id');
        $curl_options['secure'] = $params->get('secure');
        $curl_options['httponly'] = $params->get('httponly');
        $curl_options['verifyhost'] = 0; //$params->get('ssl_verifyhost');
        $curl_options['httpauth'] = $params->get('httpauth');
        $curl_options['httpauth_username'] = $params->get('curl_username');
        $curl_options['httpauth_password'] = $params->get('curl_password');
        $curl_options['leavealone'] = $params->get('leavealone');
        $curl_options['postfields'] = $params->get('postfields',"");
        $curl_options['logout'] = '1';

        // to prevent endless loops on systems where there are multiple places where a user can login
        // we post an unique ID for the initiating software so we can make a difference between
        // a user logging in or another jFusion installation, or even another system with reverse dual login code.
        // We always use the source url of the initializing system, here the source_url as defined in the joomla_int
        // plugin. This is totally transparent for the the webmaster. No additional setup is needed

        $my_ID = rtrim(parse_url(JURI::root(), PHP_URL_HOST).parse_url(JURI::root(), PHP_URL_PATH), '/');
        $curl_options['jnodeid'] = strtolower($my_ID);

        // For further simplifying setup we send also an indication if this system is a host. Other hosts should
        // only perform local joomla login when received this post. We define being a host if we have
        // at least one slave.

        
        $plugins = JFusionFactory::getPlugins('slave');
        if (count($plugins) > 2 ) {
            $jhost = true;
        } else {
            $jhost = false;
        }

        if ($jhost) {
            $curl_options['jhost'] = true;
        }
        if (!empty($curl_options_merge)) {
            $curl_options = array_merge($curl_options,$curl_options_merge);
        }

        // This check is just for Jfusion 1.x to support the reverse dual login function
        // We need to check if JFusion tries to delete this session because of this integration
        // initiated a logout by means of the reverse dual login extensions. Note that
        // if the curl routines are not used, the same check must be performed in the
        // destroysession routine in the user.php file of the plugin concerned.
        // In version 2.0 we will never reach this point as the user plugin will handle this
        $jnodeid = strtolower(JRequest::getVar('jnodeid'));
        if (!empty($jnodeid)){
            if($jnodeid == JFusionFactory::getPluginNodeId($jname)) {
                // do not delete a session, this integration started the log out and the user is already logged out
                $status['debug'][]=JText::_('ALREADY_LOGGED_OUT');
                return $status;
            }
        }

 		// correction of the integration type for Joomla Joomla using a sessionid in the logout form
 		// for joomla 1.5 we need integration type 1 for login (LI) and 0 for logout (LO)
 		// this is backward compatible
 		// joomla 1.5  : use 3
 		// joomla 1.6+ : use 1

        switch ($integrationtype1) {
        	case "0":				// LI = 0  LO = 0
        	case "3":				// LI = 1, LO = 0
        		$integrationtype = 0;
        		break;
        	case "1":				// LI = 1  LO = 1
        	case "2":				// LI = 0, LO = 1
        	default:
        		$integrationtype = 1;
        		break;
        }
        $curl_options['integrationtype'] = $integrationtype;

        $type = strtolower($type);
        switch ($type) {
            case "url":
                $status = JFusionCurl::RemoteLogoutUrl($curl_options);
                break;
            case "form":
                $status = JFusionCurl::RemoteLogin($curl_options);
                break;
            case "brute_force":
            default:
                $status = JFusionCurl::RemoteLogout($curl_options);
        }
        $status['debug'][]=JText::_('CURL_LOGOUTTYPE').'='.$type;
        return $status;
    }

    /**
     * gets the userinfo from the JFusion integrated software. Definition of object:
     *
     * @param object $userinfo contains the object of the user
     * @param string $jname    jname
     *
     * @return null|object userinfo Object containing the user information
     */
    public static function getUser($userinfo, $jname)
    {
    	$db = JFusionFactory::getDatabase($jname);
        $JFusionUser = JFusionFactory::getUser($jname);
        list($identifier_type, $identifier) = $JFusionUser->getUserIdentifier($userinfo, 'username', 'email');
        if ($jname == 'joomla_int' && $identifier_type == 'username') {
            $params = JFusionFactory::getParams($jname);
            if ($params->get('case_insensitive')) {
                $where = 'LOWER(a.' . $identifier_type . ') = ' . $db->Quote(strtolower($identifier));
            } else {
                $where = 'a.' . $identifier_type . ' = ' . $db->Quote($identifier);
            }
            //first check the JFusion user table if the identifier_type = username
            if(JFusionFunction::isJoomlaVersion('1.6',$jname)){
                $db->setQuery('SELECT b.id as userid, b.activation, a.username, b.name, b.password, b.email, b.block, b.params FROM #__users as b INNER JOIN #__jfusion_users as a ON a.id = b.id WHERE ' . $where);
            } else {
                $db->setQuery('SELECT b.id as userid, b.activation, a.username, b.name, b.password, b.email, b.block, b.usertype as group_name, b.gid as group_id, b.params FROM #__users as b INNER JOIN #__jfusion_users as a ON a.id = b.id WHERE ' . $where);
            }
            $result = $db->loadObject();
            if (!$result) {
                if ($params->get('case_insensitive')) {
                    $where = 'LOWER(' . $identifier_type . ') = ' . $db->Quote(strtolower($identifier));
                } else {
                    $where = $identifier_type . ' = ' . $db->Quote($identifier);
                }
                //check directly in the joomla user table
                if(JFusionFunction::isJoomlaVersion('1.6',$jname)){
                    $db->setQuery('SELECT id as userid, activation, username, name, password, email, block, params FROM #__users WHERE ' . $where);
                } else {
                    $db->setQuery('SELECT id as userid, activation, username, name, password, email, block, usertype as group_name, gid as group_id, params FROM #__users WHERE ' . $where);
                }
                $result = $db->loadObject();
                if ($result) {
                    //update the lookup table so that we don't have to do a double query next time
                    $query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $result->userid . ', ' . $db->Quote($identifier) . ')';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        JError::raiseWarning(0, $db->stderr());
                    }
                }
            }
        } else {
        	if(JFusionFunction::isJoomlaVersion('1.6',$jname)){
                $db->setQuery('SELECT id as userid, activation, username, name, password, email, block, params FROM #__users WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier));
        	} else {
                $db->setQuery('SELECT id as userid, activation, username, name, password, email, block, usertype as group_name, gid as group_id, params FROM #__users WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier));
        	}
            $result = $db->loadObject();
        }
        if ($result) {
			if(JFusionFunction::isJoomlaVersion('1.6',$jname)){
       	    	$query = 'SELECT a.group_id, b.title as name FROM #__user_usergroup_map as a INNER JOIN #__usergroups as b ON a.group_id = b.id WHERE a.user_id='.$db->Quote($result->userid);
        		$db->setQuery($query);
				$groupList = $db->loadObjectList();
				if ($groupList) {
					foreach ($groupList as $group) {
						$result->groups[] = $group->group_id;
						$result->groupnames[] = $group->name;

						if ( !isset($result->group_id) || $group->group_id > $result->group_id) {
                            $result->group_id = $group->group_id;
                            $result->group_name =  $group->name;
						}
					}
				} else {
					$result->groups = array();
					$result->groupnames = array();
				}
        	}
            //split up the password if it contains a salt
            //note we cannot use explode as a salt from another software may contain a colon which messes Joomla up
            if (strpos($result->password, ':') !== false) {
                $saltStart = strpos($result->password, ':');
                $result->password_salt = substr($result->password, $saltStart + 1);
                $result->password = substr($result->password, 0, $saltStart);
            } else {
                //prevent php notices
                $result->password_salt = '';
            }
            // Get the language of the user and store it as variable in the user object
            $user_params = new JParameter($result->params);
            $JLang = JFactory::getLanguage();
            $result->language = $user_params->get('language', $JLang->getTag());
            unset($JLang);
            //unset the activation status if not blocked
            if ($result->block == 0) {
                $result->activation = '';
            }
            //unset the block if user is inactive
            if (!empty($result->block) && !empty($result->activation)) {
                $result->block = 0;
            }

            //check to see if CB is installed and activated and if so update the activation and ban accordingly
            if(JFusionFunction::isJoomlaVersion('1.6',$jname)){
                $query = 'SELECT enabled FROM #__extensions WHERE name LIKE \'%com_comprofiler%\'';
            } else {
                $query = 'SELECT enabled FROM #__components WHERE link LIKE \'%option=com_comprofiler%\'';
            }
            $db->setQuery($query);
            $cbenabled = $db->loadResult();

            if (!empty($cbenabled)) {
                $query = 'SELECT confirmed, approved, cbactivation FROM #__comprofiler WHERE user_id = '.$result->userid;
                $db->setQuery($query);
                $cbresult = $db->loadObject();

                if (!empty($cbresult)) {
                    if (empty($cbresult->confirmed) && !empty($cbresult->cbactivation)) {
                        $result->activation = $cbresult->cbactivation;
                        $result->block = 0;
                    } elseif (empty($cbresult->confirmed) || empty($cbresult->approved)) {
                        $result->block = 1;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Function that updates the user email
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     * @param string $jname         jname
     *
     * @return string updates are passed on into the $status array
     */
    public static function updateEmail($userinfo, &$existinguser, &$status, $jname)
    {
        $db = JFusionFactory::getDatabase($jname);
        $query = 'UPDATE #__users SET email =' . $db->Quote($userinfo->email) . ' WHERE id =' . $existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
        }
    }

    /**
     * Function that updates the user password
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     * @param string $jname         jname
     *
     * @return string updates are passed on into the $status array
     */
    public static function updatePassword($userinfo, &$existinguser, &$status, $jname)
    {
        $db = JFusionFactory::getDatabase($jname);
	    jimport( 'joomla.user.helper' );
        $userinfo->password_salt = JUserHelper::genRandomPassword(32);
        $userinfo->password = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt);
        $new_password = $userinfo->password . ':' . $userinfo->password_salt;
        $query = 'UPDATE #__users SET password =' . $db->Quote($new_password) . ' WHERE id =' . $existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
        }
    }

    /**
     * Function that blocks user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     * @param string $jname         jname
     *
     * @return string updates are passed on into the $status array
     */
    public static function blockUser($userinfo, &$existinguser, &$status, $jname)
    {
        //do not block super administrators
        if ($existinguser->group_id != 25) {
            //block the user
            $db = JFusionFactory::getDatabase($jname);
            $query = 'UPDATE #__users SET block = 1 WHERE id =' . $existinguser->userid;
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
            } else {
                $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
            }
        } else {
            $status['debug'][] = JText::_('BLOCK_UPDATE_ERROR') . ': ' . JText::_('CANNOT_BLOCK_SUPERADMINS');
        }
    }

    /**
     * Function that unblocks user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     * @param string $jname         jname
     *
     * @return string updates are passed on into the $status array
     */
    public static function unblockUser($userinfo, &$existinguser, &$status, $jname)
    {
        //unblock the user
        $db = JFusionFactory::getDatabase($jname);
        $query = 'UPDATE #__users SET block = 0 WHERE id =' . $existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        }
    }

    /**
     * Function that activates user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     * @param string $jname         jname
     *
     * @return string updates are passed on into the $status array
     */
    public static function activateUser($userinfo, &$existinguser, &$status, $jname)
    {
        //unblock the user
        $db = JFusionFactory::getDatabase($jname);
        $query = 'UPDATE #__users SET block = 0, activation = \'\' WHERE id =' . $existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }

    /**
     * Function that inactivates user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     * @param string $jname         jname
     *
     * @return string updates are passed on into the $status array
     */
    public static function inactivateUser($userinfo, &$existinguser, &$status, $jname)
    {
        if ($existinguser->group_id != 25) {
            //unblock the user
            $db = JFusionFactory::getDatabase($jname);
            $query = 'UPDATE #__users SET block = 1, activation = ' . $db->Quote($userinfo->activation) . ' WHERE id =' . $existinguser->userid;
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
            } else {
                $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
            }
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . JText::_('CANNOT_INACTIVATE_SUPERADMINS');
        }
    }

    /**
     * filters the username to remove invalid characters
     *
     * @param string $username contains username
     * @param string $jname    contains name of plugin
     *
     * @return string filtered username
     */
    public static function filterUsername($username, $jname)
    {
        //check to see if additional username filtering need to be applied
        $params = JFusionFactory::getParams($jname);
        $added_filter = $params->get('username_filter');
        if ($added_filter && $added_filter != $jname) {
            $JFusionPlugin = JFusionFactory::getUser($added_filter);
            if (method_exists($JFusionPlugin, 'filterUsername')) {
                $filteredUsername = $JFusionPlugin->filterUsername($username);
            }
        }
        //make sure the filtered username isn't empty
        $username = (!empty($filteredUsername)) ? $filteredUsername : $username;
        //define which characters which Joomla forbids in usernames
        $trans = array('&#60;' => '_', '&lt;' => '_', '&#62;' => '_', '&gt;' => '_', '&#34;' => '_', '&quot;' => '_', '&#39;' => '_', '&#37;' => '_', '&#59;' => '_', '&#40;' => '_', '&#41;' => '_', '&amp;' => '_', '&#38;' => '_', '<' => '_', '>' => '_', '"' => '_', '\'' => '_', '%' => '_', ';' => '_', '(' => '_', ')' => '_', '&' => '_');
        //remove forbidden characters for the username
        $username = strtr($username, $trans);
        //make sure the username is at least 2 characters long
        while (strlen($username) < 2) {
            $username.= '_';
        }
        return $username;
    }

    /**
     * Function that updates username
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     * @param string $jname         jname
     *
     * @return string updates are passed on into the $status array
     */
    public static function updateUsername($userinfo, &$existinguser, &$status, $jname)
    {
        //generate the filtered integration username
        $db = JFusionFactory::getDatabase($jname);
        $username_clean = JFusionJplugin::filterUsername($userinfo->username, $jname);
        $status['debug'][] = JText::_('USERNAME') . ': ' . $userinfo->username . ' -> ' . JText::_('FILTERED_USERNAME') . ':' . $username_clean;
        $query = 'UPDATE #__users SET username =' . $db->Quote($username_clean) . 'WHERE id =' . $existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            //update failed, return error
            $status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $db->stderr();
        } else {
            $status['debug'][] = JText::_('USERNAME_UPDATE') . ': ' . $username_clean;
        }
        if ($jname == 'joomla_int') {
            //update the lookup table
            $query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $existinguser->userid . ', ' . $db->Quote($userinfo->username) . ')';
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $db->stderr();
            } else {
                $status['debug'][] = JText::_('USERNAME_UPDATE') . ': ' . $username_clean;
            }
        }
    }

    /**
     * Function that creates a new user account
     *
     * @param object $userinfo Object containing the new userinfo
     * @param array  &$status  Array containing the errors and result of the function
     * @param string $jname    jname
     *
     * @return string updates are passed on into the $status array
     */
    public static function createUser($userinfo, &$status, $jname)
    {
        $usergroups = JFusionFunction::getCorrectUserGroups($jname,$userinfo);
        //get the default user group and determine if we are using simple or advanced
        //check to make sure that if using the advanced group mode, $userinfo->group_id exists
        if (empty($usergroups)) {
            $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING');
        } else {
            //load the database
            $db = JFusionFactory::getDatabase($jname);
            //joomla does not allow duplicate email addresses, check to see if the email is unique
            $query = 'SELECT id as userid, username, email from #__users WHERE email =' . $db->Quote($userinfo->email);
            $db->setQuery($query);
            $existinguser = $db->loadObject();
            if (empty($existinguser)) {
                //apply username filtering
                $username_clean = JFusionJplugin::filterUsername($userinfo->username, $jname);
                //now we need to make sure the username is unique in Joomla
                $db->setQuery('SELECT id FROM #__users WHERE username=' . $db->Quote($username_clean));
                while ($db->loadResult()) {
                    $username_clean.= '_';
                    $db->setQuery('SELECT id FROM #__users WHERE username=' . $db->Quote($username_clean));
                }
                $status['debug'][] = JText::_('USERNAME') . ':' . $userinfo->username . ' ' . JText::_('FILTERED_USERNAME') . ':' . $username_clean;
                //create a Joomla password hash if password_clear is available
                if (!empty($userinfo->password_clear)) {
	                jimport( 'joomla.user.helper' );
                    $userinfo->password_salt = JUserHelper::genRandomPassword(32);
                    $userinfo->password = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt);
                    $password = $userinfo->password . ':' . $userinfo->password_salt;
                } else {
                    //if password_clear is not available, store hashed password as is and also store the salt if present
                    if (isset($userinfo->password_salt)) {
                        $password = $userinfo->password . ':' . $userinfo->password_salt;
                    } else {
                        $password = $userinfo->password;
                    }
                }
                $instance = new JUser();
                $instance->set('name', $userinfo->name);
                $instance->set('username', $username_clean);
                $instance->set('password', $password);
                $instance->set('email', $userinfo->email);
                $instance->set('block', $userinfo->block);
                $instance->set('activation', $userinfo->activation);
                $instance->set('sendEmail', 0);
                //find out what usergroup the new user should have
                //the $userinfo object was probably reconstructed in the user plugin and autoregister = 1
                $isadmin = false;
                if (isset($usergroups[0])) {
                    if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                        $isadmin = (in_array ( 7 , $usergroups,true ) || in_array ( 8 , $usergroups,true )) ? true : false;
                    } else {
                        $isadmin = ($usergroups[0] == 24 || $usergroups[0] == 25) ? true : false;
                    }
                } else {
                    if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                        $usergroups = array(2);
                    } else {
                        $usergroups = array(18);
                    }
                }

                //work around the issue where joomla will not allow the creation of an admin or super admin if the logged in user is not a super admin
                if ($isadmin && $jname == 'joomla_int') {
                    if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                        $usergroups = array(2);
                    } else {
                        $usergroups = array(18);
                    }
                }

                if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                    $instance->set('usertype', 'deprecated');
                    $instance->set('groups', $usergroups);
                } else {
                    $usergroup = JFusionJplugin::getUsergroupName($jname,$usergroups[0]);
                    $instance->set('usertype', $usergroup);
                    $instance->set('gid', $usergroups[0]);
                }
                if ($jname == 'joomla_int') {
                    //store the username passed into this to prevent the user plugin from attempting to recreate users
                    $instance->set('original_username', $userinfo->username);
                    // save the user
                    if (!$instance->save(false)) {
                        //report the error
                        $status['error'] = $instance->getError();
                        return $status;
                    } else {
                        $createdUser = $instance->getProperties();
                        $createdUser = (object)$createdUser;
                        //update the user's group to the correct group if they are an admin
                        if ($isadmin) {
                            $createdUser->userid = $createdUser->id;
                            JFusionJplugin::updateUsergroup($userinfo, $createdUser, $status, $jname, false);
                        }
                        //create a new entry in the lookup table
                        //if the credentialed username is available (from the auth plugin), store it; otherwise store the $userinfo username
                        $username = (!empty($userinfo->credentialed_username)) ? $userinfo->credentialed_username : $userinfo->username;
                        $query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . $createdUser->id . ', ' . $db->Quote($username) . ')';
                        $db->setQuery($query);
                        if (!$db->query()) {
                            JError::raiseWarning(0, $db->stderr());
                        }
                    }
                } else {
                    // joomla_ext
                    // convert the Joomla userobject to a std object
                    $user = $instance->getProperties();
                    // get rid of internal properties
                    unset($user['password_clear']);
                    unset($user['aid']);
                    unset($user['guest']);
                    // set the creation time and last access time
                    $user['registerDate'] = date('Y-m-d H:i:s', time());
                    $user = (object)$user;
                    $user->id = null;
                    if (!$db->insertObject('#__users', $user, 'id')) {
                        //return the error
                        $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                        return $status;
                    }

                    if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                        foreach ($usergroups as $group) {
                            $query = 'INSERT INTO #__user_usergroup_map (group_id,user_id) VALUES (' . $group . ',' . $user->id . ')';
                            $db->setQuery($query);
                            if (!$db->query()) {
                                $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                            }
                        }
                    } else {
                        //add the user to the core_acl_aro
                        $acl = array();
                        $acl['section_value'] = 'users';
                        $acl['value'] = $user->id;
                        $acl['order_value'] = 0;
                        $acl['name'] = $userinfo->name;
                        $acl['hidden'] = 0;
                        $acl = (object)$acl;
                        $acl->id = null;
                        if (!$db->insertObject('#__core_acl_aro', $acl, 'id')) {
                            //return the error
                            $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                            return $status;
                        }
                        // and finally add the user to the core_acl_groups_aro_map
                        $query = 'INSERT INTO #__core_acl_groups_aro_map (group_id, aro_id) VALUES (' . $usergroups[0] . ',' . $acl->id . ')';
                        $db->setQuery($query);
                        if (!$db->query()) {
                            $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
                            return $status;
                        }
                    }
                }
                //check to see if the user exists now
                $joomla_user = JFusionJplugin::getUser($userinfo, $jname);
                if ($joomla_user) {
                    //report back success
                    $status['userinfo'] = $joomla_user;
                    $status['debug'][] = JText::_('USER_CREATION');
                } else {
                    $status['error'] = JText::_('COULD_NOT_CREATE_USER');
                }
            } else {
                //Joomla does not allow duplicate emails report error
                $status['debug'][] = JText::_('USERNAME') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->username . ' -> ' . $userinfo->username;
                $status['error'] = JText::_('EMAIL_CONFLICT') . '. UserID: ' . $existinguser->userid . ' JFusionPlugin: ' . $jname;
                $status['userinfo'] = $existinguser;
            }
        }
        return $status;
    }

    /**
     * Updates or creates a user for the integrated software. This allows JFusion to have external software as slave for user management
     *
     * @param object $userinfo  contains the userinfo
     * @param int    $overwrite determines if the userinfo can be overwritten
     * @param string $jname     jname
     *
     * @return array result Array containing the result of the user update
     */
    public static function updateUser($userinfo, $overwrite, $jname)
    {
        // Initialise some variables
        $params = JFusionFactory::getParams($jname);
        $db = JFusionFactory::getDatabase($jname);
        $update_block = $params->get('update_block');
        $update_activation = $params->get('update_activation');
        $update_email = $params->get('update_email');
        $status = array('error' => array(),'debug' => array());
        //check to see if a valid $userinfo object was passed on
        if (!is_object($userinfo)) {
            $status['error'][] = JText::_('NO_USER_DATA_FOUND');
        } else {
            //check to see if user exists
            $existinguser = JFusionJplugin::getUser($userinfo, $jname);
            if (!empty($existinguser)) {
                $changed = false;
                //a matching user has been found
                $status['debug'][] = JText::_('USER_DATA_FOUND');
                // email update?
                if (strtolower($existinguser->email) != strtolower($userinfo->email)) {
                    $status['debug'][] = JText::_('EMAIL_CONFLICT');
                    if ($update_email || $overwrite) {
                        $status['debug'][] = JText::_('EMAIL_CONFLICT_OVERWITE_ENABLED');
                        JFusionJplugin::updateEmail($userinfo, $existinguser, $status, $jname);
                        $changed = true;
                    } else {
                        //return a email conflict
                        $status['debug'][] = JText::_('EMAIL_CONFLICT_OVERWITE_DISABLED');
                        $status['error'][] = JText::_('EMAIL') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
                        $status['userinfo'] = $existinguser;
                        return $status;
                    }
                }
                // password update ?
                if (!empty($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
                    //if not salt set, update the password
                    $existinguser->password_clear = $userinfo->password_clear;
                    //check if the password needs to be updated
                    $model = JFusionFactory::getAuth($jname);
                    $testcrypt = $model->generateEncryptedPassword($existinguser);
                    //if the passwords are not the same or if Joomla salt has inherited a colon which will confuse Joomla without JFusion; generate a new password hash
                    if ($testcrypt != $existinguser->password || strpos($existinguser->password_salt, ':') !== false) {
                        JFusionJplugin::updatePassword($userinfo, $existinguser, $status, $jname);
                        $changed = true;
                    } else {
                        $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_VALID');
                    }
                } else {
                    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
                }
                //block status update?
                if ($existinguser->block != $userinfo->block) {
                    if ($update_block || $overwrite) {
                        if ($userinfo->block) {
                            //block the user
                            JFusionJplugin::blockUser($userinfo, $existinguser, $status, $jname);
                            $changed = true;
                        } else {
                            //unblock the user
                            JFusionJplugin::unblockUser($userinfo, $existinguser, $status, $jname);
                            $changed = true;
                        }
                    } else {
                        //return a debug to inform we skipped this step
                        $status['debug'][] = JText::_('SKIPPED_BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
                    }
                }
                //activation status update?
                if ($existinguser->activation != $userinfo->activation) {
                    if ($update_activation || $overwrite) {
                        if ($userinfo->activation) {
                            //inactive the user
                            JFusionJplugin::inactivateUser($userinfo, $existinguser, $status, $jname);
                            $changed = true;
                        } else {
                            //activate the user
                            JFusionJplugin::activateUser($userinfo, $existinguser, $status, $jname);
                            $changed = true;
                        }
                    } else {
                        //return a debug to inform we skipped this step
                        $status['debug'][] = JText::_('SKIPPED_EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
                    }
                }
                //check for advanced usergroup sync
                $master = JFusionFunction::getMaster();
                if (!$userinfo->block && empty($userinfo->activation) && $master->name != $jname) {
                    if (JFusionFunction::isAdvancedUsergroupMode($jname)) {
                        $usergroups = JFusionFunction::getCorrectUserGroups($jname,$userinfo);

                        if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                            if (!JFusionFunction::compareUserGroups($existinguser,$usergroups)) {
                                JFusionJplugin::updateUsergroup($userinfo, $existinguser, $status, $jname);
                                $changed = true;
                            } else {
                                $status['debug'][] = JText::_('SKIPPED_GROUP_UPDATE') . ':' . JText::_('GROUP_VALID');
                            }
                        } else if (isset($usergroups[0])) {
                            $correct_usergroup = $usergroups[0];
                            //make sure that ACL has not been corrupted
                            $correct_groupname = JFusionJplugin::getUsergroupName($jname,$correct_usergroup);
                            $query = 'SELECT group_id FROM #__core_acl_aro as a INNER JOIN #__core_acl_groupsaro__map as b ON a.id = b.aro_id WHERE a.value = ' . $existinguser->userid;
                            $db->setQuery($query);
                            $acl_group_id = $db->loadResult();

                            if ($correct_usergroup != $existinguser->group_id || $correct_groupname != $existinguser->group_name || $correct_usergroup != $acl_group_id) {
                                JFusionJplugin::updateUsergroup($userinfo, $existinguser, $status, $jname);
                                $changed = true;
                            } else {
                                $status['debug'][] = JText::_('SKIPPED_GROUP_UPDATE') . ':' . JText::_('GROUP_VALID');
                            }
                        }
                    }
                }

                //Update the user language in the one existing from an other plugin
                if (!empty($userinfo->language) && !empty($existinguser->language) && $userinfo->language != $existinguser->language) {
                    JFusionJplugin::updateUserLanguage($userinfo, $existinguser, $status, $jname);
                    $existinguser->language = $userinfo->language;
                    $changed = true;
                } else {
                    //return a debug to inform we skipped this step
                    $status['debug'][] = JText::_('LANGUAGE_NOT_UPDATED');
                }

                if (empty($status['error'])) {
                    if ($changed == true) {
                        $status['action'] = 'updated';
                        $status['userinfo'] = JFusionJplugin::getUser($userinfo, $jname);
                    } else {
                        $status['action'] = 'unchanged';
                        $status['userinfo'] = $existinguser;
                    }
                }
            } else {
                $status['debug'][] = JText::_('NO_USER_FOUND_CREATING_ONE');
                JFusionJplugin::createUser($userinfo, $status, $jname);
                if (empty($status['error'])) {
                    $status['action'] = 'created';
                }
            }
        }
        return $status;
    }

    /**
     * Function that updates usergroup
     *
     * @param object $userinfo          Object containing the new userinfo
     * @param object &$existinguser     Object containing the old userinfo
     * @param array  &$status           Array containing the errors and result of the function
     * @param string $jname             jname
     * @param bool $fire_user_plugins needs more detail
     *
     * @return string updates are passed on into the $status array
     */
    public static function updateUsergroup($userinfo, &$existinguser, &$status, $jname, $fire_user_plugins = true)
    {
        $usergroups = JFusionFunction::getCorrectUserGroups($jname,$userinfo);
        //make sure the group exists
        if (empty($usergroups)) {
	        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST');
        } else {
            $db = JFusionFactory::getDatabase($jname);
            $params = JFusionFactory::getParams($jname);
            $dispatcher = JDispatcher::getInstance();

            //Fire the user plugin functions for joomla_int
            if ($jname == 'joomla_int' && $fire_user_plugins) {
                // Get the old user
                $old = new JUser($existinguser->userid);
                //Fire the onBeforeStoreUser event.
                JPluginHelper::importPlugin('user');
                $dispatcher->trigger('onBeforeStoreUser', array($old->getProperties(), false));
            }

            if(JFusionFunction::isJoomlaVersion('1.6',$jname)) {
                jimport('joomla.user.helper');
                $query = 'DELETE FROM #__user_usergroup_map WHERE user_id = ' . $db->Quote($existinguser->userid);
                $db->setQuery($query);
                if (!$db->query()) {
                    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $db->stderr();
                } else {
                    foreach ($usergroups as $key => $group) {
                        $temp = new stdClass;
                        $temp->user_id = $existinguser->userid;
                        $temp->group_id = $group;
                        if (!$db->insertObject('#__user_usergroup_map', $temp)) {
                            //return the error
                            $status['error'] = JText::_('USER_CREATION_ERROR') . ': ' . $db->stderr();
                            return $status;
                        }
                    }
                    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode(',', $existinguser->groups) . ' -> ' .implode(',', $usergroups);
                    //Fire the user plugin functions for joomla_int
                    if ($jname == 'joomla_int' && $fire_user_plugins) {
                        //Fire the onAfterStoreUser event
                        $updated = new JUser($existinguser->userid);
                        $dispatcher->trigger('onAfterStoreUser', array($updated->getProperties(), false, true, ''));
                    }
                }
            } else {
                $gid = $usergroups[0];
                $usertype = JFusionJplugin::getUsergroupName($jname,$gid);
                if (!empty($gid) && !empty($usertype ) ) {
                    //update the user table
                    $query = 'UPDATE #__users SET usertype = '.$db->Quote($usertype).' , gid = '.$gid.'  WHERE id = '.$existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $db->stderr();
                    } else {
                        //we have to update the acl table
                        $query = 'SELECT id FROM #__core_acl_aro WHERE value = ' . $existinguser->userid;
                        $db->setQuery($query);
                        $aro_id = $db->loadResult();
                        if (!empty($aro_id)) {
                            $query = 'UPDATE #__core_acl_groups_aro_map SET group_id = '.$gid.' WHERE aro_id = '.$aro_id;
                            $db->setQuery($query);
                            if (!$db->query()) {
                                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $db->stderr();
                                //update to acl table failed, attempt to revert changes to user table
                                $query = 'UPDATE #__users SET usertype = '.$db->Quote($existinguser->group_name).' , gid = '.$existinguser->group_id.' WHERE id = '.$existinguser->userid;
                                $db->setQuery($query);
                                if (!$db->query()) {
                                    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $db->stderr();
                                }
                            } else {
                                $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . $existinguser->group_id . ' -> ' . $gid;
                                //Fire the user plugin functions for joomla_int
                                if ($jname == 'joomla_int' && $fire_user_plugins) {
                                    // Fire the onAfterStoreUser event
                                    $updated = new JUser($existinguser->userid);
                                    $dispatcher->trigger('onAfterStoreUser', array($updated->getProperties(), false, true, ''));
                                }
                            }
                        } else {
                            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $db->stderr();
                        }
                    }
                }
            }
        }
        return $status;
    }
    /************************************************
    * Functions For JFusion Who's Online Module
    ***********************************************/

    /**
     * Returns a query to find online users
     * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
     *
     * @param int $limit integer to use as a limiter for the number of results returned
     *
     * @return string online user query
     */
    public static function getOnlineUserQuery($limit)
    {
        $limiter = (!empty($limit)) ? "LIMIT 0,$limit" : '';
        $query = 'SELECT DISTINCT u.id AS userid, u.username, u.name, u.email' . ' FROM #__users AS u INNER JOIN #__session AS s' . ' ON u.id = s.userid' . ' WHERE s.client_id = 0' . ' AND s.guest = 0 ' . $limiter;
        return $query;
    }

    /**
     * Returns number of guests
     *
     * @return int
     */
    public static function getNumberOnlineGuests()
    {
        $db = JFactory::getDBO();
        $query = 'SELECT COUNT(*) FROM #__session WHERE guest = 1 AND usertype = \'\' AND client_id = 0';
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * Returns number of logged in users
     *
     * @return int
     */
    public static function getNumberOnlineMembers()
    {
        $db = JFactory::getDBO();
        $query = 'SELECT COUNT(DISTINCT userid) AS c FROM #__session WHERE guest = 0 AND client_id = 0';
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * Update the language front end param in the account of the user if this one changes it
     * NORMALLY THE LANGUAGE SELECTION AND CHANGEMENT FOR JOOMLA IS PROVIDED BY THIRD PARTY LIKE JOOMFISH
     *
     * @param string $jname jname
     * @param object $userinfo userinfo
     *
     * @return array status
     */
    public static function setLanguageFrontEnd($jname, $userinfo)
    {
        $status = array('error' => array(),'debug' => array());
        $existinguser = (isset($userinfo)) ? JFusionJplugin::getUser($userinfo, $jname) : null;
        // If the user is connected we change his account parameter in function of the language front end
        if ($existinguser) {
            $JLang = JFactory::getLanguage();
            $userinfo->language = $JLang->getTag();
            JFusionJplugin::updateUserLanguage($userinfo, $existinguser, $status, $jname);
        } else {
            $status['debug'] = JText::_('NO_USER_DATA_FOUND');
        }
        return $status;
    }

    /**
     * Update the language user in his account when he logs in Joomla or
     * when the language is changed in the frontend
     *
     * @see JFusionJplugin::updateUser
     * @see JFusionJplugin::setLanguageFrontEnd
     *
	 * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     * @param string $jname			current plugin name
     */
    public static function updateUserLanguage($userinfo, &$existinguser, &$status, $jname)
    {
    	/**
	     * @TODO joomla 1.5/1.6 if we are talking to external joomla since joomla 1.5 store params in json
	     */
        $db = JFusionFactory::getDatabase($jname);
        $params = new JParameter($existinguser->params);
        $params->set('language', $userinfo->language);
        $query = 'UPDATE #__users SET params =' . $db->Quote($params->toString()) . ' WHERE id =' . $existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('LANGUAGE_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('LANGUAGE_UPDATE') . ' ' . $existinguser->language;
        }
    }
}