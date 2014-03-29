<?php namespace JFusion\Event;


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
	 * @return  boolean if loaded or not
	 */
	function onSessionClose();

	/**
	 * Restart an expired or locked session.
	 *
	 * @return  boolean  True on success
	 */
	public function onSessionRestart();
}