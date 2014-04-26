<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

//force required variables into global scope
use JFusion\Api\Platform;

if (!isset($GLOBALS['vbulletin']) && !empty($vbulletin)) {
    $GLOBALS['vbulletin'] = & $vbulletin;
}
if (!isset($GLOBALS['db']) && !empty($db)) {
    $GLOBALS['db'] = & $db;
}

/**
 * Vbulletin hook class
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class executeJFusionJoomlaHook
{
    var $vars;

    /**
     * @param $hook
     * @param $vars
     * @param string $key
     */
    function __construct($hook, &$vars, $key = '')
    {
        if ($hook != 'init_startup' && !defined('_VBJNAME') && empty($_POST['logintype'])) {
            die('JFusion plugins need to be updated.  Reinstall desired plugins in JFusions config for vBulletin.');
        }

        if (!defined('_JFVB_PLUGIN_VERIFIED') && $hook != 'init_startup' && defined('_VBJNAME') && defined('_JEXEC') && empty($_POST['logintype'])) {
            define('_JFVB_PLUGIN_VERIFIED', 1);
	        $user = \JFusion\Factory::getUser(_VBJNAME);
            if (!$user->isConfigured()) {
                die('JFusion plugin is invalid.  Reinstall desired plugins in JFusions config for vBulletin.');
            }
        }

        //execute the hook
        $this->vars =& $vars;
        $this->key = $key;
        eval('$success = $this->' . $hook . '();');
        //if ($success) die('<pre>'.print_r($GLOBALS['vbulletin']->pluginlist, true).'</pre>');
    }

    function init_startup()
    {
        global $vbulletin;
        if ($this->vars == 'redirect' && !isset($_GET['noredirect']) && !defined('_JEXEC') && !isset($_GET['jfusion'])) {
            //only redirect if in the main forum
            if (!empty($_SERVER['PHP_SELF'])) {
                $s = $_SERVER['PHP_SELF'];
            } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
                $s = $_SERVER['SCRIPT_NAME'];
            } else {
                //the current URL cannot be determined so abort redirect
                return;
            }
            $ignore = array($vbulletin->config['Misc']['admincpdir'], 'ajax.php', 'archive', 'attachment.php', 'cron.php', 'image.php', 'inlinemod', 'login.php', 'misc.php', 'mobiquo', $vbulletin->config['Misc']['modcpdir'], 'newattachment.php', 'picture.php', 'printthread.php', 'sendmessage.php');
            if (defined('REDIRECT_IGNORE')) {
                $custom_files = explode(',', REDIRECT_IGNORE);
                if (is_array($custom_files)) {
                    foreach ($custom_files as $file) {
                        if (!empty($file)) {
                            $ignore[] = trim($file);
                        }
                    }
                }
            }
            $redirect = true;
            foreach ($ignore as $i) {
                if (strpos($s, $i) !== false) {
                    //for sendmessage.php, only redirect if not sending an IM
                    if ($i == 'sendmessage.php') {
                        $do = $_GET['do'];
                        if ($do != 'im') {
                            continue;
                        }
                    }
                    $redirect = false;
                    break;
                }
            }

            if (isset($_POST['jfvbtask'])) {
                $redirect = false;
            }

            if ($redirect && defined('JOOMLABASEURL')) {
                $filename = basename($s);
                $query = $_SERVER['QUERY_STRING'];
                if (defined('SEFENABLED') && SEFENABLED) {
                    if (defined('SEFMODE') && SEFMODE == 1) {
                        $url = JOOMLABASEURL . $filename . '/';
                        if (!empty($query)) {
                            $q = explode('&', $query);
                            foreach ($q as $k => $v) {
                                $url.= $k . ',' . $v . '/';
                            }
                        }
                        if (!empty($query)) {
                            $queries = explode('&', $query);
                            foreach ($queries as $q) {
                                $part = explode('=', $q);
                                $url.= $part[0] . ',' . $part[1] . '/';
                            }
                        }
                    } else {
                        $url = JOOMLABASEURL . $filename;
                        $url.= (empty($query)) ? '' : '?' . $query;
                    }
                } else {
                    $url = JOOMLABASEURL . '&jfile=' . $filename;
                    $url.= (empty($query)) ? '' : '&' . $query;
                }
                header('Location: ' . $url);
                exit;
            }
        }
        //add our custom hooks into vbulletin hook cache
        if (!empty($vbulletin->pluginlist) && is_array($vbulletin->pluginlist)) {
            $hooks = $this->getHooks($this->vars);
            if (is_array($hooks)) {
                foreach ($hooks as $name => $code) {
                    if ($name == 'global_setup_complete') {
                        $depracated =  (version_compare($vbulletin->options['templateversion'], '4.0.2') >= 0) ? 1 : 0;
                        if ($depracated) {
                            $name = 'global_bootstrap_complete';
                        }
                    }

                    if (isset($vbulletin->pluginlist[$name])) {
                        $vbulletin->pluginlist[$name] .= "\n$code";
                    } else {
                        $vbulletin->pluginlist[$name] = $code;
                    }
                }
            }
        }
    }

    /**
     * @param $plugin
     * @return array
     */
    function getHooks($plugin)
    {
        global $hookFile;

        if (empty($hookFile) && defined('JFUSION_VB_JOOMLA_HOOK_FILE')) {
            //as of JFusion 1.6
            $hookFile = JFUSION_VB_JOOMLA_HOOK_FILE;
        }

        //we need to set up the hooks
        if ($plugin == 'duallogin') {
            //retrieve the hooks that vBulletin will use to login to Joomla
            $hookNames = array('global_setup_complete', 'login_verify_success', 'logout_process');
            define('DUALLOGIN', 1);
        } else {
            $hookNames = array();
        }
        $hooks = array();

        foreach ($hookNames as $h) {
	        $toPass = '$vars = null;';
	        $hooks[$h] = 'include_once \'' . $hookFile . '\'; ' . $toPass . ' $jFusionHook = new executeJFusionJoomlaHook(\'' . $h . '\', $vars, \''. $this->key . '\');';
        }
        return $hooks;
    }

    /**
     * @return bool
     */
    function global_setup_complete()
    {
        if (defined('_JEXEC')) {
            //If Joomla SEF is enabled, the dash in the logout hash gets converted to a colon which must be corrected
            global $vbulletin, $show, $vbsefenabled, $vbsefmode;
            $vbulletin->GPC['logouthash'] = str_replace(':', '-', $vbulletin->GPC['logouthash']);
            //if sef is enabled, we need to rewrite the nojs link
            if ($vbsefenabled == 1) {
                if ($vbsefmode == 1) {
                    $uri = JUri::getInstance();
                    $url = $uri->toString();
                    $show['nojs_link'] = $url;
                    $show['nojs_link'].= (substr($url, -1) != '/') ? '/nojs,1/' : 'nojs,1/';
                } else {
	                $jfile = \JFusion\Factory::getApplication()->input->get('jfile', false);
                    $jfile = ($jfile) ? $jfile : 'index.php';
                    $show['nojs_link'] = $jfile . '?nojs=1';
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    function global_start()
    {
        //lets rewrite the img urls now while we can
        global $stylevar, $vbulletin;
        //check for trailing slash
        $DS = (substr($vbulletin->options['bburl'], -1) == '/') ? '' : '/';
        if(!empty($stylevar)) {
            foreach ($stylevar as $k => $v) {
                if (strstr($k, 'imgdir') && strstr($v, $vbulletin->options['bburl']) === false && strpos($v, 'http') === false) {
                    $stylevar[$k] = $vbulletin->options['bburl'] . $DS . $v;
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    function login_verify_success()
    {
        $this->backup_restore_globals('backup');
        global $vbulletin;
        //if JS is enabled, only a hashed form of the password is available
        $password = (!empty($vbulletin->GPC['vb_login_password'])) ? $vbulletin->GPC['vb_login_password'] : $vbulletin->GPC['vb_login_md5password'];
        if (!empty($password)) {
            if (!defined('_JEXEC')) {
                $mainframe = $this->startJoomla();
            } else {
                $mainframe = JFactory::getApplication('site');
                define('_VBULLETIN_JFUSION_HOOK', true);
            }
            // do the login
            global $JFusionActivePlugin;
	        if (defined('_VBJNAME')) {
		        $JFusionActivePlugin =  _VBJNAME;
	        }
            $baseURL = (class_exists('JFusionFunction')) ? \JFusionFunction::getJoomlaURL() : JUri::root();
            $loginURL = JRoute::_($baseURL . 'index.php?option=com_user&task=login', false);
            $credentials = array('username' => $vbulletin->userinfo['username'], 'password' => $password, 'password_salt' => $vbulletin->userinfo['salt']);
            $options = array('entry_url' => $loginURL);
            //set remember me option
            if(!empty($vbulletin->GPC['cookieuser'])) {
                $options['remember'] = 1;
            }
            //creating my own vb security string for check in the function
            define('_VB_SECURITY_CHECK', md5('jfusion' . md5($password . $vbulletin->userinfo['salt'])));
            $mainframe->login($credentials, $options);
            // clean up the joomla session object before continuing
            $session = JFactory::getSession();
            $session->close();
        }
        $this->backup_restore_globals('restore');
        return true;
    }

    /**
     * @return bool
     */
    function logout_process()
    {
        $this->backup_restore_globals('backup');
        if (defined('_JEXEC')) {
            //we are in frameless mode and need to kill the cookies to prevent getting stuck logged in
            global $vbulletin;
	        $cookies = \JFusion\Factory::getCookies();
	        $cookies->addCookie(COOKIE_PREFIX . 'userid', 0, 0, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], false, true);
	        $cookies->addCookie(COOKIE_PREFIX . 'password', 0, 0, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], false, true);
            //prevent global_complete from recreating the cookies
            $vbulletin->userinfo['userid'] = 0;
            $vbulletin->userinfo['password'] = 0;
        }
        if (defined('DUALLOGIN')) {
            if (!defined('_JEXEC')) {
                $mainframe = $this->startJoomla();
            } else {
                $mainframe = JFactory::getApplication('site');
                define('_VBULLETIN_JFUSION_HOOK', true);
            }
            global $JFusionActivePlugin;
	        if (defined('_VBJNAME')) {
		        $JFusionActivePlugin =  _VBJNAME;
	        }
            // logout any joomla users
            $mainframe->logout();
            // clean up session
            $session = JFactory::getSession();
            $session->close();
        }
        $this->backup_restore_globals('restore');
        return true;
    }

    /**
     * @param $action
     */
    function backup_restore_globals($action)
    {
        static $vb_globals;

        if (!is_array($vb_globals)) {
            $vb_globals = array();
        }

        if ($action == 'backup') {
            foreach ($GLOBALS as $n => $v) {
                $vb_globals[$n] = $v;
            }
        } else {
            foreach ($vb_globals as $n => $v) {
                $GLOBALS[$n] = $v;
            }
        }
    }

    /**
     * @return JApplication
     */
    function startJoomla()
    {
        define('_VBULLETIN_JFUSION_HOOK', true);
        define('_JFUSIONAPI_INTERNAL', true);
        require_once JPATH_BASE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR  . 'jfusionapi.php';
	    $joomla = Platform::getInstance();
	    $mainframe = $joomla->getApplication();

        $curlFile = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
        if (file_exists($curlFile)) {
            require_once $curlFile;
        }
        return $mainframe;
    }
}