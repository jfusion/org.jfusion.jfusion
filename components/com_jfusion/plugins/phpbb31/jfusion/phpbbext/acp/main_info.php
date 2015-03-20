<?php
/**
*
* @package phpBB Extension - JFusion phpBB Extension
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace jfusion\phpbbext\acp;

class main_info
{
	function module()
	{
		return array(
			'filename'	=> '\jfusion\phpbbext\acp\main_module',
			'title'		=> 'ACP_JFUSION_PHPBBEXT_TITLE',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'settings'	=> array('title' => 'ACP_JFUSION_PHPBBEXT',
					'auth' => 'ext_jfusion/phpbbext && acl_a_board',
					'cat' => array('ACP_JFUSION_PHPBBEXT_TITLE')),
			),
		);
	}
}
