<?php

/**
 * This is the jfusion search plugin forum list element file
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
 * JFusion Element class ForumListSearchPlugin
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JElementForumListSearchPlugin extends JElement
{
    var $_name = "ForumListSearchPlugin";
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
        //find out which JFusion plugin is used
        $db = JFactory::getDBO();
        $query = 'SELECT params FROM #__plugins  WHERE element = \'jfusion\' and folder = \'search\'';
        $db->setQuery($query);
        $params = $db->loadResult();
        $parametersInstance = new JParameter($params, '');
        //load custom plugin parameter
        $jPluginParamRaw = unserialize(base64_decode($parametersInstance->get('JFusionPluginParam')));

        $jname = '';
        preg_match('#params\[(.*?)\]#', $control_name, $matches);
        if (!empty($matches)) {
            $jname = $matches[1];
        }

        $output = '';
        if (!empty($jname)) {
            if (JFusionFunction::validPlugin($jname)) {
                if (!isset($jPluginParamRaw[$jname])) {
                    $jPluginParamRaw[$jname] = array();
                }
                $JPluginParam = new JParameter('');
                $JPluginParam->loadArray($jPluginParamRaw[$jname]);
                $JFusionPlugin = JFusionFactory::getForum($jname);
                if (method_exists($JFusionPlugin, 'getForumList')) {
                    $forumlist = $JFusionPlugin->getForumList();
                    if (!empty($forumlist)) {
                        $selectedValue = $JPluginParam->get($name);
                        $output.= JHTML::_('select.genericlist', $forumlist, $control_name . '[' . $name . '][]', 'multiple size="6" class="inputbox"', 'id', 'name', $selectedValue);
                    } else {
                        $output.= $jname . ': ' . JText::_('NO_LIST');
                    }
                } else {
                    $output.= $jname . ': ' . JText::_('NO_LIST');
                }
                $output.= '<br />';
            } else {
                $output.= $jname . ': ' . JText::_('NO_VALID_PLUGINS') . '<br />';
            }
        } else {
            $output.= JText::_('NO_PLUGIN_SELECT');
        }
        return $output;
    }
}
