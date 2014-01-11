<?php

/**
 * This is the jfusion AdvancedParam element file
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
/**
 * JFusion Element class AdvancedParam
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.orgrg
 */
class JFormFieldJFusionAdvancedParam extends JFormField
{
	public $type = 'JFusionAdvancedParam';
	/**
	 * Get an element
	 *
	 * @return string html
	 */
	protected function getInput()
	{
		JFusionFunction::initJavaScript();

		//used to give unique ids to elements when more than one advanced param is loaded (for example in configuring JoomFish)
		static $elNum;
		if (!isset($elNum)) {
			$elNum = 0;
		}

		$ename = 'jfusionadvancedparam' . $elNum;

		JFactory::getLanguage()->load('com_jfusion');

		$feature = $this->element['feature'];
		if (!$feature) {
			$feature = 'any';
		}
		$multiselect = $this->element['multiselect'];

		//Create Link
		$link = 'index.php?option=com_jfusion&amp;task=advancedparam&amp;tmpl=component&amp;ename=' . $ename;
		if (!is_null($feature)) {
			$link .= '&amp;feature=' . $feature;
		}
		if (!is_null($multiselect)) {
			$link .= '&amp;multiselect=1';
		}

		jimport('joomla.user.helper');
		$hash = JFusionFunction::getHash($this->name . JUserHelper::genRandomPassword());
		$session = JFactory::getSession();
		$session->set($hash, $this->value);

		$link .= '&amp;' . $ename . '=' . $hash;

		//Get JRegistry from given string
		if (empty($this->value)) {
			$params = array();
		} else {
			$params = base64_decode($this->value);
			$params = unserialize($params);
			if (!is_array($params)) {
				$params = array();
			}
		}
		$title = '';
		if (isset($params['jfusionplugin'])) {
			$title = $params['jfusionplugin'];
		} else if ($multiselect) {
			$del = '';
			foreach ($params as $param) {
				if (isset($param['jfusionplugin'])) {
					$title .= $del . $param['jfusionplugin'];
					$del = '; ';
				}
			}
		}
		if (empty($title)) {
			$title = JText::_('NO_PLUGIN_SELECTED');
		}

		$select_plugin = JText::_('SELECT_PLUGIN');
		$select = JText::_('SELECT');

		$html =<<<HTML
        <div style="float: left; margin-right:5px">
            <input style="background: #ffffff;" type="text" id="{$ename}_name" value="{$title}" disabled="disabled" />
        </div>
        <a id="{$ename}_link" class="modal btn" title="{$select_plugin}"  href="{$link}" rel="{handler: 'iframe', size: {x: window.getSize().x-80, y: window.getSize().y-80}}">{$select}</a>
        <input type="hidden" id="{$ename}_id" name="{$this->name}" value="{$this->value}" />
HTML;

		$elNum++;
		return $html;
	}
}
