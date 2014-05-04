<?php
/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:24
 */
use JFusion\Factory;

use Joomla\Event\Event;
use JFusion\Event\LanguageInterface;
use JFusion\Event\ApplicationInterface;
use JFusion\Event\SessionInterface;
use JFusion\Event\RouterInterface;
use JFusion\Event\InstallerInterface;
use JFusion\Event\PlatformInterface;



use Joomla\Uri\Uri;


/**
 * Class JFusionFramework
 */
class JFusionEventHook implements LanguageInterface, ApplicationInterface, SessionInterface, RouterInterface, InstallerInterface , PlatformInterface {
	/**
	 * Loads a language file for framework
	 *
	 * @param Event $event
	 *
	 * @return bool|void
	 */
	public function onLanguageLoadFramework($event)
	{
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
	public function onLanguageLoadPlugin($event)
	{
		$jname = $event->getArgument('jname', null);
		JFactory::getLanguage()->load('com_jfusion.plg_' . $jname, JFUSIONPATH_ADMINISTRATOR);
		Factory::getLanguage()->load('com_jfusion.plg_' . $jname, JFUSIONPATH_ADMINISTRATOR);
		return true;
	}

	/**
	 * Redirect to another URL.
	 *
	 * If the headers have not been sent the redirect will be accomplished using a "301 Moved Permanently"
	 * or "303 See Other" code in the header pointing to the new location. If the headers have already been
	 * sent this will be accomplished using a JavaScript statement.
	 *
	 * @param Event $event
	 *
	 * @return  void
	 */
	function onApplicationRedirect($event)
	{
		$url = $event->getArgument('url', null);
		$moved = $event->getArgument('moved', null);
		JFactory::getApplication()->redirect($url, $moved);
	}

	/**
	 * Logout authentication function.
	 *
	 * Passed the current user information to the onUserLogout event and reverts the current
	 * session record back to 'anonymous' parameters.
	 * If any of the authentication plugins did not successfully complete
	 * the logout routine then the whole method fails. Any errors raised
	 * should be done in the plugin as this provides the ability to give
	 * much more information about why the routine may have failed.
	 *
	 * @param Event $event
	 *
	 * @return  void
	 */
	function onApplicationLogout($event)
	{
		$userid = $event->getArgument('userid', null);

		$status = JFactory::getApplication()->logout($userid);

		$event->setArgument('status', ($status === true));
	}

	/**
	 * Login authentication function.
	 *
	 * Username and encoded password are passed the onUserLogin event which
	 * is responsible for the user validation. A successful validation updates
	 * the current session record with the user's details.
	 *
	 * Username and encoded password are sent as credentials (along with other
	 * possibilities) to each observer (authentication plugin) for user
	 * validation.  Successful validation will update the current session with
	 * the user details.
	 *
	 * @param Event $event
	 *
	 * @return  void
	 */
	public function onApplicationLogin($event)
	{
		$credentials = $event->getArgument('credentials', array());
		$options = $event->getArgument('options', array());

		$status = JFactory::getApplication()->login($credentials, $options);

		$event->setArgument('status', ($status === true));
	}

	/**
	 * Enqueue a system message.
	 *
	 * @param   Event $event
	 * @return  void
	 */
	public function onApplicationEnqueueMessage($event)
	{
		$msg = $event->getArgument('messsage', null);
		$type = $event->getArgument('type', 'error');

		JFactory::getApplication()->enqueueMessage($msg, $type);
	}

	/**
	 * Is admin interface?
	 *
	 * @param   Event $event
	 * @return  void
	 */
	public function onApplicationIsAdmin($event)
	{
		$event->addArgument('admin', JFactory::getApplication()->isAdmin());
	}

	/**
	 * Loads a language file for framework
	 *
	 * @param   Event $event
	 * @return  void
	 */
	function onSessionClose($event)
	{
		JFactory::getSession()->close();
	}

	/**
	 * Restart an expired or locked session.
	 *
	 * @param   Event $event
	 * @return  void
	 */
	function onSessionRestart($event)
	{
		$status = JFactory::getSession()->restart();
		$event->getArgument('status', $status);
	}

	/**
	 * Function to convert an internal URI to a route
	 *
	 * @param   Event $event
	 *
	 * @return  void
	 */
	function  onRouterBuild($event)
	{
		$url = $event->getArgument('url', null);
		$juri = JFactory::getApplication('site')->getRouter()->build($url);

		$uri = new Uri((string) $juri);
		$event->getArgument('uri', $uri);
	}

	/**
	 * get default url
	 *
	 * @param   Event $event
	 *
	 * @return  void
	 */
	public function onApplicationGetDefaultAvatar($event)
	{
		$event->addArgument('avatar', JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png');
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
			global $JFusionActivePlugin;
			$JFusionActivePlugin = $activePlugin;
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
			global $JFusionActivePlugin;
			$JFusionActivePlugin = $activePlugin;
		}

		$user = new stdClass;
		if ($username) {
			if ($activePlugin) {
				$userlookup = new \JFusion\User\Userinfo($activePlugin);
				$userlookup->username = $username;

				$PluginUser = Factory::getUser('joomla_int');
				$userlookup = $PluginUser->lookupUser($userlookup);
				if ($userlookup instanceof \JFusion\User\Userinfo) {
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
		$userid = $event->getArgument('$userid');

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
	 * get default url
	 *
	 * @param Event $event
	 *
	 * @return  Event
	 */
	public function onApplicationRoute($event)
	{
		// TODO: Implement onApplicationRoute() method.
		$url = $event->getArgument('url');
		$itemid = $event->getArgument('itemid');
		$jname = $event->getArgument('jname', '');
		$route = $event->getArgument('route', true);
		$xhtml = $event->getArgument('xhtml', true);

		$url = JFusionFunction::routeURL($url, $itemid, $jname, $route, $xhtml);

		$url = $event->setArgument('url', $url);
	}

	/**
	 * Load Script language
	 *
	 * @param Event $event
	 *
	 * @return  Event
	 */
	public function onLoadScriptLanguage($event)
	{
		JFusionFunction::loadJavascriptLanguage($event->getArgument('keys', array()));
	}
}