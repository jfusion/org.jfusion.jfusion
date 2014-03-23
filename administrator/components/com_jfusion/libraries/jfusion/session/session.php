<?php namespace JFusion\Session;
/**
 * @package     Joomla.Platform
 * @subpackage  Session
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

use JFusion\Factory;
use Joomla\Event\Event;

defined('JPATH_PLATFORM') or die;

/**
 * Class for managing HTTP sessions
 *
 * Provides access to session-state values as well as session-level
 * settings and lifetime management methods.
 * Based on the standard PHP session handling mechanism it provides
 * more advanced features such as expire timeouts.
 *
 * @package     Joomla.Platform
 * @subpackage  Session
 * @since       11.1
 */
class Session
{
	/**
	 * Session instances container.
	 *
	 * @var    Session
	 * @since  11.3
	 */
	protected static $instance;

	/**
	 * Returns the global Session object, only creating it
	 * if it doesn't already exist.
	 *
	 * @return  Session  The Session object.
	 *
	 * @since   11.1
	 */
	public static function getInstance()
	{
		if (!self::$instance)
		{
			self::$instance = new Session();
		}
		return self::$instance;
	}

	/**
	 * Writes session data and ends session
	 *
	 * Session data is usually stored after your script terminated without the need
	 * to call JSession::close(), but as session data is locked to prevent concurrent
	 * writes only one script may operate on a session at any time. When using
	 * framesets together with sessions you will experience the frames loading one
	 * by one due to this locking. You can reduce the time needed to load all the
	 * frames by ending the session as soon as all changes to session variables are
	 * done.
	 *
	 * @return  void
	 */
	public function close()
	{
		$event = new Event('onSessionClose');
		Factory::getDispatcher()->triggerEvent($event);
	}

	/**
	 * Restart an expired or locked session.
	 *
	 * @return  boolean  True on success
	 */
	public function restart()
	{
		$event = new Event('onSessionRestart');
		Factory::getDispatcher()->triggerEvent($event);
		return $event->getArgument('status', false);
	}
}
