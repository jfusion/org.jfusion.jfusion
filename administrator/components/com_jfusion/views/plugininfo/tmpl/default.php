<?php

/**
 * This is view file for wizard
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Plugindisplay
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
//display the paypal donation button
?>
<h1><?php echo $this->jname.' '.JText::_('FEATURES'); ?></h1>
<table>
	<?php foreach ($this->features as $cname => $category) { ?>
		<?php foreach ($category as $name => $value) { ?>
		<tr>
			<td width="160px">
				<?php echo JText::_($name); ?>
			</td>
			<td>
				<?php echo $value; ?>
			</td>					
		</tr>
		<?php } ?>	
	<?php } ?>
</table>