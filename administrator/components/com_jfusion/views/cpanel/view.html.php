<?php

/**
 * This is view file for cpanel
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Cpanel
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
 * @subpackage Cpanel
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewcpanel extends JViewLegacy
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
	    jimport('joomla.version');
	    $jversion = new JVersion();
	    $cpanel = 'http://update.jfusion.org/jfusion/joomla/cpanel.php?version='.$jversion->getShortVersion();
        //define the standard message when the XML is not found
        $curl_disabled = '<?xml version="1.0" standalone="yes"?>
                    <document><item><date></date>
                    <title>' . JText::_('CURL_DISABLED') . '</title>
                    <link>www.jfusion.org</link><body></body></item></document>';
        //prevent any output during parsing
        ob_start();
        //get the jfusion news
        if (function_exists('curl_init')) {
            //curl is the preferred function
            $crl = curl_init();
            $timeout = 5;
            curl_setopt($crl, CURLOPT_URL, $cpanel);
            curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
            $JFusionCpanelRaw = curl_exec($crl);
            curl_close($crl);
        } else {
            //get the file directly if curl is disabled
            $JFusionCpanelRaw = file_get_contents($cpanel);
        }
        if (!strpos($JFusionCpanelRaw, '<document>')) {
            //check curl and file_get_content is often blocked by hosts, return an error message
            $JFusionCpanelRaw = $curl_disabled;
        }

	    $xml = JFusionFunction::getXml($JFusionCpanelRaw,false);

        //end the outputbuffer
        ob_end_clean();
        //pass on the variables to the view
        $this->assignRef('JFusionCpanel', $xml);
        parent::display($tpl);
    }
}
