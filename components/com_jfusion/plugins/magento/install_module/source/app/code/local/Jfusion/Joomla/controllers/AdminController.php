<?php
/**
 * Jfusion_Joomla_AdminController class
 *
 * @category   JFusion
 * @package    Model
 * @subpackage Jfusion_Joomla_AdminController
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Jfusion_Joomla_AdminController extends Mage_Adminhtml_Controller_Action
{
    public function backendAction()
    {
		$this->loadLayout()
			->_addContent($this->getLayout()->createBlock('joomla/backend'))
			->renderLayout();
    }

    public function indexAction()
    {
       $this->backendAction();
    }
}
