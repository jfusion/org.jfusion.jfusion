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
require_once JPATH_ADMINISTRATOR . '/components/com_jfusion/import.php';
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
	    try {
	        if ($jname) {
		        $JFusionPlugin = \JFusion\Factory::getAdmin($jname);
	            if ($JFusionPlugin->isConfigured() || $jname == 'joomla_int') {
	                $usergroups = $JFusionPlugin->getUsergroupList();
	                $multiple = $this->multiple;
	                if (!empty($usergroups)) {
	                    $multiple = (!empty($multiple)) ? ' MULTIPLE ' : '';
	                    $param_name = ($multiple) ? $this->name . '[]' : $this->name;
		                $output = JHTML::_('select.genericlist', $usergroups, $param_name, $multiple, 'id', 'name', $this->value);
	                } else {
		                $output = '';
	                }
	            } else {
		            throw new RuntimeException(JText::_('SAVE_CONFIG_FIRST'));
	            }
	        } else {
		        throw new RuntimeException('Programming error: You must define global $jname before the JParam object can be rendered.');
	        }
	    } catch (Exception $e) {
		    $output = '<span style="float:left; margin: 5px 0; font-weight: bold;">' . $e->getMessage() . '</span>';
	    }
	    return $output;
    }
}
