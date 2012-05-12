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

//load debug library
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.debug.php');
?>
<table>
	<tr>
		<td width="100px">
			<img src="components/com_jfusion/images/jfusion_large.png" height="75px" width="75px">
		</td>
		<td width="100px">
			<img src="components/com_jfusion/images/login_checker2.png" height="75px" width="75px">
		</td>
		<td>
			<h2>
				<?php echo JText::_('LOGIN_CHECKER_RESULT');?>
			</h2>
		</td>
	</tr>
</table>

<?php
debug::$callback = array($this,'jfusion_plugin',null);
foreach($this->jfusion_plugin as $key => $value) {
	$title = JText::_('JFUSION') . ' ' . $key . ' ' . JText::_('PLUGIN');
	debug::show($value, $title);
	$textOutput[$title] = $value;
	?><br><?php
}

foreach($this->jfusion_module as $key => $value) {
	debug::$callback = array($this,'jfusion_module',$key);
	$title = $key . ' ' . JText::_('MODULE');
	debug::show($value, $title);
	$textOutput[$title] = $value;
	?><br><?php
}

foreach($this->joomla_plugin as $key => $value) {
	debug::$callback = array($this,'joomla_plugin',$key);
	$title = $key . ' ' . JText::_('PLUGIN');
	debug::show($value, $title);
	$textOutput[$title] = $value;
	?><br><?php
}
debug::$callback = array($this,'menu_item',null);
foreach($this->menu_item as $key => $value) {
	$title = $key . ' ' . JText::_('MENUITEM');
	debug::show($value, $title);
	$textOutput[$title] = $value;
	?><br><?php
}
?>
<form method="post" action="index.php" name="adminForm" id="adminForm">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="logoutcheckerresult" />
</form>
<?php
$debug=null;
foreach ($textOutput as $key => $value) {
	if ($debug) {
		 $debug .= "\n\n".debug::getText($value, $key, 1);
	} else {
		$debug = debug::getText($value, $key, 1);
	}
}
?>
<textarea rows="10" cols="110"><?php echo $debug ?></textarea>