<?php

/**
 * Abstract forum file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Abstract interface for all JFusion forum implementations.
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionForum
{
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return '';
    }

    /**
     * Returns the URL to a thread of the integrated software
     *
     * @param int $threadid threadid
     *
     * @return string URL
     */
    function getThreadURL($threadid)
    {
        return '';
    }

    /**
     * Returns the URL to a post of the integrated software
     *
     * @param int $threadid threadid
     * @param int $postid   postid
     *
     * @return string URL
     */
    function getPostURL($threadid, $postid)
    {
        return '';
    }

    /**
     * Returns the URL to a userprofile of the integrated software
     *
     * @param int $uid userid
     *
     * @return string URL
     */
    function getProfileURL($uid)
    {
        return '';
    }

    /**
     * Retrieves the source path to the user's avatar
     *
     * @param int $uid softwares user id
     *
     * @return string with source path to users avatar
     */
    function getAvatar($uid)
    {
        return 0;
    }

    /**
     * Returns the URL to the view all private messages URL of the integrated software
     *
     * @return string URL
     */
    function getPrivateMessageURL()
    {
        return '';
    }

    /**
     * Returns the URL to a view new private messages URL of the integrated software
     *
     * @return string URL
     */
    function getViewNewMessagesURL()
    {
        return '';
    }

    /**
     * Returns the URL to a get private messages URL of the integrated software
     *
     * @param int $puser_id userid
     *
     * @return string URL
     */
    function getPrivateMessageCounts($puser_id)
    {
        return 0;
    }

    /**
     * Returns the an array with SQL statements used by the activity module
     *
     * @param array  $usedforums    array with used forums
     * @param string $result_order  ordering of results
     * @param int    $result_limit  number of results to limit by
     *
     * @return array
     */
    function getActivityQuery($usedforums, $result_order, $result_limit)
    {
        return 0;
    }

    /**
     * Returns the read status of a post based on the currently logged in user
     *
     * @param $post object with post data from the results returned from getActivityQuery
     * @return boolean
     */
    function checkReadStatus(&$post)
    {
        return 0;
    }

    /**
     * Returns the a list of forums of the integrated software
     *
     * @return array List of forums
     */
    function getForumList()
    {
        return array();
    }

    /**
     * Filter forums from a set of results sent in / useful if the plugin needs to restrict the forums visible to a user
     *
     * @param object &$results set of results from query
     * @param int    $limit    limit results parameter as set in the module's params; used for plugins that cannot limit using a query limiter
     *
     * @return array List of result
     */
    function filterActivityResults(&$results, $limit = 0)
    {
    }

    /************************************************
    * Functions For JFusion Discussion Bot Plugin
    ***********************************************/
    /**
     * Returns the URL to the reply page for a thread
     * @param integer $forumid
     * @param integer $threadid
     * @return string URL
     */
    function getReplyURL($forumid, $threadid)
    {
        return '';
    }

    /**
     * Checks to see if a thread already exists for the content item and calls the appropriate function
     *
     * @param object 	&$dbparams		object with discussion bot parameters
     * @param object 	&$contentitem 	object containing content information
     * @param object 	&$threadinfo 	object with threadinfo from lookup table
     * @param array 	&$status		object with debug, error, and action stati
     */
	function checkThreadExists(&$dbparams, &$contentitem, &$threadinfo, &$status)
	{
		$threadid = (is_object($threadinfo)) ? $threadinfo->threadid : $threadinfo;
		$forumid = $this->getDefaultForum($dbparams, $contentitem);
		$existingthread = (empty($threadid)) ? false : $this->getThread($threadid);

		if(!empty($forumid)) {
			if(!empty($existingthread)) {
				//datetime post was last updated
				$postModified = $threadinfo->modified;
				//datetime content was last updated
				$contentModified = JFactory::getDate($contentitem->modified)->toUnix();

				$status['debug'][] = 'Thread exists...comparing dates';
				$status['debug'][] = "Content Modification Date: $contentModified (" . date("Y-m-d H:i:s", $contentModified). ")";
				$status['debug'][] = "Thread Modification Date: $postModified  (" . date("Y-m-d H:i:s", $postModified). ")";
				$status['debug'][] = "Is $contentModified > $postModified?";
				if($contentModified > $postModified) {
					$status['debug'][] = "Yes...attempting to update thread";
					//update the post if the content has been updated
					$this->updateThread($dbparams, $existingthread, $contentitem, $status);
					if (empty($status['error'])) {
	                	$status['action'] = 'updated';
	            	}
				} else {
					$status['debug'][] = "No...thread unchanged";
				}
			} else {
				$status['debug'][] = "Thread does not exist...attempting to create thread";
		    	//thread does not exist; create it
	            $this->createThread($dbparams, $contentitem, $forumid, $status);
	            if (empty($status['error'])) {
	                $status['action'] = 'created';
	            }
	        }
		} else {
			$status['error'][] = JText::_('FORUM_NOT_CONFIGURED');
		}
	}

    /**
     * Checks to see if a thread is locked
     *
     * @param 	int 	$threadid	thread id
     *
     * @return 	boolean 			true if locked
     */
    function getThreadLockedStatus($threadid) {
        //assume false
        return 0;
    }

    /**
     * Retrieves the default forum based on section/category stipulations or default set in the plugins config
     *
     * @param object &$dbparams    discussion bot parameters
     * @param object &$contentitem object containing content information
     *
     * @return int Returns id number of the forum
     */
	function getDefaultForum(&$dbparams, &$contentitem)
	{
		//set some vars
		$forumid = $dbparams->get("default_forum");
		$catid =& $contentitem->catid;
		$option = JRequest::getCmd('option');
		$isJ16 = JFusionFunction::isJoomlaVersion('1.6');

		if ($option == 'com_k2' || $option == 'com_content') {
    		//determine default forum
    		if ($option == 'com_content' && !$isJ16) {
    		    //only J1.5 uses sections
        		$sectionid =& $contentitem->sectionid;
        		$sections = $dbparams->get("pair_sections");
        		if(!empty($sections)) {
        			$pairs = base64_decode($sections);
        			$sectionPairs = @unserialize($pairs);
        			if ($sectionPairs === false) {
        			    $sectionPairs = array();
        			}
        		} else {
        			$sectionPairs = array();
        		}

        		if(array_key_exists($sectionid, $sectionPairs)) {
        			$forumid = $sectionPairs[$sectionid];
        		}
    	    }

	        $param_name = ($option == 'com_k2') ? 'pair_k2_categories' : 'pair_categories';
    		$categories = $dbparams->get($param_name);
    		if(!empty($categories)) {
    			$pairs = base64_decode($categories);
    			$categoryPairs = @unserialize($pairs);
    			if ($categoryPairs === false) {
    			    $categoryPairs = array();
    			}
    		} else {
    			$categoryPairs = array();
    		}

    		if(array_key_exists($catid, $categoryPairs)) {
    			$forumid = $categoryPairs[$catid];
			} elseif (($option == 'com_k2' && isset($contentitem->category)) || ($option == 'com_content' && $isJ16)) {
    		    //let's see if a parent has been assigned a forum
    		    if ($option == 'com_k2') {
    		        //see if a parent category is included
    		        $db = JFactory::getDBO();
                    $stop = false;
                    $parent_id = $contentitem->category->parent;;
                    while (!$stop) {
                        if (!empty($parent_id)) {
                            if(array_key_exists($parent_id, $categoryPairs)) {
                                $stop = true;
                                $forumid = $categoryPairs[$parent_id];
                            } else {
                                //get the parent's parent
                                $query = "SELECT parent FROM #__k2_categories WHERE id = $parent_id";
                                $db->setQuery($query);
                                //keep going up
                                $parent_id = $db->loadResult();
                            }
                        } else {
                            //at the top
                            $stop = true;
                        }
                    }
    		    } else {
    		        $JCat =& JCategories::getInstance('Content');
            		$cat = $JCat->get($catid);
            		if ($cat) {
	    		        $parent_id = $cat->getParent()->id;
	                    if ($parent_id !== 'root') {
	                        $stop = false;
	                        while (!$stop) {
	                            if (array_key_exists($parent_id, $categoryPairs)) {
	                                $forumid = $categoryPairs[$parent_id];
	                                $stop = true;
	                            } else {
	                                //keep going up so get the parent's parent id
	                                $parent = $JCat->get($parent_id);
	                                $parent_id = $parent->getParent()->id;
	                                if ($parent_id == 'root') {
	                                    $stop = true;
	                                }
	                            }
	                        }
	                    }
            		}
    		    }
    		}
		}

		return $forumid;
	}

    /**
     * Retrieves thread information
     * $result->forumid
     * $result->threadid (yes add it even though it is passed in as it will be needed in other functions)
     * $result->postid - this is the id of the first post in the thread
     *
     * @param int $threadid Id of specific thread
     *
     * @return object Returns object with thread information
     */
    function getThread($threadid)
    {
        return '';
    }

    /**
     * Function that determines the author of an article or returns the default user if one is not found
     * For the discussion bot
     *
     * @param object &$dbparams    object with discussion bot parameters
     * @param object &$contentitem contentitem
     *
     * @return int forum's userid
     */
	function getThreadAuthor(&$dbparams, &$contentitem)
	{
		if($dbparams->get('use_article_userid',1)) {
			//find this user in the forum
			$userinfo = JFusionFunction::lookupUser($this->getJname(),$contentitem->created_by);

			if(empty($userinfo->userid)) {
				$id = $dbparams->get('default_userid');
			} else {
				$id = $userinfo->userid;
			}
		} else {
			$id = $dbparams->get('default_userid');
		}

		return $id;
	}

    /**
     * Creates new thread and posts first post
     *
     * @param object &$params      discussion bot parameters
     * @param object &$contentitem containing content information
     * @param int    $forumid      forum to create thread
     * @param object &$status      status object for feedback of function
     *
     * @return array $status contains errors and status of actions
     */
    function createThread(&$params, &$contentitem, $forumid, &$status)
    {
    }

    /**
     * Updates information in a specific thread/post
     *
     * @param object &$params         discussion bot parameters
     * @param object &$existingthread existing thread info
     * @param object &$contentitem    content item
     * @param object &$status         status object for feedback of function
     *
     * @return array $status contains errors and status of actions
     */
    function updateThread(&$params, &$existingthread, &$contentitem, &$status)
    {
    }

    /**
     * Returns an object of columns used in createPostTable()
     * Saves from having to repeat the same code over and over for each plugin
     * For example:
     * $columns->userid = "userid";
     * $columns->username = "username";
     * $columns->name = "realName"; //if applicable
     * $columns->dateline = "dateline";
     * $columns->posttext = "pagetext";
     * $columns->posttitle = "title";
     * $columns->postid = "postid";
     * $columns->threadid = "threadid";
     * $columns->threadtitle = "threadtitle"; //optional
     * $columns->guest = "guest";
     *
     * @return object with column names
     */
    function getDiscussionColumns()
    {
        return;
    }

	/**
	 * Prepares the body for the first post in a thread
	 *
	 * @param object	&$dbparams 		object with discussion bot parameters
	 * @param object	$contentitem 	object containing content information
	 *
	 * @return string
	 */
	function prepareFirstPostBody(&$dbparams, $contentitem)
	{
		//set what should be posted as the first post
		$post_body = $dbparams->get("first_post_text",'intro');

		$text = "";

		if($post_body=="intro") {
			//prepare the text for posting
			$text .= $contentitem->introtext;
		} elseif($post_body=='full') {
			//prepare the text for posting
			$text .= $contentitem->introtext . $contentitem->fulltext;
		}

		//create link
		$show_link = $dbparams->get('first_post_link',1);
		//add a link to the article; force a link if text body is set to none so something is returned
		if($show_link || $post_body=='none') {
			$link_text = $dbparams->get("first_post_link_text");
			if(empty($link_text)) {
				$link_text = JText::_('DEFAULT_ARTICLE_LINK_TEXT');
			} else {
				if($dbparams->get("first_post_link_type") == 'image') {
					$link_text = "<img src='$link_text'>";
				}
			}

			$text .= (!empty($text)) ? "<br /><br />" : "";
			$text .= JFusionFunction::createJoomlaArticleURL($contentitem,$link_text);
		}

		//prepare the content
        $public = & JFusionFactory::getPublic($this->getJname());
		$public->prepareText($text, 'forum');

		return $text;
	}

    /**
     * Retrieves the posts to be displayed in the content item if enabled
     *
     * @param object &$params         object with discussion bot parameters
     * @param object &$existingthread object with forumid, threadid, and postid (first post in thread)
     * @param int 	 $limitstart	  (optional) obtain results starting with this number
     * @param int 	 $limit	  		  (optional) limit number of results returned
     *
     * @return array or object Returns retrieved posts
     */
    function getPosts(&$params, &$existingthread, $limitstart = null, $limit = null)
    {
        return array();
    }
    /**
     * Returns the total number of posts in a thread
     *
     * @param object &$existingthread object with forumid, threadid, and postid (first post in thread)
     *
     * @return int
     */
    function getReplyCount(&$existingthread)
    {
        return 0;
    }

    /**
     * Loads required quick reply includes into the main document so that ajax will work properly if initiating a discussion manually.  It is best
     * to load any files but return any standalone JS declarations.
     *
     * @return string $js JS declarations
     */

    function loadQuickReplyIncludes() {
		//using markitup http://markitup.jaysalvat.com/ for bbcode textbox
		$document = JFactory::getDocument();
		$option = JRequest::getCmd('option');
		$path = (JFusionFunction::isJoomlaVersion('1.6')) ? 'jfusion/discussbot' : 'discussbot';
		if ($option != 'com_k2') {
		    //k2 loads jquery already
		    $document->addScript(JFusionFunction::getJoomlaURL().'plugins/content/'.$path.'/markitup/jquery.pack.js');
		}
		$document->addScript(JFusionFunction::getJoomlaURL().'plugins/content/'.$path.'/markitup/jquery.markitup.js');
		$document->addScript(JFusionFunction::getJoomlaURL().'plugins/content/'.$path.'/markitup/sets/bbcode/set.js');
		$document->addStylesheet(JFusionFunction::getJoomlaURL().'plugins/content/'.$path.'/markitup/skins/simple/style.css');
		$document->addStylesheet(JFusionFunction::getJoomlaURL().'plugins/content/'.$path.'/markitup/sets/bbcode/style.css');

		$js  = "var jfdb_load_markitup = 1;\n";
		$js .= "jQuery.noConflict();\n";
		return $js;
    }

    /**
     * Returns HTML of a quick reply
     *
     * @param object  &$dbparams       object with discussion bot parameters
     * @param boolean $showGuestInputs toggles whether to show guest inputs or not
     *
     * @return string of html
     */
	function createQuickReply(&$dbparams, $showGuestInputs)
	{
		$html = '';
		if($showGuestInputs) {
			$username = JRequest::getVar('guest_username','','post');
			$html .= "<table><tr><td>".JText::_('USERNAME') .":</td><td><input name='guest_username' value='$username' class='inputbox'/></td></tr>";
			$html .= $this->createCaptcha($dbparams);
			$html .= "</table><br />";
		}
		$quickReply = JRequest::getVar('quickReply','','post');
	   	$html .= "<textarea id='quickReply' name='quickReply' class='inputbox' rows='15' cols='100'>$quickReply</textarea><br />";
	   	return $html;
	}

    /**
     * Creates the html for the selected captcha for the discussion bot
     *
     * @param object $dbparams object with discussion bot parameters
     *
     * @return unknown_type
     */
	function createCaptcha($dbparams)
	{
		$html = '';
		$captcha_mode = $dbparams->get('captcha_mode','disabled');

		switch($captcha_mode) {
			case 'question':
				//answer/question method
				$question = $dbparams->get('captcha_question');
				if(!empty($question)) {
					$html .= "<tr><td>$question:</td><td><input name='captcha_answer' value='' class='inputbox'/></td></tr>";
				}
				break;
			case 'joomla15captcha':
				//using joomla15captcha (http://code.google.com/p/joomla15captcha)
				$dispatcher = &JDispatcher::getInstance();
				$results = $dispatcher->trigger( 'onCaptchaRequired', array( 'jfusion.discussion' ) );
				if ($results[0])
					ob_start();
					$dispatcher->trigger( 'onCaptchaView', array( 'jfusion.discussion', 0, '<tr><td colspan=2><br />', '<br /></td></tr>' ) );
					$html .= ob_get_contents();
					ob_end_clean();
				break;
			case 'recaptcha':
				//using reCAPTCHA (http://recaptcha.net)
				$recaptchalib = JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'recaptchalib.php';
				if(file_exists($recaptchalib)) {
					$theme = $dbparams->get('recaptcha_theme','red');
					$lang = $dbparams->get('recaptcha_lang','en');

					$document = JFactory::getDocument();
					$script .= "<script>\n";
					$script .= "var RecaptchaOptions = {\n";
   					$script .= "theme : '$theme',\n";
   					$script .= "lang: '$lang'\n";
					$script .= "};\n";
					$script .= "</script>\n";
					$document->addCustomTag($script);

					$html .= "<tr><td colspan=2>";
					if (!function_exists('recaptcha_get_html')) {
	                	include_once $recaptchalib;
	                }
					$error = null;
					$publickey = $dbparams->get('recaptcha_publickey');
					$html .= recaptcha_get_html($publickey, $error);
					if(!empty($error)) {
						$html .= $error;
					}
					$html .= "</td></tr>";
				}
				break;
			case 'custom':
				$html .= $this->createCustomCaptcha($dbparams);
				break;
			default:
				break;
		}

		return $html;
	}

    /**
     * Creates custom captcha html for this plugin
     *
     * @param object &$dbparams object with discussion bot parameters
     *
     * @return string with html
     */
	function createCustomCaptcha(&$dbparams)
	{
		JFusionFunction::raiseWarning($this->getJname() . ' ' . JText::_('DISCUSSBOT_ERROR'), JText::_('CUSTOM_CAPTCHA_NOT_IMPLEMENTED'),1);
		return '';
	}

    /**
     * Verifies captcha of a guest post submitted by the discussion bot
     *
     * @param object &$dbparams object with discussion bot parameters
     *
     * @return boolean
     */
	function verifyCaptcha(&$dbparams)
	{
		//let's check for captcha
		$captcha_mode = $dbparams->get('captcha_mode','disabled');
		$captcha_verification = false;

		switch($captcha_mode) {
			case 'question':
				//question/answer method
				$captcha_answer = JRequest::getVar('captcha_answer', '', 'POST');
				if(!empty($captcha_answer) && $captcha_answer == $dbparams->get('captcha_answer')) {
					$captcha_verification = true;
				}
				break;
			case "joomla15captcha":
				//using joomla15captcha (http://code.google.com/p/joomla15captcha)
				$dispatcher     = &JDispatcher::getInstance();
				$results = $dispatcher->trigger( 'onCaptchaRequired', array( 'jfusion.discussion' ) );
				if ( $results[0] ) {
					$captchaparams = array( JRequest::getVar( 'captchacode', '', 'post' )
						, JRequest::getVar( 'captchasuffix', '', 'post' )
						, JRequest::getVar( 'captchasessionid', '', 'post' ));
					$results = $dispatcher->trigger( 'onCaptchaVerify', $captchaparams );
					if ( $results[0] ) {
						$captcha_verification = true;
					}
				}
				break;
			case 'recaptcha':
				//using reCAPTCHA (http://recaptcha.net)
				$recaptchalib = JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'recaptchalib.php';
				if(file_exists($recaptchalib)) {
					if (!function_exists('recaptcha_check_answer')) {
                		include_once $recaptchalib;
            		}

					$privatekey = $dbparams->get('recaptcha_privatekey');
					$response_field  = JRequest::getVar('recaptcha_response_field', '', 'post', 'string');
					$challenge_field = JRequest::getVar('recaptcha_challenge_field', '', 'post', 'string');

					$resp = recaptcha_check_answer ($privatekey,
						$_SERVER["REMOTE_ADDR"],
						$challenge_field,
						$response_field);
					if ($resp->is_valid) {
		                $captcha_verification = true;
					}
				}
				break;
			case 'disabled':
				$captcha_verification = true;
				break;
			default:
				$captcha_verification = $this->verifyCustomCaptcha($dbparams);
				break;
		}

		return $captcha_verification;
	}

    /**
     * Verifies custom captcha of a JFusion plugin
     *
     * @param object &$dbparams object with discussion bot parameters
     *
     * @return boolean
     */
	function verifyCustomCaptcha(&$dbparams)
	{
		JFusionFunction::raiseWarning($this->getJname() . ' ' . JText::_('DISCUSSBOT_ERROR'), JText::_('CUSTOM_CAPTCHA_NOT_IMPLEMENTED'),1);
		return false;
	}

    /**
     * Creates a post from the quick reply
     *
     * @param object &$params      object with discussion bot parameters
     * @param array  &$ids         array with forum id ($ids["forumid"], thread id ($ids["threadid"]) and first post id ($ids["postid"])
     * @param object &$contentitem object of content item
     * @param object &$userinfo    object info of the forum user
     *
     * @return array with status
     */
	function createPost(&$params, &$ids, &$contentitem, &$userinfo)
	{
		$status = array();
		$status["error"] = false;
		return $status;
	}
}
