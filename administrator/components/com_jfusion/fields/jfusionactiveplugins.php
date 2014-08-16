<?php

/**
 * This is the jfusion ActivePlugins element file
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
require_once JPATH_ADMINISTRATOR . '/components/com_jfusion/import.php';
/**
 * JFusion Element class ActivePlugins
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFormFieldJFusionActivePlugins extends JFormField
{
    public $type = 'JFusionActivePlugins';
    /**
     * Get an element
     *
     * @return string html
     */
    protected function getInput()
    {
	    try {
		    $feature = $this->element['feature'];
		    if (!$feature) {
			    $feature = 'any';
		    }

		    $db = JFactory::getDBO();

		    $query = $db->getQuery(true)
			    ->select('name as id, name as name')
			    ->from('#__jfusion')
		        ->where('status = 1');

		    $db->setQuery($query);
		    $rows = $db->loadObjectList();

		    foreach ($rows as $key => &$row) {
			    if (!JFusionFunction::hasFeature($row->name, $feature)) {
				    unset($rows[$key]);
			    }
		    }

		    if (!empty($rows)) {
			    $output = JHTML::_('select.genericlist', $rows, $this->name, 'size="1" class="inputbox"', 'id', 'name', $this->value);
		    } else {
			    throw new RuntimeException(JText::_('NO_VALID_PLUGINS'));
		    }
	    } catch (Exception $e) {
		    $output =  '<span style="float:left; margin: 5px 0; font-weight: bold;">' . $e->getMessage() . '</span>';
	    }
	    return $output;
    }
}
