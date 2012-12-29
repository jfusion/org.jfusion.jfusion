<?php

/**
 * This is view file for itemidselect
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Itemidselect
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Renders the a screen that allows the user to choose a JFusion integration method
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Itemidselect
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewimportexport extends JView
{
    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed html output of view
     */
    function display($tpl = null)
    {
        $mainframe = JFactory::getApplication();

        $lang = JFactory::getLanguage();
        $lang->load('com_jfusion');

        $jname = JRequest::getVar('jname');

        //custom for development purposes / local use only; note do not commit your URL to SVN!!!

	    jimport('joomla.version');
	    $jversion = new JVersion();
	    $url = 'http://update.jfusion.org/jfusion/joomla/configs.php?version='.$jversion->getShortVersion();
        $ConfigList = JFusionFunctionAdmin::getFileData($url);

        /**
         * @ignore
         * @var $xmlList JSimpleXML
         */
        $xmlList = JFactory::getXMLParser('Simple');
        $xmlList->loadString($ConfigList);

        $this->assignRef('xmlList', $xmlList);
        $this->assignRef('jname', $jname);

        parent::display($tpl);
    }
}
