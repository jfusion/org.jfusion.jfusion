<?php
/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:24
 */
use JFusion\Factory;

use JFusion\Framework;
use JFusion\User\Userinfo;
use JFusion\Api\PlatformInterface;
use JFusion\Application\ApplicationInterface;
use JFusion\Installer\InstallerPluginInterface;
use JFusion\Plugin\PluginInterface;
use JFusion\FrameworkInterface;

use Joomla\Event\Event;

use Psr\Log\LogLevel;

/**
 * Class JFusionFramework
 */
class JFusionEventHook implements ApplicationInterface, PluginInterface, InstallerPluginInterface, PlatformInterface, FrameworkInterface {
	/**
	 * Loads a language file for framework
	 *
	 * @param Event $event
	 *
	 * @return bool|void
	 */
	public function onFrameworkLoadLanguage($event)
	{
		/**
		 * TODO: when language location for framework files is changed this need to be updated
		 */
		JFactory::getLanguage()->load('com_jfusion', JFUSIONPATH_ADMINISTRATOR);
		JFactory::getLanguage()->load('com_jfusion', JFUSIONPATH_SITE);

		Factory::getLanguage()->load('com_jfusion', JFUSIONPATH_ADMINISTRATOR);
		Factory::getLanguage()->load('com_jfusion', JFUSIONPATH_SITE);
	}

	/**
	 * Loads a language file for plugin
	 *
	 * @param Event $event
	 *
	 * @return  boolean if loaded or not
	 */
	public function onPluginLoadLanguage($event)
	{
		/**
		 * TODO: when language location for plugin files is changed this need to be updated
		 */
		$jname = $event->getArgument('jname', null);
		if ($jname) {
			JFactory::getLanguage()->load('com_jfusion.plg_' . $jname, JFUSIONPATH_ADMINISTRATOR);
			Factory::getLanguage()->load('com_jfusion.plg_' . $jname, JFUSIONPATH_ADMINISTRATOR);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Enqueue a system message.
	 *
	 * @param   Event $event
	 * @return  void
	 */
	public function onApplicationEnqueueMessage($event)
	{
		JFactory::getApplication()->enqueueMessage($event->getArgument('message', null), $event->getArgument('type', 'message'));
	}

	/**
	 * @param   Event $event
	 *
	 * @return  void
	 */
	function onInstallerPluginUninstall($event)
	{
		$jname = $event->getArgument('jname', null);

		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->delete('#__jfusion_discussion_bot')
			->where('jname = ' . $db->quote($jname));
		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * used for platform login
	 *
	 * @param   Event $event
	 *
	 * @return  void
	 */
	function onPlatformLogin($event)
	{
		$username = $event->getArgument('username');
		$password = $event->getArgument('password');
		$remember = $event->getArgument('remember');

		$activePlugin = $event->getArgument('activePlugin');

		if ($activePlugin) {
			\JFusion\Factory::getStatus()->set('active.plugin', $activePlugin);
		}

		$mainframe = JFactory::getApplication();

		// do the login
		$credentials = array('username' => $username, 'password' => $password);
		$options = array('entry_url' => JUri::root() . 'index.php?option=com_user&task=login', 'silent' => true);

		$options['remember'] = $remember;

		$mainframe->login($credentials, $options);

		//clean up the joomla session object before continuing
		$session = JFactory::getSession();
		$id = $session->getId();
		$session_data = session_encode();
		$session->close();

		//if we are not frameless, then we need to manually update the session data as on some servers, this data is getting corrupted
		//by php session_write_close and thus the user is not logged into Joomla.  php bug?
		if (!defined('IN_JOOMLA') && $id) {
			$jdb = JFactory::getDbo();

			$query = $jdb->getQuery(true);

			$query->select('*')
				->from('#__session')
				->where('session_id = ' . $jdb->quote($id));


			$jdb->setQuery($query, 0 , 1);

			$data = $jdb->loadObject();
			if ($data) {
				$data->time = time();
				$jdb->updateObject('#__session', $data, 'session_id');
			} else {
				// if load failed then we assume that it is because
				// the session doesn't exist in the database
				// therefore we use insert instead of store
				$app = JFactory::getApplication();

				$data = new stdClass();
				$data->session_id = $id;
				$data->data = $session_data;
				$data->client_id = $app->getClientId();
				$data->username = '';
				$data->guest = 1;
				$data->time = time();

				$jdb->insertObject('#__session', $data, 'session_id');
			}
		}
	}

	/**
	 * used for platform logout
	 *
	 * @param   Event $event
	 *
	 * @return  void
	 */
	function onPlatformLogout($event)
	{
		$username = $event->getArgument('username');
		$activePlugin = $event->getArgument('activePlugin');

		$mainframe = JFactory::getApplication();

		if ($activePlugin) {
			\JFusion\Factory::getStatus()->set('active.plugin', $activePlugin);
		}

		$user = new stdClass;
		if ($username) {
			if ($activePlugin) {
				$userlookup = new Userinfo($activePlugin);
				$userlookup->username = $username;

				$PluginUser = Factory::getUser('joomla_int');
				$userlookup = $PluginUser->lookupUser($userlookup);
				if ($userlookup instanceof Userinfo) {
					$user = JFactory::getUser($userlookup->userid);
				}
			} else {
				$user = JFactory::getUser($username);
			}
		}
		if (isset($user->userid) && $user->userid) {
			$mainframe->logout($user->userid);
		} else {
			$mainframe->logout();
		}

		// clean up session
		$session = JFactory::getSession();
		$session->close();
	}

	/**
	 * used for platform delete user
	 *
	 * @param   Event $event
	 *
	 * @return  void
	 */
	function onPlatformUserDelete($event)
	{
		$userid = $event->getArgument('userid');

		$user = JUser::getInstance($userid);

		if ($user) {
			if ($user->delete()) {
				$event->setArgument('debug', 'user deleted: ' . $userid);
			} else {
				$event->setArgument('error', 'Delete user failed: ' . $userid);
			}
		} else {
			$event->setArgument('error', 'invalid user');
		}
	}

	/**
	 * used for platform user register
	 *
	 * @param   Event $event
	 *
	 * @return  Event
	 */
	function onPlatformUserRegister($event)
	{
		$userinfo = $event->getArgument('userinfo', null);

		$error = $debug = array();
		if ($userinfo instanceof Userinfo) {
			$plugins = Framework::getSlaves();
			$plugins[] = Framework::getMaster();

			foreach ($plugins as $key => $plugin) {
				if ($userinfo->getJname() == $plugin->name) {
					unset($plugins[$key]);
				}
			}

			foreach ($plugins as $plugin) {
				try {
					$PluginUserUpdate = Factory::getUser($plugin->name);
					$existinguser = $PluginUserUpdate->getUser($userinfo);

					if(!$existinguser) {
						$PluginUserUpdate->resetDebugger();
						$PluginUserUpdate->doCreateUser($userinfo);
						$status = $PluginUserUpdate->debugger->get();

						foreach ($status[LogLevel::ERROR] as $e) {
							$error[][$plugin->name] = $e;
						}
						foreach ($status[LogLevel::DEBUG] as $d) {
							$debug[][$plugin->name] = $d;
						}
					} else {
						$error[][$plugin->name] = 'user already exsists';
					}
				} catch (Exception $e) {
					$error[][$plugin->name] = $e->getMessage();
				}
			}
		}
		$event->addArgument('debug', $debug);
		$event->addArgument('error', $error);
	}

	/**
	 * used for platform user update
	 *
	 * @param   Event $event
	 *
	 * @return  Event
	 */
	function onPlatformUserUpdate($event)
	{
		$userinfo = $event->getArgument('userinfo', null);
		$overwrite = $event->getArgument('overwrite', null);

		$error = $debug = array();
		if ($userinfo instanceof Userinfo) {
			$plugins = Framework::getSlaves();
			$plugins[] = Framework::getMaster();

			foreach ($plugins as $key => $plugin) {
				if ($userinfo->getJname() == $plugin->name) {
					unset($plugins[$key]);
				}
			}
			foreach ($plugins as $plugin) {
				try {
					$PluginUserUpdate = Factory::getUser($plugin->name);
					$updateinfo = $userinfo[$plugin->name];

					if ($updateinfo instanceof stdClass) {
						$userlookup = new Userinfo($plugin->name);
						$userlookup->username = $updateinfo->username;

						$userlookup = $PluginUserUpdate->lookupUser($userlookup);

						if($userlookup) {
							$existinguser = $PluginUserUpdate->getUser($updateinfo->username);

							foreach ($updateinfo as $key => $value) {
								if ($key != 'userid' && isset($existinguser->$key)) {
									if ($existinguser->$key != $updateinfo->$key) {
										$existinguser->$key = $updateinfo->$key;
									}
								}
							}
							$PluginUserUpdate->resetDebugger();

							$PluginUserUpdate->updateUser($existinguser, $overwrite);

							$debug[][$plugin->name] = $PluginUserUpdate->debugger->get();
						} else {
							$debug[][$plugin->name] = 'invalid user';
						}
					} else {
						$error[][$plugin->name] = 'invalid update user';
					}
				} catch (Exception $e) {
					$error[][$plugin->name] = $e->getMessage();
				}
			}
		}
		$event->addArgument('debug', $debug);
		$event->addArgument('error', $error);
	}

	/**
	 * used for platform route url
	 *
	 * @param   Event $event
	 *
	 * @return  Event
	 */
	function onPlatformRoute($event)
	{
		// TODO: Implement onPlatformRoute() method.
		$url = $event->getArgument('url', null);

		if ($url) {
			$joomla_url = JFusionFunction::getJoomlaURL();
			$juri = new JUri($joomla_url);
			$path = $juri->getPath();
			if ($path != '/') {
				$url = str_replace($path, '', $url);
			}
			$url = JRoute::_($joomla_url . $url);

			$event->setArgument('url', $url);
		}
	}

	/**
	 * Load Script language
	 *
	 * @param Event $event
	 *
	 * @return  Event
	 */
	public function onApplicationLoadScriptLanguage($event)
	{
		JFusionFunction::loadJavascriptLanguage($event->getArgument('keys', array()));
	}

	/**
	 * Load Script language
	 *
	 * @param Event $event
	 *
	 * @return  Event
	 */
	public function onApplicationGetUser($event)
	{
		$user = JFusionFunction::getJoomlaUser((object)JFactory::getUser());
		$event->setArgument('user', $user);
	}
}
