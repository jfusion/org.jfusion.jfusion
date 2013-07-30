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
JFusion.response = { 'completed' : false , 'slave_data' : [] , 'errors' : [] };
JFusion.syncMode = '<?php echo $this->sync_mode;?>';
JFusion.syncid = '<?php echo $this->syncid; ?>';

JFusion.periodical;

// refresh every 10 seconds
JFusion.syncRunning = -1;
JFusion.counter = 10;
JFusion.undateInterval = 1000;

JFusion.renderSyncHead = function() {
	var root = new Element('thead');
	var tr = new Element('tr');

	new Element('th',{'html': JFusion.JText('PLUGIN') + ' ' + JFusion.JText('NAME')}).inject(tr);
	new Element('th',{'html': JFusion.JText('SYNC_PROGRESS'), 'width': 200}).inject(tr);
	new Element('th',{'html': JFusion.JText('SYNC_USERS_TODO')}).inject(tr);
	new Element('th',{'html': JFusion.JText('USERS') + ' ' + JFusion.JText('CREATED')}).inject(tr);
	new Element('th',{'html': JFusion.JText('USERS') + ' ' + JFusion.JText('DELETED')}).inject(tr);
	new Element('th',{'html': JFusion.JText('USERS') + ' ' + JFusion.JText('UPDATED')}).inject(tr);
	new Element('th',{'html': JFusion.JText('USER') + ' ' + JFusion.JText('CONFLICTS')}).inject(tr);
	new Element('th',{'html': JFusion.JText('USERS') + ' ' + JFusion.JText('UNCHANGED')}).inject(tr);

	tr.inject(root);
	return root;
};

JFusion.renderSyncBody = function (data) {
	var root = new Element('tBody');
	for (var i=0; i<data.slave_data.length; i++) {
		var info = data.slave_data[i];
		var tr = new Element('tr');

		//NAME
		new Element('td',{'html': info.jname , 'width': 200}).inject(tr);

		// SYNC_PROGRESS
		var outer = new Element('div').inject(tr);

		var pct = 0;
		var synced = info.total_to_sync-info.total;
		if (synced !== 0 && info.total_to_sync !== 0) {
			pct = (synced/info.total_to_sync) * 100;
		}
		var color = 'blue';
		if (pct === 100) {
			color = 'green';
		}
		new Element('div',{'style': 'background-color:'+color+'; width:'+pct+'%','html': '&nbsp;'}).inject(outer);

		var progress = new Element('td');
		outer.inject(progress);
		progress.inject(tr);

		//SYNC_USERS_TODO
		new Element('td',{'html': info.total_to_sync-synced}).inject(tr);
		//CREATED
		new Element('td',{'html': info.created}).inject(tr);
		//DELETED
		new Element('td',{'html': info.deleted}).inject(tr);
		//UPDATED
		new Element('td',{'html': info.updated}).inject(tr);
		//CONFLICTS
		new Element('td',{'html': info.error}).inject(tr);
		//UNCHANGED
		new Element('td',{'html': info.unchanged}).inject(tr);

		tr.inject(root);
	}
	return root;
};

JFusion.renderSync = function(data) {
	var log_res = $('log_res');
	log_res.empty();

	var root = new Element('table',{ 'class': 'jfusionlist' });
	JFusion.renderSyncHead().inject(root);
	JFusion.renderSyncBody(data).inject(root);

	root.inject(log_res);
};

JFusion.startSync = function() {
	JFusion.syncRunning = 1;

	/* our usersync status update function: */
	var refresh = (function() {
		//add another second to the counter
		JFusion.counter -= 1;
		if (JFusion.counter < 1) {
			if (!JFusion.response.completed) {
				JFusion.counter = 10;

				/* our ajax istance for starting the sync */
				new Request.JSON({
					url: JFusion.url,
					noCache: true,
					onSuccess: function(JSONobject) {
						JFusion.render(JSONobject);
					}, onError: function(JSONobject) {
						JFusion.OnError(JSONobject);
						JFusion.stopSync();
					}
				}).get({'option': 'com_jfusion',
						'task': 'syncprogress',
						'tmpl': 'component',
						'syncid': JFusion.syncid});

				new Request.JSON({
					url: JFusion.url,
					noCache: true,
					onSuccess: function(JSONobject) {
						JFusion.render(JSONobject);
					}, onError: function(JSONobject) {
						JFusion.OnError(JSONobject);
						JFusion.stopSync();
					}
				}).get({'option': 'com_jfusion',
						'task': 'syncresume',
						'tmpl': 'component',
						'syncid': JFusion.syncid});
			}
		} else {
			JFusion.update();
		}
	});

	JFusion.periodical = refresh.periodical(JFusion.undateInterval, this);

	JFusion.renderSync(JFusion.response);
};

JFusion.stopSync = function() {
	JFusion.syncRunning = 0;
	// let's stop our timed ajax
	clearInterval(JFusion.periodical);
};

JFusion.update = function() {
	if (JFusion.syncRunning != -1) {
		var text;
		var start = $('start');
		if (JFusion.response.completed) {
			JFusion.stopSync();
			text = JFusion.JText('FINISHED');

			start.set('html', '<strong>'+JFusion.JText('CLICK_FOR_MORE_DETAILS')+'</strong>');
			start.set('href', 'index.php?option=com_jfusion&task=syncstatus&syncid='+JFusion.syncid);
			start.removeEvents('click');
		} else if (JFusion.syncRunning === 0) {
			text = JFusion.JText('PAUSED');

			start.set('html', JFusion.JText('RESUME'));
		} else {
			text = JFusion.JText('UPDATE_IN')+ ' ' + JFusion.counter + ' '+JFusion.JText('SECONDS');

			start.set('html', JFusion.JText('PAUSE'));
		}
		$('counter').set('html', '<strong>'+text+'</strong>');
	}
};

JFusion.render = function(JSONobject) {
	JFusion.response = JSONobject;

	JFusion.OnMessages(JSONobject.messages);
	if (JSONobject.messages.error) {
		JFusion.stopSync();
	} else {
		JFusion.renderSync(JSONobject);

		if (JSONobject.completed) {
			JFusion.update();
		}
	}
};

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