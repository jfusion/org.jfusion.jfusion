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
echo JFusionFunctionAdmin::getDonationBanner();

$debugger = \JFusion\Factory::getDebugger('jfusion-logoutcheckerresult');
$debugger->set(null, $this->debug);
$debugger->setTitle(JText::_('LOGOUT') . ' ' . JText::_('DEBUG'));
/**
 * Output information about the server for future support queries
 */
?>
<div class="jfusion">
	<div class="loginchecker">
		<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
			<input type="hidden" name="task" value="logoutcheckerresult" />
		</form>
		<?php $debugger->displayHtml(); ?>
		<br/>
		<br/>
		<label for="debug"><?php echo JText::_('JFUSION') . ' ' . JText::_('DEBUG'); ?></label>
		<textarea id="debug" rows="25" class="dumparea"><?php echo $debugger->getAsText(); ?></textarea>
	</div>
</div>