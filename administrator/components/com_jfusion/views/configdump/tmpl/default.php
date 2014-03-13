<?php
/**
 * @package JFusion
 * @subpackage Views
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
echo JFusionFunctionAdmin::getDonationBanner();

$debugger = JFusionFactory::getDebugger('jfusion-configdump');

$show = '';
if (JFactory::getApplication()->input->get('show', false)) {
	$show = 'checked="yes"';
}

$filter = '';
if (JFactory::getApplication()->input->get('filter', false)) {
	$filter = 'checked="yes"';
}
?>
<div class="jfusion">
	<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
		<input type="hidden" name="task" value="configdump" />
		<table>
			<tr>
				<td style="background-color: #F5A9A9;">
					<?php echo JText::_('CONFIGDUMP_ERROR');?>
				</td>
			</tr>
			<tr>
				<td style="background-color: #FFFF00;">
					<?php echo JText::_('CONFIGDUMP_POSIBLE_ERROR');?>
				</td>
			</tr>
			<tr>
				<td style="background-color: #088A08;">
					<?php echo JText::_('CONFIGDUMP_SEEMS_OK');?>
				</td>
			</tr>
			<tr>
				<td>
					<input id="filter" type="checkbox" <?php echo $filter; ?> name="filter" value="true" />
					<label for="filter" style="display: inline;"><?php echo JText::_('CONFIGDUMP_FILTER'); ?></label>
				</td>
			</tr>
			<tr>
				<td>
					<input id="show" type="checkbox" <?php echo $show; ?> name="show" value="true" />
					<label for="show" style="display: inline;"><?php echo JText::_('CONFIGDUMP_SHOW'); ?></label>
				</td>
			</tr>
		</table>
	</form>

	<?php
	$textOutput = array();
	//output the information to the user
	$debugger->set(null, $this->server_info);
	$debugger->setTitle(JText::_('SERVER') . ' ' . JText::_('CONFIGURATION'));
	$debugger->displayHtml();
	$textOutput[] = $debugger->getAsText();
	?>
	<br/>
	<?php

	$debugger->set(null, $this->jfusion_version);
	$debugger->setTitle(JText::_('JFUSION') . ' ' . JText::_('VERSIONS'));
	$debugger->displayHtml();
	$textOutput[] = $debugger->getAsText();
	?>
	<br/>
	<?php

	$debugger->setCallback(array($this, 'jfusion_plugin', null));
	foreach($this->jfusion_plugin as $key => $value) {
		$debugger->set(null, $value);
		$debugger->setTitle(JText::_('JFUSION') . ' ' . $key . ' ' . JText::_('PLUGIN'));
		$debugger->displayHtml();
		$textOutput[] = $debugger->getAsText();
		?>
		<br>
	<?php
	}

	foreach($this->jfusion_module as $key => $value) {
		$debugger->set(null, $value);
		$debugger->setCallback(array($this, 'jfusion_module', $key));
		$debugger->setTitle($key . ' ' . JText::_('MODULE'));
		$debugger->displayHtml();
		$textOutput[] = $debugger->getAsText();
		?>
		<br>
	<?php
	}

	foreach($this->joomla_plugin as $key => $value) {
		$debugger->set(null, $value);
		$debugger->setCallback(array($this, 'joomla_plugin', $key));
		$debugger->setTitle($key . ' ' . JText::_('PLUGIN'));
		$debugger->displayHtml();
		$textOutput[] = $debugger->getAsText();
		?>
		<br>
	<?php
	}
	$debugger->setCallback(array($this, 'menu_item', null));
	foreach($this->menu_item as $key => $value) {
		$debugger->set(null, $value);
		$debugger->setTitle($key . ' ' . JText::_('PLUGIN'));
		$debugger->displayHtml();
		$textOutput[] = $debugger->getAsText();
		?>
		<br>
	<?php
	}
	$debug=null;
	foreach ($textOutput as $value) {
		if ($debug) {
			$debug .= "\n\n" . $value;
		} else {
			$debug = $value;
		}
	}
	?>
	<label for="debug"><?php echo JText::_('JFUSION') . ' ' . JText::_('DEBUG'); ?></label>
	<textarea id="debug" rows="25" class="dumparea"><?php echo $debug ?></textarea>
</div>