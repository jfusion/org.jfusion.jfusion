<?php namespace JFusion\Event;

use Joomla\Event\Event;

/**
 * Interface Interface_Router
 *
 * @package JFusion\Event
 */
interface RouterInterface
{
	/**
	 * Function to convert an internal URI to a route
	 *
	 * @param   Event   $event
	 *
	 * @return  Event
	 */
	function  onRouterBuild($event);
}