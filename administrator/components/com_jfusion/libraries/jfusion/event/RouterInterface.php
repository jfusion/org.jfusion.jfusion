<?php namespace JFusion\Event;

use Joomla\Uri\Uri;

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
	 * @param   string  $url  The internal URL
	 *
	 * @return  Uri  The absolute search engine friendly URL
	 */
	function  onRouterBuild($url);
}