<?php

/**
 * file containing administrator function for the jfusion plugin
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaInt 
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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.joomlaadmin.php';

/**
 * JFusion Admin class for the internal Joomla database
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaInt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_joomla_int extends JFusionJoomlaAdmin {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'joomla_int';
    }

    /**
     * Get an usergroup element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string $node         node of element
     * @param string $control_name name of controller
     *
     * @return string html
     */
    function usergroup($name, $value, $node, $control_name)
    {
	    $value = $this->params->get('multiusergroup');
	    return parent::multiusergroup('multiusergroup', $value, $node, $control_name);
    }

	/**
	 * @return bool do the plugin support multi instance
	 */
	function multiInstance()
	{
		return false;
	}
}
