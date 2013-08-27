<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_oscommerce extends JFusionPublic
{
	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'oscommerce';
	}

    /**
     * @return string
     */
    function getRegistrationURL() {
		$osCversion = $this->params->get('osCversion');
		switch ($osCversion) {
			case 'osc2':
			case 'osc3':
			case 'oscxt':
			case 'oscmax':
			case 'oscseo':
				return 'login';
			case 'osczen':
				return 'index.php?main_page=login';
		}
        return 'index.php';
	}

    /**
     * @return string
     */
    function getLostPasswordURL() {
		$osCversion = $this->params->get('osCversion');
		switch ($osCversion) {
			case 'osc2':
			case 'oscmax':
				return 'password_forgotten';
			case 'osc3':
				return 'account.php?password_forgotten';
			case 'osczen':
				return 'index.php?main_page=password_forgotten';
			case 'oscxt':
			case 'oscseo':
				return 'cpassword_double_opt.php';
		}
        return 'index.php';
	}

    /**
     * @return string
     */
    function getLostUsernameURL() {
		$osCversion = $this->params->get('osCversion');
		switch ($osCversion) {
			case 'osc2':
			case 'oscmax':
				return 'password_forgotten'; //  not supported
			case 'osc3':
				return 'account.php?password_forgotten';
			case 'osczen':
				return 'index.php?main_page=password_forgotten';
			case 'oscxt':
			case 'oscseo':
				return 'cpassword_double_opt.php';
		}
        return 'index.php?';
	}
}