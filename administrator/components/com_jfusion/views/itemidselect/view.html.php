<?php

/**
 * This is view file for itemidselect
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Itemidselect
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Renders the a screen that allows the user to choose a JFusion integration method
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Itemidselect
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewitemidselect extends JView
{
    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed html output of view
     */
    function display($tpl = null)
    {
        $mainframe = JFactory::getApplication();

        $lang = JFactory::getLanguage();
        $lang->load('com_jfusion');

        // Initialize variables
        JHTML::_('behavior.modal');
        $document = JFactory::getDocument();
        $document->setTitle('Plugin Selection');
        $template = $mainframe->getTemplate();
        $document->addStyleSheet("templates/$template/css/general.css");
        $document->addStyleSheet('components/com_jfusion/css/jfusion.css');
        $css = 'table.adminlist, table.admintable{ font-size:11px; }';
        $document->addStyleDeclaration($css);
        $ename = JRequest::getVar('ename');
        //get the number to attach to the id of the input to update after selecting a menu item
        $elId = JRequest::getVar('elId');
        $feature = JRequest::getVar('feature');
        JHTML::_('behavior.tooltip');
        
        //get a list of jfusion menuitems
        $db = JFactory::getDBO();
        if(JFusionFunction::isJoomlaVersion('1.6')) {
            $query = 'SELECT id, menutype, title as name, alias, params FROM #__menu WHERE link = \'index.php?option=com_jfusion\' AND client_id = 0';
        } else {
            $query = 'SELECT id, menutype, name, alias, params FROM #__menu WHERE link = \'index.php?option=com_jfusion\'';        	
        }
        $db->setQuery($query);
        $menuitems = $db->loadObjectList();

        foreach ($menuitems as $key => &$row) {
            $row->params = new JParameter($row->params);

            $jPluginParam = unserialize(base64_decode($row->params->get('JFusionPluginParam')));
            if (is_array($jPluginParam)) {
                $row->jfusionplugin = $jPluginParam['jfusionplugin'];
            }
            if (!JFusionFunction::validPlugin($row->jfusionplugin) || !JFusionFunctionAdmin::hasFeature($row->name,$feature)) {
                unset($menuitems[$key]);
            }
        }
                
        //get a list of direct links for jfusion plugins
        $db = JFactory::getDBO();
        $query = 'SELECT * from #__jfusion WHERE status = 1';
        $db->setQuery($query);
        $directlinks = $db->loadObjectList();

        foreach ($directlinks as $key => &$row) {
            if (JFusionFunctionAdmin::hasFeature($row->name,$feature)) {
                $row->params = JFusionFactory::getParams($row->name);
            } else {
                unset($directlinks[$key]);
            }
        }
        
        $this->assignRef('menuitems', $menuitems);
        $this->assignRef('directlinks', $directlinks);
        
        $this->assignRef('ename', $ename);
        $this->assignRef('elId', $elId);        
        
        parent::display($tpl);
    }
}
