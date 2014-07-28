<?php namespace JFusion\Plugins\efront\Platform\Joomla;

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage eFront
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2009 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Plugin\Platform\Joomla;

use \Exception;
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Forum Class for MyBB
 * For detailed descriptions on these functions please check the model.abstractforum.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage efront
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2009 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Platform extends Joomla
{
    /**
     * @param int $userid
     * @return string
     */
    function getAvatar($userid) {
	    try {
		    //get the connection to the db
		    $db = Factory::getDatabase($this->getJname());
		    // read unread count

		    $query = $db->getQuery(true)
			    ->select('avatar')
			    ->from('#__users')
			    ->where('id = ' . $db->quote((int)$userid));

		    $db->setQuery($query);
		    $avatar_id = $db->loadResult();

		    $query = $db->getQuery(true)
			    ->select('path')
			    ->from('#__files')
			    ->where('id = ' . $db->quote((int)$avatar_id));

		    $db->setQuery($query);
		    $avatar = $db->loadResult();
		    $url = $this->params->get('avatar_url') . $avatar;
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $url = '';
	    }
        return $url;
    }

	/**
	 * getOnlineUserQuery
	 *
	 * @param array $usergroups
	 *
	 * @return string
	 */
	function getOnlineUserQuery($usergroups = array())
	{
		//get a unix time from 5 minutes ago
		date_default_timezone_set('UTC');
		//$active = strtotime('-5 minutes', time());

		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('DISTINCT u.id AS userid, u.login as username, u.login as username_clean, concat(u.name,\' \', u.surname) AS name, u.email as email')
			->from('#__users AS u')
			->innerJoin('#__users_online AS s ON u.login = s.users_LOGIN');

		$query = (string)$query;
		return $query;
	}

	/**
	 * @return int
	 */
	function getNumberOnlineGuests() {
		return 0;
	}

	/**
	 * @return int
	 */
	function getNumberOnlineMembers() {
		try {
			//get a unix time from 5 minutes ago
			date_default_timezone_set('UTC');
			// $active = strtotime('-5 minutes', time());
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from('#__users_online');

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			$result = 0;
		}
		return $result;
	}
}
