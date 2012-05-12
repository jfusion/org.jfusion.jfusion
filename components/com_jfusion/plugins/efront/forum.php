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
 /*   function getThreadURL($threadid) {
        return 'showthread.php?tid=' . $threadid;
    }
    function getPostURL($threadid, $postid) {
        return 'showthread.php?tid=' . $threadid . '&amp;pid=' . $postid . '#pid' . $postid;
    }
    function getProfileURL($uid) {
        return 'member.php?action=profile&uid=' . $uid;
    }
    function getActivityQuery($usedforums, $result_order, $result_limit) {
        $where = (!empty($usedforums)) ? ' WHERE a.fid IN (' . $usedforums . ')' : '';
        $end = $result_order . " LIMIT 0," . $result_limit;
        $query = array(
        //LAT with first post info
        LAT . '0' => "SELECT a.tid AS threadid, b.pid AS postid, b.username, b.uid AS userid, a.subject, b.dateline FROM #__threads as a INNER JOIN #__posts as b ON a.firstpost = b.pid $where ORDER BY a.lastpost $end",
        //LAT with latest post info
        LAT . '1' => "SELECT a.tid AS threadid, b.pid AS postid, b.username, b.uid AS userid, a.subject, b.dateline FROM #__threads as a INNER JOIN #__posts as b ON a.tid = b.tid AND a.lastpost = b.dateline AND a.lastposteruid = b.uid $where ORDER BY a.lastpost $end", LCT => "SELECT a.tid AS threadid, b.pid AS postid, b.username, b.uid AS userid, b.subject, b.dateline, b.message AS body FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpost = b.pid $where ORDER BY a.dateline $end", LCP => "SELECT tid AS threadid, pid AS postid, username, uid AS userid, subject, dateline, message AS body FROM `#__post` " . str_replace('a.fid', 'fid', $where) . " ORDER BY dateline $end");
        return $query;
    }
    function getThread($threadid) {
        $db = & JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT tid AS threadid, fid AS forumid, firstpost AS postid FROM #__threads WHERE tid = (int)$threadid";
        $db->setQuery($query);
        $results = $db->loadObject();
        return $results;
    }
    function getReplyCount(&$existingthread) {
        $db = & JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT replies FROM #__threads WHERE tid = ' .(int) $existingthread->threadid;
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
    }
    function getForumList() {
        //get the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT fid as id, name FROM #__forums';
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList();
    }
    function getPrivateMessageCounts($userid) {
        if ($userid) {
            //get the connection to the db
            $db = JFusionFactory::getDatabase($this->getJname());
            // read unread count
            $db->setQuery('SELECT totalpms, unreadpms FROM #__users WHERE uid = ' . (int)$userid);
            $pminfo = $db->loadObject();
            return array('unread' => $pminfo->unreadpms, 'total' => $pminfo->totalpms);
        }
        return array('unread' => 0, 'total' => 0);
    }
    function getPrivateMessageURL() {
        return 'private.php';
    }
    function getViewNewMessagesURL() {
        return 'search.php?action=getnew';
    }
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
