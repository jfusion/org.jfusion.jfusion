<?php
/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:24
 */
use \JFusion\Factory;
use \JFusion\Event\Event;
use \JFusion\Event\Dispatcher;
use \JFusion\Event\Interface_Language;
use \JFusion\Event\Interface_Application;
use \JFusion\Event\Interface_Session;
use \JFusion\Event\Interface_Router;

use Jfusion\Uri\Uri;


/**
 * Class JFusionFramework
 */
class JFusionEventHook extends Event implements Interface_Language, Interface_Application, Interface_Session, Interface_Router {
	/**
	 * @param Dispatcher $subject
	 */
	function __construct($subject)
	{
		parent::__construct($subject);
	}

	/**
	 * Loads a language file for framework
	 *
	 * @return  boolean if loaded or not
	 */
	public function onLanguageLoadFramework()
	{
		JFactory::getLanguage()->load('com_jfusion', JFUSIONPATH_ADMINISTRATOR);
		JFactory::getLanguage()->load('com_jfusion', JFUSIONPATH_SITE);

		Factory::getLanguage()->load('com_jfusion', JFUSIONPATH_ADMINISTRATOR);
		Factory::getLanguage()->load('com_jfusion', JFUSIONPATH_SITE);
		return true;
	}

	/**
	 * Loads a language file for plugin
	 *
	 * @param   string  $jname Plugin name
	 *
	 * @return  boolean if loaded or not
	 */
	public function onLanguageLoadPlugin($jname)
	{
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
	 * @param   string   $url    The URL to redirect to. Can only be http/https URL
	 * @param   boolean  $moved  True if the page is 301 Permanently Moved, otherwise 303 See Other is assumed.
	 *
	 * @return  void
	 */
	function onApplicationRedirect($url, $moved = false)
	{
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
	 * @param   integer $userid The user to load - Can be an integer or string - If string, it is converted to ID automatically
	 *
	 * @return  boolean  True on success
	 */
	function onApplicationLogout($userid = null)
	{
		JFactory::getApplication()->logout($userid);
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
	 * @param   array $credentials Array('username' => string, 'password' => string)
	 * @param   array $options     Array('remember' => boolean)
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.2
	 */
	public function onApplicationLogin($credentials, $options = array())
	{
		JFactory::getApplication()->login($credentials, $options);
	}

	/**
	 * Enqueue a system message.
	 *
	 * @param   string $msg  The message to enqueue.
	 * @param   string $type The message type. Default is message.
	 *
	 * @return  void
	 *
	 * @since   3.2
	 */
	public function onApplicationEnqueueMessage($msg, $type = 'message')
	{
		JFactory::getApplication()->enqueueMessage($msg, $type);
	}

	/**
	 * Is admin interface?
	 *
	 * @return  boolean  True if this application is administrator.
	 *
	 * @since   3.2
	 */
	public function onApplicationIsAdmin()
	{
		return JFactory::getApplication()->isAdmin();
	}

	/**
	 * Loads a language file for framework
	 *
	 * @return  boolean if loaded or not
	 */
	function onSessionClose()
	{
		JFactory::getSession()->close();
	}

	/**
	 * Restart an expired or locked session.
	 *
	 * @return  boolean  True on success
	 */
	public function onSessionRestart()
	{
		return JFactory::getSession()->restart();
	}

	/**
	 * Function to convert an internal URI to a route
	 *
	 * @param   string $url The internal URL
	 *
	 * @return  Uri  The absolute search engine friendly URL
	 */
	function  onRouterBuild($url)
	{
		$juri = JFactory::getApplication('site')->getRouter()->build($url);

		$uri = new Uri((string) $juri);
		return $uri;
	}
}