<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR .'components'.DIRECTORY_SEPARATOR.'com_jfusion'.DIRECTORY_SEPARATOR.'elements'.DIRECTORY_SEPARATOR.'JFusionPlugins.php';
/**
 * Field class
 *
 * @category   JFusion
 * @package    Field
 * @subpackage JElementJFusionMagentoPlugins
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JElementJFusionMagentoPlugins extends JElementJFusionPlugins {

    /**
     * Get an element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param JSimpleXMLElement &$node        node of element
     * @param string $control_name name of controller
     *
     * @return string|void html
     */
	function fetchElement($name,$value,&$node,$control_name) {
		return parent::fetchElement($name,$value,$node,$control_name);
	}
}