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

	$debugger = JFusionFactory::getDebugger('jfusion-syncerrordetails');

	//display the userlist info

	$debugger->reset($error['user']['jname']);
	$debugger->setTitle('User from Plugin');
	$debugger->displayHtml();
	?>
	<br/>
	<?php
	$debugger->reset($error['user']['userlist']);
	$debugger->setTitle('User Info from Usersync List');
	$debugger->displayHtml();
	?>
	<br/>
	<?php
	$debugger->reset($error['user']['userinfo']);
	$debugger->setTitle('User Info from getUser() function');
	$debugger->displayHtml();
	?>
	<br/>
	<?php
	$debugger->reset($error['conflict']['jname']);
	$debugger->setTitle('User target Plugin');
	$debugger->displayHtml();
	?>
	<br/>
	<?php
	$debugger->reset($error['conflict']['error']);
	$debugger->setTitle('Error Info from updateUser() function');
	$debugger->displayHtml();
	?>
	<br/>
	<?php
	$debugger->reset($error['conflict']['debug']);
	$debugger->setTitle('Debug Info from updateUser() function');
	$debugger->displayHtml();
	?>
	<br/>
	<?php
	$debugger->reset($error['conflict']['userinfo']);
	$debugger->setTitle('User Info from updateUser() function');
	$debugger->displayHtml();
	?>
</div>