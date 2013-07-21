<?php

/**
 * This is view file for wizard
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Plugindisplay
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'defines.php';
jimport('joomla.application.component.view');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Plugindisplay
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class jfusionViewplugindisplay extends JViewLegacy {
    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed
     */
    function display($tpl = null)
    {
	    $plugins = $this->getPlugins();
        if (!empty($plugins)) {
            //we found plugins now prepare the data
	        jimport('joomla.version');
	        $jversion = new JVersion();
            //get the install xml
	        $url = 'http://update.jfusion.org/jfusion/joomla/?version'.$jversion->getShortVersion();
	        $VersionDataRaw = JFusionFunctionAdmin::getFileData($url);
            $VersionData = null;
	        if (!empty($VersionDataRaw)) {
		        $xml = JFusionFunction::getXml($VersionDataRaw,false);
	            if ($xml) {
		            if ($xml->plugins) {
			            $VersionData = $xml->plugins->children();
		            }
		            unset($parser);
	            }
	        }

            //pass the data onto the view
	        $this->plugins = $plugins;
	        $this->VersionData = $VersionData;

	        JFusionFunction::initJavaScript();

	        parent::display();
        } else {
            JFusionFunction::raiseWarning(JText::_('NO_JFUSION_TABLE'));
        }
    }

    /**
     * @param $jname
     * @param null|stdClass $record
     *
     * @return null|\stdClass
     */
    function initRecord($jname,$record=null) {
	    $db = JFactory::getDBO();
	    if (!$record) {
		    $query = 'SELECT * from #__jfusion WHERE name LIKE '.$db->quote($jname);
		    $db->setQuery($query);
		    $record = $db->loadObject();
	    }
	    try {
		    $JFusionPlugin = JFusionFactory::getAdmin($record->name);
		    $JFusionParam = JFusionFactory::getParams($record->name);

		    if($record->status==1) {
			    //added check for database configuration to prevent error after moving sites
			    $status =  $JFusionPlugin->checkConfig();
			    //do a check to see if the status field is correct
			    if ($status['config'] != $record->status) {
				    //update the status and deactivate the plugin
				    $db->setQuery('UPDATE #__jfusion SET status = '.$db->Quote($status['config']).' WHERE name =' . $db->Quote($record->name));
				    $db->execute();
				    //update the record status for the resExecute of the code
				    $record->status = $status['config'];
			    }
		    }

		    //set copy options
		    if (!$JFusionPlugin->multiInstance() || $record->original_name) {
			    //cannot copy joomla_int
			    $record->copyimage = 'components/com_jfusion/images/copy_icon_dim.png';
			    $record->copyscript =  'javascript:void(0)';
		    } else {
			    $record->copyimage = 'components/com_jfusion/images/copy_icon.png';
			    $record->copyscript =  'javascript: JFusion.copyPlugin(\'' . $record->name . '\')';
		    }

		    //set uninstall options
		    $query = 'SELECT count(*) from #__jfusion WHERE original_name LIKE '. $db->Quote($record->name);
		    $db->setQuery($query);
		    $copys = $db->loadResult();
		    if ($record->name == 'joomla_int' || $copys) {
			    //cannot uninstall joomla_int
			    $record->deleteimage = 'components/com_jfusion/images/delete_icon_dim.png';
			    $record->deletescript =  'javascript:void(0)';
		    } else {
			    $record->deleteimage = 'components/com_jfusion/images/delete_icon.png';
			    $record->deletescript =  'javascript: JFusion.deletePlugin(\'' . $record->name .'\')"';
		    }

		    //set wizard options
		    $record->wizard = JFusionFunction::hasFeature($record->name,'wizard');
		    if($record->wizard) {
			    $record->wizardimage = 'components/com_jfusion/images/wizard_icon.png';
			    $record->wizardscript =  'index.php?option=com_jfusion&task=wizard&jname=' .$record->name;
		    } else {
			    $record->wizardimage = 'components/com_jfusion/images/wizard_icon_dim.png';
			    $record->wizardscript =  'javascript:void(0)';
		    }

		    //set master options
		    if($record->status != 1){
			    $record->masterimage = 'components/com_jfusion/images/cross_dim.png';
			    $record->masterscript =  'javascript:void(0)';
			    $record->masteralt =  'unavailable';
		    } elseif ($record->master == 1) {
			    $record->masterimage = 'components/com_jfusion/images/tick.png';
			    $record->masterscript =  'javascript: JFusion.changeSetting(\'master\',\'0\',\'' .$record->name.'\');';
			    $record->masteralt =  'enabled';
		    } else {
			    $record->masterimage = 'components/com_jfusion/images/cross.png';
			    $record->masterscript =  'javascript: JFusion.changeSetting(\'master\',\'1\',\'' .$record->name.'\');';
			    $record->masteralt =  'disabled';
		    }

		    //set slave options
		    if($record->status != 1){
			    $record->slaveimage = 'components/com_jfusion/images/cross_dim.png';
			    $record->slavescript =  'javascript:void(0)';
			    $record->slavealt =  'unavailable';
		    } elseif ($record->slave == 1) {
			    $record->slaveimage = 'components/com_jfusion/images/tick.png';
			    $record->slavescript =  'javascript: JFusion.changeSetting(\'slave\',\'0\',\'' .$record->name.'\');';
			    $record->slavealt =  'enabled';
		    } else {
			    $record->slaveimage = 'components/com_jfusion/images/cross.png';
			    $record->slavescript =  'javascript: JFusion.changeSetting(\'slave\',\'1\',\'' .$record->name.'\');';
			    $record->slavealt =  'disabled';
		    }

		    //set check encryption options
		    if($record->status != 1) {
			    $record->encryptimage = 'components/com_jfusion/images/cross_dim.png';
			    $record->encryptscript =  'javascript:void(0)';
			    $record->encryptalt =  'unavailable';
		    } elseif ($record->check_encryption == 1) {
			    $record->encryptimage = 'components/com_jfusion/images/tick.png';
			    $record->encryptscript =  'javascript: JFusion.changeSetting(\'check_encryption\',\'0\',\'' .$record->name.'\');';
			    $record->encryptalt =  'enabled';
		    } else {
			    $record->encryptimage = 'components/com_jfusion/images/cross.png';
			    $record->encryptscript =  'javascript: JFusion.changeSetting(\'check_encryption\',\'1\',\'' .$record->name.'\');';
			    $record->encryptalt =  'disabled';
		    }

		    //set dual login options
		    if($record->status != 1){
			    $record->dualimage = 'components/com_jfusion/images/cross_dim.png';
			    $record->dualscript =  'javascript:void(0)';
			    $record->dualalt =  'unavailable';
		    } elseif ($record->dual_login == 1) {
			    $record->dualimage = 'components/com_jfusion/images/tick.png';
			    $record->dualscript =  'javascript: JFusion.changeSetting(\'dual_login\',\'0\',\'' .$record->name.'\');';
			    $record->dualalt =  'enabled';
		    } else {
			    $record->dualimage = 'components/com_jfusion/images/cross.png';
			    $record->dualscript =  'javascript: JFusion.changeSetting(\'dual_login\',\'1\',\'' .$record->name.'\');';
			    $record->dualalt =  'disabled';
		    }

		    //display status
		    if ($record->status != 1) {
			    $record->statusimage = 'components/com_jfusion/images/cross.png';
			    $record->statusalt =  JText::_('NO_CONFIG');
		    } else {
			    $record->statusimage = 'components/com_jfusion/images/tick.png';
			    $record->statusalt =  JText::_('GOOD_CONFIG');
		    }

		    //see if a plugin has copies
		    $query = 'SELECT * FROM #__jfusion WHERE original_name = \''.$record->name.' \'';
		    $db->setQuery($query);
		    $record->copies = $db->loadObjectList('name');

		    //get the description
		    $record->description = $JFusionParam->get('description');
		    if(empty($record->description)){
			    //get the default description
			    $plugin_xml = JFUSION_PLUGIN_PATH .DIRECTORY_SEPARATOR. $record->name .DIRECTORY_SEPARATOR. 'jfusion.xml';
			    if(file_exists($plugin_xml) && is_readable($plugin_xml)) {
				    $xml = JFusionFunction::getXml($plugin_xml);
				    $description = $xml->description;
				    if(!empty($description)) {
					    $record->description = (string)$description;
				    }
			    }
		    }

		    if ($record->status != 1) {
			    $record->usercount = '';
		    } else {
			    $record->usercount = $JFusionPlugin->getUserCount();
		    }

		    //get the registration status
		    if ($record->status != 1) {
			    $record->registrationimage = 'components/com_jfusion/images/clear.png';
			    $record->registrationalt =  '';
		    } else {
			    $record->registration  = $JFusionPlugin->allowRegistration();
			    if (!empty($record->registration)) {
				    $record->registrationimage = 'components/com_jfusion/images/tick.png';
				    $record->registrationalt =  JText::_('ENABLED');
			    } else {
				    $record->registrationimage = 'components/com_jfusion/images/cross.png';
				    $record->registrationalt =  JText::_('DISABLED');
			    }
		    }

		    if($record->status == 1) {
			    //display the default usergroup
			    if (JFusionFunction::isAdvancedUsergroupMode($record->name)) {
				    $usergroup = JText::_('ADVANCED_GROUP_MODE');
			    } else {
				    $usergroup = $JFusionPlugin->getDefaultUsergroup();
			    }

			    if ($usergroup) {
				    $record->usergrouptext = $usergroup;
			    } else {
				    $record->usergrouptext = '<img src="components/com_jfusion/images/cross.png" border="0" alt="'.JText::_('DISABLED').'" />' . JText::_('MISSING') . ' ' . JText::_('DEFAULT_USERGROUP') ;
				    JFusionFunction::raiseWarning(JText::_('MISSING') . ' ' . JText::_('DEFAULT_USERGROUP'), $record->name);
			    }
		    } else {
			    $record->usergrouptext = '';
		    }
	    } catch (Exception $e) {
		    $record = new stdClass;
	    }
		return  $record;
    }

	/**
	 * @return array
	 */
	function getPlugins() {
		//check to see if the ordering is correct
		$db = JFactory::getDBO();
		$query = 'SELECT * from #__jfusion WHERE ordering = \'\' OR ordering IS NULL';
		$db->setQuery($query );
		$ordering = $db->loadObjectList();
		JHTML::_('behavior.modal', 'a.modal');
		if(!empty($ordering)){
			//set a new order
			$query = 'SELECT * from #__jfusion ORDER BY ordering ASC';
			$db->setQuery($query );
			$rows = $db->loadObjectList();
			$ordering = 1;
			foreach ($rows as $row){
				$db->setQuery('UPDATE #__jfusion SET ordering = '.$ordering.' WHERE name = '. $db->Quote($row->name));
				$db->execute();
				$ordering++;
			}
		}

		//get the data about the JFusion plugins
		$query = 'SELECT * from #__jfusion ORDER BY ordering ASC';
		$db->setQuery($query );
		$rows = $db->loadObjectList();
		$plugins = array();

		if ($rows) {
			//we found plugins now prepare the data
			foreach($rows as $record) {
				$JFusionPlugin = JFusionFactory::getAdmin($record->name);
				$JFusionParam = JFusionFactory::getParams($record->name);

				//output detailed configuration warnings for enabled plugins
				if ($record->status==1) {
					//check to see if the plugin files exist
					$plugin_xml = JFUSION_PLUGIN_PATH .DIRECTORY_SEPARATOR. $record->name .DIRECTORY_SEPARATOR. 'jfusion.xml';
					if(!file_exists($plugin_xml)) {
						$record->status = 0;
						JFusionFunction::raiseWarning(JText::_('NO_FILES'), $record->name);
					} else {
						$record->status = 1;
					}

					if ($record->master == '1' || $record->slave == '1') {
						try {
							$JFusionPlugin->debugConfig();
						} catch (Exception $e) {
							JFusionFunction::raiseError($e->getMessage(), $record->name);
							$record->status = 0;
						}
					}
				}

				$record = $this->initRecord($record->name, $record);

				$plugins[]=$record;
			}
		}
		return $plugins;
	}

	/**
	 * @param array $plugins
	 *
	 * @return string
	 */
	function generateListHTML($plugins) {
		$row_count = 0;
		$html = '';
		foreach($plugins as $record) {
			$row = $this->generateRowHTML($record);

			$count = ($row_count % 2);
			$html .=<<<HTML
			<tr id="{$record->name}" class="row{$count}">
				{$row}
			</tr>
HTML;
			$row_count++;
		}
		return $html;
	}

    /**
     * @param $record
     * @return string
     */
    function generateRowHTML($record) {
	    $wizard = JText::_('WIZARD');
	    $edit = JText::_('EDIT');
	    $copy = JText::_('COPY');
	    $delete = JText::_('DELETE');
	    $info = JText::_('INFO');
	    $html =<<<HTML
    	<td width="20px;"><div class="dragHandles" id="dragHandles"><img src="components/com_jfusion/images/draggable.png" name="handle"></div></td>
        <td>{$record->name}</td>
		<td width="96px;">
		    <a href="{$record->wizardscript}" title="{$wizard}"><img src="{$record->wizardimage}" alt="{$wizard}" /></a>
			<a href="index.php?option=com_jfusion&task=plugineditor&jname={$record->name}" title="{$edit}"><img src="components/com_jfusion/images/edit.png" alt="{$edit}" /></a>
	        <a href="{$record->copyscript}" title="{$copy}"><img src="{$record->copyimage}" alt="{$copy}" /></a>
	        <a href="{$record->deletescript}" title="{$delete}"><img src="{$record->deleteimage}" alt="{$delete}" /></a>
			<a class="modal" title="{$info}"  href="index.php?option=com_jfusion&task=plugininfo&tmpl=component&jname={$record->name}" rel="{handler: 'iframe', size: {x: 375, y: 375}}"><img src="components/com_jfusion/images/info.png" alt="{$info}" /></a>
		</td>
        <td>{$record->description}</td>
        <td width="40px;" id="{$record->name}_master">
        	<a href="{$record->masterscript}"><img src="{$record->masterimage}" border="0" alt="{$record->masteralt}" /></a>
		</td>
        <td width="40px;" id="{$record->name}_slave">
        	<a href="{$record->slavescript}"><img src="{$record->slaveimage}" border="0" alt="{$record->slavealt}" /></a>
        </td>
        <td width="40px;" id="{$record->name}_check_encryption">
        	<a href="{$record->encryptscript}"><img src="{$record->encryptimage}" border="0" alt="{$record->encryptalt}" /></a>
        </td>
        <td width="40px;" id="{$record->name}_dual_login">
        	<a href="{$record->dualscript}"><img src="{$record->dualimage}" border="0" alt="{$record->dualalt}" /></a>
        </td>
		<td>
			<img src="{$record->statusimage}" border="0" alt="{$record->statusalt}" /><a href="index.php?option=com_jfusion&task=plugineditor&jname={$record->name}">{$record->statusalt}</a>
		</td>
       	<td>{$record->usercount}</td>
        <td>
        	<img src="{$record->registrationimage}" border="0" alt="{$record->registrationalt}" />{$record->registrationalt}
        </td>
		<td>{$record->usergrouptext}</td>
HTML;
	    return $html;
    }
}