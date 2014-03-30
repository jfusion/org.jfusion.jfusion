<?php

use JFusion\Factory;

/**
 * Model for all jfusion related function
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
 * Class for general JFusion functions
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionFunction
{
	/**
	 * Creates a JFusion Joomla compatible URL
	 *
	 * @param string  $url    string url to be parsed
	 * @param string  $itemid string itemid of the JFusion menu item or the name of the plugin for direct link
	 * @param string  $jname  optional jname if available to prevent having to find it based on itemid
	 * @param boolean $route  boolean optional switch to send url through JRoute::_() (true by default)
	 * @param boolean $xhtml  boolean optional switch to turn & into &amp; if $route is true (true by default)
	 *
	 * @return string Parsed URL
	 */
	public static function routeURL($url, $itemid, $jname = '', $route = true, $xhtml = true)
	{
		if (!is_numeric($itemid)) {
			if ($itemid == 'joomla_int') {
				//special handling for internal URLs
				if ($route) {
					$url = JRoute::_($url, $xhtml);
				}
			} else {
				//we need to create direct link to the plugin
				$params = Factory::getParams($itemid);
				$url = $params->get('source_url') . $url;
				if ($xhtml) {
					$url = str_replace('&', '&amp;', $url);
				}
			}
		} else {
			//we need to create link to a joomla itemid
			if (empty($jname)) {
				//determine the jname from the plugin
				static $routeURL_jname;
				if (!is_array($routeURL_jname)) {
					$routeURL_jname = array();
				}
				if (!isset($routeURL_jname[$itemid])) {
					$menu = JMenu::getInstance('site');

					$menu_param = $menu->getParams($itemid);
					$plugin_param = unserialize(base64_decode($menu_param->get('JFusionPluginParam')));
					$routeURL_jname[$itemid] = $plugin_param['jfusionplugin'];
					$jname = $routeURL_jname[$itemid];
				} else {
					$jname = $routeURL_jname[$itemid];
				}
			}
			//make the URL relative so that external software can use this function
			$params = Factory::getParams($jname);
			$source_url = $params->get('source_url');
			$url = str_replace($source_url, '', $url);

			$config = Factory::getConfig();
			$sefenabled = $config->get('sef');
			$params = Factory::getParams($jname);
			$sefmode = $params->get('sefmode', 1);
			if ($sefenabled && !$sefmode) {
				//otherwise just tak on the
				$baseURL = static::getPluginURL($itemid, false);
				$url = $baseURL . $url;
				if ($xhtml) {
					$url = str_replace('&', '&amp;', $url);
				}
			} else {
				//fully parse the URL if sefmode = 1
				$u = JUri::getInstance($url);
				$u->setVar('jfile', $u->getPath());
				$u->setVar('option', 'com_jfusion');
				$u->setVar('Itemid', $itemid);
				$query = $u->getQuery(false);
				$fragment = $u->getFragment();
				if (isset($fragment)) {
					$query.= '#' . $fragment;
				}
				if ($route) {
					$url = JRoute::_('index.php?' . $query, $xhtml);
				} else {
					$url = 'index.php?' . $query;
				}
			}
		}
		return $url;
	}

	/**
	 * Updates the discussion bot lookup table
	 * @param int $contentid
	 * @param mixed &$threadinfo object with postid, threadid, and forumid
	 * @param string $jname
	 * @param int $published
	 * @param int $manual
	 *
	 * @return void
	 */
	public static function updateDiscussionBotLookup($contentid, &$threadinfo, $jname, $published = 1, $manual = 0)
	{
		$fdb = JFactory::getDBO();
		$modified = JFactory::getDate()->toUnix();
		$option = JFactory::getApplication()->input->getCmd('option');

		//populate threadinfo with other fields if necessary for content generation purposes
		//mainly used if the thread was just created
		if (empty($threadinfo->component)) {
			$threadinfo->contentid = $contentid;
			$threadinfo->component = $option;
			$threadinfo->modified = $modified;
			$threadinfo->jname = $jname;
			$threadinfo->published = $published;
			$threadinfo->manual = $manual;
		}

		$query = 'REPLACE INTO #__jfusion_discussion_bot SET
					contentid = ' . $contentid . ',
					component = ' . $fdb->quote($option) . ',
					forumid = ' . $threadinfo->forumid . ',
					threadid = ' . $threadinfo->threadid . ',
					postid = ' . $threadinfo->postid . ',
					modified = ' . $fdb->quote($modified) . ',
					jname = ' . $fdb->quote($jname) . ',
					published = ' . $published . ',
					manual = ' . $manual;
		$fdb->setQuery($query);
		$fdb->execute();
	}

	/**
	 * Creates the URL of a Joomla article
	 *
	 * @param stdClass &$contentitem contentitem
	 * @param string $text         string to place as the link
	 * @param string $jname        jname
	 *
	 * @return string link
	 */
	public static function createJoomlaArticleURL(&$contentitem, $text, $jname='')
	{
		$mainframe = JFactory::getApplication();
		$option = $mainframe->input->get('option');

		if ($option == 'com_k2') {
			include_once JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_k2' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'route.php';
			/** @noinspection PhpUndefinedClassInspection */
			$article_url = urldecode(K2HelperRoute::getItemRoute($contentitem->id . ':' . urlencode($contentitem->alias), $contentitem->catid . ':' . urlencode($contentitem->category->alias)));
		} else {
			if (empty($contentitem->slug) || empty($contentitem->catslug)) {
				//article was edited and saved from editor
				$db = JFactory::getDBO();

				$query = $db->getQuery(true)
					->select('CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug, CASE WHEN CHAR_LENGTH(cc.alias) THEN CONCAT_WS(":", cc.id, cc.alias) ELSE cc.id END as catslug')
					->from('#__content AS a')
					->leftJoin('#__categories AS cc ON a.catid = cc.id')
					->where('a.id = ' . $contentitem->id);

				$db->setQuery($query);
				$result = $db->loadObject();

				if (!empty($result)) {
					$contentitem->slug = $result->slug;
					$contentitem->catslug = $result->catslug;
				}
			}

			include_once JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_content'  . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'route.php';
			$article_url = ContentHelperRoute::getArticleRoute($contentitem->slug, $contentitem->catslug);
		}

		if ($mainframe->isAdmin()) {
			//setup JRoute to use the frontend router
			$app = JFactory::getApplication('site');
			$router = $app->getRouter();
			/**
			 * @ignore
			 * @var $uri JUri
			 */
			$uri = $router->build($article_url);
			$article_url = $uri->toString();
			//remove /administrator from path
			$article_url = str_replace('/administrator', '', $article_url);
		} else {
			$article_url = JRoute::_($article_url);
		}

		//make the URL absolute and clean it up a bit
		$joomla_url = JFusionFunction::getJoomlaURL();

		$juri = new JUri($joomla_url);
		$path = $juri->getPath();
		if ($path != '/') {
			$article_url = str_replace($path, '', $article_url);
		}

		if (substr($joomla_url, -1) == '/') {
			if ($article_url[0] == '/') {
				$article_url = substr($joomla_url, 0, -1) . $article_url;
			} else {
				$article_url = $joomla_url . $article_url;
			}
		} else {
			if ($article_url[0] == '/') {
				$article_url = $joomla_url . $article_url;
			} else {
				$article_url = $joomla_url . '/' . $article_url;
			}
		}

		$link = '<a href="' . $article_url . '">' . $text . '</a>';

		return $link;
	}

	/**
	 * Reconnects Joomla DB if it gets disconnected
	 *
	 * @return string nothing
	 */
	public static function reconnectJoomlaDb()
	{
		//check to see if the Joomla database is still connected
		$db = JFactory::getDBO();
		jimport('joomla.database.database');
		jimport('joomla.database.table');
		$conf = JFactory::getConfig();
		$database = $conf->get('db');
		$connected = true;
		if (!method_exists($db, 'connected')){
			$connected = false;
		} elseif (!$db->connected()){
			$connected = false;
		}

		if (!$connected) {
			$db->disconnect();
			$db->connect();
		}
		//try to select the joomla database
		if (!$db->select($database)) {
			//oops database select failed
			die('JFusion error: could not select Joomla database when trying to restore Joomla database object');
		} else {
			//database reconnect successful, some final tidy ups

			//add utf8 support
			$db->setQuery('SET names \'utf8\'');
			$db->execute();
			//legacy $database must be restored
			if (JPluginHelper::getPlugin('system', 'legacy')) {
				$GLOBALS['database'] = $db;
			}
		}
	}

	/**
	 * Gets the source_url from the joomla_int plugin
	 *
	 * @return string Joomla source URL
	 */
	public static function getJoomlaURL()
	{
		static $joomla_source_url;
		if (empty($joomla_source_url)) {
			$params = Factory::getParams('joomla_int');
			$joomla_source_url = $params->get('source_url', '/');
		}
		return $joomla_source_url;
	}

	/**
	 * Gets the base url of a specific menu item
	 *
	 * @param int $itemid int id of the menu item
	 * @param boolean $xhtml  return URL with encoded ampersands
	 *
	 * @return string parsed base URL of the menu item
	 */
	public static function getPluginURL($itemid, $xhtml = true)
	{
		static $jfusionPluginURL;
		if (!is_array($jfusionPluginURL)) {
			$jfusionPluginURL = array();
		}
		if (!isset($jfusionPluginURL[$itemid])) {
			$joomla_url = JFusionFunction::getJoomlaURL();
			$baseURL = JRoute::_('index.php?option=com_jfusion&Itemid=' . $itemid, false);
			if (!strpos($baseURL, '?')) {
				$baseURL = preg_replace('#\.[\w]{3,4}\z#is', '', $baseURL);
				if (substr($baseURL, -1) != '/') {
					$baseURL.= '/';
				}
			}
			$juri = new JUri($joomla_url);
			$path = $juri->getPath();
			if ($path != '/') {
				$baseURL = str_replace($path, '', $baseURL);
			}
			if (substr($joomla_url, -1) == '/') {
				if ($baseURL[0] == '/') {
					$baseURL = substr($joomla_url, 0, -1) . $baseURL;
				} else {
					$baseURL = $joomla_url . $baseURL;
				}
			} else {
				if ($baseURL[0] == '/') {
					$baseURL = $joomla_url . $baseURL;
				} else {
					$baseURL = $joomla_url . '/' . $baseURL;
				}
			}
			$jfusionPluginURL[$itemid] = $baseURL;
		}

		//let's clean up the URL here before passing it
		if($xhtml) {
			$url = str_replace('&', '&amp;', $jfusionPluginURL[$itemid]);
		} else {
			$url = $jfusionPluginURL[$itemid];
		}
		return $url;
	}

	/**
	 * checks if the user is an admin
	 *
	 * @return boolean to indicate admin status
	 */
	public static function isAdministrator()
	{
		$mainframe = JFactory::getApplication();
		if ($mainframe->isAdmin()) {
			//we are on admin side, lets confirm that the user has access to user manager
			$juser = JFactory::getUser();

			if ($juser->authorise('core.manage', 'com_users')) {
				$debug = true;
			} else {
				$debug = false;
			}
		} else {
			$debug = false;
		}
		return $debug;
	}

	/**
	 * Converts a string to all ascii characters
	 *
	 * @param string $input str to convert
	 *
	 * @return string converted string
	 */
	public static function strtoascii($input)
	{
		$output = '';
		foreach (str_split($input) as $char) {
			$output.= '&#' . ord($char) . ';';
		}
		return $output;
	}

	/**
	 * Retrieves the current timezone based on user preference
	 * Defaults to Joomla global config for timezone
	 * Hopefully the need for this will be deprecated in Joomla 1.6
	 *
	 * @return int timezone in -6 format
	 */
	public static function getJoomlaTimezone()
	{
		static $timezone;
		if (!isset($timezone)) {
			$timezone = JFactory::getConfig()->get('offset');

			$JUser = JFactory::getUser();
			if (!$JUser->guest) {
				$timezone = $JUser->getParam('timezone', $timezone);
			}
		}
		return $timezone;
	}

	/**
	 * Convert a utf-8 joomla string in to a valid encoding matching the table/filed it will be sent to
	 *
	 * @static
	 *
	 * @param string $string string to convert
	 * @param string $jname  used to get the database object, and point to the static stored data
	 * @param string $table  table that we will be looking at
	 * @param string $field  field that we will be looking at
	 *
	 * @throws RuntimeException
	 * @return bool|string
	 */
	public static function encodeDBString($string, $jname, $table, $field) {
		static $data;
		if (!isset($data)) {
			$data = array();
		}

		if (!isset($data[$jname][$table])) {
			$db = Factory::getDatabase($jname);
			$query = 'SHOW FULL FIELDS FROM ' . $table;
			$db->setQuery($query);
			$fields = $db->loadObjectList();

			foreach ($fields as $f) {
				if ($f->Collation) {
					$data[$jname][$table][$f->Field] = $f->Collation;
				}
			}
		}

		if (isset($data[$jname][$table][$field]) ) {
			$encoding = false;
			list($charset) = explode('_', $data[$jname][$table][$field]);
			switch ($charset) {
				case 'latin1':
					$encoding = 'ISO-8859-1';
					break;
				case 'utf8':
					break;
				default:
					throw new RuntimeException('JFusion Encoding support missing: ' . $charset);
					break;
			}
			if ($encoding) {
				if (function_exists ('iconv')) {
					$converted = iconv('utf-8', $encoding, $string);
				} else if (function_exists('mb_convert_encoding')) {
					$converted = mb_convert_encoding($string, $encoding, 'utf-8');
				} else {
					throw new RuntimeException('JFusion: missing iconv or mb_convert_encoding');
				}
				if ($converted !== false) {
					$string = $converted;
				} else {
					throw new RuntimeException('JFusion Encoding failed ' . $charset);
				}
			}
		}
		return $string;
	}

	/**
	 * Check if feature exists
	 *
	 * @static
	 * @param string $jname
	 * @param string $feature feature
	 * @param int $itemid itemid
	 *
	 * @return bool
	 */
	public static function hasFeature($jname, $feature, $itemid = null) {
		$return = false;
		$admin = Factory::getAdmin($jname);
		$public = Factory::getFront($jname);
		$forum = Factory::getForum($jname);
		$user = Factory::getUser($jname);
		switch ($feature) {
			//Admin Features
			case 'wizard':
				$return = $admin->methodDefined('setupFromPath');
				break;
			//Public Features
			case 'search':
				$return = ($public->methodDefined('getSearchQuery') || $public->methodDefined('getSearchResults'));
				break;
			case 'whosonline':
				$return = $public->methodDefined('getOnlineUserQuery');
				break;
			case 'breadcrumb':
				$return = $public->methodDefined('getPathWay');
				break;
			case 'frontendlanguage':
				$return = $public->methodDefined('setLanguageFrontEnd');
				break;
			case 'frameless':
				$return = $public->methodDefined('getBuffer');
				break;
			//Forum Features
			case 'discussion':
				$return = $forum->methodDefined('createThread');
				break;
			case 'activity':
				$return = ($forum->methodDefined('getActivityQuery') || $forum->methodDefined('renderActivityModule'));
				break;
			case 'threadurl':
				$return = $forum->methodDefined('getThreadURL');
				break;
			case 'posturl':
				$return = $forum->methodDefined('getPostURL');
				break;
			case 'profileurl':
				$return = $forum->methodDefined('getProfileURL');
				break;
			case 'avatarurl':
				$return = $forum->methodDefined('getAvatar');
				break;
			case 'privatemessageurl':
				$return = $forum->methodDefined('getPrivateMessageURL');
				break;
			case 'viewnewmessagesurl':
				$return = $forum->methodDefined('getViewNewMessagesURL');
				break;
			case 'privatemessagecounts':
				$return = $forum->methodDefined('getPrivateMessageCounts');
				break;
			//User Features
			case 'useractivity':
				$return = $user->methodDefined('activateUser');
				break;
			case 'duallogin':
				$return = $user->methodDefined('createSession');
				break;
			case 'duallogout':
				$return = $user->methodDefined('destroySession');
				break;
			case 'updatepassword':
				$return = $user->methodDefined('updatePassword');
				break;
			case 'updateusername':
				$return = $user->methodDefined('updateUsername');
				break;
			case 'updateemail':
				$return = $user->methodDefined('updateEmail');
				break;
			case 'updateusergroup':
				$return = $user->methodDefined('updateUsergroup');
				break;
			case 'updateuserlanguage':
				$return = $user->methodDefined('updateUserLanguage');
				break;
			case 'syncsessions':
				$return = $user->methodDefined('syncSessions');
				break;
			case 'blockuser':
				$return = $user->methodDefined('blockUser');
				break;
			case 'activateuser':
				$return = $user->methodDefined('activateUser');
				break;
			case 'deleteuser':
				$return = $user->methodDefined('deleteUser');
				break;
			case 'redirect_itemid':
				if ($itemid) {
					$app = JFactory::getApplication();
					$menus = $app->getMenu('site');
					$item = $menus->getItem($itemid);
					if ($item && $item->params->get('visual_integration') == 'frameless') {
						$return = true;
					}
				}
				break;
			case 'config':
				if ($jname == 'joomla_int') {
					$return = false;
					break;
				}
			case 'any':
				$return = true;
				break;
		}
		return $return;
	}

	/**
	 * @return void
	 */
	public static function initJavaScript() {
		static $js;
		if (!$js) {
			JHtml::_('behavior.framework', true);
			JHTML::_('behavior.modal');
			JHTML::_('behavior.tooltip');

			$document = JFactory::getDocument();
			if ( JFactory::getApplication()->isAdmin() ) {

				$keys = array('SESSION_TIMEOUT', 'NOTICE', 'WARNING', 'MESSAGE', 'ERROR', 'DELETED', 'DELETE_PAIR', 'REMOVE', 'OK');

				$url = JUri::root() . 'administrator/index.php';


				$document->addScript('components/com_jfusion/js/jfusion.js');

			} else {
				$keys = array('SESSION_TIMEOUT', 'NOTICE', 'WARNING', 'MESSAGE', 'ERROR', 'OK');

				$url = JUri::root() . 'index.php';
			}

			static::loadJavascriptLanguage($keys);

			$js=<<<JS
			JFusion.url = '{$url}';
JS;
			$document->addScriptDeclaration($js);
		}
	}

	/**
	 * @param string|array $keys
	 */
	public static function loadJavascriptLanguage($keys) {
		if (!empty($keys)) {
			$document = JFactory::getDocument();

			if (is_array($keys)) {
				foreach($keys as $key) {
					JText::script($key);
				}
			} else {
				JText::script($keys);
			}
		}
	}

	/**
	 * @param string $filename file name or url
	 *
	 * @return boolean|stdClass
	 */
	public static function getImageSize($filename) {
		$result = false;
		ob_start();

		if (strpos($filename, '://') !== false && function_exists('fopen') && ini_get('allow_url_fopen')) {
			$stream = fopen($filename, 'r');

			$rawdata = stream_get_contents($stream, 24);
			if($rawdata) {
				$type = null;
				/**
				 * check for gif
				 */
				if (strlen($rawdata) >= 10 && strpos($rawdata, 'GIF89a') === 0 || strpos($rawdata, 'GIF87a') === 0) {
					$type = 'gif';
				}
				/**
				 * check for png
				 */
				if (!$type && strlen($rawdata) >= 24) {
					$head = unpack('C8', $rawdata);
					$png = array(1 => 137, 2 => 80, 3 => 78, 4 => 71, 5 => 13, 6 => 10, 7 => 26, 8 => 10);
					if ($head === $png) {
						$type = 'png';
					}
				}
				/**
				 * check for jpg
				 */
				if (!$type) {
					$soi = unpack('nmagic/nmarker', $rawdata);
					if ($soi['magic'] == 0xFFD8) {
						$type = 'jpg';
					}
				}
				if (!$type) {
					if ( substr($rawdata, 0, 2) == 'BM' ) {
						$type = 'bmp';
					}
				}
				switch($type) {
					case 'gif':
						$data = unpack('c10', $rawdata);

						$result = new stdClass;
						$result->width = $data[8]*256 + $data[7];
						$result->height = $data[10]*256 + $data[9];
						break;
					case 'png':
						$type = substr($rawdata, 12, 4);
						if ($type === 'IHDR') {
							$info = unpack('Nwidth/Nheight', substr($rawdata, 16, 8));

							$result = new stdClass;
							$result->width = $info['width'];
							$result->height = $info['height'];
						}
						break;
					case 'bmp':
						$header = unpack('H*', $rawdata);
						// Process the header
						// Structure: http://www.fastgraph.com/help/bmp_header_format.html
						// Cut it in parts of 2 bytes
						$header = str_split($header[1], 2);
						$result = new stdClass;
						$result->width = hexdec($header[19] . $header[18]);
						$result->height = hexdec($header[23] . $header[22]);
						break;
					case 'jpg':
						$pos = 0;
						while(1) {
							$pos += 2;
							$data = substr($rawdata, $pos, 9);
							if (strlen($data) < 4) {
								break;
							}
							$info = unpack('nmarker/nlength', $data);
							if ($info['marker'] == 0xFFC0) {
								if (strlen($data) >= 9) {
									$info = unpack('nmarker/nlength/Cprecision/nheight/nwidth', $data);

									$result = new stdClass;
									$result->width = $info['width'];
									$result->height = $info['height'];
								}
								break;
							} else {
								$pos += $info['length'];
								if (strlen($rawdata) < $pos+9) {
									$rawdata .= stream_get_contents($stream, $info['length']+9);
								}
							}
						}
						break;
					default:
						/**
						 * Fallback to original getimagesize this may be slower than the original but safer.
						 */
						$rawdata .= stream_get_contents($stream);
						$temp = tmpfile();
						fwrite($temp, $rawdata);
						$meta_data = stream_get_meta_data($temp);

						$info = getimagesize($meta_data['uri']);

						if ($info) {
							$result = new stdClass;
							$result->width = $info[0];
							$result->height = $info[1];
						}
						fclose($temp);
						break;
				}
			}
			fclose($stream);
		}
		if (!$result) {
			$info = getimagesize($filename);

			if ($info) {
				$result = new stdClass;
				$result->width = $info[0];
				$result->height = $info[1];
			}
		}
		ob_end_clean();
		return $result;
	}

	/**
	 * @param $seed
	 *
	 * @return string
	 */
	public static function getHash($seed)
	{
		return md5(JFactory::getConfig()->get('secret') . $seed);
	}

	/**
	 * @param object $user
	 *
	 * @return \JFusion\User\Userinfo
	 */
	public static function getJoomlaUser($user = null)
	{
		$result = new stdClass();
		foreach($user as $key => $value) {
			if ($key == 'id') {
				$result->userid = $value;
			} else {
				$result->$key = $value;
			}
		}

		$userinfo = new \JFusion\User\Userinfo();
		$userinfo->bind($result, 'joomla_int');
		return $userinfo;
	}
}