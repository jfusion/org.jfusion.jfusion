<?php

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
class JFusionForum_phpbb3 extends JFusionForum
{
	/**
	 * @var $helper JFusionHelper_phpbb3
	 */
	var $helper;

    var $joomlaGlobals;
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'phpbb3';
    }

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
     * @param int $puser_id
     *
     * @return int|string
     */
    function getAvatar($puser_id) {
	    $url = false;
	    try {
		    if ($puser_id) {
			    $db = JFusionFactory::getDatabase($this->getJname());

			    $query = $db->getQuery(true)
				    ->select('user_avatar, user_avatar_type')
				    ->from('#__users')
				    ->where('user_id = ' . (int)$puser_id);

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
		    JFusionFunction::raiseError($e, $this->getJname());
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
			    $db = JFusionFactory::getDatabase($this->getJname());

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
			JFusionFunction::raiseError($e, $this->getJname());
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

		    $db = JFusionFactory::getDatabase($this->getJname());
		    $where = 'a.forum_id IN (' . implode(',', $forumids) . ') AND a.topic_approved = 1 AND b.post_approved = 1';

		    $numargs = func_num_args();
		    if ($numargs > 3) {
			    $filters = func_get_args();
			    for ($i = 3; $i < $numargs; $i++) {
				    if ($filters[$i][0] == 'userid') {
					    $where.= ' HAVING userid = ' . $db->Quote($filters[$i][1]);
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
		    JFusionFunction::raiseError($e, $this->getJname());
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
				    $db = JFusionFactory::getDatabase($this->getJname());

				    $userlookup = JFusionFunction::lookupUser($this->getJname(), $JUser->id);
				    if (!empty($userlookup)) {
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
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
        return $newstatus;
    }

    /**
     * @return array
     */
    function getForumList() {
	    try {
		    //get the connection to the db
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('forum_id as id, forum_name as name')
			    ->from('#__forums')
			    ->where('forum_type = 1')
		        ->order('left_id');

		    $db->setQuery($query);
		    //getting the results
		    return $db->loadObjectList('id');
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
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
			    $db = JFusionFactory::getDatabase($this->getJname());
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
		    JFusionFunction::raiseError($e, $this->getJname());
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
			    $db = JFusionFactory::getDatabase($this->getJname());
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
					    $userinfo = JFusionFunction::lookupUser($this->getJname(), $JUser->id);
					    if (!empty($userinfo)) {
						    $userid = $userinfo->userid;

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
		    JFusionFunction::raiseError($e, $this->getJname());
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
		    $db = JFusionFactory::getDatabase($this->getJname());

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
			$db = JFusionFactory::getDatabase($this->getJname());
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
				$mainframe = JFactory::getApplication();
				$timezone = $mainframe->getCfg('offset');
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
				$forum_stats->forum_last_post_subject	= $db->Quote($subject);
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
			$db = JFusionFactory::getDatabase($this->getJname());
			$subject = trim(strip_tags($contentitem->title));

			//prepare the content body
			$text = $this->prepareFirstPostBody($dbparams, $contentitem);

			$bbcode = $this->helper->bbcode_parser($text);

			$timestamp = $dbparams->get('use_content_created_date', false) ? JFactory::getDate($contentitem->created)->toUnix() : time();
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
				->set('topic_title = ' . $db->Quote($subject))
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
	 * @param object $ids         array with forum id ($ids['forumid'], thread id ($ids['threadid']) and first post id ($ids['postid'])
	 * @param object $contentitem object of content item
	 * @param object $userinfo    object info of the forum user
	 * @param stdClass $postinfo object with post info
     *
	 * @return array with status
	 */
	function createPost($params, $ids, $contentitem, $userinfo, $postinfo)
	{
        $status = array('error' => array(), 'debug' => array());
		try {
			$db = JFusionFactory::getDatabase($this->getJname());
			if($userinfo->guest) {
				$userinfo->username = $postinfo->username;
				$userinfo->userid = 1;

				if(empty($userinfo->username)) {
					throw new RuntimeException(JTEXT::_('GUEST_FIELDS_MISSING'));
				} else {
					$user = JFusionFactory::getUser($this->getJname());
					$username_clean = $user->filterUsername($userinfo->username);

					$query = $db->getQuery(true)
						->select('COUNT(*)')
						->from('#__users')
						->where('username = ' . $db->Quote($userinfo->username))
						->where('username = ' . $db->Quote($username_clean))
						->where('username_clean = ' . $db->Quote($userinfo->username))
						->where('username_clean = ' . $db->Quote($username_clean))
						->where('LOWER(user_email) = ' . $db->Quote(strtolower($userinfo->username)));

					$db->setQuery($query);
					$result = $db->loadResult();
					if(!empty($result)) {
						throw new RuntimeException(JTEXT::_('USERNAME_IN_USE'));
					}
				}
			}
			//setup some variables
			$userid = $userinfo->userid;
			$public = JFusionFactory::getPublic($this->getJname());
			//strip out html from post
			$text = strip_tags($postinfo->text);

			if(!empty($text)) {
				$public->prepareText($text);
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
			$db = JFusionFactory::getDatabase($this->getJname());

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
			JFusionFunction::raiseError($e, $this->getJname());
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
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('count(*)')
				->from('#__posts')
				->where('topic_id = ' . $existingthread->threadid)
				->where('post_approved = 1')
				->where('post_id != ' . $existingthread->postid);

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
			$result = 0;
		}
		return $result;
	}
}
