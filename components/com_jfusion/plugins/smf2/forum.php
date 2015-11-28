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
 * For detailed descriptions on these functions please check JFusionForum
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
        return  'index.php?topic=' . $threadid . '.msg' . $postid . '#msg' . $postid;
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
     * @param int|string $userid
     *
     * @return string
     */
    function getProfileURL($userid)
    {
        return  'index.php?action=profile&u=' . $userid;
    }

    /**
     * @return string
     */
    function getPrivateMessageURL()
    {
        return 'index.php?action=pm';
    }

    /**
     * @return string
     */
    function getViewNewMessagesURL()
    {
        return 'index.php?action=unread';
    }

    /**
     * @return array
     */
    function getForumList()
    {
	    try {
		    // initialise some objects
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('id_board as id, name')
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
     * @param object $post
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
			                ->select('id_msg, id_topic')
			                ->from('#__log_topics')
			                ->where('id_member = ' . $userlookup->userid);

	                    $db->setQuery($query);
	                    $markread['topic'] = $db->loadObjectList('id_topic');

		                $query = $db->getQuery(true)
			                ->select('id_msg, id_board')
			                ->from('#__log_mark_read')
			                ->where('id_member = ' . $userlookup->userid);

	                    $db->setQuery($query);
	                    $markread['mark_read'] = $db->loadObjectList('id_board');

		                $query = $db->getQuery(true)
			                ->select('id_msg, id_board')
			                ->from('#__log_boards')
			                ->where('id_member = ' . $userlookup->userid);

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
	        }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
        return $newstatus;
    }

    /**
     * @param int $userid
     * @return array
     */
    function getPrivateMessageCounts($userid)
    {
        try {
	        if ($userid) {
		        // initialise some objects
		        $db = JFusionFactory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->select('unread_messages')
			        ->from('#__members')
			        ->where('id_member = ' . $userid);

		        // read unread count
		        $db->setQuery($query);
		        $unreadCount = $db->loadResult();

		        // read total pm count
		        $query = $db->getQuery(true)
			        ->select('instant_messages')
			        ->from('#__members')
			        ->where('id_member = ' . $userid);

		        $db->setQuery($query);
		        $totalCount = $db->loadResult();

		        return array('unread' => $unreadCount, 'total' => $totalCount);
	        }
        } catch (Exception $e) {
	        JFusionFunction::raiseError($e, $this->getJname());
        }
        return array('unread' => 0, 'total' => 0);
    }

    /**
     * @param int $puser_id
     * @return bool|string
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
			        ->where('id_member = ' . $db->quote($puser_id));

			    $db->setQuery($query);
			    $db->execute();
			    $result = $db->loadObject();

			    if (!empty($result)) {
				    $url = '';
				    // SMF has a wired way of holding attachments. Get instance of the attachments table
				    $query = $db->getQuery(true)
					    ->select('*')
					    ->from('#__attachments')
					    ->where('id_member = ' . $db->quote($puser_id));

				    $db->setQuery($query);
				    $db->execute();
				    $attachment = $db->loadObject();
				    // See if the user has a specific attachment meant for an avatar
				    if(!empty($attachment) && $attachment->id_thumb == 0 && $attachment->id_msg == 0 && empty($result->avatar)) {
					    $url = $this->params->get('source_url') . 'index.php?action=dlattach;attach=' . $attachment->id_attach . ';type=avatar';
					    // If user didn't, check to see if the avatar specified in the first query is a url. If so use it.
				    } else if(preg_match('/http(s?):\/\//', $result->avatar)) {
					    $url = $result->avatar;
				    } else if($result->avatar) {
					    // If the avatar specified in the first query is not a url but is a file name. Make it one
					    $query = $db->getQuery(true)
						    ->select('*')
						    ->from('#__settings')
						    ->where('variable = ' . $db->quote('avatar_url'));

					    $db->setQuery($query);
					    $avatarurl = $db->loadObject();
					    // Check for trailing slash. If there is one DON'T ADD ONE!
					    if(substr($avatarurl->value, -1) == DIRECTORY_SEPARATOR) {
						    $url = $avatarurl->value . $result->avatar;
						    // I like redundancy. Recheck to see if there isn't a trailing slash. If there isn't one, add one.
					    } else if(substr($avatarurl->value, -1) !== DIRECTORY_SEPARATOR) {
						    $url = $avatarurl->value . '/' . $result->avatar;
					    }
				    }
				    return $url;
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
      * @param JRegistry &$dbparams with discussion bot parameters
      * @param object &$contentitem object containing content information
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
				->select('member_name, email_address')
				->from('#__members')
				->where('id_member = ' . $db->quote($userid));

			$db->setQuery($query);
			$smfUser = $db->loadObject();

			if ($dbparams->get('use_content_created_date', false)) {
				$timezone = JFusionFactory::getConfig()->get('offset');
				$timestamp = strtotime($contentitem->created);
				//undo Joomla timezone offset
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

			$db->insertObject('#__topics', $topic_row, 'id_topic');
			$topicid = $db->insertid();

			$post_row = new stdClass();
			$post_row->id_board			= $forumid;
			$post_row->id_topic 		= $topicid;
			$post_row->poster_time		= $timestamp;
			$post_row->id_member		= $userid;
			$post_row->subject			= $subject;
			$post_row->poster_name		= $smfUser->member_name;
			$post_row->poster_email		= $smfUser->email_address;
			$post_row->poster_ip			= $_SERVER['REMOTE_ADDR'];
			$post_row->smileys_enabled	= 1;
			$post_row->modified_time		= 0;
			$post_row->modified_name		= '';
			$post_row->body				= $text;
			$post_row->icon				= 'xx';

			$db->insertObject('#__messages', $post_row, 'id_msg');

			$postid = $db->insertid();

			$post_row = new stdClass();
			$post_row->id_msg = $postid;
			$post_row->id_msg_modified = $postid;

			$db->updateObject('#__messages', $post_row, 'id_msg');

			$topic_row = new stdClass();

			$topic_row->id_first_msg	= $postid;
			$topic_row->id_last_msg		= $postid;
			$topic_row->id_topic 		= $topicid;

			$db->updateObject('#__topics', $topic_row, 'id_topic');

			$forum_stats = new stdClass();
			$forum_stats->id_board =  $forumid;

			$query = $db->getQuery(true)
				->select('m.poster_time')
				->from('#__messages AS m')
				->innerJoin('#__boards AS b ON b.id_last_msg = m.id_msg')
				->where('b.id_board = ' . $db->quote($forumid));

			$db->setQuery($query);
			$lastPostTime = (int) $db->loadResult();
			if($dbparams->get('use_content_created_date', false)) {
				//only update the last post for the board if it really is newer
				$updateLastPost = ($timestamp > $lastPostTime) ? true : false;
			} else {
				$updateLastPost = true;
			}

			if($updateLastPost) {
				$forum_stats->id_last_msg =  $postid;
				$forum_stats->id_msg_updated =  $postid;
			}

			$query = $db->getQuery(true)
				->select('num_topics, num_posts')
				->from('#__boards')
				->where('id_board = ' . $forumid);

			$db->setQuery($query);
			$num = $db->loadObject();
			$forum_stats->num_posts =  $num->num_posts +1;
			$forum_stats->num_topics =  $num->num_topics +1;

			$db->updateObject('#__boards', $forum_stats, 'id_board');

			if ($updateLastPost) {
				$query = 'REPLACE INTO #__log_topics SET id_member = ' . $userid . ', id_topic = ' . $topicid . ', id_msg = ' . ($postid + 1);
				$db->setQuery($query);
				$db->execute();

				$query = 'REPLACE INTO #__log_boards SET id_member = ' . $userid . ', id_board = ' . $forumid . ', id_msg = ' . $postid;
				$db->setQuery($query);
				$db->execute();
			}
			if(!empty($topicid) && !empty($postid)) {
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
	  * @param JRegistry &$dbparams with discussion bot parameters
	  * @param object &$existingthread with existing thread info
	  * @param object &$contentitem object containing content information
	  * @param array &$status contains errors and status of actions
	  *
	  * @return void
     **/
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
			$userid = $dbparams->get('default_userid');

			if ($userid) {
				$query = $db->getQuery(true)
					->select('member_name')
					->from('#__members')
					->where('id_member = ' . $db->quote($userid));

				$db->setQuery($query);
				$smfUser = $db->loadObject();

				$post_row = new stdClass();
				$post_row->subject			= $subject;
				$post_row->body				= $text;
				$post_row->modified_time 	= $timestamp;
				$post_row->modified_name 	= $smfUser->member_name;
				$post_row->id_msg_modified	= $postid;
				$post_row->id_msg 			= $postid;
				$db->updateObject('#__messages', $post_row, 'id_msg');
			} else {
				throw new RuntimeException('NO_DEFAULT_USER');
			}
		} catch (Exception $e) {
			$status['error'] = $e->getMessage();
		}
	}

	/**
	 * Returns HTML of a quick reply
	 * @param JRegistry $dbparams object with discussion bot parameters
	 * @param boolean $showGuestInputs toggles whether to show guest inputs or not
	 * @return string of html
	 */
	function createQuickReply(&$dbparams, $showGuestInputs)
	{
        $html = '';
		$mainframe = JFusionFactory::getApplication();
        if ($showGuestInputs) {
            $username = $mainframe->input->post->get('guest_username', '');
            $email = $mainframe->input->post->get('guest_email', '');

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
	 * @param object $userinfo    object info of the forum user
	 * @param stdClass $postinfo object with post info
	 *
	 * @return array with status
	 */
	function createPost($params, $ids, $contentitem, $userinfo, $postinfo)
	{
        $status = array('error' => array(), 'debug' => array());
		try {
			if($userinfo->guest) {
				$userinfo->username = $postinfo->username;
				$userinfo->email = $postinfo->email;
				$userinfo->userid = 0;
				if (empty($userinfo->username) || empty($userinfo->email) || !preg_match('/^[^@]+@[a-zA-Z0-9._-]+\.[a-zA-Z]+$/', $userinfo->email)) {
					throw new RuntimeException(JText::_('GUEST_FIELDS_MISSING'));
				} else {
					//check to see if user exists to prevent user hijacking
					$JFusionUser = JFusionFactory::getUser($this->getJname());
					define('OVERRIDE_IDENTIFIER', 3);
					$existinguser = $JFusionUser->getUser($userinfo->username);
					if(!empty($existinguser)) {
						throw new RuntimeException(JText::_('USERNAME_IN_USE'));
					}

					//check for email
					$existinguser = $JFusionUser->getUser($userinfo->email);
					if(!empty($existinguser)) {
						throw new RuntimeException(JText::_('EMAIL_IN_USE'));
					}
				}
			}

			//setup some variables
			$userid = $userinfo->userid;
			$db = JFusionFactory::getDatabase($this->getJname());
	        $public = JFusionFactory::getPublic($this->getJname());
			//strip out html from post
			$text = strip_tags($postinfo->text);

			if(!empty($text)) {
				$public->prepareText($text);

				//get some topic information
				$query = $db->getQuery(true)
					->select('t.id_first_msg , t.num_replies, m.subject')
					->from('#__messages as m')
					->innerJoin('#__topics as t ON t.id_topic = m.id_topic')
					->where('t.id_topic = ' . $db->quote($ids->threadid))
					->where('m.id_msg = t.id_first_msg');

				$db->setQuery($query);
				$topic = $db->loadObject();

				//the user information
				if($userinfo->guest) {
					$smfUser = new stdClass();
					$smfUser->member_name = $userinfo->username;
					$smfUser->email_address = $userinfo->email;
				} else {
					$query = $db->getQuery(true)
						->select('member_name, email_address')
						->from('#__members')
						->where('id_member = ' . $db->quote($userid));

					$db->setQuery($query);
					$smfUser = $db->loadObject();
				}

	            $timestamp = time();

				$post_approved = ($userinfo->guest && $params->get('moderate_guests', 1)) ? 0 : 1;

				$post_row = new stdClass();
				$post_row->id_board			= $ids->forumid;
				$post_row->id_topic 		= $ids->threadid;
				$post_row->poster_time		= $timestamp;
				$post_row->id_member		= $userid;
				$post_row->subject			= 'Re: ' . $topic->subject;
				$post_row->poster_name		= $smfUser->member_name;
				$post_row->poster_email		= $smfUser->email_address;
				$post_row->poster_ip		= $_SERVER["REMOTE_ADDR"];
				$post_row->smileys_enabled	= 1;
				$post_row->modified_time	= 0;
				$post_row->modified_name	= '';
				$post_row->body				= $text;
				$post_row->icon				= 'xx';
				$post_row->approved 		= $post_approved;

				$db->insertObject('#__messages', $post_row, 'id_msg');

				$postid = $db->insertid();

				$post_row = new stdClass();
				$post_row->id_msg = $postid;
				$post_row->id_msg_modified = $postid;
				$db->updateObject('#__messages', $post_row, 'id_msg');

				//store the postid
				$status['postid'] = $postid;

				//only update the counters if the post is approved
				if($post_approved) {
					$topic_row = new stdClass();
					$topic_row->id_last_msg			= $postid;
					$topic_row->id_member_updated	= (int) $userid;
					$topic_row->num_replies			= $topic->num_replies + 1;
					$topic_row->id_topic			= $ids->threadid;
					$db->updateObject('#__topics', $topic_row, 'id_topic');

					$forum_stats = new stdClass();
					$forum_stats->id_last_msg 		=  $postid;
					$forum_stats->id_msg_updated	=  $postid;

					$query = $db->getQuery(true)
						->select('num_posts')
						->from('#__boards')
						->where('id_board = ' . $db->quote($ids->forumid));

					$db->setQuery($query);
					$num = $db->loadObject();
					$forum_stats->num_posts = $num->num_posts + 1;
					$forum_stats->id_board 			= $ids->forumid;
					$db->updateObject('#__boards', $forum_stats, 'id_board');

		            //update stats for threadmarking purposes
	                $query = 'REPLACE INTO #__log_topics SET id_member = ' . $userid . ', id_topic = ' . $ids->threadid . ', id_msg = ' . ($postid + 1);
					$db->setQuery($query);
					$db->execute();

	                $query = 'REPLACE INTO #__log_boards SET id_member = ' . $userid . ', id_board = ' . $ids->forumid . ', id_msg = ' . $postid;
					$db->setQuery($query);
					$db->execute();
				} else {
					//add the post to the approval queue
					$approval_queue = new stdClass;
					$approval_queue->id_msg = $postid;

					$db->insertObject('#__approval_queue', $approval_queue);
				}

				//update moderation status to tell discussion bot to notify user
				$status['post_moderated'] = ($post_approved) ? 0 : 1;
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
			$where = 'WHERE id_topic = ' . $existingthread->threadid . ' AND id_msg != ' . $existingthread->postid . ' AND approved = 1';
	        $query = '(SELECT a.id_topic , a.id_msg, a.poster_name, b.real_name, a.id_member, 0 AS guest, a.subject, a.poster_time, a.body, a.poster_time AS order_by_date FROM `#__messages` as a INNER JOIN #__members as b ON a.id_member = b.id_member ' . $where . ' AND a.id_member != 0)';
	        $query.= ' UNION ';
	        $query.= '(SELECT a.id_topic , a.id_msg, a.poster_name, a.poster_name as real_name, a.id_member, 1 AS guest, a.subject, a.poster_time, a.body, a.poster_time AS order_by_date FROM `#__messages` as a ' . $where . ' AND a.id_member = 0)';
	        $query.= ' ORDER BY order_by_date ' . $sort;
			$db = JFusionFactory::getDatabase($this->getJname());

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
     * @return int
     */
    function getReplyCount($existingthread)
	{
		try {
			$db = JFusionFactory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('num_replies')
				->from('#__topics')
				->where('id_topic = ' . $db->quote($existingthread->threadid));

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
	 * @return object with column names
	 */
	function getDiscussionColumns()
	{
		$columns = new stdClass();
		$columns->userid = 'id_member';
		$columns->username = 'poster_name';
		$columns->name = 'real_name';
		$columns->dateline = 'poster_time';
		$columns->posttext = 'body';
		$columns->posttitle = 'subject';
		$columns->postid = 'id_msg';
		$columns->threadid = 'id_topic';
		$columns->guest = 'guest';
		return $columns;
	}

    /**
     * @param int $threadid
     * @return object
     */
    function getThread($threadid)
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('id_topic AS threadid, id_board AS forumid, id_first_msg AS postid')
			    ->from('#__topics')
			    ->where('id_topic = ' . $threadid);

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
			    ->where('id_topic = ' . $threadid);

		    $db->setQuery($query);
		    $locked = $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $locked = true;
	    }
	    return $locked;
    }

    /**
     * @param array $usedforums
     * @param string $result_order
     * @param int $result_limit
     * @return array|string
     */
    function getActivityQuery($usedforums, $result_order, $result_limit)
    {
        $db = JFusionFactory::getDatabase($this->getJname());

		$userPlugin = JFusionFactory::getUser($this->getJname());

		$user = JFactory::getUser();
		$userid = $user->get('id');

		if ($userid) {
			$userlookup = JFusionFunction::lookupUser($this->getJname(), $userid, true);
			$existinguser = $userPlugin->getUser($userlookup);
			$group_id = $existinguser->group_id;
		} else {
			$group_id = '-1';
		}

	    $query = $db->getQuery(true)
		    ->select('member_groups, id_board')
		    ->from('#__boards');

		$db->setQuery($query);
        $boards = $db->loadObjectList();

		$list = array();
		foreach($boards as $value) {
			$member_groups = explode(',', $value->member_groups);
			if ( in_array($group_id, $member_groups) || $group_id == 1) {
				$list[] =  $value->id_board;
			}
		}

        $where = (!empty($usedforums)) ? ' WHERE b.id_board IN (' . $usedforums . ') AND a.id_board IN (' . implode(',', $list) . ')' : ' WHERE a.id_board IN (' . implode(',', $list) . ' )';
		$end = $result_order . ' LIMIT 0,' . $result_limit;

        $numargs = func_num_args();
        if ($numargs > 3) {
            $filters = func_get_args();
            for ($i = 3; $i < $numargs; $i++) {
                if ($filters[$i][0] == 'userid') {
                    $where .= ' HAVING userid = ' . $db->quote($filters[$i][1]);
                }
            }
        }

        //setup the guest where clause to be used in union query
        $guest_where = (empty($where)) ? ' WHERE b.id_member = 0' : ' AND b.id_member = 0';

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
	 * @param array $results set of results from query
	 * @param int $limit limit results parameter as set in the module's params; used for plugins that cannot limit using a query limiter
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
			JFusionFunction::raiseError($e, $this->getJname());
		}
	}
}
