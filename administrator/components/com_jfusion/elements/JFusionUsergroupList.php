<?php
/**
* @package JFusion
* @subpackage Elements
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/


// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

/**
* Require the Jfusion plugin factory
*/
require_once JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.factory.php';
require_once JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.jfusion.php';

/**
* Defines the forum select list for JFusion forum plugins
* @package JFusion
*/
class JElementJFusionUsergroupList extends JElement
{
    var $_name = 'JFusionUsergroupList';

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
        global $jname;
		if ($jname){
		    if (JFusionFunction::validPlugin($jname)) {
            	$JFusionPlugin =& JFusionFactory::getAdmin($jname);

            	$grouptype = $node->attributes("grouptype");
            	$usergroups = $JFusionPlugin->getUsergroupList($grouptype);

            	$multiple = $node->attributes("multiple");
            	if (!empty($usergroups)) {
					$multiple = (!empty($multiple)) ? " MULTIPLE " : "";
					$param_name = ($multiple) ? $control_name.'['.$name.'][]' : $control_name.'['.$name.']';
                	return JHTML::_('select.genericlist', $usergroups, $param_name, $multiple,
                	'id', 'name', $value);
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

