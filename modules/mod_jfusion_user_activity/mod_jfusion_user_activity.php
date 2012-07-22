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
defined('_JEXEC' ) or die('Restricted access' );
/**
* load the helper file
*/
require_once(dirname(__FILE__).DS.'helper.php');

//check if the JFusion component is installed
$model_file = JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.factory.php';
$factory_file = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
$factory_admin_file = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusionadmin.php';
if (file_exists($model_file) && file_exists($factory_file) && file_exists($factory_admin_file)) {

	/**
	* require the JFusion libraries
	*/
	require_once $model_file;
	require_once $factory_file;
    require_once $factory_admin_file;
    /**
     * @ignore
     * @var $params JParameter
     * @var $config array
     */
    $pluginParamValue = $params->get('JFusionPlugin');
    $pluginParamValue = unserialize(base64_decode($pluginParamValue));
    $jname = $pluginParamValue['jfusionplugin'];

	$view = $params->get('view','auto');

	if(JFusionFunction::validPlugin($jname)) {
		if($view == 'auto') {
			//configuration
			$config['itemid'] = $params->get('itemid');
			$config['avatar'] = $params->get('avatar',false);
			$config['avatar_software'] = $params->get('avatar_software', 'jfusion');
			$config['avatar_height'] = $params->get('avatar_height',40);
			$config['avatar_width'] = $params->get('avatar_width',30);
			$config['avatar_location'] = $params->get('avatar_location','top');
			$config['pmcount'] = $params->get('pmcount',false);
			$config['viewnewmessages'] = $params->get('viewnewmessages',false);
			$config['login_msg'] = $params->get('login_msg');
			$config['alignment'] = $params->get('alignment','center');
			$config['avatar_keep_proportional'] = $params->get('avatar_keep_proportional',false);

			if ($params->get('new_window',false)) {
				$config['new_window'] = '_blank';
			} else {
			    $config['new_window'] = '_self';
			}

			$joomlaUser = JFactory::getUser();

			if(!$joomlaUser->guest) {
				$output = modjfusionUserActivityHelper::prepareAutoOutput($jname, $config, $params);
			}

			require(JModuleHelper::getLayoutPath('mod_jfusion_user_activity'));
		} else {
			$public =& JFusionFactory::getPublic($jname);
			if (JFusionFunctionAdmin::methodDefined($public, 'renderUserActivityModule')) {
				$output = $public->renderUserActivityModule($config, $view, $params);
				echo $output;
			} else {
				echo JText::_('NOT_IMPLEMENTED_YET');
			}
        }
	} else {
		if (empty($jname)) {
			echo JText::_('MODULE_NOT_CONFIGURED');
		} else {
	    	echo JText::_('NO_PLUGIN');
		}
	}
} else {
    echo JText::_('NO_COMPONENT');
}