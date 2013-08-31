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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.joomlauser.php';
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
class JFusionUser_joomla_ext extends JFusionJoomlaUser {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */    
    function getJname() {
        return 'joomla_ext';
    }

    /**
     * @param object $userinfo
     * @return array
     */
    function deleteUser($userinfo) {
	    try {
	        //get the database ready
	        $db = JFusionFactory::getDatabase($this->getJname());
	        //setup status array to hold debug info and errors
	        $status = array('error' => array(),'debug' => array());
	        $userid = $userinfo->userid;

		    $query = $db->getQuery(true)
		        ->delete('#__users')
			    ->where('id = ' . (int)$userid);

	        $db->setQuery($query);
		    $db->execute();

		    $query = $db->getQuery(true)
			    ->delete('#__user_profiles')
			    ->where('user_id = ' . (int)$userid);

		    $db->setQuery($query);
		    $db->execute();

		    $query = $db->getQuery(true)
			    ->delete('#__user_usergroup_map')
			    ->where('user_id = ' . (int)$userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('USER_DELETION') . ' ' . $userinfo->username;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ERROR_DELETE') . ' ' . $userinfo->username . ' ' . $e->getMessage();
	    }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options) {
        $status = JFusionJplugin::destroySession($userinfo, $options, $this->getJname(),$this->params->get('logout_type'));
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * 
     * @return array
     */
    function createSession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
            $status = JFusionJplugin::createSession($userinfo, $options, $this->getJname(),$this->params->get('brute_force'));
        }
        return $status;
    }
}
