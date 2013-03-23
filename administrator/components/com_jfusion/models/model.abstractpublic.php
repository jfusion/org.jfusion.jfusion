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
     * @return void
     */
    function getBuffer(&$data)
    {
        require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.curlframeless.php');

        $frameless = new JFusionCurlFrameless();

        $status = $frameless->display($data);
        if ( isset($data->location) ) {
            $location = str_replace($data->integratedURL,'',$data->location);
	        $location = $this->fixUrl(array(1 => $location));
            $mainframe = JFactory::getApplication();
            $mainframe->redirect($location);
        }
        if ( isset($status['error']) ) {
            foreach ( $status['error'] as $key => $value ) {
                JError::raiseWarning(500, $value);
            }
        }
    }

    /**
     * function that parses the HTML body and fixes up URLs and form actions
     * @param &$data
     */
    function parseBody(&$data)
    {
        $regex_body = array();
        $replace_body = array();
        $callback_body = array();
        $params = JFusionFactory::getParams($this->getJname());

        //parse anchors
        if(!empty($data->parse_anchors)) {
            $regex_body[]	= '#href=(?<quote>["\'])\#(.*?)(\k<quote>)#mS';
            $replace_body[]	= 'href=$1'.$data->fullURL.'#$2$3';
            $callback_body[] = '';
        }

        //parse absolute URLS
        $path = '';

        if(!empty($data->parse_abs_path)) {
            $path = preg_replace( '#(\w{0,10}://)(.*?)/(.*?)#is'  , '$3' , $data->integratedURL );
            $path = preg_replace('#//+#','/',"/$path/");

            $regex_body[]	= '#(action="|href="|src="|background="|url\(\'?)'.$path.'(.*?)("|\'?\))#mS';
            $replace_body[]	= '$1'.$data->integratedURL.'$2$3';
            $callback_body[] = '';

	        $regex_body[] = '#(?<=href=["\'])'.$path.'(.*?)(?=["\'])#m';
	        $replace_body[] = '';
	        $callback_body[] = 'fixUrl';
        }

        //parse relative URLS
        if(!empty($data->parse_rel_url)) {
	        $regex_body[]	= '#(?<=href=["\'])[./|/](.*?)(?=["\'])#mS';
	        $replace_body[] = '';
	        $callback_body[] = 'fixUrl';

	        $regex_body[]	= '#(?<=href=["\'])(?!\w{0,10}://|\w{0,10}:)(.*?)(?=["\'])#mS';
	        $replace_body[] = '';
	        $callback_body[] = 'fixUrl';
        }

        //parse absolute URLS
        if(!empty($data->parse_abs_url)) {
	        $regex_body[]	= '#(?<=href=["\'])'.$data->integratedURL.'(.*?)(?=["\'])#m';
	        $replace_body[] = '';
	        $callback_body[] = 'fixUrl';
        }

        //convert relative links from images into absolute links
        if(!empty($data->parse_rel_img)) {
// (?<quote>["\'])
// \k<quote>
            $regex_body[]	= '#(src=["\']|background=["\']|url\()[./|/](.*?)(["\']|\))#mS';
            $replace_body[]	= '$1'.$data->integratedURL.'$2$3';
            $callback_body[] = '';

            $regex_body[]	= '#(src=["\']|background=["\']|url\()(?!\w{0,10}://|\w{0,10}:)(.*?)(["\']|\))#mS';
            $replace_body[]	= '$1'.$data->integratedURL.'$2$3';
            $callback_body[] = '';
        }

        //parse form actions
        if(!empty($data->parse_action)) {
	        if (!empty($data->parse_abs_path)) {
		        $regex_body[]	= '#action=[\'"]'.$path.'(.*?)[\'"](.*?)>#m';
		        $replace_body[]	= '';
		        $callback_body[] = 'fixAction';
	        }
	        if (!empty($data->parse_abs_url)) {
		        $regex_body[]	= '#action=[\'"]'.$data->integratedURL.'(.*?)[\'"](.*?)>#m';
		        $replace_body[]	= '';
		        $callback_body[] = 'fixAction';
	        }
	        if (!empty($data->parse_rel_url)) {
		        $regex_body[]	= '#action=[\'"][./|/](.*?)[\'"](.*?)>#m';
		        $replace_body[]	= '';
		        $callback_body[] = 'fixAction';

		        $regex_body[]	= '#action=[\'"](?!\w{0,10}://|\w{0,10}:)(.*?)[\'"](.*?)>#m';
		        $replace_body[]	= '';
		        $callback_body[] = 'fixAction';
	        }
        }

        //parse relative popup links to full url links
        if(!empty($data->parse_popup)) {
            $regex_body[]	= "#window\.open\('(?!\w{0,10}://)(.*?)'\)#mS";
            $replace_body[]	= 'window.open(\''.$data->integratedURL.'$1\'';
            $callback_body[] = '';
        }

	    $value = $data->bodymap;
	    $value = @unserialize($value);
	    if(is_array($value)) {
		    foreach ($value['value'] as $key => $val) {
			    $regex = html_entity_decode($value['value'][$key]);
//			    $regex = rtrim($regex,';');
//			    $regex = eval("return '$regex';");

			    $replace = html_entity_decode($value['name'][$key]);
//			    $replace = rtrim($replace,';');
//			    $replace = eval("return '$replace';");

			    if ($regex && $replace) {
				    $regex_body[]	= $regex;
				    $replace_body[]	= $replace;
				    $callback_body[] = '';
			    }
		    }
	    }
	    $data->body = preg_replace($regex_body, $replace_body, $data->body);
        foreach ($regex_body as $k => $v) {
            //check if we need to use callback
            if(!empty($callback_body[$k])){
                $data->body = preg_replace_callback($regex_body[$k],array( &$this,$callback_body[$k]), $data->body);
            } else {
                $data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
            }
        }

	    $this->_parseBody($data);
    }

	/**
	 * function that parses the HTML body and fixes up URLs and form actions
	 * @param &$data
	 */
	function _parseBody(&$data)
	{
	}

    /**
     * function that parses the HTML header and fixes up URLs
     * @param &$data
     */
    function parseHeader(&$data)
    {
        // Define our preg arrays
        $regex_header = array();
        $replace_header	= array();
        $callback_header = array();
        $params = JFusionFactory::getParams($this->getJname());

        //convert relative links into absolute links
        $path = preg_replace( '#(\w{0,10}://)(.*?)/(.*?)#is'  , '$3' , $data->integratedURL );
        $path = preg_replace('#//+#','/',"/$path/");

	    $regex_header[]	= '#(href|src)=(?<quote>["\'])'.$path.'(.*?)(\k<quote>)#Si';
	    $replace_header[] = '$1=$2'.$data->integratedURL.'$3$4';
	    $callback_header[] = '';

	    $regex_header[]		= '#(href|src)=(?<quote>["\'])(./|/)(.*?)(\k<quote>)#iS';
	    $replace_header[]	= '$1=$2'.$data->integratedURL.'$4$5';
	    $callback_header[] = '';

	    $regex_header[] 	= '#(href|src)=(?<quote>["\'])(?!\w{0,10}://)(.*?)(\k<quote>)#mSi';
	    $replace_header[]	= '$1=$2'.$data->integratedURL.'$3$4';
	    $callback_header[] = '';

	    $regex_header[]		= '#@import(.*?)(?<quote>["\'])'.$path.'(.*?)(\k<quote>)#Sis';
	    $replace_header[]	= '@import$1$2'.$data->integratedURL.'$3$4';
	    $callback_header[] = '';

	    $regex_header[]		= '#@import(.*?)(?<quote>["\'])/(.*?)(\k<quote>)#Sis';
	    $replace_header[]	= '@import$1$2'.$data->integratedURL.'$3$4';
	    $callback_header[] = '';

        //fix for URL redirects
        $parse_redirect = $params->get('parse_redirect');
        if(!empty($parse_redirect)) {
            $regex_header[] = '#(?<=<meta http-equiv="refresh" content=")(.*?)(?=")#mis';
            $replace_header[] = '';
            $callback_header[] = 'fixRedirect';
        }

        $value = $data->headermap;
	    $value = @unserialize($value);
        if(is_array($value)) {
            foreach ($value['value'] as $key => $val) {
                $regex = html_entity_decode($value['value'][$key]);
//                $regex = rtrim($regex,';');
//                $regex = eval("return '$regex';");

                $replace = html_entity_decode($value['name'][$key]);
//                $replace = rtrim($replace,';');
//                $replace = eval("return '$replace';");

                if ($regex && $replace) {
                    $regex_header[]		= $regex;
                    $replace_header[]	= $replace;
                    $callback_header[] = '';
                }
            }
        }
        foreach ($regex_header as $k => $v) {
            //check if we need to use callback
            if(!empty($callback_header[$k])){
                $data->header = preg_replace_callback($regex_header[$k],array( &$this,$callback_header[$k]), $data->header);
            } else {
                $data->header = preg_replace($regex_header[$k], $replace_header[$k], $data->header);
            }
        }

	    $this->_parseHeader($data);
    }

	/**
	 * function that parses the HTML header and fixes up URLs
	 * @param &$data
	 */
	function _parseHeader(&$data)
	{

	}

    /**
     * Parsers the buffer received from getBuffer into header and body
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
	        $data->bodyAttributes = $temp[2];
            if(preg_match($pattern, $temp[2], $temp)) {
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
        $param = JFusionFactory::getParams ( $this->getJname() );

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
                    require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'parsers' . DS . 'css.php');

                    jimport('joomla.filesystem.file');
                    foreach ($css[1] as $key => $values) {
                        if( preg_match( '#href=[\'|"](.*?)[\'|"]#Si', $values, $cssUrl )) {
                            $cssUrlRaw = $cssUrl[1];

                            if (strpos($cssUrlRaw,'/') === 0) {
                                $uri = JURI::getInstance($data->integratedURL);
                                $uri = new JURI ($data->integratedURL);

                                $cssUrlRaw = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port')).$cssUrlRaw;
                            }

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
                    $return = '<span style="font-size:'.$default.'">'.$content.'</span>';
                    break;
                case 'glow':
                    $temp = explode(',', $default);
                    $color = (!empty($temp[0])) ? $temp[0] : 'red';
                    $return = '<span style="background-color:'.$color.'">'.$content.'</span>';
                    break;
                case 'shadow':
                    $temp = explode(',', $default);
                    $color = (!empty($temp[0])) ? $temp[0] : '#6374AB';
                    $dir = (!empty($temp[1])) ? $temp[1] : 'left';
                    $x = ($dir == 'left') ? '-0.2em' : '0.2em';
                    $return = '<span style="text-shadow: '.$color.' $x 0.1em 0.2em;">'.$content.'</span>';
                    break;
                case 'move':
                    $return = '<marquee>'.$content.'</marquee>';
                    break;
                case 'pre':
                    $return = '<pre>'.$content.'</pre>';
                    break;
                case 'hr':
                    return '<hr>';
                    break;
                case 'flash':
                    $temp = explode(',', $default);
                    $width = (!empty($temp[0])) ? $temp[0] : '200';
                    $height = (!empty($temp[1])) ? $temp[1] : '200';
                    $return = <<<HTML
                        <object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" width="{$width}" height="{$height}">
                            <param name="movie" value="{$content}" />
                            <param name="play" value="false" />
                            <param name="loop" value="false" />
                            <param name="quality" value="high" />
                            <param name="allowScriptAccess" value="never" />
                            <param name="allowNetworking" value="internal" />
                            <embed src="{$content}" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" width="{$width}" height="{$height}" play="false" loop="false" quality="high" allowscriptaccess="never" allownetworking="internal">
                            </embed>
                        </object>
HTML;
                    break;
                case 'ftp':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = '<a href="'.$content.'">'.$default.'</a>';
                    break;
                case 'table':
                    $return = '<table>'.$content.'</table>';
                    break;
                case 'tr':
                    $return = '<tr>'.$content.'</tr>';
                    break;
                case 'td':
                    $return = '<td>'.$content.'</td>';
                    break;
                case 'tt';
                    $return = '<tt>'.$content.'</tt>';
                    break;
                case 'o':
                case 'O':
                case '0':
                    $return = '<li type="circle">'.$content.'</li>';
                    break;
                case '*':
                case '@':
                    $return = '<li type="disc">'.$content.'</li>';
                    break;
                case '+':
                case 'x':
                case '#':
                    $return = '<li type="square">'.$content.'</li>';
                    break;
                case 'abbr':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = '<abbr title="'.$default.'">'.$content.'</abbr>';
                    break;
                case 'anchor':
                    if (!empty($default)) {
                        $return = '<span id="'.$default.'">'.$content.'</span>';
                    } else {
                        $return = $content;
                    }
                    break;
                case 'black':
                case 'blue':
                case 'green':
                case 'red':
                case 'white':
                    $return = '<span style="color: '.$name.';">'.$content.'</span>';
                    break;
                case 'iurl':
                    if (empty($default)) {
                        $default = $content;
                    }
                    $return = '<a href="' . htmlspecialchars($default) . '" class="bbcode_url" target="_self">'.$content.'</a>';
                    break;
                case 'html':
                case 'nobbc':
                case 'php':
                    $return = $content;
                    break;
                case 'ltr':
                    $return = '<div style="text-align: left;" dir="$name">'.$content.'</div>';
                    break;
                case 'rtl':
                    $return = '<div style="text-align: right;" dir="$name">'.$content.'</div>';
                    break;
                case 'me':
                    $return = '<div style="color: red;">* '.$default.' '.$content.'</div>';
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
        if (substr($baseURL, -1) != '/') {
            //non sef URls
            $q = str_replace('?', '&amp;', $q);
            $url = $baseURL . '&amp;jfile=' . $q;
        } elseif ($this->data->sefmode == 1) {
            $url = JFusionFunction::routeURL($q, JRequest::getInt('Itemid'));
        } else {
            //we can just append both variables
            $url = $baseURL . $q;
        }
        return $url;
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
        $Itemid = JRequest::getInt('Itemid');
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
            if ($this->data->sefmode == 1) {
                //extensive SEF parsing was selected
                $url = JFusionFunction::routeURL($url, $Itemid);
                $replacement = 'action="' . $url . '"' . $extra . '>';
                return $replacement;
            } else {
                //simple SEF mode
                $url_details = parse_url($url);
                $url_variables = array();
                if(!empty($url_details['query'])){
                    parse_str($url_details['query'], $url_variables);
                }
                $jfile = basename($url_details['path']);
                $replacement = 'action="' . $baseURL . $jfile . '"' . $extra . '>';
            }
        }
        unset($url_variables['option'], $url_variables['jfile'], $url_variables['Itemid']);
        if(!empty($url_variables['mode'])){
            if ($url_variables['mode'] == 'topic_view') {
                $url_variables['t'] = JRequest::getVar('t');
                $url_variables['f'] = JRequest::getVar('f');
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
     * @param array $matches
     *
     * @return string
     */
    function fixRedirect($matches) {
        $baseURL = $this->data->baseURL;

        preg_match ( '#(.*?;url=)(.*)#mi' , $matches[1] , $matches2 );
        list(,$timeout , $url) = $matches2;

        //JError::raiseWarning(500, $url);
        //split up the timeout from url
        $parts = explode(';URL=', $url);
        $uri = new JURI($url);
        $jfile = basename($uri->getPath());
        $query = $uri->getQuery(false);
        $fragment = $uri->getFragment();
        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            $url = $baseURL . '&amp;jfile=' . $jfile;
            if (!empty($query)) {
                $url.= '&amp;' . $query;
            }
        } else {
            //check to see what SEF mode is selected
            $params = JFusionFactory::getParams($this->getJname());
            $sefmode = $params->get('sefmode');
            if ($sefmode == 1) {
                //extensive SEF parsing was selected
                $url = $jfile;
                if (!empty($query)) {
                    $url.= '?' . $query;
                }
                $url = JFusionFunction::routeURL($url, JRequest::getInt('Itemid'));
            } else {
                //simple SEF mode, we can just combine both variables
                $url = $baseURL . $jfile;
                if (!empty($query)) {
                    $url.= '?' . $query;
                }
            }
        }
        if (!empty($fragment)) {
            $url .= '#' . $fragment;
        }
        //JError::raiseWarning(500, htmlentities($return));
        return $timeout . $url;
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
        $db = JFusionFactory::getDatabase($this->getJname());
        //get the query used to search
        $query = $this->getSearchQuery($pluginParam);
        //assign specific table columns to title and text
        $columns = $this->getSearchQueryColumns();
        //build the query
        if ($phrase == 'exact') {
            $where = '((LOWER('.$columns->title.') LIKE \'%'.$text.'%\') OR (LOWER('.$columns->text.') like \'%'.$text.'%\'))';
        } else {
            $words = explode(' ', $text);
            $wheres = array();
            foreach ($words as $word) {
                $wheres[] = '((LOWER('.$columns->title.') LIKE \'%'.$word.'%\') OR (LOWER('.$columns->text.') like '%$word%'))';
            }
            if ($phrase == 'all') {
                $separator = 'AND';
            } else {
                $separator = 'OR';
            }
            $where = '(' . implode(') '.$separator.' (', $wheres) . ')';
        }
        //pass the where clause into the plugin in case it wants to add something
        $this->getSearchCriteria($where, $pluginParam, $ordering);
        $query.= ' WHERE '.$where;
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

    /**
     * function to generate url for wrapper
     * @param &$data
     *
     * @return string returns the url
     */
    function getWrapperURL($data)
    {
        //get the url
        $query = ($_GET);
        if(isset($query['jfile'])){
            $jfile = $query['jfile'];
        } else {
            $jfile = 'index.php';
        }

        unset($query['option'], $query['jfile'], $query['Itemid'], $query['jFusion_Route'], $query['view'],$query['layout'], $query['controller'], $query['lang'], $query['task']);

        $queries = array();

        foreach($query as $key => $var) {
            $queries[] = $key.'='.$var;
        }

        $wrap = $jfile . '?'. implode($queries,'&');

        $source_url = $data->jParam->get('source_url');

        //check for trailing slash
        if (substr($source_url, -1) == '/') {
            $url = $source_url . $wrap;
        } else {
            $url = $source_url . '/'. $wrap;
        }

        return $url;
    }
}
