<?php
/**
 * This is the jfusion content plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage DiscussionBot
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Factory;

defined('_JEXEC' ) or die('Restricted access' );

/**
 * Load the JFusion framework
 */
jimport('joomla.plugin.plugin');
jimport('joomla.html.pagination');
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
/**
 * ContentPlugin Class for jfusion
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage DiscussionBot
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class plgContentJfusion extends JPlugin
{
	var $params = false;
	var $mode = '';
	var $valid = false;
	var $jname = '';
	var $creationMode = '';
	var $template = 'default';
	/**
	 * @var $article object
	 */
	var $article = null;
	var $output = array();
	var $dbtask = '';
	var $ajax_request = 0;
	var $validity_reason = '';
	var $manual = false;
	var $manual_threadid = 0;
	var $helper = '';

	var $postid = 0;
	var $moderated = 0;

	/**
	 * Constructor
	 *
	 * For php4 compatibility we must not use the __constructor as a constructor for
	 * plugins because func_get_args ( void ) returns a copy of all passed arguments
	 * NOT references. This causes problems with cross-referencing necessary for the
	 * observer design pattern.
	 *
	 * @param object &$subject The object to observe
	 * @param array|object  $params   An array or object that holds the plugin configuration
	 *
	 * @since 1.5
	 * @return void
	 */
	public function plgContentJfusion(&$subject, $params)
	{
		parent::__construct($subject, $params);

		$this->loadLanguage('plg_content_jfusion', JPATH_ADMINISTRATOR);

		//retrieve plugin software for discussion bot
		if ($this->params === false) {
			if (is_array($params)) {
				$this->params = new JRegistry($params['params']);
			} else {
				$this->params = new JRegistry($params->params);
			}
		}

		$this->jname = $this->params->get('jname', false);

		//determine what mode we are to operate in
		if ($this->params->get('auto_create', 0)) {
			$this->mode = ($this->params->get('test_mode', 1)) ? 'test' : 'auto';
		} else {
			$this->mode = 'manual';
		}

		$this->creationMode = $this->params->get('create_thread', 'load');

		//define some constants
		if (!defined('DISCUSSION_TEMPLATE_PATH')) {
			define('DISCUSSBOT_URL_PATH', JUri::root(true) . 'plugins/content/jfusion/discussbot/');

			define('DISCUSSBOT_PATH', JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'jfusion' . DIRECTORY_SEPARATOR . 'discussbot' . DIRECTORY_SEPARATOR);

			//let's first check for customized files in Joomla template directory
			$app = JFactory::getApplication();
			$JoomlaTemplateOverride = JPATH_BASE . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR  . $app->getTemplate() . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . 'plg_content_jfusion' . DIRECTORY_SEPARATOR;
			if (file_exists($JoomlaTemplateOverride)) {
				define('DISCUSSION_TEMPLATE_PATH', $JoomlaTemplateOverride);
				define('DISCUSSION_TEMPLATE_URL', JFusionFunction::getJoomlaURL() . 'templates/' . $app->getTemplate() . '/html/plg_content_jfusion/');
			} else {
				define('DISCUSSION_TEMPLATE_PATH', JPATH_BASE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'jfusion' . DIRECTORY_SEPARATOR . 'discussbot' . DIRECTORY_SEPARATOR . 'tmpl' . DIRECTORY_SEPARATOR . $this->template . DIRECTORY_SEPARATOR);
				define('DISCUSSION_TEMPLATE_URL', JFusionFunction::getJoomlaURL() . 'plugins/content/jfusion/discussbot/tmpl/' . $this->template . '/');
			}
		}

		//load the helper file
		$helper_path = DISCUSSBOT_PATH . 'helper.php';
		include_once $helper_path;
		$this->helper = new JFusionDiscussBotHelper($this->params, $this->mode);

		//set option
		$this->helper->option = JFactory::getApplication()->input->getCmd('option');
	}

	/**
	 * @param $context
	 * @param $article
	 * @param $isNew
	 */
	public function onContentAfterSave($context, $article, $isNew)
	{
		try {
			if (substr($context, -8) == '.article') {
				//check to see if a valid $content object was passed on
				if (!is_object($article)) {
					throw new RuntimeException(JText::_('NO_CONTENT_DATA_FOUND'));
				} else {
					$this->article = $article;
					$this->helper->setArticle($this->article);

					//make sure there is a plugin
					if (!empty($this->jname)) {
						$this->helper->debug('onContentAfterSave called');

						//validate the article
						// changed _validate to pass the $isNew flag, so that it will only check will happen depending on this flag
						$threadinfo = $this->helper->getThreadInfo();
						list($this->valid, $this->validity_reason) = $this->helper->validate($isNew);
						$this->helper->debug('Validity: ' . $this->valid . '; ' . $this->validity_reason);

						//ignore auto mode if the article has been manually plugged
						$manually_plugged = preg_match('/\{jfusion_discuss (.*)\}/U', $this->article->introtext . $this->article->fulltext);

						$this->helper->debug('Checking mode...');
						if ($this->mode == 'auto' && empty($manually_plugged)) {
							$this->helper->debug('In auto mode');
							if ($this->valid) {
								if (($this->creationMode == 'load') ||
									($this->creationMode == 'new' && ($isNew || (!$isNew && $threadinfo->valid))) ||
									($this->creationMode == 'reply' && $threadinfo->valid)) {

									//update/create thread
									$this->helper->checkThreadExists();
								} else {
									$this->helper->debug('Article did not meet requirements to update/create thread');
								}
							} elseif ($this->creationMode == 'new' && $isNew) {
								$this->helper->debug('Failed validity test but creationMode is set to new and this is a new article');

								$publish_up = JFactory::getDate($this->article->publish_up)->toUnix();
								$now = JFactory::getDate('now', JFactory::getConfig()->get('offset'))->toUnix();
								if ($now < $publish_up || !$this->article->state) {
									$this->helper->debug('Article set to be published in the future or is unpublished thus creating an entry in the database so that the thread is created when appropriate.');

									//the publish date is set for the future so create an entry in the
									//database so that the thread is created when the publish date arrives
									$placeholder = new stdClass();
									$placeholder->threadid = 0;
									$placeholder->forumid = 0;
									$placeholder->postid = 0;
									JFusionFunction::updateDiscussionBotLookup($this->article->id, $placeholder, $this->jname);
								}
							}
						} elseif ($this->mode == 'test' && empty($manually_plugged)) {
							//recheck validity without stipulation
							$this->helper->debug('In test mode thus not creating the article');
							$JFusionForum = Factory::getForum($this->jname);
							$content = '<u>' . $this->article->title . '</u><br />';
							if ($threadinfo->valid) {
								$content .= JText::_('DISCUSSBOT_TEST_MODE') . '<img src="' . JFusionFunction::getJoomlaURL() . DISCUSSBOT_URL_PATH . 'images/check.png" style="margin-left:5px;"><br/>';
								if ($threadinfo->published) {
									$content .= JText::_('STATUS') . ': ' . JText::_('INITIALIZED_AND_PUBLISHED') . '<br />';
								} else {
									$content .= JText::_('STATUS') . ': ' . JText::_('INITIALIZED_AND_UNPUBLISHED') . '<br />';
								}
								$content .= JText::_('THREADID') . ': ' . $threadinfo->threadid . '<br />';
								$content .= JText::_('FORUMID') . ': ' . $threadinfo->forumid . '<br />';
								$content .= JText::_('FIRST_POSTID') . ': ' . $threadinfo->postid. '<br />';

								$forumlist = $this->helper->getForumList();
								if (!in_array($threadinfo->forumid, $forumlist)) {
									$content .= '<span style="color:red; font-weight:bold;">' . JText::_('WARNING') . '</span>: ' . JText::_('FORUM_NOT_EXIST') . '<br />';
								}

								$forumthread = $JFusionForum->getThread($threadinfo->threadid);
								if (empty($forumthread)) {
									$content .= '<span style="color:red; font-weight:bold;">' . JText::_('WARNING') . '</span>: ' . JText::_('THREAD_NOT_EXIST') . '<br />';
								}
							} else {
								$valid = ($this->valid) ? JText::_('JYES') : JText::_('JNO');
								if (!$this->valid) {
									$content .= JText::_('DISCUSSBOT_TEST_MODE') . '<img src="' . JFusionFunction::getJoomlaURL() . DISCUSSBOT_URL_PATH . 'images/x.png" style="margin-left:5px;"><br/>';
									$content .= JText::_('VALID') . ': ' . $valid . '<br />';
									$content .= JText::_('INVALID_REASON') . ': ' . $this->validity_reason . '<br />';
								} else {
									$content .= '<b>' . JText::_('DISCUSSBOT_TEST_MODE') . '</b><img src="' . JFusionFunction::getJoomlaURL() . DISCUSSBOT_URL_PATH . 'images/check.png" style="margin-left:5px;2><br/>';
									$content .= JText::_('VALID_REASON') . ': ' . $this->validity_reason . '<br />';
									$content .= JText::_('STATUS') . ': ' . JText::_('UNINITIALIZED_THREAD_WILL_BE_CREATED') . '<br />';
									$forumid = $JFusionForum->getDefaultForum($this->params, $this->article);
									$content .= JText::_('FORUMID') . ': ' . $forumid . '<br />';
									$author = $JFusionForum->getThreadAuthor($this->params, $this->article);
									$content .= JText::_('AUTHORID') . ': ' . $author . '<br />';
								}
							}
							\JFusion\Framework::raiseNotice($content);
						} else {
							$this->helper->debug('In manual mode...checking to see if article has been initialized');
							if ($threadinfo->valid && $threadinfo->published == 1 && $threadinfo->manual == 1) {
								$this->helper->debug('Article has been initialized...updating thread');
								//update thread
								$this->helper->checkThreadExists();
							} else {
								$this->helper->debug('Article has not been initialized');
							}
						}
						$this->helper->debug('onContentAfterSave complete', true);
					}
				}
			}
		} catch (Exception $e) {
			\JFusion\Framework::raiseError($e->getMessage(), JText::_('DISCUSSBOT_ERROR'));
		}
	}

	/**
	 * @param $context
	 * @param $article
	 * @param $params
	 * @param int $limitstart
	 */
	public function onContentPrepare($context, &$article, &$params, $limitstart = 0)
	{
		$this->ajax_request = JFactory::getApplication()->input->getInt('ajax_request', 0);
		$data = $this->prepareJSONResponce();
		try {
			if ($context != 'com_content.featured' && $context != 'com_content.category') {
				//seems syntax has completely changed :(
				$this->article = $article;
				$this->helper->setArticle($this->article);

				//reset some vars
				$this->manual = false;
				$this->manual_threadid = 0;

				$this->validity_reason = '';
				$this->helper->debug('onContentPrepare called');

				//check to see if a valid $content object was passed on
				if (!is_object($this->article)){
					\JFusion\Framework::raiseError(JText::_('NO_CONTENT_DATA_FOUND'), JText::_('DISCUSSBOT_ERROR'));
				} else {
					//make sure there is a plugin
					if (!empty($this->jname)) {
						//do nothing if this is a K2 category object
						if ($this->helper->option == 'com_k2' && get_class($this->article) == 'TableK2Category') {
						} else {
							//set some variables needed throughout
							$this->template = $this->params->get('template', 'default');

							//make sure we have an actual article
							if (!empty($this->article->id)) {
								$this->dbtask = JFactory::getApplication()->input->get('dbtask', null);
								$skip_new_check = ($this->dbtask == 'create_thread') ? true : false;
								$skip_k2_check = ($this->helper->option == 'com_k2' && in_array($this->dbtask, array('unpublish_discussion', 'publish_discussion'))) ? true : false;

								list($this->valid, $this->validity_reason) = $this->helper->validate($skip_new_check, $skip_k2_check);
								$this->helper->debug('Validity: ' . $this->valid . '; ' . $this->validity_reason);

								if ($this->dbtask == 'create_thread') {
									//this article has been manually initiated for discussion
									$this->createThread();
								} elseif (($this->dbtask == 'create_post' || $this->dbtask == 'create_threadpost') && $this->params->get('enable_quickreply', false)) {
									//a quick reply has been submitted so let's create the post
									$this->createPost();
								} elseif ($this->dbtask == 'unpublish_discussion') {
									//an article has been 'uninitiated'
									$this->unpublishDiscussion();
								} elseif ($this->dbtask == 'publish_discussion') {
									//an article has been 'reinitiated'
									$this->publishDiscussion();
									$threadinfo = $this->helper->getThreadInfo();
									if ($threadinfo->valid && $threadinfo->published) {
										//content is now published so display it
										$data->posts = $this->renderDiscussionContent();
									} else {
										$data->posts = null;
									}
									$data->error = false;
								}

								//save the visibility of the posts if applicable
								$show_discussion = JFactory::getApplication()->input->get('show_discussion', '');
								if ($show_discussion !== '') {
									$JSession = JFactory::getSession();
									$JSession->set('jfusion.discussion.visibility', (int)$show_discussion);
								}

								//check for some specific ajax requests
								if ($this->ajax_request) {
									//check to see if this is an ajax call to update the pagination
									if ($this->params->get('show_posts', 1) && $this->dbtask == 'update_posts') {
										$this->updatePosts();
									}  else if ($this->dbtask == 'update_debug_info') {
										$data->error = false;
									} else if ($show_discussion !== '') {
										$data->error = false;
										\JFusion\Framework::raiseNotice('jfusion.discussion.visibility set to ' . $show_discussion);
									} else {
										\JFusion\Framework::raiseError('Discussion bot ajax request made but it doesn\'t seem to have been picked up', JText::_('DISCUSSBOT_ERROR'));
									}
									$this->renderJSONResponce($data);
								}
								//add scripts to header
								$this->helper->loadScripts();

								if (empty($this->article->params) && !empty($this->article->parameters)) {
									$this->article->params = $this->article->parameters;
								}

								if (!empty($this->article->params)) {
									$this->prepareContent();
								}
							}
						}
					}
				}
			}
		} catch (Exception $e) {
			if ($this->ajax_request) {
				echo new JResponseJson(null, JText::_('DISCUSSBOT_ERROR') . ': ' . $e->getMessage(), true);
				exit();
			} else {
				\JFusion\Framework::raiseError($e->getMessage(), JText::_('DISCUSSBOT_ERROR'));
			}
		}
	}

	/**
	 * @param $context
	 * @param $article
	 * @param $params
	 * @param int $limitstart
	 */
	public function onContentAfterDisplay($context, &$article, &$params, $limitstart = 0)
	{
		$view = JFactory::getApplication()->input->get('view');
		$layout = JFactory::getApplication()->input->get('layout');

		if ($this->helper->option == 'com_content') {
			if ($view == 'featured' || ($view == 'category' && $layout == 'blog')) {
				$article->text = $article->introtext;
				$this->onContentPrepare($context, $article, $params, $limitstart);
				$article->introtext = $article->text;
			}
		}
	}

	/**
	 * @return stdClass
	 */
	public function prepareJSONResponce() {
		$data = new stdClass;
		$data->posts = null;
		$data->pagination = null;
		$data->error = true;
		return $data;
	}

	/**
	 * @param stdClass $data
	 */
	public function renderJSONResponce($data) {
		$data->debug = $this->renderDebugOutput();
		$data->buttons = $this->renderButtons(true);
		if ($this->params->get('enable_pagination', 0)) {
			$data->pagination = $this->updatePagination();
		}
//		$data->threadinfo = $this->helper->getThreadInfo(true);

		$data->articleid = $this->article->id;

		$data->postid = $this->postid;
		$data->moderated = $this->moderated;

		echo new JResponseJson($data, null, $data->error);
		exit();
	}

	/**
	 * Returns the view for compare
	 *
	 * @return string
	 */
	public function view() {
		return ($this->helper->option == 'com_k2') ? 'item' : 'article';
	}

	/*
	 * prepareContent
	 */
	public function prepareContent()
	{
		$this->helper->debug('Preparing content');

		$content = '';
		//get the jfusion forum object
		$JFusionForum = Factory::getForum($this->jname);

		//find any {jfusion_discuss...} to manually plug
		$this->helper->debug('Finding all manually added plugs');
		preg_match_all('/\{jfusion_discuss (.*)\}/U', $this->article->text, $matches);
		$this->helper->debug(count($matches[1]) . ' matches found');

		foreach($matches[1] as $id) {
			//only use the first and get rid of the others
			if (empty($this->manual)) {
				$this->manual = true;
				$this->helper->debug('Plugging for thread id ' . $id);
				//get the existing thread information
				$forumthread = $JFusionForum->getThread($id);

				if (!empty($forumthread)) {
					//manually plugged so definitely published
					$forumthread->published = 1;
					//set threadinfo
					$this->helper->setThreadInfo($forumthread);

					$this->helper->debug('Thread info found.');
					$content = $this->render();
					$this->article->text = str_replace('{jfusion_discuss ' . $id . '}', $content, $this->article->text);
				} else {
					$this->helper->debug('Thread info not found!');
					$this->article->text = str_replace('{jfusion_discuss ' . $id . '}', JText::_('THREADID_NOT_FOUND'), $this->article->text);
				}
			} else {
				$this->helper->debug('Removing plug for thread ' . $id);
				$this->article->text = str_replace('{jfusion_discuss ' . $id . '}', '', $this->article->text);
			}
		}

		//check to see if the fulltext has a manual plug if we are in a blog view
		if (isset($this->article->fulltext)) {
			if (!$this->manual && JFactory::getApplication()->input->get('view') != $this->view()) {
				preg_match('/\{jfusion_discuss (.*)\}/U', $this->article->fulltext, $match);
				if (!empty($match)) {
					$this->helper->debug('No plugs in text but found plugs in fulltext');
					$this->manual = true;
					$this->manual_threadid = $match[1];

					//get the existing thread information
					$forumthread = $JFusionForum->getThread($this->manual_threadid);

					if (!empty($forumthread)) {
						//manually plugged so definitely published
						$forumthread->published = 1;
						//create buttons for the manually plugged article
						//set threadinfo
						$this->helper->setThreadInfo($forumthread);
						$content = $this->renderButtons(false);

						//append the content
						$this->article->text .= $content;
					} else {
						$this->article->text .= JText::_('THREADID_NOT_FOUND');
					}
				}
			}
		}

		//check for auto mode if not already manually plugged
		if (!$this->manual) {
			$this->helper->debug('Article not manually plugged...checking for other mode');
			$threadinfo = $this->helper->getThreadInfo();

			//create the thread if this article has been validated
			if ($this->mode == 'auto') {
				$this->helper->debug('In auto mode');
				if ($this->valid) {
					if ($threadinfo->valid || $this->creationMode == 'load' || ($this->creationMode == 'view' && JFactory::getApplication()->input->get('view') == $this->view()) ) {
						$status = $this->helper->checkThreadExists();
						if ($status['action'] == 'created') {
							$threadinfo = $status['threadinfo'];
						}
					}
				}
				if ($this->validity_reason != JText::_('REASON_NOT_IN_K2_ARTICLE_TEXT')) {
					//a catch in case a plugin does something wrong
					if ($threadinfo->threadid || $this->creationMode == 'reply') {
						$content = $this->render();
					}
				}
			} elseif ($this->mode == 'test') {
				$this->helper->debug('In test mode');
				//get the existing thread information
				$content  = '<div class="jfusionclearfix" style="border:1px solid #ECF8FD; background-color:#ECF8FD; margin-top:10px; margin-bottom:10px;">';

				if ($threadinfo->valid) {
					$content .= '<b>' . JText::_('DISCUSSBOT_TEST_MODE') . '</b><img src="' . JFusionFunction::getJoomlaURL() . DISCUSSBOT_URL_PATH . 'images/check.png" style="margin-left:5px;"><br/>';
					if ($threadinfo->published) {
						$content .= JText::_('STATUS') . ': ' . JText::_('INITIALIZED_AND_PUBLISHED') . '<br />';
					} else {
						$content .= JText::_('STATUS') . ': ' . JText::_('INITIALIZED_AND_UNPUBLISHED') . '<br />';
					}
					$content .= JText::_('THREADID') . ': ' . $threadinfo->threadid . '<br />';
					$content .= JText::_('FORUMID') . ': ' . $threadinfo->forumid . '<br />';
					$content .= JText::_('FIRST_POSTID') . ': ' . $threadinfo->postid. '<br />';

					$forumlist = $this->helper->getForumList();
					if (!in_array($threadinfo->forumid, $forumlist)) {
						$content .= '<span style="color:red; font-weight:bold;">' . JText::_('WARNING') . '</span>: ' . JText::_('FORUM_NOT_EXIST') . '<br />';
					}

					$forumthread = $JFusionForum->getThread($threadinfo->threadid);
					if (empty($forumthread)) {
						$content .= '<span style="color:red; font-weight:bold;">' . JText::_('WARNING') . '</span>: ' . JText::_('THREAD_NOT_EXIST') . '<br />';
					}
				} else {
					$valid = ($this->valid) ? JText::_('JYES') : JText::_('JNO');
					if (!$this->valid) {
						$content .= '<b>' . JText::_('DISCUSSBOT_TEST_MODE') . '</b><img src="' . JFusionFunction::getJoomlaURL() . DISCUSSBOT_URL_PATH . 'images/x.png" style="margin-left:5px;"><br/>';
						$content .= JText::_('VALID') . ': ' . $valid . '<br />';
						$content .= JText::_('INVALID_REASON') . ': ' . $this->validity_reason . '<br />';
					} else {
						$content .= '<b>' . JText::_('DISCUSSBOT_TEST_MODE') . '</b><img src="' . JFusionFunction::getJoomlaURL() . DISCUSSBOT_URL_PATH . 'images/check.png" style="margin-left:5px;"><br/>';
						$content .= JText::_('VALID_REASON') . ': ' . $this->validity_reason . '<br />';
						$content .= JText::_('STATUS') . ': ' . JText::_('UNINITIALIZED_THREAD_WILL_BE_CREATED') . '<br />';
						$forumid = $JFusionForum->getDefaultForum($this->params, $this->article);
						$content .= JText::_('FORUMID') . ': ' . $forumid . '<br />';
						$author = $JFusionForum->getThreadAuthor($this->params, $this->article);
						$content .= JText::_('AUTHORID') . ': ' . $author . '<br />';
					}
				}
				$content .= '</div>';
			} elseif ($threadinfo->manual) {
				if ($threadinfo->published) {
					$this->helper->debug('In manual mode but article has been initialized');
					//this article was generated by the initialize button
					$content = $this->render();
				} else {
					$this->helper->debug('In manual mode but article was initialized then uninitialized');
					$content = $this->renderButtons();
				}
			} else {
				$this->helper->debug('In manual mode');
				//in manual mode so just create the buttons
				if ($this->validity_reason != JText::_('REASON_NOT_IN_K2_ARTICLE_TEXT')) {
					$content = $this->renderButtons();
				}
			}

			//append the content
			$this->article->text .= $content;
		}

		$this->renderDebugOutput();
	}

	/**
	 * renderDebugOutput
	 *
	 * @return string
	 */
	public function renderDebugOutput()
	{
		$html = '';
		if ($this->params->get('debug', 0)) {
			if ($this->ajax_request) {
				$html = $this->helper->debugger->getAsHtml(null, false);
			} else {
				$html = $this->helper->debugger->getAsHtml();
				$document = JFactory::getDocument();
				$document->addStyleSheet(JUri::root(true) . '/components/com_jfusion/css/debugger.css');
			}
			if (!$this->ajax_request) {
				$this->article->text .= <<<HTML
                    <div id="jfusionDebugContainer{$this->article->id}">
                        {$html}
                    </div>
HTML;
			}
		}
		return $html;
	}

	/*
	 * createThread
	 */
	public function createThread()
	{
		$JoomlaUser = JFactory::getUser();
		$mainframe = JFactory::getApplication();
		$return = JFactory::getApplication()->input->get('return');
		if ($return) {
			$url = base64_decode($return);
		} else {
			$uri = JUri::getInstance();
			$url = $uri->toString(array('path', 'query', 'fragment'));
			$url = JRoute::_($url, false);
			if ($uri->getVar('view') == 'article') {
				//tak on the discussion jump to
				$url .= '#discussion';

				$JSession = JFactory::getSession();
				$JSession->set('jfusion.discussion.visibility', 1);
			}
		}

		//make sure the article submitted matches the one loaded
		$submittedArticleId = JFactory::getApplication()->input->getInt('articleId', 0);

		$editAccess = $JoomlaUser->authorise('core.edit', 'com_content');

		$data = $this->prepareJSONResponce();

		if ($editAccess) {
			if ($this->valid) {
				if ($submittedArticleId == $this->article->id) {
					$status = $this->helper->checkThreadExists(1);

					if (!empty($status['error'])) {
						\JFusion\Framework::raise('error', $status['error'], JText::_('DISCUSSBOT_ERROR'));
					} else {
						$data->error = false;
						\JFusion\Framework::raiseMessage(JText::sprintf('THREAD_CREATED_SUCCESSFULLY', $this->article->title), JText::_('SUCCESS'));
					}
				} else {
					throw new RuntimeException(JText::_('ARTICLE_MICH_MACH'));
				}
			} else {
				throw new RuntimeException(JText::_('INVALID'));
			}
		} else {
			throw new RuntimeException(JText::_('ACCESS_DENIED'));
		}

		if ($this->ajax_request) {
			$this->renderJSONResponce($data);
		} else {
			$mainframe->redirect($url);
		}
	}

	/*
	 * createPost
	 * @return void
	 */
	public function createPost()
	{
		$data = $this->prepareJSONResponce();
		$JoomlaUser = JFactory::getUser();
		$JFusionForum = Factory::getForum($this->jname);

		//define some variables
		$allowGuests = $this->params->get('quickreply_allow_guests', 0);

		$jumpto = '';
		$url = $this->helper->getArticleUrl($jumpto, '', false);

		//process quick replies
		if (($allowGuests || !$JoomlaUser->guest) && !$JoomlaUser->block) {
			//make sure something was submitted
			$postinfo = new stdClass();
			$postinfo->text = JFactory::getApplication()->input->post->getString('quickReply', '');

			if (!empty($postinfo->text)) {
				$userinfo = new stdClass();
				$userinfo->guest = 1;
				//retrieve the userid from forum software
				if ($allowGuests && $JoomlaUser->guest) {
					$captcha_verification = $JFusionForum->verifyCaptcha($this->params);
				} else {
					$JFusionUser = Factory::getUser($this->jname);
					try {
						$userinfo = $JFusionUser->getUser($JoomlaUser);
						$userinfo->guest = 0;
					} catch (Exception $e) {}
					//we have a user logged in so ignore captcha
					$captcha_verification = true;
				}

				if ($captcha_verification) {
					if ($this->dbtask == 'create_threadpost') {
						$this->helper->checkThreadExists();
					}
					$threadinfo = $this->helper->getThreadInfo();
					//create the post
					if ($threadinfo->valid && $threadinfo->threadid && $threadinfo->forumid) {
						$postinfo->username = JFactory::getApplication()->input->post->getString('guest_username', '');
						$postinfo->name = JFactory::getApplication()->input->post->getString('guest_name', '');
						$postinfo->email = JFactory::getApplication()->input->post->getString('guest_email', '');

						$status = $JFusionForum->createPost($this->params, $threadinfo, $this->article, $userinfo, $postinfo);

						if (!empty($status['error'])) {
							\JFusion\Framework::raise('error', $status['error'], JText::_('DISCUSSBOT_ERROR'));
						} else {
							$threadinfo = $this->helper->getThreadInfo(true);

							//if pagination is set, set $limitstart so that we go to the added post
							if ($this->params->get('enable_pagination', 0)) {
								$replyCount = $JFusionForum->getReplyCount($threadinfo);
								$application = JFactory::getApplication();
								$limit = $application->getUserStateFromRequest('global.list.limit_discuss', 'limit_discuss', 5, 'int');

								if ($this->params->get('sort_posts', 'ASC') == 'ASC') {
									$limitstart = floor(($replyCount-1)/$limit) * $limit;
								} else {
									$limitstart = 0;
								}
								JFactory::getApplication()->input->set('limitstart_discuss', $limitstart);
							}
							$this->helper->output = array();
							$this->helper->output['posts'] = $this->preparePosts();

							//take note of the created post
							$this->postid = $status['postid'];

							$data->posts = $this->helper->renderFile('default_posts.php');
							$data->error = false;

							if (isset($status['post_moderated'])) {
								$this->moderated = $status['post_moderated'];
								$msg = ($this->moderated) ? JText::_('SUCCESSFUL_POST_MODERATED') : JText::_('SUCCESSFUL_POST');
							} else {
								$msg = JText::_('SUCCESSFUL_POST');
							}
							\JFusion\Framework::raiseMessage($msg, JText::_('SUCCESS'));
						}
					} else {
						throw new RuntimeException(JText::_('THREADID_NOT_FOUND'));
					}
				} else {
					throw new RuntimeException(JText::_('CAPTCHA_INCORRECT'));
				}
			} else {
				throw new RuntimeException(JText::_('QUICKEREPLY_EMPTY'));
			}
		} else {
			throw new RuntimeException(JText::_('ACCESS_DENIED'));
		}
		if ($this->ajax_request) {
			$this->renderJSONResponce($data);
		} else {
			$mainframe = JFactory::getApplication();
			$mainframe->redirect($url);
		}
	}

	/*
	 * unpublishDiscussion
	 */
	public function unpublishDiscussion()
	{
		$JoomlaUser = JFactory::getUser();

		//make sure the article submitted matches the one loaded
		$submittedArticleId = JFactory::getApplication()->input->getInt('articleId', 0);
		$editAccess = $JoomlaUser->authorise('core.edit', 'com_content');

		$data = $this->prepareJSONResponce();
		if ($editAccess && $submittedArticleId == $this->article->id) {
			if ($this->valid) {
				if ($submittedArticleId == $this->article->id) {
					$threadinfo = $this->helper->getThreadInfo();

					if ($threadinfo->valid) {
						//created by discussion bot thus update the look up table
						JFusionFunction::updateDiscussionBotLookup($this->article->id, $threadinfo, $this->jname, 0, $threadinfo->manual);
					} else {
						//manually plugged thus remove any db plugin tags
						$db = JFactory::getDBO();
						//retrieve the original text
						$query = $db->getQuery(true)
							->select('`introtext`, `fulltext`')
							->from('#__content')
							->where('id = ' . $this->article->id);

						$db->setQuery($query);
						$texts = $db->loadObject();

						//remove any {jfusion_discuss...}
						$fulltext = preg_replace('/\{jfusion_discuss (.*)\}/U', '', $texts->fulltext, -1, $fullTextCount);
						$introtext = preg_replace('/\{jfusion_discuss (.*)\}/U', '', $texts->introtext, -1, $introTextCount);

						if (!empty($fullTextCount) || !empty($introTextCount)) {
							$query = $db->getQuery(true)
								->update('#__content')
								->set('`fulltext` = ' . $db->quote($fulltext))
								->set('`introtext` = ' . $db->quote($introtext))
								->where('id = ' . (int) $this->article->id);
							$db->setQuery($query);
							$db->execute();
						}
					}
					$data->error = false;
				} else {
					throw new RuntimeException(JText::_('ARTICLE_MICH_MACH'));
				}
			} else {
				throw new RuntimeException(JText::_('INVALID'));
			}
		} else {
			throw new RuntimeException(JText::_('ACCESS_DENIED'));
		}
		if ($this->ajax_request) {
			$this->renderJSONResponce($data);
		} else {
			$mainframe = JFactory::getApplication();
			$mainframe->redirect($this->helper->getArticleUrl('', '', false));
		}
	}

	/*
	 * publishDiscussion
	 */
	public function publishDiscussion()
	{
		$JoomlaUser = JFactory::getUser();

		//make sure the article submitted matches the one loaded
		$submittedArticleId = JFactory::getApplication()->input->getInt('articleId', 0);
		$editAccess = $JoomlaUser->authorise('core.edit', 'com_content');

		$data = $this->prepareJSONResponce();
		if ($editAccess) {
			if ($this->valid) {
				if ($submittedArticleId == $this->article->id) {
					$threadinfo = $this->helper->getThreadInfo();
					JFusionFunction::updateDiscussionBotLookup($this->article->id, $threadinfo, $this->jname, 1, $threadinfo->manual);

					$data->error = false;
				} else {
					throw new RuntimeException(JText::_('ARTICLE_MICH_MACH'));
				}
			} else {
				throw new RuntimeException(JText::_('INVALID'));
			}
		} else {
			throw new RuntimeException(JText::_('ACCESS_DENIED'));
		}

		if ($this->ajax_request) {
			$this->renderJSONResponce($data);
		} else {
			$mainframe = JFactory::getApplication();
			$mainframe->redirect($this->helper->getArticleUrl('', '', false));
		}
	}

	/**
	 * @return bool|string
	 */
	public function render()
	{
		$this->helper->debug('Beginning rendering content');
		$threadinfo = $this->helper->getThreadInfo();

		$view = JFactory::getApplication()->input->get('view');
		//let's only show quick replies and posts on the article view
		if ($view == $this->view()) {
			$JSession = JFactory::getSession();

			if (!$threadinfo->published && $this->creationMode != 'reply') {
				$this->helper->debug('Discussion content not displayed as this discussion is unpublished');
				$display = 'none';
				$generate_guts = false;
			} else {
				if ($JSession->get('jfusion.discussion.visibility', 0) || (!$threadinfo->valid && $this->creationMode == 'reply')) {
					//show the discussion area if no replies have been made and creationMode is set to on first reply OR if user has set it to show
					$display = 'block';
				} else {
					$display = ($this->params->get('show_toggle_posts_link', 1) && $this->params->get('collapse_discussion', 1)) ? 'none' : 'block';
				}
				$generate_guts = true;
			}
			if ($display == 'none') {
				$JSession->set('jfusion.discussion.visibility', 0);
			} else {
				$JSession->set('jfusion.discussion.visibility', 1);
			}

			$content = '<div style="float:none; display:' . $display . ';" id="discussion">';

			if ($generate_guts) {
				$content .= $this->renderDiscussionContent();
			}

			$content .= '</div>';
			//now generate the buttons in case the thread was just created
			$button_content  = $this->renderButtons();
			$content = $button_content . $content;
		} else {
			$content = $this->renderButtons();
		}

		return $content;
	}


	/**
	 * @return bool|string
	 */
	public function renderDiscussionContent()
	{
		$this->helper->debug('Rendering discussion content');
		$threadinfo = $this->helper->getThreadInfo();

		//setup parameters
		$JFusionForum = Factory::getForum($this->jname);
		$allowGuests = $this->params->get('quickreply_allow_guests', 0);
		$JoomlaUser = JFactory::getUser();
		//make sure the user exists in the software before displaying the quick reply
		$JFusionUser = Factory::getUser($this->jname);
		$JFusionUserinfo = $JFusionUser->getUser($JoomlaUser);
		$action_url = $this->helper->getArticleUrl();
		$this->helper->output = array();

		$show_form = ($allowGuests || (!$JoomlaUser->guest && !empty($JFusionUserinfo)) && !$JoomlaUser->block) ? 1 : 0;

		$this->helper->output['post_pagination'] = '';
		$this->helper->output['posts'] = '';
		$this->helper->output['reply_form'] = '';
		$this->helper->output['reply_form_error'] = '';
		if ($threadinfo->valid) {
			//prepare quick reply box if enabled
			if ($this->params->get('enable_quickreply')){
				$threadLocked = $JFusionForum->getThreadLockedStatus($threadinfo->threadid);
				if ($threadLocked) {
					$this->helper->output['reply_form_error'] = $this->params->get('locked_msg');
				} elseif ($show_form) {
					if (!$JoomlaUser->guest && empty($JFusionUserinfo)) {
						$this->helper->output['reply_form_error'] =  $this->jname . ': ' . JText::_('USER_NOT_EXIST');
					} else {
						$showGuestInputs = ($allowGuests && $JoomlaUser->guest) ? true : false;

						$limitstart = JFactory::getApplication()->input->getInt('limitstart', 0);
						if ($limitstart) {
							$limitstart = '<input type="hidden" name="limitstart" value="' . $limitstart . '" />';
						} else {
							$limitstart = '';
						}
						$showall = JFactory::getApplication()->input->getInt('showall', 0);
						if ($showall) {
							$showall = '<input type="hidden" name="limitstart" value="' . $showall . '" />';
						} else {
							$showall = '';
						}

						$form = $JFusionForum->createQuickReply($this->params, $showGuestInputs);
						$submit = JText::_('SUBMIT');

						$this->helper->output['reply_form'] =<<<HTML
						<form id="jfusionQuickReply{$this->article->id}" name="jfusionQuickReply{$this->article->id}" method="post" action="{$action_url}">
							<input type="hidden" name="dbtask" value="create_post" />
							{$limitstart}
							{$showall}
							{$form}
							<div style="width:99%; text-align:right;">
			                    <input type="submit" class="button" id="submitpost" onclick="return JFusion.submitReply('{$this->article->id}');" value="{$submit}"/>
		                    </div>
						</form>
HTML;
					}
				} else {
					$this->helper->output['reply_form_error'] = $this->params->get('must_login_msg');
				}
			}

			//add posts to content if enabled
			if ($this->params->get('show_posts')) {
				$this->helper->output['posts'] = $this->preparePosts();

				if ($this->params->get('enable_pagination', 0)) {
					$this->helper->output['post_pagination'] = $this->updatePagination(true);
				}
			}
		} elseif ($this->creationMode == 'reply') {
			//prepare quick reply box if enabled
			if ($show_form) {
				if (!$JoomlaUser->guest && empty($JFusionUserinfo)) {
					$this->helper->output['reply_form_error'] =  $this->jname . ': ' . JText::_('USER_NOT_EXIST');
				} else {
					$showGuestInputs = ($allowGuests && $JoomlaUser->guest) ? true : false;

					$limitstart = JFactory::getApplication()->input->getInt('limitstart', 0);
					if ($limitstart) {
						$limitstart = '<input type="hidden" name="limitstart" value="' . $limitstart . '" />';
					} else {
						$limitstart = '';
					}
					$showall = JFactory::getApplication()->input->getInt('showall', 0);
					if ($showall) {
						$showall = '<input type="hidden" name="limitstart" value="' . $showall . '" />';
					} else {
						$showall = '';
					}

					$form = $JFusionForum->createQuickReply($this->params, $showGuestInputs);
					$submit = JText::_('SUBMIT');

					$this->helper->output['reply_form'] =<<<HTML
						<form id="jfusionQuickReply{$this->article->id}" name="jfusionQuickReply{$this->article->id}" method="post" action="{$action_url}">
							<input type="hidden" name="dbtask" value="create_threadpost" />
							{$limitstart}
							{$showall}
							{$form}
							<div style="width:99%; text-align:right;">
			                    <input type="submit" class="button" id="submitpost" onclick="return JFusion.submitReply('{$this->article->id}');" value="{$submit}"/>
		                    </div>
						</form>
HTML;
				}
			} else {
				$this->helper->output['reply_form_error'] = $this->params->get('must_login_msg');
			}
		}

		//populate the template
		$content = $this->helper->renderFile('default.php');
		return $content;
	}

	/**
	 * @param bool $innerhtml
	 *
	 * @return bool|string
	 */
	public function renderButtons($innerhtml = false)
	{
		$this->helper->debug('Rendering buttons');

		try {
			//setup some variables
			$threadinfo = $this->helper->getThreadInfo();

			$JUser = JFactory::getUser();
			$itemid = $this->params->get('itemid');
			$link_text = $this->params->get('link_text');
			$link_type= $this->params->get('link_type', 'text');
			$link_mode= $this->params->get('link_mode', 'always');
			$blog_link_mode= $this->params->get('blog_link_mode', 'forum');
			$linkHTML = ($link_type == 'image') ? '<img style="border:0;" src="' . $link_text . '">' : $link_text;
			if($this->params->get('show_reply_num')) {
				$post = ($this->helper->replyCount == 1) ? 'REPLY' : 'REPLIES';
				if ($linkHTML) {
					$linkHTML .= ' ';
				}
				$linkHTML .= '[' . $this->helper->replyCount . ' ' . JText::_($post) . ']';
			}
			$linkTarget = $this->params->get('link_target', '_parent');

			if ($this->helper->option == 'com_content') {
				$article_access = $this->article->params->get('access-view');
			} elseif ($this->helper->option == 'com_k2') {
				$article_access = (in_array($this->article->access, $JUser->getAuthorisedViewLevels()) && in_array($this->article->category->access, $JUser->getAuthorisedViewLevels()));
			} else {
				$article_access = 1;
			}

			//prevent notices and warnings in default_buttons.php if there are no buttons to display
			$this->helper->output = array();
			$this->helper->output['buttons'] = array();
			/**
			 * @ignore
			 * @var $article_params JRegistry
			 */
			$show_readmore = $readmore_catch = 0;
			$readmore_param = null;
			if ($this->helper->option == 'com_content') {
				if (isset($this->article->params)) {
					//blog view
					$article_params = $this->article->params;
					$readmore_catch = $show_readmore = $article_params->get('show_readmore');
				} elseif (isset($this->article->parameters)) {
					//article view
					$article_params = $this->article->parameters;
					$readmore_catch = JFactory::getApplication()->input->getInt('readmore');
					$override = JFactory::getApplication()->input->getInt('show_readmore', false);
					$show_readmore = ($override !== false) ? $override : $article_params->get('show_readmore');
				}
				$readmore_param = 'show_readmore';
			} elseif ($this->helper->option == 'com_k2' && JFactory::getApplication()->input->get('view') == 'itemlist') {
				$article_params = $this->article->params;
				$layout = JFactory::getApplication()->input->get('layout');
				if ($layout == 'category') {
					$readmore_param = 'catItemReadMore';
				} elseif ($layout == 'user') {
					$readmore_param = 'userItemReadMore';
				} else {
					$readmore_param = 'genericItemReadMore';
				}
				$show_readmore = $readmore_catch = $article_params->get($readmore_param);
			}

			//let's overwrite the read more link with our own
			//needed as in the case of updating the buttons via ajax which calls the article view
			$view = ($override = JFactory::getApplication()->input->get('view_override')) ? $override : JFactory::getApplication()->input->get('view');
			if ($view != $this->view() && $this->params->get('overwrite_readmore', 1)) {
				//make sure the read more link is enabled for this article

				if (!empty($show_readmore) && !empty($readmore_catch)) {
					if ($article_access) {
						$readmore_link = $this->helper->getArticleUrl();
						if ($this->helper->option == 'com_content') {
							if (!empty($this->article->alternative_readmore)) {
								$readmore = $this->article->alternative_readmore;
								if ($this->article->params->get('show_readmore_title', 0) != 0) {
									$readmore.= JHtml::_('string.truncate', ($this->article->title), $this->article->params->get('readmore_limit'));
								}
							} elseif ($this->article->params->get('show_readmore_title', 0) == 0) {
								$readmore = JText::_('READ_MORE');
							} else {
								$readmore = JText::_('READ_MORE') . ': ';
								$readmore.= JHtml::_('string.truncate', ($this->article->title), $this->article->params->get('readmore_limit'));
							}
						}
						if (!empty($readmore)) {
							$readmore_text = $readmore;
						} else {
							$readmore_text = JText::_('READ_MORE');
						}
					} else {
						$return_url = base64_encode($this->helper->getArticleUrl());
						$readmore_link = JRoute::_('index.php?option=com_users&view=login&return=' . $return_url);
						$readmore_text = JText::_('READ_MORE_REGISTER');
					}

					$this->helper->output['buttons']['readmore']['href'] = $readmore_link;
					$this->helper->output['buttons']['readmore']['text'] = $readmore_text;
					$this->helper->output['buttons']['readmore']['target'] = '_self';

					//set it so that Joomla does not show its read more link
					if (isset($this->article->readmore)) {
						$this->article->readmore = 0;
					}

					//hide the articles standard read more
					if ($readmore_param && $article_params) {
						$article_params->set($readmore_param, 0);
					}
				}
			}

			//create a link to manually create the thread if it is not already
			$show_button = $this->params->get('enable_initiate_buttons', false);

			if ($show_button && empty($this->manual)) {
				$user   = JFactory::getUser();
				$editAccess = $user->authorise('core.edit', 'com_content');
				if ($editAccess) {
					if ($threadinfo->valid) {
						if ($threadinfo->published) {
							//discussion is published
							$dbtask = 'unpublish_discussion';
							$text = 'UNINITIATE_DISCUSSION';
						} else {
							//discussion is unpublished
							$dbtask = 'publish_discussion';
							$text = 'INITIATE_DISCUSSION';
						}
					} else {
						//discussion is uninitiated
						$dbtask = 'create_thread';
						$text = 'INITIATE_DISCUSSION';
					}

					$this->helper->output['buttons']['initiate']['href'] = 'javascript: void(0);';

					$vars  = '&view_override=' . $view;
					$vars .= ($this->params->get('overwrite_readmore', 1)) ? '&readmore=' . $readmore_catch . '&show_readmore=' . $show_readmore : '';

					$this->helper->output['buttons']['initiate']['js']['onclick'] = 'JFusion.confirmThreadAction(' . $this->article->id . ',\'' . $dbtask . '\', \'' . $vars . '\');';
					$this->helper->output['buttons']['initiate']['text'] = JText::_($text);
					$this->helper->output['buttons']['initiate']['target'] = '_self';
				}
			}

			if($view == $this->view() && $this->params->get('show_posts') && $this->params->get('show_refresh_link', 1) && $threadinfo->published) {
				$this->helper->output['buttons']['refresh']['href'] = 'javascript:void(0);';
				$this->helper->output['buttons']['refresh']['js']['onclick'] = 'JFusion.refreshPosts(' . $this->article->id . ');';
				$this->helper->output['buttons']['refresh']['text'] = JText::_('REFRESH_POSTS');
				$this->helper->output['buttons']['refresh']['target'] = $linkTarget;
			}

			//create the discuss this link
			if ($threadinfo->valid || $this->manual) {
				if ($link_mode != 'never') {
					$JFusionForum = Factory::getForum($this->jname);

					if ($view == $this->view()) {
						if ($link_mode == 'article' || $link_mode == 'always') {
							$this->helper->output['buttons']['discuss']['href'] = \JFusion\Framework::routeURL($JFusionForum->getThreadURL($threadinfo->threadid), $itemid, $this->jname);
							$this->helper->output['buttons']['discuss']['text'] = $linkHTML;
							$this->helper->output['buttons']['discuss']['target'] = $linkTarget;

							if ($this->params->get('enable_comment_in_forum_button', 0)) {
								$commentLinkText = $this->params->get('comment_in_forum_link_text', JText::_('ADD_COMMENT'));
								$commentLinkHTML = ($this->params->get('comment_in_forum_link_type') == 'image') ? '<img style="border:0;" src="' . $commentLinkText . '">' : $commentLinkText;
								$this->helper->output['buttons']['comment_in_forum']['href'] = \JFusion\Framework::routeURL($JFusionForum->getReplyURL($threadinfo->forumid, $threadinfo->threadid), $itemid, $this->jname);
								$this->helper->output['buttons']['comment_in_forum']['text'] = $commentLinkHTML;
								$this->helper->output['buttons']['comment_in_forum']['target'] = $linkTarget;
							}
						}
					} elseif ($link_mode == 'blog' || $link_mode == 'always') {
						if ($blog_link_mode == 'joomla') {
							//see if there are any page breaks
							$joomla_text = (isset($this->article->fulltext)) ? $this->article->fulltext : $this->article->text;
							$pagebreaks = substr_count($joomla_text, 'system-pagebreak');
							$query = ($pagebreaks) ? '&limitstart=' . $pagebreaks : '';
							if ($article_access) {
								$discuss_link = $this->helper->getArticleUrl('discussion', $query);
							} else {
								$return_url = base64_encode($this->helper->getArticleUrl('discussion', $query));
								$discuss_link = JRoute::_('index.php?option=com_user&view=login&return=' . $return_url);
							}
							$this->helper->output['buttons']['discuss']['href'] = 'javascript: void(0);';
							$this->helper->output['buttons']['discuss']['js']['onclick'] = 'JFusion.toggleDiscussionVisibility(' . $this->article->id . ', \'' . $discuss_link . '\');';
							$this->helper->output['buttons']['discuss']['target'] = '_self';
						} else {
							$this->helper->output['buttons']['discuss']['href'] = \JFusion\Framework::routeURL($JFusionForum->getThreadURL($threadinfo->threadid), $itemid, $this->jname);
							$this->helper->output['buttons']['discuss']['target'] = $linkTarget;
						}

						$this->helper->output['buttons']['discuss']['text'] = $linkHTML;

						if ($this->params->get('enable_comment_in_forum_button', 0)) {
							$commentLinkText = $this->params->get('comment_in_forum_link_text', JText::_('ADD_COMMENT'));
							$commentLinkHTML = ($this->params->get('comment_in_forum_link_type') == 'image') ? '<img style="border:0;" src="' . $commentLinkText . '">' : $commentLinkText;
							$this->helper->output['buttons']['comment_in_forum']['href'] = \JFusion\Framework::routeURL($JFusionForum->getReplyURL($threadinfo->forumid, $threadinfo->threadid), $itemid, $this->jname);
							$this->helper->output['buttons']['comment_in_forum']['text'] = $commentLinkHTML;
							$this->helper->output['buttons']['comment_in_forum']['target'] = $linkTarget;
						}
					}
				}

				//show comments link
				if ($view == $this->view() && $this->params->get('show_posts') && $this->params->get('show_toggle_posts_link', 1) && $threadinfo->published) {
					$this->helper->output['buttons']['showreplies']['href'] = 'javascript: void(0);';
					$this->helper->output['buttons']['showreplies']['js']['onclick'] = 'JFusion.toggleDiscussionVisibility(' . $this->article->id . ');';

					$JSession = JFactory::getSession();
					$show_replies = $JSession->get('jfusion.discussion.visibility', 0);
					$text = (empty($show_replies)) ? 'SHOW_REPLIES' : 'HIDE_REPLIES';

					$this->helper->output['buttons']['showreplies']['text'] = JText::_($text);
					$this->helper->output['buttons']['showreplies']['target'] = '_self';
				}
			}

			if ($innerhtml) {
				$button_output = $this->helper->renderFile('default_buttons.php');
			} else {
				$button_output = <<<HTML
                <div class="jfusionclearfix" id="jfusionButtonArea{$this->article->id}">
                    {$this->helper->renderFile('default_buttons.php')}
                </div>
                <div class="jfusionclearfix jfusionButtonConfirmationBox" style="display: none;" id="jfusionButtonConfirmationBox{$this->article->id}">
                </div>
HTML;
			}
		} catch(Exception $e) {
			\JFusion\Framework::raiseError($e);
			$button_output = $e->getMessage();
		}
		return $button_output;
	}

	/**
	 * @return array|string
	 */
	public function preparePosts()
	{
		$post_output = array();

		$JFusionForum = Factory::getForum($this->jname);
		$threadinfo = $this->helper->getThreadInfo();

		$sort = $this->params->get('sort_posts', 'ASC');
		if ($this->params->get('enable_pagination', true)) {
			$application = JFactory::getApplication() ;
			$limit = (int)$application->getUserStateFromRequest('global.list.limit_discuss', 'limit_discuss', 5, 'int');
			$start = (int)$application->getUserStateFromRequest('global.list.limitstart_discuss', 'limitstart_discuss', 0, 'int');
		} else {
			$start = 0;
			$limit = (int)trim($this->params->get('limit_posts', 0));
		}

		if ($limit == 0) {
			$start = 0;
		}

		$posts = $JFusionForum->getPosts($this->params, $threadinfo, (int)$start, (int)$limit, $sort);

		$this->helper->debug('Preparing posts output');

		//get required params
		defined('_DATE_FORMAT_LC2') or define('_DATE_FORMAT_LC2', 'Y M d h:i:s A');
		$date_format = $this->params->get('custom_date', _DATE_FORMAT_LC2);
		$showdate = intval($this->params->get('show_date'));
		$showuser = intval($this->params->get('show_user'));
		$showavatar = $this->params->get('show_avatar');
		$avatar_software = $this->params->get('avatar_software', false);
		$resize_avatar = $this->params->get('avatar_keep_proportional', false);
		$userlink = intval($this->params->get('user_link'));
		$link_software = $this->params->get('userlink_software', false);
		$userlink_custom = $this->params->get('userlink_custom', false);
		$character_limit = (int) $this->params->get('character_limit');
		$itemid = $this->params->get('itemid');
		$JFusionPublic = Factory::getFront($this->jname);

		$JFusionForum = Factory::getForum($this->jname);
		$columns = $JFusionForum->getDiscussionColumns();
		if (empty($columns)) return '';

		for ($i=0; $i<count($posts); $i++) {
			$p = $posts[$i];
			$userid = $p->{$columns->userid};
			$username = ($this->params->get('display_name') && isset($p->{$columns->name})) ? $p->{$columns->name} : $p->{$columns->username};
			$dateline = $p->{$columns->dateline};
			$posttext = $p->{$columns->posttext};
			$posttitle = $p->{$columns->posttitle};
			$postid = $p->{$columns->postid};
			$threadid = $p->{$columns->threadid};
			$guest = $p->{$columns->guest};
			$threadtitle = (isset($columns->threadtitle)) ? $p->{$columns->threadtitle} : '';

			$post_output[$i] = new stdClass();
			$post_output[$i]->postid = $postid;
			$post_output[$i]->guest = $guest;

			//get Joomla id
			$userlookup = \JFusion\Framework::lookupUser($JFusionForum->getJname(), $userid, false, $p->{$columns->username});

			//avatar
			if ($showavatar){
				if (!empty($avatar_software) && $avatar_software != 'jfusion' && !empty($userlookup)) {
					$post_output[$i]->avatar_src = \JFusion\Framework::getAltAvatar($avatar_software, $userlookup);
				} else {
					$post_output[$i]->avatar_src = $JFusionForum->getAvatar($userid);
				}

				if (empty($post_output[$i]->avatar_src)) {
					$post_output[$i]->avatar_src = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
				}

				$size = ($resize_avatar) ? \JFusion\Framework::getImageSize($post_output[$i]->avatar_src) : false;
				$maxheight = $this->params->get('avatar_height', 80);
				$maxwidth = $this->params->get('avatar_width', 60);
				//size the avatar to fit inside the dimensions if larger
				if ($size !== false && ($size->width > $maxwidth || $size->height > $maxheight)) {
					$wscale = $maxwidth/$size->width;
					$hscale = $maxheight/$size->height;
					$scale = min($hscale, $wscale);
					$post_output[$i]->avatar_width = floor($scale*$size->width);
					$post_output[$i]->avatar_height = floor($scale*$size->height);
				} elseif ($size !== false) {
					//the avatar is within the limits
					$post_output[$i]->avatar_width = $size->width;
					$post_output[$i]->avatar_height = $size->height;
				} else {
					//getimagesize failed
					$post_output[$i]->avatar_width = $maxwidth;
					$post_output[$i]->avatar_height = $maxheight;
				}
			} else {
				$post_output[$i]->avatar_src = '';
				$post_output[$i]->avatar_height = '';
				$post_output[$i]->avatar_width = '';
			}

			//post title
			$post_output[$i]->subject_url = \JFusion\Framework::routeURL($JFusionForum->getPostURL($threadid, $postid), $itemid);
			if (!empty($posttitle)) {
				$post_output[$i]->subject = $posttitle;
			} elseif (!empty($threadtitle)) {
				$post_output[$i]->subject = 'Re: ' . $threadtitle;
			} else {
				$post_output[$i]->subject = JText::_('NO_SUBJECT');
			}

			//user info
			if ($showuser) {
				$post_output[$i]->username_url = '';
				if ($userlink && empty($guest) && !empty($userlookup)) {
					if ($link_software == 'custom' && !empty($userlink_custom)  && !empty($userlookup)) {
						$post_output[$i]->username_url = $userlink_custom . $userlookup->id;
					} else {
						$post_output[$i]->username_url = \JFusion\Framework::routeURL($JFusionForum->getProfileURL($userid), $itemid);
					}
				}
				$post_output[$i]->username = $username;
			} else {
				$post_output[$i]->username = '';
				$post_output[$i]->username_url  = '';
			}

			//post date
			if ($showdate){
				jimport('joomla.utilities.date');
				$JDate =  new JDate($dateline);
				$JDate->setTimezone(new DateTimeZone(JFusionFunction::getJoomlaTimezone()));
				$post_output[$i]->date = $JDate->format($date_format, true);
			} else {
				$post_output[$i]->date = '';
			}

			//post body
			$post_output[$i]->text = $posttext;
			$status = $JFusionPublic->prepareText($post_output[$i]->text, 'joomla', $this->params, $p);
			$original_text = '[quote="' . $username . '"]' . "\n" . $posttext . "\n" . '[/quote]';
			$post_output[$i]->original_text = $original_text;
			$JFusionPublic->prepareText($post_output[$i]->original_text, 'discuss', $this->params, $p);

			//apply the post body limit if there is one
			if (!empty($character_limit) && empty($status['limit_applied']) && JString::strlen($post_output[$i]->text) > $character_limit) {
				$post_output[$i]->text = JString::substr($post_output[$i]->text, 0, $character_limit) . '...';
			}

			$toolbar = array();
			if ($this->params->get('enable_quickreply')) {
				$JoomlaUser = JFactory::getUser();
				if ($this->params->get('quickreply_allow_guests', 0) || !$JoomlaUser->guest) {
					$toolbar[] = '<a href="javascript:void(0);" onclick="JFusion.quote(' . $postid . ');">' . JText::_('QUOTE') . '</a>';
				}
			}

			if (!empty($toolbar)) {
				$post_output[$i]->toolbar = '| ' . implode(' | ', $toolbar) . ' |';
			} else {
				$post_output[$i]->toolbar = '';
			}
		}
		return $post_output;
	}

	/**
	 * updatePagination
	 *
	 * @param bool $xhtml
	 *
	 * @return string
	 */
	public function updatePagination($xhtml = false)
	{
		$action_url = $this->helper->getArticleUrl('', '', $xhtml);
		$application = JFactory::getApplication();

		$limit = (int) $application->getUserStateFromRequest('global.list.limit_discuss', 'limit_discuss', 5, 'int');
		$limitstart = (int) $application->getUserStateFromRequest('global.list.limitstart_discuss', 'limitstart_discuss', 0, 'int');

		if ($this->helper->replyCount && $this->helper->replyCount > 5) {
			$pageNav = new JFusionPagination($this->helper->replyCount, $limitstart, $limit, $this->article->id, '_discuss');
			$footer = $pageNav->getListFooter();

			$pagination =<<<HTML
				<form method="post" id="jfusionPaginationForm" name="jfusionPaginationForm" action="{$action_url}">
					{$footer}
				</form>
HTML;
		} else {
			$pagination = '';
		}
		return $pagination;
	}

	/*
	 * updatePosts
	 */
	public function updatePosts()
	{
		$data = $this->prepareJSONResponce();

		$threadinfo = $this->helper->getThreadInfo();
		if ($threadinfo->published) {
			if ($threadinfo->threadid) {
				$this->helper->output = array();
				$this->helper->output['posts'] = $this->preparePosts();
				$data->posts = $this->helper->renderFile('default_posts.php');
				$data->error = false;
			} else {
				throw new RuntimeException(JText::_('NO_THREADID'));
			}
		} else {
			throw new RuntimeException(JText::_('NOT_PUBLISHED'));
		}
		$this->renderJSONResponce($data);
	}
}