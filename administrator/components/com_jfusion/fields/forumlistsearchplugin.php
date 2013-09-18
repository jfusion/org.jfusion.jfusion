<?php

/**
 * This is the jfusion search plugin forum list element file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();
/**
 * Require the Jfusion plugin factory
 */
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';
/**
 * JFusion Element class ForumListSearchPlugin
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldForumListSearchPlugin extends JFormField
{
    public $type = 'ForumListSearchPlugin';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
	    try {
	        //find out which JFusion plugin is used
	        $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('params')
			    ->from('#__extensions')
			    ->where('element = ' . $db->Quote('jfusion'))
			    ->where('folder = ' . $db->Quote('search'));

	        $db->setQuery($query);
	        $params = $db->loadResult();
	        $parametersInstance = new JRegistry($params);
	        //load custom plugin parameter
	        $jPluginParamRaw = unserialize(base64_decode($parametersInstance->get('JFusionPluginParam')));

	        $jname = '';
	        preg_match('#params\[(.*?)\]#', $this->formControl, $matches);
	        if (!empty($matches)) {
	            $jname = $matches[1];
	        }

	        if (!empty($jname)) {
		        $JFusionPlugin = JFusionFactory::getForum($jname);
		        if ($JFusionPlugin->isConfigured()) {
			        if (!isset($jPluginParamRaw[$jname])) {
				        $jPluginParamRaw[$jname] = array();
			        }
			        $JPluginParam = new JRegistry('');
			        $JPluginParam->loadArray($jPluginParamRaw[$jname]);
			        if (method_exists($JFusionPlugin, 'getForumList')) {
				        $forumlist = $JFusionPlugin->getForumList();
				        if (!empty($forumlist)) {
					        $selectedValue = $JPluginParam->get($this->fieldname);
					        $output = JHTML::_('select.genericlist', $forumlist, $this->name . '[]', 'multiple size="6" class="inputbox"', 'id', 'name', $selectedValue);
				        } else {
					        throw new RuntimeException($jname . ': ' . JText::_('NO_LIST'));
				        }
			        } else {
				        throw new RuntimeException($jname . ': ' . JText::_('NO_LIST'));
			        }
		        } else {
			        throw new RuntimeException($jname . ': ' . JText::_('NO_VALID_PLUGINS'));
		        }
	        } else {
		        throw new RuntimeException(JText::_('NO_PLUGIN_SELECT'));
	        }
	    } catch (Exception $e) {
		    $output = '<span style="float:left; margin: 5px 0; font-weight: bold;">'.$e->getMessage().'</span>';
	    }
        return $output;
    }
}
