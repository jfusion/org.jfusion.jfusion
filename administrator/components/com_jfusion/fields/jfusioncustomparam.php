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
class JFormFieldJFusionCustomParam extends JFormField
{
    public $type = 'JFusionCustomParam';
    /**
     * Get an element
     *
     * @param string $name         name of element
     * @param string $value        value of element
     * @param string &$node        node of element
     * @param string $control_name name of controler
     *
     * @return string html
     */
    protected function getInput()
    {
        global $jname;
        if ($jname) {
            //load the custom param output
            $JFusionPlugin = & JFusionFactory::getAdmin($jname);
            if (method_exists($JFusionPlugin, $this->fieldname)) {
            	//TODO NODE should be corrected
            	$node = null;
                $output = $JFusionPlugin->{$this->fieldname}($this->fieldname, $this->value, $node, $this->formControl);
                return $output;
            } else {
                return 'Undefined function:' . $this->fieldname . ' in plugin:' . $jname;
            }
        } else {
            return 'Programming error: You must define global $jname before the JParam object can be rendered';
        }
    }
}
