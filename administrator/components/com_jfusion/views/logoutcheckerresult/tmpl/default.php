<?php

/**
 * This is view file for logoutcheckerresult
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Logoutcheckerresults
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
JFusionFunctionAdmin::displayDonate();
/**
 *     Load debug library
 */
require_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.debug.php';
/**
 * Output information about the server for future support queries
 */
?>

<form method="post" action="index.php" name="adminForm" id="adminForm">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="logoutcheckerresult" />
</form>
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
				<?php echo JText::_('LOGOUT_CHECKER_RESULT'); ?>
			</h2>
		</td>
	</tr>
</table>

<div style="border: 0pt none ; margin: 0pt; padding: 0pt 5px; width: 800px; float: left;">
	<?php
    debug::show($this->debug, JText::_('LOGOUT') . ' ' . JText::_('DEBUG'), 1);
	?>
	<textarea rows="10" cols="110"><?php echo debug::getText($this->debug, JText::_('LOGOUT') . ' ' . JText::_('DEBUG'), 1) ?></textarea>
</div>