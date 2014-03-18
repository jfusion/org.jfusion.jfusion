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

//load the JFusion CSS and javascript
$document = JFactory::getDocument();
$document->addStyleSheet('components/com_jfusion/css/jfusion.css');
/**
 * Require the base controller
 */
include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'controller.jfusion.php';
\JFusion\Framework::initJavaScript();





new JUri();

$controller = new JFusionController();
// Perform the Request task
$task = JFactory::getApplication()->input->get('task');
if (!$task) {
    $task = 'cpanel';
}

$tasklist = $controller->getTasks();
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'toolbar.jfusion.php';
if (in_array($task, $tasklist)) {
	if (!headers_sent()) {
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
	}
    //execute the task
    $controller->execute($task);
} else {
    //run the task as a view
	JFactory::getApplication()->input->set('view', $task);
    $controller->display();
}
// Redirect if set by the controller
$controller->redirect();
