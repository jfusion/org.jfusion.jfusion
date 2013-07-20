<?php

/**
 * This is view file for advancedparam
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Advancedparam
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');

$uri = JURI::getInstance();
$uri->delVar('task');
?>
<div class="jfusion">
	<?php echo $this->toolbar; ?>
	<h1>Select Plugin Multi</h1>

	<form action="<?php echo $uri->toString() ?>" method="post" name="adminForm" id="adminForm" class="form-horizontal">
		<table class="paramlist jfusiontable" style="width:100%;border-spacing:1px;">
			<tbody>
			<tr>
				<td class="paramlist_key">JFusion Plugin</td>
				<td class="paramlist_value"><?php echo $this->output; ?></td>
			</tr>
			<tr style="padding:0; margin:0;">
				<td colspan="2" style="padding:0; margin:0;">
					<?php
					global $jname;
					echo JHtml::_('tabs.start','tabs', array('startOffset'=>2));
					foreach ($this->comp as $key => $value) {
						$jname = $key;
						echo JHtml::_('tabs.panel',JText::_($jname), $jname.'_jform_fieldset_label');

						echo '<div align="right"><input type="button" name="remove" value="Remove" onclick="JFusion.removePlugin(this, \'' . $key . '\');" style="margin-left: 3px;" /></div>';
						if (isset($value['form'])) {
							$form = $value['form'];
							$fieldsets = $form->getFieldsets();
							foreach ($fieldsets as $fieldset):
								echo '<fieldset class="jfusionform">';
								$fields = $form->getFieldset($fieldset->name);
								foreach($fields as $field):
									echo JFusionFunctionAdmin::renderField($field);
								endforeach;
								echo '</fieldset>';
							endforeach;
						}
						echo '<input type="hidden" name="params[' . $key . '][jfusionplugin]" value="' . $value['jfusionplugin'] . '" />';
					}
					echo JHtml::_('tabs.end');
					?>
				</td>
			</tr>
			</tbody>
		</table>
		<input type="hidden" name="task" value="advancedparamsubmit" />
		<input type="hidden" name="jfusion_task" value="" />
		<input type="hidden" name="jfusion_value" value="" />
	</form>
</div>