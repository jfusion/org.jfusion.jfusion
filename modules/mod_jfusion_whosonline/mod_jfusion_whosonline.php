<?php
/**
 * This is the whos online helper file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Whosonline
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
* Include the helper file
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
	$pluginParamValue = $params->get('JFusionPluginParam');
	$pluginParamValue = unserialize(base64_decode($pluginParamValue));

	if(is_array($pluginParamValue)) {
		$outputs = array();

		foreach($pluginParamValue as $jname => $value) {
			$pluginParam = new JParameter('');
			$pluginParam->loadArray($value);
			$view = $pluginParam->get('view', 'auto');
			if(JFusionFunction::validPlugin($jname)) {
				$public =& JFusionFactory::getPublic($jname);

				$output = new stdClass();
				$title = $pluginParam->get('title', NULL);
				$output->title = $title;

				if($view == 'auto') {
					// configuration
					$config = array();
					$config['showmode'] = intval($pluginParam->get('showmode'));
					$config['member_limit'] = $pluginParam->get('member_limit');
					$config['group_limit'] = $pluginParam->get('group_limit','');
					$config['group_limit_mode'] = $pluginParam->get('group_limit_mode','');

					//overwrite the group limit if the mode is set to display all
					$config['group_limit'] = (!empty($config['group_limit_mode'])) ? $config['group_limit'] : '';

					$config['show_total_users'] = $pluginParam->get('show_total_users', 0);
					$config['name'] = $pluginParam->get('name');
					$config['userlink'] = intval($pluginParam->get('userlink'),false);
					$config['userlink_software'] = $pluginParam->get('userlink_software',false);
					$config['userlink_custom'] = $pluginParam->get('userlink_custom',false);
					$config['itemid'] = $pluginParam->get('itemid');
					$config['avatar'] = $pluginParam->get('avatar',false);
					$config['avatar_height'] = $pluginParam->get('avatar_height',53);
					$config['avatar_width'] = $pluginParam->get('avatar_width',40);
					$config['avatar_software'] = $pluginParam->get('avatar_software','jfusion');
					$config['avatar_keep_proportional'] = $pluginParam->get('avatar_keep_proportional',false);
					$db =& JFusionFactory::getDatabase($jname);
					$query = $public->getOnlineUserQuery($config['member_limit'], $config['group_limit']);
					$db->setQuery($query);
					$output->online_users = $db->loadObjectList();

					modjfusionWhosOnlineHelper::appendAutoOutput($jname, $config, $params, $output);
				} else {
					if (JFusionFunctionAdmin::methodDefined($public, 'renderWhosOnlineModule')) {
						$output->custom_output = $public->renderWhosOnlineModule($config, $view, $pluginParam);
					} else {
						$output->error = JText::_('NOT_IMPLEMENTED_YET');
					}
				}

				$outputs[] = $output;
			}
		}

		require(JModuleHelper::getLayoutPath('mod_jfusion_whosonline'));

	} else {
		echo JText::_('MODULE_NOT_CONFIGURED');
	}
} else {
    echo JText::_('NO_COMPONENT');
}