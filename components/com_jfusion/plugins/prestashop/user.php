<?php


/**
 * JFusion User Class for PrestaShop
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage PrestaShop
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */


// no direct access
defined('_JEXEC') or die('Restricted access');


/**
 * JFusion User Class for PrestaShop
 * For detailed descriptions on these functions please check the model.abstractuser.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage PrestaShop
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_prestashop extends JFusionUser {
    function &getUser($userinfo) {
	    //get the identifier
        $identifier = $userinfo;
        if (is_object($userinfo)) {
            $identifier = $userinfo->email;
        }
        // Get user info from database
		$db = JFusionFactory::getDatabase($this->getJname());
        $query = "SELECT id_customer as userid, email, passwd as password, firstname, lastname FROM #__customer WHERE email =" . $db->Quote($identifier) ;
        $db->setQuery($query);
        $result = $db->loadObject();
        // read through params for cookie key (the salt used)
        return $result;
    }

    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */    
    function getJname() 
    {
        return 'prestashop';
    }
    function deleteUser($userinfo) {
        /* Warning: this function mimics the original prestashop function which is a suggestive deletion, 
		all user information remains in the table for past reference purposes. To delete everything associated
		with an account and an account itself, you will have to manually delete them from the table yourself. */
		// get the identifier
        $identifier = $userinfo;
        if (is_object($userinfo)) {
            $identifier = $userinfo->id_customer;
        }
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__customer SET deleted ="1" WHERE id_customer =' . $db->Quote($identifier);
        $db->setQuery($query);
		$status["debug"][] = "Deleted user";
		return $status;
    }
    function destroySession($userinfo = "", $option = "") {
	    $status = array();
        $status['error'] = array();
        $status['debug'] = array();
	    // use prestashop cookie class and functions to delete cookie
		$params = JFusionFactory::getParams($this->getJname());
		require_once $params->get('source_path') . DS . "config" . DS . "settings.inc.php";
	    require($params->get('source_path') . DS . "classes" . DS . "Cookie.php");
		require($params->get('source_path') . DS . "classes" . DS . "Blowfish.php");
		require($params->get('source_path') . DS . "classes" . DS . "Tools.php");
		require($params->get('source_path') . DS . "classes" . DS . "ObjectModel.php");
		require($params->get('source_path') . DS . "classes" . DS . "Db.php");
		require($params->get('source_path') . DS . "classes" . DS . "SubDomain.php");
        $cookie = new cookie('ps');
		$status["error"][] = "Random debugging text";
	    if(!$cookie->mylogout())
		{
		 $status["error"][] = "Error Could not delete session, doesn't exist";
		}
		else
		{
		 $status["debug"][] = "Deleted session and session data";
		}
		return $status;
    }
    function createSession($userinfo, $options, $framework = true) {
	    $params = JFusionFactory::getParams($this->getJname());
	    $status = array();
        $status['error'] = array();
        $status['debug'] = array();
        // this uses a code extract from authentication.php that deals with logging in completely
		$db = JFusionFactory::getDatabase($this->getJname());
		require_once $params->get('source_path') . DS . "config" . DS . "settings.inc.php";
	    require($params->get('source_path') . DS . "classes" . DS . "Cookie.php");
		require($params->get('source_path') . DS . "classes" . DS . "Blowfish.php");
		require($params->get('source_path') . DS . "classes" . DS . "Tools.php");
		require($params->get('source_path') . DS . "classes" . DS . "ObjectModel.php");
		require($params->get('source_path') . DS . "classes" . DS . "Db.php");
		require($params->get('source_path') . DS . "classes" . DS . "SubDomain.php");
		require($params->get('source_path') . DS . "classes" . DS . "Validate.php");
		$cookie = new cookie('ps');
		$passwd = $userinfo->password_clear;
	    $email = $userinfo->email;
		$passwd = trim($passwd);
		$email = trim($email);
		if (empty($email))
		{
		    JText::_('EMAIL_UPDATE_ERROR');
		    echo('e-mail address is required');
		}
		elseif (!Validate::isEmail($email))
		{
		    JText::_('EMAIL_UPDATE_ERROR');
		    echo('invalid e-mail address');
		}
		elseif (empty($passwd))
		{
		    JText::_('EMAIL_UPDATE_ERROR');
		    echo('password is required');
		}
		elseif (Tools::strlen($passwd) > 32)
		{
		    JText::_('EMAIL_UPDATE_ERROR');
		    echo('password is too long');
		}
		elseif (!Validate::isPasswd($passwd))
		{
		    JText::_('EMAIL_UPDATE_ERROR');
		    echo('invalid password');
		}
		else
	    { 
		    /* Handle brute force attacks */
		    sleep(1);
			// check if password matches
			$query = "SELECT passwd FROM #__customer WHERE email =" . $db-Quote($email);
            $db->setQuery($query);
            $result = $db->loadResult();
		    if (!$result)
			{
			    JText::_('EMAIL_UPDATE_ERROR');
			    echo('authentication failed');
			}
		    else
		    {
				if(md5($params->get('cookie_key') . $passwd) === $result)
				{
				$cookie->__set("id_customer", $userinfo->userid);
				$cookie->__set("customer_lastname", $userinfo->lastname);
				$cookie->__set("customer_firstname", $userinfo->firstname);
				$cookie->__set("logged", 1);
				$cookie->__set("passwd", md5($params->get('cookie_key') . $passwd));
				$cookie->__set("email", $email);
				return true;
				}
				else
				{
					JText::_('EMAIL_UPDATE_ERROR');
					echo('wrong password');
				}
			}
		}
        return false;
	}
    function filterUsername($username) {
        return $username;
    }
    function updatePassword($userinfo, &$existinguser, &$status) {
        jimport('joomla.user.helper');
        $existinguser->password_salt = JUserHelper::genRandomPassword(8);
        $existinguser->password = md5($userinfo->password_clear . $existinguser->password_salt);
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__customer SET password =' . $db->Quote($existinguser->password) . ', salt = ' . $db->Quote($existinguser->password_salt) . ' WHERE id_customer =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
        }
    }
    function createUser($userinfo, &$status) {
		$db = JFusionFactory::getDatabase($this->getJname());
	    $params = JFusionFactory::getParams($this->getJname());
		require($params->get('source_path') . DS . "classes" . DS . "Validate.php");
		require($params->get('source_path') . DS . "classes" . DS . "ObjectModel.php");
		require($params->get('source_path') . DS . "classes" . DS . "Db.php");
		require($params->get('source_path') . DS . "classes" . DS . "Country.php");
		require($params->get('source_path') . DS . "classes" . DS . "State.php");
		require($params->get('source_path') . DS . "classes" . DS . "Tools.php");
		require($params->get('source_path') . DS . "classes" . DS . "Customer.php");
		
		/* split full name into first and with/or without middlename, and lastname */
		$users_name = $userinfo->name;
		list( $uf_name, $um_name, $ul_name ) = explode( ' ', $users_name, 3 );
		if ( is_null($ul_name) ) // meaning only two names were entered
		{
			$end_name = $um_name;
		}
		else
		{
			$end_name = explode( ' ', $ul_name );
			$size = sizeof($ul_name);
			$end_name = $ul_name[$size-1];
		}
		// now have first name as $uf_name, and last name as $end_name
		
		/* user variables submitted through form (emulated) */
	    $user_variables = array(
	    'id_gender' => "1", // value of either 1 for male, 2 for female
	    'firstname' => $uf_name, // alphanumeric values between 6 and 32 charachters long 
	    'lastname' => $end_name, // alphanumeric values between 6 and 32 charachters long 
	    'customer_firstname' => $uf_name, // alphanumeric values between 6 and 32 charachters long 
	    'customer_lastname' => $end_name, // alphanumeric values between 6 and 32 charachters long 
	    'email' => $userinfo->email, // alphanumeric values aswell as @ and . symbols between 6 and 128 charachters long 
	    'passwd' => $userinfo->password_clear, // alphanumeric values between 6 and 32 charachters long
	    'days' => "01", // numeric charachter between 1 and 31
	    'months' => "01", // numeric charachter between 1 and 12
	    'years' => "2000", // numeric charachter between 1900 and latest year
	    'newsletter' => 0, // value of either 0 for no newsletters, or 1 to recieve newsletters
	    'optin' => 0, // value of either 0 for no third party options, or 1 to recieve third party options
	    'company' => "", // alphanumeric values between 6 and 32 charachters long
	    'address1' => "Update with your real address", // alphanumeric values between 6 and 128 charachters long
	    'address2' => "", // alphanumeric values between 6 and 128 charachters long
	    'postcode' => "Postcode", // alphanumeric values between 7 and 12 charachters long
	    'city' => "Not known", // alpha values between 6 and 64 charachters long
	    'id_country' => "17", // numeric charachter between 1 and 244 (normal preset)
	    'id_state' => "0", // numeric charachter between 1 and 65 (normal preset)
	    'other' => "", // alphanumeric values with mysql text limit charachters long
	    'phone' => "", // numeric values between 11 and 16 chrachters long
	    'phone_mobile' => "", // numeric values between 11 and 16 chrachters long
	    'alias' => "My address", // alphanumeric values between 6 and 32 charachters long
	    'dni' => "", // alphanumeric values between 6 and 16 charachters long
	    );
		
		/* array to go into table ps_customer */
	    $ps_customer = array(
	    'id_customer' => "NULL", // column 0 (id_customer)
	    'id_gender' => $user_variables['id_gender'], // column 1 (id_gender)
	    'id_default_group' => 1, // column 2 (id_default_group)
	    'secure_key' => md5(uniqid(rand(), true)), // column 3 (secure_key)
	    'email' => $user_variables['email'], // column 4 (email)
	    'passwd' => md5($params->get('cookie_key') . $user_variables['passwd']), // column 5 (passwd)
	    'last_passwd_gen' => date("Y-m-d h:m:s",strtotime("-6 hours")), // column 6 (last_passwd_gen)
	    'birthday' => date("Y-m-d",mktime(0,0,0,$user_variables['months'],$user_variables['days'],$user_variables['years'])), // column 7 (birthday)
	    'lastname' => $user_variables['lastname'], // column 8 (lastname) 
	    'newsletter' => $user_variables['newsletter'], // column 9 (newsletter)
	    'ip_registration_newsletter' => $_SERVER['REMOTE_ADDR'], // column 10 (ip_registration_newsletter)
	    'newsletter_date_add' => date("Y-m-d h:m:s"), // column 11 (newsletter_date_add)
	    'optin' => $user_variables['optin'], // column 12 (optin)
	    'firstname' => $user_variables['firstname'], // column 13 (firstname)
	    'dni' => $user_variables['dni'], // column 14 (dni)
	    'active' => 1, // column 15 (active)
	    'deleted' => 0, // column 16 (deleted)
	    'date_add' => date("Y-m-d h:m:s"), // column 17 (date_add)
	    'date_upd' => date("Y-m-d h:m:s") // column 18 (date_upd)
		);
		
		/* array to go into table ps_customer_group */
	    $ps_customer_group = array(
	    'id_customer' => "NULL", // column 0 (id_customer)
	    'id_group' => $params->get('usergroup') // column 1 (id_group)
	    );
		
		/* array to go into table ps_address */
	    $ps_address = array(
	    'id_address' => "NULL", // column 0 (id_address)
	    'id_country' => $user_variables['id_country'], // column 1 (id_country)
	    'id_state' => $user_variables['id_state'], // column 2 (id_state)
	    'id_customer' => "NULL", // column 3 (id_customer)
	    'id_manufacturer' => 0, // column 4 (id_manufacturer)
	    'id_supplier' => 0, // column 5 (id_supplier)
	    'alias' => $user_variables['alias'], // column 6 (alias)
	    'company' => $user_variables['company'], // column 7 (company)
	    'lastname' => $user_variables['customer_lastname'], // column 8 (lastname)
	    'firstname' => $user_variables['customer_firstname'], // column 9 (firstname)
	    'address1' => $user_variables['address1'], // column 10 (address1)
	    'address2' => $user_variables['address2'], // column 11 (address2)
	    'postcode' => $user_variables['postcode'], // column 12 (postcode)
	    'city' => $user_variables['city'], // column 13 (city)
	    'other' => $user_variables['other'], // column 14 (other)
	    'phone' => $user_variables['phone'], // column 15 (phone)
	    'phone_mobile' => $user_variables['phone_mobile'], // column 16 (phone_mobile)
	    'date_add' => date("Y-m-d h:m:s"), // column 17 (date_add)
	    'date_upd' => date("Y-m-d h:m:s"), // column 18 (date_upd)
	    'active' => 1, // column 19 (active)
	    'deleted' => 0 // column 20 (deleted)
	    );
		
		/* safe data check and validation of array $user_variables
	    no other unique variables are used so this check only includes these */
	
	    // Validate gender
	    if (!Validate::isGenderIsoCode($user_variables['id_gender'])){
		    $errors[] = Tools::displayError('gender not valid');
		    unset($ps_customer);
	    }
	
        // Validate first name
	    if (!Validate::isName($user_variables['firstname'])){
	        $errors[] = Tools::displayError('first name wrong');
	        unset($ps_customer);
	    }
	 
	    // Validate second name
	    if (!Validate::isName($user_variables['lastname'])){
	        $errors[] = Tools::displayError('second name wrong');
	        unset($ps_customer);
	    }
	 
	    // Validate address first name
	    if (!Validate::isName($user_variables['customer_firstname'])){
	        $errors[] = Tools::displayError('customer first name wrong');
	        unset($ps_address);
	    }
	 
	    // Validate address last name
	    if (!Validate::isName($user_variables['customer_lastname'])){
	        $errors[] = Tools::displayError('customer second name wrong');
	        unset($ps_address);
	    }
	
	    // Validate email
	    if (!Validate::isEmail($user_variables['email'])){
	        $errors[] = Tools::displayError('e-mail not valid');
	        unset($ps_customer);
	    }
	 
	    // Validate password
	    if (!Validate::isPasswd($user_variables['passwd'])){
	        $errors[] = Tools::displayError('invalid password');
	        unset($ps_customer);
	    }
	
	    // Validate date of birth 
	    if (!@checkdate($user_variables['months'], $user_variables['days'], $user_variables['years']) AND !( $user_variables['months']== '' AND $user_variables['days'] == '' AND $user_variables['years'] == '')){
		    $errors[] = Tools::displayError('invalid birthday');
		    unset($ps_customer);
	    }
	 
	    // Validate newsletter checkbox
        if (!Validate::isBool($user_variables['newsletter'])){
	        $errors[] = Tools::displayError('newsletter invalid choice');
	        unset($ps_customer);
	    }
	 
	    // Validate special offers from partners checkbox
	    if (!Validate::isBool($user_variables['optin'])){
	        $errors[] = Tools::displayError('optin invalid choice');
	        unset($ps_customer);
	    }
	 
	    // Validate company/orginization
	    if (!Validate::isGenericName($user_variables['company'])){
	        $errors[] = Tools::displayError('company name wrong');
	        unset($ps_address);
	    }
	 
	    // Do not validate address line 1 since a placeholder is been curently used
	    /*if (!Validate::isAddress($user_variables['address1'])){
	        $errors[] = Tools::displayError('address wrong');
	        unset($ps_address);
	    }*/
	 
	    // Validate address line 2
	    if (!Validate::isAddress($user_variables['address2'])){
	        $errors[] = Tools::displayError('address 2nd wrong');
	        unset($ps_address);
	    }

	    // Do not validate postcode since a placeholder is been curently used
	    /*if (!Validate::isPostCode($user_variables['postcode'])){
	        $errors[] = Tools::displayError('postcode wrong');
	        unset($ps_address);
	    }*/
	 
	    // Validate phone number
	    if (!Validate::isPhoneNumber($user_variables['phone'])){
	        $errors[] = Tools::displayError('invalid phone');
	        unset($ps_address);
	    }
	 
	    // Validate mobile number
	    if (!Validate::isPhoneNumber($user_variables['phone_mobile'])){
	        $errors[] = Tools::displayError('invalid mobile');
	        unset($ps_address);
	    }
	
	    // Do not validate village/town/city since a placeholder is been curently used
	    /*if (!Validate::isCityName($user_variables['city'])){
	        $errors[] = Tools::displayError('invalid village/town/city');
	        unset($ps_address);
	    }*/
	
	    // Validate country
	    if (!Validate::isInt($user_variables['id_country'])){
	        $errors[] = Tools::displayError('invalid country');
	        unset($ps_address);
        }
	    elseif (Country::getIsoById($user_variables['id_country']) === ""){
	        $errors[] = Tools::displayError('invalid country');
	        unset($ps_address);
	    }
	
	    // Validate state
	    if (!Validate::isInt($user_variables['id_state'])){
	        $errors[] = Tools::displayError('invalid state');
	        unset($ps_address);
        }
	    elseif (!State::getNameById($user_variables['id_state'])){
	        if($user_variables['id_state'] === "0"){
	            /* state valid to apply for none state */ 
	        }
	        else{
	            $errors[] = Tools::displayError('invalid state');
	            unset($ps_address);
	        }
	    }
	
	    // Validate DNI
	    $validateDni = Validate::isDni($user_variables['dni']);
	    if ($user_variables['dni'] != NULL AND $validateDni != 1){
		    $error = array(
		    0 => Tools::displayError('DNI isn\'t valid'),
		    -1 => Tools::displayError('this DNI has been already used'),
		    -2 => Tools::displayError('NIF isn\'t valid'),
		    -3 => Tools::displayError('CIF isn\'t valid'),
		    -4 => Tools::displayError('NIE isn\'t valid')
		    );
		    $errors[] = $error[$validateDni];
		    unset($ps_customer);
	    }
	
	    // Validate alias
	    elseif (!Validate::isMessage($user_variables['alias'])){
	        $errors[] = Tools::displayError('invalid alias');
	        unset($ps_address);
	    }
	
        // Validate extra information 	
	    elseif (!Validate::isMessage($user_variables['other'])){
	        $errors[] = Tools::displayError('invalid extra information');
	        unset($ps_address);
	    }
	
	    /* Check if account already exists (not a validation) */
	    elseif (Customer::customerExists($user_variables['email'])){
	        $errors[] = Tools::displayError('someone has already registered with this e-mail address');
	        unset($ps_customer);
	    }
		
		/* enter customer account into prestashop database */ // if all information is validated
	    if(isset($ps_customer) && isset($ps_customer_group) && isset($ps_address))
	    {
	        foreach($ps_customer as $key => $value){
	            if($key == "id_customer" || $key == "secure_key" || $key == "last_passwd_gen" || $key == "newsletter_date_add" || $key == "date_add" || $key == "date_upd"){
	                if($key == "id_customer"){
	                    $insert_sql_columns = "INSERT INTO " . $tbp . "customer (";
                        $insert_sql_values = "VALUES ("; 
			        }
					
	                else{
	                    $insert_sql_columns .= ", " . $key;
                        $insert_sql_values .= ", '" . $value . "'"; 
					}
	            }
				
	            elseif($key == "id_gender"){
	                $insert_sql_columns .= "" . $key;
                    $insert_sql_values .= "'" . $value . "'";
                }
	            else{
	                $insert_sql_columns .= ", " . $key;
                    $insert_sql_values .= ", '" . $value . "'";
                }
	        }   
			
	        $insert_sql_columns .= ")";
            $insert_sql_values .= ")";
	        $query = $insert_sql_columns . $insert_sql_values;
	        $db->setQuery($query);
			$result = $db->query();
	
	        // enter customer group into database 
	        $query="SELECT id_customer FROM #__customer WHERE email = " . $db-Quote($ps_customer['email']);
            $db->setQuery($query);
			$result = $db->loadResult();
		    if (!$result)
			{
			    JText::_('REGISTRATION_ERROR');
			    echo('no matching userid');
			}
			else
			{
	            $ps_customer_group['id_customer'] = $result;
                $ps_address['id_customer'] = $result;
			}
			
	        foreach($ps_customer_group as $key => $value){
	            if($key == "id_customer"){
	                $insert_sql_columns = "INSERT INTO #__customer_group (" . $key;
                    $insert_sql_values = "VALUES (" . $db->Quote($value) ;
                }
	            else{
                    $insert_sql_columns .= ", " . $key;
                    $insert_sql_values .= ", " . $db->Quote($value);
                }
	        }
			
	        $insert_sql_columns .= ")";
            $insert_sql_values .= ")";
	        $query = $insert_sql_columns . $insert_sql_values;
	        $db->setQuery($query);
			$result = $db->query();
	 
	        // enter customer address into database after
	        foreach($ps_address as $key => $value){
                if($key == "id_address" || $key == "id_customer" || $key == "date_add" || $key == "date_upd"){
	                if($key == "id_address"){
	                    $insert_sql_columns = "INSERT INTO #__address (";
                        $insert_sql_values = "VALUES ("; 
				    }
	                else{
	                    $insert_sql_columns .= ", " . $key;
                        $insert_sql_values .= ", " . $db->Quote($value); 
				    }
			    }
	            elseif($key == "id_country"){
	                $insert_sql_columns .= $key;
                    $insert_sql_values .= $db->Quote($value);
	            }
	            else{
	                $insert_sql_columns .= ", " . $key;
                    $insert_sql_values .= ", " . $db-Quote($value);
	            }
	        }
			
	        $insert_sql_columns .= ")";
            $insert_sql_values .= ")";
	        $query = $insert_sql_columns . $insert_sql_values;
	        $db->setQuery($query);
			$result = $db->query();
			$status['debug'][] = JText::_('USER_CREATION');
            $status['userinfo'] = $this->getUser($userinfo);
		    return;
		}
	    else{ 
	        foreach ($errors as $key){
	            JText::_('PASSWORD_UPDATE_ERROR');
	        }
	    }
    }
    function updateEmail($userinfo, &$existinguser, &$status) {
        //we need to update the email
		$params = JFusionFactory::getParams($this->getJname());
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'UPDATE #__customer SET email =' . $db->Quote($userinfo->email) . ' WHERE id_customer =' . (int)$existinguser->userid;
        $db->setQuery($query);
        if (!$db->query()) {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
        } else {
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
        }
    }
    function activateUser($userinfo, &$existinguser, &$status) {
        /* change the �active� field of the customer in the ps_customer table to 1 */
		$params = JFusionFactory::getParams($this->getJname());
        $query = "UPDATE #__customer SET active ='1' WHERE id_customer ='" . (int)$existinguser->userid . "'";
        $db->setQuery($query);
    }
    function inactivateUser($userinfo, &$existinguser, &$status) {
        /* change the �active� field of the customer in the ps_customer table to 0 */
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = "UPDATE #__customer SET active ='0' WHERE id_customer ='" . (int)$existinguser->userid . "'";
        $db->setQuery($query);
    }
}