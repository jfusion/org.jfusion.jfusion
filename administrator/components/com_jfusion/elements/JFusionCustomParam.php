<?php
/**
 * This is the jfusion CustomParam element file
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
 * JFusion Element class CustomParam
 *
 * @category  JFusion
 * @package   Elements
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JElementJFusionCustomParam extends JElement
{
    var $_name = 'JFusionCustomParam';

    /**
     * Get an element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param JSimpleXMLElement &$node        node of element
     * @param string $control_name name of controler
     *
     * @return string|void html
     */
    function fetchElement($name, $value, &$node, $control_name)
    {
        global $jname;
        if ($jname) {
            //load the custom param output
            $JFusionPlugin = & JFusionFactory::getAdmin($jname);
            if (method_exists($JFusionPlugin, $name)) {
                $output = $JFusionPlugin->{$name}($name, $value, $node, $control_name);
                return $output;
            } else {
                return 'Undefined function:' . $name . ' in plugin:' . $jname;
            }
        } else {
            return 'Programming error: You must define global $jname before the JParam object can be rendered';
        }
    }
}
