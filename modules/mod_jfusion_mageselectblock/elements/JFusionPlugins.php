<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DS .'components'.DS.'com_jfusion'.DS.'elements'.DS.'JFusionPlugins.php';

class JElementJFusionMagentoPlugins extends JElementJFusionPlugins{
	
	function fetchElement($name,$value,&$node,$control_name){
		
		return parent::fetchElement($name,$value,$node,$control_name);
		
	}
}
?>