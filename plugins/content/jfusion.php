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
defined('_JEXEC' ) or die('Restricted access' );

/**
* Load the JFusion framework
*/
jimport('joomla.plugin.plugin');
jimport('joomla.html.pagination');
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php';
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
    var $manual_plug = false;
    var $manual_threadid = 0;
    var $debug_mode = 0;
    var $clear_debug_output = true;
    var $helper = '';

    /**
    * Constructor
    *
    * For php4 compatability we must not use the __constructor as a constructor for
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
        if ($this->params===false) {
            if (is_array($params)) {
                $this->params = new JParameter( $params[params]);
            } else {
                $this->params = new JParameter( $params->params);
            }

        }

        $this->jname =& $this->params->get('jname',false);

        if ($this->jname !== false) {
            //load the plugin's language file
            $this->loadLanguage('com_jfusion.plg_' . $this->jname, JPATH_ADMINISTRATOR);
        }

        //determine what mode we are to operate in
        if ($this->params->get('auto_create',0)) {
            $this->mode = ($this->params->get('test_mode',1)) ? 'test' : 'auto';
        } else {
            $this->mode = 'manual';
        }

        $this->creationMode =& $this->params->get('create_thread','load');

        $this->debug_mode = $this->params->get('debug', JRequest::getInt('debug_discussionbot',0));

        //define some constants
        $isJ16 = JFusionFunction::isJoomlaVersion('1.6');
        if (!defined('DISCUSSION_TEMPLATE_PATH')) {
            $url_path = ($isJ16) ? 'jfusion/' : '';
            define('DISCUSSBOT_URL_PATH', 'plugins/content/' . $url_path . 'discussbot/');
            $path = ($isJ16) ? 'jfusion' . DS : '';
            define('DISCUSSBOT_PATH', JPATH_SITE . DS . 'plugins' . DS . 'content' . DS . $path . 'discussbot' . DS);

            //let's first check for customized files in Joomla's template directory
            $app = JFactory::getApplication();
            $JoomlaTemplateOverride = JPATH_BASE.DS.'templates'. DS .$app->getTemplate() . DS. 'html' . DS . 'plg_content_jfusion' . DS;
            if (file_exists($JoomlaTemplateOverride)) {
                define('DISCUSSION_TEMPLATE_PATH', $JoomlaTemplateOverride);
                define('DISCUSSION_TEMPLATE_URL', JFusionFunction::getJoomlaURL() . 'templates/' . $app->getTemplate() . '/html/plg_content_jfusion/');
            } else {
                define('DISCUSSION_TEMPLATE_PATH',JPATH_BASE.DS.'plugins'.DS.'content'.DS.$path.'discussbot'.DS.'tmpl'.DS.$this->template.DS);
                define('DISCUSSION_TEMPLATE_URL',JFusionFunction::getJoomlaURL().'plugins/content/'.$url_path.'discussbot/tmpl/'.$this->template.'/');
            }
        }

        //load the helper file
        $helper_path = DISCUSSBOT_PATH . 'helper.php';
        include_once $helper_path;
        $this->helper = new JFusionDiscussBotHelper($this->params, $this->jname, $this->mode, $this->debug_mode);

        //set option
        $this->helper->option = JRequest::getCmd('option');
    }


    /**
     * @param $subject
     * @param $isNew
     * @return bool
     */
    public function onAfterContentSave(&$subject, $isNew) {
        //check to see if a valid $content object was passed on
        $result = true;
        if (!is_object($subject)){
            JFusionFunction::raiseWarning(JText::_('DISCUSSBOT_ERROR'), JText::_('NO_CONTENT_DATA_FOUND'), 1);
            $result = false;
        } else {
            $this->article =& $subject;
            $this->helper->article =& $this->article;

            if ($this->debug_mode) {
                $session = JFactory::getSession();
                $this->helper->debug_output = $session->get('jfusion.discussion.debug.' . $this->article->id,false);
                if ($this->helper->debug_output!==false) {
                    $this->clear_debug_output = false;
                }
                $session->clear('jfusion.discussion.debug.' . $this->article->id);
                if (!is_array($this->helper->debug_output)) {
                    $this->helper->debug_output = array();
                }
            }

            //make sure there is a plugin
            if (empty($this->jname)) {
                $result = false;
            } else {
                $this->helper->_debug('onAfterContentSave called');

                //validate the article
                $this->helper->thread_status = $this->helper->_get_thread_status();
                // changed _validate to pass the $isNew flag, so that it will only check will happen depending on this flag
                list($this->valid, $this->validity_reason) = $this->helper->_validate($isNew);
                $this->helper->_debug('Validity: ' . $this->valid . '; ' . $this->validity_reason);

                //ignore auto mode if the article has been manually plugged
                $manually_plugged = preg_match('/\{jfusion_discuss (.*)\}/U', $this->article->introtext . $this->article->fulltext);

                $this->helper->_debug('Checking mode...');
                if ($this->mode=='auto' && empty($manually_plugged)) {
                    $this->helper->_debug('In auto mode');

                    if ($this->valid) {
                        $threadinfo =& $this->helper->_get_thread_info();
                        $JFusionForum =& JFusionFactory::getForum($this->jname);
                        $forumid = $JFusionForum->getDefaultForum($this->params, $this->article);

                        if (($this->creationMode=='load') ||
                            ($this->creationMode=='new' && ($isNew || (!$isNew && $this->helper->thread_status))) ||
                            ($this->creationMode=='reply' && $this->helper->thread_status)) {

                            //update/create thread
                            $this->helper->_check_thread_exists();

                        } else {
                            $this->helper->_debug('Article did not meet requirements to update/create thread');
                        }
                    } elseif ($this->creationMode=='new' && $isNew) {
                        $this->helper->_debug('Failed validity test but creationMode is set to new and this is a new article');

                        $mainframe = JFactory::getApplication();
                        $publish_up = JFactory::getDate($this->article->publish_up)->toUnix();
                        $now = JFactory::getDate('now', $mainframe->getCfg('offset'))->toUnix();
                        if ($now < $publish_up || !$this->article->state) {
                            $this->helper->_debug('Article set to be published in the future or is unpublished thus creating an entry in the database so that the thread is created when appropriate.');

                            //the publish date is set for the future so create an entry in the
                            //database so that the thread is created when the publish date arrives
                            $placeholder = new stdClass();
                            $placeholder->threadid = 0;
                            $placeholder->forumid = 0;
                            $placeholder->postid = 0;
                            JFusionFunction::updateDiscussionBotLookup($this->article->id, $placeholder, $this->jname);
                        }
                    }
                } elseif ($this->mode=='test' && empty($manually_plugged)) {
                    //recheck validity without stipulation
                    $this->helper->_debug('In test mode thus not creating the article');
                    $threadinfo =& $this->helper->_get_thread_info();
                    $JFusionForum =& JFusionFactory::getForum($this->jname);
                    $content = '<u>' . $this->article->title . '</u><br />';
                    if (!empty($threadinfo)) {
                        $content .= JText::_('DISCUSSBOT_TEST_MODE') . '<img src="'.JFusionFunction::getJoomlaURL().DISCUSSBOT_URL_PATH.'images/check.png" style="margin-left:5px;"><br/>';
                        if ($threadinfo->published) {
                            $content .= JText::_('STATUS') . ': ' . JText::_('INITIALIZED_AND_PUBLISHED') . '<br />';
                        } else {
                            $content .= JText::_('STATUS') . ': ' . JText::_('INITIALIZED_AND_UNPUBLISHED') . '<br />';
                        }
                        $content .= JText::_('THREADID') . ': ' . $threadinfo->threadid . '<br />';
                        $content .= JText::_('FORUMID') . ': ' . $threadinfo->forumid . '<br />';
                        $content .= JText::_('FIRST_POSTID') . ': ' . $threadinfo->postid. '<br />';

                        $forumlist =& $this->helper->_get_lists('forum');
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
                            $content .= JText::_('DISCUSSBOT_TEST_MODE') . '<img src="'.JFusionFunction::getJoomlaURL().DISCUSSBOT_URL_PATH.'images/x.png" style="margin-left:5px;"><br/>';
                            $content .= JText::_('VALID') . ': ' . $valid . '<br />';
                            $content .= JText::_('INVALID_REASON') . ': ' . $this->validity_reason . '<br />';
                        } else {
                            $content .= '<b>' . JText::_('DISCUSSBOT_TEST_MODE') . '</b><img src="'.JFusionFunction::getJoomlaURL().DISCUSSBOT_URL_PATH.'images/check.png" style="margin-left:5px;2><br/>';
                            $content .= JText::_('VALID_REASON') . ': ' . $this->validity_reason . '<br />';
                            $content .= JText::_('STATUS') . ': ' . JText::_('UNINITIALIZED_THREAD_WILL_BE_CREATED') . '<br />';
                            $forumid = $JFusionForum->getDefaultForum($this->params, $this->article);
                            $content .= JText::_('FORUMID') . ': ' . $forumid . '<br />';
                            $author = $JFusionForum->getThreadAuthor($this->params, $this->article);
                            $content .= JText::_('AUTHORID') . ': ' . $author . '<br />';
                        }
                    }
                    JError::raiseNotice('500', $content);
                } else {
                    $this->helper->_debug('In manual mode...checking to see if article has been initialized');
                    $threadinfo =& $this->helper->_get_thread_info();
                    if (!empty($threadinfo) && $threadinfo->published == 1 && $threadinfo->manual == 1) {
                        $this->helper->_debug('Article has been initialized...updating thread');
                        //update thread
                        $this->helper->_check_thread_exists();
                    } else {
                        $this->helper->_debug('Article has not been initialized');
                    }
                }
                $this->helper->_debug('onAfterContentSave complete', true);
            }
        }
        return $result;
    }

    /**
     * @param $subject
     * @param $params
     * @return bool
     */
    public function onPrepareContent(&$subject, $params)
    {
        $result = true;
        $this->article =& $subject;
        $this->helper->article =& $this->article;
        if ($this->debug_mode) {
            $session = JFactory::getSession();
            $this->helper->debug_output = $session->get('jfusion.discussion.debug.' . $this->article->id,false);
            if ($this->helper->debug_output!==false) {
                $this->clear_debug_output = false;
            }
            $session->clear('jfusion.discussion.debug.' . $this->article->id);
            if (!is_array($this->helper->debug_output)) {
                $this->helper->debug_output = array();
            }
        }

        //reset some vars
        $this->manual_plug = false;
        $this->manual_threadid = 0;
        if ($this->clear_debug_output) {
            $this->helper->debug_output = array();
        }
        $this->helper->thread_status = '';
        $this->validity_reason = '';

        $this->helper->_debug('onPrepareContent called');

        //check to see if a valid $content object was passed on
        if (!is_object($subject)){
            JFusionFunction::raiseWarning(JText::_('DISCUSSBOT_ERROR'), JText::_('NO_CONTENT_DATA_FOUND'), 1);
            $result = false;
        } else {
            //make sure there is a plugin
            if (empty($this->jname)) {
                $result = false;
            } else {
                //do nothing if this is a K2 category object
                if ($this->helper->option == 'com_k2' && get_class($this->article) == 'TableK2Category') {
                    $result = false;
                } else {
                    //set some variables needed throughout
                    $this->template = $this->params->get('template','default');

                    //make sure we have an actual article
                    if (!empty($this->article->id)) {
                        $this->dbtask = JRequest::getVar('dbtask', 'render_content', 'post');
                        $skip_new_check = ($this->dbtask=='create_thread') ? true : false;
                        $skip_k2_check = ($this->helper->option == 'com_k2' && in_array($this->dbtask, array('unpublish_discussion', 'publish_discussion'))) ? true : false;
                        $this->helper->thread_status = $this->helper->_get_thread_status();
                        list($this->valid, $this->validity_reason) = $this->helper->_validate($skip_new_check, $skip_k2_check);
                        $this->helper->_debug('Validity: ' . $this->valid . "; " . $this->validity_reason);
                        $this->ajax_request = JRequest::getInt('ajax_request',0);

                        if ($this->ajax_request) {
                            //get and set the threadinfo
                            $threadid = JRequest::getInt('threadid', 0, 'post');
                            $threadinfo = $this->helper->_get_thread_info();
                            if (empty($threadinfo))  {
                                //could be a manual plug so let's get the thread info directly
                                $JFusionForum =& JFusionFactory::getForum($this->jname);
                                $threadinfo = $JFusionForum->getThread($threadid);
                                if (!empty($threadinfo)) {
                                    //let's set threadinfo
                                    $threadinfo->published = 1;
                                    $this->helper->_get_thread_info(false, $threadinfo);
                                    //override thread status
                                    $this->helper->thread_status = true;
                                    //set manual plug
                                    $this->manual_plug = true;
                                } elseif ($this->dbtask != 'create_thread' && $this->dbtask != 'create_threadpost') {
                                    die('Thread not found!');
                                }
                            }
                        }

                        if ($this->dbtask == 'create_thread') {
                            //this article has been manually initiated for discussion
                            $this->_create_thread();
                        } elseif (($this->dbtask == 'create_post' || $this->dbtask == 'create_threadpost') && $this->params->get('enable_quickreply',false)) {
                            //a quick reply has been submitted so let's create the post
                            $this->_create_post();
                        } elseif ($this->dbtask == 'unpublish_discussion') {
                            //an article has been "uninitiated"
                            $this->_unpublish_discussion();
                        } elseif ($this->dbtask == 'publish_discussion') {
                            //an article has been "reinitiated"
                            $this->_publish_discussion();
                        }

                        //save the visibility of the posts if applicable
                        $show_discussion = JRequest::getVar('show_discussion','');
                        if ($show_discussion!=='') {
                            $JSession = JFactory::getSession();
                            $JSession->set('jfusion.discussion.visibility',(int) $show_discussion);
                        }

                        //check for some specific ajax requests
                        if ($this->ajax_request) {
                            //check to see if this is an ajax call to update the pagination
                            if ($this->params->get('enable_pagination',1) && $this->dbtask == 'update_pagination') {
                                $this->_update_pagination();
                                die('Something else was suppose to happen');
                            }

                            if ($this->params->get('show_posts',1) && $this->dbtask == 'update_posts') {
                                $this->_update_posts();
                                die('Something else was suppose to happen');
                            }

                            if ($this->dbtask=='update_content') {
                                $threadinfo =& $this->helper->_get_thread_info();
                                if (!empty($threadinfo->published)) {
                                    //content is now published so display it
                                    die($this->_render_discussion_content($threadinfo));
                                } else {
                                    //content is now not published so remove it
                                    die();
                                }
                            }

                            if ($this->dbtask == 'update_buttons') {
                                $this->_update_buttons();
                                die('Something else was suppose to happen');
                            }

                            if ($this->dbtask == 'update_debug_info') {
                                $this->_render_debug_output();
                                die('Something else was suppose to happen');
                            }

                            if ($show_discussion!=='') {
                                die('jfusion.discussion.visibility set to '.$show_discussion);
                            }

                            die("Discussion bot ajax request made but it doesn't seem to have been picked up");
                        }

                        //add scripts to header
                        static $scriptsLoaded;
                        if (empty($scriptsLoaded)) {
                            $this->helper->_load_scripts();
                            $scriptsLoaded = 1;
                        }

                        if (empty($this->article->params) && !empty($this->article->parameters)) {
                            $this->article->params =& $this->article->parameters;
                        }

                        if (!empty($this->article->params)) {
                            $this->_prepare_content();
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * joomla 1.6 compatibility layer
     *
     * @param $context
     * @param $article
     * @param $isNew
     */
    public function onContentAfterSave($context, &$article, $isNew)
	{
 	    $this->onAfterContentSave($article, $isNew);
	}

    /**
     * @param $context
     * @param $article
     * @param $params
     * @param int $limitstart
     */
    public function onContentPrepare($context, &$article, &$params, $limitstart=0)
	{
 		//seems syntax has completely changed :(
		$this->onPrepareContent($article, $params);
	}

    /**
     * @param $context
     * @param $article
     * @param $params
     * @param int $limitstart
     */
    public function onContentAfterDisplay($context, &$article, &$params, $limitstart=0)
	{
	    $view = JRequest::getVar('view');
	    $layout = JRequest::getVar('layout');

        if ($this->helper->option == 'com_content') {
            if ($view == 'featured' || ($view == 'category' && $layout == 'blog')) {
                $article->text = $article->introtext;
                $this->onPrepareContent($article, $params);
                $article->introtext = $article->text;
            }
        }
	}

    /*
     * _prepare_content
     */
    public function _prepare_content()
    {
        JHTML::_( 'behavior.mootools' );
        $this->helper->_debug('Preparing content');

        $content = '';
        //get the jfusion forum object
        $JFusionForum =& JFusionFactory::getForum($this->jname);

        //find any {jfusion_discuss...} to manually plug
        $this->helper->_debug('Finding all manually added plugs');
        preg_match_all('/\{jfusion_discuss (.*)\}/U',$this->article->text,$matches);
        $this->helper->_debug(count($matches[1]) . ' matches found');

        foreach($matches[1] as $id) {
            //only use the first and get rid of the others
            if (empty($this->manual_plug)) {
                $this->manual_plug = true;
                $this->helper->_debug('Plugging for thread id ' . $id);
                //get the existing thread information
                $threadinfo = $JFusionForum->getThread($id);

                if (!empty($threadinfo)) {
                    //manually plugged so definitely published
                    $threadinfo->published = 1;
                    //$threadinfo->manual = 1;
                    //set threadinfo
                    $this->helper->_get_thread_info(false, $threadinfo);

                    $this->helper->_debug('Thread info found.');

                    //override thread status
                    $this->helper->thread_status = true;
                    $content = $this->_render($threadinfo);
                    $this->article->text = str_replace("{jfusion_discuss $id}",$content,$this->article->text);
                } else {
                    $this->helper->_debug('Thread info not found!');
                    $this->article->text = str_replace("{jfusion_discuss $id}",JText::_("THREADID_NOT_FOUND"),$this->article->text);
                }

            } else {
                $this->helper->_debug('Removing plug for thread ' . $id);
                $this->article->text = str_replace("{jfusion_discuss $id}",'',$this->article->text);
            }
        }

        //check to see if the fulltext has a manual plug if we are in a blog view
        if (isset($this->article->fulltext)) {
            $test_view = ($this->helper->option == 'com_k2') ? 'item' : 'article';
            if (!$this->manual_plug && JRequest::getVar('view') != $test_view) {
                preg_match('/\{jfusion_discuss (.*)\}/U',$this->article->fulltext,$match);
                if (!empty($match)) {
                    $this->helper->_debug('No plugs in text but found plugs in fulltext');
                    $this->manual_plug = true;
                    $this->manual_threadid = $match[1];

                    //get the existing thread information
                    $threadinfo = $JFusionForum->getThread($this->manual_threadid);

                    if (!empty($threadinfo)) {
                        //manually plugged so definitely published
                        $threadinfo->published = 1;
                        //$threadinfo->manual = 1;

                        //create buttons for the manually plugged article
                        //set threadinfo
                        $this->helper->_get_thread_info(false, $threadinfo);
                        $content = $this->_render_buttons(false);

                        //append the content
                        $this->article->text .= $content;
                    } else {
                        $this->article->text .= JText::_('THREADID_NOT_FOUND');
                    }
                }
            }
        }

        //check for auto mode if not already manually plugged
        if (!$this->manual_plug) {
            $this->helper->_debug('Article not manually plugged...checking for other mode');
            $threadinfo =& $this->helper->_get_thread_info();

            //create the thread if this article has been validated
            if ($this->mode=='auto') {
                $this->helper->_debug('In auto mode');
                if ($this->valid) {
                    $status = $this->helper->_check_thread_exists();
                    if ($status['action'] == 'created') {
                        $threadinfo = $status['threadinfo'];
                    }
                }
                if ($this->validity_reason != JText::_('REASON_NOT_IN_K2_ARTICLE_TEXT')) {
                    //a catch in case a plugin does something wrong
                    if (!empty($threadinfo->threadid) || $this->creationMode == 'reply') {
                        $content = $this->_render($threadinfo);
                    }
                }
            } elseif ($this->mode=='test') {
                $this->helper->_debug('In test mode');
                //get the existing thread information
                $content  = '<div class="jfusionclearfix" style="border:1px solid #ECF8FD; background-color:#ECF8FD; margin-top:10px; margin-bottom:10px;">';

                if (!empty($threadinfo)) {
                    $content .= '<b>' . JText::_('DISCUSSBOT_TEST_MODE') . '</b><img src="'.JFusionFunction::getJoomlaURL().DISCUSSBOT_URL_PATH.'images/check.png" style="margin-left:5px;"><br/>';
                    if ($threadinfo->published) {
                        $content .= JText::_('STATUS') . ': ' . JText::_('INITIALIZED_AND_PUBLISHED') . '<br />';
                    } else {
                        $content .= JText::_('STATUS') . ': ' . JText::_('INITIALIZED_AND_UNPUBLISHED') . '<br />';
                    }
                    $content .= JText::_('THREADID') . ': ' . $threadinfo->threadid . '<br />';
                    $content .= JText::_('FORUMID') . ': ' . $threadinfo->forumid . '<br />';
                    $content .= JText::_('FIRST_POSTID') . ': ' . $threadinfo->postid. '<br />';

                    $forumlist =& $this->helper->_get_lists('forum');
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
                        $content .= '<b>' . JText::_('DISCUSSBOT_TEST_MODE') . '</b><img src="'.JFusionFunction::getJoomlaURL().DISCUSSBOT_URL_PATH.'images/x.png" style="margin-left:5px;"><br/>';
                        $content .= JText::_('VALID') . ': ' . $valid . '<br />';
                        $content .= JText::_('INVALID_REASON') . ': ' . $this->validity_reason . '<br />';
                    } else {
                        $content .= '<b>' . JText::_('DISCUSSBOT_TEST_MODE') . '</b><img src="'.JFusionFunction::getJoomlaURL().DISCUSSBOT_URL_PATH.'images/check.png" style="margin-left:5px;"><br/>';
                        $content .= JText::_('VALID_REASON') . ': ' . $this->validity_reason . '<br />';
                        $content .= JText::_('STATUS') . ': ' . JText::_('UNINITIALIZED_THREAD_WILL_BE_CREATED') . '<br />';
                        $forumid = $JFusionForum->getDefaultForum($this->params, $this->article);
                        $content .= JText::_('FORUMID') . ': ' . $forumid . '<br />';
                        $author = $JFusionForum->getThreadAuthor($this->params, $this->article);
                        $content .= JText::_('AUTHORID') . ': ' . $author . '<br />';
                    }
                }
                $content .= '</div>';
            } elseif (!empty($threadinfo->manual)) {
                if (!empty($threadinfo->published)) {
                    $this->helper->_debug('In manual mode but article has been initialized');
                    //this article was generated by the initialize button
                    $content = $this->_render($threadinfo);
                } else {
                    $this->helper->_debug('In manual mode but article was initialized then uninitialized');
                    $content = $this->_render_buttons();
                }
            } else {
                $this->helper->_debug('In manual mode');
                //in manual mode so just create the buttons
                if ($this->validity_reason != JText::_('REASON_NOT_IN_K2_ARTICLE_TEXT')) {
                    $content = $this->_render_buttons();
                }
            }

            //append the content
            $this->article->text .= $content;
        }

        static $taskFormLoaded;
        if (empty($taskFormLoaded)) {
            $this->helper->_debug('Adding task form');
            //tak on the task form; it only needs to be added once which will be used for create_thread
            $uri = JFactory::getURI();
            $url = $uri->toString(array('path', 'query', 'fragment'));
            $url = str_replace('&', '&amp;', $url);

            $content = <<<HTML
                <form style="display:none;" id="JFusionTaskForm" name="JFusionTaskForm" method="post" action="{$url}">
                    <input type="hidden" name="articleId" value="" />
                    <input type="hidden" name="dbtask" value="" />
                </form>
HTML;
            $this->article->text .= $content;

            $taskFormLoaded = 1;
        }

        $this->_render_debug_output();
    }

    /*
     * _render_debug_output
     */
    public function _render_debug_output()
    {
        if ($this->debug_mode) {
            require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.debug.php');

            ob_start();
            debug::show($this->helper->debug_output, 'Discussion bot debug info',1);
            $debug_contents = ob_get_contents();
            ob_end_clean();

            if ($this->ajax_request) {
                die($debug_contents);
            } else {
                $this->article->text = <<<HTML
                    <div id="jfusionDebugContainer{$this->article->id}">
                        {$debug_contents}
                    </div>
HTML;
            }
        }
    }

    /*
     * _create_thread
     */
    public function _create_thread()
    {
        $JoomlaUser = JFactory::getUser();
        $mainframe = JFactory::getApplication();
        $return = JRequest::getVar('return');
        if ($return) {
            $url = base64_decode($return);
        } else {
            $uri = JFactory::getURI();
            $url = $uri->toString(array('path', 'query', 'fragment'));
            $url = JRoute::_($url, false);
            if ($uri->getVar('view')=='article') {
                //tak on the discussion jump to
                $url .= '#discussion';

                $JSession = JFactory::getSession();
                $JSession->set('jfusion.discussion.visibility',1);
            }
        }

        //make sure the article submitted matches the one loaded
        $submittedArticleId = JRequest::getInt('articleId', 0, 'post');
        $editAccess = $JoomlaUser->authorize('com_content', 'edit', 'content', 'all');

        if ($editAccess && $this->valid && $submittedArticleId == $this->article->id) {
            $status = $this->helper->_check_thread_exists(1);

            if (!empty($status['error'])) {
                if (is_array($status['error'])) {
                    foreach($status['error'] as $err) {
                        $mainframe->enqueueMessage('error',JText::_('DISCUSSBOT_ERROR'). ': ' . $err);
                    }
                } else {
                    $mainframe->enqueueMessage('error',JText::_('DISCUSSBOT_ERROR'). ': ' . $status['error']);
                }

                $mainframe->redirect($url);

            } else {
                $mainframe->redirect($url, JText::sprintf('THREAD_CREATED_SUCCESSFULLY',$this->article->title));
            }
        }
    }

    /*
     * _create_post
     */
    public function _create_post()
    {
        $JoomlaUser = JFactory::getUser();
        $JFusionForum =& JFusionFactory::getForum($this->jname);

        //define some variables
        $allowGuests =& $this->params->get('quickreply_allow_guests',0);
        $ajaxEnabled = ($this->params->get('enable_ajax',1) && $this->ajax_request);

        //process quick replies
        if (($allowGuests || !$JoomlaUser->guest) && !$JoomlaUser->block) {
            //make sure something was submitted
            $quickReplyText = JRequest::getVar('quickReply', '', 'POST');

            if (!empty($quickReplyText)) {
                //retrieve the userid from forum software
                if ($allowGuests && $JoomlaUser->guest) {
                    $userinfo = new stdClass();
                    $userinfo->guest = 1;

                    $captcha_verification = $JFusionForum->verifyCaptcha($this->params);
                } else {
                    $JFusionUser =& JFusionFactory::getUser($this->jname);
                    $userinfo = $JFusionUser->getUser($JoomlaUser);
                    $userinfo->guest = 0;
                    //we have a user logged in so ignore captcha
                    $captcha_verification = true;
                }

                if ($captcha_verification) {
                    $threadinfo = null;
                    if ($this->dbtask=='create_threadpost') {
                        $status = $this->helper->_check_thread_exists();
                        $threadinfo = $status['threadinfo'];
                    } elseif ($this->dbtask=="create_post") {
                        $threadinfo =& $this->helper->_get_thread_info();
                    }

                    //create the post
                    if (!empty($threadinfo) && !empty($threadinfo->threadid) && !empty($threadinfo->forumid)) {
                        $status = $JFusionForum->createPost($this->params, $threadinfo, $this->article, $userinfo);

                        if (!empty($status['error'])){
                            if ($ajaxEnabled) {
                                //output the error
                                if (is_array($status['error'])) {
                                    if (count($status['error']) < 2) {
                                        $error = $status['error'][0];
                                    } else {
                                        $error = '';
                                        foreach($status['error'] as $err) {
                                           $error .= '<br /> - ' . $err;
                                        }
                                    }
                                } else {
                                    $error = $status['error'];
                                }
                                die(JText::_('DISCUSSBOT_ERROR') . ': ' . $error);
                            } else {
                                JFusionFunction::raiseWarning(JText::_('DISCUSSBOT_ERROR'), $status['error'],1);
                            }
                        } else {
                            if ($ajaxEnabled) {
                                //if pagination is set, set $limitstart so that we go to the added post
                                if ($this->params->get('enable_pagination',true)) {
                                    $replyCount = $JFusionForum->getReplyCount($threadinfo);
                                    $application = JFactory::getApplication();
                                    $limit = $application->getUserStateFromRequest( 'global.list.limit', 'limit_discuss', 5, 'int' );

                                    if ($this->params->get('sort_posts','ASC')=='ASC') {
                                        $limitstart = floor(($replyCount-1)/$limit) * $limit;
                                    } else {
                                        $limitstart = 0;
                                    }
                                    JRequest::setVar('limitstart_discuss',$limitstart);
                                }

                                $posts = $JFusionForum->getPosts($this->params, $threadinfo);
                                $this->helper->output = array();
                                $this->helper->output['posts'] = $this->_prepare_posts_output($posts);

                                //take note of the created post
                                $this->helper->output['submitted_postid'] = $status['postid'];
                                if (isset($status['post_moderated'])) {
                                    $this->helper->output['post_moderated'] = $status['post_moderated'];
                                } else {
                                    $this->helper->output['post_moderated'] = 0;
                                }

                                //output only the new post div
                                $this->helper->threadinfo =& $threadinfo;
                                $this->helper->_render_file('default_posts.php','die');
                            } else {
                                if ($this->params->get('jumpto_new_post',0)) {
                                    $jumpto = (isset($status['postid'])) ? "post" . $status['postid'] : '';
                                } else {
                                    $jumpto = '';
                                }
                                $url = $this->helper->_get_article_url($jumpto,'',false);

                                if (isset($status['post_moderated'])) {
                                    $text = ($status['post_moderated']) ? 'SUCCESSFUL_POST_MODERATED' : 'SUCCESSFUL_POST';
                                } else {
                                    $text = 'SUCCESSFUL_POST';
                                }
                                $mainframe = JFactory::getApplication();
                                $mainframe->redirect($url, JText::_($text));
                            }
                        }
                    } else {
                        if ($ajaxEnabled) {
                            die(JText::_('DISCUSSBOT_ERROR') . ': ' . JText::_('THREADID_NOT_FOUND'));
                        } else {
                            JFusionFunction::raiseWarning(JText::_('DISCUSSBOT_ERROR'), JText::_('THREADID_NOT_FOUND'),1);
                        }
                    }
                } else {
                    if ($ajaxEnabled) {
                        die(JText::_('DISCUSSBOT_ERROR') . ': ' . JText::_('CAPTCHA_INCORRECT'));
                    } else {
                        JFusionFunction::raiseWarning(JText::_('DISCUSSBOT_ERROR'), JText::_('CAPTCHA_INCORRECT'),1);
                    }
                }
            } else {
                if ($ajaxEnabled) {
                    die(JText::_('DISCUSSBOT_ERROR') . ': ' . JText::_('QUICKEREPLY_EMPTY'));
                } else {
                    JFusionFunction::raiseWarning(JText::_('DISCUSSBOT_ERROR'), JText::_('QUICKEREPLY_EMPTY'),1);
                }
            }
        }

        //if ajax is enabled, then something has gone wrong so die
        if ($ajaxEnabled) die(JText::_('DISCUSSBOT_ERROR'));
    }

    /*
     * _unpublish_discussion
     */
    public function _unpublish_discussion()
    {
        $JoomlaUser = JFactory::getUser();

        //make sure the article submitted matches the one loaded
        $submittedArticleId = JRequest::getInt('articleId', 0, 'post');
        $editAccess = $JoomlaUser->authorize('com_content', 'edit', 'content', 'all');

        if ($editAccess && $this->valid && $submittedArticleId == $this->article->id) {
            $threadinfo =& $this->helper->_get_thread_info();

            if (!empty($threadinfo)) {
                //created by discussion bot thus update the look up table
                JFusionFunction::updateDiscussionBotLookup($this->article->id, $threadinfo, $this->jname, 0, $threadinfo->manual);
            } else {
                //manually plugged thus remove any db plugin tags
                $jdb = JFactory::getDBO();
                //retrieve the original text
                $query = 'SELECT `introtext`, `fulltext` FROM #__content WHERE id = ' . $this->article->id;
                $jdb->setQuery($query);
                $texts = $jdb->loadObject();

                //remove any {jfusion_discuss...}
                $fulltext = preg_replace('/\{jfusion_discuss (.*)\}/U','',$texts->fulltext, -1, $fullTextCount);
                $introtext = preg_replace('/\{jfusion_discuss (.*)\}/U','',$texts->introtext, -1, $introTextCount);

                if (!empty($fullTextCount) || !empty($introTextCount)) {
                    $query = 'UPDATE #__content SET `fulltext` = ' . $jdb->Quote($fulltext) . ', `introtext` = ' .$jdb->Quote($introtext) . ' WHERE id = ' . (int) $this->article->id;
                    $jdb->setQuery($query);
                    $jdb->query();
                }
            }

            if ($this->ajax_request) {
                $this->helper->thread_status = $this->helper->_get_thread_status();
                die($this->_render_buttons(true));
            }
        } else {
            die('Access denied!');
        }
    }

    /*
     * _publish_discussion
     */
    public function _publish_discussion()
    {
        $JoomlaUser = JFactory::getUser();

        //make sure the article submitted matches the one loaded
        $submittedArticleId = JRequest::getInt('articleId', 0, 'post');
        $editAccess = $JoomlaUser->authorize('com_content', 'edit', 'content', 'all');

        if ($editAccess && $this->valid && $submittedArticleId == $this->article->id) {
            $threadinfo =& $this->helper->_get_thread_info();
            JFusionFunction::updateDiscussionBotLookup($this->article->id, $threadinfo, $this->jname, 1, $threadinfo->manual);
            if ($this->ajax_request) {
                $this->helper->thread_status = $this->helper->_get_thread_status();
                die($this->_render_buttons(true));
            }
        } else {
            die('Access denied!');
        }
    }

    /**
     * @param $threadinfo
     * @return bool|string
     */
    public function _render(&$threadinfo)
    {
        $this->helper->_debug('Beginning rendering content');
        if (!empty($threadinfo)) {
            $JFusionForum =& JFusionFactory::getForum($this->jname);
            $this->helper->reply_count = $JFusionForum->getReplyCount($threadinfo);
        }
        $view = JRequest::getVar('view');
        $test_view = ($this->helper->option == 'com_k2') ? 'item' : 'article';

        //let's only show quick replies and posts on the article view
        if ($view == $test_view) {
            $JSession = JFactory::getSession();

            if (empty($threadinfo->published) && $this->creationMode != 'reply') {
                $this->helper->_debug('Discussion content not displayed as this discussion is unpublished');
                $display = 'none';
                $generate_guts = false;
            } else {
                if ($JSession->get('jfusion.discussion.visibility',0) || empty($threadinfo) && $this->creationMode == 'reply') {
                    //show the discussion area if no replies have been made and creationMode is set to on first reply OR if user has set it to show
                    $display = 'block';
                } else {
                    $display = ($this->params->get('show_toggle_posts_link',1) && $this->params->get('collapse_discussion',1)) ? 'none' : 'block';
                }
                $generate_guts = true;
            }

            $content = '<div style="float:none; display:'.$display.';" id="discussion">';

            if ($generate_guts) {
                $content .= $this->_render_discussion_content($threadinfo);
            }

            $content .= '</div>';

            //now generate the buttons in case the thread was just created
            $button_content  = $this->_render_buttons();
            $content = $button_content . $content;
        } else {
            $content = $this->_render_buttons();
        }

        return $content;
    }


    /**
     * @param $threadinfo
     * @return bool|string
     */
    public function _render_discussion_content(&$threadinfo)
    {
        $this->helper->_debug('Rendering discussion content');

        //setup parameters
        $JFusionForum =& JFusionFactory::getForum($this->jname);
        $allowGuests =& $this->params->get('quickreply_allow_guests',0);
        $JoomlaUser = JFactory::getUser();
        //make sure the user exists in the software before displaying the quick reply
        $JFusionUser =& JFusionFactory::getUser($this->jname);
        $JFusionUserinfo = $JFusionUser->getUser($JoomlaUser);
        $action_url = $this->helper->_get_article_url();
        $this->helper->output = array();
        $this->helper->output['reply_count'] = '';

        $show_form = ($allowGuests || (!$JoomlaUser->guest && !empty($JFusionUserinfo)) && !$JoomlaUser->block) ? 1 : 0;
        
        if (!empty($threadinfo)) {
            if ($this->helper->reply_count === false || $this->helper->reply_count === null) {
                $this->helper->reply_count = $JFusionForum->getReplyCount($threadinfo);
            }
            //prepare quick reply box if enabled
            if ($this->params->get('enable_quickreply')){
                $threadLocked = $JFusionForum->getThreadLockedStatus($threadinfo->threadid);
                if ($threadLocked) {
                    $this->helper->output['reply_form_error'] = $this->params->get('locked_msg');
                    $this->helper->output['show_reply_form'] = false;
                } elseif ($show_form) {
                    if (!$JoomlaUser->guest && empty($JFusionUserinfo)) {
                        $this->helper->output['reply_form_error'] =  $this->jname . ': ' . JText::_('USER_NOT_EXIST');
                        $this->helper->output['show_reply_form'] = false;
                    } else {
                        $showGuestInputs = ($allowGuests && $JoomlaUser->guest) ? true : false;
                        $this->helper->output['reply_form']  = '<form id="jfusionQuickReply'.$this->article->id.'" name="jfusionQuickReply'.$this->article->id.'" method="post" action="'.$action_url.'">';
                        $this->helper->output['reply_form'] .= '<input type="hidden" name="dbtask" value="create_post" />';
                        $this->helper->output['reply_form'] .= '<input type="hidden" name="threadid" id="threadid" value="'.$threadinfo->threadid.'"/>';
                        $page_limitstart = JRequest::getInt('limitstart', 0);
                        if ($page_limitstart) {
                            $this->helper->output['reply_form'] .= '<input type="hidden" name="limitstart" value="'.$page_limitstart.'" />';
                        }
                        $this->helper->output['reply_form'] .= $JFusionForum->createQuickReply($this->params,$showGuestInputs);
                        $this->helper->output['reply_form'] .= '</form>';
                        $this->helper->output['show_reply_form'] = true;
                    }
                } else {
                    $this->helper->output['reply_form_error'] = $this->params->get('must_login_msg');
                    $this->helper->output['show_reply_form'] = false;
                }
            }

            //add posts to content if enabled
            if ($this->params->get('show_posts')) {
                //get the posts
                $posts = $JFusionForum->getPosts($this->params, $threadinfo);

                if (!empty($posts)){
                    $this->helper->output['posts'] = $this->_prepare_posts_output($posts);
                }

                if ($this->params->get('enable_pagination',1)) {
                    $application = JFactory::getApplication() ;
                    $limitstart = JRequest::getInt( 'limitstart_discuss', 0 );
                    $limit = (int) $application->getUserStateFromRequest( 'global.list.limit', 'limit_discuss', 5, 'int' );
                    $this->helper->output['post_pagination']  = '<div id="jfusionPostPagination" class="pagination">';
                    if (!empty($this->helper->reply_count) && $this->helper->reply_count > 5) {
                        $pageNav = new JFusionPagination($this->helper->reply_count, $limitstart, $limit, '_discuss' );
                        $this->helper->output['post_pagination'] .= '<form method="post" id="jfusionPaginationForm" name="jfusionPaginationForm" action="'.$action_url.'">';
                        $this->helper->output['post_pagination'] .= '<input type="hidden" name="jumpto_discussion" value="1" />';
                        $this->helper->output['post_pagination'] .= $pageNav->getListFooter();
                        $this->helper->output['post_pagination'] .= '</form>';
                    }
                    $this->helper->output['post_pagination'] .= '</div>';
                } else {
                    $this->helper->output['post_pagination'] = '';
                }
            } else {
                $this->helper->output['posts'] = '';
                $this->helper->output['post_pagination'] = '';
            }
        } elseif ($this->creationMode=='reply') {
            //prepare quick reply box if enabled
            if ($show_form) {
                if (!$JoomlaUser->guest && empty($JFusionUserinfo)) {
                    $this->helper->output['reply_form_error'] =  $this->jname . ': ' . JText::_('USER_NOT_EXIST');
                    $this->helper->output['show_reply_form'] = false;
                } else {
                    $showGuestInputs = ($allowGuests && $JoomlaUser->guest) ? true : false;
                    $this->helper->output['reply_form']  = '<form id="jfusionQuickReply'.$this->article->id.'" name="jfusionQuickReply'.$this->article->id.'" method="post" action="'.$action_url.'">';
                    $this->helper->output['reply_form'] .= '<input type="hidden" name="dbtask" value="create_threadpost"/>';
                    $page_limitstart = JRequest::getInt('limitstart', 0);
                    if ($page_limitstart) {
                        $this->helper->output['reply_form'] .= '<input type="hidden" name="limitstart" value="'.$page_limitstart.'" />';
                    }
                    $this->helper->output['reply_form'] .= $JFusionForum->createQuickReply($this->params,$showGuestInputs);
                    $this->helper->output['reply_form'] .= '</form>';
                    $this->helper->output['show_reply_form'] = true;
                }
            } else {
                $this->helper->output['reply_form_error'] = $this->params->get('must_login_msg');
                $this->helper->output['show_reply_form'] = false;
            }
        }

        //populate the template
        $this->helper->threadinfo =& $threadinfo;
        $content = $this->helper->_render_file('default.php','capture');
        return $content;
    }

    /**
     * @param bool $innerhtml
     *
     * @return bool|string
     */
    public function _render_buttons($innerhtml = false)
    {
        $this->helper->_debug('Rendering buttons');

        //setup some variables
        $threadinfo =& $this->helper->_get_thread_info();

        $JUser = JFactory::getUser();
        $itemid =& $this->params->get('itemid');
        $link_text =& $this->params->get('link_text');
        $link_type=& $this->params->get('link_type','text');
        $link_mode=& $this->params->get('link_mode','always');
        $blog_link_mode=& $this->params->get('blog_link_mode','forum');
        $linkHTML = ($link_type=='image') ? '<img style="border:0;" src="'.$link_text.'">' : $link_text;
        $linkTarget =& $this->params->get('link_target','_parent');
        if ($this->helper->isJ16) {
            if ($this->helper->option == 'com_content') {
                $article_access = $this->article->params->get('access-view');
            } elseif ($this->helper->option == 'com_k2') {
                $article_access = (in_array($this->article->access, $JUser->authorisedLevels()) && in_array($this->article->category->access, $JUser->authorisedLevels()));
            } else {
                $article_access = 1;
            }
        } else {
            if ($this->helper->option == 'com_content') {
                $article_access = ($this->article->access <= $JUser->get('aid', 0));
            } elseif ($this->helper->option == 'com_k2') {
                $article_access = ($this->article->access <= $JUser->get('aid', 0) && $this->article->category->access <= $JUser->get('aid', 0));
            } else {
                $article_access = 1;
            }
        }
        //prevent notices and warnings in default_buttons.php if there are no buttons to display
        $this->helper->output = array();
        $this->helper->output['buttons'] = array();
        /**
         * @ignore
         * @var $article_params JParameter
         */
        $attribs = $readmore_param = $article_params = null;
        $show_readmore = $readmore_catch = 0;
        if ($this->helper->option == 'com_content') {
            $attribs = new JParameter($this->article->attribs);

            if (isset($this->article->params)) {
                //blog view
                $article_params =& $this->article->params;
                $show_readmore = $article_params->get('show_readmore');
                $readmore_catch = ($this->helper->isJ16) ? $show_readmore : ((isset($this->article->readmore)) ? $this->article->readmore : 0);
            } elseif (isset($this->article->parameters)) {
                //article view
                $article_params =& $this->article->parameters;
                $readmore_catch = JRequest::getInt('readmore');
                $override = JRequest::getInt('show_readmore',false);
                $show_readmore = ($override!==false) ? $override : $article_params->get('show_readmore');
            }
            $readmore_param = 'show_readmore';
        } elseif ($this->helper->option == 'com_k2' && JRequest::getVar('view') == 'itemlist') {
            $article_params =& $this->article->params;
            $layout = JRequest::getVar('layout');
            if ($layout == 'category') {
                $readmore_param = 'catItemReadMore';
            } elseif ($layout == 'user') {
                $readmore_param = 'userItemReadMore';
            } else {
                $readmore_param = 'genericItemReadMore';
            }
            $show_readmore = $readmore_catch = $article_params->get($readmore_param);
        }

        //let's overwrite the readmore link with our own
        //needed as in the case of updating the buttons via ajax which calls the article view
        $view = ($override = JRequest::getVar('view_override')) ? $override : JRequest::getVar('view');
        $test_view = ($this->helper->option == 'com_k2') ? 'item' : 'article';

        if ($view != $test_view && $this->params->get('overwrite_readmore',1)) {
            //make sure the readmore link is enabled for this article

            if (!empty($show_readmore) && !empty($readmore_catch)) {
                if ($article_access) {
                    $readmore_link = $this->helper->_get_article_url();
                    if ($this->helper->option == "com_content") {
                        if ($this->helper->isJ16) {
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
                        } else {
                            if ($attribs) {
                                $readmore = $attribs->get('readmore');
                            }
                        }
                    }
                    if (!empty($readmore)) {
                        $readmore_text = $readmore;
                    } else {
                        $readmore_text = JText::_('READ_MORE');
                    }
                } else {
                    $return_url = base64_encode($this->helper->_get_article_url());
                    $readmore_link = JRoute::_('index.php?option=com_users&view=login&return='.$return_url);
                    $readmore_text = JText::_('READ_MORE_REGISTER');
                }

                $this->helper->output['buttons']['readmore']['href'] = $readmore_link;
                $this->helper->output['buttons']['readmore']['text'] = $readmore_text;
                $this->helper->output['buttons']['readmore']['target'] = '_self';

                //set it so that Joomla does not show its readmore link
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
        $show_button = $this->params->get('enable_initiate_buttons',false);

        if ($show_button && empty($this->manual_plug)) {
            $user   = JFactory::getUser();
            $editAccess = $user->authorize('com_content', 'edit', 'content', 'all');
            if ($editAccess) {
                if ($this->helper->thread_status) {
                    //discussion is published
                    $dbtask = 'unpublish_discussion';
                    $text = 'UNINITIATE_DISCUSSION';
                } elseif (isset($threadinfo->published)) {
                    //discussion is unpublished
                    $dbtask = 'publish_discussion';
                    $text = 'INITIATE_DISCUSSION';
                } else {
                    //discussion is uninitiated
                    $dbtask = 'create_thread';
                    $text = 'INITIATE_DISCUSSION';
                }

                $this->helper->output['buttons']['initiate']['href'] = 'javascript: void(0);';

                $vars  = '&view_override='.$view;
                $vars .= ($this->params->get('overwrite_readmore',1)) ? "&readmore={$readmore_catch}&show_readmore={$show_readmore}" : '';

                $this->helper->output['buttons']['initiate']['js']['onclick'] = "confirmThreadAction(".$this->article->id.",'$dbtask', '$vars', '{$this->helper->_get_article_url()}');";
                $this->helper->output['buttons']['initiate']['text'] = JText::_($text);
                $this->helper->output['buttons']['initiate']['target'] = '_self';
            }
        }

        //create the discuss this link
        if ($this->helper->thread_status || $this->manual_plug) {
            if ($link_mode!="never") {
                $JFusionForum =& JFusionFactory::getForum($this->jname);
                if ($this->helper->reply_count === false || $this->helper->reply_count === null) {
                    $this->helper->reply_count = $JFusionForum->getReplyCount($threadinfo);
                }

                if ($view==$test_view) {
                    if ($link_mode=="article" || $link_mode=="always") {
                        $this->helper->output['buttons']['discuss']['href'] = JFusionFunction::routeURL($JFusionForum->getThreadURL($threadinfo->threadid), $itemid, $this->jname);
                        $this->helper->output['buttons']['discuss']['text'] = $linkHTML;
                        $this->helper->output['buttons']['discuss']['target'] = $linkTarget;

                        if ($this->params->get('enable_comment_in_forum_button',0)) {
                            $commentLinkText = $this->params->get('comment_in_forum_link_text', JText::_('ADD_COMMENT'));
                            $commentLinkHTML = ($this->params->get('comment_in_forum_link_type')=='image') ? '<img style="border:0;" src="'.$commentLinkText.'">' : $commentLinkText;
                            $this->helper->output['buttons']['comment_in_forum']['href'] = JFusionFunction::routeURL($JFusionForum->getReplyURL($threadinfo->forumid, $threadinfo->threadid), $itemid, $this->jname);
                            $this->helper->output['buttons']['comment_in_forum']['text'] = $commentLinkHTML;
                            $this->helper->output['buttons']['comment_in_forum']['target'] = $linkTarget;
                        }

                    }
                } elseif ($link_mode=="blog" || $link_mode=="always") {
                    if ($blog_link_mode=="joomla") {
                        //see if there are any page breaks
                        $joomla_text = (isset($this->article->fulltext)) ? $this->article->fulltext : $this->article->text;
                        $pagebreaks = substr_count($joomla_text, 'system-pagebreak');
                        $query = ($pagebreaks) ? "&limitstart=$pagebreaks" : '';
                        if ($article_access) {
                            $discuss_link = $this->helper->_get_article_url('discussion', $query);
                        } else {
                            $return_url = base64_encode($this->helper->_get_article_url('discussion', $query));
                            $discuss_link = JRoute::_('index.php?option=com_user&view=login&return='.$return_url);
                        }
                        $this->helper->output['buttons']['discuss']['href'] = 'javascript: void(0);';
                        $this->helper->output['buttons']['discuss']['js']['onclick'] = "toggleDiscussionVisibility(1,'$discuss_link');";
                        $this->helper->output['buttons']['discuss']['target'] = '_self';
                    } else {
                        $this->helper->output['buttons']['discuss']['href'] = JFusionFunction::routeURL($JFusionForum->getThreadURL($threadinfo->threadid), $itemid, $this->jname);
                        $this->helper->output['buttons']['discuss']['target'] = $linkTarget;
                    }

                    $this->helper->output['buttons']['discuss']['text'] = $linkHTML;

                    if ($this->params->get('enable_comment_in_forum_button',0)) {
                        $commentLinkText = $this->params->get('comment_in_forum_link_text', JText::_('ADD_COMMENT'));
                        $commentLinkHTML = ($this->params->get('comment_in_forum_link_type')=='image') ? '<img style="border:0;" src="'.$commentLinkText.'">' : $commentLinkText;
                        $this->helper->output['buttons']['comment_in_forum']['href'] = JFusionFunction::routeURL($JFusionForum->getReplyURL($threadinfo->forumid, $threadinfo->threadid), $itemid, $this->jname);
                        $this->helper->output['buttons']['comment_in_forum']['text'] = $commentLinkHTML;
                        $this->helper->output['buttons']['comment_in_forum']['target'] = $linkTarget;
                    }
                }
            }

            //show comments link
            if ($view==$test_view && $this->params->get('show_toggle_posts_link',1)) {
                $this->helper->output['buttons']['showreplies']['href'] = 'javascript: void(0);';
                $this->helper->output['buttons']['showreplies']['js']['onclick'] = 'toggleDiscussionVisibility();';

                $JSession = JFactory::getSession();
                $show_replies = $JSession->get('jfusion.discussion.visibility',0);
                $text = (empty($show_replies)) ? 'HIDE_REPLIES' : 'SHOW_REPLIES';

                $this->helper->output['buttons']['showreplies']['text'] = JText::_($text);
                $this->helper->output['buttons']['showreplies']['target'] = '_self';
            }
        }

        $this->helper->threadinfo =& $threadinfo;
        if ($innerhtml) {
            $button_output = $this->helper->_render_file('default_buttons.php','capture');
        } else {
            $button_output = <<<HTML
                <div class="jfusionclearfix" id="jfusionButtonArea{$this->article->id}">
                    {$this->helper->_render_file('default_buttons.php','capture')}
                </div>
                <div class="jfusionclearfix jfusionButtonConfirmationBox" id="jfusionButtonConfirmationBox{$this->article->id}">
                </div>
HTML;

        }

        return $button_output;
    }

    /**
     * @param $posts
     * @return array|string
     */
    public function _prepare_posts_output(&$posts)
    {
        $this->helper->_debug('Preparing posts output');

        //get required params
        defined('_DATE_FORMAT_LC2') or define('_DATE_FORMAT_LC2','%A, %d %B %Y %H:%M');
        $date_format = $this->params->get('custom_date', _DATE_FORMAT_LC2);
        $showdate = intval($this->params->get('show_date'));
        $showuser = intval($this->params->get('show_user'));
        $showavatar = $this->params->get('show_avatar');
        $avatar_software = $this->params->get('avatar_software',false);
        $resize_avatar = $this->params->get('avatar_keep_proportional', false);
        $userlink = intval($this->params->get('user_link'));
        $link_software = $this->params->get('userlink_software',false);
        $userlink_custom = $this->params->get('userlink_custom',false);
        $character_limit = (int) $this->params->get('character_limit');
        $itemid = $this->params->get('itemid');
        $JFusionPublic =& JFusionFactory::getPublic($this->jname);

        $JFusionForum =& JFusionFactory::getForum($this->jname);
        $columns = $JFusionForum->getDiscussionColumns();
        if (empty($columns)) return '';

        $post_output = array();
        for ($i=0; $i<count($posts); $i++)
        {
            $p =& $posts[$i];
            $userid =& $p->{$columns->userid};
            $username = ($this->params->get('display_name') && isset($p->{$columns->name})) ? $p->{$columns->name} : $p->{$columns->username};
            $dateline =& $p->{$columns->dateline};
            $posttext =& $p->{$columns->posttext};
            $posttitle =& $p->{$columns->posttitle};
            $postid =& $p->{$columns->postid};
            $threadid =& $p->{$columns->threadid};
            $guest =& $p->{$columns->guest};
            $threadtitle = (isset($columns->threadtitle)) ? $p->{$columns->threadtitle} : '';

            $post_output[$i] = new stdClass();
            $post_output[$i]->postid = $postid;
            $post_output[$i]->guest = $guest;

            //get Joomla's id
            $userlookup = JFusionFunction::lookupUser($JFusionForum->getJname(),$userid,false,$p->{$columns->username});

            //avatar
            if ($showavatar){
                if (!empty($avatar_software) && $avatar_software!='jfusion' && !empty($userlookup)) {
                    $post_output[$i]->avatar_src = JFusionFunction::getAltAvatar($avatar_software, $userlookup->id);
                } else {
                    $post_output[$i]->avatar_src = $JFusionForum->getAvatar($userid);
                }

                if (empty($post_output[$i]->avatar_src)) {
                    $post_output[$i]->avatar_src = JFusionFunction::getJoomlaURL().'components/com_jfusion/images/noavatar.png';
                }

                $size = ($resize_avatar) ? @getimagesize($post_output[$i]->avatar_src) : false;
                $maxheight = $this->params->get('avatar_height',80);
                $maxwidth = $this->params->get('avatar_width',60);
                //size the avatar to fit inside the dimensions if larger
                if ($size!==false && ($size[0] > $maxwidth || $size[1] > $maxheight)) {
                    $wscale = $maxwidth/$size[0];
                    $hscale = $maxheight/$size[1];
                    $scale = min($hscale, $wscale);
                    $post_output[$i]->avatar_width = floor($scale*$size[0]);
                    $post_output[$i]->avatar_height = floor($scale*$size[1]);
                } elseif ($size!==false) {
                    //the avatar is within the limits
                    $post_output[$i]->avatar_width = $size[0];
                    $post_output[$i]->avatar_height = $size[1];
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
            $post_output[$i]->subject_url = JFusionFunction::routeURL($JFusionForum->getPostURL($threadid,$postid), $itemid);
            if (!empty($posttitle)) {
                $post_output[$i]->subject = $posttitle;
            } elseif (!empty($threadtitle)) {
                $post_output[$i]->subject = 'Re: '.$threadtitle;
            } else {
                $post_output[$i]->subject = JText::_('NO_SUBJECT');
            }

            //user info
            if ($showuser) {
                $post_output[$i]->username_url = '';
                if ($userlink && empty($guest) && !empty($userlookup)) {
                    if ($link_software=='custom' && !empty($userlink_custom)  && !empty($userlookup)) {
                        $post_output[$i]->username_url = $userlink_custom.$userlookup->id;
                    } else {
                        $post_output[$i]->username_url = JFusionFunction::routeURL($JFusionForum->getProfileURL($userid, $username), $itemid);
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
                $tz_offset =& JFusionFunction::getJoomlaTimezone();
                $dateline += ($tz_offset * 3600);
                $date = gmstrftime($date_format, (int) $dateline);
                $post_output[$i]->date = $date;
            } else {
                $post_output[$i]->date = '';
            }

            //post body
            $post_output[$i]->text = $posttext;
            $status = $JFusionPublic->prepareText($post_output[$i]->text,'joomla', $this->params, $p);
            $original_text = "[quote=\"$username\"]\n".$posttext."\n[/quote]";
            $post_output[$i]->original_text = $original_text;
            $JFusionPublic->prepareText($post_output[$i]->original_text, 'discuss', $this->params, $p);

            //apply the post body limit if there is one
            if (!empty($character_limit) && empty($status['limit_applied']) && JString::strlen($post_output[$i]->text) > $character_limit) {
                $post_output[$i]->text = JString::substr($post_output[$i]->text,0,$character_limit) . '...';
            }

            $toolbar = array();
            if ($this->params->get('enable_quickreply')){
                $JoomlaUser = JFactory::getUser();
                if ($this->params->get('quickreply_allow_guests',0) || !$JoomlaUser->guest) {
                    $toolbar[] = '<a href="javascript:void(0);" onclick="jfusionQuote('.$postid.');">'.JText::_('QUOTE').'</a>';
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

    /*
     * _update_pagination
     */
    public function _update_pagination()
    {
        $this->helper->reply_count = JRequest::getVar('reply_count','');
        if ($this->helper->reply_count == '') {
            $JFusionForum =& JFusionFactory::getForum($this->jname);
            $threadinfo =& $this->helper->_get_thread_info();
            if (!empty($threadinfo)) {
                $this->helper->reply_count = $JFusionForum->getReplyCount($threadinfo);
            } else {
                $this->helper->reply_count = 0;
            }
        }

        $action_url = $this->helper->_get_article_url('','',false);
        $application = JFactory::getApplication() ;

        $limit = (int) $application->getUserStateFromRequest( 'global.list.limit', 'limit_discuss', 5, 'int' );

        //set $limitstart so that the created post is shown
        if ($this->params->get('sort_posts','ASC')=='ASC') {
            $limitstart = floor(($this->helper->reply_count - 1)/$limit) * $limit;
        } else {
            $limitstart = 0;
        }

        //keep pagination from changing limit to all
        if ($limit == $this->helper->reply_count) {
            $reply_count = $this->helper->reply_count - 1;
        } else {
            $reply_count =& $this->helper->reply_count;
        }

        if (!empty($reply_count) && $reply_count > 5) {
            $pageNav = new JFusionPagination($reply_count, $limitstart, $limit, '_discuss');

            $pagination = '<form method="post" id="jfusionPaginationForm" name="jfusionPaginationForm" action="'.$action_url.'">';
            $pagination .= '<input type="hidden" name="jumpto_discussion" value="1"/>';
            $pagination .= $pageNav->getListFooter();
            $pagination .= '</form>';

            //remove the unnecessary vars added by ajax
            $search = array();
            $search[] = '&amp;tmpl=component';
            $search[] = '&amp;update_pagination=1';
            $search[] = '&amp;ajax_request=1';
            $search[] = 'tmpl=component&amp;';
            $search[] = 'update_pagination=1&amp;';
            $search[] = 'ajax_request=1&amp;';
            $search[] = 'tmpl=component';
            $search[] = 'update_pagination=1';
            $search[] = 'ajax_request=1';
            $pagination = str_replace($search,'',$pagination);
        } else {
            $pagination = '';
        }

        die($pagination);
    }

    /*
     * _update_posts
     */
    public function _update_posts()
    {
        if ($this->helper->thread_status) {
            $JFusionForum =& JFusionFactory::getForum($this->jname);
            $threadinfo =& $this->helper->_get_thread_info();
            $posts = $JFusionForum->getPosts($this->params, $threadinfo);
            $this->helper->output = array();
            $this->helper->output['posts'] = $this->_prepare_posts_output($posts);
            $this->helper->threadinfo =& $threadinfo;
            $this->helper->_render_file('default_posts.php','die');
        }
    }

    /*
     * _update_buttons
     */
    public function _update_buttons()
    {
        die($this->_render_buttons(true));
    }
}