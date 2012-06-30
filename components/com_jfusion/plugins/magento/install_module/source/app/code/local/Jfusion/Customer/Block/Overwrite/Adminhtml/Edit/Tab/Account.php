<?php
/**
 * @package JFusion
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Jfusion_Customer_Block_Overwrite_Adminhtml_Edit_Tab_Account extends Mage_Adminhtml_Block_Customer_Edit_Tab_Account
{
    /**
     * Add the field 'is_active' to the edit tab form
     * 
     * @see Mage_Adminhtml_Block_Widget_Form::_prepareForm()
     */
    protected function _prepareForm()
    {
        $customer = Mage::registry('current_customer');
        $form = $this->getForm();
        $fieldset = $form->getElements()->searchById('base_fieldset');
        $fieldset->addField('is_active', 'select',
	            array(
	                'name'  	=> 'is_active',
	                'label' 	=> Mage::helper('adminhtml')->__('This account is'),
	                'id'    	=> 'is_active',
	                'title' 	=> Mage::helper('adminhtml')->__('Account status'),
	                'class' 	=> 'input-select',
	                'required' 	=> false,
	                'style'		=> 'width: 80px',
	                'value'		=> '1',
	                'values'	=> array(
	                	array(
	                    	'label' => Mage::helper('adminhtml')->__('Active'),
	                    	'value'	=> '1',
	                	),
	                	array(
	                    	'label' => Mage::helper('adminhtml')->__('Inactive'),
	                    	'value' => '0',
	                		),
	                	),
	            	)
	        	);
	        	
	    $form->setValues($customer->getData());
	    $this->setForm($form);
        return parent::_prepareForm();
    }
}
