<?php

 /**
 * This is the jfusion admin controller
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

/**
 * Load the JFusion framework
 */
jimport('joomla.application.component.controller');
jimport('joomla.application.component.view');
require_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.factory.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.jfusion.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.jfusionadmin.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'defines.php';
/**
 * JFusion Controller class
 *
 * @category  JFusion
 * @package   ControllerAdmin
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionController extends JController
{
    /**
     * @return JController|void
     */
    function display() {
        parent::display();
    }

    /**
     * Display the results of the wizard set-up
     *
     * @return void
     */
    function wizardresult()
    {
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //find out the submitted values
        $jname = JRequest::getVar('jname');
        $post = JRequest::getVar('params', array(), 'post', 'array');
        //check to see data was posted
        if ($jname && $post) {
            //Initialize the forum
            $JFusionPlugin = & JFusionFactory::getAdmin($jname);
            $params = $JFusionPlugin->setupFromPath($post['source_path']);
            if (!empty($params)) {
                //save the params first in order for elements to utilize data
                JFusionFunctionAdmin::saveParameters($jname, $params, true);

                //make sure the usergroup paramas are available on first view
                $config_status = $JFusionPlugin->checkConfig();
                $db = JFactory::getDBO();
                $query = 'UPDATE #__jfusion SET status = ' . $config_status['config'] . ' WHERE name =' . $db->Quote($jname);
                $db->setQuery($query);
                $db->query();


                $parameters = & JFusionFactory::getParams($jname);
                $param2_output = $parameters->render();
                JError::raiseNotice(0, JText::_('WIZARD_SUCCESS'));
                $view = & $this->getView('plugineditor', 'html');
                $view->assignRef('parameters', $param2_output);
                $view->assignRef('jname', $jname);
                $view->setLayout('default');
                $view->display();
            } else {
                //load the default XML parameters
                $parameters = & JFusionFactory::getParams($jname);
                $param_output = $parameters->render();
                JError::raiseWarning(500, JText::_('WIZARD_FAILURE'));
                $view = & $this->getView('plugineditor', 'html');
                $view->assignRef('parameters', $param_output);
                $view->setLayout('default');
                $view->display();
            }
        } else {
            JError::raiseWarning(500, JText::_('WIZARD_FAILURE'));
            JRequest::setVar('view', 'plugineditor');
            $this->display();
        }
    }
    
   /**
     * Function to change the master/slave/encryption settings in the jos_jfusion table
     *
     * @return void
     */
    function changesettings()
    {
        //find out the posted ID of the JFusion module to publish
        $jname = JRequest::getVar('jname');
        $field_name = JRequest::getVar('field_name');
        $field_value = JRequest::getVar('field_value');
        //check to see if an integration was selected
        $db = JFactory::getDBO();
        if ($jname) {
            if ($field_name == 'master') {
                //If a master is being set make sure all other masters are disabled first
                $query = 'UPDATE #__jfusion SET master = 0';
                $db->setQuery($query);
                $db->query();
            }
            //perform the update
            $query = 'UPDATE #__jfusion SET ' . $field_name . ' =' . $db->Quote($field_value) . ' WHERE name = ' . $db->Quote($jname);
            $db->setQuery($query);
            $db->query();
            //get the new plugin settings
            $query = 'SELECT * FROM #__jfusion WHERE name = ' . $db->Quote($jname);
            $db->setQuery($query);
            $result = $db->loadObject();
            //disable a slave when it is turned into a master
            if ($field_name == 'master' && $field_value == '1' && $result->slave == '1') {
                $query = 'UPDATE #__jfusion SET slave = 0 WHERE name = ' . $db->Quote($jname);
                $db->setQuery($query);
                $db->query();
            }
            //disable a master when it is turned into a slave
            if ($field_name == 'slave' && $field_value == '1' && $result->master == '1') {
                $query = 'UPDATE #__jfusion SET master = 0 WHERE name = ' . $db->Quote($jname);
                $db->setQuery($query);
                $db->query();
            }
            //auto enable the auth and dual login for newly enabled plugins
            if (($field_name == 'slave' || $field_name == 'master') && $field_value == '1') {
                $query = 'SELECT dual_login FROM #__jfusion WHERE name = ' . $db->Quote($jname);
                $db->setQuery($query);
                $dual_login = $db->loadResult();
                if ($dual_login > 1) {
                    //only set the encryption if dual login is disabled
                    $query = 'UPDATE #__jfusion SET check_encryption = 1 WHERE name = ' . $db->Quote($jname);
                    $db->setQuery($query);
                    $db->query();
                } else {
                    $query = 'UPDATE #__jfusion SET dual_login = 1, check_encryption = 1 WHERE name = ' . $db->Quote($jname);
                    $db->setQuery($query);
                    $db->query();
                }
            }
            //auto disable the auth and dual login for newly disabled plugins
            if (($field_name == 'slave' || $field_name == 'master') && $field_value == '0') {
                //only set the encryption if dual login is disabled
                $query = 'UPDATE #__jfusion SET check_encryption = 0, dual_login = 0 WHERE name = ' . $db->Quote($jname);
                $db->setQuery($query);
                $db->query();
            }
        }
        
        //recheck the enabled plugins
        $query = 'SELECT * from #__jfusion WHERE master = 1 or slave = 1';
        $db->setQuery($query );
        $rows = $db->loadObjectList();
        $plugins = array();

        if ($rows) {
            foreach($rows as $record) {
                $JFusionPlugin =& JFusionFactory::getAdmin($record->name);   	
        		$JFusionPlugin->debugConfig();
            }
        }
        $debug = array();
        $view = $this->getView('plugindisplay','html');
        $debug['errormessage'] = $view->generateErrorHTML();
        die(json_encode($debug));
    }

    /**
     * Function to save the JFusion plugin parameters
     *
     * @return void
     */
    function saveconfig()
    {
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //get the posted variables
        $post = JRequest::getVar('params', array(), 'post', 'array');
        $jname = JRequest::getVar('jname', '', 'POST', 'STRING');
        //check for trailing slash in URL, in order for us not to worry about it later
        if (substr($post['source_url'], -1) == '/') {
        } else {
            $post['source_url'].= '/';
        }
        //now also check to see that the url starts with http:// or https://
        if (substr($post['source_url'], 0, 7) != 'http://' && substr($post['source_url'], 0, 8) != 'https://') {
            if (substr($post['source_url'], 0, 1) != '/') {
                $post['source_url'] = 'http://' . $post['source_url'];
            }
        }
        if (!empty($post['source_path'])) {
            if (!is_dir($post['source_path'])) {
                JError::raiseWarning(500, JText::_('SOURCE_PATH_NOT_FOUND'));
            }
        }
        if (!JFusionFunctionAdmin::saveParameters($jname, $post)) {
            $msg = $jname . ': ' . JText::_('SAVE_FAILURE');
            $msgType = 'error';
        } else {
            //update the status field
            $JFusionPlugin = & JFusionFactory::getAdmin($jname);
            $config_status = $JFusionPlugin->checkConfig();
            $db = JFactory::getDBO();
            $query = 'UPDATE #__jfusion SET status = ' . $config_status['config'] . ' WHERE name =' . $db->Quote($jname);
            $db->setQuery($query);
            $db->query();
            if (empty($config_status['config'])) {
                $msg = $jname . ': ' . $config_status['message'];
                $msgType = 'error';
            } else {
                $msg = $jname . ': ' . JText::_('SAVE_SUCCESS');
                $msgType = 'message';
                //check for any custom commands
                $customcommand = JRequest::getVar('customcommand');
                if (!empty($customcommand)) {
                    $JFusionPlugin = & JFusionFactory::getAdmin($jname);
                    if (method_exists($JFusionPlugin, $customcommand)) {
                        $JFusionPlugin->$customcommand();
                    }
                }
            }
        }
        $action = JRequest::getVar('action');
        if ($action == 'apply') {
            $this->setRedirect('index.php?option=com_jfusion&task=plugineditor&jname=' . $jname, $msg, $msgType);
        } else {
            $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay', $msg, $msgType);
        }
    }

    /**
     * Resumes a usersync if it has stopped
     *
     * @return void
     */
    function syncresume()
    {
        $syncid = JRequest::getVar('syncid', '', 'GET');
        $db = JFactory::getDBO();
        $query = 'SELECT syncid FROM #__jfusion_sync WHERE syncid =' . $db->Quote($syncid);
        $db->setQuery($query);
        if ($db->loadResult()) {
            //Load usersync library
            include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.usersync.php';
            $syncdata = JFusionUsersync::getSyncdata($syncid);
            //start the usersync
            $plugin_offset = (!empty($syncdata['plugin_offset'])) ? $syncdata['plugin_offset'] : 0;
            //start at the next user
            $user_offset = (!empty($syncdata['user_offset'])) ? $syncdata['user_offset'] : 0;
            if (JRequest::getVar('userbatch')) {
                $syncdata['userbatch'] = JRequest::getVar('userbatch');
            }
            JFusionUsersync::syncExecute($syncdata, $syncdata['action'], $plugin_offset, $user_offset);
            JRequest::setVar('view', 'syncstatus');
            $view = & $this->getView('syncstatus', 'html');
            //append log data now
            $syncdata['log'] = JFusionUsersync::getLogData($syncid);
            $view->assignRef('syncdata', $syncdata);
            $view->assignRef('syncid', $syncid);
            //notify syncstatus view that sync was just completed so that appropriate output is made via ajax
            $view->assign('sync_completed', 1);
            $view->setLayout('default');
            $view->display();
        } else {
            $mainframe = JFactory::getApplication();
            $mainframe->redirect('index.php?option=com_jfusion&task=syncoptions', JText::sprintf('SYNC_ID_NOT_EXIST', $syncid), 'error');
        }
    }

    /**
     * sync process
     *
     * @return void
     */
    function syncprogress()
    {
        $syncid = JRequest::getVar('syncid', '', 'GET');
        include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.usersync.php';
        $syncdata = JFusionUsersync::getSyncdata($syncid);
        if (empty($syncdata['completed'])) {
            JRequest::setVar('view', 'syncprogress');
            $view = & $this->getView('syncprogress', 'html');
            //append log data now
            $view->assignRef('syncdata', $syncdata);
            $view->assignRef('syncid', $syncid);
            $view->setLayout('default');
            $view->display();
        } else {
            echo '<h2>' . JText::_('USERSYNC') . ' ' . JText::_('COMPLETED') . '</h2><br/>';
            //needed in case the language does not have "finished" in it for ajax to know to stop the timer
            echo '<div style="display:none;">finished</div>';
            JRequest::setVar('view', 'syncstatus');
            $view = & $this->getView('syncstatus', 'html');
            //append log
            $syncdata['log'] = JFusionUsersync::getLogData($syncid);
            $view->assignRef('syncdata', $syncdata);
            $view->assignRef('syncid', $syncid);
            //notify syncstatus view that sync was just completed so that appropriate output is made via ajax
            $view->assign('sync_completed', 1);
            $view->setLayout('default');
            $view->display();
        }
    }

    /**
     * Displays the usersync error screen
     *
     * @return void
     */
    function syncerror()
    {
        //Load usersync library
        include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.usersync.php';
        $syncError = JRequest::getVar('syncError', array(), 'POST', 'array');
        $syncid = JRequest::getVar('syncid', '', 'POST');
        if ($syncError) {
            //apply the submitted sync error instructions
            JFusionUsersync::syncError($syncid, $syncError);
        } else {
            //output the sync errors to the user
            JRequest::setVar('view', 'syncerror');
            $this->display();
        }
    }

    /**
     * Displays the usersync history screen
     *
     * @return void
     */
    function syncerrordetails()
    {
        //Load usersync library
        include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.usersync.php';
        $view = & $this->getView('syncerrordetails', 'html');
        $view->setLayout('default');
        //$result = $view->loadTemplate();
        $result = $view->display();
        die($result);
    }

    /**
     * Initiates the sync
     *
     * @return void
     */
    function syncinitiate()
    {
        //Load usersync library
        include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.usersync.php';
        //check to see if the sync has already started
        $syncid = JRequest::getVar('syncid');
        $action = JRequest::getVar('action');

        if (!empty($syncid)) {
            //clear sync in progress catch in case we manually stopped the sync so that the sync will continue
            JFusionUsersync::changeSyncStatus($syncid, 0);
        }

        $db = JFactory::getDBO();
        $query = 'SELECT syncid FROM #__jfusion_sync WHERE syncid =' . $db->Quote($syncid);
        $db->setQuery($query);
        if (!$db->loadResult()) {
            //sync has not started, lets get going :)
            $slaves = JRequest::getVar('slave');
            $master_plugin = JFusionFunction::getMaster();
            $master = $master_plugin->name;
            $JFusionMaster = & JFusionFactory::getAdmin($master);
            //initialise the slave data array
            $slave_data = array();
            if (empty($slaves)) {
                //nothing was selected in the usersync
                die(JText::_('SYNC_NODATA'));
            }
            $syncdata = array();
            $syncdata['sync_errors'] = 0;
            $syncdata['total_to_sync'] = 0;
            $syncdata['synced_users'] = 0;
            //lets find out which slaves need to be imported into the Master
            foreach ($slaves as $jname => $slave) {
                if ($slave['perform_sync']) {
                    $temp_data = array();
                    $temp_data['jname'] = $jname;
                    $JFusionPlugin = & JFusionFactory::getAdmin($jname);
                    if ($action == 'master') {
                        $temp_data['total'] = $JFusionPlugin->getUserCount();
                    } else {
                        $temp_data['total'] = $JFusionMaster->getUserCount();
                    }
                    $syncdata['total_to_sync']+= $temp_data['total'];
                    //this doesn't change and used by usersync when limiting the number of users to grab at a time
                    $temp_data['total_to_sync'] = $temp_data['total'];
                    $temp_data['created'] = 0;
                    $temp_data['deleted'] = 0;
                    $temp_data['updated'] = 0;
                    $temp_data['error'] = 0;
                    $temp_data['unchanged'] = 0;
                    //save the data
                    $slave_data[] = $temp_data;
                    //reset the variables
                    unset($temp_data, $JFusionPlugin);
                }
            }
            //format the syncdata for storage in the JFusion sync table
            $syncdata['master'] = $master;
            $syncdata['syncid'] = $syncid;
            $syncdata['userbatch'] = JRequest::getVar('userbatch', 100);
            $syncdata['user_offset'] = 0;
            $syncdata['slave_data'] = $slave_data;
            $syncdata['action'] = $action;
            //save the submitted syndata in order for AJAX updates to work
            JFusionUsersync::saveSyncdata($syncdata);
            //start the usersync
            JFusionUsersync::syncExecute($syncdata, $action, 0, 0);
        }
        jexit();
    }

    /**
     * Function to upload, parse & install JFusion plugins
     *
     * @return void
     */
    function installplugin()
    {
        include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.install.php';
        $model = new JFusionModelInstaller();
        $result = $model->install();
		
		$ajax = JRequest::getVar('ajax');
		if ($ajax == true) {
	        if(!empty($result['jname'])){
	            $view = $this->getView('plugindisplay','html');
	            $result['rowhtml'] = $view->generateRowHTML($view->initRecord($result['jname']));            
	        }
	        die(json_encode($result));
		} else {
			if ($result['status']) {
				JError::raiseNotice(0, $result['message']);
			} else {
				JError::raiseWarning(0, $result['message']);
			}
			$this->setRedirect('index.php?option=com_jfusion&task=plugindisplay');
		}
    }
    
    /**
     * Function to upload, parse & install JFusion plugins
     *
     * @return void
     */
    function installplugin2()
    {
        include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.install.php';
        $model = new JFusionModelInstaller();
        $result = $model->install();
        if(!empty($result['jname'])){
            $view = $this->getView('plugindisplay','html');
            $result['rowhtml'] = $view->generateRowHTML($view->initRecord($result['jname']));            
        }
        die(json_encode($result));
    }
    
    function installplugins()
    {
    	$jfusionplugins = JRequest::getVar('jfusionplugins', array(), 'post', 'array');
	    include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.install.php';
		foreach ($jfusionplugins as $plugin) {
                //install updates
                $packagename = JPATH_COMPONENT_ADMINISTRATOR . DS . 'packages' . DS . 'jfusion_' . $plugin . '.zip';
                $model = new JFusionModelInstaller();
                $result = $model->installZIP($packagename);
                JError::raiseNotice(0, $result['message']);
		}
        $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay');
    }
    
    function plugincopy()
    {
        $jname = JRequest::getVar('jname');
        $new_jname = JRequest::getVar('new_jname');

        //replace not-allowed characters with _
        $new_jname = preg_replace('/([^a-zA-Z0-9_])/', '_', $new_jname);

        //initialise response element
        $result = array();
        
        //check to see if an integration was selected
        if ($jname && $new_jname) {
            include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.install.php';
            $model = new JFusionModelInstaller();
            $result = $model->copy($jname, $new_jname);
            
            //get description
            $plugin_xml = JFUSION_PLUGIN_PATH .DS. $jname .DS. 'jfusion.xml';
             if(file_exists($plugin_xml) && is_readable($plugin_xml)) {
                 $parser = JFactory::getXMLParser('Simple');
                 $xml    = $parser->loadFile($plugin_xml);
                 $xml    = $parser->document;
                 if(!empty($xml->description)) {
                     $description = $xml->description[0]->data();
                 }
            }
			if ($result['status']) {
            	$result['new_jname'] =  $new_jname;
            	$view = $this->getView('plugindisplay','html');
            	$result['rowhtml'] = $view->generateRowHTML($view->initRecord($new_jname));
			}
        } else {
            $result['status'] = false;
            $result['message'] =  JText::_('NONE_SELECTED');
        }
		//output results
		die(json_encode($result));
    }

    /**
     * Function to uninstall JFusion plugins
     *
     * @return void
     */
    function uninstallplugin()
    {
        $jname = JRequest::getVar('jname');
        //check to see if an integration was selected
        if ($jname && $jname != 'joomla_int') {
            include_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.install.php';
            $model = new JFusionModelInstaller();
            $result = $model->uninstall($jname);
        } else { 
        	$result['message'] = 'JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('UNINSTALL') . ' ' . JText::_('FAILED');
        	$result['status'] = false;
        }

        $result['jname'] = $jname;
		//output results
        die(json_encode($result));
    }

    /**
     * Enables the JFusion Plugins
     *
     * @return void
     */
    function enableplugins()
    {
        //enable the JFusion login behaviour, but we wanna make sure there is atleast 1 master with good config
        $db = JFactory::getDBO();
        $query = 'SELECT count(*) from #__jfusion WHERE master = 1 and status = 1';
        $db->setQuery($query);
        if ($db->loadResult()) {
            JFusionFunctionAdmin::changePluginStatus('joomla','authentication',0);
            JFusionFunctionAdmin::changePluginStatus('joomla','user',0);
            JFusionFunctionAdmin::changePluginStatus('jfusion','authentication',1);
            JFusionFunctionAdmin::changePluginStatus('jfusion','user',1);
        } else {
            JError::raiseWarning(500, JText::_('NO_MASTER_WARNING'));
        }
		$this->setRedirect('index.php?option=com_jfusion&task=cpanel');
    }

    /**
     * Disables the JFusion Plugins
     *
     * @return void
     */
    function disableplugins()
    {
        //restore the normal login behaviour
        JFusionFunctionAdmin::changePluginStatus('joomla','authentication',1);
        JFusionFunctionAdmin::changePluginStatus('joomla','user',1);
        JFusionFunctionAdmin::changePluginStatus('jfusion','authentication',0);
        JFusionFunctionAdmin::changePluginStatus('jfusion','user',0);
        $this->setRedirect('index.php?option=com_jfusion&task=cpanel');
    }
    
    /**
     * Config dump
     *
     * @return void
     */
    function configdump()
    {
    	JRequest::setVar('view', 'configdump');
    	$this->display();
    }    

    /**
     * delere sync history
     *
     * @return void
     */
    function deletehistory()
    {
        $db = JFactory::getDBO();
        $syncid = JRequest::getVar('syncid');
        if(!is_array($syncid)) {
            JError::raiseWarning(500, JText::_('NO_SYNCID_SELECTED'));
        } else {
            foreach ($syncid as $key => $value) {
                $query = 'DELETE FROM #__jfusion_sync WHERE syncid = ' . $db->Quote($key);
                $db->setQuery($query);
                $db->query();

                $query = 'DELETE FROM #__jfusion_sync_details WHERE syncid = ' . $db->Quote($key);
                $db->setQuery($query);
                $db->query();
            }
        }
        JRequest::setVar('view', 'synchistory');
        $this->display();
    }

    /**
     * resolve error
     *
     * @return void
     */
    function resolveerror()
    {
        $db = JFactory::getDBO();
        $syncid = JRequest::getVar('syncid');
        if(!is_array($syncid)) {
            JError::raiseWarning(500, JText::_('NO_SYNCID_SELECTED'));
        	JRequest::setVar('view', 'synchistory');
        } else {
        	foreach ($syncid as $key => $value) {
                $syncid = JRequest::setVar('syncid', $key);
                //output the sync errors to the user
                JRequest::setVar('view', 'syncerror');
                break;
            }
        }
        $this->display();
    }

    /**
     * Displays the JFusion PluginMenu Parameters
     *
     * @return void
     */
    function advancedparamsubmit()
    {
        $param = JRequest::getVar('params');
        $multiselect = JRequest::getVar('multiselect');
        if ($multiselect) {
            $multiselect = true;
        } else {
            $multiselect = false;
        }
        $elNum = JRequest::getInt('elNum');
        $serParam = base64_encode(serialize($param));
        $title = "";
        if (isset($param["jfusionplugin"])) {
            $title = $param["jfusionplugin"];
        } else if ($multiselect) {
            $del = "";
            if (is_array($param)) {
               foreach ($param as $key => $value) {
                    if (isset($value["jfusionplugin"])) {
                        $title.= $del . $value["jfusionplugin"];
	                    $del = "; ";
                    }
                }
            }
        }
        if (empty($title)) {
            $title = JText::_('NO_PLUGIN_SELECTED');
        }
        echo '<script type="text/javascript">' . 'window.parent.jAdvancedParamSet("' . $title . '", "' . $serParam . '","' . $elNum . '");' . '</script>';
        return;
    }
    
	function saveorder()
	{
	    //split the value of the sortation
	    $sort_order = JRequest::getVar('sort_order');
		$ids = explode('|',$sort_order);
		$query ='';
		$db = JFactory::getDBO();
	
		/* run the update query for each id */
		foreach($ids as $index=>$id)
		{
			if($id != '') {
	            $query .= ' UPDATE #__jfusion SET ordering = ' .(int) $index .' WHERE name = ' . $db->Quote($id) . ' ; ';
			}
		}
		$db->setQuery($query);
	    if (!$db->queryBatch()) {
	        echo $db->stderr() . '<br/>';
	    }
	}
}
