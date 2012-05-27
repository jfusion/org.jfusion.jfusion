<?php

/**
* @package JFusion_SMF
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Forum Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractforum.php
 * @package JFusion_SMF
 */

class JFusionForum_smf2 extends JFusionForum
{
    /**
     * @return string
     */
    function getJname()
    {
        return 'smf2';
    }

    /**
     * @param int $threadid
     * @return string
     */
    function getThreadURL($threadid)
    {
        return  'index.php?topic=' . $threadid;
    }

    /**
     * @param int $threadid
     * @param int $postid
     * @return string
     */
    function getPostURL($threadid, $postid)
    {
        return  'index.php?topic=' . $threadid . '.msg'.$postid.'#msg' . $postid;
    }

    /**
     * @param int $forumid
     * @param int $threadid
     * @return string
     */
    function getReplyURL($forumid, $threadid)
    {
        return "index.php?action=post;topic=$threadid";
    }

    /**
     * @param int $uid
     * @return string
     */
    function getProfileURL($uid)
    {
        return  'index.php?action=profile&u='.$uid;
    }

    function getPrivateMessageURL()
    {
        return 'index.php?action=pm';
    }

    function getViewNewMessagesURL()
    {
        return 'index.php?action=unread';
    }

    function getForumList()
    {
        // initialise some objects
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT id_board as id, name FROM #__boards';
        $db->setQuery($query );

        //getting the results
        return $db->loadObjectList('id');
    }

    function checkReadStatus(&$post)
    {
		$JUser = JFactory::getUser();
    	if (!$JUser->guest) {
            static $markread;
            if (!is_array($markread)) {
                $markread = array();
                $db = & JFusionFactory::getDatabase($this->getJname());
                $userlookup = JFusionFunction::lookupUser($this->getJname(), $JUser->id);
                if (!empty($userlookup)) {
                    $query = "SELECT id_msg, id_topic FROM #__log_topics WHERE id_member = {$userlookup->userid}";
                    $db->setQuery($query);
                    $markread['topic'] = $db->loadObjectList('id_topic');

                    $query = "SELECT id_msg, id_board FROM #__log_mark_read WHERE id_member = {$userlookup->userid}";
                    $db->setQuery($query);
                    $markread['mark_read'] = $db->loadObjectList('id_board');

                    $query = "SELECT id_msg, id_board FROM #__log_boards WHERE id_member = {$userlookup->userid}";
                    $db->setQuery($query);
                    $markread['board'] = $db->loadObjectList('id_board');
                }
            }

            if (isset($markread['topic'][$post->threadid])) {
                $latest_read_msgid = $markread['topic'][$post->threadid]->id_msg;
            } elseif (isset($markread['mark_read'][$post->forumid])) {
                $latest_read_msgid = $markread['mark_read'][$post->forumid]->id_msg;
            } elseif (isset($markread['board'][$post->forumid])) {
                $latest_read_msgid = $markread['board'][$post->forumid]->id_msg;
            } else {
                $latest_read_msgid = false;
            }

            $newstatus = ($latest_read_msgid !== false && $post->postid > $latest_read_msgid) ? 1 : 0;
        } else {
            $newstatus = 0;
        }
        return $newstatus;
    }

    function getPrivateMessageCounts($userid)
    {
        if ($userid) {

            // initialise some objects
            $db = JFusionFactory::getDatabase($this->getJname());

            // read unread count
            $db->setQuery('SELECT unread_messages FROM #__members WHERE id_member = '.$userid);
            $unreadCount = $db->loadResult();

            // read total pm count
            $db->setQuery('SELECT instant_messages FROM #__members WHERE id_member = '.$userid);
            $totalCount = $db->loadResult();

            return array('unread' => $unreadCount, 'total' => $totalCount);
        }
        return array('unread' => 0, 'total' => 0);
    }

	function getAvatar($puser_id)
    {
		if ($puser_id) {
			// Get SMF Params and get an instance of the database
			$params = JFusionFactory::getParams($this->getJname());
			$db = JFusionFactory::getDatabase($this->getJname());
			// Load member params from database "mainly to get the avatar"
			$db->setQuery('SELECT * FROM #__members WHERE id_member='.$puser_id);
			$db->query();
			$result = $db->loadObject();

			if (!empty($result)) {
				$url = '';
				// SMF has a wierd way of holding attachments. Get instance of the attachments table
				$db->setQuery('SELECT * FROM #__attachments WHERE id_member='.$puser_id);
				$db->query();
				$attachment = $db->loadObject();
				// See if the user has a specific attachment ment for an avatar
				if(!empty($attachment) && $attachment->id_thumb == 0 && $attachment->id_msg == 0 && empty($result->avatar)) {
					$url = $params->get('source_url').'index.php?action=dlattach;attach='.$attachment->id_attach.';type=avatar';
				// If user didnt, check to see if the avatar specified in the first query is a url. If so use it.
				} else if(preg_match("/http(s?):\/\//",$result->avatar)){
					$url = $result->avatar;
				} else if($result->avatar) {
					// If the avatar specified in the first query is not a url but is a file name. Make it one
					$db->setQuery('SELECT * FROM #__settings WHERE variable = "avatar_url"');
					$avatarurl = $db->loadObject();
					// Check for trailing slash. If there is one DONT ADD ONE!
					if(substr($avatarurl->value, -1) == DS){
						$url = $avatarurl->value.$result->avatar;
					// I like redundancy. Recheck to see if there isnt a trailing slash. If there isnt one, add one.
					} else if(substr($avatarurl->value, -1) !== DS){
						$url = $avatarurl->value."/".$result->avatar;
					}
				}

				return $url;
			}
		}
        return false;
	}

     /**
      * Creates new thread and posts first post
      *
      * @param object &$dbparams with discussion bot parameters
      * @param object &$contentitem object containing content information
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
		$query = "SELECT member_name, email_address FROM #__members WHERE id_member = '$userid'";
		$jdb->setQuery($query);
		$smfUser = $jdb->loadObject();

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

		$topic_row->is_sticky = 0;
		$topic_row->id_board = $forumid;
		$topic_row->id_first_msg = $topic_row->id_last_msg = 0;
		$topic_row->id_member_started = $topic_row->id_member_updated =  $userid;
		$topic_row->id_poll = 0;
		$topic_row->num_replies = 0;
		$topic_row->num_views = 0;
		$topic_row->locked = 0;

		if(!$jdb->insertObject('#__topics', $topic_row, 'id_topic' )){
			$status['error'] = $jdb->stderr();
			return;
		}
		$topicid = $jdb->insertid();

		$post_row = new stdClass();
		$post_row->id_board			= $forumid;
		$post_row->id_topic 		= $topicid;
		$post_row->poster_time		= $timestamp;
		$post_row->id_member		= $userid;
		$post_row->subject			= $subject;
		$post_row->poster_name		= $smfUser->member_name;
		$post_row->poster_email		= $smfUser->email_address;
		$post_row->poster_ip			= $_SERVER["REMOTE_ADDR"];
		$post_row->smileys_enabled	= 1;
		$post_row->modified_time		= 0;
		$post_row->modified_name		= '';
		$post_row->body				= $text;
		$post_row->icon				= 'xx';

		if(!$jdb->insertObject('#__messages', $post_row, 'id_msg')) {
			$status['error'] = $jdb->stderr();
			return;
		}
		$postid = $jdb->insertid();

		$post_row = new stdClass();
		$post_row->id_msg = $postid;
		$post_row->id_msg_modified = $postid;
		if(!$jdb->updateObject('#__messages', $post_row, 'id_msg' )) {
			$status['error'] = $jdb->stderr();
		}

		$topic_row = new stdClass();

		$topic_row->id_first_msg	= $postid;
		$topic_row->id_last_msg		= $postid;
		$topic_row->id_topic 		= $topicid;
		if(!$jdb->updateObject('#__topics', $topic_row, 'id_topic' )) {
			$status['error'] = $jdb->stderr();
		}

		$forum_stats = new stdClass();
		$forum_stats->id_board =  $forumid;

		$query = "SELECT m.poster_time FROM #__messages AS m INNER JOIN #__boards AS b ON b.id_last_msg = m.id_msg WHERE b.id_board = $forumid";
		$jdb->setQuery($query);
		$lastPostTime = (int) $jdb->loadResult();
		if($dbparams->get('use_content_created_date',false)) {
			//only update the last post for the board if it really is newer
			$updateLastPost = ($timestamp > $lastPostTime) ? true : false;
		} else {
			$updateLastPost = true;
		}

		if($updateLastPost) {
			$forum_stats->id_last_msg =  $postid;
			$forum_stats->id_msg_updated =  $postid;
		}

		$query = "SELECT num_topics, num_posts FROM #__boards WHERE id_board = $forumid";
		$jdb->setQuery($query);
		$num = $jdb->loadObject();
		$forum_stats->num_posts =  $num->num_posts +1;
		$forum_stats->num_topics =  $num->num_topics +1;

		if(!$jdb->updateObject('#__boards', $forum_stats, 'id_board' )) {
			$status['error'] = $jdb->stderr();
		}
        if ($updateLastPost) {
            $query = "REPLACE INTO #__log_topics SET id_member = $userid, id_topic = $topicid, id_msg = " . ($postid + 1);
            $jdb->setQuery($query);
            if (!$jdb->query()) {
                $status['error'] = $jdb->stderr();
            }
            $query = "REPLACE INTO #__log_boards SET id_member = $userid, id_board = $forumid, id_msg = $postid";
            $jdb->setQuery($query);
            if (!$jdb->query()) {
                $status['error'] = $jdb->stderr();
            }
        }
		if(!empty($topicid) && !empty($postid)) {
			//add information to update forum lookup
			$status['threadinfo']->forumid = $forumid;
			$status['threadinfo']->threadid = $topicid;
			$status['threadinfo']->postid = $postid;
		}
	}

	 /**
     * Updates information in a specific thread/post
     * @param object with discussion bot parameters
     * @param object with existing thread info
     * @param object $contentitem object containing content information
     * @param array $status contains errors and status of actions
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

        $timestamp = time();
		$userid = $dbparams->get('default_user');

		$query = "SELECT member_name FROM #__members WHERE id_member = '$userid'";
		$jdb->setQuery($query);
		$smfUser = $jdb->loadObject();

		$post_row = new stdClass();
		$post_row->subject			= $subject;
		$post_row->body				= $text;
		$post_row->modified_time 	= $timestamp;
		$post_row->modified_name 	= $smfUser->member_name;
		$post_row->id_msg_modified	= $postid;
		$post_row->id_msg 			= $postid;
		if(!$jdb->updateObject('#__messages', $post_row, 'id_msg')) {
			$status['error'] = $jdb->stderr();
		}
	}

	/**
	 * Returns HTML of a quick reply
	 * @param $dbparams object with discussion bot parameters
	 * @param boolean $showGuestInputs toggles whether to show guest inputs or not
	 * @return string of html
	 */
	function createQuickReply(&$dbparams,$showGuestInputs)
	{
		$html = '';
		if($showGuestInputs) {
			$username = JRequest::getVar('guest_username','','post');
			$email = JRequest::getVar('guest_email','','post');

			$html .= "<table><tr><td>".JText::_('USERNAME') .":</td><td><input name='guest_username' value='$username' class='inputbox'/></td></tr>";
			$html .= "<tr><td>".JText::_('EMAIL') ."</td><td><input name='guest_email' value='$email' class='inputbox'/></td></tr>";
			$html .= $this->createCaptcha($dbparams);
			$html .= "</table><br />";
		}
		$quickReply = JRequest::getVar('quickReply','','post');
		$html .= "<textarea id='quickReply' name='quickReply' class='inputbox' rows='15' cols='100'>$quickReply</textarea><br />";
	   	return $html;
	}

	/**
	 * Creates a post from the quick reply
	 * @param object with discussion bot parameters
	 * @param $ids stdClass with thread id ($ids->threadid) and first post id ($ids->postid)
	 * @param $contentitem object of content item
	 * @param $userinfo object info of the forum user
	 * @return array with status
	 */
	function createPost(&$dbparams, &$ids, &$contentitem, &$userinfo)
	{
		$status = array();
		$status["error"] = false;

		if($userinfo->guest) {
			$userinfo->username = JRequest::getVar('guest_username', '', 'POST');
			$userinfo->email = JRequest::getVar('guest_email', '', 'POST');
			$userinfo->userid = 0;

			$pattern = "^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$";
			            if (empty($userinfo->username) || empty($userinfo->email) || !preg_match('/^[^@]+@[a-zA-Z0-9._-]+\.[a-zA-Z]+$/', $userinfo->email)) {
				$status['error'][] = JTEXT::_('GUEST_FIELDS_MISSING');
				return $status;
			} else {
				//check to see if user exists to prevent user hijacking
				$JFusionUser = JFusionFactory::getUser($this->getJname());
				define('OVERRIDE_IDENTIFIER',3);
				$existinguser = $JFusionUser->getUser($userinfo->username);
				if(!empty($existinguser)) {
					$status["error"][] = JText::_('USERNAME_IN_USE');
					return $status;
				}

				//check for email
				$existinguser = $JFusionUser->getUser($userinfo->email);
				if(!empty($existinguser)) {
					$status["error"][] = JText::_('EMAIL_IN_USE');
					return $status;
				}
			}
		}

		//setup some variables
		$userid = $userinfo->userid;
		$jdb =& JFusionFactory::getDatabase($this->getJname());
        $public =& JFusionFactory::getPublic($this->getJname());
		$text = JRequest::getVar('quickReply', false, 'POST');
		//strip out html from post
		$text = strip_tags($text);

		if(!empty($text)) {
			$public->prepareText($text);

			//get some topic information
			$where = "WHERE t.id_topic = {$ids->threadid} AND m.id_msg = t.id_first_msg";
			$query = "SELECT t.id_first_msg , t.num_replies, m.subject FROM `#__messages` as m INNER JOIN `#__topics` as t ON t.id_topic = m.id_topic $where";

			$jdb->setQuery($query);
			$topic = $jdb->loadObject();

			//the user information
			if($userinfo->guest) {
				$smfUser = new stdClass();
				$smfUser->member_name = $userinfo->username;
				$smfUser->email_address = $userinfo->email;
			} else {
				$query = "SELECT member_name,email_address FROM #__members WHERE id_member = '$userid'";
				$jdb->setQuery($query);
				$smfUser = $jdb->loadObject();
			}

            $timestamp = time();

			$post_approved = ($userinfo->guest && $dbparams->get('moderate_guests',1)) ? 0 : 1;

			$post_row = new stdClass();
			$post_row->id_board			= $ids->forumid;
			$post_row->id_topic 		= $ids->threadid;
			$post_row->poster_time		= $timestamp;
			$post_row->id_member		= $userid;
			$post_row->subject			= 'Re: '.$topic->subject;
			$post_row->poster_name		= $smfUser->member_name;
			$post_row->poster_email		= $smfUser->email_address;
			$post_row->poster_ip		= $_SERVER["REMOTE_ADDR"];
			$post_row->smileys_enabled	= 1;
			$post_row->modified_time	= 0;
			$post_row->modified_name	= '';
			$post_row->body				= $text;
			$post_row->icon				= 'xx';
			$post_row->approved 		= $post_approved;

			if(!$jdb->insertObject('#__messages', $post_row, 'id_msg')) {
				$status['error'] = $jdb->stderr();
				return $status;
			}
			$postid = $jdb->insertid();

			$post_row = new stdClass();
			$post_row->id_msg = $postid;
			$post_row->id_msg_modified = $postid;
			if(!$jdb->updateObject('#__messages', $post_row, 'id_msg' )) {
				$status['error'] = $jdb->stderr();
			}

			//store the postid
			$status['postid'] = $postid;

			//only update the counters if the post is approved
			if($post_approved) {
				$topic_row = new stdClass();
				$topic_row->id_last_msg			= $postid;
				$topic_row->id_member_updated	= (int) $userid;
				$topic_row->num_replies			= $topic->num_replies + 1;
				$topic_row->id_topic			= $ids->threadid;
				if(!$jdb->updateObject('#__topics', $topic_row, 'id_topic' )) {
					$status['error'] = $jdb->stderr();
				}

				$forum_stats = new stdClass();
				$forum_stats->id_last_msg 		=  $postid;
				$forum_stats->id_msg_updated	=  $postid;
				$query = "SELECT num_posts FROM #__boards WHERE id_board = {$ids->forumid}";
				$jdb->setQuery($query);
				$num = $jdb->loadObject();
				$forum_stats->num_posts = $num->num_posts + 1;
				$forum_stats->id_board 			= $ids->forumid;
				if(!$jdb->updateObject('#__boards', $forum_stats, 'id_board' )) {
					$status['error'] = $jdb->stderr();
				}

	            //update stats for threadmarking purposes
                $query = "REPLACE INTO #__log_topics SET id_member = $userid, id_topic = {$ids->threadid}, id_msg = " . ($postid + 1);
                $jdb->setQuery($query);
                if (!$jdb->query()) {
                    $status['error'] = $jdb->stderr();
                }
                $query = "REPLACE INTO #__log_boards SET id_member = $userid, id_board = {$ids->forumid}, id_msg = $postid";
                $jdb->setQuery($query);
                if (!$jdb->query()) {
                    $status['error'] = $jdb->stderr();
                }
			} else {
				//add the post to the approval queue
				$query = "INSERT INTO #__approval_queue id_msg VALUES ($postid)";
				$jdb->setQuery($query);
				$jdb->query();
			}

			//update moderation status to tell discussion bot to notify user
			$status['post_moderated'] = ($post_approved) ? 0 : 1;
		}

		return $status;
	}

	/**
     * Retrieves the posts to be displayed in the content item if enabled
     *
     * @param object &$dbparams with discussion bot parameters
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
		$where = "WHERE id_topic = {$threadid} AND id_msg != {$postid} AND approved = 1";
        $query = "(SELECT a.id_topic , a.id_msg, a.poster_name, b.real_name, a.id_member, 0 AS guest, a.subject, a.poster_time, a.body, a.poster_time AS order_by_date FROM `#__messages` as a INNER JOIN #__members as b ON a.id_member = b.id_member $where AND a.id_member != 0)";
        $query.= " UNION ";
        $query.= "(SELECT a.id_topic , a.id_msg, a.poster_name, a.poster_name as real_name, a.id_member, 1 AS guest, a.subject, a.poster_time, a.body, a.poster_time AS order_by_date FROM `#__messages` as a $where AND a.id_member = 0)";
        $query.= " ORDER BY order_by_date $sort";
		$jdb = & JFusionFactory::getDatabase($this->getJname());

		if($dbparams->get('enable_pagination',true)) {
			$application = JFactory::getApplication() ;
			$limitstart = JRequest::getInt( 'limitstart_discuss', 0 );
			$limit = $application->getUserStateFromRequest( 'global.list.limit', 'limit_discuss', 5, 'int' );
			$jdb->setQuery($query,$limitstart,$limit);
		} else {
			$limit_posts = $dbparams->get('limit_posts');
			$query .= empty($limit_posts) || trim($limit_posts)==0 ? "" :  " LIMIT 0,$limit_posts";
			$jdb->setQuery($query);
		}

		$posts = $jdb->loadObjectList();

		return $posts;
	}

	function getReplyCount(&$existingthread)
	{
		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT num_replies FROM #__topics WHERE id_topic = {$existingthread->threadid}";
		$db->setQuery($query);
		$result = $db->loadResult();
		return $result;
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
		$columns->userid = "id_member";
		$columns->username = "poster_name";
		$columns->name = "real_name";
		$columns->dateline = "poster_time";
		$columns->posttext = "body";
		$columns->posttitle = "subject";
		$columns->postid = "id_msg";
		$columns->threadid = "id_topic";
		$columns->guest = "guest";
		return $columns;
	}

    /**
     * @param int $threadid
     * @return object
     */
    function getThread($threadid)
    {
		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT id_topic AS threadid, id_board AS forumid, id_first_msg AS postid FROM #__topics WHERE id_topic = $threadid";
		$db->setQuery($query);
		$results = $db->loadObject();
		return $results;
    }

    function getThreadLockedStatus($threadid) {
        $db = & JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT locked FROM #__topics WHERE id_topic = $threadid";
        $db->setQuery($query);
        $locked = $db->loadResult();
        return $locked;
    }

    function getActivityQuery($usedforums, $result_order, $result_limit)
    {
        $db = & JFusionFactory::getDatabase($this->getJname());

		$userPlugin = & JFusionFactory::getUser($this->getJname());

		$user = JFactory::getUser();
		$userid = $user->get('id');

		if ($userid) {
			$userlookup = JFusionFunction::lookupUser($this->getJname(),$userid,true);
			$existinguser = $userPlugin->getUser($userlookup);
			$group_id = $existinguser->group_id;
		} else {
			$group_id = '-1';
		}

		$query = 'SELECT member_groups, id_board FROM #__boards';
		$db->setQuery($query);
        $boards = $db->loadObjectList();

		$list = array();
		foreach( $boards as $key => $value ) {
			$member_groups = explode( ',' , $value->member_groups );
			if ( in_array($group_id, $member_groups) || $group_id == 1) {
				$list[] =  $value->id_board;
			}
		}

        $where = (!empty($usedforums)) ? ' WHERE b.id_board IN (' . $usedforums .') AND a.id_board IN ('.implode(',',$list).')' : ' WHERE a.id_board IN ('.implode(',',$list).')';
		$end = $result_order." LIMIT 0,".$result_limit;

        $numargs = func_num_args();
        if ($numargs > 3) {
            $filters = func_get_args();
            $i = 3;
            for ($i = 3; $i < $numargs; $i++) {
                if ($filters[$i][0] == 'userid') {
                    $where.= ' HAVING userid = ' . $db->Quote($filters[$i][1]);
                }
            }
        }

        //setup the guest where clause to be used in union query
        $guest_where = (empty($where)) ? " WHERE b.id_member = 0" : " AND b.id_member = 0";

        $query = array(
        //LAT with first post info
        LAT . '0' =>
        "(SELECT a.id_topic AS threadid, a.id_last_msg AS postid, b.poster_name AS username, d.real_name AS name, b.id_member AS userid, b.subject AS subject, b.poster_time AS dateline, a.id_board as forumid, c.poster_time as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_first_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_last_msg = c.id_msg
                INNER JOIN `#__members`  as d ON b.id_member = d.id_member
                $where)
        UNION
            (SELECT a.id_topic AS threadid, a.id_last_msg AS postid, b.poster_name AS username, b.poster_name AS name, b.id_member AS userid, b.subject AS subject, b.poster_time AS dateline, a.id_board as forumid, c.poster_time as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_first_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_last_msg = c.id_msg
                $where $guest_where)
        ORDER BY last_post_date $end",
        //LAT with latest post info
        LAT . '1' =>
        "(SELECT a.id_topic AS threadid, a.id_last_msg AS postid, b.poster_name AS username, d.real_name as name, b.id_member AS userid, c.subject AS subject, b.poster_time AS dateline, a.id_board as forumid, b.poster_time as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_last_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_first_msg = c.id_msg
                INNER JOIN `#__members`  as d ON b.id_member = d.id_member
                $where)
        UNION
            (SELECT a.id_topic AS threadid, a.id_last_msg AS postid, b.poster_name AS username, b.poster_name as name, b.id_member AS userid, c.subject AS subject, b.poster_time AS dateline, a.id_board as forumid, b.poster_time as last_post_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_last_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_first_msg = c.id_msg
                $where $guest_where)
        ORDER BY last_post_date $end",
        //LCT
        LCT =>
        "(SELECT a.id_topic AS threadid, b.id_msg AS postid, b.poster_name AS username, d.real_name as name, b.id_member AS userid, b.subject AS subject, b.body, b.poster_time AS dateline, a.id_board as forumid, b.poster_time as topic_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_first_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_last_msg = c.id_msg
                INNER JOIN `#__members`  as d ON b.id_member = d.id_member
                $where)
       UNION
            (SELECT a.id_topic AS threadid, b.id_msg AS postid, b.poster_name AS username, b.poster_name as name, b.id_member AS userid, b.subject AS subject, b.body, b.poster_time AS dateline, a.id_board as forumid, b.poster_time as topic_date
            FROM `#__topics` as a
                INNER JOIN `#__messages` as b ON a.id_first_msg = b.id_msg
                INNER JOIN `#__messages` as c ON a.id_last_msg = c.id_msg
                $where $guest_where)
        ORDER BY topic_date $end",
        //LCP
        LCP => "
        (SELECT b.id_topic AS threadid, b.id_msg AS postid, b.poster_name AS username, d.real_name as name, b.id_member AS userid, b.subject AS subject, b.body, b.poster_time AS dateline, b.id_board as forumid, b.poster_time as last_post_date
            FROM `#__messages` as b
                INNER JOIN `#__members` as d ON b.id_member = d.id_member
                INNER JOIN `#__topics` as a ON b.id_topic = a.id_topic
                $where)
        UNION
            (SELECT b.id_topic AS threadid, b.id_msg AS postid, b.poster_name AS username, b.poster_name as name, b.id_member AS userid, b.subject AS subject, b.body, b.poster_time AS dateline, b.id_board as forumid, b.poster_time as last_post_date
            FROM `#__messages` as b
            	INNER JOIN `#__topics` as a ON b.id_topic = a.id_topic
                $where $guest_where)
        ORDER BY last_post_date $end");

        return $query;
    }

	/**
	 * Filter forums from a set of results sent in / useful if the plugin needs to restrict the forums visible to a user
	 * @param $results set of results from query
	 * @param $limit int limit results parameter as set in the module's params; used for plugins that cannot limit using a query limiter
	 */
	function filterActivityResults(&$results, $limit=0)
	{
		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT value FROM #__settings WHERE variable='censor_vulgar'";
		$db->setQuery($query);
		$vulgar = $db->loadResult();

		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT value FROM #__settings WHERE variable='censor_proper'";
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
	}
}
