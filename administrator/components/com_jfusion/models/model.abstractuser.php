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
class JFusionUser
{
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
        $params = JFusionFactory::getParams($this->getJname());
        //the discussion bot may need to override the identifier_type to prevent user hijacking by guests
        $override = (defined('OVERRIDE_IDENTIFIER')) ? OVERRIDE_IDENTIFIER : 'default';
        $options = array('0', '1', '2');
        if (in_array($override, $options)) {
            $login_identifier = $override;
        } else {
            $login_identifier = $params->get('login_identifier', 1);
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
                    $pattern = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/i";
                    if (preg_match($pattern, $identifier)) {
                        $identifier_type = $email_col;
                    } else {
                        $identifier_type = $username_col;
                    }
                } else {
                    $pattern = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/i";
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
            $identifier_type = 'LOWER('.$identifier_type.')';
            $identifier = strtolower($identifier);
        }
        return array($identifier_type, $identifier);
    }

    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return '';
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
        //$db = JFusionFactory::getDatabase($this->getJname());
        $params = JFusionFactory::getParams($this->getJname());
        if (!empty($userinfo->params)) {
            $user_params = new JParameter($userinfo->params);
        }
        $update_block = $params->get('update_block');
        $update_activation = $params->get('update_activation');
        $update_email = $params->get('update_email');
        $status = array('error' => array(),'debug' => array());
        //check to see if a valid $userinfo object was passed on
        if (!is_object($userinfo)) {
            $status['error'][] = JText::_('NO_USER_DATA_FOUND');
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
                        $status['error'][] = JText::_('EMAIL') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
                        $status['userinfo'] = $existinguser;
                        return $status;
                    }
                }
                if (!empty($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
                    // add password_clear to existinguser for the Joomla helper routines
                    $existinguser->password_clear = $userinfo->password_clear;
                    //check if the password needs to be updated
                    $model = JFusionFactory::getAuth($this->getJname());
                    $testcrypt = $model->generateEncryptedPassword($existinguser);
                    if ($testcrypt != $existinguser->password) {
                        $this->updatePassword($userinfo, $existinguser, $status);
                        $changed = true;
                    } else {
                        $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ':' . JText::_('PASSWORD_VALID');
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
                $master = JFusionFunction::getMaster();
                if (!$userinfo->block && empty($userinfo->activation) && $master->name != $this->getJname()) {
                    if (JFusionFunction::isAdvancedUsergroupMode($this->getJname())) {
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
                $create_inactive = $params->get('create_inactive', 1);
                $create_blocked = $params->get('create_blocked', 1);
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
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
		if (!JFusionFunction::compareUserGroups($existinguser,$usergroups)) {
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
        $status = array('error' => array(),'debug' => array());
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
}
