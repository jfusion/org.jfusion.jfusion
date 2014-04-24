<?php namespace jfusion\plugins\phpbb3;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFactory;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Plugin\Plugin_Front;

use \Exception;
use Joomla\Uri\Uri;
use JRegistry;
use JUri;
use \stdClass;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Public Class for phpBB3
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Front extends Plugin_Front
{
    /**
     * @return string
     */
    function getRegistrationURL() {
        return 'ucp.php?mode=register';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return 'ucp.php?mode=sendpassword';
    }

    /**
     * @return string
     */
    function getLostUsernameURL() {
        return 'ucp.php?mode=sendpassword';
    }


    /**
     * @param object $jfdata
     *
     * @return void
     */
    function getBuffer(&$jfdata)
    {
    	$session = JFactory::getSession();
    	//detect if phpbb3 is already loaded for dual login
	    $mainframe = Factory::getApplication();
    	if (defined('IN_PHPBB')) {
    		//backup any post get vars
    		$backup = array();
            $backup['post'] = $_POST;
            $backup['request'] = $_REQUEST;
            $backup['files'] = $_FILES;
            $session->set('JFusionVarBackup', $backup);

    		//refresh the page to avoid phpbb3 error
    		//this happens as the phpbb3 config file can not be loaded twice
    		//and phpbb3 always uses include instead of include_once
            $uri = JUri::getInstance();
            //add a variable to ensure refresh
            $uri->setVar('time', time());
            $link = $uri->toString();
            $mainframe->redirect($link);
            die(' ');
    	}

        //restore $_POST, $_FILES, and $_REQUEST data if this was a refresh
        $backup = $session->get('JFusionVarBackup', array());
        if (!empty($backup)) {
            $_POST = $_POST + $backup['post'];
            $_FILES = $_FILES + $backup['files'];
            $_REQUEST = $_REQUEST + $backup['request'];
            $session->clear('JFusionVarBackup');
        }

        // Get the path
        global $source_url;
        $source_url = $this->params->get('source_url');
        $source_path = $this->params->get('source_path');
        //get the filename
        $jfile = $mainframe->input->get('jfile');
        if (!$jfile) {
            //use the default index.php
            $jfile = 'index.php';
        }
        //redirect for file download requests
        if ($jfile == 'file.php') {
            $url = 'Location: ' . $this->params->get('source_url') . 'download/file.php?' . $_SERVER['QUERY_STRING'];
            header($url);
            exit();
        }
        //combine the path and filename
	    $index_file = $source_path . basename($jfile);
        if (!is_file($index_file)) {
            Framework::raiseWarning('The path to the requested does not exist', $this->getJname());
        } else {
            //set the current directory to phpBB3
            chdir($source_path);
            /* set scope for variables required later */
            global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template, $phpbb_hook, $module, $mode, $table_prefix, $id_cache, $sort_dir;
            if ($jfile == 'mcp.php') {
                //must globalize these to make sure urls are generated correctly via extra_url() in mcp.php
                global $forum_id, $topic_id, $post_id, $report_id, $user_id, $action;
            } else if ($jfile == 'feed.php') {
                global $board_url;
            }

            //see if we need to force the database to use a new connection
            if ($this->params->get('database_new_link', 0) && !defined('PHPBB_DB_NEW_LINK')) {
                define('PHPBB_DB_NEW_LINK', 1);
            }

	        $hooks = Factory::getPlayform($jfdata->platform, $this->getJname())->hasFile('hooks.php');
	        if ($hooks) {
		        //define the phpBB3 hooks
		        require_once $hooks;
	        }
            // Get the output
            ob_start();

            //we need to hijack $_SERVER['PHP_SELF'] so that phpBB correctly utilizes it such as correctly noted the page a user is browsing
            $php_self = $_SERVER['PHP_SELF'];
            $juri = new Uri($source_url);
            $_SERVER['PHP_SELF'] = $juri->getPath() . $jfile;

            try {
                if (!defined('UTF8_STRLEN')) {
                    define('UTF8_STRLEN', true);
                }
                if (!defined('UTF8_CORE')) {
                    define('UTF8_CORE', true);
                }
                if (!defined('UTF8_CASE')) {
                    define('UTF8_CASE', true);
                }
                include_once ($index_file);
            } catch (Exception $e) {
                $jfdata->buffer = ob_get_contents();
                ob_end_clean();
            }

            //restore $_SERVER['PHP_SELF']
            $_SERVER['PHP_SELF'] = $php_self;

            //change the current directory back to Joomla.
            chdir(JPATH_SITE);
            //show more smileys without the Joomla frame
            $jfmode = $mainframe->input->get('mode');
            $jfform = $mainframe->input->get('form');
            if ($jfmode == 'smilies' || ($jfmode == 'searchuser' && !empty($jfform) || $jfmode == 'contact')) {
                $pattern = '#<head[^>]*>(.*?)<\/head>.*?<body[^>]*>(.*)<\/body>#si';
                preg_match($pattern, $jfdata->buffer, $temp);
                $jfdata->header = $temp[1];
                $jfdata->body = $temp[2];
                $this->parseHeader($jfdata);
                $this->parseBody($jfdata);
                die('<html><head>' . $jfdata->header . '</head><body>' . $jfdata->body . '</body></html>');
            }
        }
    }

    /**
     * @param object $data
     *
     * @return void
     */
    function parseBody(&$data) {

        static $regex_body, $replace_body, $callback_function;
        if (!$regex_body || !$replace_body || $callback_function) {
            // Define our preg arrays
            $regex_body = array();
            $replace_body = array();
            $callback_function = array();
            //fix anchors
            $regex_body[] = '#\"\#(.*?)\"#mS';
            $replace_body[] = '"' . $data->fullURL . '#$1"';
            $callback_function[] = '';

            //parse URLS          
            $regex_body[] = '#href="(.*?)"#m';
            $replace_body[] = '';
            $callback_function[] = 'fixUrl';              
            
            //convert relative links from images into absolute links
            $regex_body[] = '#(src="|background="|url\(\'?)./(.*?)("|\'?\))#mS';
            $replace_body[] = '$1' . $data->integratedURL . '$2$3';
            $callback_function[] = '';               
            //fix for form actions
            $regex_body[] = '#action="(.*?)"(.*?)>#m';
            $replace_body[] = ''; //$this->fixAction('$1', '$2', "' . $data->baseURL . '")';
            $callback_function[] = 'fixAction';   
            //convert relative popup links to full url links
            $regex_body[] = '#popup\(\'\.\/(.*?)\'#mS';
            $replace_body[] = 'popup(\'' . $data->integratedURL . '$1\'';
            $callback_function[] = '';    
            //fix for mcp links
	        $mainframe = Factory::getApplication();
            $jfile = $mainframe->input->get('jfile');
            if ($jfile == 'mcp.php') {
                $topicid = $mainframe->input->getInt('t');
                //fix for merge thread
                $regex_body[] = '#(&|&amp;)to_topic_id#mS';
                $replace_body[] = '$1t=' . $topicid . '$1to_topic_id';
                $callback_function[] = '';                    
                $regex_body[] = '#/to_topic_id#mS';
                $replace_body[] = '/t,' . $topicid . '/to_topic_id';
                $callback_function[] = '';                    
                //fix for merge posts
                $regex_body[] = '#(&|&amp;)action=merge_select#mS';
                $replace_body[] = '$1t=' . $topicid . '$1action=merge_select';
                $callback_function[] = '';                    
                $regex_body[] = '#/action=merge_select#mS';
                $replace_body[] = '/t,' . $topicid . '/action=merge_select';
                $callback_function[] = '';
            }
        }
        
        /**
         * @TODO lets parse our todo list for regex
         */
        foreach ($regex_body as $k => $v) {
        	//check if we need to use callback
        	if(!empty($callback_function[$k])){
			    $data->body = preg_replace_callback($regex_body[$k], array(&$this, $callback_function[$k]), $data->body);
        	} else {
        		$data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
        	}
        }    
    }

	/**
	 * @param $url
	 *
	 * @return string
	 */
	function cssCacheName($url) {
		$uri = new Uri($url);
		$uri->delVar('sid');
		return parent::cssCacheName($uri->toString());
	}

    /**
     * @param array $vars
     */
    function parseRoute(&$vars) {
        foreach ($vars as $k => $v) {
            //must undo Joomla parsing that changes dashes to colons so that PM browsing works correctly
            if ($k == 'f') {
                $vars[$k] = str_replace (':', '-', $v);
            } elseif ($k == 'redirect') {
                $vars[$k] = base64_decode($v);
            }
        }
    }

    /**
     * @param array $segments
     */
    function buildRoute(&$segments) {
        if (is_array($segments)) {
            foreach($segments as $k => $v) {
                if (strstr($v, 'redirect,./')) {
                    //need to encode the redirect to prevent issues with SEF
                    $url = substr($v, 9);
                    $segments[$k] = 'redirect,' . base64_encode($url);
                }
            }
        }
    }

    /**
     * @param $matches
     * @return string
     */
    function fixUrl($matches) {
		$q = $matches[1];
		
		$integratedURL = $this->data->integratedURL;		
		$baseURL = $this->data->baseURL;
		    	
    	if ( strpos($q, './') === 0 ) {
			$q = substr($q, 2);
		} else if ( strpos($q, $this->data->integratedURL . 'index.php') === 0 ) {
			$q = substr($q, strlen($this->data->integratedURL . 'index.php'));
		} else {
			return $matches[0];
		}
		
        //allow for direct downloads and admincp access
        if (strstr($q, 'download/') || strstr($q, 'adm/')) {
            $url = $integratedURL . $q;
            return 'href="' . $url . '"';
        }

        //these are custom links that are based on modules and thus no as easy to replace as register and lost password links in the hooks.php file so we'll just parse them
        $edit_account_url = $this->params->get('edit_account_url');
        if (strstr($q, 'mode=reg_details') && !empty($edit_account_url)) {
             $url = $edit_account_url;
             return 'href="' . $url . '"';
        }

        $edit_profile_url = $this->params->get('edit_profile_url');
        if (!empty($edit_profile_url)) {
            if (strstr($q, 'mode=profile_info')) {
                 $url = $edit_profile_url;
                 return 'href="' . $url . '"';
            }

            static $profile_mod_id;
            if (empty($profile_mod_id)) {
                //the first item listed in the profile module is the edit profile link so must rewrite it to go to signature instead
	            try {
		            $db = Factory::getDatabase($this->getJname());

		            $query = $db->getQuery(true)
			            ->select('module_id')
			            ->from('#__modules')
			            ->where('module_langname = ' . $db->quote('UCP_PROFILE'));

		            $db->setQuery($query);
		            $profile_mod_id = $db->loadResult();
	            } catch (Exception $e) {
		            Framework::raiseError($e, $this->getJname());
		            $profile_mod_id = null;
	            }
            }
            if (!empty($profile_mod_id) && strstr($q, 'i=' . $profile_mod_id)) {
                $url = 'ucp.php?i=profile&mode=signature';
                $url = Factory::getApplication()->routeURL($url, Factory::getApplication()->input->getInt('Itemid'), $this->getJname());
                return 'href="' . $url . '"';
            }
        }

        $edit_avatar_url = $this->params->get('edit_avatar_url');
        if (strstr($q, 'mode=avatar') && !empty($edit_avatar_url)) {
             $url = $edit_avatar_url;
             return 'href="' . $url . '"';
        }

        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            $q = str_replace('?', '&amp;', $q);
            $url = $baseURL . '&amp;jfile=' . $q;
        } else {
            //check to see what SEF mode is selected
            $sefmode = $this->params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $url = Factory::getApplication()->routeURL($q, Factory::getApplication()->input->getInt('Itemid'));
            } else {
                //simple SEF mode, we can just combine both variables
                $url = $baseURL . $q;
            }
        }
        return 'href="' . $url . '"';
    }

    /**
     * @param $matches
     * @return string
     */
    function fixRedirect($matches) {
		$url = $matches[1];
		$baseURL = $this->data->baseURL;
		    	
        //\JFusion\Framework::raiseWarning($url, $this->getJname());
        //split up the timeout from url
        $parts = explode('url=', $url, 2);
        $uri = new Uri($parts[1]);
        $jfile = $uri->getPath();
        $jfile = basename($jfile);
        $query = $uri->getQuery(false);
        $fragment = $uri->getFragment();
        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            $redirectURL = $baseURL . '&amp;jfile=' . $jfile;
            if (!empty($query)) {
                $redirectURL.= '&amp;' . $query;
            }
        } else {
            //check to see what SEF mode is selected
            $sefmode = $this->params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $redirectURL = $jfile;
                if (!empty($query)) {
                    $redirectURL.= '?' . $query;
                }
                $redirectURL = Factory::getApplication()->routeURL($redirectURL, Factory::getApplication()->input->getInt('Itemid'));
            } else {
                //simple SEF mode, we can just combine both variables
                $redirectURL = $baseURL . $jfile;
                if (!empty($query)) {
                    $redirectURL.= '?' . $query;
                }
            }
        }
        if (!empty($fragment)) {
            $redirectURL .= '#' . $fragment;
        }
        $return = '<meta http-equiv="refresh" content="' . $parts[0] . 'url=' . $redirectURL . '">';
        //\JFusion\Framework::raiseWarning(htmlentities($return), $this->getJname());
        return $return;
    }

    /**
     * @param $matches
     * @return string
     */
    function fixAction($matches) {
		$url = $matches[1];
		$extra = $matches[2];
		$baseURL = $this->data->baseURL;
		
        $url = htmlspecialchars_decode($url);
	    $mainframe = Factory::getApplication();
        $Itemid = $mainframe->input->getInt('Itemid');
        //strip any leading dots
        if (substr($url, 0, 2) == './') {
            $url = substr($url, 2);
        }
        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            $url_details = parse_url($url);
            $url_variables = array();
            if (!empty($url_details['query'])) {
                parse_str($url_details['query'], $url_variables);
            }
            $jfile = basename($url_details['path']);
            //set the correct action and close the form tag
            $replacement = 'action="' . $baseURL . '"' . $extra . '>';
            $replacement.= '<input type="hidden" name="jfile" value="' . $jfile . '"/>';
            $replacement.= '<input type="hidden" name="Itemid" value="' . $Itemid . '"/>';
            $replacement.= '<input type="hidden" name="option" value="com_jfusion"/>';
        } else {
            //check to see what SEF mode is selected
            $sefmode = $this->params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $url = Factory::getApplication()->routeURL($url, $Itemid);
                $replacement = 'action="' . $url . '"' . $extra . '>';
                return $replacement;
            } else {
	            //simple SEF mode
	            $url_details = parse_url($url);
	            $url_variables = array();
	            if(!empty($url_details['query'])) {
		            $query = '?' . $url_details['query'];
	            } else {
		            $query = '';
	            }
	            $jfile = basename($url_details['path']);
	            $replacement = 'action="' . $baseURL . $jfile . $query . '"' . $extra . '>';
            }
        }
        unset($url_variables['option'], $url_variables['jfile'], $url_variables['Itemid']);
        if(!empty($url_variables['mode'])){
            if ($url_variables['mode'] == 'topic_view') {
                $url_variables['t'] = $mainframe->input->get('t');
                $url_variables['f'] = $mainframe->input->get('f');
            }
        }

        //add any other variables
        if (is_array($url_variables)) {
            foreach ($url_variables as $key => $value) {
                $replacement.= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
            }
        }
        return $replacement;
    }

    /**
     * @param object $data
     *
     * @return void
     */
    function parseHeader(&$data) {
        static $regex_header, $replace_header;
        if (!$regex_header || !$replace_header) {
            // Define our preg arrays
            $regex_header = array();
            $replace_header = array();
            $callback_header = array();
            //convert relative links into absolute links
            $regex_header[] = '#(href="|src=")./(.*?")#mS';
            $replace_header[] = '$1' . $data->integratedURL . '$2';
            $callback_header[] = '';
            //fix for URL redirects
            $regex_header[] = '#<meta http-equiv="refresh" content="(.*?)"(.*?)>#m';
            $replace_header[] = ''; //$this->fixRedirect("$1","' . $data->baseURL . '")';
            $callback_header[] = 'fixRedirect';
            //fix pm popup URL to be absolute for some phpBB templates
            $regex_header[] = '#var url = \'\.\/(.*?)\';#mS';
            $replace_header[] = 'var url = \'{$data->integratedURL}$1\';';
            $callback_header[] = '';
            //convert relative popup links to full url links
            $regex_header[] = '#popup\(\'\.\/(.*?)\'#mS';
            $replace_header[] = 'popup(\'' . $data->integratedURL . '$1\'';
            $callback_header[] = '';
        }

        /**
         * @TODO lets parse our todo list for regex
         */
        foreach ($regex_header as $k => $v) {
        	//check if we need to use callback
        	if(!empty($callback_header[$k])){
			    $data->header = preg_replace_callback($regex_header[$k], array(&$this, $callback_header[$k]), $data->header);
        	} else {
        		$data->header = preg_replace($regex_header[$k], $replace_header[$k], $data->header);
        	}
        }
    }

    /**
     * @return array
     */
    function getPathWay() {
	    try {
		    $db = Factory::getDatabase($this->getJname());
		    $pathway = array();

		    $mainframe = Factory::getApplication();

		    $forum_id = $mainframe->input->getInt('f');
		    if (!empty($forum_id)) {
			    //get the forum's info

			    $query = $db->getQuery(true)
				    ->select('forum_name, parent_id, left_id, right_id, forum_parents')
				    ->from('#__forums')
				    ->where('forum_id = ' . $db->quote($forum_id));

			    $db->setQuery($query);
			    $forum_info = $db->loadObject();

			    if (!empty($forum_info)) {
				    //get forum parents

				    $query = $db->getQuery(true)
					    ->select('forum_id, forum_name')
					    ->from('#__forums')
					    ->where('left_id < ' . $forum_info->left_id)
					    ->where('right_id > ' . $forum_info->right_id)
				        ->order('left_id ASC');

				    $db->setQuery($query);
				    $forum_parents = $db->loadObjectList();

				    if (!empty($forum_parents)) {
					    foreach ($forum_parents as $data) {
						    $crumb = new stdClass();
						    $crumb->title = $data->forum_name;
						    $crumb->url = 'viewforum.php?f=' . $data->forum_id;
						    $pathway[] = $crumb;
					    }
				    }

				    $crumb = new stdClass();
				    $crumb->title = $forum_info->forum_name;
				    $crumb->url = 'viewforum.php?f=' . $forum_id;
				    $pathway[] = $crumb;
			    }
		    }

		    $topic_id = $mainframe->input->getInt('t');
		    if (!empty($topic_id)) {
			    $query = $db->getQuery(true)
				    ->select('topic_title')
				    ->from('#__topics')
				    ->where('topic_id = ' . $db->quote($topic_id));

			    $db->setQuery($query);
			    $topic_title = $db->loadObject();

			    if (!empty($topic_title)) {
				    $crumb = new stdClass();
				    $crumb->title = $topic_title->topic_title;
				    $crumb->url = 'viewtopic.php?f=' . $forum_id . '&amp;t=' . $topic_id;
				    $pathway[] = $crumb;
			    }
		    }
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
		    $pathway = array();
	    }

        return $pathway;
    }

    /**
     * @return object
     */
    function getSearchQueryColumns() {
        $columns = new stdClass();
        $columns->title = 'p.post_subject';
        $columns->text = 'p.post_text';
        return $columns;
    }

    /**
     * @param object $pluginParam
     * @return string
     */
    function getSearchQuery(&$pluginParam) {
	    $db = Factory::getDatabase($this->getJname());
        //need to return threadid, postid, title, text, created, section
	    $query = $db->getQuery(true)
		    ->select('p.topic_id, p.post_id, p.forum_id, CASE WHEN p.post_subject = "" THEN CONCAT("Re: ",t.topic_title) ELSE p.post_subject END AS title, p.post_text AS text,
                    FROM_UNIXTIME(p.post_time, "%Y-%m-%d %h:%i:%s") AS created,
                    CONCAT_WS( "/", f.forum_name, t.topic_title ) AS section,
                    t.topic_views AS hits')
		    ->from('#__posts AS p')
	        ->innerJoin('#__topics AS t ON t.topic_id = p.topic_id')
		    ->innerJoin('#__forums AS f on f.forum_id = p.forum_id');

        return (string)$query;
    }

    /**
     * @param string $where
     * @param JRegistry $pluginParam
     * @param string $ordering
     *
     * @return void
     */
    function getSearchCriteria(&$where, &$pluginParam, $ordering) {
        $where.= ' AND p.post_approved = 1';
	    /**
	     * @ignore
	     * @var $platform \JFusion\Plugin\Platform\Joomla
	     */
	    $platform = Factory::getPlayform('Joomla', $this->getJname());
        if ($pluginParam->get('forum_mode', 0)) {
            $selected_ids = $pluginParam->get('selected_forums', array());
            $forumids = $platform->filterForumList($selected_ids);
        } else {
	        try {
		        $db = Factory::getDatabase($this->getJname());
		        //no forums were selected so pull them all then filter

		        $query = $db->getQuery(true)
			        ->select('forum_id')
			        ->from('#__forums')
			        ->where('forum_type = 1')
			        ->order('left_id');

		        $db->setQuery($query);
		        $forumids = $db->loadColumn();
		        $forumids = $platform->filterForumList($forumids);
	        } catch (Exception $e) {
		        Framework::raiseError($e, $this->getJname());
		        $forumids = array();
	        }

        }
        if (empty($forumids)) {
            $forumids = array(0);
        }
        //determine how to sort the results which is required for accurate results when a limit is placed
        switch ($ordering) {
             case 'oldest':
                $sort = 'p.post_time ASC';
                break;
            case 'category':
                $sort = 'section ASC';
                break;
            case 'popular':
                $sort = 't.topic_views DESC, p.post_time DESC';
                break;
            case 'alpha':
                $sort = 'title ASC';
                break;
            case 'newest':
            default:
                $sort = 'p.post_time DESC';
                break;
        }
        $where.= ' AND p.forum_id IN (' . implode(',', $forumids) . ') ORDER BY ' . $sort;
    }

    /**
     * @param mixed $post
     * @return string
     */
    function getSearchResultLink($post) {
	    /**
	     * @ignore
	     * @var $platform \JFusion\Plugin\Platform\Joomla
	     */
	    $platform = Factory::getPlayform('Joomla', $this->getJname());
        return $platform->getPostURL($post->topic_id, $post->post_id);
    }
}