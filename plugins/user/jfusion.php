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
use JFusion\Debugger\Debugger;
use JFusion\Factory;
use JFusion\Framework;
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');
/**
 * Load the JFusion framework
 */
jimport('joomla.plugin.plugin');
require_once JPATH_ADMINISTRATOR . '/components/com_jfusion/import.php';
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

			$userinfo = JFusionFunction::getJoomlaUser((object)$user);

			if (!isset($userinfo->group_id) && !empty($userinfo->groups)) {
				$userinfo->group_id = $userinfo->groups[0];
			}
			//check to see if we need to update the master
			$master = Framework::getMaster();
			// Recover the old data of the user
			// This is then used to determine if the username was changed
			$session = JFactory::getSession();

			$olduserinfo = $session->get('olduser');
			$olduserinfo = JFusionFunction::getJoomlaUser((object)$olduserinfo);
			$session->clear('olduser');


			$JFuser = new \JFusion\User\User();

			$JFuser->save($userinfo, $olduserinfo, $isnew);

			//check to see if the Joomla database is still connected in case the plugin messed it up
			JFusionFunction::reconnectJoomlaDb();
		}
		if ($Itemid_backup != 0) {
			//reset the global $Itemid so that modules are not repeated
			global $Itemid;
			$Itemid = $Itemid_backup;
			//reset Itemid so that it can be obtained via getVar
			JFactory::getApplication()->input->set('Itemid', $Itemid_backup);
		}
		//return output if allowed
		$isAdministrator = JFusionFunction::isAdministrator();
		if ($isAdministrator === true) {
			$debugger = Debugger::getInstance('jfusion-saveuser');
			$this->raise('notice', $debugger->get('debug'));
			$this->raise('error', $debugger->get('error'));
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

			$user['password'] = JFactory::getApplication()->input->get('password', null, 'raw');

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
					$cookies = Factory::getCookies();
					if ($allow_redirect_login && !empty($redirecturl_login)) {
						// only redirect if we are in the frontend and allowed and have an URL
						$cookies->executeRedirect($source_url, $redirecturl_login);
					} else {
						$cookies->executeRedirect($source_url);
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

		$userinfo = new \JFusion\User\Userinfo('joomla_int');
		$userinfo->bind($result);

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
				Framework::raise(LogLevel::ERROR, $e, $JoomlaUser->getJname());
			}
		}

		$params = Factory::getParams('joomla_int');
		$allow_redirect_logout = $params->get('allow_redirect_logout', 0);
		$redirecturl_logout = $params->get('redirecturl_logout', '');
		$source_url = $params->get('source_url', '');
		ob_end_clean();
		$jfc = Factory::getCookies();

		$mainframe = JFactory::getApplication();
		if (!$mainframe->isAdmin()) {
			if ($allow_redirect_logout && !empty($redirecturl_logout)) // only redirect if we are in the frontend and allowed and have an URL
			{
				$jfc->executeRedirect($source_url, $redirecturl_logout);
			} else {
				$jfc->executeRedirect($source_url);
			}
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
				$debugger = Debugger::getInstance('jfusion-deleteuser');
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
				// Initialise variables.
				$app    = JFactory::getApplication();
				$config = JFactory::getConfig();
				$mail_to_user = $this->params->get('mail_to_user', 0); // change default to 0 to prevent user email spam! while running sync

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
								Framework::raise(LogLevel::WARNING, JText::_('ERROR_SENDING_EMAIL'));
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