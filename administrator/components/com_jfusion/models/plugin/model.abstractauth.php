<?php

/**
 * abstract authentication file
 * 
 * PHP version 5
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'model.abstractplugin.php';

/**
 * Abstract interface for all JFusion auth implementations.
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionAuth extends JFusionPlugin
{
	var $helper;

	/**
	 *
	 */
	function __construct()
	{
		parent::__construct();
		//get the helper object
		$this->helper = JFusionFactory::getHelper($this->getJname());
	}

    /**
     * Generates an encrypted password based on the userinfo passed to this function
     *
     * @param object $userinfo userdata object containing the userdata
     * 
     * @return string Returns generated password
     */
    function generateEncryptedPassword($userinfo) 
    {
        return '';
    }

	/**
	 * used by framework to ensure a password test
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return boolean
	 */
	function checkPassword($userinfo) {
		return ($userinfo->password == $this->generateEncryptedPassword($userinfo));
	}
}
