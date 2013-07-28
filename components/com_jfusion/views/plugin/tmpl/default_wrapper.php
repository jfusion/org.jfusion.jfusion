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

?>
<script type="text/javascript">
	if('undefined'=== typeof JFusion) {
		var JFusion = {};
	}

	JFusion.getElement = function(aID) {
		return (document.getElementById) ? document.getElementById(aID) : document.all[aID];
	};

	JFusion.getIFrameDocument = function(aID) {
		var rv = null;
		var frame=JFusion.getElement(aID);
		// if contentDocument exists, W3C compliant (e.g. Mozilla)

		if (frame.contentDocument) {
			rv = frame.contentDocument;
		} else {
			// bad IE  ;)
			rv = document.frames[aID].document;
		}
		return rv;
	};

	JFusion.adjustMyFrameHeight = function() {
		var frame = JFusion.getElement("jfusioniframe");
		frame.height = JFusion.getIFrameDocument("jfusioniframe").body.offsetHeight;

		window.scrollTo(window.pageYOffset,JFusion.getOffsetTop(frame));
	};

	JFusion.getOffsetTop = function(el) {
		var top = 0;
		while( el && !isNaN( el.offsetTop ) ) {
			top += el.offsetTop;
			el = el.offsetParent;
		}
		return top;
	};
</script>
<?php
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
?>

