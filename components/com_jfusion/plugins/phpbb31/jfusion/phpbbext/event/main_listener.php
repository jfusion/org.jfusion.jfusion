<?php
/**
*
* @package phpBB Extension - JFusion phpBB Extension
* @copyright (c) 2013 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace jfusion\phpbbext\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class main_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
//			'core.common' => 'core_common',
			'core.user_setup' => 'core_user_setup',
		);
	}

	/* @var \phpbb\config\db */
	protected $config;

	/* @var \phpbb\user */
	protected $user;

	/**
	* Constructor
	*
	* @param \phpbb\config\db	$config		Controller helper object
	* @param \phpbb\user			$user	Template object
	*/
	public function __construct(\phpbb\config\db $config, \phpbb\user $user)
	{
		$this->config = $config;
		$this->user = $user;
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\Event $event
	 */
	public function core_user_setup($event)
	{
		$page = $this->user->page['page'];
		$url = $this->config['jfusion_phpbbext_redirect_url'];
		if (strpos($page, 'feed.php') !== 0 && !empty($url)) {
			$groups = array();
			if ($this->config['jfusion_phpbbext_direct_access']) {
				$groups = explode(',', $this->config['jfusion_phpbbext_direct_access_groups']);
			}

			if (!in_array($this->user->data['group_id'], $groups)) {
				if (!defined('_JEXEC') && !defined('ADMIN_START') && !defined('IN_MOBIQUO')) {
					if (strpos('?', $url) !== false && strpos('?', $page) !== false) {
						$page = str_replace('?', '&', $page);
					}

				    header('Location: ' . $url . $page);
				}
			}
		}
	}
}
