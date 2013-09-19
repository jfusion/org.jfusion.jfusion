<?php
/**
 * @package JFusion
 * @subpackage Views
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 *
 * @var jfusionViewPlugin $this
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

$wrapper_scroll = $this->params->get('wrapper_scroll', 'auto');
if ($wrapper_scroll=='hidden') {
	$scroll = 'no';
} elseif ($wrapper_scroll=='scroll') {
	$scroll = 'yes';
} else {
	$scroll = 'auto';
}
$pageclass_sfx = $this->params->get('pageclass_sfx','');

if($this->params->get('wrapper_autoheight', 1)) {
	$onload = 'JFusion.adjustMyFrameHeight();';
} else {
	$onload = '';
}
$wrapper_width = $this->params->get('wrapper_width', '100%');
$wrapper_height = $this->params->get('wrapper_height', '500');

$oldbrowser = JText::_('OLD_BROWSER');
$html =<<<HTML
	<div class="contentpane{$pageclass_sfx}">
		<iframe scrolling="{$scroll}"
			onload="{$onload}"
			id="jfusioniframe" name="iframe" src="{$this->url}"
			width="{$wrapper_width}"
			height="{$wrapper_height}"
			style="vertical-align:top; border-style:none; overflow:{$wrapper_scroll};" class="wrapper">
			{$oldbrowser}
		</iframe>
	</div>
HTML;

echo $html;