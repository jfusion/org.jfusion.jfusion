<?php

/**
 * @package JFusion_universal
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Admin Class for universal
 * For detailed descriptions on these functions please check JFusionAdmin
 * @package JFusion_universal
 */
class JFusionAdmin_universal extends JFusionAdmin
{
	/**
	 * @var $helper JFusionHelper_universal
	 */
	var $helper;

	/**
	 * @return string
	 */
	function getJname()
	{
		return 'universal';
	}

	/**
	 * @return string
	 */
	function getTablename()
	{
		return $this->helper->getTable();
	}

	/**
	 * @return array
	 */
	function getUsergroupList()
	{
		$usergroupmap = $this->params->get('usergroupmap', false);
		$usergrouplist = array();
		if(is_object($usergroupmap) && isset($usergroupmap->name)) {
			foreach ($usergroupmap->name as $key => $value) {
				if ($value && isset($usergroupmap->value[$key])) {
					//append the default usergroup
					$default_group = new stdClass;
					$value = html_entity_decode($value);
					$default_group->id = base64_encode($usergroupmap->value[$key]);
					$default_group->name = $value;
					$usergrouplist[] = $default_group;
				}
			}
		}
		return $usergrouplist;
	}


	/**
	 * Returns the a list of users of the integrated software
	 *
	 * @param int $limitstart start at
	 * @param int $limit number of results
	 *
	 * @return array
	 */
	function getUserList($limitstart = 0, $limit = 0)
	{
		try {
			$f = array('USERNAME', 'EMAIL');
			$field = $this->helper->getQuery($f);

			// initialise some objects
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select($field)
				->from('#__' . $this->getTablename());

			$db->setQuery($query, $limitstart, $limit);
			$userlist = $db->loadObjectList();
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			$userlist = array();
		}
		return $userlist;
	}

	/**
	 * @return int
	 */
	function getUserCount()
	{
		try {
			//getting the connection to the db
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('count(*)')
				->from('#__' . $this->getTablename());

			$db->setQuery($query);

			//getting the results
			return $db->loadResult();
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			return 0;
		}
	}

	/**
	 * @return bool
	 */
	function allowRegistration()
	{
		return true;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return string
	 */
	function mapuser($name, $value, $node, $control_name)
	{
		$value = $this->helper->getMapRaw('user');

		return $this->map('map', $value, $node, $control_name, 'user');
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return string
	 */
	function user_auth($name, $value, $node, $control_name)
	{
		$output = '<textarea name="' . $control_name . '[' . $name . ']" rows="20" cols="55">' . $value . '</textarea>';
		return $output;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return string
	 */
	function mapgroup($name, $value, $node, $control_name)
	{
		$value = $this->helper->getMapRaw('group');
		return $this->map('map', $value, $node, $control_name, 'group');
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @param $type
	 *
	 * @return string
	 */
	function map($name, $value, $node, $control_name, $type)
	{
		$output = '';
		try {
			$jname = $this->getJname();

			$database_name = $this->params->get('database_name');
			$database_prefix = $this->params->get('database_prefix');

			try {
				$db = JFusionFactory::getDatabase($jname);
			} catch (Exception $e) {
				throw new Exception(JText::_('SAVE_CONFIG_FIRST'));
			}

			$tabelslist = $db->getTableList();
			if ($tabelslist) {
				$tl = array();
				$fl = array();

				$fieldtypes = $this->helper->getField();

				$table = new stdClass;
				$table->id = null;
				$table->name = JText::_('UNSET');
				$tl[] = $table;

				$firstTable = null;
				foreach ($tabelslist as $val) {
					if(strpos($val, $database_prefix) === 0 || $database_prefix == '') {
						$table = new stdClass;

						$table->name = $table->id = substr($val, strlen($database_prefix));

						$query = 'SHOW COLUMNS FROM ' . $val;
						$db->setQuery($query);
						$fieldslist = $db->loadObjectList();

						if (!$firstTable) $firstTable = $table->id;
						$fl[$table->id] = $fieldslist;
						$tl[] = $table;
					}
				}

				$mapuser = array();
				if ($value->table) {
					$mapuser = $fl[$value->table];
				} else {
					if ($firstTable) $mapuser = $fl[$firstTable];
				}

				$onchange = 'onchange="javascript: groupchange(this)"';

				$output .= '<table>';
				$output .= '<tr><td>';
				$output .= JHTML::_('select.genericlist', $tl, $control_name . '[' . $name . '][' . $type . '][table]', 'onchange="javascript: Joomla.submitbutton(\'applyconfig\')"', 'id', 'name', $value->table);
				$output .= '</td></tr>';
				$output .= '<tr><td>';
				if (!empty($value->table) ) {
					$output .= '<table>';
					foreach ($mapuser as $val) {
						$output .= '<tr><td>';
						//object(stdClass)#245 (6) { ["Field"]=>  string(2) "id" ["Type"]=>  string(6) "int(5)" ["Null"]=>  string(0) "" ["Key"]=>  string(0) "" ["Default"]=>  string(1) "0" ["Extra"]=>  string(0) "" }
						$output .= '<div>Name: ' . $val->Field . '</div>';
						$output .= '<div>Type: ' . $val->Type . '</div>';
						$output .= '<div>Default: "' . $val->Default . '" </div>';
						$null = $val->Null ? JText::_('YES') : JText::_('NO');
						$output .= '<div>Null: ' . $null . '</div>';
						$output .= '<div>Extra: "' . $val->Extra . '" </div></td><td>';
						if ( isset($value->field->{$val->Field}) ) {
							$mapuserfield = $value->field->{$val->Field};
						} else {
							$mapuserfield = '';
						}
						if ( isset($value->type->{$val->Field}) ) {
							$fieldstype = $value->type->{$val->Field};
						} else {
							$fieldstype = '';
						}
						$fieldsvalueobject = new stdClass();
						$fieldsvalue = '';
						if ( isset($value->value->{$val->Field}) ) {
							$fieldsvalue = $value->value->{$val->Field};
							if (is_object($fieldsvalue)) {
								$fieldsvalueobject = $fieldsvalue;
							} else {
								$fieldsvalue = htmlentities($fieldsvalue);
							}
						}

						$onchange = 'size="8" multiple onchange="javascript: JFusion.Plugin.changeField(this,\'' . $val->Field . '\',\'' . $type . '\')"';
						$output .= '<table>';
						$output .= '<tr>';
						$output .= '<td>';
						$output .= JHTML::_('select.genericlist', $fieldtypes, $control_name . '[' . $name . '][' . $type . '][field][' . $val->Field . '][]', $onchange, 'id', 'name', $mapuserfield);
						$output .= '</td>';
						$output .= '<td>';
						$onchange = 'onchange="javascript: JFusion.Plugin.changeValue(this,\'' . $val->Field . '\',\'' . $type . '\')"';
						$output .= '<div id="' . $type . $val->Field . '">';

						if (isset($mapuserfield[0])) {
							if (isset($fieldtypes[$mapuserfield[0]])) {
								if (isset($fieldtypes[$mapuserfield[0]]->types)) {
									$output .= JHTML::_('select.genericlist', $fieldtypes[$mapuserfield[0]]->types, $control_name . '[' . $name . '][' . $type . '][type][' . $val->Field . ']', $onchange, 'id', 'name', $fieldstype);
								}
							}
						}
						$output .= '</div>';
						$output .= '</td>';
						$output .= '<td>';
						$output .= '<div id="' . $type . $val->Field . 'value">';
						switch ($fieldstype) {
							case 'CUSTOM':
								$output .= '<textarea id="' . $control_name . $name . $type . 'value' . $val->Field . '" name="' . $control_name . '[' . $name . '][' . $type . '][value][' . $val->Field . ']" rows="8" cols="55">' . htmlentities($fieldsvalue) . '</textarea>';
								break;
							case 'DEFAULT':
							case 'VALUE':
							case 'DATE':
								$output .= '<input type="text" id="' . $control_name . $name . $type . 'value' . $val->Field . '" name="' . $control_name . '[' . $name . '][' . $type . '][value][' . $val->Field . ']" value="' . $fieldsvalue . '" size="100" class="inputbox" />';
								break;
							case 'ONOFF':
								foreach ($fieldsvalueobject as $key2 => $val2) {
									$output .= '<input type="text" id="' . $control_name . $name . $type . 'value' . $val->Field . $key2 . '" name="' . $control_name . '[' . $name . '][' . $type . '][value][' . $val->Field . '][' . $key2 . ']" value="' . htmlentities($val2) . '" size="40" class="inputbox" />';
								}
								break;
						}
						$output .= '</div>';
						$output .= '</td>';
						$output .= '</tr>';
						$output .= '</table>';
						$output .= '</td></tr>';
					}
					$output .= '</table>';
				}
				$output .= '</td></tr>';
				$output .= '</table>';
			} else {
				throw new Exception(JText::_('SAVE_CONFIG_FIRST'));
			}
		} catch (Exception $e) {
			$output = $e->getMessage();
		}
		return $output;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return string
	 */
	function js($name, $value, $node, $control_name) {
		$document = JFusionFactory::getDocument();

		$list = $this->helper->getField();

		$list = json_encode($list);

		$output = <<<JS
        JFusion.Plugin.TypeAry = {$list};

		JFusion.Plugin.disableOptions = function(elements, disable) {
			elements.each(function(element) {
				var options = element.getElements('option');
				options.each(function(option) {
				    if (option.get('value') == disable && !option.selected) {
				        option.disabled = true;
				    }
				});
			});
		};

		JFusion.Plugin.update = function() {
			JFusion.Plugin.updateOptions('user');
			JFusion.Plugin.updateOptions('group');
		};

        JFusion.Plugin.updateOptions = function(type) {
			var elements = document.getElements('select[id^=paramsmap'+type+'field]');
			elements.each(function(element) {
				var options = element.getElements('option');
				options.each(function(option) {
				    if (option.disabled) {
				        option.disabled = false;
				    }
				});
			});
			elements.each(function(element) {
				var options = element.getElements('option');
				options.each(function(option) {
					if (option.selected) {
						var value = option.get('value');
						if (value != 'DEFAULT') {
							switch (value) {
								case 'REALNAME':
									JFusion.Plugin.disableOptions(elements, 'LASTNAME');
									JFusion.Plugin.disableOptions(elements, 'FIRSTNAME');
									break;
								case 'LASTNAME':
								case 'FIRSTNAME':
									JFusion.Plugin.disableOptions(elements, 'REALNAME');
									break;
								default:
							}
							JFusion.Plugin.disableOptions(elements, value);
						}
					}
				});
			});
        };

        JFusion.Plugin.changeField = function(ref, name, parmtype) {
        	var options = ref.getElements('option');
        	options.each(function(option) {
        		var value = option.get('value');
				if (option.selected && value) {
					if (JFusion.Plugin.TypeAry[value].types !== undefined) {
						options.each(function(option) {
							option.selected = false;
						});
						option.selected = true;
					}
				}
			});

            var id = $(parmtype+name);
            id.empty();

            $(parmtype+name+'value').empty();

			JFusion.Plugin.update();
			var value = ref.get('value');
            if ( value && JFusion.Plugin.TypeAry[value].types !== undefined ) {
            	var valueid = 'paramsmap'+parmtype+'type'+name;
	            var select = new Element('select', {
					'type': 'option',
					'id': valueid,
					'name': 'params[map][user][type]['+name+']',
					'events': {
			            'change': function () {
			                JFusion.Plugin.changeValue(this, name, parmtype);
			            }
			        }
	            });

				Array.each(JFusion.Plugin.TypeAry[value].types, function(type) {
					select.appendChild(new Element('option', {
						'html' : type.name,
						'value' : type.id
					}));
				});
				/*
                type.options.length = 0;
                for (var i=0; i<JFusion.Plugin.TypeAry[value].types.length; i++) {
                    type.options[type.options.length] = new Option(JFusion.Plugin.TypeAry[value].types[i].name, JFusion.Plugin.TypeAry[value].types[i].id);
                }
                */
                id.appendChild(select);
            }
        };

        JFusion.Plugin.changeValue = function(ref, name, parmtype) {
            var id = $(parmtype+name+'value');

			var paramsmap = $("paramsmap"+parmtype+"value"+name);
            if ( paramsmap ) {
                paramsmap.dispose();
            }

            var paramsmapon = $("paramsmap"+parmtype+"value"+name+"on");
            if (paramsmapon) {
                paramsmapon.dispose();
            }

            var paramsmapoff = $("paramsmap"+parmtype+"value"+name+"off");
            if (paramsmapoff) {
                paramsmapoff.dispose();
            }

            var value = ref.get('value');
            if(value == 'CUSTOM') {
                id.appendChild(new Element('textarea', {
					'id': 'paramsmap'+parmtype+'value'+name,
					'name': 'params[map]['+parmtype+'][value]['+name+']',
					'rows': 8,
					'cols': 55
            	}));
            } else if(value == 'DATE' || value == 'VALUE') {
                id.appendChild(new Element('input', {
					'type': 'text',
					'id': 'paramsmap'+parmtype+'value'+name,
					'name': 'params[map]['+parmtype+'][value]['+name+']',
					'size': 100,
					'value': value == 'DATE' ? 'Y-m-d H:i:s' : ''
            	}));
            } else if ( value == 'ONOFF') {
                id.appendChild(new Element('input', {
					'type': 'text',
					'id': 'paramsmap'+parmtype+'value'+name+'on',
					'name': 'params[map]['+parmtype+'][value]['+name+'][on]',
					'size': 40
            	}));
                id.appendChild(new Element('input', {
					'type': 'text',
					'id': 'paramsmap'+parmtype+'value'+name+'off',
					'name': 'params[map]['+parmtype+'][value]['+name+'][off]',
					'size': 40
            	}));
            }
        };

        window.addEvent('domready',function() {
			JFusion.Plugin.update();
        });
JS;
		$document->addScriptDeclaration($output);
		return '';
	}

	/**
	 * @param string $url
	 * @param int $itemid
	 *
	 * @return string
	 */
	function generateRedirectCode($url, $itemid)
	{
		$universal_url = $this->params->get('source_url');

		//create the new redirection code

		$redirect_code = '//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \'' . $url . '\';
$universal_url \'' . $universal_url . '\';
$joomla_itemid = ' . $itemid . ';
	';
		$redirect_code .= '
if(!isset($_COOKIE[\'jfusionframeless\']))';

		$redirect_code .= '
{
	$list = explode(\'/\', $universal_url, 4);
	$jfile = ltrim (str_replace($list[3], \'\', $_SERVER[\'PHP_SELF\'] ), \'/\');
	$jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$jfile. \'&\' . $_SERVER[\'QUERY_STRING\'];
	header(\'Location: \' . $jfusion_url);
	exit;
}
//JFUSION REDIRECT END';
		return $redirect_code;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return string
	 */
	function showRedirectMod($name, $value, $node, $control_name)
	{
		$action = JFusionFactory::getApplication()->input->get('action');
		if ($action == 'redirectcode') {
			$joomla_url = JFusionFactory::getParams('joomla_int')->get('source_url');
			$joomla_itemid = $this->params->get('redirect_itemid');

			//check to see if all vars are set
			if (empty($joomla_url)) {
				JFusionFunction::raiseWarning(JText::_('MISSING') . ' Joomla URL', $this->getJname());
			} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
				JFusionFunction::raiseWarning(JText::_('MISSING') . ' ItemID', $this->getJname());
			} else if (!$this->isValidItemID($joomla_itemid)) {
				JFusionFunction::raiseWarning(JText::_('MISSING') . ' ItemID ' . JText::_('MUST BE') . ' ' . $this->getJname(), $this->getJname());
			} else {
				header('Content-disposition: attachment; filename=jfusion_' . $this->getJname() . '_redirectcode.txt');
				header('Pragma: no-cache');
				header('Expires: 0');
				header ('content-type: text/html');

				echo $this->generateRedirectCode($joomla_url, $joomla_itemid);
				exit();
			}
		}

		$output = ' <a href="index.php?option=com_jfusion&amp;task=plugineditor&amp;jname=' . $this->getJname() . '&amp;action=redirectcode">' . JText::_('MOD_ENABLE_MANUALLY') . '</a>';
		return $output;
	}

	/**
	 * do plugin support multi usergroups
	 *
	 * @return bool
	 */
	function isMultiGroup()
	{
		$userid = $this->helper->getFieldType('USERID', 'group');
		if ($userid) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Debug Extras
	 *
	 * @return void
	 */
	function debugConfigExtra()
	{
		$usertable = $this->helper->getTable();
		if ($usertable) {
			$userid = $this->helper->getFieldType('USERID');

			$username = $this->helper->getFieldType('USERNAME');

			$email = $this->helper->getFieldType('EMAIL');

			if ( !$userid ) {
				JFusionFunction::raiseWarning(JText::_('NO_USERID_DEFINED'), $this->getJname());
			}

			if ( !$email ) {
				JFusionFunction::raiseWarning(JText::_('NO_EMAIL_DEFINED'), $this->getJname());
			}

			if ( !$username ) {
				JFusionFunction::raiseWarning(JText::_('NO_USERNAME_DEFINED'), $this->getJname());
			}
			$grouptable = $this->helper->getTable('group');
			if ($grouptable) {
				$group_userid = $this->helper->getFieldType('USERID', 'group');
				$group_group = $this->helper->getFieldType('GROUP', 'group');

				if ( !$group_userid ) {
					JFusionFunction::raiseWarning(JText::_('NO_GROUP_USERID_DEFINED'), $this->getJname());
				}
				if ( !$group_group ) {
					JFusionFunction::raiseWarning(JText::_('NO_GROUP_GROUPID_DEFINED'), $this->getJname());
				}
			}
			$grouplist = $this->getUsergroupList();
			if (empty($grouplist)) {
				JFusionFunction::raiseWarning(JText::_('NO_GROUPS_MAPPED'), $this->getJname());
			}
		} else {
			JFusionFunction::raiseWarning(JText::_('NO_USERTABLE_DEFINED'), $this->getJname());
		}
	}

	/**
	 * create the render group function
	 *
	 * @return string
	 */
	function getRenderGroup()
	{
		$jname = $this->getJname();

		if ($this->helper->isDualGroup()) {
			JFusionFunction::loadJavascriptLanguage(array('MAIN_USERGROUP', 'MEMBERGROUPS'));
			$js = <<<JS
		JFusion.renderPlugin['{$jname}'] = function(index, plugin, pair) {
			var usergroups = JFusion.usergroups[plugin.name];

			var div = new Element('div');

			// render default group
			div.appendChild(new Element('div', {'html': Joomla.JText._('MAIN_USERGROUP')}));

		    var defaultselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][defaultgroup]',
		    	'id': 'usergroups_'+plugin.name+index+'defaultgroup'
		    });

			jQuery(document).on('change', '#usergroups_'+plugin.name+index+'defaultgroup', function() {
                var value = this.get('value');

				jQuery('#'+'usergroups_'+plugin.name+index+'groups'+' option').each(function() {
					if (jQuery(this).attr('value') == value) {
						jQuery(this).prop('selected', false);
						jQuery(this).prop('disabled', true);

						jQuery(this).trigger('chosen:updated').trigger('liszt:updated');
	                } else if (jQuery(this).prop('disabled') === true) {
						jQuery(this).prop('disabled', false);
						jQuery(this).trigger('chosen:updated').trigger('liszt:updated');
					}
				});
			});

		    Array.each(usergroups, function (group) {
			    var options = {'value': group.id,
					            'html': group.name};

		        if (pair && pair.defaultgroup && pair.defaultgroup == group.id) {
					options.selected = 'selected';
		        }

				defaultselect.appendChild(new Element('option', options));
		    });
		    div.appendChild(defaultselect);


			// render default member groups
			div.appendChild(new Element('div', {'html': Joomla.JText._('MEMBERGROUPS')}));


		    var membergroupsselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][groups][]',
		    	'multiple': 'multiple',
		    	'id': 'usergroups_'+plugin.name+index+'groups'
		    });


		    Array.each(usergroups, function (group, i) {
			    var options = {'value': group.id,
					            'html': group.name};

		        if (pair && pair.defaultgroup == group.id) {
					options.disabled = 'disabled';
		        } else if (!pair && i === 0) {
		        	options.disabled = 'disabled';
		        } else {
		            if (pair && pair.groups && pair.groups.contains(group.id)) {
						options.selected = 'selected';
		        	}
		        }

				membergroupsselect.appendChild(new Element('option', options));
		    });
		    div.appendChild(membergroupsselect);
		    return div;
		};
JS;
		} else {
			$js = <<<JS
		JFusion.renderPlugin['{$jname}'] = JFusion.renderDefault;
JS;
		}
		return $js;
	}
}