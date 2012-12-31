<?php
/**
* @package JFusion
* @subpackage Elements
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/


// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

/**
* Require the Jfusion plugin factory
*/
require_once JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.factory.php';
require_once JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusion.php';

/**
* Defines the forum select list for JFusion forum plugins
* @package JFusion
*/
class JElementJFusionPair extends JElement
{
    var $_name = 'JFusionPair';

    /**
     * Get an element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param JSimpleXMLElement &$node        node of element
     * @param string $control_name name of controler
     *
     * @return string|void html
     */
    function fetchElement($name, $value, &$node, $control_name)
    {
        static $js;

        $delete = JText::_('DELETE_PAIR');

        if (!$js) {
            $document = JFactory::getDocument();

            $output = <<<JS
         function addPair(t,s)	{
            var d = document.createElement("p");
            var l = document.createElement("a");

            var index = 0;
            var list;
            while (true) {
                list = document.getElementById("params"+t+"value"+index);
                if (!list) break;
                index++;
            }

            var group_value = document.createElement("input");
            group_value.setAttribute("type", "text");
            group_value.setAttribute("id", "params"+t+"value"+index);
            group_value.setAttribute("name", "params["+t+"][value]["+index+"]");
            group_value.setAttribute("size", s);

            var group_name = document.createElement("input");
            group_name.setAttribute("type", "text");
            group_name.setAttribute("id", "params"+t+"name"+index);
            group_name.setAttribute("name", "params["+t+"][name]["+index+"]");
            group_name.setAttribute("size", s);

            l.setAttribute("href", "javascript:removePair(\'"+t+"\',\'"+t+index+"\');");
            d.setAttribute("id", "params"+t+index);

            var image = document.createTextNode("{$delete}");
            l.appendChild(image);

            d.appendChild(group_value);
            d.appendChild(group_name);
            d.appendChild(l);

            document.getElementById("params"+t).appendChild(d);
            group_value.focus();
        }

        function removePair(t,i) {
            var elm = document.getElementById("params"+i);
            document.getElementById("params"+t).removeChild(elm);
        }

        function closePair() {
			$(this.options.adopt).clone().inject($(this.options.return));
        }

        SqueezeBox.handlers['jfusion'] = function(el) {
			return el;
		}
		SqueezeBox.parsers['jfusion'] = SqueezeBox.parsers['adopt'];
JS;
            $document->addScriptDeclaration($output);
            $js = true;
        }

        $temp = $value;

        $temp = @unserialize($temp);

        if (!is_array($temp)) {
            $values = explode( ',', $value);
            if ($values) {
                $temp = array();
                foreach($values as $pair) {
                    $result = explode( ':', $pair);
                    if (count($result)==2) {
                        $temp['name'][] = $result[0];
                        $temp['value'][] = $result[1];
                    }
                }
            }
        }
	    JHTML::_('behavior.modal', 'a.modal');
        $value = $temp;

	    $output = '<div style="display:none;" id="jform_params_'.$name.'">';
	    $output .= '<div id="target_jform_params_'.$name.'"><div id="params'.$name.'">';
        if (!is_array($value) || !count($value)) {
            $output .= '<p id="params'.$name.'0">';
            $output .= '<input type="text" name="params['.$name.'][value][0]" id="params'.$name.'value0" size="50"/>';
            $output .= '<input type="text" name="params['.$name.'][name][0]" id="params'.$name.'name0" size="50"/>';
            $output .= '<a href="javascript:removePair(\''.$name.'\', \''.$name.'0\');">'.$delete.'</a>';
            $output .= '</p>';
        } else {
            $i = 0;
            foreach ($value['value'] as $key => $val) {
                $val = htmlentities($val);
                $output .= '<p id="params'.$name.$i.'">';
                $output .= '<input value="'.$val.'" type="text" name="params['.$name.'][value]['.$i.']" id="params'.$name.'value'.$i.'" size="50"/>';
                $output .= '<input value="'.$value['name'][$key].'" type="text" name="params['.$name.'][name]['.$i.']" id="params'.$name.'name'.$i.'" size="50"/>';
                $output .= '<a href="javascript:removePair(\''.$name.'\', \''.$name.$i.'\');">'.$delete.'</a>';
                $output .= '</p>';
                $i++;
            }
        }
	    $output .= '</div><div><a href="javascript:addPair(\''.$name.'\',50);">'.JText::_('ADD_PAIR').'</a></div>';
	    $output .= '</div>';
	    $output .= '</div>';
	    $output.= '<div class="button2-left"><div class="blank"><a class="modal" title="' . JText::_('CONFIGURE') . '"  href="" rel="{adopt: \'target_jform_params_'.$name.'\', handler: \'adopt\', return: \'jform_params_'.$name.'\', onClose : closePair, size: {x: 650, y: 375}}">' . JText::_('CONFIGURE') . '</a></div></div>';

        return $output;
    }
}