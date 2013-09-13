<?php

/**
 * file containing forum function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * JFusion Forum Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractforum.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionForum_smf extends JFusionForum
{

    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'smf';
    }

    /**
     * Get profile url
     *
     * @param int $threadid thread id
     *
     * @return string url
     */
    function getThreadURL($threadid)
    {
        return 'index.php?topic=' . $threadid;
    }

    /**
     * Get profile url
     *
     * @param int $threadid thread id
     * @param int $postid   post id
     *
     * @return string url
     */
    function getPostURL($threadid, $postid)
    {
        return 'index.php?topic=' . $threadid . '.msg' . $postid . '#msg' . $postid;
    }

    /**
     * @param int $forumid
     * @param int $threadid
     * @return string
     */
    function getReplyURL($forumid, $threadid)
    {
        return 'index.php?action=post;topic='.$threadid;
    }

    /**
     * Get profile url
     *
     * @param int $uid user id
     *
     * @return string url
     */
    function getProfileURL($uid)
    {
        return 'index.php?action=profile&u=' . $uid;
    }

    /**
     * Get pm message url
     *
     * @return string url
     */
    function getPrivateMessageURL()
    {
        return 'index.php?action=pm';
    }

    /**
     * Get new message url
     *
     * @return string url
     */
    function getViewNewMessagesURL()
    {
        return 'index.php?action=unread';
    }

    /**
     * Get activity query
     *
     * @param string $usedforums   sting coma separated with board id's
     * @param string $result_order how to order
     * @param string $result_limit limit
     *
     * @return array
     */
    function getActivityQuery($usedforums, $result_order, $result_limit)
    {
        $where = (!empty($usedforums)) ? ' WHERE b.ID_BOARD IN (' . $usedforums . ')' : '';
        $end = $result_order . " LIMIT 0," . $result_limit;

        $numargs = func_num_args();
        if ($numargs > 3) {
	        try {
		        $db = JFusionFactory::getDatabase($this->getJname());
		        $filters = func_get_args();
		        for ($i = 3; $i < $numargs; $i++) {
			        if ($filters[$i][0] == 'userid') {
				        $where.= ' HAVING userid = ' . $db->quote($filters[$i][1]);
			        }
		        }
	        } catch (Exception $e) {
				JFusionFunction::raiseError($e, $this->getJname());
	        }
        }

        //setup the guest where clause to be used in union query
        $guest_where = (empty($where)) ? ' WHERE b.ID_MEMBER = 0' : ' AND b.ID_MEMBER = 0';

        $query = array(
        //LAT with first post info
        LAT . '0' =>
        "(SELECT a.ID_TOPIC AS threadid, a.ID_LAST_MSG AS postid, b.posterName AS username, d.realName AS name, b.ID_MEMBER AS userid, b.subject AS subject, b.posterTime AS dateline, a.ID_BOARD as forumid, c.posterTime as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.ID_FIRST_MSG = b.ID_MSG
                INNER JOIN `#__messages` as c ON a.ID_LAST_MSG = c.ID_MSG
                INNER JOIN `#__members`  as d ON b.ID_MEMBER = d.ID_MEMBER
                $where)
        UNION
            (SELECT a.ID_TOPIC AS threadid, a.ID_LAST_MSG AS postid, b.posterName AS username, b.posterName AS name, b.ID_MEMBER AS userid, b.subject AS subject, b.posterTime AS dateline, a.ID_BOARD as forumid, c.posterTime as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.ID_FIRST_MSG = b.ID_MSG
                INNER JOIN `#__messages` as c ON a.ID_LAST_MSG = c.ID_MSG
                $where $guest_where)
        ORDER BY last_post_date $end",
        //LAT with latest post info
        LAT . '1' =>
        "(SELECT a.ID_TOPIC AS threadid, a.ID_LAST_MSG AS postid, b.posterName AS username, d.realName as name, b.ID_MEMBER AS userid, c.subject AS subject, b.posterTime AS dateline, a.ID_BOARD as forumid, b.posterTime as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.ID_LAST_MSG = b.ID_MSG
                INNER JOIN `#__messages` as c ON a.ID_FIRST_MSG = c.ID_MSG
                INNER JOIN `#__members`  as d ON b.ID_MEMBER = d.ID_MEMBER
                $where)
        UNION
            (SELECT a.ID_TOPIC AS threadid, a.ID_LAST_MSG AS postid, b.posterName AS username, b.posterName as name, b.ID_MEMBER AS userid, c.subject AS subject, b.posterTime AS dateline, a.ID_BOARD as forumid, b.posterTime as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.ID_LAST_MSG = b.ID_MSG
                INNER JOIN `#__messages` as c ON a.ID_FIRST_MSG = c.ID_MSG
                $where $guest_where)
        ORDER BY last_post_date $end",
        //LCT
        LCT =>
        "(SELECT a.ID_TOPIC AS threadid, b.ID_MSG AS postid, b.posterName AS username, d.realName as name, b.ID_MEMBER AS userid, b.subject AS subject, b.body, b.posterTime AS dateline, a.ID_BOARD as forumid, b.posterTime as topic_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.ID_FIRST_MSG = b.ID_MSG
                INNER JOIN `#__messages` as c ON a.ID_LAST_MSG = c.ID_MSG
                INNER JOIN `#__members`  as d ON b.ID_MEMBER = d.ID_MEMBER
                $where)
       UNION
            (SELECT a.ID_TOPIC AS threadid, b.ID_MSG AS postid, b.posterName AS username, b.posterName as name, b.ID_MEMBER AS userid, b.subject AS subject, b.body, b.posterTime AS dateline, a.ID_BOARD as forumid, b.posterTime as topic_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.ID_FIRST_MSG = b.ID_MSG
                INNER JOIN `#__messages` as c ON a.ID_LAST_MSG = c.ID_MSG
                $where $guest_where)
        ORDER BY topic_date $end",
        //LCP
        LCP => "
        (SELECT b.ID_TOPIC AS threadid, b.ID_MSG AS postid, b.posterName AS username, d.realName as name, b.ID_MEMBER AS userid, b.subject AS subject, b.body, b.posterTime AS dateline, b.ID_BOARD as forumid, b.posterTime as last_post_date
            FROM `#__messages` as b
                INNER JOIN `#__members` as d ON b.ID_MEMBER = d.ID_MEMBER
                INNER JOIN `#__topics` as a ON b.ID_TOPIC = a.ID_TOPIC
                $where)
        UNION
            (SELECT b.ID_TOPIC AS threadid, b.ID_MSG AS postid, b.posterName AS username, b.posterName as name, b.ID_MEMBER AS userid, b.subject AS subject, b.body, b.posterTime AS dateline, b.ID_BOARD as forumid, b.posterTime as last_post_date
            FROM `#__messages` as b
            	INNER JOIN `#__topics` as a ON b.ID_TOPIC = a.ID_TOPIC
                $where $guest_where)
        ORDER BY last_post_date $end");
        return $query;
    }

    /**
     * Filter forums from a set of results sent in / useful if the plugin needs to restrict the forums visible to a user
     *
     * @param array &$results set of results from query
     * @param int $limit   limit results parameter as set in the module's params; used for plugins that cannot limit using a query limiter
     *
     * @return void
     */
    function filterActivityResults(&$results, $limit=0)
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('value')
			    ->from('#__settings')
			    ->where('variable = ' . $db->quote('censor_vulgar'));

		    $db->setQuery($query);
		    $vulgar = $db->loadResult();

		    $query = $db->getQuery(true)
			    ->select('value')
			    ->from('#__settings')
			    ->where('variable = ' . $db->quote('censor_proper'));

		    $db->setQuery($query);
		    $proper = $db->loadResult();


		    $vulgar = explode  ( ',' , $vulgar );
		    $proper = explode  ( ',' , $proper );

		    foreach($results as $rkey => $result) {
			    foreach( $vulgar as $key => $value ) {
				    $results[$rkey]->subject = preg_replace  ( '#\b'.preg_quote($value,'#').'\b#is' , $proper[$key]  , $result->subject );
				    if (isset($results[$rkey]->body)) {
					    $results[$rkey]->body = preg_replace  ( '#\b'.preg_quote($value,'#').'\b#is' , $proper[$key]  , $result->body );
				    }
			    }
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
    }

    /**
     * @param object $post
     *
     * @return int
     */
    function checkReadStatus(&$post)
    {
        $JUser = JFactory::getUser();
	    $newstatus = 0;
	    try {
		    if (!$JUser->guest) {
			    static $markread;
			    if (!is_array($markread)) {
				    $markread = array();
				    $db = JFusionFactory::getDatabase($this->getJname());
				    $userlookup = JFusionFunction::lookupUser($this->getJname(), $JUser->id);
				    if (!empty($userlookup)) {
					    $query = $db->getQuery(true)
						    ->select('ID_MSG, ID_TOPIC')
						    ->from('#__log_topics')
						    ->where('ID_MEMBER = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $markread['topic'] = $db->loadObjectList('ID_TOPIC');

					    $query = $db->getQuery(true)
						    ->select('ID_MSG, ID_BOARD')
						    ->from('#__log_mark_read')
						    ->where('ID_MEMBER = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $markread['mark_read'] = $db->loadObjectList('ID_BOARD');

					    $query = $db->getQuery(true)
						    ->select('ID_MSG, ID_BOARD')
						    ->from('#__log_boards')
						    ->where('ID_MEMBER = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $markread['board'] = $db->loadObjectList('ID_BOARD');
				    }
			    }

			    if (isset($markread['topic'][$post->threadid])) {
				    $latest_read_msgid = $markread['topic'][$post->threadid]->ID_MSG;
			    } elseif (isset($markread['mark_read'][$post->forumid])) {
				    $latest_read_msgid = $markread['mark_read'][$post->forumid]->ID_MSG;
			    } elseif (isset($markread['board'][$post->forumid])) {
				    $latest_read_msgid = $markread['board'][$post->forumid]->ID_MSG;
			    } else {
				    $latest_read_msgid = false;
			    }
			    $newstatus = ($latest_read_msgid !== false && $post->postid > $latest_read_msgid) ? 1 : 0;
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
        return $newstatus;
    }

    /**
     * Get forum list
     *
     * @return array
     */
    function getForumList()
    {
	    try {
		    // initialise some objects
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('ID_BOARD as id, name')
			    ->from('#__boards');

		    $db->setQuery($query);
		    //getting the results
		    return $db->loadObjectList('id');
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
			return array();
	    }
    }

    /**
     * Get avatar
     *
     * @param int $userid user id
     *
     * @return array number of unread and total messages
     */
    function getPrivateMessageCounts($userid)
    {
        if ($userid) {
	        try {
		        // initialise some objects
		        $db = JFusionFactory::getDatabase($this->getJname());
		        // read unread count

		        $query = $db->getQuery(true)
			        ->select('unreadMessages')
			        ->from('#__members')
			        ->where('ID_MEMBER = ' . $userid);

		        $db->setQuery($query);
		        $unreadCount = $db->loadResult();
		        // read total pm count

		        $query = $db->getQuery(true)
			        ->select('instantMessages')
			        ->from('#__members')
			        ->where('ID_MEMBER = ' . $userid);

		        $db->setQuery($query);
		        $totalCount = $db->loadResult();
		        return array('unread' => $unreadCount, 'total' => $totalCount);
	        } catch (Exception $e) {
				JFusionFunction::raiseError($e, $this->getJname());
	        }
        }
        return array('unread' => 0, 'total' => 0);
    }

    /**
     * Get avatar
     *
     * @param int $puser_id user id
     *
     * @return string url of avatar
     */
    function getAvatar($puser_id)
    {
	    $url = false;
	    try {
		    if ($puser_id) {
			    // Get SMF Params and get an instance of the database
			    $db = JFusionFactory::getDatabase($this->getJname());
			    // Load member params from database "mainly to get the avatar"

			    $query = $db->getQuery(true)
				    ->select('*')
				    ->from('#__members')
				    ->where('ID_MEMBER = ' . $puser_id);

			    $db->setQuery($query);
			    $db->execute();
			    $result = $db->loadObject();
			    if (!empty($result)) {
				    $url = '';
				    // SMF has a wired way of holding attachments. Get instance of the attachments table
				    $query = $db->getQuery(true)
					    ->select('*')
					    ->from('#__attachments')
					    ->where('ID_MEMBER = ' . $puser_id);

				    $db->setQuery($query);
				    $db->execute();
				    $attachment = $db->loadObject();
				    // See if the user has a specific attachment meant for an avatar
				    if (!empty($attachment) && $attachment->ID_THUMB == 0 && $attachment->ID_MSG == 0 && empty($result->avatar)) {
					    $url = $this->params->get('source_url') . 'index.php?action=dlattach;attach=' . $attachment->ID_ATTACH . ';type=avatar';
					    // If user didn't, check to see if the avatar specified in the first query is a url. If so use it.

				    } else if (preg_match("/http(s?):\/\//", $result->avatar)) {
					    $url = $result->avatar;
				    } else if ($result->avatar) {
					    // If the avatar specified in the first query is not a url but is a file name. Make it one
					    $query = $db->getQuery(true)
						    ->select('*')
						    ->from('#__settings')
						    ->where('variable = ' . $db->quote('avatar_url'));

					    $db->setQuery($query);
					    $avatarurl = $db->loadObject();
					    // Check for trailing slash. If there is one DON'T ADD ONE!
					    if (substr($avatarurl->value, -1) == DIRECTORY_SEPARATOR) {
						    $url = $avatarurl->value . $result->avatar;
						    // I like redundancy. Recheck to see if there isn't a trailing slash. If there isn't one, add one.

					    } else if (substr($avatarurl->value, -1) !== DIRECTORY_SEPARATOR) {
						    $url = $avatarurl->value . '/' . $result->avatar;
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $url = false;
	    }
        return $url;
    }

    /**
     * Creates new thread and posts first post
     *
     * @param JRegistry &$dbparams    with discussion bot parameters
     * @param object &$contentitem object containing content information
     * @param int    $forumid      Id of forum to create thread
     * @param array  &$status      contains errors and status of actions
     *
     * @return void
     */
    function createThread(&$dbparams, &$contentitem, $forumid, &$status)
    {
	    try {
		    //setup some variables
		    $userid = $this->getThreadAuthor($dbparams, $contentitem);
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $subject = trim(strip_tags($contentitem->title));

		    //prepare the content body
		    $text = $this->prepareFirstPostBody($dbparams, $contentitem);

		    //the user information

		    $query = $db->getQuery(true)
			    ->select('memberName, emailAddress')
			    ->from('#__members')
			    ->where('ID_MEMBER = ' . $userid);

		    $db->setQuery($query);
		    $smfUser = $db->loadObject();
		    if ($dbparams->get('use_content_created_date', false)) {
			    $mainframe = JFactory::getApplication();
			    $timezone = $mainframe->getCfg('offset');
			    $timestamp = strtotime($contentitem->created);
			    //undo Joomla timezone offset
			    $timestamp += ($timezone * 3600);
		    } else {
			    $timestamp = time();
		    }
		    $topic_row = new stdClass();
		    $topic_row->isSticky = 0;
		    $topic_row->ID_BOARD = $forumid;
		    $topic_row->ID_FIRST_MSG = $topic_row->ID_LAST_MSG = 0;
		    $topic_row->ID_MEMBER_STARTED = $topic_row->ID_MEMBER_UPDATED = $userid;
		    $topic_row->ID_POLL = 0;
		    $topic_row->numReplies = 0;
		    $topic_row->numViews = 0;
		    $topic_row->locked = 0;

		    $db->insertObject('#__topics', $topic_row, 'ID_TOPIC');

		    $topicid = $db->insertid();
		    $post_row = new stdClass();
		    $post_row->ID_BOARD = $forumid;
		    $post_row->ID_TOPIC = $topicid;
		    $post_row->posterTime = $timestamp;
		    $post_row->ID_MEMBER = $userid;
		    $post_row->subject = $subject;
		    $post_row->posterName = $smfUser->memberName;
		    $post_row->posterEmail = $smfUser->emailAddress;
		    $post_row->posterIP = $_SERVER['REMOTE_ADDR'];
		    $post_row->smileysEnabled = 1;
		    $post_row->modifiedTime = 0;
		    $post_row->modifiedName = '';
		    $post_row->body = $text;
		    $post_row->icon = 'xx';
		    $db->insertObject('#__messages', $post_row, 'ID_MSG');

		    $postid = $db->insertid();
		    $post_row = new stdClass();
		    $post_row->ID_MSG = $postid;
		    $post_row->ID_MSG_MODIFIED = $postid;
		    $db->updateObject('#__messages', $post_row, 'ID_MSG');

		    $topic_row = new stdClass();
		    $topic_row->ID_FIRST_MSG = $postid;
		    $topic_row->ID_LAST_MSG = $postid;
		    $topic_row->ID_TOPIC = $topicid;
		    $db->updateObject('#__topics', $topic_row, 'ID_TOPIC');

		    $forum_stats = new stdClass();
		    $forum_stats->ID_BOARD = $forumid;

		    $query = $db->getQuery(true)
			    ->select('m.posterTime')
			    ->from('#__messages as m')
		        ->innerJoin('#__boards AS b ON b.ID_LAST_MSG = m.ID_MSG')
			    ->where('b.ID_BOARD = ' . $forumid);

		    $db->setQuery($query);
		    $lastPostTime = (int)$db->loadResult();
		    if ($dbparams->get('use_content_created_date', false)) {
			    //only update the last post for the board if it really is newer
			    $updateLastPost = ($timestamp > $lastPostTime) ? true : false;
		    } else {
			    $updateLastPost = true;
		    }
		    if ($updateLastPost) {
			    $forum_stats->ID_LAST_MSG = $postid;
			    $forum_stats->ID_MSG_UPDATED = $postid;
		    }

		    $query = $db->getQuery(true)
			    ->select('numTopics, numPosts')
			    ->from('#__boards')
			    ->where('ID_BOARD = ' . $forumid);

		    $db->setQuery($query);
		    $num = $db->loadObject();
		    $forum_stats->numPosts = $num->numPosts + 1;
		    $forum_stats->numTopics = $num->numTopics + 1;
		    $db->updateObject('#__boards', $forum_stats, 'ID_BOARD');

		    if ($updateLastPost) {
			    $query = 'REPLACE INTO #__log_topics SET ID_MEMBER = '.$userid.', ID_TOPIC = '.$topicid.', ID_MSG = ' . ($postid + 1);
			    $db->setQuery($query);
			    $db->execute();

			    $query = 'REPLACE INTO #__log_boards SET ID_MEMBER = '.$userid.', ID_BOARD = '.$forumid.', ID_MSG = '.$postid;
			    $db->setQuery($query);
			    $db->execute();
		    }
		    if (!empty($topicid) && !empty($postid)) {
			    //add information to update forum lookup
			    $status['threadinfo']->forumid = $forumid;
			    $status['threadinfo']->threadid = $topicid;
			    $status['threadinfo']->postid = $postid;
		    }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
    }

    /**
     * Updates information in a specific thread/post
     *
     * @param JRegistry &$dbparams       with discussion bot parameters
     * @param object &$existingthread with existing thread info
     * @param object &$contentitem    object containing content information
     * @param array  &$status         contains errors and status of actions
     *
     * @return void
     */
    function updateThread(&$dbparams, &$existingthread, &$contentitem, &$status)
    {
	    try {
		    $postid = $existingthread->postid;
		    //setup some variables
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $subject = trim(strip_tags($contentitem->title));

		    //prepare the content body
		    $text = $this->prepareFirstPostBody($dbparams, $contentitem);

		    $timestamp = time();
		    $userid = $dbparams->get('default_user');

		    $query = $db->getQuery(true)
			    ->select('memberName')
			    ->from('#__members')
			    ->where('ID_MEMBER = ' . $userid);

		    $db->setQuery($query);
		    $smfUser = $db->loadObject();
		    $post_row = new stdClass();
		    $post_row->subject = $subject;
		    $post_row->body = $text;
		    $post_row->modifiedTime = $timestamp;
		    $post_row->modifiedName = $smfUser->memberName;
		    $post_row->ID_MSG_MODIFIED = $postid;
		    $post_row->ID_MSG = $postid;
		    $db->updateObject('#__messages', $post_row, 'ID_MSG');
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
    }

    /**
     * Returns HTML of a quick reply
     *
     * @param JRegistry  &$dbparams       with discussion bot parameters
     * @param boolean $showGuestInputs toggles whether to show guest inputs or not
     *
     * @return string of html
     */
    function createQuickReply(&$dbparams, $showGuestInputs)
    {
        $html = '';
        if ($showGuestInputs) {
            $username = JFactory::getApplication()->input->post->get('guest_username', '');
            $email = JFactory::getApplication()->input->post->get('guest_email', '');

            $j_username = JText::_('USERNAME');
            $j_email = JText::_('EMAIL');
            $html = <<<HTML
                <table>
                    <tr>
                        <td>
                            {$j_username}:
                        </td>
                        <td>
                            <input name='guest_username' value='{$username}' class='inputbox'/>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            {$j_email}
                        </td>
                        <td>
                            <input name='guest_email' value='{$email}' class='inputbox'/>
                        </td>
                    </tr>
                    {$this->createCaptcha($dbparams)}
                </table>
                <br />
HTML;
        }
        $quickReply = JFactory::getApplication()->input->post->get('quickReply', '');
		$html .= '<textarea id="quickReply" name="quickReply" class="inputbox" rows="15" cols="100">'.$quickReply.'</textarea><br />';
        return $html;
    }

    /**
     * Creates a post from the quick reply
     *
     * @param object &$dbparams    with discussion bot parameters
     * @param object &$ids         stdClass with thread id ($ids->threadid) and first post id ($ids->postid)
     * @param object &$contentitem object of content item
     * @param object &$userinfo    object info of the forum user
     *
     * @return array with status
     */
    function createPost(&$dbparams, &$ids, &$contentitem, &$userinfo)
    {
        $status = array('error' => array(),'debug' => array());
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
		    if ($userinfo->guest) {
			    $userinfo->username = JFactory::getApplication()->input->post->get('guest_username', '');
			    $userinfo->email = JFactory::getApplication()->input->post->get('guest_email', '');
			    $userinfo->userid = 0;
			    if (empty($userinfo->username) || empty($userinfo->email) || !preg_match('/^[^@]+@[a-zA-Z0-9._-]+\.[a-zA-Z]+$/', $userinfo->email)) {
				    throw new RuntimeException(JText::_('GUEST_FIELDS_MISSING'));
			    } else {
				    $query = $db->getQuery(true)
					    ->select('COUNT(*)')
					    ->from('#__members')
					    ->where('memberName = ' . $db->Quote($userinfo->username), 'OR')
					    ->where('memberName = ' . $db->Quote($userinfo->email))
					    ->where('realName = ' . $db->Quote($userinfo->username))
					    ->where('realName = ' . $db->Quote($userinfo->email))
					    ->where('LOWER(emailAddress) = ' . $db->Quote($userinfo->username))
					    ->where('LOWER(emailAddress) = ' . $db->Quote($userinfo->email));

				    $db->setQuery($query);
				    $result = $db->loadResult();
				    if (!empty($result)) {
					    throw new RuntimeException(JText::_('USERNAME_IN_USE'));
				    }
			    }
		    }
		    //setup some variables
		    $userid = $userinfo->userid;
		    $public = JFusionFactory::getPublic($this->getJname());
		    $text = JFactory::getApplication()->input->post->get('quickReply', false);
		    //strip out html from post
		    $text = strip_tags($text);

		    if (!empty($text)) {
			    $public->prepareText($text);
			    //get some topic information

			    $query = $db->getQuery(true)
				    ->select('t.ID_FIRST_MSG , t.numReplies, m.subject')
				    ->from('#__messages as m')
			        ->innerJoin('#__topics as t ON t.ID_TOPIC = m.ID_TOPIC')
				    ->where('t.ID_TOPIC = '.$ids->threadid)
				    ->where('m.ID_MSG = t.ID_FIRST_MSG');

			    $db->setQuery($query);
			    $topic = $db->loadObject();
			    //the user information
			    if ($userinfo->guest) {
				    $smfUser = new stdClass();
				    $smfUser->memberName = $userinfo->username;
				    $smfUser->emailAddress = $userinfo->email;
			    } else {
				    $query = $db->getQuery(true)
					    ->select('memberName, emailAddress')
					    ->from('#__members')
					    ->where('ID_MEMBER = ' . $userid);

				    $db->setQuery($query);
				    $smfUser = $db->loadObject();
			    }
			    $timestamp = time();
			    $post_row = new stdClass();
			    $post_row->ID_BOARD = $ids->forumid;
			    $post_row->ID_TOPIC = $ids->threadid;
			    $post_row->posterTime = $timestamp;
			    $post_row->ID_MEMBER = $userid;
			    $post_row->subject = 'Re: ' . $topic->subject;
			    $post_row->posterName = $smfUser->memberName;
			    $post_row->posterEmail = $smfUser->emailAddress;
			    $post_row->posterIP = $_SERVER['REMOTE_ADDR'];
			    $post_row->smileysEnabled = 1;
			    $post_row->modifiedTime = 0;
			    $post_row->modifiedName = '';
			    $post_row->body = $text;
			    $post_row->icon = 'xx';
			    $db->insertObject('#__messages', $post_row, 'ID_MSG');

			    $postid = $db->insertid();
			    $post_row = new stdClass();
			    $post_row->ID_MSG = $postid;
			    $post_row->ID_MSG_MODIFIED = $postid;
			    $db->updateObject('#__messages', $post_row, 'ID_MSG');

			    //store the postid
			    $status['postid'] = $postid;
			    $topic_row = new stdClass();
			    $topic_row->ID_LAST_MSG = $postid;
			    $topic_row->ID_MEMBER_UPDATED = (int)$userid;
			    $topic_row->numReplies = $topic->numReplies + 1;
			    $topic_row->ID_TOPIC = $ids->threadid;
			    $db->updateObject('#__topics', $topic_row, 'ID_TOPIC');

			    $forum_stats = new stdClass();
			    $forum_stats->ID_LAST_MSG = $postid;
			    $forum_stats->ID_MSG_UPDATED = $postid;

			    $query = $db->getQuery(true)
				    ->select('numPosts')
				    ->from('#__boards')
			        ->where('ID_BOARD = '.$ids->forumid);

			    $db->setQuery($query);
			    $num = $db->loadObject();
			    $forum_stats->numPosts = $num->numPosts + 1;
			    $forum_stats->ID_BOARD = $ids->forumid;
			    $db->updateObject('#__boards', $forum_stats, 'ID_BOARD');

			    //update stats for threadmarking purposes
			    $query = 'REPLACE INTO #__log_topics SET ID_MEMBER = '.$userid.', ID_TOPIC = '.$ids->threadid.', ID_MSG = ' . ($postid + 1);
			    $db->setQuery($query);
			    $db->execute();

			    $query = 'REPLACE INTO #__log_boards SET ID_MEMBER = '.$userid.', ID_BOARD = '.$ids->forumid.', ID_MSG = '.$postid;
			    $db->setQuery($query);
			    $db->execute();
		    }
	    } catch (Exception $e) {
		    $status['error'] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * Retrieves the posts to be displayed in the content item if enabled
     *
     * @param JRegistry $dbparams       with discussion bot parameters
     * @param object $existingthread info about thread
     *
     * @return array or object Returns retrieved posts
     */
	function getPosts($dbparams, $existingthread)
    {
	    try {
		    //set the query
		    $sort = $dbparams->get('sort_posts');
		    $where = 'WHERE ID_TOPIC = '.$existingthread->threadid.' AND ID_MSG != '.$existingthread->postid;
		    $query = '(SELECT a.ID_TOPIC , a.ID_MSG, a.posterName, b.realName, a.ID_MEMBER, 0 AS guest, a.subject, a.posterTime, a.body, a.posterTime AS order_by_date FROM `#__messages` as a INNER JOIN #__members as b ON a.ID_MEMBER = b.ID_MEMBER '.$where.' AND a.ID_MEMBER != 0)';
		    $query.= ' UNION ';
		    $query.= '(SELECT a.ID_TOPIC , a.ID_MSG, a.posterName, a.posterName as realName, a.ID_MEMBER, 1 AS guest, a.subject, a.posterTime, a.body, a.posterTime AS order_by_date FROM `#__messages` as a '.$where.' AND a.ID_MEMBER = 0)';
		    $query.= ' ORDER BY order_by_date '.$sort;
		    $jdb = JFusionFactory::getDatabase($this->getJname());

		    if($dbparams->get('enable_pagination',true)) {
			    $application = JFactory::getApplication() ;
			    $limit = (int) $application->getUserStateFromRequest( 'global.list.limit_discuss', 'limit_discuss', 5, 'int' );
			    $limitstart = (int) $application->getUserStateFromRequest( 'global.list.limitstart_discuss', 'limitstart_discuss', 0, 'int' );
			    $jdb->setQuery($query, $limitstart, $limit);
		    } else {
			    $limit_posts = $dbparams->get('limit_posts');
			    $query .= empty($limit_posts) || trim($limit_posts)==0 ? '' :  ' LIMIT 0,'.$limit_posts;
			    $jdb->setQuery($query);
		    }

		    $posts = $jdb->loadObjectList();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $posts = array();
	    }
        return $posts;
    }

    /**
     * get number of replies
     *
     * @param object &$existingthread info about existing thread
     *
     * @return int
     */
    function getReplyCount($existingthread)
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('numReplies')
			    ->from('#__topics')
			    ->where('ID_TOPIC = ' . $existingthread->threadid);

		    $db->setQuery($query);
		    $result = $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = 0;
	    }
        return $result;
    }

    /**
     * Returns an object of columns used in createPostTable()
     * Saves from having to repeat the same code over and over for each plugin
     * For example:
     * $columns->userid = 'userid';
     * $columns->username = 'username';
     * $columns->username_clean = 'username_clean'; //if applicable for filtered usernames
     * $columns->dateline = 'dateline';
     * $columns->posttext = 'pagetext';
     * $columns->posttitle = 'title';
     * $columns->postid = 'postid';
     * $columns->threadid = 'threadid';
     *
     * @return object with column names
     */
    function getDiscussionColumns()
    {
        $columns = new stdClass();
        $columns->userid = 'ID_MEMBER';
        $columns->username = 'posterName';
        $columns->name = 'realName';
        $columns->dateline = 'posterTime';
        $columns->posttext = 'body';
        $columns->posttitle = 'subject';
        $columns->postid = 'ID_MSG';
        $columns->threadid = 'ID_TOPIC';
        $columns->guest = 'guest';
        return $columns;
    }

    /**
     * get info about a thread
     *
     * @param int $threadid thread id
     *
     * @return object
     */
    function getThread($threadid)
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('ID_TOPIC AS threadid, ID_BOARD AS forumid, ID_FIRST_MSG AS postid')
			    ->from('#__topics')
			    ->where('ID_TOPIC = ' . $threadid);

		    $db->setQuery($query);
		    $results = $db->loadObject();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $results = null;
	    }
        return $results;
    }

    /**
     * @param int $threadid
     * @return bool
     */
    function getThreadLockedStatus($threadid) {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('locked')
			    ->from('#__topics')
			    ->where('ID_TOPIC = ' . $threadid);

		    $db->setQuery($query);
		    $locked = $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $locked = true;
	    }
        return $locked;
    }
}
