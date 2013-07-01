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

class JFormFieldJFusionItemid extends JFormField
{
    public $type = 'JFusionItemid';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
        JHTML::_('behavior.modal', 'a.modal');

    	$value = $this->value;
    	$name = $this->name;
        static $elId;
        static $js;
        if (!is_int($elId)) {
            $elId = 0;
        } else {
            $elId++;
        }
        if (!$js) {
        	$doc = JFactory::getDocument();
            $js = <<<JS
            function jSelectItemid(name,id,num) {
	            $(name+'_id'+num).value = id;
	            $(name+'_name'+num).value = id;
	            $(name+'_save'+num).set('src', 'components/com_jfusion/images/filesave.png');
	            SqueezeBox.close();
	        }
JS;
	        $doc->addScriptDeclaration($js);
        }
        $feature = $this->element['feature'];

        if (!$feature) {
            $feature = 'any';
        }

        if($value) {
            $src = 'components/com_jfusion/images/tick.png';
        } else {
            $src = 'components/com_jfusion/images/clear.png';
        }

        $link = 'index.php?option=com_jfusion&amp;task=itemidselect&amp;tmpl=component&amp;ename=' . $name . '&amp;elId=' . $elId . '&amp;feature=' . $feature;

        $select_menuitem = JText::_('SELECT_MENUITEM');
        $select = JText::_('SELECT');
        $html = <<<HTML
            <div style="float: left;">
                <input style="background: #ffffff;" type="text" id="{$name}_name{$elId}" value="{$value}" disabled="disabled" />
            </div>
            <div class="button2-left">
                <div class="blank">
                    <a class="modal" title="{$select_menuitem}"  href="{$link}" rel="{handler: 'iframe', size: {x: 650, y: 375}}">{$select}</a>
                </div>
            </div>
            <img id="{$name}_save{$elId}" src="{$src}" alt="Save">
            <input type="hidden" id="{$name}_id{$elId}" name="{$name}" value="{$value}" />
HTML;
        return $html;
    }
}
