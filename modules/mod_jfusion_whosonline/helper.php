<?php
/**
 * This is the whos online helper file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Whosonline
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
*/
use JFusion\Factory;
use JFusion\Framework;

/**
 * Helper class
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Whosonline
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
*/
class modjfusionWhosOnlineHelper {
    /**
     * @static
     * @param $jname
     * @param $config
     * @param $params
     * @param $output
     * @return mixed
     */
    public static function appendAutoOutput($jname, $config, $params, $output) {
		//get the itemid and jname to get any missing urls
		$link_itemid = $config['itemid'];
		if (is_numeric($link_itemid)) {
			$menu = JMenu::getInstance('site');

			$menu_param = $menu->getParams($link_itemid);
			$plugin_param = unserialize(base64_decode($menu_param->get('JFusionPluginParam')));
			$link_jname = $plugin_param['jfusionplugin'];
		} else {
			$link_jname = $link_itemid;
		}

		if(empty($link_jname)) {
			$output->error = JText::_('NO_MENU_ITEM');
		} else{
			$forum_links = Factory::getForum($link_jname);
			$public_users = Factory::getFront($jname);
			if(!$public_users->isConfigured()) {
				$output->error = $jname . ': ' . JText::_('NOT_CONFIGURED');
			} else {
				//show the number of people online if set to do so
				$output->num_guests = $public_users->getNumberOnlineGuests();
				$output->num_members = $public_users->getNumberOnlineMembers();

				if(is_array($output->online_users)) {
					// process result
					foreach($output->online_users as $u) {
						$u->output = new stdClass();
						$jfusion_userid = 0;
						//assign the joomla_userid and jfusion_userid variables

						if($link_jname == $jname) {
							$userinfo = $u;
							$jfusion_userid = $userinfo->userid;
						} else {
							//first, the userid of the JFusion plugin for the menu item must be obtained
							$JFusionUser = Factory::getUser($link_jname);

							try {
								$userinfo = $JFusionUser->getUser($u);
							} catch (Exception $e) {
								$userinfo = null;
							}
							if($userinfo) {
								$jfusion_userid = $userinfo->userid;
							}
						}

						$u->output->display_name = ($config['name'] == 1) ? $u->name : $u->username;
						$user_url = '';
						if ($config['userlink']) {
							$joomla_userid = 0;
							if($jname == 'joomla_int') {
								//Joomla userid is readily available
								$joomla_userid = $u->userid;
							} else {
								$PluginUser = Factory::getUser('joomla_int');
								$userlookup = $PluginUser->lookupUser($userinfo, $link_jname);
								if($userlookup) {
									$joomla_userid = $userlookup->userid;
								}
							}
							if ($config['userlink_software'] == 'custom' && !empty($config['userlink_custom']) && $joomla_userid) {
								$user_url = $config['userlink_custom'] . $joomla_userid;
							} else if ($jfusion_userid) {
								$user_url = Framework::routeURL($forum_links->getProfileURL($jfusion_userid), $config['itemid'], $link_jname);
							}
						}
						$u->output->user_url = $user_url;

						if ($config['avatar']) {
							// retrieve avatar
							$avatarSrc = $config['avatar_software'];
							$avatar = '';
							if(!empty($avatarSrc) && $avatarSrc != 'jfusion' && $userinfo) {
								$avatar = Framework::getAltAvatar($avatarSrc, $userinfo);
							} else if ($jfusion_userid) {
								$avatar = $forum_links->getAvatar($jfusion_userid);
							}
							if(empty($avatar)) {
								$avatar = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
							}

							$u->output->avatar_source = $avatar;

							$maxheight = $config['avatar_height'];
							$maxwidth = $config['avatar_width'];
							$size = ($config['avatar_keep_proportional']) ? Framework::getImageSize($avatar) : false;
							//size the avatar to fit inside the dimensions if larger
							if($size !== false && ($size->width > $maxwidth || $size->height > $maxheight)) {
								$wscale = $maxwidth/$size->width;
								$hscale = $maxheight/$size->height;
								$scale = min($hscale, $wscale);
								$w = floor($scale*$size->width);
								$h = floor($scale*$size->height);
							}
							elseif($size !== false) {
								//the avatar is within the limits
								$w = $size->width;
								$h = $size->height;
							} else {
								//getimagesize failed
								$w = $maxwidth;
								$h = $maxheight;
							}

							$u->output->avatar_height = $h;
							$u->output->avatar_width = $w;
						} else {
							$u->output->avatar_source = '';
							$u->output->avatar_height = '';
							$u->output->avatar_width = '';
						}
					}
				}
			}
		}
	}
}