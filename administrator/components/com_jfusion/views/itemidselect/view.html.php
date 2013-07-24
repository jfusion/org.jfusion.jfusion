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
class jfusionViewitemidselect extends JViewLegacy
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
        $document->addStyleSheet('templates/'.$template.'/css/general.css');
        $document->addStyleSheet('components/com_jfusion/css/jfusion.css');
        $css = '.jfusion table.jfusionlist, .jfusion table.jfusiontable{ font-size:11px; }';
        $document->addStyleDeclaration($css);
        $ename = JFactory::getApplication()->input->getString('ename');
        //get the number to attach to the id of the input to update after selecting a menu item
        $feature = JFactory::getApplication()->input->get('feature', 'any');
        JHTML::_('behavior.tooltip');
        
        //get a list of jfusion menuitems
        $app		= JFactory::getApplication();
        $menus		= $app->getMenu('site');
        $component	= JComponentHelper::getComponent('com_jfusion');

	    $menuitems		= $menus->getItems('component_id', $component->id);

        foreach ($menuitems as $key => $row) {
            if ($row->link != 'index.php?option=com_jfusion&view=plugin') {
                unset($menuitems[$key]);
            } else {
	            $row->name = $row->title;
            }
        }

        foreach ($menuitems as $key => $row) {
	        $row->jfusionplugin = null;
	        $row->params = $menus->getParams($row->id);
	        $jPluginParam = unserialize(base64_decode($row->params->get('JFusionPluginParam')));
	        if (is_array($jPluginParam)) {
		        $row->jfusionplugin = $jPluginParam['jfusionplugin'];
	        }
            if (!JFusionFunction::validPlugin($row->jfusionplugin) || !JFusionFunction::hasFeature($row->jfusionplugin,$feature,$row->id)) {
                unset($menuitems[$key]);
            }
        }
                
        //get a list of direct links for jfusion plugins
        $db = JFactory::getDBO();
        $query = 'SELECT * from #__jfusion WHERE status = 1';
        $db->setQuery($query);
        $directlinks = $db->loadObjectList();

        foreach ($directlinks as $key => &$row) {
            if (JFusionFunction::hasFeature($row->name,$feature)) {
                $row->params = JFusionFactory::getParams($row->name);
            } else {
                unset($directlinks[$key]);
            }
        }

	    $this->menuitems = $menuitems;
	    $this->directlinks = $directlinks;

	    $this->ename = $ename;
        parent::display($tpl);
    }
}
