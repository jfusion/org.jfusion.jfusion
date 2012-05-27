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
			$menu = &JSite::getMenu();
			$menu_param =& $menu->getParams($link_itemid);
			$plugin_param = unserialize(base64_decode($menu_param->get('JFusionPluginParam')));
			$link_jname = $plugin_param['jfusionplugin'];
		} else {
			$link_jname = $link_itemid;
		}

		if(empty($link_jname)) {
			$output->error = JText::_('NO_MENU_ITEM');
			return;
		} elseif(!JFusionFunction::validPlugin($link_jname)) {
			$output->error = JText::_('NOT_CONFIGURED');
			return;
		}

		$forum_links =& JFusionFactory::getForum($link_jname);
		$public_users =& JFusionFactory::getPublic($jname);


		//show the number of people online if set to do so
		$output->num_guests = $public_users->getNumberOnlineGuests();
		$output->num_members = $public_users->getNumberOnlineMembers($config['group_limit'], $config["show_total_users"]);

		if(is_array($output->online_users)) {
			// process result
			foreach($output->online_users as $u) {
				$jfusion_userid = 0;
				//assign the joomla_userid and jfusion_userid variables
				if($link_jname==$jname) {
					$jfusion_userid = $u->userid;

					if($jname=='joomla_int') {
						//Joomla's userid is readily available
						$joomla_userid = $u->userid;
					} elseif(!empty($userlookup)) {
						//obtain the correct Joomla userid for the user
						$lookupUsername = (!empty($u->username_clean)) ? $u->username_clean : $u->username;
					 	//find it in the lookup table
						$userlookup = JFusionFunction::lookupUser($link_jname, $u->userid, false, $lookupUsername);
						if(!empty($userlookup)) {
					 		$joomla_userid = $userlookup->id;
						}
					}
				} else {
					//first, the userid of the JFusion plugin for the menu item must be obtained
					$JFusionUser =& JFusionFactory::getUser($link_jname);
					$userinfo = $JFusionUser->getUser($u);

					if(!empty($userinfo)) {
						$jfusion_userid = $userinfo->userid;

						if($jname=="joomla_int") {
							//Joomla's userid is readily available
							$joomla_userid = $u->userid;
						} else {
				 			$userlookup = JFusionFunction::lookupUser($link_jname, $userinfo->userid, false, $userinfo->username);
							if(!empty($userlookup)) {
                                $joomla_userid = $userlookup->id;
							}
						}
					}
				}

				$u->output->display_name = ($config["name"]==1) ? $u->name : $u->username;

				if ($config['userlink']) {
					if ($config['userlink_software']=='custom' && !empty($config['userlink_custom'])  && !empty($joomla_userid)) {
						$user_url = $config['userlink_custom'].$joomla_userid;
					} else if ($jfusion_userid) {
	  					$user_url = JFusionFunction::routeURL($forum_links->getProfileURL($jfusion_userid, $u->username), $config['itemid'], $link_jname);
					}

	  				$u->output->user_url = $user_url;
				} else {
					$u->output->user_url = '';
				}

	            if ($config['avatar']) {
	                // retrieve avatar
	                $avatarSrc = $config['avatar_software'];
	               if(!empty($avatarSrc) && $avatarSrc!='jfusion' && !empty($joomla_userid)) {
	                	$avatar = JFusionFunction::getAltAvatar($avatarSrc, $joomla_userid);
	                } else if ($jfusion_userid) {
						$avatar = $forum_links->getAvatar($jfusion_userid);
	                }

					if(empty($avatar)) {
						$avatar = JFusionFunction::getJoomlaURL().'components/com_jfusion/images/noavatar.png';
					}

					$u->output->avatar_source = $avatar;

					$maxheight =& $config['avatar_height'];
					$maxwidth =& $config['avatar_width'];
					$size = ($config['avatar_keep_proportional']) ? @getimagesize($avatar) : false;
	            	//size the avatar to fit inside the dimensions if larger
					if($size!==false && ($size[0] > $maxwidth || $size[1] > $maxheight)) {
						$wscale = $maxwidth/$size[0];
						$hscale = $maxheight/$size[1];
						$scale = min($hscale, $wscale);
						$w = floor($scale*$size[0]);
						$h = floor($scale*$size[1]);
					}
					elseif($size!==false) {
						//the avatar is within the limits
						$w = $size[0];
						$h = $size[1];
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