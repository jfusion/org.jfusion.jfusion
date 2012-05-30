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
        $url = 'http://jfusion.googlecode.com/svn/branches/jfusion_version.xml';
        if (function_exists('curl_init')) {
            //curl is the preferred function
            $crl = curl_init();
            $timeout = 5;
            curl_setopt($crl, CURLOPT_URL, $url);
            curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
            $JFusionVersionRaw = curl_exec($crl);
            curl_close($crl);
        } else {
            //get the file directly if curl is disabled
            $JFusionVersionRaw = file_get_contents($url);
            if (!strpos($JFusionVersionRaw, '<document>')) {
                //file_get_content is often blocked by hosts, return an error message
                echo JText::_('CURL_DISABLED');
                return;
            }
        }
        $JFusionVersion = JText::_('UNKNOWN');
        $system = $jfusion_plugins = $components = array();
        $up2date = $server_compatible = false;

        $parser = JFactory::getXMLParser('Simple');
        if ($parser->loadString($JFusionVersionRaw)) {
            if (isset($parser->document)) {
                $JFusionVersionInfo = $parser->document;
                $up2date = true;
                $server_compatible = true;

                $php = new stdClass;
                $php->oldversion = phpversion();
                $php->version = $JFusionVersionInfo->php[0]->data();
                $php->name = 'PHP';

                if (version_compare(phpversion(), $JFusionVersionInfo->php[0]->data()) == - 1) {
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
                $joomla->version = $JFusionVersionInfo->joomla[0]->data();
                $joomla->name = 'Joomla';

                //remove any letters from the version
                $joomla_versionclean = preg_replace("[A-Za-z !]", "", $joomla_version);
                if (version_compare($joomla_versionclean, $JFusionVersionInfo->joomla[0]->data()) == - 1) {
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
                $mysql->version = $JFusionVersionInfo->joomla[0]->data();
                $mysql->name = 'MySQL';

                if (version_compare($mysql_version, $JFusionVersionInfo->mysql[0]->data()) == - 1) {
                    $class_name = 'bad0';
                    $server_compatible = false;
                } else {
                    $class_name = 'good0';
                }
                $system[] = $mysql;

                $row_count = 0;
                //check the JFusion component,plugins and modules versions
                $JFusion = $this->getVersionNumber(JPATH_COMPONENT_ADMINISTRATOR . DS . 'jfusion.xml', JText::_('COMPONENT'), $JFusionVersionInfo->component[0]->data(), $row_count, $up2date);
                $components[] = $JFusion;
                $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'modules' . DS . 'mod_jfusion_activity' . DS . 'mod_jfusion_activity.xml', JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), $JFusionVersionInfo->activity[0]->data(), $row_count, $up2date);
                $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'modules' . DS . 'mod_jfusion_user_activity' . DS . 'mod_jfusion_user_activity.xml', JText::_('USER') . ' ' . JText::_('ACTIVITY') . ' ' . JText::_('MODULE'), $JFusionVersionInfo->useractivity[0]->data(), $row_count, $up2date);
                $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'modules' . DS . 'mod_jfusion_whosonline' . DS . 'mod_jfusion_whosonline.xml', JText::_('WHOSONLINE') . ' ' . JText::_('MODULE'), $JFusionVersionInfo->whosonline[0]->data(), $row_count, $up2date);
                $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'modules' . DS . 'mod_jfusion_login' . DS . 'mod_jfusion_login.xml', JText::_('LOGIN') . ' ' . JText::_('MODULE'), $JFusionVersionInfo->login[0]->data(), $row_count, $up2date);
                if(JFusionFunction::isJoomlaVersion('1.6')){
                    $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'authentication' . DS . 'jfusion'. DS . 'jfusion.xml', JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'), $JFusionVersionInfo->auth[0]->data(), $row_count, $up2date);
                    $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'user' . DS .  'jfusion'. DS . 'jfusion.xml', JText::_('USER') . ' ' . JText::_('PLUGIN'), $JFusionVersionInfo->user[0]->data(), $row_count, $up2date);
                    $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'search' . DS .  'jfusion'. DS . 'jfusion.xml', JText::_('SEARCH') . ' ' . JText::_('PLUGIN'), $JFusionVersionInfo->search[0]->data(), $row_count, $up2date);
                    $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'content' . DS .  'jfusion'. DS . 'jfusion.xml', JText::_('DISCUSSION') . ' ' . JText::_('PLUGIN'), $JFusionVersionInfo->discussion[0]->data(), $row_count, $up2date);
                } else {
                    $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'authentication' . DS . 'jfusion.xml', JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'), $JFusionVersionInfo->auth[0]->data(), $row_count, $up2date);
                    $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'user' . DS . 'jfusion.xml', JText::_('USER') . ' ' . JText::_('PLUGIN'), $JFusionVersionInfo->user[0]->data(), $row_count, $up2date);
                    $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'search' . DS . 'jfusion.xml', JText::_('SEARCH') . ' ' . JText::_('PLUGIN'), $JFusionVersionInfo->search[0]->data(), $row_count, $up2date);
                    $components[] = $this->getVersionNumber(JPATH_SITE . DS . 'plugins' . DS . 'content' . DS . 'jfusion.xml', JText::_('DISCUSSION') . ' ' . JText::_('PLUGIN'), $JFusionVersionInfo->discussion[0]->data(), $row_count, $up2date);
                }

                $db = JFactory::getDBO();
                $query = 'SELECT * from #__jfusion';
                $db->setQuery($query);
                $plugins = $db->loadObjectList();
                foreach ($plugins as $plugin) {
                    if (isset($JFusionVersionInfo->{$plugin->name})) {
                        $plugin_version = $JFusionVersionInfo->{$plugin->name};
                        if ($plugin_version[0]->data()) {
                            $version = $plugin_version[0]->data();
                        } else {
                            $version = JText::_('UNKNOWN');
                        }
                    } else {
                        $version = JText::_('UNKNOWN');
                    }
                    $jfusion_plugins[] =$this->getVersionNumber(JFUSION_PLUGIN_PATH . DS . $plugin->name . DS . 'jfusion.xml', $plugin->name . ' ' . JText::_('PLUGIN'), $version, $row_count, $up2date);
                }
            } else {
                echo JText::_('CURL_DISABLED');
                return;
            }
        }
        unset($parser);
        ob_end_clean();
        
        $this->assignRef('up2date', $up2date);
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
     * @param string $version    version
     * @param string &$row_count rowcount
     * @param string &$up2date   up2date
     *
     * @return string nothing
     *
     */
    function getVersionNumber($filename, $name, $version, &$row_count, &$up2date)
    {
    	$output = new stdClass;
    	$output->class = '';
    	$output->rev = '';
    	$output->name = $name;

    	$output->version = JText::_('UNKNOWN');
    	$output->oldversion = JText::_('UNKNOWN');
    	
    	if (file_exists($filename) && is_readable($filename)) {
    		//get the version number
    		$parser = JFactory::getXMLParser('Simple');
    		$parser->loadFile($filename);
    		if (version_compare($parser->document->version[0]->data(), $version) == - 1) {
    			$output->class = 'bad'.$row_count;
    			$up2date = false;
    		} else {
    			$output->class = 'good'.$row_count;
    		}
    		$output->oldversion = $parser->document->version[0]->data();
    		if ($name == JText::_('COMPONENT') && !empty($parser->document->revision[0])) {
    			$output->rev = $parser->document->revision[0]->data();
    		}
			$output->version = $version; 
    	} else {
	    	JFusionFunction::raiseWarning(JText::_('ERROR'), JText::_('XML_FILE_MISSING') . ' '. JText::_('JFUSION') . ' ' . $name . ' ' . JText::_('PLUGIN'), 1);
		}
		//cleanup for the next function call
		unset($parser);
		if ($row_count == 1) {
			$row_count = 0;
		} else {
			$row_count = 1;
		}
		return $output;
    }    
}
