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

<form method="post" action="index.php" name="adminForm" id="adminForm">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="wizardresult" />
	
	<table>
		<tr>
			<td width="100px">
				<img src="components/com_jfusion/images/jfusion_large.png" height="75px" width="75px">
			</td>
			<td width="100px">
				<img src="components/com_jfusion/images/wizard.png" height="75px" width="75px">
			<td>
				<h2>
					<?php echo $this->jname . ' ' . JText::_('SETUP_WIZARD'); ?>
				</h2>
			</td>
		</tr>
	</table>
	<br/><br/>
	<font size="2">
		<?php echo JText::_('WIZARD_INSTR'); ?>
	</font>
	<br><br><br>
	<table style="width:100%;;border-spacing:1px;" class="paramlist admintable">
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
	<font size="2">
		<?php echo JText::_('WIZARD_INSTR2'); ?>
	</font>
	<br>
	<input type=hidden name=jname value="<?php echo $this->jname; ?>">
</form>
