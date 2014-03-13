<?php

/**
 * This is view file for logincheckerresult
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Logincheckerresults
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
//please support JFusion
echo JFusionFunctionAdmin::getDonationBanner();

$joomlaid = JFusionFactory::getDebugger('jfusion-loginchecker')->get('joomlaid');

$debugger = JFusionFactory::getDebugger('jfusion-logincheckerresult');

/**
 * Output information about the server for future support queries
 */
?>
<div class="jfusion">
	<div class="loginchecker">
		<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
			<input type="hidden" name="show_unsensored" value="<?php echo $this->options['show_unsensored']; ?>" />
			<input type="hidden" name="task" value="logoutcheckerresult" />
			<?php if ($joomlaid) : ?>
				<input type="hidden" name="joomlaid" value="<?php echo $joomlaid; ?>"/>
			<?php endif; ?>
		</form>
		<?php
		$textOutput = array();
		//prevent current joomla session from being destroyed
		global $JFusionActivePlugin;
		$JFusionActivePlugin = 'joomla_int';
		foreach ($this->plugins as $plugin) {
			$debugger->set(null, $plugin);
			$debugger->setTitle(JText::_('JFUSION') . ' ' . $plugin->name . ' ' . JText::_('PLUGIN'));
			$debugger->displayHtml();
			$textOutput[] = $debugger->getAsText();
		}
		?>
		<br/>
		<br/>
		<?php
		$debugger->set(null, $this->auth_userinfo);
		$debugger->setTitle(JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'));
		$debugger->displayHtml();
		$textOutput[] = $debugger->getAsText();

		if ($this->response->status === JAuthentication::STATUS_SUCCESS) {
			$title = JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('SUCCESS');
			$class = 'success';
			JToolBarHelper::custom('logoutcheckerresult', 'forward.png', 'forward.png', JText::_('Check Logout'), false, false);
		} else {
			$title = JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('ERROR');
			$class = 'error';
		}
		?>
		<br/>
		<br/>
		<div class="login <?php echo $class; ?>">
			<div>
				<h1>
					<strong><?php echo $title; ?></strong>
				</h1>
			</div>
		</div>
		<?php

		$authenticationDebugger = JFusionFactory::getDebugger('jfusion-authentication');

		if (!$authenticationDebugger->isEmpty('debug')) {
			$authenticationDebugger->setTitle($title);
			$authenticationDebugger->displayHtml('debug');
			$textOutput[] = $authenticationDebugger->getAsText('debug');
		}

		foreach ($this->auth_results as $name => $auth_result) {
			$title = $name . ' ' . JText::_('USER') . ' ' . JText::_('PLUGIN');

			if ($auth_result->result == true) {
				$title .= ' ' . JText::_('SUCCESS');
				$class = 'success';
			} else {
				$title .= ' ' . JText::_('ERROR');
				$class = 'error';
			}
			?>
			<br/>
			<br/>
			<div class="login <?php echo $class; ?>">
				<div>
					<h1>
						<strong><?php echo $title; ?></strong>
					</h1>
				</div>
			</div>
			<?php
			if (!empty($auth_result->debug)) {
				$debugger->set(null, $auth_result->debug);
				$debugger->setTitle($title);
				$debugger->displayHtml();
				$textOutput[] = $debugger->getAsText();
			}
		}

		//create a link to test out the logout function
		?>

		<br/><br/>
		<?php
		$debug = null;
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
</div>