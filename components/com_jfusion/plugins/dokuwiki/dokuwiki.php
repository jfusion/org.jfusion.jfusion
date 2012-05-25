<?php

/**
 * file containing functions for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * load the DokuWiki framework
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractadmin.php';
require_once dirname(__FILE__) . DS . 'admin.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractuser.php';
require_once dirname(__FILE__) . DS . 'user.php';

if (!class_exists('doku_auth_plain')) {
	require_once dirname(__FILE__) . DS . 'auth' . DS . 'plain.class.php';
}
if (!class_exists('doku_auth_mysql')) {
	require_once dirname(__FILE__) . DS . 'auth' . DS . 'mysql.class.php';
}
/**
 * JFusion plugin class for DokuWiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class Dokuwiki
{
	var $jname = null;
    var $auth = null;
    var $io = null;
    function Dokuwiki($jname)
    {
    	$this->jname = $jname;
    	
    	$params = JFusionFactory::getParams($this->getJname());
    	$database_type = $params->get('database_type');
    	$database_host = $params->get('database_host');
    	if ($database_host && $database_type == 'mysql') {
        	$this->auth = new doku_auth_mysql($jname);
    	} else {
    		$this->auth = new doku_auth_plain($jname);
    	}
    }

    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return $this->jname;
    }

    /**
     * This method should handle any login logic and report back to the subject
     *
     * @return object instance
     * @since 1.5
     * @access public
     */
    public static function &getInstance($jname)
    {
        static $instances;
        if (!isset($instances[$jname])) {
            $instance = new Dokuwiki($jname);
            $instances[$jname] = & $instance;
        }
        return $instances[$jname];
    }

    /**
     * This method should handle any login logic and report back to the subject
     *
     * @param string $path path to config file
     *
     * @return array on success, false on falior
     * @since 1.5
     * @access public
     */
    function getConf($path = false)
    {
        static $config;
        if (is_array($config)) {
            return $config;
        }

        if (!$path) {
            $params = JFusionFactory::getParams($this->getJname());
            $path = $params->get('source_path');
        }

        $helper = & JFusionFactory::getHelper($this->getJname());
        $path = $helper->getConfigPath($path);

        $myfile = array();
        $myfile[] = $path . 'dokuwiki.php';
        $myfile[] = $path . 'local.php';
        $myfile[] = $path . 'local.protected.php';

        $conf = null;

        foreach ($myfile as $file) {
            if (file_exists($file)) {
                require($file);
            }
        }

        $config = $conf;
        if (is_array($config)) {
            return $config;
        } else {
            return false;
        }
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
        $share = Dokuwiki::getInstance($this->getJname());
        $conf = $share->getConf();
        if (!empty($conf['defaultgroup'])) {
        	return $conf['defaultgroup'];
        }
        return 'user';
    }
}
