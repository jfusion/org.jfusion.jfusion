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
class jfusionViewsyncoptions extends JViewLegacy
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
	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/'.$this->getName().'/tmpl/default.js');

        //find out what the JFusion master and slaves are
        $db = JFactory::getDBO();
	    $master = JFusionFunction::getMaster();
	    $slaves = JFusionFunction::getSlaves();
        //were we redirected here for a sync resume?
        $syncid = JFactory::getApplication()->input->get->get('syncid', '');
        if (!empty($syncid)) {
	        $query = $db->getQuery(true)
		        ->select('syncid')
		        ->from('#__jfusion_sync')
		        ->where('syncid = '.$db->Quote($syncid));

            $db->setQuery($query);
            if ($db->loadResult()) {
                include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';
                $syncdata = JFusionUsersync::getSyncdata($syncid);
	            $this->syncdata = $syncdata;
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
	        $this->sync_mode = $mode;
	        $this->master_data = $master_data;
	        $this->slave_data = $slave_data;
	        $this->syncid = $syncid;
	        $this->sync_active = $sync_active;

	        JFusionFunction::loadJavascriptLanguage(array('SYNC_PROGRESS', 'SYNC_USERS_TODO', 'CLICK_FOR_MORE_DETAILS', 'CONFLICTS',
		        'UNCHANGED', 'FINISHED', 'PAUSE', 'UPDATE_IN', 'SECONDS', 'SYNC_CONFIRM_START', 'UPDATED', 'PLUGIN', 'USER', 'USERS',
		        'NAME', 'CREATED'));

	        parent::display();
        } else {
            JFusionFunctionAdmin::displayDonate();
            JFusionFunction::raiseWarning(JText::_('SYNC_NOCONFIG'));
        }
    }
}
