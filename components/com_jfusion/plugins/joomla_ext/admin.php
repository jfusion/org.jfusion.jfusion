<?php

/**
 * file containing administrator function for the jfusion plugin
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
 * JFusion Admin Class for an external Joomla database.
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class JFusionAdmin_joomla_ext extends JFusionAdmin
{
	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */

	function getVersion() {
		// find out what Joomla version we have
		$params = & JFusionFactory::getParams($this->getJname());
		$joomlaversion = $params->get('joomlaversion','');
		if (!empty($joomlaversion)) {
			return $params->get('joomlaversion');
		}

		$db = & JFusionFactory::getDatabase($this->getJname());
		if (!$db) {
			return '1.6';
		}
		$query = 'SELECT id, name FROM #__core_acl_aro_groups WHERE name != "ROOT" AND name != "USERS"';
		$db->setQuery($query);
		$result = $db->loadObjectList();
		if ($result) {
			return '1.5';
		}
		return '1.6';
	}

    /**
     * @return string
     */
    function getJname()
	{
		return 'joomla_ext';
	}

    /**
     * @return string
     */
    function getTablename() {
		return JFusionJplugin::getTablename();
	}

    /**
     * @return array
     */
    function getUserList() {
		return JFusionJplugin::getUserList($this->getJname());
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
		$params = & JFusionFactory::getParams($this->getJname());
		return JFusionJplugin::getDefaultUsergroup($this->getJname());
	}

    /**
     * @param string $path
     * @return array
     */
    function setupFromPath($path) {
		return JFusionJplugin::setupFromPath($path);
	}

    /**
     * @return bool
     */
    function allowRegistration() {
		return JFusionJplugin::allowRegistration($this->getJname());
	}

    function debugConfig() {
		$jname = $this->getJname();
		//get registration status
		$new_registration = $this->allowRegistration();
		//get the data about the JFusion plugins
		$db = JFactory::getDBO();
		$query = 'SELECT * from #__jfusion WHERE name = ' . $db->Quote($jname);
		$db->setQuery($query);
		$plugin = $db->loadObject();
		//output a warning to the administrator if the allowRegistration setting is wrong
		if ($new_registration && $plugin->slave == '1') {
			JError::raiseNotice(0, $jname . ': ' . JText::_('DISABLE_REGISTRATION'));
		}
		if (!$new_registration && $plugin->master == '1') {
			JError::raiseNotice(0, $jname . ': ' . JText::_('ENABLE_REGISTRATION'));
		}
		//check that master plugin does not have advanced group mode data stored
		$master = JFusionFunction::getMaster();
		$params = & JFusionFactory::getParams($jname);
		if (!empty($master) && $master->name == $jname && substr($params->get('usergroup'), 0, 2) == 'a:') {
			JError::raiseWarning(0, $jname . ': ' . JText::_('ADVANCED_GROUPMODE_ONLY_SUPPORTED_FORSLAVES'));
		}
	}

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JNO';
	}	
}
