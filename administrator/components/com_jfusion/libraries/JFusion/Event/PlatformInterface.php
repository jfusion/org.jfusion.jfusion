<?php namespace JFusion\Event;

use Joomla\Event\Event;

/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:14
 */
interface PlatformInterface
{
	/**
	 * used for platform login
	 *
	 * @param   Event   $event
	 *
	 * @return  Event
	 */
	function onPlatformLogin($event);

	/**
	 * used for platform logout
	 *
	 * @param   Event   $event
	 *
	 * @return  Event
	 */
	function onPlatformLogout($event);

	/**
	 * used for platform delete user
	 *
	 * @param   Event   $event
	 *
	 * @return  Event
	 */
	function onPlatformUserDelete($event);
}