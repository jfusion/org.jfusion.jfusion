<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
global $baseURL, $fullURL, $integratedURL, $vbsefmode;
/**
 * JFusion Public Class for vBulletin
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_vbulletin extends JFusionPublic
{
    var $params;
    var $helper;

    /**
     *
     */
    function __construct()
    {
        //get the params object
        $this->params = JFusionFactory::getParams($this->getJname());
        //get the helper object
        $this->helper = JFusionFactory::getHelper($this->getJname());
    }

    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'vbulletin';
    }

    /**
     * @return string
     */
    function getRegistrationURL()
    {
        return 'register.php';
    }

    /**
     * @return string
     */
    function getLostPasswordURL()
    {
        return 'login.php?do=lostpw';
    }

    /**
     * @return string
     */
    function getLostUsernameURL()
    {
        return 'login.php?do=lostpw';
    }

    /**
     * @param string $text
     * @param string $for
     * @param JRegistry $params
     * @param string $object
     *
     * @return array
     */
    function prepareText(&$text, $for = 'forum', $params = null, $object = null)
    {
        $status = array();
        if ($for == 'forum') {
            //first thing is to remove all joomla plugins
            preg_match_all('/\{(.*)\}/U', $text, $matches);
            //find each thread by the id
            foreach ($matches[1] AS $plugin) {
                //replace plugin with nothing
                $text = str_replace('{' . $plugin . '}', "", $text);
            }
            $text = html_entity_decode($text);
            $text = JFusionFunction::parseCode($text, 'bbcode');
        } elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
            static $custom_smileys, $vb_bbcodes;
	        $options = array();
	        try {
		        $db = JFusionFactory::getDatabase($this->getJname());

		        //parse smilies
		        if (!is_array($custom_smileys)) {
			        $query = 'SELECT title, smilietext, smiliepath FROM #__smilie';
			        $db->setQuery($query);
			        $smilies = $db->loadObjectList();
			        $vburl = $this->params->get('source_url');
			        if (!empty($smilies)) {
				        $custom_smileys = array();
				        foreach ($smilies as $s) {
					        $path = (strpos($s->smiliepath, 'http') !== false) ? $s->smiliepath : $vburl . $s->smiliepath;
					        $custom_smileys[$s->smilietext] = $path;
				        }
			        }
		        }
	        } catch (Exception $e) {
				JFusionFunction::raiseError($e);
	        }

            $options['custom_smileys'] = $custom_smileys;
            $options['parse_smileys'] = true;

            //add custom bbcode rules
            if (!is_array($vb_bbcodes)) {
                $vb_bbcodes = array();
	            try {
		            $query = 'SELECT bbcodetag, bbcodereplacement, twoparams FROM #__bbcode';
		            $db->setQuery($query);
		            $bbcodes = $db->loadObjectList();
		            foreach ($bbcodes as $bb) {
			            $template = $bb->bbcodereplacement;
			            //replace vb content holder with nbbc
			            $template = str_replace('%1$s', '{$_content}', $template);
			            if ($bb->twoparams) {
				            //if using the option tag, replace vb option tag with one nbbc will understand
				            $template = str_replace('%2$s', '{$_default}', $template);
			            }
			            $vb_bbcodes[$bb->bbcodetag] = array( 'mode' => 4, 'template' => $template, 'class' => 'inline', 'allow_in' => array('block', 'inline', 'link', 'list', 'listitem', 'columns', 'image'));
		            }
	            } catch (Exception $e) {
		            JFusionFunction::raiseError($e);
	            }
            }

            if (!empty($vb_bbcodes)) {
                $options['html_patterns'] = $vb_bbcodes;
            }
            if (!empty($params) && $params->get('character_limit', false)) {
                $status['limit_applied'] = 1;
                $options['character_limit'] = $params->get('character_limit');
            }
            $text = JFusionFunction::parseCode($text, 'html', $options);

            //remove the post id from any quote heads
            $text = preg_replace('#<div class="bbcode_quote_head">(.*?);(.*?) (.*?):</div>#' , '<div class="bbcode_quote_head">$1 $3:</div>', $text);
        } elseif ($for == 'activity' || $for == 'search') {
            static $vb_bbcodes_plain;
            $options = array();
	        try {
		        $db = JFusionFactory::getDatabase($this->getJname());

		        //add custom bbcode rules
		        if (!is_array($vb_bbcodes_plain)) {
			        $vb_bbcodes_plain = array();
			        $query = 'SELECT bbcodetag FROM #__bbcode';
			        $db->setQuery($query);
			        $vb_bbcodes_plain = $db->loadColumn();
		        }
	        } catch (Exception $e) {
		        JFusionFunction::raiseError($e);
	        }

            if (!empty($vb_bbcodes_plain)) {
                $options['plain_tags'] = $vb_bbcodes_plain;
            }

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

    /************************************************
    * Functions For JFusion Who's Online Module
    ***********************************************/
    /**
     * Returns a query to find online users
     * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
     *
     * @param int $limit
     *
     * @return string
     */
    function getOnlineUserQuery($limit)
    {
        $limiter = (!empty($limit)) ? 'LIMIT 0,'.$limit : '';
        $name_field = $this->params->get('name_field');
        $query = 'SELECT DISTINCT u.userid, u.username AS username, u.email';
        $query.= (!empty($name_field)) ? ", CASE WHEN f.$name_field IS NULL OR f.$name_field = '' THEN u.username ELSE f.$name_field END AS name FROM #__userfield as f INNER JOIN #__user AS u ON f.userid = u.userid" : ", u.username as name FROM #__user AS u";
        $query.= ' INNER JOIN #__session AS s ON u.userid = s.userid WHERE s.userid != 0 '.$limiter;
        return $query;
    }
    /**
     * Returns number of guests
     *
     * @return int
     */
    function getNumberOnlineGuests()
    {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = 'SELECT COUNT(DISTINCT(host)) FROM #__session WHERE userid = 0';
		    $db->setQuery($query);
		    return $db->loadResult();
	    } catch (Exception $e) {
		    FusionFunction::raiseError($e);
		    return 0;
	    }
    }
    /**
     * Returns number of logged in users
     *
     * @return int
     */
    function getNumberOnlineMembers()
    {
	    try {
	        $db = JFusionFactory::getDatabase($this->getJname());
	        $query = 'SELECT COUNT(DISTINCT(userid)) FROM #__session WHERE userid != 0';
	        $db->setQuery($query);
	        return $db->loadResult();
	    } catch (Exception $e) {
			FusionFunction::raiseError($e);
			return 0;
		}
    }

    /**
     * @param object $jfdata
     *
     * @return void
     */
    function getBuffer(&$jfdata)
    {
        global $vbsefmode, $vbJname, $vbsefenabled, $baseURL, $integratedURL, $hookFile;
        //make sure the curl model is loaded for the hooks file
        if (!class_exists('JFusionCurl')) {
            require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
        }
        //define('_JFUSION_DEBUG',1);
        define('_VBFRAMELESS', 1);

        //frameless integration is only supported for 3.x
        /**
         * @ignore
         * @var $helper JFusionHelper_vbulletin
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $version = $helper->getVersion();
        if ((int) substr($version, 0, 1) > 3) {
            JFusionFunction::raiseWarning(JText::sprintf('VB_FRAMELESS_NOT_SUPPORTED',$version));
        } else {

	        try {
		        //check to make sure the frameless hook is installed
		        $db = JFusionFactory::getDatabase($this->getJname());
		        $q = 'SELECT active FROM #__plugin WHERE hookname = \'init_startup\' AND title = \'JFusion Frameless Integration Plugin\'';
		        $db->setQuery($q);
		        $active = $db->loadResult();
	        } catch (Exception $e) {
		        JFusionFunction::raiseError($e);
		        $active = 0;
	        }

            if ($active != '1') {
                JFusionFunction::raiseWarning(JText::_('VB_FRAMELESS_HOOK_NOT_INSTALLED'));
            } else {
                //have to clear this as it shows up in some text boxes
                unset($q);
                // Get some params
                $params = JFusionFactory::getParams($this->getJname());
                $vbsefmode = $params->get('sefmode', 0);
                $source_path = $params->get('source_path');
                $source_url = $params->get('source_url');
                $baseURL = $jfdata->baseURL;
                $integratedURL = $jfdata->integratedURL;
                $config = JFactory::getConfig();
                $vbsefenabled = $config->get('sef');
                $hookFile = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'hooks.php';
                if ($vbsefmode) {
                    //need to set the base tag as vB JS/ajax requires it to function
                    $document = JFactory::getDocument();
                    $document->setBase($jfdata->baseURL);
                }
                //get the jname to be used in the hook file
                $vbJname = $this->getJname();
                //fix for some instances of vB redirecting
                $redirects = array('ajax.php', 'attachment.php', 'clientscript', 'member.php', 'misc.php', 'picture.php', 'sendmessage.php');
                $custom_files = explode(',', $params->get('redirect_ignore'));
                if (is_array($custom_files)) {
                    foreach ($custom_files as $file) {
                        //add file to the array of files to be redirected to forum
                        if (!empty($file) && strpos($file, '.php') !== false) {
                            $redirects[] = trim($file);
                        }
                    }
                }
                $uri = JURI::getInstance();
                $url = $uri->toString();
                foreach ($redirects as $r) {
                    if (strpos($url, $r) !== false) {
                        if ($r == 'member.php') {
                            //only redirect if using another profile
                            $profile_url = $this->getAlternateProfileURL($url);
                            if (!empty($profile_url)) {
                                $url = $profile_url;
                            } else {
                                continue;
                            }
                        } else {
                            if ($r == 'sendmessage.php') {
                                //only redirect if sending an IM
                                $do = JFactory::getApplication()->input->get('do');
                                if ($do != 'im') {
                                    continue;
                                }
                            }
                            $url = $integratedURL . substr($url, strpos($url, $r));
                        }
                        $mainframe = JFactory::getApplication();
                        $mainframe->redirect($url);
                    }
                }
                //get the filename
                $jfile = JFactory::getApplication()->input->get('jfile');
                if (!$jfile) {
                    //use the default index.php
                    $jfile = 'index.php';
                }
                //combine the path and filename
                if (substr($source_path, -1) == DIRECTORY_SEPARATOR) {
                    $index_file = $source_path . $jfile;
                } else {
                    $index_file = $source_path . DIRECTORY_SEPARATOR . $jfile;
                }
                if (!is_file($index_file)) {
                    JFusionFunction::raiseWarning('The path to the requested does not exist');
                } else {
                    //set the current directory to vBulletin
                    chdir($source_path);
                    // Get the output
                    ob_start();
                    //ahh; basically everything global in vbulletin must be declared here for it to work  ;-{
                    //did not include specific globals in admincp
                    $vbGlobals = array('_CALENDARHOLIDAYS', '_CALENDAROPTIONS', '_TEMPLATEQUERIES', 'ad_location', 'albumids', 'allday', 'altbgclass', 'attachementids', 'badwords', 'bb_view_cache', 'bgclass', 'birthdaycache', 'cache_postids', 'calendarcache', 'calendarids', 'calendarinfo', 'calmod', 'checked', 'checked', 'cmodcache', 'colspan', 'copyrightyear', 'count', 'counters', 'cpnav', 'curforumid', 'curpostid', 'curpostidkey', 'currentdepth', 'customfields', 'datastore_fetch', 'date1', 'date2', 'datenow', 'day', 'days', 'daysprune', 'db', 'defaultselected', 'DEVDEBUG', 'disablesmiliesoption', 'display', 'dotthreads', 'doublemonth', 'doublemonth1', 'doublemonth2', 'eastercache', 'editor_css', 'eventcache', 'eventdate', 'eventids', 'faqbits', 'faqcache', 'faqjumpbits', 'faqlinks', 'faqparent', 'firstnew', 'folder', 'folderid', 'foldernames', 'folderselect', 'footer', 'foruminfo', 'forumjump', 'forumpermissioncache', 'forumperms', 'forumrules', 'forumshown', 'frmjmpsel', 'gobutton', 'goodwords', 'header', 'headinclude', 'holiday', 'html_allowed', 'hybridposts', 'ifaqcache', 'ignore', 'imodcache', 'imodecache', 'inforum', 'infractionids', 'ipclass', 'ipostarray', 'istyles', 'jumpforumbits', 'jumpforumtitle', 'langaugecount', 'laspostinfo', 'lastpostarray', 'limitlower', 'limitupper', 'links', 'message', 'messagearea', 'messagecounters', 'messageid', 'mod', 'month', 'months', 'monthselected', 'morereplies', 'navclass', 'newpm', 'newthreads', 'notifications_menubits', 'notifications_total', 'onload', 'optionselected', 'p', 'p_two_linebreak', 'pagestarttime', 'pagetitle', 'parent_postids', 'parentassoc', 'parentoptions', 'parents', 'pda', 'period', 'permissions', 'permscache', 'perpage', 'phrasegroups', 'phrasequery', 'pictureids', 'pmbox', 'pmids', 'pmpopupurl', 'post', 'postarray', 'postattache', 'postids', 'postinfo', 'postorder', 'postparent', 'postusername', 'previewpost', 'project_forums', 'project_types', 'querystring', 'querytime', 'rate', 'ratescore', 'recurcriteria', 'reminder', 'replyscore', 'searchforumids', 'searchids', 'searchthread', 'searchthreadid', 'searchtype', 'selectedicon', 'selectedone', 'serveroffset', 'show', 'smilebox', 'socialgroups', 'spacer_close', 'spacer_open', 'strikes', 'style', 'stylecount', 'stylevar', 'subscribecounters', 'subscriptioncache', 'template_hook', 'templateassoc', 'tempusagecache', 'threadedmode', 'threadids', 'threadinfo', 'time1', 'time2', 'timediff', 'timenow', 'timerange', 'timezone', 'titlecolor', 'titleonly', 'today', 'usecategories', 'usercache', 'userids', 'vbcollapse', 'vBeditTemplate', 'vboptions', 'vbphrase', 'vbulletin', 'viewscore', 'wol_album', 'wol_attachement', 'wol_calendar', 'wol_event', 'wol_inf', 'wol_pm', 'wol_post', 'wol_search', 'wol_socialgroup', 'wol_thread', 'wol_user', 'year');
                    foreach ($vbGlobals as $g) {
                        //global the variable
                        global $$g;
                    }
                    if (defined('_JFUSION_DEBUG')) {
                        $_SESSION['jfvbdebug'] = array();
                    }
                    try {
                        include_once ($index_file);
                    } catch(Exception $e) {
                        $jfdata->buffer = ob_get_contents();
                        ob_end_clean();
                    }
                    //change the current directory back to Joomla.
                    chdir(JPATH_SITE);
                }
            }
        }
    }

    /**
     * @param object $data
     *
     * @return void
     */
    function parseBody(&$data)
    {
        global $baseURL, $fullURL, $integratedURL, $vbsefmode, $vbsefenabled;
        $baseURL = $data->baseURL;
        $fullURL = $data->fullURL;
        $integratedURL = $data->integratedURL;
        $params = JFusionFactory::getParams($this->getJname());
        $vbsefmode = $params->get('sefmode', 0);
        $config = JFactory::getConfig();
        $vbsefenabled = $config->get('sef');
        //fix for form actions
        //cannot use preg_replace here because it adds unneeded slashes which messes up JS
        $action_search = '#action="(?!http)(.*?)"(.*?)>#mS';

        $data->body = preg_replace_callback($action_search, array(&$this,'fixAction'), $data->body);
        //fix for the rest of the urls
        $url_search = '#href="(?!http)(.*?)"(.*?)>#mSs';
        $data->body = preg_replace_callback($url_search, array(&$this,'fixURL'), $data->body);
        //$url_search = '#<link="(?!http)(.*?)"(.*?)>#mS';
        //$data->body = preg_replace_callback($url_search, array(&$this,'fixURL'),$data->body);
        //convert relative urls in JS links
        $url_search = '#window.location=\'(?!http)(.*?)\'#mS';

        $data->body = preg_replace_callback($url_search, array(&$this,'fixJS'), $data->body);
        //convert relative links from images and js files into absolute links
        $include_search = "#(src=\"|background=\"|url\('|open_window\(\\\\'|window.open\('|window.open\(\"?)(?!http)(.*?)(\\\\',|',|\"|'\)|')#mS";

        $data->body = preg_replace_callback($include_search, array(&$this,'fixInclude'), $data->body);
        //we need to fix the cron.php file
        $data->body = preg_replace('#src="(.*)cron.php(.*)>#mS', 'src="' . $integratedURL . 'cron.php$2>', $data->body);
        //if we have custom register and lost password urls and vBulletin uses an absolute URL, fixURL will not catch it
        $register_url = $params->get('register_url');
        if (!empty($register_url)) {
            $data->body = str_replace($integratedURL . 'register.php', $register_url, $data->body);
        }
        $lostpassword_url = $params->get('lostpassword_url');
        if (!empty($lostpassword_url)) {
            $data->body = str_replace($integratedURL . 'login.php?do=lostpw', $lostpassword_url, $data->body);
        }
        if ($params->get('parseCSS', false)) {
            //we need to wrap the body in a div to prevent some CSS clashes
            $data->body = '<div id="framelessVb">'.$data->body.'</div>';
        }
        if (defined('_JFUSION_DEBUG')) {
            $data->body.= '<pre><code>' . htmlentities(print_r($_SESSION['jfvbdebug'], true)) . '</code></pre>';
            $data->body.= '<pre><code>' . htmlentities(print_r($GLOBALS['vbulletin'], true)) . '</code></pre>';
        }
    }

    /**
     * @param object $data
     *
     * @return void
     */
    function parseHeader(&$data)
    {
        global $baseURL, $fullURL, $integratedURL, $vbsefmode, $vbsefenabled;
        $baseURL = $data->baseURL;
        $fullURL = $data->fullURL;
        $integratedURL = $data->integratedURL;
        $params = JFusionFactory::getParams($this->getJname());
        $vbsefmode = $params->get('sefmode', 0);
        $config = JFactory::getConfig();
        $vbsefenabled = $config->get('sef');
        $js = '<script type="text/javascript">';
        $js .= <<<JS
            var vbSourceURL = '{$integratedURL}';
JS;
        $js .= '</script>';

        //we need to find and change the call to vb yahoo connection file to our own customized one
        //that adds the source url to the ajax calls
        $yuiURL = JFusionFunction::getJoomlaURL() . JFUSION_PLUGIN_DIR_URL . $this->getJname();
        $data->header = preg_replace('#\<script type="text\/javascript" src="(.*?)(connection-min.js|connection.js)\?v=(.*?)"\>#mS', "$js <script type=\"text/javascript\" src=\"$yuiURL/yui/connection/connection.js?v=$3\">", $data->header);
        //convert relative links into absolute links
        $url_search = '#(src="|background="|href="|url\("|url\(\'?)(?!http)(.*?)("\)|\'\)|"?)#mS';
        $data->header = preg_replace_callback($url_search, array(&$this,'fixInclude'), $data->header);
        if ($params->get('parseCSS', false)) {
            $css_search = '#<style type="text/css" id="vbulletin(.*?)">(.*?)</style>#ms';
            $data->header = preg_replace_callback($css_search, array(&$this,'fixCSS'), $data->header);
        }
    }

    /**
     * @return array
     *
     * @return void
     */
    function getPathWay()
    {
	    $pathway = array();
	    try {
		    $mainframe = JFactory::getApplication();
		    $db = JFusionFactory::getDatabase($this->getJname());
		    //let's get the jfile
		    $jfile = JFactory::getApplication()->input->get('jfile');
		    //we are viewing a forum
		    if (JFactory::getApplication()->input->get('f', false) !== false) {
			    $fid = JFactory::getApplication()->input->get('f');
			    $query = 'SELECT title, parentlist, parentid from #__forum WHERE forumid = '.$db->Quote($fid);
			    $db->setQuery($query);
			    $forum = $db->loadObject();
			    if ($forum->parentid != '-1') {
				    $parents = array_reverse(explode(',', $forum->parentlist));
				    foreach ($parents as $p) {
					    if ($p != '-1') {
						    $query = 'SELECT title from #__forum WHERE forumid = '.$p;
						    $db->setQuery($query);
						    $title = $db->loadResult();
						    $crumb = new stdClass();
						    $crumb->title = $title;
						    $crumb->url = 'forumdisplay.php?f='.$p;
						    $pathway[] = $crumb;
					    }
				    }
			    } else {
				    $crumb = new stdClass();
				    $crumb->title = $forum->title;
				    $crumb->url = 'forumdisplay.php?f='.$fid;
				    $pathway[] = $crumb;
			    }
		    } elseif (JFactory::getApplication()->input->get('t', false) !== false) {
			    $tid = JFactory::getApplication()->input->get('t');
			    $query = 'SELECT t.title AS thread, f.title AS forum, f.forumid, f.parentid, f.parentlist FROM #__thread AS t JOIN #__forum AS f ON t.forumid = f.forumid WHERE t.threadid = '.$db->Quote($tid);
			    $db->setQuery($query);
			    $result = $db->loadObject();
			    if ($result->parentid != '-1') {
				    $parents = array_reverse(explode(',', $result->parentlist));
				    foreach ($parents as $p) {
					    if ($p != '-1') {
						    $query = 'SELECT title from #__forum WHERE forumid = '.$p;
						    $db->setQuery($query);
						    $title = $db->loadResult();
						    $crumb = new stdClass();
						    $crumb->title = $title;
						    $crumb->url = 'forumdisplay.php?f='.$p;
						    $pathway[] = $crumb;
					    }
				    }
			    } else {
				    $crumb = new stdClass();
				    $crumb->title = $result->forum;
				    $crumb->url = 'forumdisplay.php?f='.$result->forumid;
				    $pathway[] = $crumb;
			    }
			    $crumb = new stdClass();
			    $crumb->title = $result->thread;
			    $crumb->url = 'showthread.php?t='.$tid;
			    $pathway[] = $crumb;
		    } elseif (JFactory::getApplication()->input->get('p', false) !== false) {
			    $pid = JFactory::getApplication()->input->get('p');
			    $query = 'SELECT t.title AS thread, t.threadid, f.title AS forum, f.forumid, f.parentid, f.parentlist FROM #__thread AS t JOIN #__forum AS f JOIN #__post AS p ON t.forumid = f.forumid AND t.threadid = p.threadid WHERE p.postid = '.$db->Quote($pid);
			    $db->setQuery($query);
			    $result = $db->loadObject();
			    if ($result->parentid != '-1') {
				    $parents = array_reverse(explode(',', $result->parentlist));
				    foreach ($parents as $p) {
					    if ($p != '-1') {
						    $query = 'SELECT title from #__forum WHERE forumid = '.$p;
						    $db->setQuery($query);
						    $title = $db->loadResult();
						    $crumb = new stdClass();
						    $crumb->title = $title;
						    $crumb->url = 'forumdisplay.php?f='.$p;
						    $pathway[] = $crumb;
					    }
				    }
			    } else {
				    $crumb = new stdClass();
				    $crumb->title = $result->forum;
				    $crumb->url = 'forumdisplay.php?f='.$result->forumid;
				    $pathway[] = $crumb;
			    }
			    $crumb = new stdClass();
			    $crumb->title = $result->thread;
			    $crumb->url = 'showthread.php?t='.$result->threadid;
			    $pathway[] = $crumb;
		    } elseif (JFactory::getApplication()->input->get('u', false) !== false) {
			    if ($jfile == 'member.php') {
				    // we are viewing a member's profile
				    $uid = JFactory::getApplication()->input->get('u');
				    $crumb = new stdClass();
				    $crumb->title = 'Members List';
				    $crumb->url = 'memberslist.php';
				    $pathway[] = $crumb;
				    $query = 'SELECT username FROM #__user WHERE userid = '.$db->Quote($uid);
				    $db->setQuery($query);
				    $username = $db->loadResult();
				    $crumb = new stdClass();
				    $crumb->title = $username.'\'s Profile';
				    $crumb->url = 'member.php?u='.$uid;
				    $pathway[] = $crumb;
			    }
		    } elseif ($jfile == 'search.php') {
			    $crumb = new stdClass();
			    $crumb->title = 'Search';
			    $crumb->url = 'search.php';
			    $pathway[] = $crumb;
			    if (JFactory::getApplication()->input->get('do', false) !== false) {
				    $do = JFactory::getApplication()->input->get('do');
				    if ($do == 'getnew') {
					    $crumb = new stdClass();
					    $crumb->title = 'New Posts';
					    $crumb->url = 'search.php?do=getnew';
					    $pathway[] = $crumb;
				    } elseif ($do == 'getdaily') {
					    $crumb = new stdClass();
					    $crumb->title = 'Today\'s Posts';
					    $crumb->url = 'search.php?do=getdaily';
					    $pathway[] = $crumb;
				    }
			    }
		    } elseif ($jfile == 'private.php') {
			    $crumb = new stdClass();
			    $crumb->title = 'User Control Panel';
			    $crumb->url = 'usercp.php';
			    $pathway[] = $crumb;
			    $crumb = new stdClass();
			    $crumb->title = 'Private Messages';
			    $crumb->url = 'private.php';
			    $pathway[] = $crumb;
		    } elseif ($jfile == 'usercp.php') {
			    $crumb = new stdClass();
			    $crumb->title = 'User Control Panel';
			    $crumb->url = 'usercp.php';
			    $pathway[] = $crumb;
		    } elseif ($jfile == 'profile.php') {
			    $crumb = new stdClass();
			    $crumb->title = 'User Control Panel';
			    $crumb->url = 'usercp.php';
			    $pathway[] = $crumb;
			    if (JFactory::getApplication()->input->get('do', false) !== false) {
				    $crumb = new stdClass();
				    $crumb->title = 'Your Profile';
				    $crumb->url = 'profile.php?do=editprofile';
				    $pathway[] = $crumb;
			    }
		    } elseif ($jfile == 'moderation.php') {
			    $crumb = new stdClass();
			    $crumb->title = 'User Control Panel';
			    $crumb->url = 'usercp.php';
			    $pathway[] = $crumb;
			    if (JFactory::getApplication()->input->get('do', false) !== false) {
				    $crumb = new stdClass();
				    $crumb->title = 'Moderator Tasks';
				    $crumb->url = 'moderation.php';
				    $pathway[] = $crumb;
			    }
		    } elseif ($jfile == 'memberlist.php') {
			    $crumb = new stdClass();
			    $crumb->title = 'Members List';
			    $crumb->url = 'memberslist.php';
			    $pathway[] = $crumb;
		    }
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e);
	    }
        return $pathway;
    }

    /**
     * @param $vb_url
     *
     * @return string
     */
    function getAlternateProfileURL($vb_url)
    {
        $params = JFusionFactory::getParams($this->getJname());
        $profile_plugin = $params->get('profile_plugin');
        $url = '';
        if (!empty($profile_plugin) && JFusionFunction::validPlugin($profile_plugin)) {
            $juri = new JURI($vb_url);
            $vbUid = $juri->getVar('u');
            if (!empty($vbUid)) {
                //first get Joomla id for the vBulletin user
                $vbUser = JFusionFactory::getUser($this->getJname());
				$userinfo = $vbUser->getUser($vbUid, 'userid');
                $vb_userlookup = JFusionFunction::lookupUser($this->getJname(), $vbUid, false, $userinfo->username);
                //now get the id of the selected plugin based on Joomla id
                if (!empty($vb_userlookup)) {
                    $profile_userlookup = JFusionFunction::lookupUser($profile_plugin, $vb_userlookup->id);
                    //get the profile link
                    $url = $this->getProfileURL($profile_userlookup->userid);
                }
            }
        }
        return $url;
    }

    /**
     * @return object
     */
    function getSearchQueryColumns()
    {
        $columns = new stdClass();
        $columns->title = 'p.title';
        $columns->text = 'p.pagetext';
        return $columns;
    }

    /**
     * @param object $pluginParam
     *
     * @return string
     */
    function getSearchQuery(&$pluginParam)
    {
        //need to return threadid, postid, title, text, created, section
        $query = 'SELECT p.userid, p.threadid, p.postid, f.forumid, CASE WHEN p.title = "" THEN CONCAT("Re: ",t.title) ELSE p.title END AS title, p.pagetext AS text,
                    FROM_UNIXTIME(p.dateline, "%Y-%m-%d %h:%i:%s") AS created,
                    CONCAT_WS( "/", f.title_clean, t.title ) AS section,
                    t.views AS hits
                    FROM #__post AS p
                    INNER JOIN #__thread AS t ON p.threadid = t.threadid
                    INNER JOIN #__forum AS f on f.forumid = t.forumid';
        return $query;
    }

    /**
     * @param string &$where
     * @param JRegistry &$pluginParam
     * @param string $ordering
     *
     * @return void
     */
    function getSearchCriteria(&$where, &$pluginParam, $ordering)
    {
        $where.= ' AND p.visible = 1 AND f.password = \'\'';

        if ($pluginParam->get('forum_mode', 0)) {
            $forumids = $pluginParam->get('selected_forums', array());
            if (empty($forumids)) {
                $forumids = array(0);
            }
            $where.= ' AND f.forumid IN (' . implode(',', $forumids) . ')';
        }

        //determine how to sort the results which is required for accurate results when a limit is placed
        switch ($ordering) {
             case 'oldest':
                $sort = 'p.dateline ASC';
                break;
            case 'category':
                $sort = 'section ASC';
                break;
            case 'popular':
                $sort = 't.views DESC, p.dateline DESC';
                break;
            case 'alpha':
                $sort = 'title ASC';
                break;
            case 'newest':
            default:
                $sort = 'p.dateline DESC';
                break;
        }
        $where .= ' ORDER BY '.$sort;
    }

    /**
     * @param array &$results
     * @param object &$pluginParam
     *
     * @return void
     */
    function filterSearchResults(&$results, &$pluginParam)
    {
        $plugin = JFusionFactory::getForum($this->getJname());
        $plugin->filterActivityResults($results, 0, 'forumid', true);
    }

    /**
     * @param mixed $post
     *
     * @return string
     */
    function getSearchResultLink($post)
    {
        $forum = JFusionFactory::getForum($this->getJname());
        return $forum->getPostURL($post->threadid, $post->postid);
    }













    /**
     * @param $matches
     * @return string
     */
    function fixAction($matches)
    {
        global $baseURL, $integratedURL, $vbsefmode, $vbsefenabled;

        $url = $matches[1];
        $extra = $matches[2];
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['original'] = $matches[0];
            $debug['url'] = $url;
            $debug['extra'] = $extra;
            $debug['function'] = 'fixAction';
        }

        $url = htmlspecialchars_decode($url);
        $url_details = parse_url($url);
        $url_variables = array();
        parse_str($url_details['query'], $url_variables);
        if (defined('_JFUSION_DEBUG')) {
            $debug['url_variables'] = $url_variables;
        }


        //add which file is being referred to
        if ($url_variables['jfile']) {
            //use the action file that was in jfile variable
            $jfile = $url_variables['jfile'];
            unset($url_variables['jfile']);
        } else {
            //use the action file from the action URL itself
            $jfile = basename($url_details['path']);
        }

        $actionURL = JFusionFunction::routeURL($jfile, JFactory::getApplication()->input->getInt('Itemid'));
        $replacement = 'action=\'' . $actionURL . '\'' . $extra . '>';

        unset($url_variables['option']);
        unset($url_variables['Itemid']);

        //add any other variables
        foreach ($url_variables as $key => $value) {
            $replacement.= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        }

        if (defined('_JFUSION_DEBUG')) {
            $debug['parsed'] = $replacement;
            $_SESSION['jfvbdebug'][] = $debug;
        }
        return $replacement;
    }

    /**
     * @param $matches
     * @return string
     */
    function fixURL($matches)
    {
        global $baseURL, $integratedURL, $vbsefmode, $vbsefenabled;
        $params = JFusionFactory::getParams($this->getJname());
        $plugin_itemid = $params->get('plugin_itemid');

        $url = $matches[1];
        $extra = $matches[2];
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['original'] = $matches[0];
            $debug['url'] = $url;
            $debug['extra'] = $extra;
            $debug['function'] = 'fixURL';
        }
        $uri = JURI::getInstance();
        $currentURL = $uri->toString();
        if ((string)strpos($url, '#') === (string)0 && strlen($url) != 1) {
            $url = (str_replace('&', '&amp;', $currentURL)) . $url;
        }
        //we need to make some exceptions
        //absolute url, already parsed URL, JS function, or jumpto
        if (strpos($url, 'http') !== false || strpos($url, $currentURL) !== false || strpos($url, 'com_jfusion') !== false || ((string)strpos($url, '#') === (string)0 && strlen($url) == 1)) {
            $replacement = 'href="'.$url.'" '.$extra.'>';
            if (defined('_JFUSION_DEBUG')) {
                $debug['parsed'] = $replacement;
            }
            return $replacement;
        }
        //admincp, mocp, archive, printthread.php or attachment.php
        if (strpos($url, $params->get('admincp', 'admincp')) !== false || strpos($url, $params->get('modcp', 'modcp')) !== false || strpos($url, 'archive') !== false || strpos($url, 'printthread.php') !== false || strpos($url, 'attachment.php') !== false) {
            $replacement = 'href="' . $integratedURL . $url . "\" $extra>";
            if (defined('_JFUSION_DEBUG')) {
                $debug['parsed'] = $replacement;
            }
            return $replacement;
        }
        //if the plugin is set as a slave, find the master and replace register/lost password urls
        if (strpos($url, 'register.php') !== false) {
            if (!empty($params)) {
                $register_url = $params->get('register_url');
                if (!empty($register_url)) {
                    $replacement = 'href="' . $register_url . '"' . $extra . '>';
                    if (defined('_JFUSION_DEBUG')) {
                        $debug['parsed'] = $replacement;
                    }
                    return $replacement;
                }
            }
        }
        if (strpos($url, 'login.php?do=lostpw') !== false) {
            if (!empty($params)) {
                $lostpassword_url = $params->get('lostpassword_url');
                if (!empty($lostpassword_url)) {
                    $replacement = 'href="' . $lostpassword_url . '"' . $extra . '>';
                    if (defined('_JFUSION_DEBUG')) {
                        $debug['parsed'] = $replacement;
                    }
                    return $replacement;
                }
            }
        }
        if (strpos($url, 'member.php') !== false) {
            $profile_url = $this->getAlternateProfileURL($url);
            if (!empty($profile_url)) {
                $replacement = 'href="' . $profile_url . '"' . $extra . '>';
                if (defined('_JFUSION_DEBUG')) {
                    $debug['parsed'] = $replacement;
                }
                return $replacement;
            }
        }
        if (empty($vbsefenabled)) {
            //non sef URls
            $url = str_replace('?', '&amp;', $url);
            $url = $baseURL . '&amp;jfile=' . $url;
        } else {
            if ($vbsefmode) {
                $url = JFusionFunction::routeURL($url, $plugin_itemid);
            } else {
                //we can just append both variables
                $url = $baseURL . $url;
            }
        }
        //set the correct url and close the a tag
        $replacement = 'href="' . $url . '"' . $extra . '>';
        if (defined('_JFUSION_DEBUG')) {
            $debug['parsed'] = $replacement;
            $_SESSION['jfvbdebug'][] = $debug;
        }
        return $replacement;
    }

    /**
     * @param $matches
     * @return string
     */
    function fixJS($matches)
    {
        global $baseURL, $integratedURL, $vbsefmode, $vbsefenabled;
        $params = JFusionFactory::getParams($this->getJname());
        $plugin_itemid = $params->get('plugin_itemid');

        $url = $matches[1];
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['original'] = $matches[0];
            $debug['url'] = $url;
            $debug['function'] = 'fixJS';
        }
        if (strpos($url, 'http') !== false) {
            if (defined('_JFUSION_DEBUG')) {
                $debug['parsed'] = 'window.location=\''.$url.'\'';
            }
            return 'window.location=\''.$url.'\'';
        }

        if (empty($vbsefenabled)) {
            //non sef URls
            $url = str_replace('?', '&', $url);
            $url = $baseURL . '&jfile=' . $url;
        } else {
            if ($vbsefmode) {
                $url = JFusionFunction::routeURL($url, $plugin_itemid);
            } else {
                //we can just append both variables
                $url = $baseURL . $url;
            }
        }
        $url = str_replace('&amp;', '&', $url);
        if (defined('_JFUSION_DEBUG')) {
            $debug['parsed'] = 'window.location=\''.$url.'\'';
            $_SESSION['jfvbdebug'][] = $debug;
        }
        return 'window.location=\''.$url.'\'';
    }

    /**
     * @param $matches
     * @return string
     */
    function fixInclude($matches)
    {
        global $integratedURL;
        $pre = $matches[1];
        $url = $matches[2];
        $post = $matches[3];
        $replacement = $pre . $integratedURL . $url . $post;
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['original'] = $matches[0];
            $debug['pre'] = $pre;
            $debug['url'] = $url;
            $debug['post'] = $post;
            $debug['function'] = 'fixInclude';
            $debug['replacement'] = $replacement;
            $_SESSION['jfvbdebug'][] = $debug;
        }
        return $replacement;
    }

    /**
     * @param $matches
     * @return mixed|string
     */
    function fixCSS($matches)
    {
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['function'] = 'fixCSS';
            $debug['original'] = $matches[0];
        }
        $css = $matches[2];
        //remove html comments
        $css = str_replace(array('<!--', '-->'), '', $css);
        //remove PHP comments
        $css = preg_replace('#\/\*(.*?)\*\/#mSs', '', $css);
        //strip newlines
        $css = str_replace("\r\n", '', $css);
        //break up the CSS into styles
        $elements = explode('}', $css);
        //unset the last one as it is empty
        unset($elements[count($elements) - 1]);
        $imports = array();
        //rewrite css
        foreach ($elements as $k => $v) {
            //breakup each element into selectors and properties
            $element = explode('{', $v);
            //breakup the selectors
            $selectors = explode(',', $element[0]);
            foreach ($selectors as $sk => $sv) {
                //add vb frameless container
                if (strpos($sv, '<!--') !== false) {
                    var_dump($sv);
                    die();
                }
                if ($sv == 'body' || $sv == 'html' || $sv == '*') {
                    $selectors[$sk] = $sv.' #framelessVb';
                } elseif (strpos($sv, '@') === 0) {
                    $import = explode(';', $sv);
                    $import = $import[0] . ';';
                    $sv = substr($sv, strlen($import));
                    if ($sv == 'body' || $sv == 'html' || $sv == '*') {
                        $selectors[$sk] = $sv.' #framelessVb';
                    } else {
                        $selectors[$sk] = '#framelessVb '.$sv;
                    }
                    $imports[] = $import;
                } elseif (strpos($sv, 'wysiwyg') === false) {
                    $selectors[$sk] = '#framelessVb '.$sv;
                }
            }
            //reconstruct the element
            $elements[$k] = implode(', ', $selectors) . ' {' . $element[1] . '}';
        }
        //reconstruct the css
        $css = '<style type="text/css" id="vbulletin'.$matches[1].'">'."\n" . implode("\n", $imports) . "\n" . implode("\n", $elements) . "\n".'</style>';
        if (defined('_JFUSION_DEBUG')) {
            $debug['parsed'] = $css;
            $_SESSION['jfvbdebug'] = $debug;
        }
        return $css;
    }
}