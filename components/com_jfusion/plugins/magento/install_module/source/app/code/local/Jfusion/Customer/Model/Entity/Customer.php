<?php
/**
 * @package JFusion
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

class Jfusion_Customer_Model_Entity_Customer extends Mage_Customer_Model_Entity_Customer{

    /**
     * @return array
     */
    protected function _getDefaultAttributes(){
		$attributes = parent::_getDefaultAttributes();
		array_push($attributes, 'is_active');
		return $attributes;
	}
	
	/**
     * Load customer by username
     *
     * @param Mage_Customer_Model_Customer $customer
     * @param string $username
     * @param bool $testOnly
     * @return Mage_Customer_Model_Entity_Customer
     * @throws Mage_Core_Exception
     */
    public function loadByUsername(Mage_Customer_Model_Customer $customer, $username, $testOnly = false)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getEntityTable(), array($this->getEntityIdField()))
            ->joinNatural('customer_entity_varchar')
            ->joinNatural('eav_attribute')
            ->where('eav_attribute.attribute_code=\'username\' AND customer_entity_varchar.value=?',$username);
            //->where('username=?',$username);
        if ($customer->getSharingConfig()->isWebsiteScope()) {
            if (!$customer->hasData('website_id')) {
                Mage::throwException(Mage::helper('customer')->__('Customer website ID must be specified when using the website scope.'));
            }
            $select->where('website_id=?', (int)$customer->getWebsiteId());
        }
        $id = $this->_getReadAdapter()->fetchOne($select, 'entity_id');
        if ($id) {
            $this->load($customer, $id);
        }
        else {
            $customer->setData(array());
        }
        return $this;
    }
}