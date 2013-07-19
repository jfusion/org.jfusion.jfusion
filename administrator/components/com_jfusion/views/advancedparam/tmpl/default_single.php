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
$uri->setVar('task','advancedparamsubmit');
?>
<div class="jfusion">
	<h1>Select Plugin Single</h1>
	<form
		action="<?php echo $uri->toString() ?>"
		method="post" name="adminForm" id="adminForm">
		<table class="paramlist jfusiontable" style="width:100%;border-spacing:1px;">
			<tbody>
			<tr>
				<td class="paramlist_key">JFusion Plugin</td>
				<td class="paramlist_value"><?php echo $this->output; ?></td>
			</tr>
			<tr style="padding:0; margin:0;">
				<td colspan="2" style="padding:0; margin:0;">
					<?php
					if (!empty($this->comp)):
						$fieldsets = $this->comp->getFieldsets();
						echo JHtml::_('tabs.start','tabs', array('startOffset'=>2));
						foreach ($fieldsets as $fieldset):
							echo JHtml::_('tabs.panel',JText::_($fieldset->name.'_jform_fieldset_label'), $fieldset->name.'_jform_fieldset_label');
							echo '<fieldset class="panelform">';
							echo '<dl>';
							foreach($this->comp->getFieldset($fieldset->name) as $field):
								// If the field is hidden, just display the input.
								if ($field->hidden):
									echo $field->input;
								else:
									echo '<dt>' . $field->label . '</dt>';
									echo '<dd' . (($field->type == 'Editor' || $field->type == 'Textarea') ? ' style="clear: both; margin: 0;"' : '') . '>';
									echo $field->input;
									echo '</dd>';
								endif;
							endforeach;
							echo '</dl>';
							echo '</fieldset>';
						endforeach;
						echo JHtml::_('tabs.end');
					endif;
					?>
				</td>
			</tr>
			<tr>
				<td colspan="2"><input type="submit" value="Save" /></td>
			</tr>
			</tbody>
		</table>
	</form>
</div>
