<?php namespace JFusion\Plugins\smf\Platform\Joomla;

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
use Exception;
use JFactory;
use JFile;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use JFusionFunction;
use Joomla\Language\Text;
use JFusion\Plugin\Platform\Joomla;
use Joomla\Uri\Uri;
use JRegistry;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;

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
class Platform extends Joomla
{
	/**
	 * @var $callbackdata object
	 */
	var $callbackdata = null;
	/**
	 * @var bool $callbackbypass
	 */
	var $callbackbypass = null;

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
        return 'index.php?action=post;topic=' . $threadid;
    }

    /**
     * Get profile url
     *
     * @param int|string $userid user id
     *
     * @return string url
     */
    function getProfileURL($userid)
    {
        return 'index.php?action=profile&u=' . $userid;
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
		        $db = Factory::getDatabase($this->getJname());
		        $filters = func_get_args();
		        for ($i = 3; $i < $numargs; $i++) {
			        if ($filters[$i][0] == 'userid') {
				        $where.= ' HAVING userid = ' . $db->quote($filters[$i][1]);
			        }
		        }
	        } catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
		    $db = Factory::getDatabase($this->getJname());

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


		    $vulgar = explode(',', $vulgar);
		    $proper = explode(',', $proper);

		    foreach($results as $rkey => $result) {
			    foreach($vulgar as $key => $value) {
				    $results[$rkey]->subject = preg_replace('#\b' . preg_quote($value,'#') . '\b#is', $proper[$key], $result->subject);
				    if (isset($results[$rkey]->body)) {
					    $results[$rkey]->body = preg_replace('#\b' . preg_quote($value,'#') . '\b#is', $proper[$key], $result->body);
				    }
			    }
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
				    $db = Factory::getDatabase($this->getJname());

				    $userlookup = new Userinfo('joomla_int');
				    $userlookup->userid = $JUser->get('id');

				    $PluginUser = Factory::getUser($this->getJname());
				    $userlookup = $PluginUser->lookupUser($userlookup);
				    if ($userlookup) {
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
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('ID_BOARD as id, name')
			    ->from('#__boards');

		    $db->setQuery($query);
		    //getting the results
		    return $db->loadObjectList('id');
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
		        $db = Factory::getDatabase($this->getJname());
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
				Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	        }
        }
        return array('unread' => 0, 'total' => 0);
    }

    /**
     * Get avatar
     *
     * @param int $userid user id
     *
     * @return string url of avatar
     */
    function getAvatar($userid)
    {
	    $url = false;
	    try {
		    if ($userid) {
			    // Get SMF Params and get an instance of the database
			    $db = Factory::getDatabase($this->getJname());
			    // Load member params from database "mainly to get the avatar"

			    $query = $db->getQuery(true)
				    ->select('*')
				    ->from('#__members')
				    ->where('ID_MEMBER = ' . $userid);

			    $db->setQuery($query);
			    $db->execute();
			    $result = $db->loadObject();
			    if (!empty($result)) {
				    $url = '';
				    // SMF has a wired way of holding attachments. Get instance of the attachments table
				    $query = $db->getQuery(true)
					    ->select('*')
					    ->from('#__attachments')
					    ->where('ID_MEMBER = ' . $userid);

				    $db->setQuery($query);
				    $db->execute();
				    $attachment = $db->loadObject();
				    // See if the user has a specific attachment meant for an avatar
				    if (!empty($attachment) && $attachment->ID_THUMB == 0 && $attachment->ID_MSG == 0 && empty($result->avatar)) {
					    $url = $this->params->get('source_url') . 'index.php?action=dlattach;attach=' . $attachment->ID_ATTACH . ';type=avatar';
					    // If user didn't, check to see if the avatar specified in the first query is a url. If so use it.

				    } else if (preg_match('/http(s?):\/\//', $result->avatar)) {
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
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
		    $db = Factory::getDatabase($this->getJname());
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
			    $timezone = Factory::getConfig()->get('offset');
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
			    $query = 'REPLACE INTO #__log_topics SET ID_MEMBER = ' . $userid . ', ID_TOPIC = ' . $topicid . ', ID_MSG = ' . ($postid + 1);
			    $db->setQuery($query);
			    $db->execute();

			    $query = 'REPLACE INTO #__log_boards SET ID_MEMBER = ' . $userid . ', ID_BOARD = ' . $forumid . ', ID_MSG = ' . $postid;
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
		    $db = Factory::getDatabase($this->getJname());
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
	    $mainframe = Factory::getApplication();
        if ($showGuestInputs) {
            $username = $mainframe->input->post->get('guest_username', '');
            $email = $mainframe->input->post->get('guest_email', '');

            $j_username = Text::_('USERNAME');
            $j_email = Text::_('EMAIL');
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
        $quickReply = $mainframe->input->post->get('quickReply', '');
		$html .= '<textarea name="quickReply" class="inputbox quickReply" rows="15" cols="100">' . $quickReply . '</textarea><br />';
        return $html;
    }

    /**
     * Creates a post from the quick reply
     *
     * @param JRegistry $params      object with discussion bot parameters
     * @param stdClass $ids         stdClass with forum id ($ids->forumid, thread id ($ids->threadid) and first post id ($ids->postid)
     * @param object $contentitem object of content item
     * @param Userinfo $userinfo    object info of the forum user
     * @param stdClass $postinfo object with post info
     *
     * @return array with status
     */
    function createPost($params, $ids, $contentitem, Userinfo $userinfo, $postinfo)
    {
        $status = array('error' => array(), 'debug' => array());
	    try {
		    $db = Factory::getDatabase($this->getJname());
		    if ($userinfo->guest) {
			    $userinfo->username = $postinfo->username;
			    $userinfo->email = $postinfo->email;
			    $userinfo->userid = 0;
			    if (empty($userinfo->username) || empty($userinfo->email) || !preg_match('/^[^@]+@[a-zA-Z0-9._-]+\.[a-zA-Z]+$/', $userinfo->email)) {
				    throw new RuntimeException(Text::_('GUEST_FIELDS_MISSING'));
			    } else {
				    $query = $db->getQuery(true)
					    ->select('COUNT(*)')
					    ->from('#__members')
					    ->where('memberName = ' . $db->quote($userinfo->username), 'OR')
					    ->where('memberName = ' . $db->quote($userinfo->email))
					    ->where('realName = ' . $db->quote($userinfo->username))
					    ->where('realName = ' . $db->quote($userinfo->email))
					    ->where('LOWER(emailAddress) = ' . $db->quote($userinfo->username))
					    ->where('LOWER(emailAddress) = ' . $db->quote($userinfo->email));

				    $db->setQuery($query);
				    $result = $db->loadResult();
				    if (!empty($result)) {
					    throw new RuntimeException(Text::_('USERNAME_IN_USE'));
				    }
			    }
		    }
		    //setup some variables
		    $userid = $userinfo->userid;
		    $front = Factory::getFront($this->getJname());
		    //strip out html from post
		    $text = strip_tags($postinfo->text);

		    if (!empty($text)) {
			    $this->prepareText($text, 'forum', new JRegistry());
			    //get some topic information

			    $query = $db->getQuery(true)
				    ->select('t.ID_FIRST_MSG , t.numReplies, m.subject')
				    ->from('#__messages as m')
			        ->innerJoin('#__topics as t ON t.ID_TOPIC = m.ID_TOPIC')
				    ->where('t.ID_TOPIC = ' . $ids->threadid)
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
			        ->where('ID_BOARD = ' . $ids->forumid);

			    $db->setQuery($query);
			    $num = $db->loadObject();
			    $forum_stats->numPosts = $num->numPosts + 1;
			    $forum_stats->ID_BOARD = $ids->forumid;
			    $db->updateObject('#__boards', $forum_stats, 'ID_BOARD');

			    //update stats for threadmarking purposes
			    $query = 'REPLACE INTO #__log_topics SET ID_MEMBER = ' . $userid . ', ID_TOPIC = ' . $ids->threadid . ', ID_MSG = ' . ($postid + 1);
			    $db->setQuery($query);
			    $db->execute();

			    $query = 'REPLACE INTO #__log_boards SET ID_MEMBER = ' . $userid . ', ID_BOARD = ' . $ids->forumid . ', ID_MSG = ' . $postid;
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
	 * @param JRegistry $dbparams with discussion bot parameters
	 * @param object $existingthread object with forumid, threadid, and postid (first post in thread)
	 * @param int $start
	 * @param int $limit
	 * @param string $sort
	 *
	 * @return array or object Returns retrieved posts
	 */
	function getPosts($dbparams, $existingthread, $start, $limit, $sort)
    {
	    try {
		    //set the query
		    $where = 'WHERE ID_TOPIC = ' . $existingthread->threadid . ' AND ID_MSG != ' . $existingthread->postid;
		    $query = '(SELECT a.ID_TOPIC , a.ID_MSG, a.posterName, b.realName, a.ID_MEMBER, 0 AS guest, a.subject, a.posterTime, a.body, a.posterTime AS order_by_date FROM `#__messages` as a INNER JOIN #__members as b ON a.ID_MEMBER = b.ID_MEMBER ' . $where . ' AND a.ID_MEMBER != 0)';
		    $query.= ' UNION ';
		    $query.= '(SELECT a.ID_TOPIC , a.ID_MSG, a.posterName, a.posterName as realName, a.ID_MEMBER, 1 AS guest, a.subject, a.posterTime, a.body, a.posterTime AS order_by_date FROM `#__messages` as a ' . $where . ' AND a.ID_MEMBER = 0)';
		    $query.= ' ORDER BY order_by_date ' . $sort;
		    $db = Factory::getDatabase($this->getJname());

		    $db->setQuery($query, $start, $limit);

		    $posts = $db->loadObjectList();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('numReplies')
			    ->from('#__topics')
			    ->where('ID_TOPIC = ' . $existingthread->threadid);

		    $db->setQuery($query);
		    $result = $db->loadResult();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('ID_TOPIC AS threadid, ID_BOARD AS forumid, ID_FIRST_MSG AS postid')
			    ->from('#__topics')
			    ->where('ID_TOPIC = ' . $threadid);

		    $db->setQuery($query);
		    $results = $db->loadObject();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
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
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('locked')
			    ->from('#__topics')
			    ->where('ID_TOPIC = ' . $threadid);

		    $db->setQuery($query);
		    $locked = $db->loadResult();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $locked = true;
	    }
        return $locked;
    }

	/************************************************
	 * Functions For JFusion Who's Online Module
	 ***********************************************/

	/**
	 * Returns a query to find online users
	 * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
	 *
	 * @param array $usergroups
	 *
	 * @return string
	 */
	function getOnlineUserQuery($usergroups = array())
	{
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('DISTINCT u.ID_MEMBER AS userid, u.memberName AS username, u.realName AS name, u.emailAddress as email')
			->from('#__members AS u')
			->innerJoin('#__log_online AS s ON u.ID_MEMBER = s.ID_MEMBER WHERE s.ID_MEMBER != 0');

		if(!empty($usergroups)) {
			if(is_array($usergroups)) {
				$usergroups_string = implode(',', $usergroups);
				$usergroup_query = '(u.ID_GROUP IN (' . $usergroups_string . ') OR u.ID_POST_GROUP IN (' . $usergroups_string . ')';
				foreach($usergroups AS $usergroup) {
					$usergroup_query .= ' OR FIND_IN_SET(' . intval($usergroup) . ', u.additionalGroups)';
				}
				$usergroup_query .= ')';
			} else {
				$usergroup_query = '(u.ID_GROUP = ' . $usergroups . ' OR u.ID_POST_GROUP = ' . $usergroups . ' OR FIND_IN_SET(' . $usergroups . ', u.additionalGroups))';
			}
			$query->where($usergroup_query);
		}

		$query = (string)$query;

		return $query;
	}

	/**
	 * Returns number of guests
	 *
	 * @return int
	 */
	function getNumberOnlineGuests()
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(DISTINCT(ip))')
				->from('#__log_online')
				->where('ID_MEMBER = 0');

			$db->setQuery($query);
			return $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			return 0;
		}
	}

	/**
	 * Returns number of logged in users
	 *
	 * @return int
	 */
	function getNumberOnlineMembers()
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(DISTINCT(ip))')
				->from('#__log_online')
				->where('ID_MEMBER != 0');

			$db->setQuery($query);
			return $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			return 0;
		}
	}

	/**
	 * Prepares text for various areas
	 *
	 * @param string &$text             Text to be modified
	 * @param string $for              (optional) Determines how the text should be prepared.
	 * Options for $for as passed in by JFusion's plugins and modules are:
	 * joomla (to be displayed in an article; used by discussion bot)
	 * forum (to be published in a thread or post; used by discussion bot)
	 * activity (displayed in activity module; used by the activity module)
	 * search (displayed as search results; used by search plugin)
	 * @param JRegistry $params           (optional) Joomla parameter object passed in by JFusion's module/plugin
	 *
	 * @return array  $status           Information passed back to calling script such as limit_applied
	 */
	function prepareText(&$text, $for = 'forum', $params = null)
	{
		$status = array();
		if ($for == 'forum') {
			static $bbcode;
			//first thing is to remove all joomla plugins
			preg_match_all('/\{(.*)\}/U', $text, $matches);
			//find each thread by the id
			foreach ($matches[1] AS $plugin) {
				//replace plugin with nothing
				$text = str_replace('{' . $plugin . '}', "", $text);
			}
			if (!is_array($bbcode)) {
				$bbcode = array();
				//pattens to run in beginning
				$bbcode[0][] = '#<a[^>]*href=[\'|"](ftp://)(.*?)[\'|"][^>]*>(.*?)</a>#si';
				$bbcode[1][] = '[ftp=$1$2]$3[/ftp]';
				//pattens to run in end
				$bbcode[2][] = '#<table[^>]*>(.*?)<\/table>#si';
				$bbcode[3][] = '[table]$1[/table]';
				$bbcode[2][] = '#<tr[^>]*>(.*?)<\/tr>#si';
				$bbcode[3][] = '[tr]$1[/tr]';
				$bbcode[2][] = '#<td[^>]*>(.*?)<\/td>#si';
				$bbcode[3][] = '[td]$1[/td]';
				$bbcode[2][] = '#<strong[^>]*>(.*?)<\/strong>#si';
				$bbcode[3][] = '[b]$1[/b]';
				$bbcode[2][] = '#<(strike|s)>(.*?)<\/\\1>#sim';
				$bbcode[3][] = '[s]$2[/s]';
			}
			$options = array();
			$options['bbcode_patterns'] = $bbcode;
			$text = Framework::parseCode($text, 'bbcode', $options);
		} elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
			$options = array();
			//convert smilies so they show up in Joomla as images
			static $custom_smileys;
			if (!is_array($custom_smileys)) {
				$custom_smileys = array();
				try {
					$db = Factory::getDatabase($this->getJname());

					$query = $db->getQuery(true)
						->select('value, variable')
						->from('#__settings')
						->where('variable = ' . $db->quote('smileys_url'), 'OR')
						->where('variable = ' . $db->quote('smiley_sets_default'));

					$db->setQuery($query);
					$settings = $db->loadObjectList('variable');

					$query = $db->getQuery(true)
						->select('code, filename')
						->from('#__smileys')
						->order('smileyOrder');

					$db->setQuery($query);
					$smilies = $db->loadObjectList();
					if (!empty($smilies)) {
						foreach ($smilies as $s) {
							$custom_smileys[$s->code] = "{$settings['smileys_url']->value}/{$settings['smiley_sets_default']->value}/{$s->filename}";
						}
					}
				} catch (Exception $e) {
					Framework::raise(LogLevel::ERROR, $e, $this->getJname());
				}
			}
			$options['custom_smileys'] = $custom_smileys;
			$options['parse_smileys'] = \JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/smileys';
			//parse bbcode to html
			if (!empty($params) && $params->get('character_limit', false)) {
				$status['limit_applied'] = 1;
				$options['character_limit'] = $params->get('character_limit');
			}

			//add smf bbcode rules
			$options['html_patterns'] = array();
			$options['html_patterns']['li'] = array('simple_start' => '<li>', 'simple_end' => "</li>\n", 'class' => 'listitem', 'allow_in' => array('list'), 'end_tag' => 0, 'before_tag' => 's', 'after_tag' => 's', 'before_endtag' => 'sns', 'after_endtag' => 'sns', 'plain_start' => "\n * ", 'plain_end' => "\n");

			$bbcodes = array('size', 'glow', 'shadow', 'move', 'pre', 'hr', 'flash', 'ftp', 'table', 'tr', 'td', 'tt', 'abbr', 'anchor', 'black', 'blue', 'green', 'iurl', 'html', 'ltr', 'me', 'nobbc', 'php', 'red', 'rtl', 'time', 'white', 'o', 'O', '0', '@', '*', '=', '@', '+', 'x', '#');

			foreach($bbcodes as $bb) {
				if (in_array($bb, array('ftp', 'iurl'))) {
					$class = 'link';
				} elseif (in_array($bb, array('o', 'O', '0', '@', '*', '=', '@', '+', 'x', '#'))) {
					$class = 'listitem';
				} elseif ($bb == 'table') {
					$class = 'table';
				} else {
					$class = 'inline';
				}

				if (in_array($bb, array('o', 'O', '0', '@', '*', '=', '@', '+', 'x', '#'))) {
					$allow_in = array('list');
				} elseif (in_array($bb, array('td', 'tr'))) {
					$allow_in = array('table');
				} else {
					$allow_in = array('listitem', 'block', 'columns', 'inline', 'link');
				}

				$options['html_patterns'][$bb] = array('mode' => 1, 'content' => 0, 'method' => array($this, 'parseCustomBBCode'), 'class' => $class, 'allow_in' => $allow_in);
			}

			$text = Framework::parseCode($text, 'html', $options);
		} elseif ($for == 'search') {
			$text = Framework::parseCode($text, 'plaintext');
		} elseif ($for == 'activity') {
			if ($params->get('parse_text') == 'plaintext') {
				$options = array();
				$options['plaintext_line_breaks'] = 'space';
				if ($params->get('character_limit')) {
					$status['limit_applied'] = 1;
					$options['character_limit'] = $params->get('character_limit');
				}
				$text = Framework::parseCode($text, 'plaintext', $options);
			}
		}
		return $status;
	}

	/**
	 * regenerate redirect code
	 *
	 * @param string $url
	 * @param int $itemid
	 *
	 * @return string output php redirect code
	 */
	function generateRedirectCode($url, $itemid)
	{
		//create the new redirection code
		/*
		$pattern = \'#action=(login|admin|profile|featuresettings|news|packages|detailedversion|serversettings|theme|manageboards|postsettings|managecalendar|managesearch|smileys|manageattachments|viewmembers|membergroups|permissions|regcenter|ban|maintain|reports|viewErrorLog|optimizetables|detailedversion|repairboards|boardrecount|convertutf8|helpadmin|packageget)#\';
		*/
		$redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \'' . $url . '\';
$joomla_itemid = ' . $itemid . ';
    ';
		$redirect_code.= '
if (!defined(\'_JEXEC\') && strpos($_SERVER[\'QUERY_STRING\'], \'dlattach\') === false && strpos($_SERVER[\'QUERY_STRING\'], \'verificationcode\') === false)';
		$redirect_code.= '
{
    $pattern = \'#action=(login|logout)#\';
    if (!preg_match($pattern, $_SERVER[\'QUERY_STRING\'])) {
        $file = $_SERVER["SCRIPT_NAME"];
        $break = explode(\'/\', $file);
        $pfile = $break[count($break) - 1];
        $query = str_replace(\';\', \'&\', $_SERVER[\'QUERY_STRING\']);
        $jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $query;
        header(\'Location: \' . $jfusion_url);
        exit;
    }
}
//JFUSION REDIRECT END';
		return $redirect_code;
	}

	/**
	 * @param $action
	 *
	 * @return int
	 */
	function redirectMod($action)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getPluginFile('index.php', $error, $reason);
		switch($action) {
			case 'reenable':
			case 'disable':
				if ($error == 0) {
					//get the joomla path from the file
					jimport('joomla.filesystem.file');
					$file_data = file_get_contents($mod_file);
					$search = '/(\r?\n)\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/si';
					preg_match_all($search, $file_data, $matches);
					//remove any old code
					if (!empty($matches[1][0])) {
						$file_data = preg_replace($search, '', $file_data);
						if (!JFile::write($mod_file, $file_data)) {
							$error = 1;
						}
					}
				}
				if ($action == 'disable') {
					break;
				}
			case 'enable':
				$joomla_url = Factory::getParams('joomla_int')->get('source_url');
				$joomla_itemid = $this->params->get('redirect_itemid');

				//check to see if all vars are set
				if (empty($joomla_url)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' Joomla URL', $this->getJname(), $this->getJname());
				} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ItemID', $this->getJname(), $this->getJname());
				} else if (!$this->isValidItemID($joomla_itemid)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ItemID ' . Text::_('MUST BE') . ' ' . $this->getJname(), $this->getJname(), $this->getJname());
				} else if($error == 0) {
					//get the joomla path from the file
					jimport('joomla.filesystem.file');
					$file_data = file_get_contents($mod_file);
					$redirect_code = $this->generateRedirectCode($joomla_url, $joomla_itemid);

					$search = '/\<\?php/si';
					$replace = '<?php' . $redirect_code;

					$file_data = preg_replace($search, $replace, $file_data);
					JFile::write($mod_file, $file_data);
				}
				break;
		}
		return $error;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 *
	 * @return string
	 */
	function showRedirectMod($name, $value, $node, $control_name)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getPluginFile('index.php', $error, $reason);

		if($error == 0) {
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = file_get_contents($mod_file);
			preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms', $file_data, $matches);

			//compare it with our joomla path
			if(empty($matches[1][0])) {
				$error = 1;
				$reason = Text::_('MOD_NOT_ENABLED');
			}
		}

		//add the javascript to enable buttons
		if ($error == 0) {
			//return success
			$text = Text::_('REDIRECTION_MOD') . ' ' . Text::_('ENABLED');
			$disable = Text::_('MOD_DISABLE');
			$update = Text::_('MOD_UPDATE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'disable');">{$disable}</a>
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'reenable');">{$update}</a>
HTML;
		} else {
			$text = Text::_('REDIRECTION_MOD') . ' ' . Text::_('DISABLED') . ': ' . $reason;
			$enable = Text::_('MOD_ENABLE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'enable');">{$enable}</a>
HTML;
		}
		return $output;
	}

	/**
	 * uninstall function is to disable verious mods
	 *
	 * @return array
	 */
	function uninstall()
	{
		$error = $this->redirectMod('disable');
		if (!empty($error)) {
			$reason = Text::_('REDIRECT_MOD_UNINSTALL_FAILED');
			return array(false, $reason);
		}

		return array(true, '');
	}

	/************************************************
	 * For JFusion Search Plugin
	 ***********************************************/

	/**
	 * Get the search Columns for query
	 *
	 * @return object
	 */
	function getSearchQueryColumns()
	{
		$columns = new stdClass();
		$columns->title = 'p.subject';
		$columns->text = 'p.body';
		return $columns;
	}

	/**
	 * Get the search query
	 *
	 * @param object &$pluginParam custom plugin parameters in search.xml
	 *
	 * @return string
	 */
	function getSearchQuery(&$pluginParam)
	{
		$db = Factory::getDatabase($this->getJname());
		//need to return threadid, postid, title, text, created, section
		$query = $db->getQuery(true)
			->select('p.ID_TOPIC, p.ID_MSG, p.ID_BOARD, CASE WHEN p.subject = "" THEN CONCAT("Re: ",fp.subject) ELSE p.subject END AS title, p.body AS text,
                    FROM_UNIXTIME(p.posterTime, "%Y-%m-%d %h:%i:%s") AS created,
                    CONCAT_WS( "/", f.name, fp.subject ) AS section,
                    t.numViews as hits')
			->from('#__messages AS p')
			->innerJoin('#__topics AS t ON t.ID_TOPIC = p.ID_TOPIC')
			->innerJoin('#__messages AS fp ON fp.ID_MSG = t.ID_FIRST_MSG')
			->innerJoin('#__boards AS f on f.ID_BOARD = p.ID_BOARD');
		return (string)$query;
	}

	/**
	 * Add on a plugin specific clause;
	 *
	 * @param string &$where reference to where clause already generated by search bot; add on plugin specific criteria
	 * @param JRegistry &$pluginParam custom plugin parameters in search.xml
	 * @param string $ordering
	 *
	 * @return void
	 */
	function getSearchCriteria(&$where, &$pluginParam, $ordering)
	{
		if ($pluginParam->get('forum_mode', 0)) {
			$forumids = $pluginParam->get('selected_forums', array());
			$where.= ' AND p.ID_BOARD IN (' . implode(',', $forumids) . ')';
		}

		//determine how to sort the results which is required for accurate results when a limit is placed
		switch ($ordering) {
			case 'oldest':
				$sort = 'p.posterTime ASC';
				break;
			case 'category':
				$sort = 'section ASC';
				break;
			case 'popular':
				$sort = 't.numViews DESC, p.posterTime DESC';
				break;
			case 'alpha':
				$sort = 'title ASC';
				break;
			case 'newest':
			default:
				$sort = 'p.posterTime DESC';
				break;
		}
		$where .= ' ORDER BY ' . $sort;
	}

	/**
	 * filter search results
	 *
	 * @param array &$results array with search results
	 * @param object &$pluginParam custom plugin parameters in search.xml
	 *
	 * @return void
	 */
	function filterSearchResults(&$results, &$pluginParam)
	{
		try {
			$db = Factory::getDatabase($this->getJname());

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

			$vulgar = explode(',', $vulgar);
			$proper = explode(',', $proper);

			foreach($results as $rkey => &$result) {
				foreach($vulgar as $key => $value) {
					$results[$rkey]->title = preg_replace('#\b' . preg_quote($value, '#') . '\b#is', $proper[$key], $result->title);
					$results[$rkey]->text = preg_replace('#\b' . preg_quote($value, '#') . '\b#is', $proper[$key], $result->text);
				}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
	}

	/**
	 * Create search link from post info
	 *
	 * @param object $post convert post info in to a link
	 *
	 * @return string
	 */
	function getSearchResultLink($post)
	{
		/**
		 * @ignore
		 * @var $platform \JFusion\Plugin\Platform\Joomla
		 */
		$platform = Factory::getPlayform('Joomla', $this->getJname());
		return $platform->getPostURL($post->ID_TOPIC, $post->ID_MSG);
	}

	/**
	 * getBuffer
	 *
	 * @param object &$data object that has must of the plugin data in it
	 *
	 * @return void
	 */
	function getBuffer(&$data)
	{
		$mainframe = Factory::getApplication();
		$jFusion_Route = $mainframe->input->get('jFusion_Route', null, 'raw');
		if ($jFusion_Route) {
			$jFusion_Route = unserialize($jFusion_Route);
			foreach ($jFusion_Route as $value) {
				if (stripos($value, 'action') === 0) {
					list ($key, $value) = explode(',', $value);
					if ($key == 'action') {
						$mainframe->input->set('action', $value);
					}
				}
			}
		}
		$action = $mainframe->input->get('action');
		if ($action == 'register' || $action == 'reminder') {
			$master = Framework::getMaster();
			if ($master->name != $this->getJname()) {
				$JFusionMaster = Factory::getFront($master->name);
				$source_url = $this->params->get('source_url');
				$source_url = rtrim($source_url, '/');
				try {
					if ($action == 'register') {
						header('Location: ' . $source_url . '/' . $JFusionMaster->getRegistrationURL());
					} else {
						header('Location: ' . $source_url . '/' . $JFusionMaster->getLostPasswordURL());
					}
					exit();
				} catch (Exception $e) {}
			}
		}
		//handle dual logout
		if ($action == 'logout') {
			//destroy the SMF session first
			$JFusionUser = Factory::getUser($this->getJname());
			try {
				$JFusionUser->destroySession(null, null);
			} catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			}

			//destroy the Joomla session
			$mainframe->logout();
			Factory::getSession()->close();

			$cookies = Factory::getCookies();
			$cookies->addCookie($this->params->get('cookie_name'), '', 0, $this->params->get('cookie_path'), $this->params->get('cookie_domain'), $this->params->get('secure'), $this->params->get('httponly'));
			//redirect so the changes are applied
			$mainframe->redirect(str_replace('&amp;', '&', $data->baseURL));
			exit();
		}
		//handle dual login
		if ($action == 'login2') {
			//uncommented out the code below, as the smf session is needed to validate the password, which can not be done unless SSI.php is required
			//get the submitted user details
			//$username = \JFusion\Factory::getApplication()->input->get('user');
			//$password = \JFusion\Factory::getApplication()->input->get('hash_passwrd');
			//get the userinfo directly from SMF
			//$JFusionUser = \JFusion\Factory::getUser($this->getJname());
			//$userinfo = $JFusionUser->getUser($username);
			//generate the password hash
			//$test_crypt = sha1($userinfo->password . $smf_session_id);
			//validate that the password is correct
			//if (!empty($password) && !empty($test_crypt) && $password == $test_crypt){
			//}
		}
		if ($action == 'verificationcode') {
			$mainframe->input->set('format', null);
		}

		// We're going to want a few globals... these are all set later.
		global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
		global $boardurl, $boarddir, $sourcedir, $webmaster_email, $cookiename;
		global $db_connection, $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_last_error;
		global $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
		global $scripturl, $ID_MEMBER, $func, $newpassemail, $user_profile, $validationCode;
		global $settings, $options, $board_info, $attachments, $messages_request, $memberContext, $db_character_set;
		global $db_cache, $db_count, $db_show_debug;
		// Required to avoid a warning about a license violation even though this is not the case
		global $forum_version;
		$source_path = $this->params->get('source_path');
		$index_file = $source_path . 'index.php';
		if (!is_file($index_file)) {
			Framework::raise(LogLevel::WARNING, 'The path to the SMF index file set in the component preferences does not exist', $this->getJname());
			return null;
		}
		//set the current directory to SMF
		chdir($source_path);
		$this->callbackdata = $data;
		$this->callbackbypass = false;

		// Get the output
		ob_start(array($this, 'callback'));
		$h = ob_list_handlers();
		$rs = include_once ($index_file);
		// die if popup
		if ($action == 'findmember' || $action == 'helpadmin' || $action == 'spellcheck' || $action == 'requestmembers') {
			die();
		} else {
			$this->callbackbypass = true;
		}
		while(in_array(get_class($this) . '::callback', $h) ) {
			$data->buffer .= ob_get_contents();
			ob_end_clean();
			$h = ob_list_handlers();
		}
		//change the current directory back to Joomla.
		chdir(JPATH_SITE);
		// Log an error if we could not include the file
		if (!$rs) {
			Framework::raise(LogLevel::WARNING, 'Could not find SMF in the specified directory', $this->getJname());
		}
		$document = JFactory::getDocument();
		$document->addScript(JFusionFunction::getJoomlaURL() . JFUSION_PLUGIN_DIR_URL . $this->getJname() . '/js/script.js');
	}

	/**
	 * parseBody
	 *
	 * @param object &$data object that has must of the plugin data in it
	 *
	 * @return void
	 */
	function parseBody(&$data)
	{
		$regex_body = array();
		$replace_body = array();
		$callback_body = array();
		//fix for form actions
//        $regex_body[] = '#action="' . $data->integratedURL . 'index.php(.*?)"(.*?)>#m';
		$regex_body[] = '#action="(.*?)"(.*?)>#m';
		$replace_body[] = '';
		$callback_body[] = 'fixAction';

		$regex_body[] = '#(?<=href=["\'])' . preg_quote($data->integratedURL,'#') . '(.*?)(?=["\'])#mSi';
		$replace_body[] = '';
		$callback_body[] = 'fixURL';
		$regex_body[] = '#(?<=href=["\'])(\#.*?)(?=["\'])#mSi';
		$replace_body[] = '';
		$callback_body[] = 'fixURL';

		//Jump Related fix
		$regex_body[] = '#<select name="jumpto" id="jumpto".*?">(.*?)</select>#mSsi';
		$replace_body[] = '';
		$callback_body[] = 'fixJump';

		$regex_body[] = '#<input (.*?) window.location.href = \'(.*?)\' \+ this.form.jumpto.options(.*?)>#mSsi';
		$replace_body[] = '<input $1 window.location.href = jf_scripturl + this.form.jumpto.options$3>';
		$callback_body[] = '';

		$regex_body[] = '#smf_scripturl \+ \"\?action#mSsi';
		$replace_body[] = 'jf_scripturl + "&action';
		$callback_body[] = '';

		$regex_body[] = '#<a (.*?) onclick="doQuote(.*?)>#mSsi';
		$replace_body[] = '<a $1 onclick="jfusion_doQuote$2>';
		$callback_body[] = '';

		$regex_body[] = '#<a (.*?) onclick="modify_msg(.*?)>#mSsi';
		$replace_body[] = '<a $1 onclick="jfusion_modify_msg$2>';
		$callback_body[] = '';

		$regex_body[] = '#modify_save\(#mSsi';
		$replace_body[] = 'jfusion_modify_save(';
		$callback_body[] = '';

		// Captcha fix
		$regex_body[] = '#(?<=")' . preg_quote($data->integratedURL, '#') . '(index.php\?action=verificationcode;rand=.*?)(?=")#si';
		$replace_body[] = '';
		$callback_body[] = 'fixUrlNoAmp';
		/*
				$regex_body[] = '#new_url[.]indexOf[(]"rand="#si';
				$replace_body[] = 'new_url.indexOf("rand';
				$callback_body[] = '';
		*/
		//Fix auto member search
		$regex_body[] = '#(?<=toComplete\.source = \")' . preg_quote($data->integratedURL, '#') . '(.*?)(?=\")#si';
		$replace_body[] = '';
		$callback_body[] = 'fixUrlNoAmp';

		$regex_body[] = '#(?<=bccComplete\.source = \")' . preg_quote($data->integratedURL, '#') . '(.*?)(?=\")#si';
		$replace_body[] = '';
		$callback_body[] = 'fixUrlNoAmp';

		foreach ($regex_body as $k => $v) {
			//check if we need to use callback
			if(!empty($callback_body[$k])){
				$data->body = preg_replace_callback($regex_body[$k], array(&$this, $callback_body[$k]), $data->body);
			} else {
				$data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
			}
		}
	}

	/**
	 * parseHeader
	 *
	 * @param object &$data object that has must of the plugin data in it
	 *
	 * @return void
	 */
	function parseHeader(&$data)
	{
		static $regex_header, $replace_header;
		if (!$regex_header || !$replace_header) {
			$joomla_url = Factory::getParams('joomla_int')->get('source_url');
			$baseURLnoSef = 'index.php?option=com_jfusion&Itemid=' . Factory::getApplication()->input->getInt('Itemid');
			if (substr($joomla_url, -1) == '/') {
				$baseURLnoSef = $joomla_url . $baseURLnoSef;
			} else {
				$baseURLnoSef = $joomla_url . '/' . $baseURLnoSef;
			}
			// Define our preg arrays
			$regex_header = array();
			$replace_header = array();

			//convert relative links into absolute links
			$regex_header[] = '#(href|src)=("./|"/)(.*?)"#mS';
			$replace_header[] = '$1="' . $data->integratedURL . '$3"';
			//$regex_header[]    = '#(href|src)="(.*)"#mS';
			//$replace_header[]    = 'href="' . $data->integratedURL . '$2"';
			//convert relative links into absolute links
			$regex_header[] = '#(href|src)=("./|"/)(.*?)"#mS';
			$replace_header[] = '$1="' . $data->integratedURL . '$3"';
			$regex_header[] = '#var smf_scripturl = ["\'](.*?)["\'];#mS';
			$replace_header[] = 'var smf_scripturl = "$1"; var jf_scripturl = "' . $baseURLnoSef . '";';
		}
		$data->header = preg_replace($regex_header, $replace_header, $data->header);

		//fix for URL redirects
		$data->header = preg_replace_callback('#<meta http-equiv="refresh" content="(.*?)"(.*?)>#m',array( &$this,'fixRedirect'), $data->header);
	}

	/**
	 * Fix Url
	 *
	 * @param array $matches
	 *
	 * @return string url
	 */
	function fixUrl($matches)
	{
		$q = $matches[1];

		$baseURL = $this->data->baseURL;
		$fullURL = $this->data->fullURL;

		//SMF uses semi-colons to separate vars as well. Convert these to normal ampersands
		$q = str_replace(';', '&amp;', $q);
		if (strpos($q, '#') === 0) {
			$url = $fullURL . $q;
		} else {
			if (substr($baseURL, -1) != '/') {
				//non sef URls
				$q = str_replace('?', '&amp;', $q);
				$url = $baseURL . '&amp;jfile=' . $q;
			} else {
				$sefmode = $this->params->get('sefmode');
				if ($sefmode == 1) {
					$url = Factory::getApplication()->routeURL($q, Factory::getApplication()->input->getInt('Itemid'));
				} else {
					//we can just append both variables
					$url = $baseURL . $q;
				}
			}
		}
		return $url;
	}

	/**
	 * Fix url with no amps
	 *
	 * @param array $matches
	 *
	 * @return string url
	 */
	function fixUrlNoAmp($matches)
	{
		$url = $this->fixUrl($matches);
		$url = str_replace('&amp;', '&', $url);
		return $url;
	}

	/**
	 * Fix action
	 *
	 * @param array $matches
	 *
	 * @return string html
	 */
	function fixAction($matches)
	{
		$url = $matches[1];
		$extra = $matches[2];

		$baseURL = $this->data->baseURL;
		//\JFusion\Framework::raise(LogLevel::WARNING, $url, $this->getJname());
		$url = htmlspecialchars_decode($url);
		$Itemid = Factory::getApplication()->input->getInt('Itemid');
		$extra = stripslashes($extra);
		$url = str_replace(';', '&amp;', $url);
		if (substr($baseURL, -1) != '/') {
			//non-SEF mode
			$url_details = parse_url($url);
			$url_variables = array();
			$jfile = basename($url_details['path']);
			if (isset($url_details['query'])) {
				parse_str($url_details['query'], $url_variables);
				$baseURL.= '&amp;' . $url_details['query'];
			}
			//set the correct action and close the form tag
			$replacement = 'action="' . $baseURL . '"' . $extra . '>';
			$replacement.= '<input type="hidden" name="jfile" value="' . $jfile . '"/>';
			$replacement.= '<input type="hidden" name="Itemid" value="' . $Itemid . '"/>';
			$replacement.= '<input type="hidden" name="option" value="com_jfusion"/>';
		} else {
			//check to see what SEF mode is selected
			$sefmode = $this->params->get('sefmode');
			if ($sefmode == 1) {
				//extensive SEF parsing was selected
				$url = Factory::getApplication()->routeURL($url, $Itemid);
				$replacement = 'action="' . $url . '"' . $extra . '>';
				return $replacement;
			} else {
				//simple SEF mode
				$url_details = parse_url($url);
				$url_variables = array();
				$jfile = basename($url_details['path']);
				if (isset($url_details['query'])) {
					parse_str($url_details['query'], $url_variables);
					$jfile.= '?' . $url_details['query'];
				}
				$replacement = 'action="' . $baseURL . $jfile . '"' . $extra . '>';
			}
		}
		unset($url_variables['option'], $url_variables['jfile'], $url_variables['Itemid']);
		//add any other variables
		/* Commented out because of problems with wrong variables being set
		if (is_array($url_variables)){
		foreach ($url_variables as $key => $value){
		$replacement .=  '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
		}
		}
		*/
		return $replacement;
	}

	/**
	 * Fix jump code
	 *
	 * @param array $matches
	 *
	 * @return string html
	 */
	function fixJump($matches)
	{
		$content = $matches[1];

		$find = '#<option value="[?](.*?)">(.*?)</option>#mSsi';
		$replace = '<option value="&$1">$2</option>';
		$content = preg_replace($find, $replace, $content);
		return '<select name="jumpto" id="jumpto" onchange="if (this.selectedIndex > 0 && this.options[this.selectedIndex].value && this.options[this.selectedIndex].value.length) window.location.href = jf_scripturl + this.options[this.selectedIndex].value;">' . $content . '</select>';
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixRedirect($matches) {
		$url = $matches[1];
		$baseURL = $this->data->baseURL;

		//\JFusion\Framework::raise(LogLevel::WARNING, $url, $this->getJname());
		//split up the timeout from url
		$parts = explode(';url=', $url);
		$timeout = $parts[0];
		$uri = new Uri($parts[1]);
		$jfile = $uri->getPath();
		$jfile = basename($jfile);
		$query = $uri->getQuery(false);
		$fragment = $uri->getFragment();
		if (substr($baseURL, -1) != '/') {
			//non-SEF mode
			$redirectURL = $baseURL . '&amp;jfile=' . $jfile;
			if (!empty($query)) {
				$redirectURL.= '&amp;' . $query;
			}
		} else {
			//check to see what SEF mode is selected
			$sefmode = $this->params->get('sefmode');
			if ($sefmode == 1) {
				//extensive SEF parsing was selected
				$redirectURL = $jfile;
				if (!empty($query)) {
					$redirectURL.= '?' . $query;
				}
				$redirectURL = Factory::getApplication()->routeURL($redirectURL, Factory::getApplication()->input->getInt('Itemid'));
			} else {
				//simple SEF mode, we can just combine both variables
				$redirectURL = $baseURL . $jfile;
				if (!empty($query)) {
					$redirectURL.= '?' . $query;
				}
			}
		}
		if (!empty($fragment)) {
			$redirectURL .= '#' . $fragment;
		}
		$return = '<meta http-equiv="refresh" content="' . $timeout . ';url=' . $redirectURL . '">';
		//\JFusion\Framework::raise(LogLevel::WARNING, htmlentities($return), $this->getJname());
		return $return;
	}

	/**
	 * @return array
	 */
	function getPathWay()
	{
		$pathway = array();
		try {
			$db = Factory::getDatabase($this->getJname());

			$mainframe = Factory::getApplication();
			list ($board_id) = explode('.', $mainframe->input->get('board'), 1);
			list ($topic_id) = explode('.', $mainframe->input->get('topic'), 1);
			list ($action) = explode(';', $mainframe->input->get('action'), 1);

			$msg = $mainframe->input->get('msg');

			$query = $db->getQuery(true)
				->select('ID_TOPIC,ID_BOARD, subject')
				->from('#__messages')
				->order('ID_TOPIC = ' . $db->quote($topic_id));

			$db->setQuery($query);
			$topic = $db->loadObject();

			if ($topic) {
				$board_id = $topic->ID_BOARD;
			}

			if ($board_id) {
				$boards = array();
				// Loop while the parent is non-zero.
				while ($board_id != 0)
				{
					$query = $db->getQuery(true)
						->select('b.ID_PARENT , b.ID_BOARD, b.ID_CAT, b.name , c.name as catname')
						->from('#__boards AS b')
						->innerJoin('#__categories AS c ON b.ID_CAT = c.ID_CAT')
						->where('ID_BOARD = ' . $db->quote($board_id));

					$db->setQuery($query);
					$result = $db->loadObject();

					$board_id = 0;
					if ($result) {
						$board_id = $result->ID_PARENT;
						$boards[] = $result;
					}
				}
				$boards = array_reverse($boards);
				$cat_id = 0;
				foreach ($boards as $board) {
					$path = new stdClass();
					if ($board->ID_CAT != $cat_id) {
						$cat_id = $board->ID_CAT;
						$path->title = $board->catname;
						$path->url = 'index.php#' . $board->ID_CAT;
						$pathway[] = $path;

						$path = new stdClass();
						$path->title = $board->name;
						$path->url = 'index.php?board=' . $board->ID_BOARD . '.0';
					} else {
						$path->title = $board->name;
						$path->url = 'index.php?board=' . $board->ID_BOARD . '.0';
					}
					$pathway[] = $path;
				}
			}
			switch ($action) {
				case 'post':
					$path = new stdClass();
					if ( $mainframe->input->get('board')) {
						$path->title = 'Modify Toppic ( Start new topic )';
						$path->url = 'index.php?action=post&board=' . $board_id . '.0';;
					} else if ($msg) {
						$path->title = 'Modify Toppic ( ' . $topic->subject . ' )';
						$path->url = 'index.php?action=post&topic=' . $topic_id . '.msg' . $msg . '#msg' . $msg;
					} else {
						$path->title = 'Post reply ( Re: ' . $topic->subject . ' )';
						$path->url = 'index.php?action=post&topic=' . $topic_id;
					}
					$pathway[] = $path;
					break;
				case 'pm':
					$path = new stdClass();
					$path->title = 'Personal Messages';
					$path->url = 'index.php?action=pm';
					$pathway[] = $path;

					$path = new stdClass();
					if ( $mainframe->input->get('sa') == 'send' ) {
						$path->title = 'New Message';
						$path->url = 'index.php?action=pm&sa=send';
						$pathway[] = $path;
					} elseif ( $mainframe->input->get('sa') == 'search' ) {
						$path->title = 'Search Messages';
						$path->url = 'index.php?action=pm&sa=search';
						$pathway[] = $path;
					} elseif ( $mainframe->input->get('sa') == 'prune' ) {
						$path->title = 'Prune Messages';
						$path->url = 'index.php?action=pm&sa=prune';
						$pathway[] = $path;
					} elseif ( $mainframe->input->get('sa') == 'manlabels' ) {
						$path->title = 'Manage Labels';
						$path->url = 'index.php?action=pm&sa=manlabels';
						$pathway[] = $path;
					} elseif ( $mainframe->input->get('f') == 'outbox' ) {
						$path->title = 'Outbox';
						$path->url = 'index.php?action=pm&f=outbox';
						$pathway[] = $path;
					} else {
						$path->title = 'Inbox';
						$path->url = 'index.php?action=pm';
						$pathway[] = $path;
					}
					break;
				case 'search2':
					$path = new stdClass();
					$path->title = 'Search';
					$path->url = 'index.php?action=search';
					$pathway[] = $path;
					$path = new stdClass();
					$path->title = 'Search Results';
					$path->url = 'index.php?action=search';
					$pathway[] = $path;
					break;
				case 'search':
					$path = new stdClass();
					$path->title = 'Search';
					$path->url = 'index.php?action=search';
					$pathway[] = $path;
					break;
				case 'unread':
					$path = new stdClass();
					$path->title = 'Recent Unread Topics';
					$path->url = 'index.php?action=unread';
					$pathway[] = $path;
					break;
				case 'unreadreplies':
					$path = new stdClass();
					$path->title = 'Updated Topics';
					$path->url = 'index.php?action=unreadreplies';
					$pathway[] = $path;
					break;
				default:
					if ($topic_id) {
						$path = new stdClass();
						$path->title = $topic->subject;
						$path->url = 'index.php?topic=' . $topic_id;
						$pathway[] = $path;
					}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
		return $pathway;
	}

	/**
	 * this is the callback for the ob_start for smf
	 *
	 * @param string $buffer Html buffer from the smf callback
	 *
	 * @return string
	 */
	function callback($buffer)
	{
		$data = $this->callbackdata;
		$headers_list = headers_list();
		foreach ($headers_list as $value) {
			$matches = array();
			if (stripos($value, 'location') === 0) {
				if (preg_match('#' . preg_quote($data->integratedURL, '#') . '(.*?)\z#Sis', $value , $matches)) {
					header('Location: ' . $this->fixUrlNoAmp($matches));
					return $buffer;
				}
			} else if (stripos($value, 'refresh') === 0) {
				if (preg_match('#: (.*?) URL=' . preg_quote($data->integratedURL, '#') . '(.*?)\z#Sis', $value , $matches)) {
					$time = $matches[1];
					$matches[1] = $matches[2];
					header('Refresh: ' . $time . ' URL=' . $this->fixUrlNoAmp($matches));
					return $buffer;
				}
			}
		}
		if ($this->callbackbypass) {
			return $buffer;
		}
		global $context;
		if (isset($context['get_data'])) {
			if ($context['get_data'] && strpos($context['get_data'], 'jFusion_Route')) {
				$buffer = str_replace($context['get_data'], '?action=admin', $buffer);
			}
		}
		$data->buffer = $buffer;
		ini_set('pcre.backtrack_limit', strlen($data->buffer) * 2);
		$pattern = '#<head[^>]*>(.*?)<\/head>.*?<body([^>]*)>(.*)<\/body>#si';
		if (preg_match($pattern, $data->buffer, $temp)) {
			$data->header = $temp[1];
			$data->body = $temp[3];
			$pattern = '#onload=["]([^"]*)#si';
			if (preg_match($pattern, $temp[2], $temp)) {
				$js ='<script language="JavaScript" type="text/javascript">';
				$js .= <<<JS
                if(window.addEventListener) { // Standard
                    window.addEventListener(\'load\', function(){
                        {$temp[1]}
                    }, false);
                } else if(window.attachEvent) { // IE
                    window.attachEvent(\'onload\', function(){
                        {$temp[1]}
                    });
                }
JS;
				$js .='</script>';
				$data->header.= $js;
			}
			unset($temp);
			$this->parseHeader($data);
			$this->parseBody($data);
			return '<html><head>' . $data->header . '</head><body>' . $data->body . '<body></html>';
		} else {
			return $buffer;
		}
	}
}
