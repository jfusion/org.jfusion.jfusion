<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'fields' . DIRECTORY_SEPARATOR . 'jformfieldjfusionactiveplugins.php';
/**
 * Field class
 *
 * @category   JFusion
 * @package    Field
 * @subpackage JFieldJFusionMagentoPlugins
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFieldJFusionMagentoPlugins extends JFormFieldJFusionActivePlugins{
	
    public $type = 'JFusionMagentoPlugins';
    /**
     * Get an element
     *
     * @return string html
     */
	protected function getInput(){
		
		return parent::getInput();
		
	}
}