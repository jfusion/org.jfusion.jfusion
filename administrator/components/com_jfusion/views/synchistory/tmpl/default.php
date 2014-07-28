<?php

/**
 * This is view file for synchistory
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Synchistory
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 *
 * @var $this jfusionViewsynchistory
 */
// no direct access
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');
//display the paypal donation button
echo JFusionFunctionAdmin::getDonationBanner();
?>
<div class="jfusion">
	<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
		<input type="hidden" name="task" value="syncstatus" />

		<table class="jfusionlist" style="border-spacing:1px;">
			<thead>
				<tr>
					<th class="title" width="20px">
						<label for="checkall"></label><input id="checkall" type="checkbox" onclick="JFusion.applyAll();"/>
					</th>
					<th class="title" width="20px">
						<?php echo JText::_('ID'); ?>
					</th>
					<th class="title" >
						<?php echo JText::_('ACTION'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('START_TIME'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('END_TIME'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('TOTAL_TIME'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('ERRORS'); ?>
					</th>
					<th class="title" align="center">
						<?php echo JText::_('DETAILS'); ?>
					</th>
				</tr>
			</thead>
		<tbody>
			<?php
			$row_count = 0;
			if (empty($this->rows)) {
			    $this->rows = array();
			    \JFusion\Framework::raise(LogLevel::WARNING, JText::_('NO_USERSYNC_DATA'));
			}
			foreach ($this->rows as $record) {
			    ?><tr class="row<?php echo ($row_count % 2);?>"><?php
	                $row_count++;
				    $syncdata = unserialize(base64_decode($record->syncdata));

				    ?>
				    <td>
				        <input type="checkbox" id="syncid<?php echo $record->syncid; ?>" name="syncid[<?php echo $record->syncid; ?>]" />
				    </td>
				    <td>
					    <label for="syncid<?php echo $record->syncid; ?>"><?php echo $record->syncid; ?></label>
				    </td>
				    <td>
				        <?php echo $record->action; ?>
				    </td>
				    <td>
				        <?php echo date('d/m/y : H:i:s', $record->time_start); ?>
				    </td>
				    <?php
				    if ($record->time_end) { ?>
				        <td>
				            <?php echo date('d/m/y : H:i:s', $record->time_end); ?>
				        </td>
				        <td>
				            <?php echo $this->getFormattedTimediff($record->time_start, $record->time_end); ?>
				        </td>
			        <?php
				    } else { ?>
				        <td>
				        </td>
				        <td>
				            <?php echo JText::_('SYNC_NOT_FINISHED'); ?>
				        </td>
			        <?php
				    }
					//get the total errors
					?>
					<td><?php echo \JFusion\Usersync\Usersync::countLogData($record->syncid, 'error'); ?></td>
				    <td>
					    <a class="btn" href="index.php?option=com_jfusion&amp;task=syncstatus&amp;syncid=<?php echo $record->syncid; ?>">
						    <?php echo JText::_('CLICK_FOR_MORE_DETAILS'); ?>
					    </a>
				    </td>
				</tr>
			    <?php
			}
			?>
			</tbody>
		</table>
	</form>
	<br/><br/><br/>
</div>