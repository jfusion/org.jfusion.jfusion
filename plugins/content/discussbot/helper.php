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
    var $params;
    var $jname;
    var $mode;
    var $thread_status;
    var $threadinfo;
    var $debug_mode;
    var $debug_output;
    var $output;
    var $reply_count;
    var $option;
    var $isJ16;

    /**
     * @param JParameter $params
     * @param $jname
     * @param $mode
     * @param $debug_mode
     */
    public function __construct(&$params, $jname, $mode, $debug_mode) {
        $this->params = $params;
        $this->jname = $jname;
        $this->mode = $mode;
        $this->debug_mode = $debug_mode;
        $this->isJ16 = (JFusionFunction::isJoomlaVersion('1.6')) ? 1 : 0;
        if ($this->isJ16) {
            //needed for category support
            jimport('joomla.application.categories');
        }
    }

    /**
     * @param bool $update
     * @param bool $threadinfo
     * @return mixed
     */
    public function &_get_thread_info($update = false, $threadinfo = false)
    {
        static $thread_instance;

        if (!is_array($thread_instance)) {
            $thread_instance = array();
        }

        $contentid =& $this->article->id;

        if (!empty($threadinfo)) {
            $thread_instance[$contentid] = $threadinfo;
        } elseif (empty($thread_instance) || !isset($thread_instance[$contentid]) || $update) {
            $db = JFactory::getDBO();
            $query = 'SELECT * FROM #__jfusion_discussion_bot WHERE contentid = \''.$contentid.'\' AND jname = \''.$this->jname.'\' AND component = '.$db->Quote($this->option);
            $db->setQuery($query);
            $thread_instance[$contentid] = $db->loadObject();
        }

        return $thread_instance[$contentid];
    }

    /**
     * @param int $force_new
     * @return array
     */
    public function _check_thread_exists($force_new = 0)
    {
        $this->_debug('Checking if thread exists');

        $JFusionForum =& JFusionFactory::getForum($this->jname);

        if ($force_new) {
            $threadinfo = new stdClass();
            $threadinfo->threadid = 0;
            $threadinfo->postid = 0;
            $threadinfo->forumid = 0;
            $manually_created = 1;
        } else {
            $threadinfo =& $this->_get_thread_info();
            $manually_created = (empty($threadinfo->manual)) ? 0 : 1;
        }

        $status = array('error' => array(),'debug' => array());
        $status['action'] = 'unchanged';
        $status['threadinfo'] = new stdClass();

        $JFusionForum->checkThreadExists($this->params, $this->article, $threadinfo, $status);
        if (!empty($status['error'])) {
            JFusionFunction::raiseWarning($this->jname . ' ' .JText::_('FORUM') . ' ' .JText::_('UPDATE'), $status['error'],1);
        } else {
            if ($status['action']!='unchanged') {
                if ($status['action'] == 'created') {
                    $threadinfo =& $status['threadinfo'];
                }

                //catch in case plugins screwed up
                if (!empty($threadinfo->threadid)) {
                    //update the lookup table
                    JFusionFunction::updateDiscussionBotLookup($this->article->id, $threadinfo, $this->jname, 1, $manually_created);

                    //set the thread_status to true since it was just created
                    $this->thread_status = true;
                } else {
                    $this->thread_status = false;
                }
            }
        }

        $this->_debug($status, $force_new);

        return $status;
    }


    /**
     * @param string $jumpto
     * @param string $query
     * @param bool $xhtml
     * @return string|The
     */
    public function _get_article_url($jumpto = '', $query = '', $xhtml = true)
    {
        //make sure Joomla's content helper is loaded
        if (!class_exists('ContentHelperRoute')) {
            require_once JPATH_SITE . DS . 'components' . DS . 'com_content' . DS . 'helpers' . DS . 'route.php';
        }
        if ($this->option == 'com_k2') {
            if (!class_exists('K2HelperRoute')) {
                include_once JPATH_SITE . DS . 'components' . DS . 'com_k2' . DS . 'helpers' . DS . 'route.php';
            }
        }

        if ($this->option == 'com_content') {
            //take into account page breaks
            if ($this->isJ16) {
                $url = ContentHelperRoute::getArticleRoute($this->article->slug, $this->article->catid);
            } else {
                $url = ContentHelperRoute::getArticleRoute($this->article->slug, $this->article->catslug, $this->article->sectionid);
            }
            $start = JRequest::getInt('start',0);
            if ($start) {
                $url .= '&start='.$start;
            }
            $limitstart = JRequest::getInt('limitstart',0);
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
     * @return bool
     */
    public function _get_thread_status()
    {
        $threadinfo =& $this->_get_thread_info(true);
        if (!empty($threadinfo)) {

            if (empty($threadinfo->forumid)) {
                //could be that publish date is in the future
                $active = false;
                return $active;
            }

            //make sure the forum and thread still exists
            $JFusionForum =& JFusionFactory::getForum($this->jname);

            $forumlist =& $this->_get_lists('forum');
            if (!in_array($threadinfo->forumid, $forumlist)) {
                //seems the forum is now missing
                $active = false;
                return $active;
            }

            $forumthread = $JFusionForum->getThread($threadinfo->threadid);
            if (empty($forumthread)) {
                //seems the thread is now missing
                $active = false;
                return $active;
            }
        }

        $active = (!empty($threadinfo) && !empty($threadinfo->published)) ? true : false;
        return $active;
    }

    /**
     * @param $type
     * @return mixed
     */
    public function &_get_lists($type)
    {
        static $lists_instance;

        if ($type=='forum') {
            if (!isset($lists_instance[$type])) {
                $JFusionForum =& JFusionFactory::getForum($this->jname);
                $full_list = $JFusionForum->getForumList();
                $ids = array();
                foreach ($full_list as $a) {
                    $ids[] = (isset($a->forum_id)) ? $a->forum_id : $a->id;
                }
                $lists_instance[$type] = $ids;
            }
        }

        return $lists_instance[$type];
    }

    /**
     * @param bool $skip_new_check
     * @param bool $skip_k2_check
     * @return array
     */
    public function _validate($skip_new_check = false, $skip_k2_check = false)
    {
        $this->_debug('Validating article');

        //allowed components
        $components = array('com_content', 'com_k2');
        $valid = 0;
        $validity_reason = '';
        //make sure we have an article
        if (!$this->article->id || !in_array($this->option, $components)) {
            $validity_reason = JText::sprintf('REASON_NOT_AN_ARTICLE', $this->option);
        } else {
            //if in K2, make sure we are after the article itself and not video or gallery
            $view = JRequest::getVar('view');
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
                    return array($valid, JTEXT::_('REASON_NOT_IN_K2_ARTICLE_TEXT'));
                }
            }

            //make sure there is a default user set
            if ($this->params->get('default_userid',false)===false) {
                $validity_reason = JText::_('REASON_NO_DEFAULT_USER');
            } else {
                $JFusionForum =& JFusionFactory::getForum($this->jname);
                $forumid = $JFusionForum->getDefaultForum($this->params, $this->article);
                if (empty($forumid)) {
                    $validity_reason = JText::_('REASON_NO_FORUM_FOUND');
                } else {
                    $dbtask = JRequest::getVar('dbtask', 'render_content', 'post');
                    $bypass_tasks = array('create_thread', 'render_content', 'publish_discussion', 'unpublish_discussion');
                    if (!empty($dbtask) && !in_array($dbtask, $bypass_tasks)) {
                        $validity_reason = JText::_('REASON_DISCUSSION_MANUALLY_INITIALISED');
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
                            $validity_reason = JText::_('REASON_ARTICLE_NOT_PUBLISHED');
                        } else {
                            //make sure the article is set to be published
                            $mainframe = JFactory::getApplication();
                            $publish_up = JFactory::getDate($this->article->publish_up)->toUnix();
                            $now = JFactory::getDate('now', $mainframe->getCfg('offset'))->toUnix();

                            if ($now < $publish_up) {
                                $validity_reason = JText::_('REASON_PUBLISHED_IN_FUTURE');
                            } else {
                                $creationMode =& $this->params->get('create_thread','load');
                                //make sure create_thread is appropriate
                                if ($creationMode == 'reply' && $dbtask != 'create_thread') {
                                    return array($valid, JText::_('REASON_CREATED_ON_FIRST_REPLY'));
                                } elseif ($creationMode == 'view') {
                                    //only create the article if we are in the article view
                                    $test_view = ($this->option == 'com_k2') ? 'item' : 'article';
                                    if (JRequest::getVar('view') != $test_view) {
                                        return array($valid, JText::_('REASON_CREATED_ON_VIEW'));
                                    }
                                } elseif ($creationMode == 'new' && !$skip_new_check) {
                                    //if set to create a thread for new articles only, make sure the thread was created with onAfterContentSave
                                    if (!$this->thread_status) {
                                        return array($valid, JText::_('REASON_ARTICLE_NOT_NEW'));
                                    }
                                }

                                if ($this->option == 'com_content') {
                                    if ($this->isJ16) {
                                        //Joomla 1.6 has a different model for sections/category so need to handle it separately from J1.5
                                        $catid =& $this->article->catid;
                                        $JCat =& JCategories::getInstance('Content');
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
                                                                $validity_reason = JText::_('REASON_NOT_IN_INCLUDED_CATEGORY_OR_PARENTS');
                                                                break;
                                                            }
                                                        } else {
                                                            $validity_reason = JText::_('REASON_IN_INCLUDED_CATEGORY_PARENT');
                                                            break;
                                                        }
                                                    }
                                                } else {
                                                    $validity_reason = JText::_('REASON_NOT_IN_INCLUDED_CATEGORY_OR_PARENTS');
                                                }
                                            } else {
                                                $validity_reason = JText::_('REASON_IN_INCLUDED_CATEGORY');
                                            }

                                            //make sure the category is not in an excluded category
                                            if ($valid && !empty($excludedCategories)) {
                                                if (in_array($catid, $excludedCategories)) {
                                                    $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY');
                                                }
                                            }
                                        } elseif (!empty($excludedCategories)) {
                                            $valid = (!in_array($catid, $excludedCategories)) ? 1 : 0;
                                            if ($valid) {
                                                $validity_reason = JText::_('REASON_NOT_IN_EXCLUDED_CATEGORY');

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
                                                            $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY_PARENT');
                                                            break;
                                                        }
                                                    }
                                                }
                                            } else {
                                                $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY');
                                            }
                                        } else {
                                            $validity_reason = JText::_('REASON_NO_STIPULATIONS');
                                            $valid = 1;
                                        }
                                    } else {
                                        //section and category id of content
                                        $secid =& $this->article->sectionid;
                                        $catid =& $this->article->catid;

                                        //check to see if we have an uncategorized article
                                        if (empty($secid) && empty($catid)) {
                                            //does the admin want a thread generated?
                                            if ($this->params->get('include_static',false)) {
                                                return array(1, JText::_('REASON_INCLUDE_UNCATEGORIZED'));
                                            } else {
                                                return array($valid, JText::_('REASON_DISCLUDE_UNCATEGORIZED'));
                                            }
                                        }

                                        //check to see if there are sections/categories that are specifically included/excluded
                                        $includedSections = $this->params->get('include_sections');
                                        if (!is_array($includedSections)) {
                                            $includedSections = (empty($includedSections)) ? array() : array($includedSections);
                                        }

                                        $includedCategories = $this->params->get('include_categories');
                                        if (!is_array($includedCategories)) {
                                            $includedCategories = (empty($includedCategories)) ? array() : array($includedCategories);
                                        }

                                        $excludedSections = $this->params->get('exclude_sections');
                                        if (!is_array($excludedSections)) {
                                            $excludedSections = (empty($excludedSections)) ? array() : array($excludedSections);
                                        }

                                        $excludedCategories = $this->params->get('exclude_categories');
                                        if (!is_array($excludedCategories)) {
                                            $excludedCategories = (empty($excludedCategories)) ? array() : array($excludedCategories);
                                        }

                                        //there are section stipulations on what articles to include
                                        if (!empty($includedSections)) {
                                            if (!in_array($secid,$includedSections)) {
                                                //this article is not in one of the sections to include
                                                $validity_reason = 'REASON_NOT_IN_INCLUDE_SECTION';
                                            } elseif (!empty($includedCategories)) {
                                                //there are both specific sections and categories to include
                                                //check to see if this article is not in the selected categories within the included sections

                                                if (!in_array($catid,$includedCategories)) {
                                                    $validity_reason = JText::_('REASON_IN_INCLUDED_SECTION_NOT_IN_INCLUDED_CATEGORY');
                                                } else {
                                                    $validity_reason = JText::_('REASON_IN_INCLUDED_SECTION_AND_CATEGORY');
                                                    $valid = 1;
                                                }
                                            } elseif (!empty($excludedCategories)) {
                                                //exclude this article if it is in one of the excluded categories
                                                if (in_array($catid,$excludedCategories)) {
                                                    $validity_reason = JText::_('REASON_IN_INCLUDED_SECTION_BUT_IN_EXCLUDED_CATEGORY');
                                                } else {
                                                    $validity_reason = JText::_('REASON_IN_INCLUDED_SECTION_NOT_IN_EXCLUDED_CATEGORY');
                                                    $valid = 1;
                                                }
                                            } else {
                                                //there are only specific sections to include with no applicable category stipulations
                                                $validity_reason = JText::_('REASON_IN_INCLUDED_SECTION');
                                                $valid = 1;
                                            }
                                        } elseif (!empty($excludedSections)) {
                                            //there are section stipulations on what articles to exclude
                                            //check to see if this article is in the excluded sections
                                            $valid = (in_array($secid,$excludedSections)) ? 0 : 1;
                                            if (!$valid) {
                                                $validity_reason = JText::_('REASON_IN_EXCLUDED_SECTION');
                                            } else {
                                                $validity_reason = JText::_('REASON_NOT_IN_EXCLUDED_SECTION');
                                            }

                                            if ($excludedCategories) {
                                                //exclude this article if it is in one of the excluded categories
                                                $valid = (in_array($catid, $excludedCategories)) ? 0 : $valid;
                                                if (!$valid) {
                                                    $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY');
                                                }
                                            }
                                        } elseif (!empty($includedCategories)) {
                                            //there are category stipulations on what articles to include but no section stipulations
                                            //check to see if this article is not in the selected categories
                                            $valid = (!in_array($catid,$includedCategories)) ? 0 : 1;
                                            if (!$valid) {
                                                $validity_reason = JText::_('REASON_NOT_IN_INCLUDED_CATEGORY');
                                            } else {
                                                $validity_reason = JText::_('REASON_IN_INCLUDED_CATEGORY');
                                            }
                                        } elseif (!empty($excludedCategories)) {
                                            //there are category stipulations on what articles to exclude but no exclude stipulations on section
                                            //check to see if this article is in the excluded categories
                                            $valid = (in_array($catid,$excludedCategories)) ? 0 : 1;
                                            if (!$valid) {
                                                $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY');
                                            } else {
                                                $validity_reason = JText::_('REASON_NOT_IN_EXCLUDED_CATEGORY');
                                            }
                                        } else {
                                            $validity_reason = JText::_('REASON_NO_STIPULATIONS');
                                            $valid = 1;
                                        }
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
                                        $valid = (in_array($catid, $includedCategories)) ? 1 : 0;
                                        if ($valid) {
                                            //its included
                                            $validity_reason = JText::_('REASON_IN_INCLUDED_CATEGORY');
                                        } elseif (!empty($cat_parentid)) {
                                            $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY');

                                            //see if a parent category is included
                                            $parent_id = $cat_parentid;
                                            while (true) {
                                                if (!empty($parent_id)) {
                                                    if (in_array($parent_id, $includedCategories)) {
                                                        $valid = 1;
                                                        $validity_reason = JText::_('REASON_IN_INCLUDED_CATEGORY_PARENT');
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

                                            //if valid, make sure the category is not in an exluded cat
                                            if ($valid && !empty($excludedCategories)) {
                                                if (in_array($catid, $excludedCategories)) {
                                                    $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY');
                                                }
                                            }
                                        }
                                    } elseif (!empty($excludedCategories)) {
                                        $valid = (!in_array($catid, $excludedCategories)) ? 1 : 0;
                                        if ($valid) {
                                            $validity_reason = JText::_('REASON_NOT_IN_EXCLUDED_CATEGORY');
                                            $parent_id = $cat_parentid;
                                            while (true) {
                                                if (!empty($parent_id)) {
                                                    if (in_array($parent_id, $excludedCategories)) {
                                                        $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY_PARENT');
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
                                            $validity_reason = JText::_('REASON_IN_EXCLUDED_CATEGORY');
                                        }
                                    } else {
                                        $valid = 1;
                                        $validity_reason = JText::_('REASON_NO_STIPULATIONS');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return array($valid, $validity_reason);
    }

    public function _load_scripts()
    {
        $this->_debug('Loading scripts into header');

        $view = JRequest::getVar('view');
        $test_view = ($this->option == 'com_k2') ? 'item' : 'article';

        $lang_strings = array(
        	'BUTTON_CANCEL',
            'BUTTON_INITIATE',
            'BUTTON_PUBLISH_NEW_DISCUSSION',
            'BUTTON_REPUBLISH_DISCUSSION',
            'BUTTON_UNPUBLISH_DISCUSSION',
            'CONFIRM_THREAD_CREATION',
            'CONFIRM_UNPUBLISH_DISCUSSION',
            'CONFIRM_PUBLISH_DISCUSSION',
            'DISCUSSBOT_ERROR',
            'HIDE_REPLIES',
            'JYES',
        	'SHOW_REPLIES',
            'SUBMITTING_QUICK_REPLY',
            'SUCCESSFUL_POST',
        	'SUCCESSFUL_POST_MODERATED'
        );

        $jumpto_discussion = JRequest::getInt('jumpto_discussion', '0', 'post');

        $js = <<<JS
        var jfdb_isJ16 = {$this->isJ16};
        var jfdb_view = '{$view}';
        var jfdb_jumpto_discussion = {$jumpto_discussion};
        var jfdb_enable_pagination = {$this->params->get('enable_pagination',1)};
        var jfdb_enable_ajax = {$this->params->get('enable_ajax',1)};
        var jfdb_enable_jumpto = {$this->params->get('jumpto_new_post',0)};
JS;

        if ($this->debug_mode) {
            $js .= <<<JS
            var jfdb_debug = '1';
JS;
        }

        foreach ($lang_strings as $str) {
            $jstr = JText::_($str);
            $js .= <<<JS
            var JFDB_{$str} = "{$jstr}";
JS;
        }

        //Load quick reply includes if enabled
        if ($this->params->get('enable_quickreply')) {
            $JFusionForum =& JFusionFactory::getForum($this->jname);
            $this->_debug('Quick reply is enabled and thus loading any includes (js, css, etc).');
            $js .= $JFusionForum->loadQuickReplyIncludes();
        }

        if ($view == $test_view) {
            $joomla_basepath = JPATH_SITE;
            $js .= <<<JS
            var jfdb_article_url = '{$this->_get_article_url()}';
            var jfdb_article_id = '{$this->article->id}';
            window.addEvent(window.webkit ? 'load' : 'domready', function () {
                initializeDiscussbot();
            });
JS;
        } else {
            $js .= <<<JS
            window.addEvent(window.webkit ? 'load' : 'domready', function () {
                initializeConfirmationBoxes();
            });
JS;
        }

        $document = JFactory::getDocument();
        $document->addScriptDeclaration($js);

        //check for a custom js file
        if (file_exists(DISCUSSION_TEMPLATE_PATH.'jfusion.js')) {
            $document->addScript(DISCUSSION_TEMPLATE_URL.'jfusion.js');
        }

         //add css
        $css = DISCUSSION_TEMPLATE_PATH.'jfusion.css';
        if (file_exists($css)) {
            $document->addStyleSheet(DISCUSSION_TEMPLATE_URL.'jfusion.css');
        }
    }

    /**
     * @param $file
     * @param string $mode
     * @return bool|string
     */
    public function _render_file($file, $mode = 'require')
    {
        $this->_debug('Rendering file ' . $file . ' in ' . $mode . ' mode');
        if (file_exists(DISCUSSION_TEMPLATE_PATH . $file)) {
            if ($mode == 'capture') {
                ob_start();
                include DISCUSSION_TEMPLATE_PATH.$file;
                $captured_content = ob_get_contents();
                ob_end_clean();
                return $captured_content;
            } elseif ($mode=='die') {
                die(include DISCUSSION_TEMPLATE_PATH.$file);
            } else {
                include DISCUSSION_TEMPLATE_PATH.$file;
            }
        } else {
            die(DISCUSSION_TEMPLATE_PATH . $file . " is missing!");
        }
        return false;
    }

    /**
     * @param $text
     * @param bool $save
     */
    public function _debug($text, $save = false)
    {
        if ($this->debug_mode) {
            $this->debug_output[] = $text;

            if ($save) {
                $session = JFactory::getSession();
                $session->set('jfusion.discussion.debug.' . $this->article->id, $this->debug_output);
            }
        }
    }
}

/**
 *
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

		$selected = $this->_viewall ? 0 : $this->limit;

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
            return '<a href="#" title="'.$item->text.'" onclick="javascript: document.jfusionPaginationForm.limitstart'.$this->identifier.'.value='.$item->base.'; document.jfusionPaginationForm.submit(); return false;">'.$item->text.'</a>';
        } else{
            return '<a href="#" title="'.$item->text.'" onclick="javascript: document.jfusionPaginationForm.limitstart'.$this->identifier.'.value=0; document.jfusionPaginationForm.submit(); return false;">'.$item->text.'</a>';
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