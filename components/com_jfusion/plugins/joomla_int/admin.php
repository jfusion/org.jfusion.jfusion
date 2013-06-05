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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';

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

class JFusionAdmin_joomla_int extends JFusionAdmin {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'joomla_int';
    }

    /**
     * @return string
     */
    function getTablename() {
        return JFusionJplugin::getTablename();
    }

    /**
     * Returns the a list of users of the integrated software
     *
     * @param int $limitstart start at
     * @param int $limit number of results
     *
     * @return array
     *
     */
    function getUserList($limitstart = 0, $limit = 0) {
        return JFusionJplugin::getUserList($this->getJname(),$limitstart,$limit);
    }

    /**
     * @return int
     */
    function getUserCount() {
        return JFusionJplugin::getUserCount($this->getJname());
    }

    /**
     * @return array
     */
    function getUsergroupList() {
        return JFusionJplugin::getUsergroupList($this->getJname());
    }

    /**
     * @return string
     */
    function getDefaultUsergroup() {
        return JFusionJplugin::getDefaultUsergroup($this->getJname());
    }

    /**
     * @return bool
     */
    function allowRegistration() {
        return JFusionJplugin::allowRegistration($this->getJname());
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
        $params = JFusionFactory::getParams($this->getJname());
        
        if(JFusionFunction::isJoomlaVersion('1.6')) {
			$value = $params->get('multiusergroup');
        	return parent::multiusergroup('multiusergroup', $value, $node, $control_name);
        } else {
        	return parent::usergroup($name, $value, $node, $control_name);
        }
    }

    /**
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JNO';
	}

	/**
	 * @return bool do the plugin support multi instance
	 */
	function multiInstance()
	{
		return false;
	}
}
