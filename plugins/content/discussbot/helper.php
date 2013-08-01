<?php
/**
 * This is the jfusion content plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage DiscussionBot Helper File
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * ContentPlugin Helper Class for jfusion
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage DiscussionBot
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionDiscussBotHelper {
	var $article;
	/**
	 * @var JRegistry $params
	 */
	var $params;
	var $jname;
	var $mode;
	var $threadinfo = array();
	var $debug = array();
	var $output;
	var $replyCount = 0;
	var $option;
	var $isJ16;

	/**
	 * @param JRegistry $params
	 * @param $mode
	 */
	public function __construct(&$params, $mode) {
		$this->params = $params;
		$this->jname = $this->params->get('jname', false);
		$this->mode = $mode;
		//needed for category support
		jimport('joomla.application.categories');
	}

	/**
	 * @param mixed $article

	 * @return void
	 */
	public function setArticle($article)
	{
		$this->article = $article;

		if (isset($this->article->id)) {
			$session = JFactory::getSession();
			$this->debug = $session->get('jfusion.discussion.debug.' . $this->article->id, false);
			if ($this->debug == false) {
				$this->debug = array();
			}
			$session->clear('jfusion.discussion.debug.' . $this->article->id);
		}
	}

	/**
	 * @param bool $update
	 *
	 * @return stdClass
	 */
	public function getThreadInfo($update = false)
	{
		if (isset($this->article->id)) {
			if (!isset($this->threadinfo[$this->article->id]) || $update) {
				$db = JFactory::getDBO();
				$query = 'SELECT * FROM #__jfusion_discussion_bot WHERE contentid = \''.$this->article->id.'\' AND jname = \''.$this->jname.'\' AND component = '.$db->Quote($this->option);
				$db->setQuery($query);
				$threadinfo = $this->setThreadInfo($db->loadObject());
			} else {
				$threadinfo = $this->threadinfo[$this->article->id];
			}
		} else {
			$threadinfo = $this->setThreadInfo(null);
		}
		return $threadinfo;
	}

	/**
	 * @param object $threadinfo
	 *
	 * @return stdClass
	 */
	public function setThreadInfo($threadinfo)
	{
		$this->replyCount = 0;
		if ($threadinfo) {
			$threadinfo->valid = false;
			//make sure the forum and thread still exists
			$Forum = JFusionFactory::getForum($this->jname);

			$forumlist = $this->getForumList();
			if (in_array($threadinfo->forumid, $forumlist)) {
				$forumthread = $Forum->getThread($threadinfo->threadid);
				if ($forumthread) {
					$this->replyCount = $Forum->getReplyCount($forumthread);
					//seems the thread is now missing
					$threadinfo->valid = true;
				}
			}
		} else {
			$threadinfo = new stdClass();
			$threadinfo->threadid = 0;
			$threadinfo->forumid = 0;
			$threadinfo->postid = 0;
			$threadinfo->manual = false;
			$threadinfo->valid = false;
			$threadinfo->published = false;
		}
		if (isset($this->article->id)) {
			$this->threadinfo[$this->article->id] = $threadinfo;
		}
		return $threadinfo;
	}

	/**
	 * @param int $force_new
	 * @return array
	 */
	public function checkThreadExists($force_new = 0)
	{
		$this->debug('Checking if thread exists');

		$JFusionForum = JFusionFactory::getForum($this->jname);

		if ($force_new) {
			$threadinfo = $this->setThreadInfo(null);
			$manually_created = 1;
		} else {
			$threadinfo = $this->getThreadInfo();
			$manually_created = (empty($threadinfo->manual)) ? 0 : 1;
		}

		$status = array('error' => array(),'debug' => array());
		$status['action'] = 'unchanged';
		$status['threadinfo'] = new stdClass();

		$JFusionForum->checkThreadExists($this->params, $this->article, $threadinfo, $status);
		if (!empty($status['error'])) {
			JFusionFunction::raiseNotices($status['error'], $this->jname. ' '. JText::_('FORUM') . ' ' .JText::_('UPDATE'));
		} else {
			if ($status['action']!='unchanged') {
				if ($status['action'] == 'created') {
					$threadinfo = $status['threadinfo'];
				}

				//catch in case plugins screwed up
				if (!empty($threadinfo->threadid)) {
					//update the lookup table
					JFusionFunction::updateDiscussionBotLookup($this->article->id, $threadinfo, $this->jname, 1, $manually_created);

					//set the status to true since it was just created
				}
			}
		}
		$this->setThreadInfo($threadinfo);

		$this->debug($status, $force_new);

		return $status;
	}


	/**
	 * @param string $jumpto
	 * @param string $query
	 * @param bool $xhtml
	 * @return string|The
	 */
	public function getArticleUrl($jumpto = '', $query = '', $xhtml = true)
	{
		//make sure Joomla content helper is loaded
		if (!class_exists('ContentHelperRoute')) {
			require_once JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_content' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'route.php';
		}
		if ($this->option == 'com_k2') {
			if (!class_exists('K2HelperRoute')) {
				include_once JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_k2' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'route.php';
			}
		}

		if ($this->option == 'com_content') {
			//take into account page breaks
			$url = ContentHelperRoute::getArticleRoute($this->article->slug, $this->article->catid);
			$start = JFactory::getApplication()->input->getInt('start',0);
			if ($start) {
				$url .= '&start='.$start;
			}
			$limitstart = JFactory::getApplication()->input->getInt('limitstart',0);
			if ($limitstart) {
				$url .= '&limitstart='.$limitstart;
			}
			$url .= $query;
		} else {
			$url = urldecode(K2HelperRoute::getItemRoute($this->article->id.':'.urlencode($this->article->alias),$this->article->catid.':'.urlencode($this->article->category->alias)));
		}


		$url = JRoute::_($url, $xhtml);

		if (!empty($jumpto)) {
			$url .= '#'.$jumpto;
		}

		return $url;
	}

	/**
	 * @return array
	 */
	public function getForumList()
	{
		static $lists_instance;

		if (!isset($lists_instance)) {
			$JFusionForum = JFusionFactory::getForum($this->jname);
			$full_list = $JFusionForum->getForumList();
			$lists_instance = array();
			foreach ($full_list as $a) {
				$lists_instance[] = (isset($a->forum_id)) ? $a->forum_id : $a->id;
			}
		}
		return $lists_instance;
	}

	/**
	 * @param bool $skip_new_check
	 * @param bool $skip_k2_check
	 * @return array
	 */
	public function validate($skip_new_check = false, $skip_k2_check = false)
	{
		$this->debug('Validating article');
		$threadinfo = $this->getThreadInfo();
		//allowed components
		$components = array('com_content', 'com_k2');
		$responce = array(0, JText::_('UNKNOWN'));
		//make sure we have an article
		if (!$this->article->id || !in_array($this->option, $components)) {
			$responce = array(0, JText::sprintf('REASON_NOT_AN_ARTICLE', $this->option));
		} else {
			//if in K2, make sure we are after the article itself and not video or gallery
			$view = JFactory::getApplication()->input->get('view');
			if ($this->option == 'com_k2' && $view == 'item' && !$skip_k2_check && is_object($this->article->params)) {
				static $k2_tracker;
				if ($this->article->params->get('itemImageGallery') && empty($k2_tracker)) {
					$k2_tracker = 'gallery';
				} elseif ($this->article->params->get('itemVideo') && (empty($k2_tracker) || $k2_tracker == 'gallery')) {
					$k2_tracker = 'video';
				} else {
					$k2_tracker = 'item';
				}

				if ($k2_tracker != 'item') {
					$responce = array(0, JText::_('REASON_NOT_IN_K2_ARTICLE_TEXT'));
				}
			}

			//make sure there is a default user set
			if ($this->params->get('default_userid',false)===false) {
				$responce = array(0, JText::_('REASON_NO_DEFAULT_USER'));
			} else {
				$JFusionForum = JFusionFactory::getForum($this->jname);
				$forumid = $JFusionForum->getDefaultForum($this->params, $this->article);
				if (empty($forumid)) {
					$responce = array(0, JText::_('REASON_NO_FORUM_FOUND'));
				} else {
					$dbtask = JFactory::getApplication()->input->post->get('dbtask', null);
					$bypass_tasks = array('create_thread', 'publish_discussion', 'unpublish_discussion');
					if (!empty($dbtask) && !in_array($dbtask, $bypass_tasks)) {
						$responce = array(1, JText::_('REASON_DISCUSSION_MANUALLY_INITIALISED'));
					} else {
						//make sure article is published
						$state = false;
						if ($this->option == 'com_k2') {
							if (isset($this->article->published)) {
								$state = $this->article->published;
							}
						} else {
							if (isset($this->article->state)) {
								$state = $this->article->state;
							}
						}
						if (!$state) {
							$responce = array(0, JText::_('REASON_ARTICLE_NOT_PUBLISHED'));
						} else {
							//make sure the article is set to be published
							$mainframe = JFactory::getApplication();
							$publish_up = JFactory::getDate($this->article->publish_up)->toUnix();
							$now = JFactory::getDate('now', $mainframe->getCfg('offset'))->toUnix();

							$creationMode = $this->params->get('create_thread','load');
							if ($now < $publish_up && $creationMode != 'new') {
								$responce = array(0, JText::_('REASON_PUBLISHED_IN_FUTURE'));
							} else {
								//make sure create_thread is appropriate
								if ($creationMode == 'reply' && $dbtask != 'create_thread') {
									$responce = array(1, JText::_('REASON_CREATED_ON_FIRST_REPLY'));
								} elseif ($creationMode == 'view') {
									//only create the article if we are in the article view
									$test_view = ($this->option == 'com_k2') ? 'item' : 'article';
									if (JFactory::getApplication()->input->get('view') != $test_view) {
										$responce = array(0, JText::_('REASON_CREATED_ON_VIEW'));
									}
								} elseif ($creationMode == 'new' && !$skip_new_check) {
									//if set to create a thread for new articles only, make sure the thread was created with onAfterContentSave
									if (!$threadinfo->valid) {
										$responce = array(0, JText::_('REASON_ARTICLE_NOT_NEW'));
									}
								}
								if ($this->option == 'com_content') {
									//Joomla 1.6 has a different model for sections/category so need to handle it separately from J1.5
									$catid = $this->article->catid;
									$JCat = JCategories::getInstance('Content');
									/**
									 * @ignore
									 * @var $cat JCategoryNode
									 */
									$cat = $JCat->get($catid);

									$includedCategories = $this->params->get('include_categories');
									if (!is_array($includedCategories)) {
										$includedCategories = (empty($includedCategories)) ? array() : array($includedCategories);
									}

									$excludedCategories = $this->params->get('exclude_categories');
									if (!is_array($excludedCategories)) {
										$excludedCategories = (empty($excludedCategories)) ? array() : array($excludedCategories);
									}

									if (!empty($includedCategories)) {
										//there are category stipulations on what articles to include
										//check to see if this article is not in the selected categories
										$valid = (!in_array($catid,$includedCategories)) ? 0 : 1;
										if (!$valid) {
											//check to see if this article is in any included parents
											$parent_id = $cat->getParent()->id;
											if ($parent_id !== 'root') {
												while (true) {
													$valid = (!in_array($parent_id,$includedCategories)) ? 0 : 1;
													//keep going up
													if (!$valid) {
														//get the parent's parent id
														/**
														 * @ignore
														 * @var $parent JCategoryNode
														 */
														$parent = $JCat->get($parent_id);
														$parent_id = $parent->getParent()->id;
														if ($parent_id == 'root') {
															$responce = array(0, JText::_('REASON_NOT_IN_INCLUDED_CATEGORY_OR_PARENTS'));
															break;
														}
													} else {
														$responce = array(1, JText::_('REASON_IN_INCLUDED_CATEGORY_PARENT'));
														break;
													}
												}
											} else {
												$responce = array(0, JText::_('REASON_NOT_IN_INCLUDED_CATEGORY_OR_PARENTS'));
											}
										} else {
											$responce = array(1, JText::_('REASON_IN_INCLUDED_CATEGORY'));
										}

										//make sure the category is not in an excluded category
										if ($valid && !empty($excludedCategories)) {
											if (in_array($catid, $excludedCategories)) {
												$responce = array(0, JText::_('REASON_IN_EXCLUDED_CATEGORY'));
											}
										}
									} elseif (!empty($excludedCategories)) {
										$valid = (!in_array($catid, $excludedCategories)) ? 1 : 0;
										if ($valid) {
											$responce = array(1, JText::_('REASON_NOT_IN_EXCLUDED_CATEGORY'));

											//now to see if the category is an excluded cat or parent cat
											$parent_id = $cat->getParent()->id;
											if ($parent_id !== 'root') {
												while (true) {
													//keep going up
													if (!in_array($parent_id,$excludedCategories)) {
														//get the parent's parent id
														$parent = $JCat->get($parent_id);
														$parent_id = $parent->getParent()->id;
														if ($parent_id == 'root') {
															break;
														}
													} else {
														$responce = array(0, JText::_('REASON_IN_EXCLUDED_CATEGORY_PARENT'));
														break;
													}
												}
											}
										} else {
											$responce = array(0, JText::_('REASON_IN_EXCLUDED_CATEGORY'));
										}
									} else {
										$responce = array(0, JText::_('REASON_NO_STIPULATIONS'));
									}
								} elseif ($this->option == 'com_k2') {
									$includedCategories = $this->params->get('include_k2_categories');
									if (!is_array($includedCategories)) {
										$includedCategories = (empty($includedCategories)) ? array() : array($includedCategories);
									}

									$excludedCategories = $this->params->get('exclude_k2_categories');
									if (!is_array($excludedCategories)) {
										$excludedCategories = (empty($excludedCategories)) ? array() : array($excludedCategories);
									}

									$catid = $this->article->catid;
									$cat_parentid = (!empty($this->article->category->parent)) ? $this->article->category->parent : 0;
									$db = JFactory::getDBO();
									static $k2_parent_cats;
									if (!is_array($k2_parent_cats)) {
										$k2_parent_cats = array();
									}

									if (!empty($includedCategories)) {
										//check to see if the article's category is included
										if (in_array($catid, $includedCategories)) {
											//its included
											$responce = array(1, JText::_('REASON_IN_INCLUDED_CATEGORY'));
										} elseif (!empty($cat_parentid)) {
											$responce = array(0, JText::_('REASON_IN_EXCLUDED_CATEGORY'));

											//see if a parent category is included
											$parent_id = $cat_parentid;
											while (true) {
												if (!empty($parent_id)) {
													if (in_array($parent_id, $includedCategories)) {
														$responce = array(1, JText::_('REASON_IN_INCLUDED_CATEGORY_PARENT'));
														break;
													} else {
														//get the parent's parent
														$query = 'SELECT parent FROM #__k2_categories WHERE id = '.$parent_id;
														$db->setQuery($query);
														//keep going up
														$parent_id = $db->loadResult();
													}
												} else {
													break;
												}
											}

											//if valid, make sure the category is not in an excluded cat
											if ($responce[0] && !empty($excludedCategories)) {
												if (in_array($catid, $excludedCategories)) {
													$responce = array(0, JText::_('REASON_IN_EXCLUDED_CATEGORY'));
												}
											}
										}
									} elseif (!empty($excludedCategories)) {
										if (!in_array($catid, $excludedCategories)) {
											$responce = array(1, JText::_('REASON_NOT_IN_EXCLUDED_CATEGORY'));
											$parent_id = $cat_parentid;
											while (true) {
												if (!empty($parent_id)) {
													if (in_array($parent_id, $excludedCategories)) {
														$responce = array(0, JText::_('REASON_IN_EXCLUDED_CATEGORY_PARENT'));
														break;
													} else {
														//get the parent's parent
														$query = 'SELECT parent FROM #__k2_categories WHERE id = '.$parent_id;
														$parent_id = $db->setQuery($query);
													}
												} else {
													break;
												}
											}
										} else {
											$responce = array(0, JText::_('REASON_IN_EXCLUDED_CATEGORY'));
										}
									} else {
										$responce = array(0, JText::_('REASON_NO_STIPULATIONS'));
									}
								}
							}
						}
					}
				}
			}
		}
		return $responce;
	}

	public function loadScripts()
	{
		JHtml::_('behavior.framework', true);
		JHtml::_('jquery.framework');
		static $scriptsLoaded;
		if (!isset($scriptsLoaded)) {
			$this->debug('Loading scripts into header');

			$view = JFactory::getApplication()->input->get('view');
			$test_view = ($this->option == 'com_k2') ? 'item' : 'article';

			$jumpto_discussion = JFactory::getApplication()->input->post->getInt('jumpto_discussion', '0');

			$js = <<<JS
		        JFusion.view = '{$view}';
		        JFusion.jumptoDiscussion = {$jumpto_discussion};
		        JFusion.enablePagination = {$this->params->get('enable_pagination',0)};
		        JFusion.enableAjax = {$this->params->get('enable_ajax',0)};
		        JFusion.enableJumpto = {$this->params->get('jumpto_new_post',0)};
JS;

			JFusionFunction::loadJavascriptLanguage(array('BUTTON_CANCEL', 'BUTTON_INITIATE',
				'BUTTON_PUBLISH_NEW_DISCUSSION', 'BUTTON_REPUBLISH_DISCUSSION', 'BUTTON_UNPUBLISH_DISCUSSION',
				'CONFIRM_THREAD_CREATION', 'CONFIRM_UNPUBLISH_DISCUSSION', 'CONFIRM_PUBLISH_DISCUSSION',
				'DISCUSSBOT_ERROR', 'HIDE_REPLIES', 'JYES', 'SHOW_REPLIES', 'SUBMITTING_QUICK_REPLY'));
			$document = JFactory::getDocument();
			//check for a custom js file
			if (file_exists(DISCUSSION_TEMPLATE_PATH.'jfusion.js')) {
				$document->addScript(DISCUSSION_TEMPLATE_URL.'jfusion.js');
			}

			//Load quick reply includes if enabled
			if ($this->params->get('enable_quickreply')) {
				$JFusionForum = JFusionFactory::getForum($this->jname);
				$this->debug('Quick reply is enabled and thus loading any includes (js, css, etc).');
				$js .= $JFusionForum->loadQuickReplyIncludes();
			}

			if ($view == $test_view) {
				$js .= <<<JS
				window.addEvent('load', function() {
        				JFusion.initializeDiscussbot();
    				});
JS;
			} else {
				$js .= <<<JS
				window.addEvent('load', function() {
        				JFusion.initializeConfirmationBoxes();
    				});
JS;
			}

			$document->addScriptDeclaration($js);

			//add css
			$css = DISCUSSION_TEMPLATE_PATH.'jfusion.css';
			if (file_exists($css)) {
				$document->addStyleSheet(DISCUSSION_TEMPLATE_URL.'jfusion.css');
			}
			$scriptsLoaded = true;
		}
	}

	/**
	 * @param $file
	 *
	 * @return bool|string
	 */
	public function renderFile($file)
	{
		$captured_content = false;
		$this->debug('Rendering file ' . $file);
		if (file_exists(DISCUSSION_TEMPLATE_PATH . $file)) {
			ob_start();
			include DISCUSSION_TEMPLATE_PATH.$file;
			$captured_content = ob_get_contents();
			ob_end_clean();
		} else {
			die(DISCUSSION_TEMPLATE_PATH . $file . " is missing!");
		}
		return $captured_content;
	}

	/**
	 * @param $text
	 * @param bool $save
	 */
	public function debug($text, $save = false)
	{
		if ($this->params->get('debug', 0)) {
			$this->debug[] = $text;

			if ($save) {
				$session = JFactory::getSession();
				$session->set('jfusion.discussion.debug.' . $this->article->id, $this->debug);
			}
		}
	}
}

/**
 *
 */
jimport( 'joomla.html.pagination' );
/**
 * Class JFusionPagination
 */
class JFusionPagination extends JPagination {
	var $identifier = '';

	/**
	 * @param int $total
	 * @param int $limitstart
	 * @param int $limit
	 * @param string $identifier
	 */
	public function __construct($total, $limitstart, $limit, $identifier = '')
	{
		$this->identifier = $identifier;
		parent::__construct($total, $limitstart, $limit);
	}

	/**
	 * @return string
	 */
	public function getPagesLinks()
	{
		// Build the page navigation list
		$data = $this->_buildDataObject();

		$list = array();

		// Build the select list
		if ($data->all->base !== null) {
			$list['all']['active'] = true;
			$list['all']['data'] = $this->jfusion_item_active($data->all);
		} else {
			$list['all']['active'] = false;
			$list['all']['data'] = $this->jfusion_item_inactive($data->all);
		}

		if ($data->start->base !== null) {
			$list['start']['active'] = true;
			$list['start']['data'] = $this->jfusion_item_active($data->start);
		} else {
			$list['start']['active'] = false;
			$list['start']['data'] = $this->jfusion_item_inactive($data->start);
		}
		if ($data->previous->base !== null) {
			$list['previous']['active'] = true;
			$list['previous']['data'] = $this->jfusion_item_active($data->previous);
		} else {
			$list['previous']['active'] = false;
			$list['previous']['data'] = $this->jfusion_item_inactive($data->previous);
		}

		$list['pages'] = array(); //make sure it exists
		foreach ($data->pages as $i => $page)
		{
			if ($page->base !== null) {
				$list['pages'][$i]['active'] = true;
				$list['pages'][$i]['data'] = $this->jfusion_item_active($page);
			} else {
				$list['pages'][$i]['active'] = false;
				$list['pages'][$i]['data'] = $this->jfusion_item_inactive($page);
			}
		}

		if ($data->next->base !== null) {
			$list['next']['active'] = true;
			$list['next']['data'] = $this->jfusion_item_active($data->next);
		} else {
			$list['next']['active'] = false;
			$list['next']['data'] = $this->jfusion_item_inactive($data->next);
		}
		if ($data->end->base !== null) {
			$list['end']['active'] = true;
			$list['end']['data'] = $this->jfusion_item_active($data->end);
		} else {
			$list['end']['active'] = false;
			$list['end']['data'] = $this->jfusion_item_inactive($data->end);
		}

		if($this->total > $this->limit){
			return $this->_list_render($list);
		}
		else{
			return '';
		}
	}

	/**
	 * @return string
	 */
	public function getListFooter()
	{
		$list = array();
		$list['limit']			= $this->limit;
		$list['limitstart']		= $this->limitstart;
		$list['total']			= $this->total;
		$list['limitfield']		= $this->getLimitBox();
		$list['pagescounter']	= $this->getPagesCounter();
		$list['pageslinks']		= $this->getPagesLinks();

		return $this->jfusion_list_footer($list);
	}

	/**
	 * @return mixed|string
	 */
	public function getLimitBox()
	{
		$mainframe = JFactory::getApplication();

		// Initialize variables
		$limits = array ();

		// Make the option list
		for ($i = 5; $i <= 30; $i += 5) {
			$limits[] = JHTML::_('select.option', "$i");
		}
		$limits[] = JHTML::_('select.option', '50');
		$limits[] = JHTML::_('select.option', '100');
		$limits[] = JHTML::_('select.option', '0', JText::_('all'));

		$selected = $this->viewall ? 0 : $this->limit;

		// Build the select list
		if ($mainframe->isAdmin()) {
			$html = JHTML::_('select.genericlist',  $limits, 'limit' . $this->identifier, 'class="inputbox" size="1" onchange="submitform();"', 'value', 'text', $selected);
		} else {
			$html = JHTML::_('select.genericlist',  $limits, 'limit' . $this->identifier, 'class="inputbox" size="1" onchange="this.form.submit()"', 'value', 'text', $selected);
		}
		return $html;
	}

	/**
	 * @param $list
	 * @return null|string
	 */
	public function jfusion_list_render($list)
	{
		// Initialize variables
		$html = null;

		// Reverse output rendering for right-to-left display
		$html .= '&lt;&lt; ';
		$html .= $list['start']['data'];
		$html .= ' &lt; ';
		$html .= $list['previous']['data'];
		foreach( $list['pages'] as $page ) {
			$html .= ' '.$page['data'];
		}
		$html .= ' '. $list['next']['data'];
		$html .= ' &gt;';
		$html .= ' '. $list['end']['data'];
		$html .= ' &gt;&gt;';

		return $html;
	}


	/**
	 * @param $list
	 * @return string
	 */
	public function jfusion_list_footer($list)
	{
		// Initialize variables
		$html = '<div class="list-footer">';
		$html .= '<div class="limit">'.JText::_('JGLOBAL_DISPLAY_NUM').$list['limitfield'].'</div>';
		$html .= '<p class="counter" style="font-weight: bold; margin: 8px 0;">'.$list['pagescounter'].'</p>';
		$html .= $list['pageslinks'];

		$html .= '<input type="hidden" name="limitstart'.$this->identifier.'" value="'.$list['limitstart'].'"/>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * @param $item
	 * @return string
	 */
	public function jfusion_item_active(&$item)
	{
		if($item->base>0) {
			return '<a href="#" title="'.$item->text.'" onclick="javascript: document.jfusionPaginationForm.limitstart'.$this->identifier.'.value='.$item->base.'; JFusion.pagination(); return false;">'.$item->text.'</a>';
		} else {
			return '<a href="#" title="'.$item->text.'" onclick="javascript: document.jfusionPaginationForm.limitstart'.$this->identifier.'.value=0; JFusion.pagination(); return false;">'.$item->text.'</a>';
		}
	}


	/**
	 * @param $item
	 * @return string
	 */
	public function jfusion_item_inactive(&$item)
	{
		return '<span class="pagenav">'.$item->text.'</span>';
	}
}