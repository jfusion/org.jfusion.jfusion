<?php
 
/**
 * This is the jfusion AdvancedParam element file
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
 * JFusion Element class AdvancedParam
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.orgrg
 */
class JElementJFusionAdvancedParam extends JElement
{
    var $_name = 'JFusionAdvancedParam';

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
        //used to give unique ids to elements when more than one advanced param is loaded (for example in configuring JoomFish)
        static $elNum;
        if (!isset($elNum)) {
            $elNum = 0;
        }

        $lang = JFactory::getLanguage();
        $lang->load('com_jfusion');

        $doc = JFactory::getDocument();
        $fieldName = $control_name . '[' . $name . ']';
        $feature = $node->attributes('feature');
        if (!$feature) {
            $feature = 'any';
        }
        $multiselect = $node->attributes('multiselect');

        if (!defined('JFUSION_ADVANCEDPARAM_JS_LOADED')) {
            define('JFUSION_ADVANCEDPARAM_JS_LOADED', 1);

            if (!is_null($feature)) {
                $cfile = '&feature='.$feature;
            } else {
                $cfile = '';
            }
            if (!is_null($multiselect)) {
                $mselect = '&multiselect=1';
            } else {
                $mselect = '';
            }

            $js = <<<JS
            function jAdvancedParamSet(title, base64, elNum) {
                var link = 'index.php?option=com_jfusion&task=advancedparam&tmpl=component&params=';
                link += base64;
                link += '{$cfile}';
                link += '{$mselect}';

                $('plugin_id' + elNum).value = base64;
                $('plugin_name' + elNum).value = title;
                $('plugin_link' + elNum).href = link;
                SqueezeBox.close();
            }
JS;
            $doc->addScriptDeclaration($js);
        }
        //Create Link
        $link = 'index.php?option=com_jfusion&amp;task=advancedparam&amp;tmpl=component&amp;elNum='.$elNum.'&amp;params=' . $value;
        if (!is_null($feature)) {
            $link.= '&amp;feature=' . $feature;
        }
        if (!is_null($multiselect)) {
            $link.= '&amp;multiselect=1';
        }
        //Get JParameter from given string
        if (empty($value)) {
            $params = array();
        } else {
            $params = base64_decode($value);
            $params = unserialize($params);
            if (!is_array($params)) {
                $params = array();
            }
        }
        $title = '';
        if (isset($params['jfusionplugin'])) {
            $title = $params['jfusionplugin'];
        } else if ($multiselect) {
            $del = '';
            foreach ($params as $key => $param) {
                if (isset($param['jfusionplugin'])) {
                    $title.= $del . $param['jfusionplugin'];
                    $del = '; ';
                }
            }
        }
        if (empty($title)) {
			$title = JText::_('NO_PLUGIN_SELECTED');
        }
        //Replace new Lines with the placeholder \n
        JHTML::_('behavior.modal', 'a.modal');

        $select_plugin = JText::_('SELECT_PLUGIN');
        $select = JText::_('SELECT');
        $html = <<<HTML
        <div style="float: left;">
            <input style="background: #ffffff;" type="text" id="plugin_name{$elNum}" value="{$title}" disabled="disabled" />
        </div>
        <div class="button2-left">
            <div class="blank">
                <a id="plugin_link{$elNum}" class="modal" title="{$select_plugin}"  href="{$link}" rel="{handler: 'iframe', size: {x: 750, y: 475}}">{$select}</a>
            </div>
        </div>
        <input type="hidden" id="plugin_id{$elNum}" name="{$fieldName}" value="{$value}" />
HTML;
        $elNum++;
        return $html;
    }
}
