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
	<input type="hidden" name="task" value="saveorder" />

	<table class="jfusionlist" style="border-spacing:1px;" id="sortables">
		<thead>
			<tr>
				<th class="title" width="20px;">
				</th>
				<th class="title" align="left" style="min-width: 125px">
					<?php echo JText::_('PLUGIN');?>
				</th>
				<th class="title configicon">
					<div class="smallicon status_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('STATUS_TOOLTIP');?>"></div>
				</th>
				<th class="title configicon">
					<div class="smallicon encryption_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('CHECK_ENCRYPTION_TOOLTIP');?>"></div>
				</th>
				<th class="title configicon">
					<div class="smallicon login_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('DUAL_LOGIN_TOOLTIP');?>"></div>
				</th>
				<th class="title configicon">
					<div class="smallicon registration_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('REGISTRATION_TOOLTIP');?>"></div>
				</th>
				<th class="title" style="text-align: center; min-width: 60px;">
					<div class="smallicon users_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('USERS_TOOLTIP');?>"></div>
				</th>
				<th class="title" align="center">
					<?php echo JText::_('DEFAULT_USERGROUP');?>
				</th>
			</tr>
		</thead>
		<tbody id="sort_table">
			<?php echo $this->generateListHTML($this->plugins); ?>
		</tbody>
	</table>
	<br />

	<table style="width:100%;">
		<tr style="vertical-align: text-top;">
			<td style="width: 33%;">
				<p>
					<div class="smallicon wizard_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('WIZARD_TOOLTIP');?>"></div> = <?php echo JText::_('WIZARD');?>
				</p>
				<p>
					<div class="smallicon edit_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('EDIT_TOOLTIP');?>"></div> = <?php echo JText::_('EDIT');?>
				</p>
				<p>
					<div class="smallicon copy_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('COPY_TOOLTIP');?>"></div> = <?php echo JText::_('COPY');?>
				</p>
				<p>
					<div class="smallicon delete_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('DELETE_TOOLTIP');?>"></div> = <?php echo JText::_('DELETE');?>
				</p>
				<p>
					<div class="smallicon info_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('INFO_TOOLTIP');?>"></div> = <?php echo JText::_('INFO');?>
				</p>
			</td>
			<td style="width: 33%;">
				<p>
					<div class="smallicon encryption_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('CHECK_ENCRYPTION_TOOLTIP');?>"></div> = <?php echo JText::_('CHECK_ENCRYPTION');?>
				</p>
				<p>
					<div class="smallicon login_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('DUAL_LOGIN_TOOLTIP');?>"></div> = <?php echo JText::_('DUAL_LOGIN');?>
				</p>
				<p>
					<div class="smallicon status_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('STATUS_TOOLTIP');?>"></div> = <?php echo JText::_('STATUS');?>
				</p>
				<p>
					<div class="smallicon registration_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('REGISTRATION_TOOLTIP');?>"></div> = <?php echo JText::_('REGISTRATION');?>
				</p>
				<p>
					<div class="smallicon users_icon" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('USERS_TOOLTIP');?>"></div> = <?php echo JText::_('USERS');?>
				</p>
			</td>
			<td>
				<p>
					<div class="smallicon enabled" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('ENABLED_TOOLTIP');?>"></div> = <?php echo JText::_('ENABLED');?>
				</p>
				<p>
					<div class="smallicon disabled" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('DISABLED_TOOLTIP');?>"></div> = <?php echo JText::_('DISABLED');?>
				</p>
				<p>
					<div class="smallicon disabled dim" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php echo JText::_('CONFIG_FIRST_TOOLTIP');?>"></div> = <?php echo JText::_('CONFIG_FIRST');?>
				</p>
			</td>
		</tr>
	</table>

</form>
<br/><br/>

<?php echo JText::_('PLUGIN_INSTALL_INSTR'); ?><br/>

<?php if($this->VersionData) {
	//display installer data ?>

	<form id="installSERVER" method="post" action="./index.php" enctype="multipart/form-data">
		<input type="hidden" name="option" value="com_jfusion" />
		<input type="hidden" name="task" value="installplugin" />
		<input type="hidden" name="installtype" value="url" />

		<table class="jfusionform">
			<tr>
				<td>
					<img src="components/com_jfusion/images/folder_url.png">
				</td>
				<td>
					<table>
						<tr>
							<th colspan="2">
								<?php echo JText::_('INSTALL') . ' ' . JText::_('FROM') . ' JFusion ' .JText::_('SERVER'); ?>
							</th>
						</tr>
						<tr>
							<td width="120">
								<label for="server_install_url">
									<?php echo JText::_('PLUGIN') . ' ' . JText::_('NAME'); ?> :
								</label>
							</td>
							<td>
								<select name="install_url" id="server_install_url" style="width: auto;">
									<?php
									/**
									 * @var $plugin SimpleXMLElement
									 */
									foreach ($this->VersionData as $plugin): ?>
										<option value="<?php echo (string)$plugin->remotefile; ?>"><?php echo $plugin->getName() . ' - ' . (string)$plugin->description; ?></option>
									<?php endforeach; ?>
								</select>
								<input class="btn" type="submit" value="<?php echo JText::_('INSTALL'); ?>"/>
								<a class="btn" href="javascript:JFusion.downloadPlugin();"><?php echo JText::_('DOWNLOAD'); ?></a>
								<div id="spinnerSERVER">
								</div>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</form>
<?php }  else { ?>
	<table class="jfusionform">
		<tr>
			<td>
				<img src="components/com_jfusion/images/folder_url.png">
			</td>
			<td>
				<table>
					<tr>
						<th colspan="2">
							<?php echo JText::_('INSTALL') . ' ' . JText::_('FROM') . ' JFusion ' .JText::_('SERVER'); ?>
						</th>
					</tr>
					<tr>
						<td width="120">
							<?php echo JText::_('PLUGIN') . ' ' . JText::_('NAME'); ?> :
						</td>
						<td>
							<?php echo JText::_('ERROR_LOADING_REMOTE_PLUGIN_DATA_FROM_JFUSION_SERVER'); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
<?php } ?>

<form id="installZIP" method="post" action="index.php" enctype="multipart/form-data">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="installplugin" />
	<input type="hidden" name="installtype" value="upload" />
	<table class="jfusionform">
		<tr>
			<td>
				<img src="components/com_jfusion/images/folder_zip.png">
			</td>
			<td>
				<table>
					<tr>
						<th colspan="2">
							<?php echo JText::_('UPLOAD_PACKAGE'); ?>
						</th>
					</tr>
					<tr>
						<td width="120">
							<label for="install_package">
								<?php echo JText::_('PACKAGE_FILE'); ?> :
							</label>
						</td>
						<td>
							<input class="input_box" id="install_package" name="install_package" type="file" size="57" />
							<input class="btn" type="submit" value="<?php echo JText::_('UPLOAD_FILE'); ?> &amp; <?php echo JText::_('INSTALL'); ?>"/>
							<div id="spinnerZIP">
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</form>

<form id="installDIR" method="post" action="index.php" enctype="multipart/form-data">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="installplugin" />
	<input type="hidden" name="installtype" value="folder" />
	<table class="jfusionform">
		<tr>
			<td>
				<img src="components/com_jfusion/images/folder_dir.png">
			</td>
			<td>
				<table>
					<tr>
						<th colspan="2">
							<?php echo JText::_('INSTALL_FROM_DIRECTORY'); ?>
						</th>
					</tr>
					<tr>
						<td width="120"><label for="install_directory">
								<?php echo JText::_('INSTALL_DIRECTORY'); ?> :
							</label>
						</td>
						<td>
							<input type="text" id="install_directory" name="install_directory" class="input_box" size="150" value="" />
							<input class="btn" type="submit" value="<?php echo JText::_('INSTALL'); ?>"/>
							<div id="spinnerDIR">
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</form>

<form id="installURL" method="post" action="index.php" enctype="multipart/form-data">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="installplugin" />
	<input type="hidden" name="installtype" value="url" />
	<table class="jfusionform">
		<tr>
			<td>
				<img src="components/com_jfusion/images/folder_url.png">
			</td>
			<td>
				<table>
					<tr>
						<th colspan="2">
							<?php echo JText::_('INSTALL_FROM_URL'); ?>
						</th>
					</tr>
					<tr>
						<td width="120">
							<label for="install_url">
								<?php echo JText::_('INSTALL_URL'); ?> :
							</label>
						</td>
						<td>
							<input type="text" id="install_url" name="install_url" class="input_box" size="150" value="http://" />
							<input class="btn" type="submit" value="<?php echo JText::_('INSTALL'); ?>"/>
							<div id="spinnerURL">
							</div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</form>
</div>

