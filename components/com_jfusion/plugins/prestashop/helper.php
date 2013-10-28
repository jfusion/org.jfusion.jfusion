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
 * JFusion Helper Class for Prestashop
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage prestashop
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHelper_prestashop
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
		$params = JFusionFactory::getParams($this->getJname());
		$source_path = $params->get('source_path');

		require_once($source_path . DS . 'config' . DS . 'settings.inc.php');

		require_once($source_path . DS . 'classes' . DS . 'Context.php');
		require_once(JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'Context.php');
		require_once($source_path . DS . 'classes' . DS . 'Cookie.php');
		require_once($source_path . DS . 'classes' . DS . 'Blowfish.php');
		require_once($source_path . DS . 'classes' . DS . 'Tools.php');
		require_once($source_path . DS . 'classes' . DS . 'ObjectModel.php');
		require_once($source_path . DS . 'tools' . DS . 'profiling' . DS . 'ObjectModel.php');
		require_once($source_path . DS . 'classes' . DS . 'db' . DS . 'Db.php');
		require_once($source_path . DS . 'classes' . DS . 'Validate.php');
		require_once($source_path . DS . 'classes' . DS . 'Country.php');
		require_once($source_path . DS . 'classes' . DS . 'State.php');
		require_once($source_path . DS . 'classes' . DS . 'Customer.php');
	}
}