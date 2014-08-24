<?php
/**
 * Helper class
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage JFusion_Helper_Mageselectblock
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusion_Helper_Mageselectblock {
	/**
	 * MUST be in a function otherwise a double display is done. One with interpreted code, the other with brute code
	 * I don't know yet why but in this case it works without this double display.
	 *
	 * @param integer $blockId
	 * @return string
	 */
	public static function callblock($blockId = null) {
        $html = '';
		if ($blockId) {
			/**
			 * @var $model Mage_Cms_Helper_Wysiwyg_Images
			 * @var $store Mage_Cms_Model_Page
			 */

			$model = Mage::getModel('cms/block');
			$store = $model->setStoreId(Mage::app ()->getStore()->getId());

            $block = $store->load($blockId);
			if ($block->getIsActive()) {
				$content = $block->getContent();

				/**
				 * @var $processor Mage_Core_Model_Email_Template_Filter
				 */

				$processor = Mage::getModel('core/email_template_filter');
				$html = $processor->filter($content);
			}
		}
		return $html;
	}
}