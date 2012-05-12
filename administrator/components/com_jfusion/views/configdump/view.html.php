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

class jfusionViewconfigdump extends JView {

    function display($tpl = null)
    {
    	$db = JFactory::getDBO();
    	
    	$this->checkvalue=array();
    	// menuitem Checks
    	$this->checkvalue['menu_item']['jfusionplugin'] = 'is_string|not_empty';
    	$this->checkvalue['menu_item']['source_url'] = 'is_url';
    	$this->checkvalue['menu_item']['visual_integration'] = 'is_string';
    	$this->checkvalue['menu_item']['cookie_domain'] = 'is_string|is_cookie_domain';
    	$this->checkvalue['menu_item']['cookie_path'] = 'is_string';
    	$this->checkvalue['menu_item']['cookie_name'] = 'is_string';
    	
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
    	
    	$query = 'SELECT id,name,params,dual_login from #__jfusion WHERE status = 1';
    	$db->setQuery($query);
    	$rows = $db->loadObjectList();
    	
    	if(count($rows) ) {
    		foreach($rows as $row) {
    			$jPluginParam = new JParameter('');
    	
    			$new = new stdClass;
    			$new->dual_login = $row->dual_login;
    			$new->params = array();
    	
    			if ( $row->params ) $jPluginParam->loadArray(unserialize(base64_decode($row->params)));
    			$new->params = $jPluginParam->toObject();

    			$this->clearParameters($new,'jfusion_plugin');

    			$jfusion_plugin[$row->name] = $new;
    		}
    	}
	
    	$rows = array();
    	if ( JPluginHelper::isEnabled('search','jfusion') ) $rows[] = JPluginHelper::getPlugin('search','jfusion');
    	if ( JPluginHelper::isEnabled('content','jfusion') ) $rows[] = JPluginHelper::getPlugin('content','jfusion');

		foreach($rows as $row) {
			$new = new stdClass;
			$this->loadParams($new,$row);

			$this->clearParameters($new,'joomla_plugin',$row->type);
    		$this->addMissingParameters($new,'joomla_plugin',$row->type);
			
			$joomla_plugin[$row->type] = $new;
		}
		
        $rows = array();
        /*
    	if ( JModuleHelper::isEnabled('mod_jfusion_login') ) $rows[] = JModuleHelper::getModule('mod_jfusion_login');
    	if ( JModuleHelper::isEnabled('mod_jfusion_activity') ) $rows[] = JModuleHelper::getModule('mod_jfusion_activity');
    	if ( JModuleHelper::isEnabled('mod_jfusion_whosonline') ) $rows[] = JModuleHelper::getModule('mod_jfusion_whosonline');
    	*/

//		$query = "SELECT id,published,params,module from #__modules WHERE published = 1 AND module IN ('mod_jfusion_activity', 'mod_jfusion_whosonline', 'mod_jfusion_user_activity');";
		$query = "SELECT id,published,params,module from #__modules WHERE published = 1 AND module IN ('mod_jfusion_user_activity');";
		$db->setQuery($query);
		$rows = $db->loadObjectList();
    	
		foreach($rows as $row) {
			$new = new stdClass;
			$this->loadParams($new,$row);

			$this->clearParameters($new,'jfusion_module',$row->module);
    		$this->addMissingParameters($new,'jfusion_module',$row->module);

    		$name = !empty($row->title) ? $row->module.' '.$row->title : $row->module;
			$jfusion_module[$name] = $new;
		}

		$app		= JFactory::getApplication();	
		$menus		= $app->getMenu('site');
		$component	= JComponentHelper::getComponent('com_jfusion');
		$items		= $menus->getItems('component_id', $component->id);

		foreach($items as $row) {
			$jPluginParam = new JParameter('');
	
			$new = new stdClass;
			$this->loadParams($new , $row);
			
			$menu_item[$new->id] = $new;
		}

    	$this->assignRef('jfusion_plugin', $jfusion_plugin);    
    	$this->assignRef('jfusion_module', $jfusion_module);
    	$this->assignRef('joomla_plugin', $joomla_plugin);
    	$this->assignRef('menu_item', $menu_item);	
    	
        parent::display($tpl);
    }
    
    function jfusion_plugin($key,$value) {
    	return $this->check('jfusion_plugin',$key,$value);
    }
    
    function menu_item($key,$value) {
    	return $this->check('menu_item',$key,$value);
    }
    
    function joomla_plugin($key,$value,$name) {
    	return $this->check('joomla_plugin',$key,$value,$name);
    }
    
    function jfusion_module($key,$value,$name) {
    	return $this->check('jfusion_module',$key,$value,$name);
    }
    
    function loadParams(&$new,$row) {
    	$JParameter = new JParameter('');

		$new->params = new stdClass;
		
    	foreach($row as $key => $value) {
    		if ($key != 'params') {
				$new->$key = $value;
    		}
		}		

		$params = new JParameter($row->params, '');
		$params = $params->toObject();

		if (isset($params->JFusionPluginParam)) {
			$JParameter->loadArray(unserialize(base64_decode($params->JFusionPluginParam)));
			$JParameters = $JParameter->toObject();
			foreach($JParameters as $key => $value) {
				$new->params->$key = $value;
			}
			unset($params->JFusionPluginParam);
		}
		if (isset($params->JFusionPlugin)) {
			$JParameter->loadArray(unserialize(base64_decode($params->JFusionPlugin)));
			$JParameters = $JParameter->toObject();
			foreach($JParameters as $key => $value) {
				$new->params->$key = $value;
			}
			unset($params->JFusionPlugin);
		}

		if (is_object($params)) {
			foreach($params as $key => $value) {
				$new->params->$key = $value;
			}
		}
    }
    
    function clearParameters(&$new,$name,$type=null) {
// TODO: disabled for now, i want to at some point make this an option
    	return;
        foreach($new->params as $key => $value) {
    		if (is_array($value) || is_object($value)) {
    			foreach($value as $akey => $avalue) {
    				if ( !isset($this->checkvalue[$name]['*'][$akey])
    					&& !isset($this->checkvalue[$name][$type][$akey])
    					&& !isset($this->checkvalue[$name][$type]['*'][$akey]) ) {
    					unset($new->params->$key->$akey);
    				}
    			}
    		} else if ( !isset($this->checkvalue[$name]['*'][$key]) && !isset($this->checkvalue[$name][$type][$key]) ) {
    			unset($new->params->$key);
    		}
    	}
    }    
    
    function addMissingParameters(&$new,$name,$type) {
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
			foreach($new->params as $key => $value) {
				if (is_array($value) || is_object($value)) {
					foreach($this->checkvalue[$name][$type]['*'] as $key2 => $value2) {
						if (!isset($new->params->$key->$key2)) {
							$new->params->$key->$key2 = null;
						}
					}
				}
    		}
    	}
    }
    
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
    		$checks = split ( '\|' , $check );
    		
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
	    				$value = '************';
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
}