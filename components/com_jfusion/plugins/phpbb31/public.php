<?php

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
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Public Class for phpBB3
 * For detailed descriptions on these functions please check JFusionPublic
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_phpbb31 extends JFusionPublic
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {	
        return 'phpbb31';
    }

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
     * Prepares text for various areas
     *
     * @param string  &$text             Text to be modified
     * @param string  $for              (optional) Determines how the text should be prepared.
     * Options for $for as passed in by JFusion's plugins and modules are:
     * joomla (to be displayed in an article; used by discussion bot)
     * forum (to be published in a thread or post; used by discussion bot)
     * activity (displayed in activity module; used by the activity module)
     * search (displayed as search results; used by search plugin)
     * @param JRegistry $params           (optional) Joomla parameter object passed in by JFusion's module/plugin
     * @param object  $object           (optional) Object with information for the specific element the text is from
     *
     * @return array  $status           Information passed back to calling script such as limit_applied
     */
    function prepareText(&$text, $for = 'forum', $params = null, $object = null)
    {
        $status = array('error' => array(), 'debug' => array());
        if ($for == 'forum') {
            //first thing is to remove all joomla plugins
            preg_match_all('/\{(.*)\}/U', $text, $matches);
            //find each thread by the id
            foreach ($matches[1] AS $plugin) {
                //replace plugin with nothing
                $text = str_replace('{' . $plugin . '}', "", $text);
            }
            $text = JFusionFunction::parseCode($text, 'bbcode');
        } elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
            //remove phpbb bbcode uids
            $text = preg_replace('#\[(.*?):(.*?)]#si', '[$1]', $text);
            //encode &nbsp; prior to decoding as somehow it is getting added into phpBB without getting encoded
            $text = str_replace('&nbsp;', '&amp;nbsp;', $text);
            //decode html entities
            $text = html_entity_decode($text);
            if (strpos($text, 'SMILIES_PATH') !== false) {
                //must convert smilies
	            try {
		            $db = JFusionFactory::getDatabase($this->getJname());

		            $query = $db->getQuery(true)
			            ->select('config_value')
			            ->from('#__config')
			            ->where('config_name = ' . $db->quote('smilies_path'));

		            $db->setQuery($query);
		            $smilie_path = $db->loadResult();
		            $source_url = $this->params->get('source_url');
		            $text = preg_replace('#<!-- s(.*?) --><img src="\{SMILIES_PATH\}\/(.*?)" alt="(.*?)" title="(.*?)" \/><!-- s\\1 -->#si', "[img]{$source_url}{$smilie_path}/$2[/img]", $text);
	            } catch (Exception $e) {
					JFusionFunction::raiseError($e, $this->getJname());
	            }
            }
            //parse bbcode to html
            $options = array();
            $options['parse_smileys'] = false;
            if (!empty($params) && $params->get('character_limit', false)) {
                $status['limit_applied'] = 1;
                $options['character_limit'] = $params->get('character_limit');
            }
            $text = JFusionFunction::parseCode($text, 'html', $options);
        } elseif ($for == 'activity' || $for == 'search') {
            $text = preg_replace('#\[(.*?):(.*?)]#si', '[$1]', $text);
            $text = html_entity_decode($text);
            if ($for == 'activity') {
                if ($params->get('parse_text') == 'plaintext') {
                    $options = array();
                    $options['plaintext_line_breaks'] = 'space';
                    if ($params->get('character_limit')) {
                        $status['limit_applied'] = 1;
                        $options['character_limit'] = $params->get('character_limit');
                    }
                    $text = JFusionFunction::parseCode($text, 'plaintext', $options);
                }
            } else {
                $text = JFusionFunction::parseCode($text, 'plaintext');
            }
        }

        return $status;
    }

	/**
	 * @param array $usergroups
	 *
	 * @return string
	 */
    function getOnlineUserQuery($usergroups = array())
    {
	    $db = JFusionFactory::getDatabase($this->getJname());
        //get a unix time from 5 minutes ago
        date_default_timezone_set('UTC');
        $active = strtotime('-5 minutes', time());

	    $query = $db->getQuery(true)
		    ->select('DISTINCT u.user_id AS userid, u.username_clean AS username, u.username AS name, u.user_email as email')
		    ->from('#__users AS u')
		    ->innerJoin('#__sessions AS s ON u.user_id = s.session_user_id')
		    ->where('s.session_viewonline = 1')
		    ->where('s.session_user_id != 1')
		    ->where('s.session_time > ' . $active);

	    if (!empty($usergroups)) {
		    $usergroups = implode(',', $usergroups);

		    $query->innerJoin('#___user_group AS g ON u.user_id = g.user_id')
			    ->where('g.group_id IN (' . $usergroups . ')');
	    }

	    $query = (string)$query;
        return $query;
    }

    /**
     * @return int
     */
    function getNumberOnlineGuests() {
	    try {
		    //get a unix time from 5 minutes ago
		    date_default_timezone_set('UTC');
		    $active = strtotime('-5 minutes', time());
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('COUNT(DISTINCT(session_ip))')
			    ->from('#__sessions')
			    ->where('session_user_id = 1')
			    ->where('session_time > ' . $active);

		    $db->setQuery($query);
		    $result = $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = 0;
	    }
        return $result;
    }

    /**
     * @return int
     */
    function getNumberOnlineMembers() {
	    try {
		    //get a unix time from 5 minutes ago
		    date_default_timezone_set('UTC');
		    $active = strtotime('-5 minutes', time());
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('COUNT(DISTINCT(session_user_id))')
			    ->from('#__sessions')
			    ->where('session_viewonline = 1')
			    ->where('session_user_id != 1')
			    ->where('session_time > ' . $active);

		    $db->setQuery($query);
		    $result = $db->loadResult();
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = 0;
	    }
        return $result;
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
	    $mainframe = JFusionFactory::getApplication();
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
            $uri = JURI::getInstance();
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
	    //combine the path and filename
	    if (!is_file($source_path . basename($jfile))) {
		    $jfile = 'app.php';
	    }

        //redirect for file download requests
        if ($jfile == 'file.php') {
            header('Location: ' . $this->params->get('source_url') . 'download/file.php?' . $_SERVER['QUERY_STRING']);
            exit();
        }

	    if ($jfile == 'app.php') {
		    parent::getBuffer($jfdata);
	    } else {
		    //set the current directory to phpBB3
		    chdir($source_path);
		    /* set scope for variables required later */
		    global $phpbb_root_path, $phpEx, $db, $config, $user, $auth, $cache, $template, $phpbb_hook, $module, $mode, $table_prefix, $id_cache, $sort_dir;

		    $SERVER = $_SERVER;

		    $fullURI = new JURI($jfdata->fullURL);

		    $_SERVER['REQUEST_URI'] = $fullURI->toString(array('path', 'query', 'fragment'));
		    $baseURI = new JURI($jfdata->baseURL);
		    $paths = explode('/', $baseURI->getPath());
		    foreach ($paths as $path) {
			    if (!empty($path)) {
				    if (strpos($_SERVER['REQUEST_URI'], '/' . $path) === 0) {
					    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen('/' . $path));
				    }
			    }
		    }
		    $integratedURI = new JURI($this->data->integratedURL);
		    $_SERVER['REQUEST_URI'] = $integratedURI->getPath() . ltrim($_SERVER['REQUEST_URI'], '/');

		    //combine the path and filename
		    $index_file = $source_path . basename($jfile);
		    if (!is_file($index_file)) {
			    JFusionFunction::raiseWarning('The path to the requested does not exist', $this->getJname());
		    } else {
			    /**
			     * changed for phpbb31
			     */
			    global $SID, $_EXTRA_URL;
			    global $request, $phpbb_container;
			    global $symfony_request, $phpbb_filesystem;
			    global $phpbb_dispatcher;
			    global $phpbb_path_helper;
			    global $phpbb_extension_manager;
			    global $phpbb_log;
			    global $starttime;
			    global $phpbb_admin_path;

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

			    //define the phpBB3 hooks
			    require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'hooks.php';
			    // Get the output

			    ob_start(array($this, 'callback'));
			    $h = ob_list_handlers();

			    //we need to hijack $_SERVER['PHP_SELF'] so that phpBB correctly utilizes it such as correctly noted the page a user is browsing
			    $juri = new JURI($source_url);
			    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $juri->getPath() . $jfile;
			    $_SERVER['SCRIPT_FILENAME'] = $source_path . $jfile;

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
			    }

			    while( in_array( get_class($this) . '::callback', $h) ) {
				    $jfdata->buffer .= ob_get_contents();
				    ob_end_clean();
				    $h = ob_list_handlers();
			    }

			    if ($request) {
				    $request->enable_super_globals();
			    }

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
		    //restore $_SERVER
		    $_SERVER = $SERVER;
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
            $regex_body[] = '#(?<=href=")(.*?)(?=")#m';
            $replace_body[] = '';
            $callback_function[] = 'fixUrl';
            
            //convert relative links from images into absolute links

            $regex_body[] = '#(src="|background="|url\(\'?)[\.\/]*([^:]*?)(["\'\)]+)#mS';
            
            $replace_body[] = '$1' . $data->integratedURL . '$2$3';
            $callback_function[] = '';
            //fix for form actions
            $regex_body[] = '#action="(.*?)"(.*?)>#m';
            $replace_body[] = ''; //$this->fixAction('$1', '$2', "' . $data->baseURL . '")';
            $callback_function[] = 'fixAction';
            //fix for mcp links
	        $mainframe = JFusionFactory::getApplication();
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
		$uri = new JURI($url);
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

	    $q = str_replace('../', '', $q);

	    $integratedURI = new JURI($integratedURL);
	    if ( $q === $integratedURL ) {
		    $q = '';
	    } else if ( strpos($q, $integratedURI->getPath()) === 0 ) {
		    $q = substr($q, strlen($integratedURI->getPath()));
	    } else if ( strpos($q, './') === 0 ) {
			$q = substr($q, 2);
		} else if ( strpos($q, $this->data->integratedURL . 'index.php') === 0 ) {
			$q = substr($q, strlen($this->data->integratedURL . 'index.php'));
		} else {
			return $matches[0];
		}

        //allow for direct downloads and admincp access
        if (strstr($q, 'download/') || strstr($q, 'adm/')) {
            $url = $integratedURL . $q;
        } else {
	        //these are custom links that are based on modules and thus no as easy to replace as register and lost password links in the hooks.php file so we'll just parse them
	        $edit_account_url = $this->params->get('edit_account_url');
	        if (strstr($q, 'mode=reg_details') && !empty($edit_account_url)) {
		        $url = $edit_account_url;
	        } else {
		        $edit_profile_url = $this->params->get('edit_profile_url');
		        if (!empty($edit_profile_url)) {
			        if (strstr($q, 'mode=profile_info')) {
				        return $edit_profile_url;
			        }

			        static $profile_mod_id;
			        if (empty($profile_mod_id)) {
				        //the first item listed in the profile module is the edit profile link so must rewrite it to go to signature instead
				        try {
					        $db = JFusionFactory::getDatabase($this->getJname());

					        $query = $db->getQuery(true)
						        ->select('module_id')
						        ->from('#__modules')
						        ->where('module_langname = ' . $db->quote('UCP_PROFILE'));

					        $db->setQuery($query);
					        $profile_mod_id = $db->loadResult();
				        } catch (Exception $e) {
					        JFusionFunction::raiseError($e, $this->getJname());
					        $profile_mod_id = null;
				        }
			        }
			        if (!empty($profile_mod_id) && strstr($q, 'i=' . $profile_mod_id)) {
				        $url = 'ucp.php?i=profile&mode=signature';
				        $url = JFusionFunction::routeURL($url, JFusionFactory::getApplication()->input->getInt('Itemid'), $this->getJname());
				        return $url;
			        }
		        }

		        $edit_avatar_url = $this->params->get('edit_avatar_url');
		        if (strstr($q, 'mode=avatar') && !empty($edit_avatar_url)) {
			        $url = $edit_avatar_url;
		        } else if (substr($baseURL, -1) != '/') {
			        //non-SEF mode
			        $q = str_replace('?', '&amp;', $q);
			        $url = $baseURL . '&amp;jfile=' . $q;
		        } else {
			        //check to see what SEF mode is selected
			        $sefmode = $this->params->get('sefmode');
			        if ($sefmode == 1) {
				        //extensive SEF parsing was selected
				        $url = JFusionFunction::routeURL($q, JFusionFactory::getApplication()->input->getInt('Itemid'));
			        } else {
				        //simple SEF mode, we can just combine both variables
				        $url = $baseURL . $q;
			        }
		        }
	        }
        }
        return $url;
    }

    /**
     * @param $matches
     * @return string
     */
    function fixRedirect($matches) {
		$url = $matches[1];
		$baseURL = $this->data->baseURL;
		    	
        //JFusionFunction::raiseWarning($url, $this->getJname());
        //split up the timeout from url
        $parts = explode('url=', $url, 2);
        $uri = new JURI($parts[1]);
        $jfile = $uri->getPath();
        $jfile = basename($jfile);
        $query = $uri->getQuery(false);
        $fragment = $uri->getFragment();
        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            $redirectURL = $baseURL . '&amp;jfile=' . $jfile;
            if (!empty($query)) {
                $redirectURL .= '&amp;' . $query;
            }
        } else {
            //check to see what SEF mode is selected
            $sefmode = $this->params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $redirectURL = $jfile;
                if (!empty($query)) {
                    $redirectURL .= '?' . $query;
                }
                $redirectURL = JFusionFunction::routeURL($redirectURL, JFusionFactory::getApplication()->input->getInt('Itemid'));
            } else {
                //simple SEF mode, we can just combine both variables
                $redirectURL = $baseURL . $jfile;
                if (!empty($query)) {
                    $redirectURL .= '?' . $query;
                }
            }
        }
        if (!empty($fragment)) {
            $redirectURL .= '#' . $fragment;
        }
        $return = '<meta http-equiv="refresh" content="' . $parts[0] . 'url=' . $redirectURL . '">';
        //JFusionFunction::raiseWarning(htmlentities($return), $this->getJname());
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
	    $mainframe = JFusionFactory::getApplication();
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
            $replacement .= '<input type="hidden" name="jfile" value="' . $jfile . '"/>';
            $replacement .= '<input type="hidden" name="Itemid" value="' . $Itemid . '"/>';
            $replacement .= '<input type="hidden" name="option" value="com_jfusion"/>';
        } else {
            //check to see what SEF mode is selected
            $sefmode = $this->params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $url = JFusionFunction::routeURL($url, $Itemid);
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
                $replacement .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
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
            $regex_header[] = '#(href="|src=")[\.\/]+(.*?")#mS';
            $replace_header[] = '$1' . $data->integratedURL . '$2';
            $callback_header[] = '';
            //fix for URL redirects
            $regex_header[] = '#<meta http-equiv="refresh" content="(.*?)"(.*?)>#m';
            $replace_header[] = '';
            $callback_header[] = 'fixRedirect';
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
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $pathway = array();

		    $mainframe = JFusionFactory::getApplication();

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
		    JFusionFunction::raiseError($e, $this->getJname());
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
	    $db = JFusionFactory::getDatabase($this->getJname());
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
        $where.= ' AND p.post_visibility = 1';
        $forum = JFusionFactory::getForum($this->getJname());
        if ($pluginParam->get('forum_mode', 0)) {
            $selected_ids = $pluginParam->get('selected_forums', array());
            $forumids = $forum->filterForumList($selected_ids);
        } else {
	        try {
		        $db = JFusionFactory::getDatabase($this->getJname());
		        //no forums were selected so pull them all then filter

		        $query = $db->getQuery(true)
			        ->select('forum_id')
			        ->from('#__forums')
			        ->where('forum_type = 1')
			        ->order('left_id');

		        $db->setQuery($query);
		        $forumids = $db->loadColumn();
		        $forumids = $forum->filterForumList($forumids);
	        } catch (Exception $e) {
		        JFusionFunction::raiseError($e, $this->getJname());
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
     *
     * @return string
     */
    function getSearchResultLink($post) {
        $forum = JFusionFactory::getForum($this->getJname());
        return $forum->getPostURL($post->topic_id, $post->post_id);
    }

	/**
	 * @param $buffer
	 *
	 * @return mixed|string
	 */
	function callback($buffer) {
		$headers_list = headers_list();
		foreach ($headers_list as $value) {
			$matches = array();
			if (stripos($value, 'location') === 0) {
				if (preg_match('#' . preg_quote($this->data->integratedURL, '#') . '(.*?)\z#Sis', $value, $matches)) {
					$matches[1] = './' . $matches[1];
					header('Location: ' . $this->fixUrl($matches));
				}
			} else if (stripos($value, 'refresh') === 0) {
				if (preg_match('#: (.*?) URL=' . preg_quote($this->data->integratedURL, '#') . '(.*?)\z#Sis', $value, $matches)) {
					$time = $matches[1];
					$matches[1] = $matches[2];
					header('Refresh: ' . $time . ' URL=' . $this->fixUrl($matches));
				}
			}
		}
		return $buffer;
	}
}