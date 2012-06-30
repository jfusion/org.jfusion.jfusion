<?php
/**
 * Jfusion_Customer_Model_Customer class
 *
 * @category   JFusion
 * @package    Model
 * @subpackage Jfusion_Customer_Model_Customer
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Jfusion_Customer_Model_Customer extends Mage_Customer_Model_Customer{

    /**
     * Authenticate customer
     *
     * @param  string $login
     * @param  string $password
     * @return true
     * @throws Exception
     */
    public function authenticate($login, $password)
    {
        if(strpos($login, '@') !== false){
            $this->loadByEmail($login);
        }else{
            $this->loadByUsername($login);   
        }
        if ($this->getConfirmation() && $this->isConfirmationRequired()) {
            throw Mage::exception('Mage_Core', Mage::helper('customer')->__('This account is not confirmed.'),
                self::EXCEPTION_EMAIL_NOT_CONFIRMED
            );
        }
        if (!$this->validatePassword($password)) {
            throw Mage::exception('Mage_Core', Mage::helper('customer')->__('Invalid login or password.'),
                self::EXCEPTION_INVALID_EMAIL_OR_PASSWORD
            );
        }
        Mage::dispatchEvent('customer_customer_authenticated', array(
           'model'    => $this,
           'password' => $password,
        ));
        return true;
    }
        
    /**
     * Load customer by username
     *
     * @param   string $customerUsername
     * @return  Mage_Customer_Model_Customer
     */
    public function loadByUsername($customerUsername)
    {
        $this->_getResource()->loadByUsername($this, $customerUsername);
        return $this;
    }

    /**
     * @param $username
     * @param null $websiteId
     * @return bool|\Jfusion_Customer_Model_Customer
     */
    public function customerUsernameExists($username, $websiteId = null)
    {
        if($websiteId){
            $this->setWebsiteId($websiteId);
        }
        
        $this->loadByUsername($username);
        if ($this->getId()) {
            return $this;
        }
        return false;
    }
}