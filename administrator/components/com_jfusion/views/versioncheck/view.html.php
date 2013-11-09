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
	 * @var boolean $server_compatible
	 */
	var $server_compatible = true;

	/**
	 * @var array $system
	 */
	var $system = array();

	/**
	 * @var array $jfusion_plugins
	 */
	var $jfusion_plugins = array();

	/**
	 * @var array $components
	 */
	var $components = array();

	/**
	 * @var string $JFusionVersion
	 */
	var $JFusionVersion;

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

		$document = JFactory::getDocument();
		$document->addScript('components/com_jfusion/views/' . $this->getName() . '/tmpl/default.js');

		JFusionFunction::loadJavascriptLanguage(array('UPGRADE_CONFIRM_PLUGIN', 'UPGRADE_CONFIRM_BUILD', 'UPGRADE_CONFIRM_GIT', 'UPGRADE_CONFIRM_RELEASE', 'UPGRADE'));

		$db = JFactory::getDBO();

		$query = $db->getQuery(true)
			->select('name')
			->from('#__jfusion')
			->where('original_name IS NULL');

		$db->setQuery($query);
		$plugins = $db->loadObjectList();

		jimport('joomla.version');
		$jversion = new JVersion();
		$jfusionurl = new stdClass;
		$jfusionurl->url = 'http://update.jfusion.org/jfusion/joomla/?version=' . $jversion->getShortVersion();
		$jfusionurl->jnames = array();
		$urls[md5($jfusionurl->url)] = $jfusionurl;
		foreach ($plugins as $plugin) {
			$xml = JFusionFunction::getXml(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $plugin->name . DIRECTORY_SEPARATOR . 'jfusion.xml');
			if($xml) {
				$update = $xml->update;
				if ($update) {
					$u = trim((string)$update);
					if (strpos($u, '?') === false) {
						$u .= '?version=' . $jversion->getShortVersion();
					} else {
						$u .= '&version=' . $jversion->getShortVersion();
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

		$this->JFusionVersion = JText::_('UNKNOWN');

		foreach ($urls as &$url) {
			$url->data = JFusionFunctionAdmin::getFileData($url->url);
			$xml = JFusionFunction::getXml($url->data, false);
			if ($xml) {
				if ($url->url == $jfusionurl->url) {
					$php = new stdClass;
					$php->oldversion = phpversion();
					$php->version = (string)$xml->system->php;
					$php->name = 'PHP';

					if (version_compare(phpversion(), $php->version) == - 1) {
						$php->class = 'bad0';
						$this->server_compatible = false;
					} else {
						$php->class = 'good0';
					}
					$this->system[] = $php;

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
						$this->server_compatible = false;
					} else {
						$joomla->class = 'good1';
					}
					$this->system[] = $joomla;

					$mysql = new stdClass;
					$db = JFactory::getDBO();
					$mysql_version = $db->getVersion();

					$mysql->oldversion = $mysql_version;
					$mysql->version = (string)$xml->system->mysql;
					$mysql->name = 'MySQL';

					if (version_compare($mysql_version, $mysql->version) == - 1) {
						$mysql->class = 'bad0';
						$this->server_compatible = false;
					} else {
						$mysql->class = 'good0';
					}
					$this->system[] = $mysql;

					//check the JFusion component,plugins and modules versions
					$JFusion = $this->getVersionNumber(JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('COMPONENT'), $xml->component[0]);
					if ($xml->component->version) {
						$this->JFusionVersion = (string)$xml->component->version;
					}
					$this->components[] = $JFusion;
					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_activity' . DIRECTORY_SEPARATOR . 'mod_jfusion_activity.xml', JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), $xml->module->activity);
					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_user_activity' . DIRECTORY_SEPARATOR . 'mod_jfusion_user_activity.xml', JText::_('USER') . ' ' . JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), $xml->module->useractivity);
					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_whosonline' . DIRECTORY_SEPARATOR . 'mod_jfusion_whosonline.xml', JText::_('WHOSONLINE') . ' ' . JText::_('MODULE'), $xml->module->whosonline);
					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'mod_jfusion_login' . DIRECTORY_SEPARATOR . 'mod_jfusion_login.xml', JText::_('LOGIN') . ' ' . JText::_('MODULE'), $xml->module->login);

					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR . 'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'), $xml->plugin->auth);
					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('USER') . ' ' . JText::_('PLUGIN'), $xml->plugin->user);
					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('SEARCH') . ' ' . JText::_('PLUGIN'), $xml->plugin->search);
					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('DISCUSSION') . ' ' . JText::_('PLUGIN'), $xml->plugin->discussion);
					$this->components[] = $this->getVersionNumber(JPATH_SITE . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'jfusion'. DIRECTORY_SEPARATOR . 'jfusion.xml', JText::_('SYSTEM') . ' ' . JText::_('PLUGIN'), $xml->plugin->system);
				}

				foreach ($plugins as $key => $plugin) {
					if (in_array($plugin->name, $url->jnames)) {
						$this->jfusion_plugins[] = $this->getVersionNumber(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $plugin->name . DIRECTORY_SEPARATOR . 'jfusion.xml', $plugin->name, $xml->plugins->{$plugin->name});
						unset($plugins[$key]);
					}
				}
			}
		}
		foreach ($plugins as $plugin) {
			$this->jfusion_plugins[] = $this->getVersionNumber(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $plugin->name . DIRECTORY_SEPARATOR . 'jfusion.xml', $plugin->name);
		}
		ob_end_clean();

		$js=<<<JS
			JFusion.version = '{$this->JFusionVersion}';
JS;

		$document->addScriptDeclaration($js);

		parent::display($tpl);
	}

	/**
	 * This function allows the version number to be retrieved for JFusion plugins
	 *
	 * @param string $filename   filename
	 * @param string $name       name
	 * @param SimpleXMLElement $xml    version
	 *
	 * @return string nothing
	 *
	 */
	function getVersionNumber($filename, $name, $xml=null)
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

		if ($xml) {
			if ($xml->version) {
				$output->version = (string)$xml->version;
			}
			if ($xml->remotefile) {
				$output->updateurl = (string)$xml->remotefile;
			}
			if ($xml->revision) {
				$output->rev = trim((string)$xml->revision);
			}
			if ($xml->timestamp) {
				$output->date = (int) trim((string)$xml->timestamp);
			}
		}

		if (file_exists($filename) && is_readable($filename)) {
			//get the version number
			$xml = JFusionFunction::getXml($filename);

			$output->oldversion = (string)$xml->version;
			if ($xml->revision) {
				$output->oldrev = trim((string)$xml->revision);
			}
			if ($xml->timestamp) {
				$output->olddate = (int) trim((string)$xml->timestamp);
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
			JFusionFunction::raiseError(JText::_('XML_FILE_MISSING') . ' '. JText::_('JFUSION') . ' ' . $name . ' ' . JText::_('PLUGIN'), $name);
		}
		return $output;
	}
}
