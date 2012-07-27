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
class JFormFieldForumListSearchPlugin extends JFormField
{
    public $type = "ForumListSearchPlugin";
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
        //find out which JFusion plugin is used
        $db = JFactory::getDBO();
        $query = 'SELECT params FROM #__extensions  WHERE element = \'jfusion\' and folder = \'search\'';
        $db->setQuery($query);
        $params = $db->loadResult();
        $parametersInstance = new JParameter($params, '');
        //load custom plugin parameter
        $jPluginParamRaw = unserialize(base64_decode($parametersInstance->get('JFusionPluginParam')));

        $jname = '';
        preg_match('#params\[(.*?)\]#', $this->formControl, $matches);
        if (!empty($matches)) {
            $jname = $matches[1];
        }

        $output = "<span style='float:left; margin: 5px 0; font-weight: bold;'>";
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
                        $selectedValue = $JPluginParam->get($this->fieldname);
                        $output = JHTML::_('select.genericlist', $forumlist, $this->name . '[]', 'multiple size="6" class="inputbox"', 'id', 'name', $selectedValue);
                        return $output;
                    } else {
                        $output.= $jname . ': ' . JText::_('NO_LIST');
                    }
                } else {
                    $output.= $jname . ': ' . JText::_('NO_LIST');
                }
                $output.= "<br />\n";
            } else {
                $output.= $jname . ": " . JText::_('NO_VALID_PLUGINS') . "<br />";
            }
        } else {
            $output.= JText::_('NO_PLUGIN_SELECT');
        }
        $output.= "</span>";
        return $output;
    }
}
