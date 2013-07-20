<?php

/**
 * This is view file for versioncheck
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Versioncheck
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Renders the main admin screen that shows the configuration overview of all integrations
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Versioncheck
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewversioncheck extends JViewLegacy
{
	var $up2date = true;
	var $pluginsup2date = true;
	/**
	 * displays the view
	 *
	 * @param string $tpl template name
	 *
	 * @return mixed|string html output of view
	 */
	function display($tpl = null)
	{
		//get the jfusion news
		ob_start();

		$db = JFactory::getDBO();
		$query = 'SELECT name from #__jfusion WHERE original_name IS NULL';
		$db->setQuery($query);
		$plugins = $db->loadObjectList();

		jimport('joomla.version');
		$jversion = new JVersion();
		$jfusionurl = new stdClass;
		$jfusionurl->url = 'http://update.jfusion.org/jfusion/joomla/?version='.$jversion->getShortVersion();
		$jfusionurl->jnames = array();
		$urls[md5($jfusionurl->url)] = $jfusionurl;
		foreach ($plugins as $plugin) {
			$xml = JFusionFunction::getXml(JFUSION_PLUGIN_PATH.DIRECTORY_SEPARATOR.$plugin->name.DIRECTORY_SEPARATOR.'jfusion.xml');
			if($xml) {
				$update = $xml->update;
				if ($update) {
					$u = trim((string)$update);
					if (strpos($u,'?') === false) {
						$u .= '?version='.$jversion->getShortVersion();
					} else {
						$u .= '&version='.$jversion->getShortVersion();
					}
					if (!isset($urls[md5($u)])) {
						$url = new stdClass();
						$url->url = $u;

						$url->jnames = array($plugin->name);

						$urls[md5($u)] = $url;
					} else {
						$urls[md5($u)]->jnames[] = $plugin->name;
					}
				}
			}
		}

		$JFusionVersion = JText::_('UNKNOWN');
		$system = $jfusion_plugins = $components = array();
		$server_compatible = true;

		foreach ($urls as &$url) {
			$url->data = JFusionFunctionAdmin::getFileData($url->url);
			$xml = JFusionFunction::getXml($url->data,false);
			if ($xml) {
				if ( $url->url == $jfusionurl->url) {
					$php = new stdClass;
					$php->oldversion = phpversion();
					$php->version = (string)$xml->system->php;
					$php->name = 'PHP';

					if (version_compare(phpversion(), $php->version) == - 1) {
						$php->class = 'bad0';
						$server_compatible = false;
					} else {
						$php->class = 'good0';
					}
					$system[] = $php;

					$joomla = new stdClass;
					$version = new JVersion;
					$joomla_version = $version->getShortVersion();
					$joomla->oldversion = $joomla_version;
					$joomla->version = (string)$xml->system->joomla;
					$joomla->name = 'Joomla';

					//remove any letters from the version
					$joomla_versionclean = preg_replace('[A-Za-z !]', '', $joomla_version);
					if (version_compare($joomla_versionclean, $joomla->version) == - 1) {
						$joomla->class = 'bad1';
						$server_compatible = false;
					} else {
						$joomla->class = 'good1';
					}
					$system[] = $joomla;

					$mysql = new stdClass;
					$db = JFactory::getDBO();
					$mysql_version = $db->getVersion();

					$mysql->oldversion = $mysql_version;
					$mysql->version = (string)$xml->system->mysql;
					$mysql->name = 'MySQL';

					if (version_compare($mysql_version, $mysql->version) == - 1) {
						$mysql->class = 'bad0';
						$server_compatible = false;
					} else {
						$mysql->class = 'good0';
					}
					$system[] = $mysql;

					//check the JFusion component,plugins and modules versions
					$JFusion = $this->getVersionNumber(JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('COMPONENT'), 'component', $xml);
					$p = $xml->component->version;
					if ($p) {
						$JFusionVersion = (string)$p;
					}
					$components[] = $JFusion;
					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_activity' . DIRECTORY_SEPARATOR . 'mod_jfusion_activity.xml', JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), 'module/activity', $xml);
					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_user_activity' . DIRECTORY_SEPARATOR . 'mod_jfusion_user_activity.xml', JText::_('USER') . ' ' . JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), 'module/useractivity', $xml);
					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_whosonline' . DIRECTORY_SEPARATOR . 'mod_jfusion_whosonline.xml', JText::_('WHOSONLINE') . ' ' . JText::_('MODULE'), 'module/whosonline', $xml);
					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_login' . DIRECTORY_SEPARATOR . 'mod_jfusion_login.xml', JText::_('LOGIN') . ' ' . JText::_('MODULE'), 'module/login', $xml);

					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR . 'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'), 'plugin/auth', $xml);
					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR .  'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('USER') . ' ' . JText::_('PLUGIN'), 'plugin/user', $xml);
					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR .  'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('SEARCH') . ' ' . JText::_('PLUGIN'), 'plugin/search', $xml);
					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR .  'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('DISCUSSION') . ' ' . JText::_('PLUGIN'), 'plugin/discussion', $xml);
					$components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR .  'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('SYSTEM') . ' ' . JText::_('PLUGIN'), 'plugin/system', $xml);
				}

				foreach ($plugins as $key => $plugin) {
					if (in_array($plugin->name,$url->jnames)) {
						$jfusion_plugins[] = $this->getVersionNumber(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $plugin->name . DIRECTORY_SEPARATOR . 'jfusion.xml', $plugin->name, 'plugins/'.$plugin->name, $xml);
						unset($plugins[$key]);
					}
				}
			}
		}
		foreach ($plugins as $key => $plugin) {
			$jfusion_plugins[] = $this->getVersionNumber(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $plugin->name . DIRECTORY_SEPARATOR . 'jfusion.xml', $plugin->name);
		}
		ob_end_clean();

		$this->server_compatible = $server_compatible;
		$this->system = $system;
		$this->jfusion_plugins = $jfusion_plugins;
		$this->components = $components;
		$this->JFusionVersion = $JFusionVersion;
		parent::display($tpl);
	}

	/**
	 * This function allows the version number to be retrieved for JFusion plugins
	 *
	 * @param string $filename   filename
	 * @param string $name       name
	 * @param string $path    version
	 * @param SimpleXMLElement $xml    version
	 *
	 * @return string nothing
	 *
	 */
	function getVersionNumber($filename, $name, $path=null, $xml=null)
	{
		$output = new stdClass;
		$output->class = '';
		$output->rev = '';
		$output->oldrev = '';
		$output->date = '';
		$output->olddate = '';
		$output->name = $name;
		$output->id = md5($filename);
		$output->updateurl = null;

		$output->version = JText::_('UNKNOWN');
		$output->oldversion = JText::_('UNKNOWN');

		if ($path && $xml) {
			$element = $xml->$path->version;
			if ($element) {
				$output->version = (string)$element;
			}
			$element = $xml->$path->remotefile;
			if ($element) {
				$output->updateurl = (string)$element;
			}
			$element = $xml->$path->revision;
			if ($element) {
				$output->rev = trim((string)$element);
			}
			$element = $xml->$path->timestamp;
			if ($element) {
				$output->date = trim((string)$element);
			}
		}

		if (file_exists($filename) && is_readable($filename)) {
			//get the version number
			$xml = JFusionFunction::getXml($filename);

			$output->oldversion = (string)$xml->version;
			$revision = $xml->revision;
			if ($revision) {
				$output->oldrev = trim((string)$revision);
			}
			$timestamp = $xml->timestamp;
			if ($timestamp) {
				$output->olddate = trim((string)$timestamp);
			}

			if (version_compare($output->oldversion, $output->version) == - 1 || ($output->oldrev && $output->rev && $output->oldrev != $output->rev )) {
				$output->class = 'bad';
				$this->up2date = false;
			} else {
				$output->updateurl = null;
				$output->class = 'good';
			}

			//cleanup for the next function call
		} else {
			JFusionFunction::raiseNotices(JText::_('ERROR'), JText::_('XML_FILE_MISSING') . ' '. JText::_('JFUSION') . ' ' . $name . ' ' . JText::_('PLUGIN'), $name);
		}
		return $output;
	}
}
