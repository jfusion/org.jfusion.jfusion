<?php

/**
 * This is view file for versioncheck
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Versioncheck
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

<style type="text/css">
	.percentbar {
		background: #CCCCCC;
		border: 1px solid #666666;
		height: 10px;
	}
	.percentbar div {
		background: #28B8C0;
		height: 10px;
	}
</style>
<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
	<input type="hidden" name="task" value="languages" />
</form>
<div class="jfusion">
	<table class="jfusionform" style="border-spacing:1px;">
		<thead>
			<tr>
				<th class="title " align="left">
					<?php echo JText::_('ID'); ?>
				</th>
				<th class="title " align="left">
					<?php echo JText::_('LANGUAGE'); ?>
				</th>
				<th class="title" align="center">
					<?php echo JText::_('TRANSLATION_STATUS'); ?>
				</th>
				<th class="title" align="center">
					<?php echo JText::_('YOUR_VERSION'); ?>
				</th>
				<th class="title" align="center">
					<?php echo JText::_('CURRENT_VERSION'); ?>
				</th>
				<th class="title" align="center">
					<?php echo JText::_('OPTIONS'); ?>
				</th>

			</tr>
		</thead>
		<tbody>
		<?php $row_count = 0;
		$scale = 1;
		foreach ($this->lang_repo as $lang => $data) {
			$percent = str_replace('%','',$data->progress); ?>
			<tr class="<?php echo $data->class.($row_count % 2); ?>">
				<td style="width:50px;">
					<?php echo $lang; ?>
				</td>
				<td>
					<?php echo $data->description; ?>
				</td>
				<td style="width:150px;">
					<div>
						<div class="percentbar" style="width:<?php echo round(100 * $scale); ?>px;">
							<div style="width:<?php echo round($percent * $scale); ?>px;"></div>
						</div>
						<?php echo $data->progress; ?>
					</div>
				</td>
				<td style="width:20%;">
					<?php
					if ($data->currentdate) {
						echo $data->currentdate;
						$mode = JText::_('UPDATE');
					} else {
						echo JText::_('NOT_INSTALLED');
						$mode = JText::_('INSTALL');
					}
					?>
				</td>
				<td style="width:20%;">
					<?php echo $data->date; ?>
				</td>
				<td>
					<?php
					if ($data->currentdate != $data->date) {
						?>
						<script type="text/javascript">
							<!--
							window.addEvent('domready',function() {
								$('<?php echo $lang ;?>').addEvent('click', function(e) {
									e.stop();

									JFusion.confirmInstallLanguage('<?php echo $data->file; ?>');
								});
							});
							// -->
						</script>
						<a id="<?php echo $lang; ?>" href="<?php echo $data->file; ?>"><?php echo $mode; ?></a> / <a href="<?php echo $data->file; ?>"><?php echo JText::_('DOWNLOAD') ; ?></a>
						<?php
						if ($data->extension_id) {
							?>
							/ <a href="javascript:JFusion.confirmUninstallLanguage('<?php echo $data->extension_id; ?>');"><?php echo JText::_('UNINSTALL'); ?></a>
							<?php
						}
					} else {
						if ($data->extension_id) {
							?>
							<a href="javascript:JFusion.confirmUninstallLanguage('<?php echo $data->extension_id; ?>');"><?php echo JText::_('UNINSTALL'); ?></a>
							<?php
						}
					}
					?>
				</td>
			</tr>
			<?php
			$row_count++;
		} ?>
		</tbody>
	</table>
	<form action="index.php" method="post" id="install">
		<input type="hidden" name="install_url" value="" />
		<input type="hidden" name="type" value="" />
		<input type="hidden" name="installtype" value="url" />
		<input type="hidden" name="task" value="installlanguage" />
		<input type="hidden" name="eid" value="" />
		<input type="hidden" name="option" value="com_jfusion" />
		<?php echo JHTML::_('form.token'); ?>
	</form>
	<br/><br/>
	<a target="_blank" href="https://www.transifex.com/projects/p/jfusion/"><img border="0" src="components/com_jfusion/images/transifex.png"></a>
	<br/><br/>
</div>