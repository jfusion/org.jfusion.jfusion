<?php
// Set the platform root path as a constant if necessary.
if (!defined('JFUSIONPATH_PLATFORM'))
{
	define('JFUSIONPATH_PLATFORM', __DIR__);
}

// Import the library loader if necessary.
if (!class_exists('JFusionLoader'))
{
	require_once JFUSIONPATH_PLATFORM . '/loader.php';
}

// Make sure that the Jfusion Platform has been successfully loaded.
if (!class_exists('JFusionLoader'))
{
	throw new RuntimeException('Jfusion Platform not loaded.');
}

// Setup the autoloaders.
JFusionLoader::setup();

JFusionLoader::register('JFusionPlugin', JFUSIONPATH_PLATFORM . '/jfusion/plugin/plugin.php');
JFusionLoader::register('JFusionAdmin', JFUSIONPATH_PLATFORM . '/jfusion/plugin/plugin/admin.php');
JFusionLoader::register('JFusionAuth', JFUSIONPATH_PLATFORM . '/jfusion/plugin/plugin/auth.php');
JFusionLoader::register('JFusionForum', JFUSIONPATH_PLATFORM . '/jfusion/plugin/plugin/forum.php');
JFusionLoader::register('JFusionUser', JFUSIONPATH_PLATFORM . '/jfusion/plugin/plugin/user.php');

// Import the base Jfusion Platform libraries.
JFusionLoader::import('jfusion.factory');