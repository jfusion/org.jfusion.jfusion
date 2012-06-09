<?php
/**
* @package JFusion
* @subpackage Toolbar
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

defined( '_JEXEC' ) or die( 'Restricted access' );
/**
 * @ignore
 * @var $task string
 */
switch($task)
{
	case 'plugineditor':
        $jname = JRequest::getVar('jname');
		JToolBarHelper::title( $jname . ' ' . JText::_('PLUGIN_EDITOR'), 'config.png' );
		JToolBarHelper::save('saveconfig');
		JToolBarHelper::apply('applyconfig');
		JToolBarHelper::cancel('plugindisplay');
		break;
	case 'joomlaeditor':
		$folder = JRequest::getVar('folder');
		$element = JRequest::getVar('element');
		$db = & JFactory::getDBO();
		$query = 'SELECT name from #__plugins WHERE folder = '.$db->Quote($folder) . ' AND element ='.$db->Quote($element);
		$db->setQuery($query);
		$name = $db->loadResult();
		JToolBarHelper::title( $name. ' ' . JText::_('PLUGIN_EDITOR'), 'config.png' );
		JToolBarHelper::save('joomlasave');
		JToolBarHelper::apply('joomlaapply');
		JToolBarHelper::cancel('joomladisplay');
		break;
	case 'wizard':
		JToolBarHelper::title( JText::_('WIZARD'), 'component.png' );
		JToolBarHelper::custom( 'wizardresult', 'forward.png', 'forward.png', JText::_('NEXT'), false, false);
		JToolBarHelper::cancel('plugindisplay');
		break;
	case 'plugindisplay':
		JToolBarHelper::title( JText::_('PLUGIN_CONFIGURATION'), 'cpanel.png' );
		break;
	case 'syncoptions':
		JToolBarHelper::title( JText::_('USERSYNC'), 'user.png' );
		JToolBarHelper::cancel('cpanel');
		break;
	case 'synchistory':
		JToolBarHelper::title( JText::_('SYNC_HISTORY'), 'content.png' );
		JToolBarHelper::custom('deletehistory', 'delete.png','delete.png', 'Delete Record', false, false );
		break;
	case 'extensions':
		JToolBarHelper::title( JText::_('EXTENSIONS'), 'extension.png' );
		JToolBarHelper::cancel('cpanel');
		break;
	case 'help':
		JToolBarHelper::title( JText::_('HELP_SCREEN'), 'help_header.png' );
		JToolBarHelper::cancel('cpanel');
		break;
	case 'loginchecker':
		JToolBarHelper::title( JText::_('LOGIN_CHECKER'), 'info.png' );
		JToolBarHelper::custom( 'logincheckerresult', 'forward.png', 'forward.png', JText::_('Check Login'), false, false);
		JToolBarHelper::cancel('plugindisplay');
		break;
	case 'logincheckerresult':
		JToolBarHelper::title( JText::_('LOGIN_CHECKER_RESULT'), 'info.png' );
		JToolBarHelper::cancel('loginchecker');
		break;
	case 'logoutcheckerresult':
		JToolBarHelper::title( JText::_('LOGOUT_CHECKER_RESULT'), 'info.png' );
		JToolBarHelper::cancel('loginchecker');
		break;
	case 'configdump':
        JToolBarHelper::title( JText::_('CP_CONFIG_DUMP'), 'print.png' );
        JToolBarHelper::custom( 'configdump', 'forward.png', 'forward.png', JText::_('UPDATE'), false, false);
        JToolBarHelper::cancel('cpanel');
		break;
	default:
		JToolBarHelper::title( 'JFusion - The Universal Bridge', 'jfusion_logo.png' );
        break;
}

//add our custom banner
$bar =& JToolBar::getInstance('toolbar');
$bar->prependButton('Custom', '<img border="0" alt="Enabled" src="components/com_jfusion/images/jfusion_logo.png"/>');

//include submenu for J1.6+
if (JFusionFunction::isJoomlaVersion('1.6')) {
	$task = JRequest::getVar('task');
	JLoader::register('JFusionHelper', JPATH_COMPONENT_ADMINISTRATOR . DS . 'helpers' . DS . 'jfusion.php');
	JFusionHelper::addSubmenu($task);
}