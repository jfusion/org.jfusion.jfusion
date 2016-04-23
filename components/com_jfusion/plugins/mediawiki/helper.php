<?php
// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * JFusionHelper_mediawiki class
 *
 * @category   JFusion
 * @package    Model
 * @subpackage JFusionHelper_mediawiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHelper_mediawiki extends JFusionPlugin
{
    var $joomlaSessionName = '';
    var $joomlaSessionId = '';
    var $joomlaSessionUseCookies = '';
    var $joomlaSessionCookieParams = '';

    /**
     * Returns the name for this plugin
     *
     * @return string
     */
    function getJname()
    {
        return 'mediawiki';
    }

    /**
     * @return string
     */
    function getCookieName() {
        static $mediawiki_cookieName;
        if (!empty($mediawiki_cookieName)) {
            return $mediawiki_cookieName;
        }

        $cookie_name = $this->getConfig('wgCookiePrefix');
        if (empty($cookie_name)) {
            $db_name = $this->params->get('database_name');
            $db_prefix = $this->params->get('database_prefix');
            $cookie_name = (!empty($db_prefix)) ? $db_name . '_' . $db_prefix : $db_name;
        }
        $mediawiki_cookieName = strtr($cookie_name, "=,; +.\"'\\[", '__________');
        return $mediawiki_cookieName;
    }

    /**
     * Backup Joomla session info and start one for the software
     *
     * @param array $options login options
     */
    function startSession($options = array()) {
		$this->joomlaSessionName = session_name();
		$this->joomlaSessionId = session_id();
		$this->joomlaSessionCookieParams = session_get_cookie_params();

		//close Joomla session
		session_write_close();

        //initialize refbase session
		if (!$this->joomlaSessionUseCookies = ini_get('session.use_cookies')) {
			ini_set('session.use_cookies', 1);
		}
		ini_set('session.save_handler', 'files');
		$lifetime = (empty($options['remember'])) ? 0 : 31536000;
		$cookie_name = $this->getCookieName();
		$cookie_domain = $this->params->get('cookie_domain', null);
		$cookie_path = $this->params->get('cookie_path', null);
		$secure = $this->params->get('secure', null);
		$httponly = $this->params->get('httponly', null);
		$session_name = $cookie_name . '_session';
		session_set_cookie_params($lifetime, $cookie_path, $cookie_domain, $secure, $httponly);
		session_name($session_name);
		session_start();
    }

    /**
     * Close session and restore Joomla
     */
    function closeSession() {
		session_write_close();
    	session_set_cookie_params($this->joomlaSessionCookieParams['lifetime'], $this->joomlaSessionCookieParams['path'], $this->joomlaSessionCookieParams['domain'], $this->joomlaSessionCookieParams['secure'], $this->joomlaSessionCookieParams['httponly']);
        ini_set('session.use_cookies', $this->joomlaSessionUseCookies);
		session_name($this->joomlaSessionName);
		session_id($this->joomlaSessionId);
		session_start();
    }

    /**
     * @param $getVar
     * @return mixed
     */
    function getConfig($getVar) {
        static $config = array();

        if (isset($config[$getVar])) {
            return $config[$getVar];
        }

        $source_path = $this->params->get('source_path');

        $paths = $this->includeFramework($source_path);
        $IP = $source_path;
        foreach($paths as $path) {
            include($path);
        }
        $config[$getVar] = (isset($$getVar)) ? $$getVar : '';
        return $config[$getVar];
    }

    /**
     * @param $source_path
     *
     * @return array
     */
    function includeFramework($source_path) {
        //check for trailing slash and generate file path

        $return[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'DefaultSettings.php';
        $return[] = $source_path . 'LocalSettings.php';

        global $IP, $wgStylePath;
        $IP = $source_path;

        $source_url = $this->params->get('source_url');

        $uri = new JURI($source_url);

        $wgStylePath = $uri->getPath() .'skinns';

//        $paths[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'WebStart.php';

        $paths[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'AutoLoader.php';
        $paths[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'Defines.php';
	    $paths[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'GlobalFunctions.php';
        $paths[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'IP.php';
        $paths[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'utils' . DIRECTORY_SEPARATOR . 'IP.php';
        $paths[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'WebRequest.php';
        $paths[] = $source_path . 'includes'. DIRECTORY_SEPARATOR . 'SiteConfiguration.php';

        defined ('MEDIAWIKI') or define('MEDIAWIKI', TRUE);
        defined ('MW_INSTALL_PATH') or define('MW_INSTALL_PATH', $source_path);
        putenv('MW_INSTALL_PATH=' . $source_path);
        foreach($paths as $path) {
            if (file_exists($path)) {
                include_once($path);
            }
        }
        return $return;
    }
}
