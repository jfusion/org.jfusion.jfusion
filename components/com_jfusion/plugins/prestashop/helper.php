<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpbb
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
 * @subpackage phpbb
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHelper_prestashop extends JFusionPlugin
{
    /**
     * Returns the name for this plugin
     *
     * @return string
     */
    function getJname() {
        return 'prestashop';
    }

    /**
	 * Load Framework
     */
    function loadFramework()
    {
	    $source_path = $this->params->get('source_path');

	    require_once($source_path . DS . 'config' . DS . 'settings.inc.php');

	    $this->loadClass('Context');

	    $this->loadClass('Blowfish');
	    $this->loadClass('Cookie');
	    $this->loadClass('Tools');

	    $this->loadClass('ObjectModel');
//		require_once($source_path . DS . 'classes' . DS . 'ObjectModel.php');
//		require_once($source_path . DS . 'tools' . DS . 'profiling' . DS . 'ObjectModel.php');

	    require_once($source_path . DS . 'classes' . DS . 'db' . DS . 'Db.php');

	    $this->loadClass('Validate');
	    $this->loadClass('Country');
	    $this->loadClass('State');
	    $this->loadClass('Customer');

	    $this->loadClass('Configuration');
    }

	function loadClass($class) {
		$source_path = $this->params->get('source_path');

		require_once($source_path . DS . 'classes' . DS . $class.'.php');
		require_once(JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'classes' . DS . $class.'.php');
	}
}