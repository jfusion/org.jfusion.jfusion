<?php namespace JFusion\Plugins\vbulletin;

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
use Exception;
use JFactory;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_Front;
use JFusionFunction;
use stdClass;

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
class Front extends Plugin_Front
{
	/**
	 * @var Helper
	 */
	var $helper;

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
        $version = $this->helper->getVersion();
        if ((int) substr($version, 0, 1) > 3) {
            Framework::raiseWarning(Text::sprintf('VB_FRAMELESS_NOT_SUPPORTED', $version), $this->getJname());
        } else {

	        try {
		        //check to make sure the frameless hook is installed
		        $db = Factory::getDatabase($this->getJname());

		        $query = $db->getQuery(true)
			        ->select('active')
			        ->from('#__plugin')
			        ->where('hookname = ' . $db->quote('init_startup'))
			        ->where('title = ' . $db->quote('JFusion Frameless Integration Plugin'));

		        $db->setQuery($query);
		        $active = $db->loadResult();
	        } catch (Exception $e) {
		        Framework::raiseError($e, $this->getJname());
		        $active = 0;
	        }

            if ($active != '1') {
                Framework::raiseWarning(Text::_('VB_FRAMELESS_HOOK_NOT_INSTALLED'), $this->getJname());
            } else {
                //have to clear this as it shows up in some text boxes
                unset($q);
                // Get some params
                $vbsefmode = $this->params->get('sefmode', 0);
                $source_path = $this->params->get('source_path');
                $baseURL = $jfdata->baseURL;
                $integratedURL = $jfdata->integratedURL;
                $config = Factory::getConfig();
                $vbsefenabled = $config->get('sef');

	            $hooks = Factory::getPlayform($jfdata->platform, $this->getJname())->hasFile('hooks.php');
	            if ($hooks) {
		            $hookFile = $hooks;
	            }
                if ($vbsefmode) {
                    //need to set the base tag as vB JS/ajax requires it to function
                    $document = JFactory::getDocument();
                    $document->setBase($jfdata->baseURL);
                }
                //get the jname to be used in the hook file
                $vbJname = $this->getJname();
                //fix for some instances of vB redirecting
                $redirects = array('ajax.php', 'attachment.php', 'clientscript', 'member.php', 'misc.php', 'picture.php', 'sendmessage.php');
                $custom_files = explode(',', $this->params->get('redirect_ignore'));
                if (is_array($custom_files)) {
                    foreach ($custom_files as $file) {
                        //add file to the array of files to be redirected to forum
                        if (!empty($file) && strpos($file, '.php') !== false) {
                            $redirects[] = trim($file);
                        }
                    }
                }
                $uri = JUri::getInstance();
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
                                $do = Factory::getApplication()->input->get('do');
                                if ($do != 'im') {
                                    continue;
                                }
                            }
                            $url = $integratedURL . substr($url, strpos($url, $r));
                        }
                        $mainframe = Factory::getApplication();
                        $mainframe->redirect($url);
                    }
                }
                //get the filename
                $jfile = Factory::getApplication()->input->get('jfile');
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
                    Framework::raiseWarning('The path to the requested does not exist', $this->getJname());
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
                    } catch (Exception $e) {
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
        $vbsefmode = $this->params->get('sefmode', 0);
        $config = Factory::getConfig();
        $vbsefenabled = $config->get('sef');
        //fix for form actions
        //cannot use preg_replace here because it adds unneeded slashes which messes up JS
        $action_search = '#action="(?!http)(.*?)"(.*?)>#mS';

        $data->body = preg_replace_callback($action_search, array(&$this, 'fixAction'), $data->body);
        //fix for the rest of the urls
        $url_search = '#href="(?!http)(.*?)"(.*?)>#mSs';
        $data->body = preg_replace_callback($url_search, array(&$this, 'fixURL'), $data->body);
        //$url_search = '#<link="(?!http)(.*?)"(.*?)>#mS';
        //$data->body = preg_replace_callback($url_search, array(&$this, 'fixURL'), $data->body);
        //convert relative urls in JS links
        $url_search = '#window.location=\'(?!http)(.*?)\'#mS';

        $data->body = preg_replace_callback($url_search, array(&$this, 'fixJS'), $data->body);
        //convert relative links from images and js files into absolute links
        $include_search = "#(src=\"|background=\"|url\('|open_window\(\\\\'|window.open\('|window.open\(\"?)(?!http)(.*?)(\\\\',|',|\"|'\)|')#mS";

        $data->body = preg_replace_callback($include_search, array(&$this, 'fixInclude'), $data->body);
        //we need to fix the cron.php file
        $data->body = preg_replace('#src="(.*)cron.php(.*)>#mS', 'src="' . $integratedURL . 'cron.php$2>', $data->body);
        //if we have custom register and lost password urls and vBulletin uses an absolute URL, fixURL will not catch it
        $register_url = $this->params->get('register_url');
        if (!empty($register_url)) {
            $data->body = str_replace($integratedURL . 'register.php', $register_url, $data->body);
        }
        $lostpassword_url = $this->params->get('lostpassword_url');
        if (!empty($lostpassword_url)) {
            $data->body = str_replace($integratedURL . 'login.php?do=lostpw', $lostpassword_url, $data->body);
        }
        if ($this->params->get('parseCSS', false)) {
            //we need to wrap the body in a div to prevent some CSS clashes
            $data->body = '<div id="framelessVb">' . $data->body . '</div>';
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
        $vbsefmode = $this->params->get('sefmode', 0);
        $config = Factory::getConfig();
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
        $data->header = preg_replace_callback($url_search, array(&$this, 'fixInclude'), $data->header);
        if ($this->params->get('parseCSS', false)) {
            $css_search = '#<style type="text/css" id="vbulletin(.*?)">(.*?)</style>#ms';
            $data->header = preg_replace_callback($css_search, array(&$this, 'fixCSS'), $data->header);
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
		    $db = Factory::getDatabase($this->getJname());
		    //let's get the jfile
		    $mainframe = Factory::getApplication();
		    $jfile = $mainframe->input->get('jfile');
		    //we are viewing a forum
		    if ($mainframe->input->get('f', false) !== false) {
			    $fid = $mainframe->input->get('f');

			    $query = $db->getQuery(true)
				    ->select('title, parentlist, parentid')
				    ->from('#__forum')
				    ->where('forumid = ' . $db->quote($fid));

			    $db->setQuery($query);
			    $forum = $db->loadObject();
			    if ($forum->parentid != '-1') {
				    $parents = array_reverse(explode(',', $forum->parentlist));
				    foreach ($parents as $p) {
					    if ($p != '-1') {
						    $query = $db->getQuery(true)
							    ->select('title')
							    ->from('#__forum')
							    ->where('forumid = ' . $p);

						    $db->setQuery($query);
						    $title = $db->loadResult();
						    $crumb = new stdClass();
						    $crumb->title = $title;
						    $crumb->url = 'forumdisplay.php?f=' . $p;
						    $pathway[] = $crumb;
					    }
				    }
			    } else {
				    $crumb = new stdClass();
				    $crumb->title = $forum->title;
				    $crumb->url = 'forumdisplay.php?f=' . $fid;
				    $pathway[] = $crumb;
			    }
		    } elseif ($mainframe->input->get('t', false) !== false) {
			    $tid = $mainframe->input->get('t');

			    $query = $db->getQuery(true)
				    ->select('t.title AS thread, f.title AS forum, f.forumid, f.parentid, f.parentlist')
				    ->from('#__thread AS t')
			        ->join('', '#__forum AS f ON t.forumid = f.forumid')
				    ->where('t.threadid = ' . $db->quote($tid));

			    $db->setQuery($query);
			    $result = $db->loadObject();
			    if ($result->parentid != '-1') {
				    $parents = array_reverse(explode(',', $result->parentlist));
				    foreach ($parents as $p) {
					    if ($p != '-1') {
						    $query = $db->getQuery(true)
							    ->select('title')
							    ->from('#__forum')
							    ->where('forumid = ' . $p);

						    $db->setQuery($query);
						    $title = $db->loadResult();
						    $crumb = new stdClass();
						    $crumb->title = $title;
						    $crumb->url = 'forumdisplay.php?f=' . $p;
						    $pathway[] = $crumb;
					    }
				    }
			    } else {
				    $crumb = new stdClass();
				    $crumb->title = $result->forum;
				    $crumb->url = 'forumdisplay.php?f=' . $result->forumid;
				    $pathway[] = $crumb;
			    }
			    $crumb = new stdClass();
			    $crumb->title = $result->thread;
			    $crumb->url = 'showthread.php?t=' . $tid;
			    $pathway[] = $crumb;
		    } elseif ($mainframe->input->get('p', false) !== false) {
			    $pid = $mainframe->input->get('p');

			    $query = $db->getQuery(true)
				    ->select('t.title AS thread, t.threadid, f.title AS forum, f.forumid, f.parentid, f.parentlist')
				    ->from('#__thread AS t')
			        ->join('', '#__post AS p ON t.forumid = f.forumid AND t.threadid = p.threadid')
				    ->where('p.postid = ' . $db->quote($pid));

			    $db->setQuery($query);
			    $result = $db->loadObject();
			    if ($result->parentid != '-1') {
				    $parents = array_reverse(explode(',', $result->parentlist));
				    foreach ($parents as $p) {
					    if ($p != '-1') {
						    $query = $db->getQuery(true)
							    ->select('title')
							    ->from('#__forum')
							    ->where('forumid = ' . $p);

						    $db->setQuery($query);
						    $title = $db->loadResult();
						    $crumb = new stdClass();
						    $crumb->title = $title;
						    $crumb->url = 'forumdisplay.php?f=' . $p;
						    $pathway[] = $crumb;
					    }
				    }
			    } else {
				    $crumb = new stdClass();
				    $crumb->title = $result->forum;
				    $crumb->url = 'forumdisplay.php?f=' . $result->forumid;
				    $pathway[] = $crumb;
			    }
			    $crumb = new stdClass();
			    $crumb->title = $result->thread;
			    $crumb->url = 'showthread.php?t=' . $result->threadid;
			    $pathway[] = $crumb;
		    } elseif ($mainframe->input->get('u', false) !== false) {
			    if ($jfile == 'member.php') {
				    // we are viewing a member's profile
				    $uid = $mainframe->input->get('u');
				    $crumb = new stdClass();
				    $crumb->title = 'Members List';
				    $crumb->url = 'memberslist.php';
				    $pathway[] = $crumb;

				    $query = $db->getQuery(true)
					    ->select('username')
					    ->from('#__user')
					    ->where('userid = ' . $db->quote($uid));

				    $db->setQuery($query);
				    $username = $db->loadResult();
				    $crumb = new stdClass();
				    $crumb->title = $username . '\'s Profile';
				    $crumb->url = 'member.php?u=' . $uid;
				    $pathway[] = $crumb;
			    }
		    } elseif ($jfile == 'search.php') {
			    $crumb = new stdClass();
			    $crumb->title = 'Search';
			    $crumb->url = 'search.php';
			    $pathway[] = $crumb;
			    if ($mainframe->input->get('do', false) !== false) {
				    $do = $mainframe->input->get('do');
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
			    if ($mainframe->input->get('do', false) !== false) {
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
			    if ($mainframe->input->get('do', false) !== false) {
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
			Framework::raiseError($e, $this->getJname());
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
        $profile_plugin = $this->params->get('profile_plugin');
        $url = '';
	    try {
		    if (!empty($profile_plugin)) {
			    $user = Factory::getUser($profile_plugin);
			    if ($user->isConfigured()) {
				    $juri = new JUri($vb_url);
				    $vbUid = $juri->getVar('u');
				    if (!empty($vbUid)) {
					    //first get Joomla id for the vBulletin user
					    $vbUser = Factory::getUser($this->getJname());
					    $userinfo = $vbUser->getUser($vbUid, 'userid');

					    $PluginUser = Factory::getUser($profile_plugin);
					    $userlookup = $PluginUser->lookupUser($userinfo);
					    //now get the id of the selected plugin based on Joomla id
					    if ($userlookup) {
						    //get the profile link
						    /**
						     * @ignore
						     * @var $platform \JFusion\Plugin\Platform\Joomla
						     */
						    $platform = Factory::getPlayform('Joomla', $profile_plugin);
						    $url = $platform->getProfileURL($userlookup->userid);
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    Framework::raiseError($e, $this->getJname());
	    }
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

        $actionURL = Factory::getApplication()->routeURL($jfile, Factory::getApplication()->input->getInt('Itemid'));
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
        $plugin_itemid = $this->params->get('plugin_itemid');

        $url = $matches[1];
        $extra = $matches[2];
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['original'] = $matches[0];
            $debug['url'] = $url;
            $debug['extra'] = $extra;
            $debug['function'] = 'fixURL';
        }
        $uri = JUri::getInstance();
        $currentURL = $uri->toString();
        if ((string)strpos($url, '#') === (string)0 && strlen($url) != 1) {
            $url = (str_replace('&', '&amp;', $currentURL)) . $url;
        }
        //we need to make some exceptions
        //absolute url, already parsed URL, JS function, or jumpto
        if (strpos($url, 'http') !== false || strpos($url, $currentURL) !== false || strpos($url, 'com_jfusion') !== false || ((string)strpos($url, '#') === (string)0 && strlen($url) == 1)) {
            $replacement = 'href="' . $url . '" ' . $extra . '>';
            if (defined('_JFUSION_DEBUG')) {
                $debug['parsed'] = $replacement;
            }
            return $replacement;
        }
        //admincp, mocp, archive, printthread.php or attachment.php
        if (strpos($url, $this->params->get('admincp', 'admincp')) !== false || strpos($url, $this->params->get('modcp', 'modcp')) !== false || strpos($url, 'archive') !== false || strpos($url, 'printthread.php') !== false || strpos($url, 'attachment.php') !== false) {
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
                $url = Factory::getApplication()->routeURL($url, $plugin_itemid);
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
        global $baseURL, $vbsefmode, $vbsefenabled;
        $plugin_itemid = $this->params->get('plugin_itemid');

        $url = $matches[1];
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['original'] = $matches[0];
            $debug['url'] = $url;
            $debug['function'] = 'fixJS';
        }
        if (strpos($url, 'http') !== false) {
            if (defined('_JFUSION_DEBUG')) {
                $debug['parsed'] = 'window.location=\'' . $url . '\'';
            }
            return 'window.location=\'' . $url . '\'';
        }

        if (empty($vbsefenabled)) {
            //non sef URls
            $url = str_replace('?', '&', $url);
            $url = $baseURL . '&jfile=' . $url;
        } else {
            if ($vbsefmode) {
                $url = Factory::getApplication()->routeURL($url, $plugin_itemid);
            } else {
                //we can just append both variables
                $url = $baseURL . $url;
            }
        }
        $url = str_replace('&amp;', '&', $url);
        if (defined('_JFUSION_DEBUG')) {
            $debug['parsed'] = 'window.location=\'' . $url . '\'';
            $_SESSION['jfvbdebug'][] = $debug;
        }
        return 'window.location=\'' . $url . '\'';
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
                    $selectors[$sk] = $sv . ' #framelessVb';
                } elseif (strpos($sv, '@') === 0) {
                    $import = explode(';', $sv);
                    $import = $import[0] . ';';
                    $sv = substr($sv, strlen($import));
                    if ($sv == 'body' || $sv == 'html' || $sv == '*') {
                        $selectors[$sk] = $sv . ' #framelessVb';
                    } else {
                        $selectors[$sk] = '#framelessVb ' . $sv;
                    }
                    $imports[] = $import;
                } elseif (strpos($sv, 'wysiwyg') === false) {
                    $selectors[$sk] = '#framelessVb ' . $sv;
                }
            }
            //reconstruct the element
            $elements[$k] = implode(', ', $selectors) . ' {' . $element[1] . '}';
        }
        //reconstruct the css
        $css = '<style type="text/css" id="vbulletin' . $matches[1] . '">' . "\n" . implode("\n", $imports) . "\n" . implode("\n", $elements) . "\n" . '</style>';
        if (defined('_JFUSION_DEBUG')) {
            $debug['parsed'] = $css;
            $_SESSION['jfvbdebug'] = $debug;
        }
        return $css;
    }
}