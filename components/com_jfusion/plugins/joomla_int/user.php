<?php

/**
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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'model.joomlauser.php';

//require the standard joomla user functions
jimport('joomla.user.helper');

/**
 * JFusion User Class for the internal Joomla database
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaInt
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_joomla_int extends JFusionJoomlaUser {
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'joomla_int';
    }

    /**
     * @param object $userinfo
     *
     * @return array
     */
    function deleteUser($userinfo) {
        //get the database ready
        $db = JFactory::getDBO();
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());
        $username = $userinfo->username;
        //since the jfusion_user table will be updated to the user's email if they use it as an identifier, we must check for both the username and email

	    $query = $db->getQuery(true)
		    ->select('id')
		    ->from('#__jfusion_users')
		    ->where('username = ' . $db->Quote($username), 'OR')
		    ->where('LOWER(username) = ' . $db->Quote(strtolower($userinfo->email)));

        $db->setQuery($query);
        $userid = $db->loadResult();
        if ($userid) {
            //this user was created by JFusion and we need to delete them from the joomla user and jfusion lookup table
            $user = JUser::getInstance($userid);
            $user->delete();

	        $query = $db->getQuery(true)
		        ->delete('#__jfusion_users_plugin')
		        ->where('id = ' . (int)$userid);

            $db->setQuery($query);
            $db->execute();

	        $query = $db->getQuery(true)
		        ->delete('#__jfusion_users')
		        ->where('id = ' . (int)$userid);

	        $db->setQuery($query);
            $db->execute();
            $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
        } else {
            //this user was NOT create by JFusion. Therefore we need to delete it in the Joomla user table only

	        $query = $db->getQuery(true)
		        ->select('id')
		        ->from('#__users')
		        ->where('username  = ' . $db->Quote($username));

            $db->setQuery($query);
            $userid = $db->loadResult();
            if ($userid) {
                //just in case
	            $query = $db->getQuery(true)
		            ->delete('#__jfusion_users_plugin')
		            ->where('id = ' . (int)$userid);

	            $db->setQuery($query);
                $db->execute();
                //delete it from the Joomla usertable
                $user = JUser::getInstance($userid);
                $user->delete();
                $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
            } else {
                //could not find user and return an error
                $status['error'][] = JText::_('ERROR_DELETE') . $username;
            }
        }
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
	        jimport('joomla.user.helper');
	        $instance = JUser::getInstance();

	        // If _getUser returned an error, then pass it back.
	        if (!$instance->load($userinfo->userid)) {
		        $status['error'][] = JText::_('FUSION_ERROR_LOADING_USER');
	        } else {
		        // If the user is blocked, redirect with an error
		        if ($instance->get('block') == 1) {
			        $status['error'][] = JText::_('JERROR_NOLOGIN_BLOCKED');
		        } else {
			        // Authorise the user based on the group information
			        if (!isset($options['group'])) {
				        $options['group'] = 'USERS';
			        }

			        if (!isset($options['action'])) {
				        $options['action'] = 'core.login.site';
			        }

			        // Check the user can login.
			        $result	= $instance->authorise($options['action']);
			        if (!$result) {
				        $status['error'][] = JText::_('JERROR_LOGIN_DENIED');
			        } else {
				        // Mark the user as logged in
				        $instance->set('guest', 0);

				        // Register the needed session variables
				        $session = JFactory::getSession();
				        $session->set('user', $instance);

				        // Update the user related fields for the Joomla sessions table.
				        try {
					        $db = JFactory::getDBO();

					        $query = $db->getQuery(true)
						        ->update('#__session')
						        ->set('guest = '.$db->quote($instance->get('guest')))
						        ->set('username = '.$db->quote($instance->get('username')))
						        ->set('userid = '.$db->quote($instance->get('id')))
						        ->where('session_id = '.$db->quote($session->getId()));

					        $db->setQuery($query);
					        $db->execute();

					        // Hit the user last visit field
					        if ($instance->setLastVisit()) {
						        $status['debug'][] = 'Joomla session created';
					        } else {
						        $status['error'][] = 'Error Joomla session created';
					        }
				        } catch (Exception $e) {
					        $status['error'][] = $e->getMessage();
				        }
			        }
		        }
	        }
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
	    if (!isset($options['clientid'])) {
		    $mainframe = JFactory::getApplication();
		    if ($mainframe->isAdmin()) {
		        $options['clientid'] = array(1);
		    } else {
		        $options['clientid'] = array(0);
		    }
		} elseif (!is_array($options['clientid'])) {
		    //J1.6+ does not pass clientid as an array so let's fix that
		    $options['clientid'] = array($options['clientid']);
		}

	    if ($userinfo->id) {
		    $my = JFactory::getUser();
		    if ($my->id == $userinfo->id) {
			    // Hit the user last visit field
			    $my->setLastVisit();
			    // Destroy the php session for this user
			    $session = JFactory::getSession();
			    $session->destroy();
		    }
		    //destroy the Joomla session but do so directly based on what $options is
		    $table = JTable::getInstance('session');
		    $table->destroy($userinfo->id, $options['clientid']);
	    }
        return array();
    }
}
