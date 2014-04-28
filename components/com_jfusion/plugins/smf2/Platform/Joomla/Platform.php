<?php namespace JFusion\Plugins\smf2\Platform\Joomla;

/**
* @package JFusion_SMF
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
use Exception;
use JFactory;
use JFile;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Platform\Joomla;
use JRegistry;
use RuntimeException;
use stdClass;

defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Forum Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractforum.php
 * @package JFusion_SMF
 */

class Platform extends Joomla
{
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
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('id_board as id, name')
			    ->from('#__boards');

		    $db->setQuery($query);

		    //getting the results
		    return $db->loadObjectList('id');
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
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
	                $db = Factory::getDatabase($this->getJname());

		            $userlookup = new Userinfo('joomla_int');
		            $userlookup->userid = $JUser->get('id');

		            $PluginUser = Factory::getUser($this->getJname());
		            $userlookup = $PluginUser->lookupUser($userlookup);
	                if ($userlookup) {
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
		    Framework::raiseError($e, $this->getJname());
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
		        $db = Factory::getDatabase($this->getJname());

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
	        Framework::raiseError($e, $this->getJname());
        }
        return array('unread' => 0, 'total' => 0);
    }

    /**
     * @param int $userid
     * @return bool|string
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
			        ->where('id_member = ' . $userid);

			    $db->setQuery($query);
			    $db->execute();
			    $result = $db->loadObject();

			    if (!empty($result)) {
				    $url = '';
				    // SMF has a wired way of holding attachments. Get instance of the attachments table
				    $query = $db->getQuery(true)
					    ->select('*')
					    ->from('#__attachments')
					    ->where('id_member = ' . $userid);

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
		    Framework::raiseError($e, $this->getJname());
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
			$db = Factory::getDatabase($this->getJname());
			$subject = trim(strip_tags($contentitem->title));

			//prepare the content body
			$text = $this->prepareFirstPostBody($dbparams, $contentitem);

			//the user information
			$query = $db->getQuery(true)
				->select('member_name, email_address')
				->from('#__members')
				->where('id_member = ' . $userid);

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

			$topic_row->is_sticky = 0;
			$topic_row->id_board = $forumid;
			$topic_row->id_first_msg = $topic_row->id_last_msg = 0;
			$topic_row->id_member_started = $topic_row->id_member_updated =  $userid;
			$topic_row->id_poll = 0;
			$topic_row->num_replies = 0;
			$topic_row->num_views = 0;
			$topic_row->locked = 0;

			$db->insertObject('#__topics', $topic_row, 'id_topic' );
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

			$db->updateObject('#__messages', $post_row, 'id_msg' );

			$topic_row = new stdClass();

			$topic_row->id_first_msg	= $postid;
			$topic_row->id_last_msg		= $postid;
			$topic_row->id_topic 		= $topicid;

			$db->updateObject('#__topics', $topic_row, 'id_topic' );

			$forum_stats = new stdClass();
			$forum_stats->id_board =  $forumid;

			$query = $db->getQuery(true)
				->select('m.poster_time')
				->from('#__messages AS m')
				->innerJoin('#__boards AS b ON b.id_last_msg = m.id_msg')
				->where('b.id_board = ' . $forumid);

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

			$db->updateObject('#__boards', $forum_stats, 'id_board' );

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
			$db = Factory::getDatabase($this->getJname());
			$subject = trim(strip_tags($contentitem->title));

			//prepare the content body
			$text = $this->prepareFirstPostBody($dbparams, $contentitem);

	        $timestamp = time();
			$userid = $dbparams->get('default_user');

			$query = $db->getQuery(true)
				->select('member_name')
				->from('#__members')
				->where('id_member = ' . $userid);

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
			if($userinfo->guest) {
				$userinfo->username = $postinfo->username;
				$userinfo->email = $postinfo->email;
				$userinfo->userid = null;
				if (empty($userinfo->username) || empty($userinfo->email) || !preg_match('/^[^@]+@[a-zA-Z0-9._-]+\.[a-zA-Z]+$/', $userinfo->email)) {
					throw new RuntimeException(Text::_('GUEST_FIELDS_MISSING'));
				} else {
					//check to see if user exists to prevent user hijacking
					$JFusionUser = Factory::getUser($this->getJname());
					$existinguser = $JFusionUser->getUser($userinfo);
					if(!empty($existinguser)) {
						throw new RuntimeException(Text::_('USERNAME_IN_USE'));
					}

					//check for email
					$existinguser = $JFusionUser->getUser($userinfo->email);
					if(!empty($existinguser)) {
						throw new RuntimeException(Text::_('EMAIL_IN_USE'));
					}
				}
			}

			//setup some variables
			$userid = $userinfo->userid;
			$db = Factory::getDatabase($this->getJname());
			$front = Factory::getFront($this->getJname());
			//strip out html from post
			$text = strip_tags($postinfo->text);

			if(!empty($text)) {
				$this->prepareText($text, 'forum', new JRegistry());

				//get some topic information
				$query = $db->getQuery(true)
					->select('t.id_first_msg , t.num_replies, m.subject')
					->from('#__messages')
					->innerJoin('#__topics as t ON t.id_topic = m.id_topic')
					->where('id_topic = ' . $ids->threadid)
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
						->where('id_member = ' . $userid);

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
				$db->updateObject('#__messages', $post_row, 'id_msg' );

				//store the postid
				$status['postid'] = $postid;

				//only update the counters if the post is approved
				if($post_approved) {
					$topic_row = new stdClass();
					$topic_row->id_last_msg			= $postid;
					$topic_row->id_member_updated	= (int) $userid;
					$topic_row->num_replies			= $topic->num_replies + 1;
					$topic_row->id_topic			= $ids->threadid;
					$db->updateObject('#__topics', $topic_row, 'id_topic' );

					$forum_stats = new stdClass();
					$forum_stats->id_last_msg 		=  $postid;
					$forum_stats->id_msg_updated	=  $postid;

					$query = $db->getQuery(true)
						->select('num_posts')
						->from('#__boards')
						->where('id_member = ' . $ids->forumid);

					$db->setQuery($query);
					$num = $db->loadObject();
					$forum_stats->num_posts = $num->num_posts + 1;
					$forum_stats->id_board 			= $ids->forumid;
					$db->updateObject('#__boards', $forum_stats, 'id_board' );

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
			$db = Factory::getDatabase($this->getJname());

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
     * @return int
     */
    function getReplyCount($existingthread)
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('num_posts')
				->from('#__topics')
				->where('id_topic = ' . $existingthread->threadid);

			$db->setQuery($query);
			$result = $db->loadResult();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
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
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('id_topic AS threadid, id_board AS forumid, id_first_msg AS postid')
			    ->from('#__topics')
			    ->where('id_topic = ' . $threadid);

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
     * @return bool
     */
    function getThreadLockedStatus($threadid) {
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('locked')
			    ->from('#__topics')
			    ->where('id_topic = ' . $threadid);

		    $db->setQuery($query);
		    $locked = $db->loadResult();
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
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
        $db = Factory::getDatabase($this->getJname());

		$userPlugin = Factory::getUser($this->getJname());

		$user = JFactory::getUser();
		$userid = $user->get('id');

		if ($userid) {
			$userlookup = new Userinfo('joomla_int');
			$userlookup->userid = $userid;

			$userlookup = $userPlugin->lookupUser($userlookup);
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
			Framework::raiseError($e, $this->getJname());
		}
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
	 **/
	function getOnlineUserQuery($usergroups = array())
	{
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->select('DISTINCT u.id_member AS userid, u.member_name AS username, u.real_name AS name, u.email_address as email')
			->from('#__members AS u')
			->innerJoin('#__log_online AS s ON u.id_member = s.id_member')
			->where('s.id_member != 0');

		if(!empty($usergroups)) {
			if(is_array($usergroups)) {
				$usergroups_string = implode(',', $usergroups);
				$usergroup_query = '(u.id_group IN (' . $usergroups_string . ') OR u.id_post_group IN (' . $usergroups_string . ')';
				foreach($usergroups AS $usergroup) {
					$usergroup_query .= ' OR FIND_IN_SET(' . intval($usergroup) . ', u.additional_groups)';
				}
				$usergroup_query .= ')';
			} else {
				$usergroup_query = '(u.id_group = ' . $usergroups . ' OR u.id_post_group = ' . $usergroups . ' OR FIND_IN_SET(' . $usergroups . ', u.additional_groups))';
			}
			$query->where($usergroup_query);
		}

		$query = (string)$query;
		return $query;
	}

	/**
	 * Returns number of guests
	 * @return int
	 */
	function getNumberOnlineGuests()
	{
		try {
			$db = Factory::getDatabase($this->getJname());

			$query = $db->getQuery(true)
				->select('COUNT(DISTINCT(ip))')
				->from('#__log_online')
				->where('id_member = 0');

			$db->setQuery($query);
			return $db->loadResult();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
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
				->select('COUNT(DISTINCT(l.ip))')
				->from('#__log_online AS l')
				->join('', '#__members AS u ON l.id_member = u.id_member')
				->where('l.id_member != 0');

			$db->setQuery($query);
			return $db->loadResult();
		} catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
			return 0 ;
		}
	}

	/**
	 * Prepares text for various areas
	 *
	 * @param string  &$text             Text to be modified
	 * @param string  $for              (optional) Determines how the text should be prepared.
	 *                                  Options for $for as passed in by JFusion's plugins and modules are:
	 *                                  joomla (to be displayed in an article; used by discussion bot)
	 *                                  forum (to be published in a thread or post; used by discussion bot)
	 *                                  activity (displayed in activity module; used by the activity module)
	 *                                  search (displayed as search results; used by search plugin)
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
					Framework::raiseError($e, $this->getJname());
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
	 * @param string $url
	 * @param int $itemid
	 *
	 * @return string
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
		$redirect_code .= '
if(!defined(\'_JEXEC\') && strpos($_SERVER[\'QUERY_STRING\'], \'dlattach\') === false && strpos($_SERVER[\'QUERY_STRING\'], \'verificationcode\') === false)';

		$redirect_code .= '
{
	$pattern = \'#action=(login|logout)#\';
	if (!preg_match($pattern , $_SERVER[\'QUERY_STRING\'])) {
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
					Framework::raiseWarning(Text::_('MISSING') . ' Joomla URL', $this->getJname(), $this->getJname());
				} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
					Framework::raiseWarning(Text::_('MISSING') . ' ItemID', $this->getJname(), $this->getJname());
				} else if (!$this->isValidItemID($joomla_itemid)) {
					Framework::raiseWarning(Text::_('MISSING') . ' ItemID ' . Text::_('MUST BE') . ' ' . $this->getJname(), $this->getJname(), $this->getJname());
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
			if(empty($matches[1][0])){
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
}
