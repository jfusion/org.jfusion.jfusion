<?php
/**
 * This is the user activity helper file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Useractivity
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
*/

// no direct access
use Psr\Log\LogLevel;

defined('_JEXEC' ) or die('Restricted access' );
/**
* load the helper file
*/
require_once(dirname(__FILE__) . '/helper.php');

//check if the JFusion component is installed
$factory_file = JPATH_ADMINISTRATOR . '/components/com_jfusion/import.php';
try {
	if (file_exists($factory_file)) {
		/**
		* require the JFusion libraries
		*/
		require_once $factory_file;
	    /**
	     * @ignore
	     * @var $params JRegistry
	     * @var $config array
	     */
	    $pluginParamValue = $params->get('JFusionPlugin');
	    $pluginParamValue = unserialize(base64_decode($pluginParamValue));
	    $jname = $pluginParamValue['jfusionplugin'];

		$view = $params->get('view', 'auto');

		/**
		 * @ignore
		 * @var $platform \JFusion\Plugin\Platform\Joomla
		 */
		$platform = \JFusion\Factory::getPlayform('Joomla', $jname);
		if($platform->isConfigured()) {
			//configuration
			$config['itemid'] = $params->get('itemid');
			$config['avatar'] = $params->get('avatar', false);
			$config['avatar_software'] = $params->get('avatar_software', 'jfusion');
			$config['avatar_height'] = $params->get('avatar_height', 40);
			$config['avatar_width'] = $params->get('avatar_width', 30);
			$config['avatar_location'] = $params->get('avatar_location', 'top');
			$config['pmcount'] = $params->get('pmcount', false);
			$config['viewnewmessages'] = $params->get('viewnewmessages', false);
			$config['login_msg'] = $params->get('login_msg');
			$config['alignment'] = $params->get('alignment', 'center');
			$config['avatar_keep_proportional'] = $params->get('avatar_keep_proportional', false);

			if ($params->get('new_window', false)) {
				$config['new_window'] = '_blank';
			} else {
				$config['new_window'] = '_self';
			}

			if (empty($config['itemid'])) {
				throw new RuntimeException(JText::_('NO_ITEMID_SELLECTED'));
			}

			if($view == 'auto') {
				$joomlaUser = JFactory::getUser();

				if(!$joomlaUser->guest) {
					$output = modjfusionUserActivityHelper::prepareAutoOutput($jname, $config, $params);
				}

				require(JModuleHelper::getLayoutPath('mod_jfusion_user_activity'));
			} else {
				if ($platform->methodDefined('renderUserActivityModule')) {
					$output = $platform->renderUserActivityModule($config, $view, $params);
					echo $output;
				} else {
					throw new RuntimeException(JText::_('NOT_IMPLEMENTED_YET'));
				}
	        }
		} else {
			if (empty($jname)) {
				throw new RuntimeException(JText::_('MODULE_NOT_CONFIGURED'));
			} else {
				throw new RuntimeException(JText::_('NO_PLUGIN'));
			}
		}
	} else {
		throw new RuntimeException(JText::_('NO_COMPONENT'));
	}
} catch (Exception $e) {
	\JFusion\Framework::raise(LogLevel::ERROR, $e, 'mod_jfusion_user_activity');
	echo $e->getMessage();
}