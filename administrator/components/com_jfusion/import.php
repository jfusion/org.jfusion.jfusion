<?php
require_once __DIR__ . '/defines.php';
require_once __DIR__ . '/vendor/autoload.php';

require_once(JPATH_ADMINISTRATOR . '/components/com_jfusion/models/model.jfusion.php');

use JFusion\Config;
use JFusion\Factory;

use Joomla\Registry\Registry;

jimport('joomla.application.component.helper');
/**
 * TODO: This results in error if com_jfusion is not installed yet. create a manual query ?
 */
$params = JComponentHelper::getParams('com_jfusion');

$config = new Registry();

$config->set('url', JUri::root());

$config->set('plugin.path', JFUSION_PLUGIN_PATH);

/**
 * Database settings
 */
$config->set('database.host', JFactory::getConfig()->get('host'));
$config->set('database.user', JFactory::getConfig()->get('user'));
$config->set('database.password', JFactory::getConfig()->get('password'));
$config->set('database.name', JFactory::getConfig()->get('db'));
$config->set('database.prefix', JFactory::getConfig()->get('dbprefix'));
$config->set('database.driver', JFactory::getConfig()->get('dbtype'));
$config->set('database.debug', JFactory::getConfig()->get('debug'));

/**
 * Database language
 */
$config->set('debug_lang', JFactory::getConfig()->get('debug_lang'));
$config->set('language', JFactory::getConfig()->get('language'));

/**
 * Uri settings
 */
$config->set('live_site', JFactory::getConfig()->get('live_site'));


/**
 * Framework settings
 */
$config->set('updateusergroups', $params->get('updateusergroups', new stdClass()));
$config->set('usergroups', $params->get('usergroups', false));

Config::set($config);

Config::get()->set('apikey', Factory::getParams('joomla_int')->get('secret'));
Config::get()->set('plugin.url', JFusionFunction::getJoomlaURL() . 'components/com_jfusion/plugins/');

require_once(__DIR__ . '/models/model.eventhook.php');

$listener = new JFusionEventHook();

Factory::getDispatcher()->addListener($listener);