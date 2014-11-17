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
use Psr\Log\LogLevel;

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
	    $document->addScript('components/com_jfusion/views/' . $this->getName() . '/tmpl/default.js');
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //find out the submitted name of the JFusion module
        $jname = JFactory::getApplication()->input->get('jname');
        if ($jname) {
	        // Keep the idea of instanciate the parameters only with the parameters of the XML file from the plugin needed but with a centralized method (\JFusion\Factory::createParams)
	        $parametersInstance = \JFusion\Factory::getParams($jname);

	        $JFusionPlugin = \JFusion\Factory::getAdmin($jname);

	        $name = $JFusionPlugin->getName();

	        $file = JFUSION_PLUGIN_PATH . '/' . $name . '/jfusion.xml';
	        $form = new JForm($jname);
	        if (file_exists($file)) {
		        jimport('joomla.filesystem.file');
		        $content = file_get_contents($file);

		        $content = str_replace('<fieldset name="FRAMELESS_OPTIONS"/>', $this->getFramelessOptions(), $content);

		        $xml = \JFusion\Framework::getXML($content, false);

		        $fields = $xml->form;
		        jimport('joomla.form.form');
		        jimport('joomla.form.helper');

		        JFormHelper::addFieldPath(JPATH_COMPONENT_ADMINISTRATOR . '/fields');
		        JFormHelper::addFieldPath(JFUSION_PLUGIN_PATH . '/' . $name . '/fields');

		        $form->load($fields);

		        $file = JFUSION_PLUGIN_PATH . '/' . $name . '/Platform/Joomla/jfusion.xml';
		        if (file_exists($file)) {
			        $content = file_get_contents($file);
			        $xml = \JFusion\Framework::getXML($content, false);
			        $form->load($xml);
		        }

		        $params = array();
		        $params['params'] = $parametersInstance->toArray();
		        $form->bind($params);
	        }

	        //assign data to view
	        $this->form = $form;
	        $this->jname = $jname;
	        //output detailed configuration warnings for the plugin
	        if ($JFusionPlugin->isConfigured()) {
		        try {
			        $JFusionPlugin->debugConfig();
		        } catch (Exception $e) {
			        \JFusion\Framework::raise(LogLevel::ERROR, $e, $JFusionPlugin->getJname());
		        }
	        }
            //render view
            parent::display($tpl);
        } else {
	        throw new RuntimeException(JText::_('NONE_SELECTED'));
        }
    }

	/**
	 * @return string
	 */
	function getFramelessOptions() {
		$xml =<<<XML
            <fieldset name="FRAMELESS_OPTIONS">
                <field name="parse_anchors" type="radio" class="btn-group" default="1" label="PARSE_ANCHORS" description="PARSE_ANCHORS">
                    <option value="1">JYES</option>
                    <option value="0">JNO</option>
                </field>
                <field name="parse_rel_url" type="radio" class="btn-group" default="1" label="PARSE_REL_URL" description="PARSE_REL_URL">
                    <option value="1">JYES</option>
                    <option value="0">JNO</option>
                </field>
                <field name="parse_abs_url" type="radio" class="btn-group" default="1" label="PARSE_ABS_URL" description="PARSE_ABS_URL">
                    <option value="1">JYES</option>
                    <option value="0">JNO</option>
                </field>
                <field name="parse_abs_path" type="radio" class="btn-group" default="1" label="PARSE_ABS_PATH" description="PARSE_ABS_PATH">
                    <option value="1">JYES</option>
                    <option value="0">JNO</option>
                </field>
                <field name="parse_rel_img" type="radio" class="btn-group" default="1" label="PARSE_REL_IMG" description="PARSE_REL_IMG">
                    <option value="1">JYES</option>
                    <option value="0">JNO</option>
                </field>
                <field name="parse_action" type="radio" class="btn-group" default="1" label="PARSE_ACTION" description="PARSE_ACTION">
                    <option value="1">JYES</option>
                    <option value="0">JNO</option>
                </field>
                <field name="parse_popup" type="radio" class="btn-group" default="1" label="PARSE_POPUP" description="PARSE_POPUP">
                    <option value="1">JYES</option>
                    <option value="0">JNO</option>
                </field>
                <field name="parse_redirect" type="radio" class="btn-group" default="1" label="PARSE_REDIRECT" description="PARSE_REDIRECT">
                    <option value="1">JYES</option>
                    <option value="0">JNO</option>
                </field>

                <field name="headermap" default="" type="JFusionPair" label="HEADER_MAP" description="HEADER_MAP_DESCR"/>
                <field name="bodymap" default="" type="JFusionPair" label="BODY_MAP" description="BODY_MAP_DESCR"/>
            </fieldset>
XML;
		return $xml;
	}
}
