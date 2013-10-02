<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * load the common Joomla JFusion plugin functions
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'model.joomlaadmin.php';

/**
 * JFusion Admin Class for an external Joomla database.
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_joomla_ext extends JFusionJoomlaAdmin
{
    /**
     * @return string
     */
    function getJname()
	{
		return 'joomla_ext';
	}

	/**
	 * Function finds config file of integrated software and automatically configures the JFusion plugin
	 *
	 * @param string $path path to root of integrated software
	 *
	 * @return object JParam JParam objects with ne newly found configuration
	 * Now Joomla 1.6+ compatible
	 */
	public function setupFromPath($path)
	{
		//check for trailing slash and generate file path
		if (substr($path, -1) == DIRECTORY_SEPARATOR) {
			$configfile = $path . 'configuration.php';
			//joomla 1.6+ test
			$test_version_file = $path . 'includes' . DIRECTORY_SEPARATOR . 'version.php';
		} else {
			$configfile = $path . DIRECTORY_SEPARATOR . 'configuration.php';
			$test_version_file = $path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'version.php';
		}
		$params = array();
		$lines = $this->readFile($configfile);
		if ($lines === false) {
			JFusionFunction::raiseWarning(JText::_('WIZARD_FAILURE') . ': ' . $configfile . ' ' . JText::_('WIZARD_MANUAL'));
			return false;
		} else {
			//parse the file line by line to get only the config variables
			//we can not directly include the config file as JConfig is already defined
			$config = array();
			foreach ($lines as $line) {
				if (strpos($line, '$')) {
					//extract the name and value, it was coded to avoid the use of eval() function
					// because from Joomla 1.6 the configuration items are declared public in tead of var
					// we just convert public to var
					$line = str_replace('public $','var $',$line);
					$vars = explode("'", $line);
					$names = explode('var', $vars[0]);
					if (isset($vars[1]) && isset($names[1])) {
						$name = trim($names[1], ' $=');
						$value = trim($vars[1], ' $=');
						$config[$name] = $value;
					}
				}
			}

			//Save the parameters into the standard JFusion params format
			$params['database_host'] = isset($config['host']) ? $config['host'] : '';
			$params['database_name'] = isset($config['db']) ? $config['db'] : '';
			$params['database_user'] = isset($config['user']) ? $config['user'] : '';
			$params['database_password'] = isset($config['password']) ? $config['password'] : '';
			$params['database_prefix'] = isset($config['dbprefix']) ? $config['dbprefix'] : '';
			$params['database_type'] = isset($config['dbtype']) ? $config['dbtype'] : '';
			$params['source_path'] = $path;

			//determine if this is 1.5 or 1.6+
			$params['joomlaversion'] = (file_exists($test_version_file)) ? '1.6' : '1.5';
		}
		return $params;
	}
}
