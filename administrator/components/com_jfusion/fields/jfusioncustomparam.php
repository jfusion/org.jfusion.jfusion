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
	 * @return string html
	 */
	protected function getInput()
	{
		global $jname;
		try {
			if ($jname) {
				//load the custom param output
				$JFusionPlugin = JFusionFactory::getAdmin($jname);
				if (method_exists($JFusionPlugin, $this->fieldname)) {
					$output = $JFusionPlugin->{$this->fieldname}($this->fieldname, $this->value, $this->element, $this->group);
				} else {
					throw new RuntimeException('Undefined function:' . $this->fieldname . ' in plugin:' . $jname);
				}
			} else {
				throw new RuntimeException('Programming error: You must define global $jname before the JParam object can be rendered.');
			}
		} catch (Exception $e) {
			$output = '<span style="float:left; margin: 5px 0; font-weight: bold;">'.$e->getMessage().'</span>';
		}
		return $output;
	}
}
