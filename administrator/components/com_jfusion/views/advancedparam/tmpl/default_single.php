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
$uri = JUri::getInstance();
$uri->setVar('task', 'advancedparamsubmit');
?>
<div class="jfusion">
	<?php echo $this->toolbar; ?>
	<h1>Select Plugin Single</h1>
	<form
		action="<?php echo $uri->toString() ?>"
		method="post" name="adminForm" id="adminForm" class="form-horizontal">
		<table class="paramlist jfusiontable" style="width:100%;border-spacing:1px;">
			<tbody>
			<tr>
				<td class="paramlist_key">JFusion Plugin</td>
				<td class="paramlist_value"><?php echo $this->output; ?></td>
			</tr>
			<tr style="padding:0; margin:0;">
				<td colspan="2" style="padding:0; margin:0;">
					<?php
					/**
					 * @var $form JForm
					 */
					if (!empty($this->comp) && !empty($this->comp['form'])):
						$form = $this->comp['form'];
						$fieldsets = $form->getFieldsets();
						echo JHtml::_('tabs.start', 'tabs', array('startOffset' => 2));
						foreach ($fieldsets as $fieldset):
							echo JHtml::_('tabs.panel',JText::_($fieldset->name . '_jform_fieldset_label'), $fieldset->name . '_jform_fieldset_label');
							echo '<fieldset class="jfusionform">';
							$fields = $this->comp->getFieldset($fieldset->name);
							foreach($fields as $field):
								echo JFusionFunctionAdmin::renderField($field);
							endforeach;
							echo '</fieldset>';
						endforeach;
						echo JHtml::_('tabs.end');
					endif;
					?>
				</td>
			</tr>
			</tbody>
		</table>
	</form>
</div>
