<?php

/**
 * This is the jfusion user plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage User
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Factory;
use JFusion\Framework;

defined('_JEXEC') or die('Restricted access');
/**
 * Load the JFusion framework
 */
jimport('joomla.plugin.plugin');
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
/**
 * JFusion User class
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage User
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class plgUserJfusion extends JPlugin
{
	/**
	 * Constructor
	 *
	 * For php4 compatibility we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object &$subject The object to observe
	 * @param array  $config   An array that holds the plugin configuration
	 *
	 * @since 1.5
	 * @return void
	 */
	function plgUserJfusion(&$subject, $config)
	{
		parent::__construct($subject, $config);
		//load the language
		$this->loadLanguage('com_jfusion', JPATH_BASE);
	}

	/**
	 * This method is called after user is stored
	 *
	 * @param array   $user   holds the user data
	 * @param boolean $isnew  is new user
	 * @param boolean $success was it a success
	 * @param string  $msg    Message
	 *
	 * @access public
	 * @return boolean False on Error
	 */
	function onAfterStoreUser($user, $isnew, $success, $msg)
	{
		/**
		 * TODO: finish moving this to user->save(..)
		 */
		if (!$success) {
			$result = false;
			return $result;
		}
		//create an array to store the debug info
		$debug_info = array();
		$error_info = array();
		$master_userinfo = null;
		//prevent any output by the plugins (this could prevent cookies from being passed to the header)
		ob_start();
		$Itemid_backup = JFactory::getApplication()->input->getInt('Itemid', 0);
		global $JFusionActive;
		if (!$JFusionActive) {
			//A change has been made to a user without JFusion knowing about it

			$JoomlaUser = JFusionFunction::getJoomlaUser((object)$user);

			if (!isset($JoomlaUser->group_id) && !empty($JoomlaUser->groups)) {
				$JoomlaUser->group_id = $JoomlaUser->groups[0];
			}
			//check to see if we need to update the master
			$master = Framework::getMaster();
			// Recover the old data of the user
			// This is then used to determine if the username was changed
			$session = JFactory::getSession();
			$JoomlaUser->olduserinfo = (object)$session->get('olduser');
			$session->clear('olduser');
			$updateUsername = (!$isnew && $JoomlaUser->olduserinfo->username != $JoomlaUser->username) ? true : false;
			//retrieve the username stored in jfusion_users if it exists
			$db = JFactory::getDBO();

			$query = $db->getQuery(true)
				->select('username')
				->from('#__jfusion_users')
				->where('id = ' . (int)$JoomlaUser->userid);

			$db->setQuery($query);
			$storedUsername = $db->loadResult();
			if ($updateUsername) {
				try {
					$update = new stdClass();
					$update->id = $JoomlaUser->userid;
					$update->username = $JoomlaUser->username;
					if ($storedUsername) {
						$db->updateObject('#__jfusion_users', $update, 'id');
					} else {
						$db->insertObject('#__jfusion_users', $update);
					}
				} catch ( Exception $e ) {
					Framework::raiseError($e);
				}

				//if we had a username stored in jfusion_users, update the olduserinfo with that username before passing it into the plugins so they will find the intended user
				if (!empty($storedUsername)) {
					$JoomlaUser->olduserinfo->username = $storedUsername;
				}
			} else {
				if (!empty($JoomlaUser->original_username)) {
					//the user was created by JFusion's JFusionJoomlaUser::createUser and we have the original username which must be used as the jfusion_user table has not been updated yet
					$JoomlaUser->username = $JoomlaUser->original_username;
				} elseif (!empty($storedUsername)) {
					//the username is not being updated but if there is a username stored in jfusion_users table, it must be used instead to prevent user duplication
					$JoomlaUser->username = $storedUsername;
				}
			}
			try {
				$JFusionMaster = Factory::getUser($master->name);
				//update the master user if not joomla_int
				if ($master->name != 'joomla_int') {
					$master_userinfo = $JFusionMaster->getUser($JoomlaUser->olduserinfo);
					//if the username was updated, call the updateUsername function before calling updateUser
					if ($updateUsername) {
						if (!empty($master_userinfo)) {
							try {
								$updateUsernameStatus = array();
								$JFusionMaster->debugger->set(null, $updateUsernameStatus);
								$JFusionMaster->updateUsername($JoomlaUser, $master_userinfo, $updateUsernameStatus);
								$JFusionMaster->mergeStatus($updateUsernameStatus);
								if (!$JFusionMaster->debugger->isEmpty('error')) {
									$error_info[$master->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $JFusionMaster->debugger->get('error');
								}
								if (!$JFusionMaster->debugger->isEmpty('debug')) {
									$debug_info[$master->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG') ] = $JFusionMaster->debugger->get('debug');
								}
							} catch (Exception $e) {
								$status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
							}
						} else {
							$error_info[$master->name] = JText::_('NO_USER_DATA_FOUND');
						}
					}
					try {
						//run the update user to ensure any other userinfo is updated as well
						$MasterUser = $JFusionMaster->updateUser($JoomlaUser, 1);
						if (!empty($MasterUser['error'])) {
							$error_info[$master->name] = $MasterUser['error'];
						}
						if (!empty($MasterUser['debug'])) {
							$debug_info[$master->name] = $MasterUser['debug'];
						}
						//make sure the userinfo is available
						if (empty($MasterUser['userinfo'])) {
							$userinfo = $JFusionMaster->getUser($JoomlaUser);
						} else {
							$userinfo = $MasterUser['userinfo'];
						}
						//update the jfusion_users_plugin table
						$JFusionMaster->updateLookup($userinfo, $JoomlaUser, 'joomla_int');
					} catch (Exception $e) {
						$error_info[$master->name] = array($e->getMessage());
					}
				} else {
					//Joomla is master
					// commented out because we should use the joomla use object (in out plugins)
					//	            $master_userinfo = $JoomlaUser;
					$master_userinfo = $JFusionMaster->getUser($JoomlaUser);
				}
			} catch (Exception $e) {
				$error_info[$master->name] = array($e->getMessage());
			}

			if ($master_userinfo) {
				if ( !empty($JoomlaUser->password_clear) ) {
					$master_userinfo->password_clear = $JoomlaUser->password_clear;
				}
				//update the user details in any JFusion slaves
				$slaves = Factory::getPlugins('slave');
				foreach ($slaves as $slave) {
					try {
						$JFusionSlave = Factory::getUser($slave->name);
						//if the username was updated, call the updateUsername function before calling updateUser
						if ($updateUsername) {
							$slave_userinfo = $JFusionSlave->getUser($JoomlaUser->olduserinfo);
							if (!empty($slave_userinfo)) {
								try {
									$updateUsernameStatus = array();
									$JFusionSlave->debugger->set(null, $updateUsernameStatus);
									$JFusionSlave->updateUsername($master_userinfo, $slave_userinfo, $updateUsernameStatus);
									$JFusionSlave->mergeStatus($updateUsernameStatus);
									if (!$JFusionSlave->debugger->isEmpty('error')) {
										$error_info[$slave->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $JFusionSlave->debugger->get('error');
									}
									if (!$JFusionSlave->debugger->isEmpty('debug')) {
										$debug_info[$slave->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG') ] = $JFusionSlave->debugger->get('debug');
									}
								}  catch (Exception $e) {
									$status['error'][] = JText::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
								}
							} else {
								$error_info[$slave->name] = JText::_('NO_USER_DATA_FOUND');
							}
						}
						$SlaveUser = $JFusionSlave->updateUser($master_userinfo, 1);
						if (!empty($SlaveUser['error'])) {
							if (!is_array($SlaveUser['error'])) {
								$error_info[$slave->name] = array($SlaveUser['error']);
							} else {
								$error_info[$slave->name] = $SlaveUser['error'];
							}
						}
						if (!empty($SlaveUser['debug'])) {
							if (!is_array($SlaveUser['debug'])) {
								$debug_info[$slave->name] = array($SlaveUser['debug']);
							} else {
								$debug_info[$slave->name] = $SlaveUser['debug'];
							}
						}
						if (empty($SlaveUser['userinfo'])) {
							$userinfo = $JFusionSlave->getUser($master_userinfo);
						} else {
							$userinfo = $SlaveUser['userinfo'];
						}

						//update the jfusion_users_plugin table
						$JFusionSlave->updateLookup($userinfo, $JoomlaUser, 'joomla_int');
					} catch (Exception $e) {
						$error_info[$slave->name] = $debug_info[$slave->name] + array($e->getMessage());
					}
				}
			}

			//check to see if the Joomla database is still connected in case the plugin messed it up
			JFusionFunction::reconnectJoomlaDb();
		}
		if ($Itemid_backup!=0) {
			//reset the global $Itemid so that modules are not repeated
			global $Itemid;
			$Itemid = $Itemid_backup;
			//reset Itemid so that it can be obtained via getVar
			JFactory::getApplication()->input->set('Itemid', $Itemid_backup);
		}
		//return output if allowed
		$isAdministrator = JFusionFunction::isAdministrator();
		if ($isAdministrator === true) {
			$this->raise('notice', $debug_info);
			$this->raise('error', $error_info);
		}
		//stop output buffer
		ob_end_clean();
		return true;
	}

	/**
	 * @param $user
	 * @param array $options
	 * @return bool
	 */
	public function onUserLogin($user, $options = array()){

		//prevent any output by the plugins (this could prevent cookies from being passed to the header)
		$result = false;
		$mainframe = JFactory::getApplication();
		//prevent a login if AEC denied a user
		if (!defined('AEC_AUTH_ERROR_UNAME')) {
			jimport('joomla.user.helper');
			global $JFusionActive, $JFusionLoginCheckActive;

			$JFusionActive = true;

			$jfusionoptions = $options;

			$isAdministrator = JFusionFunction::isAdministrator();
			if (!empty($options['overwrite']) && $isAdministrator === true) {
				$jfusionoptions['overwrite'] = 1;
			} else {
				$jfusionoptions['overwrite'] = 0;
			}

			$jfusionoptions['skipplugin'] = array();
			if (empty($JFusionLoginCheckActive) && $mainframe->isAdmin()) {
				$slaves = Framework::getSlaves();
				if ($slaves) {
					foreach ($slaves as $slave) {
						if ($slave->name != 'joomla_int') {
							$jfusionoptions['skipplugin'][] = $slave->name;
						}
					}
				}
				$master = Framework::getMaster();
				if ($master) {
					if ($master->name != 'joomla_int') {
						$jfusionoptions['skipplugin'][] = $master->name;
					}
				}
			}

			$JFuser = new \JFusion\User\User();

			$result = $JFuser->login($user, $jfusionoptions);

			if ($result) {
				//Clean up the joomla session table
				$conf = JFactory::getConfig();
				$expire = ($conf->get('lifetime')) ? $conf->get('lifetime') * 60 : 900;

				try {
					$db =  JFactory::getDbo();
					$query = $db->getQuery(true)
						->delete('#__session')
						->where('time < ' . (int) (time() - $expire));
					$db->setQuery($query);

					$db->execute();
				} catch (Exception $e) {
				}

				if (!$mainframe->isAdmin()) {
					$params = Factory::getParams('joomla_int');
					$allow_redirect_login = $params->get('allow_redirect_login', 0);
					$redirecturl_login = $params->get('redirecturl_login', '');
					$source_url = $params->get('source_url', '');
					$jfc = Factory::getCookies();
					if ($allow_redirect_login && !empty($redirecturl_login)) {
						// only redirect if we are in the frontend and allowed and have an URL
						$jfc->executeRedirect($source_url, $redirecturl_login);
					} else {
						$jfc->executeRedirect($source_url);
					}
				}
			}
		}
		return $result;
	}

	/**
	 * @param $user
	 * @param array $options
	 * @return object
	 */
	public function onUserLogout($user, $options = array())	{
		$result = true;

		//initialise some vars
		global $JFusionActive, $JFusionActivePlugin;
		$JFusionActive = true;
		$user = JFactory::getUser($user['id']);
		$result = new stdClass();
		$result->userid = $user->get('id');
		$result->email = $user->get('email');
		$result->username = $user->get('username');

		$userinfo = new \JFusion\User\Userinfo();
		$userinfo->bind($result, 'joomla_int');

		if (empty($options['clientid'][0])) {
			$JFuser = new \JFusion\User\User();

			$result = $JFuser->logout($userinfo, $options);
		}

		//destroy the joomla session itself
		if ($JFusionActivePlugin != 'joomla_int') {
			$JoomlaUser = Factory::getUser('joomla_int');
			try {
				$JoomlaUser->destroySession($userinfo, $options);
			} catch (Exception $e) {
				Framework::raiseError($e, $JoomlaUser->getJname());
			}
		}

		$params = Factory::getParams('joomla_int');
		$allow_redirect_logout = $params->get('allow_redirect_logout', 0);
		$redirecturl_logout = $params->get('redirecturl_logout', '');
		$source_url = $params->get('source_url', '');
		ob_end_clean();
		$jfc = Factory::getCookies();
		if ($allow_redirect_logout && !empty($redirecturl_logout)) // only redirect if we are in the frontend and allowed and have an URL
		{
			$jfc->executeRedirect($source_url, $redirecturl_logout);
		} else {
			$jfc->executeRedirect($source_url);
		}

		return $result;
	}

	/**
	 * @param $user
	 * @param $success
	 * @param $msg
	 * @return bool
	 */
	public function onUserAfterDelete($user, $success, $msg) {
		$result = true;
		if (!$success) {
			$result = false;
		} else {
			$user = JFactory::getUser($user['id']);

			$userinfo = JFusionFunction::getJoomlaUser((object)$user);

			$JFuser = new \JFusion\User\User();

			$result = $JFuser->delete($userinfo);

			//delete any sessions that the user could have active
			$db = JFactory::getDBO();


			$query = $db->getQuery(true)
				->delete('#__session')
				->where('userid = ' . $db->quote($user->get('id')));

			$db->setQuery($query);
			$db->execute();
			//return output if allowed
			$isAdministrator = JFusionFunction::isAdministrator();
			if ($isAdministrator === true) {
				$debugger = Factory::getDebugger('jfusion-deleteuser');
				$this->raise('notice', $debugger->get('debug'));
				$this->raise('error', $debugger->get('error'));
			}
		}
		return $result;
	}

	/**
	 * @param $olduser
	 * @param $isnew
	 * @param $new
	 * @return bool
	 */
	public function onUserBeforeSave($olduser, $isnew, $new){
		global $JFusionActive;
		if (!$JFusionActive) {
			// Recover old data from user before to save it. The purpose is to provide it to the plugins if needed
			$session = JFactory::getSession();
			$session->set('olduser', $olduser);
		}
		$result = true;
		return $result;
	}

	/**
	 * @param $user
	 * @param $isnew
	 * @param $success
	 * @param $msg
	 * @return bool
	 */
	public function onUserAfterSave($user, $isnew, $success, $msg) {
		if (!JPluginHelper::isEnabled('user', 'joomla')) {
			$master = Framework::getMaster();
			if ($master->name == 'joomla_int') {
				$userInfo = JFactory::getUser();
				$levels = implode(',', $userInfo->getAuthorisedViewLevels());

				$db = JFactory::getDbo();
				$query = $db->getQuery(true)
					->select('folder, type, element, params')
					->from('#__extensions')
					->where('type =' . $db->quote('plugin'))
					->where('element =' . $db->quote('joomla'))
					->where('folder =' . $db->quote('user'))
					->where('access IN (' . $levels . ')');

				$plugin = $db->setQuery($query, 0, 1)->loadObject();

				$params = new JRegistry;
				$params->loadString($plugin->params);

				// Initialise variables.
				$app    = JFactory::getApplication();
				$config = JFactory::getConfig();
				$mail_to_user = $params->get('mail_to_user', 0); // change default to 0 to prevent user email spam! while running sync

				if ($isnew) {
					/**
					 * @TODO Suck in the frontend registration emails here as well. Job for a rainy day.
					 */

					if ($app->isAdmin()) {
						if ($mail_to_user) {

							// Load user_joomla plugin language (not done automatically).
							JFactory::getLanguage()->load('plg_user_joomla', JPATH_ADMINISTRATOR);

							// Compute the mail subject.
							$emailSubject = JText::sprintf(
								'PLG_USER_JOOMLA_NEW_USER_EMAIL_SUBJECT',
								$user['name'],
								$config->get('sitename')
							);

							// Compute the mail body.
							$emailBody = JText::sprintf(
								'PLG_USER_JOOMLA_NEW_USER_EMAIL_BODY',
								$user['name'],
								$config->get('sitename'),
								JUri::root(),
								$user['username'],
								$user['password_clear']
							);

							// Assemble the email data...the sexy way!
							$mail = JFactory::getMailer()
								->setSender(
									array(
										$config->get('mailfrom'),
										$config->get('fromname')
									)
								)
								->addRecipient($user['email'])
								->setSubject($emailSubject)
								->setBody($emailBody);


							if (!$mail->Send()) {
								/**
								 * @TODO Probably should raise a plugin error but this event is not error checked.
								 */
								Framework::raiseWarning(JText::_('ERROR_SENDING_EMAIL'));
							}
						}
					}
				} else {
					// Existing user - nothing to do...yet.
				}
			}
		}
		$result = $this->onAfterStoreUser($user, $isnew, $success, $msg);
		return $result;
	}

	/**
	 * Raise warning function that can handle arrays
	 *
	 * @param        $type
	 * @param array  $message   message itself
	 * @param string $jname
	 *
	 * @return string nothing
	 */
	public function raise($type, $message, $jname = '') {
		global $JFusionLoginCheckActive;
		if (!$JFusionLoginCheckActive) {
			Framework::raise($type, $message, $jname);
		}
	}
}