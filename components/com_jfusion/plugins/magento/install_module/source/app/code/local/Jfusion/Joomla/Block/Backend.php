<?php
class Jfusion_Joomla_Block_Backend extends Mage_Core_Block_Template {
	
	public function __construct() {
		$this->setTemplate ( 'joomla/backend.phtml' );
	}
	
	public function getCustomAdminUrl() {
		return Mage::helper('joomla')->getData ( 'custom_adminurl' );
	}
	
	public function getJoomlaBaseUrl() {
		return Mage::helper('joomla')->getData ( 'baseurl' );
	}
}