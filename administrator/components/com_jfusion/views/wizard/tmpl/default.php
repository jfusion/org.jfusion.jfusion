<?php

/**
 * This is the jfusion user plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Wizard
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
//display the paypal donation button
JFusionFunctionAdmin::displayDonate();
?>
<div class="jfusion">
	<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
		<input type="hidden" name="task" value="wizardresult" />
		<h1>
			<?php echo JText::_('WIZARD_INSTR'); ?>
		</h1>
		<br><br><br>
		<table style="width:100%;;border-spacing:1px;" class="paramlist jfusiontable">
			<tr>
				<td class="paramlist_key">
					<?php echo JText::_('WIZARD_PATH'); ?>
				</td>
				<td class="paramlist_value">
					<input type="text" name="params[source_path]" id="paramssource_path" value="<?php echo JPATH_ROOT; ?>" class="text_area" size="100" />
				</td>
			</tr>
		</table>
		<br>
		<h1>
			<?php echo JText::_('WIZARD_INSTR2'); ?>
		</h1>
		<br>
		<input type=hidden name=jname value="<?php echo $this->jname; ?>">
	</form>
</div>