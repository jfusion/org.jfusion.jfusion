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

JFactory::getDocument()->addStyleSheet(JUri::root(true) . '/components/com_jfusion/css/debugger.css');

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

	$debugger = \JFusion\Factory::getDebugger('jfusion-syncerrordetails');

	//display the userlist info

	$debugger->set(null, $error['user']);
	$debugger->setTitle('User from Plugin');
	$debugger->displayHtml('jname');
	?>
	<br/>
	<?php
	$debugger->setTitle('User Info from Usersync List');
	$debugger->displayHtml('userlist');
	?>
	<br/>
	<?php
	$debugger->setTitle('User Info from getUser() function');
	$debugger->displayHtml('userinfo');
	?>
	<br/>
	<?php
	$debugger->set(null, $error['conflict']);
	$debugger->setTitle('User target Plugin');
	$debugger->displayHtml('jname');
	?>
	<br/>
	<?php
	$debugger->setTitle('Error Info from updateUser() function');
	$debugger->displayHtml('error');
	?>
	<br/>
	<?php
	$debugger->setTitle('Debug Info from updateUser() function');
	$debugger->displayHtml('debug');
	?>
	<br/>
	<?php
	$debugger->setTitle('User Info from updateUser() function');
	$debugger->displayHtml('userinfo');
	?>
</div>