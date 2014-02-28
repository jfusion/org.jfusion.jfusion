<?php

/**
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
 * JFusion Authentication Class for an external Joomla database
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_joomla_ext extends JFusionAuth
{
	/**
	 * @var $helper JFusionHelper_joomla_ext
	 */
	var $helper;

	/**
	 * returns the name of this JFusion plugin
	 *
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'joomla_ext';
	}

	/**
	 * Generates an encrypted password based on the userinfo passed to this function
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return string Returns generated password
	 */
	public function generateEncryptedPassword($userinfo)
	{
		if (substr($userinfo->password, 0, 4) == '$2y$') {
			// BCrypt passwords are always 60 characters, but it is possible that salt is appended although non standard.
			$password60 = substr($userinfo->password, 0, 60);

			if (JCrypt::hasStrongPasswordSupport()) {
				$testcrypt = password_verify($userinfo->password_clear, $password60);
			} else {
				$testcrypt = null;
			}
		} elseif (substr($userinfo->password, 0, 8) == '{SHA256}') {
			jimport('joomla.user.helper');

			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'sha256', false);
		} else {
			jimport('joomla.user.helper');

			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'md5-hex', false);
		}
		return $testcrypt;
	}

	/**
	 * used by framework to ensure a password test
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return boolean
	 */
	function checkPassword($userinfo) {
		$match = false;
		if (substr($userinfo->password, 0, 4) == '$2y$') {
			// BCrypt passwords are always 60 characters, but it is possible that salt is appended although non standard.
			$password60 = substr($userinfo->password, 0, 60);

			if (JCrypt::hasStrongPasswordSupport()) {
				$match = password_verify($userinfo->password_clear, $password60);
			}
		} elseif (substr($userinfo->password, 0, 8) == '{SHA256}') {
			jimport('joomla.user.helper');

			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'sha256', false);

			if ($userinfo->password == $testcrypt) {
				$match = true;
			}
		} else {
			jimport('joomla.user.helper');

			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'md5-hex', false);

			if ($userinfo->password == $testcrypt) {
				$match = true;
			}
		}
		return $match;
	}
}
