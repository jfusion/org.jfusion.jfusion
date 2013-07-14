<?php

/**
 * This is the jfusion Discussionbot element file
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
 * JFusion Element class Discussionbot
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldForumListDiscussionbot extends JFormField
{
    public $type = 'ForumListDiscussionbot';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
        $db = JFactory::getDBO();
        $query = 'SELECT params FROM #__extensions WHERE element = \'jfusion\' and folder = \'content\'';
        $db->setQuery($query);
        $params = $db->loadResult();
        $jPluginParam = new JRegistry($params);
        $jname = $jPluginParam->get('jname', false);
        $output = '<span style="float:left; margin: 5px 0; font-weight: bold;">';
        if ($jname !== false) {
	        try {
		        if (JFusionFunction::validPlugin($jname)) {
			        $JFusionPlugin = JFusionFactory::getForum($jname);
			        if (method_exists($JFusionPlugin, 'getForumList')) {
				        $forumlist = $JFusionPlugin->getForumList();
				        if (!empty($forumlist)) {
					        $selectedValue = $jPluginParam->get($this->fieldname);
					        $output = JHTML::_('select.genericlist', $forumlist, $this->formControl.'['.$this->group.']['.$this->fieldname.']', 'class="inputbox"', 'id', 'name', $selectedValue);
					        return $output;
				        } else {
					        $output.= $jname . ': ' . JText::_('NO_LIST');
				        }
			        } else {
				        $output.= $jname . ': ' . JText::_('NO_LIST');
			        }
			        $output.= '<br />';
		        } else {
			        $output.= $jname . ': ' . JText::_('NO_VALID_PLUGINS');
		        }
	        } catch (Exception $e) {
		        $output.= $jname . ': ' . JText::_('NO_VALID_PLUGINS') . ' '.$e->getMessage();
	        }
        } else {
            $output.= JText::_('NO_PLUGIN_SELECT');
        }
        $output.= '</span>';
        return $output;
    }
}
