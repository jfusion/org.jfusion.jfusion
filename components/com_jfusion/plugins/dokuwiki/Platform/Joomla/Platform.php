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

// no direct access
use JFile;
use JFolder;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Plugin\Platform\Joomla;
use JFusion\Plugins\dokuwiki\Search;
use JFusion\Plugins\dokuwiki\Helper;

use Joomla\Language\Text;
use JPath;
use JRegistry;
use JText;

defined('_JEXEC') or die('Restricted access');

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
					Framework::raiseWarning(Text::_('MISSING') . ' Joomla URL', $this->getJname());
				} else if (empty($joomla_itemid) || !is_numeric($joomla_itemid)) {
					Framework::raiseWarning(Text::_('MISSING') . ' ItemID', $this->getJname());
				} else if (!$this->isValidItemID($joomla_itemid)) {
					Framework::raiseWarning(Text::_('MISSING') . ' ItemID ' . Text::_('MUST BE') . ' ' . $this->getJname(), $this->getJname());
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
}
