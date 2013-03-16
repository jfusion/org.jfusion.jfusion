<?php

/**
 * This is the jfusion Plugins element file
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
 * JFusion Element class Plugins
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JElementJFusionPlugins extends JElement
{
    var $_name = 'JFusionPlugins';

    /**
     * Get an element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param JSimpleXMLElement &$node        node of element
     * @param string $control_name name of controller
     *
     * @return string|void html
     */
    function fetchElement($name, $value, &$node, $control_name)
    {
        /**
         * @ignore
         * @var $db JDatabase
         */
        $db = JFactory::getDBO();
        $query = 'SELECT name as id, name as name from #__jfusion';
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        if (!empty($rows)) {
            return JHTML::_('select.genericlist', $rows, $control_name . '[' . $name . ']', 'size="1" class="inputbox"', 'id', 'name', $value);
        } else {
            return JText::_('NO_VALID_PLUGINS');
        }
    }
}
