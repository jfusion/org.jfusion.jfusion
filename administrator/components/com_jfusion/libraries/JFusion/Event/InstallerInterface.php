<?php namespace JFusion\Event;

use Joomla\Event\Event;

/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:14
 */
interface InstallerInterface
{
	/**
	 * @param Event $event
	 *
	 * @return  Event
	 */
	function onInstallerPluginUninstall($event);
}