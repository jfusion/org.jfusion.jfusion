<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_efront extends JFusionPublic 
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'efront';
    }

    /**
     * @return string
     */
    function getRegistrationURL()
    {
        return 'index.php?ctg=signup';
    }

    /**
     * @return string
     */
    function getLostPasswordURL()
    {
        return 'index.php?ctg=reset_pwd';
    }

    /**
     * @return string
     */
    function getLostUsernameURL()
    {
        return 'index.php?ctg=reset_pwd';
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

	    $db = \JFusion\Factory::getDatabase($this->getJname());

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
	        $db = \JFusion\Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('COUNT(*)')
			    ->from('#__users_online');

	        $db->setQuery($query);
	        $result = $db->loadResult();
	    } catch (Exception $e) {
		    \JFusion\Framework::raiseError($e, $this->getJname());
		    $result = 0;
	    }
        return $result;
    }
}