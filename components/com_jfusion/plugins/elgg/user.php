<?php

/**
 * JFusion User Class for elgg
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Elgg 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion User Class for Elgg
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Elgg 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_elgg extends JFusionUser {
    /**
     * @param object $userinfo
     *
     * @return null|object
     */
    function getUser($userinfo) {
        //get the identifier
        $identifier = $userinfo;
        if (is_object($userinfo)) {
            $identifier = $userinfo->username;
        }
        // Get user info from database
        $db = JFusionFactory::getDatabase($this->getJname());

        $query = 'SELECT guid as userid, username, name, name as lastname, email, password, salt as password_salt,banned as block FROM #__users_entity WHERE username = ' . $db->Quote($identifier);
        $db->setQuery($query);
        $result = $db->loadObject();
        
        if ($result) {
	        $params = JFusionFactory::getParams($this->getJname());
	
	    	if (defined('externalpage')) {
	        	define('externalpage', true);	
	        }
	        require_once $params->get('source_path') . DS . 'engine' . DS . 'start.php';
	        // Get variables
	        global $CONFIG;
        
	        $user = get_user_by_username($userinfo->username);
	        if ($result->block == 'no') {
	        	$result->block = 0;
	        } else {
	        	$result->block = 1;
	        }
	        if ((!$user->isAdmin()) && (!$user->validated) && (!$user->admin_created)) {
	        	$result->activation = md5($result->userid . $result->email . $CONFIG->site->url . get_site_secret());	
	        } else {
	        	$result->activation = '';
	        }	
        }
        return $result;
    }

    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */    
    function getJname() 
    {
        return 'elgg';
    }

    /**
     * @param object $userinfo
     *
     * @return array
     */
    function deleteUser($userinfo) {
        $params = JFusionFactory::getParams($this->getJname());

    	if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $params->get('source_path') . DS . 'engine' . DS . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($userinfo->username);
        if($user) {
        	if ($user->delete()) {
            	$status['debug'][] = JText::_('USER_DELETION') . ' ' . $userinfo->username;
        	} else {
        		$status['error'][] = JText::_('USER_DELETION_ERROR');
        	}
        } else {
        	$status['error'][] = JText::_('USER_DELETION_ERROR');
		}
		return $status;
    }

    /**
     * @param object $userinfo
     * @param array $option
     *
     * @return array
     */
    function destroySession($userinfo, $option) {
        $status = array('error' => array(),'debug' => array());
        /*
        NOTE:
        !Can not include elgg engine and use core elgg logout functions since it conflicts with Community Builder Logout function!
        unsetting the elgg cookies has been problematic as well.
        */
        $params = JFusionFactory::getParams($this->getJname());
        $expire = -3600;
        $status['debug'][] = JFusionFunction::addCookie('Elgg', '', $expire, $params->get('cookie_path'), $params->get('cookie_domain'));
        $status['debug'][] = JFusionFunction::addCookie('elggperm', '', $expire, '/', $params->get('cookie_domain'));
        return array();
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @param bool $framework
     *
     * @return array
     */
    function createSession($userinfo, $options, $framework = true) {
        //destroy a cookie if it exists already, this will prevent the person logging in from having to refresh twice to appear as logged in
        $this->destroySession(null,null);
        $status = array('error' => array(),'debug' => array());

        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
            $params = JFusionFactory::getParams($this->getJname());

            if (defined('externalpage')) {
                define('externalpage', true);
            }
            require_once $params->get('source_path') . DS . 'engine' . DS . 'start.php';
            // Get variables
            global $CONFIG;
            // Action Gatekeep not necessary as person should already be validated by Joomla!
            //action_gatekeeper();
            //Get username and password
            $username = $userinfo->username;
            $password = $userinfo->password_clear;
            $persistent = true;
            // If all is present and correct, try to log in
            $result = false;
            if (!empty($username) && !empty($password)) {
                $user = authenticate($username, $password);
                if ($user) {
                    //if ($user->isBanned()) return false; // User is banned, return false.
                    $_SESSION['user'] = $user;
                    $_SESSION['guid'] = $user->getGUID();
                    $_SESSION['id'] = $_SESSION['guid'];
                    $_SESSION['username'] = $user->username;
                    $_SESSION['name'] = $user->name;
                    $code = (md5($user->name . $user->username . time() . rand()));
                    $user->code = md5($code);
                    $_SESSION['code'] = $code;
                    if (($persistent)) $status['debug'][] = JFusionFunction::addCookie('elggperm', $code, (86400 * 30), '/', $params->get('cookie_domain'));
                    if (!$user->save() || !trigger_elgg_event('login', 'user', $user)) {
                        unset($_SESSION['username']);
                        unset($_SESSION['name']);
                        unset($_SESSION['code']);
                        unset($_SESSION['guid']);
                        unset($_SESSION['id']);
                        unset($_SESSION['user']);
                        $status['debug'][] = JFusionFunction::addCookie('elggperm', '', -3600, '/', $params->get('cookie_domain'));
                    } else {
                        // Users privilege has been elevated, so change the session id (help prevent session hijacking)
                        //session_regenerate_id();
                        // Update statistics
                        set_last_login($_SESSION['guid']);
                        reset_login_failure_count($user->guid); // Reset any previous failed login attempts
                    }
                }
            }
        }
        return $status;
    }

    /**
     * @param string $username
     *
     * @return string
     */
    function filterUsername($username) {
        return $username;
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status) {
        jimport('joomla.user.helper');
        $existinguser->password_salt = JUserHelper::genRandomPassword(8);
        $existinguser->password = md5($userinfo->password_clear . $existinguser->password_salt);
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users_entity SET password =' . $db->Quote($existinguser->password) . ', salt = ' . $db->Quote($existinguser->password_salt) . ' WHERE guid =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
        }
    }

    /**
     * @param object $userinfo
     * @param array &$status
     *
     * @return void
     */
    function createUser($userinfo, &$status) {
        //found out what usergroup should be used
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('ERROR_CREATE_USER') . ": " . JText::_('USERGROUP_MISSING');
        } else {
            $usergroup = $usergroups[0];
            $username_clean = $this->filterUsername($userinfo->username);
            //prepare the variables
            $user = new stdClass;
            $user->uid = null;
            $user->username = $username_clean;
            $user->email = $userinfo->email;
            jimport('joomla.user.helper');
            if (isset($userinfo->password_clear)) {
                $user->password = $userinfo->password_clear;
            } else {
                //generate a random one for now
                jimport('joomla.user.helper');
                $user->password = JUserHelper::genRandomPassword(12);
            }
            /**
             * @TODO add usergroup functionality
             */
            if (!empty($userinfo->activation)) {
                $user->usergroup = 2;
            } elseif (!empty($userinfo->block)) {
                $user->usergroup = 7;
            } else {
                $user->usergroup = $usergroup;
            }
            if (defined('externalpage')) {
                define('externalpage', true);
            }
            require_once $params->get('source_path') . DS . 'engine' . DS . 'start.php';
            // Get variables
            global $CONFIG;
            $username = $user->username;
            $password = $user->password;
            $password2 = $user->password;
            $email = $user->email;
            $name = $userinfo->name;
            // For now, just try and register the user

            try {
                if (((trim($password) != '') && (strcmp($password, $password2) == 0)) && ($guid = register_user($username, $password, $name, $email, true))) {
    // commented out, if user is created by admin validated emails or not user can still login.., don't think this is what we want as i added update validation functions.
    //                $new_user = get_entity($guid);
    //                $new_user->admin_created = true;
                    if (empty($userinfo->password_clear)) {
                        //we need to update the password
                        $db = JFusionFactory::getDatabase($this->getJname());
                        $query = 'UPDATE #__users_entity SET password =' . $db->Quote($userinfo->password) . ', salt = ' . $db->Quote($userinfo->password_salt) . ' WHERE username = ' . $db->Quote($username);
                        $db->setQuery($query);
                        $db->query();
                    }
                    //return the good news
                    $status['debug'][] = JText::_('USER_CREATION');
                    $status['userinfo'] = $this->getUser($userinfo);
                    //notify_user($new_user->guid, $CONFIG->site->guid, elgg_echo('useradd:subject'), sprintf(elgg_echo('useradd:body'), $name, $CONFIG->site->name, $CONFIG->site->url, $username, $password));
                    //system_message(sprintf(elgg_echo('adduser:ok'),$CONFIG->sitename));

                } else {
                    //register_error(elgg_echo('adduser:bad'));
                }
            } catch(RegistrationException $r) {
                //register_error($r->getMessage());
                $status['error'][] = JText::_('USER_CREATION_ERROR').' '.$r->getMessage();
            }
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status) {
        //we need to update the email
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users_entity SET email =' . $db->Quote($userinfo->email) . ' WHERE guid =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
        }
    }
    
    /**
     * blockUser
     *
     * @param object $userinfo      holds the new user data
     * @param object &$existinguser holds the existing user data
     * @param array  &$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function blockUser($userinfo, &$existinguser, &$status)
    {
        $params = JFusionFactory::getParams($this->getJname());

    	if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $params->get('source_path') . DS . 'engine' . DS . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($existinguser->username);
        if($user) {
        	if ($user->ban()) {
				$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        	} else {
        		$status['error'][] = JText::_('BLOCK_UPDATE_ERROR');
        	}
        } else {
        	$status['error'][] = JText::_('BLOCK_UPDATE_ERROR');
		}
    }

    /**
     * unblock user
     *
     * @param object $userinfo      holds the new user data
     * @param object &&$existinguser holds the existing user data
     * @param array  &&$status       Status array
     *
     * @access public
     *
     * @return void
     */
    function unblockUser($userinfo, &$existinguser, &$status)
    {
        $params = JFusionFactory::getParams($this->getJname());

    	if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $params->get('source_path') . DS . 'engine' . DS . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($existinguser->username);
        if($user) {
        	if ($user->unban()) {
				$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        	} else {
        		$status['error'][] = JText::_('BLOCK_UPDATE_ERROR');
        	}
        } else {
        	$status['error'][] = JText::_('BLOCK_UPDATE_ERROR');
		}
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function activateUser($userinfo, &$existinguser, &$status) {
        $params = JFusionFactory::getParams($this->getJname());

    	if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $params->get('source_path') . DS . 'engine' . DS . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($existinguser->username);
        if($user) {
        	if (set_user_validation_status($user->guid,1,'validated:jfusion')) {
				$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        	} else {
        		$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR');
        	}
        } else {
        	$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR');
		}    
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function inactivateUser($userinfo, &$existinguser, &$status) {
        $params = JFusionFactory::getParams($this->getJname());

		if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $params->get('source_path') . DS . 'engine' . DS . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($existinguser->username);
        if($user) {
        	if (set_user_validation_status($user->guid,0)) {
				$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        	} else {
        		$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR');
        	}
        } else {
        	$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR');
		}    
    }
}
