<?php

class Jfusion_Customer_Model_Form extends Mage_Customer_Model_Form
{
   public function validateData(array $data){
       
       $errors = parent::validateData($data);
       
       if(isset($data['username'])){
           $model = Mage::getModel('customer/customer');

           if (isset($data['website_id']) && $data['website_id'] !== false) {
                $websiteId = $data['website_id'];
            } elseif ($customerId = (Mage::app()->getFrontController()->getRequest()->getParam('id') || Mage::app()->getFrontController()->getRequest()->getParam('customer_id'))) {
                $customer = $model->load($customerId);
                $websiteId = $customer->getWebsiteId();
                if($customer->getUsername() == $data['username']){ // don't make any test if the user
                    return $errors;
                }
            } else {
                $websiteId = Mage::app()->getWebsite()->getId();
            }
           
           
           $result = $model->customerUsernameExists($data['username'], $websiteId);
           if($result && $result->getId() != Mage::app()->getFrontController()->getRequest()->getParam('id')
               && $result->getId() != Mage::getSingleton('customer/session')->getCustomerId('id')){
               $message = Mage::helper('customer')->__("Username already exists");
               if($errors === true){
                   $errors = array();
               }
               $errors = array_merge($errors, array($message));
           }
       }
       
       if (count($errors) == 0) {
            return true;
       }
       
       return $errors;
   }
}
