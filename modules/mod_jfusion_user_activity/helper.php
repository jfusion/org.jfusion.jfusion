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

    /**
     * @static
     * @param $jname
     * @param $config
     * @param $params
     * @return \stdClass
     */
    public static function prepareAutoOutput($jname, $config, $params) {
		$output = new stdClass();

	    $forum = \JFusion\Factory::getForum($jname);
	    try {
		    $joomlaUser = JFactory::getUser();
	    } catch (Exception $e) {
		    $joomlaUser = null;
	    }

	    $PluginUser = \JFusion\Factory::getUser($jname);
	    try {
		    $userinfo = $PluginUser->getUser($joomlaUser);
	    } catch (Exception $e) {
		    $userinfo = null;
	    }

		//get the avatar of the logged in user
		if ($config['avatar']) {
			//retrieve avatar
			if (!empty($config['avatar_software']) && $config['avatar_software'] != 'jfusion') {
				$avatar = \JFusion\Framework::getAltAvatar($config['avatar_software'], $joomlaUser->id);
			} else if ($userinfo) {
				$avatar = $forum->getAvatar($userinfo->userid);
			} else {
				$avatar = '';
			}

			if (empty($avatar)) {
				$avatar = \JFusion\Framework::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
			}

			$maxheight = $config['avatar_height'];
			$maxwidth = $config['avatar_width'];
			$size = ($config['avatar_keep_proportional']) ? \JFusion\Framework::getImageSize($avatar) : false;
				//size the avatar to fit inside the dimensions if larger
			if ($size !== false && ($size->width > $maxwidth || $size->height > $maxheight)) {
				$wscale = $maxwidth/$size->width;
				$hscale = $maxheight/$size->height;
				$scale = min($hscale, $wscale);
				$w = floor($scale*$size->width);
				$h = floor($scale*$size->height);
			} elseif ($size !== false) {
				//the avatar is within the limits
				$w = $size->width;
				$h = $size->height;
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
		if($userinfo && $config['pmcount'] ) {
			$output->pm_url = \JFusion\Framework::routeURL($forum->getPrivateMessageURL(), $config['itemid'], $jname);
			$output->pm_count = $forum->getPrivateMessageCounts($userinfo->userid);
		} else {
			$output->pm_url = '';
			$output->pm_count = '';
		}

		//get the new message url
		if ($config['viewnewmessages']) {
			$output->newmessages_url = \JFusion\Framework::routeURL($forum->getViewNewMessagesURL(), $config['itemid'], $jname);
		} else {
			$output->newmessages_url = '';
		}
		return $output;
	}
}