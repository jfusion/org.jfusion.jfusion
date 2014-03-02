<?php

/**
 * This is view file for syncError
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage SyncError
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
//display the paypal donation button
echo JFusionFunctionAdmin::getDonationBanner();
?>
<div class="jfusion">
	<table>
		<tr>
			<td width="100px">
				<img src="components/com_jfusion/images/jfusion_large.png">
			</td>
			<td width="100px">
				<img src="components/com_jfusion/images/usersync.png">
			</td>
			<td>
				<h2>
					<?php echo JText::_('RESOLVE_CONLFICTS'); ?>
				</h2>
			</td>
		</tr>
	</table>
	<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
		<input type="hidden" name="task" value="resolvesyncerror" />
		<input type="hidden" name="syncid" value="<?php echo $this->syncid; ?>" />

	<?php
	if (!empty($this->synclog)) {
		?>
		<br/>
		<br/>
		<h2>
			<?php echo JText::_('CONFLICT_INSTRUCTION'); ?>
		</h2>
		<br/>
		<h3>
			<?php echo JText::_('EMAIL') . ' ' . JText::_('CONFLICTS'); ?>
		</h3>
		<h2>
			<?php echo JText::_('CONFLICTS_EMAIL'); ?>
		</h2>
		<br/>
		<h3>
			<?php echo JText::_('USERNAME') . ' ' . JText::_('CONFLICTS'); ?>
		</h3>
		<h2>
			<?php echo JText::_('CONFLICTS_USERNAME'); ?>
		</h2>
		<br/>
		<h3>
			<?php echo JText::_('USERSYNC') . ' ' . JText::_('ERROR'); ?>
		</h3>
		<h2>
			<?php echo JText::_('CONFLICTS_ERROR'); ?>
		</h2>
		<br/>
		<br/>

		<div id="ajax_bar">
			<label for="default_value"><?php echo JText::_('APPLY_ACTION_ALL_CONFLICTS'); ?></label>
			<?php
			$action = $this->syncdata['action'];
			if ($action == 'slave') {
			    $user = JText::_('MASTER');
			    $conflict = JText::_('SLAVE');
			} else {
			    $user = JText::_('SLAVE');
			    $conflict = JText::_('MASTER');
			}
			?>
			<select id="default_value" name="default_value">
				<option value="0"><?php echo JText::_('IGNORE') ?></option>
				<option value="1"><?php echo JText::_('UPDATE') . ' ' . $user . ' ' . JText::_('USER') ?></option>
				<option value="2"><?php echo JText::_('UPDATE') . ' ' . $conflict . ' ' . JText::_('USER') ?></option>
				<option value="3"><?php echo JText::_('DELETE') . ' ' . $user . ' ' . JText::_('USER') ?></option>
				<option value="4"><?php echo JText::_('DELETE') . ' ' . $conflict . ' ' . JText::_('USER') ?></option>
			</select>
			<a href="javascript:void(0);"  onclick="JFusion.applyAll();">
				<?php echo JText::_('APPLY'); ?>
			</a>
		</div>

		<table class="jfusionlist" style="border-spacing:1px;">
			<thead>
				<tr>
					<th class="title" width="20px">
						<?php echo JText::_('ID'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('TYPE'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('PLUGIN') . ' ' . JText::_('NAME') . ': ' . JText::_('USERID') . ' / ' . JText::_('USERNAME') . ' / ' . JText::_('EMAIL'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('CONFLICT'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('DETAILS'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('ACTION'); ?>
					</th>
				</tr>
			</thead>
			<tbody>
			<?php $row_count = 0;
			foreach ($this->synclog as $i => $details) {
			    $error = unserialize($details->data);

			    ?>
			    <tr class="row<?php echo ($row_count % 2); ?>">
			    <?php
	                $row_count++;
				    //just some checks to prevent php notices
				    if (!is_object($error['conflict']['userinfo'])) {
				        $error['conflict']['userinfo'] = new stdClass();
				    }
				    if (!is_object($error['user']['userlist'])) {
				        $error['user']['userinfo'] = new stdClass();
				    }
				    $ary = array('username', 'userid', 'email');
				    foreach ($ary as $a) {
				        if (!isset($error['conflict']['userinfo']->$a)) {
				            $error['conflict']['userinfo']->$a = '';
				        }
				        if (!isset($error['user']['userinfo']->$a)) {
				            $error['user']['userinfo']->$a = '';
				        }
				    }
				    ?>
				    <td>
					    <?php echo $i; ?>
					    <input type="hidden" name="syncError[<?php echo $i; ?>][user_jname]" value="<?php echo $error['user']['jname'] ?>" />
					    <input type="hidden" name="syncError[<?php echo $i; ?>][conflict_jname]" value="<?php echo $error['conflict']['jname'] ?>" />
					    <input type="hidden" name="syncError[<?php echo $i; ?>][user_username]" value="<?php echo $error['user']['userlist']->username ?>" />
					    <input type="hidden" name="syncError[<?php echo $i; ?>][conflict_username]" value="<?php echo $error['conflict']['userinfo']->username ?>" />
				    </td>
				    <td>
					    <?php
					    //check to see what sort of an error it is
					    if (empty($error['conflict']['userinfo']->username)) {
					        $error_type = 'ERROR';
					    } elseif ($error['user']['userinfo']->username != $error['conflict']['userinfo']->username) {
					        $error_type = 'USERNAME';
					    } elseif ($error['user']['userinfo']->email != $error['conflict']['userinfo']->email) {
					        $error_type = 'EMAIL';
					    } else {
					        $error_type = 'ERROR';
					    }
					    ?>
					    <label for="syncError<?php echo $i; ?>"><?php echo JText::_($error_type); ?></label>
				    </td>
				    <td>
					    <?php echo $error['user']['jname'] . ': ' . $error['user']['userinfo']->userid . ' / ' . $error['user']['userlist']->username . ' / ' . $error['user']['userlist']->email; ?>
				    </td>
				    <td>
					    <?php
					    if ($error_type != 'ERROR') {
					        echo $error['conflict']['jname'] . ': ' . $error['conflict']['userinfo']->userid . ' / ' . $error['conflict']['userinfo']->username . ' / ' . $error['conflict']['userinfo']->email;
					    }
					    ?>
				    </td>
				    <td>
				        <a class="modal btn" rel="{handler: 'iframe', size: {x: 650, y: 375}}" href="index.php?option=com_jfusion&amp;task=syncerrordetails&amp;syncid=<?php echo $this->syncdata['syncid']; ?>&amp;tmpl=component&amp;errorid=<?php echo $i; ?>"><?php echo JText::_('DETAILS'); ?></a>
				    </td>
				    <td>
					    <?php
					    if ($error_type != 'ERROR') { ?>
					        <select id="syncError<?php echo $i; ?>" name="syncError[<?php echo $i; ?>][action]">
						        <option value="0"><?php echo JText::_('IGNORE') ?></option>
						        <option value="1"><?php echo JText::_('UPDATE') . ' ' . $error['user']['jname'] . ' ' . JText::_('USER') ?></option>
						        <option value="2"><?php echo JText::_('UPDATE') . ' ' . $error['conflict']['jname'] . ' ' . JText::_('USER') ?></option>
						        <option value="3"><?php echo JText::_('DELETE') . ' ' . $error['user']['jname'] . ' ' . JText::_('USER') ?></option>
						        <option value="4"><?php echo JText::_('DELETE') . ' ' . $error['conflict']['jname'] . ' ' . JText::_('USER') ?></option>
					        </select>
					    <?php
					    }
					    ?>
				    </td>
			    </tr>
			<?php
			}
			//close the table and render submit button
			?>
		</table>
		<input type="submit" value="<?php echo JText::_('RESOLVE_CONLFICTS')?>"/>

		<input type="hidden" name="filter_order" value="<?php echo $this->filter['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->filter['dir']; ?>" />
		<input type="hidden" name="filter_client" value="<?php echo $this->filter['client'];?>" />
		<?php echo $this->pageNav->getListFooter(); ?>

	<?php
	} else {
	?>
		<h2><?php echo JText::_('CONFLICT_RESOLUTION_COMPLETE'); ?></h2>
	<?php
	}
	?>
	</form>
</div>