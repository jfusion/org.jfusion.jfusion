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
class JFusionForum_phpbb3 extends JFusionForum {
    var $joomlaGlobals;
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'phpbb3';
    }

    /**
     * @param int $threadid
     * @return string
     */
    function getThreadURL($threadid) {
        return 'viewtopic.php?t=' . $threadid;
    }

    /**
     * @param int $threadid
     * @param int $postid
     * @return string
     */
    function getPostURL($threadid, $postid) {
        return 'viewtopic.php?p=' . $postid . '#p' . $postid;
    }

    /**
     * @param int $uid
     * @return string
     */
    function getProfileURL($uid) {
        return 'memberlist.php?mode=viewprofile&u=' . $uid;
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
     * @return int|string
     */
    function getAvatar($puser_id) {
        if ($puser_id) {
            $dbparams = JFusionFactory::getParams($this->getJname());
            $db = JFusionFactory::getDatabase($this->getJname());
            $db->setQuery('SELECT user_avatar, user_avatar_type FROM #__users WHERE user_id=' . (int)$puser_id);
            $db->query();
            $result = $db->loadObject();
            if (!empty($result)) {
                if ($result->user_avatar_type == 1) {
                    // AVATAR_UPLOAD
                    $url = $dbparams->get('source_url') . 'download/file.php?avatar=' . $result->user_avatar;
                } else if ($result->user_avatar_type == 3) {
                    // AVATAR_GALLERY
                    $db->setQuery("SELECT config_value FROM #__config WHERE config_name='avatar_gallery_path'");
                    $db->query();
                    $path = $db->loadResult();
                    if (!empty($path)) {
                        $url = $dbparams->get('source_url') . $path . '/' . $result->user_avatar;
                    } else {
                        $url = '';
                    }
                } else if ($result->user_avatar_type == 2) {
                    // AVATAR REMOTE URL
                    $url = $result->user_avatar;
                } else {
                    $url = '';
                }
                return $url;
            }
        }
        return 0;
    }

    /**
     * @param int $puser_id
     * @return array
     */
    function getPrivateMessageCounts($puser_id) {
        $unreadCount = $totalCount = 0;
        if ($puser_id) {
            // read pm counts
            $db = JFusionFactory::getDatabase($this->getJname());
            // read unread count
            $db->setQuery('SELECT COUNT(msg_id)
            FROM #__privmsgs_to
            WHERE pm_unread = 1
            AND folder_id <> -2
            AND user_id = ' . (int)$puser_id);
            $unreadCount = $db->loadResult();
            // read total pm count
            $db->setQuery('SELECT COUNT(msg_id)
            FROM #__privmsgs_to
            WHERE folder_id NOT IN (-1, -2)
            AND user_id = ' . (int)$puser_id);
            $totalCount = $db->loadResult();
        }
        return array('unread' => $unreadCount, 'total' => $totalCount);
    }

    /**
     * @param array $usedforums
     * @param string $result_order
     * @param int $result_limit
     * @return array
     */
    function getActivityQuery($usedforums, $result_order, $result_limit) {
        //filter forums based on user permissions
        $forumids = $this->filterForumList($usedforums);
        if (empty($forumids)) {
            $forumids = array(0);
        }
        $where = ' WHERE a.forum_id IN (' . implode(',', $forumids) . ') AND a.topic_approved = 1 AND b.post_approved = 1';

        $numargs = func_num_args();
        if ($numargs > 3) {
            $db = & JFusionFactory::getDatabase($this->getJname());
            $filters = func_get_args();
            $i = 3;
            for ($i = 3; $i < $numargs; $i++) {
                if ($filters[$i][0] == 'userid') {
                    $where.= ' HAVING userid = ' . $db->Quote($filters[$i][1]);
                }
            }
        }

        $end = $result_order . " LIMIT 0," . $result_limit;
        $query = array(
        //LAT with first post info
        LAT . '0' => "SELECT a.topic_id AS threadid, a.topic_first_post_id AS postid, a.topic_first_poster_name AS name, CASE WHEN b.poster_id = 1 AND a.topic_first_poster_name != '' THEN a.topic_first_poster_name ELSE c.username_clean END as username, a.topic_poster AS userid, CASE WHEN b.poster_id = 1 THEN 1 ELSE 0 END AS guest, a.topic_title AS subject, a.topic_time AS dateline, a.forum_id as forum_specific_id, a.topic_last_post_time as last_post_dateline FROM `#__topics` as a INNER JOIN `#__posts` as b ON a.topic_first_post_id = b.post_id INNER JOIN `#__users` AS c ON b.poster_id = c.user_id $where ORDER BY a.topic_last_post_time $end",
        //LAT with latest post info
        LAT . '1' => "SELECT a.topic_id AS threadid, a.topic_last_post_id AS postid, a.topic_last_poster_name AS name, CASE WHEN b.poster_id = 1 AND a.topic_last_poster_name != '' THEN a.topic_last_poster_name ELSE c.username_clean END as username, a.topic_last_poster_id AS userid, CASE WHEN a.topic_last_poster_id = 1 THEN 1 ELSE 0 END AS guest, a.topic_title AS subject, a.topic_last_post_time AS dateline, a.forum_id as forum_specific_id, a.topic_last_post_time as last_post_dateline FROM `#__topics` as a INNER JOIN `#__posts` as b ON a.topic_last_post_id = b.post_id INNER JOIN `#__users` AS c ON b.poster_id = c.user_id $where ORDER BY a.topic_last_post_time $end",
        //LCT
        LCT => "SELECT a.topic_id AS threadid, a.topic_first_post_id AS postid, a.topic_first_poster_name AS name, CASE WHEN a.topic_poster = 1 AND a.topic_first_poster_name != '' THEN a.topic_first_poster_name ELSE c.username_clean END as username, a.topic_poster AS userid, CASE WHEN a.topic_poster = 1 THEN 1 ELSE 0 END AS guest, a.topic_title AS subject, b.post_text AS body, a.topic_time AS dateline, a.forum_id as forum_specific_id, a.topic_last_post_time as last_post_dateline FROM `#__topics` as a INNER JOIN `#__posts` as b ON a.topic_first_post_id = b.post_id INNER JOIN `#__users` AS c ON b.poster_id = c.user_id $where ORDER BY a.topic_time $end",
        //LCP
        LCP => "SELECT b.topic_id AS threadid, b.post_id AS postid, CASE WHEN b.poster_id = 1 AND b.post_username!='' THEN b.post_username ELSE c.username END AS name, CASE WHEN b.poster_id = 1 AND b.post_username != '' THEN b.post_username ELSE c.username_clean END as username, b.poster_id AS userid, CASE WHEN b.poster_id = 1 THEN 1 ELSE 0 END AS guest, b.post_subject AS subject, b.post_text AS body, b.post_time AS dateline, b.post_time as last_post_dateline, b.forum_id as forum_specific_id FROM `#__topics` as a INNER JOIN `#__posts` AS b ON a.topic_id = b.topic_id INNER JOIN `#__users` AS c ON b.poster_id = c.user_id $where ORDER BY b.post_time $end");

        return $query;
    }

    /**
     * @param object $post
     * @return int
     */
    function checkReadStatus(&$post)
    {
        $JUser = JFactory::getUser();
        if (!$JUser->guest) {
            static $marktimes;
            if (!is_array($marktimes)) {
                $marktimes = array();
                $db = & JFusionFactory::getDatabase($this->getJname());

                $userlookup = JFusionFunction::lookupUser($this->getJname(), $JUser->id);
                if (!empty($userlookup)) {
                    $query = "SELECT topic_id, mark_time FROM #__topics_track WHERE user_id = {$userlookup->userid}";
                    $db->setQuery($query);
                    $marktimes['thread'] = $db->loadObjectList('topic_id');

                    $query = "SELECT forum_id, mark_time FROM #__forums_track WHERE user_id = {$userlookup->userid}";
                    $db->setQuery($query);
                    $marktimes['forum'] = $db->loadObjectList('forum_id');

                    $query = "SELECT user_lastmark FROM #__users WHERE user_id = {$userlookup->userid}";
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
        } else {
            $newstatus = 0;
        }
        return $newstatus;
    }

    /**
     * @return array
     */
    function getForumList() {
        //get the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT forum_id as id, forum_name as name FROM #__forums
                    WHERE forum_type = 1 ORDER BY left_id';
        $db->setQuery($query);
        //getting the results
        return $db->loadObjectList('id');
    }

    /**
     * @param $forumids
     * @return array
     */
    function filterForumList($forumids)
    {
        if (empty($forumids)) {
            $db = & JFusionFactory::getDatabase($this->getJname());
            //no forums were selected so pull them all
            $query = "SELECT forum_id FROM #__forums WHERE forum_type = 1 ORDER BY left_id";
            $db->setQuery($query);
            $forumids = $db->loadResultArray();
        } elseif (!is_array($forumids)) {
            $forumids = explode(',', $forumids);
        }

        $phpbb_acl = $this->getForumPermissions('find');

        //determine if this user has permission to view the forum
        if (is_array($forumids)) {
	        foreach( $forumids as $k => $f) {
	            if (!$phpbb_acl[$f]) {
	                unset($forumids[$k]);
	            }
	        }
        }
        return $forumids;
    }

    /**
     * @param string $userid
     * @return array
     */
    function getForumPermissions($userid = 'find') {
        static $phpbb_acl;
        if (!is_array($phpbb_acl)) {
            $db = & JFusionFactory::getDatabase($this->getJname());
            $phpbb_acl = array();
            $user_acl = array();
            $groups_acl = array();

            //get permissions for all forums in case more than one module/plugin is present with different settings
            $db = & JFusionFactory::getDatabase($this->getJname());
            $query = "SELECT forum_id FROM #__forums WHERE forum_type = 1 ORDER BY left_id";
            $db->setQuery($query);
            $forumids = $db->loadResultArray();

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
                        $query = "SELECT group_id FROM #__user_group WHERE user_id = $userid";
                        $db->setQuery($query);
                        $groupids = $db->loadResultArray();

                        $query = "SELECT user_type FROM #__users WHERE user_id = $userid";
                        $db->setQuery($query);
                        $usertype = (int) $db->loadResult();
                    } else {
                        //oops, something has failed so use the anonymous user
                        $userid = 1;
                        $query = "SELECT group_id FROM #__groups WHERE group_name = 'GUESTS'";
                        $db->setQuery($query);
                        $groupids[] = $db->loadResult();
                    }
                } else {
                    $userid = 1;
                    $query = "SELECT group_id FROM #__groups WHERE group_name = 'GUESTS'";
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
                $query = "SELECT auth_option_id, auth_option FROM #__acl_options WHERE auth_option IN ('f_', 'f_read')";
                $db->setQuery($query);
                $option_ids = $db->loadObjectList('auth_option');

                $read_id = 0;
                if ( isset($option_ids['f_read']->auth_option_id) ) {
                	$read_id = $option_ids['f_read']->auth_option_id;
                }
            	$global_id = 0;
                if ( isset($option_ids['f_']->auth_option_id) ) {
                	$global_id = $option_ids['f_']->auth_option_id;
                }

                //get the permissions for the user
                $auth_option_ids = array(0, $global_id, $read_id);
                $query = "SELECT * FROM #__acl_users WHERE user_id = $userid AND auth_option_id IN (" . implode(', ', $auth_option_ids) . ") AND forum_id IN (" . implode(', ', $forumids) . ")";
                $db->setQuery($query);
                $results = $db->loadObjectList();

				if ($results) {
	                foreach ($results as $r) {
	                    if ($r->auth_option_id) {
	                        //use the specific setting
	                        $user_acl[$r->forum_id] = (int) $r->auth_setting;
	                    } else {
	                        //there is a role assigned so find out what the role's permission is
	                        $query = "SELECT auth_option_id, auth_setting FROM #__acl_roles_data WHERE role_id = {$r->auth_role_id} AND auth_option_id IN ('$global_id', '$read_id')";
	                        $db->setQuery($query);
	                        $role_permissions = $db->loadObjectList('auth_option_id');
	                        if (isset($role_permissions[$global_id]) && !$role_permissions[$global_id]) {
	                            //no access role is assigned to this user
	                            $user_acl[$r->forum_id] = (int) $role_permissions[$global_id]->auth_setting;
	                        } elseif (isset($role_permissions[$read_id]) && $role_permissions[$read_id]) {
	                            $user_acl[$r->forum_id] = 1;
	                        }
	                    }
	                }
				}

                //get the permissions for groups
                $query = "SELECT * FROM #__acl_groups WHERE group_id IN (" . implode(", ", $groupids) . ") AND auth_option_id IN (" . implode(', ', $auth_option_ids) . ") AND forum_id IN (" . implode(', ', $forumids) . ")";
                $db->setQuery($query);
                $results = $db->loadObjectList();

                if ($results) {
	                foreach ($results as $r) {
	                    if (empty($groups_acl[$r->forum_id])) {
	                        if ($r->auth_option_id) {
	                            //use the specific setting
	                            $groups_acl[$r->forum_id] = (int) $r->auth_setting;
	                        } else {
	                            //there is a role assigned so find out what the role's permission is
	                            $query = "SELECT auth_option_id, auth_setting FROM #__acl_roles_data WHERE role_id = {$r->auth_role_id} AND auth_option_id IN ('$global_id', '$read_id')";
	                            $db->setQuery($query);
	                            $role_permissions = $db->loadObjectList('auth_option_id');
	                            if (isset($role_permissions[$global_id]) && !$role_permissions[$global_id]) {
	                                //no access role is assigned to this group
	                                $groups_acl[$r->forum_id] = (int) $role_permissions[$global_id]->auth_setting;
	                            } elseif (isset($role_permissions[$read_id]) && $role_permissions[$read_id]) {
	                                //group has been given access
	                                $groups_acl[$r->forum_id] = 1;
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
                } elseif (isset($user_acl[$id])) {
                    //user permissions have preference over group permissions
                    $phpbb_acl[$id] = $user_acl[$id];
                } elseif (isset($groups_acl[$id])) {
                    //use group's permission
                    $phpbb_acl[$id] = $groups_acl[$id];
                } else {
                    //assume user does not have permission
                    $phpbb_acl[$id] = 0;
                }
            }
        }

        return $phpbb_acl;
    }
    /************************************************
    * Functions For JFusion Discussion Bot Plugin
    ***********************************************/
    /**
     * Retrieves thread information
     * @param int Id of specific thread
     * @return object Returns object with thread information
     * return the object with these three items
     * $result->forumid
     * $result->threadid (yes add it even though it is passed in as it will be needed in other functions)
     * $result->postid - this is the id of the first post in the thread
     */
    function getThread($threadid)
    {
		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT topic_id AS threadid, forum_id AS forumid, topic_first_post_id AS postid FROM #__topics WHERE topic_id = $threadid";
		$db->setQuery($query);
		$results = $db->loadObject();
		return $results;
    }

    /**
     * Creates new thread and posts first post
     *
     * @param JParameter &$dbparams with discussion bot parameters
     * @param object &$contentitem
     * @param int $forumid Id of forum to create thread
     * @param array &$status contains errors and status of actions
     *
     * @return void
     */
	function createThread(&$dbparams, &$contentitem, $forumid, &$status)
	{
		//setup some variables
		$userid = $this->getThreadAuthor($dbparams,$contentitem);
		$jdb =& JFusionFactory::getDatabase($this->getJname());
		$subject = trim(strip_tags($contentitem->title));

		//prepare the content body
		$text = $this->prepareFirstPostBody($dbparams, $contentitem);

		//the user information
		$query = "SELECT username, username_clean, user_colour, user_permissions FROM #__users WHERE user_id = '$userid'";
		$jdb->setQuery($query);
		$phpbbUser = $jdb->loadObject();

        if ($dbparams->get('use_content_created_date', false)) {
            $mainframe = JFactory::getApplication();
            $timezone = $mainframe->getCfg('offset');
            $timestamp = strtotime($contentitem->created);
            //undo Joomla's timezone offset
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

		if(!$jdb->insertObject('#__topics', $topic_row, 'topic_id' )){
			$status['error'] = $jdb->stderr();
			return;
		}
		$topicid = $jdb->insertid();
        require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'bbcode_parser.php';
		$parser = new phpbb_bbcode_parser($text, $this->getJname());

		$post_row = new stdClass();
		$post_row->forum_id			= $forumid;
		$post_row->topic_id 		= $topicid;
		$post_row->poster_id		= $userid;
		$post_row->icon_id			= 0;
		$post_row->poster_ip		= $_SERVER["REMOTE_ADDR"];
		$post_row->post_time		= $timestamp;
		$post_row->post_approved	= 1;
		$post_row->enable_bbcode	= 1;
		$post_row->enable_smilies	= 1;
		$post_row->enable_magic_url	= 1;
		$post_row->enable_sig		= 1;
		$post_row->post_username	= $phpbbUser->username;
		$post_row->post_subject		= $subject;
		$post_row->post_text		= $parser->text;
		$post_row->post_checksum	= md5($parser->text);
		$post_row->post_attachment	= 0;
		$post_row->bbcode_bitfield	= $parser->bbcode_bitfield;
		$post_row->bbcode_uid		= $parser->bbcode_uid;
		$post_row->post_postcount	= 1;
		$post_row->post_edit_locked	= 0;

		if(!$jdb->insertObject('#__posts', $post_row, 'post_id')) {
			$status['error'] = $jdb->stderr();
			return;
		}
		$postid = $jdb->insertid();

		$topic_row = new stdClass();
		$topic_row->topic_first_post_id			= $postid;
		$topic_row->topic_last_post_id			= $postid;
		$topic_row->topic_last_post_time		= $timestamp;
		$topic_row->topic_last_poster_id		= (int) $userid;
		$topic_row->topic_last_poster_name		= $phpbbUser->username;
		$topic_row->topic_last_poster_colour	= $phpbbUser->user_colour;
		$topic_row->topic_last_post_subject		= (string) $subject;
		$topic_row->topic_id					= $topicid;
		if(!$jdb->updateObject('#__topics', $topic_row, 'topic_id' )) {
			$status['error'] = $jdb->stderr();
			return;
		}

		$query = "SELECT forum_last_post_time, forum_topics, forum_topics_real, forum_posts FROM #__forums WHERE forum_id = $forumid";
		$jdb->setQuery($query);
		$num = $jdb->loadObject();

		$forum_stats = new stdClass();

		if($dbparams->get('use_content_created_date',false)) {
			//only update the last post for the topic if it really is newer
			$updateLastPost = ($timestamp > $num->forum_last_post_time) ? true : false;
		} else {
			$updateLastPost = true;
		}

		if($updateLastPost) {
			$forum_stats->forum_last_post_id 		=  $postid;
			$forum_stats->forum_last_post_subject	= $jdb->Quote($subject);
			$forum_stats->forum_last_post_time 		=  $timestamp;
			$forum_stats->forum_last_poster_id 		=  (int) $userid;
			$forum_stats->forum_last_poster_name 	=  $phpbbUser->username;
			$forum_stats->forum_last_poster_colour 	= $phpbbUser->user_colour;
		}

		$forum_stats->forum_id 			= $forumid;
		$forum_stats->forum_topics 		= $num->forum_topics + 1;
		$forum_stats->forum_topics_real = $num->forum_topics_real + 1;
		$forum_stats->forum_posts 		= $num->forum_posts + 1;

		if(!$jdb->updateObject('#__forums', $forum_stats, 'forum_id' )) {
			$status['error'] = $jdb->stderr();
			return;
		}

		//update some stats
		$query = "UPDATE #__users SET user_posts = user_posts + 1 WHERE user_id = {$userid}";
		$jdb->setQuery($query);
		if(!$jdb->query()) {
			$status['error'] = $jdb->stderr();
		}

		$query = 'UPDATE #__config SET config_value = config_value + 1 WHERE config_name = \'num_topics\'';
		$jdb->setQuery($query);
		if(!$jdb->query()) {
			$status['error'] = $jdb->stderr();
		}

		if(!empty($topicid) && !empty($postid)) {
			//add information to update forum lookup
			$status['threadinfo']->forumid = $forumid;
			$status['threadinfo']->threadid = $topicid;
			$status['threadinfo']->postid = $postid;
		}
	}

    /**
     * @param int $forumid
     * @param int $threadid
     * @return string
     */
    function getReplyURL($forumid, $threadid)
    {
        return "posting.php?mode=reply&f=$forumid&t=$threadid";
    }

	 /**
     * Updates information in a specific thread/post
     * @param JParameter &$dbparams with discussion bot parameters
     * @param object &$existingthread with existing thread info
     * @param object &$contentitem object containing content information
     * @param array &$status contains errors and status of actions
     */
	function updateThread(&$dbparams, &$existingthread, &$contentitem, &$status)
	{
		$threadid =& $existingthread->threadid;
		$forumid =& $existingthread->forumid;
		$postid =& $existingthread->postid;

		//setup some variables
		$jdb =& JFusionFactory::getDatabase($this->getJname());
		$subject = trim(strip_tags($contentitem->title));

		//prepare the content body
		$text = $this->prepareFirstPostBody($dbparams, $contentitem);

        require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'bbcode_parser.php';
		$parser = new phpbb_bbcode_parser($text, $this->getJname());

        $timestamp = $dbparams->get('use_content_created_date', false) ? JFactory::getDate($contentitem->created)->toUnix() : time();
		$userid = $dbparams->get('default_user');

		$query = "SELECT post_edit_count FROM #__posts WHERE post_id = $postid";
		$jdb->setQuery($query);
		$count = $jdb->loadResult();

		$post_row = new stdClass();
		$post_row->post_subject		= $subject;
		$post_row->post_text		= $parser->text;
		$post_row->post_checksum	= md5($parser->text);
		$post_row->bbcode_bitfield	= $parser->bbcode_bitfield;
		$post_row->bbcode_uid		= $parser->bbcode_uid;
		$post_row->post_edit_time 	= $timestamp;
		$post_row->post_edit_user	= $userid;
		$post_row->post_edit_count	= $count + 1;
		$post_row->post_id 			= $postid;
		if(!$jdb->updateObject('#__posts', $post_row, 'post_id')) {
			$status['error'] = $jdb->stderr();
		} else {
			//update the thread title
			$query = "UPDATE #__topics SET topic_title = " . $jdb->Quote($subject) . " WHERE topic_id = " . (int) $threadid;
			$jdb->Execute($query);
		}
	}

	/**
	 * Creates a post from the quick reply
     *
	 * @param JParameter &$dbparams with discussion bot parameters
	 * @param object &$ids array with thread id ($ids["threadid"]) and first post id ($ids["postid"])
	 * @param &$contentitem object of content item
	 * @param &$userinfo object info of the forum user
     *
	 * @return array with status
	 */
	function createPost(&$dbparams, &$ids, &$contentitem, &$userinfo)
	{
        $status = array('error' => array(),'debug' => array());

		if($userinfo->guest) {
			$userinfo->username = JRequest::getVar('guest_username', '', 'POST');
			$userinfo->userid = 1;

			if(empty($userinfo->username)) {
				$status['error'][] = JTEXT::_('GUEST_FIELDS_MISSING');
				return $status;
			} else {
				$db =& JFusionFactory::getDatabase($this->getJname());
				$user =& JFusionFactory::getUser($this->getJname());
				$username_clean = $user->filterUsername($userinfo->username);
				$query = "SELECT COUNT(*) FROM #__users "
						. " WHERE username = " . $db->Quote($userinfo->username)
						. " OR username = " . $db->Quote($username_clean)
						. " OR username_clean = " . $db->Quote($userinfo->username)
						. " OR username_clean = " . $db->Quote($username_clean)
						. " OR LOWER(user_email) = " . strtolower($db->Quote($userinfo->username));
				$db->setQuery($query);
				$result = $db->loadResult();
				if(!empty($result)) {
					$status["error"][] = JText::_('USERNAME_IN_USE');
					return $status;
				}
			}
		}
		//setup some variables
		$userid =& $userinfo->userid;
		$jdb =& JFusionFactory::getDatabase($this->getJname());
		$public =& JFusionFactory::getPublic($this->getJname());
		$text = JRequest::getVar('quickReply', false, 'POST');
		//strip out html from post
		$text = strip_tags($text);

		if(!empty($text)) {
			$public->prepareText($text);

            require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'bbcode_parser.php';
			$parser = new phpbb_bbcode_parser($text, $this->getJname());

			//get some topic information
			$query = "SELECT topic_title, topic_replies, topic_replies_real FROM #__topics WHERE topic_id = {$ids->threadid}";
			$jdb->setQuery($query);
			$topic = $jdb->loadObject();
			//the user information
			$query = "SELECT username, user_colour, user_permissions FROM #__users WHERE user_id = '$userid'";
			$jdb->setQuery($query);
			$phpbbUser = $jdb->loadObject();

			if($userinfo->guest && !empty($userinfo->username)) {
				$phpbbUser->username = $userinfo->username;
			}

            $timestamp = time();

			$post_approved = ($userinfo->guest && $dbparams->get('moderate_guests',1)) ? 0 : 1;

			$post_row = new stdClass();
			$post_row->forum_id			= $ids->forumid;
			$post_row->topic_id 		= $ids->threadid;
			$post_row->poster_id		= $userid;
			$post_row->icon_id			= 0;
			$post_row->poster_ip		= $_SERVER["REMOTE_ADDR"];
			$post_row->post_time		= $timestamp;
			$post_row->post_approved	= $post_approved;
			$post_row->enable_bbcode	= 1;
			$post_row->enable_smilies	= 1;
			$post_row->enable_magic_url	= 1;
			$post_row->enable_sig		= 1;
			$post_row->post_username	= $phpbbUser->username;
			$post_row->post_subject		= "Re: {$topic->topic_title}";
			$post_row->post_text		= $parser->text;
			$post_row->post_checksum	= md5($parser->text);
			$post_row->post_attachment	= 0;
			$post_row->bbcode_bitfield	= $parser->bbcode_bitfield;
			$post_row->bbcode_uid		= $parser->bbcode_uid;
			$post_row->post_postcount	= 1;
			$post_row->post_edit_locked	= 0;

			if(!$jdb->insertObject('#__posts', $post_row, 'post_id')) {
				$status['error'] = $jdb->stderr();
				return $status;
			}
			$postid = $jdb->insertid();
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
                $topic_row->topic_last_post_subject = "Re: {$topic->topic_title}";
				$topic_row->topic_replies				= $topic->topic_replies + 1;
				$topic_row->topic_replies_real 			= $topic->topic_replies_real + 1;
				$topic_row->topic_id					= $ids->threadid;
				if(!$jdb->updateObject('#__topics', $topic_row, 'topic_id' )) {
					$status['error'] = $jdb->stderr();
				}

				$query = "SELECT forum_posts FROM #__forums WHERE forum_id = {$ids->forumid}";
				$jdb->setQuery($query);
				$num = $jdb->loadObject();

				$forum_stats = new stdClass();
				$forum_stats->forum_last_post_id 		= $postid;
				$forum_stats->forum_last_post_subject	= '';
				$forum_stats->forum_last_post_time 		= $timestamp;
				$forum_stats->forum_last_poster_id 		= (int) $userid;
				$forum_stats->forum_last_poster_name 	= $phpbbUser->username;
				$forum_stats->forum_last_poster_colour 	= $phpbbUser->user_colour;
				$forum_stats->forum_posts				= $num->forum_posts + 1;
				$forum_stats->forum_id 					= $ids->forumid;
				$query = "SELECT forum_topics, forum_topics_real, forum_posts FROM #__forums WHERE forum_id = {$ids->forumid}";
				$jdb->setQuery($query);
				$num = $jdb->loadObject();
				$forum_stats->forum_topics = $num->forum_topics + 1;
				$forum_stats->forum_topics_real = $num->forum_topics_real + 1;
				$forum_stats->forum_posts = $num->forum_posts + 1;
				if(!$jdb->updateObject('#__forums', $forum_stats, 'forum_id' )) {
					$status['error'] = $jdb->stderr();
				}

				//update some stats
				$query = "UPDATE #__users SET user_posts = user_posts + 1 WHERE user_id = {$userid}";
				$jdb->setQuery($query);
				if(!$jdb->query()) {
					$status['error'] = $jdb->stderr();
				}

				$query = 'UPDATE #__config SET config_value = config_value + 1 WHERE config_name = \'num_posts\'';
				$jdb->setQuery($query);
				if(!$jdb->query()) {
					$status['error'] = $jdb->stderr();
				}
			} else {
				//update the for real count so that phpbb notes there are unapproved messages here
				$topic_row = new stdClass();
				$topic_row->topic_replies_real 			= $topic->topic_replies_real + 1;
				$topic_row->topic_id					= $ids->threadid;
				if(!$jdb->updateObject('#__topics', $topic_row, 'topic_id' )) {
					$status['error'] = $jdb->stderr();
				}
			}

			//update moderation status to tell discussion bot to notify user
			$status['post_moderated'] = ($post_approved) ? 0 : 1;
		}

		return $status;
	}

	/**
	 * Returns an object of columns used in createPostTable()
	 * Saves from having to repeat the same code over and over for each plugin
	 * For example:
	 * $columns->userid = "userid";
	 * $columns->username = "username";
	 * $columns->username_clean = "username_clean"; //if applicable for filtered usernames
	 * $columns->dateline = "dateline";
	 * $columns->posttext = "pagetext";
	 * $columns->posttitle = "title";
	 * $columns->postid = "postid";
	 * $columns->threadid = "threadid";
	 * @return object with column names
	 */
	function getDiscussionColumns()
	{
		$columns = new stdClass();
		$columns->userid = "user_id";
		$columns->username = "username";
		$columns->name = "name";
		$columns->dateline = "post_time";
		$columns->posttext = "post_text";
		$columns->posttitle = "post_subject";
		$columns->postid = "post_id";
		$columns->threadid = "topic_id";
		$columns->guest = "guest";
		return $columns;
	}

	/**
     * Retrieves the posts to be displayed in the content item if enabled
     *
     * @param JParameter &$dbparams with discussion bot parameters
     * @param object &$existingthread
     *
     * @return array or object Returns retrieved posts
     */
	function getPosts(&$dbparams, &$existingthread)
	{
		$threadid =& $existingthread->threadid;
		$postid =& $existingthread->postid;

		//set the query
		$sort = $dbparams->get("sort_posts");
		$where = "WHERE p.topic_id = {$threadid} AND p.post_id != {$postid} AND p.post_approved = 1";
        $query = "SELECT p.post_id , CASE WHEN p.poster_id = 1 THEN 1 ELSE 0 END AS guest, CASE WHEN p.poster_id = 1 AND p.post_username != '' THEN p.post_username ELSE u.username END AS name, CASE WHEN p.poster_id = 1 AND p.post_username != '' THEN p.post_username ELSE u.username_clean END AS username, u.user_id, p.post_subject, p.post_time, p.post_text, p.topic_id FROM `#__posts` as p INNER JOIN `#__users` as u ON p.poster_id = u.user_id $where ORDER BY p.post_time $sort";

		$jdb = & JFusionFactory::getDatabase($this->getJname());

		if($dbparams->get('enable_pagination',true)) {
			$application = JFactory::getApplication() ;
			$limitstart = JRequest::getInt( 'limitstart_discuss', 0 );
			$limit = (int) $application->getUserStateFromRequest( 'global.list.limit', 'limit_discuss', 5, 'int' );
			$jdb->setQuery($query,$limitstart,$limit);
		} else {
			$limit_posts = $dbparams->get('limit_posts');
			$query .= empty($limit_posts) || trim($limit_posts)==0 ? "" :  " LIMIT 0,$limit_posts";
			$jdb->setQuery($query);
		}

		$posts = $jdb->loadObjectList();
		return $posts;
	}

    /**
     * @param object $existingthread
     * @return int
     */
    function getReplyCount(&$existingthread)
	{
		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT count(*) FROM #__posts WHERE topic_id = {$existingthread->threadid} AND post_approved = 1 AND post_id != {$existingthread->postid}";
		$db->setQuery($query);
		$result = $db->loadResult();
		return $result;
	}
}
