<?php

/**
 * file containing public function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion public class for DokuWiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_dokuwiki extends JFusionPublic {

    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'dokuwiki';
    }

    /**
     * @return string
     */
    function getRegistrationURL() {
        return 'doku.php?do=login';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return 'doku.php?do=resendpwd';
    }

    /**
     * @param object $data
     */
    function getBuffer(&$data) {

        // We're going to want a few globals... these are all set later.
        global $INFO, $ACT, $ID, $QUERY, $USERNAME, $CLEAR, $QUIET, $USERINFO, $DOKU_PLUGINS, $PARSER_MODES, $TOC, $EVENT_HANDLER, $AUTH, $IMG, $JUMPTO;
        global $HTTP_RAW_POST_DATA, $RANGE, $HIGH, $MSG, $DATE, $PRE, $TEXT, $SUF, $AUTH_ACL, $QUIET, $SUM, $SRC, $IMG, $NS, $IDX, $REV, $INUSE, $NS, $AUTH_ACL;
        global $UTF8_UPPER_TO_LOWER, $UTF8_LOWER_TO_UPPER, $UTF8_LOWER_ACCENTS, $UTF8_UPPER_ACCENTS, $UTF8_ROMANIZATION, $UTF8_SPECIAL_CHARS, $UTF8_SPECIAL_CHARS2;
        global $auth, $plugin_protected, $plugin_types, $conf, $lang, $argv;
        global $cache_revinfo, $cache_wikifn, $cache_cleanid, $cache_authname, $cache_metadata, $tpl_configloaded;
        global $db_host, $db_name, $db_username, $db_password, $db_prefix, $pun_user, $pun_config;
        global $updateVersion;

        // Get the path
        $params = JFusionFactory::getParams($this->getJname());
        $source_path = $params->get('source_path');

        if (substr($source_path, -1) != DS) {
            $source_path .= DS;
        }

        //setup constants needed by Dokuwiki
        /**
         * @ignore
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $helper->defineConstants();

        $do = JRequest::getVar('do');
        if ($do == 'logout') {
            $mainframe = JFactory::getApplication();
            // logout any joomla users
            $mainframe->logout();
            //clean up session
            $session = JFactory::getSession();
            $session->close();
            $session->restart();
        } else if ($do == 'login') {
            $credentials['username'] = JRequest::getVar('u');
            $credentials["password"] = JRequest::getVar('p');
            if ($credentials['username'] && $credentials['password']) {
                $mainframe = JFactory::getApplication();
                $credentials['username'] = JRequest::getVar('u');
                $credentials['password'] = JRequest::getVar('p');
                $options['remember'] = JRequest::getVar('r');
                //                $options["return"] = 'http://.......';
                //                $options["entry_url"] = 'http://.......';
                // logout any joomla users
                $mainframe->login($credentials, $options);
            }
        }
        $index_file = $source_path . 'doku.php';
        if (JRequest::getVar('jfile') == 'detail.php') $index_file = $source_path . 'lib' . DS . 'exe' . DS . 'detail.php';

        if (JRequest::getVar('media')) JRequest::setVar('media', str_replace(':', '-', JRequest::getVar('media')));
        //loading the JString, to prevent error!
        require_once JPATH_LIBRARIES . DS . 'joomla' . DS . 'utilities' . DS . 'string.php';
        if( !JFusionFunction::isJoomlaVersion('1.6') ) {
        	require_once JPATH_LIBRARIES . DS . 'phputf8' . DS . 'mbstring' . DS . 'strlen.php';
        	require_once JPATH_LIBRARIES . DS . 'phputf8' . DS . 'mbstring' . DS . 'case.php';
        }
        require_once JPATH_LIBRARIES . DS . 'phputf8' . DS . 'mbstring' . DS . 'core.php';

        define('DOKU_INC', $source_path);
        require_once $source_path . 'inc' . DS . 'events.php';
        require_once $source_path . 'inc' . DS . 'init.php';

        require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'hooks.php';
        if (!is_file($index_file)) {
            JError::raiseWarning(500, 'The path to the DokuWiki index file set in the component preferences does not exist');
        } else {
            //set the current directory to dokuwiki
            chdir($source_path);
            // Get the output

            ob_start();
            $rs = include_once ($index_file);
            $data->buffer = ob_get_contents();
            ob_end_clean();

            if (ob_get_contents() !== false) {
                $data->buffer = ob_get_contents().$data->buffer;
                ob_end_clean();
                ob_start();
            }

            //restore the __autoload handeler
            if(JFusionFunction::isJoomlaVersion('1.6')) {
                spl_autoload_register(array('JLoader','load'));
            } else {
                spl_autoload_register('__autoload');
            }

            //change the current directory back to Joomla. 5*60
            chdir(JPATH_SITE);
            // Log an error if we could not include the file
            if (!$rs) {
                JError::raiseWarning(500, 'Could not find DokuWiki in the specified directory');
            }
        }
    }

    /**
     * @param object $data
     */
    function parseBody(&$data) {
        $regex_body = array();
        $replace_body = array();
        $callback_body = array();
        $params = JFusionFactory::getParams($this->getJname());
        $source_url = $params->get('source_url');
        $doku_path = preg_replace('#(\w{0,10}://)(.*?)/(.*?)#is', '$3', $source_url);
        $doku_path = preg_replace('#//+#', '/', "/$doku_path/");
        
        $regex_body[] = '#(href|action|src)=["|\']' . preg_quote($data->integratedURL,'#') . '(.*?)["|\']#mS';
        $replace_body[] = '$1="/$2"';
        $callback_body[] = '';
        
        $regex_body[] = '#(href|action|src)=["|\']' . preg_quote($doku_path,'#') . '(.*?)["|\']#mS';
        $replace_body[] = '$1="/$2"';
        $callback_body[] = '';
        
        $regex_body[] = '#(href)=["|\']/feed.php["|\']#mS';
        $replace_body[] = '$1="' . $data->integratedURL . 'feed.php"';
        $callback_body[] = '';
        
        $regex_body[] = '#href=["|\']/(lib/exe/fetch.php)(.*?)["|\']#mS';
        $replace_body[] = 'href="' . $data->integratedURL . '$1$2"';
        $callback_body[] = '';
        
        $regex_body[] = '#href=["|\']/(_media/)(.*?)["|\']#mS';
        $replace_body[] = 'href="' . $data->integratedURL . '$1$2"';
        $callback_body[] = '';
        
        $regex_body[] = '#href=["|\']/(lib/exe/mediamanager.php)(.*?)["|\']#mS';
        $replace_body[] = 'href="' . $data->integratedURL . '$1$2"';
        $callback_body[] = '';
        
		$regex_body[] = '#(?<=href=["|\'])(?!\w{0,10}://|\w{0,10}:)(.*?)(?=["|\'])#mSi';        
        $replace_body[] = '';//\'href="\'.$this->fixUrl("$1","' . $data->baseURL . '","' . $data->fullURL . '").\'"\'';
        $callback_body[] = 'fixUrl';
        
        $regex_body[] = '#(src)=["|\'][./|/](.*?)["|\']#mS';
        $replace_body[] = '$1="' . $data->integratedURL . '$2"';
        $callback_body[] = '';
        
        foreach ($regex_body as $k => $v) {
        	//check if we need to use callback
        	if(!empty($callback_body[$k])){
			    $data->body = preg_replace_callback($regex_body[$k],array( &$this,$callback_body[$k]), $data->body);             		
        	} else {
        		$data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
        	}
        }  
        
        $this->replaceForm($data);
    }

    /**
     * @param object $data
     */
    function parseHeader(&$data) {
        static $regex_header, $replace_header;
        if (!$regex_header || !$replace_header) {
            // Define our preg arrays
            $regex_header = array();
            $replace_header = array();
            /*
            $params = JFusionFactory::getParams($this->getJname());
            $source_url = $params->get('source_url');

            $doku_path = preg_replace( '#(\w{0,10}://)(.*?)/(.*?)#is'  , '$3' , $source_url );
            $doku_path = preg_replace('#//+#','/',"/$doku_path/");

            $regex_header[]    = '#(href|src)=["|\']'.preg_quote($data->integratedURL,'#').'(.*?)["|\']#mS';
            $replace_header[]    = '$1="/$2"';
            $regex_header[]    = '#(href|src)=["|\']'.preg_quote($doku_path,'#').'(.*?)["|\']#mS';
            $replace_header[]    = '$1="/$2"';

            //convert relative links into absolute links
            $regex_header[]    = '#(href|src)=["|\'][./|/](.*?)["|\']#mS';
            $replace_header[] = '$1="'.$data->integratedURL.'$2"';
            */
        }
        $data->header = preg_replace($regex_header, $replace_header, $data->header);
    }

    /**
     * @param $matches
     * @return mixed|string
     */
    function fixUrl($matches) {
		$q = $matches[1];
		$integratedURL = $this->data->integratedURL;		
		$baseURL = $this->data->baseURL;
		$fullURL = $this->data->fullURL;
		    	
        $q = urldecode($q);
        $q = str_replace(':', ';', $q);
        if (strpos($q, '#') === 0) {
            $url = $fullURL . $q;
        } else {
            $q = ltrim($q, '/');
            if (strpos($q, '_detail/') === 0 || strpos($q, 'lib/exe/detail.php') === 0) {
                if (strpos($q, '_detail/') === 0) {
                    $q = substr($q, strlen('_detail/'));
                } else {
                    $q = substr($q, strlen('lib/exe/detail.php'));
                }
                if (strpos($q, '?') === 0) {
                    $url = 'detail.php' . $q;
                } else {
                    $this->trimUrl($q);
                    $url = 'detail.php?media=' . $q;
                }
            } else if ((strpos($q, '_media/') === 0 || strpos($q, 'lib/exe/fetch.php') === 0)) {
                if (strpos($q, '_media/') === 0) {
                    $q = substr($q, strlen('_media/'));
                } else {
                    $q = substr($q, strlen('lib/exe/fetch.php'));
                }
                if (strpos($q, '?') === 0) {
                    $url = 'fetch.php' . $q;
                } else {
                    $this->trimUrl($q);
                    $url = 'fetch.php?media=' . $q;
                }
            } else if (strpos($q, 'doku.php') === 0) {
                $q = substr($q, strlen('doku.php'));
                if (strpos($q, '?') === 0) {
                    $url = 'doku.php' . $q;
                } else {
                    $this->trimUrl($q);
                    if (strlen($q)) $url = 'doku.php?id=' . $q;
                    else $url = 'doku.php';
                }
            } else {
                $this->trimUrl($q);
                if (strlen($q)) {
                    $url = 'doku.php?id=' . $q;
                } else  {
                    $url = 'doku.php';
                }
            }
            if (substr($baseURL, -1) != '/') {
                //non sef URls
                $url = str_replace('?', '&amp;', $url);
                $url = $baseURL . '&amp;jfile=' . $url;
            } else {
                $params = JFusionFactory::getParams($this->getJname());
                $sefmode = $params->get('sefmode');
                if ($sefmode == 1) {
                    $url = JFusionFunction::routeURL($url, JRequest::getInt('Itemid'));
                } else {
                    //we can just append both variables
                    $url = $baseURL . $url;
                }
            }
        }
        return $url;
    }

    /**
     * @param $url
     */
    function trimUrl(&$url) {
        $url = ltrim($url, '/');
        $order = array('/', '?');
        $replace = array(';', '&');
        $url = str_replace($order, $replace, $url);
    }

    /**
     * @param $data
     */
    function replaceForm(&$data) {
        $pattern = '#<form(.*?)action=["|\'](.\S*?)["|\'](.*?)>(.*?)</form>#mSsi';
        $getData = '';
        if (JRequest::getInt('Itemid')) $getData.= '<input name="Itemid" value="' . JRequest::getInt('Itemid') . '" type="hidden"/>';
        if (JRequest::getVar('option')) $getData.= '<input name="option" value="' . JRequest::getVar('option') . '" type="hidden"/>';
        if (JRequest::getVar('jname')) $getData.= '<input name="jname" value="' . JRequest::getVar('jname') . '" type="hidden"/>';
        if (JRequest::getVar('view')) $getData.= '<input name="view" value="' . JRequest::getVar('view') . '" type="hidden"/>';
        preg_match_all($pattern, $data->body, $links);
        foreach ($links[2] as $key => $value) {
            $method = '#method=["|\']post["|\']#mS';
            $is_get = true;
            if (preg_match($method, $links[1][$key]) || preg_match($method, $links[3][$key])) {
                $is_get = false;
            }
            $matches[1] = $links[2][$key];
            $value = $this->fixUrl($matches);
            if ($is_get && substr($value, -1) != DS) $links[4][$key] = $getData . $links[4][$key];
            $data->body = str_replace($links[0][$key], '<form' . $links[1][$key] . 'action="' . $value . '"' . $links[3][$key] . '>' . $links[4][$key] . '</form>', $data->body);
        }
    }
    /************************************************
    * For JFusion Search Plugin
    ***********************************************/
    /**
     * Retrieves the search results to be displayed.  Placed here so that plugins that do not use the database can retrieve and return results
     * @param &$text string text to be searched
     * @param &$phrase string how the search should be performed exact, all, or any
     * @param &$pluginParam custom plugin parameters in search.xml
     * @param $itemid what menu item to use when creating the URL
     *
     * @return array of results as objects
     *
     * Each result should include:
     * $result->title = title of the post/article
     * $result->section = (optional) section of  the post/article (shows underneath the title; example is Forum Name / Thread Name)
     * $result->text = text body of the post/article
     * $result->href = link to the content (without this, joomla will not display a title)
     * $result->browsernav = 1 opens link in a new window, 2 opens in the same window
     * $result->created = (optional) date when the content was created
     */
    function getSearchResults(&$text, &$phrase, &$pluginParam, $itemid) {
        $params = JFusionFactory::getParams($this->getJname());

        require_once 'doku_search.php';
        $highlights = array();
        $search = new DokuWikiSearch($this->getJname());
        $results = $search->ft_pageSearch($text, $highlights);
        //pass results back to the plugin in case they need to be filtered
        $this->filterSearchResults($results);
        $rows = array();
        $pos = 0;

        foreach ($results as $key => $index) {
            $rows[$pos]->title = JText::_($key);
            $rows[$pos]->text = $search->getPage($key);
            $rows[$pos]->created = $search->getPageModifiedDateTime($key);
            //dokuwiki doesn't track hits
            $rows[$pos]->hits = 0;
            $rows[$pos]->href = JFusionFunction::routeURL(str_replace(':', ';', $this->getSearchResultLink($key)), $itemid);
            $rows[$pos]->section = JText::_($key);
            $pos++;
        }
        return $rows;
    }

    /**
     * @param string &$results
     */
    function filterSearchResults(&$results) {
    }

    /**
     * @param mixed $post
     * @return string
     */
    function getSearchResultLink($post) {
        return "doku.php?id=" . $post;
    }

    /**
     * @return array
     */
    function getPathWay() {
        $pathway = array();
        if (JRequest::getVar('id')) {
            $bread = explode(';', JRequest::getVar('id'));
            $url = '';
            $i = 0;
            foreach ($bread as $key) {
                if ($url) {
                    $url.= ';' . $key;
                } else {
                    $url = $key;
                }
                $path = new stdClass();
                $path->title = $key;
                $path->url = 'doku.php?id=' . $url;
                $pathway[] = $path;
            }
            if (JRequest::getVar('media') || JRequest::getVar('do')) {
                if (JRequest::getVar('media')) {
                    $add = JRequest::getVar('media');
                } else {
                    $add = JRequest::getVar('do');
                }
                $pathway[count($pathway) - 1]->title = $pathway[count($pathway) - 1]->title . ' ( ' . $add . ' )';
            }
        }
        return $pathway;
    }
}
