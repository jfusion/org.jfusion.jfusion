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
JFusionFunctionAdmin::displayDonate();
$images = 'components/com_jfusion/images/';
?>
<script type="text/javascript">
	//<![CDATA[
	window.addEvent('domready',function() {
		JFusion.createRows();
	});
	//]]>
</script>
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
					<th width="100px">
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
							<input id="updateusergroups_<?php echo $plugin->name; ?>" <?php echo $checked; ?> type="checkbox" name="updateusergroups[<?php echo $plugin->name; ?>]" value="1"> <?php echo JText::_('UPDATE_ON_CHANGE'); ?>
						</th>
					<?php
					}
					?>
					<th width="100px">
					</th>
				</tr>
			</thead>
			<tbody id="sort_table">
			</tbody>
		</table>
	</form>
</div>

