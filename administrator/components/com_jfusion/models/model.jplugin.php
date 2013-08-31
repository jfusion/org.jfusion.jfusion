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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
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
        $curl_options = array();
        $status = array('error' => array(),'debug' => array());
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
        $jnodeid = strtolower(JFactory::getApplication()->input->get('jnodeid'));
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
                $status['error'][] = JText::_('CURL_LOGINTYPE_NOT_SUPPORTED');
                break;
            case "brute_force":
        	   $curl_options['brute_force'] = $type;
                $status = JFusionCurl::RemoteLogin($curl_options);
        	   break;
            default:
                $status = JFusionCurl::RemoteLogin($curl_options);
        }
        $status['debug'][] = JText::_('CURL_LOGINTYPE').'='.$type;
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
        $curl_options = array();
        $status = array('error' => array(),'debug' => array());

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
        $jnodeid = strtolower(JFactory::getApplication()->input->get('jnodeid'));
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
}