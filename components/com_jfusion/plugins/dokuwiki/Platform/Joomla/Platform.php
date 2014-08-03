<?php namespace JFusion\Plugins\dokuwiki\Platform\Joomla;

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage JoomlaExt 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFile;
use JFolder;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Plugin\Platform\Joomla;
use JFusion\Plugins\dokuwiki\Search;
use JFusion\Plugins\dokuwiki\Helper;

use Joomla\Language\Text;
use Joomla\Uri\Uri;
use JPath;
use JRegistry;
use JText;
use Psr\Log\LogLevel;
use stdClass;

/**
 * JFusion Authentication Class for an external Joomla database
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Platform extends Joomla
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

	/************************************************
	 * For JFusion Search Plugin
	 ***********************************************/
	/**
	 * Retrieves the search results to be displayed.  Placed here so that plugins that do not use the database can retrieve and return results
	 * @param string &$text string text to be searched
	 * @param string &$phrase string how the search should be performed exact, all, or any
	 * @param JRegistry &$pluginParam custom plugin parameters in search.xml
	 * @param int $itemid what menu item to use when creating the URL
	 * @param string $ordering
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
	function getSearchResults(&$text, &$phrase, JRegistry &$pluginParam, $itemid, $ordering) {
		$highlights = array();
		$search = new Search($this->getJname());
		$results = $search->ft_pageSearch($text, $highlights);
		//pass results back to the plugin in case they need to be filtered

		$this->filterSearchResults($results, $pluginParam);
		$rows = array();
		$pos = 0;

		foreach ($results as $key => $index) {
			$rows[$pos]->title = JText::_($key);
			$rows[$pos]->text = $search->getPage($key);
			$rows[$pos]->created = $search->getPageModifiedDateTime($key);
			//dokuwiki doesn't track hits
			$rows[$pos]->hits = 0;
			$rows[$pos]->href = \JFusionFunction::routeURL(str_replace(':', ';', $this->getSearchResultLink($key)), $itemid);
			$rows[$pos]->section = JText::_($key);
			$pos++;
		}
		return $rows;
	}

	/**
	 * @param mixed $post
	 *
	 * @return string
	 */
	function getSearchResultLink($post) {
		return 'doku.php?id=' . $post;
	}

	/**
	 * renerate redirect code
	 *
	 * @param string $url
	 * @param int $itemid
	 *
	 * @return string output php redirect code
	 */
	function generateRedirectCode($url, $itemid)
	{
		//create the new redirection code
		$redirect_code = '
//JFUSION REDIRECT START
//SET SOME VARS
$joomla_url = \'' . $url . '\';
$joomla_itemid = ' . $itemid . ';
    ';
		$redirect_code.= '
if (!defined(\'_JEXEC\'))';
		$redirect_code.= '
{
    $QUERY_STRING = array_merge($_GET, $_POST);
    if (!isset($QUERY_STRING[\'id\'])) $QUERY_STRING[\'id\'] = $ID;
    $QUERY_STRING = http_build_query($QUERY_STRING);
    $order = array(\'%3A\', \':\', \'/\');
    $QUERY_STRING = str_replace($order,\';\', $QUERY_STRING);
    $pattern = \'#do=(admin|login|logout)#\';
    if (!preg_match($pattern , $QUERY_STRING)) {
        $file = $_SERVER["SCRIPT_NAME"];
        $break = explode(\'/\', $file);
        $pfile = $break[count($break) - 1];
        $jfusion_url = $joomla_url . \'index.php?option=com_jfusion&Itemid=\' . $joomla_itemid . \'&jfile=\'.$pfile. \'&\' . $QUERY_STRING;
        header(\'Location: \' . $jfusion_url);
        exit;
    }
}
//JFUSION REDIRECT END';
		return $redirect_code;
	}

	/**
	 * @param $action
	 *
	 * @return int
	 */
	function redirectMod($action)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getPluginFile('doku.php', $error, $reason);
		switch($action) {
			case 'reenable':
			case 'disable':
				if ($error == 0) {
					//get the joomla path from the file
					jimport('joomla.filesystem.file');
					$file_data = file_get_contents($mod_file);
					$search = '/(\r?\n)\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/si';
					preg_match_all($search, $file_data, $matches);
					//remove any old code
					if (!empty($matches[1][0])) {
						$file_data = preg_replace($search, '', $file_data);
						if (!JFile::write($mod_file, $file_data)) {
							$error = 1;
						}
					}
				}
				if ($action == 'disable') {
					break;
				}
			case 'enable':
				$joomla_url = Factory::getParams('joomla_int')->get('source_url');
				$joomla_itemid = $this->params->get('redirect_itemid');

				//check to see if all vars are set
				if (empty($joomla_url)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' Joomla URL', $this->getJname());
				} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ItemID', $this->getJname());
				} else if (!$this->isValidItemID($joomla_itemid)) {
					Framework::raise(LogLevel::WARNING, Text::_('MISSING') . ' ItemID ' . Text::_('MUST BE') . ' ' . $this->getJname(), $this->getJname());
				} else {
					if ($error == 0) {
						//get the joomla path from the file
						jimport('joomla.filesystem.file');
						$file_data = file_get_contents($mod_file);
						$redirect_code = $this->generateRedirectCode($joomla_url, $joomla_itemid);

						$search = '/\<\?php/si';
						$replace = '<?php' . $redirect_code;

						$file_data = preg_replace($search, $replace, $file_data);
						JFile::write($mod_file, $file_data);
					}
				}
				break;
		}
		return $error;
	}

	/**
	 * Used to display and configure the redirect mod
	 *
	 * @param string $name         name of element
	 * @param string $value        value of element
	 * @param string $node         node
	 * @param string $control_name name of controller
	 *
	 * @return string html
	 */
	function showRedirectMod($name, $value, $node, $control_name)
	{
		$error = 0;
		$reason = '';
		$mod_file = $this->getPluginFile('doku.php', $error, $reason);
		if ($error == 0) {
			//get the joomla path from the file
			jimport('joomla.filesystem.file');
			$file_data = file_get_contents($mod_file);
			preg_match_all('/\/\/JFUSION REDIRECT START(.*)\/\/JFUSION REDIRECT END/ms', $file_data, $matches);
			//compare it with our joomla path
			if (empty($matches[1][0])) {
				$error = 1;
				$reason = Text::_('MOD_NOT_ENABLED');
			}
		}
		//add the javascript to enable buttons
		if ($error == 0) {
			//return success
			$text = Text::_('REDIRECTION_MOD') . ' ' . Text::_('ENABLED');
			$disable = Text::_('MOD_DISABLE');
			$update = Text::_('MOD_UPDATE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'disable')">{$disable}</a>
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'reenable')">{$update}</a>
HTML;
		} else {
			$text = Text::_('REDIRECTION_MOD') . ' ' . Text::_('DISABLED') . ': ' . $reason;
			$enable = Text::_('MOD_ENABLE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('redirectMod', 'enable')">{$enable}</a>
HTML;
		}
		return $output;
	}

	/**
	 * Used to display and configure the Auth mod
	 *
	 * @param string $name         name of element
	 * @param string $value        value of element
	 * @param string $node         node
	 * @param string $control_name name of controller
	 *
	 * @return string html
	 */
	function showAuthMod($name, $value, $node, $control_name)
	{
		$error = 0;
		$reason = '';


		$conf = $this->helper->getConf();
		$source_path = $this->params->get('source_path');
		$plugindir = $source_path . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'plugins';

		//check to see if plugin installed and config options available
		jimport('joomla.filesystem.folder');


		if (!is_dir(JPath::clean($plugindir . DIRECTORY_SEPARATOR . 'jfusion')) || empty($conf['jfusion'])) {
			$error = 1;
			$reason = Text::_('MOD_NOT_ENABLED');
		}

		//add the javascript to enable buttons
		if ($error == 0) {
			//return success
			$text = Text::_('AUTHENTICATION_MOD') . ' ' . Text::_('ENABLED');
			$disable = Text::_('MOD_DISABLE');
			$update = Text::_('MOD_UPDATE');

			$output = <<<HTML
            <img src="components/com_jfusion/images/check_good_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('authMod', 'disable')">{$disable}</a>
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('authMod', 'reenable')">{$update}</a>
HTML;
		} else {
			$text = Text::_('AUTHENTICATION_MOD') . ' ' . Text::_('DISABLED') . ': ' . $reason;
			$enable = Text::_('MOD_ENABLE');
			$output = <<<HTML
            <img src="components/com_jfusion/images/check_bad_small.png">{$text}
            <a href="javascript:void(0);" onclick="return JFusion.Plugin.module('authMod', 'enable')">{$enable}</a>
HTML;
		}
		return $output;
	}

	/**
	 * @param $action
	 *
	 * @return bool
	 */
	function authMod($action)
	{
		$error = 0;
		switch($action) {
			case 'reenable':
			case 'disable':
				$source_path = $this->params->get('source_path');
				$plugindir = $source_path . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'jfusion';

				jimport('joomla.filesystem.folder');
				jimport('joomla.filesystem.file');

				//delete the jfusion plugin from Dokuwiki plugin directory

				if (is_dir(JPath::clean($plugindir)) && !JFolder::delete($plugindir)) {
					$error = 1;
				}

				//update the config file
				$config_path = $this->helper->getConfigPath();

				if (is_dir(JPath::clean($config_path))) {
					$config_file = $config_path . 'local.php';
					if (is_file(JPath::clean($config_file))) {
						$file_data = file_get_contents($config_file);
						preg_match_all('/\/\/JFUSION AUTOGENERATED CONFIG START(.*)\/\/JFUSION AUTOGENERATED CONFIG END/ms', $file_data, $matches);
						//remove any old code
						if (!empty($matches[1][0])) {
							$search = '/\/\/JFUSION AUTOGENERATED CONFIG START(.*)\/\/JFUSION AUTOGENERATED CONFIG END/ms';
							$file_data = preg_replace($search, '', $file_data);
						}

						JFile::write($config_file, $file_data);
					}
				}
				if ($action == 'disable') {
					break;
				}
			case 'enable':
				$source_path = $this->params->get('source_path');
				$plugindir = $source_path . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'jfusion';
				$pluginsource = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'dokuwiki' . DIRECTORY_SEPARATOR . 'jfusion';

				//copy the jfusion plugin to Dokuwiki plugin directory
				jimport('joomla.filesystem.folder');
				jimport('joomla.filesystem.file');

				if (JFolder::copy($pluginsource, $plugindir, '', true)) {
					//update the config file
					$cookie_domain = $this->params->get('cookie_domain');
					$cookie_path = $this->params->get('cookie_path');

					$config_path = $this->helper->getConfigPath();

					if (is_dir(JPath::clean($config_path))) {
						$config_file = $config_path . 'local.php';
						if (is_file(JPath::clean($config_file))) {
							$file_data = file_get_contents($config_file);
							preg_match_all('/\/\/JFUSION AUTOGENERATED CONFIG START(.*)\/\/JFUSION AUTOGENERATED CONFIG END/ms', $file_data, $matches);
							//remove any old code
							if (!empty($matches[1][0])) {
								$search = '/\/\/JFUSION AUTOGENERATED CONFIG START(.*)\/\/JFUSION AUTOGENERATED CONFIG END/ms';
								$file_data = preg_replace($search, '', $file_data);
							}
							$joomla_basepath = JPATH_SITE;
							$config_code = <<<PHP
//JFUSION AUTOGENERATED CONFIG START
\$conf['jfusion']['cookie_path'] = '{$cookie_path}';
\$conf['jfusion']['cookie_domain'] = '{$cookie_domain}';
\$conf['jfusion']['joomla'] = 1;
\$conf['jfusion']['joomla_basepath'] = '{$joomla_basepath}';
\$conf['jfusion']['jfusion_plugin_name'] = '{$this->getJname()}';
//JFUSION AUTOGENERATED CONFIG END
PHP;
							$file_data .= $config_code;
							JFile::write($config_file, $file_data);
						}
					}
				}
				break;
		}
		return $error;
	}

	/**
	 * uninstall function is to disable verious mods
	 *
	 * @return array
	 */
	function uninstall()
	{
		$return = true;
		$reasons = array();

		$error = $this->redirectMod('disable');
		if (!empty($error)) {
			$reasons[] = Text::_('REDIRECT_MOD_UNINSTALL_FAILED');
			$return = false;
		}

		$error = $this->authMod('disable');
		if ($error) {
			$reasons[] = Text::_('AUTH_MOD_UNINSTALL_FAILED');
			$return = false;
		}

		return array($return, $reasons);
	}

	/**
	 * @param object $data
	 *
	 * @return void
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
		$source_path = $this->params->get('source_path');

		if (substr($source_path, -1) != DIRECTORY_SEPARATOR) {
			$source_path .= DIRECTORY_SEPARATOR;
		}

		//setup constants needed by Dokuwiki
		$this->helper->defineConstants();

		$mainframe = Factory::getApplication();

		$do = $mainframe->input->get('do');
		if ($do == 'logout') {
			// logout any joomla users
			$mainframe->logout();
			//clean up session
			$session = Factory::getSession();
			$session->close();
			$session->restart();
		} else if ($do == 'login') {
			$credentials['username'] = $mainframe->input->get('u');
			$credentials["password"] = $mainframe->input->get('p');
			if ($credentials['username'] && $credentials['password']) {
				$options['remember'] = $mainframe->input->get('r');
				//                $options["return"] = 'http://.......';
				//                $options["entry_url"] = 'http://.......';
				// logout any joomla users
				$mainframe->login($credentials, $options);
			}
		}
		$index_file = $source_path . 'doku.php';
		if ($mainframe->input->get('jfile') == 'detail.php') $index_file = $source_path . 'lib' . DIRECTORY_SEPARATOR . 'exe' . DIRECTORY_SEPARATOR . 'detail.php';

		if ($mainframe->input->get('media')) $mainframe->input->set('media', str_replace(':', '-', $mainframe->input->get('media')));
		require_once JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'phputf8' . DIRECTORY_SEPARATOR . 'mbstring' . DIRECTORY_SEPARATOR . 'core.php';

		define('DOKU_INC', $source_path);
		$hooks = Factory::getPlayform($data->platform, $this->getJname())->hasFile('hooks.php');

		if ($hooks) {
			require_once $source_path . 'inc' . DIRECTORY_SEPARATOR . 'events.php';
			require_once $source_path . 'inc' . DIRECTORY_SEPARATOR . 'init.php';

			require_once $hooks;

			$hook = new \Hooks();
			/**
			 * @ignore
			 * @var $EVENT_HANDLER \Doku_Event_Handler
			 */
			$hook->register($EVENT_HANDLER);
		}

		if (!is_file($index_file)) {
			Framework::raise(LogLevel::WARNING, 'The path to the DokuWiki index file set in the component preferences does not exist', $this->getJname());
		} else {
			//set the current directory to dokuwiki
			chdir($source_path);
			// Get the output

			ob_start();
			$rs = include_once ($index_file);
			$data->buffer = ob_get_contents();
			ob_end_clean();

			if (ob_get_contents() !== false) {
				$data->buffer = ob_get_contents() . $data->buffer;
				ob_end_clean();
				ob_start();
			}

			//restore the __autoload handler
			spl_autoload_register(array('JLoader', 'load'));

			//change the current directory back to Joomla. 5*60
			chdir(JPATH_SITE);
			// Log an error if we could not include the file
			if (!$rs) {
				Framework::raise(LogLevel::WARNING, 'Could not find DokuWiki in the specified directory', $this->getJname());
			}
		}
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function parseBody(&$data) {
		$regex_body = array();
		$replace_body = array();
		$callback_body = array();

		$uri = new Uri($data->integratedURL);
		$path = $uri->getPath();

		$regex_body[] = '#(href|action|src)=["\']' . preg_quote($data->integratedURL, '#') . '(.*?)["\']#mS';
		$replace_body[] = '$1="/$2"';
		$callback_body[] = '';

		$regex_body[] = '#(href|action|src)=["\']' . preg_quote($path, '#') . '(.*?)["\']#mS';
		$replace_body[] = '$1="/$2"';
		$callback_body[] = '';

		$regex_body[] = '#(href)=["\']/feed.php["\']#mS';
		$replace_body[] = '$1="' . $data->integratedURL . 'feed.php"';
		$callback_body[] = '';

		$regex_body[] = '#href=["\']/(lib/exe/fetch.php)(.*?)["\']#mS';
		$replace_body[] = 'href="' . $data->integratedURL . '$1$2"';
		$callback_body[] = '';

		$regex_body[] = '#href=["\']/(_media/)(.*?)["\']#mS';
		$replace_body[] = 'href="' . $data->integratedURL . '$1$2"';
		$callback_body[] = '';

		$regex_body[] = '#href=["\']/(lib/exe/mediamanager.php)(.*?)["\']#mS';
		$replace_body[] = 'href="' . $data->integratedURL . '$1$2"';
		$callback_body[] = '';

		$regex_body[] = '#(?<=href=["\'])(?!\w{0,10}://|\w{0,10}:)(.*?)(?=["\'])#mSi';
		$replace_body[] = '';
		$callback_body[] = 'fixUrl';

		$regex_body[] = '#(src)=["\'][./|/](.*?)["\']#mS';
		$replace_body[] = '$1="' . $data->integratedURL . '$2"';
		$callback_body[] = '';

		foreach ($regex_body as $k => $v) {
			//check if we need to use callback
			if(!empty($callback_body[$k])){
				$data->body = preg_replace_callback($regex_body[$k], array(&$this, $callback_body[$k]), $data->body);
			} else {
				$data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
			}
		}

		$this->replaceForm($data);
	}

	/**
	 * @param object $data
	 *
	 * @return void
	 */
	function parseHeader(&$data) {
		static $regex_header, $replace_header;
		if (!$regex_header || !$replace_header) {
			// Define our preg arrays
			$regex_header = array();
			$replace_header = array();
			/*
			$uri = new JUri($data->integratedURL);
			$path = $uri->getPath();

			$regex_header[]    = '#(href|src)=["\']'.preg_quote($data->integratedURL, '#').'(.*?)["\']#mS';
			$replace_header[]    = '$1="/$2"';
			$regex_header[]    = '#(href|src)=["\']'.preg_quote($path, '#').'(.*?)["\']#mS';
			$replace_header[]    = '$1="/$2"';

			//convert relative links into absolute links
			$regex_header[]    = '#(href|src)=["\'][./|/](.*?)["\']#mS';
			$replace_header[] = '$1="' . $data->integratedURL . '$2"';
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

		$q = urldecode($q);
		$q = str_replace(':', ';', $q);
		if (strpos($q, '#') === 0) {
			$url = $this->data->fullURL . $q;
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
			if (substr($this->data->baseURL, -1) != '/') {
				//non sef URls
				$url = str_replace('?', '&amp;', $url);
				$url = $this->data->baseURL . '&amp;jfile=' . $url;
			} else {
				$sefmode = $this->params->get('sefmode');
				if ($sefmode == 1) {
					$url = Factory::getApplication()->routeURL($url, Factory::getApplication()->input->getInt('Itemid'));
				} else {
					//we can just append both variables
					$url = $this->data->baseURL . $url;
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
		$pattern = '#<form(.*?)action=["\'](.*?)["\'](.*?)>(.*?)</form>#mSsi';
		$getData = '';
		$mainframe = Factory::getApplication();
		if ($mainframe->input->getInt('Itemid')) $getData.= '<input name="Itemid" value="' . $mainframe->input->getInt('Itemid') . '" type="hidden"/>';
		if ($mainframe->input->get('option')) $getData.= '<input name="option" value="' . $mainframe->input->get('option') . '" type="hidden"/>';
		if ($mainframe->input->get('jname')) $getData.= '<input name="jname" value="' . $mainframe->input->get('jname') . '" type="hidden"/>';
		if ($mainframe->input->get('view')) $getData.= '<input name="view" value="' . $mainframe->input->get('view') . '" type="hidden"/>';
		preg_match_all($pattern, $data->body, $links);
		foreach ($links[2] as $key => $value) {
			$method = '#method=["\']post["\']#mS';
			$is_get = true;
			if (preg_match($method, $links[1][$key]) || preg_match($method, $links[3][$key])) {
				$is_get = false;
			}
			$value = $this->fixUrl(array(1 => $links[2][$key]));
			if ($is_get && substr($value, -1) != DIRECTORY_SEPARATOR) $links[4][$key] = $getData . $links[4][$key];
			$data->body = str_replace($links[0][$key], '<form' . $links[1][$key] . 'action="' . $value . '"' . $links[3][$key] . '>' . $links[4][$key] . '</form>', $data->body);
		}
	}

	/**
	 * @return array
	 */
	function getPathWay() {
		$pathway = array();
		$mainframe = Factory::getApplication();
		if ($mainframe->input->get('id')) {
			$bread = explode(';', $mainframe->input->get('id'));
			$url = '';
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
			if ($mainframe->input->get('media') || $mainframe->input->get('do')) {
				if ($mainframe->input->get('media')) {
					$add = $mainframe->input->get('media');
				} else {
					$add = $mainframe->input->get('do');
				}
				$pathway[count($pathway) - 1]->title = $pathway[count($pathway) - 1]->title . ' ( ' . $add . ' )';
			}
		}
		return $pathway;
	}
}
