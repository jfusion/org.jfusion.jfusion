<?php
/**
*
* @package phpBB Extension - JFusion phpBB Extension
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'ACP_JFUSION_PHPBBEXT_TITLE'			=> 'JFusion Extention',
	'ACP_JFUSION_PHPBBEXT'					=> 'Settings',

	'ACP_JFUSION_PHPBBEXT_REDIRECT_URL'			=> 'Redirect URL',

	'ACP_JFUSION_PHPBBEXT_DIRECT_ACCESS'			=> 'Allow Direct Access',
	'ACP_JFUSION_PHPBBEXT_DIRECT_ACCESS_GROUPS'			=> 'Direct Access Groups',


	'ACP_JFUSION_PHPBBEXT_JNAME'			=> 'Insance Name',


	'ACP_JFUSION_PHPBBEXT_APIPATH'			=> 'Api Path',


	'ACP_JFUSION_PHPBBEXT_REDIRECT_LOGIN'			=> 'Redirect after login',
	'ACP_JFUSION_PHPBBEXT_REDIRECT_LOGOUT'			=> 'Redirect after logout',


	'ACP_JFUSION_PHPBBEXT_SETTING_SAVED'	=> 'Settings have been saved successfully!'
));