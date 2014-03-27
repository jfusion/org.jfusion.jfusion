<?php
if (!defined('_JEXEC')) {
	// load joomla libraries
	require_once JPATH_BASE . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'defines.php';
	define('_JREQUEST_NO_CLEAN', true); // we don't want to clean variables as it can	"corrupt" them for some applications, it also clear any globals used...

	if (!class_exists('JVersion')) {
		include_once(JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'cms' . DIRECTORY_SEPARATOR . 'version' . DIRECTORY_SEPARATOR . 'version.php');
	}

	include_once JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'import.php';
	require_once JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'loader.php';

	$autoloaders = spl_autoload_functions();
	if ($autoloaders && in_array('__autoload', $autoloaders)) {
		spl_autoload_register('__autoload');
	}

	require_once JPATH_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'framework.php';
	jimport('joomla.base.object');
	jimport('joomla.factory');
	jimport('joomla.filter.filterinput');
	jimport('joomla.error.error');
	jimport('joomla.event.dispatcher');
	jimport('joomla.event.plugin');
	jimport('joomla.plugin.helper');
	jimport('joomla.utilities.arrayhelper');
	jimport('joomla.environment.uri');
	jimport('joomla.environment.request');
	jimport('joomla.user.user');
	jimport('joomla.html.parameter');
	// JText cannot be loaded with jimport since it's not in a file called text.php but in methods
	JLoader::register('JText', JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'methods.php');
	JLoader::register('JRoute', JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'methods.php');

	//load JFusion's libraries
	require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
}

// add everything inside a function to prevent 'sniffing';
if (!defined('_JFUSIONAPI_INTERNAL')) {
	$secretkey = 'secret passphrase';
	if ($secretkey == 'secret passphrase') {
		exit('JFusion Api Disabled');
	}
	$Api = new JFusion\Api\Api('', $secretkey);
	$Api->parse();
}