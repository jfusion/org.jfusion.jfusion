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
 * Load the JFusion framework
 */
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusionpublic.php');
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.abstractadmin.php');

require_once(dirname(__FILE__).DS.'map.php');

/**
 * JFusion Admin Class for universal
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * @package JFusion_universal
 */
class JFusionAdmin_universal extends JFusionAdmin{
    function getJname()
    {
        return 'universal';
    }

    function getTablename()
    {
        $map = JFusionMap::getInstance($this->getJname());
        return $map->getTablename('user');
    }

    function getUsergroupList()
    {
        $params = JFusionFactory::getParams($this->getJname());
		$usergroupmap = $params->get('usergroupmap');

        $usergrouplist = arrat();
		if ( is_array($usergroupmap) ) {
			foreach ($usergroupmap['value'] as $key => $value) {
	         	//append the default usergroup
	         	$default_group = new stdClass;
	         	$value = html_entity_decode($value);
	            $default_group->id = base64_encode($value);
	            $default_group->name = $usergroupmap['name'][$key];
	            $usergrouplist[] = $default_group;
      		}
    	}
     	return $usergrouplist;
	}

    function getDefaultUsergroup()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroup_id = $params->get('usergroup');

        $usergrouplist = $this->getUsergroupList();
        foreach ($usergrouplist as $value) {
            if($value ->id == $usergroup_id){
                return $value->name;
            }
        }
        return null;
    }

    function getUserList()
    {
    	$map = JFusionMap::getInstance($this->getJname());
    	$f = array('USERNAME', 'EMAIL', 'USERNAMEEMAIL');
    	$field = $map->getQuery($f);

        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT '.$field.' from #__'.$this->getTablename();
        $db->setQuery($query );
        $userlist = $db->loadObjectList();

        return $userlist;
    }

    function getUserCount()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__'.$this->getTablename();
        $db->setQuery($query );

        //getting the results
        return $db->loadResult();
    }

    function allowRegistration()
    {
        return false;
    }

    function mapuser($name, $value, $node, $control_name)
    {
        $map = JFusionMap::getInstance($this->getJname());
        $value = $map->getMapRaw('user');


        return $this->map('map', $value, $node, $control_name,'user');
    }

    function user_auth($name, $value, $node, $control_name)
    {    	
    	$output = '<textarea name="'.$control_name.'['.$name.']" rows="20" cols="55">'.$value.'</textarea>';
    	return $output;
    }
    
    function mapgroup($name, $value, $node, $control_name)
    {
        $map = JFusionMap::getInstance($this->getJname());
        $value = $map->getMapRaw('group');
        return $this->map('map', $value, $node, $control_name,'group');
    }

    function map($name, $value, $node, $control_name,$type)
    {
        $jname = $this->getJname();
        $params = JFusionFactory::getParams($jname);

        $database_name = $params->get('database_name');
        $database_prefix = $params->get('database_prefix');
        $output = '';
        $db = JFusionFactory::getDatabase($jname);
        if ( $db ) {
            $query = 'SHOW TABLES FROM '.$database_name;
            $db->setQuery($query);
            $tabelslist = $db->loadRowList();

            if ($tabelslist) {
                $tl = array();
                $fl = array();

                $map = JFusionMap::getInstance($this->getJname());

                $fieldtypes = $map->getField();

                $table = new stdClass;
                $table->id = null;
                $table->name = JText::_('UNSET');
                $tl[] = $table;

                $firstTable = null;
                foreach ($tabelslist as $key => $val) {
                    if( @strpos( $val[0], $database_prefix ) === 0 || $database_prefix == '' ) {
                        $table = new stdClass;

                        $table->id = substr($val[0], strlen($database_prefix));
                        $table->name = $val[0];

                        $query = 'SHOW COLUMNS FROM '.$table->name;
                        $db->setQuery($query);
                        $fieldslist = $db->loadObjectList();

                        if (!$firstTable) $firstTable = $table->id;
                        $fl[$table->id] = $fieldslist;
                        $tl[] = $table;
                    }
                }

                $mapuser = array();
                if ( $value['table'] ) {
                    $mapuser = $fl[$value['table']];
                } else {
                    if ($firstTable) $mapuser = $fl[$firstTable];
                }

                $onchange = 'onchange="javascript: groupchange(this)"';

                $output .= JHTML::_('select.genericlist', $tl, $control_name.'['.$name.']['.$type.'][table]', 'onchange="javascript: submitbutton(\'applyconfig\')"',	'id', 'name', $value['table']);
                if ( !empty($value['table']) ) {
                    $output .= '<br>';
                    foreach ($mapuser as $val) {
                        $output .= 'Name: '.$val->Field.' ';
                        if ( isset($value['field'][$val->Field]) ) {
                            $mapuserfield = $value['field'][$val->Field];
                        } else {
                            $mapuserfield = '';
                        }
                        if ( isset($value['type'][$val->Field]) ) {
                            $fieldstype = $value['type'][$val->Field];
                        } else {
                            $fieldstype = '';
                        }
                        if ( isset($value['value'][$val->Field]) ) {
                            $fieldsvalue = $value['value'][$val->Field];
                            if (is_array($fieldsvalue)) {
                                foreach ($fieldsvalue as $key2 => $val2) {
                                    $fieldsvalue[$key2] = htmlentities($val2);
                                }
                            } else {
                                $fieldsvalue = htmlentities($fieldsvalue);
                            }
                        } else {
                            $fieldsvalue = '';
                        }

                        $onchange = 'onchange="javascript: changefield(this,\''.$val->Field.'\',\''.$type.'\')"';
                        $output .= JHTML::_('select.genericlist', $fieldtypes, $control_name.'['.$name.']['.$type.'][field]['.$val->Field.']', $onchange,	'id', 'name', $mapuserfield);

                        $onchange = 'onchange="javascript: changevalue(this,\''.$val->Field.'\',\''.$type.'\')"';
                        $output .= '<div id="'.$val->Field.'">';
                        if ( isset( $fieldtypes[$mapuserfield]) ) {
                            if ( isset( $fieldtypes[$mapuserfield]->types) ) {
                                $output .= JHTML::_('select.genericlist', $fieldtypes[$mapuserfield]->types, $control_name.'['.$name.']['.$type.'][type]['.$val->Field.']', $onchange, 'id', 'name', $fieldstype);
                            }
                        }

                        switch ($fieldstype) {
                            case 'CUSTOM':
                                $output .= '<textarea id="'.$control_name.$name.$type.'value'.$val->Field.'" name="'.$control_name.'['.$name.']['.$type.'][value]['.$val->Field.']" rows="20" cols="55">'.$fieldsvalue.'</textarea>';
                                break;
                            case 'DEFAULT':
                            case 'VALUE':
                            case 'DATE':
                                $output .= '<input type="text" id="'.$control_name.$name.$type.'value'.$val->Field.'" name="'.$control_name.'['.$name.']['.$type.'][value]['.$val->Field.']" value="'.$fieldsvalue.'" size="100" class="inputbox" />';
                                break;
                            case 'ONOFF':
                                foreach ($fieldsvalue as $key2 => $val2) {
                                    $output .= '<input type="text" id="'.$control_name.$name.$type.'value'.$val->Field.$key2.'" name="'.$control_name.'['.$name.']['.$type.'][value]['.$val->Field.']['.$key2.']" value="'.$val2.'" size="40" class="inputbox" />';
                                }
                                break;
                        }
                        $output .= '</div>';
                        //object(stdClass)#245 (6) { ["Field"]=>  string(2) "id" ["Type"]=>  string(6) "int(5)" ["Null"]=>  string(0) "" ["Key"]=>  string(0) "" ["Default"]=>  string(1) "0" ["Extra"]=>  string(0) "" }
                        $output .= ' Type: '.$val->Type;
                        $output .= ' Default: "'.$val->Default.'"';
                        $output .= ' Null: ';
                        $output .= $val->Null?JText::_('YES'):JText::_('NO');

                        $output .= '<br><br>';
                    }
                }
            } else {
                $output .= JText::_('SAVE_CONFIG_FIRST');
            }
        } else {
            $output .= JText::_('SAVE_CONFIG_FIRST');
        }
        return $output;
    }

    function usergroupmap($name, $value, $node, $control_name)
    {
		if (!is_array($value)) $value = null;

		$output = '';

		if (!is_array($value)) {
    		$output .= '<input type="text" name="params[usergroupmap][value][0]" id="paramsusergroupmapvalue0" size="50"/>';
    		$output .= '<input type="text" name="params[usergroupmap][name][0]" id="paramsusergroupmapname0" size="50"/>';
			$output .= '<div id="paramsusergroupmap"></div>';
		} else {
  			$i = 0;
  			foreach ($value['value'] as $key => $val) {
	         	$val = htmlentities($val);
				if ( $i ) $output .= '<div id="paramsusergroupmap'.$i.'">';
    			$output .= '<input value="'.$val.'" type="text" name="params[usergroupmap][value]['.$i.']" id="paramsusergroupmapvalue'.$i.'" size="50"/>';
    			$output .= '<input value="'.$value['name'][$key].'" type="text" name="params[usergroupmap][name]['.$i.']" id="paramsusergroupmapname'.$i.'" size="50"/>';
  				if ( $i ) {
					$output .= '<a href="javascript:removePair(\'usergroupmap\', \'usergroupmap'.$i.'\');">Delete</a></div>';
  				} else {
					$output .= '<div id="paramsusergroupmap">';
  				}
    			$i++;
  			}
			$output .= '</div>';
		}

		$output .= '<div id="addGroupPair" style="display:block;"><a href="javascript:addPair(\'usergroupmap\',50);">Add Another Pair</a></div>';

		return $output;
    }

    function js($name, $value, $node, $control_name)
    {
        $document =& JFactory::getDocument();

		$map = JFusionMap::getInstance($this->getJname());
        $list = $map->getField();

		$output = $primlist = '';
        $primlist .= 'var TypeAry = new Array(); ';
		foreach ($list as $key => $val) {
			if(isset($val->types) ) {
				$primlist .= 'TypeAry[\''.$val->id.'\'] = new Array(); ';
				foreach ($val->types as $k => $v) {
					$primlist .= 'TypeAry[\''.$val->id.'\']['.$k.'] = [\''.$v->id.'\',\''.$v->name.'\']; ';
				}
			}
		}

		$output .= $primlist;
$output .= <<<JS
        function changefield(ref,name,parmtype) {
            var id = document.getElementById(name);
            id.innerHTML = '';
            if ( TypeAry[ref.value] !== undefined ) {
                var type = document.createElement("select");
                type.setAttribute("type", "option");
                type.setAttribute("id", "paramsmap"+parmtype+"type"+name);
                type.setAttribute("name", "params[map][user][type]["+name+"]");
                type.setAttribute("onchange", "javascript: changevalue(this,'"+name+"','"+parmtype+"')");

                type.options.length = 0;
                for (i=0;i<TypeAry[ref.value].length;i++) {
                     type.options[type.options.length] = new Option(TypeAry[ref.value][i][1],TypeAry[ref.value][i][0]);
                }
                id.appendChild(type);
            }
        }
        function changevalue(ref,name,parmtype) {
            var id = document.getElementById(name);
            var old = document.getElementById("paramsmap"+parmtype+"value"+name);

            var oldON = document.getElementById("paramsmap"+parmtype+"value"+name+"on");
            var oldOFF = document.getElementById("paramsmap"+parmtype+"value"+name+"off");

            if (oldON) {
                oldON.remove();
            }
            if (oldOFF) {
                oldOFF.remove();
            }

            var oldvalue = '';
            if ( old ) {
                oldvalue = old.value;
                old.remove();
            }
            if(ref.value == 'CUSTOM') {
                var value = document.createElement("textarea");
                value.setAttribute("id", "paramsmap"+parmtype+"value"+name);
                value.setAttribute("name", "params[map]["+parmtype+"][value]["+name+"]");
                value.setAttribute("rows", 20);
                value.setAttribute("cols", 55);

                id.appendChild(value);
            } else if(ref.value == 'DATE' || ref.value == 'VALUE') {
                var value = document.createElement("input");
                value.setAttribute("type", "text");
                value.setAttribute("id", "paramsmap"+parmtype+"value"+name);
                value.setAttribute("name", "params[map]["+parmtype+"][value]["+name+"]");
                value.setAttribute("size", "100");
                if ( oldvalue ) {
                    value.setAttribute("value", oldvalue);
                } else if (ref.value == 'DATE') {
                    value.setAttribute("value", 'Y-m-d H:i:s');
                }
                id.appendChild(value);
            } else if ( ref.value == 'ONOFF') {
                var valueON = document.createElement("input");
                valueON.setAttribute("type", "text");
                valueON.setAttribute("id", "paramsmap"+parmtype+"value"+name+"on");
                valueON.setAttribute("name", "params[map]["+parmtype+"][value]["+name+"][on]");
                valueON.setAttribute("size", "40");

                var valueOFF = document.createElement("input");
                valueOFF.setAttribute("type", "text");
                valueOFF.setAttribute("id", "paramsmap"+parmtype+"value"+name+"off");
                valueOFF.setAttribute("name", "params[map]["+parmtype+"][value]["+name+"][off]");
                valueOFF.setAttribute("size", "40");

                id.appendChild(valueON);
                id.appendChild(valueOFF);
            }
        }
JS;
        $document->addScriptDeclaration($output);
		return '';
    }
    
	function generateRedirectCode()
	{
        $params = JFusionFactory::getParams($this->getJname());
        $joomla_params = JFusionFactory::getParams('joomla_int');
        $joomla_url = $joomla_params->get('source_url');
        $universal_url = $params->get('source_url');
        $joomla_itemid = $params->get('redirect_itemid', 0); // Set to '0' to prevent error by activating the mod redirection and none itemid provided

        //create the new redirection code

        $redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \''. $joomla_url . '\';
$universal_url \''. $universal_url . '\';
$joomla_itemid = ' . $joomla_itemid .';
	';
        $redirect_code .= '
if(!isset($_COOKIE[\'jfusionframeless\']))';

        $redirect_code .= '
{
	$list = explode  (  \'/\' ,  $universal_url ,4);
	$jfile = ltrim (str_replace  (  $list[3] ,  \'\'  ,  $_SERVER[\'PHP_SELF\'] ), \'/\');
	$jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$jfile. \'&\' . $_SERVER[\'QUERY_STRING\'];
	header(\'Location: \' . $jfusion_url);
	exit;
}
//JFUSION REDIRECT END
';
	    return $redirect_code;
	}
	
    function show_redirect_mod($name, $value, $node, $control_name)
    {
		$action = JRequest::getVar('action');
		if ($action == 'redirectcode') {
 			header('Content-disposition: attachment; filename=jfusion_'.$this->getJname().'_redirectcode.txt');
 			header('Pragma: no-cache');
			header('Expires: 0');
			header ("content-type: text/html");

			echo $this->generateRedirectCode();
			exit();
		}

		$output = ' <a href="index.php?option=com_jfusion&amp;task=plugineditor&amp;jname='.$this->getJname().'&amp;action=redirectcode">' . JText::_('MOD_ENABLE_MANUALLY') . '</a>';
		return $output;
    }    
}