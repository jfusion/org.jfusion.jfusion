<?php
require_once __DIR__ . '/defines.php';
require_once __DIR__ . '/vendor/autoload.php';

require_once(JPATH_ADMINISTRATOR . '/components/com_jfusion/models/model.jfusion.php');

use JFusion\Factory;

use Joomla\Registry\Registry;

jimport('joomla.application.component.helper');
/**
 * TODO: This results in error if com_jfusion is not installed yet. create a manual query ?
 */
$params = JComponentHelper::getParams('com_jfusion');

$config = new Registry();

$config->set('url', JUri::root());

$config->set('plugin-path', JFUSION_PLUGIN_PATH);

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

Factory::$config = $config;

Factory::$config->set('apikey', Factory::getParams('joomla_int')->get('secret'));
Factory::$config->set('plugin-url', JFusionFunction::getJoomlaURL() . 'components/com_jfusion/plugins/');

require_once(__DIR__ . '/models/model.eventhook.php');

$listener = new JFusionEventHook();

Factory::getDispatcher()->addListener($listener);