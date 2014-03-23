<?php namespace JFusion\Event;

use Joomla\Event\Event;

/**
 * Interface Interface_Session
 *
 * @package JFusion\Event
 */
interface SessionInterface
{
	/**
	 * Close the session
	 *
	 * @param   Event   $event
	 *
	 * @return  void
	 */
	function onSessionClose($event);

	/**
	 * Restart an expired or locked session.
	 *
	 * @param   Event   $event
	 *
	 * @return  void
	 */
	public function onSessionRestart($event);
}