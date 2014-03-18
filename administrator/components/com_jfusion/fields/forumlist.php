<?php
/**
 * This is the jfusion Forumlist field file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Fields
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
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
/**
 * JFusion Field class Forumlist
 *
 * @category  JFusion
 * @package   Fields
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldForumlist extends JFormField
{
	public $type = 'forumlist';
	/**
	 * Get an field
	 *
	 * @return string html
	 */
	protected function getInput()
	{
		try {
			//Query current selected Module Id
			$id = JFactory::getApplication()->input->getInt('id', 0);
			$cid = JFactory::getApplication()->input->get('cid', array($id), 'array');
			JArrayHelper::toInteger($cid, array(0));
			//find out which JFusion plugin is used in the activity module
			$db = JFactory::getDBO();

			$query = $db->getQuery(true)
				->select('params')
				->from('#__modules')
				->where('module = ' . $db->quote('mod_jfusion_activity'))
				->where('id = ' . $db->quote($cid[0]));

			$db->setQuery($query);
			$params = $db->loadResult();
			$parametersInstance = new JRegistry($params);
			//load custom plugin parameter
			$jPluginParamRaw = unserialize(base64_decode($parametersInstance->get('JFusionPluginParam')));
			$jname = $jPluginParamRaw['jfusionplugin'];

			$control_name = $this->formControl . '[' . $this->group . ']';
			if (!empty($jname)) {
				$JFusionPlugin = \JFusion\Factory::getForum($jname);
				if ($JFusionPlugin->isConfigured()) {
					if (method_exists($JFusionPlugin, 'getForumList')) {
						$forumlist = $JFusionPlugin->getForumList();
						if (!empty($forumlist)) {
							$selectedValue = $parametersInstance->get($this->fieldname);
							$output = JHTML::_('select.genericlist', $forumlist, $control_name . '[' . $this->fieldname . '][]', 'multiple size="6" class="inputbox"', 'id', 'name', $selectedValue);
						} else {
							throw new RuntimeException($jname . ': ' .JText::_('NO_LIST'));
						}
					} else {
						throw new RuntimeException($jname . ': ' .JText::_('NO_LIST'));
					}
				} else {
					throw new RuntimeException($jname . ': ' .JText::_('NO_VALID_PLUGINS'));
				}
			} else {
				throw new RuntimeException(JText::_('NO_PLUGIN_SELECT'));
			}
		} catch(Exception $e) {
			$output = '<span style="float:left; margin: 5px 0; font-weight: bold;">' . $e->getMessage() . '</span>';
		}
		return $output;
	}
}
