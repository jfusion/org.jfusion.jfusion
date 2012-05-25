<?php

/**
 * This is the jfusion Itemid element file
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
defined('_JEXEC') or die('Restricted access');
/**
 * JFusion Element class Itemid
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JElementJFusionItemid extends JElement
{
    var $_name = 'JFusionItemid';
    /**
     * Get an element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string &$node        node of element
     * @param string $control_name name of controler
     *
     * @return string html
     */
    function fetchElement($name, $value, &$node, $control_name)
    {
        $lang = JFactory::getLanguage();
        $lang->load('com_jfusion');

        $mainframe = JFactory::getApplication();
        static $elId;
        static $js;
        if (!is_int($elId)) {
            $elId = 0;
        } else {
            $elId++;
        }
        $doc = JFactory::getDocument();
        $fieldName = $control_name . '[' . $name . ']';
		if (!$js) {
	        $js = "
	        function jSelectItemid(name,id,num) {
	            document.getElementById(name+'_id'+num).value = id;
	            document.getElementById(name+'_name'+num).value = id;
	            SqueezeBox.close();
	        }";
	        $doc->addScriptDeclaration($js);
        }
        $link = 'index.php?option=com_jfusion&amp;task=itemidselect&amp;tmpl=component&amp;ename=' . $name . '&amp;elId=' . $elId;
        JHTML::_('behavior.modal', 'a.modal');
        $html = "\n" . '<div style="float: left;"><input style="background: #ffffff;" type="text" id="' . $name . '_name' . $elId . '" value="' . $value . '" disabled="disabled" /></div>';
        $html.= '<div class="button2-left"><div class="blank"><a class="modal" title="' . JText::_('SELECT_MENUITEM') . '"  href="' . $link . '" rel="{handler: \'iframe\', size: {x: 650, y: 375}}">' . JText::_('SELECT') . '</a></div></div>' . "\n";
        $html.= "\n" . '<input type="hidden" id="' . $name . '_id' . $elId . '" name="' . $fieldName . '" value="' . $value . '" />';
        return $html;
    }
}
