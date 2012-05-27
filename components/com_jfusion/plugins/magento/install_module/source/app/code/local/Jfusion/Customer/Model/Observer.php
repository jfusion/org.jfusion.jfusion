<?php
/**
 * @package JFusion
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Jfusion_Customer_Model_Observer extends Mage_Customer_Model_Observer {

    /**
     * @param $observer
     * @throws Mage_Core_Exception
     */
    public function isActive($observer)
    {   
    	$customer = $observer->getEvent()->getModel();
    	// Add the inactive option - rissip
        if($customer->getIsActive () != '1' ){
        	throw new Mage_Core_Exception(Mage::helper('customer')->__('This account is disabled.'), 0);
        }
    }
}