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
JFusionFunctionAdmin::displayDonate();
?>

<form method="post" action="index.php" name="adminForm" id="adminForm">
    <input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="logincheckerresult" />
	
	<table>
        <tr>
            <td width="100px">
	           <img src="components/com_jfusion/images/jfusion_large.png" height="75px" width="75px">
            </td>
            <td width="100px">
                <img src="components/com_jfusion/images/login_checker.png" height="75px" width="75px">
            </td>
            <td>
                <h2>
	               <?php echo JText::_('LOGIN_CHECKER'); ?>
                </h2>
            </td>
        </tr>
    </table>
    <br/>
    <font size="2">
        <?php echo JText::_('LOGIN_CHECKER_TEXT'); ?>
    </font>
	<br/><br/>
	<table class="adminlist" style="border-spacing:1px;">
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
                    <?php echo JText::_('USERNAME'); ?>
				</td>
				<td>
				    <input type="text" name="check_username" size="40"/>
				</td>
			</tr>
			<tr>
				<td width="100px">
				    <?php echo JText::_('PASSWORD'); ?>
				</td>
				<td>
				    <input type="password" name="check_password" size="40"/>
				</td>
			</tr>
			<tr>
				<td width="100px">
				    <?php echo JText::_('DEBUG') . ' ' . JText::_('REMEMBER_ME'); ?>
				</td>
				<td>
				    <input type="checkbox" name="remember" value="1" alt="Debug Remember Me" />
				</td>
			</tr>
			<tr>
				<td width="100px">
				    <?php echo JText::_('SHOW_UNSENSORED_DATA'); ?>
				</td>
				<td>
				    <input type="checkbox" name="show_unsensored" value="1" alt="Show Uncensored Data" />
				</td>
			</tr>
			<tr>
				<td width="100px">
				    <?php echo JText::_('SKIP_PASSWORD_CHECK'); ?>
				</td>
				<td>
				    <input type="checkbox" name="skip_password_check" value="1" alt="Skip Password Check" />
				</td>
			</tr>
			<tr>
				<td width="100px">
                    <?php echo JText::_('AUTO_OVERWITE_CONFLICTS'); ?>
				</td>
				<td>
                    <input type="checkbox" name="overwrite" value="1" alt="Auto Overwite Conflicts" />
				</td>
            </tr>
        <tbody>
	</table>
</form>