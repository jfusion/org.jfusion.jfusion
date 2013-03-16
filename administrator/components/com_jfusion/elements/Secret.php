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
* Generates a secret key if value is empty
* @package JFusion
*/
class JElementSecret extends JElement
{
    var $_name = 'Secret';

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
		if(!empty($value)){
	        $value = htmlspecialchars(html_entity_decode($value, ENT_QUOTES), ENT_QUOTES);
		} else {
			jimport('joomla.user.helper');
			$value = JUtility::getHash( JUserHelper::genRandomPassword());
			$value = substr($value, 0, 10);  
		}
		
		return '<input type="text" name="'.$control_name.'['.$name.']" id="'.$control_name.$name.'" value="'.$value.'" size="16" />';
    }
}
