<?php

/**
 * This is view file for advancedparam
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Advancedparam
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
jimport('joomla.application.component.view');
/**
 * Renders the JFusion Advanced Param view
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Advancedparam
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class jfusionViewadvancedparam extends JView
{
    var $configArray = array(1 => array(1 => "config.xml", 2 => " WHERE status = 1"), 2 => array(1 => "activity.xml", 2 => "WHERE activity = 1 and status = 1"), 3 => array(1 => "search.xml", 2 => " WHERE search = 1 and status =1"), 4 => array(1 => "whosonline.xml", 2 => "WHERE status = 1"), 5 => array(1 => "useractivity.xml", 2 => "WHERE status = 1"));
    var $isJ16;

    /**
     * displays the view
     *
     * @param string $tpl template name
     *
     * @return mixed html output of view
     */
    function display($tpl = null)
    {
        $this->isJ16 = JFusionFunction::isJoomlaVersion('1.6');
        if ($this->isJ16) {
            //include some J1.6+ classes
            jimport('joomla.form.form');
            jimport('joomla.form.formfield');
            jimport('joomla.html.pane');
        }

        $mainframe = JFactory::getApplication();

        $lang = JFactory::getLanguage();
        $lang->load('com_jfusion');

        //Load Current Configfile
        $config = JRequest::getVar('configfile');
        if (empty($config)) {
            $config = null;
        }
        //Load multiselect
        $multiselect = JRequest::getVar('multiselect');
        if ($multiselect) {
            $multiselect = true;
            //Load Plugin XML Parameter
            $params = $this->loadXMLParamMulti($config);
            //Load enabled Plugin List
            list($output, $js) = $this->loadElementMulti($params, $config);
        } else {
            $multiselect = false;
            //Load Plugin XML Parameter
            $params = $this->loadXMLParamSingle($config);
            //Load enabled Plugin List
            list($output, $js) = $this->loadElementSingle($params, $config);
        }
        //load the element number for multiple advanceparam elements
        $elNum = JRequest::getInt('elNum');
        $this->assignRef('elNum', $elNum);

        //Add Document dependent things like javascript, css
        $document = JFactory::getDocument();
        $document->setTitle('Plugin Selection');
        $template = $mainframe->getTemplate();
        $document->addStyleSheet("templates/$template/css/general.css");
        $document->addStyleSheet('components/com_jfusion/css/jfusion.css');
        $css = 'table.adminlist, table.admintable{ font-size:11px; }';
        $document->addStyleDeclaration($css);
        $document->addScriptDeclaration($js);
        $this->assignRef('output', $output);

        //for J1.6+ single select modes, params is an array
        if ($this->isJ16 && empty($multiselect)) {
            $this->assignRef('comp', $params['params']);
        } else {
            $this->assignRef('comp', $params);
        }

        JHTML::_('behavior.modal');
        JHTML::_('behavior.tooltip');
        parent::display($multiselect ? 'multi' : 'single');
    }

    /**
     * Loads a single element
     *
     * @param JParameter $params parameters
     * @param string $config configuration
     *
     * @return string html
     */
    function loadElementSingle($params, $config)
    {
        if ($this->isJ16) {
            $JPlugin = (!empty($params['jfusionplugin'])) ? $params['jfusionplugin'] : '';
        } else {
            $JPlugin = $params->get('jfusionplugin', '');
        }
        $db = JFactory::getDBO();
        $query = 'SELECT name as id, name as name from #__jfusion ' . $this->configArray[$config][2];
        $db->setQuery($query);
        $noSelected = new stdClass();
        $noSelected->id = null;
        $noSelected->name = JText::_("SELECT_ONE");
        $rows = array_merge(array($noSelected), $db->loadObjectList());
        $attributes = array("size" => "1", "class" => "inputbox", "onchange" => "jPluginChange(this);");
        $output = JHTML::_('select.genericlist', $rows, 'params[jfusionplugin]', $attributes, 'id', 'name', $JPlugin);
        $configLink = '';
        if (isset($this->configArray[$config])) {
            $configLink = '&configfile=' . $config;
        }
        $elNum = JRequest::getInt('elNum');
        $js = <<<JS
        function jPluginChange(select) {
            var plugin = select.options[select.selectedIndex].value;
            plugin = 'a:1:{s:13:\"jfusionplugin\";s:'+plugin.length+':\"'+plugin+'\";}';
            var value = encode64(plugin);
            window.location.href = 'index.php?option=com_jfusion&task=advancedparam' +
                                   '&tmpl=component&elNum={$elNum}{$configLink} . "&params='+value;
        }

        function encode64(inp){
            var key='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
            var chr1,chr2,chr3,enc3,enc4,i=0,out='';
            while(i<inp.length){
                chr1=inp.charCodeAt(i++);
                if (chr1>127) {
                    chr1=88;
                }
                chr2=inp.charCodeAt(i++);
                if (chr2>127) {
                    chr2=88;
                }
                chr3=inp.charCodeAt(i++);
                if (chr3>127) {
                    chr3=88;
                }
                if (isNaN(chr3)) {
                    enc4=64;chr3=0;
                } else {
                    enc4=chr3&63;
                }
                if (isNaN(chr2)) {
                    enc3=64;
                    chr2=0;
                } else {
                    enc3=((chr2<<2)|(chr3>>6))&63;
                }
                out+=key.charAt((chr1>>2)&63)+key.charAt(((chr1<<4)|(chr2>>4))&63)+key.charAt(enc3)+key.charAt(enc4);
            }
            return encodeURIComponent(out);
        }
JS;

        return array($output, $js);
    }

    /**
     * Loads a single xml param
     *
     * @param string $config configuration
     *
     * @return array|JParameter html
     */
    function loadXMLParamSingle($config)
    {
        $option = JRequest::getCmd('option');
        //Load current Parameter
        $value = JRequest::getVar('params');
        if (empty($value)) {
            $value = array();
        } else {
            $value = base64_decode($value);
            $value = unserialize($value);
            if (!is_array($value)) {
                $value = array();
            }
        }

        /**
         * @ignore
         * @var $xml JSimpleXML
         */
        $xml = JFactory::getXMLParser('Simple');

        if ($this->isJ16) {
            global $jname;
            $jname = (!empty($value['jfusionplugin'])) ? $value['jfusionplugin'] : '';
            if (isset($this->configArray[$config]) && !empty($jname)) {
                $path = JFUSION_PLUGIN_PATH . DS . $jname . DS . $this->configArray[$config][1];
                $defaultPath = JPATH_ADMINISTRATOR . DS . 'components' . DS . $option . DS . 'views' . DS . 'advancedparam' . DS . 'paramfiles' . DS . $this->configArray[$config][1];
                $xml_path = (file_exists($path)) ? $path : $defaultPath;
                $form = false;
                if ($xml->loadFile($xml_path)) {
                    $fields = $xml->document->getElementByPath('fields');
                    if ($fields) {
                        $data = $fields->toString();
                        //make sure it is surround by <form>
                        if (substr($data, 0, 5) != '<form>') {
                            $data = '<form>' . $data . '</form>';
                        }
                        /**
                         * @ignore
                         * @var $form JForm
                         */
                        $form = JForm::getInstance($jname, $data, array('control' => "params[$jname]"));
                        //add JFusion's fields
                        $form->addFieldPath(JPATH_COMPONENT.DS.'fields');
						if (isset($value[$jname])) {
                        	$form->bind($value[$jname]);
                        }
                    }

                    $this->loadLanguage($xml);
                }
                $value['params'] = $form;
            }
        } else {
            //Load Plugin XML Parameter
            $params = new JParameter('');
            $params->loadArray($value);
            $params->addElementPath(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'elements');
            $JPlugin = $params->get('jfusionplugin', '');
            if (isset($this->configArray[$config]) && !empty($JPlugin)) {
                global $jname;
                $jname = $JPlugin;
                $path = JFUSION_PLUGIN_PATH . DS . $JPlugin . DS . $this->configArray[$config][1];
                $defaultPath = JPATH_ADMINISTRATOR . DS . 'components' . DS . $option . DS . 'views' . DS . 'advancedparam' . DS . 'paramfiles' . DS . $this->configArray[$config][1];
                $xml_path = (file_exists($path)) ? $path : $defaultPath;
                if ($xml->loadFile($xml_path)) {
                    /**
                     * @ignore
                     * @var $xmlparams JSimpleXMLElement
                     */
                    $xmlparams = $xml->document->getElementByPath('params');
                    $params->setXML($xmlparams);
                    $this->loadLanguage($xml);
                }
            }
            $value = $params;
        }
        return $value;
    }

    /**
     * Loads a multi element
     *
     * @param array $params parameters
     * @param string $config configuration
     *
     * @return string html
     */
    function loadElementMulti($params, $config)
    {
        $db = JFactory::getDBO();
        $query = 'SELECT name as id, name as name from #__jfusion ' . $this->configArray[$config][2];
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        //remove plugins that have already been selected
        foreach ($rows AS $k => $v) {
            if (array_key_exists($v->name, $params)) {
                unset($rows[$k]);
            }
        }
        $noSelected = new stdClass();
        $noSelected->id = null;
        $noSelected->name = JText::_("SELECT_ONE");
        $rows = array_merge(array($noSelected), $rows);
        $attributes = array("size" => "1", "class" => "inputbox");
        $output = JHTML::_('select.genericlist', $rows, 'jfusionplugin', $attributes, 'id', 'name');
        $output.= ' <input type="button" value="add" name="add" onclick="jPluginAdd(this);" />';
        $configLink = '';
        if (isset($this->configArray[$config])) {
            $configLink = '&configfile=' . $config;
        }
        $elNum = JRequest::getInt('elNum');
        $js = <<<JS
        function jPluginAdd(button) {
            button.form.jfusion_task.value = 'add';
            button.form.action = 'index.php?option=com_jfusion&task=advancedparam' +
                                   '&tmpl=component&elNum={$elNum}{$configLink}&multiselect=1';
            button.form.submit();
        }
        function jPluginRemove(button, value) {
            button.form.jfusion_task.value = 'remove';
            button.form.jfusion_value.value = value;
            button.form.action = 'index.php?option=com_jfusion&task=advancedparam' +
                                   '&tmpl=component&elNum={$elNum}{$configLink}&multiselect=1';
            button.form.submit();
        }
JS;

        return array($output, $js);
    }

    /**
     * Loads a multi XML param
     *
     * @param string $config configuration
     *
     * @return array html
     */
    function loadXMLParamMulti($config)
    {
        global $jname;
        $option = JRequest::getCmd('option');
        //Load current Parameter
        $value = JRequest::getVar('params');
        if (empty($value)) {
            $value = array();
        } else if (!is_array($value)) {
            $value = base64_decode($value);
            $value = unserialize($value);
            if (!is_array($value)) {
                $value = array();
            }
        }
        $task = JRequest::getVar('jfusion_task');
        if ($task == 'add') {
        	$newPlugin = JRequest::getVar('jfusionplugin');
			if ($newPlugin) {
	            if (!array_key_exists($newPlugin, $value)) {
	                $value[$newPlugin] = array('jfusionplugin' => $newPlugin);
	            } else {
	                $this->assignRef('error', JText::_('NOT_ADDED_TWICE'));
	            }
            } else {
				$this->assignRef('error', JText::_('MUST_SELLECT_PLUGIN'));
            }
        } else if ($task == 'remove') {
            $rmPlugin = JRequest::getVar('jfusion_value');
            if (array_key_exists($rmPlugin, $value)) {
                unset($value[$rmPlugin]);
            } else {
                $this->assignRef('error', JText::_('NOT_PLUGIN_REMOVE'));
            }
        }

        /**
         * @ignore
         * @var $xml JSimpleXML
         */
        $xml = JFactory::getXMLParser('Simple');
        foreach (array_keys($value) as $key) {
            if ($this->isJ16) {
                $jname = $value[$key]['jfusionplugin'];

                if (isset($this->configArray[$config]) && !empty($jname)) {
                    $path = JFUSION_PLUGIN_PATH . DS . $jname . DS . $this->configArray[$config][1];
                    $defaultPath = JPATH_ADMINISTRATOR . DS . 'components' . DS . $option . DS . 'views' . DS . 'advancedparam' . DS . 'paramfiles' . DS . $this->configArray[$config][1];
                    $xml_path = (file_exists($path)) ? $path : $defaultPath;

                    if ($xml->loadFile($xml_path)) {
                        $fields = $xml->document->getElementByPath('fields');
                        if ($fields) {
                            $data = $fields->toString();
                            //make sure it is surround by <form>
                            if (substr($data, 0, 5) != '<form>') {
                                $data = '<form>' . $data . '</form>';
                            }
                            /**
                             * @ignore
                             * @var $form JForm
                             */
                            $form = JForm::getInstance($jname, $data, array('control' => "params[$jname]"));
                            //add JFusion's fields
                            $form->addFieldPath(JPATH_COMPONENT.DS.'fields');
                            //bind values
                            $form->bind($value[$key]);
                            $value[$key]['params'] = $form;
                        }
                        $this->loadLanguage($xml);
                    }
                }
            } else {
                $params = new JParameter('');
                $params->loadArray($value[$key]);
                $params->addElementPath(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'elements');
                $jname = $params->get('jfusionplugin', '');
                if (isset($this->configArray[$config]) && !empty($jname)) {
                    $path = JFUSION_PLUGIN_PATH . DS . $jname . DS . $this->configArray[$config][1];
                    $defaultPath = JPATH_ADMINISTRATOR . DS . 'components' . DS . $option . DS . 'views' . DS . 'advancedparam' . DS . 'paramfiles' . DS . $this->configArray[$config][1];
                    $xml_path = (file_exists($path)) ? $path : $defaultPath;
                    if ($xml->loadFile($xml_path)) {
                        /**
                         * @ignore
                         * @var $xmlparams JSimpleXMLElement
                         */
                        $xmlparams = $xml->document->getElementByPath('params');
                        $params->setXML($xmlparams);
                        $this->loadLanguage($xml);
                    }
                }
                $value[$key]['params'] = $params;
            }
        }
        return $value;
    }

    /**
     * Loads the language
     *
     * @param object &$xml parameters
     *
     * @return string html
     */
    function loadLanguage(&$xml)
    {
        if (!empty($xml->document) && !empty($xml->document->language[0])) {
            //check for a language file and set it
            if ($xml->document->getElementByPath('language')) {
                $lang = $xml->document->language[0]->attributes();
                if (!empty($lang)) {
                    if (!empty($lang['filename'])) {
                        $location = null;
                        if (!empty($lang['location'])) {
                            if ($lang['location'] == 'site' || $lang['location'] == 'frontend') {
                                $location = JPATH_SITE;
                            } elseif ($lang['location'] == 'admin' || $lang['location'] == 'administrator') {
                                $location = JPATH_ADMINISTRATOR;
                            }
                        }
                        jimport('joomla.plugin.plugin');
						$language = JFactory::getLanguage();
						$language->load( strtolower($lang['filename']), $location);
                    }
                }
            }
        }
    }
}