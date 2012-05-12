<?php
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
