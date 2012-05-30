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
class jfusionViewplugineditor extends JView
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
            //hides the main menu and disables the Joomla's navigation menu
            //JRequest::setVar('hidemainmenu', 1);
            // Keep the idea of instanciate the parameters only with the parameters of the XML file from the plugin needed but with a centralized method (JFusionFactory::createParams)
            $parametersInstance = & JFusionFactory::createParams($jname);
            $file = JFUSION_PLUGIN_PATH . DS . $jname . DS . 'jfusion.xml';
            if (file_exists($file)) {
                $parametersInstance->loadSetupFile($file);
            }
            $params = $parametersInstance->getParams();
            
			if (JFusionFunction::isJoomlaVersion()) {
	            jimport('joomla.filesystem.file');
	            $content = JFile::read($file);
	            $content = str_replace(array('<param','</param'),array('<field','</field'),$content);
	
				$xml = JFactory::getXML($content, false);
	            $fields = $xml->xpath('//field');
	            jimport('joomla.form.form');
	            jimport('joomla.form.helper');
	            $form = new JForm($jname,array('control'=>'params'));
				JFormHelper::addFieldPath(JPATH_COMPONENT_ADMINISTRATOR.'/fields');
				foreach ($params as $key => $param) {
					$element = $fields[$key];
					$name = $element->getAttribute('name');
					if ($name!='jfusionbox') {
						$field = JFormHelper::loadFieldType($element->getAttribute('type'), true);
						if ($field) {
							$value = $parametersInstance->get($name, $element->getAttribute('default'));
							$field->setForm($form);
							$field->setup($element, $value);
							$params[$key][0] = $field->label;
							$params[$key][1] = $field->input;
						}
					}
				}
            }
            
            //assign data to view
            $this->assignRef('params', $params);
            $this->assignRef('jname', $jname);
            //output detailed configuration warnings for the plugin
            if (JFusionFunction::validPlugin($jname)) {
                $JFusionPlugin = & JFusionFactory::getAdmin($jname);
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
