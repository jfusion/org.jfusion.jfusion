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

JFusionLoader::registerNamespace('JFusion', JFUSIONPATH_PLATFORM);