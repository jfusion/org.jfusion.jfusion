<?php

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
class JFusionForum_efront extends JFusionForum {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'efront';
    }

    /**
     * @param int $userid
     * @return string
     */
    function getAvatar($userid) {
        //get the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        // read unread count
        $db->setQuery('SELECT avatar FROM #__users WHERE id = ' . (int)$userid);
        $avatar_id = $db->loadResult();
        $db->setQuery('SELECT path FROM #__files WHERE id = ' . (int)$avatar_id);
        $params = JFusionFactory::getParams($this->getJname());
        $avatar = $db->loadResult();
        $url = $params->get('avatar_url') . $avatar;
        return $url;
    }
}
