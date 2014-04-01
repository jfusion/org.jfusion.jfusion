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
 * load the common Joomla JFusion plugin functions
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';

/**
 * JFusion Authentication Class for an external Joomla database
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_joomla_ext extends JFusionAuth {
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
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo) {
        return JFusionJplugin::generateEncryptedPassword($userinfo);
    }

	/**
	 * used by framework to ensure a password test
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return boolean
	 */
	function checkPassword($userinfo) {
		$rehash = false;
		$match = false;

		jimport('joomla.user.helper');
		// If we are using phpass
		if (strpos($userinfo->password, '$P$') === 0) {
			jimport('phpass.passwordhash');
			// Use PHPass's portable hashes with a cost of 10.
			$phpass = new PasswordHash(10, true);

			$match = $phpass->CheckPassword($userinfo->password_clear, $userinfo->password);
		} elseif (substr($userinfo->password, 0, 8) == '{SHA256}') {
			// Check the password
			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'sha256', true);

			$match = $this->comparePassword($userinfo->password, $testcrypt);

			$rehash = true;
		} else {
			$testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt, 'md5-hex', false);

			$match = $this->comparePassword($userinfo->password, $testcrypt);

			$rehash = true;
		}

		// If we have a match and rehash = true, rehash the password with the current algorithm.
		if ($match && $rehash) {
			$user = JFusionFactory::getUser($this->getJname());
			$old = $user->getUser($userinfo);
			if ($old) {
				$status = array('error' => array(), 'debug' => array());
				$user->updatePassword($userinfo, $old, $status);
			}
		}
		return $match;
	}
}
