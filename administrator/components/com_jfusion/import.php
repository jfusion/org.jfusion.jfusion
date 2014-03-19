<?php
require_once __DIR__ . '/defines.php';
require_once __DIR__ . '/libraries/import.php';

require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php');

use \JFusion\Factory;
use \JFusion\Registry\Registry;

jimport('joomla.application.component.helper');
$params = JComponentHelper::getParams('com_jfusion');

$config = new Registry();

$config->set('host', JFactory::getConfig()->get('host'));
$config->set('user', JFactory::getConfig()->get('user'));
$config->set('password', JFactory::getConfig()->get('password'));
$config->set('db', JFactory::getConfig()->get('db'));
$config->set('dbprefix', JFactory::getConfig()->get('dbprefix'));
$config->set('dbtype', JFactory::getConfig()->get('dbtype'));
$config->set('debug', JFactory::getConfig()->get('debug'));

$config->set('updateusergroups', $params->get('updateusergroups', new stdClass()));
$config->set('usergroups', $params->get('usergroups', false));

Factory::$config = $config;

Factory::$language = JFactory::getLanguage();
Factory::$document = JFactory::getDocument();
Factory::$application = JFactory::getApplication();

