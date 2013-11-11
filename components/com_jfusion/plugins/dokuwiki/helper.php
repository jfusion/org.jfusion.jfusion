<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Dokuwiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Helper Class for Dokuwiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Dokuwiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHelper_dokuwiki extends JFusionPlugin
{
    /**
     * @var Jfusion_DokuWiki_Basic
     */
    var $auth;

    /**
     *
     */
    function __construct()
    {
        parent::__construct();
        $database_type = $this->params->get('database_type');
        $database_host = $this->params->get('database_host');
        if ($database_host && $database_type == 'mysql') {
            if (!class_exists('Jfusion_DokuWiki_Mysql')) {
                require_once('auth' . DIRECTORY_SEPARATOR . 'mysql.class.php');
            }
            $this->auth = new Jfusion_DokuWiki_Mysql($this);
        } else {
            if (!class_exists('Jfusion_DokuWiki_Plain')) {
                require_once('auth' . DIRECTORY_SEPARATOR . 'plain.class.php');
            }
            $this->auth = new Jfusion_DokuWiki_Plain($this);
        }
	    if (!$this->auth) {
		    if (!class_exists('Jfusion_DokuWiki_Basic')) {
			    require_once('auth' . DIRECTORY_SEPARATOR . 'basic.class.php');
		    }
		    $this->auth = new Jfusion_DokuWiki_Basic($this);
	    }
    }

    /**
     * Returns the name for this plugin
     *
     * @return string
     */
    function getJname() {
        return 'dokuwiki';
    }

    /**
     * Defines constants required by Dokuwiki
     *
     * @param $nosession    boolean to set the NOSESSION constant; false by default
     */
    function defineConstants($nosession = false) {
        $conf = $this->getConf();

        $source_url = $this->params->get('source_url');
        $doku_rel = preg_replace('#(\w{0,10}://)(.*?)/(.*?)#is', '$3', $source_url);
        $doku_rel = preg_replace('#//+#', '/', "/$doku_rel/");
        if (!defined('DOKU_REL')) {
            define('DOKU_REL', $doku_rel);
        }
        if (!defined('DOKU_URL')) {
            define('DOKU_URL', $source_url);
        }
        if (!defined('DOKU_BASE')) {
            define('DOKU_BASE', DOKU_URL);
        }
        if ($nosession && !defined('NOSESSION')) {
            define('NOSESSION', 1);
        }
        if (!defined('DOKU_COOKIE')) {
            define('DOKU_COOKIE', 'DW' . md5(DOKU_REL . (($conf['securecookie']) ? $_SERVER['SERVER_PORT'] : '')));
        }
    }

    /**
     * Retrieves, sets, and returns Dokuwiki cookie salt
     * @return string   cookie salt
     */

    function getCookieSalt() {
        static $dokuwiki_cookie_salt;

        if (empty($dokuwiki_cookie_salt)) {
            $source_path = $this->params->get('source_path');

            $conf = $this->getConf();
            $data_dir = (isset($conf['savedir'])) ? $source_path . DIRECTORY_SEPARATOR . $conf['savedir'] : $source_path . DIRECTORY_SEPARATOR . 'data';

            //get the cookie salt file
            $saltfile = $data_dir . DIRECTORY_SEPARATOR . 'meta' . DIRECTORY_SEPARATOR . '_htcookiesalt';
            jimport('joomla.filesystem.file');
            $dokuwiki_cookie_salt = file_get_contents($saltfile);
            if(empty($dokuwiki_cookie_salt)){
                $dokuwiki_cookie_salt = uniqid(rand(), true);
                JFile::write($saltfile, $dokuwiki_cookie_salt);
            }
        }
        return $dokuwiki_cookie_salt;
    }
    
    /**
     * Retrieves, sets, and returns Dokuwiki Version
     *
     * @param string $v
     *
     * @return string   version
     */

    function getVersion($v = 'version') {
        static $dokuwiki_version;

        if (empty($dokuwiki_version)) {
            $source_path = $this->params->get('source_path');

            jimport('joomla.filesystem.file');
            $file_version = file_get_contents($source_path . 'VERSION');
            $matches = array();
            if (preg_match('#([a-z]*)([0-9]*-[0-9]*-[0-9]*)([a-z]*)#is', $file_version, $matches)) {
	            list($fullversion, $rc, $version, $sub) = $matches;
	            $dokuwiki_version['full'] = $fullversion;
	            $dokuwiki_version['version'] = $version . $rc . $sub;
            }
        }
        if (isset($dokuwiki_version[$v])) {
        	return $dokuwiki_version[$v];
        }
        return null;
    }

    /**
     * @param bool $path
     * @return bool|string
     */
    function getConfigPath($path = false) {
        static $config_path;

        if (empty($config_path)) {
            $source_path = $this->params->get('source_path');
            $config_path = (empty($path)) ? $source_path : $path;

            //make sure the source path ends with a DIRECTORY_SEPARATOR
            if (substr($path, -1) != DIRECTORY_SEPARATOR) {
                $config_path .= DIRECTORY_SEPARATOR;
            }

            //standard config path
            $config_path .= 'conf' . DIRECTORY_SEPARATOR;

            //check to see if conf directory is located somewhere else
            if (file_exists($source_path  . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'preload.php')) {
                include_once $source_path  . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'preload.php';
                if (defined('DOKU_CONF')) {
                    $config_path = DOKU_CONF;
                    //make sure we have a ending DIRECTORY_SEPARATOR
                    if (substr($config_path, -1) != DIRECTORY_SEPARATOR) {
                       $config_path .= DIRECTORY_SEPARATOR;
                    }
                }
            }
        }

        return $config_path;
    }

    /**
     * This method should handle any login logic and report back to the subject
     *
     * @param string|bool $path path to config file
     *
     * @return array on success, false on Error
     * @since 1.5
     * @access public
     */
    function getConf($path = false)
    {
        static $config;
        if (!is_array($config)) {
            if (!$path) {
                $path = $this->params->get('source_path');
            }

            $path = $this->getConfigPath($path);

            $myfile = array();
            $myfile[] = $path . 'dokuwiki.php';
            $myfile[] = $path . 'local.php';
            $myfile[] = $path . 'local.protected.php';

            $conf = array();
            foreach ($myfile as $file) {
                if (file_exists($file)) {
                    require($file);
                }
            }
            $config = $conf;
            if (!count($config)) {
                $config = false;
            }
        }
        return $config;
    }

    /**
     * This method should handle any login logic and report back to the subject
     *
     * @return string True on success
     * @since 1.5
     * @access public
     */
    function getDefaultUsergroup()
    {
        $conf = $this->getConf();
        $usergroup = 'user';
        if (!empty($conf['defaultgroup'])) {
            $usergroup = $conf['defaultgroup'];
        }
        return $usergroup;
    }
}