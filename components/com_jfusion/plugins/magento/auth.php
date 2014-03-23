<?php namespace JFusion\Plugins\magento;

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
use JFusion\Plugin\Plugin_Auth;

defined('_JEXEC') or die('Restricted access');

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
class Auth extends Plugin_Auth
{
	/**
	 * @param array|object $userinfo
	 * @return string
	 */

	function generateEncryptedPassword($userinfo) {
        $magentoEncryption = $this->params->get('magento_encryption', 'MD5');
        if ($magentoEncryption == "MD5") {
			if ($userinfo->password_salt) {
				$hash = md5($userinfo->password_salt . $userinfo->password_clear);
			} else {
				$hash = md5($userinfo->password_clear);
			}

		} else {
			if ($userinfo->password_salt) {
				$hash = hash('sha256', $userinfo->password_salt . $userinfo->password_clear);
			} else {
				$hash = hash('sha256', $userinfo->password_clear);
			}

		}
		return $hash;
	}
}
