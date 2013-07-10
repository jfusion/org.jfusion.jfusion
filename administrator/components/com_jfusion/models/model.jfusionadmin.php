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
	public static function changePluginStatus($element,$folder,$status) {
		//get joomla specs
        $db = JFactory::getDBO();

		$db->setQuery('UPDATE #__extensions SET enabled = ' . $status .' WHERE element =' . $db->Quote($element) . ' and folder = ' . $db->Quote($folder));

        $db->execute();
	}

    /**
     * Saves the posted JFusion component variables
     *
     * @param string $jname name of the JFusion plugin used
     * @param array  $post  Array of JFusion plugin parameters posted to the JFusion component
     * @param boolean $wizard Notes if function was called by wizardresult();
     *
     * @return true|false returns true if successful and false if an error occurred
     */
    public static function saveParameters($jname, $post, $wizard = false)
    {
        $db = JFactory::getDBO();

        if ($wizard) {
            //data submitted by the wizard so merge the data with existing params if they do indeed exist
            $query = 'SELECT params FROM #__jfusion WHERE name = ' . $db->Quote($jname);
            $db->setQuery($query);
            $existing_serialized = $db->loadResult();
            if (!empty($existing_serialized)) {
                $existing_params = unserialize(base64_decode($existing_serialized));
                if (is_array($existing_params)) {
                    $post = array_merge($existing_params, $post);
                }
            }
        }

        //serialize the $post to allow storage in a SQL field
        $serialized = base64_encode(serialize($post));
        //set the current parameters in the jfusion table
        $query = 'UPDATE #__jfusion SET params = ' . $db->Quote($serialized) . ' WHERE name = ' . $db->Quote($jname);
        $db->setQuery($query);
        if (!$db->execute()) {
            //there was an error saving the parameters
            JFusionFunction::raiseWarning(0, $db->stderr());
            $result = false;
        } else {
            //reset the params instance for this plugin
            JFusionFactory::getParams($jname, true);
            $result = true;
        }
        return $result;
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
        if (!JFusionFunctionAdmin::isPluginInstalled('jfusion', 'authentication', false)) {
            JFusionFunction::raiseWarning(0, JText::_('FUSION_MISSING_AUTH'));
            $authPlugin = false;
        }
        if (!JFusionFunctionAdmin::isPluginInstalled('jfusion', 'user', false)) {
            JFusionFunction::raiseWarning(0, JText::_('FUSION_MISSING_USER'));
            $userPlugin = false;
        }
        if ($authPlugin && $userPlugin) {
            $jAuth = JFusionFunctionAdmin::isPluginInstalled('jfusion', 'user', true);
            $jUser = JFusionFunctionAdmin::isPluginInstalled('jfusion', 'authentication', true);
            if (!$jAuth) {
                JFusionFunction::raiseNotice(0, JText::_('FUSION_READY_TO_USE_AUTH'));
            }
            if (!$jUser) {
                JFusionFunction::raiseNotice(0, JText::_('FUSION_READY_TO_USE_USER'));
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
     * @return true|false returns true if successful and false if an error occurred
     */
    public static function isPluginInstalled($element, $folder, $testPublished)
    {
        $db = JFactory::getDBO();

	    $query = 'SELECT enabled FROM #__extensions WHERE element=' . $db->Quote($element) . ' AND folder=' . $db->Quote($folder);

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
     * Raise warning function that can handle arrays
     *
     * @return string display donate information
     */
    public static function displayDonate()
    {
        $msg = JText::_('BANNER_MESSAGE');
        $html =<<<HTML
        <table class="adminform">
            <tr>
                <td>
                    <img src="components/com_jfusion/images/jfusion_logo.png">
                </td>
                <td>
                    <font size="3"><b>{$msg}</b></font>
                </td>
                <td style="width: 15%; text-align: right;">
                    <div id="navButton">
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
                        <a class="navButton" href="#" onclick="$('ppform').submit();return false"></a>
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
     * @param $url
     * @param int $save
     * @param int $unpack
     * @return bool|string|array
     */
    public static function getFileData($url,$save = 0, $unpack = 0)
    {
        ob_start();
        $FileData = false;
        if (function_exists('curl_init')) {
            //curl is the preferred function
            $crl = curl_init();
            $timeout = 5;
            curl_setopt($crl, CURLOPT_URL,$url);
            curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
            $FileData = curl_exec($crl);
            $FileInfo = curl_getinfo($crl);
            curl_close($crl);
            if ($FileInfo['http_code'] != 200) {
                //there was an error
                JFusionFunction::raiseWarning(0,$FileInfo['http_code'] . ' error for file:' . $url);
                $FileData = false;
            }
        } else {
            //see if we can use fopen to get file
            $fopen_check = ini_get('allow_url_fopen');
            if (!empty($fopen_check)) {
                $FileData = file_get_contents($url);
            } else {
                JFusionFunction::raiseWarning(0,JText::_('CURL_DISABLED'));
                $FileData = false;
            }
        }

        if ($save && $FileData !== false) {
            jimport('joomla.installer.helper');
            $filename = JInstallerHelper::getFilenameFromURL($url);
            $config = JFactory::getConfig();
            $target = $config->get('tmp_path').DIRECTORY_SEPARATOR.JInstallerHelper::getFilenameFromURL($url);
            // Write buffer to file
            JFile::write($target, $FileData);
            if ($unpack) {
                $package = JInstallerHelper::unpack($target);
                ob_end_clean();
                $FileData = $package;
            } else {
                ob_end_clean();
                $FileData = $target;
            }
        } else {
            ob_end_clean();
        }
        return $FileData;
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
        $filename = JPATH_ADMINISTRATOR .DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jfusion'.DIRECTORY_SEPARATOR.'jfusion.xml';
        $VersionCurrent = $RevisionCurrent = 0;
        if (file_exists($filename) && is_readable($filename)) {
            //get the version number

	        $xml = JFusionFunction::getXml($filename);

            $VersionCurrent = (string)$xml->version;

            if($includeRev) {
                $RevisionCurrent = trim((string)$xml->revision);
            }
        }
        return array($VersionCurrent, $RevisionCurrent);
    }
}