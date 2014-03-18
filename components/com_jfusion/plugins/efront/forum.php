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
	    try {
		    //get the connection to the db
		    $db = \JFusion\Factory::getDatabase($this->getJname());
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
		    \JFusion\Framework::raiseError($e, $this->getJname());
		    $url = '';
	    }

        return $url;
    }
}
