<?php namespace JFusion\Event;

use Jfusion\Uri\Uri;

/**
 * Interface Interface_Router
 *
 * @package JFusion\Event
 */
interface Interface_Router
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