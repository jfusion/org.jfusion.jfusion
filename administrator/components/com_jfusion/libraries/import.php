<?php
// Set the platform root path as a constant if necessary.

if (!defined('JFUSIONPATH_PLATFORM'))
{
	define('JFUSIONPATH_PLATFORM', __DIR__);
}

// Import the library loader if necessary.
if (!class_exists('\\JFusion\\Loader'))
{
	require_once JFUSIONPATH_PLATFORM . '/jfusion/loader.php';
}

use JFusion\Loader;

// Make sure that the Jfusion Platform has been successfully loaded.
if (!class_exists('\\JFusion\\Loader'))
{
	throw new RuntimeException('Jfusion Platform not loaded.');
}

// Setup the autoloaders.
Loader::setup();

Loader::registerNamespace('JFusion', JFUSIONPATH_PLATFORM);
Loader::registerNamespace('JFusion', JFUSION_PLUGIN_AUTOLOADER_PATH);

Loader::registerNamespace('Joomla', JFUSIONPATH_PLATFORM);
Loader::registerNamespace('Psr', JFUSIONPATH_PLATFORM);
Loader::registerNamespace('Symfony', JFUSIONPATH_PLATFORM);