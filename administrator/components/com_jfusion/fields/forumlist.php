<?php

/**
 * This is the jfusion Forumlist element file
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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';
/**
 * JFusion Element class Forumlist
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldForumlist extends JFormField
{
    public $type = 'forumlist';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
        //Query current selected Module Id
        $id = JFactory::getApplication()->input->getInt('id', 0);
        $cid = JFactory::getApplication()->input->get('cid', array($id), 'array');
        JArrayHelper::toInteger($cid, array(0));
        //find out which JFusion plugin is used in the activity module
        $db = JFactory::getDBO();
        $query = 'SELECT params FROM #__modules  WHERE module = \'mod_jfusion_activity\' and id = ' . $db->Quote($cid[0]);
        $db->setQuery($query);
        $params = $db->loadResult();
        $parametersInstance = new JRegistry($params);
        //load custom plugin parameter
        $jPluginParam = new JRegistry('');
        $jPluginParamRaw = unserialize(base64_decode($parametersInstance->get('JFusionPluginParam')));
        $output = '<span style="float:left; margin: 5px 0; font-weight: bold;">';
        $jname = $jPluginParamRaw['jfusionplugin'];

        $control_name = $this->formControl.'['.$this->group.']';
        if (!empty($jname)) {
            if (JFusionFunction::validPlugin($jname)) {
                $output.= '<b>' . $jname . '</b><br />';
                $JFusionPlugin = JFusionFactory::getForum($jname);
                if (method_exists($JFusionPlugin, 'getForumList')) {
                    $forumlist = $JFusionPlugin->getForumList();
                    if (!empty($forumlist)) {
                        $selectedValue = $parametersInstance->get($this->fieldname);
                        $output = JHTML::_('select.genericlist', $forumlist, $control_name . '[' . $this->fieldname . '][]', 'multiple size="6" class="inputbox"', 'id', 'name', $selectedValue);
                        return $output;
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
        $output.= '</span>';
        return $output;
    }
}
