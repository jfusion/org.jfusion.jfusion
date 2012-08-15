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
     * @param int $limit
     *
     * @return string
     */
    function getOnlineUserQuery($limit) {
        $limiter = (!empty($limit)) ? 'LIMIT 0,'.$limit : '';
        //get a unix time from 5 mintues ago
        date_default_timezone_set('UTC');
        // $active = strtotime('-5 minutes', time());
        $query = "SELECT DISTINCT u.id AS userid, u.login as username, u.login as username_clean, concat(u.name,' ', u.surname) AS name, u.email as email FROM #__users AS u INNER JOIN #__users_online AS s ON u.login = s.users_LOGIN $limiter" ; //WHERE  s.timestamp > $active $limiter";
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
        //get a unix time from 5 mintues ago
        date_default_timezone_set('UTC');
        // $active = strtotime('-5 minutes', time());
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT COUNT(*) FROM #__users_online'; // WHERE  timestamp > $active";
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
    }
}