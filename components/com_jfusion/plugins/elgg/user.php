<?php namespace JFusion\Plugins\elgg;

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
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;

use \Exception;
use Psr\Log\LogLevel;
use \RuntimeException;
use \stdClass;

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

class JFusionUser_elgg extends Plugin_User
{
    /**
     * @param Userinfo $userinfo
     *
     * @return null|Userinfo
     */
    function getUser(Userinfo $userinfo) {
	    $user = null;
	    try {
		    //get the identifier
		    list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'username', 'email', 'guid');

		    // Get user info from database
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('guid as userid, username, name, name as lastname, email, password, salt as password_salt, banned as block')
			    ->from('#__users_entity')
		        ->where($identifier_type . ' = ' . $db->quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();

		    if ($result) {
			    if (defined('externalpage')) {
				    define('externalpage', true);
			    }
			    require_once $this->params->get('source_path') . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
			    // Get variables
			    global $CONFIG;

			    $user = get_user_by_username($userinfo->username);
			    if ($result->block == 'no') {
				    $result->block = false;
			    } else {
				    $result->block = true;
			    }
			    if ((!$user->isAdmin()) && (!$user->validated) && (!$user->admin_created)) {
				    $result->activation = md5($result->userid . $result->email . $CONFIG->site->url . get_site_secret());
			    } else {
				    $result->activation = null;
			    }

			    $user = new Userinfo($this->getJname());
			    $user->bind($result);
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $user;
    }

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 * @return array
	 */
    function deleteUser(Userinfo $userinfo) {
    	if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $this->params->get('source_path') . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($userinfo->username);
        if($user) {
        	if ($user->delete()) {
		        $status[LogLevel::DEBUG][] = Text::_('USER_DELETION') . ': ' . $userinfo->username;
        	} else {
		        throw new RuntimeException($userinfo->username);
        	}
        } else {
	        throw new RuntimeException($userinfo->username);
		}
		return $status;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $option
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $option) {
        $status = array(LogLevel::ERROR => array(), LogLevel::DEBUG => array());
        /*
        NOTE:
        !Can not include elgg engine and use core elgg logout functions since it conflicts with Community Builder Logout function!
        unsetting the elgg cookies has been problematic as well.
        */
        $expire = -3600;
        $status[LogLevel::DEBUG][] = $this->addCookie('Elgg', '', $expire, $this->params->get('cookie_path'), $this->params->get('cookie_domain'));
        $status[LogLevel::DEBUG][] = $this->addCookie('elggperm', '', $expire, '/', $this->params->get('cookie_domain'));
        return array();
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     * @param bool $framework
     *
     * @return array
     */
    function createSession(Userinfo $userinfo, $options, $framework = true) {
        //destroy a cookie if it exists already, this will prevent the person logging in from having to refresh twice to appear as logged in
	    try {
		    $this->destroySession(null, null);
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        $status = array('error' => array(), 'debug' => array());

        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = Text::_('FUSION_BLOCKED_USER');
        } else {
            if (defined('externalpage')) {
                define('externalpage', true);
            }
            require_once $this->params->get('source_path') . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
            // Get variables
            global $CONFIG;
            // Action Gatekeep not necessary as person should already be validated by Joomla!
            //action_gatekeeper();
            //Get username and password
            $username = $userinfo->username;
            $password = $userinfo->password_clear;
            $persistent = true;
            // If all is present and correct, try to log in
            if (!empty($username) && !empty($password)) {
                $auth = elgg_authenticate($username, $password);
                if ($auth===true) {
	                $user = get_user_by_username($userinfo->username);
                    //if ($user->isBanned()) return false; // User is banned, return false.
                    $_SESSION['user'] = $user;
                    $_SESSION['guid'] = $user->getGUID();
                    $_SESSION['id'] = $_SESSION['guid'];
                    $_SESSION['username'] = $user->username;
                    $_SESSION['name'] = $user->name;
                    $code = (md5($user->name . $user->username . time() . rand()));
                    $user->code = md5($code);
                    $_SESSION['code'] = $code;
                    if (($persistent)) $status[LogLevel::DEBUG][] = $this->addCookie('elggperm', $code, (86400 * 30), '/', $this->params->get('cookie_domain'));
                    if (!$user->save() || !elgg_trigger_event('login', 'user', $user)) {
                        unset($_SESSION['username']);
                        unset($_SESSION['name']);
                        unset($_SESSION['code']);
                        unset($_SESSION['guid']);
                        unset($_SESSION['id']);
                        unset($_SESSION['user']);
                        $status[LogLevel::DEBUG][] = $this->addCookie('elggperm', '', -3600, '/', $this->params->get('cookie_domain'));
                    } else {
                        // Users privilege has been elevated, so change the session id (help prevent session hijacking)
                        //session_regenerate_id();
                        // Update statistics
                        set_last_login($_SESSION['guid']);
                        reset_login_failure_count($user->guid); // Reset any previous failed login attempts
                    }
                } else {
	                $status['error'][] = $auth;
                }
            }
        }
        return $status;
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo &$existinguser
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser) {
	    jimport('joomla.user.helper');
	    $existinguser->password_salt = Framework::genRandomPassword(8);
	    $existinguser->password = md5($userinfo->password_clear . $existinguser->password_salt);
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__users_entity')
		    ->set('password = ' . $db->quote($existinguser->password))
		    ->set('salt = ' . $db->quote($existinguser->password_salt))
		    ->where('guid = ' . (int)$existinguser->userid);

	    $db->setQuery($query);

	    $db->execute();

	    $this->debugger->addDebug(Text::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
    }

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
    function createUser(Userinfo $userinfo) {
	    //found out what usergroup should be used
	    $usergroups = $this->getCorrectUserGroups($userinfo);
	    if (empty($usergroups)) {
		    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
	    } else {
		    $usergroup = $usergroups[0];
		    //prepare the variables
		    $user = new stdClass;
		    $user->uid = null;
		    $user->username = $userinfo->username;
		    $user->email = $userinfo->email;
		    jimport('joomla.user.helper');
		    if (isset($userinfo->password_clear)) {
			    $user->password = $userinfo->password_clear;
		    } else {
			    //generate a random one for now
			    $user->password = Framework::genRandomPassword(12);
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
		    require_once $this->params->get('source_path') . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
		    // Get variables
		    global $CONFIG;
		    $username = $user->username;
		    $password = $user->password;
		    $password2 = $user->password;
		    $email = $user->email;
		    $name = $userinfo->name;
		    // For now, just try and register the user

		    if (((trim($password) != '') && (strcmp($password, $password2) == 0)) && ($guid = register_user($username, $password, $name, $email, true))) {
			    // commented out, if user is created by admin validated emails or not user can still login.., don't think this is what we want as i added update validation functions.
			    //                $new_user = get_entity($guid);
			    //                $new_user->admin_created = true;
			    if (empty($userinfo->password_clear)) {
				    //we need to update the password
				    $db = Factory::getDatabase($this->getJname());

				    $query = $db->getQuery(true)
					    ->update('#__users_entity')
					    ->set('password = ' . $db->quote($userinfo->password))
					    ->set('salt = ' . $db->quote($userinfo->password_salt))
					    ->where('username = ' . $db->quote($username));

				    $db->setQuery($query);
				    $db->execute();
			    }
			    //return the good news
			    $status[LogLevel::DEBUG][] = Text::_('USER_CREATION');
			    $status['userinfo'] = $this->getUser($userinfo);
			    //notify_user($new_user->guid, $CONFIG->site->guid, elgg_echo('useradd:subject'), sprintf(elgg_echo('useradd:body'), $name, $CONFIG->site->name, $CONFIG->site->url, $username, $password));
			    //system_message(sprintf(elgg_echo('adduser:ok'), $CONFIG->sitename));

		    } else {
			    //register_error(elgg_echo('adduser:bad'));
		    }
	    }
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    //we need to update the email
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__users_entity')
		    ->set('email = ' . $db->quote($userinfo->email))
		    ->where('guid = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('PASSWORD_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
    }
    
    /**
     * @param Userinfo $userinfo      holds the new user data
     * @param Userinfo &$existinguser holds the existing user data
     *
     * @access public
     *
     * @return void
     */
    function blockUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
    	if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $this->params->get('source_path') . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($existinguser->username);
	    /**
	     * TODO: THROW ERROR INSTEAD
	     */
	    if($user) {
        	if ($user->ban()) {
		        $this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
        	} else {
		        $this->debugger->addError(Text::_('BLOCK_UPDATE_ERROR'));
        	}
        } else {
	        $this->debugger->addError(Text::_('BLOCK_UPDATE_ERROR'));
		}
    }

	/**
	 * unblock user
	 *
	 * @param Userinfo $userinfo holds the new user data
	 * @param Userinfo $existinguser
	 *
	 * @access   public
	 *
	 * @return void
	 */
    function unblockUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
    	if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $this->params->get('source_path') . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($existinguser->username);
	    /**
	     * TODO: THROW ERROR INSTEAD
	     */
        if($user) {
        	if ($user->unban()) {
		        $this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
        	} else {
		        $this->debugger->addError(Text::_('BLOCK_UPDATE_ERROR'));
        	}
        } else {
	        $this->debugger->addError(Text::_('BLOCK_UPDATE_ERROR'));
		}
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo &$existinguser
     *
     * @return void
     */
    function activateUser(Userinfo $userinfo, Userinfo &$existinguser) {
    	if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $this->params->get('source_path') . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($existinguser->username);
	    /**
	     * TODO: THROW ERROR INSTEAD
	     */
        if($user) {
        	if (elgg_set_user_validation_status($user->guid, 1, 'validated:jfusion')) {
		        $this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
        	} else {
		        $this->debugger->addError(Text::_('ACTIVATION_UPDATE_ERROR'));
        	}
        } else {
	        $this->debugger->addError(Text::_('ACTIVATION_UPDATE_ERROR'));
		}    
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo &$existinguser
     *
     * @return void
     */
    function inactivateUser(Userinfo $userinfo, Userinfo &$existinguser) {
		if (defined('externalpage')) {
        	define('externalpage', true);	
        }
        require_once $this->params->get('source_path') . 'engine' . DIRECTORY_SEPARATOR . 'start.php';
        // Get variables
        global $CONFIG;
        $user = get_user_by_username($existinguser->username);
	    /**
	     * TODO: THROW ERROR INSTEAD
	     */
        if($user) {
        	if (elgg_set_user_validation_status($user->guid, 0)) {
		        $this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
        	} else {
		        $this->debugger->addError(Text::_('ACTIVATION_UPDATE_ERROR'));
        	}
        } else {
	        $this->debugger->addError(Text::_('ACTIVATION_UPDATE_ERROR'));
		}    
    }
}
