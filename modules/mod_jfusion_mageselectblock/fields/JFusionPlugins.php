<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DS .'components'.DS.'com_jfusion'.DS.'fields'.DS.'jformfieldjfusionactiveplugins.php';

class JFieldJFusionMagentoPlugins extends JFormFieldJFusionActivePlugins{
	
    public $type = "JFusionMagentoPlugins";
    
	protected function getInput(){
		
		return parent::getInput();
		
	}
}