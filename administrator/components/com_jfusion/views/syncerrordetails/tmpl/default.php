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
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.debug.php';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo JFactory::getLanguage()->getTag(); ?>" lang="<?php echo JFactory::getLanguage()->getTag(); ?>">
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<head>
	</head>
	<body>
		<div id="ajax_bar">
			<font size="3">
				Detailed JFusion Error Report
			</font>
		</div>
		
		<?php
		//get the error
		$errorid = JRequest::getVar('errorid', '', 'GET');
		$error =& unserialize($this->synclog[$errorid]->data);
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
	</body>
</html>