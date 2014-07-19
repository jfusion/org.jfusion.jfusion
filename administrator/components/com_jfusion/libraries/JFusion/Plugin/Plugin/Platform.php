<?php namespace JFusion\Plugin;

/**
 * Abstract Plugin_Platform class for JFusion
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
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Parser\Css;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Language\Text;
use Joomla\Uri\Uri;
use stdClass;

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
class Plugin_Platform extends Plugin
{
	var $helper;

	/**
	 * @var $data stdClass
	 */
	var $data;

	private $cookies = array();
	private $protected = array('format');
	private $curlLocation = null;

	/**
	 * @param string $instance instance name of this plugin
	 */
	function __construct($instance)
	{
		parent::__construct($instance);
		//get the helper object
		$this->helper = & Factory::getHelper($this->getJname(), $this->getName());
	}

	/**
	 * framework has file?
	 *
	 * @param $file
	 *
	 * @return boolean|string
	 */
	final public function hasFile($file)
	{
		$helloReflection = new \ReflectionClass($this);
		$dir = dirname($helloReflection->getFilename());
		if(file_exists($dir . '/' . $file)) {
			return $dir . '/' . $file;
		}
		return false;
	}

	/**
	 * Called when JFusion is uninstalled so that plugins can run uninstall processes such as removing auth mods
	 * @return array    [0] boolean true if successful uninstall
	 *                  [1] mixed reason(s) why uninstall was unsuccessful
	 */
	function uninstall()
	{
		return array(true, '');
	}

	/**
	 * gets the visual html output from the plugin
	 *
	 * @param object &$data object containing all frameless data
	 *
	 * @return void
	 */
	function getBuffer(&$data)
	{
		trigger_error('&$data deprecreated use $this->data instead', E_USER_DEPRECATED);
		$status = $this->curlFrameless($data);

		if ( isset($data->location) ) {
			$location = str_replace($data->integratedURL, '', $data->location);
			$location = $this->fixUrl(array(1 => $location));
			$mainframe = Factory::getApplication();
			$mainframe->redirect($location);
		}
		if ( isset($status['error']) ) {
			foreach ($status['error'] as $value) {
				Framework::raiseWarning($value, $this->getJname());
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

		$siteuri = new Uri($data->integratedURL);
		$path = $siteuri->getPath();

		//parse anchors
		if(!empty($data->parse_anchors)) {
			$regex_body[]	= '#href=(?<quote>["\'])\#(.*?)(\k<quote>)#mS';
			$replace_body[]	= 'href=$1' . $data->fullURL . '#$2$3';
			$callback_body[] = '';
		}

		//parse relative URLS
		if(!empty($data->parse_rel_url)) {
			$regex_body[]	= '#(?<=href=["\'])\./(.*?)(?=["\'])#mS';
			$replace_body[] = '';
			$callback_body[] = 'fixUrl';

			$regex_body[]	= '#(?<=href=["\'])(?!\w{0,10}://|\w{0,10}:|\/)(.*?)(?=["\'])#mS';
			$replace_body[] = '';
			$callback_body[] = 'fixUrl';
		}

		if(!empty($data->parse_abs_path)) {
			$regex_body[]	= '#(?<=action=["\']|href=["\'])' . $path . '(.*?)(?=["\'])#mS';
			$replace_body[]	= '';
			$callback_body[] = 'fixUrl';

			$regex_body[] = '#(?<=href=["\'])' . $path . '(.*?)(?=["\'])#m';
			$replace_body[] = '';
			$callback_body[] = 'fixUrl';

			$regex_body[] = '#(src=["\']|background=["\']|url\()' . $path . '(.*?)(["\']|\))#mS';
			$replace_body[]	= '$1' . $data->integratedURL . '$2$3';
			$callback_body[] = '';
		}

		//parse absolute URLS
		if(!empty($data->parse_abs_url)) {
			$regex_body[]	= '#(?<=href=["\'])' . $data->integratedURL . '(.*?)(?=["\'])#m';
			$replace_body[] = '';
			$callback_body[] = 'fixUrl';
		}

		//convert relative links from images into absolute links
		if(!empty($data->parse_rel_img)) {
// (?<quote>["\'])
// \k<quote>
			$regex_body[] = '#(src=["\']|background=["\']|url\()\./(.*?)(["\']|\))#mS';
			$replace_body[]	= '$1' . $data->integratedURL . '$2$3';
			$callback_body[] = '';

			$regex_body[] = '#(src=["\']|background=["\']|url\()(?!\w{0,10}://|\w{0,10}:|\/)(.*?)(["\']|\))#mS';
			$replace_body[]	= '$1' . $data->integratedURL . '$2$3';
			$callback_body[] = '';
		}

		//parse form actions
		if(!empty($data->parse_action)) {
			if (!empty($data->parse_abs_path)) {
				$regex_body[] = '#action=[\'"]' . $path . '(.*?)[\'"](.*?)>#m';
				$replace_body[]	= '';
				$callback_body[] = 'fixAction';
			}
			if (!empty($data->parse_abs_url)) {
				$regex_body[] = '#action=[\'"]' . $data->integratedURL . '(.*?)[\'"](.*?)>#m';
				$replace_body[]	= '';
				$callback_body[] = 'fixAction';
			}
			if (!empty($data->parse_rel_url)) {
				$regex_body[] = '#action=[\'"](?!\w{0,10}://|\w{0,10}:|\/)(.*?)[\'"](.*?)>#m';
				$replace_body[]	= '';
				$callback_body[] = 'fixAction';
			}
		}

		//parse relative popup links to full url links
		if(!empty($data->parse_popup)) {
			$regex_body[] = '#window\.open\(\'(?!\w{0,10}://)(.*?)\'\)#mS';
			$replace_body[]	= 'window.open(\'' . $data->integratedURL . '$1\'';
			$callback_body[] = '';
		}

		$value = $data->bodymap;
		$value = @unserialize($value);
		if(is_array($value)) {
			foreach ($value['value'] as $key => $val) {
				$regex = html_entity_decode($value['value'][$key]);
//			    $regex = rtrim($regex, ';');
//			    $regex = eval("return '$regex';");

				$replace = html_entity_decode($value['name'][$key]);
//			    $replace = rtrim($replace, ';');
//			    $replace = eval("return '$replace';");

				if ($regex && $replace) {
					$regex_body[]	= $regex;
					$replace_body[]	= $replace;
					$callback_body[] = '';
				}
			}
		}

		foreach ($regex_body as $k => $v) {
			//check if we need to use callback
			if(!empty($callback_body[$k])) {
				$data->body = preg_replace_callback($regex_body[$k], array(&$this, $callback_body[$k]), $data->body);
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

		//convert relative links into absolute links
		$siteuri = new Uri($data->integratedURL);
		$path = $siteuri->getPath();

		$regex_header[]	= '#(href|src)=(?<quote>["\'])' . $path . '(.*?)(\k<quote>)#Si';
		$replace_header[] = '$1=$2' . $data->integratedURL . '$3$4';
		$callback_header[] = '';

		$regex_header[]		= '#(href|src)=(?<quote>["\'])(\.\/|/)(.*?)(\k<quote>)#iS';
		$replace_header[]	= '$1=$2' . $data->integratedURL . '$4$5';
		$callback_header[] = '';

		$regex_header[] 	= '#(href|src)=(?<quote>["\'])(?!\w{0,10}://)(.*?)(\k<quote>)#mSi';
		$replace_header[]	= '$1=$2' . $data->integratedURL . '$3$4';
		$callback_header[] = '';

		$regex_header[]		= '#@import(.*?)(?<quote>["\'])' . $path . '(.*?)(\k<quote>)#Sis';
		$replace_header[]	= '@import$1$2' . $data->integratedURL . '$3$4';
		$callback_header[] = '';

		$regex_header[]		= '#@import(.*?)(?<quote>["\'])\.\/(.*?)(\k<quote>)#Sis';
		$replace_header[]	= '@import$1$2' . $data->integratedURL . '$3$4';
		$callback_header[] = '';

		//fix for URL redirects
		$parse_redirect = $this->params->get('parse_redirect');
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
			if(!empty($callback_header[$k])) {
				$data->header = preg_replace_callback($regex_header[$k], array(&$this, $callback_header[$k]), $data->header);
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
		if(!empty($temp[2])) {
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
				$js .= '</script>';
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
	function parseCSS(&$data, &$html, $infile_only = false)
	{
		$jname = $this->getJname();

		if (empty($jname)) {
			$jname = Factory::getApplication()->input->get('Itemid');
		}

		$sourcepath = $data->css->sourcepath . $jname . DIRECTORY_SEPARATOR;
		$urlpath = $data->css->url . $jname . '/';

		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		Folder::create($sourcepath . 'infile');
		if (!$infile_only) {
			//Outputs: apearpearle pear
			if ($data->parse_css) {
				if (preg_match_all('#<link(.*?type=[\'|"]text\/css[\'|"][^>]*)>#Si', $html, $css)) {
					jimport('joomla.filesystem.file');
					foreach ($css[1] as $values) {
						if(preg_match('#href=[\'|"](.*?)[\'|"]#Si', $values, $cssUrl)) {
							$cssUrlRaw = $cssUrl[1];

							if (strpos($cssUrlRaw, '/') === 0) {
								$uri = new Uri($data->integratedURL);

								$cssUrlRaw = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port')) . $cssUrlRaw;
							}
							$filename = $this->cssCacheName(urldecode(htmlspecialchars_decode($cssUrl[1])));
							$filenamesource = $sourcepath . $filename;

							if (!is_file(Path::clean($filenamesource))) {
								$cssparser = new Css('#jfusionframeless');
								$result = $cssparser->ParseUrl($cssUrlRaw);
								if ($result !== false) {
									$content = $cssparser->GetCSS();
									File::write($filenamesource, $content);
								}
							}

							if (is_file(Path::clean($filenamesource))) {
								$html = str_replace($cssUrlRaw, $urlpath . $filename, $html);
							}
						}
					}
				}
			}
		}
		if ($data->parse_infile_css) {
			if (preg_match_all('#<style.*?type=[\'|"]text/css[\'|"].*?>(.*?)</style>#Sims', $html, $css)) {
				foreach ($css[1] as $key => $values) {
					$filename = md5($values) . '.css';
					$filenamesource = $sourcepath . 'infile' . DIRECTORY_SEPARATOR . $filename;

					if (preg_match('#media=[\'|"](.*?)[\'|"]#Si', $css[0][$key], $cssMedia)) {
						$cssMedia = $cssMedia[1];
					} else {
						$cssMedia = '';
					}

					if (!is_file(Path::clean($filenamesource))) {
						$cssparser = new Css('#jfusionframeless');
						$cssparser->setUrl($data->integratedURL);
						$cssparser->ParseStr($values);
						$content = $cssparser->GetCSS();
						File::write($filenamesource, $content);
					}
					if (is_file(Path::clean($filenamesource))) {
						$data->css->files[] = $urlpath . 'infile/' . $filename;
						$data->css->media[] = $cssMedia;
					}
				}
				$html = preg_replace('#<style.*?type=[\'|"]text/css[\'|"].*?>(.*?)</style>#Sims', '', $html);
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
		$filename = $uri->toString(array('path', 'query'));
		$filename = trim($filename, '/');

		$filename = str_replace(array('.css', '\\', '/', '|', '*', ':', ';', '?', '"', '<', '>', '=', '&'),
		                        array('', '', '-', '', '', '', '', '', '', '', '', ',', '_'),
		                        $filename);
		$filename .= '.css';
		return $filename;
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

		if (substr($this->data->baseURL, -1) != '/') {
			//non sef URls
			$q = str_replace('?', '&amp;', $q);
			$url = $this->data->baseURL . '&amp;jfile=' . $q;
		} elseif ($this->data->sefmode == 1) {
			$url = Factory::getApplication()->routeURL($q, Factory::getApplication()->input->getInt('Itemid'));
		} else {
			//we can just append both variables
			$url = $this->data->baseURL . $q;
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
		$Itemid = Factory::getApplication()->input->getInt('Itemid');
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
				$url = Factory::getApplication()->routeURL($url, $Itemid);
				$replacement = 'action="' . $url . '"' . $extra . '>';
				return $replacement;
			} else {
				//simple SEF mode
				$url_details = parse_url($url);
				$url_variables = array();
				if(!empty($url_details['query'])) {
					parse_str($url_details['query'], $url_variables);
				}
				$jfile = basename($url_details['path']);
				$replacement = 'action="' . $baseURL . $jfile . '"' . $extra . '>';
			}
		}
		unset($url_variables['option'], $url_variables['jfile'], $url_variables['Itemid']);

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

		preg_match('#(.*?;url=)(.*)#mi', $matches[1], $matches2);
		list(, $timeout , $url) = $matches2;

		$uri = new Uri($url);
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
			$sefmode = $this->params->get('sefmode');
			if ($sefmode == 1) {
				//extensive SEF parsing was selected
				$url = $jfile;
				if (!empty($query)) {
					$url.= '?' . $query;
				}
				$url = Factory::getApplication()->routeURL($url, Factory::getApplication()->input->getInt('Itemid'));
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
		//Framework::raiseWarning(htmlentities($return), $this->getJname());
		return $timeout . $url;
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

		$jfile = Factory::getApplication()->input->get('jfile', 'index.php', 'raw');

		unset($query['option'], $query['jfile'], $query['Itemid'], $query['jFusion_Route'], $query['view'], $query['layout'], $query['controller'], $query['lang'], $query['task']);

		$queries = array();

		foreach($query as $key => $var) {
			$queries[] = $key . '=' . $var;
		}

		$wrap = $jfile . '?' . implode($queries, '&');

		$source_url = $this->params->get('source_url');

		return $source_url . $wrap;
	}

	/**
	 * @param $data
	 * @return array
	 */
	private function curlFrameless(&$data) {
		require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.cookie.php');

		$status = array('error' => array(), 'debug' => array());

		$url = $data->source_url;

		$config = Factory::getConfig();
		$sefenabled = $config->get('sef');
		if(!empty($sefenabled)) {
			$uri = JUri::getInstance();
			$current = $uri->toString(array('path', 'query'));

			$menu = JMenu::getInstance('site');
			$item = $menu->getActive();
			$index = '/' . $item->route;
			$pos = strpos($current, $index);
			if ($pos !== false) {
				$current = substr($current, $pos + strlen($index));
			}
			$current = ltrim($current , '/');
		} else {
			$current = Factory::getApplication()->input->get('jfile') . '?';
			$current .= $this->curlFramelessBuildUrl('GET');
		}

		$url .= $current;
		$post = $this->curlFramelessBuildUrl('POST');

		$files = $_FILES;
		$filepath = array();
		if($post) {
			foreach($files as $userfile=>$file) {
				if (is_array($file)) {
					if(is_array($file['name'])) {
						foreach ($file['name'] as $key => $value) {
							$name = $file['name'][$key];
							$path = $file['tmp_name'][$key];
							if ($name) {
								$filepath[$key] = JPATH_ROOT . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $name;
								rename($path, $filepath[$key]);
								$post[$userfile . '[' . $key . ']'] = '@' . $filepath[$key];
							}
						}
					} else {
						$path = $file['tmp_name'];
						$name = $file['name'];
						$key = $path;
						$filepath[$key] = JPATH_ROOT . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $name;
						rename($path, $filepath[$key]);
						$post[$userfile] = '@' . $filepath[$key];
					}
				}
			}
		}

		$ch = curl_init($url);
		if ($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		} else {
			curl_setopt($ch, CURLOPT_POST, 0);
		}

		if(!empty($data->httpauth) ) {
			curl_setopt($ch,CURLOPT_USERPWD, $data->httpauth_username . ':' . $data->httpauth_password);

			switch ($data->httpauth) {
				case 'basic':
					$data->httpauth = CURLAUTH_BASIC;
					break;
				case 'gssnegotiate':
					$data->httpauth = CURLAUTH_GSSNEGOTIATE;
					break;
				case 'digest':
					$data->httpauth = CURLAUTH_DIGEST;
					break;
				case 'ntlm':
					$data->httpauth = CURLAUTH_NTLM;
					break;
				case 'anysafe':
					$data->httpauth = CURLAUTH_ANYSAFE;
					break;
				case 'any':
				default:
					$data->httpauth = CURLAUTH_ANY;
			}

			curl_setopt($ch,CURLOPT_HTTPAUTH, $data->httpauth);
		}

		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		curl_setopt($ch, CURLOPT_REFERER, $ref);

		$headers = array();
		$headers[] = 'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'];
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlFramelessReadHeader'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_FAILONERROR, 0);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 2 );
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$data->verifyhost = isset($data->verifyhost) ? $data->verifyhost : 2;
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $data->verifyhost);

		curl_setopt($ch, CURLOPT_HEADER, 0);

		$cookies = Factory::getCookies();

		$_COOKIE['jfusionframeless'] = true;
		curl_setopt($ch, CURLOPT_COOKIE, $cookies->buildCookie());
		unset($_COOKIE['jfusionframeless']);

		$data->buffer = curl_exec($ch);

		$this->curlFramelessProtectParams($data);

		if ($this->curlLocation) {
			$data->location = $this->curlLocation;
		}

		$data->cookie_domain = isset($data->cookie_domain) ? $data->cookie_domain : '';
		$data->cookie_path = isset($data->cookie_path) ? $data->cookie_path : '';

		foreach ($this->cookies as $cookie) {
			$cookies->addCookie($cookie->name, urldecode($cookie->value), $cookie->expires, $data->cookie_path, $data->cookie_domain);
		}

		if (curl_error($ch)) {
			$status['error'][] = Text::_('CURL_ERROR_MSG') . ': ' . curl_error($ch) . ' URL:' . $url;
			curl_close($ch);
			return $status;
		}

		curl_close($ch);

		if (count($filepath)) {
			foreach($filepath as $value) {
				unlink($value);
			}
		}
		return $status;
	}

	/**
	 * @param $ch
	 * @param $string
	 *
	 * @return int
	 */
	public final function curlFramelessReadHeader($ch, $string) {
		$length = strlen($string);
		if(!strncmp($string, 'Location:', 9)) {
			$this->curlLocation = trim(substr($string, 9, -1));
		} else if(!strncmp($string, 'Set-Cookie:', 11)) {
			$string = trim(substr($string, 11, -1));
			$parts = explode(';', $string);

			list($name, $value) = explode('=', $parts[0]);

			$cookie = new stdClass;
			$cookie->name = trim($name);
			$cookie->value = trim($value);
			$cookie->expires = 0;

			if (isset($parts[1])) {
				list($name, $value) = explode('=', $parts[1]);
				if ($name == 'expires') {
					$cookie->expires = strtotime($value);
				}
			}

			$this->cookies[] = $cookie;
		}
		return $length;
	}

	/**
	 * @param string $type
	 * @return mixed|string
	 */
	private function curlFramelessBuildUrl($type = 'GET') {
		if ($type == 'POST') {
			$var = $_POST;
		} else {
			$var = $_GET;
		}

		foreach($this->protected as $name) {
			$key = 'jfusion_' . $name;
			if (isset($var[$key])) {
				$var[$name] = $var[$key];
				unset($var[$key]);
			}
		}

		unset($var['Itemid'], $var['option'], $var['view'], $var['jFusion_Route'], $var['jfile']);
		if ($type == 'POST') return $var;
		return http_build_query($var);
	}

	/**
	 * @param stdClass $data
     */
	private function curlFramelessProtectParams(&$data) {
		$regex_input = array();
		$replace_input = array();

		$uri = new Uri($data->source_url);

		$search = array();
		$search[] = preg_quote($uri->getPath(), '#');
		$search[] = preg_quote($uri->toString(array('scheme', 'host', 'path')), '#');
		$search[] = '(?!\w{0,10}://|\w{0,10}:|\/)';

		foreach($this->protected as $name) {
			$name = preg_quote($name , '#');
			$regex_input[]	= '#<input([^<>]+name=["\'])(' . $name . '["\'][^<>]*)>#Si';
			$replace_input[] = '<input$1jfusion_$2>';

			foreach($search as $type) {
				$regex_input[]	= '#<a([^<>]+href=["\']' . $type . '.*?[\?|\&|\&amp;])(' . $name . '.*?["\'][^<>]*)>#Si';
				$replace_input[] = '<a$1jfusion_$2>';
			}
		}

		foreach ($regex_input as $k => $v) {
			//check if we need to use callback
			$data->buffer = preg_replace($regex_input[$k], $replace_input[$k], $data->buffer);
		}
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
}
