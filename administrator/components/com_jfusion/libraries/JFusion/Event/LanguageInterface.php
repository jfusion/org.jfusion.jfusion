<?php namespace JFusion\Event;

use Joomla\Event\Event;

/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:14
 */
interface LanguageInterface
{
	/**
	 * Loads a language file for framework
	 *
	 * @param Event $event
	 *
	 * @return  Event
	 */
	function onLanguageLoadFramework($event);

	/**
	 * Loads a language file for plugin
	 *
	 * @param Event $event
	 *
	 * @return  Event
	 */
	function onLanguageLoadPlugin($event);
}