<?php namespace JFusion\Plugins\vbulletin;

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
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_Forum;
use JRegistry;
use RuntimeException;
use stdClass;

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
class Forum extends Plugin_Forum
{
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
		    Framework::raiseError($e, $this->getJname());
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
		    Framework::raiseError($e, $this->getJname());
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
		            $status['error'][] = $e->getMessage();
	            }
            }

			//add information to update forum lookup
			$status['threadinfo']->forumid = $forumid;
			$status['threadinfo']->threadid = $threadid;
			$status['threadinfo']->postid = $postid;
		}
	    foreach ($response['errors'] as $error) {
		    $status['error'][] = $error;
	    }
	    foreach ($response['debug'] as $debug) {
		    $status['debug'][] = $debug;
	    }
    }

    /**
     * @param JRegistry $params
     * @param stdClass $ids         stdClass with forum id ($ids->forumid, thread id ($ids->threadid) and first post id ($ids->postid)
     * @param object $contentitem
     * @param object $userinfo
     * @param stdClass $postinfo object with post info
     *
     * @return array
     */
    function createPost($params, $ids, $contentitem, $userinfo, $postinfo)
    {
        $status = array('error' => array(), 'debug' => array());
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
			    $public = Factory::getFront($this->getJname());
			    $public->prepareText($title);

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
				    $status['error'][] = $error;
			    }
			    foreach ($response['debug'] as $debug) {
				    $status['debug'][] = $debug;
			    }

			    //update moderation status to tell discussion bot to notify user
			    $status['post_moderated'] = ($post_approved) ? 0 : 1;
		    }
	    } catch (Exception $e) {
		    $status['error'][] = Text::_('USERNAME_IN_USE');
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
		    $status['error'][] =  $error;
	    }
	    foreach ($response['debug'] as $debug) {
		    $status['debug'][] = $debug;
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
		    Framework::raiseError($e, $this->getJname());
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
		    Framework::raiseError($e, $this->getJname());
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
		    Framework::raiseError($e, $this->getJname());
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
		    Framework::raiseError($e, $this->getJname());
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
		    Framework::raiseError($e, $this->getJname());
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
				    ->where('varname = ' . $db->quote('usefileavatar'))
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
			Framework::raiseError($e, $this->getJname());
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
				Framework::raiseError($e, $this->getJname());
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
				    $userlookup = Framework::lookupUser($this->getJname(), $JUser->id);
				    if (!empty($userlookup)) {
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
			Framework::raiseError($e, $this->getJname());
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
			Framework::raiseError($e, $this->getJname());
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
					    $user = Framework::lookupUser($this->getJname(), $JoomlaUser->id);
					    if (!empty($user)) {
						    $userid = $user->userid;
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
		    Framework::raiseError($e, $this->getJname());
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
            $user = Framework::lookupUser($this->getJname(), $JoomlaUser->id);
            if (!empty($user)) {
                $userid = $user->userid;
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

}