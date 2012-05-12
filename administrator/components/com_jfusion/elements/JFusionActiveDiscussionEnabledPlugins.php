<?php

/**
 * This is the jfusion ActiveDiscussionEnabledPlugins element file
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

require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';

/**
 * JFusion Element class ActiveDiscussionEnabledPlugins
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JElementJFusionActiveDiscussionEnabledPlugins extends JElement
{
    var $_name = "JFusionActiveDiscussionEnabledPlugins";
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
        JFusionFunction::loadLanguage('plg','content','jfusion');
        $db = JFactory::getDBO();
        $query = 'SELECT name as id, name as name from #__jfusion WHERE status = 1 AND discussion = 1';
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        if (!empty($rows)) {
            return JHTML::_('select.genericlist', $rows, $control_name . '[' . $name . ']', 'size="1" class="inputbox"', 'id', 'name', $value);
        } else {
            return JText::_('NO_DISCUSSION_ENABLED_PLUGINS');
        }
    }
}
