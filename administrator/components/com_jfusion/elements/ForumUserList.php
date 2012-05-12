<?php
/**
 * This is the jfusion Discussionbot element file
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
 * JFusion Element class Discussionbot
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JElementForumUserList extends JElement
{
    var $_name = "ForumUserList";
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
        global $jname;
        if ($jname) {
            if (JFusionFunction::validPlugin($jname)) {
                $JFusionPlugin = & JFusionFactory::getForum($jname);
                $users = $JFusionPlugin->getUserList();
                if (!empty($users)) {
                    return JHTML::_('select.genericlist', $users, $control_name . '[' . $name . ']', '', 'id', 'name', $value);
                } else {
                    return '';
                }
            } else {
                return JText::_('SAVE_CONFIG_FIRST');
            }
        } else {
            return 'Programming error: You must define global $jname before the JParam object can be rendered';
        }
    }
}
