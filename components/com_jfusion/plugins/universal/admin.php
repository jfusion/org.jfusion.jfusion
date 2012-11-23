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
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusion.php');
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.abstractadmin.php');

require_once(dirname(__FILE__).DS.'map.php');

/**
 * JFusion Admin Class for universal
 * For detailed descriptions on these functions please check the model.abstractadmin.php
 * @package JFusion_universal
 */
class JFusionAdmin_universal extends JFusionAdmin{
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
        /**
         * @ignore
         * @var $helper JFusionHelper_universal
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        return $helper->getTablename('user');
    }

    /**
     * @return array
     */
    function getUsergroupList()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroupmap = $params->get('usergroupmap');

        $usergrouplist = array();
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

    /**
     * @return null|string
     */
    function getDefaultUsergroup()
    {
        $params = JFusionFactory::getParams($this->getJname());
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),null);
        $usergroup_id = null;
        if(!empty($usergroups)) {
            $usergroup_id = $usergroups[0];
        }

        $usergrouplist = $this->getUsergroupList();
        foreach ($usergrouplist as $value) {
            if($value ->id == $usergroup_id){
                return $value->name;
            }
        }
        return null;
    }

    /**
     * @return array
     */
    function getUserList()
    {
        /**
         * @ignore
         * @var $helper JFusionHelper_universal
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $f = array('USERNAME', 'EMAIL', 'USERNAMEEMAIL');
        $field = $helper->getQuery($f);

        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT '.$field.' from #__'.$this->getTablename();
        $db->setQuery($query );
        $userlist = $db->loadObjectList();

        return $userlist;
    }

    /**
     * @return int
     */
    function getUserCount()
    {
        //getting the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT count(*) from #__'.$this->getTablename();
        $db->setQuery($query );

        //getting the results
        return $db->loadResult();
    }

    /**
     * @return bool
     */
    function allowRegistration()
    {
        return false;
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
        /**
         * @ignore
         * @var $helper JFusionHelper_universal
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $value = $helper->getMapRaw('user');

        return $this->map('map', $value, $node, $control_name,'user');
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
        $output = '<textarea name="'.$control_name.'['.$name.']" rows="20" cols="55">'.$value.'</textarea>';
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
        /**
         * @ignore
         * @var $helper JFusionHelper_universal
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $value = $helper->getMapRaw('group');
        return $this->map('map', $value, $node, $control_name,'group');
    }

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @param $type
     * @return string
     */
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
                /**
                 * @ignore
                 * @var $helper JFusionHelper_universal
                 */
                $helper = JFusionFactory::getHelper($this->getJname());

                $fieldtypes = $helper->getField();

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
                    $output .= '<table>';
                    foreach ($mapuser as $val) {
                        $output .= '<tr><td>';
                        //object(stdClass)#245 (6) { ["Field"]=>  string(2) "id" ["Type"]=>  string(6) "int(5)" ["Null"]=>  string(0) "" ["Key"]=>  string(0) "" ["Default"]=>  string(1) "0" ["Extra"]=>  string(0) "" }
                        $output .= '<div>Name: '.$val->Field.'</div>';
                        $output .= '<div>Type: '.$val->Type.'</div>';
                        $output .= '<div>Default: "'.$val->Default.'" </div>';
                        $null = $val->Null?JText::_('YES'):JText::_('NO');
                        $output .= '<div>Null: '.$null.'</div></td><td>';
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
                        $fieldsvaluearray = array();
                        $fieldsvalue = '';
                        if ( isset($value['value'][$val->Field]) ) {
                            $fieldsvalue = $value['value'][$val->Field];
                            if (is_array($fieldsvalue)) {
                                $fieldsvaluearray = (array)$fieldsvalue;
                                foreach ($fieldsvaluearray as &$val2) {
                                    $val2 = htmlentities($val2);
                                }
                            } else {
                                $fieldsvalue = htmlentities($fieldsvalue);
                            }
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
                                foreach ($fieldsvaluearray as $key2 => $val2) {
                                    $output .= '<input type="text" id="'.$control_name.$name.$type.'value'.$val->Field.$key2.'" name="'.$control_name.'['.$name.']['.$type.'][value]['.$val->Field.']['.$key2.']" value="'.$val2.'" size="40" class="inputbox" />';
                                }
                                break;
                        }
                        $output .= '</div>';
                        $output .= '</td></tr>';
                    }
                    $output .= '</table>';
                }
            } else {
                $output .= JText::_('SAVE_CONFIG_FIRST');
            }
        } else {
            $output .= JText::_('SAVE_CONFIG_FIRST');
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

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return string
     */
    function js($name, $value, $node, $control_name) {
        $document =& JFactory::getDocument();
        /**
         * @ignore
         * @var $helper JFusionHelper_universal
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $list = $helper->getField();

        $list = json_encode($list);

        $output = <<<JS
        var TypeAry = {$list};

        function changefield(ref,name,parmtype) {
            var id = $(name);
            id.innerHTML = '';

            if ( TypeAry[ref.value].types !== undefined ) {
                var type = document.createElement("select");
                type.setAttribute("type", "option");
                type.setAttribute("id", "paramsmap"+parmtype+"type"+name);
                type.setAttribute("name", "params[map][user][type]["+name+"]");
                type.setAttribute("onchange", "javascript: changevalue(this,'"+name+"','"+parmtype+"')");

                type.options.length = 0;
                for (var i=0; i<TypeAry[ref.value].types.length; i++) {
                    type.options[type.options.length] = new Option(TypeAry[ref.value].types[i].name,TypeAry[ref.value].types[i].id);
                }
                id.appendChild(type);
            }
        }
        function changevalue(ref,name,parmtype) {
            var id = $(name);

            if ( $("paramsmap"+parmtype+"value"+name) ) {
                $("paramsmap"+parmtype+"value"+name).dispose();
            }
            if ($("paramsmap"+parmtype+"value"+name+"on")) {
                $("paramsmap"+parmtype+"value"+name+"on").dispose();
            }
            if ($("paramsmap"+parmtype+"value"+name+"off")) {
                $("paramsmap"+parmtype+"value"+name+"off").dispose();
            }

            var value;
            if(ref.value == 'CUSTOM') {
                value = document.createElement("textarea");
                value.setAttribute("id", "paramsmap"+parmtype+"value"+name);
                value.setAttribute("name", "params[map]["+parmtype+"][value]["+name+"]");
                value.setAttribute("rows", 20);
                value.setAttribute("cols", 55);

                id.appendChild(value);
            } else if(ref.value == 'DATE' || ref.value == 'VALUE') {
                value = document.createElement("input");
                value.setAttribute("type", "text");
                value.setAttribute("id", "paramsmap"+parmtype+"value"+name);
                value.setAttribute("name", "params[map]["+parmtype+"][value]["+name+"]");
                value.setAttribute("size", "100");
                if (ref.value == 'DATE') {
                    value.setAttribute("value", 'Y-m-d H:i:s');
                }
                id.appendChild(value);
            } else if ( ref.value == 'ONOFF') {
                value = document.createElement("input");
                value.setAttribute("type", "text");
                value.setAttribute("id", "paramsmap"+parmtype+"value"+name+"on");
                value.setAttribute("name", "params[map]["+parmtype+"][value]["+name+"][on]");
                value.setAttribute("size", "40");
                id.appendChild(value);

                value = document.createElement("input");
                value.setAttribute("type", "text");
                value.setAttribute("id", "paramsmap"+parmtype+"value"+name+"off");
                value.setAttribute("name", "params[map]["+parmtype+"][value]["+name+"][off]");
                value.setAttribute("size", "40");

                id.appendChild(value);
            }
        }
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
        $params = JFusionFactory::getParams($this->getJname());
        $universal_url = $params->get('source_url');

        //create the new redirection code

        $redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \''. $url . '\';
$universal_url \''. $universal_url . '\';
$joomla_itemid = ' . $itemid .';
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

    /**
     * @param $name
     * @param $value
     * @param $node
     * @param $control_name
     * @return string
     */
    function showRedirectMod($name, $value, $node, $control_name)
    {
        $action = JRequest::getVar('action');
        if ($action == 'redirectcode') {
            $params = JFusionFactory::getParams($this->getJname());
            $joomla_params = JFusionFactory::getParams('joomla_int');
            $joomla_url = $joomla_params->get('source_url');
            $joomla_itemid = $params->get('redirect_itemid');

            //check to see if all vars are set
            if (empty($joomla_url)) {
                JError::raiseWarning(0, JText::_('MISSING') . ' Joomla URL');
            } else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
                JError::raiseWarning(0, JText::_('MISSING') . ' ItemID');
            } else if ($this->isValidItemID($joomla_itemid)) {
                JError::raiseWarning(0, JText::_('MISSING') . ' ItemID '. JText::_('MUST BE'). ' ' . $this->getJname());
            } else {
                header('Content-disposition: attachment; filename=jfusion_'.$this->getJname().'_redirectcode.txt');
                header('Pragma: no-cache');
                header('Expires: 0');
                header ('content-type: text/html');

                echo $this->generateRedirectCode($joomla_url, $joomla_itemid);
                exit();
            }
        }

        $output = ' <a href="index.php?option=com_jfusion&amp;task=plugineditor&amp;jname='.$this->getJname().'&amp;action=redirectcode">' . JText::_('MOD_ENABLE_MANUALLY') . '</a>';
        return $output;
    }
}