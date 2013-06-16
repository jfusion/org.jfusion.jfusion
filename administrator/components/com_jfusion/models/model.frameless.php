<?php

/**
 * @package JFusion
 * @subpackage Models
 * @author JFusion development team -- Morten Hundevad
 * @copyright Copyright (C) 2008 JFusion -- Morten Hundevad. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php');
require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php');

/**
 * Singleton static only class that creates instances for each specific JFusion plugin.
 * @package JFusion
 */

class JFusionFrameless {
	/**
	 * @static
	 * @param $jname
	 * @param bool $isPlugin
	 *
	 * @return \stdClass
	 *
	 */
	public static function initData($jname,$isPlugin=true)
	{
		$uri = JURI::getInstance ();

		// declare Data object
		$data = new stdClass ( );
		$data->buffer = null;
		$data->header = null;
		$data->bodyAttributes = null;
		$data->body = null;
		$data->baseURL = null;
		$data->fullURL = null;
		$data->jname = $jname;
		$data->integratedURL = null;
		$data->isPlugin = $isPlugin;
		$data->Itemid = JRequest::getVar ( 'Itemid' );

		//Get the base URL to the specific JFusion plugin
		$data->baseURL = JFusionFunction::getPluginURL ( $data->Itemid );

		//Get the full current URL
		$query = $uri->getQuery ();
		$url = $uri->current ();
		$data->fullURL = $query ? $url . '?' . $query : $url;
		$data->fullURL = str_replace('&', '&amp;', $data->fullURL);

		/**
		 * @ignore
		 * @var $menu JMenu
		 */
		$menu = JSite::getMenu();
		if(!$isPlugin) {
			$item = $menu->getItem($jname);

			if ($item) {
				$JFusionParam = $menu->getParams ( $item->id );
				$MenuParam = $menu->getParams ( $item->id );
			} else {
				$JFusionParam = $menu->getParams( null );
				$MenuParam = $menu->getParams( null );
			}
		} else {
			$MenuParam = $menu->getParams ( $data->Itemid );
			$JFusionParam = JFusionFactory::getParams ( $jname );
		}

		$data->jParam = $JFusionParam;
		$data->mParam = $MenuParam;

		$JFusionPluginParam = $MenuParam->get('JFusionPluginParam');
		if ($JFusionPluginParam) {
			$params = unserialize(base64_decode($JFusionPluginParam));
			if ($params && isset($params['jfusionplugin'])) {
				if (JFusionFunction::isJoomlaVersion('1.6') && isset($params[$params['jfusionplugin']]['params'])) {
					$params += $params[$params['jfusionplugin']]['params'];
					unset($params[$params['jfusionplugin']]);
				}
				$data->mParam->loadArray($params);
			}
		}

		//Get the integrated URL
		$data->integratedURL = $JFusionParam->get ( 'source_url' );

		$data->source_url = $JFusionParam->get('source_url');
		$data->cookie_domain = $JFusionParam->get('cookie_domain');
		$data->cookie_path = $JFusionParam->get('cookie_path');
		$data->cookie_expires = $JFusionParam->get('cookie_expires');
		$data->httpauth = $JFusionParam->get('httpauth');
		$data->httpauth_username = $JFusionParam->get('curl_username');
		$data->httpauth_password = $JFusionParam->get('curl_password');
		$data->verifyhost = $JFusionParam->get('verifyhost');

		$data->sefmode = $MenuParam->get('sefmode',$JFusionParam->get('sefmode',0));

		$data->bodyextract = $JFusionParam->get('bodyextract');
		$data->bodyremove = $JFusionParam->get('bodyremove');

		// CSS PARSER INFO
		$data->default_css = $MenuParam->get('default_css',1);
		$data->default_css_overflow = $MenuParam->get('default_css_overflow' ,'visible');

		$data->parse_infile_css = $MenuParam->get('parse_infile_css',1);
		$data->parse_css = $MenuParam->get('parse_css',1);

		$data->parse_anchors = $MenuParam->get('parse_anchors',$JFusionParam->get('parse_anchors',1));
		$data->parse_rel_url = $MenuParam->get('parse_rel_url',$JFusionParam->get('parse_rel_url',1));
		$data->parse_abs_url = $MenuParam->get('parse_abs_url',$JFusionParam->get('parse_abs_url',1));
		$data->parse_abs_path = $MenuParam->get('parse_abs_path',$JFusionParam->get('parse_abs_path',1));
		$data->parse_rel_img = $MenuParam->get('parse_rel_img',$JFusionParam->get('parse_rel_img',1));
		$data->parse_action = $MenuParam->get('parse_action',$JFusionParam->get('parse_action',1));
		$data->parse_popup = $MenuParam->get('parse_popup',$JFusionParam->get('parse_popup',1));

		$data->bodymap = $JFusionParam->get('bodymap',$JFusionParam->get('bodymap'));
		$data->headermap = $JFusionParam->get('headermap',$JFusionParam->get('headermap'));

		return $data;
	}


	/**
	 * @static
	 * @param $data
	 * @return bool
	 */
	public static function displayContent($data)
	{
		$mainframe = JFactory::getApplication();
		/**
		 * @ignore
		 * @var $document JDocumentHTML
		 */
		$document = JFactory::getDocument();

		if (!$data->isPlugin) {
			require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.abstractpublic.php');
			$JFusionPlugin = new JFusionPublic();
		} else {
			$JFusionPlugin = JFusionFactory::getPublic ( $data->jname );

			$sef_suffix = $mainframe->getCfg('sef_suffix');
			$sef = $mainframe->getCfg('sef');
			if($sef_suffix == 1 && $sef == 1 && !count(JRequest::get('POST'))){
				//redirect if url non_sef
				if (strrpos($data->fullURL, '?') !== false) {
					$u = JFactory::getURI();
					if ($u->getVar('Itemid') && $u->getVar('option')) {
						$u->delVar('Itemid');
						$u->delVar('option');
						$jfile = $u->getVar('jfile');
						if ($jfile) {
							$u->delVar('jfile');
						}
						$url = $u->getQuery();
						$url = JFusionFunction::routeURL($jfile.'?'.$url,$data->Itemid,'',true,false);
						$mainframe->redirect($url);
					}
				}
			}

			/*
			 * Caused issues with more people than it helped
			//make sure that the software's database is selected in the case the mysql server and credentials are the same but a different database is used
			$JFusionParam = JFusionFactory::getParams($data->jname);
			$db_name = $JFusionParam->get('database_name');
			if (!empty($db_name)) {
				$db = JFusionFactory::getDatabase($this->jname);
				$query = 'USE '.$db_name;
				$db->setQuery($query);
				$db->query();
			}
			*/
		}

		//get Joomla session token so we can reset it afterward in case the software closes the session
		$session = JFactory::getSession();
		$token = $session->getToken();

		$REQUEST = $_REQUEST; // backup variables

		$JFusionPlugin->data = $data;
		$JFusionPlugin->getBuffer ( $data );

		$_REQUEST = $REQUEST; // restore backup

		//restore session token
		$session->set('session.token', $token);

		//clear the page title
		if (! empty ( $data->buffer )) {
			if (JFusionFunction::isJoomlaVersion('1.6')) {
				$document->setTitle('');
			} else {
				$mainframe->setPageTitle('');
			}
		}

		//check to see if the Joomla database is still connected in case the plugin messed it up
		JFusionFunction::reconnectJoomlaDb();

		if ($data->buffer === 0) {
			JError::raiseWarning ( 500, JText::_ ( 'NO_FRAMELESS' ) );
			$result = false;
			return $result;
		}

		if (! $data->buffer) {
			JError::raiseWarning ( 500, JText::_ ( 'NO_BUFFER' ) );
			$result = false;
			return $result;
		}

		$data->buffer = JFusionFrameless::parseEncoding($data->buffer);

		//we set the backtrack_limit to twice the buffer length just in case!
		$backtrack_limit = ini_get ( 'pcre.backtrack_limit' );
		ini_set ( 'pcre.backtrack_limit', strlen ( $data->buffer ) * 2 );

		$JFusionPlugin->parseBuffer($data);

		// Check if we found something
		if (! strlen ( $data->header ) || ! strlen ( $data->body )) {
			if (! empty ( $data->buffer )) {
				//non html output, return without parsing
				die ( $data->buffer );
			}
			else {
				unset ( $data->buffer );
				//no output returned
				JError::raiseWarning ( 500, JText::_ ( 'NO_HTML' ) );
			}
		}
		else {
			unset ( $data->buffer );
			// Add the header information
			if (isset ( $data->header )) {
				$regex_header = array ();
				$replace_header = array ();

				//change the page title
				$pattern = '#<title>(.*?)<\/title>#si';
				preg_match ( $pattern, $data->header, $page_title );

				if (JFusionFunction::isJoomlaVersion('1.6')) {
					$document->setTitle( html_entity_decode ( $page_title [1], ENT_QUOTES, "utf-8" ) );
				} else {
					$mainframe->setPageTitle ( html_entity_decode ( $page_title [1], ENT_QUOTES, "utf-8" ) );
				}

				$regex_header [] = $pattern;
				$replace_header [] = '';

				//set meta data to that of software
				$meta = array ('keywords', 'description', 'robots' );

				foreach ( $meta as $m ) {
					$pattern = '#<meta name=["|\']' . $m . '["|\'](.*?)content=["|\'](.*?)["|\'](.*?)>#Si';
					if (preg_match ( $pattern, $data->header, $page_meta )) {
						if ($page_meta [2]) {
							$document->setMetaData ( $m, $page_meta [2] );
						}
						$regex_header [] = $pattern;
						$replace_header [] = '';
					}
				}

				$pattern = '#<meta name=["|\']generator["|\'](.*?)content=["|\'](.*?)["|\'](.*?)>#Si';
				if (preg_match ( $pattern, $data->header, $page_generator )) {
					if ($page_generator [2]) {
						$document->setGenerator ( $document->getGenerator () . ', ' . $page_generator [2] );
					}
					$regex_header [] = $pattern;
					$replace_header [] = '';
				}

				//use Joomla default
				$regex_header [] = '#<meta http-equiv=["|\']Content-Type["|\'](.*?)>#Si';
				$replace_header [] = '';

				//remove above set meta data from software's header
				$data->header = preg_replace ( $regex_header, $replace_header, $data->header );

				$JFusionPlugin->parseHeader ( $data );

				if ($data->default_css) {
					$document->addStyleSheet(JURI::base().'/components/com_jfusion/css/default.css');
				}
				if ($data->default_css_overflow) {
					$style = 'style="overflow: '.$data->default_css_overflow.';"';
					$data->style = $style;
				} else {
					$data->style = '';
				}

				$JFusionPlugin->parseCSS($data,$data->header);

				$document->addCustomTag ( $data->header );

				$pathway = $JFusionPlugin->getPathWay();
				if (is_array($pathway)) {
					$breadcrumbs = & $mainframe->getPathWay();
					foreach ($pathway as $path) {
						$breadcrumbs->addItem($path->title, JFusionFunction::routeURL($path->url, JRequest::getInt('Itemid')));
					}
				}
			}

			// Output the body
			if (isset ( $data->body )) {
				$JFusionPlugin->parseCSS($data,$data->body,true);

				JFusionFrameless::parseBody($data);

				// parse the URL's'
				$JFusionPlugin->parseBody ( $data );
			}

			//set the base href (commented out by mariusvr as this caused errors for people using IE)
			//$document->setBase($$baseURL_backup);


			//restore the backtrack_limit
			ini_set ( 'pcre.backtrack_limit', $backtrack_limit );
		}
		return true;
	}

	/**
	 * @param $buffer
	 * @return string
	 */
	function parseEncoding($buffer) {
		if ( preg_match  ( '#<meta.*?content="(.*?); charset=(.*?)".*?/>#isS'  , $buffer , $matches)) {
			if ( stripos  ( $matches[1] , 'text/html' ) !== false && stripos( $matches[2] , 'utf-8' ) === false ) {
				foreach(mb_list_encodings() as $chr) {
					if (stripos( $matches[2] , $chr ) !== false) {
						$buffer = mb_convert_encoding( $buffer , 'UTF-8', $matches[2] );
					}
				}
			}
		}
		return $buffer;
	}

	/**
	 * @param $data
	 * @return mixed
	 */
	function parseBody(&$data) {
		if ( !empty($data->bodyextract) || !empty($data->bodyremove) ) {
			/*
			require_once (JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'parsers' . DS . 'simple_html_dom.php');
			$html = str_get_html($data->body);

			if ( !empty($data->bodyremove) ) {
				$extract = explode(';' , $data->bodyremove);
				foreach ( $extract as $value ) {
					$elements = $html->find(trim($value));
					if ( $elements ) {
						foreach ( $elements as $element ) {
							$element->outertext = '';
						}
					}
				}
			}
			if ( !empty($data->bodyextract) ) {
				$extract = explode(';' , $data->bodyextract);

				foreach ( $extract as $value ) {
					$elements = $html->find(trim($value));
					if ( $elements ) {
						foreach( $elements as $element ) {
							$data->body = $element->outertext();
							return;
						}
					}
				}
			}
			$data->body = $html->outertext();
			*/
		}
	}
}