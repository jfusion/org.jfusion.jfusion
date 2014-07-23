<?php namespace JFusion\Event;

use Joomla\Event\Event;

/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:14
 */
interface ApplicationInterface
{
	/**
	 * Redirect to another URL.
	 *
	 * If the headers have not been sent the redirect will be accomplished using a "301 Moved Permanently"
	 * or "303 See Other" code in the header pointing to the new location. If the headers have already been
	 * sent this will be accomplished using a JavaScript statement.
	 *
	 * @param Event $event
	 *
	 * @return  Event
	 */
	function onApplicationRedirect($event);

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
	 * @return  Event
	 */
	function onApplicationLogout($event);

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
	 * @return  Event
	 */
	public function onApplicationLogin($event);

	/**
	 * Enqueue a system message.
	 *
	 * @param Event $event
	 *
	 * @return  Event
	 */
	public function onApplicationEnqueueMessage($event);

	/**
	 * Is admin interface?
	 *
	 * @param Event $event
	 * @return  Event
	 */
	public function onApplicationIsAdmin($event);

	/**
	 * get default url
	 *
	 * @param Event $event
	 * @return  Event
	 */
	public function onApplicationGetDefaultAvatar($event);

	/**
	 * get default url
	 *
	 * @param Event $event
	 * @return  Event
	 */
	public function onApplicationRoute($event);

	/**
	 * Load Script language
	 *
	 * @param Event $event
	 * @return  Event
	 */
	public function onApplicationLoadScriptLanguage($event);

	/**
	 * Load Script language
	 *
	 * @param Event $event
	 * @return  Event
	 */
	public function onApplicationGetUser($event);
}