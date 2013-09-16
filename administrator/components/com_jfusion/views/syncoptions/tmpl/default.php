<?php

/**
 * This is view file for syncoptions
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Syncoptions
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


<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
	<input type="hidden" name="task" value="syncoptions" />
</form>
<div class="jfusion">
	<h3><?php echo JText::_('SYNC_WARNING'); ?></h3><br/>

	<?php
	if ($this->sync_active) {
		echo '<h3 style="color:red;">' . JText::_('SYNC_IN_PROGRESS_WARNING') . "</h3><br />\n" ;
	}
	?>
	<?php if ($this->sync_mode == 'new') { ?>
		<div id="log_res">
			<form method="post" action="index.php" name="syncForm" id="syncForm">
				<input type="hidden" name="option" value="com_jfusion" />
				<input type="hidden" name="task" value="syncstatus" />
				<input type="hidden" name="syncid" value="<?php echo $this->syncid; ?>" />
				<div class="ajax_bar">
					<label for="action"><?php echo JText::_('SYNC_DIRECTION_SELECT'); ?></label>
					<select id="action" name="action" style="margin-right:10px; margin-left:5px;">
						<option value="master"><?php echo JText::_('SYNC_MASTER'); ?></option>
						<option value="slave"><?php echo JText::_('SYNC_SLAVE'); ?></option>
					</select>
					<label for="userbatch"><?php echo JText::_('SYNC_NUMBER_OF_USERS'); ?></label>
					<input id="userbatch" name="userbatch" class="inputbox" style="margin-right:10px; margin-left:5px;" value="500"/>
				</div>
				<br/>

				<table class="jfusionlist" style="border-spacing:1px;">
					<thead>
					<tr>
						<th width="50px"><?php echo JText::_('NAME'); ?></th>
						<th width="50px"><?php echo JText::_('TYPE'); ?></th>
						<th width="50px"><?php echo JText::_('USERS'); ?></th>
						<th width="200px"><?php echo JText::_('OPTIONS'); ?></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><?php echo $this->master_data['jname']; ?></td>
						<td><?php echo JText::_('MASTER') ?></td>
						<td><?php echo $this->master_data['total']; ?></td>
						<td></td>
					</tr>

					<?php
					foreach ($this->slave_data as $slave) { ?>
						<tr>
							<td><label for="plugin<?php echo $slave['jname']; ?>"><?php echo $slave['jname']; ?></label></td>
							<td><?php echo JText::_('SLAVE') ?></td>
							<td><?php echo $slave['total']; ?></td>
							<td>
								<select id="plugin<?php echo $slave['jname']; ?>" name="slave[<?php echo $slave['jname']; ?>][perform_sync]">
									<option value=""><?php echo JText::_('SYNC_EXCLUDE_PLUGIN'); ?></option>
									<option value="<?php echo $slave['jname']; ?>"><?php echo JText::_('SYNC_INCLUDE_PLUGIN'); ?></option>
								</select>
							</td>
						</tr>
					<?php }
					?>
					</tbody>
				</table>
			</form>
		</div>
	<?php
	} else {
	?>
		<div id="log_res">
		</div>
	<?php
	} ?>
	<br/>
	<div id="counter"></div>
	<br/>
	<div class="ajax_bar">
		<strong><?php echo JText::_('SYNC_CONTROLLER'); ?></strong>&nbsp;&nbsp;&nbsp;
		<a id="start" href="#"><?php echo JText::_('START'); ?></a>
	</div>
</div>