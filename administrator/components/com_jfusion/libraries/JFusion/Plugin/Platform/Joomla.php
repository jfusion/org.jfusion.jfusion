<?php namespace JFusion\Plugin\Platform;

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

use JCategories;
use JCategoryNode;
use JEventDispatcher;
use JFactory;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Plugin\Plugin_Platform;
use JFusion\User\Userinfo;
use Joomla\Language\Text;

use JFusionFunction;
use JRegistry;
use \stdClass;

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
class Joomla extends Plugin_Platform
{
	var $helper;

	/**
	 * @param string $instance instance name of this plugin
	 */
	function __construct($instance)
	{
		parent::__construct($instance);
		//get the helper object
		$this->helper = & Factory::getHelper($this->getJname(), $this->getName());
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
     * @param int|string $userid userid
     *
     * @return string URL
     */
    function getProfileURL($userid)
    {
        return '';
    }

    /**
     * Retrieves the source path to the user's avatar
     *
     * @param int|string $userid software user id
     *
     * @return string with source path to users avatar
     */
    function getAvatar($userid)
    {
        return '';
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
     * @param int|string $puser_id userid
     *
     * @return array
     */
    function getPrivateMessageCounts($puser_id)
    {
        return array('unread' => 0, 'total' => 0);
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
        return array();
    }

    /**
     * Returns the read status of a post based on the currently logged in user
     *
     * @param $post object with post data from the results returned from getActivityQuery
     * @return int
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
     * @param JRegistry 	&$dbparams		object with discussion bot parameters
     * @param object 	&$contentitem 	object containing content information
     * @param object|int 	&$threadinfo 	object with threadinfo from lookup table
     * @param array 	&$status        object with debug, error, and action static
     */
	function checkThreadExists(&$dbparams, &$contentitem, &$threadinfo, &$status)
	{
		$threadid = (int) (is_object($threadinfo)) ? $threadinfo->threadid : $threadinfo;
		$forumid = $this->getDefaultForum($dbparams, $contentitem);
		$existingthread = (empty($threadid)) ? false : $this->getThread($threadid);

		if(!empty($forumid)) {
			if(!empty($existingthread)) {
				//datetime post was last updated
				if (isset($threadinfo->modified)) {
					$postModified = $threadinfo->modified;
				} else {
					$postModified = 0;
				}
				//datetime content was last updated
				$contentModified = Factory::getDate($contentitem->modified)->toUnix();

				$status['debug'][] = 'Thread exists...comparing dates';
				$status['debug'][] = 'Content Modification Date: ' . $contentModified . ' (' . date('Y-m-d H:i:s', $contentModified) . ')';
				$status['debug'][] = 'Thread Modification Date: ' . $postModified . '  (' . date('Y-m-d H:i:s', $postModified) . ')';
				$status['debug'][] = 'Is ' . $contentModified . ' > ' . $postModified . ' ?';
				if($contentModified > $postModified && $postModified != 0) {
					$status['debug'][] = 'Yes...attempting to update thread';
					//update the post if the content has been updated
					$this->updateThread($dbparams, $existingthread, $contentitem, $status);
					if (empty($status['error'])) {
	                	$status['action'] = 'updated';
	            	}
				} else {
					$status['debug'][] = 'No...thread unchanged';
				}
			} else {
				$status['debug'][] = 'Thread does not exist...attempting to create thread';
		    	//thread does not exist; create it
	            $this->createThread($dbparams, $contentitem, $forumid, $status);
	            if (empty($status['error'])) {
	                $status['action'] = 'created';
	            }
	        }
		} else {
			$status['error'][] = Text::_('FORUM_NOT_CONFIGURED');
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
        return false;
    }

    /**
     * Retrieves the default forum based on section/category stipulations or default set in the plugins config
     *
     * @param JRegistry &$dbparams    discussion bot parameters
     * @param object &$contentitem object containing content information
     *
     * @return int Returns id number of the forum
     */
	function getDefaultForum(&$dbparams, &$contentitem)
	{
		//set some vars
		$forumid = $dbparams->get('default_forum');
		$catid = $contentitem->catid;
		$option = Factory::getApplication()->input->getCmd('option');

		if ($option == 'com_k2' || $option == 'com_content') {
    		//determine default forum

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
			} elseif (($option == 'com_k2' && isset($contentitem->category)) || ($option == 'com_content')) {
    		    //let's see if a parent has been assigned a forum
    		    if ($option == 'com_k2') {
    		        //see if a parent category is included
    		        $db = Factory::getDBO();
                    $stop = false;
                    $parent_id = $contentitem->category->parent;;
                    while (!$stop) {
                        if (!empty($parent_id)) {
                            if(array_key_exists($parent_id, $categoryPairs)) {
                                $stop = true;
                                $forumid = $categoryPairs[$parent_id];
                            } else {
                                //get the parent's parent
	                            $query = $db->getQuery(true)
		                            ->select('parent')
		                            ->from('#__k2_categories')
		                            ->where('id = ' . $parent_id);

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
    		        $JCat = JCategories::getInstance('Content');
                    /**
                     * @ignore
                     * @var $cat JCategoryNode
                     */
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
                                    /**
                                     * @ignore
                                     * @var $parent JCategoryNode
                                     */
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
        return null;
    }

    /**
     * Function that determines the author of an article or returns the default user if one is not found
     * For the discussion bot
     *
     * @param JRegistry &$dbparams    object with discussion bot parameters
     * @param object &$contentitem contentitem
     *
     * @return int forum's userid
     */
	function getThreadAuthor(&$dbparams, &$contentitem)
	{
		if($dbparams->get('use_article_userid', 1)) {
			//find this user in the forum

			$userlookup = new Userinfo('joomla_int');
			$userlookup->userid = $contentitem->created_by;

			$PluginUser = Factory::getUser($this->getJname());
			$userlookup = $PluginUser->lookupUser($userlookup);

			if(!$userlookup) {
				$id = $dbparams->get('default_userid');
			} else {
				$id = $userlookup->userid;
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
     * @param array &$status      status object for feedback of function
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
     * @param array &$status         status object for feedback of function
     */
    function updateThread(&$params, &$existingthread, &$contentitem, &$status)
    {
    }

    /**
     * Returns an object of columns used in createPostTable()
     * Saves from having to repeat the same code over and over for each plugin
     * For example:
     * $columns->userid = 'userid'
     * $columns->username = 'username';
     * $columns->name = 'realName'; //if applicable
     * $columns->dateline = 'dateline';
     * $columns->posttext = 'pagetext';
     * $columns->posttitle = 'title';
     * $columns->postid = 'postid';
     * $columns->threadid = 'threadid';
     * $columns->threadtitle = 'threadtitle'; //optional
     * $columns->guest = 'guest';
     *
     * @return object with column names
     */
    function getDiscussionColumns()
    {
        return null;
    }

	/**
	 * Prepares the body for the first post in a thread
	 *
	 * @param JRegistry &$dbparams 		object with discussion bot parameters
	 * @param object	$contentitem 	object containing content information
	 *
	 * @return string
	 */
	function prepareFirstPostBody(&$dbparams, $contentitem)
	{
		//set what should be posted as the first post
		$post_body = $dbparams->get('first_post_text', 'intro');

		$text = '';

		if($post_body == 'intro') {
			//prepare the text for posting
			$text .= $contentitem->introtext;
		} elseif($post_body == 'full') {
			//prepare the text for posting
			$text .= $contentitem->introtext . $contentitem->fulltext;
		}

		//create link
		$show_link = $dbparams->get('first_post_link', 1);
		//add a link to the article; force a link if text body is set to none so something is returned
		if($show_link || $post_body == 'none') {
			$link_text = $dbparams->get('first_post_link_text');
			if(empty($link_text)) {
				$link_text = Text::_('DEFAULT_ARTICLE_LINK_TEXT');
			} else {
				if($dbparams->get('first_post_link_type') == 'image') {
					$link_text = '<img src="' . $link_text . '">';
				}
			}

			$text .= (!empty($text)) ? '<br /><br />' : '';
			$text .= JFusionFunction::createJoomlaArticleURL($contentitem, $link_text);
		}

		//prepare the content
        $public = Factory::getFront($this->getJname());
		$public->prepareText($text, 'forum');

		return $text;
	}

	/**
	 * Retrieves the posts to be displayed in the content item if enabled
	 *
	 * @param JRegistry $dbparams
	 * @param object $existingthread object with forumid, threadid, and postid (first post in thread)
	 * @param int $start
	 * @param int $limit
	 * @param string $sort
	 *
	 * @internal param object $params object with discussion bot parameters
	 * @return array or object Returns retrieved posts
	 */
    function getPosts($dbparams, $existingthread, $start, $limit, $sort)
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
    function getReplyCount($existingthread)
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

		$path = 'plugins/content/jfusion/discussbot/markitup';

		$document->addScript(JFusionFunction::getJoomlaURL() . $path . '/jquery.markitup.js');
		$document->addScript(JFusionFunction::getJoomlaURL() . $path . '/sets/bbcode/set.js');
		$document->addStylesheet(JFusionFunction::getJoomlaURL() . $path . '/skins/simple/style.css');
		$document->addStylesheet(JFusionFunction::getJoomlaURL() . $path . '/sets/bbcode/style.css');

		$js = <<<JS
		JFusion.loadMarkitup = true;
		jQuery.noConflict();
JS;
		return $js;
	}

    /**
     * Returns HTML of a quick reply
     *
     * @param JRegistry &$dbparams       object with discussion bot parameters
     * @param boolean $showGuestInputs toggles whether to show guest inputs or not
     *
     * @return string of html
     */
	function createQuickReply(&$dbparams, $showGuestInputs)
	{
		$html = '';
		if($showGuestInputs) {
			$username = Factory::getApplication()->input->post->get('guest_username', '');
            $jusername = Text::_('USERNAME');
            $html = <<<HTML
            <table>
                <tr>
                    <td>
                        {$jusername}:
                    </td>
                    <td>
                        <input name='guest_username' value='{$username}' class='inputbox'/>
                    </td>
                </tr>
                {$this->createCaptcha($dbparams)}
            </table>
            <br />
HTML;

		}
		$quickReply = Factory::getApplication()->input->post->get('quickReply', '');
	   	$html .= '<textarea name="quickReply" class="inputbox quickReply" rows="15" cols="100">' . $quickReply . '</textarea><br />';
	   	return $html;
	}

    /**
     * Creates the html for the selected captcha for the discussion bot
     *
     * @param JRegistry $dbparams object with discussion bot parameters
     *
     * @return string
     */
	function createCaptcha($dbparams)
	{
		$html = '';
		$captcha_mode = $dbparams->get('captcha_mode', 'disabled');

		switch($captcha_mode) {
			case 'question':
				//answer/question method
				$question = $dbparams->get('captcha_question');
				if(!empty($question)) {
					$html .= '<tr><td>' . $question . ':</td><td><input name="captcha_answer" value="" class="inputbox"/></td></tr>';
				}
				break;
			case 'joomla15captcha':
				//using joomla15captcha (http://code.google.com/p/joomla15captcha)
				$dispatcher = JEventDispatcher::getInstance();
				$results = $dispatcher->trigger('onCaptchaRequired', array('jfusion.discussion'));
				if ($results[0])
					ob_start();
					$dispatcher->trigger('onCaptchaView', array('jfusion.discussion', 0, '<tr><td colspan=2><br />', '<br /></td></tr>'));
					$html .= ob_get_contents();
					ob_end_clean();
				break;
			case 'recaptcha':
				//using reCAPTCHA (http://recaptcha.net)
				$recaptchalib = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'recaptchalib.php';
				if(file_exists($recaptchalib)) {
					$theme = $dbparams->get('recaptcha_theme', 'red');
					$lang = $dbparams->get('recaptcha_lang', 'en');

					$document = JFactory::getDocument();

                    $js = <<<JS
					var RecaptchaOptions = {
   					    theme : '{$theme}',
   					    lang: '{$lang}'
					};
JS;

					$document->addScriptDeclaration($js);

					$html .= '<tr><td colspan="2">';
					if (!function_exists('recaptcha_get_html')) {
	                	include_once $recaptchalib;
	                }
					$error = null;
					$publickey = $dbparams->get('recaptcha_publickey');
					$html .= recaptcha_get_html($publickey, $error);
					if(!empty($error)) {
						$html .= $error;
					}
					$html .= '</td></tr>';
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
		Framework::raiseError(Text::_('DISCUSSBOT_ERROR') . ': ' . Text::_('CUSTOM_CAPTCHA_NOT_IMPLEMENTED'), $this->getJname());
		return '';
	}

    /**
     * Verifies captcha of a guest post submitted by the discussion bot
     *
     * @param JRegistry &$dbparams object with discussion bot parameters
     *
     * @return boolean
     */
	function verifyCaptcha(&$dbparams)
	{
		//let's check for captcha
		$captcha_mode = $dbparams->get('captcha_mode', 'disabled');
		$captcha_verification = false;

		switch($captcha_mode) {
			case 'question':
				//question/answer method
				$captcha_answer = Factory::getApplication()->input->post->get('captcha_answer', '');
				if(!empty($captcha_answer) && $captcha_answer == $dbparams->get('captcha_answer')) {
					$captcha_verification = true;
				}
				break;
			case "joomla15captcha":
				//using joomla15captcha (http://code.google.com/p/joomla15captcha)
				$dispatcher = JEventDispatcher::getInstance();
				$results = $dispatcher->trigger('onCaptchaRequired', array('jfusion.discussion'));
				if ($results[0]) {
					$captchaparams = array(Factory::getApplication()->input->post->get('captchacode', '')
						, Factory::getApplication()->input->post->get('captchasuffix', '')
						, Factory::getApplication()->input->post->get('captchasessionid', ''));
					$results = $dispatcher->trigger('onCaptchaVerify', $captchaparams);
					if ($results[0]) {
						$captcha_verification = true;
					}
				}
				break;
			case 'recaptcha':
				//using reCAPTCHA (http://recaptcha.net)
				$recaptchalib = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'recaptchalib.php';
				if(file_exists($recaptchalib)) {
					if (!function_exists('recaptcha_check_answer')) {
                		include_once $recaptchalib;
            		}

					$privatekey = $dbparams->get('recaptcha_privatekey');
					$response_field  = Factory::getApplication()->input->post->getString('recaptcha_response_field', '');
					$challenge_field = Factory::getApplication()->input->post->getString('recaptcha_challenge_field', '');

					$resp = recaptcha_check_answer ($privatekey,
						$_SERVER['REMOTE_ADDR'],
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
		Framework::raiseError(Text::_('DISCUSSBOT_ERROR') . ': ' . Text::_('CUSTOM_CAPTCHA_NOT_IMPLEMENTED'), $this->getJname());
		return false;
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
        $status['debug'] = Text::_('METHOD_NOT_IMPLEMENTED');
		return $status;
	}

    /**
     * @param array $forumids
     *
     * @return array
     */
    function filterForumList($forumids)
    {
        return $forumids;
    }

    /**
     * @param array $config
     * @param $view
     * @param JRegistry $params
     *
     * @return string
     */
    function renderActivityModule($config, $view, $params)
    {
        return Text::_('METHOD_NOT_IMPLEMENTED');
    }

	/**
	 * Function that that is used to keep sessions in sync and/or alive
	 *
	 * @param boolean $keepalive    Tells the function to regenerate the inactive session as long as the other is active
	 * unless there is a persistent cookie available for inactive session
	 * @return integer 0 if no session changes were made, 1 if session created
	 */
	function syncSessions($keepalive = false)
	{
		return 0;
	}

	/**
	 * @param array $config
	 * @param $view
	 * @param JRegistry $params
	 *
	 * @return string
	 */
	function renderUserActivityModule($config, $view, $params)
	{
		return Text::_('METHOD_NOT_IMPLEMENTED');
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
	 * @return string online user query
	 */
	function getOnlineUserQuery($usergroups = array())
	{
		return '';
	}

	/**
	 * Returns number of guests
	 *
	 * @return int
	 */
	function getNumberOnlineGuests()
	{
		return 0;
	}

	/**
	 * Returns number of logged in users
	 *
	 * @return int
	 */
	function getNumberOnlineMembers()
	{
		return 0;
	}

	/**
	 * @param array $config
	 * @param $view
	 * @param JRegistry $params
	 *
	 * @return string
	 */
	function renderWhosOnlineModule($config, $view, $params)
	{
		return Text::_('METHOD_NOT_IMPLEMENTED');
	}

	/**
	 * Set the language from Joomla to the integrated software
	 *
	 * @param Userinfo $userinfo - it can be null if the user is not logged for example.
	 *
	 * @throws RuntimeException
	 *
	 * @return array nothing
	 */
	function setLanguageFrontEnd(Userinfo $userinfo = null)
	{
		$status = array('error' => '', 'debug' => '');
		$status['debug'] = Text::_('METHOD_NOT_IMPLEMENTED');
		return $status;
	}

	/************************************************
	 * Functions For JFusion Search Plugin
	 ***********************************************/

	/**
	 * Retrieves the search results to be displayed.  Placed here so that plugins that do not use the database can retrieve and return results
	 * Each result should include:
	 * $result->title = title of the post/article
	 * $result->section = (optional) section of  the post/article (shows underneath the title; example is Forum Name / Thread Name)
	 * $result->text = text body of the post/article
	 * $result->href = link to the content (without this, joomla will not display a title)
	 * $result->browsernav = 1 opens link in a new window, 2 opens in the same window
	 * $result->created = (optional) date when the content was created
	 *
	 * @param string &$text        string text to be searched
	 * @param string &$phrase      string how the search should be performed exact, all, or any
	 * @param JRegistry &$pluginParam custom plugin parameters in search.xml
	 * @param int    $itemid       what menu item to use when creating the URL
	 * @param string $ordering     ordering sent by Joomla: null, oldest, popular, category, alpha, or newest
	 *
	 * @return array of results as objects
	 */
	function getSearchResults(&$text, &$phrase, &$pluginParam, $itemid, $ordering)
	{
		//initialize plugin database
		$db = Factory::getDatabase($this->getJname());
		//get the query used to search
		$query = $this->getSearchQuery($pluginParam);
		//assign specific table columns to title and text
		$columns = $this->getSearchQueryColumns();
		//build the query
		if ($phrase == 'exact') {
			$where = '((LOWER(' . $columns->title . ') LIKE \'%' . $text . '%\') OR (LOWER(' . $columns->text . ') like \'%' . $text . '%\'))';
		} else {
			$words = explode(' ', $text);
			$wheres = array();
			foreach ($words as $word) {
				$wheres[] = '((LOWER(' . $columns->title . ') LIKE \'%' . $word . '%\') OR (LOWER(' . $columns->text . ') like \'%' . $word . '%\'))';
			}
			if ($phrase == 'all') {
				$separator = 'AND';
			} else {
				$separator = 'OR';
			}
			$where = '(' . implode(') ' . $separator . ' (', $wheres) . ')';
		}
		//pass the where clause into the plugin in case it wants to add something
		$this->getSearchCriteria($where, $pluginParam, $ordering);
		$query.= ' WHERE ' . $where;
		//add a limiter if set
		$limit = $pluginParam->get('search_limit', '');
		if (!empty($limit)) {
			$db->setQuery($query, 0, $limit);
		} else {
			$db->setQuery($query);
		}
		$results = $db->loadObjectList();
		//pass results back to the plugin in case they need to be filtered
		$this->filterSearchResults($results, $pluginParam);
		//load the results
		if (is_array($results)) {
			foreach ($results as $result) {
				//add a link
				$href = Framework::routeURL($this->getSearchResultLink($result), $itemid, $this->getJname(), false);
				$result->href = $href;
				//open link in same window
				$result->browsernav = 2;
				//clean up the text such as removing bbcode, etc
				$this->prepareText($result->text, 'search', $pluginParam, $result);
				$this->prepareText($result->title, 'search', $pluginParam, $result);
				$this->prepareText($result->section, 'search', $pluginParam, $result);
			}
		}
		return $results;
	}

	/**
	 * Assigns specific db columns to title and text of content retrieved
	 *
	 * @return object Db columns assigned to title and text of content retrieved
	 */
	function getSearchQueryColumns()
	{
		$columns = new stdClass();
		$columns->title = '';
		$columns->text = '';
		return $columns;
	}

	/**
	 * Generates SQL query for the search plugin that does not include where, limit, or order by
	 *
	 * @param object &$pluginParam custom plugin parameters in search.xml
	 * @return string Returns query string
	 */
	function getSearchQuery(&$pluginParam)
	{
		return '';
	}

	/**
	 * Add on a plugin specific clause;
	 *
	 * @param string &$where reference to where clause already generated by search bot; add on plugin specific criteria
	 * @param object &$pluginParam custom plugin parameters in search.xml
	 * @param string $ordering     ordering sent by Joomla: null, oldest, popular, category, alpha, or newest
	 */
	function getSearchCriteria(&$where, &$pluginParam, $ordering)
	{
	}

	/**
	 * Filter out results from the search ie forums that a user does not have permission to
	 *
	 * @param array &$results object list of search query results
	 * @param object &$pluginParam custom plugin parameters in search.xml
	 */
	function filterSearchResults(&$results, &$pluginParam)
	{
	}

	/**
	 * Returns the URL for a post
	 *
	 * @param mixed $vars mixed
	 *
	 * @return string with URL
	 */
	function getSearchResultLink($vars)
	{
		return '';
	}
}
