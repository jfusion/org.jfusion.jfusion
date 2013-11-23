<?php

/**
 * Model for joomla actions
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
 * Common Class for Joomla JFusion plugins
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionJoomlaUser extends JFusionUser
{
    /**
     * Function that updates the user email
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function updateEmail($userinfo, &$existinguser, &$status)
    {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('email = ' . $db->quote($userinfo->email))
			    ->where('id = ' . $db->quote($existinguser->userid));

	        $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that updates the user password
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function updatePassword($userinfo, &$existinguser, &$status)
    {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());
		    jimport('joomla.user.helper');
	        $userinfo->password_salt = JUserHelper::genRandomPassword(32);
	        $userinfo->password = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt);
	        $new_password = $userinfo->password . ':' . $userinfo->password_salt;

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('password = ' . $db->quote($new_password))
			    ->where('id = ' . $db->quote($existinguser->userid));

	        $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that blocks user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function blockUser($userinfo, &$existinguser, &$status)
    {
	    try {
	        //do not block super administrators
	        if ($existinguser->group_id != 25) {
	            //block the user
	            $db = JFusionFactory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->update('#__users')
			        ->set('block = 1')
			        ->where('id = ' . $db->quote($existinguser->userid));

	            $db->setQuery($query);
		        $db->execute();

		        $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	        } else {
	            $status['debug'][] = JText::_('BLOCK_UPDATE_ERROR') . ': ' . JText::_('CANNOT_BLOCK_SUPERADMINS');
	        }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that unblocks user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function unblockUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    //unblock the user
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('block = 0')
			    ->where('id = ' . $db->quote($existinguser->userid));

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that activates user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function activateUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    //unblock the user
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('block = 0')
			    ->set('activation = ' . $db->quote(''))
			    ->where('id = ' . $db->quote($existinguser->userid));

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * Function that inactivates user
     *
     * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     *
     * @return string updates are passed on into the $status array
     */
    public function inactivateUser($userinfo, &$existinguser, &$status)
    {
	    try {
		    if ($existinguser->group_id != 25) {
			    //unblock the user
			    $db = JFusionFactory::getDatabase($this->getJname());

			    $query = $db->getQuery(true)
				    ->update('#__users')
				    ->set('block = 1')
				    ->set('activation = ' . $db->quote($userinfo->activation))
				    ->where('id = ' . $db->quote($existinguser->userid));

			    $db->setQuery($query);
			    $db->execute();

			    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		    } else {
			    $status['debug'][] = JText::_('ACTIVATION_UPDATE_ERROR') . ': ' . JText::_('CANNOT_INACTIVATE_SUPERADMINS');
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * filters the username to remove invalid characters
     *
     * @param string $username contains username
     *
     * @return string filtered username
     */
    public function filterUsername($username)
    {
	    //check to see if additional username filtering need to be applied
	    $added_filter = $this->params->get('username_filter');
	    if ($added_filter && $added_filter != $this->getJname()) {
		    $JFusionPlugin = JFusionFactory::getUser($added_filter);
		    if (method_exists($JFusionPlugin, 'filterUsername')) {
			    $filteredUsername = $JFusionPlugin->filterUsername($username);
		    }
	    }
	    //make sure the filtered username isn't empty
	    $username = (!empty($filteredUsername)) ? $filteredUsername : $username;
	    //define which characters which Joomla forbids in usernames
	    $trans = array('&#60;' => '_', '&lt;' => '_', '&#62;' => '_', '&gt;' => '_', '&#34;' => '_', '&quot;' => '_', '&#39;' => '_', '&#37;' => '_', '&#59;' => '_', '&#40;' => '_', '&#41;' => '_', '&amp;' => '_', '&#38;' => '_', '<' => '_', '>' => '_', '"' => '_', '\'' => '_', '%' => '_', ';' => '_', '(' => '_', ')' => '_', '&' => '_');
	    //remove forbidden characters for the username
	    $username = strtr($username, $trans);
	    //make sure the username is at least 2 characters long
	    while (strlen($username) < 2) {
		    $username.= '_';
	    }
        return $username;
    }

    /**
     * Updates or creates a user for the integrated software. This allows JFusion to have external software as slave for user management
     *
     * @param object $userinfo  contains the userinfo
     * @param int    $overwrite determines if the userinfo can be overwritten
     *
     * @return array result Array containing the result of the user update
     */
    public function updateUser($userinfo, $overwrite = 0)
    {
	    $status = array('error' => array(), 'debug' => array());
	    try {
		    // Initialise some variables
		    $update_block = $this->params->get('update_block');
		    $update_activation = $this->params->get('update_activation');
		    $update_email = $this->params->get('update_email');
		    //check to see if a valid $userinfo object was passed on
		    if (!is_object($userinfo)) {
			    throw new RuntimeException(JText::_('NO_USER_DATA_FOUND'));
		    } else {
			    //check to see if user exists
			    $existinguser = $this->getUser($userinfo);
			    if (!empty($existinguser)) {
				    $changed = false;
				    //a matching user has been found
				    $status['debug'][] = JText::_('USER_DATA_FOUND');
				    // email update?
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
				    // password update ?
				    if (!empty($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
					    //if not salt set, update the password
					    $existinguser->password_clear = $userinfo->password_clear;
					    //check if the password needs to be updated
					    $model = JFusionFactory::getAuth($this->getJname());
					    $testcrypt = $model->generateEncryptedPassword($existinguser);
					    //if the passwords are not the same or if Joomla salt has inherited a colon which will confuse Joomla without JFusion; generate a new password hash
					    if ($testcrypt != $existinguser->password || strpos($existinguser->password_salt, ':') !== false) {
						    $this->updatePassword($userinfo, $existinguser, $status);
						    $changed = true;
					    } else {
						    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_VALID');
					    }
				    } else {
					    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
				    }
				    //block status update?
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
				    //activation status update?
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
						    $status['debug'][] = JText::_('SKIPPED_EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
					    }
				    }
				    //check for advanced usergroup sync
				    if (!$userinfo->block && empty($userinfo->activation)) {
					    if (JFusionFunction::updateUsergroups($this->getJname())) {
						    $usergroups = $this->getCorrectUserGroups($userinfo);
						    if (!$this->compareUserGroups($existinguser, $usergroups)) {
							    $this->updateUsergroup($userinfo, $existinguser, $status);
							    $changed = true;
						    } else {
							    $status['debug'][] = JText::_('SKIPPED_GROUP_UPDATE') . ':' . JText::_('GROUP_VALID');
						    }
					    }
				    }

				    //Update the user language in the one existing from an other plugin
				    if (!empty($userinfo->language) && !empty($existinguser->language) && $userinfo->language != $existinguser->language) {
					    $this->updateUserLanguage($userinfo, $existinguser, $status);
					    $existinguser->language = $userinfo->language;
					    $changed = true;
				    } else {
					    //return a debug to inform we skipped this step
					    $status['debug'][] = JText::_('LANGUAGE_NOT_UPDATED');
				    }

				    if (empty($status['error'])) {
					    if ($changed == true) {
						    $status['action'] = 'updated';
						    $status['userinfo'] = $this->getUser($userinfo);
					    } else {
						    $status['action'] = 'unchanged';
						    $status['userinfo'] = $existinguser;
					    }
				    }
			    } else {
				    $status['debug'][] = JText::_('NO_USER_FOUND_CREATING_ONE');
				    $this->createUser($userinfo, $status);
				    if (empty($status['error'])) {
					    $status['action'] = 'created';
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
        return $status;
    }

    /************************************************
    * Functions For JFusion Who's Online Module
    ***********************************************/



    /**
     * Update the language user in his account when he logs in Joomla or
     * when the language is changed in the frontend
     *
     * @see JFusionJoomlaUser::updateUser
     * @see JFusionJoomlaPublic::setLanguageFrontEnd
     *
	 * @param object $userinfo      Object containing the new userinfo
     * @param object &$existinguser Object containing the old userinfo
     * @param array  &$status       Array containing the errors and result of the function
     */
    public function updateUserLanguage($userinfo, &$existinguser, &$status)
    {
	    try {
		    /**
		     * @TODO joomla 1.5/1.6 if we are talking to external joomla since joomla 1.5 store params in json
		     */
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $params = new JRegistry($existinguser->params);
		    $params->set('language', $userinfo->language);

		    $query = $db->getQuery(true)
			    ->update('#__users')
			    ->set('params = ' . $db->quote($params->toString()))
			    ->where('id = ' . $db->quote($existinguser->userid));

		    $db->setQuery($query);

		    $db->execute();
		    $status['debug'][] = JText::_('LANGUAGE_UPDATE') . ' ' . $existinguser->language;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('LANGUAGE_UPDATE_ERROR') . $e->getMessage();
	    }
    }
}