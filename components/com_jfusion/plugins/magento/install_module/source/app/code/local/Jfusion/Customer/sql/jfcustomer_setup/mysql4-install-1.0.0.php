<?php
$installer = $this;

/* @var $installer Jfusion_Customer_Model_Entity_Setup */
$store = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);

/* @var $eavConfig Mage_Eav_Model_Config */
$eavConfig = Mage::getSingleton('eav/config');

$attribute = array(
    'label'        => 'Username',
    'visible'      => true,
    'required'     => true,
    'type'         => 'varchar',
    'input'        => 'text',
    'sort_order'	=> 65,
	'validate_rules'    => array(
            'max_text_length'   => 30,
            'min_text_length'   => 1
    ),
    'used_in_forms' => array('adminhtml_customer','customer_account_edit', 'customer_account_create', 'checkout_register'),
);

$installer->addAttribute('customer','username', $attribute);

$attributes  = array('username' => $attribute);

foreach ($attributes as $attributeCode => $data) {
    $attribute = $eavConfig->getAttribute('customer', $attributeCode);
    $attribute->setWebsite($store->getWebsite());
    $attribute->addData($data);
    if (false === ($data['is_system'] == 1 && $data['is_visible'] == 0)) {
        $usedInForms = array(
            'customer_account_create',
            'customer_account_edit',
            'checkout_register',
        );
        if (!empty($data['adminhtml_only'])) {
            $usedInForms = array('adminhtml_customer');
        } else {
            $usedInForms[] = 'adminhtml_customer';
        }
        if (!empty($data['admin_checkout'])) {
            $usedInForms[] = 'adminhtml_checkout';
        }

        $attribute->setData('used_in_forms', $usedInForms);
    }
    $attribute->save();
}

$installer->startSetup();

$installer->run("
ALTER TABLE  `{$this->getTable('sales_flat_quote')}`
	ADD  `customer_username` VARCHAR( 255 ) NULL AFTER  `customer_taxvat`
");

$installer->endSetup();