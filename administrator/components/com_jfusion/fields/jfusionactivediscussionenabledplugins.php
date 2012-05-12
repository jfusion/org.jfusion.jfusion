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
class JFormFieldJFusionActiveDiscussionEnabledPlugins extends JFormField
{
    public $type = "JFusionActiveDiscussionEnabledPlugins";
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
    protected function getInput()
    {
		$lang = JFactory::getLanguage();
		$lang->load( strtolower('plg_content_jfusion'), JPATH_ADMINISTRATOR);

        $db = JFactory::getDBO();
        $query = 'SELECT name as id, name as name from #__jfusion WHERE status = 1 AND discussion = 1';
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        if (!empty($rows)) {
            return JHTML::_('select.genericlist', $rows, $this->name, 'size="1" class="inputbox"', 'id', 'name', $this->value);
        } else {
            return "<span style='float:left; margin: 5px 0; font-weight: bold;'>" . JText::_('NO_DISCUSSION_ENABLED_PLUGINS') . "</span>";
        }
    }
}
