<?php

/**
 * Model for joomla actions
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Common Class for Joomla JFusion plugins
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionJoomlaForum extends JFusionForum
{
	/**
	 * Returns the URL to a userprofile of the integrated software
	 *
	 * @param int|string $userid userid
	 *
	 * @return string URL
	 */
	function getProfileURL($userid)
	{
		$url = '';
		try {
			$db = JFusionFactory::getDatabase($this->getJname());
			if ($userid) {
				$query = $db->getQuery(true)
					->select('id')
					->from('#__menu')
					->where('type = '.$db->Quote('component'))
					->where('link LIKE '.$db->Quote('%com_comprofiler%'));
				$db->setQuery($query, 0, 1);
				$itemid = $db->loadResult();
				if ($itemid) {
					$url = JRoute::_('index.php?option=com_comprofiler&task=userProfile&Itemid=' . $itemid . '&user=' . $userid);
				} else {
					$query = $db->getQuery(true)
						->select('id')
						->from('#__menu')
						->where('type = '.$db->Quote('component'))
						->where('link LIKE '.$db->Quote('%com_community%'));
					$db->setQuery($query, 0, 1);
					$itemid = $db->loadResult();
					if ($itemid) {
						$url = JRoute::_('index.php?option=com_community&view=profile&Itemid=' . $itemid . '&userid=' . $userid);
					} else {
						$query = $db->getQuery(true)
							->select('id')
							->from('#__menu')
							->where('type = '.$db->Quote('component'))
							->where('link LIKE '.$db->Quote('%com_joomunity%'));
						$db->setQuery($query, 0, 1);
						$itemid = $db->loadResult();
						if ($itemid) {
							$url = JRoute::_('index.php?option=com_joomunity&Itemid=' . $itemid . '&cmd=Profile.View.' . $userid);
						}
					}
				}
			}
		} catch (Exception $e) {
			$url = '';
		}
		return $url;
	}

	/**
	 * Retrieves the source path to the user's avatar
	 *
	 * @param int|string $uid software user id
	 *
	 * @return string with source path to users avatar
	 */
	function getAvatar($uid)
	{
		try {
			$db = JFusionFactory::getDatabase($this->getJname());
			$source_url = $this->params->get('source_url', '/');
			try {
				$query = $db->getQuery(true)
					->select('avatar')
					->from('#__comprofiler')
					->where('user_id = '.$uid);

				$db->setQuery($query);
				$result = $db->loadResult();
				if (!empty($result)) {
					$avatar = $source_url . 'images/comprofiler/'.$result;
				} else {
					$avatar = $source_url . 'components/com_comprofiler/plugin/templates/default/images/avatar/nophoto_n.png';
				}
			} catch (Exception $e) {
				try {
					$query = $db->getQuery(true)
						->select('avatar')
						->from('#__community_users')
						->where('userid = '.$uid);

					$db->setQuery($query);
					$result = $db->loadResult();
					if (!empty($result)) {
						$avatar = $source_url . $result;
					} else {
						$avatar = $source_url . 'components/com_community/assets/default_thumb.jpg';
					}
				} catch (Exception $e) {
					$query = $db->getQuery(true)
						->select('user_picture')
						->from('#__joom_users')
						->where('user_id = '.$uid);

					$db->setQuery($query);
					$result = $db->loadResult();
					$avatar = $source_url . 'components/com_joomunity/files/avatars/' . $result;
				}
			}
		} catch (Exception $e) {
			$avatar = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
		}
		return $avatar;
	}
}