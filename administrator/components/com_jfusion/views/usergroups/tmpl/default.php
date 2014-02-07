<?php
/**
 * @package JFusion
 * @subpackage Views
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 *
 * @var $this jfusionViewplugindisplay
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
echo JFusionFunctionAdmin::getDonationBanner();
$images = 'components/com_jfusion/images/';
?>
<div class="jfusion">
	<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
		<input type="hidden" name="task" value="saveusergroups"/>

		<table class="jfusionlist" style="border-spacing:1px;" id="sortables">
			<thead>
				<tr>
					<th class="title" width="20px">
					</th>
					<?php
					foreach ($this->plugins as $plugin) {
					?>
						<th class="title" align="center">
							<?php echo $plugin->name; ?>
						</th>
					<?php
					}
					?>
					<th width="60px">
						<?php echo JText::_('REMOVE'); ?>
					</th>
				</tr>
				<tr>
					<th class="title" width="20px">
					</th>
					<?php
					foreach ($this->plugins as $plugin) {
						$checked = '';
						if ($plugin->update) {
							$checked = 'checked="checked"';
						}
						?>
						<th class="title" align="center">
							<?php
							if ($plugin->master) {
								echo JText::_('Master');
							} else {
							?>
								<input id="updateusergroups_<?php echo $plugin->name; ?>" <?php echo $checked; ?> type="checkbox" name="updateusergroups[<?php echo $plugin->name; ?>]" value="1"> <label for="updateusergroups_<?php echo $plugin->name; ?>"><?php echo JText::_('UPDATE_ON_CHANGE'); ?></label>
							<?php
							}
							?>
						</th>
					<?php
					}
					?>
					<th>
					</th>
				</tr>
			</thead>
			<tbody id="sort_table">
			</tbody>
		</table>
	</form>
</div>

