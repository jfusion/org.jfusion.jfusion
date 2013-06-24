<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * load the Abstract Auth Class
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.abstractauth.php';
/**
 * JFusion Authentication Class for Magento 1.1
 * For detailed descriptions on these functions please check the model.abstractauth.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_magento extends JFusionAuth {

	function getJname()
	{
		return 'magento';
	}



	/**
	 * @param array|object $userinfo
	 * @return string
	 */

	function generateEncryptedPassword($userinfo) {
		$params = JFusionFactory::getParams($this->getJname());
		$magentoVersion = $params->get('magento_version','1.7');
		if (version_compare($this->integration_version_number,'1.8','<')) {
			if ($userinfo->password_salt) {
				$hash = md5($userinfo->password_salt . $userinfo->password_clear);
			} else {
				$hash = md5($userinfo->password_clear);
			}

		} else {
			if ($userinfo->password_salt) {
				$hash = hash("sha256",$userinfo->password_salt . $userinfo->password_clear);
			} else {
				$hash = hash("sha256",$userinfo->password_clear);
			}

		}
		return $hash;
	}
}
