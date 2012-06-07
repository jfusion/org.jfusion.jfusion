<?php
  
/**
 * Abstract public class for JFusion
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Abstract interface for all JFusion functions that are accessed through the Joomla front-end
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionPublic
{
    var $data;
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return '';
    }

    /**
     * gets the visual html output from the plugin
     *
     * @param object &$data object containing all frameless data
     */
    function getBuffer(&$data)
    {
    }

    /**
     * function that parses the HTML body and fixes up URLs and form actions
     *
     * @param object &$data object containing all frameless data
     */
    function parseBody(&$data)
    {
    }

    /**
     * function that parses the HTML header and fixes up URLs
     *
     * @param object &$data object containing all frameless data
     */
    function parseHeader(&$data)
    {
    }

    /**
     * Parsers the buffer recieved from getBuffer into header and body
     * @param &$data
     */
    function parseBuffer(&$data) {
    	$pattern = '#<head[^>]*>(.*?)<\/head>.*?<body([^>]*)>(.*)<\/body>#si';
    	$temp = array();
    
    	preg_match($pattern, $data->buffer, $temp);
    	if(!empty($temp[1])) $data->header = $temp[1];
    	if(!empty($temp[3])) $data->body = $temp[3];
    
    	$pattern = '#onload=["]([^"]*)#si';
    	if(!empty($temp[2])){
    		if(preg_match($pattern, $temp[2], $temp)){
    			$js = '<script language="JavaScript" type="text/javascript">
    			if(window.addEventListener) { // Standard
    			window.addEventListener(\'load\', function(){'.$temp[1].'}, false);
    		} else if(window.attachEvent) { // IE
    		window.attachEvent(\'onload\', function(){'.$temp[1].'});
    		}
    		</script>';
    			$data->header .= $js;
    		}
    	}
    	unset($temp);
    }    
    
    /**
     * function that parses the HTML and fix the css
     *
     * @param object &$data data to parse
     * @param string &$html data to parse
     * @param bool $infile_only parse only infile (body)
     */
    function parseCSS(&$data,&$html,$infile_only=false)
    {
    	$jname = $this->getJname();
    	$param =& JFusionFactory::getParams ( $this->getJname() );
    
    	if (empty($jname)) {
    		$jname = JRequest::getVar ( 'Itemid' );
    	}
    
    	$document = JFactory::getDocument();
    
    	$sourcepath = JPATH_SITE . DS . 'components' . DS . 'com_jfusion' . DS . 'css' . DS .$jname . DS;
    	$urlpath = 'components/com_jfusion/css/'.$jname.'/';
    
    	jimport('joomla.filesystem.file');
    	jimport('joomla.filesystem.folder');
    
    	JFolder::create($sourcepath.'infile');
    	if (!$infile_only) {
    		//Outputs: apearpearle pear
    		$urlPattern = array('http://', 'https://', '.css', '\\', '/', '|', '*', ':', ';', '?', '"', '<', '>', '=', '&');
    		$urlReplace = array('', '', '', '', '-', '', '', '', '', '', '', '', '', ',', '_');
    		if ($data->parse_css) {
    			if (preg_match_all( '#<link(.*?type=[\'|"]text\/css[\'|"][^>]*)>#Si', $html, $css )) {
    
    				$regex_header = array ();
    				$replace_header = array ();
    				require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'parsers' . DS . 'css.php');
    
    				jimport('joomla.filesystem.file');
    				foreach ($css[1] as $key => $values) {
    					if( preg_match( '#href=[\'|"](.*?)[\'|"]#Si', $values, $cssUrl )) {
    						$cssUrlRaw = $cssUrl[1];
    						$cssUrl = urldecode(htmlspecialchars_decode($cssUrl[1]));
    
    						if ( preg_match( '#media=[\'|"](.*?)[\'|"]#Si', $values, $cssMedia ) ) {
    							$cssMedia = $cssMedia[1];
    						} else {
    							$cssMedia = '';
    						}
    						$filename = str_replace($urlPattern, $urlReplace, $cssUrl).'.css';
    						$filenamesource = $sourcepath.$filename;
    
    						if ( !JFile::exists($filenamesource) ) {
    							$cssparser = new cssparser('#jfusionframeless');
    							$result = $cssparser->ParseUrl($cssUrlRaw);
    							if ($result !== false ) {
    								$content = $cssparser->GetCSS();
    								JFile::write($filenamesource, $content);
    							}
    						}
    
    						if ( JFile::exists($filenamesource) ) {
    							$html = str_replace($cssUrlRaw  , $urlpath.$filename  , $html );
    						}
    					}
    				}
    			}
    		}
    	}
    	if ($data->parse_infile_css) {
    		if (preg_match_all( '#<style.*?type=[\'|"]text/css[\'|"].*?>(.*?)</style>#Sims', $html, $css )) {
    			require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'parsers' . DS . 'css.php');
    			foreach ($css[1] as $key => $values) {
    				$filename = md5($values).'.css';
    				$filenamesource = $sourcepath.'infile'.DS.$filename;
    
    				if ( preg_match( '#media=[\'|"](.*?)[\'|"]#Si', $css[0][$key], $cssMedia ) ) {
    					$cssMedia = $cssMedia[1];
    				} else {
    					$cssMedia = '';
    				}
    
    				if ( !JFile::exists($filenamesource) ) {
    					$cssparser = new cssparser('#jfusionframeless');
    					$cssparser->setUrl($data->integratedURL);
    					$cssparser->ParseStr($values);
    					$content = $cssparser->GetCSS();
    					JFile::write($filenamesource, $content);
    				}
    				if ( JFile::exists($filenamesource) ) {
    					$document->addStyleSheet($urlpath.'infile/'.$filename,'text/css',$cssMedia);
    				}
    			}
    			$html = preg_replace ( '#<style.*?type=[\'|"]text/css[\'|"].*?>(.*?)</style>#Sims', '', $html );
    		}
    	}
    }
    
    /**
     * extends JFusion's parseRoute function to reconstruct the SEF URL
     *
     * @param array &$vars vars already parsed by JFusion's router.php file
     *
     */
    function parseRoute(&$vars)
    {
    }

    /**
     * extends JFusion's buildRoute function to build the SEF URL
     *
     * @param array &$segments query already prepared by JFusion's router.php file
     */
    function buildRoute(&$segments)
    {
    }

    /**
     * Returns the registration URL for the integrated software
     *
     * @return string registration URL
     */
    function getRegistrationURL()
    {
        return '';
    }

    /**
     * Returns the lost password URL for the integrated software
     *
     * @return string lost password URL
     */
    function getLostPasswordURL()
    {
        return '';
    }

    /**
     * Returns the lost username URL for the integrated software
     *
     * @return string lost username URL
     */
    function getLostUsernameURL()
    {
        return '';
    }

    /**
     * Returns Array of stdClass title / url
     * Array of stdClass with title and url assigned.
     *
     * @return array Db columns assigned to title and url links for pathway
     */
    function getPathWay()
    {
        return array();
    }

    /**
     * Prepares text for various areas
     *
     * @param string &$text             Text to be modified
     * @param string $for              (optional) Determines how the text should be prepared.
     *                                  Options for $for as passed in by JFusion's plugins and modules are:
     *                                  joomla (to be displayed in an article; used by discussion bot)
     *                                  forum (to be published in a thread or post; used by discussion bot)
     *                                  activity (displayed in activity module; used by the activity module)
     *                                  search (displayed as search results; used by search plugin)
     * @param JParameter $params        (optional) Joomla parameter object passed in by JFusion's module/plugin
     * @param mixed $object             (optional) Object with information for the specific element the text is from
     *
     * @return array  $status           Information passed back to calling script such as limit_applied
     */
    function prepareText(&$text, $for = '', $params = null, $object = '')
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
        } elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
            $options = array();
            if (!empty($params) && $params->get('character_limit', false)) {
                $status['limit_applied'] = 1;
                $options['character_limit'] = $params->get('character_limit');
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
     * Parses custom BBCode defined in $this->prepareText() and called by the nbbc parser via JFusionFunction::parseCode()
     *
     * @param mixed $bbcode
     * @param int $action
     * @param string $name
     * @param string $default
     * @param mixed $params
     * @param string $content
     *
     * @return mixed bbcode converted to html
     */
    function parseCustomBBCode($bbcode, $action, $name, $default, $params, $content)
    {
        if ($action == 1) {
            $return = true;
        } else {
            $return = $content;
            switch ($name) {
                case 'size':
                    $return = "<span style=\"font-size:$default\">$content</span>";
                    break;
                case 'glow':
                    $temp = explode(',', $default);
                    $color = (!empty($temp[0])) ? $temp[0] : 'red';
                    $return = "<span style=\"background-color:$color\">$content</span>";
                    break;
                case 'shadow':
                    $temp = explode(',', $default);
                    $color = (!empty($temp[0])) ? $temp[0] : '#6374AB';
                    $dir = (!empty($temp[1])) ? $temp[1] : 'left';
                    $x = ($dir == 'left') ? '-0.2em' : '0.2em';
                    $return = "<span style=\"text-shadow: $color $x 0.1em 0.2em;\">$content</span>";
                    break;
                case 'move':
                    $return = "<marquee>$content</marquee>";
                    break;
                case 'pre':
                    $return = "<pre>$content</pre>";
                    break;
                case 'hr':
                    return '<hr>';
                    break;
                case 'flash':
                    $temp = explode(',', $default);
                    $width = (!empty($temp[0])) ? $temp[0] : '200';
                    $height = (!empty($temp[1])) ? $temp[1] : '200';
                    $return = "<object classid='clsid:D27CDB6E-AE6D-11CF-96B8-444553540000' codebase='http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0' width='$width' height='$height'><param name='movie' value='$content' /><param name='play' value='false' /><param name='loop' value='false' /><param name='quality' value='high' /><param name='allowScriptAccess' value='never' /><param name='allowNetworking' value='internal' /><embed src='$content' type='application/x-shockwave-flash' pluginspage='http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash' width='$width' height='$height' play='false' loop='false' quality='high' allowscriptaccess='never' allownetworking='internal'></embed></object>";
                    break;
                case 'ftp':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = "<a href='$content'>$default</a>";
                    break;
                case 'table':
                    $return = "<table>$content</table>";
                    break;
                case 'tr':
                    $return = "<tr>$content</tr>";
                    break;
                case 'td':
                    $return = "<td>$content</td>";
                    break;
                case 'tt';
                    $return = "<tt>$content</tt>";
                    break;
                case 'o':
                case 'O':
                case '0':
                    $return = "<li type='circle'>$content</li>";
                    break;
                case '*':
                case '@':
                    $return = "<li type='disc'>$content</li>";
                    break;
                case '+':
                case 'x':
                case '#':
                    $return = "<li type='square'>$content</li>";
                    break;
                case 'abbr':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = "<abbr title='$default'>$content</abbr>";
                    break;
                case 'anchor':
                    if (!empty($default)) {
                        $return = "<span id='$default'>$content</span>";
                    } else {
                        $return = $content;
                    }
                    break;
                case 'black':
                case 'blue':
                case 'green':
                case 'red':
                case 'white':
                    $return = "<span style='color: $name;'>$content</span>";
                    break;
                case 'iurl':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = "<a href='" . htmlspecialchars($default) . "' class='bbcode_url' target='_self'>$content</a>";
                    break;
                case 'html':
                case 'nobbc':
                case 'php':
                    $return = $content;
                    break;
                case 'ltr':
                    $return = "<div style='text-align: left;' dir='$name'>$content</div>";
                    break;
                case 'rtl':
                    $return = "<div style='text-align: right;' dir='$name'>$content</div>";
                    break;
                case 'me':
                    $return = "<div style='color: red;'>* $default $content</div>";
                    break;
                case 'time':
                    $return = date("Y-m-d H:i", $content);
                    break;
                default:
                    break;
            }
        }
        return $return;
    }

    /************************************************
    * Functions For JFusion Search Plugin
    ***********************************************/

    /**
     * Retrieves the search results to be displayed.  Placed here so that plugins that do not use the database can retrieve and return results
     * Each result should include:
     * $result->title = title of the post/article
     * $result->section = (optional) section of  the post/article (shows underneath the title; example is Forum Name / Thread Name)
     * $result->text = text body of the post/article
     * $result->href = link to the content (without this, joomla will not display a title)
     * $result->browsernav = 1 opens link in a new window, 2 opens in the same window
     * $result->created = (optional) date when the content was created
     *
     * @param string &$text        string text to be searched
     * @param string &$phrase      string how the search should be performed exact, all, or any
     * @param JParameter &$pluginParam custom plugin parameters in search.xml
     * @param int    $itemid       what menu item to use when creating the URL
     * @param string $ordering     ordering sent by Joomla: null, oldest, popular, category, alpha, or newest
     *
     * @return array of results as objects
     */
    function getSearchResults(&$text, &$phrase, &$pluginParam, $itemid, $ordering)
    {
        //initialize plugin database
        $db = & JFusionFactory::getDatabase($this->getJname());
        //get the query used to search
        $query = $this->getSearchQuery($pluginParam);
        //assign specific table colums to title and text
        $columns = $this->getSearchQueryColumns();
        //build the query
        if ($phrase == 'exact') {
            $where = "((LOWER({$columns->title}) LIKE '%$text%') OR (LOWER({$columns->text}) like '%$text%'))";
        } else {
            $words = explode(' ', $text);
            $wheres = array();
            foreach ($words as $word) {
                $wheres[] = "((LOWER({$columns->title}) LIKE '%$word%') OR (LOWER({$columns->text}) like '%$word%'))";
            }
            if ($phrase == 'all') {
                $separator = "AND";
            } else {
                $separator = "OR";
            }
            $where = '(' . implode(") $separator (", $wheres) . ')';
        }
        //pass the where clause into the plugin in case it wants to add something
        $this->getSearchCriteria($where, $pluginParam, $ordering);
        $query.= " WHERE $where";
        //add a limiter if set
        $limit = $pluginParam->get('search_limit', '');
        if (!empty($limit)) {
            $db->setQuery($query, 0, $limit);
        } else {
            $db->setQuery($query);
        }
        $results = $db->loadObjectList();
        //pass results back to the plugin in case they need to be filtered
        $this->filterSearchResults($results, $pluginParam);
        //load the results
        if (is_array($results)) {
            foreach ($results as $result) {
                //add a link
                $href = JFusionFunction::routeURL($this->getSearchResultLink($result), $itemid, $this->getJname(), false);
                $result->href = $href;
                //open link in same window
                $result->browsernav = 2;
                //clean up the text such as removing bbcode, etc
                $this->prepareText($result->text, 'search', $pluginParam, $result);
                $this->prepareText($result->title, 'search', $pluginParam, $result);
                $this->prepareText($result->section, 'search', $pluginParam, $result);
            }
        }
        return $results;
    }

    /**
     * Assigns specific db columns to title and text of content retrieved
     *
     * @return object Db columns assigned to title and text of content retrieved
     */
    function getSearchQueryColumns()
    {
        $columns = new stdClass();
        $columns->title = '';
        $columns->text = '';
        return $columns;
    }

    /**
     * Generates SQL query for the search plugin that does not include where, limit, or order by
     *
     * @param object &$pluginParam custom plugin parameters in search.xml
     * @return string Returns query string
     */
    function getSearchQuery(&$pluginParam)
    {
        return '';
    }

    /**
     * Add on a plugin specific clause;
     *
     * @param string &$where reference to where clause already generated by search bot; add on plugin specific criteria
     * @param object &$pluginParam custom plugin parameters in search.xml
     * @param string $ordering     ordering sent by Joomla: null, oldest, popular, category, alpha, or newest
     */
    function getSearchCriteria(&$where, &$pluginParam, $ordering)
    {
    }

    /**
     * Filter out results from the search ie forums that a user does not have permission to
     *
     * @param array &$results object list of search query results
     * @param object &$pluginParam custom plugin parameters in search.xml
     */
    function filterSearchResults(&$results, &$pluginParam)
    {
    }

    /**
     * Returns the URL for a post
     *
     * @param mixed $vars mixed
     *
     * @return string with URL
     */
    function getSearchResultLink($vars)
    {
        return '';
    }

    /************************************************
    * Functions For JFusion Who's Online Module
    ***********************************************/

    /**
     * Returns a query to find online users
     * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
     *
     * @param int $limit integer to use as a limiter for the number of results returned
     *
     * @return string online user query
     */
    function getOnlineUserQuery($limit)
    {
        return '';
    }

    /**
     * Returns number of guests
     *
     * @return int
     */
    function getNumberOnlineGuests()
    {
        return 0;
    }

    /**
     * Returns number of logged in users
     *
     * @return int
     */
    function getNumberOnlineMembers()
    {
        return 0;
    }

    /**
     * Set the language from Joomla to the integrated software
     *
     * @param object $userinfo - it can be null if the user is not logged for example.
     *
     * @return array nothing
     */
    function setLanguageFrontEnd($userinfo = null)
    {
        $status = array('error' => array(),'debug' => array());
        $status['debug'] = JText::_('METHOD_NOT_IMPLEMENTED');
        return $status;
    }

    /**
     * @param array $config
     * @param $view
     * @param JParameter $params
     *
     * @return string
     */
    function renderUserActivityModule($config, $view, $params)
    {
        return JText::_('METHOD_NOT_IMPLEMENTED');
    }

    /**
     * @param array $config
     * @param $view
     * @param JParameter $params
     *
     * @return string
     */
    function renderWhosOnlineModule($config, $view, $params)
    {
        return JText::_('METHOD_NOT_IMPLEMENTED');
    }
}
