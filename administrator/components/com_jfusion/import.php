<?php
require_once __DIR__ . '/defines.php';
require_once __DIR__ . '/libraries/import.php';

require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php');



\JFusion\Factory::$config = JFactory::getConfig();
\JFusion\Factory::$language = JFactory::getLanguage();
\JFusion\Factory::$database = JFactory::getDbo();
\JFusion\Factory::$document = JFactory::getDocument();
\JFusion\Factory::$application = JFactory::getApplication();

