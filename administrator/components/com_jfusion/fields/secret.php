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
class JFormFieldSecret extends JFormField
{
    public $type = 'Secret';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
		if(!empty($this->value)) {
	        $value = htmlspecialchars(html_entity_decode($this->value, ENT_QUOTES), ENT_QUOTES);
		} else {
			jimport('joomla.user.helper');
			$value = JApplication::getHash(JUserHelper::genRandomPassword());
			$value = substr($value, 0, 10);
		}

		return '<input type="text" name="' . $this->name . '" id="' . $this->id . '" value="' . $value . '" size="16" />';
    }
}
