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
class JFormFieldJFusionActivePlugins extends JFormField
{
    public $type = "JFusionActivePlugins";
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
        $db = JFactory::getDBO();
        $query = 'SELECT name as id, name as name from #__jfusion WHERE status = 1';
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        if (!empty($rows)) {
            return JHTML::_('select.genericlist', $rows, $this->name, 'size="1" class="inputbox"', 'id', 'name', $this->value);
        } else {
            return "<span style='float:left; margin: 5px 0; font-weight: bold;'>" . JText::_('NO_VALID_PLUGINS') . "</span>";
        }
    }
}
