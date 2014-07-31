<?php namespace JFusion\Plugins\vbulletin\Platform\Joomla;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use Exception;
use JFactory;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use JFusionFunction;
use Joomla\Language\Text;
use JFusion\Plugin\Platform\Joomla;
use Joomla\Uri\Uri;
use JPluginHelper;
use JRegistry;
use JUri;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;
use JFusion\Plugins\vbulletin\Helper;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Forum Class for vBulletin
 * For detailed descriptions on these functions please check the model.abstractforum.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Platform extends Joomla
{
	static private $mods = array('redirect' => 'JFusion Redirect Plugin',
		'duallogin' => 'JFusion Dual Login Plugin');

    /**
     * @var $helper Helper
     */
    var $helper;

    /**
     * @param int $forumid
     * @param int $threadid
     *
     * @return string
     */
    function getReplyURL($forumid, $threadid)
    {
        return 'newreply.php?do=newreply&t=' . $threadid . '&noquote=1';
    }

    /**
     * @param int $threadid
     *
     * @return object
     */
    function getThread($threadid)
    {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('threadid, forumid, firstpostid AS postid')
			    ->from('#__thread')
			    ->where('threadid = ' . $threadid);

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
     *
     * @return bool
     */
    function getThreadLockedStatus($threadid)
    {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('open')
			    ->from('#__thread')
			    ->where('threadid = ' . $threadid);

		    $db->setQuery($query);
		    $open = $db->loadResult();
		    $locked = ($open) ? false : true;
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $locked = true;
	    }
        return $locked;
    }

    /**
     * @param JRegistry &$dbparams
     * @param object &$contentitem
     * @param int $forumid
     * @param array &$status
     *
     * @return void
     */
    function createThread(&$dbparams, &$contentitem, $forumid, &$status)
    {
        $userid = $this->getThreadAuthor($dbparams, $contentitem);

        //strip title of all html characters and convert entities back to applicable characters (to prevent double encoding by vB)
        $title = trim(strip_tags(html_entity_decode($contentitem->title)));

        $useContentDate = $dbparams->get('use_content_created_date', false);
        if ($useContentDate) {
            $timezone = Factory::getConfig()->get('offset');
            $timestamp = strtotime($contentitem->created);
            //undo Joomla timezone offset
            $timestamp += ($timezone * 3600);
        } else {
             $timestamp = 'timenow';
        }

		//prepare the content body
		$text = $this->prepareFirstPostBody($dbparams, $contentitem);

        $apidata = array(
            'userid' => $userid,
            'forumid' => $forumid,
            'timestamp' => $timestamp,
            'ipaddress' => $_SERVER['REMOTE_ADDR'],
        	'title' => $title,
            'text' => $text
        );
        $response = $this->helper->apiCall('createThread', $apidata);

        if ($response['success']) {
            $threadid = $response['new_id'];
            $postid = $response['firstpostid'];

            //if using the content date, manually update the forum's stats
            if ($useContentDate) {
	            try {
	                $db = Factory::getDatabase($this->getJname());
	                $user = Factory::getUser($this->getJname());
	                $userinfo = $user->getUser($userid, 'userid');

		            $query = $db->getQuery(true)
			            ->update('#__forum')
			            ->set('threadcount = threadcount + 1')
			            ->set('replycount = replycount + 1');

	                //is this really the forum's latest thread?
	                /**
	                 * @TODO $foruminfo undefined ... if ($timestamp > $foruminfo['lastpost']) { not sure what to replace it with
	                 */
	                if ($timestamp > 0) {
		                $query->set('lastpost = ' . $timestamp)
			                ->set('lastpostid = ' . $postid)
			                ->set('lastthreadid = ' . $threadid)
			                ->set('lastposter = ' . $db->quote($userinfo->username))
			                ->set('lastthread = ' . $db->quote($title))
			                ->set('lasticonid = 0');
	                }
		            $query->where('forumid = ' . $forumid);
		            $db->setQuery($query);

		            $db->execute();
	            } catch (Exception $e) {
		            $status[LogLevel::ERROR][] = $e->getMessage();
	            }
            }

			//add information to update forum lookup
			$status['threadinfo']->forumid = $forumid;
			$status['threadinfo']->threadid = $threadid;
			$status['threadinfo']->postid = $postid;
		}
	    foreach ($response['errors'] as $error) {
		    $status[LogLevel::ERROR][] = $error;
	    }
	    foreach ($response['debug'] as $debug) {
		    $status[LogLevel::DEBUG][] = $debug;
	    }
    }

    /**
     * @param JRegistry $params
     * @param stdClass $ids         stdClass with forum id ($ids->forumid, thread id ($ids->threadid) and first post id ($ids->postid)
     * @param object $contentitem
     * @param Userinfo $userinfo
     * @param stdClass $postinfo object with post info
     *
     * @return array
     */
    function createPost($params, $ids, $contentitem, Userinfo $userinfo, $postinfo)
    {
	    $status = array(LogLevel::ERROR => array(), LogLevel::DEBUG => array());
	    try {
		    if ($userinfo->guest) {
			    $userinfo->username = $postinfo->username;
			    $userinfo->userid = 0;
			    if (empty($userinfo->username)) {
				    throw new RuntimeException(Text::_('GUEST_FIELDS_MISSING'));
			    } else {
				    $db = Factory::getDatabase($this->getJname());

				    $query = $db->getQuery(true)
					    ->select('COUNT(*)')
					    ->from('#__user')
					    ->where('LOWER(username) = ' . $db->quote(strtolower($userinfo->username)), 'OR')
					    ->where('LOWER(email) = ' . $db->quote(strtolower($userinfo->username)));

				    $db->setQuery($query);
				    $result = $db->loadResult();
				    if (!empty($result)) {
					    throw new RuntimeException(Text::_('USERNAME_IN_USE'));
				    }

				    $name_field = $this->params->get('name_field');
				    if (!empty($name_field)) {
					    $query = $db->getQuery(true)
						    ->select('COUNT(*)')
						    ->from('#__userfield')
						    ->where('LOWER(' . $name_field . ') = ' . strtolower($db->quote($userinfo->username)), 'OR')
						    ->where('LOWER(' . $name_field . ') = ' . strtolower($db->quote($userinfo->username)));

					    $db->setQuery($query);
					    $result = $db->loadResult();
					    if (!empty($result)) {
						    throw new RuntimeException(Text::_('USERNAME_IN_USE'));
					    }
				    }
			    }
		    }
		    //strip out html from post
		    $text = strip_tags($postinfo->text);

		    if (!empty($text)) {
			    $foruminfo = $this->getForumInfo($ids->forumid);
			    $threadinfo = $this->getThreadInfo($ids->threadid, $params);
			    $post_approved = ($userinfo->guest && ($foruminfo['moderatenewposts'] || $params->get('moderate_guests', 1))) ? 0 : 1;
			    $title = 'Re: ' . $threadinfo['title'];
			    $this->prepareText($title, 'forum', new JRegistry());

			    $apidata = array(
				    'userinfo' => $this->helper->convertUserData($userinfo),
				    'ids' => $ids,
				    'ipaddress' => $_SERVER['REMOTE_ADDR'],
				    'title' => $title,
				    'text' => $text,
				    'post_approved' => $post_approved
			    );
			    $response = $this->helper->apiCall('createPost', $apidata);

			    if ($response['success']) {
				    $id = $response['new_id'];

				    //store post id
				    $status['postid'] = $id;
			    }
			    foreach ($response['errors'] as $error) {
				    $status[LogLevel::ERROR][] = $error;
			    }
			    foreach ($response['debug'] as $debug) {
				    $status[LogLevel::DEBUG][] = $debug;
			    }

			    //update moderation status to tell discussion bot to notify user
			    $status['post_moderated'] = ($post_approved) ? 0 : 1;
		    }
	    } catch (Exception $e) {
		    $status[LogLevel::ERROR][] = Text::_('USERNAME_IN_USE');
	    }
        return $status;
    }

    /**
     * @param JRegistry &$dbparams
     * @param object &$existingthread
     * @param object &$contentitem
     * @param array &$status
     *
     * @return void
     */
    function updateThread(&$dbparams, &$existingthread, &$contentitem, &$status)
    {
        //strip title of all html characters and convert entities back to applicable characters (to prevent double encoding by vB)
        $title = trim(strip_tags(html_entity_decode($contentitem->title)));
		$text = $this->prepareFirstPostBody($dbparams, $contentitem);

        $apidata = array(
	        'existingthread' => $existingthread,
            'ipaddress' => $_SERVER['REMOTE_ADDR'],
        	'title' => $title,
            'text' => $text
        );
        $response = $this->helper->apiCall('updateThread', $apidata);
	    foreach ($response['errors'] as $error) {
		    $status[LogLevel::ERROR][] =  $error;
	    }
	    foreach ($response['debug'] as $debug) {
		    $status[LogLevel::DEBUG][] = $debug;
	    }
    }

    /**
     * @param $id
     * @param $dbparams
     *
     * @return mixed
     */
    function getThreadInfo($id, &$dbparams)
    {
	    try {
		    $threadid = intval($id);
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('if (visible = 2, 1, 0) AS isdeleted, thread.*')
			    ->from('#__thread AS thread')
			    ->where('thread.threadid = ' . $threadid);

		    $db->setQuery($query);
		    $threadinfo = $db->loadAssoc();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $threadinfo = null;
	    }
        return $threadinfo;
    }

    /**
     * @param $id
     *
     * @return array
     */
    function getForumInfo($id) {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('*')
			    ->from('#__forum')
			    ->where('forumid = ' . (int) $id);

		    $db->setQuery($query);
		    $foruminfo = $db->loadAssoc();

		    //set the forum options
		    $options = array(
			    'active' 			=> 1,
			    'allowposting' 		=> 2,
			    'cancontainthreads'	=> 4,
			    'moderatenewpost' 	=> 8,
			    'moderatenewthread' => 16,
			    'moderateattach' 	=> 32,
			    'allowbbcode' 		=> 64,
			    'allowimages' 		=> 128,
			    'allowhtml'			=> 256,
			    'allowsmilies' 		=> 512,
			    'allowicons' 		=> 1024,
			    'allowratings' 		=> 2048,
			    'countposts' 		=> 4096,
			    'canhavepassword' 	=> 8192,
			    'indexposts' 		=> 16384,
			    'styleoverride' 	=> 32768,
			    'showonforumjump' 	=> 65536,
			    'prefixrequired' 	=> 131072
		    );

		    foreach($options as $name => $val) {
			    $foruminfo[$name] = (($foruminfo['options'] & $val) ? 1 : 0);
		    }

		    $foruminfo['depth'] = substr_count($foruminfo['parentlist'], ',') - 1;
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $foruminfo = array();
	    }

		return $foruminfo;
	}

	/**
	 * @param JRegistry $dbparams with discussion bot parameters
	 * @param object $existingthread object with forumid, threadid, and postid (first post in thread)
	 * @param int $start
	 * @param int $limit
	 * @param string $sort
	 *
	 * @return array
	 */
    function getPosts($dbparams, $existingthread, $start, $limit, $sort)
    {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    //set the query
		    if (empty($name_field)) {
			    $query = $db->getQuery(true)
				    ->select('a.postid , a.username, a.username as name, a.userid, CASE WHEN a.userid = 0 THEN 1 ELSE 0 END AS guest, a.title, a.dateline, a.pagetext, a.threadid, b.title AS threadtitle')
				    ->from('#__post as a')
			        ->innerJoin('#__thread` as b ON a.threadid = b.threadid')
				    ->where('a.threadid = ' . $existingthread->threadid)
				    ->where('a.postid != ' . $existingthread->postid)
				    ->where('a.visible = 1')
			        ->order('a.dateline ' . $sort);
		    } else {
			    $name_field = $this->params->get('name_field');

			    $q1 = $db->getQuery(true)
				    ->select('a.postid , a.username, CASE WHEN f.' . $name_field . ' IS NULL OR f.' . $name_field . ' = \'\' THEN a.username ELSE f.' . $name_field . ' END AS name, a.userid, 0 AS guest, a.title, a.dateline, a.dateline as order_by_date, a.pagetext, a.threadid, b.title AS threadtitle')
				    ->from('#__post as a')
				    ->innerJoin('#__thread as b ON a.threadid = b.threadid')
				    ->innerJoin('#__userfield as f ON f.userid = a.userid')
				    ->where('a.threadid = ' . $existingthread->threadid)
				    ->where('a.postid != ' . $existingthread->postid)
				    ->where('a.visible = 1')
				    ->where('a.userid != 0');

			    $q2 = $db->getQuery(true)
				    ->select('a.postid , a.username, a.username as name, a.userid, 1 AS guest, a.title, a.dateline, a.dateline as order_by_date, a.pagetext, a.threadid, b.title AS threadtitle')
				    ->from('#__post as a')
				    ->innerJoin('#__thread as b ON a.threadid = b.threadid')
				    ->where('a.threadid = ' . $existingthread->threadid)
				    ->where('a.postid != ' . $existingthread->postid)
				    ->where('a.visible = 1')
				    ->where('a.userid != 0');

			    $query = '( ' . (string)$q1 . ' ) UNION ( ' . (string)$q2 . ' ) ORDER BY order_by_date ' . $sort;
		    }

		    $db->setQuery($query, $start, $limit);

		    $posts = $db->loadObjectList();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $posts = array();
	    }
        return $posts;
    }

    /**
     * @param object $existingthread
     *
     * @return int
     */
    function getReplyCount($existingthread)
    {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('replycount')
			    ->from('#__thread')
			    ->where('threadid = ' . $existingthread->threadid);

		    $db->setQuery($query);
		    $result = $db->loadResult();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $result = 0;
	    }
        return $result;
    }

    /**
     * @return object
     */
    function getDiscussionColumns()
    {
        $columns = new stdClass();
        $columns->userid = 'userid';
        $columns->username = 'username';
        $columns->name = 'name';
        $columns->dateline = 'dateline';
        $columns->posttext = 'pagetext';
        $columns->posttitle = 'title';
        $columns->postid = 'postid';
        $columns->threadid = 'threadid';
        $columns->threadtitle = 'threadtitle';
        $columns->guest = 'guest';
        return $columns;
    }

    /**
     * @param int $threadid
     *
     * @return string
     */
    function getThreadURL($threadid)
    {
        return $this->helper->getVbURL('showthread.php?t=' . $threadid, 'threads');
    }

    /**
     * @param int $threadid
     * @param int $postid
     *
     * @return string
     */
    function getPostURL($threadid, $postid)
    {
        return $this->helper->getVbURL('showthread.php?p=' . $postid . '#post' . $postid, 'post');
    }

    /**
     * @param int|string $userid
     *
     * @return string
     */
    function getProfileURL($userid)
    {
        return $this->helper->getVbURL('member.php?u=' . $userid, 'members');
    }

    /**
     * @param int|string $userid
     *
     * @return array
     */
    function getPrivateMessageCounts($userid)
    {
	    $pmcount = array('total' => 0, 'unread' => 0);
	    try {
		    // initialise some objects
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('pmtotal, pmunread')
			    ->from('#__user')
			    ->where('userid = ' . $userid);

		    $db->setQuery($query);
		    $vbPMData = $db->loadObject();
		    $pmcount['total'] = $vbPMData->pmtotal;
		    $pmcount['unread'] = $vbPMData->pmunread;
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $pmcount;
    }

    /**
     * @return string
     */
    function getPrivateMessageURL()
    {
        return 'private.php';
    }

    /**
     * @return string
     */
    function getViewNewMessagesURL()
    {
        return 'search.php?do=getnew';
    }

    /**
     * @param int $userid
     *
     * @return null|string
     */
    function getAvatar($userid)
    {
        $url = false;
	    try {
		    if ($userid) {
			    $db = Factory::getDatabase($this->getJname());

			    $query = $db->getQuery(true)
				    ->select('u.avatarid, u.avatarrevision, avatarpath, NOT ISNULL(c.userid) AS usecustom, c.dateline')
				    ->from('#__user AS u')
			        ->leftJoin('#__avatar AS a ON a.avatarid = u.avatarid')
				    ->leftJoin('#__customavatar AS c ON c.userid = u.userid')
				    ->where('u.userid = ' . $userid);

			    $db->setQuery($query);
			    $avatar = $db->loadObject();

			    $usefileavatar = $avatarurl = null;

			    $query = $db->getQuery(true)
				    ->select('varname, value')
				    ->from('#__setting')
				    ->where('varname = ' . $db->quote('usefileavatar'), 'OR')
				    ->where('varname = ' . $db->quote('avatarurl'));

			    $db->setQuery($query);
			    $settings = $db->loadObjectList();
			    if ($settings) {
				    foreach ($settings as $s) {
					    ${$s->varname} = $s->value;
				    }
			    }

			    if (!empty($avatar->avatarpath)) {
				    if (strpos($avatar->avatarpath, 'http') === false) {
					    $url = $this->params->get('source_url') . $avatar->avatarpath;
				    } else {
					    $url = $avatar->avatarpath;
				    }
			    } elseif (isset($avatar->usecustom)) {
				    if ($usefileavatar && $avatarurl) {
					    //avatars are saved to the filesystem
					    $url = (strpos($avatarurl, 'http') === false) ? $this->params->get('source_url') . $avatarurl : $avatarurl;
					    $url .= '/avatar' . $userid . '_' . $avatar->avatarrevision . '.gif';
				    } else {
					    //avatars are saved in the database
					    $url = $this->params->get('source_url') . 'image.php?u=' . $userid . '&amp;dateline=' . $avatar->dateline;
				    }
			    }
		    }
	    } catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $url;
    }

    /**
     * @param array $usedforums
     * @param string $result_order
     * @param int $result_limit
     * @return array
     */
    function getActivityQuery($usedforums, $result_order, $result_limit)
    {
        $usedforums = $this->filterForumList($usedforums);
        //if no there were no forums passed, the entire list is called and filtered in filterForumList
        //however if for some reason filterForumList fails, set forumid to 0 to prevent anything from showing protecting private forums
        $where = (!empty($usedforums)) ? 'WHERE a.forumid IN (' . implode(',', $usedforums) . ') AND b.visible = 1 AND c.password = ""' : 'WHERE a.forumid = 0 AND b.visible = 1 AND c.password = ""';
        $end = $result_order . ' LIMIT 0,' . ($result_limit + 25);

        $numargs = func_num_args();

        if ($numargs > 3) {
	        try {
		        $db = Factory::getDatabase($this->getJname());
		        $filters = func_get_args();
		        for ($i = 3; $i < $numargs; $i++) {
			        if ($filters[$i][0] == 'userid') {
				        $where.= ' AND b.userid = ' . $db->quote($filters[$i][1]);
			        }
		        }
	        } catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	        }
        }

        $name_field = $this->params->get('name_field');
        $query = array();
        if (empty($name_field)) {
            //Latest active topic with first post info
            $query[LAT . '0'] = 'SELECT a.threadid, a.lastpostid AS postid, b.username, b.username as name, b.userid, CASE WHEN b.userid = 0 THEN 1 ELSE 0 END AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid ' . $where . ' ORDER BY a.lastpost ' . $end;

            //Latest active topic with lastest post info
            $query[LAT . '1'] = 'SELECT a.threadid, a.lastpostid AS postid, b.username, b.username as name, b.userid, CASE WHEN b.userid = 0 THEN 1 ELSE 0 END AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost FROM `#__thread` as a INNER JOIN `#__post` as b ON a.lastpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid ' . $where . ' ORDER BY a.lastpost ' . $end;

            //Latest created topic
            $query[LCT] = 'SELECT a.threadid, b.postid, b.username, b.username as name, b.userid, CASE WHEN b.userid = 0 THEN 1 ELSE 0 END AS guest, a.title AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid ' . $where . ' ORDER BY a.dateline ' . $end;

            //Latest created post
            $query[LCP] = 'SELECT b.threadid, b.postid, b.username, b.username as name, b.userid, CASE WHEN b.userid = 0 THEN 1 ELSE 0 END AS guest, CASE WHEN b.title = \'\' THEN CONCAT("Re: ",a.title) ELSE b.title END AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost FROM `#__thread` as a INNER JOIN `#__post` AS b ON a.threadid = b.threadid INNER JOIN #__forum as c ON a.forumid = c.forumid ' . $where . ' ORDER BY b.dateline ' . $end;
        } else {
            //Latest active topic with first post info
            $query[LAT . '0'] = '(SELECT a.threadid, a.lastpostid AS postid, b.username, b.userid, 0 AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost, a.lastpost as order_by_date, CASE WHEN f.' . $name_field . ' IS NULL OR f.' . $name_field . ' = \'\' THEN b.username ELSE f.' . $name_field . ' END AS name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid INNER JOIN `#__userfield` as f ON f.userid = b.userid ' . $where . ' AND b.userid != 0)';
            $query[LAT . '0'].= ' UNION ';
            $query[LAT . '0'].= '(SELECT a.threadid, a.lastpostid AS postid, b.username, b.userid, 1 AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost, a.lastpost as order_by_date, b.username as name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid ' . $where . ' AND b.userid = 0)';
            $query[LAT . '0'].= ' ORDER BY order_by_date ' . $end;

            //Latest active topic with lastest post info
            $query[LAT . '1'] = '(SELECT a.threadid, a.lastpostid AS postid, b.username, b.userid, 0 AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost, a.lastpost as order_by_date, CASE WHEN f.' . $name_field . ' IS NULL OR f.' . $name_field . ' = \'\' THEN b.username ELSE f.' . $name_field . ' END AS name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.lastpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid INNER JOIN `#__userfield` as f ON f.userid = b.userid ' . $where . ' AND b.userid != 0)';
            $query[LAT . '1'].= ' UNION ';
            $query[LAT . '1'].= '(SELECT a.threadid, a.lastpostid AS postid, b.username, b.userid, 1 AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost, a.lastpost as order_by_date, b.username as name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.lastpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid ' . $where . ' AND b.userid = 0)';
            $query[LAT . '1'].= ' ORDER BY order_by_date ' . $end;

            //Latest created topic
            $query[LCT] = '(SELECT a.threadid, b.postid, b.username, b.userid, 0 AS guest, a.title AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost, a.dateline as order_by_date, CASE WHEN f.' . $name_field.' IS NULL OR f.' . $name_field . ' = \'\' THEN b.username ELSE f.' . $name_field . ' END AS name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid INNER JOIN `#__userfield` as f ON f.userid = b.userid ' . $where . ' AND b.userid != 0)';
            $query[LCT].= ' UNION ';
            $query[LCT].= '(SELECT a.threadid, b.postid, b.username, b.userid, 1 AS guest, a.title AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost, a.dateline as order_by_date, b.username AS name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid ' . $where . ' and b.userid = 0)';
            $query[LCT].= ' ORDER BY order_by_date ' . $end;

            //Latest created post
            $query[LCP] = '(SELECT b.threadid, b.postid, b.username, b.userid, 0 AS guest, CASE WHEN b.title = \'\' THEN CONCAT("Re: ",a.title) ELSE b.title END AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost, b.dateline as order_by_date, CASE WHEN f.' . $name_field . ' IS NULL OR f.' . $name_field . ' = \'\' THEN b.username ELSE f.' . $name_field . ' END AS name FROM `#__thread` as a INNER JOIN `#__post` AS b ON a.threadid = b.threadid INNER JOIN #__forum as c ON a.forumid = c.forumid INNER JOIN `#__userfield` as f ON f.userid = b.userid ' . $where . ' AND b.userid != 0)';
            $query[LCP].= ' UNION ';
            $query[LCP].= '(SELECT b.threadid, b.postid, b.username, b.userid, 1 AS guest, CASE WHEN b.title = \'\' THEN CONCAT("Re: ",a.title) ELSE b.title END AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost, b.dateline as order_by_date, b.username AS name FROM `#__thread` as a INNER JOIN `#__post` AS b ON a.threadid = b.threadid INNER JOIN #__forum as c ON a.forumid = c.forumid ' . $where . ' AND b.userid = 0)';
            $query[LCP].= ' ORDER BY order_by_date ' . $end;
        }
        return $query;
    }

    /**
     * @param object $post
     * @return int
     */
    function checkReadStatus(&$post)
    {
		$JUser = JFactory::getUser();
	    $newstatus = 0;
	    try {
		    if (!$JUser->guest) {
			    static $marktimes;
			    if (!is_array($marktimes)) {
				    $marktimes = array();
				    $db = Factory::getDatabase($this->getJname());

				    $userlookup = new Userinfo('joomla_int');
				    $userlookup->userid = $JUser->get('id');

				    $PluginUser = Factory::getUser($this->getJname());
				    $userlookup = $PluginUser->lookupUser($userlookup);
				    if ($userlookup) {
					    $query = $db->getQuery(true)
						    ->select('threadid, readtime')
						    ->from('#__threadread')
						    ->where('userid = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $marktimes['thread'] = $db->loadObjectList('threadid');

					    $query = $db->getQuery(true)
						    ->select('forumid, readtime')
						    ->from('#__forumread')
						    ->where('userid = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $marktimes['forum'] = $db->loadObjectList('forumid');

					    $query = $db->getQuery(true)
						    ->select('lastvisit')
						    ->from('#__user')
						    ->where('userid = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $marktimes['user'] = $db->loadResult();
				    }
			    }

			    if (isset($marktimes['thread'][$post->threadid])) {
				    $marktime = $marktimes['thread'][$post->threadid]->readtime;
			    } elseif (isset($marktimes['forum'][$post->forumid])) {
				    $marktime = $marktimes['forum'][$post->forumid]->readtime;
			    } elseif (isset($marktimes['user'])) {
				    $marktime = $marktimes['user'];
			    } else {
				    $marktime = false;
			    }

			    $newstatus = ($marktime !== false && $post->lastpost > $marktime) ? 1 : 0;
		    }
	    } catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $newstatus;
    }

    /**
     * @param bool $objectList
     * @return array
     */
    function getForumList($objectList = true)
    {
	    $results = array();
	    try {
		    //get the connection to the db
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('forumid as id, title_clean as name, options')
			    ->from('#__forum')
		        ->order('forumid');

		    $db->setQuery($query);
		    $results = $db->loadObjectList('id');
		    //we have to filter out those that are considered categories
		    $temp = array();
		    foreach ($results as $r) {
			    if ($r->options & 4) {
				    $temp[$r->id] = $r;
			    }
		    }
		    $results = $temp;
		    if (!$objectList) {
			    $array = array();
			    foreach ($results as $r) {
				    $array[$r->id] = $r->id;
			    }
			    $results = $array;
		    }
	    } catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $results;
    }

    /**
     * @param string $userid
     * @return array
     */
    function getForumPermissions($userid = 'find')
    {
        static $forumPerms, $groupPerms;
	    try {
		    if (empty($forumPerms)) {
			    if ($userid == 'find') {
				    //get the joomla user
				    $JoomlaUser = JFactory::getUser();

				    //get the vb user
				    if (!$JoomlaUser->guest) {
					    $userlookup = new Userinfo('joomla_int');
					    $userlookup->userid = $JoomlaUser->get('id');

					    $PluginUser = Factory::getUser($this->getJname());
					    $userlookup = $PluginUser->lookupUser($userlookup);
					    if ($userlookup) {
						    $userid = $userlookup->userid;
					    } else {
						    //oops, something has failed
						    $userid = 0;
					    }
				    } else {
					    $userid = 0;
				    }
			    }
			    //define some permissions
			    defined('CAN_VIEW_THREAD_CONTENT') OR define('CAN_VIEW_THREAD_CONTENT', 524288);
			    defined('CAN_VIEW_FORUM') OR define('CAN_VIEW_FORUM', 1);
			    defined('CAN_VIEW_OTHERS_THREADS') OR define('CAN_VIEW_OTHERS_THREADS', 2);
			    defined('CAN_SEARCH_FORUM') OR define('CAN_SEARCH_FORUM', 4);
			    //get the usergroup permissions
			    $db = Factory::getDatabase($this->getJname());
			    if ($userid != 0) {
				    $query = $db->getQuery(true)
					    ->select('u.usergroupid AS gid, u.membergroupids, g.forumpermissions AS perms')
					    ->from('#__user AS u')
				        ->innerJoin('#__usergroup AS g ON u.usergroupid = g.usergroupid')
					    ->where('u.userid = ' . $userid);
			    } else {
				    $query = $db->getQuery(true)
					    ->select('usergroupid AS gid, forumpermissions AS perms')
					    ->from('#__usergroup')
					    ->where('usergroupid = ' . $db->quote('1'));
			    }
			    $db->setQuery($query);
			    $usergroup = $db->loadObject();
			    $groupPerms = $usergroup->perms;
			    //merge the permissions of member groups
			    if (!empty($usergroup->membergroupids)) {
				    $membergroups = explode(',', $usergroup->membergroupids);

				    $query = $db->getQuery(true)
					    ->select('forumpermissions')
					    ->from('#__usergroup')
					    ->where('usergroupid IN (' . $usergroup->membergroupids . ')');

				    $db->setQuery($query);
				    $perms = $db->loadObjectList();
				    foreach ($perms as $p) {
					    //use which ever grants the greatest number of permissions
					    if ($p->forumpermissions > $groupPerms) {
						    $groupPerms = $p->forumpermissions;
					    }
				    }
			    }
			    //get custom forum permissions
			    $query = $db->getQuery(true)
				    ->select('p.forumpermissions, p.forumid, p.usergroupid, f.parentlist, f.childlist')
				    ->from('#__forumpermission AS p')
			        ->innerJoin('#__forum AS f ON p.forumid = f.forumid')
				    ->where('p.usergroupid = ' . $usergroup->gid)
			        ->order('p.forumid');

			    $db->setQuery($query);
			    $perms = $db->loadObjectList();
			    $tempPerms = array();
			    if (is_array($perms)) {
				    foreach ($perms as $p) {
					    $tempPerms[$p->forumid]['perms'] = $p->forumpermissions;
					    $tempPerms[$p->forumid]['childlist'] = explode(',', $p->childlist, -1);
					    $tempPerms[$p->forumid]['parentlist'] = array_reverse(explode(',', $p->parentlist, -1));
				    }
			    }
			    //get custom forum permissions for member groups
			    if (!empty($membergroups)) {
				    $query = $db->getQuery(true)
					    ->select('p.forumpermissions, p.forumid, p.usergroupid, f.parentlist, f.childlist')
					    ->from('#__forumpermission AS p')
					    ->innerJoin('#__forum AS f ON p.forumid = f.forumid')
					    ->where('p.usergroupid IN (' . $usergroup->membergroupids . ')')
					    ->order('p.forumid');

				    $db->setQuery($query);
				    $perms = $db->loadObjectList();
				    foreach ($perms as $p) {
					    if (!isset($tempPerms[$p->forumid])) {
						    $tempPerms[$p->forumid]['perms'] = 0;
						    $tempPerms[$p->forumid]['childlist'] = explode(',', $p->childlist, -1);
						    $tempPerms[$p->forumid]['parentlist'] = array_reverse(explode(',', $p->parentlist, -1));
					    }
					    //use which ever grants the greatest number of permissions
					    if ($p->forumpermissions > $tempPerms[$p->forumid]['perms']) {
						    $tempPerms[$p->forumid]['perms'] = $p->forumpermissions;
					    }
				    }
			    }
			    $forumPerms = array();
			    //we need to copy parent's permissions to the children if the child does not have custom permissions
			    foreach ($tempPerms as $id => $attributes) {
				    if (!array_key_exists($id, $forumPerms)) {
					    $forumPerms[$id] = $tempPerms[$id]['perms'];
				    }
				    $parent = '';
				    //the permissions are set by the top parent with custom params
				    foreach ($attributes['parentlist'] as $p) {
					    if (array_key_exists($p, $tempPerms)) {
						    $parent = $p;
						    break;
					    }
				    }
				    if (!empty($parent)) {
					    foreach ($attributes['childlist'] AS $c) {
						    if (!array_key_exists($c, $tempPerms) && array_key_exists($parent, $tempPerms)) {
							    $forumPerms[$c] = $tempPerms[$parent]['perms'];
						    }
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    $forumPerms = array();
		    $groupPerms = null;
	    }

        return array($groupPerms, $forumPerms);
    }

    /**
     * @param object $results
     * @param int $limit
     * @param string $idKey
     * @param bool $search
     *
     * @return void
     */
    function filterActivityResults(&$results, $limit = 0, $idKey = 'forumid', $search = false)
    {
        //get the joomla user
        $JoomlaUser = JFactory::getUser();
        //get the vb user
        if (!$JoomlaUser->guest) {
	        $userlookup = new Userinfo('joomla_int');
	        $userlookup->userid = $JoomlaUser->get('id');

	        $PluginUser = Factory::getUser($this->getJname());
	        $userlookup = $PluginUser->lookupUser($userlookup);
            if ($userlookup) {
                $userid = $userlookup->userid;
            } else {
                //oops, something has failed
                $userid = 0;
            }
        } else {
            $userid = 0;
        }
        list($groupPerms, $forumPerms) = $this->getForumPermissions($userid);
        //use a counter to keep track of number of results
        $counter = 0;
        if (is_array($results)) {
            foreach ($results as $k => $r) {
                $forumid = $r->$idKey;
                $counter++;
                //use the custom forum permissions by default; if they are empty then use the groups permission
                $perms = (isset($forumPerms[$forumid])) ? $forumPerms[$forumid] : $groupPerms;
                //check permissions
                if ($search) {
                    if (!($perms & CAN_SEARCH_FORUM) || !($perms & CAN_VIEW_FORUM) || !($perms & CAN_VIEW_THREAD_CONTENT) || ($r->userid != $userid && !($perms & CAN_VIEW_OTHERS_THREADS))) {
                        unset($results[$k]);
                        $counter--;
                    }
                } else {
                    if (!$perms & CAN_VIEW_FORUM || ($r->userid != $userid && !($perms & CAN_VIEW_OTHERS_THREADS))) {
                        //user does not have permission to view the forum or another user's thread
                        unset($results[$k]);
                        $counter--;
                    } elseif (!($perms & CAN_VIEW_THREAD_CONTENT)) {
                        //user cannot view posts within the thread
                        if (defined('ACTIVITY_MODE') && ACTIVITY_MODE == LCP) {
                            //in activity module and using latest created post mode so remove the entire post
                            unset($results[$k]);
                            $counter--;
                        } else {
                            //in activity module and using the latest active topic or latest created topic mode so just empty the post body
                            $r->body = '';
                        }
                    }
                }
                //if the limit has been met, remove the rest of the results
                if (!empty($limit) && $counter == $limit) {
                    $results = array_slice($results, 0, $limit);
                    break;
                }
            }
        }
    }

    /**
     * @param $forumids
     * @return array
     */
    function filterForumList($forumids)
    {
        list($groupPerms, $forumPerms) = $this->getForumPermissions();
        if (empty($forumids)) {
	        $forumids = $this->getForumList(false);
        } elseif (!is_array($forumids)) {
            $forumids = explode(',', $forumids);
        }
        if (!empty($forumids)) {
            if (is_array($forumids)) {
                foreach ($forumids as $k => $id) {
                    //use the custom forum permissions by default; if they are empty then use the groups permission
                    $perms = (isset($forumPerms[$id])) ? $forumPerms[$id] : $groupPerms;
                    if (!$perms & CAN_VIEW_FORUM) {
                        //user does not have permission to view the forum
                        unset($forumids[$k]);
                    }
                }
            }
        }
        if (is_array($forumids)) {
            $forumids = array_values($forumids);
        }
        return $forumids;
    }

	/**
	 * @param bool $keepalive
	 *
	 * @return int
	 */
	function syncSessions($keepalive = false)
	{
		try {
			$userPlugin = Factory::getUser($this->getJname());
			$debug = (defined('DEBUG_SYSTEM_PLUGIN') ? true : false);
			if ($debug) {
				Framework::raise(LogLevel::NOTICE, 'keep alive called', $this->getJname());
			}
			$options = array();
			//retrieve the values for vb cookies
			$cookie_prefix = $this->params->get('cookie_prefix');
			$vbversion = $this->helper->getVersion();
			if ((int) substr($vbversion, 0, 1) > 3) {
				if (substr($cookie_prefix, -1) !== '_') {
					$cookie_prefix .= '_';
				}
			}
			$mainframe = Factory::getApplication();
			$cookie_sessionhash = $mainframe->input->cookie->get($cookie_prefix . 'sessionhash', '');
			$cookie_userid = $mainframe->input->cookie->get($cookie_prefix . 'userid', '');
			$cookie_password = $mainframe->input->cookie->get($cookie_prefix . 'password', '');
			$JUser = JFactory::getUser();
			if (JPluginHelper::isEnabled('system', 'remember')) {
				jimport('joomla.utilities.utility');
				$hash = Framework::getHash('JLOGIN_REMEMBER');

				$joomla_persistant_cookie = $mainframe->input->cookie->get($hash, '', 'raw');
			} else {
				$joomla_persistant_cookie = '';
			}
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('userid')
				->from('#__session')
				->where('sessionhash = ' . $db->quote($cookie_sessionhash));

			$db->setQuery($query);
			$session_userid = $db->loadResult();

			if (!$JUser->get('guest', true)) {
				//user logged into Joomla so let's check for an active vb session
				if ($debug) {
					Framework::raise(LogLevel::NOTICE, 'Joomla user logged in', $this->getJname());
				}

				//find the userid attached to Joomla userid

				$userlookup = new Userinfo('joomla_int');
				$userlookup->userid = $JUser->get('id');
				$userlookup->username = $JUser->get('username');
				$userlookup->email = $JUser->get('email');

				$PluginUser = Factory::getUser($this->getJname());
				$userlookup = $PluginUser->lookupUser($userlookup);
				$vb_userid = (!empty($userlookup)) ? $userlookup->userid : 0;

				//is there a valid VB user logged in?
				$vb_session = ((!empty($cookie_userid) && !empty($cookie_password) && $cookie_userid == $vb_userid) || (!empty($session_userid) && $session_userid == $vb_userid)) ? 1 : 0;

				if ($debug) {
					Framework::raise(LogLevel::NOTICE, 'vB session active: ' . $vb_session, $this->getJname());
				}

				//create a new session if one does not exist and either keep alive is enabled or a joomla persistent cookie exists
				if (!$vb_session) {
					if ((!empty($keepalive) || !empty($joomla_persistant_cookie))) {
						if ($debug) {
							Framework::raise(LogLevel::NOTICE, 'vbulletin guest', $this->getJname());
							Framework::raise(LogLevel::NOTICE, 'cookie_sessionhash = '. $cookie_sessionhash, $this->getJname());
							Framework::raise(LogLevel::NOTICE, 'session_userid = '. $session_userid, $this->getJname());
							Framework::raise(LogLevel::NOTICE, 'vb_userid = ' . $vb_userid, $this->getJname());
						}
						//enable remember me as this is a keep alive function anyway
						$options['remember'] = 1;
						//get the user's info

						$query = $db->getQuery(true)
							->select('username, email')
							->from('#__user')
							->where('userid = ' . $userlookup->userid);

						$db->setQuery($query);
						$user_identifiers = $db->loadObject();
						$userinfo = $userPlugin->getUser($user_identifiers);
						//create a new session
						try {
							$status = $userPlugin->createSession($userinfo, $options);
							if ($debug) {
								Framework::raise(LogLevel::NOTICE, $status, $this->getJname());
							}
						} catch (Exception $e) {
							Framework::raise(LogLevel::ERROR, $e, $this->getJname());
						}
						//signal that session was changed
						return 1;
					} else {
						if ($debug) {
							Framework::raise(LogLevel::NOTICE, 'keep alive disabled or no persistant session found so calling Joomla\'s destorySession', $this->getJname());
						}
						$JoomlaUser = Factory::getUser('joomla_int');

						$userinfo = \JFusionFunction::getJoomlaUser((object)$JUser);

						$options['clientid'][] = '0';
						try {
							$status = $JoomlaUser->destroySession($userinfo, $options);
							if ($debug) {
								Framework::raise(LogLevel::NOTICE, $status, $this->getJname());
							}
						} catch (Exception $e) {
							Framework::raise(LogLevel::ERROR, $e, $JoomlaUser->getJname());
						}
					}
				} elseif ($debug) {
					Framework::raise(LogLevel::NOTICE, 'Nothing done as both Joomla and vB have active sessions.', $this->getJname());
				}
			} elseif (!empty($session_userid) || (!empty($cookie_userid) && !empty($cookie_password))) {
				//the user is not logged into Joomla and we have an active vB session

				if ($debug) {
					Framework::raise(LogLevel::NOTICE, 'Joomla has a guest session', $this->getJname());
				}

				if (!empty($cookie_userid) && $cookie_userid != $session_userid) {
					try {
						$status = $userPlugin->destroySession(null, null);
						if ($debug) {
							Framework::raise(LogLevel::NOTICE, 'Cookie userid did not match session userid thus destroyed vB\'s session.', $this->getJname());
							Framework::raise(LogLevel::NOTICE, $status, $this->getJname());
						}
					} catch (Exception $e) {
						Framework::raise(LogLevel::ERROR, $e, $this->getJname());
					}
				}

				//find the Joomla user id attached to the vB user
				$userlookup = new Userinfo($this->getJname());
				$userlookup->userid = $session_userid;

				$PluginUser = Factory::getUser($this->getJname());
				$userlookup = $PluginUser->lookupUser($userlookup);
				if (!empty($joomla_persistant_cookie)) {
					if ($debug) {
						Framework::raise(LogLevel::NOTICE, 'Joomla persistant cookie found so let Joomla handle renewal', $this->getJname());
					}
					return 0;
				} elseif (empty($keepalive)) {
					if ($debug) {
						Framework::raise(LogLevel::NOTICE, 'Keep alive disabled so kill vBs session', $this->getJname());
					}
					//something fishy or user chose not to use remember me so let's destroy vB's session
					try {
						$userPlugin->destroySession(null, null);
					} catch (Exception $e) {
						Framework::raise(LogLevel::ERROR, $e, $this->getJname());
					}
					return 1;
				} elseif ($debug) {
					Framework::raise(LogLevel::NOTICE, 'Keep alive enabled so renew Joomla\'s session', $this->getJname());
				}

				$joomlaid = $JUser->get('id');
				if ($joomlaid) {
					if ($debug) {
						Framework::raise(LogLevel::NOTICE, 'Found a phpBB user so attempting to renew Joomla\'s session.', $this->getJname());
					}
					//get the user's info
					$db = JFactory::getDBO();

					$query = $db->getQuery(true)
						->select('username, email')
						->from('#__users')
						->where('id = ' . $joomlaid);

					$db->setQuery($query);
					$user_identifiers = $db->loadObject();
					$JoomlaUser = Factory::getUser('joomla_int');
					$userinfo = $JoomlaUser->getUser($user_identifiers);
					if (!empty($userinfo)) {
						global $JFusionActivePlugin;
						$JFusionActivePlugin = $this->getJname();
						try {
							$status = $JoomlaUser->createSession($userinfo, $options);
							if ($debug) {
								Framework::raise(LogLevel::NOTICE, $status, $this->getJname());
							}
						} catch (Exception $e) {
							Framework::raise(LogLevel::ERROR, $e, $JoomlaUser->getJname());
						}

						//no need to signal refresh as Joomla will recognize this anyway
						return 0;
					}
				}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
		return 0;
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

		$name_field = $this->params->get('name_field');

		$query = $db->getQuery(true)
			->select('DISTINCT u.userid, u.username AS username, u.email');

		if (!empty($name_field)) {
			$query->select('CASE WHEN f.' . $name_field . ' IS NULL OR f.' . $name_field . ' = \'\' THEN u.username ELSE f.' . $name_field . ' END AS name')
				->from('#__userfield as f')
				->innerJoin('#__user AS u ON f.userid = u.userid');
		} else {
			$query->select('u.username as name')
				->from('#__user AS u');
		}

		$query->innerJoin('#__session AS s ON u.userid = s.userid')
			->where('s.userid != 0');

		if (!empty($usergroups)) {
			$usergroups = implode(',', $usergroups);

			$query->where('u.usergroupid IN (' . $usergroups . ')');
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
				->select('COUNT(DISTINCT(host))')
				->from('#__session')
				->where('userid = 0');

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
				->select('COUNT(DISTINCT(userid))')
				->from('#__session')
				->where('userid != 0');

			$db->setQuery($query);
			return $db->loadResult();
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			return 0;
		}
	}

	/**
	 * @param string $text
	 * @param string $for
	 * @param JRegistry $params
	 *
	 * @return array
	 */
	function prepareText(&$text, $for = 'forum', $params = null)
	{
		$status = array();
		if ($for == 'forum') {
			//first thing is to remove all joomla plugins
			preg_match_all('/\{(.*)\}/U', $text, $matches);
			//find each thread by the id
			foreach ($matches[1] AS $plugin) {
				//replace plugin with nothing
				$text = str_replace('{' . $plugin . '}', "", $text);
			}
			$text = html_entity_decode($text);
			$text = Framework::parseCode($text, 'bbcode');
		} elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
			static $custom_smileys, $vb_bbcodes;
			$options = array();
			try {
				$db = Factory::getDatabase($this->getJname());

				//parse smilies
				if (!is_array($custom_smileys)) {
					$query = $db->getQuery(true)
						->select('title, smilietext, smiliepath')
						->from('#__smilie');

					$db->setQuery($query);
					$smilies = $db->loadObjectList();
					$vburl = $this->params->get('source_url');
					if (!empty($smilies)) {
						$custom_smileys = array();
						foreach ($smilies as $s) {
							$path = (strpos($s->smiliepath, 'http') !== false) ? $s->smiliepath : $vburl . $s->smiliepath;
							$custom_smileys[$s->smilietext] = $path;
						}
					}
				}
			} catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			}

			$options['custom_smileys'] = $custom_smileys;
			$options['parse_smileys'] = \JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/smileys';

			//add custom bbcode rules
			if (!is_array($vb_bbcodes)) {
				$vb_bbcodes = array();
				try {
					$db = Factory::getDatabase($this->getJname());

					$query = $db->getQuery(true)
						->select('bbcodetag, bbcodereplacement, twoparams')
						->from('#__bbcode');

					$db->setQuery($query);
					$bbcodes = $db->loadObjectList();
					foreach ($bbcodes as $bb) {
						$template = $bb->bbcodereplacement;
						//replace vb content holder with nbbc
						$template = str_replace('%1$s', '{$_content}', $template);
						if ($bb->twoparams) {
							//if using the option tag, replace vb option tag with one nbbc will understand
							$template = str_replace('%2$s', '{$_default}', $template);
						}
						$vb_bbcodes[$bb->bbcodetag] = array('mode' => 4, 'template' => $template, 'class' => 'inline', 'allow_in' => array('block', 'inline', 'link', 'list', 'listitem', 'columns', 'image'));
					}
				} catch (Exception $e) {
					Framework::raise(LogLevel::ERROR, $e, $this->getJname());
				}
			}

			if (!empty($vb_bbcodes)) {
				$options['html_patterns'] = $vb_bbcodes;
			}
			if (!empty($params) && $params->get('character_limit', false)) {
				$status['limit_applied'] = 1;
				$options['character_limit'] = $params->get('character_limit');
			}
			$text = Framework::parseCode($text, 'html', $options);

			//remove the post id from any quote heads
			$text = preg_replace('#<div class="bbcode_quote_head">(.*?);(.*?) (.*?):</div>#', '<div class="bbcode_quote_head">$1 $3:</div>', $text);
		} elseif ($for == 'activity' || $for == 'search') {
			static $vb_bbcodes_plain;
			$options = array();
			try {
				$db = Factory::getDatabase($this->getJname());

				//add custom bbcode rules
				if (!is_array($vb_bbcodes_plain)) {
					$vb_bbcodes_plain = array();

					$query = $db->getQuery(true)
						->select('bbcodetag')
						->from('#__bbcode');

					$db->setQuery($query);
					$vb_bbcodes_plain = $db->loadColumn();
				}
			} catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			}

			if (!empty($vb_bbcodes_plain)) {
				$options['plain_tags'] = $vb_bbcodes_plain;
			}

			if ($for == 'activity') {
				if ($params->get('parse_text') == 'plaintext') {
					$options = array();
					$options['plaintext_line_breaks'] = 'space';
					if ($params->get('character_limit')) {
						$status['limit_applied'] = 1;
						$options['character_limit'] = $params->get('character_limit');
					}
					$text = Framework::parseCode($text, 'plaintext', $options);
				}
			} else {
				$text = Framework::parseCode($text, 'plaintext');
			}
		}
		return $status;
	}

	/**
	 * @param string $name         name of element
	 * @param string $value        value of element
	 * @param string $node         node
	 * @param string $control_name name of controller
	 *
	 * @return string html
	 */
	function redirect($name, $value, $node, $control_name)
	{
		return $this->renderHook($name);
	}

	/**
	 * @param string $name         name of element
	 * @param string $value        value of element
	 * @param string $node         node
	 * @param string $control_name name of controller
	 *
	 * @return string html
	 */
	function duallogin($name, $value, $node, $control_name)
	{
		return $this->renderHook($name);
	}

	/**
	 * @param string $name         name of element
	 *
	 * @return string html
	 */
	function renderHook($name)
	{
		try {
			try {
				$db = Factory::getDatabase($this->getJname());
			} catch (Exception $e) {
				throw new RuntimeException(Text::_('VB_CONFIG_FIRST'));
			}
			$secret = $this->params->get('vb_secret', null);
			if (empty($secret)) {
				throw new RuntimeException(Text::_('VB_SECRET_EMPTY'));
			}

			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from('#__plugin')
				->where('hookname = ' . $db->quote('init_startup'))
				->where('title = ' . $db->quote(static::$mods[$name]))
				->where('active = 1');

			$db->setQuery($query);
			$check = ($db->loadResult() > 0) ? true : false;

			if ($check) {
				//return success
				$enabled = Text::_('ENABLED');
				$disable = Text::_('DISABLE_THIS_PLUGIN');
				$reenable = Text::_('REENABLE_THIS_PLUGIN');
				$output = <<<HTML
                    <img style="float: left;" src="components/com_jfusion/images/check_good_small.png">
                    <span style="float: left; margin-left: 5px;">{$enabled}</span>
                    <a style="margin-left:5px; float: left;" href="javascript:void(0);" onclick="return JFusion.Plugin.module('toggleHook', '{$name}', 'disable');">{$disable}</a>
                    <a style="margin-left:5px; float: left;" href="javascript:void(0);" onclick="return JFusion.Plugin.module('toggleHook', '{$name}', 'reenable');">{$reenable}</a>
HTML;
			} else {
				$disabled = Text::_('DISABLED');
				$enable = Text::_('ENABLE_THIS_PLUGIN');
				$output = <<<HTML
                    <img style="float: left;" src="components/com_jfusion/images/check_bad_small.png">
                    <span style="float: left; margin-left: 5px;">{$disabled}</span>
                    <a style="margin-left:5px; float: left;" href="javascript:void(0);" onclick="return JFusion.Plugin.module('toggleHook', '{$name}', 'enable');">{$enable}</a>
HTML;
			}
		} catch (Exception $e) {
			$output = $e->getMessage();
		}
		return $output;
	}

	/**
	 * @param string $hook
	 * @param string $action
	 *
	 * @return void
	 */
	function toggleHook($hook, $action)
	{
		try {
			$db = Factory::getDatabase($this->getJname());
			$params = Factory::getApplication()->input->get('params', array(), 'array');
			$itemid = $params['plugin_itemid'];

			$hookName = static::$mods[$hook];

			if ($hookName) {
				//all three cases, we want to remove the old hook
				$query = $db->getQuery(true)
					->delete('#__plugin')
					->where('hookname = ' . $db->quote('init_startup'))
					->where('title = ' . $db->quote($hookName));

				$db->setQuery($query);
				$db->execute();

				//enable or re-enable the plugin
				if ($action != 'disable') {
					$secret = $this->params->get('vb_secret', null);
					if (empty($secret)) {
						Framework::raise(LogLevel::WARNING, Text::_('VB_SECRET_EMPTY'));
					} else if (($hook == 'redirect' || $hook == 'frameless') && !$this->isValidItemID($itemid)) {
						Framework::raise(LogLevel::WARNING, Text::_('VB_REDIRECT_HOOK_ITEMID_EMPTY'));
					} else {
						//install the hook
						$php = $this->getHookPHP($hook, $itemid);

						//add the post to the approval queue
						$plugin = new stdClass;
						$plugin->title = $hookName;
						$plugin->hookname = 'init_startup';
						$plugin->phpcode = $php;
						$plugin->product = 'vbulletin';
						$plugin->active = 1;
						$plugin->executionorder = 1;

						$db->insertObject('#__plugin', $plugin);
					}
				}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
	}

	/**
	 * @param $plugin
	 * @param $itemid
	 *
	 * @return string
	 */
	function getHookPHP($plugin, $itemid)
	{
		$php = $inner = null;

		$jname = $this->getJname();

		$config = JFactory::getConfig();
		if ($plugin == 'redirect') {
			$sefmode = $this->params->get('sefmode', 0);
			$sef = $config->get('sef');
			//get the baseUR
			$app = JFactory::getApplication('site');
			$router = $app->getRouter();
			/**
			 * @ignore
			 * @var $uri \JUri
			 */
			$uri = $router->build ('index.php?option=com_jfusion&Itemid=' . $itemid);
			$baseURL = $uri->toString();
			$joomla_url = \JFusionFunction::getJoomlaURL();
			if (!strpos($baseURL, '?')) {
				$baseURL .= '/';
			}
			$juri = new \JUri($joomla_url);
			$path = $juri->getPath();
			if ($path != '/') {
				$baseURL = str_replace($path, '', $baseURL);
			}
			if (substr($joomla_url, -1) == '/') {
				if ($baseURL[0] == '/') {
					$baseURL = substr($joomla_url, 0, -1) . $baseURL;
				} else {
					$baseURL = $joomla_url . $baseURL;
				}
			} else {
				if ($baseURL[0] == '/') {
					$baseURL = $joomla_url . $baseURL;
				} else {
					$baseURL = $joomla_url . '/' . $baseURL;
				}
			}
			//let's clean up the URL here before passing it
			$baseURL = str_replace('&amp;', '&', $baseURL);
			//remove /administrator from path
			$baseURL = str_replace('/administrator', '', $baseURL);
			//set some constants needed to recreate the Joomla URL

			$redirect_ignore = $this->params->get('redirect_ignore');
			$inner =<<<PHP
			if (!defined('_JEXEC')){
				define('SEFENABLED','{$sef}');
				define('SEFMODE','{$sefmode}');
				define('JOOMLABASEURL','{$baseURL}');
				define('REDIRECT_IGNORE','{$redirect_ignore}');
PHP;
		} elseif ($plugin == 'duallogin') {
			//only login if not logging into the frontend of the forum and if $JFusionActivePlugin is not active for this plugin
			$inner =<<<PHP
			global \$JFusionActivePlugin,\$JFusionLoginCheckActive;
			if (empty(\$_POST['logintype']) && \$JFusionActivePlugin != '{$jname}' && empty(\$JFusionLoginCheckActive)) {
				\$JFusionActivePlugin = '{$jname}';
				//set the JPATH_BASE needed to initiate Joomla if no already inside Joomla
				defined('JPATH_BASE') or define('JPATH_BASE','" . JPATH_ROOT . "');
PHP;
		}

		if ($inner) {
			$version = $this->helper->getVersion();
			if (substr($version, 0, 1) > 3) {
				$setplugins = 'vBulletinHook::set_pluginlist($vbulletin->pluginlist);';
			} else {
				$setplugins = '';
			}

			$hookFile = __DIR__ . DIRECTORY_SEPARATOR . 'hooks.php';
			$path = str_replace(DIRECTORY_SEPARATOR . 'administrator', '', JPATH_BASE);
			$secret = $this->params->get('vb_secret', $config->get('secret'));

			$php =<<<PHP
			defined('_VBJNAME') or define('_VBJNAME', '{$jname}');
			defined('JPATH_PATH') or define('JPATH_BASE', '{$path}');
			defined('JFUSION_VB_JOOMLA_HOOK_FILE') or define('JFUSION_VB_JOOMLA_HOOK_FILE', '{$hookFile}');

			{$inner}

				if (file_exists(JFUSION_VB_JOOMLA_HOOK_FILE)) {
					include_once(JFUSION_VB_JOOMLA_HOOK_FILE);
					\$val = '{$plugin}';
					\$JFusionHook = new executeJFusionJoomlaHook('init_startup', \$val, '{$secret}');

					{$setplugins}

				}
			}
PHP;
		}

		return $php;
	}

	/**
	 * @return object
	 */
	function getSearchQueryColumns()
	{
		$columns = new stdClass();
		$columns->title = 'p.title';
		$columns->text = 'p.pagetext';
		return $columns;
	}

	/**
	 * @param object $pluginParam
	 *
	 * @return string
	 */
	function getSearchQuery(&$pluginParam)
	{
		$db = Factory::getDatabase($this->getJname());
		//need to return threadid, postid, title, text, created, section
		$query = $db->getQuery(true)
			->select('p.userid, p.threadid, p.postid, f.forumid, CASE WHEN p.title = "" THEN CONCAT("Re: ",t.title) ELSE p.title END AS title, p.pagetext AS text,
                    FROM_UNIXTIME(p.dateline, "%Y-%m-%d %h:%i:%s") AS created,
                    CONCAT_WS( "/", f.title_clean, t.title ) AS section,
                    t.views AS hits')
			->from('#__post AS p')
			->innerJoin('#__thread AS t ON p.threadid = t.threadid')
			->innerJoin('#__forum AS f on f.forumid = t.forumid');

		return (string)$query;
	}

	/**
	 * @param string &$where
	 * @param JRegistry &$pluginParam
	 * @param string $ordering
	 *
	 * @return void
	 */
	function getSearchCriteria(&$where, &$pluginParam, $ordering)
	{
		$where.= ' AND p.visible = 1 AND f.password = \'\'';

		if ($pluginParam->get('forum_mode', 0)) {
			$forumids = $pluginParam->get('selected_forums', array());
			if (empty($forumids)) {
				$forumids = array(0);
			}
			$where.= ' AND f.forumid IN (' . implode(',', $forumids) . ')';
		}

		//determine how to sort the results which is required for accurate results when a limit is placed
		switch ($ordering) {
			case 'oldest':
				$sort = 'p.dateline ASC';
				break;
			case 'category':
				$sort = 'section ASC';
				break;
			case 'popular':
				$sort = 't.views DESC, p.dateline DESC';
				break;
			case 'alpha':
				$sort = 'title ASC';
				break;
			case 'newest':
			default:
				$sort = 'p.dateline DESC';
				break;
		}
		$where .= ' ORDER BY ' . $sort;
	}

	/**
	 * @param array &$results
	 * @param object &$pluginParam
	 *
	 * @return void
	 */
	function filterSearchResults(&$results, &$pluginParam)
	{
		/**
		 * @ignore
		 * @var $platform \JFusion\Plugin\Platform\Joomla
		 */
		$platform = Factory::getPlayform('Joomla', $this->getJname());
		$platform->filterActivityResults($results, 0, 'forumid', true);
	}

	/**
	 * @param mixed $post
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
		return $platform->getPostURL($post->threadid, $post->postid);
	}

	/**
	 * @param object $jfdata
	 *
	 * @return void
	 */
	function getBuffer(&$jfdata)
	{
		global $vbsefmode, $vbJname, $vbsefenabled, $baseURL, $integratedURL, $hookFile;
		//make sure the curl model is loaded for the hooks file
		if (!class_exists('JFusionCurl')) {
			require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
		}
		//define('_JFUSION_DEBUG',1);
		define('_VBFRAMELESS', 1);
		//frameless integration is only supported for 3.x
		$version = $this->helper->getVersion();
		if ((int) substr($version, 0, 1) > 3) {
			Framework::raise(LogLevel::WARNING, Text::sprintf('VB_FRAMELESS_NOT_SUPPORTED', $version), $this->getJname());
		} else {

			try {
				//check to make sure the frameless hook is installed
				$db = Factory::getDatabase($this->getJname());

				$query = $db->getQuery(true)
					->select('active')
					->from('#__plugin')
					->where('hookname = ' . $db->quote('init_startup'))
					->where('title = ' . $db->quote('JFusion Frameless Integration Plugin'));

				$db->setQuery($query);
				$active = $db->loadResult();
			} catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e, $this->getJname());
				$active = 0;
			}

			if ($active != '1') {
				Framework::raise(LogLevel::WARNING, Text::_('VB_FRAMELESS_HOOK_NOT_INSTALLED'), $this->getJname());
			} else {
				//have to clear this as it shows up in some text boxes
				unset($q);
				// Get some params
				$vbsefmode = $this->params->get('sefmode', 0);
				$source_path = $this->params->get('source_path');
				$baseURL = $jfdata->baseURL;
				$integratedURL = $jfdata->integratedURL;
				$config = Factory::getConfig();
				$vbsefenabled = $config->get('sef');

				$hooks = Factory::getPlayform($jfdata->platform, $this->getJname())->hasFile('hooks.php');
				if ($hooks) {
					$hookFile = $hooks;
				}
				if ($vbsefmode) {
					//need to set the base tag as vB JS/ajax requires it to function
					$document = JFactory::getDocument();
					$document->setBase($jfdata->baseURL);
				}
				//get the jname to be used in the hook file
				$vbJname = $this->getJname();
				//fix for some instances of vB redirecting
				$redirects = array('ajax.php', 'attachment.php', 'clientscript', 'member.php', 'misc.php', 'picture.php', 'sendmessage.php');
				$custom_files = explode(',', $this->params->get('redirect_ignore'));
				if (is_array($custom_files)) {
					foreach ($custom_files as $file) {
						//add file to the array of files to be redirected to forum
						if (!empty($file) && strpos($file, '.php') !== false) {
							$redirects[] = trim($file);
						}
					}
				}
				$uri = JUri::getInstance();
				$url = $uri->toString();
				foreach ($redirects as $r) {
					if (strpos($url, $r) !== false) {
						if ($r == 'member.php') {
							//only redirect if using another profile
							$profile_url = $this->getAlternateProfileURL($url);
							if (!empty($profile_url)) {
								$url = $profile_url;
							} else {
								continue;
							}
						} else {
							if ($r == 'sendmessage.php') {
								//only redirect if sending an IM
								$do = Factory::getApplication()->input->get('do');
								if ($do != 'im') {
									continue;
								}
							}
							$url = $integratedURL . substr($url, strpos($url, $r));
						}
						$mainframe = Factory::getApplication();
						$mainframe->redirect($url);
					}
				}
				//get the filename
				$jfile = Factory::getApplication()->input->get('jfile');
				if (!$jfile) {
					//use the default index.php
					$jfile = 'index.php';
				}
				//combine the path and filename
				if (substr($source_path, -1) == DIRECTORY_SEPARATOR) {
					$index_file = $source_path . $jfile;
				} else {
					$index_file = $source_path . DIRECTORY_SEPARATOR . $jfile;
				}
				if (!is_file($index_file)) {
					Framework::raise(LogLevel::WARNING, 'The path to the requested does not exist', $this->getJname());
				} else {
					//set the current directory to vBulletin
					chdir($source_path);
					// Get the output
					ob_start();
					//ahh; basically everything global in vbulletin must be declared here for it to work  ;-{
					//did not include specific globals in admincp
					$vbGlobals = array('_CALENDARHOLIDAYS', '_CALENDAROPTIONS', '_TEMPLATEQUERIES', 'ad_location', 'albumids', 'allday', 'altbgclass', 'attachementids', 'badwords', 'bb_view_cache', 'bgclass', 'birthdaycache', 'cache_postids', 'calendarcache', 'calendarids', 'calendarinfo', 'calmod', 'checked', 'checked', 'cmodcache', 'colspan', 'copyrightyear', 'count', 'counters', 'cpnav', 'curforumid', 'curpostid', 'curpostidkey', 'currentdepth', 'customfields', 'datastore_fetch', 'date1', 'date2', 'datenow', 'day', 'days', 'daysprune', 'db', 'defaultselected', 'DEVDEBUG', 'disablesmiliesoption', 'display', 'dotthreads', 'doublemonth', 'doublemonth1', 'doublemonth2', 'eastercache', 'editor_css', 'eventcache', 'eventdate', 'eventids', 'faqbits', 'faqcache', 'faqjumpbits', 'faqlinks', 'faqparent', 'firstnew', 'folder', 'folderid', 'foldernames', 'folderselect', 'footer', 'foruminfo', 'forumjump', 'forumpermissioncache', 'forumperms', 'forumrules', 'forumshown', 'frmjmpsel', 'gobutton', 'goodwords', 'header', 'headinclude', 'holiday', 'html_allowed', 'hybridposts', 'ifaqcache', 'ignore', 'imodcache', 'imodecache', 'inforum', 'infractionids', 'ipclass', 'ipostarray', 'istyles', 'jumpforumbits', 'jumpforumtitle', 'langaugecount', 'laspostinfo', 'lastpostarray', 'limitlower', 'limitupper', 'links', 'message', 'messagearea', 'messagecounters', 'messageid', 'mod', 'month', 'months', 'monthselected', 'morereplies', 'navclass', 'newpm', 'newthreads', 'notifications_menubits', 'notifications_total', 'onload', 'optionselected', 'p', 'p_two_linebreak', 'pagestarttime', 'pagetitle', 'parent_postids', 'parentassoc', 'parentoptions', 'parents', 'pda', 'period', 'permissions', 'permscache', 'perpage', 'phrasegroups', 'phrasequery', 'pictureids', 'pmbox', 'pmids', 'pmpopupurl', 'post', 'postarray', 'postattache', 'postids', 'postinfo', 'postorder', 'postparent', 'postusername', 'previewpost', 'project_forums', 'project_types', 'querystring', 'querytime', 'rate', 'ratescore', 'recurcriteria', 'reminder', 'replyscore', 'searchforumids', 'searchids', 'searchthread', 'searchthreadid', 'searchtype', 'selectedicon', 'selectedone', 'serveroffset', 'show', 'smilebox', 'socialgroups', 'spacer_close', 'spacer_open', 'strikes', 'style', 'stylecount', 'stylevar', 'subscribecounters', 'subscriptioncache', 'template_hook', 'templateassoc', 'tempusagecache', 'threadedmode', 'threadids', 'threadinfo', 'time1', 'time2', 'timediff', 'timenow', 'timerange', 'timezone', 'titlecolor', 'titleonly', 'today', 'usecategories', 'usercache', 'userids', 'vbcollapse', 'vBeditTemplate', 'vboptions', 'vbphrase', 'vbulletin', 'viewscore', 'wol_album', 'wol_attachement', 'wol_calendar', 'wol_event', 'wol_inf', 'wol_pm', 'wol_post', 'wol_search', 'wol_socialgroup', 'wol_thread', 'wol_user', 'year');
					foreach ($vbGlobals as $g) {
						//global the variable
						global $$g;
					}
					if (defined('_JFUSION_DEBUG')) {
						$_SESSION['jfvbdebug'] = array();
					}
					try {
						include_once ($index_file);
					} catch (Exception $e) {
						$jfdata->buffer = ob_get_contents();
						ob_end_clean();
					}
					//change the current directory back to Joomla.
					chdir(JPATH_SITE);
				}
			}
		}
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function parseBody(&$data)
	{
		global $baseURL, $fullURL, $integratedURL, $vbsefmode, $vbsefenabled;
		$baseURL = $data->baseURL;
		$fullURL = $data->fullURL;
		$integratedURL = $data->integratedURL;
		$vbsefmode = $this->params->get('sefmode', 0);
		$config = Factory::getConfig();
		$vbsefenabled = $config->get('sef');
		//fix for form actions
		//cannot use preg_replace here because it adds unneeded slashes which messes up JS
		$action_search = '#action="(?!http)(.*?)"(.*?)>#mS';

		$data->body = preg_replace_callback($action_search, array(&$this, 'fixAction'), $data->body);
		//fix for the rest of the urls
		$url_search = '#href="(?!http)(.*?)"(.*?)>#mSs';
		$data->body = preg_replace_callback($url_search, array(&$this, 'fixURL'), $data->body);
		//$url_search = '#<link="(?!http)(.*?)"(.*?)>#mS';
		//$data->body = preg_replace_callback($url_search, array(&$this, 'fixURL'), $data->body);
		//convert relative urls in JS links
		$url_search = '#window.location=\'(?!http)(.*?)\'#mS';

		$data->body = preg_replace_callback($url_search, array(&$this, 'fixJS'), $data->body);
		//convert relative links from images and js files into absolute links
		$include_search = "#(src=\"|background=\"|url\('|open_window\(\\\\'|window.open\('|window.open\(\"?)(?!http)(.*?)(\\\\',|',|\"|'\)|')#mS";

		$data->body = preg_replace_callback($include_search, array(&$this, 'fixInclude'), $data->body);
		//we need to fix the cron.php file
		$data->body = preg_replace('#src="(.*)cron.php(.*)>#mS', 'src="' . $integratedURL . 'cron.php$2>', $data->body);
		//if we have custom register and lost password urls and vBulletin uses an absolute URL, fixURL will not catch it
		$register_url = $this->params->get('register_url');
		if (!empty($register_url)) {
			$data->body = str_replace($integratedURL . 'register.php', $register_url, $data->body);
		}
		$lostpassword_url = $this->params->get('lostpassword_url');
		if (!empty($lostpassword_url)) {
			$data->body = str_replace($integratedURL . 'login.php?do=lostpw', $lostpassword_url, $data->body);
		}
		if ($this->params->get('parseCSS', false)) {
			//we need to wrap the body in a div to prevent some CSS clashes
			$data->body = '<div id="framelessVb">' . $data->body . '</div>';
		}
		if (defined('_JFUSION_DEBUG')) {
			$data->body.= '<pre><code>' . htmlentities(print_r($_SESSION['jfvbdebug'], true)) . '</code></pre>';
			$data->body.= '<pre><code>' . htmlentities(print_r($GLOBALS['vbulletin'], true)) . '</code></pre>';
		}
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function parseHeader(&$data)
	{
		global $baseURL, $fullURL, $integratedURL, $vbsefmode, $vbsefenabled;
		$baseURL = $data->baseURL;
		$fullURL = $data->fullURL;
		$integratedURL = $data->integratedURL;
		$vbsefmode = $this->params->get('sefmode', 0);
		$config = Factory::getConfig();
		$vbsefenabled = $config->get('sef');
		$js = '<script type="text/javascript">';
		$js .= <<<JS
            var vbSourceURL = '{$integratedURL}';
JS;
		$js .= '</script>';

		//we need to find and change the call to vb yahoo connection file to our own customized one
		//that adds the source url to the ajax calls
		$yuiURL = JFusionFunction::getJoomlaURL() . JFUSION_PLUGIN_DIR_URL . $this->getJname();
		$data->header = preg_replace('#\<script type="text\/javascript" src="(.*?)(connection-min.js|connection.js)\?v=(.*?)"\>#mS', "$js <script type=\"text/javascript\" src=\"$yuiURL/yui/connection/connection.js?v=$3\">", $data->header);
		//convert relative links into absolute links
		$url_search = '#(src="|background="|href="|url\("|url\(\'?)(?!http)(.*?)("\)|\'\)|"?)#mS';
		$data->header = preg_replace_callback($url_search, array(&$this, 'fixInclude'), $data->header);
		if ($this->params->get('parseCSS', false)) {
			$css_search = '#<style type="text/css" id="vbulletin(.*?)">(.*?)</style>#ms';
			$data->header = preg_replace_callback($css_search, array(&$this, 'fixCSS'), $data->header);
		}
	}

	/**
	 * @return array
	 *
	 * @return void
	 */
	function getPathWay()
	{
		$pathway = array();
		try {
			$db = Factory::getDatabase($this->getJname());
			//let's get the jfile
			$mainframe = Factory::getApplication();
			$jfile = $mainframe->input->get('jfile');
			//we are viewing a forum
			if ($mainframe->input->get('f', false) !== false) {
				$fid = $mainframe->input->get('f');

				$query = $db->getQuery(true)
					->select('title, parentlist, parentid')
					->from('#__forum')
					->where('forumid = ' . $db->quote($fid));

				$db->setQuery($query);
				$forum = $db->loadObject();
				if ($forum->parentid != '-1') {
					$parents = array_reverse(explode(',', $forum->parentlist));
					foreach ($parents as $p) {
						if ($p != '-1') {
							$query = $db->getQuery(true)
								->select('title')
								->from('#__forum')
								->where('forumid = ' . $p);

							$db->setQuery($query);
							$title = $db->loadResult();
							$crumb = new stdClass();
							$crumb->title = $title;
							$crumb->url = 'forumdisplay.php?f=' . $p;
							$pathway[] = $crumb;
						}
					}
				} else {
					$crumb = new stdClass();
					$crumb->title = $forum->title;
					$crumb->url = 'forumdisplay.php?f=' . $fid;
					$pathway[] = $crumb;
				}
			} elseif ($mainframe->input->get('t', false) !== false) {
				$tid = $mainframe->input->get('t');

				$query = $db->getQuery(true)
					->select('t.title AS thread, f.title AS forum, f.forumid, f.parentid, f.parentlist')
					->from('#__thread AS t')
					->join('', '#__forum AS f ON t.forumid = f.forumid')
					->where('t.threadid = ' . $db->quote($tid));

				$db->setQuery($query);
				$result = $db->loadObject();
				if ($result->parentid != '-1') {
					$parents = array_reverse(explode(',', $result->parentlist));
					foreach ($parents as $p) {
						if ($p != '-1') {
							$query = $db->getQuery(true)
								->select('title')
								->from('#__forum')
								->where('forumid = ' . $p);

							$db->setQuery($query);
							$title = $db->loadResult();
							$crumb = new stdClass();
							$crumb->title = $title;
							$crumb->url = 'forumdisplay.php?f=' . $p;
							$pathway[] = $crumb;
						}
					}
				} else {
					$crumb = new stdClass();
					$crumb->title = $result->forum;
					$crumb->url = 'forumdisplay.php?f=' . $result->forumid;
					$pathway[] = $crumb;
				}
				$crumb = new stdClass();
				$crumb->title = $result->thread;
				$crumb->url = 'showthread.php?t=' . $tid;
				$pathway[] = $crumb;
			} elseif ($mainframe->input->get('p', false) !== false) {
				$pid = $mainframe->input->get('p');

				$query = $db->getQuery(true)
					->select('t.title AS thread, t.threadid, f.title AS forum, f.forumid, f.parentid, f.parentlist')
					->from('#__thread AS t')
					->join('', '#__post AS p ON t.forumid = f.forumid AND t.threadid = p.threadid')
					->where('p.postid = ' . $db->quote($pid));

				$db->setQuery($query);
				$result = $db->loadObject();
				if ($result->parentid != '-1') {
					$parents = array_reverse(explode(',', $result->parentlist));
					foreach ($parents as $p) {
						if ($p != '-1') {
							$query = $db->getQuery(true)
								->select('title')
								->from('#__forum')
								->where('forumid = ' . $p);

							$db->setQuery($query);
							$title = $db->loadResult();
							$crumb = new stdClass();
							$crumb->title = $title;
							$crumb->url = 'forumdisplay.php?f=' . $p;
							$pathway[] = $crumb;
						}
					}
				} else {
					$crumb = new stdClass();
					$crumb->title = $result->forum;
					$crumb->url = 'forumdisplay.php?f=' . $result->forumid;
					$pathway[] = $crumb;
				}
				$crumb = new stdClass();
				$crumb->title = $result->thread;
				$crumb->url = 'showthread.php?t=' . $result->threadid;
				$pathway[] = $crumb;
			} elseif ($mainframe->input->get('u', false) !== false) {
				if ($jfile == 'member.php') {
					// we are viewing a member's profile
					$uid = $mainframe->input->get('u');
					$crumb = new stdClass();
					$crumb->title = 'Members List';
					$crumb->url = 'memberslist.php';
					$pathway[] = $crumb;

					$query = $db->getQuery(true)
						->select('username')
						->from('#__user')
						->where('userid = ' . $db->quote($uid));

					$db->setQuery($query);
					$username = $db->loadResult();
					$crumb = new stdClass();
					$crumb->title = $username . '\'s Profile';
					$crumb->url = 'member.php?u=' . $uid;
					$pathway[] = $crumb;
				}
			} elseif ($jfile == 'search.php') {
				$crumb = new stdClass();
				$crumb->title = 'Search';
				$crumb->url = 'search.php';
				$pathway[] = $crumb;
				if ($mainframe->input->get('do', false) !== false) {
					$do = $mainframe->input->get('do');
					if ($do == 'getnew') {
						$crumb = new stdClass();
						$crumb->title = 'New Posts';
						$crumb->url = 'search.php?do=getnew';
						$pathway[] = $crumb;
					} elseif ($do == 'getdaily') {
						$crumb = new stdClass();
						$crumb->title = 'Today\'s Posts';
						$crumb->url = 'search.php?do=getdaily';
						$pathway[] = $crumb;
					}
				}
			} elseif ($jfile == 'private.php') {
				$crumb = new stdClass();
				$crumb->title = 'User Control Panel';
				$crumb->url = 'usercp.php';
				$pathway[] = $crumb;
				$crumb = new stdClass();
				$crumb->title = 'Private Messages';
				$crumb->url = 'private.php';
				$pathway[] = $crumb;
			} elseif ($jfile == 'usercp.php') {
				$crumb = new stdClass();
				$crumb->title = 'User Control Panel';
				$crumb->url = 'usercp.php';
				$pathway[] = $crumb;
			} elseif ($jfile == 'profile.php') {
				$crumb = new stdClass();
				$crumb->title = 'User Control Panel';
				$crumb->url = 'usercp.php';
				$pathway[] = $crumb;
				if ($mainframe->input->get('do', false) !== false) {
					$crumb = new stdClass();
					$crumb->title = 'Your Profile';
					$crumb->url = 'profile.php?do=editprofile';
					$pathway[] = $crumb;
				}
			} elseif ($jfile == 'moderation.php') {
				$crumb = new stdClass();
				$crumb->title = 'User Control Panel';
				$crumb->url = 'usercp.php';
				$pathway[] = $crumb;
				if ($mainframe->input->get('do', false) !== false) {
					$crumb = new stdClass();
					$crumb->title = 'Moderator Tasks';
					$crumb->url = 'moderation.php';
					$pathway[] = $crumb;
				}
			} elseif ($jfile == 'memberlist.php') {
				$crumb = new stdClass();
				$crumb->title = 'Members List';
				$crumb->url = 'memberslist.php';
				$pathway[] = $crumb;
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
		return $pathway;
	}

	/**
	 * @param $vb_url
	 *
	 * @return string
	 */
	function getAlternateProfileURL($vb_url)
	{
		$profile_plugin = $this->params->get('profile_plugin');
		$url = '';
		try {
			if (!empty($profile_plugin)) {
				$user = Factory::getUser($profile_plugin);
				if ($user->isConfigured()) {
					$juri = new Uri($vb_url);
					$vbUid = $juri->getVar('u');
					if (!empty($vbUid)) {
						//first get Joomla id for the vBulletin user
						$vbUser = Factory::getUser($this->getJname());
						$userinfo = $vbUser->getUser($vbUid, 'userid');

						$PluginUser = Factory::getUser($profile_plugin);
						$userlookup = $PluginUser->lookupUser($userinfo);
						//now get the id of the selected plugin based on Joomla id
						if ($userlookup) {
							//get the profile link
							/**
							 * @ignore
							 * @var $platform \JFusion\Plugin\Platform\Joomla
							 */
							$platform = Factory::getPlayform('Joomla', $profile_plugin);
							$url = $platform->getProfileURL($userlookup->userid);
						}
					}
				}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
		return $url;
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixAction($matches)
	{
		$url = $matches[1];
		$extra = $matches[2];
		if (defined('_JFUSION_DEBUG')) {
			$debug = array();
			$debug['original'] = $matches[0];
			$debug['url'] = $url;
			$debug['extra'] = $extra;
			$debug['function'] = 'fixAction';
		}

		$url = htmlspecialchars_decode($url);
		$url_details = parse_url($url);
		$url_variables = array();
		parse_str($url_details['query'], $url_variables);
		if (defined('_JFUSION_DEBUG')) {
			$debug['url_variables'] = $url_variables;
		}


		//add which file is being referred to
		if ($url_variables['jfile']) {
			//use the action file that was in jfile variable
			$jfile = $url_variables['jfile'];
			unset($url_variables['jfile']);
		} else {
			//use the action file from the action URL itself
			$jfile = basename($url_details['path']);
		}

		$actionURL = Factory::getApplication()->routeURL($jfile, Factory::getApplication()->input->getInt('Itemid'));
		$replacement = 'action=\'' . $actionURL . '\'' . $extra . '>';

		unset($url_variables['option']);
		unset($url_variables['Itemid']);

		//add any other variables
		foreach ($url_variables as $key => $value) {
			$replacement.= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
		}

		if (defined('_JFUSION_DEBUG')) {
			$debug['parsed'] = $replacement;
			$_SESSION['jfvbdebug'][] = $debug;
		}
		return $replacement;
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixURL($matches)
	{
		global $baseURL, $integratedURL, $vbsefmode, $vbsefenabled;
		$plugin_itemid = $this->params->get('plugin_itemid');

		$url = $matches[1];
		$extra = $matches[2];
		if (defined('_JFUSION_DEBUG')) {
			$debug = array();
			$debug['original'] = $matches[0];
			$debug['url'] = $url;
			$debug['extra'] = $extra;
			$debug['function'] = 'fixURL';
		}
		$uri = JUri::getInstance();
		$currentURL = $uri->toString();
		if ((string)strpos($url, '#') === (string)0 && strlen($url) != 1) {
			$url = (str_replace('&', '&amp;', $currentURL)) . $url;
		}
		//we need to make some exceptions
		//absolute url, already parsed URL, JS function, or jumpto
		if (strpos($url, 'http') !== false || strpos($url, $currentURL) !== false || strpos($url, 'com_jfusion') !== false || ((string)strpos($url, '#') === (string)0 && strlen($url) == 1)) {
			$replacement = 'href="' . $url . '" ' . $extra . '>';
			if (defined('_JFUSION_DEBUG')) {
				$debug['parsed'] = $replacement;
			}
			return $replacement;
		}
		//admincp, mocp, archive, printthread.php or attachment.php
		if (strpos($url, $this->params->get('admincp', 'admincp')) !== false || strpos($url, $this->params->get('modcp', 'modcp')) !== false || strpos($url, 'archive') !== false || strpos($url, 'printthread.php') !== false || strpos($url, 'attachment.php') !== false) {
			$replacement = 'href="' . $integratedURL . $url . "\" $extra>";
			if (defined('_JFUSION_DEBUG')) {
				$debug['parsed'] = $replacement;
			}
			return $replacement;
		}
		//if the plugin is set as a slave, find the master and replace register/lost password urls
		if (strpos($url, 'register.php') !== false) {
			if (!empty($params)) {
				$register_url = $params->get('register_url');
				if (!empty($register_url)) {
					$replacement = 'href="' . $register_url . '"' . $extra . '>';
					if (defined('_JFUSION_DEBUG')) {
						$debug['parsed'] = $replacement;
					}
					return $replacement;
				}
			}
		}
		if (strpos($url, 'login.php?do=lostpw') !== false) {
			if (!empty($params)) {
				$lostpassword_url = $params->get('lostpassword_url');
				if (!empty($lostpassword_url)) {
					$replacement = 'href="' . $lostpassword_url . '"' . $extra . '>';
					if (defined('_JFUSION_DEBUG')) {
						$debug['parsed'] = $replacement;
					}
					return $replacement;
				}
			}
		}
		if (strpos($url, 'member.php') !== false) {
			$profile_url = $this->getAlternateProfileURL($url);
			if (!empty($profile_url)) {
				$replacement = 'href="' . $profile_url . '"' . $extra . '>';
				if (defined('_JFUSION_DEBUG')) {
					$debug['parsed'] = $replacement;
				}
				return $replacement;
			}
		}
		if (empty($vbsefenabled)) {
			//non sef URls
			$url = str_replace('?', '&amp;', $url);
			$url = $baseURL . '&amp;jfile=' . $url;
		} else {
			if ($vbsefmode) {
				$url = Factory::getApplication()->routeURL($url, $plugin_itemid);
			} else {
				//we can just append both variables
				$url = $baseURL . $url;
			}
		}
		//set the correct url and close the a tag
		$replacement = 'href="' . $url . '"' . $extra . '>';
		if (defined('_JFUSION_DEBUG')) {
			$debug['parsed'] = $replacement;
			$_SESSION['jfvbdebug'][] = $debug;
		}
		return $replacement;
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixJS($matches)
	{
		global $baseURL, $vbsefmode, $vbsefenabled;
		$plugin_itemid = $this->params->get('plugin_itemid');

		$url = $matches[1];
		if (defined('_JFUSION_DEBUG')) {
			$debug = array();
			$debug['original'] = $matches[0];
			$debug['url'] = $url;
			$debug['function'] = 'fixJS';
		}
		if (strpos($url, 'http') !== false) {
			if (defined('_JFUSION_DEBUG')) {
				$debug['parsed'] = 'window.location=\'' . $url . '\'';
			}
			return 'window.location=\'' . $url . '\'';
		}

		if (empty($vbsefenabled)) {
			//non sef URls
			$url = str_replace('?', '&', $url);
			$url = $baseURL . '&jfile=' . $url;
		} else {
			if ($vbsefmode) {
				$url = Factory::getApplication()->routeURL($url, $plugin_itemid);
			} else {
				//we can just append both variables
				$url = $baseURL . $url;
			}
		}
		$url = str_replace('&amp;', '&', $url);
		if (defined('_JFUSION_DEBUG')) {
			$debug['parsed'] = 'window.location=\'' . $url . '\'';
			$_SESSION['jfvbdebug'][] = $debug;
		}
		return 'window.location=\'' . $url . '\'';
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixInclude($matches)
	{
		global $integratedURL;
		$pre = $matches[1];
		$url = $matches[2];
		$post = $matches[3];
		$replacement = $pre . $integratedURL . $url . $post;
		if (defined('_JFUSION_DEBUG')) {
			$debug = array();
			$debug['original'] = $matches[0];
			$debug['pre'] = $pre;
			$debug['url'] = $url;
			$debug['post'] = $post;
			$debug['function'] = 'fixInclude';
			$debug['replacement'] = $replacement;
			$_SESSION['jfvbdebug'][] = $debug;
		}
		return $replacement;
	}

	/**
	 * @param $matches
	 * @return mixed|string
	 */
	function fixCSS($matches)
	{
		if (defined('_JFUSION_DEBUG')) {
			$debug = array();
			$debug['function'] = 'fixCSS';
			$debug['original'] = $matches[0];
		}
		$css = $matches[2];
		//remove html comments
		$css = str_replace(array('<!--', '-->'), '', $css);
		//remove PHP comments
		$css = preg_replace('#\/\*(.*?)\*\/#mSs', '', $css);
		//strip newlines
		$css = str_replace("\r\n", '', $css);
		//break up the CSS into styles
		$elements = explode('}', $css);
		//unset the last one as it is empty
		unset($elements[count($elements) - 1]);
		$imports = array();
		//rewrite css
		foreach ($elements as $k => $v) {
			//breakup each element into selectors and properties
			$element = explode('{', $v);
			//breakup the selectors
			$selectors = explode(',', $element[0]);
			foreach ($selectors as $sk => $sv) {
				//add vb frameless container
				if (strpos($sv, '<!--') !== false) {
					var_dump($sv);
					die();
				}
				if ($sv == 'body' || $sv == 'html' || $sv == '*') {
					$selectors[$sk] = $sv . ' #framelessVb';
				} elseif (strpos($sv, '@') === 0) {
					$import = explode(';', $sv);
					$import = $import[0] . ';';
					$sv = substr($sv, strlen($import));
					if ($sv == 'body' || $sv == 'html' || $sv == '*') {
						$selectors[$sk] = $sv . ' #framelessVb';
					} else {
						$selectors[$sk] = '#framelessVb ' . $sv;
					}
					$imports[] = $import;
				} elseif (strpos($sv, 'wysiwyg') === false) {
					$selectors[$sk] = '#framelessVb ' . $sv;
				}
			}
			//reconstruct the element
			$elements[$k] = implode(', ', $selectors) . ' {' . $element[1] . '}';
		}
		//reconstruct the css
		$css = '<style type="text/css" id="vbulletin' . $matches[1] . '">' . "\n" . implode("\n", $imports) . "\n" . implode("\n", $elements) . "\n" . '</style>';
		if (defined('_JFUSION_DEBUG')) {
			$debug['parsed'] = $css;
			$_SESSION['jfvbdebug'] = $debug;
		}
		return $css;
	}
}