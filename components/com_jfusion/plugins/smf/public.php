<?php

/**
 * file containing public function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * JFusion Public Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_smf extends JFusionPublic
{
    /**
     * @var $callbackdata object
     */
    var $callbackdata = null;
    /**
     * @var bool $callbackbypass
     */
	var $callbackbypass = null;

    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'smf';
    }

    /**
     * Get registration url
     *
     * @return string url
     */
    function getRegistrationURL()
    {
        return 'index.php?action=register';
    }

    /**
     * Prepares text for various areas
     *
     * @param string &$text             Text to be modified
     * @param string $for              (optional) Determines how the text should be prepared.
     * Options for $for as passed in by JFusion's plugins and modules are:
     * joomla (to be displayed in an article; used by discussion bot)
     * forum (to be published in a thread or post; used by discussion bot)
     * activity (displayed in activity module; used by the activity module)
     * search (displayed as search results; used by search plugin)
     * @param JParameter $params           (optional) Joomla parameter object passed in by JFusion's module/plugin
     * @param object $object           (optional) Object with information for the specific element the text is from
     * @param object $return
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
                $query = 'SELECT value, variable FROM #__settings WHERE variable = \'smileys_url\' OR variable = \'smiley_sets_default\'';
                $db->setQuery($query);
                $settings = $db->loadObjectList('variable');
                $query = 'SELECT code, filename FROM #__smileys ORDER BY smileyOrder';
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
     * Get lost password url
     *
     * @return string url
     */
    function getLostPasswordURL()
    {
        return 'index.php?action=reminder';
    }

    /**
     * Get url for lost user name
     *
     * @return string url
     */
    function getLostUsernameURL()
    {
        return 'index.php?action=reminder';
    }

    /**
     * getBuffer
     *
     * @param object &$data object that has must of the plugin data in it
     *
     * @return void
     */
    function getBuffer(&$data)
    {
    	$this->data = $data;
        $jFusion_Route = JRequest::getVar('jFusion_Route',null);
        if ($jFusion_Route) {
        	$jFusion_Route = unserialize ($jFusion_Route);
        	foreach ($jFusion_Route as $key => $value) {
        		if (stripos($value, 'action') === 0) {
	        		list ($key,$value) = explode ( ',' , $value);
	        		if ($key == 'action') {
	        			JRequest::setVar('action',$value);
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
        global $scripturl, $ID_MEMBER, $func,$newpassemail,$user_profile, $validationCode;
        global $settings, $options, $board_info, $attachments, $messages_request, $memberContext, $db_character_set;
        // Required to avoid a warning about a license violation even though this is not the case
        global $forum_version;
        // require_once JFUSION_PLUGIN_PATH.DS.$this->getJname().DS.'hooks.php';
        $source_path = $params->get('source_path');
        if (substr($source_path, -1) == DS) {
            $index_file = $source_path . 'index.php';
        } else {
            $index_file = $source_path . DS . 'index.php';
        }
        if (!is_file($index_file)) {
            JError::raiseWarning(500, 'The path to the SMF index file set in the component preferences does not exist');
            return null;
        }
        //set the current directory to SMF
        chdir($source_path);
		$this->callbackdata = $data;
		$this->callbackbypass = false;

        // Get the output
		ob_start(array($this, 'callback'));
		$h = ob_list_handlers();
        $rs = include_once ($index_file);
        // die if popup
        if ($action == 'findmember' || $action == 'helpadmin' || $action == 'spellcheck' || $action == 'requestmembers') {
            die();
        } else {
            $this->callbackbypass = true;
        }
    	while( in_array( get_class($this).'::callback' , $h) ) {
			$data->buffer .= ob_get_contents();
			ob_end_clean();
			$h = ob_list_handlers();
		}
        //change the current directory back to Joomla.
        chdir(JPATH_SITE);
        // Log an error if we could not include the file
        if (!$rs) {
            JError::raiseWarning(500, 'Could not find SMF in the specified directory');
        }
        $document = JFactory::getDocument();
        $document->addScript(JFusionFunction::getJoomlaURL().JFUSION_PLUGIN_DIR_URL.$this->getJname().'/js/script.js');
    }

    /**
     * parseBody
     *
     * @param object &$data object that has must of the plugin data in it
     *
     * @return void
     */
    function parseBody(&$data)
    {
        $regex_body = array();
        $replace_body = array();
        $callback_body = array();
        //fix for form actions
//        $regex_body[] = '#action="' . $data->integratedURL . 'index.php(.*?)"(.*?)>#m';
        $regex_body[] = '#action="(.*?)"(.*?)>#m';
        $replace_body[] = '';//$this->fixAction("index.php$1","$2","' . $data->baseURL . '")';
        $callback_body[] = 'fixAction';
        
        $regex_body[] = '#(?<=href=["|\'])'.preg_quote($data->integratedURL,'#').'(.*?)(?=["|\'])#mSi';        
        $replace_body[] = '';//\'href="\'.$this->fixUrl("#$1","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixURL';
        $regex_body[] = '#(?<=href=["|\'])(\#.*?)(?=["|\'])#mSi';        
        $replace_body[] = '';//\'href="\'.$this->fixUrl("#$1","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixURL';

        //Jump Related fix
        $regex_body[] = '#<select name="jumpto" id="jumpto".*?">(.*?)</select>#mSsi';
        $replace_body[] = '';//$this->fixJump("$1")';
        $callback_body[] = 'fixJump';
        
        $regex_body[] = '#<input (.*?) window.location.href = \'(.*?)\' \+ this.form.jumpto.options(.*?)>#mSsi';
        $replace_body[] = '<input $1 window.location.href = jf_scripturl + this.form.jumpto.options$3>';
        $callback_body[] = '';
        
        $regex_body[] = '#smf_scripturl \+ \"\?action#mSsi';
        $replace_body[] = 'jf_scripturl + "&action';
        $callback_body[] = '';
        
        $regex_body[] = '#<a (.*?) onclick="doQuote(.*?)>#mSsi';
        $replace_body[] = '<a $1 onclick="jfusion_doQuote$2>';
        $callback_body[] = '';
        
        $regex_body[] = '#<a (.*?) onclick="modify_msg(.*?)>#mSsi';
        $replace_body[] = '<a $1 onclick="jfusion_modify_msg$2>';
        $callback_body[] = '';
        
        $regex_body[] = '#modify_save\(#mSsi';
        $replace_body[] = 'jfusion_modify_save(';
        $callback_body[] = '';
        
        // Chaptcha fix
        $regex_body[] = '#(?<=")'.preg_quote($data->integratedURL,'#').'(index.php\?action=verificationcode;rand=.*?)(?=")#si';
        $replace_body[] = '';//\'"\'.$this->fixUrl("index.php?$2$3","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixURL';
        $regex_body[] = '#new_url[.]indexOf[(]"rand="#si';
        $replace_body[] = 'new_url.indexOf("rand';    

        //Fix auto member search
        $regex_body[] = '#(?<=toComplete\.source = \")'.preg_quote($data->integratedURL,'#'). '(.*?)(?=\")#si';
        $replace_body[] = '';//\'toComplete.source = "\'.$this->fixUrlNoAmp("$1","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixUrlNoAmp';
        
        $regex_body[] = '#(?<=bccComplete\.source = \")'.preg_quote($data->integratedURL,'#'). '(.*?)(?=\")#si';
        $replace_body[] = '';//\'bccComplete.source = "\'.$this->fixUrlNoAmp("$1","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
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
     * Parseheader
     *
     * @param object &$data object that has must of the plugin data in it
     *
     * @return void
     */
    function parseHeader(&$data)
    {
        static $regex_header, $replace_header;
        if (!$regex_header || !$replace_header) {
            $params = JFusionFactory::getParams('joomla_int');
            $joomla_url = $params->get('source_url');
            $baseURLnoSef = 'index.php?option=com_jfusion&Itemid=' . JRequest::getInt('Itemid');
            if (substr($joomla_url, -1) == '/') {
                $baseURLnoSef = $joomla_url . $baseURLnoSef;
            } else {
                $baseURLnoSef = $joomla_url . '/' . $baseURLnoSef;
            }
            // Define our preg arrays
            $regex_header = array();
            $replace_header = array();
            
            //convert relative links into absolute links
            $regex_header[] = '#(href|src)=("./|"/)(.*?)"#mS';
            $replace_header[] = '$1="' . $data->integratedURL . '$3"';
            //$regex_header[]    = '#(href|src)="(.*)"#mS';
            //$replace_header[]    = 'href="'.$data->integratedURL.'$2"';
            //convert relative links into absolute links
            $regex_header[] = '#(href|src)=("./|"/)(.*?)"#mS';
            $replace_header[] = '$1="' . $data->integratedURL . '$3"';
            $regex_header[] = '#var smf_scripturl = ["|\'](.*?)["|\'];#mS';
            $replace_header[] = 'var smf_scripturl = "$1"; var jf_scripturl = "' . $baseURLnoSef . '";';
        }
        $data->header = preg_replace($regex_header, $replace_header, $data->header);

        //fix for URL redirects
	    $data->header = preg_replace_callback('#<meta http-equiv="refresh" content="(.*?)"(.*?)>#m',array( &$this,'fixRedirect'), $data->header);
    }

    /**
     * Fix Url
     *
     * @param array $matches
     *
     * @return string url
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
     * Fix action
     *
     * @param array $matches
     *
     * @return string html
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
     * Fix jump code
     *
     * @param array $matches
     *
     * @return string html
     */
    function fixJump($matches)
    {
    	$content = $matches[1];
    	
        $find = '#<option value="[?](.*?)">(.*?)</option>#mSsi';
        $replace = '<option value="&$1">$2</option>';
        $content = preg_replace($find, $replace, $content);
        return '<select name="jumpto" id="jumpto" onchange="if (this.selectedIndex > 0 && this.options[this.selectedIndex].value && this.options[this.selectedIndex].value.length) window.location.href = jf_scripturl + this.options[this.selectedIndex].value;">' . $content . '</select>';
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
            $redirectURL .= '#'.$fragment;
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

        $query = 'SELECT ID_TOPIC,ID_BOARD, subject '.
        'FROM #__messages '.
        'WHERE ID_TOPIC = ' . $db->Quote($topic_id);
        $db->setQuery($query );
        $topic = $db->loadObject();

        if ($topic) {
            $board_id = $topic->ID_BOARD;
        }

        if ($board_id) {
            $boards = array();
            // Loop while the parent is non-zero.
            while ($board_id != 0)
            {
                $query = 'SELECT b.ID_PARENT , b.ID_BOARD, b.ID_CAT, b.name , c.name as catname '.
                'FROM #__boards AS b INNER JOIN #__categories AS c ON b.ID_CAT = c.ID_CAT '.
                'WHERE ID_BOARD = ' . $db->Quote($board_id);
                $db->setQuery($query );
                $result = $db->loadObject();

                $board_id = 0;
                if ($result) {
                    $board_id = $result->ID_PARENT;
                    $boards[] = $result;
                }
            }
            $boards = array_reverse($boards);
            $cat_id = 0;
            foreach ($boards as $board) {
                $path = new stdClass();
                if ( $board->ID_CAT != $cat_id ) {
                    $cat_id = $board->ID_CAT;
                    $path->title = $board->catname;
                    $path->url = 'index.php#'.$board->ID_CAT;
                    $pathway[] = $path;

                    $path = new stdClass();
                    $path->title = $board->name;
                    $path->url = 'index.php?board='.$board->ID_BOARD.'.0';
                } else {
                    $path->title = $board->name;
                    $path->url = 'index.php?board='.$board->ID_BOARD.'.0';
                }
                $pathway[] = $path;
            }
        }
        switch ($action) {
            case 'post':
                $path = new stdClass();
                if ( JRequest::getVar('board')) {
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

    /************************************************
    * For JFusion Search Plugin
    ***********************************************/

    /**
     * Get the search Columns for queary
     *
     * @return object
     */
    function getSearchQueryColumns()
    {
        $columns = new stdClass();
        $columns->title = 'p.subject';
        $columns->text = 'p.body';
        return $columns;
    }

    /**
     * Get the search queary
     *
     * @param object &$pluginParam custom plugin parameters in search.xml
     *
     * @return string
     */
    function getSearchQuery(&$pluginParam)
    {
        //need to return threadid, postid, title, text, created, section
        $query = 'SELECT p.ID_TOPIC, p.ID_MSG, p.ID_BOARD, CASE WHEN p.subject = "" THEN CONCAT("Re: ",fp.subject) ELSE p.subject END AS title, p.body AS text,
                    FROM_UNIXTIME(p.posterTime, "%Y-%m-%d %h:%i:%s") AS created,
                    CONCAT_WS( "/", f.name, fp.subject ) AS section,
                    t.numViews as hits
                    FROM #__messages AS p
                    INNER JOIN #__topics AS t ON t.ID_TOPIC = p.ID_TOPIC
                    INNER JOIN #__messages AS fp ON fp.ID_MSG = t.ID_FIRST_MSG
                    INNER JOIN #__boards AS f on f.ID_BOARD = p.ID_BOARD';
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
        if ($pluginParam->get('forum_mode', 0)) {
            $forumids = $pluginParam->get('selected_forums', array());
            $where.= ' AND p.ID_BOARD IN (' . implode(',', $forumids) . ')';
        }

        //determine how to sort the results which is required for accurate results when a limit is placed
        switch ($ordering) {
             case 'oldest':
                $sort = 'p.posterTime ASC';
                break;
            case 'category':
                $sort = 'section ASC';
                break;
            case 'popular':
                $sort = 't.numViews DESC, p.posterTime DESC';
                break;
            case 'alpha':
                $sort = 'title ASC';
                break;
            case 'newest':
            default:
                $sort = 'p.posterTime DESC';
                break;
        }
        $where .= ' ORDER BY '.$sort;
    }

    /**
     * filter search results
     *
     * @param array &$results array with search results
     * @param object &$pluginParam custom plugin parameters in search.xml
     */
    function filterSearchResults(&$results, &$pluginParam)
    {
		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT value FROM #__settings WHERE variable=\'censor_vulgar\'';
		$db->setQuery($query);
		$vulgar = $db->loadResult();

		$db =& JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT value FROM #__settings WHERE variable=\'censor_proper\'';
		$db->setQuery($query);
		$proper = $db->loadResult();

		$vulgar = explode  ( ',' , $vulgar );
		$proper = explode  ( ',' , $proper );

		foreach($results as $rkey => &$result) {
			foreach( $vulgar as $key => $value ) {
				$results[$rkey]->title = preg_replace  ( '#\b'.preg_quote($value,'#').'\b#is' , $proper[$key]  , $result->title );
				$results[$rkey]->text = preg_replace  ( '#\b'.preg_quote($value,'#').'\b#is' , $proper[$key]  , $result->text );
			}
		}
    }

    /**
     * Create search link from post info
     *
     * @param object $post convert post info in to a link
     *
     * @return string
     */
    function getSearchResultLink($post)
    {
        $forum = JFusionFactory::getForum($this->getJname());
        return $forum->getPostURL($post->ID_TOPIC, $post->ID_MSG);
    }

    /************************************************
    * Functions For JFusion Who's Online Module
    ***********************************************/

    /**
     * Returns a query to find online users
     * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
     *
     * @param int $limit limit of user online
     *
     * @return string
     */
    function getOnlineUserQuery($limit)
    {
        $limiter = (!empty($limit)) ? 'LIMIT 0,'.$limit : '';
        return 'SELECT DISTINCT u.ID_MEMBER AS userid, u.memberName AS username, u.realName AS name, u.emailAddress as email FROM #__members AS u INNER JOIN #__log_online AS s ON u.ID_MEMBER = s.ID_MEMBER WHERE s.ID_MEMBER != 0 '.$limiter;
    }

    /**
     * Returns number of guests
     *
     * @return int
     */
    function getNumberOnlineGuests()
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT COUNT(DISTINCT(ip)) FROM #__log_online WHERE ID_MEMBER = 0';
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * Returns number of logged in users
     *
     * @return int
     */
    function getNumberOnlineMembers()
    {
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT COUNT(DISTINCT(ip)) FROM #__log_online WHERE ID_MEMBER != 0';
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * Function called by sh404sef for url building
     *
     * @param array &$title with titles for url
     * @param array &$get   global pointer to sh404sef remaning $_GET values from the url
     *
     * @return void
     */
    function sh404sef(&$title, &$get)
    {
        if (isset($get['action'])) {
            $title[] = $get['action'];
            shRemoveFromGETVarsList('action');
        }
        foreach ($get as $key => $value) {
            $title[] = $key . $value;
            shRemoveFromGETVarsList($key);
        }
    }

    /**
     * this is the callback for the ob_start for smf
     *
     * @param string $buffer Html buffer from the smf callback
     *
     * @return string
     */
    function callback($buffer)
    {
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
        if ($this->callbackbypass) {
            return $buffer;
        }
        global $context;
        if (isset($context['get_data'])) {
            if ($context['get_data'] && strpos($context['get_data'], 'jFusion_Route')) {
                $buffer = str_replace($context['get_data'], '?action=admin', $buffer);
            }
        }
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