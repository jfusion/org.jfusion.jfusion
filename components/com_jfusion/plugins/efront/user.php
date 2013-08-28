<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage efront
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * load the jplugin model
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jplugin.php';

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage efront
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_efront extends JFusionUser
{
	/**
	 * @var $helper JFusionHelper_efront
	 */
	var $helper;

    /**
     * @param object $userinfo
     *
     * @return null|object
     */
    function getUser($userinfo) {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());
	        //get the identifier
	        list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'login', 'email');
	        if ($identifier_type == 'login') {
	            $identifier = $this->filterUsername($identifier);
	        }

	        //initialise some params
		    $query = $db->getQuery(true)
			    ->select('*')
			    ->from('#__users')
			    ->where($identifier_type . ' = ' . $db->Quote($identifier));

	        $db->setQuery($query);
	        $result = $db->loadObject();
	        if ($result) {
	            // change/add fields used by jFusion
	            $result->userid = $result->id;
	            $result->username = $result->login;
	            $result->group_id = $this->helper->groupNameToID($result->user_type,$result->user_types_ID);
	            $result->group_name = $this->helper->groupIdToName($result->group_id);

	            $result->groups = array($result->group_id);
	            $result->groupnames = array($result->group_name);

	            $result->name = trim($result->name . ' ' . $result->surname);
	            $result->registerDate = date('d-m-Y H:i:s', $result->timestamp);
	            $result->activation = ($result->pending == 1) ? "1" : '';
	            $result->block = !$result->active;
	        }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = null;
	    }
        return $result;
    }
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'efront';
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array
     */
    function destroySession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());
        if (isset($options['remember'])) {
            if ($options['remember']) {
                 return $status;
            }
        }
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());
	        $status = JFusionJplugin::destroySession($userinfo, $options, $this->getJname(),$this->params->get('logout_type'));

		    $query = $db->getQuery(true)
			    ->select('action')
			    ->from('#__logs')
			    ->where('users_LOGIN = ' . $db->Quote($userinfo->username))
		        ->order('timestamp desc');

	        $db->setQuery($query, 0 , 1);
	        $action = $db->loadResult();
	        if ($action != 'logout') {
	            $log = new stdClass;
	            $log->id = null;
	            $log->users_LOGIN = $userinfo->username;
	            $log->timestamp = time();
	            $log->action = 'logout';
	            $log->comments = 'logged out by jFusion';
	            $log->lessons_ID =0;
	            $ip = explode('.',$_SERVER['REMOTE_ADDR']);
	            $log->session_ip = sprintf('%02x%02x%02x%02x',  $ip[0],  $ip[1],  $ip[2],  $ip[3]);
		        try {
			        $ok = $db->insertObject('#__logs', $log, 'id');

			        $status['debug'][] = 'Logged the logout action for user '.$userinfo->username;
		        } catch (Exception $e) {
			        $status['debug'][] = 'Error Could not log the logout action for user '.$userinfo->username.': '.$e->getMessage();
		        }
	        }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
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
	    try {
	        //do not create sessions for blocked users
	        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
		        throw new RuntimeException(JText::_('FUSION_BLOCKED_USER'));
	        } else {
	            //get cookiedomain, cookiepath
	            $cookiedomain = $this->params->get('cookie_domain', '');
	            $cookiepath = $this->params->get('cookie_path', '/');
	            $httponly = $this->params->get('httponly',0);
	            $secure = $this->params->get('secure',0);
	            $db = JFusionFactory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->select('password')
			        ->from('#__users')
			        ->where('login = ' . $db->Quote($userinfo->username));

	            $db->setQuery($query);
	            $user = $db->loadObject();
	            // Set cookie values
		        $query = $db->getQuery(true)
			        ->select('value')
			        ->from('#__configuration')
			        ->where('name = ' . $db->Quote('autologout_time'));

	            $db->setQuery($query);
	            $autologout_time = $db->loadResult(); // this is in minutes
	            $expires = 60 * $autologout_time; // converted to seconds
	            // correct for remember me option
	            if (isset($options['remember'])) {
	                if ($options['remember']) {
	                    // Make the cookie expire in a years time
	                    $expires = 60 * 60 * 24 * 365;
	                }
	            }
	            $name = 'cookie_login';
	            $value = $userinfo->username;
	            $status['debug'][] = JFusionFunction::addCookie($name, $value, $expires, $cookiepath, $cookiedomain, false, $httponly);
	            if ( ($expires) == 0) {
	                $expires_time='Session_cookie';
	            } else {
	                $expires_time=date('d-m-Y H:i:s',time()+$expires);
	            }
	            $name = 'cookie_password';
	            $value = $user->password;
	            $status['debug'][] = JFusionFunction::addCookie($name, $value, $expires, $cookiepath, $cookiedomain, false, $httponly);
	        }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * @param string $username
     *
     * @return string
     */
    function filterUsername($username) {
        // as the username also is used as a directory we probably must strip unwanted characters.
        $bad           = array("\\", "/", ":", ";", "~", "|", "(", ")", "\"", "#", "*", "$", "@", "%", "[", "]", "{", "}", "<", ">", "`", "'", ",", " ", "ÄŸ", "Äž", "Ã¼", "Ãœ", "ÅŸ", "Åž", "Ä±", "Ä°", "Ã¶", "Ã–", "Ã§", "Ã‡");
        $replacement    = array("_", "_", "_", "_", "_", "_", "", "_", "_", "_", "_", "_", "_", "_", "_", "_", "_", "_", "_", "_", "_", "", "_", "_", "g", "G", "u", "U", "s", "S", "i", "I", "o", "O", "c", "C");
        $username = str_replace($bad, $replacement, $username);
    	return $username;
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status) {
	    try {
	        $md5_key = $this->params->get('md5_key');
	        $existinguser->password = md5($userinfo->password_clear.$md5_key);
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('password =' . $db->Quote($existinguser->password))
			    ->where('id =' . (int)$existinguser->userid);

	        $db->setQuery($query);

		    $db->execute();
		    $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password,0,6) . '********';
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR')  . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateUsername($userinfo, &$existinguser, &$status) {
        // not implemented in jFusion 1.x
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status) {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('email =' . $db->Quote($userinfo->email))
			    ->where('id =' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();
		    $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     *
     * @return void
     */
    function blockUser($userinfo, &$existinguser, &$status) {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('active = 0')
			    ->where('id =' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     *
     * @return void
     */
    function unblockUser($userinfo, &$existinguser, &$status) {
	    try {
		    //unblock the user
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('active = 1')
			    ->where('id =' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();
		    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     *
     * @return void
     */
    function activateUser($userinfo, &$existinguser, &$status) {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('pending = 0')
			    ->where('id =' . (int)$existinguser->userid);

	        $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
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
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('pending = 1')
			    ->where('id =' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param array &$status
     *
     * @return void
     */
    function createUser($userinfo, &$status) {
       /**
        * NOTE: eFront does a character check on the user credentials. I think we are ok (HW): if (preg_match("/^.*[$\/\'\"]+.*$/", $parameter))
        */
        $status = array('error' => array(),'debug' => array());
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());
	        //prepare the variables
	        $user = new stdClass;
	        $user->id = null;
	        $user->login = $this->filterUsername($userinfo->username);
	        $parts = explode(' ', $userinfo->name);
	        $user->name = trim($parts[0]);
	        if (count($parts) > 1) {
	            // construct the lastname
	            $lastname = '';
	            for ($i = 1;$i < (count($parts));$i++) {
	                $lastname = $lastname . ' ' . $parts[$i];
	            }
	            $user->surname = trim($lastname);
	        } else {
	            // eFront needs Firstname AND Lastname, so add a dot when lastname is empty
	            $user->surname = '.';
	        }
	        $user->email = $userinfo->email;

	        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
	        if (empty($usergroups)) {
		        throw new RuntimeException(JText::_('USERGROUP_MISSING'));
	        } else {
	            $usergroup = $usergroups[0];
	            $user_type = '';
	            $user_types_ID = 0;
	            switch ($usergroup){
	                case 0:
	                    $user_type = 'student';
	                    break;
	                case 1:
	                    $user_type = 'professor';
	                    break;
	                case 2:
	                    $user_type = 'administrator';
	                    break;
	                default:
	                    // correct id
	                    $user_types_ID = $usergroup - 2;

	                    $query = $db->getQuery(true)
		                    ->select('basic_user_type')
		                    ->from('#__user_types')
		                    ->where('id = ' . $db->Quote($user_types_ID));

	                    $db->setQuery($query);
	                    $user_type = $db->loadResult();
	            }
	            $user->user_type = $user_type;
	            $user->user_types_ID = $user_types_ID;
	            if (isset($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
	                $md5_key = $this->params->get('md5_key');
	                $user->password = md5($userinfo->password_clear.$md5_key);
	            } else {
	                $user->password = $userinfo->password;
	            }
	            // note that we will plan to propagate the language setting for a user from version 2.0
	            // for now we just use the default defined in eFront
		        $query = $db->getQuery(true)
			        ->select('value')
			        ->from('#__configuration')
			        ->where('name = ' . $db->Quote('default_language'));

	            $db->setQuery($query);
	            $default_language = $db->loadResult();
	            $user->languages_NAME = $default_language;
	            $user->active = 1;
	            $user->comments = null;
	            $user->timestamp = time();
	            $user->pending = 0;
	            $user->avatar = null;
	            $user->additional_accounts = null;
	            $user->viewed_license =0;
	            $user->need_mod_init =1;

		        $db->insertObject('#__users', $user, 'id');

                // we need to create the user directories. Can't use Joomla API because it uses the Joomla Root Path
                $uploadpath = $this->params->get('uploadpath');
                $user_dir = $uploadpath.$user->login.'/';
                if (is_dir($user_dir)) {
	                $this->helper->delete_directory($user_dir); //If the folder already exists, delete it first, including files
                }
                // we are not interested in the result of the deletion, just continue
                if (mkdir($user_dir, 0755) || is_dir($user_dir))
                {
	                //Now, the directory either gets created, or already exists (in case errors happened above). In both cases, we continue
	                //Create personal messages attachments folders
	                mkdir($user_dir.'message_attachments/', 0755);
	                mkdir($user_dir.'message_attachments/Incoming/', 0755);
	                mkdir($user_dir.'message_attachments/Sent/', 0755);
	                mkdir($user_dir.'message_attachments/Drafts/', 0755);
	                mkdir($user_dir.'avatars/', 0755);

	                //Create database representations for personal messages folders (it has nothing to do with filesystem database representation)
	                $f_folder = new stdClass;
	                $f_folder->id = null;
	                $f_folder->name = 'Incoming';
	                $f_folder->users_LOGIN = $user->login;
	                $db->insertObject('#__f_folders', $f_folder, 'id');

	                $f_folder->id = null;
	                $f_folder->name = 'Sent';
	                $f_folder->users_LOGIN = $user->login;
	                $db->insertObject('#__f_folders', $f_folder, 'id');

	                $f_folder->id = null;
	                $f_folder->name = 'Drafts';
	                $f_folder->users_LOGIN = $user->login;
	                $db->insertObject('#__f_folders', $f_folder, 'id');

	                // for eFront Educational and enterprise versions we now should assign skill gap tests
	                // not sure I should implemented it, anyway I have only the community version to work on
                }
                //return the good news
                $status['debug'][] = JText::_('USER_CREATION');
                $status['userinfo'] = $this->getUser($userinfo);
	        }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ERROR_CREATE_USER') . ': ' .$e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     *
     * @return array|bool
     */
    function deleteUser($userinfo){
        // we are using the api function remove_user here. 
        // User deletion is not a time critical function and deleting a user is
        // more often than not a complicated task in this type of software.
        // In eFront, it is impossible to trigger the 'ondeleteuser' signal for the
        // modules without loading the complete website. 
        
    	// check apiuser existence
        $status = array('error' => array(),'debug' => array());
        if (!is_object($userinfo)) {
            $status['error'][] = JText::_('NO_USER_DATA_FOUND');
        } else {
            $existinguser = $this->getUser($userinfo);
            if (!empty($existinguser)) {
                $apiuser = $this->params->get('apiuser');
                $apikey = $this->params->get('apikey');
                $login = $existinguser->username;
                $jname = $this->getJname();
                if (!$apiuser || !$apikey) {
                    $status['error'][] = JText::_('EFRONT_WRONG_APIUSER_APIKEY_COMBINATION');
                } else {
                    // get token
                    $curl_options['action'] ='token';
                    $status = $this->helper->send_to_api($curl_options,$status);
                    if (!$status['error']) {
                        $result = $status['result'][0];
                        $token = $result->token;
                        // login
                        $curl_options['action']='login';
                        $curl_options['parms'] = '&token='.$token.'&username='.$apiuser.'&password='.$apikey;
                        $status = $this->helper->send_to_api($curl_options,$status);
                        if (!$status['error']){
                            $result = $status['result'][0];
                            if($result->status == 'ok'){
                                // logged in (must logout later)
                                // delete user
                                $curl_options['action'] = 'remove_user';
                                $curl_options['parms'] = '&token='.$token.'&login='.$login;
                                $status = $this->helper->send_to_api($curl_options,$status);
                                $errorstatus = $status;
                                if ($status['error']){
                                    $status['debug'][] = $status['error'][0];
                                    $status['error'] = array();
                                }
                                $result = $status['result'][0];
                                if($result->status != 'ok'){
                                    $errorstatus['debug'][] = $jname.' eFront API--'.$result->message;
                                }
                                // logout
                                $curl_options['action']='logout';
                                $curl_options['parms'] = '&token='.$token;
                                $status = $this->helper->send_to_api($curl_options,$status);
                                $result = $status['result'][0];
                                if($result->status != 'ok'){
                                    $errorstatus['error'][] = $jname.' eFront API--'.$result->message;
                                    return $errorstatus;
                                }
                            }
                            $status['debug'][] = JText::_('DELETED').JTEXT::_(' USER: ' ).$login;
                        }
                    }
                }
            }
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status) {
	    try {
		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
		    } else {
			    $usergroup = $usergroups[0];
			    $db = JFusionFactory::getDataBase($this->getJname());
			    if ($usergroup< 3) {
				    $user_type = $this->helper->groupIdToName($usergroup);
				    $user_types_ID = 0;
			    } else {
				    $user_types_ID = $usergroup-2;

				    $query = $db->getQuery(true)
					    ->select('basic_user_type')
					    ->from('#__user_types')
					    ->where('id = ' . $db->Quote($user_types_ID));

				    $db->setQuery($query);
				    $user_type = $db->loadResult();
			    }

			    $query = $db->getQuery(true)
				    ->update('#__users')
				    ->set('user_type =' . $db->Quote($user_type))
				    ->set('user_types_ID =' . $db->Quote($user_types_ID))
				    ->where('id =' . (int)$existinguser->userid);

			    $db->setQuery($query);
			    $db->execute();
			    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $e->getMessage();
	    }
    }
}