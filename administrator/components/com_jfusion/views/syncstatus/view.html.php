<?php

/**
 * This is view file for syncstatus
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Syncstatus
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Syncstatus
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewsyncstatus extends JViewLegacy
{
    var $syncid;
    var $syncdata;

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
        //add css
        $document = JFactory::getDocument();
        $document->addStyleSheet('components/com_jfusion/css/jfusion.css');
        $template = $mainframe->getTemplate();
        $document->addStyleSheet('templates/'.$template.'/css/general.css');
        JHTML::_('behavior.modal');
        JHTML::_('behavior.tooltip');

        //Load usersync library
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';

	    $this->syncid = JFactory::getApplication()->input->get('syncid');
	    $syncdata = JFusionUsersync::getSyncdata($this->syncid);

        //append log
        $mainframe = JFactory::getApplication();
        $client             = JFactory::getApplication()->input->getWord( 'filter_client', 'site' );
        $option = JFactory::getApplication()->input->getCmd('option');
        $filter_order       = $mainframe->getUserStateFromRequest( "$option.$client.filter_order",      'filter_order',     'id',       'cmd' );
        $filter_order_Dir   = $mainframe->getUserStateFromRequest( "$option.$client.filter_order_Dir",  'filter_order_Dir', '',         'word' );
        $limit              = (int)$mainframe->getUserStateFromRequest( 'global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int' );
        $limitstart         = (int)$mainframe->getUserStateFromRequest( $option.'.limitstart', 'limitstart', 0, 'int' );
        $syncdata['log'] = JFusionUsersync::getLogData($this->syncid, 'all', $limitstart, $limit, $filter_order, $filter_order_Dir);
        $filter = array('order' => $filter_order, 'dir' => $filter_order_Dir, 'limit' => $limit, 'limitstart' => $limitstart, 'client' => $client);

        $db = JFactory::getDBO();

	    $query = $db->getQuery(true)
		    ->select('COUNT(*)')
		    ->from('#__jfusion_sync_details')
		    ->where('syncid = '.$db->Quote($this->syncid));

        $db->setQuery($query);
        $total = $db->loadResult();
        jimport('joomla.html.pagination');
        $pageNav = new JPagination($total, $limitstart, $limit);

	    $this->pageNav = $pageNav;
	    $this->filter = $filter;
	    $this->syncdata = $syncdata;

	    $this->errorCount = JFusionUsersync::countLogData($this->syncid, 'error');
        parent::display($tpl);
    }
}
