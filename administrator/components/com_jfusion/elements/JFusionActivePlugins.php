<?php

/**
 * This is the jfusion ActivePlugins element file
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
 * JFusion Element class ActivePlugins
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JElementJFusionActivePlugins extends JElement
{
    var $_name = 'JFusionActivePlugins';

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
        $feature = $node->attributes('feature');
        if (!$feature) {
            $feature = 'any';
        }

        $db = JFactory::getDBO();
        $query = 'SELECT name as id, name as name from #__jfusion WHERE status = 1';
        $db->setQuery($query);
        $rows = $db->loadObjectList();

        require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php';
        require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
        require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusionadmin.php';

        foreach ($rows as $key => &$row) {
            if (!JFusionFunctionAdmin::hasFeature($row->name,$feature)) {
                unset($rows[$key]);
            }
        }

        if (!empty($rows)) {
            return JHTML::_('select.genericlist', $rows, $control_name . '[' . $name . ']', 'size="1" class="inputbox"', 'id', 'name', $value);
        } else {
            return JText::_('NO_VALID_PLUGINS');
        }
    }
}
