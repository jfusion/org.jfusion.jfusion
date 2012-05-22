<?php
/**
 * This is the user activity helper file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Useractivity
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
*/

/**
 * This is the user activity helper file
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Useractivity
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
*/
class modjfusionUserActivityHelper {
	function prepareAutoOutput($jname, $config, $params) {
		$output = new stdClass();

        $forum =& JFusionFactory::getForum($jname);
        $db =& JFusionFactory::getDatabase($jname);
		$joomlaUser = JFactory::getUser();

		$PluginUser =& JFusionFactory::getUser($jname);
		$userinfo = $PluginUser->getUser($joomlaUser);

		//get the avatar of the logged in user
		if ($config['avatar']) {
			//retrieve avatar
			$avatarSrc =& $config['avatar_software'];
			if(!empty($avatarSrc) && $avatarSrc!='jfusion') {
				$avatar = JFusionFunction::getAltAvatar($avatarSrc, $joomlaUser->id);
			} else {
				$avatar = $forum->getAvatar($userinfo->userid);
			}

			if(empty($avatar)) {
				$avatar = JFusionFunction::getJoomlaURL().'components/com_jfusion/images/noavatar.png';
			}

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

			$output->avatar_source = $avatar;
			$output->avatar_height = $h;
			$output->avatar_width = $w;
		} else {
			$output->avatar_source = '';
			$output->avatar_height = '';
			$output->avatar_width = '';
		}

		//get the PM count of the logged in user
		if($config["pmcount"]) {
			$output->pm_url = JFusionFunction::routeURL($forum->getPrivateMessageURL(),$config['itemid'], $jname);
			$output->pm_count = $forum->getPrivateMessageCounts($userinfo->userid);
		} else {
			$output->pm_url = '';
			$output->pm_count = '';
		}

		//get the new message url
		if($config['viewnewmessages']) {
			$output->newmessages_url = JFusionFunction::routeURL($forum->getViewNewMessagesURL(), $config['itemid'], $jname);
		} else {
			$output->newmessages_url = '';
		}

		return $output;
	}
}