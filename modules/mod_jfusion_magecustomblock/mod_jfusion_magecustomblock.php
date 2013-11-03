<?php
/**
 * @package JFusion
 * @subpackage Modules
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined ( '_JEXEC' ) or trigger_error ( 'Restricted access' );
/**
 * @ignore
 * @var $params JRegistry
 * @var $module object
 */
if (JPluginHelper::importPlugin ( 'system', 'magelib' )) {
	
	$plgMageLib = new plgSystemMagelib ( );
	$plgMageLib->destroyTemporaryJoomlaSession ();
	$app = $plgMageLib->loadAndStartMagentoBootstrap ();
	if ($app) :
		$plgMageLib->startMagentoSession ();
		
		/* Content of Magento logic, blocks or else */
			
		if($params->get ( 'enable_scriptaculous', 0 )){
			/*@todo - find a way to allow compatibility between Mootools and Prototype */
			$document = JFactory::getDocument();
			$document->addScript($plgMageLib->getMageUrl() . "/js/index.php?c=auto&amp;f=,prototype/prototype.js,prototype/validation.js,scriptaculous/builder.js,scriptaculous/effects.js,scriptaculous/dragdrop.js,scriptaculous/controls.js,scriptaculous/slider.js,varien/js.js,varien/form.js,varien/menu.js,mage/translate.js,mage/cookies.js");
		}
		
		$xml_output = $params->get ( 'xml_output', '' );

		/**
		 * @ignore
		 * @var $layout Mage_Core_Model_Layout
		 * @var $block Mage_Core_Block_Template
		 */

		if(strlen($xml_output) <= 0){
		$block_type = $params->get ( 'block_type', '' );
		$block_name = $params->get ( 'block_name', '' );
		$mage_template_path = $params->get ( 'mage_template_path', '' );
		
			if ($block_type != '' && $block_name != '' ) {
				$layout = Mage::getSingleton ( 'core/layout' );
				$block = $layout->createBlock ( $block_type, $block_name );
				if($mage_template_path){
				    $block->setTemplate ( $mage_template_path );
				    $output = $block->RenderView ();
				}else{
				    $output = $block->toHtml();    
				}
				echo $output;
			} else {
				JFusionFunction::raiseNotice('MODULE_BAD_CONFIGURED: ' . $module->title );
			}
		}else{
			$xml = '<block type="core/text_list" name="content">' . $xml_output . '</block>';
			$layout = Mage::getSingleton('core/layout');
			$update = $layout->getUpdate();
			$update->resetHandles();
			$update->resetUpdates();
			$update->addUpdate($xml);
			$update->setCacheId('JOOMLAMOD_' . $module->id . md5($module->title));
			$update->addHandle('JOOMLA_' . $module->id)->load();
			$layout->generateXml()->generateBlocks();
			$layout->addOutputBlock('content');
			echo $layout->getOutput();
		}
		/* EOF */
		
		$plgMageLib->stopMagentoSession ();
		
		endif;
	$plgMageLib->restartJoomlaSession ();
} else {
	JFusionFunction::raiseWarning(JText::_ ( 'Plugin system magelib not installed or activated!' ) );
}