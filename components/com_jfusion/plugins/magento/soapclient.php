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

if (!class_exists('MagentoSoapClient')) {
	/**
	 * Class MagentoSoapClients
	 *
	 */
	class MagentoSoapClient extends SoapClient {
		/**
		 * @var $sessionId
		 */
		private $sessionId;

		/**
		 * @param string $user
		 * @param string $key
		 *
		 * @return bool
		 */
		function login($user, $key) {
			/** @noinspection PhpUndefinedMethodInspection */
			$sessionid = parent::login($user, $key);
			return true;
		}

		/**
		 * End session
		 */
		function endSession() {
			/** @noinspection PhpUndefinedMethodInspection */
			parent::endSession($this->sessionId);
		}

		/**
		 * @param string $function
		 * @param array $args
		 *
		 * @return mixed
		 */
		function call($function, $args) {
			/** @noinspection PhpUndefinedMethodInspection */
			return parent::call($this->sessionId, $function, $args);
		}
	}
}

