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
	 * @var $list JSimpleXMLElement
	 */
	var $list;

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

        //custom for development purposes / local use only; note do not commit your URL to GIT!!!

	    jimport('joomla.version');
	    $jversion = new JVersion();
	    $url = 'http://update.jfusion.org/jfusion/joomla/configs.php?version='.$jversion->getShortVersion();
        $ConfigList = JFusionFunctionAdmin::getFileData($url);

	    $xml = JFusionFunction::getXml($ConfigList,false);

        $this->assignRef('list', $xml);
        $this->assignRef('jname', $jname);

        parent::display($tpl);
    }
}
