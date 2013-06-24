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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jplugin.php';

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
        /**
         * @ignore
         * @var $params JParameter
         */
		$params = JFusionFactory::getParams($this->getJname());
		$joomlaversion = $params->get('joomlaversion','');
		if (empty($joomlaversion)) {
            $db = JFusionFactory::getDatabase($this->getJname());
            if (!$db) {
                $joomlaversion = '1.6';
            } else {
                $query = 'SELECT id, name FROM #__core_acl_aro_groups WHERE name != \'ROOT\' AND name != \'USERS\'';
                $db->setQuery($query);
                $result = $db->loadObjectList();
                if ($result) {
                    $joomlaversion = '1.5';
                } else {
                    $joomlaversion = '1.6';
                }
            }
		}
		return $joomlaversion;
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
		$params = JFusionFactory::getParams($this->getJname());
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
