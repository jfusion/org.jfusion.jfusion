<?php
/**
*
* @package phpBB Extension - JFusion phpBB Extension
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace jfusion\phpbbext\acp;

class main_module
{
	var $u_action;

	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache, $request;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		$user->add_lang('acp/common');
		$user->add_lang('common', false, false, 'jfusion/phpbbext');
		$this->tpl_name = 'phpbbext_body';
		$this->page_title = $user->lang('ACP_JFUSION_PHPBBEXT_TITLE');
		add_form_key('jfusion/phpbbext');

		if ($request->is_set_post('submit')) {
			if (!check_form_key('jfusion/phpbbext')) {
				trigger_error('FORM_INVALID');
			}

			$config->set('jfusion_phpbbext_redirect_url', $request->variable('jfusion_phpbbext_redirect_url', ''));

			$config->set('jfusion_phpbbext_direct_access', $request->variable('jfusion_phpbbext_direct_access', 0));
			$config->set('jfusion_phpbbext_direct_access_groups', $request->variable('jfusion_phpbbext_direct_access_groups', ''));

			$config->set('jfusion_phpbbext_jname', $request->variable('jfusion_phpbbext_jname', ''));

			$config->set('jfusion_phpbbext_apipath', $request->variable('jfusion_phpbbext_apipath', ''));

			$config->set('jfusion_phpbbext_redirect_login', $request->variable('jfusion_phpbbext_redirect_login', 0));
			$config->set('jfusion_phpbbext_redirect_logout', $request->variable('jfusion_phpbbext_redirect_logout', 0));

			trigger_error($user->lang('ACP_JFUSION_PHPBBEXT_SETTING_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars(array(
			'U_ACTION'				=> $this->u_action,

			'JFUSION_PHPBBEXT_REDIRECT_URL'		=> $config['jfusion_phpbbext_redirect_url'],

			'JFUSION_PHPBBEXT_DIRECT_ACCESS'		=> $config['jfusion_phpbbext_direct_access'],
			'JFUSION_PHPBBEXT_DIRECT_ACCESS_GROUPS'		=> $config['jfusion_phpbbext_direct_access_groups'],

			'JFUSION_PHPBBEXT_JNAME'		=> $config['jfusion_phpbbext_jname'],

			'JFUSION_PHPBBEXT_APIPATH'		=> $config['jfusion_phpbbext_apipath'],

			'JFUSION_PHPBBEXT_REDIRECT_LOGIN'		=> $config['jfusion_phpbbext_redirect_login'],
			'JFUSION_PHPBBEXT_REDIRECT_LOGOUT'		=> $config['jfusion_phpbbext_redirect_logout'],
		));
	}
}
