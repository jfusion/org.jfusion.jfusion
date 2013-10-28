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

		require_once($source_path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings.inc.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Cookie.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Blowfish.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Tools.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'ObjectModel.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'profiling' . DIRECTORY_SEPARATOR . 'ObjectModel.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'Db.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Validate.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Country.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'State.php');
		require_once($source_path . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Customer.php');
	}
}