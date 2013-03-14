<?php

/**
 * First file that gets called for accessing jfusion in the administrator panel
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   ControllerAdmin
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
//check to see if PHP5 is used
if (version_compare(PHP_VERSION, '5.0.0', '<')) {
    //die if php4 is used
    die(JText::_('PHP_VERSION_OUTDATED') . PHP_VERSION . '<br/><br/>' . JText::_('PHP_VERSION_UPGRADE'));
}

//Load the language files of the plugins
$db = JFactory::getDBO();
$query = 'SELECT name , original_name from #__jfusion';
$db->setQuery($query);
$plugins = $db->loadObjectList();
$lang = JFactory::getLanguage();
foreach ($plugins as $plugin) {
    $name = $plugin->original_name ? $plugin->original_name : $plugin->name;
    // Language file is loaded in function of the context
    // of the selected language in Joomla
    // and of the JPATH_BASE (in admin = JPATH_ADMINISTRATOR, in site = JPATH_SITE)
    $lang->load('com_jfusion.plg_' . $name, JPATH_COMPONENT_ADMINISTRATOR);
}

//load the JFusion CSS and javascript
$document = JFactory::getDocument();
$document->addStyleSheet('components/com_jfusion/css/jfusion.css');
/**
 * Require the base controller
 */
require_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'controllers' . DS . 'controller.jfusion.php';

if (JFusionFunction::isJoomlaVersion('1.7')) {
    JHtml::_('behavior.framework');
}
$document->addScript('components/com_jfusion/js/jfusion.js');
// Require specific controller if requested
$controller = JRequest::getWord('controller');
if ($controller) {
    $path = JPATH_COMPONENT_ADMINISTRATOR . DS . 'controllers' . DS . $controller . '.php';
    if (file_exists($path)) {
        include_once $path;
    } else {
        $controller = '';
    }
}
// Create the controller
$classname = 'JFusionController' . $controller;
/**
 * @ignore
 * @var $controller JController
 */
$controller = new $classname();
// Perform the Request task
$task = JRequest::getVar('task');
if (!$task) {
    $task = 'cpanel';
}
$tasklist = $controller->getTasks();
if (in_array($task, $tasklist)) {
	if (!headers_sent()) {
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
	}
    //excute the task
    $controller->execute($task);
} else {
    //run the task as a view
    JRequest::setVar('view', $task);
    $controller->display();
}
// Redirect if set by the controller
$controller->redirect();
