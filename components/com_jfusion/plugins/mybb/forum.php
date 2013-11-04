<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MyBB
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
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
 * @subpackage MyBB
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionForum_mybb extends JFusionForum {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'mybb';
    }

    /**
     * @param int $threadid
     * @return string
     */
    function getThreadURL($threadid) {
        return 'showthread.php?tid=' . $threadid;
    }

    /**
     * @param int $threadid
     * @param int $postid
     * @return string
     */
    function getPostURL($threadid, $postid) {
        return 'showthread.php?tid=' . $threadid . '&amp;pid=' . $postid . '#pid' . $postid;
    }

    /**
     * @param int $userid
     *
     * @return int|string
     */
    function getProfileURL($userid) {
        return 'member.php?action=profile&uid=' . $userid;
    }

    /**
     * @param array $usedforums
     * @param string $result_order
     * @param int $result_limit
     * @return array
     */
    function getActivityQuery($usedforums, $result_order, $result_limit) {
	    $query = array();
	    try {
	        $where = (!empty($usedforums)) ? 'a.fid IN (' . $usedforums . ')' : '';
		    $limiter = ' LIMIT 0,' . $result_limit;

		    $db = JFusionFactory::getDatabase($this->getJname());

		    $q = $db->getQuery(true)
			    ->select('a.tid AS threadid, b.pid AS postid, b.username, b.uid AS userid, a.subject, b.dateline')
			    ->from('#__threads AS a')
			    ->innerJoin('#__posts as b ON a.firstpost = b.pid')
			    ->where($where)
			    ->order('a.lastpost ' . $result_order);

		    $query[LAT . '0'] = (string)$q . $limiter;

		    $q = $db->getQuery(true)
			    ->select('a.tid AS threadid, b.pid AS postid, b.username, b.uid AS userid, a.subject, b.dateline')
			    ->from('#__threads AS a')
			    ->innerJoin('#__posts as b ON a.tid = b.tid AND a.lastpost = b.dateline AND a.lastposteruid = b.uid')
			    ->where($where)
			    ->order('a.lastpost ' . $result_order);

		    $query[LAT . '1'] = (string)$q . $limiter;

		    $q = $db->getQuery(true)
			    ->select('a.tid AS threadid, b.pid AS postid, b.username, b.uid AS userid, b.subject, b.dateline, b.message AS body')
			    ->from('#__threads AS a')
			    ->innerJoin('#__posts as b ON a.firstpost = b.pid')
			    ->where($where)
			    ->order('a.dateline ' . $result_order);

		    $query[LCT] = (string)$q . $limiter;

		    $q = $db->getQuery(true)
			    ->select('a.tid AS threadid, a.pid AS postid, a.username, a.uid AS userid, a.subject, a.dateline, a.message AS body')
			    ->from('#__posts AS a')
			    ->where($where)
			    ->order('a.dateline ' . $result_order);

		    $query[LCP] = (string)$q . $limiter;
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
        return $query;
    }

    /**
     * @param int $threadid
     * @return object
     */
    function getThread($threadid) {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('tid AS threadid, fid AS forumid, firstpost AS postid')
			    ->from('#__threads')
		        ->where('tid = ' . (int)$threadid);

		    $db->setQuery($query);
		    $results = $db->loadObject();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $results = null;
	    }
        return $results;
    }

    /**
     * @param object $existingthread
     * @return int
     */
    function getReplyCount($existingthread) {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('replies')
			    ->from('#__threads')
			    ->where('tid = ' . (int)$existingthread->threadid);

		    $db->setQuery($query);
		    $result = $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = 0;
	    }
        return $result;
    }

    /**
     * @return array
     */
    function getForumList() {
	    try {
		    //get the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('fid as id, name')
			    ->from('#__forums');

		    $db->setQuery($query);
		    //getting the results
		    return $db->loadObjectList();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    return array();
	    }
    }

    /**
     * @param int $userid
     * @return array
     */
    function getPrivateMessageCounts($userid) {
	    try {
		    if ($userid) {
			    //get the connection to the db
			    $db = JFusionFactory::getDatabase($this->getJname());

			    $query = $db->getQuery(true)
				    ->select('totalpms, unreadpms')
				    ->from('#__users')
				    ->where('uid = ' . (int)$userid);
			    // read unread count
			    $db->setQuery($query);

			    $pminfo = $db->loadObject();
			    return array('unread' => $pminfo->unreadpms, 'total' => $pminfo->totalpms);
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
        return array('unread' => 0, 'total' => 0);
    }

    /**
     * @return string
     */
    function getPrivateMessageURL() {
        return 'private.php';
    }

    /**
     * @return string
     */
    function getViewNewMessagesURL() {
        return 'search.php?action=getnew';
    }

    /**
     * @param int $userid
     * @return string
     */
    function getAvatar($userid) {
	    try {
		    //get the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());
		    // read unread count

		    $query = $db->getQuery(true)
			    ->select('avatar')
			    ->from('#__users')
			    ->where('uid = ' . (int)$userid);

		    $db->setQuery($query);
		    $avatar = $db->loadResult();
		    $avatar = substr($avatar, 2);

		    $url = $this->params->get('source_url') . $avatar;
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $url = '';
	    }
        return $url;
    }
}
