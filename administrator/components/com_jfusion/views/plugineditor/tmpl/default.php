<?php

/**
 * This is view file for plugineditor
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Plugineditor
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
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="action" value="" />
	<input type="hidden" name="customcommand" value="" />
	<style type="text/css">

	</style>
	<?php
	if ($this->form) {
		$params = $this->form->getGroup('params');

		echo JHtml::_('tabs.start', 'params');
		// Iterate through the extra form fieldsets and display each one.
		foreach ($this->form->getFieldsets("params") as $fieldsets => $fieldset):
			echo JHtml::_('tabs.panel', JText::_($fieldset->name), $fieldsets);
			?>
			<table>
				<?php
				// Iterate through the fields and display them.
				foreach($this->form->getFieldset($fieldset->name) as $field):
					// If the field is hidden, just display the input.
					?>
					<?php
					if ($field->hidden):
						echo $field->input;
					else:
						?>
						<tr>
							<td>
								<?php echo $field->label; ?>
							</td>
							<td>
								<?php echo $field->input; ?>
							</td>
						</tr>
					<?php
					endif;
					?>

				<?php
				endforeach;
				?>
			</table>
		<?php

		endforeach;
		?>
		<?php echo JHtml::_('tabs.end'); ?>
	<?php

	}
	?>
	<input type="hidden" name="jname" value="<?php echo $this->jname; ?>"/>
</form>