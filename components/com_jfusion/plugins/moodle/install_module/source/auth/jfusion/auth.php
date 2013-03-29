<?php

/**
 * @author Henk Wevers
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package moodle multiauth
 *
 * Authentication Plugin: Jfusion DSSO support
 *
 * Not really an authentication plugin, but a way to login/logout to Joomla (via JFusions user frontcontroller).
 * Jfusion will trigger the onloginuser and onlogoutuser routines in jfusions user plugin and make jfusion login/out of all supported software
 *
 * When a user logs in on Moodle and the user is not registered on Moodle then the DSSO mechanism will find
 * the user if registered elsewhere in the network and create the user in Moodle
 * The system works slightly different for JFusion 1.x and jFusion 2.x, but the code here acommodate both versions
 *
 * When the user is registered on Moodle this module will send a login/logout request to Joomla WITH its nodeID (see jfusion doc)
 * This will prevent JFusion calling back and thus creating an endless loop
 * on the other hand, when the login on Moodle contains a nodeid and/or jhost=true variable this module will not
 * call JFusion at all. Jfusion has already taken care of loggingin/out other nodes and hosts.
 *
 * IMPORTANT: THIS PLUGIN MUST BE THE LAST ONE OF ALL ENABLED AUTH PLUGINS
 *
 * created 26-06-2010 Henk Wevers
 */



if (!defined('MOODLE_INTERNAL')) {
	die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
/**
 * @ignore
 * @var $CFG object
 */
require_once($CFG->libdir.'/authlib.php');


/**
 *
 */
class JText {

    /**
     * @static
     * @param $string
     * @param bool $jsSafe
     * @return mixed
     */
    static public function _($string, $jsSafe = false)
	{
		return ($string);
	}

}

/**
 *
 */
class DualLogin {

    /**
     * @param $curl_options
     * @return array|string
     */
    function login($curl_options){
		$status = array();
		$helper = new JFusionCurl;
		$status = $helper->RemoteLogin($curl_options);
		unset($helper);
		return $status;
	}

    /**
     * @param $curl_options
     * @return array|string
     */
    function logout($curl_options){
		$status = array();
		$helper = new JFusionCurl;
		/**
		 * @TODO to fix: For info, with J! 1.6 there is problem with a form token and it's not provided to the post data
		 */
		// RemoteLogoutUrl not work but RemoteLogout() work. 
		$status= $helper->RemoteLogoutUrl($curl_options);
		unset($helper);
		return $status;
	}
}




/**
 * Plugin for jfusion support.
 */
class auth_plugin_jfusion extends auth_plugin_base {

    /**
     * @var object
     */
    var $config;

	/**
	 * Constructor.
	 */
	function auth_plugin_jfusion() {
		$this->authtype = 'jfusion';
		$this->config = get_config('auth/jfusion');

	}

	/**
	 * Returns true if the username and password work and false if they are
	 * wrong or don't exist.
	 *
	 * THIS ROUTINE IS ONLY CALLED WHEN OTHER AUTH PLUGINS COULD NOT VALIDATE THE USER
	 * OR FOUND A CORRECT USER WITH A WRONG PASSWORD. IN OTHER WORDS THE USER DOES NOT EXIST
	 * WE USE THIS TO CALL JFUSION TO SEE IF THE USER IS KNOWN ELSEWHERE IN THE NETWORK
	 * JFUSION WILL TAKE CARE OF CREATING THE USER AN LOGGING IN AFTERWARDS
	 * @param string $username The username
	 * @param string $password The password
	 * @return bool Authentication success or failure.
	 */
	function user_login($username, $password) {
		// if we come here, no active authentication methods succeeded (or returned false) so the user does not exist
		// as far as Moodle is concerned.
		// if Moodle is master, this is it, return
		global $CFG;
		// back off if we have not enabled the plugin
		if ($this->config->jf_enabled != 1) {
			return false;
		}
		if ($this->config->jf_ismaster) {
			return false;
		}
		$local_login = empty($_REQUEST['jnodeid']);
		if (!$local_login)
		{
			return false;
		}
		// So Moodle is slave and we have a local login, just call Joomla, with nodeid NOT set
		$this->LoginJoomla($username, $password, false);
		// now test if we have a valid user, the host should have created one
        $user = get_record('user', 'username', $username, 'mnethostid', $CFG->mnet_localhost_id);
		if ($user) {
			$valid = validate_internal_user_password($user, $password);
			if ($valid){
				redirect($CFG->wwwroot);
				return true;
			}
		}
		return false;
	}

	/**
	 * No password updates.
     *
     * @param string $user
     * @param string $newpassword
     *
     * @return bool
	 */
	function user_update_password($user, $newpassword) {
		return false;
	}

	/**
	 * No external data sync.
	 *
	 * @return bool
	 */
	function is_internal() {
		//we do not know if it was internal or external originally
		return true;
	}

	/**
	 * No changing of password.
	 *
	 * @return bool
	 */
	function can_change_password() {
		return false;
	}

	/**
	 * No password resetting.
     *
     * @return bool
	 */
	function can_reset_password() {
		return false;
	}

	/**
	 * This function is called when a user is authenticated by another plugin
	 * We use it to start a login procedure in case we have a non JFusion login on Moodle
     *
     * @param string $user
     * @param string $username
     * @param string $password
     *
     * @return bool
	 */

	function user_authenticated_hook($user, $username, $password){

		global $CFG;
		//  global session;
		//just testing  config comes later

		$local_login = empty($_REQUEST['jnodeid']);
		if ($local_login) {
            $this->LoginJoomla($username, $password, true);
		}
		return false;
	}

	/**
	 * This function is called when a user logs out
	 * We use it to start a logout procedure in case we have a non JFusion logout on Moodle
     *
     * @return bool
	 */
	function prelogout_hook(){
		global $CFG;
		//  global session;

		$local_login = empty($_REQUEST['jnodeid']);
		if (!$local_login)
		{
			return false;
		}
		// abort the Joomla logoutroutine if we have switched off the SSO routines


		$params_joomlafullpath = $this->config->jf_fullpath;
		$params_joomlabaseurl = $this->config->jf_baseurl;
		$params_joomlaactive = $this->config->jf_enabled;
		$params_logoutpath = $this->config->jf_logoutpath;
		$params_formid = $this->config->jf_formid;
		$params_relpath = $this->config->jf_relpath;
		$params_cookiedomain = $this->config->jf_cookiedomain;
		$params_cookiepath = $this->config->jf_cookiepath;
		$params_expires = $this->config->jf_expires;
		$params_input_username_id = $this->config->jf_username_id;
		$params_input_password_id = $this->config->jf_password_id;
		$params_cookie_secure = $this->config->jf_cookie_secure;
		$params_cookie_httponly = $this->config->jf_cookie_httponly;
		$params_leavealone = $this->config->jf_leavealone;
		$params_verifyhost = $this->config->jf_verifyhost;
		$params_postfields = $this->config->jf_postfields;
		
		if ($params_joomlaactive == '0')
		{
			return false;
		}
		$curl_options=array();

		#prevent user error by preventing a heading forward slash
		ltrim($params_logoutpath,'/');

		#prevent user error by not supplying trailing forward slash
		if (substr($params_logoutpath,-1) != '/') {
			$params_logoutpath = $params_logoutpath.'/';
		}
		if (substr($params_joomlabaseurl,-1) != '/')
		{
			$params_joomlabaseurl = $params_joomlabaseurl.'/';
		}
		if (substr($params_joomlafullpath,-1) != '/')
		{
			$params_joomlafullpath = $params_joomlafullpath.'/';
		}

		define('_JEXEC','Yeah_I_know');
		require_once($params_joomlafullpath.'administrator/components/com_jfusion/models/model.curl.php');
		$LoginLogout = new DualLogin();
		$curl_options['post_url']          = $params_joomlabaseurl.$params_logoutpath;
		$curl_options['postfields']        = $params_postfields;
		$curl_options['formid']            = $params_formid;
		$curl_options['integrationtype']   = 0;
		$curl_options['relpath']           = $params_relpath;
		$curl_options['hidden']            = '1';
		$curl_options['buttons']           = '1';
		$curl_options['override']          = '';
		$curl_options['cookiedomain']      = $params_cookiedomain;
		$curl_options['cookiepath']        = $params_cookiepath;
		$curl_options['expires']           = $params_expires;
		$curl_options['input_username_id'] = $params_input_username_id;
		$curl_options['input_password_id'] = $params_input_password_id;
		$curl_options['secure']            = $params_cookie_secure;
		$curl_options['httponly']          = $params_cookie_httponly;
		$curl_options['httponly']          = $params_cookie_httponly;
		$curl_options['leavealone']        = $params_leavealone;
		$curl_options['brute_force']       = 'brute_force'; // needed to avoid the dreadfull Joomla problem -- your session has expired --
		$curl_options['verifyhost']        = $params_verifyhost;
		// to prevent endless loops on systems where there are multiple places where a user can login
		// we post an unique ID for the initiating software so we can make a difference between
		// a user logging in or another jFusion installation, or even another system with reverse dual login code.
		// We always use the source url of the initializing system, here the source_url as defined in the joomla_int
		// plugin. This is totally transparent for the the webmaster. No additional setup is needed
		$Host_source_url = $CFG->wwwroot;
		$my_ID = rtrim(parse_url($Host_source_url,PHP_URL_HOST).parse_url($Host_source_url,PHP_URL_PATH),'/');
		$curl_options['jnodeid'] = $my_ID;
		$curl_options['logout'] = '1';
		$status = $LoginLogout->login($curl_options);  // form logout
	
	
		unset($LoginLogout);
		if (!empty($status['error']))
		{
			$message= 'Fatal JFusion Dual logout Error : statusdump: '.print_r($status,true) ;
			return false;
		}
        return true;

	}

	/**
	 * Prints a form for configuring this authentication plugin.
	 *
	 * This function is called from admin/auth.php, and outputs a full page with
	 * a form for configuring this plugin.
	 *
	 * @param array $config An object containing all the data for this page.
     * @param string $err
     * @param string $user_fields
	 */
	function config_form($config, $err, $user_fields) {

		include 'config.phtml';
	}

	/**
	 * Processes and stores configuration data for this authentication plugin.
     *
     * @param object $config
	 */
	function process_config($config) {
		set_config('jf_enabled',          $config->jf_enabled,            'auth/jfusion');
		set_config('jf_ismaster',         $config->jf_ismaster,           'auth/jfusion');
		set_config('jf_fullpath',         $config->jf_fullpath,           'auth/jfusion');
		set_config('jf_baseurl',          $config->jf_baseurl,            'auth/jfusion');
		set_config('jf_loginpath',        $config->jf_loginpath,          'auth/jfusion');
		set_config('jf_logoutpath',       $config->jf_logoutpath,         'auth/jfusion');
		set_config('jf_formid',           $config->jf_formid,             'auth/jfusion');
		set_config('jf_relpath',          $config->jf_relpath,            'auth/jfusion');
		set_config('jf_cookiedomain',     $config->jf_cookiedomain,       'auth/jfusion');
		set_config('jf_cookiepath',       $config->jf_cookiepath,         'auth/jfusion');
		set_config('jf_username_id',      $config->jf_username_id,        'auth/jfusion');
		set_config('jf_password_id',      $config->jf_password_id,        'auth/jfusion');
		set_config('jf_cookie_secure',    $config->jf_cookie_secure,      'auth/jfusion');
		set_config('jf_cookie_httponly',  $config->jf_cookie_httponly,    'auth/jfusion');
		set_config('jf_veryfyhost',       $config->jf_verifyhost,         'auth/jfusion');
		set_config('jf_leavealone',       $config->jf_leavealone,         'auth/jfusion');
		set_config('jf_expires',          $config->jf_expires,            'auth/jfusion');
		set_config('jf_postfields',       $config->jf_postfields,         'auth/jfusion');
		
	}

	/***
	 * Logs into Joomla using Curl
     *
     * @param string $username
     * @param string $password
     * @param string $jnodeid
     *
     * @return bool
     */
	function LoginJoomla($username, $password, $jnodeid){
		global $CFG;

		$params_joomlafullpath = $this->config->jf_fullpath;
		$params_joomlabaseurl = $this->config->jf_baseurl;
		$params_joomlaactive = $this->config->jf_enabled;
		$params_loginpath = $this->config->jf_loginpath;
		$params_formid = $this->config->jf_formid;
		$params_relpath = $this->config->jf_relpath;
		$params_cookiedomain = $this->config->jf_cookiedomain;
		$params_cookiepath = $this->config->jf_cookiepath;
		$params_expires = $this->config->jf_expires;
		$params_input_username_id = $this->config->jf_username_id;
		$params_input_password_id = $this->config->jf_password_id;
		$params_cookie_secure = $this->config->jf_cookie_secure;
		$params_cookie_httponly = $this->config->jf_cookie_httponly;
		$params_leavealone = $this->config->jf_leavealone;
		$params_verifyhost = $this->config->jf_verifyhost;

		$curl_options=array();

		#prevent user error by preventing a heading forward slash
		ltrim($params_loginpath,'/');
		#prevent user error by not supplying trailing forward slash
		if (substr($params_loginpath,-1) != '/') {
			$params_loginpath = $params_loginpath.'/';
		}
		if (substr($params_joomlabaseurl,-1) != '/')
		{
			$params_joomlabaseurl = $params_joomlabaseurl.'/';
		}
		if (substr($params_joomlafullpath,-1) != '/')
		{
			$params_joomlafullpath = $params_joomlafullpath.'/';
		}

		// abort the Joomla login routine if we have switched off the SSO routines
		// just to prevent programming errors
		// This should have been done earlier in the code

		if ($params_joomlaactive != '0')
		{
            define('_JEXEC','Yeah_I_know');
            require_once($params_joomlafullpath.'administrator/components/com_jfusion/models/model.curl.php');
            //    require_once('DualLoginHelper.php');
            $LoginLogout = new DualLogin();
            $curl_options['username']          = $username;
            $curl_options['password']          = $password;
            $curl_options['post_url']          = $params_joomlabaseurl.$params_loginpath;
            $curl_options['formid']            = $params_formid;
            $curl_options['integrationtype']   = 0;
            $curl_options['relpath']           = $params_relpath;
            $curl_options['hidden']            = '1';
            $curl_options['buttons']           = '1';
            $curl_options['override']          = '';
            $curl_options['cookiedomain']      = $params_cookiedomain;
            $curl_options['cookiepath']        = $params_cookiepath;
            $curl_options['expires']           = $params_expires;
            $curl_options['input_username_id'] = $params_input_username_id;
            $curl_options['input_password_id'] = $params_input_password_id;
            $curl_options['secure']            = $params_cookie_secure;
            $curl_options['httponly']          = $params_cookie_httponly;
            $curl_options['httponly']          = $params_cookie_httponly;
            $curl_options['leavealone']        = $params_leavealone;
            $curl_options['brute_force']       = 'brute_force'; // needed to avoid the dreadfull Joomla problem -- your session has expired --
            $curl_options['verifyhost']        = $params_verifyhost;
            // to prevent endless loops on systems where there are multiple places where a user can login
            // we post an unique ID for the initiating software so we can make a difference between
            // a user logging in or another jFusion installation, or even another system with reverse dual login code.
            // We always use the source url of the initializing system, here the source_url as defined in the joomla_int
            // plugin. This is totally transparent for the the webmaster. No additional setup is needed
            if ($jnodeid){
                $Host_source_url = $CFG->wwwroot;
                $my_ID = rtrim(parse_url($Host_source_url,PHP_URL_HOST).parse_url($Host_source_url,PHP_URL_PATH),'/');
                $curl_options['jnodeid'] = $my_ID;
            }
            $status = $LoginLogout->login($curl_options);
            unset($LoginLogout);

            if (!empty($status['error']))
            {
                $message= 'Fatal JFusion Dual login Error : statusdump: '.print_r($status,true) ;
                //      $session->addError($message);
            }
		}
		return false;
	}
}