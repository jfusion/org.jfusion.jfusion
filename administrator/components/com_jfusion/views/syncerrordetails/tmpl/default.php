<?php

/**
 * This is view file for syncErrordetails
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage SyncErrordetails
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 *     Load usersync and debug library
 */
?>
<div class="jfusion">
	<h1>
		Detailed JFusion Error Report
	</h1>
	<?php
	//get the error
	$errorid = JFactory::getApplication()->input->get->get('errorid', '');
	$error = unserialize($this->synclog[$errorid]->data);
	//display the userlist info
	debug::show($error['user']['jname'], 'User from Plugin', 1);
	?><br/><?php
	debug::show($error['user']['userlist'], 'User Info from Usersync List', 1);
	?><br/><?php
	debug::show($error['user']['userinfo'], 'User Info from getUser() function');
	?><br/><?php
	debug::show($error['conflict']['jname'], 'User target Plugin', 1);
	?><br/><?php
	debug::show($error['conflict']['error'], 'Error Info from updateUser() function');
	?><br/><?php
	debug::show($error['conflict']['debug'], 'Debug Info from updateUser() function');
	?><br/><?php
	debug::show($error['conflict']['userinfo'], 'User Info from updateUser() function');
	?>
</div>