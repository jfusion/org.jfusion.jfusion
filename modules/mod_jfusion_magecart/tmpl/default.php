<?php
/**
* @package JFusion
* @subpackage Modules
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC') or die('Restricted access'); ?>

<?php $_cartQty = $cart->getSummaryQty() ?>
<?php if ($_cartQty>0): ?>
	<?php if ($_cartQty==1): ?>
		<?php echo JText::sprintf('There is <a href="%s"><strong>1 item</strong></a> in your cart.', Mage::getUrl('checkout/cart')) ?>
	<?php else: ?>
	<?php echo JText::sprintf('There are <a href="%s"><strong>%s items</strong></a> in your cart.', Mage::getUrl('checkout/cart'), $_cartQty) ?>
<?php endif ?>

<p class="subtotal"><?php echo JText::_('Cart Subtotal:') ?> <strong><?php echo Mage::helper('checkout')->formatPrice($sidebar->getSubtotal()) ?></strong>
<?php $_subtotalInclTax = $sidebar->getSubtotalInclTax(); if ($_subtotalInclTax): ?> <br /> (<strong><?php echo Mage::helper('checkout')->formatPrice($_subtotalInclTax) ?></strong>
<?php echo Mage::helper('tax')->getIncExcText(true) ?>) <?php endif; ?>
</p>
<?php endif ?>

<?php if($_cartQty && Mage::helper('checkout')->canOnepageCheckout()): ?>
<div class="actions">
	<button class="form-button" type="button" onclick="window.location.href='<?php echo Mage::helper('checkout/url')->getCheckoutUrl(); ?>';">
	<span><?php echo JText::_('CHECKOUT') ?></span></button>
</div>
<?php endif ?>

<?php $_items = $sidebar->getRecentItems() ?>
<?php if(!count($_items)): ?>
<div class="content">
	<p><?php echo JText::_('You have no items in your shopping cart.') ?></p>
</div>
<?php endif ?>