<?php

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
class JFusionForum_vbulletin extends JFusionForum
{
    var $params;
    /**
     * @var $helper JFusionHelper_vbulletin
     */
    var $helper;

    /**
     *
     */
    function __construct()
    {
        //get the params object
        $this->params = JFusionFactory::getParams($this->getJname());
        //get the helper object
        $this->helper = JFusionFactory::getHelper($this->getJname());
    }

    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'vbulletin';
    }

    /**
     * @param int $forumid
     * @param int $threadid
     * @return string
     */
    function getReplyURL($forumid, $threadid)
    {
        return "newreply.php?do=newreply&t=$threadid&noquote=1";
    }

    /**
     * @param int $threadid
     * @return object
     */
    function getThread($threadid)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT threadid, forumid, firstpostid AS postid FROM #__thread WHERE threadid = $threadid";
        $db->setQuery($query);
        $results = $db->loadObject();
        return $results;
    }

    /**
     * @param int $threadid
     * @return bool
     */
    function getThreadLockedStatus($threadid)
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT open FROM #__thread WHERE threadid = $threadid";
        $db->setQuery($query);
        $open = $db->loadResult();
        $locked = ($open) ? false : true;
        return $locked;
    }

    /**
     * @param JParameter &$dbparams
     * @param object &$contentitem
     * @param int $forumid
     * @param array &$status
     */
    function createThread(&$dbparams, &$contentitem, $forumid, &$status)
    {
        $userid = $this->getThreadAuthor($dbparams, $contentitem);

        //strip title of all html characters and convert entities back to applicable characaters (to prevent double encoding by vB)
        $title = trim(strip_tags(html_entity_decode($contentitem->title)));

        $useContentDate = $dbparams->get('use_content_created_date', false);
        if ($useContentDate) {
            $mainframe = JFactory::getApplication();
            $timezone = $mainframe->getCfg('offset');
            $timestamp = strtotime($contentitem->created);
            //undo Joomla's timezone offset
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

        if (empty($response['errors'])) {
            $threadid = $response['new_id'];
            $postid = $response['firstpostid'];

            //if using the content date, manually update the forum's stats
            if ($useContentDate) {
                $jdb =& JFusionFactory::getDatabase($this->getJname());
                $user = JFusionFactory::getUser($this->getJname());
                $userinfo = $user->getUser($userid, 'userid');

                $query = "UPDATE #__forum SET ";
                //update counters
                $query.= 'threadcount = threadcount + 1';
                $query.= ', replycount = replycount + 1';
                //is this really the forum's latest thread?
                // TODO: $foruminfo undefined ... if ($timestamp > $foruminfo['lastpost']) { not sure what to replace it with
                if ($timestamp > 0) {
                    $query.= ", lastpost = $timestamp";
                    $query.= ", lastpostid = $postid";
                    $query.= ", lastthreadid = $threadid";
                    $query.= ", lastposter = " . $jdb->Quote($userinfo->username);
                    $query.= ", lastthread = " . $jdb->Quote($title);
                    $query.= ", lasticonid = 0";
                }
                $query.= " WHERE forumid = $forumid";
                $jdb->setQuery($query);
                if (!$jdb->query()) {
                    $status['error'][] = $jdb->stderr();
                }
            }

			//add information to update forum lookup
			$status['threadinfo']->forumid = $forumid;
			$status['threadinfo']->threadid = $threadid;
			$status['threadinfo']->postid = $postid;
		} else {
            foreach ($response['errors'] as $error) {
                $status['error'][] = $error;
            }
		}

        if (!empty($response['debug'])) {
		    $status['debug']['api_call'] = $response['debug'];
		}
    }

    /**
     * @param JParameter $dbparams
     * @param object $ids
     * @param object $contentitem
     * @param object $userinfo
     * @return array
     */
    function createPost(&$dbparams, &$ids, &$contentitem, &$userinfo)
    {
        $status = array('error' => array(),'debug' => array());
        if ($userinfo->guest) {
            $userinfo->username = JRequest::getVar('guest_username', '', 'POST');
            $userinfo->userid = 0;
            if (empty($userinfo->username)) {
                $status['error'][] = JTEXT::_('GUEST_FIELDS_MISSING');
                return $status;
            } else {
                $db = JFusionFactory::getDatabase($this->getJname());
				$query = "SELECT COUNT(*) FROM #__user "
						. " WHERE LOWER(username) = " . strtolower($db->Quote($userinfo->username))
						. " OR LOWER(email) = " . strtolower($db->Quote($userinfo->username));
                $db->setQuery($query);
                $result = $db->loadResult();
                if (!empty($result)) {
                    $status['error'][] = JText::_('USERNAME_IN_USE');
                    return $status;
                }

                $name_field = $this->params->get('name_field');
                if (!empty($name_field)) {
                    $query = "SELECT COUNT(*) FROM #__userfield WHERE LOWER($name_field) = " . strtolower($db->Quote($userinfo->username)) . " OR LOWER($name_field) = " . strtolower($db->Quote($userinfo->username));
                    $db->setQuery($query);
                    $result = $db->loadResult();
                    if (!empty($result)) {
                        $status['error'][] = JText::_('USERNAME_IN_USE');
                        return $status;
                    }
                }
            }
        }
        $guest = $userinfo->guest;
        $text = JRequest::getVar('quickReply', false, 'POST');
		//strip out html from post
		$text = strip_tags($text);

        if (!empty($text)) {
			$foruminfo = $this->getForumInfo($ids->forumid);
			$threadinfo = $this->getThreadInfo($ids->threadid, $dbparams);
			$post_approved = ($userinfo->guest && ($foruminfo['moderatenewposts'] || $dbparams->get('moderate_guests',1))) ? 0 : 1;
            $title = "Re: " . $threadinfo['title'];
            $public =& JFusionFactory::getPublic($this->getJname());
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

            if (!empty($response['errors'])) {
                $status['error'] = array_merge($status['error'], $response['errors']);
            } else {
                $id = $response['new_id'];;

                //store post id
                $status['postid'] = $id;
            }


			//update moderation status to tell discussion bot to notify user
			$status['post_moderated'] = ($post_approved) ? 0 : 1;
		}

		if (!empty($response['debug'])) {
		    $status['debug'][] = $response['debug'];
		}

        return $status;
    }

    /**
     * @param JParameter &$dbparams
     * @param object &$existingthread
     * @param object &$contentitem
     * @param array &$status
     */
    function updateThread(&$dbparams, &$existingthread, &$contentitem, &$status)
    {
        //strip title of all html characters and convert entities back to applicable characaters (to prevent double encoding by vB)
        $title = trim(strip_tags(html_entity_decode($contentitem->title)));
		$text = $this->prepareFirstPostBody($dbparams, $contentitem);

        $apidata = array(
            "existingthread" => $existingthread,
            "ipaddress" => $_SERVER["REMOTE_ADDR"],
        	"title" => $title,
            "text" => $text
        );
        $response = $this->helper->apiCall('updateThread', $apidata);

        if (!empty($response['errors'])) {
            $status['error'] = array_merge($status['error'], $response['errors']);
        }

        if (!empty($response['debug'])) {
		    $status['debug']['api_call'] = $response['debug'];
		}
    }

    /**
     * @param $id
     * @param $dbparams
     * @return mixed
     */
    function getThreadInfo($id, &$dbparams)
    {
        $threadid = intval($id);
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT if (visible = 2, 1, 0) AS isdeleted,";
        $query.= " thread.* FROM #__thread AS thread";
        $query.= " WHERE thread.threadid = $threadid";
        $db->setQuery($query);
        $threadinfo = $db->loadAssoc();
        return $threadinfo;
    }

    /**
     * @param $id
     * @return array
     */
    function getForumInfo($id) {
		$jdb =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT * FROM #__forum WHERE forumid = " . (int) $id;
		$jdb->setQuery($query);
		$foruminfo = $jdb->loadAssoc();

		//set the forum options
		$options = array(
			"active" 			=> 1,
			"allowposting" 		=> 2,
			"cancontainthreads"	=> 4,
			"moderatenewpost" 	=> 8,
			"moderatenewthread" => 16,
			"moderateattach" 	=> 32,
			"allowbbcode" 		=> 64,
			"allowimages" 		=> 128,
			"allowhtml"			=> 256,
			"allowsmilies" 		=> 512,
			"allowicons" 		=> 1024,
			"allowratings" 		=> 2048,
			"countposts" 		=> 4096,
			"canhavepassword" 	=> 8192,
			"indexposts" 		=> 16384,
			"styleoverride" 	=> 32768,
			"showonforumjump" 	=> 65536,
			"prefixrequired" 	=> 131072
		);

		foreach($options as $name => $val) {
			$foruminfo[$name] = (($foruminfo['options'] & $val) ? 1 : 0);
		}

		$foruminfo['depth'] = substr_count($foruminfo['parentlist'], ',') - 1;

		return $foruminfo;
	}

    /**
     * @param JParameter $dbparams
     * @param object $existingthread
     * @return array
     */
    function getPosts(&$dbparams, &$existingthread)
    {
        $threadid = $existingthread->threadid;
        $postid = $existingthread->postid;
        //set the query
        $sort = $dbparams->get('sort_posts');
        $where = "WHERE a.threadid = {$threadid} AND a.postid != {$postid} AND a.visible = 1";
        $name_field = $this->params->get('name_field');
        if (empty($name_field)) {
            $query = "SELECT a.postid , a.username, a.username as name, a.userid, CASE WHEN a.userid = 0 THEN 1 ELSE 0 END AS guest, a.title, a.dateline, a.pagetext, a.threadid, b.title AS threadtitle FROM `#__post` as a INNER JOIN `#__thread` as b ON a.threadid = b.threadid $where ORDER BY a.dateline $sort";
        } else {
            $query = "(SELECT a.postid , a.username, CASE WHEN f.$name_field IS NULL OR f.$name_field = '' THEN a.username ELSE f.$name_field END AS name, a.userid, 0 AS guest, a.title, a.dateline, a.dateline as order_by_date, a.pagetext, a.threadid, b.title AS threadtitle FROM `#__post` as a INNER JOIN `#__thread` as b ON a.threadid = b.threadid INNER JOIN `#__userfield` as f ON f.userid = a.userid $where AND a.userid != 0)";
            $query.= " UNION ";
            $query.= "(SELECT a.postid , a.username, a.username as name, a.userid, 1 AS guest, a.title, a.dateline, a.dateline as order_by_date, a.pagetext, a.threadid, b.title AS threadtitle FROM `#__post` as a INNER JOIN `#__thread` as b ON a.threadid = b.threadid $where AND a.userid = 0)";
            $query.= " ORDER BY order_by_date $sort";
        }
        $jdb = JFusionFactory::getDatabase($this->getJname());

		if($dbparams->get('enable_pagination',true)) {
			$application = JFactory::getApplication() ;
			$limitstart = JRequest::getInt( 'limitstart_discuss', 0 );
			$limit = (int) $application->getUserStateFromRequest( 'global.list.limit', 'limit_discuss', 5, 'int' );
        	$jdb->setQuery($query, $limitstart, $limit);
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
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT replycount FROM #__thread WHERE threadid = {$existingthread->threadid}";
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
    }

    /**
     * @return object
     */
    function getDiscussionColumns()
    {
        $columns = new stdClass();
        $columns->userid = "userid";
        $columns->username = "username";
        $columns->name = "name";
        $columns->dateline = "dateline";
        $columns->posttext = "pagetext";
        $columns->posttitle = "title";
        $columns->postid = "postid";
        $columns->threadid = "threadid";
        $columns->threadtitle = "threadtitle";
        $columns->guest = "guest";
        return $columns;
    }

    /**
     * @param int $threadid
     * @return string
     */
    function getThreadURL($threadid)
    {
        return $this->helper->getVbURL('showthread.php?t=' . $threadid, 'threads');
    }

    /**
     * @param int $threadid
     * @param int $postid
     * @return string
     */
    function getPostURL($threadid, $postid)
    {
        return $this->helper->getVbURL('showthread.php?p=' . $postid . '#post' . $postid, 'post');
    }

    /**
     * @param int $uid
     * @return string
     */
    function getProfileURL($uid)
    {
        return $this->helper->getVbURL('member.php?u=' . $uid, 'members');
    }

    /**
     * @param int $userid
     * @return array
     */
    function getPrivateMessageCounts($userid)
    {
        // initialise some objects
        $jdb = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT pmtotal,pmunread FROM #__user WHERE userid = ' . $userid;
        $jdb->setQuery($query);
        $vbPMData = $jdb->loadObject();
        $pmcount['total'] = $vbPMData->pmtotal;
        $pmcount['unread'] = $vbPMData->pmunread;
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
     * @return int|null|string
     */
    function getAvatar($userid)
    {
        $url = 0;
        if ($userid) {
            $db = JFusionFactory::getDatabase($this->getJname());

            $query = "SELECT u.avatarid, u.avatarrevision, avatarpath, NOT ISNULL(c.userid) AS usecustom, c.dateline
                        FROM #__user AS u
                        LEFT JOIN #__avatar AS a ON a.avatarid = u.avatarid
                        LEFT JOIN #__customavatar AS c ON c.userid = u.userid
                        WHERE u.userid = $userid";
            $db->setQuery($query);
            $avatar = $db->loadObject();

            $usefileavatar = $avatarurl = null;
            $query = "SELECT varname, value FROM #__setting WHERE varname = 'usefileavatar' OR varname = 'avatarurl'";
            $db->setQuery($query);
            $settings = $db->loadObjectList();
            foreach ($settings as $s) {
                ${$s->varname} = $s->value;
            }

            if (!empty($avatar->avatarpath)) {
                if (strpos($avatar->avatarpath, 'http') === false) {
                    $url = $this->params->get('source_url') . $avatar->avatarpath;
                } else {
                    $url = $avatar->avatarpath;
                }
            } elseif ($avatar->usecustom) {
                if ($usefileavatar && $avatarurl) {
                    //avatars are saved to the filesystem
                    $url = (strpos($avatarurl, 'http') === false) ? $this->params->get('source_url') . $avatarurl : $avatarurl;
                    $url.= "/avatar{$userid}_{$avatar->avatarrevision}.gif";
                } else {
                    //avatars are saved in the database
                    $url = $this->params->get('source_url') . 'image.php?u=' . $userid . '&amp;dateline=' . $avatar->dateline;
                }
            }
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
        //if no ther were no forums passed, the entire list is called and filtered in filterForumList
        //however if for some reason filterForumList fails, set forumid to 0 to prevent anything from showing protecting private forums
        $where = (!empty($usedforums)) ? 'WHERE a.forumid IN (' . implode(',', $usedforums) . ') AND b.visible = 1 AND c.password = ""' : 'WHERE a.forumid = 0 AND b.visible = 1 AND c.password = ""';
        $end = $result_order . " LIMIT 0," . ($result_limit + 25);

        $numargs = func_num_args();

        if ($numargs > 3) {
            $db = JFusionFactory::getDatabase($this->getJname());
            $filters = func_get_args();
            $i = 3;
            for ($i = 3; $i < $numargs; $i++) {
                if ($filters[$i][0] == 'userid') {
                    $where.= ' AND b.userid = ' . $db->Quote($filters[$i][1]);
                }
            }
        }

        $name_field = $this->params->get('name_field');
        $query = array();
        if (empty($name_field)) {
            //Latest active topic with first post info
            $query[LAT . '0'] = "SELECT a.threadid, a.lastpostid AS postid, b.username, b.username as name, b.userid, CASE WHEN b.userid = 0 THEN 1 ELSE 0 END AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid $where ORDER BY a.lastpost $end";

            //Latest active topic with lastest post info
            $query[LAT . '1'] = "SELECT a.threadid, a.lastpostid AS postid, b.username, b.username as name, b.userid, CASE WHEN b.userid = 0 THEN 1 ELSE 0 END AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost FROM `#__thread` as a INNER JOIN `#__post` as b ON a.lastpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid $where ORDER BY a.lastpost $end";

            //Latest created topic
            $query[LCT] = "SELECT a.threadid, b.postid, b.username, b.username as name, b.userid, CASE WHEN b.userid = 0 THEN 1 ELSE 0 END AS guest, a.title AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid $where ORDER BY a.dateline $end";

            //Latest created post
            $query[LCP] = "SELECT b.threadid, b.postid, b.username, b.username as name, b.userid, CASE WHEN b.userid = 0 THEN 1 ELSE 0 END AS guest, CASE WHEN b.title = '' THEN CONCAT(\"Re: \",a.title) ELSE b.title END AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost FROM `#__thread` as a INNER JOIN `#__post` AS b ON a.threadid = b.threadid INNER JOIN #__forum as c ON a.forumid = c.forumid $where ORDER BY b.dateline $end";
        } else {
            //Latest active topic with first post info
            $query[LAT . '0'] = "(SELECT a.threadid, a.lastpostid AS postid, b.username, b.userid, 0 AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost, a.lastpost as order_by_date, CASE WHEN f.$name_field IS NULL OR f.$name_field = '' THEN b.username ELSE f.$name_field END AS name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid INNER JOIN `#__userfield` as f ON f.userid = b.userid $where AND b.userid != 0)";
            $query[LAT . '0'].= " UNION ";
            $query[LAT . '0'].= "(SELECT a.threadid, a.lastpostid AS postid, b.username, b.userid, 1 AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost, a.lastpost as order_by_date, b.username as name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid $where AND b.userid = 0)";
            $query[LAT . '0'].= " ORDER BY order_by_date $end";

            //Latest active topic with lastest post info
            $query[LAT . '1'] = "(SELECT a.threadid, a.lastpostid AS postid, b.username, b.userid, 0 AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost, a.lastpost as order_by_date, CASE WHEN f.$name_field IS NULL OR f.$name_field = '' THEN b.username ELSE f.$name_field END AS name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.lastpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid INNER JOIN `#__userfield` as f ON f.userid = b.userid $where AND b.userid != 0)";
            $query[LAT . '1'].= " UNION ";
            $query[LAT . '1'].= "(SELECT a.threadid, a.lastpostid AS postid, b.username, b.userid, 1 AS guest, a.title AS subject, b.dateline, a.forumid, a.lastpost, a.lastpost as order_by_date, b.username as name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.lastpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid $where AND b.userid = 0)";
            $query[LAT . '1'].= " ORDER BY order_by_date $end";

            //Latest created topic
            $query[LCT] = "(SELECT a.threadid, b.postid, b.username, b.userid, 0 AS guest, a.title AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost, a.dateline as order_by_date, CASE WHEN f.$name_field IS NULL OR f.$name_field = '' THEN b.username ELSE f.$name_field END AS name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid INNER JOIN `#__userfield` as f ON f.userid = b.userid $where AND b.userid != 0)";
            $query[LCT].= " UNION ";
            $query[LCT].= "(SELECT a.threadid, b.postid, b.username, b.userid, 1 AS guest, a.title AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost, a.dateline as order_by_date, b.username AS name FROM `#__thread` as a INNER JOIN `#__post` as b ON a.firstpostid = b.postid INNER JOIN #__forum as c ON a.forumid = c.forumid $where and b.userid = 0)";
            $query[LCT].= " ORDER BY order_by_date $end";

            //Latest created post
            $query[LCP] = "(SELECT b.threadid, b.postid, b.username, b.userid, 0 AS guest, CASE WHEN b.title = '' THEN CONCAT(\"Re: \",a.title) ELSE b.title END AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost, b.dateline as order_by_date, CASE WHEN f.$name_field IS NULL OR f.$name_field = '' THEN b.username ELSE f.$name_field END AS name FROM `#__thread` as a INNER JOIN `#__post` AS b ON a.threadid = b.threadid INNER JOIN #__forum as c ON a.forumid = c.forumid INNER JOIN `#__userfield` as f ON f.userid = b.userid $where AND b.userid != 0)";
            $query[LCP].= " UNION ";
            $query[LCP].= "(SELECT b.threadid, b.postid, b.username, b.userid, 1 AS guest, CASE WHEN b.title = '' THEN CONCAT(\"Re: \",a.title) ELSE b.title END AS subject, b.dateline, b.pagetext AS body, a.forumid, a.lastpost, b.dateline as order_by_date, b.username AS name FROM `#__thread` as a INNER JOIN `#__post` AS b ON a.threadid = b.threadid INNER JOIN #__forum as c ON a.forumid = c.forumid $where AND b.userid = 0)";
            $query[LCP].= " ORDER BY order_by_date $end";
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
    	if (!$JUser->guest) {
            static $marktimes;
            if (!is_array($marktimes)) {
                $marktimes = array();
                $db = JFusionFactory::getDatabase($this->getJname());
                $userlookup = JFusionFunction::lookupUser($this->getJname(), $JUser->id);
                if (!empty($userlookup)) {
                    $query = "SELECT threadid, readtime FROM #__threadread WHERE userid = {$userlookup->userid}";
                    $db->setQuery($query);
                    $marktimes['thread'] = $db->loadObjectList('threadid');

                    $query = "SELECT forumid, readtime FROM #__forumread WHERE userid = {$userlookup->userid}";
                    $db->setQuery($query);
                    $marktimes['forum'] = $db->loadObjectList('forumid');

                    $query = "SELECT lastvisit FROM #__user WHERE userid = {$userlookup->userid}";
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
        } else {
            $newstatus = 0;
        }
        return $newstatus;
    }

    /**
     * @param bool $objectList
     * @return array
     */
    function getForumList($objectList = true)
    {
        //get the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT forumid as id, title_clean as name, options FROM #__forum ORDER BY forumid';
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
        return $results;
    }

    /**
     * @param string $userid
     * @return array
     */
    function getForumPermissions($userid = 'find')
    {
        static $forumPerms, $groupPerms;
        if (empty($forumPerms)) {
            if ($userid == 'find') {
                //get the joomla user
                $JoomlaUser = JFactory::getUser();
                //get the vb user
                if (!$JoomlaUser->guest) {
                    $user = JFusionFunction::lookupUser($this->getJname(), $JoomlaUser->id);
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
            $db = JFusionFactory::getDatabase($this->getJname());
            if ($userid != 0) {
                $query = "SELECT u.usergroupid AS gid, u.membergroupids, g.forumpermissions AS perms FROM #__user AS u INNER JOIN #__usergroup AS g ON u.usergroupid = g.usergroupid WHERE u.userid = '$userid'";
            } else {
                $query = "SELECT usergroupid AS gid, forumpermissions AS perms FROM #__usergroup WHERE usergroupid = '1'";
            }
            $db->setQuery($query);
            $usergroup = $db->loadObject();
            $groupPerms = $usergroup->perms;
            //merge the permissions of member groups
            if (!empty($usergroup->membergroupids)) {
                $membergroups = explode(',', $usergroup->membergroupids);
                $query = "SELECT forumpermissions FROM #__usergroup WHERE usergroupid IN ({$usergroup->membergroupids})";
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
            $query = "SELECT p.forumpermissions, p.forumid, p.usergroupid, f.parentlist, f.childlist FROM #__forumpermission AS p INNER JOIN #__forum AS f ON p.forumid = f.forumid WHERE p.usergroupid = {$usergroup->gid} ORDER BY p.forumid";
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
                $query = "SELECT p.forumpermissions, p.forumid, p.usergroupid, f.parentlist, f.childlist FROM #__forumpermission AS p INNER JOIN #__forum AS f ON p.forumid = f.forumid WHERE p.usergroupid IN ({$usergroup->membergroupids}) ORDER BY p.forumid";
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
        return array($groupPerms, $forumPerms);
    }

    /**
     * @param object $results
     * @param int $limit
     * @param string $idKey
     * @param bool $search
     */
    function filterActivityResults(&$results, $limit = 0, $idKey = 'forumid', $search = false)
    {
        //get the joomla user
        $JoomlaUser = JFactory::getUser();
        //get the vb user
        if (!$JoomlaUser->guest) {
            $user = JFusionFunction::lookupUser($this->getJname(), $JoomlaUser->id);
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