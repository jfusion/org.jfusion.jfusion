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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';

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
class JFusionUser_joomla_int extends JFusionUser {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'joomla_int';
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

    /**
     * @param object $userinfo
     * @return array
     */
    function deleteUser($userinfo) {
        //get the database ready
        $db = JFactory::getDBO();
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());
        $username = $userinfo->username;
        //since the jfusion_user table will be updated to the user's email if they use it as an identifier, we must check for both the username and email
        $query = 'SELECT id FROM #__jfusion_users WHERE username=' . $db->Quote($username) . ' OR LOWER(username)=' . strtolower($db->Quote($userinfo->email));
        $db->setQuery($query);
        $userid = $db->loadResult();
        if ($userid) {
            //this user was created by JFusion and we need to delete them from the joomla user and jfusion lookup table
            $user = JUser::getInstance($userid);
            $user->delete();
            $db->setQuery('DELETE FROM #__jfusion_users_plugin WHERE id = ' . (int)$userid);
            $db->query();
            $db->setQuery('DELETE FROM #__jfusion_users WHERE id=' . (int)$userid);
            $db->query();
            $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
        } else {
            //this user was NOT create by JFusion. Therefore we need to delete it in the Joomla user table only
            $query = 'SELECT id from #__users WHERE username = ' . $db->Quote($username);
            $db->setQuery($query);
            $userid = $db->loadResult();
            if ($userid) {
                //just in case
                $db->setQuery('DELETE FROM #__jfusion_users_plugin WHERE id = ' . (int)$userid);
                $db->query();
                //delete it from the Joomla usertable
                $user = JUser::getInstance($userid);
                $user->delete();
                $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
            } else {
                //could not find user and return an error
                //JError::raiseWarning(0, JText::_('ERROR_DELETE') . $username);
                $status['error'][] = JText::_('ERROR_DELETE') . $username;
            }
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @return null|object
     */
    function getUser($userinfo) {
        $userinfo = JFusionJplugin::getUser($userinfo, $this->getJname());
        return $userinfo;
    }

    /**
     * @param string $username
     * @return string
     */
    function filterUsername($username) {
        $username = JFusionJplugin::filterUsername($username, $this->getJname());
        return $username;
    }

    /**
     * @param $userinfo
     * @param $options
     * @return array
     */
    function createSession16($userinfo, $options) {

    	jimport('joomla.user.helper');
    	$instance = JUser::getInstance();
		$instance->load($userinfo->userid);

		// If _getUser returned an error, then pass it back.
		if (JError::isError($instance)) {
            $status['error'] = $instance;
		} else {
            // If the user is blocked, redirect with an error
            if ($instance->get('block') == 1) {
                $status['error'] = JText::_('JERROR_NOLOGIN_BLOCKED');
            } else {
                // Authorise the user based on the group information
                if (!isset($options['group'])) {
                    $options['group'] = 'USERS';
                }

                if (!isset($options['action'])) {
                    $options['action'] = 'core.login.site';
                }

                // Chek the user can login.
                $result	= $instance->authorise($options['action']);
                if (!$result) {
                    $status['error'] = JText::_('JERROR_LOGIN_DENIED');
                } else {
                    // Mark the user as logged in
                    $instance->set('guest', 0);

                    // Register the needed session variables
                    $session = JFactory::getSession();
                    $session->set('user', $instance);

                    // Update the user related fields for the Joomla sessions table.
                    $db = JFactory::getDBO();
                    $db->setQuery(
                        'UPDATE `#__session`' .
                            ' SET `guest` = '.$db->quote($instance->get('guest')).',' .
                            '	`username` = '.$db->quote($instance->get('username')).',' .
                            '	`userid` = '.(int) $instance->get('id') .
                            ' WHERE `session_id` = '.$db->quote($session->getId())
                    );
                    $db->query();

                    // Hit the user last visit field
                    if ($instance->setLastVisit()) {
                        $status['debug'] = 'Joomla session created';
                    } else {
                        $status['error'] = $instance->getError();
                    }
                }
            }
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array
     */
    function createSession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
            if(JFusionFunction::isJoomlaVersion('1.6')){
                //joomla 1.6 detected
                //use new create session function
                $status = $this->createSession16($userinfo, $options);
            } else {
                //initalise some objects
                $acl = JFactory::getACL();
                $instance = JUser::getInstance($userinfo->userid);
                $grp = $acl->getAroGroup($userinfo->userid);

                //Authorise the user based on the group information
                if (!isset($options['group'])) {
                    $options['group'] = 'USERS';
                }

                //reject the session if the user is in a public group
                if ($grp->id == 29 || $grp->id == 30) {
                    //report back error
                    $status['error'] = JText::sprintf('JOOMLA_INT_ACCESS_DENIED', $grp->name, $options['group']);
                } else {
                    if (!$acl->is_group_child_of($grp->name, $options['group'])) {
                        //report back error
                        $status['error'] = JText::sprintf('JOOMLA_INT_ACCESS_DENIED', $grp->name, $options['group']);
                    } else {
                        //Mark the user as logged in
                        $instance->set('guest', 0);
                        $instance->set('aid', 1);
                        // Fudge Authors, Editors, Publishers and Super Administrators into the special access group
                        if ($acl->is_group_child_of($grp->name, 'Registered') || $acl->is_group_child_of($grp->name, 'Public Backend')) {
                            $instance->set('aid', 2);
                        }
                        //Set the usertype based on the ACL group name
                        $instance->set('usertype', $grp->name);
                        // Register the needed session variables
                        $session = JFactory::getSession();
                        $session->set('user', $instance);
                        //$session->set('referer', $_SERVER['HTTP_REFERER']);
                        //$session->set('ip_address', $_SERVER['REMOTE_ADDR']);
                        //$session->set('time', time());
                        //$session->set('query', $_SERVER['QUERY_STRING']);
                        //$session->set('filename', $_SERVER['SCRIPT_FILENAME']);

                        //JError::raiseNotice('500',$session->getId());

                        /**
                         * @ignore
                         * @var $table JTableSession
                         */
                        $table = JTable::getInstance('session');
                        $table->load($session->getId());
                        $table->guest = $instance->get('guest');
                        $table->username = $instance->get('username');
                        $table->userid = intval($instance->get('id'));
                        $table->usertype = $instance->get('usertype');
                        $table->gid = intval($instance->get('gid'));
                        $table->update();
                        // Hit the user last visit field
                        if ($instance->setLastVisit()) {
                            $status['debug'] = 'Joomla session created';
                        } else {
                            $status['error'] = $instance->getError();
                        }
                    }
                }
            }
        }
        return $status;
    }

    /**
     * @param object $user
     * @param array $options
     * @return array
     */
    function destroySession($user, $options) {
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

        //destroy the Joomla session but do so directly based on what $options is
        $table = JTable::getInstance('session');
        $table->destroy($user['id'], $options['clientid']);
        $my = JFactory::getUser();
        if ($my->get('id') == $user['id']) {
            // Hit the user last visit field
            $my->setLastVisit();
            // Destroy the php session for this user
            $session = JFactory::getSession();
            $session->destroy();
        } else {
            // Force logout all users with that userid
            $table = JTable::getInstance('session');
            $table->destroy($user['id'], $options['clientid']);
        }
        return array();
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updateUsergroup($userinfo, $existinguser, &$status) {
        JFusionJplugin::updateUsergroup($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updatePassword($userinfo, &$existinguser, &$status) {
        JFusionJplugin::updatePassword($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updateUsername($userinfo, &$existinguser, &$status) {
        JFusionJplugin::updateUsername($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @todo - To implement after the RC 1.1.2
     *
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updateUserLanguage($userinfo, &$existinguser, &$status) {
        JFusionJplugin::updateUserLanguage($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updateEmail($userinfo, &$existinguser, &$status) {
        JFusionJplugin::updateEmail($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function blockUser($userinfo, &$existinguser, &$status) {
        JFusionJplugin::blockUser($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function unblockUser($userinfo, &$existinguser, &$status) {
        JFusionJplugin::unblockUser($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function activateUser($userinfo, &$existinguser, &$status) {
        JFusionJplugin::activateUser($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function inactivateUser($userinfo, &$existinguser, &$status) {
        JFusionJplugin::inactivateUser($userinfo, $existinguser, $status, $this->getJname());
    }

    /**
     * @param object $userinfo
     * @param array $status
     */
    function createUser($userinfo, &$status) {
        JFusionJplugin::createUser($userinfo, $status, $this->getJname());
    }
}
