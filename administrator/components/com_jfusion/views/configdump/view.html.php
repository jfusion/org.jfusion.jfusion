<?php
/**
 * @package JFusion
 * @subpackage Views
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.view');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 * @package JFusion
 */

class jfusionViewconfigdump extends JViewLegacy {
	/**
	 * @var array
	 */
	var $checkvalue =array();

	/**
	 * @param null $tpl
	 * @return mixed|void
	 */
	function display($tpl = null)
	{
		//load debug library
		require_once(JPATH_ADMINISTRATOR .DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jfusion'.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'model.debug.php');
		
		$db = JFactory::getDBO();

		// menuitem Checks
		$this->checkvalue['menu_item']['*']['jfusionplugin'] = 'is_string|not_empty';
		$this->checkvalue['menu_item']['*']['source_url'] = 'is_url';
		$this->checkvalue['menu_item']['*']['visual_integration'] = 'is_string';
		$this->checkvalue['menu_item']['*']['cookie_domain'] = 'is_string|is_cookie_domain';
		$this->checkvalue['menu_item']['*']['cookie_path'] = 'is_string';
		$this->checkvalue['menu_item']['*']['cookie_name'] = 'is_string';

		// jfusion module Checks
		$this->checkvalue['jfusion_module']['mod_jfusion_user_activity']['jfusionplugin'] = 'is_string|not_empty';
		$this->checkvalue['jfusion_module']['mod_jfusion_user_activity']['itemid'] = 'is_string|not_empty';
		$this->checkvalue['jfusion_module']['mod_jfusion_activity']['jfusionplugin'] = 'is_string|not_empty';
		$this->checkvalue['jfusion_module']['mod_jfusion_activity']['itemid'] = 'is_string|not_empty';
		$this->checkvalue['jfusion_module']['mod_jfusion_whosonline']['*']['jfusionplugin'] = 'is_string';
		$this->checkvalue['jfusion_module']['mod_jfusion_whosonline']['*']['itemid'] = 'is_numeric';

		// joomla plugin Checks
		$this->checkvalue['joomla_plugin']['search']['*']['itemid'] = 'is_string|not_empty';
		$this->checkvalue['joomla_plugin']['search']['*']['title'] = 'is_string|empty';
		$this->checkvalue['joomla_plugin']['search']['*']['jfusionplugin'] = 'is_string|not_empty';

		$this->checkvalue['joomla_plugin']['content']['itemid'] = 'is_string|not_empty';
		$this->checkvalue['joomla_plugin']['content']['jname'] = 'is_string|not_empty';
		$this->checkvalue['joomla_plugin']['content']['default_forum'] = 'is_numeric';
		$this->checkvalue['joomla_plugin']['content']['default_userid'] = 'is_numeric';

		// jfusion plugin Checks
		$this->checkvalue['jfusion_plugin']['*']['source_url'] = 'is_url';
		$this->checkvalue['jfusion_plugin']['*']['source_path'] = 'is_string|is_dir|empty';
		$this->checkvalue['jfusion_plugin']['*']['database_type'] = 'is_string|not_empty';
		$this->checkvalue['jfusion_plugin']['*']['database_host'] = 'is_string|not_empty';
		$this->checkvalue['jfusion_plugin']['*']['database_name'] = 'is_string|not_empty';
		$this->checkvalue['jfusion_plugin']['*']['database_user'] = 'is_string|not_empty';
		$this->checkvalue['jfusion_plugin']['*']['database_password'] = 'is_string|not_empty|mask';
		$this->checkvalue['jfusion_plugin']['*']['database_prefix'] = 'is_string';

		$jfusion_plugin=array();
		$jfusion_module=array();
		$joomla_plugin=array();
		$menu_item=array();

		$query = $db->getQuery(true)
			->select('id, name, params, dual_login, original_name')
			->from('#__jfusion')
			->where('status = 1');

		$db->setQuery($query);
		$rows = $db->loadObjectList();

		if(count($rows) ) {
			foreach($rows as $row) {
				$jPluginParam = new JRegistry('');
				if ( $row->params ) $jPluginParam->loadArray(unserialize(base64_decode($row->params)));
				$row->params = $jPluginParam->toString();

				$new = $this->loadParams($row);

				$this->clearParameters($new,'jfusion_plugin');

				$jfusion_plugin[$row->name] = $new;
			}
		}

		$rows = array();
		if ( JPluginHelper::isEnabled('search','jfusion') ) $rows[] = JPluginHelper::getPlugin('search','jfusion');
		if ( JPluginHelper::isEnabled('content','jfusion') ) $rows[] = JPluginHelper::getPlugin('content','jfusion');

		foreach($rows as $row) {
			$new = $this->loadParams($row);

			$this->clearParameters($new,'joomla_plugin',$row->type);
			$this->addMissingParameters($new,'joomla_plugin',$row->type);

			$joomla_plugin[$row->type] = $new;
		}

		$rows = array();

		$query = $db->getQuery(true)
			->select('id, published, params ,module')
			->from('#__modules')
			->where('published = 1')
			->where('module IN (\'mod_jfusion_login\', \'mod_jfusion_activity\', \'mod_jfusion_whosonline\', \'mod_jfusion_user_activity\')');

		$db->setQuery($query);
		$rows = $db->loadObjectList();
		foreach($rows as $row) {
			$new = $this->loadParams($row);

			$this->clearParameters($new,'jfusion_module',$row->module);
			$this->addMissingParameters($new,'jfusion_module',$row->module);

			$name = !empty($row->title) ? $row->module.' '.$row->title : $row->module;
			$jfusion_module[$name] = $new;
		}

		$app		= JFactory::getApplication();
		$menus		= $app->getMenu('site');
		$component	= JComponentHelper::getComponent('com_jfusion');

		$items		= $menus->getItems('component_id', $component->id);

		if ($items && is_array($items)) {
			foreach($items as $row) {
				unset($row->note,$row->route,$row->level,$row->language,$row->browserNav,$row->access,$row->home,$row->img);
				unset($row->type,$row->template_style_id,$row->component_id,$row->parent_id,$row->component,$row->tree);

				$new = $this->loadParams($row);
				$this->clearParameters($new,'menu_item');

				$menu_item[$new->id] = $new;
			}
		}

		$this->getServerInfo();
		$this->getVersion();

		$this->jfusion_plugin = $jfusion_plugin;
		$this->jfusion_module = $jfusion_module;
		$this->joomla_plugin = $joomla_plugin;
		$this->menu_item = $menu_item;
		parent::display($tpl);
	}

	/**
	 * @param $key
	 * @param $value
	 * @return array
	 */
	function jfusion_plugin($key,$value) {
		return $this->check('jfusion_plugin',$key,$value);
	}

	/**
	 * @param $key
	 * @param $value
	 * @return array
	 */
	function menu_item($key,$value) {
		return $this->check('menu_item',$key,$value);
	}

	/**
	 * @param $key
	 * @param $value
	 * @param $name
	 * @return array
	 */
	function joomla_plugin($key,$value,$name) {
		return $this->check('joomla_plugin',$key,$value,$name);
	}

	/**
	 * @param $key
	 * @param $value
	 * @param $name
	 * @return array
	 */
	function jfusion_module($key,$value,$name) {
		return $this->check('jfusion_module',$key,$value,$name);
	}

	/**
	 * @param $row
	 * @return stdClass
	 */
	function loadParams($row) {
		$JParameter = new JRegistry('');
		$new = new stdClass;
		$new->params = new stdClass;
		foreach($row as $key => $value) {
			if ($key == 'params') {
				$params = new JRegistry($value);
				$params = $params->toObject();

				if (isset($params->JFusionPluginParam)) {
					$JParameter->loadArray(unserialize(base64_decode($params->JFusionPluginParam)));
					$JParameters = $JParameter->toObject();
					foreach($JParameters as $key2 => $value2) {
						$new->params->$key2 = $value2;
					}
					unset($params->JFusionPluginParam);
				}
				if (isset($params->JFusionPlugin)) {
					$JParameter->loadArray(unserialize(base64_decode($params->JFusionPlugin)));
					$JParameters = $JParameter->toObject();
					foreach($JParameters as $key2 => $value2) {
						$new->params->$key2 = $value2;
					}
					unset($params->JFusionPlugin);
				}
				if (is_object($params)) {
					foreach($params as $key2 => $value2) {
						$new->params->$key2 = $value2;
					}
				}
			} else {
				$new->$key = $value;
			}
		}
		return $new;
	}

	/**
	 * @param $new
	 * @param $name
	 * @param null $type
	 */
	function clearParameters(&$new,$name,$type=null) {
		if (JFactory::getApplication()->input->get('filter',false)) {
			foreach($new->params as $key => $value) {
				if ( !isset($this->checkvalue[$name]['*'][$key]) && !isset($this->checkvalue[$name][$type][$key]) ) {
					unset($new->params->$key);
				} else if (is_array($value) || is_object($value)) {
					foreach($value as $akey => $avalue) {
						if ( !isset($this->checkvalue[$name]['*'][$akey])
							&& !isset($this->checkvalue[$name][$type][$akey])
							&& !isset($this->checkvalue[$name][$type]['*'][$akey]) ) {
							unset($new->params->$key->$akey);
						}
					}
				}
			}
		}
	}

	/**
	 * @param $new
	 * @param $name
	 * @param null $type
	 */
	function addMissingParameters(&$new,$name,$type=null) {
		if (isset($this->checkvalue[$name]['*'])) {
			foreach($this->checkvalue[$name]['*'] as $key => $value) {
				if (!isset($new->params->$key)) {
					$new->params->$key = null;
				}
			}
		}
		if (isset($this->checkvalue[$name][$type])) {
			foreach($this->checkvalue[$name][$type] as $key => $value) {
				if (!isset($new->params->$key) && $key != '*') {
					$new->params->$key = null;
				}
			}
		}
		if (isset($this->checkvalue[$name][$type]['*'])) {
			foreach($new->params as $key => &$value) {
				if (is_array($value) || is_object($value)) {
					foreach($this->checkvalue[$name][$type]['*'] as $key2 => $value2) {
						if (!isset($value->$key2)) {
							if (is_array($value)) {
								$value[$key2] = null;
							} else {
								$value->$key2 = null;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param $type
	 * @param $key
	 * @param $value
	 * @param null $name
	 * @return array
	 */
	function check($type,$key,$value,$name=null) {
		$newStatus = new stdClass;
		$check = null;

		if ( $name != null && isset($this->checkvalue[$type][$name]['*'][$key]) ) {
			$check = $this->checkvalue[$type][$name]['*'][$key];
		} else if ( $name != null && isset($this->checkvalue[$type][$name][$key]) ) {
			$check = $this->checkvalue[$type][$name][$key];
		} else if ( isset($this->checkvalue[$type][$key]) ) {
			$check = $this->checkvalue[$type][$key];
		} else if ( isset($this->checkvalue[$type]['*'][$key]) ) {
			$check = $this->checkvalue[$type]['*'][$key];
		}

		if( $check ) {
			$checks = explode( '|' , $check );

			$valid = 0;
			foreach($checks as $check) {
				switch ( $check ) {
					case 'not_empty';
						if (empty($value) || $value === null) {
							$valid = 0;
						}
						break;
					case 'mask':
						$valid = 1;
						if (JFactory::getApplication()->input->get('mask',false)) {
							$value = '************';
						}
						break;
					case 'empty':
						if ( empty($value) ) $valid = 2;
						break;
					case 'is_string':
						if (is_string($value)) $valid = 1;
						break;
					case 'is_numeric':
						if (is_numeric($value)) $valid = 1;
						break;
					case 'is_url':
						if (preg_match("#^((((https?|ftps?|gopher|telnet|nntp)://)|(mailto:|news:))(%[0-9A-Fa-f]{2}|[-()_.!~*';/?:@&=+$,A-Za-z0-9])+)([).!';/?:,][[:blank:]])?$#i", $value, $matches))  $valid = 1;
						break;
					case 'is_cookie_domain':
						if (strlen($value)) {
							if (strpos($value, '.') == 0) {
								$valid = 1;
							} else {
								$valid = 2;
							}
						}
						break;
					case 'is_dir':
						if (strpos($value, '/') == 0) {
							if (is_dir($value)) {
								$valid = 1;
							} else {
								$valid = 0;
							}
						} else {
							$valid = 2;
						}
						break;
					default:
						if (!empty($value)) $valid = 1;
				}
			}
		} else {
			$valid = -1;
		}

		switch ($valid) {
			case 0:
				$result = array('background-color:#F5A9A9',$value);
				break;
			case 1:
				$result = array('background-color:#088A08',$value);
				break;
			case 2:
				$result = array('background-color:#FFFF00',$value);
				break;
			default:
				$result = array('',$value);
		}
		return $result;
	}

	function getServerInfo()
	{
		//get server specs
		$version = new JVersion;
		//put the relevant specs into an array
		$server_info = array();
		$server_info['Joomla Version'] = $version->getShortVersion();
		$server_info['PHP Version'] = phpversion();
		$db = JFactory::getDBO();
		$mysql_version = $db->getVersion();
		$server_info['MySQL Version'] = $mysql_version;

		$disabled = ini_get('disable_functions');
		if ($disabled) {
			$disabled = explode(',', $disabled);
			$disabled = array_map('trim', $disabled);
		} else {
			$disabled = array();
		}
		if (!in_array('php_uname', $disabled)) {
			$server_info['System Information'] = php_uname();
		} else {
			$server_info['System Information'] = JText::_('UNKNOWN');
		}

		$server_info['Browser Information'] = $_SERVER['HTTP_USER_AGENT'];
		//display active plugins
		$query = $db->getQuery(true)
			->select('folder, element, enabled as published')
			->from('#__extensions')
			->where('(folder = \'authentication\' OR folder = \'user\')')
			->where('(element =\'jfusion\' OR enabled = 1)');

		$db->setQuery($query);
		$system_plugins = $db->loadObjectList();
		foreach ($system_plugins as $system_plugin) {
			if ($system_plugin->published == 1) {
				$server_info[$system_plugin->element . ' ' . $system_plugin->folder . ' Plugin'] = JText::_('ENABLED');
			} else {
				$server_info[$system_plugin->element . ' ' . $system_plugin->folder . ' Plugin'] = JText::_('DISABLED');
			}
		}
		$this->server_info = $server_info;
	}

	function getVersion()
	{
		$jfusion_version = array();
		$this->getVersionNumber(JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('COMPONENT'), $jfusion_version);
		$this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_activity' . DIRECTORY_SEPARATOR . 'mod_jfusion_activity.xml', JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), $jfusion_version);
		$this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_login' . DIRECTORY_SEPARATOR . 'mod_jfusion_login.xml', JText::_('LOGIN') . ' ' . JText::_('MODULE'), $jfusion_version);

		$this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR . 'jfusion' . DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'), $jfusion_version);
		$this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'jfusion' . DIRECTORY_SEPARATOR .'jfusion.xml', JText::_('USER') . ' ' . JText::_('PLUGIN'), $jfusion_version);
		$this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'jfusion' . DIRECTORY_SEPARATOR .'jfusion.xml', JText::_('SEARCH') . ' ' . JText::_('PLUGIN'), $jfusion_version);
		$this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'jfusion' . DIRECTORY_SEPARATOR .'jfusion.xml', JText::_('DISCUSSION') . ' ' . JText::_('PLUGIN'), $jfusion_version);
		$this->jfusion_version = $jfusion_version;
	}

	/**
	 * retrieves version numbers
	 *
	 * @param string $filename         filename
	 * @param string $name             name
	 * @param string &$jfusion_version version number of the current jfusion
	 *
	 * @return void
	 */
	function getVersionNumber($filename, $name, &$jfusion_version)
	{
		if (file_exists($filename)) {
			//get the version number
			$xml = JFusionFunction::getXml($filename);

			$jfusion_version[JText::_('JFUSION') . ' ' . $name . ' ' . JText::_('VERSION') ] = ' ' . (string)$xml->version . ' ';
			$revision = $xml->revision;
			if (!empty($revision)) {
				$jfusion_version[JText::_('JFUSION') . ' ' . $name . ' ' . JText::_('VERSION') ].= '(Rev '.(string)$revision.') ';
			}
			unset($parser);
		}
	}
}