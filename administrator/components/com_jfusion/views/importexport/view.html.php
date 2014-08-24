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
class jfusionViewimportexport extends JViewLegacy
{
	/**
	 * @var $list SimpleXMLElement
	 */
	var $list;

	/**
	 * @var $jname string
	 */
	var $jname;

    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed html output of view
     */
    function display($tpl = null)
    {
	    JFactory::getLanguage()->load('com_jfusion');

        $jname = JFactory::getApplication()->input->get('jname');

        //custom for development purposes / local use only; note do not commit your URL to GIT!!!

	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/' . $this->getName() . '/tmpl/default.js');

	    jimport('joomla.version');
	    $jversion = new JVersion();
	    $url = 'http://update.jfusion.org/jfusion/joomla/configs.php?version=' . $jversion->getShortVersion();
        $ConfigList = \JFusion\Framework::getFileData($url);

	    $xml = \JFusion\Framework::getXml($ConfigList, false);

	    try {
		    $xml = \JFusion\Framework::getXml($ConfigList, false);
	    } catch (Exception $e) {
		    $xml = null;
	    }

	    $this->list = $xml;
	    $this->jname = $jname;
        parent::display($tpl);
    }
}
