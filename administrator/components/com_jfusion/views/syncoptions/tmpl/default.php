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

<script type="text/javascript">
<!--
JFusion.slaveData = <?php echo json_encode($this->slave_data);?>;

JFusion.syncMode = '<?php echo $this->sync_mode;?>';
JFusion.syncid = '<?php echo $this->syncid; ?>';

window.addEvent('domready', function() {
		// start and stop click events
		$('start').addEvent('click', function(e) {
			// prevent default
			e.stop();
			if (JFusion.syncRunning == 1) {
				JFusion.stopSync();
			} else {
				// prevent insane clicks to start numerous requests
				clearInterval(JFusion.periodical);

				if (JFusion.syncMode == 'new') {
					var form = $('syncForm');
					var count = 0;

					if (form) {
						var select = form.getElements('select[name^=slave]');

						select.each(function (el) {
							var value = el.get('value');
							if (value) {
								JFusion.response.slave_data[count] = {
									"jname": value,
									"total": JFusion.slaveData[value]['total'],
									"total_to_sync": JFusion.slaveData[value]['total'],
									"created": 0,
									"deleted": 0,
									"updated": 0,
									"error": 0,
									"unchanged": 0};
								count++;
							}
						});
					}
					if (JFusion.response.slave_data.length) {
						//give the user a last chance to opt-out
						var answer = confirm(JFusion.JText('SYNC_CONFIRM_START'));
						if (answer) {
							JFusion.syncMode = 'resume';
							//do start
							new Request.JSON({
								url: JFusion.url,
								noCache: true,
								onSuccess: function (JSONobject) {
									JFusion.render(JSONobject);
								}, onError: function (JSONobject) {
									JFusion.OnError(JSONobject);
									JFusion.stopSync();
								}}).get(form.toQueryString() + '&option=com_jfusion&task=syncinitiate&tmpl=component&syncid=' + JFusion.syncid);
							JFusion.startSync();
						}
					} else {
						JFusion.OnError(JFusion.JText('SYNC_NODATA'));
					}
				} else {
					JFusion.startSync();
				}
			}
			JFusion.update();
		});
	}
);
// -->
</script>
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
		<script type="text/javascript">
			<!--
			JFusion.response = <?php echo json_encode($this->syncdata);?>;
			JFusion.renderSync(JFusion.response);
			// -->
		</script>
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