<?php

/**
 * This is the jfusion Usergroups element file
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
 * JFusion Element class Usergroups
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldJFusionUsergroups extends JFormField
{
    public $type = 'JFusionUsergroups';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
        global $jname;
        if ($jname) {
            if (JFusionFunction::validPlugin($jname) || $jname == 'joomla_int') {
                $JFusionPlugin = JFusionFactory::getAdmin($jname);
                $usergroups = $JFusionPlugin->getUsergroupList();
                $multiple = $this->multiple;
                if (!empty($usergroups)) {
                    $multiple = (!empty($multiple)) ? " MULTIPLE " : "";
                    $param_name = ($multiple) ? $this->name . '[]' : $this->name;
                    return JHTML::_('select.genericlist', $usergroups, $param_name, $multiple, 'id', 'name', $this->value);
                } else {
                    return '';
                }
            } else {
                $output = "<span style='float:left; margin: 5px 0; font-weight: bold;'>";
                $output.= JText::_('SAVE_CONFIG_FIRST');
                $output.= "</span>";
                return $output;
            }
        } else {
            $output = "<span style='float:left; margin: 5px 0; font-weight: bold;'>";
            $output.= 'Programming error: You must define global $jname before the JParam object can be rendered';
            $output.= "</span>";
            return $output;
        }
    }
}
