<?php

/**
 * This is view file for syncoptions
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Syncoptions
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
 * @subpackage Syncoptions
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewsyncoptions extends JView
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
        //find out what the JFusion master and slaves are
        $db = JFactory::getDBO();
        $query = 'SELECT * from #__jfusion WHERE master = 1 and status = 1';
        $db->setQuery($query);
        $master = $db->loadObject();
        $query = 'SELECT * from #__jfusion WHERE slave = 1 and status = 1';
        $db->setQuery($query);
        $slaves = $db->loadObjectList();
        //were we redirected here for a sync resume?
        $syncid = JRequest::getVar('syncid', '', 'GET');
        if (!empty($syncid)) {
            $query = 'SELECT syncid FROM #__jfusion_sync WHERE syncid =' . $db->Quote($syncid);
            $db->setQuery($query);
            if ($db->loadResult()) {
                include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.usersync.php';
                $syncdata = JFusionUsersync::getSyncdata($syncid);
                $this->assignRef('syncdata', $syncdata);
                $mode = 'resume';
            } else {
                $mode = 'new';
            }
        } else {
            $mode = 'new';
        }
        //only run the usersync if master and slaves exist
        if ($master && $slaves) {
            if ($mode == 'new') {
                //generate a user sync sessionid
                jimport('joomla.user.helper');
                $syncid = JUserHelper::genRandomPassword(10);
                $sync_active = 0;
            } else {
                $sync_active = JFusionUsersync::getSyncStatus($syncid);
            }
            //get the master data
            $JFusionPlugin = JFusionFactory::getAdmin($master->name);
            $master_data = $slave_data = array();
            $master_data['total'] = $JFusionPlugin->getUserCount();
            $master_data['jname'] = $master->name;
            //get the slave data
            foreach ($slaves as $slave) {
                $JFusionSlave = JFusionFactory::getAdmin($slave->name);
                $slave_data[$slave->name]['total'] = $JFusionSlave->getUserCount();
                $slave_data[$slave->name]['jname'] = $slave->name;
                unset($JFusionSlave);
            }
            //serialise the data for storage in the usersync table
            $slave_serial = serialize($slave_data);
            $master_serial = serialize($master_data);

            //print out results to user
            $this->assignRef('sync_mode', $mode);
            $this->assignRef('master_data', $master_data);
            $this->assignRef('slave_data', $slave_data);
            $this->assignRef('syncid', $syncid);
            $this->assignRef('sync_active', $sync_active);
            if(JFusionFunction::isJoomlaVersion('1.6')){
                parent::display('25');
            } else {
                parent::display('15');
            }
        } else {
            JFusionFunctionAdmin::displayDonate();
            JError::raiseWarning(500, JText::_('SYNC_NOCONFIG'));
        }
    }
}
