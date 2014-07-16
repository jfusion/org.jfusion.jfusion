<?php
require_once __DIR__ . '/defines.php';
require_once __DIR__ . '/libraries/import.php';

require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php');

use JFusion\Factory;
use Joomla\Registry\Registry;

jimport('joomla.application.component.helper');
/**
 * TODO: This results in error if com_jfusion is not installed yet. create a manual query ?
 */
$params = JComponentHelper::getParams('com_jfusion');

$config = new Registry();

$config->set('url', JUri::root());

/**
 * Database settings
 */
$config->set('host', JFactory::getConfig()->get('host'));
$config->set('user', JFactory::getConfig()->get('user'));
$config->set('password', JFactory::getConfig()->get('password'));
$config->set('db', JFactory::getConfig()->get('db'));
$config->set('dbprefix', JFactory::getConfig()->get('dbprefix'));
$config->set('dbtype', JFactory::getConfig()->get('dbtype'));
$config->set('debug', JFactory::getConfig()->get('debug'));

/**
 * Database language
 */
$config->set('language', JFactory::getConfig()->get('language'));
$config->set('debug_lang', JFactory::getConfig()->get('debug_lang'));

/**
 * Uri settings
 */
$config->set('live_site', JFactory::getConfig()->get('live_site'));


/**
 * Framework settings
 */
$config->set('updateusergroups', $params->get('updateusergroups', new stdClass()));
$config->set('usergroups', $params->get('usergroups', false));

Factory::$config = $config;

require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.eventhook.php');

$listener = new JFusionEventHook();

Factory::getDispatcher()->addListener($listener);