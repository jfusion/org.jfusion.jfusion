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
	 * @var string $jname
	 */
	var $jname;

	/**
	 * @var JForm $form
	 */
	var $form;

    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @throws RuntimeException
     * @return mixed html output of view
     */
    function display($tpl = null)
    {
	    $document = JFactory::getDocument();
	    $document->addScript('components/com_jfusion/views/'.$this->getName().'/tmpl/default.js');
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //find out the submitted name of the JFusion module
        $jname = JFactory::getApplication()->input->get('jname');
        if ($jname) {
	        // Keep the idea of instanciate the parameters only with the parameters of the XML file from the plugin needed but with a centralized method (JFusionFactory::createParams)
	        $parametersInstance = JFusionFactory::createParams($jname);

	        $file = JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'jfusion.xml';
	        $form = new JForm($jname);
	        if (file_exists($file)) {
		        jimport('joomla.filesystem.file');
		        $content = file_get_contents($file);

		        $xml = JFusionFunction::getXML($content, false);

		        $fields = $xml->form;
		        jimport('joomla.form.form');
		        jimport('joomla.form.helper');

		        JFormHelper::addFieldPath(JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'fields');
		        JFormHelper::addFieldPath(JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $jname . DIRECTORY_SEPARATOR . 'fields');

		        $form->load($fields);
		        $params = array();
		        $params['params'] = $parametersInstance->toArray();
		        $form->bind($params);
	        }

	        //assign data to view
	        $this->form = $form;
	        $this->jname = $jname;
	        //output detailed configuration warnings for the plugin
	        $JFusionPlugin = JFusionFactory::getAdmin($jname);
	        if ($JFusionPlugin->isConfigured()) {
		        $JFusionPlugin->debugConfig();
	        }
            //render view
            parent::display($tpl);
        } else {
	        throw new RuntimeException(JText::_('NONE_SELECTED'));
        }
    }
}
