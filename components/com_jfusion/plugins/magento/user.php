<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * load the Factory and jplugin model
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';

/**
 * JFusion User Class for Magento 1.1
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Magento 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_magento extends JFusionUser {
    /* Magento does not have usernames.
    *  The user is identified by an 'identity_id' that is found through the users e-mail address.
    *  To make it even more difficult for us, there is no simple tablecontaining all userdata, but
    *  the userdata is arranged in tables for different variable types.
    *  User attributes are identified by fixed attribute ID's in these tables
    *
    *  The usertables are:
    *  customer_entity
    *  customer_address_entity
    *  customer_address_entity_datetime
    *  customer_address_entity_decimal
    *  customer_address_entity_int
    *  customer_address_entity_text
    *  customer_address_entity_varchar
    *  customer_entity_datetime
    *  customer_entity_decimal
    *  customer_entity_int
    *  customer_entity_text
    *  customer_entity_varchar
    *
    */
    function connect_to_api(&$proxi, &$sessionId) {
        $status = array();
        $status['debug'] = array();
        $status['error'] = array();
        $params = JFusionFactory::getParams($this->getJname());
        $apipath = $params->get('source_url') . 'index.php/api/?wsdl';
        $apiuser = '';
        $apikey = '';
        $apiuser = $params->get('apiuser');
        $apikey = $params->get('apikey');
        if (!$apiuser || !$apikey) {
            $status['error'][] = 'Could not login to Magento API (empty apiuser and/or apikey)';
            return $status;
        }
        try {
            $proxi = new SoapClient($apipath);
            $sessionId = $proxi->login($apiuser, $apikey);
            $status['debug'][] = 'Logged into Magento API as ' . $apiuser . ' using key, message:' . $apikey;
            return $status;
        }
        catch(Soapfault $fault) {
            $status['error'][] = 'Could not login to Magento API as ' . $apiuser . ' using key ' . $apikey . ',message:' . $fault->faultstring;
            return $status;
        }
    }
    /*
    * Returns an array of Magento entity types
    */
    function getMagentoEntityTypeID($eav_entity_code) {
        static $eav_entity_types;
        if (!isset($eav_entity_types)) {
            $db = JFusionFactory::getDataBase($this->getJname());
            $db->setQuery("SELECT entity_type_id,entity_type_code FROM #__eav_entity_type");
            if ($db->getErrorNum() != 0) {
                $result = false;
                return $result;
            }
            $result = $db->loadObjectList();
            for ($i = 0;$i < count($result);$i++) {
                $eav_entity_types[$result[$i]->entity_type_code] = $result[$i]->entity_type_id;
            }
        }
        return $eav_entity_types[$eav_entity_code];
    }
    /*
    * Returns a Magento UserObject for the current installation
    * (see eav_entity_type)
    * please note, this is all my coding, so please report bugs to me, not to the Magento developers
    * henk wevers
    */
    function getMagentoDataObjectRaw($entity_type_code) {
        static $eav_attributes;
        if (!isset($eav_attributes[$entity_type_code])) {
            // first get the entity_type_id to access the attribute table
            $entity_type_id = $this->getMagentoEntityTypeID('customer');
            $db = JFusionFactory::getDataBase($this->getJname());
            // Get a database object
            $db->setQuery('SELECT attribute_id, attribute_code, backend_type FROM #__eav_attribute WHERE entity_type_id =' . (int)$entity_type_id);
            if ($db->getErrorNum() != 0) {
                $result = false;
                return $result;
            }
            //getting the results
            $result = $db->loadObjectList();
            for ($i = 0;$i < count($result);$i++) {
                $db->setQuery('SELECT attribute_id, attribute_code, backend_type FROM #__eav_attribute WHERE entity_type_id =' . (int)$entity_type_id);
                $eav_attributes[$entity_type_code][$i]['attribute_code'] = $result[$i]->attribute_code;
                $eav_attributes[$entity_type_code][$i]['attribute_id'] = $result[$i]->attribute_id;
                $eav_attributes[$entity_type_code][$i]['backend_type'] = $result[$i]->backend_type;
                if ($db->getErrorNum() != 0) {
                    $result = false;
                    return $result;
                }
            }
        }
        return $eav_attributes[$entity_type_code];
    }
    function getMagentoDataObject($entity_type_code) {
        $result = $this->getMagentoDataObjectRaw($entity_type_code);
        $dataObject = array();
        for ($i = 0;$i < count($result);$i++) {
            $dataObject[$result[$i]['attribute_code']]['attribute_id'] = $result[$i]['attribute_id'];
            $dataObject[$result[$i]['attribute_code']]['backend_type'] = $result[$i]['backend_type'];
        }
        return $dataObject;
    }
    function fillMagentoDataObject($entity_type_code, $entity_id, $entity_type_id) {
        $result = array();
        $result = $this->getMagentoDataObjectRaw($entity_type_code);
        if (!$result) {
            $result = false;
            return $result;
        }
        // walk through the array and fill the object requested
        // TODO This can be smarter by reading types at once and put the data them in the right place
        // for now I'm trying to get this working. optimising comes next
        $filled_object = array();
        $db = JFusionFactory::getDataBase($this->getJname());
        for ($i = 0;$i < count($result);$i++) {
            if ($result[$i]['backend_type'] == 'static') {
                $query = 'SELECT ' . $result[$i]['attribute_code'] . ' FROM #__' . $entity_type_code . '_entity' . ' WHERE entity_type_id =' . (int)$entity_type_id . ' AND entity_id =' . (int)$entity_id;
                $db->setQuery($query);
                if ($db->getErrorNum() != 0) {
                    $result = false;
                    return $result;
                }
            } else {
                $query = 'SELECT value FROM #__' . $entity_type_code . '_entity_' . $result[$i]['backend_type'] . ' WHERE entity_type_id =' . (int)$entity_type_id . ' AND attribute_id =' . (int)$result[$i]['attribute_id'] . ' AND entity_id =' . (int)$entity_id;
                $db->setQuery($query);
                if ($db->getErrorNum() != 0) {
                    $result = false;
                    return $result;
                }
            }
            $filled_object[$result[$i]['attribute_code']]['value'] = $db->loadResult();
            $filled_object[$result[$i]['attribute_code']]['attribute_id'] = $result[$i]['attribute_id'];
            $filled_object[$result[$i]['attribute_code']]['backend_type'] = $result[$i]['backend_type'];
        }
        return $filled_object;
    }
    function &getUser($userinfo) {
        $identifier = $userinfo;
        if (is_object($userinfo)) {
            $identifier = $userinfo->email;
        }
        $result = array();
        // Get the user id
        $db = JFusionFactory::getDataBase($this->getJname());
        $query = "SELECT entity_id FROM #__customer_entity WHERE email =" . $db->Quote($identifier);
        $db->setQuery($query);
        $entity = (int)$db->loadResult();
        // check if we have found the user, if not return failure
        if (!$entity) {
            return $result;
        }
        // Return a Magento customer array
        $magento_user = $this->fillMagentoDataObject("customer", $entity, 1);
        if (!$magento_user) {
            return $result;
        }
        $instance = array();
        // get the static data also
        $query = 'SELECT email, group_id, created_at, updated_at, is_active FROM #__customer_entity ' . 'WHERE entity_id = ' . $db->Quote($entity);
        $db->setQuery($query);
        $result = $db->loadObject();
        if (!$result) {
            return $result;
        }
        $instance['group_id'] = $result->group_id;
        if ($result->group_id == 0) {
            $instance['group_name'] = "Default Usergroup";
        } else {
            $query = 'SELECT customer_group_code from #__customer_group WHERE customer_group_id = ' . $result->group_id;
            $db->setQuery($query);
            $instance['group_name'] = $db->loadResult();
        }
        $magento_user['email']['value'] = $result->email;
        $magento_user['created_at']['value'] = $result->created_at;
        $magento_user['updated_at']['value'] = $result->updated_at;
        $is_active = $result->is_active; //TO DO: have to figure out what theis means
        $instance['userid'] = $entity;
        $instance['username'] = $magento_user['email']['value'];
        $name = $magento_user['firstname']['value'];
        if ($magento_user['middlename']['value']) {
            $name = $name . ' ' . $magento_user['middlename']['value'];
        }
        if ($magento_user['lastname']['value']) {
            $name = $name . ' ' . $magento_user['lastname']['value'];
        }
        $instance['name'] = $name;
        $instance['email'] = $magento_user['email']['value'];
        $password = $magento_user['password_hash']['value'];
        $hashArr = explode(':', $password);
        $instance['password'] = $hashArr[0];
        if (!empty($hashArr[1])) {
            $instance['password_salt'] = $hashArr[1];
        }
        $instance['activation'] = '';
        if ($magento_user['confirmation']['value']) {
            $instance['activation'] = $magento_user['confirmation']['value'];
        }
        $instance['registerDate'] = $magento_user['created_at']['value'];
        $instance['lastvisitDate'] = $magento_user['updated_at']['value'];
        if ($instance['activation']) {
            $instance['block'] = 1;
        } else {
            $instance['block'] = 0;
        }
        $instance1 = (object)$instance;
        return $instance1;
    }
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'magento';
    }
    function destroySession($userinfo, $options) {
    	$params = JFusionFactory::getParams($this->getJname());    	
        return JFusionJplugin::destroySession($userinfo, $options, $this->getJname(),$params->get('logout_type'));
    }
    function createSession($userinfo, $options) {
    	$params = JFusionFactory::getParams($this->getJname());
        return JFusionJplugin::createSession($userinfo, $options, $this->getJname(),$params->get('brute_force'));
    }
    function getRandomString($len, $chars = null) {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
        mt_srand(10000000 * (double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars) - 1;$i < $len;$i++) {
            $str.= $chars[mt_rand(0, $lc) ];
        }
        return $str;
    }
    function filterUsername($username) {
        //no username filtering implemented yet
        return $username;
    }
    function update_create_Magentouser($user, $entity_id) {
        $db = JFusionFactory::getDataBase($this->getJname());
        $sqlDateTime = date('Y-m-d H:i:s', time());
        // transactional handling of this update is a neccessity
        if (!$entity_id) { //create an (almost) empty user
            // first get the current increment
            //$db->Execute ( 'START TRANSACTION' );//in mysql - Before the query  was BEGIN TRANSACTION
            // This method is an empty implemented method into the core of joomla database class
            // So, we need to implement it for our purpose that's why there is a new factory for magento
            $db->BeginTrans();
            $query = 'SELECT increment_last_id FROM #__eav_entity_store WHERE entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer') . ' AND store_id = 0';
            $db->Execute($query);
            if ($db->getErrorNum() != 0) {
                //$db->Execute ( 'ROLLBACK' );//ROLLBACK TRANSACTION
                $db->RollbackTrans();
                return $db->stderr();
            }
            $increment_last_id_int = ( int )$db->loadresult();
            $increment_last_id = sprintf("%'09u", ($increment_last_id_int + 1));
            $query = 'UPDATE #__eav_entity_store SET increment_last_id = ' . $db->Quote($increment_last_id) . ' WHERE entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer') . ' AND store_id = 0';
            $db->Execute($query);
            if ($db->getErrorNum() != 0) {
                //$db->Execute ( 'ROLLBACK' );
                $db->RollbackTrans();
                return $db->stderr();
            }
            // so far so good, now create an empty user, to be updates later
            $query = 'INSERT INTO #__customer_entity   (entity_type_id, increment_id, is_active, created_at, updated_at) VALUES ' . '(' . (int)$this->getMagentoEntityTypeID('customer') . ',' . $db->Quote($increment_last_id) . ',1,' . $db->Quote($sqlDateTime) . ', ' . $db->Quote($sqlDateTime) . ')';
            $db->Execute($query);
            if ($db->getErrorNum() != 0) {
                //$db->Execute ( 'ROLLBACK' );
                $db->RollbackTrans();
                return $db->stderr();
            }
            $entity_id = $db->insertid();
        } else { // we are updating
            $query = 'UPDATE #__customer_entity' . ' SET updated_at = ' . $db->Quote($sqlDateTime) . ' WHERE entity_id = ' . (int)$entity_id;
            $db->Execute($query);
            if ($db->getErrorNum() != 0) {
                //$db->Execute ( 'ROLLBACK' );
                $db->RollbackTrans();
                return $db->stderr();
            }
        }
        // the basic userrecord is created, now update/create the eav records
        for ($i = 0;$i < count($user);$i++) {
            if ($user[$i]['backend_type'] == 'static') {
                if (isset($user[$i]['value'])) {
                    $query = 'UPDATE #__customer_entity' . ' SET ' . $user[$i]['attribute_code'] . '= ' . $db->Quote($user[$i]['value']) . ' WHERE entity_id = ' . $entity_id;
                    $db->Execute($query);
                    if ($db->getErrorNum() != 0) {
                        //$db->Execute ( 'ROLLBACK' );
                        $db->RollbackTrans();
                        return $db->stderr();
                    }
                }
            } else {
                if (isset($user[$i]['value'])) {
                    $query = 'SELECT value FROM #__customer_entity' . '_' . $user[$i]['backend_type'] . ' WHERE entity_id = ' . (int)$entity_id . ' AND entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer') . ' AND attribute_id = ' . (int)$user[$i]['attribute_id'];
                    $db->Execute($query);
                    $result = $db->loadresult();
                    if ($result) {
                        // we do not update an empty value, but remove the record instead
                        if ($user[$i]['value'] == '') {
                            $query = 'DELETE FROM #__customer_entity' . '_' . $user[$i]['backend_type'] . ' WHERE entity_id = ' . (int)$entity_id . ' AND entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer') . ' AND attribute_id = ' . (int)$user[$i]['attribute_id'];
                        } else {
                            $query = 'UPDATE #__customer_entity' . '_' . $user[$i]['backend_type'] . ' SET value = ' . $db->Quote($user[$i]['value']) . ' WHERE entity_id = ' . (int)$entity_id . ' AND entity_type_id = ' . (int)$this->getMagentoEntityTypeID('customer') . ' AND attribute_id = ' . (int)$user[$i]['attribute_id'];
                        }
                    } else { // must create
                        $query = 'INSERT INTO #__customer_entity' . '_' . $user[$i]['backend_type'] . ' (value, attribute_id, entity_id, entity_type_id) VALUES (' . $db->Quote($user[$i]['value']) . ', ' . $user[$i]['attribute_id'] . ', ' . $entity_id . ', ' . (int)$this->getMagentoEntityTypeID('customer') . ')';
                    }
                    $db->Execute($query);
                    if ($db->getErrorNum() != 0) {
                        //$db->Execute ( 'ROLLBACK' );
                        $db->RollbackTrans();
                        return $db->stderr();
                    }
                }
            }
        }
        // Change COMMIT TRANSACTION to COMMIT - This last is used in mysql but in fact it depends of the database system
        //$db->Execute ( 'COMMIT' );
        $db->CommitTrans();
        $result = false;
        return $result; //NOTE false is NO ERRORS!
        
    }
    function fillMagentouser(&$Magento_user, $attribute_code, $value) {
        $result = array();
        for ($i = 0;$i < count($Magento_user);$i++) {
            if ($Magento_user[$i]['attribute_code'] == $attribute_code) {
                $Magento_user[$i]['value'] = $value;
            }
        }
    }
    function createUser($userinfo, &$status) {
        $params = JFusionFactory::getParams($this->getJname());
        //get the default user group and determine if we are using simple or advanced
        $usergroups = (substr($params->get('usergroup'), 0, 2) == 'a:') ? unserialize($params->get('usergroup')) : $params->get('usergroup', 18);
        //check to make sure that if using the advanced group mode, $userinfo->group_id exists
        if (is_array($usergroups) && !isset($userinfo->group_id)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID');
            return null;
        }
        $default_group_id = (is_array($usergroups)) ? $usergroups[$userinfo->group_id] : $usergroups;
        $db = JFusionFactory::getDataBase($this->getJname());
        //prepare the variables
        // first get some default stuff from Magento
        //        $db->setQuery("SELECT default_group_id FROM #__core_website WHERE is_default = 1");
        //        $default_group_id = (int) $db->loadResult();
        $db->setQuery("SELECT default_store_id FROM #__core_store_group WHERE group_id =" . (int)$default_group_id);
        $default_store_id = (int)$db->loadResult();
        $db->setQuery('SELECT name, website_id FROM #__core_store WHERE store_id =' . (int)$default_store_id);
        $result = $db->loadObject();
        $default_website_id = (int)$result->website_id;
        $default_created_in_store = $result->name;
        $magento_user = $this->getMagentoDataObjectRaw('customer');
        if ($userinfo->activation) {
            $this->fillMagentouser($magento_user, 'confirmation', $userinfo->activation);
        }
        $this->fillMagentouser($magento_user, 'created_in', $default_created_in_store);
        $this->fillMagentouser($magento_user, 'email', $userinfo->email);
        $parts = explode(' ', $userinfo->name);
        $this->fillMagentouser($magento_user, 'firstname', $parts[0]);
        if (count($parts) > 1) {
            $this->fillMagentouser($magento_user, 'lastname', $parts[(count($parts) - 1) ]);
        } else {
            // Magento needs Firstname AND Lastname, so add a dot when lastname is empty
            $this->fillMagentouser($magento_user, 'lastname', '.');
        }
        $middlename = '';
        for ($i = 1;$i < (count($parts) - 1);$i++) {
            $middlename = $middlename . ' ' . $parts[$i];
        }
        if ($middlename) {
            $this->fillMagentouser($magento_user, 'middlename', $middlename);
        }
        if (isset($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
            $password_salt = $this->getRandomString(2);
            $this->fillMagentouser($magento_user, 'password_hash', md5($password_salt . $userinfo->password_clear) . ':' . $password_salt);
        } else {
            if (!empty($userinfo->password_salt)) {
                $this->fillMagentouser($magento_user, 'password_hash', $userinfo->password . ':' . $userinfo->password_salt);
            } else {
                $this->fillMagentouser($magento_user, 'password_hash', $userinfo->password);
            }
        }
        /*     $this->fillMagentouser($magento_user,'prefix','');
        $this->fillMagentouser($magento_user,'suffix','');
        $this->fillMagentouser($magento_user,'taxvat','');
        */
        $this->fillMagentouser($magento_user, 'group_id', $default_group_id);
        $this->fillMagentouser($magento_user, 'store_id', $default_store_id);
        $this->fillMagentouser($magento_user, 'website_id', $default_website_id);
        //now append the new user data
        $errors = $this->update_create_Magentouser($magento_user, 0);
        if ($errors) {
            $status['error'][] = JText::_('USER_CREATION_ERROR') . $errors;
            return;
        }
        //return the good news
        $status['debug'][] = JText::_('USER_CREATION');
        $status['userinfo'] = $this->getUser($userinfo);
    }
    function updatePassword($userinfo, $existinguser, &$status) {
        $magento_user = $this->getMagentoDataObjectRaw('customer');
        $password_salt = $this->getRandomString(2);
        $this->fillMagentouser($magento_user, 'password_hash', md5($password_salt . $userinfo->password_clear) . ':' . $password_salt);
        $errors = $this->update_create_Magentouser($magento_user, $existinguser->userid);
        if ($errors) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . $existinguser->password;
        }
    }
    //TODO update username code
    function updateUsername($userinfo, &$existinguser, &$status) {
    }
    function activateUser($userinfo, &$existinguser, &$status) {
        $magento_user = $this->getMagentoDataObjectRaw('customer');
        $this->fillMagentouser($magento_user, 'confirmation', '');
        $errors = $this->update_create_Magentouser($magento_user, $existinguser->userid);
        if ($errors) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }
    function inactivateUser($userinfo, &$existinguser, &$status) {
        $magento_user = $this->getMagentoDataObjectRaw('customer');
        $this->fillMagentouser($magento_user, 'confirmation', $userinfo->activation);
        $errors = $this->update_create_Magentouser($magento_user, $existinguser->userid);
        if ($errors) {
            $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
        }
    }
    function deleteUser($userinfo) {
        //setup status array to hold debug info and errors
        $status = array();
        $status['debug'] = array();
        $status['error'] = array();
        //set the userid
        //check to see if a valid $userinfo object was passed on
        if (!is_object($userinfo)) {
            $status['error'][] = JText::_('NO_USER_DATA_FOUND');
            return $status;
        }
        $existinguser = $this->getUser($userinfo);
        if (!empty($existinguser)) {
            $user_id = $existinguser->userid;
            // this can be complicated so we are going to use the Magento customer API
            // for the time being. Speed is not a great issue here
            // connect to host
            $sessionId = '';
            $proxi = '';
            $status = $this->connect_to_api($proxi, $sessionId);
            if ($status['error']) {
                return $status;
            }
            try {
                $result = $proxi->call($sessionId, "customer.delete", $user_id);
            }
            catch(Soapfault $fault) {
                $status['error'][] = "Magento API: Could not delete user with id $user_id , message: " . $fault->faultstring;
            }
            if (!$status['error']) {
            	$status['debug'][] = "Magento API: Delete user with id $user_id , email " . $userinfo->email;
            }
            
            try {
                $proxi->endSession($sessionId);
            }
            catch(Soapfault $fault) {
                $status['error'][] = "Magento API: Could not end this session, message: " . $fault->faultstring;
            }
        }
        return $status;
    }
    function updateEmail($userinfo, &$existinguser, &$status, $jname) {
        //set the userid
        $user_id = $existinguser->userid;
        $new_email = $userinfo->email;
        $update = array('email' => $new_email);
        // this can be complicated so we are going to use the Magento customer API
        // for the time being. Speed is not a great issue here
        // connect to host
        $sessionId = '';
        $proxi = '';
        $status = $this->connect_to_api($proxi, $sessionId);
        if ($status['error']) {
            return;
        }
        try {
            $result = $proxi->call($sessionId, "customer.update", array($user_id, $update));
        }
        catch(Soapfault $fault) {
            $status['error'][] = "Magento API: Could not update email of user with id $user_id , message: " . $fault->faultstring;
        }
        try {
            $proxi->endSession($sessionId);
        }
        catch(Soapfault $fault) {
            $status['error'][] = "Magento API: Could not end this session, message: " . $fault->faultstring;
        }
    }
    function updateUsergroup($userinfo, &$existinguser, &$status) {
        $params = JFusionFactory::getParams($this->getJname());
        //get the usergroup and determine if working in advanced or simple mode
        if (substr($params->get('usergroup'), 0, 2) == 'a:') {
            //check to see if we have a group_id in the $userinfo, if not return
            if (!isset($userinfo->group_id)) {
                $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID');
                return null;
            }
            $usergroups = unserialize($params->get('usergroup'));
            if (isset($usergroups[$userinfo->group_id])) {
                //set the usergroup in the user table
                $db = JFusionFactory::getDataBase($this->getJname());
                $query = 'UPDATE #__customer_entity SET group_id = ' . (int)$usergroups[$userinfo->group_id] . ' WHERE entity_id =' . (int)$existinguser->userid;
                $db->setQuery($query);
                if (!$db->query()) {
                    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                } else {
                    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . $existinguser->group_id . ' -> ' . $usergroups[$userinfo->group_id];
                }
            }
        } else {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR');
        }
    }
}