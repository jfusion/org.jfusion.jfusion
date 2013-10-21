<?php

/**
 * This is view file for syncError
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage SyncError
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
 * @subpackage SyncError
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewsyncerror extends JViewLegacy
{
	/**
	 * @var $pageNav JPagination
	 */
	var $pageNav;

	/**
	 * @var $filter array
	 */
	var $filter;

	/**
	 * @var $synclog array
	 */
	var $synclog;

	/**
	 * @var $syncdata array
	 */
	var $syncdata;

	/**
	 * @var $syncid string
	 */
	var $syncid;

    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed html output of view
     */
    function display($tpl = null)
    {
	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/'.$this->getName().'/tmpl/default.js');

        //Load usersync library
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';
        //check to see if the sync has already started
	    $this->syncid = JFactory::getApplication()->input->get('syncid');

        //append log
        $mainframe = JFactory::getApplication();
        $client = JFactory::getApplication()->input->getWord( 'filter_client', 'site' );
        $option = JFactory::getApplication()->input->getCmd('option');
        $filter_order = $mainframe->getUserStateFromRequest("$option.$client.filter_order", 'filter_order', 'id', 'cmd');
        $filter_order_Dir = $mainframe->getUserStateFromRequest("$option.$client.filter_order_Dir", 'filter_order_Dir', '', 'word');
        $limit = (int)$mainframe->getUserStateFromRequest('global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
        $limitstart  = (int)$mainframe->getUserStateFromRequest( $option.'.limitstart', 'limitstart', 0, 'int');

        $total = JFusionUsersync::countLogData($this->syncid, 'error');
        
        jimport('joomla.html.pagination');

	    $this->pageNav = new JPagination($total, $limitstart, $limit);
	    $this->syncdata = JFusionUsersync::getSyncdata($this->syncid);
	    $this->filter = array('order' => $filter_order, 'dir' => $filter_order_Dir, 'limit' => $limit, 'limitstart' => $limitstart, 'client' => $client);
	    $this->synclog = JFusionUsersync::getLogData($this->syncid, 'error', $limitstart, $limit, $filter_order, $filter_order_Dir);
        parent::display($tpl);
    }
}
