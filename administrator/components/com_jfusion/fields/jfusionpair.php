<?php

/**
 * This is the jfusion Userpostgroups element file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();
/**
 * Require the Jfusion plugin factory
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';

/**
 * JFusion Element class Discussionbot
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldJFusionPair extends JFormField
{
	public $type = 'JFusionPair';
	/**
	 * Get an element
	 *
	 * @return string html
	 */
	protected function getInput()
	{
		static $js;

		$delete = JText::_('DELETE_PAIR');

		if (!$js) {
			$document = JFactory::getDocument();

			$output = <<<JS
		function addPair(t,s)	{
            var tr = document.createElement("tr");


            var index = 0;
            var list;
            while (true) {
                list = document.getElementById("params"+t+"value"+index);
                if (!list) break;
                index++;
            }
            tr.setAttribute("id", "params"+t+index);

            var input = document.createElement("input");
            var td = document.createElement("td");
            input.setAttribute("type", "text");
            input.setAttribute("id", "params"+t+"name"+index);
            input.setAttribute("name", "params["+t+"][name]["+index+"]");
            input.setAttribute("size", s);
            td.appendChild(input);
            tr.appendChild(td);

            input = document.createElement("input");
            td = document.createElement("td");
			input.setAttribute("type", "text");
            input.setAttribute("id", "params"+t+"value"+index);
            input.setAttribute("name", "params["+t+"][value]["+index+"]");
            input.setAttribute("size", s);
            td.appendChild(input);
            tr.appendChild(td);

			var a = document.createElement("a");
			td = document.createElement("td");
            a.setAttribute("href", "javascript:removePair(\'"+t+"\',\'"+t+index+"\');");
            a.appendChild(document.createTextNode("{$delete}"));
            td.appendChild(a);
            tr.appendChild(td);

            $("params"+t).appendChild(tr);

            $("params"+t+"_save").src = 'components/com_jfusion/images/filesave.png';
        }

        function removePair(t,i) {
            $("params"+t).removeChild($("params"+i));
            $("params"+t+"_save").src = 'components/com_jfusion/images/filesave.png';
        }

        function closePair() {
			$(this.options.target).inject($(this.options.return));
        }
JS;
			$document->addScriptDeclaration($output);
			$js = true;
		}

		$name = $this->fieldname;
		$temp = $value = $this->value;

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

		$att = $this->element->attributes();

		$col1 = isset($att['col1']) ? JText::_($att['col1']) : JText::_('NAME');
		$col2 = isset($att['col2']) ? JText::_($att['col2']) : JText::_('VALUE');

		$values = '';
		if (!is_array($value) || !count($value)) {
			$values .= '<tr id="params'.$name.'0">';
			$values .= '<td>';
			$values .= '<input type="text" name="params['.$name.'][name][0]" id="params'.$name.'name0" size="50"/>';
			$values .= '</td><td>';
			$values .= '<input type="text" name="params['.$name.'][value][0]" id="params'.$name.'value0" size="50"/>';
			$values .= '</td><td>';
			$values .= '<a href="javascript:removePair(\''.$name.'\', \''.$name.'0\');">'.$delete.'</a>';
			$values .= '</td>';
			$values .= '</tr>';
		} else {
			$i = 0;
			foreach ($value['value'] as $key => $val) {
				$v = htmlentities($val);
				$n = htmlentities($value['name'][$key]);
				$values .= '<tr id="params'.$name.$i.'">';
				$values .= '<td>';
				$values .= '<input value="'.$n.'" type="text" name="params['.$name.'][name]['.$i.']" id="params'.$name.'name'.$i.'" size="50"/>';
				$values .= '</td><td>';
				$values .= '<input value="'.$v.'" type="text" name="params['.$name.'][value]['.$i.']" id="params'.$name.'value'.$i.'" size="50"/>';
				$values .= '</td><td>';
				$values .= '<a href="javascript:removePair(\''.$name.'\', \''.$name.$i.'\');">'.$delete.'</a>';
				$values .= '</td>';
				$values .= '</tr>';
				$i++;
			}
		}

		$add = JText::_('ADD_PAIR');

		$output = <<<HTML
			<div style="display:none;" id="jform_params_{$name}">
				<div id="target_jform_params_{$name}">
					<table>
						<thead>
							<tr>
								<th>
									{$col1}
								</th>
								<th>
									{$col2}
								</th>
								<th>
								</th>
							</tr>
						</thead>
						<tbody id="params{$name}">
							{$values}
						</tbody>
					</table>
					<div>
						<a href="javascript:addPair('{$name}',50);">{$add}</a>
					</div>
	    		</div>
			</div>
HTML;
		$output .= '<div class="button2-left"><div class="blank"><a class="modal" title="' . JText::_('CONFIGURE') . '"  href="" rel="{target: \'target_jform_params_'.$name.'\', handler: \'adopt\', return: \'jform_params_'.$name.'\', onClose : closePair, size: {x: 650, y: 375}}">' . JText::_('CONFIGURE') . '</a></div></div>';
		if($value) {
			$src = 'components/com_jfusion/images/tick.png';
		} else {
			$src = 'components/com_jfusion/images/clear.png';
		}
		$output .= '<img id="params'.$name.'_save" src="'.$src.'" alt="Save">';
		return $output;
	}
}
