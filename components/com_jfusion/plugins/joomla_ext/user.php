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
//require the standard joomla user functions
jimport('joomla.user.helper');
/**
 * JFusion User Class for an external Joomla database
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
class JFusionUser_joomla_ext extends JFusionUser {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */    
    function getJname() {
        return 'joomla_ext';
    }

    /**
     * @param object $userinfo
     * @param int $overwrite
     * @return array
     */
    function updateUser($userinfo, $overwrite) {
        $status = JFusionJplugin::updateUser($userinfo, $overwrite, $this->getJname());
        return $status;
    }
    function deleteUser($userinfo) {
        //get the database ready
        $db = & JFusionFactory::getDatabase($this->getJname());
        //setup status array to hold debug info and errors
        $status = array();
        $status['debug'] = array();
        $status['error'] = array();
        $username = $userinfo->username;
        $userid = $userinfo->userid;
        $query = 'DELETE FROM #__users WHERE id = ' . (int)$userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status["error"][] = JText::_('ERROR_DELETE') . ' ' . $username . ' ' . $db->stderr();
            return $status;
        }

        if (JFusionFunction::isJoomlaVersion('1.6',$this->getJname())) {
			$query = 'DELETE FROM #__user_profiles WHERE user_id = ' . (int)$userid;
	        $db->setQuery($query);
			$db->query();
			$query = 'DELETE FROM #__user_usergroup_map WHERE user_id = ' . (int)$userid;
	        $db->setQuery($query);
			$db->query();
        } else {
			$query = 'SELECT id FROM #__core_acl_aro WHERE value = ' . (int)$userid;
	        $db->setQuery($query);
	        $aroid = $db->loadResult();
	        if ($aroid != '') {
	            $query = 'DELETE FROM #__core_acl_aro WHERE value = ' . (int)$userid;
	            $db->setQuery($query);
	            if (!$db->query()) {
	                $status["error"][] = JText::_('ERROR_DELETE') . ' ' . $username . ' ' . $db->stderr();
	            }
	            $query = 'DELETE FROM #__core_acl_groups_aro_map WHERE aro_id = ' . (int)$aroid;
	            $db->setQuery($query);
	            if (!$db->query()) {
	                $status["error"][] = JText::_('ERROR_DELETE') . ' ' . $username . ' ' . $db->stderr();
	            }
	        }
        }
        $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
        return $status;
    }
    function &getUser($userinfo) {
        $userinfo = JFusionJplugin::getUser($userinfo, $this->getJname());
        return $userinfo;
    }
    function filterUsername($username) {
        $username = JFusionJplugin::filterUsername($username, $this->getJname());
    }
    function destroySession($userinfo, $options) {
    	$params = & JFusionFactory::getParams($this->getJname());
        $status = JFusionJplugin::destroySession($userinfo, $options, $this->getJname(),$params->get('logout_type'));
        return $status;
    }
    function createSession($userinfo, $options) {
    	$params = & JFusionFactory::getParams($this->getJname());
    	$status = JFusionJplugin::createSession($userinfo, $options, $this->getJname(),$params->get('brute_force'));
        return $status;
    }
    function updateUsergroup($userinfo, $existinguser, &$status) {
        JFusionJplugin::updateUsergroup($userinfo, $existinguser, $status, $this->getJname());
    }
    function updatePassword($userinfo, &$existinguser, &$status) {
        JFusionJplugin::updatePassword($userinfo, $existinguser, $status, $this->getJname());
    }
    function updateUsername($userinfo, &$existinguser, &$status) {
        JFusionJplugin::updateUsername($userinfo, $existinguser, $status, $this->getJname());
    }
    // @todo - To implement after the RC 1.1.2
    function updateUserLanguage($userinfo, &$existinguser, &$status) {
        JFusionJplugin::updateUserLanguage($userinfo, $existinguser, $status, $this->getJname());
    }
    function updateEmail($userinfo, &$existinguser, &$status) {
        JFusionJplugin::updateEmail($userinfo, $existinguser, $status, $this->getJname());
    }
    function blockUser($userinfo, &$existinguser, &$status) {
        JFusionJplugin::blockUser($userinfo, $existinguser, $status, $this->getJname());
    }
    function unblockUser($userinfo, &$existinguser, &$status) {
        JFusionJplugin::unblockUser($userinfo, $existinguser, $status, $this->getJname());
    }
    function activateUser($userinfo, &$existinguser, &$status) {
        JFusionJplugin::activateUser($userinfo, $existinguser, $status, $this->getJname());
    }
    function inactivateUser($userinfo, &$existinguser, &$status) {
        JFusionJplugin::inactivateUser($userinfo, $existinguser, $status, $this->getJname());
    }
    function createUser($userinfo, &$status) {
        JFusionJplugin::createUser($userinfo, $status, $this->getJname());
    }
}
