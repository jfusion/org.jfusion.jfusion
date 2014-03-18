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
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helper.php');

//check if the JFusion component is installed
$factory_file = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
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
		$pluginParamValue = $params->get('JFusionPluginParam');
		$pluginParamValue = unserialize(base64_decode($pluginParamValue));

		if(is_array($pluginParamValue)) {
			$outputs = array();

			foreach($pluginParamValue as $jname => $value) {
				$pluginParam = new JRegistry('');
				$pluginParam->loadArray($value);
				$view = $pluginParam->get('view', 'auto');

				$public = \JFusion\Factory::getPublic($jname);
				if($public->isConfigured()) {
					$output = new stdClass();
					$title = $pluginParam->get('title', NULL);
					$output->title = $title;

					// configuration
					$config = array();
					$config['showmode'] = intval($pluginParam->get('showmode'));
					$config['member_limit'] = (int)$pluginParam->get('member_limit', 0);

					$config['group_limit'] = $pluginParam->get('group_limit', '');
					$config['group_limit_mode'] = $pluginParam->get('group_limit_mode', '');

					//overwrite the group limit if the mode is set to display all
					$config['group_limit'] = (!empty($config['group_limit_mode'])) ? $config['group_limit'] : array();

					$config['name'] = $pluginParam->get('name');
					$config['userlink'] = intval($pluginParam->get('userlink'), false);
					$config['userlink_software'] = $pluginParam->get('userlink_software', false);
					$config['userlink_custom'] = $pluginParam->get('userlink_custom', false);
					$config['itemid'] = $pluginParam->get('itemid');
					$config['avatar'] = $pluginParam->get('avatar', false);
					$config['avatar_height'] = $pluginParam->get('avatar_height', 53);
					$config['avatar_width'] = $pluginParam->get('avatar_width', 40);
					$config['avatar_software'] = $pluginParam->get('avatar_software', 'jfusion');
					$config['avatar_keep_proportional'] = $pluginParam->get('avatar_keep_proportional', false);

					if (empty($config['itemid'])) {
						throw new RuntimeException(JText::_('NO_ITEMID_SELLECTED'));
					}

					if($view == 'auto') {
						$db = \JFusion\Factory::getDatabase($jname);
						$query = $public->getOnlineUserQuery($config['group_limit']);
						$db->setQuery($query, 0, $config['member_limit']);
						$output->online_users = $db->loadObjectList();

						modjfusionWhosOnlineHelper::appendAutoOutput($jname, $config, $params, $output);
					} else {
						if ($public->methodDefined('renderWhosOnlineModule')) {
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
			throw new RuntimeException(JText::_('MODULE_NOT_CONFIGURED'));
		}
	} else {
		throw new RuntimeException(JText::_('NO_COMPONENT'));
	}
} catch (Exception $e) {
	\JFusion\Framework::raiseError($e, 'mod_jfusion_whosonline');
	echo $e->getMessage();
}