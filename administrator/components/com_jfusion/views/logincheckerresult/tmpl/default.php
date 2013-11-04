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
JFusionFunctionAdmin::displayDonate();

global $jfusionDebug;

/**
 * Output information about the server for future support queries
 */
?>
<div class="jfusion">
	<div style="border:0 none ; margin:0; padding:0 5px; width: 800px; float: left;">
	    <form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
	        <input type="hidden" name="show_unsensored" value="<?php echo $this->options['show_unsensored']; ?>" />
	        <input type="hidden" name="task" value="logoutcheckerresult" />
			<?php if (!empty($jfusionDebug['joomlaid'])) : ?>
	        <input type="hidden" name="joomlaid" value="<?php echo $jfusionDebug['joomlaid']; ?>"/>
			<?php endif; ?>

			<?php
			$textOutput = array();
			//prevent current joomla session from being destroyed
			global $JFusionActivePlugin;
			$JFusionActivePlugin = 'joomla_int';
			foreach ($this->plugins as $plugin) {
				$title = JText::_('JFUSION') . ' ' . $plugin->name . ' ' . JText::_('PLUGIN');
				debug::show($plugin, $title);
				$textOutput[] = debug::getText($plugin, $title);
			}
			?><br/><br/><?php

			$title = JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN');
			//output from auth plugin results
			debug::show($this->auth_userinfo, $title);
			$textOutput[] = debug::getText($this->auth_userinfo, $title);

			//check to see if plugins returned true
			if ($this->response->status === JAuthentication::STATUS_SUCCESS) {
				$title = JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('SUCCESS');
				?>
	            <table style="background-color:#d9f9e2;width:100%;">
	                <tr style="height: 30px;">
	                    <td width="50px">
	                        <img src="components/com_jfusion/images/check_good.png">
	                    </td>
	                    <td>
	                        <h1>
	                            <strong>
									<?php echo $title; ?>
	                            </strong>
	                        </h1>
	                    </td>
	                </tr>
	            </table>
	            <br/>
	            <br/>
				<?php
				if (!empty($this->response->debug)) {
					debug::show($this->response->debug, $title);
					$textOutput[] = debug::getText($this->response->debug, $title);
				}

				foreach ($this->auth_results as $name => $auth_result) {
					$title = $name . ' ' . JText::_('USER') . ' ' . JText::_('PLUGIN');
					?>
	                <br/><br/>
	                <table style="width: 100%;">
	                    <tr style="height: 30px;">
	                        <td ALIGN="center" colspan="2" bgcolor="#D6F2FF">
	                            <strong>
									<?php echo $title; ?>
	                            </strong>
	                        </td>
	                    </tr>
						<?php
						if ($auth_result->result == true) {
							$title .= ' ' . JText::_('SUCCESS');
							?>
	                        <tr style="height: 30px;">
	                            <td width="50px" style="background-color: #d9f9e2;">
	                                <img src="components/com_jfusion/images/check_good_small.png">
	                            </td>
	                            <td style="background-color:#d9f9e2;">
	                                <h1>
	                                    <strong>
											<?php echo $title; ?>
	                                    </strong>
	                                </h1>
	                            </td>
	                        </tr>
							<?php
						} else {
							$title .= ' ' . JText::_('ERROR');
							?>
	                        <tr style="height: 30px;">
	                            <td width="50px" style="background-color: #f9ded9;">
	                                <img src="components/com_jfusion/images/check_bad_small.png">
	                            </td>
	                            <td style="background-color:#f9ded9;">
	                                <h1>
	                                    <strong>
											<?php echo $title; ?>
	                                    </strong>
	                                </h1>
	                            </td>
	                        </tr>
							<?php
						}
						?></table><?php
					if (!empty($auth_result->debug)) {
						?> <br/><br/> <?php
						debug::show($auth_result->debug, $title);
						$textOutput[] = debug::getText($auth_result->debug, $title);
					}
				}
				JToolBarHelper::custom( 'logoutcheckerresult', 'forward.png', 'forward.png', JText::_('Check Logout'), false, false);
			} else {
				$title = JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('ERROR');
				?>
	            <table style="background-color:#f9ded9;width:100%;">
	                <tr style="height: 30px;">
	                    <td width="50px">
	                        <img src="components/com_jfusion/images/check_bad_small.png">
	                    </td>
	                    <td>
	                        <h1>
	                            <strong>
									<?php echo $title; ?>
	                            </strong>
	                        </h1>
	                    </td>
	                </tr>
	            </table>
				<?php

				if (!empty($this->response->debug)) {
					?><br/><br/><?php
					debug::show($this->response->debug, $title);
					$textOutput[] = debug::getText($this->response->debug, $title);
				}
			}

			//create a link to test out the logout function
			?>
	    </form>
	    <br/><br/>
		<?php
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
</div>