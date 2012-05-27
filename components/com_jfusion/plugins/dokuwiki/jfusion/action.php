<?php
/**
 * DokuWiki Plugin jfusion (Action Component)
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  JFusion Team <webmaster@jfusion.org>
 *
 * Adapted from Dokuwiki's own auth routines
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';
/**
 * action_plugin_jfusion class
 *
 * @category   JFusion
 * @package    Model
 * @subpackage action_plugin_jfusion
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class action_plugin_jfusion extends DokuWiki_Action_Plugin {

	var $session_save_handler = '';

    /**
     * @param $controller
     */
    function register(&$controller) {
       $controller->register_hook('AUTH_LOGIN_CHECK', 'BEFORE', $this, 'jfusion_login');
       $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'jfusion_logout');
    }

    /**
     * @param $event
     * @param $param
     */
    function jfusion_login(&$event, $param) {
        //do not use Dokuwiki's standard login method
        $event->preventDefault();

        $user = & $event->data['user'];
        $password = & $event->data['password'];
        $sticky = & $event->data['sticky'];
        $silent = & $event->data['silent'];

        $this->loginDokuwiki($user, $password, $sticky, $silent);
    }

    function loginDokuwiki($user, $password, $sticky, $silent) {
        global $USERINFO, $conf, $lang, $auth;

        if (!$auth) return false;

        $sticky ? $sticky = true : $sticky = false; //sanity check

        if(!empty($user)){
            //usual login
            if ($auth->checkPass($user,$password)){
                // make logininfo globally available
                $_SERVER['REMOTE_USER'] = $user;

                $pass = PMA_blowfish_encrypt($password,auth_cookiesalt());
                $USERINFO = $auth->getUserData($user);

                // set cookie
                $cookie = base64_encode($user).'|'.((int) $sticky).'|'.base64_encode($pass);
                $time = $sticky ? (time()+60*60*24*365) : 0; //one year
                if (version_compare(PHP_VERSION, '5.2.0', '>')) {
                    setcookie(DOKU_COOKIE,$cookie,$time,$conf['jfusion']['cookie_path'],$conf['jfusion']['cookie_domain'],($conf['securecookie'] && is_ssl()),true);
                }else{
                    setcookie(DOKU_COOKIE,$cookie,$time,$conf['jfusion']['cookie_path'],$conf['jfusion']['cookie_domain'],($conf['securecookie'] && is_ssl()));
                }
                // set session
                $_SESSION[DOKU_COOKIE]['auth']['user'] = $user;
                $_SESSION[DOKU_COOKIE]['auth']['pass'] = $pass;
                $_SESSION[DOKU_COOKIE]['auth']['buid'] = auth_browseruid();
                $_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;
                $_SESSION[DOKU_COOKIE]['auth']['time'] = time();

        if (!empty($conf['jfusion']['joomla'])) {
            $this->loginJoomla($user, $password, $sticky);
        }

                return true;
            }else{
                //invalid credentials - log off
                if(!$silent) msg($lang['badlogin'],-1);
                $this->logoutDokuwiki();
                return false;
            }
        }else{
            // read cookie information
            list($user,$sticky,$pass) = auth_getCookie();

            // get session info
            $session = $_SESSION[DOKU_COOKIE]['auth'];
            if($user && $pass){
                // we got a cookie - see if we can trust it
                if(isset($session) &&
                        $auth->useSessionCache($user) &&
                        ($session['time'] >= time()-$conf['auth_security_timeout']) &&
                        ($session['user'] == $user) &&
                        ($session['pass'] == $pass) &&  //still crypted
                        ($session['buid'] == auth_browseruid()) ){
                    // he has session, cookie and browser right - let him in
                    $_SERVER['REMOTE_USER'] = $user;
                    $USERINFO = $session['info']; //FIXME move all references to session
                    return true;
                }
                // no we don't trust it yet - recheck pass but silent
                $pass = PMA_blowfish_decrypt($pass,auth_cookiesalt());
                return $this->loginDokuwiki($user,$pass,$sticky,true);
            }
        }
        //just to be sure
        $this->logoutDokuwiki(true);
        return false;
    }

    function jfusion_logout(&$event, $param) {
        global $ACT;

        //sanitize $ACT
        $ACT = act_clean($ACT);

        if ($ACT == 'logout') {
            global $ID, $INFO;
            //do not use Dokuwiki's standard logout method
            $event->preventDefault();

            $lockedby = checklock($ID); //page still locked?
            if($lockedby == $_SERVER['REMOTE_USER']) {
                unlock($ID); //try to unlock
            }

            $this->logoutDokuwiki();

            // rebuild info array
            $INFO = pageinfo();
            act_redirect($ID,'login');
        }
    }

    function logoutDokuwiki($keepbc = false) {
        global $conf, $USERINFO, $auth;

        // do the logout stuff
        if(isset($_SESSION[DOKU_COOKIE]['auth']['user']))
            unset($_SESSION[DOKU_COOKIE]['auth']['user']);
        if(isset($_SESSION[DOKU_COOKIE]['auth']['pass']))
            unset($_SESSION[DOKU_COOKIE]['auth']['pass']);
        if(isset($_SESSION[DOKU_COOKIE]['auth']['info']))
            unset($_SESSION[DOKU_COOKIE]['auth']['info']);
        if(!$keepbc && isset($_SESSION[DOKU_COOKIE]['bc']))
            unset($_SESSION[DOKU_COOKIE]['bc']);
        if(isset($_SERVER['REMOTE_USER']))
            unset($_SERVER['REMOTE_USER']);
        $USERINFO=null; //FIXME

        if (version_compare(PHP_VERSION, '5.2.0', '>')) {
            setcookie(DOKU_COOKIE,'',time()-600000,$conf['jfusion']['cookie_path'],$conf['jfusion']['cookie_domain'],($conf['securecookie'] && is_ssl()),true);
        }else{
            setcookie(DOKU_COOKIE,'',time()-600000,$conf['jfusion']['cookie_path'],$conf['jfusion']['cookie_domain'],($conf['securecookie'] && is_ssl()));
        }

        if($auth && $auth->canDo('logout')){
            $auth->logOff();
        }

        if (!empty($conf['jfusion']['joomla'])) {
            $this->logoutJoomla();
        }
    }

    function startJoomla() {
    	$this->session_save_handler = ini_get('session.save_handler');
    	global $conf;
        if (!defined('_JEXEC')) {
            // trick joomla into thinking we're running through joomla
            define('_JEXEC', true);
            define('DS', DIRECTORY_SEPARATOR);
            define('JPATH_BASE', $conf['jfusion']['joomla_basepath']);
            // load joomla libraries
            require_once JPATH_BASE . DS . 'includes' . DS . 'defines.php';
			define('_JREQUEST_NO_CLEAN', true); // we dont want to clean variables as it can "commupt" them for some applications, it also clear any globals used...
        	require_once JPATH_BASE . DS . 'includes' . DS . 'framework.php';
//			include_once JPATH_LIBRARIES . DS . 'import.php'; //include not require, so we only get it if its there ...     
//	        require_once JPATH_LIBRARIES . DS . 'loader.php';

        	//load JFusion's libraries
        	require_once JPATH_BASE . DS . 'administrator' . DS . 'components' . DS . 'com_jfusion' . DS  . 'models' . DS . 'model.factory.php';
        	require_once JPATH_BASE . DS . 'administrator' . DS . 'components' . DS . 'com_jfusion' . DS  . 'models' . DS . 'model.jfusion.php';        	
        	
			if(JFusionFunction::isJoomlaVersion('1.6')) {
				spl_autoload_register(array('JLoader','load'));
        	} else {
        		spl_autoload_register('__autoload');
        	}
            jimport('joomla.base.object');
            jimport('joomla.factory');
            jimport('joomla.filter.filterinput');
            jimport('joomla.error.error');
            jimport('joomla.event.dispatcher');
            jimport('joomla.event.plugin');
            jimport('joomla.plugin.helper');
            jimport('joomla.utilities.arrayhelper');
            jimport('joomla.environment.uri');
            jimport('joomla.environment.request');
            jimport('joomla.user.user');
            jimport('joomla.html.parameter');
            jimport('joomla.version');            
            // JText cannot be loaded with jimport since it's not in a file called text.php but in methods
            JLoader::register('JText', JPATH_BASE . DS . 'libraries' . DS . 'joomla' . DS . 'methods.php');
            JLoader::register('JRoute', JPATH_BASE . DS . 'libraries' . DS . 'joomla' . DS . 'methods.php');
        } else {
            define('IN_JOOMLA', 1);
        }

        //set the cookie path to the correct setting
        if (version_compare(PHP_VERSION, '5.2.0', '>')) {
            session_set_cookie_params(0, '/', '', ($conf['securecookie'] && is_ssl()), true);
        } else {
            session_set_cookie_params(0, '/', '', ($conf['securecookie'] && is_ssl()));
        }

        $mainframe = JFactory::getApplication('site');
        $GLOBALS['mainframe'] = & $mainframe;
        return $mainframe;
    }

    function stopJoomla() {
        global $conf;
        //restore Dokuwiki's cookie settings
        if (version_compare(PHP_VERSION, '5.2.0', '>')) {
            session_set_cookie_params(0, DOKU_REL, '', ($conf['securecookie'] && is_ssl()), true);
        } else {
            session_set_cookie_params(0, DOKU_REL, '', ($conf['securecookie'] && is_ssl()));
        }
        ini_set('session.save_handler',$this->session_save_handler);
    }

    function loginJoomla($username, $password, $sticky) {
        global $JFusionActive, $conf;
        if (empty($JFusionActive)) {
            $mainframe = $this->startJoomla();
            //if already in Joomla framelessly, then do nothing as the getBuffer function will handle logins/outs
            if (!defined('IN_JOOMLA')) {
                //define that the phpBB3 JFusion plugin needs to be excluded
                global $JFusionActivePlugin;
                $JFusionActivePlugin =(empty($conf['jfusion']['jfusion_plugin_name'])) ? 'dokuwiki' : $conf['jfusion']['jfusion_plugin_name'];
                // do the login
                $credentials = array('username' => $username, 'password' => $password);
                $options = array('entry_url' => JURI::root() . 'index.php?option=com_user&task=login', 'silent' => true);

                //detect if the session should be remembered
                if (!empty($sticky)) {
                    $options['remember'] = 1;
                } else {
                    $options['remember'] = 0;
                }

                $success = $mainframe->login($credentials, $options);

                // clean up the joomla session object before continuing
                $session = JFactory::getSession();
                $session->close();
            }

            $this->stopJoomla();
        }
    }

    function logoutJoomla() {
        global $JFusionActive, $conf;
        if (empty($JFusionActive)) {
            //define that the phpBB3 JFusion plugin needs to be excluded
            global $JFusionActivePlugin;
            $JFusionActivePlugin =(empty($conf['jfusion']['jfusion_plugin_name'])) ? 'dokuwiki' : $conf['jfusion']['jfusion_plugin_name'];
            $mainframe = $this->startJoomla();

            //if already in Joomla framelessly, then do nothing as the getBuffer function will handle logins/outs
            if (!defined('IN_JOOMLA')) {
                //logout any joomla users
                $mainframe->logout();

                //clean up session
                $session = JFactory::getSession();
                $session->close();
            }

            $this->stopJoomla();
        }
    }
}