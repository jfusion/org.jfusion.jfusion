<?php

/**
 * Abstract user class
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'model.abstractplugin.php';

/**
 * Abstract interface for all JFusion plugin implementations.
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.orgrg
 */
class JFusionUser extends JFusionPlugin
{
	var $helper;

	/**
	 *
	 */
	function __construct()
	{
		parent::__construct();
		//get the helper object
		$this->helper = JFusionFactory::getHelper($this->getJname());
	}

    /**
     * gets the userinfo from the JFusion integrated software. Definition of object:
     * $userinfo->userid
     * $userinfo->name
     * $userinfo->username
     * $userinfo->email
     * $userinfo->password (encrypted password)
     * $userinfo->password_salt (salt used to encrypt password)
     * $userinfo->block (0 if allowed to access site, 1 if user access is blocked)
     * $userinfo->registerdate
     * $userinfo->lastvisitdate
     * $userinfo->group_id
     *
     * @param object $userinfo contains the object of the user
     *
     * @return null|object userinfo Object containing the user information
     */
    function getUser($userinfo)
    {
        return null;
    }

    /**
     * Returns the identifier and identifier_type for getUser
     *
     * @param object &$userinfo    object with user identifying information
     * @param string $username_col Database column for username
     * @param string $email_col    Database column for email
     * @param bool $lowerEmail   Boolean to lowercase emails for comparison
     *
     * @return array array($identifier, $identifier_type)
     */
    function getUserIdentifier(&$userinfo, $username_col, $email_col, $lowerEmail = true)
    {
        //the discussion bot may need to override the identifier_type to prevent user hijacking by guests
        $override = (defined('OVERRIDE_IDENTIFIER')) ? OVERRIDE_IDENTIFIER : 'default';
        $options = array('0', '1', '2');
        if (in_array($override, $options)) {
            $login_identifier = $override;
        } else {
            $login_identifier = $this->params->get('login_identifier', 1);
        }
        $identifier = $userinfo; // saves some code lines, only change if userinfo is an object
        switch ($login_identifier) {
            default:
            case 1:
                // username
                if (is_object($userinfo)) {
                    $identifier = $userinfo->username;
                }
                $identifier_type = $username_col;
                break;
            case 2:
                // email
                if (is_object($userinfo)) {
                    $identifier = $userinfo->email;
                }
                $identifier_type = $email_col;
                break;
            case 3:
                // username or email
                if (!is_object($userinfo)) {
                    $pattern = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/i';
                    if (preg_match($pattern, $identifier)) {
                        $identifier_type = $email_col;
                    } else {
                        $identifier_type = $username_col;
                    }
                } else {
                    $pattern = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/i';
                    if (preg_match($pattern, $userinfo->username)) {
                        $identifier_type = $email_col;
                        $identifier = $userinfo->email;
                    } else {
                        $identifier_type = $username_col;
                        $identifier = $userinfo->username;
                    }
                }
                break;
        }
        if ($lowerEmail && $identifier_type == $email_col) {
            $identifier_type = 'LOWER(' . $identifier_type . ')';
            $identifier = strtolower($identifier);
        }
        return array($identifier_type, $identifier);
    }

    /**
     * Function that automatically logs out the user from the integrated software
     * $result['error'] (contains any error messages)
     * $result['debug'] (contains information on what was done)
     *
     * @param object $userinfo contains the userinfo
     * @param array $options  contains Array with the login options, such as remember_me
     *
     * @return array result Array containing the result of the session destroy
     */
    function destroySession($userinfo, $options)
    {
        $result = array();
        $result['error'] = array();
        $result['debug'] = array();
        return $result;
    }

    /**
     * Function that automatically logs in the user from the integrated software
     * $result['error'] (contains any error messages)
     * $result['debug'] (contains information on what was done)
     *
     * @param object $userinfo contains the userinfo
     * @param array  $options  contains array with the login options, such as remember_me     *
     *
     * @return array result Array containing the result of the session creation
     */
    function createSession($userinfo, $options)
    {
        return array();
    }

    /**
     * Function that filters the username according to the JFusion plugin
     *
     * @param string $username Username as it was entered by the user
     *
     * @return string filtered username that should be used for lookups
     */
    function filterUsername($username)
    {
        return $username;
    }

    /**
     * Updates or creates a user for the integrated software. This allows JFusion to have external software as slave for user management
     * $result['error'] (contains any error messages)
     * $result['userinfo'] (contains the userinfo object of the integrated software user)
     *
     * @param object $userinfo  contains the userinfo
     * @param int    $overwrite determines if the userinfo can be overwritten
     *
     * @return array result Array containing the result of the user update
     */
    function updateUser($userinfo, $overwrite = 0)
    {
        // Initialise some variables
        if (!empty($userinfo->params)) {
            $user_params = new JRegistry($userinfo->params);
        }
        $update_block = $this->params->get('update_block');
        $update_activation = $this->params->get('update_activation');
        $update_email = $this->params->get('update_email');
        $status = array('error' => array(), 'debug' => array());
	    try {
		    //check to see if a valid $userinfo object was passed on
		    if (!is_object($userinfo)) {
			    throw new RuntimeException(JText::_('NO_USER_DATA_FOUND'));
		    } else {
			    //get the user
			    $existinguser = $this->getUser($userinfo);
			    if (!empty($existinguser)) {
				    $changed = false;
				    //a matching user has been found
				    $status['debug'][] = JText::_('USER_DATA_FOUND');
				    if (strtolower($existinguser->email) != strtolower($userinfo->email)) {
					    $status['debug'][] = JText::_('EMAIL_CONFLICT');
					    if ($update_email || $overwrite) {
						    $status['debug'][] = JText::_('EMAIL_CONFLICT_OVERWITE_ENABLED');
						    $this->updateEmail($userinfo, $existinguser, $status);
						    $changed = true;
					    } else {
						    //return a email conflict
						    $status['debug'][] = JText::_('EMAIL_CONFLICT_OVERWITE_DISABLED');
						    $status['userinfo'] = $existinguser;
						    throw new RuntimeException(JText::_('EMAIL') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
					    }
				    }
				    if (!empty($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
					    // add password_clear to existinguser for the Joomla helper routines
					    $existinguser->password_clear = $userinfo->password_clear;
					    //check if the password needs to be updated
					    try {
						    $model = JFusionFactory::getAuth($this->getJname());
						    $testcrypt = $model->generateEncryptedPassword($existinguser);
						    if ($testcrypt != $existinguser->password) {
							    $this->updatePassword($userinfo, $existinguser, $status);
							    $changed = true;
						    } else {
							    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ':' . JText::_('PASSWORD_VALID');
						    }
					    } catch (Exception $e) {
						    $status['error'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ':' . $e->getMessage();
					    }
				    } else {
					    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
				    }
				    //check the blocked status
				    if ($existinguser->block != $userinfo->block) {
					    if ($update_block || $overwrite) {
						    if ($userinfo->block) {
							    //block the user
							    $this->blockUser($userinfo, $existinguser, $status);
							    $changed = true;
						    } else {
							    //unblock the user
							    $this->unblockUser($userinfo, $existinguser, $status);
							    $changed = true;
						    }
					    } else {
						    //return a debug to inform we skipped this step
						    $status['debug'][] = JText::_('SKIPPED_BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
					    }
				    }
				    //check the activation status
				    if (isset($existinguser->activation)) {
					    if ($existinguser->activation != $userinfo->activation) {
						    if ($update_activation || $overwrite) {
							    if ($userinfo->activation) {
								    //inactive the user
								    $this->inactivateUser($userinfo, $existinguser, $status);
								    $changed = true;
							    } else {
								    //activate the user
								    $this->activateUser($userinfo, $existinguser, $status);
								    $changed = true;
							    }
						    } else {
							    //return a debug to inform we skipped this step
							    $status['debug'][] = JText::_('SKIPPED_ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
						    }
					    }
				    }
				    //check for advanced usergroup sync
				    if (!$userinfo->block && empty($userinfo->activation)) {
					    if (JFusionFunction::updateUsergroups($this->getJname())) {
						    $usergroup_updated = $this->executeUpdateUsergroup($userinfo, $existinguser, $status);
						    if ($usergroup_updated) {
							    $changed = true;
						    } else {
							    $status['debug'][] = JText::_('SKIPPED_GROUP_UPDATE') . ':' . JText::_('GROUP_VALID');
						    }
					    }
				    }

				    //Update the user language with the current used in Joomla or the one existing from an other plugin
				    if (empty($userinfo->language)) {
					    $user_lang = (!empty($user_params)) ? $user_params->get('language') : '';
					    $userinfo->language = ($user_lang) ? $user_lang : JFactory::getLanguage()->getTag();
				    }
				    if (!empty($userinfo->language) && isset($existinguser->language) && !empty($existinguser->language) && $userinfo->language != $existinguser->language) {
					    $this->updateUserLanguage($userinfo, $existinguser, $status);
					    $existinguser->language = $userinfo->language;
					    $status['debug'][] = JText::_('LANGUAGE_UPDATED') . ' : ' . $existinguser->language . ' -> ' . $userinfo->language;
					    $changed = true;
				    } else {
					    //return a debug to inform we skipped this step
					    $status['debug'][] = JText::_('LANGUAGE_NOT_UPDATED');
				    }

				    if (empty($status['error'])) {
					    if ($changed == true) {
						    $status['action'] = 'updated';
						    //let's get updated information
						    $status['userinfo'] = $this->getUser($userinfo);
					    } else {
						    $status['action'] = 'unchanged';
						    $status['userinfo'] = $existinguser;
					    }
				    } else {
					    $status['action'] = 'error';
				    }
			    } else {
				    $status['debug'][] = JText::_('NO_USER_FOUND_CREATING_ONE');
				    //check activation and block status
				    $create_inactive = $this->params->get('create_inactive', 1);
				    $create_blocked = $this->params->get('create_blocked', 1);
				    if ((empty($create_inactive) && !empty($userinfo->activation)) || (empty($create_blocked) && !empty($userinfo->block))) {
					    //block user creation
					    $status['debug'][] = JText::_('SKIPPED_USER_CREATION');
					    $status['action'] = 'unchanged';
					    $status['userinfo'] = $existinguser;
				    } else {
					    $this->createUser($userinfo, $status);
					    if (empty($status['error'])) {
						    $status['action'] = 'created';
					    } else {
						    $status['action'] = 'error';
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * Function that determines if the usergroup needs to be updated and executes updateUsergroup if it does
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return boolean Whether updateUsergroup was executed or not
     */
    function executeUpdateUsergroup(&$userinfo, &$existinguser, &$status)
    {
        $changed = false;
        $usergroups = $this->getCorrectUserGroups($userinfo);
		if (!$this->compareUserGroups($existinguser, $usergroups)) {
            $this->updateUsergroup($userinfo, $existinguser, $status);
            $changed = true;
        }
    	return $changed;
    }

    /**
     * Function that updates the user password
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    function updatePassword($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'updatePassword function not implemented';
    }

    /**
     * Function that updates the username
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    function updateUsername($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'updateUsername function not implemented';
    }

    /**
     * Function that updates the user email address
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    function updateEmail($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'updateEmail function not implemented';
    }

    /**
     * Function that updates the usergroup
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    function updateUsergroup($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'updateUsergroup function not implemented';
    }

    /**
     * Function that updates the blocks the user account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    function blockUser($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'blockUser function not implemented';
    }

    /**
     * Function that unblocks the user account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    function unblockUser($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'unblockUser function not implemented';
    }

    /**
     * Function that activates the users account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    function activateUser($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'activateUser function not implemented';
    }

    /**
     * Function that inactivates the users account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    function inactivateUser($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'inactivate function not implemented';
    }

    /**
     * Function that creates a new user account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo Object containing the new userinfo
     * @param array  &$status  Array containing the errors and result of the function
     */
    function createUser($userinfo, &$status)
    {
    }

    /**
     * Function that deletes a user account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param object $userinfo Object containing the existing userinfo
     *
     * @return array status Array containing the errors and result of the function
     */
    function deleteUser($userinfo)
    {
        //setup status array to hold debug info and errors
        $status = array('error' => array(), 'debug' => array());
        $status['error'][] = JText::_('DELETE_FUNCTION_MISSING');
        return $status;
    }

    /**
     * Function that update the language of a user
     *
     * @param object $userinfo Object containing the existing userinfo
     * @param object $existinguser         Object JLanguage containing the current language of Joomla
     * @param array  &$status      Array containing the errors and result of the function
     */
    function updateUserLanguage($userinfo, &$existinguser, &$status)
    {
        $status['debug'][] = 'Update user language method not implemented';
    }

    /**
     * Function that that is used to keep sessions in sync and/or alive
     *
     * @param boolean $keepalive    Tells the function to regenerate the inactive session as long as the other is active
     * unless there is a persistent cookie available for inactive session
     * @return integer 0 if no session changes were made, 1 if session created
     */
    function syncSessions($keepalive = false)
    {
        return 0;
    }

	/**
	 * compare set of usergroup with a user returns true if the usergroups are correct
	 *
	 * @param object $userinfo user with current usergroups
	 * @param array $usergroups array with the correct usergroups
	 *
	 * @return boolean
	 */
	public function compareUserGroups($userinfo, $usergroups) {
		if (!is_array($usergroups)) {
			$usergroups = array($usergroups);
		}
		$correct = false;
		if (isset($userinfo->groups)) {
			$count = 0;
			if ( count($usergroups) == count($userinfo->groups) ) {
				foreach ($usergroups as $group) {
					if (in_array($group, $userinfo->groups, true)) {
						$count++;
					}
				}
				if (count($userinfo->groups) == $count) {
					$correct = true;
				}
			}
		} else {
			foreach ($usergroups as $group) {
				if ($group == $userinfo->group_id) {
					$correct = true;
					break;
				}
			}
		}
		return $correct;
	}

	/**
	 * Function That find the correct user group index
	 *
	 * @param array $mastergroups
	 * @param stdClass $userinfo
	 *
	 * @return int
	 */
	function getUserGroupIndex($mastergroups, $userinfo)
	{
		$index = 0;

		$groups = array();
		if ($userinfo) {
			if (isset($userinfo->groups)) {
				$groups = $userinfo->groups;
			} elseif (isset($userinfo->group_id)) {
				$groups[] = $userinfo->group_id;
			}
		}

		foreach ($mastergroups as $key => $mastergroup) {
			if ($mastergroup) {
				if ( count($mastergroup) == count($groups) ) {
					$count = 0;
					foreach ($mastergroup as $value) {
						if (in_array($value, $groups, true)) {
							$count++;
						}
					}
					if (count($groups) == $count ) {
						$index = $key;
						break;
					}
				}
			}
		}
		return $index;
	}

	/**
	 * Common code for user.php
	 *
	 * @param object $userinfo userinfo
	 * @param array $options  options
	 * @param string $type    jname
	 * @param array $curl_options_merge
	 *
	 * @return string nothing
	 */
	final public function curlLogin($userinfo, $options, $type = 'brute_force', $curl_options_merge = array())
	{
		require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
		$curl_options = array();
		$status = array('error' => array(), 'debug' => array());
		$source_url = $this->params->get('source_url');
		$login_url = $this->params->get('login_url');
		//prevent user error by not supplying trailing forward slash
		if (substr($source_url, -1) != '/') {
			$source_url = $source_url . '/';
		}
		//prevent user error by preventing a heading forward slash
		ltrim($login_url, '/');
		$curl_options['post_url'] = $source_url . $login_url;

		$curl_options['formid'] = $this->params->get('loginform_id');

		$login_identifier = $this->params->get('login_identifier', '1');
		$identifier = ($login_identifier === '2') ? 'email' : 'username';

		$curl_options['username'] = $userinfo->$identifier;
		$curl_options['password'] = $userinfo->password_clear;
		$integrationtype1 = $this->params->get('integrationtype');
		$curl_options['relpath']=  $this->params->get('relpath');
		$curl_options['hidden'] = $this->params->get('hidden');
		$curl_options['buttons'] = $this->params->get('buttons');
		$curl_options['override'] = $this->params->get('override');
		$curl_options['cookiedomain'] = $this->params->get('cookie_domain');
		$curl_options['cookiepath'] = $this->params->get('cookie_path');
		$curl_options['expires'] = $this->params->get('cookie_expires');
		$curl_options['input_username_id'] = $this->params->get('input_username_id');
		$curl_options['input_password_id'] = $this->params->get('input_password_id');
		$curl_options['secure'] = $this->params->get('secure');
		$curl_options['httponly'] = $this->params->get('httponly');
		$curl_options['verifyhost'] = 0; //$params->get('ssl_verifyhost');
		$curl_options['httpauth'] = $this->params->get('httpauth');
		$curl_options['httpauth_username'] = $this->params->get('curl_username');
		$curl_options['httpauth_password'] = $this->params->get('curl_password');

		// to prevent endless loops on systems where there are multiple places where a user can login
		// we post an unique ID for the initiating software so we can make a difference between
		// a user logging in or another jFusion installation, or even another system with reverse dual login code.
		// We always use the source url of the initializing system, here the source_url as defined in the joomla_int
		// plugin. This is totally transparent for the the webmaster. No additional setup is needed

		$my_ID = rtrim(parse_url(JURI::root(), PHP_URL_HOST) . parse_url(JURI::root(), PHP_URL_PATH), '/');
		$curl_options['jnodeid'] = strtolower($my_ID);

		// For further simplifying setup we send also an indication if this system is a host. Other hosts should
		// only perform local joomla login when received this post. We define being a host if we have
		// at least one slave.

		$plugins = JFusionFactory::getPlugins('slave');
		if (count($plugins) > 2 ) {
			$jhost = true;
		} else {
			$jhost = false;
		}

		if ($jhost) {
			$curl_options['jhost'] = true;
		}
		if (!empty($curl_options_merge)) {
			$curl_options = array_merge($curl_options, $curl_options_merge);
		}

		// This check is just for Jfusion 1.x to support the reverse dual login function
		// We need to check if JFusion tries to create this session because of this integration
		// initiated a login by means of the reverse dual login extensions. Note that
		// if the curl routines are not used, the same check must be performed in the
		// create session routine in the user.php file of the plugin concerned.
		// In version 2.0 we will never reach this point as the user plugin will handle this
		$jnodeid = strtolower(JFactory::getApplication()->input->get('jnodeid'));
		if (!empty($jnodeid)) {
			if($jnodeid == JFusionFactory::getPluginNodeId($this->getJname())) {
				// do not create a session, this integration started the log in and the user is already logged in
				$status['debug'][] = JText::_('ALREADY_LOGGED_IN');
				return $status;
			}
		}

		// correction of the integration type for Joomla Joomla using a sessionid in the logout form
		// for joomla 1.5 we need integration type 1 for login (LI) and 0 for logout (LO)
		// this is backward compatible
		// joomla 1.5  : use 3
		// joomla 1.6+ : use 1

		switch ($integrationtype1) {
			case '0':				// LI = 0  LO = 0
			case '2':				// LI = 0, LO = 1
				$integrationtype = 0;
				break;
			case '1':				// LI = 1  LO = 1
			case '3':				// LI = 1, LO = 0
			default:
				$integrationtype = 1;
				break;
		}

		$curl_options['integrationtype'] = $integrationtype;


		// extra lines for passing curl options to other routines, like ambrasubs payment processor
		// we are using the super global $_SESSION to pass data in $_SESSION[$var]
		$var = 'curl_options';
		if(!array_key_exists($var, $_SESSION)) $_SESSION[$var] = '';
		$_SESSION[$var] = $curl_options;
		$GLOBALS[$var] = &$_SESSION[$var];
		// end extra lines

		$type = strtolower($type);
		switch ($type) {
			case 'url':
//              $status = JFusionCurl::RemoteLoginUrl($curl_options);
				$status['error'][] = JText::_('CURL_LOGINTYPE_NOT_SUPPORTED');
				break;
			case 'brute_force':
				$curl_options['brute_force'] = $type;
				$status = JFusionCurl::RemoteLogin($curl_options);
				break;
			default:
				$status = JFusionCurl::RemoteLogin($curl_options);
		}
		$status['debug'][] = JText::_('CURL_LOGINTYPE') . '=' . $type;
		return $status;
	}


	/**
	 * Function that automatically logs out the user from the integrated software
	 *
	 * @param object $userinfo contains the userinfo
	 * @param array  $options  contains Array with the login options, such as remember_me
	 * @param string $type     method of destruction
	 * @param array $curl_options_merge
	 *
	 * @return array result Array containing the result of the session destroy
	 */
	final public function curlLogout($userinfo, $options, $type = 'brute_force', $curl_options_merge = array())
	{
		require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
		$curl_options = array();
		$status = array('error' => array(), 'debug' => array());

		$source_url = $this->params->get('source_url');
		$logout_url = $this->params->get('logout_url');
		//prevent user error by not supplying trailing forward slash
		if (substr($source_url, -1) != '/') {
			$source_url = $source_url . '/';
		}
		//prevent user error by preventing a heading forward slash
		ltrim($logout_url, '/');
		$curl_options['post_url'] = $source_url . $logout_url;

		$curl_options['formid'] = $this->params->get('loginform_id');
		$curl_options['username'] = $userinfo->username;
//        $curl_options['password'] = $userinfo->password_clear;
		$integrationtype1 = $this->params->get('integrationtype');
		$curl_options['relpath'] = $this->params->get('relpathl', $this->params->get('relpath', 0));
		$curl_options['hidden'] = '1';
		$curl_options['buttons'] = '1';
		$curl_options['override'] = '';
		$curl_options['cookiedomain'] = $this->params->get('cookie_domain');
		$curl_options['cookiepath'] = $this->params->get('cookie_path');
		$curl_options['expires'] = time() - 30*60*60;
		$curl_options['input_username_id'] = $this->params->get('input_username_id');
		$curl_options['input_password_id'] = $this->params->get('input_password_id');
		$curl_options['secure'] = $this->params->get('secure');
		$curl_options['httponly'] = $this->params->get('httponly');
		$curl_options['verifyhost'] = 0; //$params->get('ssl_verifyhost');
		$curl_options['httpauth'] = $this->params->get('httpauth');
		$curl_options['httpauth_username'] = $this->params->get('curl_username');
		$curl_options['httpauth_password'] = $this->params->get('curl_password');
		$curl_options['leavealone'] = $this->params->get('leavealone');
		$curl_options['postfields'] = $this->params->get('postfields','');
		$curl_options['logout'] = '1';

		// to prevent endless loops on systems where there are multiple places where a user can login
		// we post an unique ID for the initiating software so we can make a difference between
		// a user logging in or another jFusion installation, or even another system with reverse dual login code.
		// We always use the source url of the initializing system, here the source_url as defined in the joomla_int
		// plugin. This is totally transparent for the the webmaster. No additional setup is needed

		$my_ID = rtrim(parse_url(JURI::root(), PHP_URL_HOST) . parse_url(JURI::root(), PHP_URL_PATH), '/');
		$curl_options['jnodeid'] = strtolower($my_ID);

		// For further simplifying setup we send also an indication if this system is a host. Other hosts should
		// only perform local joomla login when received this post. We define being a host if we have
		// at least one slave.


		$plugins = JFusionFactory::getPlugins('slave');
		if (count($plugins) > 2 ) {
			$jhost = true;
		} else {
			$jhost = false;
		}

		if ($jhost) {
			$curl_options['jhost'] = true;
		}
		if (!empty($curl_options_merge)) {
			$curl_options = array_merge($curl_options, $curl_options_merge);
		}

		// This check is just for Jfusion 1.x to support the reverse dual login function
		// We need to check if JFusion tries to delete this session because of this integration
		// initiated a logout by means of the reverse dual login extensions. Note that
		// if the curl routines are not used, the same check must be performed in the
		// destroysession routine in the user.php file of the plugin concerned.
		// In version 2.0 we will never reach this point as the user plugin will handle this
		$jnodeid = strtolower(JFactory::getApplication()->input->get('jnodeid'));
		if (!empty($jnodeid)) {
			if($jnodeid == JFusionFactory::getPluginNodeId($this->getJname())) {
				// do not delete a session, this integration started the log out and the user is already logged out
				$status['debug'][] = JText::_('ALREADY_LOGGED_OUT');
				return $status;
			}
		}

		// correction of the integration type for Joomla Joomla using a sessionid in the logout form
		// for joomla 1.5 we need integration type 1 for login (LI) and 0 for logout (LO)
		// this is backward compatible
		// joomla 1.5  : use 3
		// joomla 1.6+ : use 1

		switch ($integrationtype1) {
			case '0':				// LI = 0  LO = 0
			case '3':				// LI = 1, LO = 0
				$integrationtype = 0;
				break;
			case '1':				// LI = 1  LO = 1
			case '2':				// LI = 0, LO = 1
			default:
				$integrationtype = 1;
				break;
		}
		$curl_options['integrationtype'] = $integrationtype;

		$type = strtolower($type);
		switch ($type) {
			case 'url':
				$status = JFusionCurl::RemoteLogoutUrl($curl_options);
				break;
			case 'form':
				$status = JFusionCurl::RemoteLogin($curl_options);
				break;
			case 'brute_force':
			default:
				$status = JFusionCurl::RemoteLogout($curl_options);
		}
		$status['debug'][] = JText::_('CURL_LOGOUTTYPE') . '=' . $type;
		return $status;
	}

	/**
	 * return the correct usergroups for a given user
	 *
	 * @param object|null $userinfo user with correct usergroups, if null it will return the usergroup for new users
	 *
	 * @return array
	 */
	final public function getCorrectUserGroups($userinfo)
	{
		$jname = $this->getJname();
		$group = array();

		$master = JFusionFunction::getMaster();

		$mastergroups = JFusionFunction::getUserGroups($master->name);
		$slavegroups = JFusionFunction::getUserGroups($jname);

		if ($master->name == $jname) {
			if (isset($mastergroups[0])) {
				$group = $mastergroups[0];
			}
		} else {
			$user = JFusionFactory::getUser($master->name);
			$index = $user->getUserGroupIndex($mastergroups, $userinfo);

			if (isset($slavegroups[$index])) {
				$group = $slavegroups[$index];
			}

			if ($group === null && isset($slavegroups[0])) {
				$group =  $slavegroups[0];
			}
		}
		if (!is_array($group)) {
			if ($group !==  null) {
				$group = array($group);
			} else {
				$group = array();
			}
		}
		return $group;
	}

	/**
	 * Adds a cookie to the php header
	 *
	 * @param string $name      cookie name
	 * @param string $value     cookie value
	 * @param int    $expires   cookie expiry time
	 * @param string $path      cookie path
	 * @param string $domain    cookie domain
	 * @param bool $secure      is the secure
	 * @param bool $httponly    is the cookie http only
	 * @param bool $mask        should debug info be masked ?
	 *
	 * @return array            cookie debug info
	 */
	final public function addCookie($name, $value, $expires, $path, $domain, $secure = false, $httponly = false, $mask = false)
	{
		$cookies = JFusionFactory::getCookies();
		return $cookies->addCookie($name, $value, $expires, $path, $domain, $secure, $httponly, $mask);
	}
}
