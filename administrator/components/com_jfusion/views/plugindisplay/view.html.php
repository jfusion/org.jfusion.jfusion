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
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');

require_once JPATH_COMPONENT_ADMINISTRATOR . '/defines.php';
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
	 * @var $plugins array
	 */
	var $plugins;

	/**
	 * @var $VersionData array
	 */
	var $VersionData;

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
	        $url = 'http://update.jfusion.org/jfusion/joomla/?version=' . $jversion->getShortVersion();

	        $VersionData = null;
	        try {
	            $VersionDataRaw = JFusionFunction::getFileData($url);
		        $xml = \JFusion\Framework::getXml($VersionDataRaw, false);

		        if ($xml) {
			        if ($xml->plugins) {
				        $VersionData = $xml->plugins->children();
			        }
			        unset($parser);
		        }
	        } catch (Exception $e) {
	        }

            //pass the data onto the view
	        $this->plugins = $plugins;
	        $this->VersionData = $VersionData;

	        $document = JFactory::getDocument();
	        $document->addScript('components/com_jfusion/js/File.Upload.js');
	        $document->addScript('components/com_jfusion/views/' . $this->getName() . '/tmpl/default.js');

	        JFusionFunction::loadJavascriptLanguage(array('COPY_MESSAGE', 'DELETE', 'PLUGIN', 'COPY'));

	        parent::display();
        } else {
            \JFusion\Framework::raise(LogLevel::WARNING, JText::_('NO_JFUSION_TABLE'));
        }
    }

	/**
	 * @param $jname
	 * @param null|stdClass $record
	 *
	 * @return null|\stdClass
	 */
	private function initRecord($jname, $record = null) {
		$db = Factory::getDBO();
		if (!$record) {
			$query = $db->getQuery(true)
				->select('*')
				->from('#__jfusion')
				->where('name = ' . $db->quote($jname));

			$db->setQuery($query);
			$record = $db->loadObject();
		}
		try {
			$Admin = Factory::getAdmin($record->name);
			$Param = Factory::getParams($record->name);

			if($record->status >= 1) {
				//added check for database configuration to prevent error after moving sites

				$status = 0;
				try {
					if ($Admin->checkConfig()) {
						if ($record->status == 2) {
							$status = 2;
						} else {
							$status = 1;
						}
					}
				} catch (Exception $e) {}

				//do a check to see if the status field is correct
				if ($status != $record->status) {
					//update the status and deactivate the plugin
					$Admin->updateStatus($status);
					$record->status = $status;
				}
			}

			//set copy options
			if (!$Admin->multiInstance() || $record->original_name) {
				//cannot copy joomla_int
				$record->copyclass = 'copy_icon dim';
				$record->copyscript =  'javascript:void(0)';
			} else {
				$record->copyclass = 'copy_icon';
				$record->copyscript =  'javascript: JFusion.copyPlugin(\'' . $record->name . '\');';
			}

			//set uninstall options
			$query = $db->getQuery(true)
				->select('count(*)')
				->from('#__jfusion')
				->where('original_name = ' . $db->quote($record->name));

			$db->setQuery($query);
			$copys = $db->loadResult();
			if ($copys) {
				//cannot uninstall joomla_int
				$record->deleteclass = 'delete_icon dim';
				$record->deletescript =  'javascript:void(0)';
			} else {
				$record->deleteclass = 'delete_icon';
				$record->deletescript =  'javascript: JFusion.deletePlugin(\'' . $record->name . '\');';
			}

			//set wizard options
			$record->wizard = Framework::hasFeature($record->name, 'wizard');
			if($record->wizard) {
				$record->wizardclass = 'wizard_icon';
				$record->wizardscript =  'index.php?option=com_jfusion&task=wizard&jname=' . $record->name;
			} else {
				$record->wizardclass = 'wizard_icon dim';
				$record->wizardscript = 'javascript:void(0)';
			}

			//set check encryption options
			if($record->status < 1) {
				$record->encryptclass = 'disabled dim';
				$record->encryptscript = 'javascript:void(0)';
				$record->encryptmessage = Text::_('UNAVAILABLE');
			} elseif ($record->check_encryption == 1) {
				$record->encryptclass = 'enabled';
				$record->encryptscript = 'javascript: JFusion.toggleSetting(\'check_encryption\', \'' . $record->name . '\');';
				$record->encryptmessage = Text::_('ENABLED');
			} else {
				$record->encryptclass = 'disabled';
				$record->encryptscript = 'javascript: JFusion.toggleSetting(\'check_encryption\', \'' . $record->name . '\');';
				$record->encryptmessage = Text::_('DISABLED');
			}

			//set dual login options
			if($record->status < 1) {
				$record->dualclass = 'disabled dim';
				$record->dualscript = 'javascript:void(0)';
				$record->dualmessage = Text::_('UNAVAILABLE');
			} elseif ($record->dual_login == 1) {
				$record->dualclass = 'enabled';
				$record->dualscript = 'javascript: JFusion.toggleSetting(\'dual_login\', \'' . $record->name . '\');';
				$record->dualmessage = Text::_('ENABLED');
			} else {
				$record->dualclass = 'disabled';
				$record->dualscript = 'javascript: JFusion.toggleSetting(\'dual_login\', \'' . $record->name . '\');';
				$record->dualmessage = Text::_('DISABLED');
			}

			//display status
			if ($record->status < 1) {
				$record->statusclass = 'disabled dim';
				if ($record->wizard) {
					$record->statusscript =  'index.php?option=com_jfusion&task=wizard&jname=' . $record->name;
				} else {
					$record->statusscript =  'index.php?option=com_jfusion&task=plugineditor&jname=' . $record->name;
				}
				$record->statusmessage = Text::_('NO_CONFIG');
			} else if ($record->status == 1) {
				$record->statusclass = 'disabled';
				$record->statusscript = 'javascript: JFusion.toggleSetting(\'status\', \'' . $record->name . '\');';
				$record->statusmessage = Text::_('DISABLED');
			} else {
				$record->statusclass = 'enabled';
				$record->statusscript = 'javascript: JFusion.toggleSetting(\'status\', \'' . $record->name . '\');';
				$record->statusmessage = Text::_('ENABLED');
			}

			//see if a plugin has copies
			$query = $db->getQuery(true)
				->select('*')
				->from('#__jfusion')
				->where('original_name = ' . $db->quote($record->name));

			$db->setQuery($query);
			$record->copies = $db->loadObjectList('name');

			//get the description
			$record->description = $Param->get('description');
			if(empty($record->description)) {
				//get the default description
				$plugin_xml = Framework::getPluginPath($record->name) . DIRECTORY_SEPARATOR . 'jfusion.xml';
				if(file_exists($plugin_xml) && is_readable($plugin_xml)) {
					$xml = Framework::getXml($plugin_xml);
					$description = $xml->description;
					if(!empty($description)) {
						$record->description = (string)$description;
					}
				}
			}

			if ($record->status < 1) {
				$record->usercount = '';
				$record->usermessage = '';
			} else {
				$record->usercount = $Admin->getUserCount();
				$record->usermessage = Text::_('USERS');
			}

			//get the registration status
			if ($record->status < 1) {
				$record->registrationclass = '';
				$record->registrationmessage = '';
			} else {
				try {
					$record->registration = $Admin->allowRegistration();
				} catch (Exception $e) {
					Framework::raise(LogLevel::ERROR, $e, $Admin->getJname());
					$record->registration = false;
				}

				if (!empty($record->registration)) {
					$record->registrationclass = 'enabled';
					$record->registrationmessage = Text::_('ENABLED');
				} else {
					$record->registrationclass = 'disabled';
					$record->registrationmessage = Text::_('DISABLED');
				}
			}

			if($record->status >= 1) {
				try {
					$usergroup = $Admin->getDefaultUsergroup();
				} catch (Exception $e) {
					Framework::raise(LogLevel::ERROR, $e, $Admin->getJname());
					$usergroup = null;
				}

				if ($usergroup) {
					if (is_array($usergroup)) {
						$usergroup = join(', ', $usergroup);
					}
					$record->usergrouptext = '<div class="smallicon enabled" title="' . Text::_('ENABLED') . '"></div>' . $usergroup;
				} else {
					$record->usergrouptext = '<div class="smallicon disabled" title="' . Text::_('DISABLED') . '"></div>' . Text::_('MISSING') . ' ' . Text::_('DEFAULT_USERGROUP') ;
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ' . Text::_('DEFAULT_USERGROUP'), $record->name);
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

		$query = $db->getQuery(true)
			->select('*')
			->from('#__jfusion')
			->where('ordering = ' . $db->quote(''), 'OR')
			->where('ordering IS NULL');

		$db->setQuery($query);
		$ordering = $db->loadObjectList();
		if(!empty($ordering)){
			//set a new order
			$query = $db->getQuery(true)
				->select('*')
				->from('#__jfusion')
				->order('ordering ASC');
			$db->setQuery($query);
			$rows = $db->loadObjectList();
			$ordering = 1;
			foreach ($rows as $row){
				$query = $db->getQuery(true)
					->update('#__jfusion')
					->set('ordering = ' . $ordering)
					->where('name = ' . $db->quote($row->name));

				$db->setQuery($query);
				$db->execute();
				$ordering++;
			}
		}

		//get the data about the JFusion plugins
		$query = $db->getQuery(true)
			->select('*')
			->from('#__jfusion')
			->order('ordering ASC');

		$db->setQuery($query);
		$rows = $db->loadObjectList();
		$plugins = array();

		if ($rows) {
			//we found plugins now prepare the data
			foreach($rows as $record) {
				$JFusionPlugin = \JFusion\Factory::getAdmin($record->name);

				//output detailed configuration warnings for enabled plugins
				if ($record->status == 1) {
					//check to see if the plugin files exist

					$plugin_xml = JFUSION_PLUGIN_PATH . '/' . $JFusionPlugin->getName() . '/jfusion.xml';
					if(!file_exists($plugin_xml)) {
						$record->status = 0;
						\JFusion\Framework::raise(LogLevel::WARNING, JText::_('NO_FILES'), $record->name);
					} else {
						$record->status = 1;
					}

					if ($record->status == 2) {
						try {
							$JFusionPlugin->debugConfig();
						} catch (Exception $e) {
							\JFusion\Framework::raise(LogLevel::ERROR, $e, $record->name);
							$record->status = 0;
						}
					}
				}

				$record = $this->initRecord($record->name, $record);

				$plugins[] = $record;
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
	/**
	 * @param $record
	 * @return string
	 */
	private function generateRowHTML($record) {
		$wizard = Text::_('WIZARD');
		$edit = Text::_('EDIT');
		$copy = Text::_('COPY');
		$delete = Text::_('DELETE');
		$info = Text::_('INFO');
		$close = Text::_('CLOSE');

		$infodata = $this->getInfo($record->name);

		$html =<<<HTML
    	<td class="dragHandles" style="vertical-align: middle;">
    		<div class="smallicon"></div>
    	</td>

        <td style="min-width: 125px;">
			<div>
	        	{$record->name}
	        </div>
	        <div>
			    <a href="{$record->wizardscript}" data-toggle="tooltip" data-container="body" data-placement="top" title="{$wizard}"><div class="smallicon {$record->wizardclass}"></div></a>
				<a href="'index.php?option=com_jfusion&task=plugineditor&jname=' . $record->name" data-toggle="tooltip" data-container="body" data-placement="top" title="{$edit}"><div class="smallicon edit_icon"></div></a>
		        <a href="{$record->copyscript}" data-toggle="tooltip" data-container="body" data-placement="top" title="{$copy}"><div class="smallicon {$record->copyclass}"></div></a>
		        <a href="{$record->deletescript}" data-toggle="tooltip" data-container="body" data-placement="top" title="{$delete}"><div class="smallicon {$record->deleteclass}"></div></a>
				<a data-toggle="modal" data-target="#modal{$record->name}" href="#"><div class="smallicon info_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="{$info}"></div></a>
			</div>
        	<div class="overflowbox">
        		{$record->description}
			</div>
			<div class="modal fade" id="modal{$record->name}">
			  <div class="modal-dialog">
			    <div class="modal-content">
			      <div class="modal-header">
			        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{$close}</span></button>
			        <h4 class="modal-title">{$record->name} {$info}</h4>
			      </div>
			      <div class="modal-body">
			    	{$infodata}
			      </div>
			      <div class="modal-footer">
			        <button type="button" class="btn btn-default" data-dismiss="modal">{$close}</button>
			      </div>
			    </div>
			  </div>
			</div>
        </td>
		<td class="configicon" id="{$record->name}_status">
			<a href="{$record->statusscript}" title="{$record->statusmessage}"><div class="smallicon {$record->statusclass}"></div></a>
		</td>
        <td class="configicon" id="{$record->name}_check_encryption">
        	<a href="{$record->encryptscript}" title="{$record->encryptmessage}"><div class="smallicon {$record->encryptclass}"></div></a>
        </td>
        <td class="configicon" id="{$record->name}_dual_login">
        	<a href="{$record->dualscript}" title="{$record->dualmessage}"><div class="smallicon {$record->dualclass}"></div></a>
        </td>
        <td class="configicon">
        	<div class="smallicon {$record->registrationclass}" title="{$record->registrationmessage}"></div>
        </td>
       	<td class="configicon">
       		<div title="{$record->usermessage}">{$record->usercount}</div>
       	</td>
		<td>
			{$record->usergrouptext}
		</td>
HTML;
		return $html;
	}

	/**
	 * @param $name
	 *
	 * @return string
	 */
	private function getInfo($name) {
		$admin = Factory::getAdmin($name);

		$features = array();

		$features['ADMIN']['FEATURE_WIZARD'] = $this->outputFeature(Framework::hasFeature($name, 'wizard'));
		$features['ADMIN']['FEATURE_REQUIRE_FILE_ACCESS'] = $this->outputFeature($admin->requireFileAccess());
		$features['ADMIN']['FEATURE_MULTI_USERGROUP'] = $this->outputFeature($admin->isMultiGroup());
		$features['ADMIN']['FEATURE_MULTI_INSTANCE'] = $this->outputFeature($admin->multiInstance());

		$features['USER']['FEATURE_DUAL_LOGIN'] = $this->outputFeature(Framework::hasFeature($name, 'duallogin'));
		$features['USER']['FEATURE_DUAL_LOGOUT'] = $this->outputFeature(Framework::hasFeature($name, 'duallogout'));
		$features['USER']['FEATURE_UPDATE_PASSWORD'] = $this->outputFeature(Framework::hasFeature($name, 'updatepassword'));
		$features['USER']['FEATURE_UPDATE_USERNAME'] = $this->outputFeature(Framework::hasFeature($name, 'updateusername'));
		$features['USER']['FEATURE_UPDATE_EMAIL'] = $this->outputFeature(Framework::hasFeature($name, 'updateemail'));
		$features['USER']['FEATURE_UPDATE_USERGROUP'] = $this->outputFeature(Framework::hasFeature($name, 'updateusergroup'));
		$features['USER']['FEATURE_UPDATE_LANGUAGE'] = $this->outputFeature(Framework::hasFeature($name, 'updateuserlanguage'));
		$features['USER']['FEATURE_SESSION_SYNC'] = $this->outputFeature(Framework::hasFeature($name, 'syncsessions'));
		$features['USER']['FEATURE_BLOCK_USER'] = $this->outputFeature(Framework::hasFeature($name, 'blockuser'));
		$features['USER']['FEATURE_ACTIVATE_USER'] = $this->outputFeature(Framework::hasFeature($name, 'activateuser'));
		$features['USER']['FEATURE_DELETE_USER'] = $this->outputFeature(Framework::hasFeature($name, 'deleteuser'));

		$html = '<table>';

		foreach ($features as $cname => $category) {
			foreach ($category as $name => $value) {
				$name = Text::_($name);
				$html .=<<<HTML
				<tr>
					<td width="160px">
						{$name}
					</td>
					<td>
						{$value}
					</td>
				</tr>
HTML;
			}
		}

		$html .= '</table>';
		return $html;
	}

	/**
	 * @param $feature
	 * @return string
	 */
	private function outputFeature($feature) {
		if ($feature === true) {
			$feature = 'JYES';
		} else if ($feature === false) {
			$feature = 'JNO';
		}
		switch ($feature) {
			case 'JNO':
				$class = 'disabled';
				break;
			case 'JYES':
				$class = 'enabled';
				break;
			default:
				$class = 'documentation_icon';
				break;
		}
		return '<div class="smallicon ' . $class . '"></div> ' . Text::_($feature);
	}
}