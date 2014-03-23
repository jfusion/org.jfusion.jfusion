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
	 * @var $syncid string
	 */
	var $syncid;

	/**
	 * @var $sync_mode string
	 */
	var $sync_mode;

	/**
	 * @var $master_data array
	 */
	var $master_data;

	/**
	 * @var $slave_data array
	 */
	var $slave_data;

	/**
	 * @var $sync_active int
	 */
	var $sync_active;

	/**
	 * @var $syncdata array
	 */
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
	    if (JFusionFunctionAdmin::isConfigOk()) {
		    $document = JFactory::getDocument();
		    $document->addScript('components/com_jfusion/views/' . $this->getName() . '/tmpl/default.js');

		    //find out what the JFusion master and slaves are
		    $db = JFactory::getDBO();
		    $master = \JFusion\Framework::getMaster();
		    $slaves = \JFusion\Framework::getSlaves();
		    //were we redirected here for a sync resume?
		    $syncid = JFactory::getApplication()->input->get->get('syncid', '');
		    if (!empty($syncid)) {
			    $query = $db->getQuery(true)
				    ->select('syncid')
				    ->from('#__jfusion_sync')
				    ->where('syncid = ' . $db->quote($syncid));

			    $db->setQuery($query);
			    if ($db->loadResult()) {
				    $syncdata = \JFusion\Usersync\Usersync::getSyncdata($syncid);
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
				    $sync_active = \JFusion\Usersync\Usersync::getSyncStatus($syncid);
			    }
			    //get the master data
			    $JFusionPlugin = \JFusion\Factory::getAdmin($master->name);
			    $master_data = $slave_data = array();

			    try {
				    $master_data['total'] = $JFusionPlugin->getUserCount();
			    } catch(Exception $e) {
				    $master_data['total'] = 0;
				    \JFusion\Framework::raiseWarning($e, $JFusionPlugin->getJname());
			    }
			    $master_data['jname'] = $master->name;

			    //get the slave data
			    foreach ($slaves as $slave) {
				    $JFusionSlave = \JFusion\Factory::getAdmin($slave->name);
				    $slave_data[$slave->name]['total'] = $JFusionSlave->getUserCount();
				    try {
					    $slave_data[$slave->name]['total'] = $JFusionSlave->getUserCount();
				    } catch(Exception $e) {
					    $slave_data[$slave->name]['total'] = 0;
					    \JFusion\Framework::raiseWarning($e, $JFusionSlave->getJname());
				    }


				    $slave_data[$slave->name]['jname'] = $slave->name;
				    unset($JFusionSlave);
			    }

			    //print out results to user
			    $this->sync_mode = $mode;
			    $this->master_data = $master_data;
			    $this->slave_data = $slave_data;
			    $this->syncid = $syncid;
			    $this->sync_active = $sync_active;

			    JFusionFunction::loadJavascriptLanguage(array('SYNC_PROGRESS', 'SYNC_USERS_TODO', 'CLICK_FOR_MORE_DETAILS', 'CONFLICTS',
				                                            'UNCHANGED', 'FINISHED', 'PAUSE', 'UPDATE_IN', 'SECONDS', 'SYNC_CONFIRM_START', 'UPDATED', 'PLUGIN', 'USER', 'USERS',
				                                            'NAME', 'CREATED', 'RESUME', 'SYNC_NODATA'));

			    $slave_data = json_encode($this->slave_data);

			    $js=<<<JS
	        JFusion.slaveData = {$slave_data};
	        JFusion.syncMode = '{$this->sync_mode}';
			JFusion.syncid = '{$this->syncid}';
JS;
			    $document->addScriptDeclaration($js);
			    if ($this->sync_mode != 'new') {
				    $syncdata = (string)new JResponseJson($this->syncdata);

				    $js=<<<JS
	        JFusion.response = $syncdata;

			window.addEvent('domready',function() {
				JFusion.renderSync(JFusion.response)
			});
JS;
				    $document->addScriptDeclaration($js);
			    }

			    parent::display();
		    } else {
			    \JFusion\Framework::raiseWarning(JText::_('SYNC_NOCONFIG'));
		    }
	    }
    }
}
