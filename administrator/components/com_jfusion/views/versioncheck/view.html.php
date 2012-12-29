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
class jfusionViewversioncheck extends JView
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
		$query = 'SELECT name from #__jfusion';
		$db->setQuery($query);
		$plugins = $db->loadObjectList();

		jimport('joomla.version');
		$jfusionurl = new stdClass;
		$jfusionurl->url = 'http://update.jfusion.org/jfusion/joomla?version='.JVersion::getShortVersion();
		$jfusionurl->jnames = array();
		$urls[md5($jfusionurl->url)] = $jfusionurl;
		foreach ($plugins as $plugin) {
			$parser = JFactory::getXMLParser('Simple');
			if($parser->loadFile(JFUSION_PLUGIN_PATH.DS.$plugin->name.DS.'jfusion.xml')) {
				if (isset($parser->document)) {
					$update = $parser->document->getElementByPath('update');
					if ($update) {
						$url = new stdClass;
						$url->url = $update->data();
						$url->jnames = array($plugin->name);
						if (!isset($urls[md5($url->url)])) {
							$urls[md5($url->url)] = $url;
						} else {
							$urls[md5($url->url)]->jnames[] = $plugin->name;
						}
					}
				}
			}
		}

		$JFusionVersion = JText::_('UNKNOWN');
		$system = $jfusion_plugins = $components = array();
		$server_compatible = true;

		foreach ($urls as &$url) {
			$url->data = JFusionFunctionAdmin::getFileData($url->url);
			$parser = JFactory::getXMLParser('Simple');
			if ($parser->loadString($url->data)) {
				if (isset($parser->document)) {
					$JFusionVersionInfo = $parser->document;
					if ( $url->url == $jfusionurl->url) {
						$php = new stdClass;
						$php->oldversion = phpversion();
						$php->version = $JFusionVersionInfo->getElementByPath('system/php')->data();
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
						$joomla->version = $JFusionVersionInfo->getElementByPath('system/joomla')->data();
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
						$mysql->version = $JFusionVersionInfo->getElementByPath('system/mysql')->data();
						$mysql->name = 'MySQL';

						if (version_compare($mysql_version, $mysql->version) == - 1) {
							$mysql->class = 'bad0';
							$server_compatible = false;
						} else {
							$mysql->class = 'good0';
						}
						$system[] = $mysql;

						//check the JFusion component,plugins and modules versions
						$JFusion = $this->getVersionNumber(JPATH_COMPONENT_ADMINISTRATOR . DS . 'jfusion.xml', JText::_('COMPONENT'), 'component', $JFusionVersionInfo);
						$p = $JFusionVersionInfo->getElementByPath('component/version');
						if ($p) {
							$JFusionVersion = $p->data();
						}
						$components[] = $JFusion;
						$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'modules' . DS . 'mod_jfusion_activity' . DS . 'mod_jfusion_activity.xml', JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), 'module/activity', $JFusionVersionInfo);
						$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'modules' . DS . 'mod_jfusion_user_activity' . DS . 'mod_jfusion_user_activity.xml', JText::_('USER') . ' ' . JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), 'module/useractivity', $JFusionVersionInfo);
						$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'modules' . DS . 'mod_jfusion_whosonline' . DS . 'mod_jfusion_whosonline.xml', JText::_('WHOSONLINE') . ' ' . JText::_('MODULE'), 'module/whosonline', $JFusionVersionInfo);
						$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'modules' . DS . 'mod_jfusion_login' . DS . 'mod_jfusion_login.xml', JText::_('LOGIN') . ' ' . JText::_('MODULE'), 'module/login', $JFusionVersionInfo);
						if(JFusionFunction::isJoomlaVersion('1.6')){
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'authentication' . DS . 'jfusion'. DS . 'jfusion.xml', JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'), 'plugin/auth', $JFusionVersionInfo);
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'user' . DS .  'jfusion'. DS . 'jfusion.xml', JText::_('USER') . ' ' . JText::_('PLUGIN'), 'plugin/user', $JFusionVersionInfo);
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'search' . DS .  'jfusion'. DS . 'jfusion.xml', JText::_('SEARCH') . ' ' . JText::_('PLUGIN'), 'plugin/search', $JFusionVersionInfo);
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'content' . DS .  'jfusion'. DS . 'jfusion.xml', JText::_('DISCUSSION') . ' ' . JText::_('PLUGIN'), 'plugin/discussion', $JFusionVersionInfo);
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'system' . DS .  'jfusion'. DS . 'jfusion.xml', JText::_('SYSTEM') . ' ' . JText::_('PLUGIN'), 'plugin/system', $JFusionVersionInfo);
						} else {
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'authentication' . DS . 'jfusion.xml', JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'), 'plugin/auth', $JFusionVersionInfo);
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'user' . DS . 'jfusion.xml', JText::_('USER') . ' ' . JText::_('PLUGIN'), 'plugin/user', $JFusionVersionInfo);
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'search' . DS . 'jfusion.xml', JText::_('SEARCH') . ' ' . JText::_('PLUGIN'), 'plugin/search', $JFusionVersionInfo);
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'content' . DS . 'jfusion.xml', JText::_('DISCUSSION') . ' ' . JText::_('PLUGIN'), 'plugin/discussion', $JFusionVersionInfo);
							$components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'system' . DS . 'jfusion.xml', JText::_('SYSTEM') . ' ' . JText::_('PLUGIN'), 'plugin/system', $JFusionVersionInfo);
						}
					}

					foreach ($plugins as $key => $plugin) {
						if (in_array($plugin->name,$url->jnames)) {
							$jfusion_plugins[] = $this->getVersionNumber(JFUSION_PLUGIN_PATH . DS . $plugin->name . DS . 'jfusion.xml', $plugin->name, 'plugins/'.$plugin->name, $JFusionVersionInfo);
							unset($plugins[$key]);
						}
					}
				}
			}
		}
		foreach ($plugins as $key => $plugin) {
			$jfusion_plugins[] = $this->getVersionNumber(JFUSION_PLUGIN_PATH . DS . $plugin->name . DS . 'jfusion.xml', $plugin->name);
		}
		unset($parser);
		ob_end_clean();
    

		$this->assignRef('up2date', $this->up2date);
		$this->assignRef('server_compatible', $server_compatible);
		$this->assignRef('system', $system);
		$this->assignRef('jfusion_plugins', $jfusion_plugins);
		$this->assignRef('components', $components);
		$this->assignRef('JFusionVersion', $JFusionVersion);
		parent::display($tpl);
	}

	/**
	 * This function allows the version number to be retrieved for JFusion plugins
	 *
	 * @param string $filename   filename
	 * @param string $name       name
	 * @param string $path    version
	 * @param JSimpleXMLElement $xml    version
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
		$output->name = $name;
		$output->updateurl = null;

		$output->version = JText::_('UNKNOWN');
		$output->oldversion = JText::_('UNKNOWN');

		if ($path && $xml) {
			/**
			 * @ignore
			 * @var $element JSimpleXMLElement
			 */
			$element = $xml->getElementByPath($path.'/version');
			if ($element) {
				$output->version = $element->data();
			}
			$element = $xml->getElementByPath($path.'/remotefile');
			if ($element) {
				$output->updateurl = $element->data();
			}
			$element = $xml->getElementByPath($path.'/revision');
			if ($element) {
				$output->rev = trim($element->data());
			}
		}

		if (file_exists($filename) && is_readable($filename)) {
			//get the version number
			/**
			 * @ignore
			 * @var $parser JSimpleXML
			 */
			$parser = JFactory::getXMLParser('Simple');
			$parser->loadFile($filename);
			$output->oldversion = $parser->document->getElementByPath('version')->data();
			$revision = $parser->document->getElementByPath('revision');
			if ($revision) {
				$output->oldrev = trim($revision->data());
			}

			if (version_compare($output->oldversion, $output->version) == - 1 || ($output->oldrev && $output->rev && $output->oldrev != $output->rev )) {
				$output->class = 'bad';
				$this->up2date = false;
			} else {
				$output->updateurl = null;
				$output->class = 'good';
			}

			//cleanup for the next function call
			unset($parser);
		} else {
			JFusionFunction::raiseWarning(JText::_('ERROR'), JText::_('XML_FILE_MISSING') . ' '. JText::_('JFUSION') . ' ' . $name . ' ' . JText::_('PLUGIN'), 1);
		}
		return $output;
	}
}
