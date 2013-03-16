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
class JFusionHelper_dokuwiki
{
    /**
     * @var doku_auth_mysql|doku_auth_plain
     */
    var $auth = null;

    /**
     *
     */
    function __construct()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $database_type = $params->get('database_type');
        $database_host = $params->get('database_host');
        if ($database_host && $database_type == 'mysql') {
            if (!class_exists('doku_auth_mysql')) {
                require_once('auth'.DS.'mysql.class.php');
            }
            $this->auth = new doku_auth_mysql($this);
        } else {
            if (!class_exists('doku_auth_plain')) {
                require_once('auth'.DS.'plain.class.php');
            }
            $this->auth = new doku_auth_plain($this);
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
        $params = JFusionFactory::getParams($this->getJname());
        $source_url = $params->get('source_url');
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
            define('DOKU_COOKIE', 'DW'.md5(DOKU_REL.(($conf['securecookie'])?$_SERVER['SERVER_PORT']:'')));
        }
    }

    /**
     * Retrieves, sets, and returns Dokuwiki cookie salt
     * @return string   cookie salt
     */

    function getCookieSalt() {
        static $dokuwiki_cookie_salt;

        if (empty($dokuwiki_cookie_salt)) {
            $params = JFusionFactory::getParams($this->getJname());
            $source_path = $params->get('source_path');

            $conf = $this->getConf();
            $data_dir = (isset($conf['savedir'])) ? $source_path . DS . $conf['savedir'] : $source_path . DS . 'data';

            //get the cookie salt file
            $saltfile = $data_dir . DS . 'meta' . DS .'_htcookiesalt';
            jimport('joomla.filesystem.file');
            $dokuwiki_cookie_salt = JFile::read($saltfile);
            if(empty($dokuwiki_cookie_salt)){
                $dokuwiki_cookie_salt = uniqid(rand(),true);
                JFile::write($saltfile,$dokuwiki_cookie_salt);
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

    function getVersion($v='version') {
        static $dokuwiki_version;

        if (empty($dokuwiki_version)) {
            $params = JFusionFactory::getParams($this->getJname());
            $source_path = $params->get('source_path');

            jimport('joomla.filesystem.file');
            $file_version = JFile::read($source_path.DS.'VERSION');
            $matches = array();
            if (preg_match('#([a-z]*)([0-9]*-[0-9]*-[0-9]*)([a-z]*)#is' , $file_version, $matches)) {
	            list($fullversion, $rc, $version, $sub) = $matches;
	            $dokuwiki_version['full'] = $fullversion;
	            $dokuwiki_version['version'] = $version.$rc.$sub;
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
            $params = JFusionFactory::getParams($this->getJname());
            $source_path = $params->get('source_path');
            $config_path = (empty($path)) ? $source_path : $path;

            //make sure the source path ends with a DS
            if (substr($path, -1) != DS) {
                $config_path .= DS;
            }

            //standard config path
            $config_path .= 'conf' . DS;

            //check to see if conf directory is located somewhere else
            if (file_exists($source_path  . DS . 'inc' . DS . 'preload.php')) {
                include_once $source_path  . DS . 'inc' . DS . 'preload.php';
                if (defined('DOKU_CONF')) {
                    $config_path = DOKU_CONF;
                    //make sure we have a ending DS
                    if (substr($config_path, -1) != DS) {
                       $config_path .= DS;
                    }
                }
            }
        }

        return $config_path;
    }







    /**
     * This method should handle any login logic and report back to the subject
     *
     * @param string $jname
     *
     * @return Dokuwiki object instance
     * @since 1.5
     * @access public
     */
    public static function &getInstance($jname)
    {
        static $instances;
        if (!isset($instances[$jname])) {
            $instance = new Dokuwiki($jname);
            $instances[$jname] = $instance;
        }
        return $instances[$jname];
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
                $params = JFusionFactory::getParams($this->getJname());
                $path = $params->get('source_path');
            }
            /**
             * @ignore
             * @var $helper JFusionHelper_dokuwiki
             */
            $helper = JFusionFactory::getHelper($this->getJname());
            $path = $helper->getConfigPath($path);

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