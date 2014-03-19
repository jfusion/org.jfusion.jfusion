<?php namespace JFusion\Router;
/**
 * @package     Joomla.Libraries
 * @subpackage  Router
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

use JFusion\Event\Dispatcher;
use JFusion\Uri\Uri;

defined('JPATH_PLATFORM') or die;

/**
 * Class to create and parse routes
 *
 * @package     Joomla.Libraries
 * @subpackage  Router
 * @since       1.5
 */
class Router
{
	/**
	 * JRouter instances container.
	 *
	 * @var    Router
	 * @since  1.7
	 */
	protected static $instance = null;

	/**
	 * Class constructor
	 *
	 * @since   1.5
	 */
	public function __construct()
	{
	}

	/**
	 * Returns the global Router object, only creating it if it
	 * doesn't already exist.
	 *
	 * @return  Router  A Router object.
	 *
	 * @since   1.5
	 */
	public static function getInstance()
	{
		if (self::$instance)
		{
			self::$instance = new Router();
		}
		return self::$instance;
	}

	/**
	 * Function to convert an internal URI to a route
	 *
	 * @param   string  $url  The internal URL
	 *
	 * @return  Uri  The absolute search engine friendly URL
	 *
	 * @since   1.5
	 */
	public function build($url)
	{
		$responces = Dispatcher::getInstance()->trigger('onRouterBuild');
		if ($responces) {
			foreach ($responces as $responce) {
				if ($responce instanceof Uri) {
					return $responce;
				}
			}
		}
		return new Uri();
	}
}
