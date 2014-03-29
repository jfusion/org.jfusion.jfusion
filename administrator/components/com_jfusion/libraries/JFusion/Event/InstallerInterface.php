<?php namespace JFusion\Event;

/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:14
 */
interface InstallerInterface
{
	/**
	 * @param string $instance the name of the plugin that are getting uninstalled.
	 *
	 * @return mixed
	 */
	function onInstallerPluginUninstall($instance);
}