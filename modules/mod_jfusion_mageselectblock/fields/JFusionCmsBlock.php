<?php
/**
 * @package JFusion
 * @subpackage Elements
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
// Check to ensure this file is included in Joomla!
defined ( '_JEXEC' ) or die ();

require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';

/**
 * Get the block list of the cms of Magento
 * @package mod_jfusion_magecustomblock
 */
class JFormFieldJFusionCmsBlock extends JFormField {
	
    public $type = "JFusionCmsBlock";

    /**
     * Get an element
     *
     * @return string html
     */
	function getInput() {
		
		$output = "";
		
		//Query current selected Module Id
		$id = JRequest::getVar ( 'id', 0, 'method', 'int' );
		$cid = JRequest::getVar ( 'cid', array ($id ), 'method', 'array' );
		JArrayHelper::toInteger ( $cid, array (0 ) );
		
		$db = JFactory::getDBO ();

		$query = $db->getQuery(true)
			->select('params')
			->from('#__modules')
			->where('module = ' . $db->Quote('mod_jfusion_mageselectblock'))
			->where('id = ' . $db->Quote ( $cid [0] ));

		$db->setQuery ( $query );
		$params = $db->loadResult ();
		$parametersInstance = new JRegistry ( $params, '' );
		
		$jname = $parametersInstance->get ( 'magento_plugin', '' );
		if (! empty ( $jname )) {
			if (JFusionFunction::validPlugin ( $jname )) {
				$dbplugin = JFusionFactory::getDatabase ( $jname );
				
				//@todo - take in charge the different stores
				$query = "SELECT block_id as value, title as name FROM #__cms_block WHERE is_active = '1' ORDER BY block_id";
				$dbplugin->setQuery ( $query );
				$rows = $dbplugin->loadObjectList ();
				if (! empty ( $rows )) {
					$output .= JHTML::_ ( 'select.genericlist', $rows, $this->getFieldName(null) . '[' .  $this->getName(null) . ']', 'size="1" class="inputbox"', 'value', 'name', $this->getValue() );
				} else {
					$output .= $jname . ': ' . JText::_('No list');
				}
			} else {
				$output .= $jname . ': ' . JText::_ ( 'No valid plugin' ) . '<br />';
			}
		} else {
			$output .= JText::_ ( 'No plugin selected' );
		}
		
		return $output;
	}
}