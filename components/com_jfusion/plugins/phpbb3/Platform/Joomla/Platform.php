<?php namespace jfusion\plugins\phpbb3\Platform\Joomla;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFactory;
use JFile;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use JFusion\Plugins\phpbb3\Helper;

use Joomla\Language\Text;
use JFusion\Plugin\Platform\Joomla;

use \Exception;
use Joomla\Uri\Uri;
use JRegistry;
use JUri;
use \stdClass;
use \RuntimeException;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Forum Class for phpBB3
 * For detailed descriptions on these functions please check the model.abstractforum.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Platform extends Joomla
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    var $joomlaGlobals;

    /**
     * @param int $threadid
     *
     * @return string
     */
    function getThreadURL($threadid) {
        return 'viewtopic.php?t=' . $threadid;
    }

    /**
     * @param int $threadid
     * @param int $postid
     *
     * @return string
     */
    function getPostURL($threadid, $postid) {
        return 'viewtopic.php?p=' . $postid . '#p' . $postid;
    }

    /**
     * @param int|string $userid
     *
     * @return string
     */
    function getProfileURL($userid) {
        return 'memberlist.php?mode=viewprofile&u=' . $userid;
    }

    /**
     * @return string
     */
    function getPrivateMessageURL() {
        return 'ucp.php?i=pm&folder=inbox';
    }

    /**
     * @return string
     */
    function getViewNewMessagesURL() {
        return 'search.php?search_id=newposts';
    }

    /**
     * @param int $userid
     *
     * @return int|string
     */
    function getAvatar($userid) {
	    $url = false;
	    try {
		    if ($userid) {
			    $db = Factory::getDatabase($this->getJname());

			    $query = $db->getQuery(true)
				    ->select('user_avatar, user_avatar_type')
				    ->from('#__users')
				    ->where('user_id = ' . (int)$userid);

			    $db->setQuery($query);
			    $db->execute();
			    $result = $db->loadObject();
			    if (!empty($result)) {
				    if ($result->user_avatar_type == 1) {
					    // AVATAR_UPLOAD
					    $url = $this->params->get('source_url') . 'download/file.php?avatar=' . $result->user_avatar;
				    } else if ($result->user_avatar_type == 3) {
					    // AVATAR_GALLERY
					    $query = $db->getQuery(true)
						    ->select('config_value')
						    ->from('#__config')
						    ->where('config_name = ' . $db->quote('avatar_gallery_path'));

					    $db->setQuery($query);
					    $db->execute();
					    $path = $db->loadResult();
					    if (!empty($path)) {
						    $url = $this->params->get('source_url') . $path . '/' . $result->user_avatar;
					    } else {
						    $url = '';
					    }
				    } else if ($result->user_avatar_type == 2) {
					    // AVATAR REMOTE URL
					    $url = $result->user_avatar;
				    } else {
					    $url = '';
				    }
			    }
		    }
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
		    $url = false;
	    }
	    return $url;
    }

    /**
     * @param int $puser_id
     *
     * @return array
     */
    function getPrivateMessageCounts($puser_id) {
	    $unreadCount = $totalCount = 0;
	    try {
		    if ($puser_id) {
			    // read pm counts
			    $db = Factory::getDatabase($this->getJname());

			    // read unread count
			    $query = $db->getQuery(true)
				    ->select('COUNT(msg_id)')
				    ->from('#__privmsgs_to')
				    ->where('pm_unread = 1')
				    ->where('folder_id <> -2')
				    ->where('user_id = ' . (int)$puser_id);

			    $db->setQuery($query);
			    $unreadCount = $db->loadResult();

			    // read total pm count
			    $query = $db->getQuery(true)
				    ->select('COUNT(msg_id)')
				    ->from('#__privmsgs_to')
				    ->where('folder_id NOT IN (-1, -2)')
				    ->where('user_id = ' . (int)$puser_id);

			    $db->setQuery($query);
			    $totalCount = $db->loadResult();
		    }
	    } catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
	    }

        return array('unread' => $unreadCount, 'total' => $totalCount);
    }

    /**
     * @param array $usedforums
     * @param string $result_order
     * @param int $result_limit
     *
     * @return array
     */
    function getActivityQuery($usedforums, $result_order, $result_limit) {
	    $query = array();

	    try {
	        //filter forums based on user permissions
	        $forumids = $this->filterForumList($usedforums);
	        if (empty($forumids)) {
	            $forumids = array(0);
	        }

		    $db = Factory::getDatabase($this->getJname());
		    $where = 'a.forum_id IN (' . implode(',', $forumids) . ') AND a.topic_approved = 1 AND b.post_approved = 1';

		    $numargs = func_num_args();
		    if ($numargs > 3) {
			    $filters = func_get_args();
			    for ($i = 3; $i < $numargs; $i++) {
				    if ($filters[$i][0] == 'userid') {
					    $where.= ' HAVING userid = ' . $db->quote($filters[$i][1]);
				    }
			    }
		    }

		    $limiter = ' LIMIT 0,' . $result_limit;

		    $q = $db->getQuery(true)
			    ->select('a.topic_id AS threadid, a.topic_first_post_id AS postid, a.topic_first_poster_name AS name, CASE WHEN b.poster_id = 1 AND a.topic_first_poster_name != \'\' THEN a.topic_first_poster_name ELSE c.username_clean END as username, a.topic_poster AS userid, CASE WHEN b.poster_id = 1 THEN 1 ELSE 0 END AS guest, a.topic_title AS subject, a.topic_time AS dateline, a.forum_id as forum_specific_id, a.topic_last_post_time as last_post_dateline')
			    ->from('#__topics as a')
			    ->innerJoin('#__posts AS b ON a.topic_first_post_id = b.post_id')
			    ->innerJoin('#__users AS c ON b.poster_id = c.user_id')
			    ->where($where)
			    ->order('a.topic_last_post_time ' . $result_order);

		    $query[LAT . '0'] = (string)$q . $limiter;

		    $q = $db->getQuery(true)
			    ->select('a.topic_id AS threadid, a.topic_last_post_id AS postid, a.topic_last_poster_name AS name, CASE WHEN b.poster_id = 1 AND a.topic_last_poster_name != \'\' THEN a.topic_last_poster_name ELSE c.username_clean END as username, a.topic_last_poster_id AS userid, CASE WHEN a.topic_last_poster_id = 1 THEN 1 ELSE 0 END AS guest, a.topic_title AS subject, a.topic_last_post_time AS dateline, a.forum_id as forum_specific_id, a.topic_last_post_time as last_post_dateline')
			    ->from('#__topics as a')
			    ->innerJoin('#__posts AS b ON a.topic_last_post_id = b.post_id')
			    ->innerJoin('#__users AS c ON b.poster_id = c.user_id')
			    ->where($where)
			    ->order('a.topic_last_post_time ' . $result_order);

		    $query[LAT . '1'] = (string)$q . $limiter;

		    $q = $db->getQuery(true)
			    ->select('a.topic_id AS threadid, a.topic_first_post_id AS postid, a.topic_first_poster_name AS name, CASE WHEN a.topic_poster = 1 AND a.topic_first_poster_name != \'\' THEN a.topic_first_poster_name ELSE c.username_clean END as username, a.topic_poster AS userid, CASE WHEN a.topic_poster = 1 THEN 1 ELSE 0 END AS guest, a.topic_title AS subject, b.post_text AS body, a.topic_time AS dateline, a.forum_id as forum_specific_id, a.topic_last_post_time as last_post_dateline')
			    ->from('#__topics as a')
			    ->innerJoin('#__posts AS b ON a.topic_first_post_id = b.post_id')
			    ->innerJoin('#__users AS c ON b.poster_id = c.user_id')
			    ->where($where)
			    ->order('a.topic_time ' . $result_order);

		    $query[LCT] = (string)$q . $limiter;

		    $q = $db->getQuery(true)
			    ->select('b.topic_id AS threadid, b.post_id AS postid, CASE WHEN b.poster_id = 1 AND b.post_username != \'\' THEN b.post_username ELSE c.username END AS name, CASE WHEN b.poster_id = 1 AND b.post_username != \'\' THEN b.post_username ELSE c.username_clean END as username, b.poster_id AS userid, CASE WHEN b.poster_id = 1 THEN 1 ELSE 0 END AS guest, b.post_subject AS subject, b.post_text AS body, b.post_time AS dateline, b.post_time as last_post_dateline, b.forum_id as forum_specific_id')
			    ->from('#__topics as a')
			    ->innerJoin('#__posts AS b ON a.topic_id = b.topic_id')
			    ->innerJoin('#__users AS c ON b.poster_id = c.user_id')
			    ->where($where)
			    ->order('b.post_time ' . $result_order);

		    $query[LCP] = (string)$q . $limiter;
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
	    }
        return $query;
    }

    /**
     * @param object $post
     *
     * @return int
     */
    function checkReadStatus(&$post)
    {
	    $newstatus = 0;
        $JUser = JFactory::getUser();
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
						    ->select('topic_id, mark_time')
						    ->from('#__topics_track')
						    ->where('user_id = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $marktimes['thread'] = $db->loadObjectList('topic_id');

					    $query = $db->getQuery(true)
						    ->select('forum_id, mark_time')
						    ->from('#__forums_track')
						    ->where('user_id = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $marktimes['forum'] = $db->loadObjectList('forum_id');

					    $query = $db->getQuery(true)
						    ->select('user_lastmark')
						    ->from('#__users')
						    ->where('user_id = ' . $userlookup->userid);

					    $db->setQuery($query);
					    $marktimes['user'] = $db->loadResult();
				    }
			    }

			    if (isset($marktimes['thread'][$post->threadid])) {
				    $marktime = $marktimes['thread'][$post->threadid]->mark_time;
			    } elseif (isset($marktimes['forum'][$post->forum_specific_id])) {
				    $marktime = $marktimes['forum'][$post->forum_specific_id]->mark_time;
			    } elseif (isset($marktimes['user'])) {
				    $marktime = $marktimes['user'];
			    } else {
				    $marktime = false;
			    }
			    $newstatus = ($marktime !== false && $post->last_post_dateline > $marktime) ? 1 : 0;
		    }
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
	    }
        return $newstatus;
    }

    /**
     * @return array
     */
    function getForumList() {
	    try {
		    //get the connection to the db
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('forum_id as id, forum_name as name')
			    ->from('#__forums')
			    ->where('forum_type = 1')
		        ->order('left_id');

		    $db->setQuery($query);
		    //getting the results
		    return $db->loadObjectList('id');
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
		    return array();
	    }
    }

    /**
     * @param $forumids
     *
     * @return array
     */
    function filterForumList($forumids)
    {
	    try {
		    if (empty($forumids)) {
			    $db = Factory::getDatabase($this->getJname());
			    //no forums were selected so pull them all

			    $query = $db->getQuery(true)
				    ->select('forum_id')
				    ->from('#__forums')
				    ->where('forum_type = 1')
				    ->order('left_id');

			    $db->setQuery($query);
			    $forumids = $db->loadColumn();
		    } elseif (!is_array($forumids)) {
			    $forumids = explode(',', $forumids);
		    }
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
		    $forumids = array();
	    }

        $phpbb_acl = $this->getForumPermissions('find');

        //determine if this user has permission to view the forum
        if (is_array($forumids)) {
	        foreach($forumids as $k => $f) {
	            if (!$phpbb_acl[$f]) {
	                unset($forumids[$k]);
	            }
	        }
        }
        return $forumids;
    }

    /**
     * @param string $userid
     *
     * @return array
     */
    function getForumPermissions($userid = 'find') {
        static $phpbb_acl;
	    try {
		    if (!is_array($phpbb_acl)) {
			    $db = Factory::getDatabase($this->getJname());
			    $phpbb_acl = array();

			    //get permissions for all forums in case more than one module/plugin is present with different settings
			    $query = $db->getQuery(true)
				    ->select('forum_id')
				    ->from('#__forums')
				    ->where('forum_type = 1')
				    ->order('left_id');

			    $db->setQuery($query);
			    $forumids = $db->loadColumn();

			    //prevent SQL errors
			    if (empty($forumids)) {
				    $forumids = array(0);
			    }

			    $groupids = array();
			    $usertype = 2;
			    if ($userid == 'find') {
				    $JUser = JFactory::getUser();
				    if (!$JUser->guest) {
					    $userlookup = new Userinfo('joomla_int');
					    $userlookup->userid = $JUser->get('id');

					    $PluginUser = Factory::getUser($this->getJname());
					    $userlookup = $PluginUser->lookupUser($userlookup);
					    if ($userlookup) {
						    $userid = $userlookup->userid;

						    $query = $db->getQuery(true)
							    ->select('group_id')
							    ->from('#__user_group')
							    ->where('user_id = ' . $userid);

						    $db->setQuery($query);
						    $groupids = $db->loadColumn();

						    $query = $db->getQuery(true)
							    ->select('user_type')
							    ->from('#__users')
							    ->where('user_id = ' . $userid);

						    $db->setQuery($query);
						    $usertype = (int) $db->loadResult();
					    } else {
						    //oops, something has failed so use the anonymous user
						    $userid = 1;

						    $query = $db->getQuery(true)
							    ->select('group_id')
							    ->from('#__groups')
							    ->where('group_name = ' . $db->quote('GUESTS'));

						    $db->setQuery($query);
						    $groupids[] = $db->loadResult();
					    }
				    } else {
					    $userid = 1;

					    $query = $db->getQuery(true)
						    ->select('group_id')
						    ->from('#__groups')
						    ->where('group_name = ' . $db->quote('GUESTS'));

					    $db->setQuery($query);
					    $groupids[] = $db->loadResult();
				    }
			    }

			    //prevent SQL errors
			    if (empty($groupids)) {
				    $groupids = array(0);
			    }

			    //set the permissions for non-founders
			    if ($usertype != 3) {
				    //get the option id for f_read

				    $query = $db->getQuery(true)
					    ->select('auth_option_id, auth_option')
					    ->from('#__acl_options')
					    ->where('auth_option IN (\'f_\', \'f_read\')');

				    $db->setQuery($query);
				    $option_ids = $db->loadObjectList('auth_option');

				    $read_id = 0;
				    if ( isset($option_ids['f_read']->auth_option_id) ) {
					    $read_id = $option_ids['f_read']->auth_option_id;
				    }
				    $global_id = 0;
				    if ( isset($option_ids['f_']->auth_option_id) ) {
					    //$global_id = $option_ids['f_']->auth_option_id;
				    }

				    //get the permissions for the user
				    $auth_option_ids = array(0, $global_id, $read_id);

				    //get the permissions for groups

				    $query = $db->getQuery(true)
					    ->select('*')
					    ->from('#__acl_groups')
					    ->where('group_id IN (' . implode(', ', $groupids) . ')')
					    ->where('auth_option_id IN (' . implode(', ', $auth_option_ids) . ')')
					    ->where('forum_id IN (' . implode(', ', $forumids) . ')');

				    $db->setQuery($query);
				    $results = $db->loadObjectList();

				    if ($results) {
					    foreach ($results as $r) {
						    if (!isset($phpbb_acl[$r->forum_id])) {
							    $phpbb_acl[$r->forum_id] = -1;
						    }
						    if ($phpbb_acl[$r->forum_id]) {
							    if ($r->auth_option_id) {
								    $this->setPremission($phpbb_acl[$r->forum_id], $r->auth_setting);
							    } else {
								    //there is a role assigned so find out what the role's permission is
								    $query = $db->getQuery(true)
									    ->select('auth_option_id, auth_setting')
									    ->from('#__acl_roles_data')
								        ->where('role_id = ' . $r->auth_role_id)
									    ->where('auth_option_id IN (\'' . $global_id . '\', \'' . $read_id . '\')');

								    $db->setQuery($query);
								    $role_permissions = $db->loadObjectList('auth_option_id');
								    if (isset($role_permissions[$global_id])) {
									    $this->setPremission($phpbb_acl[$r->forum_id], $role_permissions[$global_id]->auth_setting);
								    }
								    if (isset($role_permissions[$read_id])) {
									    $this->setPremission($phpbb_acl[$r->forum_id], $role_permissions[$read_id]->auth_setting);
								    }
							    }
						    }
					    }
				    }

				    $query = $db->getQuery(true)
					    ->select('*')
					    ->from('#__acl_users')
					    ->where('user_id = ' . $userid)
					    ->where('auth_option_id IN (' . implode(', ', $auth_option_ids) . ')')
					    ->where('forum_id IN (' . implode(', ', $forumids) . ')');

				    $db->setQuery($query);
				    $results = $db->loadObjectList();

				    if ($results) {
					    foreach ($results as $r) {
						    if (!isset($phpbb_acl[$r->forum_id])) {
							    $phpbb_acl[$r->forum_id] = -1;
						    }
						    if ($phpbb_acl[$r->forum_id]) {
							    if ($r->auth_option_id) {
								    //use the specific setting
								    $this->setPremission($phpbb_acl[$r->forum_id], $r->auth_setting);
							    } else {
								    //there is a role assigned so find out what the role's permission is
								    $query = $db->getQuery(true)
									    ->select('auth_option_id, auth_setting')
									    ->from('#__acl_roles_data')
									    ->where('role_id = ' . $r->auth_role_id)
									    ->where('auth_option_id IN (\'' . $global_id . '\', \'' . $read_id . '\')');

								    $db->setQuery($query);
								    $role_permissions = $db->loadObjectList('auth_option_id');
								    if (isset($role_permissions[$global_id])) {
									    $this->setPremission($phpbb_acl[$r->forum_id], $role_permissions[$global_id]->auth_setting);
								    }
								    if (isset($role_permissions[$read_id])) {
									    //group has been given access
									    $this->setPremission($phpbb_acl[$r->forum_id], $role_permissions[$read_id]->auth_setting);
								    }
							    }
						    }
					    }
				    }
			    }

			    //compile permissions
			    foreach ($forumids as $id) {
				    if ($usertype == 3) {
					    //founder gets permission to all forums
					    $phpbb_acl[$id] = 1;
				    } else {
					    //assume user does not have permission
					    if(!isset($phpbb_acl[$id]) || $phpbb_acl[$id] != 1) {
						    $phpbb_acl[$id] = 0;
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
		    $phpbb_acl = array();
	    }
        return $phpbb_acl;
    }

    /**
     * Update permissions for phpbb
     *
     * @param int $old existing permission
     * @param int $new new precession
     *
     * @return void
     */
    function setPremission(&$old, $new) {
        switch ($old) {
            case 0:
                break;
            case 1:
                if ($new == 0) {
                    $old = (int) $new;
                }
                break;
            case -1:
            default:
                if ($old) {
                    $old = (int) $new;
                }
                break;
        }
    }

    /************************************************
    * Functions For JFusion Discussion Bot Plugin
    ***********************************************/
    /**
     * Retrieves thread information
     * @param int $threadid Id of specific thread
     *
     * @return object Returns object with thread information
     *
     * return the object with these three items
     * $result->forumid
     * $result->threadid (yes add it even though it is passed in as it will be needed in other functions)
     * $result->postid - this is the id of the first post in the thread
     */
    function getThread($threadid)
    {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('topic_id AS threadid, forum_id AS forumid, topic_first_post_id AS postid')
			    ->from('#__topics')
			    ->where('topic_id = ' . $threadid);

		    $db->setQuery($query);
		    $result = $db->loadObject();
	    } catch (Exception $e)  {
		    $result = null;
	    }
	    return $result;
    }

    /**
     * Creates new thread and posts first post
     *
     * @param JRegistry &$dbparams with discussion bot parameters
     * @param object &$contentitem
     * @param int $forumid Id of forum to create thread
     * @param array &$status contains errors and status of actions
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
				->select('username, username_clean, user_colour, user_permissions')
				->from('#__users')
				->where('user_id = ' . $userid);

			$db->setQuery($query);
			$phpbbUser = $db->loadObject();

			if ($dbparams->get('use_content_created_date', false)) {
				$timezone = Factory::getConfig()->get('offset');
				$timestamp = strtotime($contentitem->created);
				//undo Joomla timezone offset
				$timestamp += ($timezone * 3600);
			} else {
				$timestamp = time();
			}

			$topic_row = new stdClass();
			$topic_row->topic_poster = $userid;
			$topic_row->topic_time = $timestamp;
			$topic_row->forum_id = $forumid;
			$topic_row->icon_id = false;
			$topic_row->topic_approved	= 1;
			$topic_row->topic_title = $subject;
			$topic_row->topic_first_poster_name	= $phpbbUser->username;
			$topic_row->topic_first_poster_colour = $phpbbUser->user_colour;
			$topic_row->topic_type = 0;
			$topic_row->topic_time_limit = 0;
			$topic_row->topic_attachment = 0;

			$db->insertObject('#__topics', $topic_row, 'topic_id' );

			$topicid = $db->insertid();

			$bbcode = $this->helper->bbcode_parser($text);

			$post_row = new stdClass();
			$post_row->forum_id			= $forumid;
			$post_row->topic_id 		= $topicid;
			$post_row->poster_id		= $userid;
			$post_row->icon_id			= 0;
			$post_row->poster_ip		= $_SERVER['REMOTE_ADDR'];
			$post_row->post_time		= $timestamp;
			$post_row->post_approved	= 1;
			$post_row->enable_bbcode	= 1;
			$post_row->enable_smilies	= 1;
			$post_row->enable_magic_url	= 1;
			$post_row->enable_sig		= 1;
			$post_row->post_username	= $phpbbUser->username;
			$post_row->post_subject		= $subject;
			$post_row->post_text		= $bbcode->text;
			$post_row->post_checksum	= md5($bbcode->text);
			$post_row->post_attachment	= 0;
			$post_row->bbcode_bitfield	= $bbcode->bbcode_bitfield;
			$post_row->bbcode_uid		= $bbcode->bbcode_uid;
			$post_row->post_postcount	= 1;
			$post_row->post_edit_locked	= 0;

			$db->insertObject('#__posts', $post_row, 'post_id');

			$postid = $db->insertid();

			$topic_row = new stdClass();
			$topic_row->topic_first_post_id			= $postid;
			$topic_row->topic_last_post_id			= $postid;
			$topic_row->topic_last_post_time		= $timestamp;
			$topic_row->topic_last_poster_id		= (int) $userid;
			$topic_row->topic_last_poster_name		= $phpbbUser->username;
			$topic_row->topic_last_poster_colour	= $phpbbUser->user_colour;
			$topic_row->topic_last_post_subject		= (string) $subject;
			$topic_row->topic_id					= $topicid;

			$db->updateObject('#__topics', $topic_row, 'topic_id' );

			$query = $db->getQuery(true)
				->select('forum_last_post_time, forum_topics, forum_topics_real, forum_posts')
				->from('#__forums')
				->where('forum_id = ' . $forumid);

			$db->setQuery($query);
			$num = $db->loadObject();

			$forum_stats = new stdClass();

			if ($dbparams->get('use_content_created_date', false)) {
				//only update the last post for the topic if it really is newer
				$updateLastPost = ($timestamp > $num->forum_last_post_time) ? true : false;
			} else {
				$updateLastPost = true;
			}

			if($updateLastPost) {
				$forum_stats->forum_last_post_id 		=  $postid;
				$forum_stats->forum_last_post_subject	= $db->quote($subject);
				$forum_stats->forum_last_post_time 		=  $timestamp;
				$forum_stats->forum_last_poster_id 		=  (int) $userid;
				$forum_stats->forum_last_poster_name 	=  $phpbbUser->username;
				$forum_stats->forum_last_poster_colour 	= $phpbbUser->user_colour;
			}

			$forum_stats->forum_id 			= $forumid;
			$forum_stats->forum_topics 		= $num->forum_topics + 1;
			$forum_stats->forum_topics_real = $num->forum_topics_real + 1;
			$forum_stats->forum_posts 		= $num->forum_posts + 1;

			$db->updateObject('#__forums', $forum_stats, 'forum_id' );

			//update some stats
			$query = $db->getQuery(true)
				->update('#__users')
				->set('user_posts = user_posts + 1')
				->where('user_id  = ' . $userid);

			$db->setQuery($query);
			$db->execute();

			$query = $db->getQuery(true)
				->update('#__config')
				->set('config_value = config_value + 1')
				->where('config_name  = ' . $db->quote('num_topics'));

			$db->setQuery($query);
			$db->execute();

			if(!empty($topicid) && !empty($postid)) {
				//add information to update forum lookup
				$status['threadinfo']->forumid = $forumid;
				$status['threadinfo']->threadid = $topicid;
				$status['threadinfo']->postid = $postid;
			}
		} catch (Exception $e) {
			$status['error'] = $e->getMessage();
		}
	}

    /**
     * @param int $forumid
     * @param int $threadid
     *
     * @return string
     */
    function getReplyURL($forumid, $threadid)
    {
        return 'posting.php?mode=reply&f=' . $forumid . '&t=' . $threadid;
    }

	 /**
      * Updates information in a specific thread/post
      * @param JRegistry &$dbparams with discussion bot parameters
      * @param object &$existingthread with existing thread info
      * @param object &$contentitem object containing content information
      * @param array &$status contains errors and status of actions
	  *
	  * @return void
      */
	function updateThread(&$dbparams, &$existingthread, &$contentitem, &$status)
	{
		try {
			$threadid = $existingthread->threadid;
			$postid = $existingthread->postid;

			//setup some variables
			$db = Factory::getDatabase($this->getJname());
			$subject = trim(strip_tags($contentitem->title));

			//prepare the content body
			$text = $this->prepareFirstPostBody($dbparams, $contentitem);

			$bbcode = $this->helper->bbcode_parser($text);

			$timestamp = $dbparams->get('use_content_created_date', false) ? Factory::getDate($contentitem->created)->toUnix() : time();
			$userid = $dbparams->get('default_user');

			$query = $db->getQuery(true)
				->select('post_edit_count')
				->from('#__posts')
				->where('post_id = ' . $postid);

			$db->setQuery($query);
			$count = $db->loadResult();

			$post_row = new stdClass();
			$post_row->post_subject		= $subject;
			$post_row->post_text		= $bbcode->text;
			$post_row->post_checksum	= md5($bbcode->text);
			$post_row->bbcode_bitfield	= $bbcode->bbcode_bitfield;
			$post_row->bbcode_uid		= $bbcode->bbcode_uid;
			$post_row->post_edit_time 	= $timestamp;
			$post_row->post_edit_user	= $userid;
			$post_row->post_edit_count	= $count + 1;
			$post_row->post_id 			= $postid;
			$db->updateObject('#__posts', $post_row, 'post_id');

			//update the thread title
			$query = $db->getQuery(true)
				->update('#__topics')
				->set('topic_title = ' . $db->quote($subject))
				->where('topic_id = ' . (int) $threadid);

			$db->setQuery($query);
			$db->execute();
		} catch (Exception $e) {
			$status['error'][] = $e->getMessage();
		}
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
			if($userinfo->guest) {
				$userinfo->username = $postinfo->username;
				$userinfo->userid = 1;

				if(empty($userinfo->username)) {
					throw new RuntimeException(Text::_('GUEST_FIELDS_MISSING'));
				} else {
					$user = Factory::getUser($this->getJname());
					$username_clean = $user->filterUsername($userinfo->username);

					$query = $db->getQuery(true)
						->select('COUNT(*)')
						->from('#__users')
						->where('username = ' . $db->quote($userinfo->username), 'OR')
						->where('username = ' . $db->quote($username_clean))
						->where('username_clean = ' . $db->quote($userinfo->username))
						->where('username_clean = ' . $db->quote($username_clean))
						->where('LOWER(user_email) = ' . $db->quote(strtolower($userinfo->username)));

					$db->setQuery($query);
					$result = $db->loadResult();
					if(!empty($result)) {
						throw new RuntimeException(Text::_('USERNAME_IN_USE'));
					}
				}
			}
			//setup some variables
			$userid = $userinfo->userid;
			$front = Factory::getFront($this->getJname());
			//strip out html from post
			$text = strip_tags($postinfo->text);

			if(!empty($text)) {
				$this->prepareText($text, 'forum', new JRegistry());
				$text = htmlspecialchars($text);

				$bbcode = $this->helper->bbcode_parser($text);

				//get some topic information
				$query = $db->getQuery(true)
					->select('topic_title, topic_replies, topic_replies_real')
					->from('#__topics')
					->where('topic_id = ' . $ids->threadid);

				$db->setQuery($query);
				$topic = $db->loadObject();
				//the user information

				$query = $db->getQuery(true)
					->select('username, user_colour, user_permissions')
					->from('#__users')
					->where('user_id = ' . $userid);

				$db->setQuery($query);
				$phpbbUser = $db->loadObject();

				if($userinfo->guest && !empty($userinfo->username)) {
					$phpbbUser->username = $userinfo->username;
				}

				$timestamp = time();

				$post_approved = ($userinfo->guest && $params->get('moderate_guests', 1)) ? 0 : 1;

				$post_row = new stdClass();
				$post_row->forum_id			= $ids->forumid;
				$post_row->topic_id 		= $ids->threadid;
				$post_row->poster_id		= $userid;
				$post_row->icon_id			= 0;
				$post_row->poster_ip		= $_SERVER['REMOTE_ADDR'];
				$post_row->post_time		= $timestamp;
				$post_row->post_approved	= $post_approved;
				$post_row->enable_bbcode	= 1;
				$post_row->enable_smilies	= 1;
				$post_row->enable_magic_url	= 1;
				$post_row->enable_sig		= 1;
				$post_row->post_username	= $phpbbUser->username;
				$post_row->post_subject		= 'Re: ' . $topic->topic_title;
				$post_row->post_text		= $bbcode->text;
				$post_row->post_checksum	= md5($bbcode->text);
				$post_row->post_attachment	= 0;
				$post_row->bbcode_bitfield	= $bbcode->bbcode_bitfield;
				$post_row->bbcode_uid		= $bbcode->bbcode_uid;
				$post_row->post_postcount	= 1;
				$post_row->post_edit_locked	= 0;

				$db->insertObject('#__posts', $post_row, 'post_id');

				$postid = $db->insertid();
				//store the postid
				$status['postid'] = $postid;

				//only update the counters if the post is approved
				if($post_approved) {
					$topic_row = new stdClass();
					$topic_row->topic_last_post_id			= $postid;
					$topic_row->topic_last_post_time		= $timestamp;
					$topic_row->topic_last_poster_id		= (int) $userid;
					$topic_row->topic_last_poster_name		= $phpbbUser->username;
					$topic_row->topic_last_poster_colour	= $phpbbUser->user_colour;
					$topic_row->topic_last_post_subject     = 'Re: ' . $topic->topic_title;
					$topic_row->topic_replies				= $topic->topic_replies + 1;
					$topic_row->topic_replies_real 			= $topic->topic_replies_real + 1;
					$topic_row->topic_id					= $ids->threadid;
					$db->updateObject('#__topics', $topic_row, 'topic_id' );

					$query = $db->getQuery(true)
						->select('forum_posts')
						->from('#__forums')
						->where('forum_id = ' . $ids->forumid);

					$db->setQuery($query);
					$num = $db->loadObject();

					$forum_stats = new stdClass();
					$forum_stats->forum_last_post_id 		= $postid;
					$forum_stats->forum_last_post_subject	= '';
					$forum_stats->forum_last_post_time 		= $timestamp;
					$forum_stats->forum_last_poster_id 		= (int) $userid;
					$forum_stats->forum_last_poster_name 	= $phpbbUser->username;
					$forum_stats->forum_last_poster_colour 	= $phpbbUser->user_colour;
					$forum_stats->forum_posts				= $num->forum_posts + 1;
					$forum_stats->forum_id 					= $ids->forumid;

					$query = $db->getQuery(true)
						->select('forum_topics, forum_topics_real, forum_posts')
						->from('#__forums')
						->where('forum_id = ' . $ids->forumid);

					$db->setQuery($query);
					$num = $db->loadObject();
					$forum_stats->forum_topics = $num->forum_topics + 1;
					$forum_stats->forum_topics_real = $num->forum_topics_real + 1;
					$forum_stats->forum_posts = $num->forum_posts + 1;
					$db->updateObject('#__forums', $forum_stats, 'forum_id' );

					//update some stats
					$query = $db->getQuery(true)
						->update('#__users')
						->set('user_posts = user_posts + 1')
						->where('user_id = ' . $userid);

					$db->setQuery($query);
					$db->execute();

					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = config_value + 1')
						->where('config_name = ' . $db->quote('num_posts'));

					$db->setQuery($query);
					$db->execute();
				} else {
					//update the for real count so that phpbb notes there are unapproved messages here
					$topic_row = new stdClass();
					$topic_row->topic_replies_real 			= $topic->topic_replies_real + 1;
					$topic_row->topic_id					= $ids->threadid;
					$db->updateObject('#__topics', $topic_row, 'topic_id' );
				}

				//update moderation status to tell discussion bot to notify user
				$status['post_moderated'] = ($post_approved) ? 0 : 1;
			}
		} catch (Exception $e) {
			$status['error'][] = $e->getMessage();
		}
		return $status;
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
		$columns->userid = 'user_id';
		$columns->username = 'username';
		$columns->name = 'name';
		$columns->dateline = 'post_time';
		$columns->posttext = 'post_text';
		$columns->posttitle = 'post_subject';
		$columns->postid = 'post_id';
		$columns->threadid = 'topic_id';
		$columns->guest = 'guest';
		return $columns;
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
			$db = Factory::getDatabase($this->getJname());

			//set the query
			$query = $db->getQuery(true)
				->select('p.post_id , CASE WHEN p.poster_id = 1 THEN 1 ELSE 0 END AS guest, CASE WHEN p.poster_id = 1 AND p.post_username != \'\' THEN p.post_username ELSE u.username END AS name, CASE WHEN p.poster_id = 1 AND p.post_username != \'\' THEN p.post_username ELSE u.username_clean END AS username, u.user_id, p.post_subject, p.post_time, p.post_text, p.topic_id')
				->from('#__posts as p')
				->innerJoin('#__users as u ON p.poster_id = u.user_id')
				->where('p.topic_id = ' . $existingthread->threadid)
				->where('p.post_id != ' . $existingthread->postid)
				->where('p.post_approved = 1')
				->order('p.post_time ' . $sort);

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
				->select('count(*)')
				->from('#__posts')
				->where('topic_id = ' . $existingthread->threadid)
				->where('post_approved = 1')
				->where('post_id != ' . $existingthread->postid);

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			$result = 0;
		}
		return $result;
	}

	/**
	 * @param bool $keepalive
	 *
	 * @return int
	 */
	function syncSessions($keepalive = false) {
		$return = 0;
		try {
			$userPlugin = Factory::getUser($this->getJname());
			$debug = (defined('DEBUG_SYSTEM_PLUGIN') ? true : false);

			$login_type = $this->params->get('login_type');
			if ($login_type == 1) {
				if ($debug) {
					Framework::raiseNotice('syncSessions called', $this->getJname());
				}

				$options = array();
				$options['action'] = 'core.login.site';

				//phpbb variables
				$phpbb_cookie_prefix = $this->params->get('cookie_prefix');
				$mainframe = Factory::getApplication();
				$userid_cookie_value = $mainframe->input->cookie->get($phpbb_cookie_prefix . '_u', '');
				$sid_cookie_value = $mainframe->input->cookie->get($phpbb_cookie_prefix . '_sid', '');
				$phpbb_allow_autologin = $this->params->get('allow_autologin');
				$persistant_cookie = ($phpbb_allow_autologin) ? $mainframe->input->cookie->get($phpbb_cookie_prefix . '_k', '') : '';
				//joomla variables
				$JUser = JFactory::getUser();
				if (\JPluginHelper::isEnabled ('system', 'remember')) {
					jimport('joomla.utilities.utility');
					$hash = Framework::getHash('JLOGIN_REMEMBER');
					$joomla_persistant_cookie = $mainframe->input->cookie->get($hash, '', 'raw');
				} else {
					$joomla_persistant_cookie = '';
				}

				if (!$JUser->get('guest', true)) {
					//user logged into Joomla so let's check for an active phpBB session

					if (!empty($phpbb_allow_autologin) && !empty($persistant_cookie) && !empty($sid_cookie_value)) {
						//we have a persistent cookie set so let phpBB handle the session renewal
						if ($debug) {
							Framework::raiseNotice('persistant cookie enabled and set so let phpbb handle renewal', $this->getJname());
						}
					} else {
						if ($debug) {
							Framework::raiseNotice('Joomla user is logged in', $this->getJname());
						}

						//check to see if the userid cookie is empty or if it contains the anonymous user, or if sid cookie is empty or missing
						if (empty($userid_cookie_value) || $userid_cookie_value == '1' || empty($sid_cookie_value)) {
							if ($debug) {
								Framework::raiseNotice('has a guest session', $this->getJname());
							}
							//find the userid attached to Joomla userid
							$userlookup = new Userinfo('joomla_int');
							$userlookup->userid = $JUser->get('id');

							$PluginUser = Factory::getUser($this->getJname());
							$userlookup = $PluginUser->lookupUser($userlookup);
							//get the user's info
							if ($userlookup) {
								$db = Factory::getDatabase($this->getJname());

								$query = $db->getQuery(true)
									->select('username_clean AS username, user_email as email')
									->from('#__users')
									->where('user_id = ' . $userlookup->userid);

								$db->setQuery($query);
								$user_identifiers = $db->loadObject();
								$userinfo = $userPlugin->getUser($user_identifiers);
							}

							if (!empty($userinfo) && (!empty($keepalive) || !empty($joomla_persistant_cookie))) {
								if ($debug) {
									Framework::raiseNotice('keep alive enabled or Joomla persistant cookie found, and found a valid phpbb3 user so calling createSession', $this->getJname());
								}
								//enable remember me as this is a keep alive function anyway
								$options['remember'] = 1;
								//create a new session

								try {
									$status = $userPlugin->createSession($userinfo, $options);
									if ($debug) {
										Framework::raise('notice', $status, $this->getJname());
									}
								} catch (Exception $e) {
									Framework::raiseError($e, $this->getJname());
								}
								//signal that session was changed
								$return = 1;
							} else {
								if ($debug) {
									Framework::raiseNotice('keep alive disabled or no persistant session found so calling Joomla\'s destorySession', $this->getJname());
								}
								$JoomlaUser = Factory::getUser('joomla_int');

								$userinfo = \JFusionFunction::getJoomlaUser((object)$JUser);

								$options['clientid'][] = '0';
								try {
									$status = $JoomlaUser->destroySession($userinfo, $options);
									if ($debug) {
										Framework::raise('notice', $status, $this->getJname());
									}
								} catch (Exception $e) {
									Framework::raiseError($e, $JoomlaUser->getJname());
								}
							}
						} else {
							if ($debug) {
								Framework::raiseNotice('user logged in', $this->getJname());
							}
						}
					}
				} elseif ((!empty($sid_cookie_value) || !empty($persistant_cookie)) && $userid_cookie_value != '1') {
					$db = Factory::getDatabase($this->getJname());
					$query = $db->getQuery(true)
						->select('b.group_name')
						->from('#__users as a')
						->join('LEFT OUTER', '#__groups as b ON a.group_id = b.group_id')
						->where('a.user_id = ' . $db->quote($userid_cookie_value));

					$db->setQuery($query);
					$group_name = $db->loadresult();
					if ($group_name !== 'BOTS') {
						if ($debug) {
							Framework::raiseNotice('Joomla has a guest session', $this->getJname());
						}
						//the user is not logged into Joomla and we have an active phpBB session
						if (!empty($joomla_persistant_cookie)) {
							if ($debug) {
								Framework::raiseNotice('Joomla persistant cookie found so let Joomla handle renewal', $this->getJname());
							}
						} elseif (empty($keepalive)) {
							if ($debug) {
								Framework::raiseNotice('Keep alive disabled so kill phpBBs session', $this->getJname());
							}
							//something fishy or person chose not to use remember me so let's destroy phpBBs session
							$phpbb_cookie_name = $this->params->get('cookie_prefix');
							$phpbb_cookie_path = $this->params->get('cookie_path');
							//baltie cookie domain fix
							$phpbb_cookie_domain = $this->params->get('cookie_domain');
							if ($phpbb_cookie_domain == 'localhost' || $phpbb_cookie_domain == '127.0.0.1') {
								$phpbb_cookie_domain = '';
							}
							//delete the cookies
							$status['debug'][] = $userPlugin->addCookie($phpbb_cookie_name . '_u', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
							$status['debug'][] = $userPlugin->addCookie($phpbb_cookie_name . '_sid', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
							$status['debug'][] = $userPlugin->addCookie($phpbb_cookie_name . '_k', '', -3600, $phpbb_cookie_path, $phpbb_cookie_domain);
							$return = 1;
						} elseif ($debug) {
							Framework::raiseNotice('Keep alive enabled so renew Joomla\'s session', $this->getJname());
						} else {
							if (!empty($persistant_cookie)) {
								$query = $db->getQuery(true)
									->select('user_id')
									->from('#__sessions_keys')
									->where('key_id = ' . $db->quote(md5($persistant_cookie)));

								if ($debug) {
									Framework::raiseNotice('Using phpBB persistant cookie to find user', $this->getJname());
								}
							} else {
								$query = $db->getQuery(true)
									->select('session_user_id')
									->from('#__sessions')
									->where('session_id = ' . $db->quote($sid_cookie_value));

								if ($debug) {
									Framework::raiseNotice('Using phpBB sid cookie to find user', $this->getJname());
								}
							}
							$db->setQuery($query);
							$userid = $db->loadresult();

							$userlookup = new Userinfo($this->getJname());
							$userlookup->userid = $userid;

							$PluginUser = Factory::getUser($this->getJname());
							$userlookup = $PluginUser->lookupUser($userlookup);
							if ($userlookup) {
								if ($debug) {
									Framework::raiseNotice('Found a phpBB user so attempting to renew Joomla\'s session.', $this->getJname());
								}
								//get the user's info
								$jdb = JFactory::getDBO();

								$query = $jdb->getQuery(true)
									->select('username, email')
									->from('#__users')
									->where('id = ' . $userlookup->id);

								$jdb->setQuery($query);
								$user_identifiers = $jdb->loadObject();
								$JoomlaUser = Factory::getUser('joomla_int');
								$userinfo = $JoomlaUser->getUser($user_identifiers);
								if (!empty($userinfo)) {
									global $JFusionActivePlugin;
									$JFusionActivePlugin = $this->getJname();

									try {
										$status = $JoomlaUser->createSession($userinfo, $options);
										if ($debug) {
											Framework::raise('notice', $status, $JoomlaUser->getJname());
										}
									} catch (Exception $e) {
										Framework::raiseError($e, $JoomlaUser->getJname());
									}
									//no need to signal refresh as Joomla will recognize this anyway
								}
							}
						}
					}
				}
			} else {
				if ($debug) {
					Framework::raiseNotice('syncSessions do not work in this login mode.', $this->getJname());
				}
			}
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
		}
		return $return;
	}

	/**
	 * @param array $usergroups
	 *
	 * @return string
	 */
	function getOnlineUserQuery($usergroups = array())
	{
		$db = Factory::getDatabase($this->getJname());
		//get a unix time from 5 minutes ago
		date_default_timezone_set('UTC');
		$active = strtotime('-5 minutes', time());

		$query = $db->getQuery(true)
			->select('DISTINCT u.user_id AS userid, u.username_clean AS username, u.username AS name, u.user_email as email')
			->from('#__users AS u')
			->innerJoin('#__sessions AS s ON u.user_id = s.session_user_id')
			->where('s.session_viewonline = 1')
			->where('s.session_user_id != 1')
			->where('s.session_time > ' . $active);

		if (!empty($usergroups)) {
			$usergroups = implode(',', $usergroups);

			$query->innerJoin('#___user_group AS g ON u.user_id = g.user_id')
				->where('g.group_id IN (' . $usergroups . ')');
		}

		$query = (string)$query;
		return $query;
	}

	/**
	 * @return int
	 */
	function getNumberOnlineGuests() {
		try {
			//get a unix time from 5 minutes ago
			date_default_timezone_set('UTC');
			$active = strtotime('-5 minutes', time());
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(DISTINCT(session_ip))')
				->from('#__sessions')
				->where('session_user_id = 1')
				->where('session_time > ' . $active);

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			$result = 0;
		}
		return $result;
	}

	/**
	 * @return int
	 */
	function getNumberOnlineMembers() {
		try {
			//get a unix time from 5 minutes ago
			date_default_timezone_set('UTC');
			$active = strtotime('-5 minutes', time());
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(DISTINCT(session_user_id))')
				->from('#__sessions')
				->where('session_viewonline = 1')
				->where('session_user_id != 1')
				->where('session_time > ' . $active);

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			$result = 0;
		}
		return $result;
	}

	/**
	 * Prepares text for various areas
	 *
	 * @param string  &$text             Text to be modified
	 * @param string  $for              (optional) Determines how the text should be prepared.
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
		$status = array('error' => array(), 'debug' => array());
		if ($for == 'forum') {
			//first thing is to remove all joomla plugins
			preg_match_all('/\{(.*)\}/U', $text, $matches);
			//find each thread by the id
			foreach ($matches[1] AS $plugin) {
				//replace plugin with nothing
				$text = str_replace('{' . $plugin . '}', "", $text);
			}
			$text = Framework::parseCode($text, 'bbcode');
		} elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
			//remove phpbb bbcode uids
			$text = preg_replace('#\[(.*?):(.*?)]#si', '[$1]', $text);
			//encode &nbsp; prior to decoding as somehow it is getting added into phpBB without getting encoded
			$text = str_replace('&nbsp;', '&amp;nbsp;', $text);
			//decode html entities
			$text = html_entity_decode($text);
			if (strpos($text, 'SMILIES_PATH') !== false) {
				//must convert smilies
				try {
					$db = Factory::getDatabase($this->getJname());

					$query = $db->getQuery(true)
						->select('config_value')
						->from('#__config')
						->where('config_name = ' . $db->quote('smilies_path'));

					$db->setQuery($query);
					$smilie_path = $db->loadResult();
					$source_url = $this->params->get('source_url');
					$text = preg_replace('#<!-- s(.*?) --><img src="\{SMILIES_PATH\}\/(.*?)" alt="(.*?)" title="(.*?)" \/><!-- s\\1 -->#si', "[img]{$source_url}{$smilie_path}/$2[/img]", $text);
				} catch (Exception $e) {
					Framework::raiseError($e, $this->getJname());
				}
			}
			//parse bbcode to html
			$options = array();
			$options['parse_smileys'] = false;
			if (!empty($params) && $params->get('character_limit', false)) {
				$status['limit_applied'] = 1;
				$options['character_limit'] = $params->get('character_limit');
			}
			$text = Framework::parseCode($text, 'html', $options);
		} elseif ($for == 'activity' || $for == 'search') {
			$text = preg_replace('#\[(.*?):(.*?)]#si', '[$1]', $text);
			$text = html_entity_decode($text);
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
	 * @param string $url
	 * @param int $itemid
	 *
	 * @return string
	 */
	function generateRedirectCode($url, $itemid)
	{
		try {
			$cookie_name = $this->params->get('cookie_prefix') . '_u';
			//create the new redirection code
			$redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
if (!empty($_COOKIE[\'' . $cookie_name . '\']))
{
    $current_userid = $_COOKIE[\'' . $cookie_name . '\'];
} else {
    $current_userid = \'\';
}
$joomla_url = \'' . $url . '\';
$joomla_itemid = ' . $itemid . ';
$file = $_SERVER[\'SCRIPT_NAME\'];
$break = Explode(\'/\', $file);
$pfile = $break[count($break) - 1];

$jfile = \'\';
if (isset($_GET[\'jfile\'])) {
     $jfile = $_GET[\'jfile\'];
}
    ';
			$allow_mods = $this->params->get('mod_ids');
			if (!empty($allow_mods)) {
				//get a userlist of mod ids
				$db = Factory::getDatabase($this->getJname());

				$query = $db->getQuery(true)
					->select('b.user_id, a.group_name')
					->from('#__groups as a')
					->innerJoin('#__user_group as b ON a.group_id = b.group_id')
					->where('a.group_name = ' . $db->quote('GLOBAL_MODERATORS'))
					->where('a.group_name = ' . $db->quote('ADMINISTRATORS'));

				$db->setQuery($query);
				$mod_list = $db->loadObjectList();
				$mod_array = array();
				foreach ($mod_list as $mod) {
					if (!isset($mod_array[$mod->user_id])) {
						$mod_array[$mod->user_id] = $mod->user_id;
					}
				}
				$mod_ids = implode(',', $mod_array);
				$redirect_code.= '
$mod_ids = array(' . $mod_ids . ');
if (!defined(\'_JEXEC\') && !defined(\'ADMIN_START\') && !defined(\'IN_MOBIQUO\') && $pfile != \'file.php\' && $jfile != \'file.php\' && $pfile != \'feed.php\' && $jfile != \'feed.php\' && !in_array($current_userid, $mod_ids))';
			} else {
				$redirect_code.= '
if (!defined(\'_JEXEC\') && !defined(\'ADMIN_START\') && !defined(\'IN_MOBIQUO\') && $pfile != \'file.php\' && $jfile != \'file.php\' && $pfile != \'feed.php\' && $jfile != \'feed.php\')';
			}
			$redirect_code.= '
{
    $jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $_SERVER[\'QUERY_STRING\'];
    header(\'Location: \' . $jfusion_url);
}
//JFUSION REDIRECT END';
			return $redirect_code;
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			return '';
		}
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
		$mod_file = $this->getPluginFile('common.php', $error, $reason);
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
					Framework::raiseWarning(Text::_('MISSING') . ' Joomla URL', $this->getJname());
				} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
					Framework::raiseWarning(Text::_('MISSING') . ' ItemID', $this->getJname());
				} else if (!$this->isValidItemID($joomla_itemid)) {
					Framework::raiseWarning(Text::_('MISSING') . ' ItemID ' . Text::_('MUST BE') . ' ' . $this->getJname(), $this->getJname());
				} else if ($error == 0) {
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
	 * @return string
	 */
	function showRedirectMod($name, $value, $node, $control_name)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getPluginFile('common.php', $error, $reason);
		if ($error == 0) {
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = file_get_contents($mod_file);
			preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms', $file_data, $matches);
			//compare it with our joomla path
			if (empty($matches[1][0])) {
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
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'disable')">{$disable}</a>
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'reenable')">{$update}</a>
HTML;
		} else {
			$text = Text::_('REDIRECTION_MOD') . ' ' . Text::_('DISABLED') . ': ' . $reason;
			$enable = Text::_('MOD_ENABLE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'enable')">{$enable}</a>
HTML;
		}
		return $output;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 * @return mixed|string
	 */
	function showAuthMod($name, $value, $node, $control_name)
	{
		try {
			//do a database check to avoid fatal error with incorrect database settings
			$db = Factory::getDatabase($this->getJname());

			$error = 0;
			$reason = '';

			if ($this->helper->isVersion('3.1')) {
				$mod_file = $this->getPluginFile('phpbb' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php', $error, $reason);
			} else {
				$mod_file = $this->getPluginFile('includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php', $error, $reason);
			}

			if ($error == 0) {
				//get the joomla path from the file
				jimport('joomla.filesystem.file');
				$file_data = file_get_contents($mod_file);
				if(preg_match_all('/define\(\'JPATH_BASE\'\,(.*)\)/', $file_data, $matches)) {
					//compare it with our joomla path
					if ($matches[1][0] != '\'' . JPATH_SITE . '\'') {
						$error = 1;
						$reason = Text::_('PATH') . ' ' . Text::_('INVALID');
					}
				}
			}
			if ($error == 0) {
				//check to see if the mod is enabled
				$query = $db->getQuery(true)
					->select('config_value')
					->from('#__config')
					->where('config_name = ' . $db->quote('auth_method'));

				$db->setQuery($query);
				$auth_method = $db->loadResult();
				if ($auth_method != 'jfusion') {
					$error = 1;
					$reason = Text::_('MOD_NOT_ENABLED');
				}
			}
			//add the javascript to enable buttons
			if ($error == 0) {
				//return success
				$text = Text::_('AUTHENTICATION_MOD') . ' ' . Text::_('ENABLED');
				$disable = Text::_('MOD_DISABLE');
				$output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('disableAuthMod')">{$disable}</a>
HTML;
				return $output;
			} else {
				$text = Text::_('AUTHENTICATION_MOD') . ' ' . Text::_('DISABLED') . ': ' . $reason;
				$enable = Text::_('MOD_ENABLE');
				$output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('enableAuthMod')">{$enable}</a>
HTML;
				return $output;
			}
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}

	function enableAuthMod()
	{
		$error = 0;
		$reason = '';
		if ($this->helper->isVersion('3.1')) {
			$auth_file = $this->getPluginFile('phpbb' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php', $error, $reason);
		} else {
			$auth_file = $this->getPluginFile('includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php', $error, $reason);
		}

		//see if the auth mod file exists
		if (!file_exists($auth_file)) {
			jimport('joomla.filesystem.file');
			$copy_file = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'auth_jfusion.php';
			JFile::copy($copy_file, $auth_file);
		}
		if (file_exists($auth_file)) {
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = file_get_contents($auth_file);
			//compare it with our joomla path
			if (preg_match_all('/JFUSION_PATH/', $file_data, $matches)) {
				$file_data = preg_replace('/JFUSION_JNAME/', $this->getJname(), $file_data);
				$file_data = preg_replace('/JFUSION_PATH/', JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion', $file_data);
				JFile::write($auth_file, $file_data);
			}

			//only update the database if the file now exists
			if (file_exists($auth_file)) {
				try {
					//check to see if the mod is enabled
					$db = Factory::getDatabase($this->getJname());

					$query = $db->getQuery(true)
						->select('config_value')
						->from('#__config')
						->where('config_name = ' . $db->quote('auth_method'));

					$db->setQuery($query);
					$auth_method = $db->loadResult();
					if ($auth_method != 'jfusion') {
						$query = $db->getQuery(true)
							->update('#__config')
							->set('config_value = ' . $db->quote('jfusion'))
							->where('config_name  = ' . $db->quote('auth_method'));

						$db->setQuery($query);
						$db->execute();
					}
				} catch (Exception $e) {
					//there was an error saving the parameters
					Framework::raiseWarning($e, $this->getJname());
				}
			} else {
				try {
					//safety catch to make sure we use phpBB default to prevent lockout from phpBB
					$db = Factory::getDatabase($this->getJname());

					$query = $db->getQuery(true)
						->update('#__config')
						->set('config_value = ' . $db->quote('db'))
						->where('config_name  = ' . $db->quote('auth_method'));

					$db->setQuery($query);
					$db->execute();
				} catch (Exception $e) {
					//there was an error saving the parameters
					Framework::raiseWarning($e, $this->getJname());
				}
			}
			//clear the config cache so that phpBB recognizes the change
			$this->clearConfigCache();
		} else {
			Framework::raiseWarning('FAILED_TO_COPY_AUTHFILE' . $auth_file, $this->getJname());
		}
	}

	/**
	 * @return bool
	 */
	function disableAuthMod()
	{
		$return = true;
		try {
			//check to see if the mod is enabled
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->update('#__config')
				->set('config_value = ' . $db->quote('db'))
				->where('config_name  = ' . $db->quote('auth_method'));

			$db->setQuery($query);
			$db->execute();

			//remove the file as well to allow for updates of the auth mod content
			$source_path = $this->params->get('source_path');

			if ($this->helper->isVersion('3.1')) {
				$auth_file = $source_path . 'phpbb' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php';
			} else {
				$auth_file = $source_path . 'includes' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'auth_jfusion.php';
			}

			if (file_exists($auth_file)) {
				jimport('joomla.filesystem.file');
				if (!JFile::delete($auth_file)) {
					throw new RuntimeException('Cant delete file: ' . $auth_file);
				}
			}

			//clear the config cache so that phpBB recognizes the change
			$cleared = $this->clearConfigCache();
			if (!$cleared) {
				throw new RuntimeException('Cash not cleared!');
			}
		} catch (Exception $e) {
			Framework::raiseWarning($e, $this->getJname());
			$return = false;
		}
		return $return;
	}

	/**
	 * @return array
	 */
	function uninstall()
	{
		$return = true;
		$reasons = array();

		$error = $this->disableAuthMod();
		if (!$error) {
			$reasons[] = Text::_('AUTH_MOD_UNINSTALL_FAILED');
			$return = false;
		}

		$error = $this->redirectMod('disable');
		if (!empty($error)) {
			$reasons[] = Text::_('REDIRECT_MOD_UNINSTALL_FAILED');
			$return = false;
		}

		return array($return, $reasons);
	}

	/**
	 * @return bool
	 */
	function clearConfigCache()
	{
		$source_path = $this->params->get('source_path');
		$cache = $source_path . 'cache' . DIRECTORY_SEPARATOR . 'data_global.php';
		if (file_exists($cache)) {
			jimport('joomla.filesystem.file');
			return JFile::delete($cache);
		}
		return true;
	}

	/**
	 * @return object
	 */
	function getSearchQueryColumns() {
		$columns = new stdClass();
		$columns->title = 'p.post_subject';
		$columns->text = 'p.post_text';
		return $columns;
	}

	/**
	 * @param object $pluginParam
	 * @return string
	 */
	function getSearchQuery(&$pluginParam) {
		$db = Factory::getDatabase($this->getJname());
		//need to return threadid, postid, title, text, created, section
		$query = $db->getQuery(true)
			->select('p.topic_id, p.post_id, p.forum_id, CASE WHEN p.post_subject = "" THEN CONCAT("Re: ",t.topic_title) ELSE p.post_subject END AS title, p.post_text AS text,
                    FROM_UNIXTIME(p.post_time, "%Y-%m-%d %h:%i:%s") AS created,
                    CONCAT_WS( "/", f.forum_name, t.topic_title ) AS section,
                    t.topic_views AS hits')
			->from('#__posts AS p')
			->innerJoin('#__topics AS t ON t.topic_id = p.topic_id')
			->innerJoin('#__forums AS f on f.forum_id = p.forum_id');

		return (string)$query;
	}

	/**
	 * @param string $where
	 * @param JRegistry $pluginParam
	 * @param string $ordering
	 *
	 * @return void
	 */
	function getSearchCriteria(&$where, &$pluginParam, $ordering) {
		$where.= ' AND p.post_approved = 1';
		/**
		 * @ignore
		 * @var $platform \JFusion\Plugin\Platform\Joomla
		 */
		$platform = Factory::getPlayform('Joomla', $this->getJname());
		if ($pluginParam->get('forum_mode', 0)) {
			$selected_ids = $pluginParam->get('selected_forums', array());
			$forumids = $platform->filterForumList($selected_ids);
		} else {
			try {
				$db = Factory::getDatabase($this->getJname());
				//no forums were selected so pull them all then filter

				$query = $db->getQuery(true)
					->select('forum_id')
					->from('#__forums')
					->where('forum_type = 1')
					->order('left_id');

				$db->setQuery($query);
				$forumids = $db->loadColumn();
				$forumids = $platform->filterForumList($forumids);
			} catch (Exception $e) {
				Framework::raiseError($e, $this->getJname());
				$forumids = array();
			}

		}
		if (empty($forumids)) {
			$forumids = array(0);
		}
		//determine how to sort the results which is required for accurate results when a limit is placed
		switch ($ordering) {
			case 'oldest':
				$sort = 'p.post_time ASC';
				break;
			case 'category':
				$sort = 'section ASC';
				break;
			case 'popular':
				$sort = 't.topic_views DESC, p.post_time DESC';
				break;
			case 'alpha':
				$sort = 'title ASC';
				break;
			case 'newest':
			default:
				$sort = 'p.post_time DESC';
				break;
		}
		$where.= ' AND p.forum_id IN (' . implode(',', $forumids) . ') ORDER BY ' . $sort;
	}

	/**
	 * @param mixed $post
	 * @return string
	 */
	function getSearchResultLink($post) {
		/**
		 * @ignore
		 * @var $platform \JFusion\Plugin\Platform\Joomla
		 */
		$platform = Factory::getPlayform('Joomla', $this->getJname());
		return $platform->getPostURL($post->topic_id, $post->post_id);
	}

	/**
	 * @param object $jfdata
	 *
	 * @return void
	 */
	function getBuffer(&$jfdata)
	{
		$session = JFactory::getSession();
		//detect if phpbb3 is already loaded for dual login
		$mainframe = Factory::getApplication();
		if (defined('IN_PHPBB')) {
			//backup any post get vars
			$backup = array();
			$backup['post'] = $_POST;
			$backup['request'] = $_REQUEST;
			$backup['files'] = $_FILES;
			$session->set('JFusionVarBackup', $backup);

			//refresh the page to avoid phpbb3 error
			//this happens as the phpbb3 config file can not be loaded twice
			//and phpbb3 always uses include instead of include_once
			$uri = JUri::getInstance();
			//add a variable to ensure refresh
			$uri->setVar('time', time());
			$link = $uri->toString();
			$mainframe->redirect($link);
			die(' ');
		}

		//restore $_POST, $_FILES, and $_REQUEST data if this was a refresh
		$backup = $session->get('JFusionVarBackup', array());
		if (!empty($backup)) {
			$_POST = $_POST + $backup['post'];
			$_FILES = $_FILES + $backup['files'];
			$_REQUEST = $_REQUEST + $backup['request'];
			$session->clear('JFusionVarBackup');
		}

		// Get the path
		global $source_url;
		$source_url = $this->params->get('source_url');
		$source_path = $this->params->get('source_path');
		//get the filename
		$jfile = $mainframe->input->get('jfile');
		if (!$jfile) {
			//use the default index.php
			$jfile = 'index.php';
		}
		//redirect for file download requests
		if ($jfile == 'file.php') {
			$url = 'Location: ' . $this->params->get('source_url') . 'download/file.php?' . $_SERVER['QUERY_STRING'];
			header($url);
			exit();
		}
		//combine the path and filename
		$index_file = $source_path . basename($jfile);
		if (!is_file($index_file)) {
			Framework::raiseWarning('The path to the requested does not exist', $this->getJname());
		} else {
			//set the current directory to phpBB3
			chdir($source_path);
			/* set scope for variables required later */
			global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template, $phpbb_hook, $module, $mode, $table_prefix, $id_cache, $sort_dir;
			if ($jfile == 'mcp.php') {
				//must globalize these to make sure urls are generated correctly via extra_url() in mcp.php
				global $forum_id, $topic_id, $post_id, $report_id, $user_id, $action;
			} else if ($jfile == 'feed.php') {
				global $board_url;
			}

			//see if we need to force the database to use a new connection
			if ($this->params->get('database_new_link', 0) && !defined('PHPBB_DB_NEW_LINK')) {
				define('PHPBB_DB_NEW_LINK', 1);
			}

			$hooks = Factory::getPlayform($jfdata->platform, $this->getJname())->hasFile('hooks.php');
			if ($hooks) {
				//define the phpBB3 hooks
				require_once $hooks;
			}
			// Get the output
			ob_start();

			//we need to hijack $_SERVER['PHP_SELF'] so that phpBB correctly utilizes it such as correctly noted the page a user is browsing
			$php_self = $_SERVER['PHP_SELF'];
			$juri = new Uri($source_url);
			$_SERVER['PHP_SELF'] = $juri->getPath() . $jfile;

			try {
				if (!defined('UTF8_STRLEN')) {
					define('UTF8_STRLEN', true);
				}
				if (!defined('UTF8_CORE')) {
					define('UTF8_CORE', true);
				}
				if (!defined('UTF8_CASE')) {
					define('UTF8_CASE', true);
				}
				include_once ($index_file);
			} catch (Exception $e) {
				$jfdata->buffer = ob_get_contents();
				ob_end_clean();
			}

			//restore $_SERVER['PHP_SELF']
			$_SERVER['PHP_SELF'] = $php_self;

			//change the current directory back to Joomla.
			chdir(JPATH_SITE);
			//show more smileys without the Joomla frame
			$jfmode = $mainframe->input->get('mode');
			$jfform = $mainframe->input->get('form');
			if ($jfmode == 'smilies' || ($jfmode == 'searchuser' && !empty($jfform) || $jfmode == 'contact')) {
				$pattern = '#<head[^>]*>(.*?)<\/head>.*?<body[^>]*>(.*)<\/body>#si';
				preg_match($pattern, $jfdata->buffer, $temp);
				$jfdata->header = $temp[1];
				$jfdata->body = $temp[2];
				$this->parseHeader($jfdata);
				$this->parseBody($jfdata);
				die('<html><head>' . $jfdata->header . '</head><body>' . $jfdata->body . '</body></html>');
			}
		}
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function parseBody(&$data) {

		static $regex_body, $replace_body, $callback_function;
		if (!$regex_body || !$replace_body || $callback_function) {
			// Define our preg arrays
			$regex_body = array();
			$replace_body = array();
			$callback_function = array();
			//fix anchors
			$regex_body[] = '#\"\#(.*?)\"#mS';
			$replace_body[] = '"' . $data->fullURL . '#$1"';
			$callback_function[] = '';

			//parse URLS
			$regex_body[] = '#href="(.*?)"#m';
			$replace_body[] = '';
			$callback_function[] = 'fixUrl';

			//convert relative links from images into absolute links
			$regex_body[] = '#(src="|background="|url\(\'?)./(.*?)("|\'?\))#mS';
			$replace_body[] = '$1' . $data->integratedURL . '$2$3';
			$callback_function[] = '';
			//fix for form actions
			$regex_body[] = '#action="(.*?)"(.*?)>#m';
			$replace_body[] = ''; //$this->fixAction('$1', '$2', "' . $data->baseURL . '")';
			$callback_function[] = 'fixAction';
			//convert relative popup links to full url links
			$regex_body[] = '#popup\(\'\.\/(.*?)\'#mS';
			$replace_body[] = 'popup(\'' . $data->integratedURL . '$1\'';
			$callback_function[] = '';
			//fix for mcp links
			$mainframe = Factory::getApplication();
			$jfile = $mainframe->input->get('jfile');
			if ($jfile == 'mcp.php') {
				$topicid = $mainframe->input->getInt('t');
				//fix for merge thread
				$regex_body[] = '#(&|&amp;)to_topic_id#mS';
				$replace_body[] = '$1t=' . $topicid . '$1to_topic_id';
				$callback_function[] = '';
				$regex_body[] = '#/to_topic_id#mS';
				$replace_body[] = '/t,' . $topicid . '/to_topic_id';
				$callback_function[] = '';
				//fix for merge posts
				$regex_body[] = '#(&|&amp;)action=merge_select#mS';
				$replace_body[] = '$1t=' . $topicid . '$1action=merge_select';
				$callback_function[] = '';
				$regex_body[] = '#/action=merge_select#mS';
				$replace_body[] = '/t,' . $topicid . '/action=merge_select';
				$callback_function[] = '';
			}
		}

		/**
		 * @TODO lets parse our todo list for regex
		 */
		foreach ($regex_body as $k => $v) {
			//check if we need to use callback
			if(!empty($callback_function[$k])){
				$data->body = preg_replace_callback($regex_body[$k], array(&$this, $callback_function[$k]), $data->body);
			} else {
				$data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
			}
		}
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	function cssCacheName($url) {
		$uri = new Uri($url);
		$uri->delVar('sid');
		return parent::cssCacheName($uri->toString());
	}

	/**
	 * @param array $vars
	 */
	function parseRoute(&$vars) {
		foreach ($vars as $k => $v) {
			//must undo Joomla parsing that changes dashes to colons so that PM browsing works correctly
			if ($k == 'f') {
				$vars[$k] = str_replace (':', '-', $v);
			} elseif ($k == 'redirect') {
				$vars[$k] = base64_decode($v);
			}
		}
	}

	/**
	 * @param array $segments
	 */
	function buildRoute(&$segments) {
		if (is_array($segments)) {
			foreach($segments as $k => $v) {
				if (strstr($v, 'redirect,./')) {
					//need to encode the redirect to prevent issues with SEF
					$url = substr($v, 9);
					$segments[$k] = 'redirect,' . base64_encode($url);
				}
			}
		}
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixUrl($matches) {
		$q = $matches[1];

		$integratedURL = $this->data->integratedURL;
		$baseURL = $this->data->baseURL;

		if ( strpos($q, './') === 0 ) {
			$q = substr($q, 2);
		} else if ( strpos($q, $this->data->integratedURL . 'index.php') === 0 ) {
			$q = substr($q, strlen($this->data->integratedURL . 'index.php'));
		} else {
			return $matches[0];
		}

		//allow for direct downloads and admincp access
		if (strstr($q, 'download/') || strstr($q, 'adm/')) {
			$url = $integratedURL . $q;
			return 'href="' . $url . '"';
		}

		//these are custom links that are based on modules and thus no as easy to replace as register and lost password links in the hooks.php file so we'll just parse them
		$edit_account_url = $this->params->get('edit_account_url');
		if (strstr($q, 'mode=reg_details') && !empty($edit_account_url)) {
			$url = $edit_account_url;
			return 'href="' . $url . '"';
		}

		$edit_profile_url = $this->params->get('edit_profile_url');
		if (!empty($edit_profile_url)) {
			if (strstr($q, 'mode=profile_info')) {
				$url = $edit_profile_url;
				return 'href="' . $url . '"';
			}

			static $profile_mod_id;
			if (empty($profile_mod_id)) {
				//the first item listed in the profile module is the edit profile link so must rewrite it to go to signature instead
				try {
					$db = Factory::getDatabase($this->getJname());

					$query = $db->getQuery(true)
						->select('module_id')
						->from('#__modules')
						->where('module_langname = ' . $db->quote('UCP_PROFILE'));

					$db->setQuery($query);
					$profile_mod_id = $db->loadResult();
				} catch (Exception $e) {
					Framework::raiseError($e, $this->getJname());
					$profile_mod_id = null;
				}
			}
			if (!empty($profile_mod_id) && strstr($q, 'i=' . $profile_mod_id)) {
				$url = 'ucp.php?i=profile&mode=signature';
				$url = Factory::getApplication()->routeURL($url, Factory::getApplication()->input->getInt('Itemid'), $this->getJname());
				return 'href="' . $url . '"';
			}
		}

		$edit_avatar_url = $this->params->get('edit_avatar_url');
		if (strstr($q, 'mode=avatar') && !empty($edit_avatar_url)) {
			$url = $edit_avatar_url;
			return 'href="' . $url . '"';
		}

		if (substr($baseURL, -1) != '/') {
			//non-SEF mode
			$q = str_replace('?', '&amp;', $q);
			$url = $baseURL . '&amp;jfile=' . $q;
		} else {
			//check to see what SEF mode is selected
			$sefmode = $this->params->get('sefmode');
			if ($sefmode == 1) {
				//extensive SEF parsing was selected
				$url = Factory::getApplication()->routeURL($q, Factory::getApplication()->input->getInt('Itemid'));
			} else {
				//simple SEF mode, we can just combine both variables
				$url = $baseURL . $q;
			}
		}
		return 'href="' . $url . '"';
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixRedirect($matches) {
		$url = $matches[1];
		$baseURL = $this->data->baseURL;

		//\JFusion\Framework::raiseWarning($url, $this->getJname());
		//split up the timeout from url
		$parts = explode('url=', $url, 2);
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
		$return = '<meta http-equiv="refresh" content="' . $parts[0] . 'url=' . $redirectURL . '">';
		//\JFusion\Framework::raiseWarning(htmlentities($return), $this->getJname());
		return $return;
	}

	/**
	 * @param $matches
	 * @return string
	 */
	function fixAction($matches) {
		$url = $matches[1];
		$extra = $matches[2];
		$baseURL = $this->data->baseURL;

		$url = htmlspecialchars_decode($url);
		$mainframe = Factory::getApplication();
		$Itemid = $mainframe->input->getInt('Itemid');
		//strip any leading dots
		if (substr($url, 0, 2) == './') {
			$url = substr($url, 2);
		}
		if (substr($baseURL, -1) != '/') {
			//non-SEF mode
			$url_details = parse_url($url);
			$url_variables = array();
			if (!empty($url_details['query'])) {
				parse_str($url_details['query'], $url_variables);
			}
			$jfile = basename($url_details['path']);
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
				if(!empty($url_details['query'])) {
					$query = '?' . $url_details['query'];
				} else {
					$query = '';
				}
				$jfile = basename($url_details['path']);
				$replacement = 'action="' . $baseURL . $jfile . $query . '"' . $extra . '>';
			}
		}
		unset($url_variables['option'], $url_variables['jfile'], $url_variables['Itemid']);
		if(!empty($url_variables['mode'])){
			if ($url_variables['mode'] == 'topic_view') {
				$url_variables['t'] = $mainframe->input->get('t');
				$url_variables['f'] = $mainframe->input->get('f');
			}
		}

		//add any other variables
		if (is_array($url_variables)) {
			foreach ($url_variables as $key => $value) {
				$replacement.= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
			}
		}
		return $replacement;
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function parseHeader(&$data) {
		static $regex_header, $replace_header;
		if (!$regex_header || !$replace_header) {
			// Define our preg arrays
			$regex_header = array();
			$replace_header = array();
			$callback_header = array();
			//convert relative links into absolute links
			$regex_header[] = '#(href="|src=")./(.*?")#mS';
			$replace_header[] = '$1' . $data->integratedURL . '$2';
			$callback_header[] = '';
			//fix for URL redirects
			$regex_header[] = '#<meta http-equiv="refresh" content="(.*?)"(.*?)>#m';
			$replace_header[] = ''; //$this->fixRedirect("$1","' . $data->baseURL . '")';
			$callback_header[] = 'fixRedirect';
			//fix pm popup URL to be absolute for some phpBB templates
			$regex_header[] = '#var url = \'\.\/(.*?)\';#mS';
			$replace_header[] = 'var url = \'{$data->integratedURL}$1\';';
			$callback_header[] = '';
			//convert relative popup links to full url links
			$regex_header[] = '#popup\(\'\.\/(.*?)\'#mS';
			$replace_header[] = 'popup(\'' . $data->integratedURL . '$1\'';
			$callback_header[] = '';
		}

		/**
		 * @TODO lets parse our todo list for regex
		 */
		foreach ($regex_header as $k => $v) {
			//check if we need to use callback
			if(!empty($callback_header[$k])){
				$data->header = preg_replace_callback($regex_header[$k], array(&$this, $callback_header[$k]), $data->header);
			} else {
				$data->header = preg_replace($regex_header[$k], $replace_header[$k], $data->header);
			}
		}
	}

	/**
	 * @return array
	 */
	function getPathWay() {
		try {
			$db = Factory::getDatabase($this->getJname());
			$pathway = array();

			$mainframe = Factory::getApplication();

			$forum_id = $mainframe->input->getInt('f');
			if (!empty($forum_id)) {
				//get the forum's info

				$query = $db->getQuery(true)
					->select('forum_name, parent_id, left_id, right_id, forum_parents')
					->from('#__forums')
					->where('forum_id = ' . $db->quote($forum_id));

				$db->setQuery($query);
				$forum_info = $db->loadObject();

				if (!empty($forum_info)) {
					//get forum parents

					$query = $db->getQuery(true)
						->select('forum_id, forum_name')
						->from('#__forums')
						->where('left_id < ' . $forum_info->left_id)
						->where('right_id > ' . $forum_info->right_id)
						->order('left_id ASC');

					$db->setQuery($query);
					$forum_parents = $db->loadObjectList();

					if (!empty($forum_parents)) {
						foreach ($forum_parents as $data) {
							$crumb = new stdClass();
							$crumb->title = $data->forum_name;
							$crumb->url = 'viewforum.php?f=' . $data->forum_id;
							$pathway[] = $crumb;
						}
					}

					$crumb = new stdClass();
					$crumb->title = $forum_info->forum_name;
					$crumb->url = 'viewforum.php?f=' . $forum_id;
					$pathway[] = $crumb;
				}
			}

			$topic_id = $mainframe->input->getInt('t');
			if (!empty($topic_id)) {
				$query = $db->getQuery(true)
					->select('topic_title')
					->from('#__topics')
					->where('topic_id = ' . $db->quote($topic_id));

				$db->setQuery($query);
				$topic_title = $db->loadObject();

				if (!empty($topic_title)) {
					$crumb = new stdClass();
					$crumb->title = $topic_title->topic_title;
					$crumb->url = 'viewtopic.php?f=' . $forum_id . '&amp;t=' . $topic_id;
					$pathway[] = $crumb;
				}
			}
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			$pathway = array();
		}

		return $pathway;
	}
}
