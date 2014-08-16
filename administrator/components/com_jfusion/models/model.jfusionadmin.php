<?php

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
use Psr\Log\LogLevel;

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
class JFusionFunctionAdmin
{

    /**
     * Changes plugin status in both Joomla 1.5 and Joomla 1.6
     *
     * @param string $element
     * @param string $folder
     * @param int $status
     *
     * @return object master details
     */
	public static function changePluginStatus($element, $folder, $status) {
		//get joomla specs
        $db = JFactory::getDBO();

		$query = $db->getQuery(true)
			->update('#__extensions')
			->set('enabled = ' . $db->quote($status))
			->where('element = ' . $db->quote($element))
			->where('folder = ' . $db->quote($folder));

		$db->setQuery($query);

        $db->execute();
	}

    /**
     * Checks to see if the JFusion plugins are installed and enabled
     *
     * @return string nothing
     */
    public static function checkPlugin()
    {
        $userPlugin = true;
        $authPlugin = true;
        if (!static::isPluginInstalled('jfusion', 'authentication', false)) {
            \JFusion\Framework::raise(LogLevel::WARNING, JText::_('FUSION_MISSING_AUTH'));
            $authPlugin = false;
        }
        if (!static::isPluginInstalled('jfusion', 'user', false)) {
            \JFusion\Framework::raise(LogLevel::WARNING, JText::_('FUSION_MISSING_USER'));
            $userPlugin = false;
        }
        if ($authPlugin && $userPlugin) {
            $jAuth = static::isPluginInstalled('jfusion', 'user', true);
            $jUser = static::isPluginInstalled('jfusion', 'authentication', true);
            if (!$jAuth) {
                \JFusion\Framework::raise(LogLevel::NOTICE, JText::_('FUSION_READY_TO_USE_AUTH'));
            }
            if (!$jUser) {
                \JFusion\Framework::raise(LogLevel::NOTICE, JText::_('FUSION_READY_TO_USE_USER'));
            }
        }
    }


    /**
 * Tests if a plugin is installed with the specified name, where folder is the type (e.g. user)
 *
 * @param string $element       element name of the plugin
 * @param string $folder        folder name of the plugin
 * @param int    $testPublished Variable to determine if the function should test to see if the plugin is published
 *
 * @return boolean returns true if successful and false if an error occurred
 */
	public static function isPluginInstalled($element, $folder, $testPublished)
	{
		$db = JFactory::getDBO();

		$query = $db->getQuery(true)
			->select('enabled')
			->from('#__extensions')
			->where('element = ' . $db->quote($element))
			->where('folder = ' . $db->quote($folder));

		$db->setQuery($query);
		$result = $db->loadResult();
		if ($result) {
			if ($testPublished) {
				$result = ($result == 1);
			} else {
				$result = true;
			}
		} else {
			$result = false;
		}
		return $result;
	}

	/**
	 * Check if the jfusion configuration is ok
	 *
	 * @return boolean returns true if config seems ok
	 */
	public static function isConfigOk()
	{
		$result = true;
		$task = 'cpanel';

		//enable the JFusion login behaviour, but we wanna make sure there is at least 1 master with good config
		$db = JFactory::getDBO();

		$query = $db->getQuery(true)
			->select('count(*)')
			->from('#__jfusion')
			->where('master = 1')
			->where('status = 1');

		$db->setQuery($query);
		if (!$db->loadResult()) {
			$result = false;
			$task = 'plugindisplay';
			\JFusion\Framework::raise(LogLevel::WARNING, JText::_('NO_MASTER_WARNING'));
		} else if (\JFusion\Framework::getUserGroups() === false) {
			// Prevent to loginchecker without any usergroups configured.
			$result = false;
			$task = 'usergroups';
			\JFusion\Framework::raise(LogLevel::WARNING, JText::_('NO_USERGROUPS_ERROR'));
		}

		if ($result === false) {
			$mainframe = JFactory::getApplication();
			$mainframe->redirect('index.php?option=com_jfusion&task=' . $task);
		}
		return $result;
	}

    /**
     * Raise warning function that can handle arrays
     *
     * @return string display donate information
     */
    public static function getDonationBanner()
    {
        $msg = JText::_('BANNER_MESSAGE');
        $html =<<<HTML
        <table class="jfusionform">
            <tr>
            	<!--
                <td>
                    <img src="components/com_jfusion/images/jfusion_logo.png">
                </td>
                -->
                <td>
                    <h1><strong>{$msg}</strong></h1>
                </td>
                <td style="width: 15%; text-align: right;">
                    <div id="jfusionDonateButton">
                        <form id="ppform" action="https://www.paypal.com/cgi-bin/webscr" method="post">
                        <input type="hidden" name="cmd" value="_donations" />
                        <input type="hidden" name="business" value="webmaster@jfusion.org" />
                        <input type="hidden" name="item_name" value="jfusion.org" />
                        <input type="hidden" name="no_shipping" value="0" />
                        <input type="hidden" name="no_note" value="1" />
                        <input type="hidden" name="currency_code" value="AUD" />
                        <input type="hidden" name="tax" value="0" />
                        <input type="hidden" name="lc" value="AU" />
                        <input type="hidden" name="bn" value="PP-DonationsBF" />
                        <a class="jfusionDonateButton" href="#" onclick="$('ppform').submit();return false"></a>
                    </form>
                    </div>
                </td>
            </tr>
        </table>
HTML;
        echo $html;
    }

    /**
     * @static
     * @param bool $includeRev
     *
     * @return array
     */
    public static function currentVersion($includeRev = false)
    {
        //get the current JFusion version number
        $filename = JPATH_ADMINISTRATOR . '/components/com_jfusion/jfusion.xml';
        $VersionCurrent = $RevisionCurrent = 0;
        if (file_exists($filename) && is_readable($filename)) {
            //get the version number

	        $xml = \JFusion\Framework::getXml($filename);

            $VersionCurrent = (string)$xml->version;

            if($includeRev) {
                $RevisionCurrent = trim((string)$xml->revision);
            }
        }
        return array($VersionCurrent, $RevisionCurrent);
    }

	/**
	 * @param JFormField $field
	 *
	 * @return string
	 */
	public static function renderField($field) {
		$label = '';
		if (!$field->hidden) {
			$label =<<<HTML
			<div class="control-label">
				{$field->label}
			</div>
HTML;
		}

		$html=<<<HTML
		<div class="control-group">
			{$label}
			<div class="controls">
				{$field->input}
			</div>
		</div>
HTML;
		return $html;
	}
}