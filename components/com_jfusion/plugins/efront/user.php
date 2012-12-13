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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';

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
     * @param object $userinfo
     * @return null|object
     */
    function getUser($userinfo) {
        $db = JFusionFactory::getDatabase($this->getJname());
        //get the identifier
        list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'login', 'email');
        if ($identifier_type == 'login') {
            $identifier = $this->filterUsername($identifier);
        }
        
        //initialise some params
        $query = 'SELECT * FROM #__users WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);
        $db->setQuery($query);
        $result = $db->loadObject();
        if ($result) {
            /**
             * @ignore
             * @var $helper JFusionHelper_efront
             */
            $helper = JFusionFactory::getHelper($this->getJname());
            // change/add fields used by jFusion
            $result->userid = $result->id;
            $result->username = $result->login;
            $result->group_id = $helper->groupNameToID($result->user_type,$result->user_types_ID);
            $result->group_name = $helper->groupIdToName($result->group_id);

            $result->groups = array($result->group_id);
            $result->groupnames = array($result->group_name);

            $result->name = trim($result->name . ' ' . $result->surname);
            $result->registerDate = date('d-m-Y H:i:s', $result->timestamp);
            $result->activation = ($result->pending == 1) ? "1" : '';
            $result->block = !$result->active;
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

        $params = JFusionFactory::getParams($this->getJname());
        $cookiedomain = $params->get('cookie_domain');
        $cookiepath = $params->get('cookie_path', '/');
        $httponly = $params->get('httponly',0);
        $secure = $params->get('secure',0);
        //Set cookie values
        $expires = -3600;
        if (!$cookiepath) {
            $cookiepath = '/';
        }
        // Clearing eFront Cookies
        $remove_cookies = array('cookie_login', 'cookie_password');
        if ($cookiedomain) {
            foreach ($remove_cookies as $name) {
                $status['debug'][] = JFusionFunction::addCookie($name,  '', $expires, $cookiepath, $cookiedomain);
           }
        } else {
            foreach ($remove_cookies as $name) {
                $status['debug'][] = JFusionFunction::addCookie($name,  '', $expires, $cookiepath, '');
            }
        }

        // do some eFront housekeeping
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'DELETE FROM #__users_to_chatrooms WHERE users_LOGIN = ' . $db->Quote($userinfo->username);
        $db->setQuery($query);
        if (!$db->query()) {
            $status['debug'][] = 'Error Could not delete users_to_chatroom for user '.$userinfo->username.': '.$db->stderr();
        } else {
            $status['debug'][] = 'Deleted users_to_chatroom for user '.$userinfo->username;
        }
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'DELETE FROM #__chatrooms WHERE users_LOGIN = ' . $db->Quote($userinfo->username). ' AND type = '.$db->Quote('one_to_one');
        $db->setQuery($query);
        if (!$db->query()) {
            $status['debug'][] = 'Error Could not delete chatrooms for user '.$userinfo->username.': '.$db->stderr();
        } else {
            $status['debug'][] = 'Deleted chatrooms for user '.$userinfo->username;
        }
        $query = 'DELETE FROM #__users_online WHERE users_LOGIN = ' . $db->Quote($userinfo->username);
        $db->setQuery($query);
        if (!$db->query()) {
            $status['debug'][] = 'Error Could not delete users_on_line for user '.$userinfo->username.': '.$db->stderr();
        } else {
            $status['debug'][] = 'Deleted users_on_line for user '.$userinfo->username;
        }
        $query = 'SELECT action FROM #__logs WHERE users_LOGIN = ' . $db->Quote($userinfo->username).' timestamp desc limit 1';
        $db->setQuery($query);
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
            $ok = $db->insertObject('#__logs', $log, 'id');
            if (!$ok) {
                $status['debug'][] = 'Error Could not log the logout action for user '.$userinfo->username.': '.$db->stderr();
            } else {
                $status['debug'][] = 'Logged the logout action for user '.$userinfo->username;
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
        //do not create sessions for blocked users
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = JText::_('FUSION_BLOCKED_USER');
        } else {
            //get cookiedomain, cookiepath
            $params = JFusionFactory::getParams($this->getJname());
            $cookiedomain = $params->get('cookie_domain', '');
            $cookiepath = $params->get('cookie_path', '/');
            $httponly = $params->get('httponly',0);
            $secure = $params->get('secure',0);
            $db = JFusionFactory::getDatabase($this->getJname());
            $query = 'SELECT password FROM #__users WHERE login=' . $db->Quote($userinfo->username);
            $db->setQuery($query);
            $user = $db->loadObject();
            // Set cookie values
            $query = 'SELECT value FROM #__configuration WHERE name = \'autologout_time\'';
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
        return $status;
    }

    /**
     * @param string $username
     * @return mixed|string
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
     */
    function updatePassword($userinfo, &$existinguser, &$status) {
        $params = JFusionFactory::getParams($this->getJname());
        $md5_key = $params->get('md5_key');
        $existinguser->password = md5($userinfo->password_clear.$md5_key);
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET password =' . $db->Quote($existinguser->password). 'WHERE id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
        }
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     */
    function updateUsername($userinfo, &$existinguser, &$status) {
        // not implemented in jFusion 1.x
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     */
    function updateEmail($userinfo, &$existinguser, &$status) {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET email =' . $db->Quote($userinfo->email) . ' WHERE id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     */
    function blockUser($userinfo, &$existinguser, &$status) {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET active = 0 WHERE id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     */
    function unblockUser($userinfo, &$existinguser, &$status) {
        //unblock the user
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET active = 1 WHERE id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array &$status
     */
    function activateUser($userinfo, &$existinguser, &$status) {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET pending = 0 WHERE id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     */
    function inactivateUser($userinfo, &$existinguser, &$status) {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__users SET pending = 1 WHERE id =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }

    /**
     * @param object $userinfo
     * @param array &$status
     */
    function createUser($userinfo, &$status) {
       /**
        * NOTE: eFront does a charactercheck on the user credentials. I think we are ok (HW): if (preg_match("/^.*[$\/\'\"]+.*$/", $parameter))
        */
        $status = array('error' => array(),'debug' => array());
    	$params = JFusionFactory::getParams($this->getJname());
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
            $status['error'][] = JText::_('ERROR_CREATE_USER') . ": " . JText::_('USERGROUP_MISSING');
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
                    $query = 'SELECT basic_user_type from #__user_types WHERE id = '.$user_types_ID;
                    $db->setQuery($query);
                    $user_type = $db->loadResult();
            }
            $user->user_type = $user_type;
            $user->user_types_ID = $user_types_ID;
            if (isset($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
                $md5_key = $params->get('md5_key');
                $user->password = md5($userinfo->password_clear.$md5_key);
            } else {
                $user->password = $userinfo->password;
            }
            // note that we will plan to propagate the language setting for a user from version 2.0
            // for now we just use the default defined in eFront
            $query = 'SELECT value from #__configuration WHERE name = \'default_language\'';
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
            if (!$db->insertObject('#__users', $user, 'id')) {
                //return the error
                $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
            } else {
                // we need to create the user directories. Can't use Joomla's API because it uses the Joomla Root Path
                $uploadpath = $params->get('uploadpath');
                $user_dir = $uploadpath.$user->login.'/';
                if (is_dir($user_dir)) {
                    /**
                     * @ignore
                     * @var $helper JFusionHelper_efront
                     */
                    $helper = JFusionFactory::getHelper($this->getJname());
                    $helper->delete_directory($user_dir); //If the folder already exists, delete it first, including files
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
                    $errors = $db->insertObject('#__f_folders', $f_folder, 'id');
                    $f_folder->id = null;
                    $f_folder->name = 'Sent';
                    $f_folder->users_LOGIN = $user->login;
                    $errors = $db->insertObject('#__f_folders', $f_folder, 'id');
                    $f_folder->id = null;
                    $f_folder->name = 'Drafts';
                    $f_folder->users_LOGIN = $user->login;
                    $errors = $db->insertObject('#__f_folders', $f_folder, 'id');

                    // for eFront Educational and enterprise versions we now should assign skillgap tests
                    // not sure I should implemented it, anyway I have only the community version to work on
                }
                //return the good news
                $status['debug'][] = JText::_('USER_CREATION');
                $status['userinfo'] = $this->getUser($userinfo);
            }
        }
    }

    /**
     * @param object $userinfo
     * @return array|bool
     */
    function deleteUser($userinfo){
        // we are using the api function remove_user here. 
        // User deletion is not a time critical function and deleting a user is
        // more often than not a complicated task in this type of software.
        // In eFront, it is impossible to trigger the 'ondeleteuser' signal for the
        // modules without loading the complete website. 
        
    	// check apiuser existance
        $status = array('error' => array(),'debug' => array());
        if (!is_object($userinfo)) {
            $status['error'][] = JText::_('NO_USER_DATA_FOUND');
        } else {
            $existinguser = $this->getUser($userinfo);
            if (!empty($existinguser)) {
                $params = JFusionFactory::getParams($this->getJname());
                /**
                 * @ignore
                 * @var $helper JFusionHelper_efront
                 */
                $helper = JFusionFactory::getHelper($this->getJname());
                $apiuser = $params->get('apiuser');
                $apikey = $params->get('apikey');
                $login = $existinguser->username;
                $jname = $this->getJname();
                if (!$apiuser || !$apikey) {
                    JError::raiseWarning(0, $jname . '-plugin: ' . JText::_('EFRONT_WRONG_APIUSER_APIKEY_COMBINATION'));
                    $status['error'][] = '';
                } else {
                    // get token
                    $curl_options['action'] ='token';
                    $status = $helper->send_to_api($curl_options,$status);
                    if (!$status['error']) {
                        $result = $status['result'][0];
                        $token = $result->token;
                        // login
                        $curl_options['action']='login';
                        $curl_options['parms'] = '&token='.$token.'&username='.$apiuser.'&password='.$apikey;
                        $status = $helper->send_to_api($curl_options,$status);
                        if (!$status['error']){
                            $result = $status['result'][0];
                            if($result->status == 'ok'){
                                // logged in (must logout later)
                                // delete user
                                $curl_options['action']='remove_user';
                                $curl_options['parms'] = '&token='.$token.'&login='.$login;
                                $status = $helper->send_to_api($curl_options,$status);
                                $errorstatus = $status;
                                if ($status['error']){
                                    $status['debug'][] = $status['error'][0];
                                    $status['error']=array();
                                }
                                $result = $status['result'][0];
                                if($result->status != 'ok'){
                                    $errorstatus['debug'][]=$jname.' eFront API--'.$result->message;
                                }
                                // logout
                                $curl_options['action']='logout';
                                $curl_options['parms'] = '&token='.$token;
                                $status = $helper->send_to_api($curl_options,$status);
                                $result = $status['result'][0];
                                if($result->status != 'ok'){
                                    $errorstatus['error'][]=$jname.' eFront API--'.$result->message;
                                    return $errorstatus;
                                }
                            }
                            $status['error']= null;
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
     */
    function updateUsergroup($userinfo, &$existinguser, &$status) {
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('USERGROUP_MISSING');
        } else {
            $usergroup = $usergroups[0];
            $db = JFusionFactory::getDataBase($this->getJname());
            if ($usergroup< 3) {
                /**
                 * TODO: Undefined function
                 */
                $user_type = $this->groupIDToName($usergroup);
                $user_types_ID = 0;
            } else {
                $user_types_ID = $usergroup-2;
                $query = 'SELECT basic_user_type from #__user_types WHERE id = '.$user_types_ID;
                $db->setQuery($query);
                $user_type = $db->loadResult();
            }
            $query = 'UPDATE #__users SET user_type = '.$db->Quote($user_type).', user_types_ID = '.$user_types_ID.' WHERE id =' . $existinguser->userid;
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
            } else {
                $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
            }
        }
    }
}