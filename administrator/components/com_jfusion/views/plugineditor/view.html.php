<?php

/**
 * This is view file for plugineditor
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Plugineditor
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
 * @subpackage Plugineditor
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewplugineditor extends JViewLegacy
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
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //find out the submitted name of the JFusion module
        $jname = JRequest::getVar('jname');
        if ($jname) {
	        // Keep the idea of instanciate the parameters only with the parameters of the XML file from the plugin needed but with a centralized method (JFusionFactory::createParams)
	        $parametersInstance = JFusionFactory::createParams($jname);

	        $file = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'jfusion.xml';
	        $form = new JForm($jname);
	        if (file_exists($file)) {
		        jimport('joomla.filesystem.file');
		        $content = JFile::read($file);

		        /**
		         * @ignore
		         * @var $xml JXMLElement
		         */
		        $xml = JFactory::getXML($content, false);

		        $fields = $xml->form;
		        jimport('joomla.form.form');
		        jimport('joomla.form.helper');

		        JFormHelper::addFieldPath(JPATH_COMPONENT_ADMINISTRATOR.'/fields');

		        $form->load($fields);
		        $params = array();
		        $params['params'] = $parametersInstance->toArray();
		        $form->bind($params);
	        }

	        //assign data to view
	        $this->assignRef('form', $form);
	        $this->assignRef('jname', $jname);
	        //output detailed configuration warnings for the plugin
	        if (JFusionFunction::validPlugin($jname)) {
		        $JFusionPlugin = JFusionFactory::getAdmin($jname);
		        $JFusionPlugin->debugConfig();
	        }
            //render view
            parent::display($tpl);
        } else {
            //report error
            JError::raiseWarning(500, JText::_('NONE_SELECTED'));
        }
    }
}
