<?php

/**
 * This is view file for loginchecker
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Loginchecker
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
//display the paypal donation button
echo JFusionFunctionAdmin::getDonationBanner();
?>
<div class="jfusion">
	<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
		<input type="hidden" name="task" value="logincheckerresult" />

	    <h3>
	        <?php echo JText::_('LOGIN_CHECKER_TEXT'); ?>
	    </h3>
		<br/><br/>
		<table class="jfusionlist" style="border-spacing:1px;">
	        <thead>
	            <tr>
	                <th colspan="2" class="title" >
	                    <?php echo JText::_('LOGIN_CHECKER'); ?>
	                </th>
	            </tr>
	        </thead>
	        <tbody>
		        <tr>
			        <td width="200px">
				        <label for="username"><?php echo JText::_('USERNAME'); ?></label>
					</td>
					<td>
					    <input id="username" type="text" name="username" size="40"/>
					</td>
				</tr>
				<tr>
					<td width="100px">
						<label for="password"><?php echo JText::_('PASSWORD'); ?></label>
					</td>
					<td>
					    <input id="password" type="password" name="password" size="40"/>
					</td>
				</tr>
				<tr>
					<td width="100px">
						<label for="remember"><?php echo JText::_('DEBUG') . ' ' . JText::_('REMEMBER_ME'); ?></label>
					</td>
					<td>
					    <input id="remember" type="checkbox" name="remember" value="1" alt="Debug Remember Me" />
					</td>
				</tr>
				<tr>
					<td width="100px">
						<label for="show_unsensored"><?php echo JText::_('SHOW_UNSENSORED_DATA'); ?></label>
					</td>
					<td>
					    <input id="show_unsensored" type="checkbox" name="show_unsensored" value="1" alt="Show Uncensored Data" />
					</td>
				</tr>
				<tr>
					<td width="100px">
						<label for="skip_password_check"><?php echo JText::_('SKIP_PASSWORD_CHECK'); ?></label>
					</td>
					<td>
					    <input id="skip_password_check" type="checkbox" name="skip_password_check" value="1" alt="Skip Password Check" />
					</td>
				</tr>
				<tr>
					<td width="100px">
						<label for="overwrite"><?php echo JText::_('AUTO_OVERWITE_CONFLICTS'); ?></label>
					</td>
					<td>
	                    <input id="overwrite" type="checkbox" name="overwrite" value="1" alt="Auto Overwite Conflicts" />
					</td>
	            </tr>
	        <tbody>
		</table>
	</form>
</div>