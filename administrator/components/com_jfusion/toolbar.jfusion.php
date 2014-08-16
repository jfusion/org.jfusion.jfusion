<?php
/**
* @package JFusion
* @subpackage Toolbar
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

defined('_JEXEC') or die('Restricted access');
/**
 * @ignore
 * @var $task string
 */
switch($task)
{
	case 'plugineditor':
        $jname = JFactory::getApplication()->input->get('jname');
		JToolBarHelper::title($jname . ' ' . JText::_('PLUGIN_EDITOR'), 'controlpanel.png');
        JToolBarHelper::custom('importexport', 'importexport-icon.png', 'importexport-icon.png', JText::_('IMPORTEXPORT'), false);
		JToolBarHelper::save('saveconfig');
		JToolBarHelper::apply('applyconfig');
		JToolBarHelper::cancel('plugindisplay');
		break;
    case 'importexport':
        $jname = JFactory::getApplication()->input->get('jname');
        JToolBarHelper::title($jname . ' ' . JText::_('IMPORTEXPORT'), 'importexport.png');
        JToolBarHelper::custom('import', 'import-icon.png', 'import-icon.png', JText::_('IMPORT'), false);
        JToolBarHelper::custom('export', 'export-icon.png', 'export-icon.png', JText::_('EXPORT'), false);
        JToolBarHelper::cancel('plugindisplay');
        break;
	case 'joomlaeditor':
		$folder = JFactory::getApplication()->input->get('folder');
		$element = JFactory::getApplication()->input->get('element');
		$db = JFactory::getDBO();

		$query = $db->getQuery(true)
			->select('name')
			->from('#__plugins')
			->where('folder = ' . $db->quote($folder))
			->where('element = ' . $db->quote($element));

		$db->setQuery($query);
		$name = $db->loadResult();
		JToolBarHelper::title($name . ' ' . JText::_('PLUGIN_EDITOR'), 'controlpanel.png');
		JToolBarHelper::save('joomlasave');
		JToolBarHelper::apply('joomlaapply');
		JToolBarHelper::cancel('joomladisplay');
		break;
	case 'wizard':
		JToolBarHelper::title(JText::_('WIZARD'), 'wizard.png');
		JToolBarHelper::custom('wizardresult', 'forward.png', 'forward.png', JText::_('NEXT'), false);
		JToolBarHelper::cancel('plugindisplay');
		break;
	case 'plugindisplay':
		JToolBarHelper::title(JText::_('PLUGIN_CONFIGURATION'), 'controlpanel.png');
		JToolBarHelper::cancel('cpanel');
		break;
	case 'syncoptions':
		JToolBarHelper::title(JText::_('USERSYNC'), 'usersync.png');
		JToolBarHelper::cancel('cpanel');
		break;
	case 'syncerror':
		JToolBarHelper::cancel('synchistory');
		break;
	case 'syncstatus':
		JToolBarHelper::title(JText::_('SYNCSTATUS'), 'syncstatus.png');
		JToolBarHelper::cancel('synchistory');
		break;
	case 'synchistory':
		JToolBarHelper::title(JText::_('SYNC_HISTORY'), 'synchistory.png');
		JToolBarHelper::custom('deletehistory', 'delete.png', 'delete.png', 'Delete Record', false);
		JToolBarHelper::cancel('cpanel');
		break;
	case 'extensions':
		JToolBarHelper::title(JText::_('EXTENSIONS'), 'extension.png');
		JToolBarHelper::cancel('cpanel');
		break;
	case 'help':
		JToolBarHelper::title(JText::_('HELP_SCREEN'), 'help_header.png');
		JToolBarHelper::cancel('cpanel');
		break;
	case 'loginchecker':
		JToolBarHelper::title(JText::_('LOGIN_CHECKER'), 'login_checker.png');
		JToolBarHelper::custom('logincheckerresult', 'forward.png', 'forward.png', JText::_('Check Login'), false);
		JToolBarHelper::cancel('plugindisplay');
		break;
	case 'logincheckerresult':
		JToolBarHelper::title(JText::_('LOGIN_CHECKER_RESULT'), 'login_checker.png');
		JToolBarHelper::cancel('loginchecker');
		break;
	case 'logoutcheckerresult':
		JToolBarHelper::title(JText::_('LOGOUT_CHECKER_RESULT'), 'login_checker.png');
		JToolBarHelper::cancel('loginchecker');
		break;
	case 'configdump':
        JToolBarHelper::title(JText::_('CONFIG_DUMP'), 'configdump.png');
        JToolBarHelper::custom('configdump', 'forward.png', 'forward.png', JText::_('UPDATE'), false);
        JToolBarHelper::cancel('cpanel');
		break;
    case 'languages':
        JToolBarHelper::title(JText::_('CONFIG_DUMP'), 'languages.png');
	    JToolBarHelper::cancel('cpanel');
		break;
    case 'versioncheck':
        JToolBarHelper::title(JText::_('VERSION_CHECK'), 'versioncheck.png');
	    JToolBarHelper::cancel('cpanel');
		break;
	case 'usergroups':
		JToolBarHelper::title(JText::_('USERGROUPS'), 'usergroups.png');
		JToolBarHelper::custom('add', 'new.png', 'new.png', JText::_('ADD'), false);
		JToolBarHelper::apply('saveusergroups');
		JToolBarHelper::cancel('cpanel');
		break;
	default:
        JToolBarHelper::title( JText::_('JFUSION_UNIVERSAL_BRIDGE'), 'jfusion.png');
        break;
}

//add our custom banner
$bar = JToolBar::getInstance('toolbar');
$bar->prependButton('Custom', '<img border="0" alt="Enabled" src="components/com_jfusion/images/jfusion_logo.png"/>');

JLoader::register('JFusionHelper', JPATH_COMPONENT_ADMINISTRATOR . '/helpers/jfusion.php');
JFusionHelper::addSubmenu($task);
