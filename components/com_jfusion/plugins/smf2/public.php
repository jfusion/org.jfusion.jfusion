<?php

/**
* @package JFusion_SMF
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Public Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * @package JFusion_SMF
 */
class JFusionPublic_smf2 extends JFusionPublic {
    /**
     * @var $callbackdata object
     */
    var $callbackdata = null;
    /**
     * @var bool $callbackbypass
     */
    var $callbackbypass = null;


    /**
     * @return string
     */
    function getJname()
	{
		return 'smf2';
	}

    /**
     * @return string
     */
    function getRegistrationURL()
	{
		return 'index.php?action=register';
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
     * @param JParameter $params           (optional) Joomla parameter object passed in by JFusion's module/plugin
     * @param string  $object           (optional) Object with information for the specific element the text is from
     * @param string  $return
     *
     * @return array  $status           Information passed back to calling script such as limit_applied
     */
    function prepareText(&$text, $for = 'forum', $params = null, $object = null, $return = null)
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
                //pattens to run in begening
                $bbcode[0][] = "#<a[^>]*href=['|\"](ftp://)(.*?)['|\"][^>]*>(.*?)</a>#si";
                $bbcode[1][] = "[ftp=$1$2]$3[/ftp]";
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
            $text = JFusionFunction::parseCode($text, 'bbcode', $options);
        } elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
            $options = array();
            //convert smilies so they show up in Joomla as images
            static $custom_smileys;
            if (!is_array($custom_smileys)) {
                $custom_smileys = array();
                $db = JFusionFactory::getDatabase($this->getJname());
                $query = "SELECT value, variable FROM #__settings WHERE variable = 'smileys_url' OR variable = 'smiley_sets_default'";
                $db->setQuery($query);
                $settings = $db->loadObjectList('variable');
                $query = "SELECT code, filename FROM #__smileys ORDER BY smileyOrder";
                $db->setQuery($query);
                $smilies = $db->loadObjectList();
                if (!empty($smilies)) {
                    foreach ($smilies as $s) {
                        $custom_smileys[$s->code] = "{$settings['smileys_url']->value}/{$settings['smiley_sets_default']->value}/{$s->filename}";
                    }
                }
            }
            $options['custom_smileys'] = $custom_smileys;
            $options['parse_smileys'] = true;
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

            $text = JFusionFunction::parseCode($text, 'html', $options);
        } elseif ($for == 'search') {
            $text = JFusionFunction::parseCode($text, 'plaintext');
        } elseif ($for == 'activity') {
            if ($params->get('parse_text') == 'plaintext') {
                $options = array();
                $options['plaintext_line_breaks'] = 'space';
                if ($params->get('character_limit')) {
                    $status['limit_applied'] = 1;
                    $options['character_limit'] = $params->get('character_limit');
                }
                $text = JFusionFunction::parseCode($text, 'plaintext', $options);
            }
        }
        return $status;
    }

    /**
     * @return string
     */
    function getLostPasswordURL()
	{
		return 'index.php?action=reminder';
	}

    /**
     * @return string
     */
    function getLostUsernameURL()
	{
		return 'index.php?action=reminder';
	}

    /**
     * @param object $data
     */
    function getBuffer(&$data)
	{
		$this->data = $data;
	    $jFusion_Route = JRequest::getVar('jFusion_Route',null);
        if ($jFusion_Route) {
        	$jFusion_Route = unserialize ($jFusion_Route);
        	foreach ($jFusion_Route as $value) {
        		if (stripos($value, 'action') === 0) {
	        		list ($k,$v) = explode ( ',' , $value);
	        		if ($k == 'action') {
	        			JRequest::setVar('action',$v);
	        		}
        		}
        	}
        }
        $action = JRequest::getVar('action');
        if ($action == 'register' || $action == 'reminder') {
            $master = JFusionFunction::getMaster();
            if ($master->name != $this->getJname()) {
                $JFusionMaster = JFusionFactory::getPublic($master->name);
                $params = JFusionFactory::getParams($master->name);
                $source_url = $params->get('source_url');
                $source_url = rtrim($source_url, '/');
				if ($action == 'register') {
                    header('Location: ' . $source_url . $JFusionMaster->getRegistrationURL());
                } else {
                    header('Location: ' . $source_url . $JFusionMaster->getLostPasswordURL());
                }
                exit();
            }
        }
        //handle dual logout
        $params = JFusionFactory::getParams($this->getJname());
        if ($action == 'logout') {
            //destroy the SMF session first
            $JFusionUser = JFusionFactory::getUser($this->getJname());
            $JFusionUser->destroySession(null, null);
            //destroy the Joomla session
            $mainframe = JFactory::getApplication();
            $mainframe->logout();
            $session = JFactory::getSession();
            $session->close();
            JFusionFunction::addCookie($params->get('cookie_name'), '', 0, $params->get('cookie_path'), $params->get('cookie_domain'), $params->get('secure'), $params->get('httponly'));
            //redirect so the changes are applied
            $mainframe->redirect(str_replace('&amp;', '&', $data->baseURL));
            exit();
        }
        //handle dual login
        if ($action == 'login2') {
            //uncommented out the code below, as the smf session is needed to validate the password, which can not be done unless SSI.php is required
            //get the submitted user details
            //$username = JRequest::getVar('user');
            //$password = JRequest::getVar('hash_passwrd');
            //get the userinfo directly from SMF
            //$JFusionUser =& JFusionFactory::getUser($this->getJname());
            //$userinfo = $JFusionUser->getUser($username);
            //generate the password hash
            //$test_crypt = sha1($userinfo->password . $smf_session_id);
            //validate that the password is correct
            //if (!empty($password) && !empty($test_crypt) && $password == $test_crypt){
            //}

        }
		if ($action == 'verificationcode') {
			JRequest::setVar('format',null);
		}

		// We're going to want a few globals... these are all set later.
		global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
		global $boardurl, $boarddir, $sourcedir, $webmaster_email, $cookiename;
		global $db_connection, $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_last_error;
		global $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
		global $scripturl, $ID_MEMBER, $func;

	    global $settings, $options, $board_info, $attachments, $messages_request ,$memberContext, $db_character_set;

		// new in smf 2
		global $smcFunc, $mysql_set_mode,$cachedir,$db_passwd,$db_type, $ssi_db_user, $ssi_db_passwd,$board_info, $options;

		// Required to avoid a warning about a license violation even though this is not the case
		global $forum_version;

		// Get the path
		$source_path = $params->get('source_path');

		if (substr($source_path, -1) == DS) {
			$index_file = $source_path .'index.php';
		} else {
			$index_file = $source_path .DS.'index.php';
		}

		if ( ! is_file($index_file) ) {
			JError::raiseWarning(500, 'The path to the SMF index file set in the component preferences does not exist');
		} else {
            //add handeler to undo changes that plgSystemSef create
            $dispatcher = JDispatcher::getInstance();
            if (JFusionFunction::isJoomlaVersion('1.6')) {
                $method = array('event' => 'onAfterRender', 'handler' => array($this, 'onAfterRender'));
                $dispatcher->attach($method);
            } else {
                $dispatcher->attach($this);
            }

            //set the current directory to SMF
            chdir($source_path);
            $this->callbackdata = $data;
            $this->callbackbypass = false;

            // Get the output
            ob_start(array($this, 'callback'));
            $h = ob_list_handlers();
            $rs = include_once($index_file);
            // die if popup
            if ( $action == 'findmember' || $action == 'helpadmin' || $action == 'spellcheck' || $action == 'requestmembers' || strpos($action ,'xml') !== false ) {
                exit();
            } else {
                $this->callbackbypass = true;
            }
            while( in_array( get_class($this).'::callback' , $h) ) {
                $data->buffer .= ob_get_contents();
                ob_end_clean();
                $h = ob_list_handlers();
            }

            // needed to ensure option is defined after using smf frameless. bug/conflict with System - Highlight plugin
            JRequest::setVar('option','com_jfusion');

            //change the current directory back to Joomla.
            chdir(JPATH_SITE);

            // Log an error if we could not include the file
            if (!$rs) {
                JError::raiseWarning(500, 'Could not find SMF in the specified directory');
            }
        }
	}

    /**
     * undo damage caused by plgSystemSef
     *
     * @return bool
     */
    function onAfterRender()
    {	
        $buffer = JResponse::getBody();    	
    	
        $base = JURI::base(true).'/';

        $regex_body  = '#src="'.preg_quote($base,'#').'%#mSsi';
        $replace_body= 'src="%';
        
        $buffer = preg_replace($regex_body, $replace_body, $buffer);
        
        JResponse::setBody($buffer);
        return true;    	
    }

    /**
     * @param $args
     * @return bool
     */
    function update($args)
    {
    	if (isset($args['event']) && $args['event'] == 'onAfterRender') {
    		return $this->onAfterRender();
    	}
    	return true;
    }

    /**
     * @param object $data
     */
    function parseBody(&$data)
	{
		$regex_body		= array();
		$replace_body	= array();

		//fix for form actions
        $regex_body[] = '#action="(.*?)"(.*?)>#m';
        $replace_body[] = '';//$this->fixAction("index.php$1","$2","' . $data->baseURL . '")';
        $callback_body[] = 'fixAction';

        $regex_body[] = '#(?<=href=["|\'])' . $data->integratedURL . '(.*?)(?=["|\'])#mSi';        
        $replace_body[] = '';//\'href="\'.$this->fixUrl("#$1","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixURL';
        $regex_body[] = '#(?<=href=["|\'])(\#.*?)(?=["|\'])#mSi';        
        $replace_body[] = '';//\'href="\'.$this->fixUrl("#$1","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixURL';
		
		$regex_body[]	= '#sScriptUrl: \'http://joomla.fanno.dk/smf2/index.php\'#mSsi';
		$replace_body[]	= 'sScriptUrl: \''.$data->baseURL.'\'';

        // Chaptcha fix
        $regex_body[] = '#(?<=src=")' . $data->integratedURL . '(index.php\?action=verificationcode.*?)(?=")#si';
        $replace_body[] = '';//\'"\'.$this->fixUrl("index.php?$2$3","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixURL';
        $regex_body[] = '#(?<=data=")' . $data->integratedURL . '(index.php\?action=verificationcode.*?)(?=")#si';
        $replace_body[] = '';//\'"\'.$this->fixUrl("index.php?$2$3","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixURL';
        $regex_body[] = '#(?<=\(")' . $data->integratedURL . '(index.php\?action=verificationcode.*?)(?=")#si';
        $replace_body[] = '';//\'"\'.$this->fixUrl("index.php?$2$3","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixUrlNoAmp';
        $regex_body[] = '#(?<=\>)' . $data->integratedURL . '(index.php\?action=verificationcode.*?)(?=</a>)#si';
        $replace_body[] = '';//\'"\'.$this->fixUrl("index.php?$2$3","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixUrlNoAmp';
		
		foreach ($regex_body as $k => $v) {
        	//check if we need to use callback
        	if(!empty($callback_body[$k])){
			    $data->body = preg_replace_callback($regex_body[$k],array( &$this,$callback_body[$k]), $data->body);             		
        	} else {
        		$data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
        	}
        }
	}

    /**
     * @param object $data
     */
    function parseHeader(&$data)
	{
		static $regex_header, $replace_header;
		if ( ! $regex_header || ! $replace_header )
		{
			$params = JFusionFactory::getParams('joomla_int');
			$joomla_url = $params->get('source_url');

			$baseURLnoSef = 'index.php?option=com_jfusion&Itemid=' . JRequest::getInt('Itemid');
			if (substr($joomla_url, -1) == '/') $baseURLnoSef = $joomla_url . $baseURLnoSef;
			else $baseURLnoSef = $joomla_url . '/' . $baseURLnoSef;

			// Define our preg arrays
			$regex_header		= array();
			$replace_header	= array();

			//convert relative links into absolute links
			$regex_header[]	= '#(href|src)=("./|"/)(.*?)"#mS';
			$replace_header[]	= '$1="'.$data->integratedURL.'$3"';

			//$regex_header[]	= '#(href|src)="(.*)"#mS';
			//$replace_header[]	= 'href="'.$data->integratedURL.'$2"';

			//convert relative links into absolute links
			$regex_header[]	= '#(href|src)=("./|"/)(.*?)"#mS';
			$replace_header[]	= '$1="'.$data->integratedURL.'$3"';

			$regex_header[] = '#var smf_scripturl = ["|\'](.*?)["|\'];#mS';
			$replace_header[] = 'var smf_scripturl = "'.$baseURLnoSef.'&";';

	        //fix for URL redirects
        	$regex_body[] = '#(?<=")' . $data->integratedURL . '(index.php\?action=verificationcode;rand=.*?)(?=")#si';
        	$replace_body[] = '';//\'"\'.$this->fixUrl("index.php?$2$3","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        	$callback_body[] = 'fixRedirect';
		}
		$data->header = preg_replace($regex_header, $replace_header, $data->header);
	}

    /**
     * @param $matches
     * @return string
     */
    function fixUrl($matches)
    {
		$q = $matches[1];

		$integratedURL = $this->data->integratedURL;		
		$baseURL = $this->data->baseURL;
		$fullURL = $this->data->fullURL;

        //SMF uses semi-colons to seperate vars as well. Convert these to normal ampersands
        $q = str_replace(';', '&amp;', $q);
        if (strpos($q, '#') === 0) {
            $url = $fullURL . $q;
        } else {
			if (substr($baseURL, -1) != '/') {
	            //non sef URls
	            $q = str_replace('?', '&amp;', $q);
	            $url = $baseURL . '&amp;jfile=' . $q;
	        } else {
	            $params = JFusionFactory::getParams($this->getJname());
	            $sefmode = $params->get('sefmode');
	            if ($sefmode == 1) {
	                $url = JFusionFunction::routeURL($q, JRequest::getInt('Itemid'));
	            } else {
	                //we can just append both variables
	                $url = $baseURL . $q;
	            }
	        }
        }
        return $url;
    }
    
    /**
     * Fix url with no amps
     *
     * @param array $matches
     *
     * @return string url
     */    
    function fixUrlNoAmp($matches)
    {    	
		$url = $this->fixUrl($matches);
		$url = str_replace('&amp;', '&', $url);
        return $url;
    }

    /**
     * @param $matches
     * @return string
     */
    function fixAction($matches)
    {
		$url = $matches[1];
		$extra = $matches[2];		

		$baseURL = $this->data->baseURL;    	
        //JError::raiseWarning(500, $url);
        $url = htmlspecialchars_decode($url);
        $Itemid = JRequest::getInt('Itemid');
        $extra = stripslashes($extra);
        $url = str_replace(';', '&amp;', $url);
        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            $url_details = parse_url($url);
            $url_variables = array();
            $jfile = basename($url_details['path']);
            if (isset($url_details['query'])) {
                parse_str($url_details['query'], $url_variables);
                $baseURL.= '&amp;' . $url_details['query'];
            }
            //set the correct action and close the form tag
            $replacement = 'action="' . $baseURL . '"' . $extra . '>';
            $replacement.= '<input type="hidden" name="jfile" value="' . $jfile . '"/>';
            $replacement.= '<input type="hidden" name="Itemid" value="' . $Itemid . '"/>';
            $replacement.= '<input type="hidden" name="option" value="com_jfusion"/>';
        } else {
            //check to see what SEF mode is selected
            $params = JFusionFactory::getParams($this->getJname());
            $sefmode = $params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $url = JFusionFunction::routeURL($url, $Itemid);
                $replacement = 'action="' . $url . '"' . $extra . '>';
                return $replacement;
            } else {
                //simple SEF mode
                $url_details = parse_url($url);
                $url_variables = array();
                $jfile = basename($url_details['path']);
                if (isset($url_details['query'])) {
                    parse_str($url_details['query'], $url_variables);
                    $jfile.= '?' . $url_details['query'];
                }
                $replacement = 'action="' . $baseURL . $jfile . '"' . $extra . '>';
            }
        }
        unset($url_variables['option'], $url_variables['jfile'], $url_variables['Itemid']);
        //add any other variables
        /* Commented out because of problems with wrong variables being set
        if (is_array($url_variables)){
        foreach ($url_variables as $key => $value){
        $replacement .=  '<input type="hidden" name="'. $key .'" value="'.$value . '"/>';
        }
        }
        */
        return $replacement;
    }

    /**
     * @param $matches
     * @return string
     */
    function fixRedirect($matches) {
		$url = $matches[1];
		$baseURL = $this->data->baseURL;
		    	
        //JError::raiseWarning(500, $url);
        //split up the timeout from url
        $parts = explode(';url=', $url);
        $timeout = $parts[0];
        $uri = new JURI($parts[1]);
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
            $params = JFusionFactory::getParams($this->getJname());
            $sefmode = $params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $redirectURL = $jfile;
                if (!empty($query)) {
                    $redirectURL.= '?' . $query;
                }
                $redirectURL = JFusionFunction::routeURL($redirectURL, JRequest::getInt('Itemid'));
            } else {
                //simple SEF mode, we can just combine both variables
                $redirectURL = $baseURL . $jfile;
                if (!empty($query)) {
                    $redirectURL.= '?' . $query;
                }
            }
        }
        if (!empty($fragment)) {
            $redirectURL .= "#$fragment";
        }
        $return = '<meta http-equiv="refresh" content="' . $timeout . ';url=' . $redirectURL . '">';
        //JError::raiseWarning(500, htmlentities($return));
        return $return;
    }

    /**
     * @return array
     */
    function getPathWay()
	{
		$db = JFusionFactory::getDatabase($this->getJname());
		$pathway = array();

		list ($board_id ) = split  ( '.'  , JRequest::getVar('board'),1 );
		list ($topic_id ) = split  ( '.'  , JRequest::getVar('topic'),1 );
		list ($action ) = split  ( ';'  , JRequest::getVar('action'),1 );

		$msg = JRequest::getVar('msg');

        $query = 'SELECT id_topic,id_board, subject '.
        		'FROM #__messages '.
        		'WHERE id_topic = ' . $db->Quote($topic_id);
        $db->setQuery($query );
        $topic = $db->loadObject();

        if ($topic) {
			$board_id = $topic->id_board;
        }

		if ($board_id) {
			$boards = array();
			// Loop while the parent is non-zero.
			while ($board_id != 0)
			{
		        $query = 'SELECT b.id_parent , b.id_board, b.id_cat, b.name , c.name as catname '.
		        		'FROM #__boards AS b INNER JOIN #__categories AS c ON b.id_cat = c.id_cat '.
		        		'WHERE id_board = ' . $db->Quote($board_id);
		        $db->setQuery($query );
		        $result = $db->loadObject();

				$board_id = 0;
		 		if ($result) {
		 			$board_id = $result->id_parent;
		 			$boards[] = $result;
				}
			}
			$boards = array_reverse($boards);
			$cat_id = 0;
			foreach($boards as $board) {
				$path = new stdClass();
				if ( $board->id_cat != $cat_id ) {
					$cat_id = $board->id_cat;
					$path->title = $board->catname;
					$path->url = 'index.php#'.$board->id_cat;
					$pathway[] = $path;

					$path = new stdClass();
					$path->title = $board->name;
					$path->url = 'index.php?board='.$board->id_board.'.0';
				} else {
					$path->title = $board->name;
					$path->url = 'index.php?board='.$board->id_board.'.0';
				}
				$pathway[] = $path;
			}
		}
		switch ($action) {
		    case 'post':
		    	$path = new stdClass();
		    	if ( JRequest::getVar('board') ) {
					$path->title = 'Modify Toppic ( Start new topic )';
					$path->url = 'index.php?action=post&board='.$board_id.'.0';;
		    	} else if ( $msg ) {
					$path->title = 'Modify Toppic ( '.$topic->subject.' )';
					$path->url = 'index.php?action=post&topic='.$topic_id.'.msg'.$msg.'#msg'.$msg;
		    	} else {
					$path->title = 'Post reply ( Re: '.$topic->subject.' )';
					$path->url = 'index.php?action=post&topic='.$topic_id;
		    	}
				$pathway[] = $path;
		        break;
			case 'pm':
				$path = new stdClass();
				$path->title = 'Personal Messages';
				$path->url = 'index.php?action=pm';
				$pathway[] = $path;

				$path = new stdClass();
				if ( JRequest::getVar('sa')=='send' ) {
					$path->title = 'New Message';
					$path->url = 'index.php?action=pm&sa=send';
					$pathway[] = $path;
				} elseif ( JRequest::getVar('sa')=='search' ) {
					$path->title = 'Search Messages';
					$path->url = 'index.php?action=pm&sa=search';
					$pathway[] = $path;
				} elseif ( JRequest::getVar('sa')=='prune' ) {
					$path->title = 'Prune Messages';
					$path->url = 'index.php?action=pm&sa=prune';
					$pathway[] = $path;
				} elseif ( JRequest::getVar('sa')=='manlabels' ) {
					$path->title = 'Manage Labels';
					$path->url = 'index.php?action=pm&sa=manlabels';
					$pathway[] = $path;
				} elseif ( JRequest::getVar('f')=='outbox' ) {
					$path->title = 'Outbox';
					$path->url = 'index.php?action=pm&f=outbox';
					$pathway[] = $path;
				} else {
					$path->title = 'Inbox';
					$path->url = 'index.php?action=pm';
					$pathway[] = $path;
				}
		        break;
			case 'search2':
				$path = new stdClass();
				$path->title = 'Search';
				$path->url = 'index.php?action=search';
				$pathway[] = $path;
				$path = new stdClass();
				$path->title = 'Search Results';
				$path->url = 'index.php?action=search';
				$pathway[] = $path;
				break;
			case 'search':
				$path = new stdClass();
				$path->title = 'Search';
				$path->url = 'index.php?action=search';
				$pathway[] = $path;
				break;
			case 'unread':
				$path = new stdClass();
				$path->title = 'Recent Unread Topics';
				$path->url = 'index.php?action=unread';
				$pathway[] = $path;
				break;
			case 'unreadreplies':
				$path = new stdClass();
				$path->title = 'Updated Topics';
				$path->url = 'index.php?action=unreadreplies';
				$pathway[] = $path;
				break;
			default:
				if ( $topic_id ) {
					$path = new stdClass();
					$path->title = $topic->subject;
					$path->url = 'index.php?topic='.$topic_id;
					$pathway[] = $path;
				}
		}
		return $pathway;
	}

    /**
     * @return object
     */
    function getSearchQueryColumns()
	{
		$columns = new stdClass();
		$columns->title = "p.subject";
		$columns->text = "p.body";
		return $columns;
	}

    /**
     * @param object $pluginParam
     * @return string
     */
    function getSearchQuery(&$pluginParam)
	{
		//need to return threadid, postid, title, text, created, section
		$query = 'SELECT p.id_topic, p.id_msg, p.id_board, CASE WHEN p.subject = "" THEN CONCAT("Re: ",fp.subject) ELSE p.subject END AS title, p.body AS text,
					FROM_UNIXTIME(p.poster_time, "%Y-%m-%d %h:%i:%s") AS created,
					CONCAT_WS( "/", f.name, fp.subject ) AS section,
					t.num_views as hits
					FROM #__messages AS p
					INNER JOIN #__topics AS t ON t.id_topic = p.id_topic
					INNER JOIN #__messages AS fp ON fp.id_msg = t.id_first_msg
					INNER JOIN #__boards AS f on f.id_board = p.id_board';
		return $query;
	}

	/**
	 * Add on a plugin specific clause;
     *
     * @param string &$where reference to where clause already generated by search bot; add on plugin specific criteria
     * @param JParameter &$pluginParam custom plugin parameters in search.xml
     * @param string $ordering
	 */
	function getSearchCriteria(&$where, &$pluginParam, $ordering)
	{
        $db = JFusionFactory::getDatabase($this->getJname());

		$userPlugin = JFusionFactory::getUser($this->getJname());

		$user = JFactory::getUser();
		$userid = $user->get('id');

		if ($userid) {
			$userlookup = JFusionFunction::lookupUser($this->getJname(),$userid,true);
			$existinguser = $userPlugin->getUser($userlookup);
			$group_id = $existinguser->group_id;
		} else {
			$group_id = '-1';
		}

        if ($pluginParam->get('forum_mode', 0)) {
            $forumids = $pluginParam->get('selected_forums', array());
            $selected_boards = " WHERE id_board IN (" . implode(',', $forumids) . ")";
        } else {
            $selected_boards = '';
        }

		$query = 'SELECT member_groups, id_board FROM #__boards' . $selected_boards;
		$db->setQuery($query);
        $boards = $db->loadObjectList();

		$list = array();
		foreach( $boards as $key => $value ) {
			$member_groups = explode( ',' , $value->member_groups );
			if ( in_array($group_id, $member_groups) || $group_id == 1) {
				$list[] =  $value->id_board;
			}
		}
        //determine how to sort the results which is required for accurate results when a limit is placed
        switch ($ordering) {
             case 'oldest':
                $sort = 'p.poster_time ASC';
                break;
            case 'category':
                $sort = 'section ASC';
                break;
            case 'popular':
                $sort = 't.num_views DESC, p.poster_time DESC';
                break;
            case 'alpha':
                $sort = 'title ASC';
                break;
            case 'newest':
            default:
                $sort = 'p.poster_time DESC';
                break;
        }
		$where .= ' AND p.id_board IN ('.implode(',',$list).') ORDER BY ' . $sort;
	}

    /**
     * @param array &$results
     * @param object &$pluginParam
     */
    function filterSearchResults(&$results = array(), &$pluginParam)
	{
		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT value FROM #__settings WHERE variable='censor_vulgar'";
		$db->setQuery($query);
		$vulgar = $db->loadResult();

		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT value FROM #__settings WHERE variable='censor_proper'";
		$db->setQuery($query);
		$proper = $db->loadResult();

		$vulgar = explode  ( ',' , $vulgar );
		$proper = explode  ( ',' , $proper );

		foreach($results as $rkey => $result) {
			foreach( $vulgar as $key => $value ) {
				$results[$rkey]->subject = preg_replace  ( '#\b'.preg_quote($value,'#').'\b#is' , $proper[$key]  , $result->subject );
				$results[$rkey]->body = preg_replace  ( '#\b'.preg_quote($value,'#').'\b#is' , $proper[$key]  , $result->body );
			}
		}
	}

    /**
     * @param mixed $post
     * @return string
     */
    function getSearchResultLink($post)
	{
		$forum = JFusionFactory::getForum($this->getJname());
		return $forum->getPostURL($post->id_topic,$post->id_msg);
	}

   /************************************************
	 * Functions For JFusion Who's Online Module
	 ***********************************************/

	/**
	 * Returns a query to find online users
	 * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
     *
     * @param int $limit
     * @param array $usergroups
     *
     * @return string
	 **/
	function getOnlineUserQuery($limit, $usergroups = array())
	{
		$usergroup_query = '';
		if(!empty($usergroups)) {
			if(is_array($usergroups)) {
				$usergroups_string = implode(',',$usergroups);
				$usergroup_query .= "AND (u.id_group IN ($usergroups_string) OR u.id_post_group IN ($usergroups_string)";
				foreach($usergroups AS $usergroup) {
					$usergroup_query .= " OR FIND_IN_SET(" . intval($usergroup) . ", u.additional_groups)";
				}
				$usergroup_query .= ")";
			} else {
				$usergroup_query .= "AND (u.id_group = $usergroups OR u.id_post_group = $usergroups OR FIND_IN_SET($usergroups, u.additional_groups))";
			}
		}

		$limiter = (!empty($limit)) ? "LIMIT 0,$limit" : '';

		return "SELECT DISTINCT u.id_member AS userid, u.member_name AS username, u.real_name AS name, u.email_address as email FROM #__members AS u INNER JOIN #__log_online AS s ON u.id_member = s.id_member WHERE s.id_member != 0 $usergroup_query $limiter";
	}

	/**
	 * Returns number of guests
	 * @return int
	 */
	function getNumberOnlineGuests()
	{
		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = "SELECT COUNT(DISTINCT(ip)) FROM #__log_online WHERE id_member = 0";
		$db->setQuery($query);
		return $db->loadResult();
	}

	/**
	 * Returns number of logged in users
     *
     * @param array $usergroups
     * @param int $total
     *
	 * @return int
	 */
	function getNumberOnlineMembers($usergroups = array(), $total = 1)
	{
		$usergroup_query = '';
		if(!empty($usergroups) && empty($total)) {
			if(is_array($usergroups)) {
                $usergroups_string = implode(',',$usergroups);
				$usergroup_query .= "AND (u.id_group IN ($usergroups_string) OR u.id_post_group IN ($usergroups_string)";
				foreach($usergroups AS $usergroup) {
					$usergroup_query .= " OR FIND_IN_SET(" . intval($usergroup) . ", u.additional_groups)";
				}
				$usergroup_query .= ")";
			} else {
				$usergroup_query .= "AND (u.id_group = $usergroups OR u.id_post_group = $usergroups OR FIND_IN_SET($usergroups, u.additional_groups))";
			}
		}

		$db =& JFusionFactory::getDatabase($this->getJname());

		$query = "SELECT COUNT(DISTINCT(l.ip)) FROM #__log_online AS l JOIN #__members AS u ON l.id_member = u.id_member WHERE l.id_member != 0 $usergroup_query";

		$db->setQuery($query);
		return $db->loadResult();
	}

	/**
	 * Function called by sh404sef for url building
     * @param array with titles for url
     * @param array global pointer to sh404sef remaning $_GET values from the url
	 */
	function sh404sef(&$title,&$get)
	{
		if( isset($get['action'] ) ) {
			$title[] = $get['action'];
			shRemoveFromGETVarsList('action');
		}

		foreach( $get as $key => $value ) {
			$title[] = $key.$value;
			shRemoveFromGETVarsList($key);
		}
	}

    /**
     * @param $buffer
     *
     * @return mixed|string
     */
    function callback($buffer) {
		$data = $this->callbackdata;
		$headers_list = headers_list();
		foreach ($headers_list as $key => $value) {
        	$matches = array();
            if (stripos($value, 'location') === 0) {
                if (preg_match('#'.preg_quote($data->integratedURL,'#').'(.*?)\z#Sis' , $value , $matches)) {
                    header('Location: '.$this->fixUrlNoAmp($matches));
                    return $buffer;
                }
            } else if (stripos($value, 'refresh') === 0) {
                if (preg_match('#: (.*?) URL='.preg_quote($data->integratedURL,'#').'(.*?)\z#Sis' , $value , $matches)) {
                	$time = $matches[1];
                	$matches[1] = $matches[2];
                    header('Refresh: '.$time.' URL='.$this->fixUrlNoAmp($matches));
                    return $buffer;
                }
            }
        }
		if ( $this->callbackbypass ) return $buffer;
		global $context;

		if ( isset($context['get_data']) ) {
			if ( $context['get_data'] && strpos( $context['get_data']  , 'jFusion_Route' ) ) {
				$buffer = str_replace ($context['get_data'],'?action=admin',$buffer);
			}
		}

		//fix for form actions
		$data->buffer = $buffer;
        ini_set('pcre.backtrack_limit', strlen($data->buffer) * 2);
        $pattern = '#<head[^>]*>(.*?)<\/head>.*?<body([^>]*)>(.*)<\/body>#si';
        if (preg_match($pattern, $data->buffer, $temp)) {
            $data->header = $temp[1];
            $data->body = $temp[3];
            $pattern = '#onload=["]([^"]*)#si';
            if (preg_match($pattern, $temp[2], $temp)) {
                $js ='<script language="JavaScript" type="text/javascript">';
                $js .= <<<JS
                if(window.addEventListener) { // Standard
                    window.addEventListener(\'load\', function(){
                        {$temp[1]}
                    }, false);
                } else if(window.attachEvent) { // IE
                    window.attachEvent(\'onload\', function(){
                        {$temp[1]}
                    });
                }
JS;
                $js .='</script>';
                $data->header.= $js;
            }
            unset($temp);
            $this->parseHeader($data);
            $this->parseBody($data);
            return '<html><head>' . $data->header . '</head><body>' . $data->body . '<body></html>';
        } else {
            return $buffer;
        }
	}
}