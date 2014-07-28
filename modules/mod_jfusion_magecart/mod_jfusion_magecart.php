<?php
/**
 * @package JFusion
 * @subpackage Modules
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
use Psr\Log\LogLevel;

defined('_JEXEC') or trigger_error('Restricted access');

try {
	if (JPluginHelper::importPlugin('system', 'magelib')) {
	    /**
	     * @ignore
	     * @var $params JRegistry
	     */
		$template_selection = $params->get('template_selection', 1); // Use the Magento template by default
		$moduleclass_sfx = $params->get('moduleclass_sfx');
		if ($template_selection) {
			$mage_template_path = $params->get('mage_template_path', 'checkout/cart/sidebar.phtml');
		} else {
	        $mage_template_path = '';
	    }

		$plgMageLib = new plgSystemMagelib();

		/* Content of Magento logic, blocks or else */

		if ($params->get('enable_scriptaculous', 0)) {
			/*@todo - find a way to allow compatibility between Mootools and Prototype */
			$document = JFactory::getDocument();
			$document->addScript($plgMageLib->getMageUrl() . "/js/index.php?c=auto&amp;f=,prototype/prototype.js,prototype/validation.js,scriptaculous/builder.js,scriptaculous/effects.js,scriptaculous/dragdrop.js,scriptaculous/controls.js,scriptaculous/slider.js,varien/js.js,varien/form.js,varien/menu.js,mage/translate.js,mage/cookies.js");
		}

		$plgMageLib->destroyTemporaryJoomlaSession();
		if ($plgMageLib->loadAndStartMagentoBootstrap()) {
			$plgMageLib->startMagentoSession();

			/* Content of Magento logic, blocks or else */

			if ($template_selection) {
				/**
				 * @ignore
				 * @var $layout Mage_Core_Model_Layout
				 * @var $block Mage_Core_Block_Template
				 */
				$layout = Mage::getSingleton('core/layout');
				$block = $layout->createBlock('checkout/cart_sidebar');
				$block->setTemplate($mage_template_path);
				echo $block->RenderView();
			} else {
				$cart = Mage::getSingleton('checkout/cart');
				$cart_help = Mage::helper('checkout/url');
				$classname = Mage::getConfig()->getBlockClassName('checkout/cart_sidebar');
				$sidebar = new $classname();

				require (JModuleHelper::getLayoutPath('mod_jfusion_magecart'));
			}

			/* EOF */

			$plgMageLib->stopMagentoSession ();
		}

		$plgMageLib->restartJoomlaSession ();
	} else {
		throw new RuntimeException(JText::_('Plugin system magelib not installed or activated!'));
	}
} catch (Exception $e) {
	\JFusion\Framework::raise(LogLevel::ERROR, $e, 'mod_jfusion_magecart');
	echo $e->getMessage();
}