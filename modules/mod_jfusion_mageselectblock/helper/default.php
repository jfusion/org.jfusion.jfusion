<?php
class JFusion_Helper_Mageselectblock {
	/**
	 * MUST be in a function otherwise a double display is done. One with interpreted code, the other with brute code
	 * I don't know yet why but in this case it works without this double display.
	 *
	 * @param integer $blockId
	 * @return string
	 */
	function callblock($blockId = null) {
		if ($blockId) {
			$block = Mage::getModel ( 'cms/block' )->setStoreId ( Mage::app ()->getStore ()->getId () )->load ( $blockId );
			if (! $block->getIsActive ()) {
				$html = '';
			} else {
				$content = $block->getContent ();
				
				$processor = Mage::getModel ( 'core/email_template_filter' );
				$html = $processor->filter ( $content );
			}
		}
		return $html;
	}
}