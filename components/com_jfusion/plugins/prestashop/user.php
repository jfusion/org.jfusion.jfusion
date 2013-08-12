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
    /**
     * @param object $userinfo
     *
     * @return null|object
     */
    function getUser($userinfo) {
	    try {
		    //get the identifier
		    $identifier = $userinfo;
		    if (is_object($userinfo)) {
			    $identifier = $userinfo->email;
		    }
		    // Get user info from database
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $query = 'SELECT id_customer as userid, email, passwd as password, firstname, lastname FROM #__customer WHERE email =' . $db->Quote($identifier) ;
		    $db->setQuery($query);
		    $result = $db->loadObject();
		    $result->block = 0;
		    $result->activation = '';
		    if ($result) {
			    $query = 'SELECT id_customer as userid, email, passwd as password, firstname, lastname FROM #__customer_group WHERE id_customer =' . $db->Quote($result->userid);
			    $db->setQuery($query);
			    $groups = $db->loadObjectList();

			    if ($groups) {
				    foreach($groups as $group) {
					    $result->group_id = $group->id_group;
					    $result->groups[] = $result->group_id;
				    }
			    }
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
		    $result = null;
	    }
        // read through params for cookie key (the salt used)
        return $result;
    }

    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */    
    function getJname() 
    {
        return 'prestashop';
    }

    /**
     * @param object $userinfo
     *
     * @return array
     */
    function deleteUser($userinfo) {
	    try {
		    /* Warning: this function mimics the original prestashop function which is a suggestive deletion,
				all user information remains in the table for past reference purposes. To delete everything associated
				with an account and an account itself, you will have to manually delete them from the table yourself. */
		    // get the identifier
		    $identifier = $userinfo;
		    if (is_object($userinfo)) {
			    $identifier = $userinfo->id_customer;
		    }
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__customer')
			    ->set('deleted = 1')
			    ->where('id_customer = ' . $db->Quote($identifier));

		    $db->setQuery($query);
		    $status['debug'][] = 'Deleted user';
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
		return $status;
    }

    /**
     * @param object $userinfo
     * @param string $option
     *
     * @return array
     */
    function destroySession($userinfo, $option) {
        $status = array('error' => array(),'debug' => array());
	    // use prestashop cookie class and functions to delete cookie
		$params = JFusionFactory::getParams($this->getJname());
		require_once $params->get('source_path') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings.inc.php';
	    require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Cookie.php');
		require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Blowfish.php');
		require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Tools.php');
		require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'ObjectModel.php');
		require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Db.php');
		require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'SubDomain.php');
        $cookie = new CookieCore('ps', '', '');
	    $cookie->logout();
	    $status['debug'][] = 'Deleted session and session data';
		return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @param bool $framework
     *
     * @return array
     */
    function createSession($userinfo, $options, $framework = true) {
	    try {
		    $params = JFusionFactory::getParams($this->getJname());
		    $status = array('error' => array(),'debug' => array());
		    // this uses a code extract from authentication.php that deals with logging in completely
		    $db = JFusionFactory::getDatabase($this->getJname());
		    require_once $params->get('source_path') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings.inc.php';
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Cookie.php');
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Blowfish.php');
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Tools.php');
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'ObjectModel.php');
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Db.php');
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'SubDomain.php');
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Validate.php');
		    $cookie = new CookieCore('ps', '', '');
		    $passwd = $userinfo->password_clear;
		    $email = $userinfo->email;
		    $passwd = trim($passwd);
		    $email = trim($email);
		    if (empty($email)) {
			    throw new RuntimeException('invalid e-mail address');
		    } elseif (!Validate::isEmail($email)) {
			    throw new RuntimeException('invalid e-mail address');
		    } elseif (empty($passwd)) {
			    throw new RuntimeException('password is required');
		    } elseif (Tools::strlen($passwd) > 32) {
			    throw new RuntimeException('password is too long');
		    } elseif (!Validate::isPasswd($passwd)) {
			    throw new RuntimeException('invalid password');
		    } else {
			    /* Handle brute force attacks */
			    sleep(1);
			    // check if password matches
			    $query = 'SELECT passwd FROM #__customer WHERE email = ' . $db->Quote($email);
			    $db->setQuery($query);
			    $result = $db->loadResult();
			    if (!$result) {
				    throw new RuntimeException('authentication failed');
			    } else {
				    if(md5($params->get('cookie_key') . $passwd) === $result) {
					    $cookie->id_customer = $userinfo->userid;
					    $cookie->customer_lastname = $userinfo->lastname;
					    $cookie->customer_firstname = $userinfo->firstname;
					    $cookie->logged = 1;
					    $cookie->passwd = md5($params->get('cookie_key') . $passwd);
					    $cookie->email = $email;
				    } else {
					    throw new RuntimeException('wrong password');
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
        return $status;
	}

    /**
     * @param string $username
     *
     * @return string
     */
    function filterUsername($username) {
        return $username;
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status) {
	    try {
	        jimport('joomla.user.helper');
	        $existinguser->password_salt = JUserHelper::genRandomPassword(8);
	        $existinguser->password = md5($userinfo->password_clear . $existinguser->password_salt);
	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__customer')
			    ->set('password = ' . $db->Quote($existinguser->password))
			    ->set('salt = ' . $db->Quote($existinguser->password_salt))
			    ->where('id_customer = ' . $db->Quote((int)$existinguser->userid));

	        $db->setQuery($query);

		    $db->execute();
		    $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param array $status
     *
     * @return void
     */
    function createUser($userinfo, &$status) {
	    try {
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $params = JFusionFactory::getParams($this->getJname());
		    $errors = array();
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "Validate.php");
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "ObjectModel.php");
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "Db.php");
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "Country.php");
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "State.php");
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "Tools.php");
		    require($params->get('source_path') . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR . "Customer.php");

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
			    'firstname' => $uf_name, // alphanumeric values between 6 and 32 characters long
			    'lastname' => $end_name, // alphanumeric values between 6 and 32 characters long
			    'customer_firstname' => $uf_name, // alphanumeric values between 6 and 32 characters long
			    'customer_lastname' => $end_name, // alphanumeric values between 6 and 32 characters long
			    'email' => $userinfo->email, // alphanumeric values as well as @ and . symbols between 6 and 128 characters long
			    'passwd' => $userinfo->password_clear, // alphanumeric values between 6 and 32 characters long
			    'days' => "01", // numeric character between 1 and 31
			    'months' => "01", // numeric character between 1 and 12
			    'years' => "2000", // numeric character between 1900 and latest year
			    'newsletter' => 0, // value of either 0 for no newsletters, or 1 to relieve newsletters
			    'optin' => 0, // value of either 0 for no third party options, or 1 to relieve third party options
			    'company' => "", // alphanumeric values between 6 and 32 characters long
			    'address1' => "Update with your real address", // alphanumeric values between 6 and 128 characters long
			    'address2' => "", // alphanumeric values between 6 and 128 characters long
			    'postcode' => "Postcode", // alphanumeric values between 7 and 12 characters long
			    'city' => "Not known", // alpha values between 6 and 64 characters long
			    'id_country' => "17", // numeric character between 1 and 244 (normal preset)
			    'id_state' => "0", // numeric character between 1 and 65 (normal preset)
			    'other' => "", // alphanumeric values with mysql text limit characters long
			    'phone' => "", // numeric values between 11 and 16 characters long
			    'phone_mobile' => "", // numeric values between 11 and 16 characters long
			    'alias' => "My address", // alphanumeric values between 6 and 32 characters long
			    'dni' => "", // alphanumeric values between 6 and 16 characters long
		    );

		    $ps_customer = new stdClass;
		    $ps_customer->id_customer = null;
		    $ps_customer->id_gender = $user_variables['id_gender'];
		    $ps_customer->id_default_group = 1;
		    $ps_customer->secure_key = md5(uniqid(rand(), true));
		    $ps_customer->email = $user_variables['email'];
		    $ps_customer->passwd = md5($params->get('cookie_key') . $user_variables['passwd']);
		    $ps_customer->last_passwd_gen = date('Y-m-d h:m:s',strtotime("-6 hours"));
		    $ps_customer->birthday = date('Y-m-d',mktime(0,0,0,$user_variables['months'],$user_variables['days'],$user_variables['years']));
		    $ps_customer->lastname = $user_variables['lastname'];
		    $ps_customer->newsletter = $_SERVER['REMOTE_ADDR'];
		    $ps_customer->ip_registration_newsletter = date('Y-m-d h:m:s');
		    $ps_customer->optin = $user_variables['optin'];
		    $ps_customer->firstname = $user_variables['firstname'];
		    $ps_customer->dni = $user_variables['dni'];
		    $ps_customer->active = 1;
		    $ps_customer->deleted = 0;
		    $ps_customer->date_add = date('Y-m-d h:m:s');
		    $ps_customer->date_upd = date('Y-m-d h:m:s');


		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);

		    /* array to go into table ps_address */
		    $ps_address = new stdClass;
		    $ps_address->id_address = null;
		    $ps_address->id_country = $user_variables['id_country'];
		    $ps_address->id_state = $user_variables['id_state'];
		    $ps_address->id_manufacturer = 0;
		    $ps_address->id_supplier = 0;
		    $ps_address->alias = $user_variables['alias'];
		    $ps_address->company = $user_variables['company'];
		    $ps_address->lastname = $user_variables['customer_lastname'];
		    $ps_address->firstname = $user_variables['customer_firstname'];
		    $ps_address->address1 = $user_variables['address1'];
		    $ps_address->address2 = $user_variables['address2'];
		    $ps_address->postcode = $user_variables['postcode'];
		    $ps_address->city = $user_variables['city'];
		    $ps_address->other = $user_variables['other'];
		    $ps_address->phone = $user_variables['phone'];
		    $ps_address->phone_mobile = $user_variables['phone_mobile'];
		    $ps_address->date_add = date('Y-m-d h:m:s');
		    $ps_address->date_upd = date('Y-m-d h:m:s');
		    $ps_address->active = 1;
		    $ps_address->deleted = 0;

		    /* safe data check and validation of array $user_variables
			no other unique variables are used so this check only includes these */
		    // Do not validate address line 1 since a placeholder is been currently used

		    /*if (!Validate::isAddress($user_variables['address1'])){
			  $errors[] = Tools::displayError('address wrong');
			  unset($ps_address);
		    }*/



		    // Do not validate postcode since a placeholder is been currently used
		    /*if (!Validate::isPostCode($user_variables['postcode'])){
			  $errors[] = Tools::displayError('postcode wrong');
			  unset($ps_address);
		    }*/



		    // Do not validate village/town/city since a placeholder is been currently used
		    /*if (!Validate::isCityName($user_variables['city'])){
			  $errors[] = Tools::displayError('invalid village/town/city');
			  unset($ps_address);
		    }*/

		    // Validate gender
		    if (!Validate::isGenderIsoCode($user_variables['id_gender'])) {
			    throw new RuntimeException(Tools::displayError('gender not valid'));
		    } elseif (!Validate::isName($user_variables['firstname'])) {
			    throw new RuntimeException(Tools::displayError('first name wrong'));
		    } elseif (!Validate::isName($user_variables['lastname'])) {
			    throw new RuntimeException(Tools::displayError('second name wrong'));
		    } elseif (!Validate::isName($user_variables['customer_firstname'])) {
			    throw new RuntimeException(Tools::displayError('customer first name wrong'));
		    } elseif (!Validate::isName($user_variables['customer_lastname'])) {
			    throw new RuntimeException(Tools::displayError('customer second name wrong'));
		    } elseif (!Validate::isEmail($user_variables['email'])) {
			    throw new RuntimeException(Tools::displayError('e-mail not valid'));
		    } elseif (!Validate::isPasswd($user_variables['passwd'])) {
			    throw new RuntimeException(Tools::displayError('invalid password'));
		    } elseif (!@checkdate($user_variables['months'], $user_variables['days'], $user_variables['years']) AND !( $user_variables['months']== '' AND $user_variables['days'] == '' AND $user_variables['years'] == '')) {
			    throw new RuntimeException(Tools::displayError('invalid birthday'));
		    } elseif (!Validate::isBool($user_variables['newsletter'])) {
			    throw new RuntimeException(Tools::displayError('newsletter invalid choice'));
		    } elseif (!Validate::isBool($user_variables['optin'])) {
			    throw new RuntimeException(Tools::displayError('optin invalid choice'));
		    } elseif (!Validate::isGenericName($user_variables['company'])) {
			    throw new RuntimeException(Tools::displayError('company name wrong'));
		    } elseif (!Validate::isAddress($user_variables['address2'])) {
			    throw new RuntimeException(Tools::displayError('address 2nd wrong'));
		    } elseif (!Validate::isPhoneNumber($user_variables['phone'])) {
			    throw new RuntimeException(Tools::displayError('invalid phone'));
		    } elseif (!Validate::isPhoneNumber($user_variables['phone_mobile'])) {
			    throw new RuntimeException(Tools::displayError('invalid mobile'));
		    } elseif (!Validate::isInt($user_variables['id_country'])) {
			    throw new RuntimeException(Tools::displayError('invalid country'));
		    } elseif (Country::getIsoById($user_variables['id_country']) === '') {
			    throw new RuntimeException(Tools::displayError('invalid country'));
		    } elseif (!Validate::isInt($user_variables['id_state'])) {
			    throw new RuntimeException(Tools::displayError('invalid state'));
		    } else {
			    if (!State::getNameById($user_variables['id_state'])){
				    if($user_variables['id_state'] === '0'){
					    /* state valid to apply for none state */
				    } else {
					    unset($ps_customer);
					    throw new RuntimeException(Tools::displayError('invalid state'));
				    }
			    }

			    if(isset($ps_customer)) {
				    // Validate DNI
				    $validateDni = Validate::isDni($user_variables['dni']);
				    if ($user_variables['dni'] != NULL && $validateDni != 1) {
					    $error = array(
						    0 => Tools::displayError('DNI isn\'t valid'),
						    -1 => Tools::displayError('this DNI has been already used'),
						    -2 => Tools::displayError('NIF isn\'t valid'),
						    -3 => Tools::displayError('CIF isn\'t valid'),
						    -4 => Tools::displayError('NIE isn\'t valid')
					    );
					    throw new RuntimeException($error[$validateDni]);
				    } elseif (!Validate::isMessage($user_variables['alias'])) {
					    throw new RuntimeException(Tools::displayError('invalid alias'));
				    } elseif (!Validate::isMessage($user_variables['other'])) {
					    throw new RuntimeException(Tools::displayError('invalid extra information'));
				    } elseif (Customer::customerExists($user_variables['email'])) {
					    throw new RuntimeException(Tools::displayError('someone has already registered with this e-mail address'));
				    } else {
					    /* enter customer account into prestashop database */ // if all information is validated
					    $db->insertObject('#__customer', $ps_customer, 'id_customer');

					    // enter customer group into database
					    $ps_address->id_customer = $ps_customer->id_customer;

					    foreach($usergroups as $value) {
						    $ps_customer_group = new stdClass;
						    $ps_customer_group->id_customer = $ps_customer->id_customer;
						    $ps_customer_group->id_group = $value;
						    $db->insertObject('#__customer_group', $ps_customer_group);
					    }

					    $db->insertObject('#__address', $ps_address);

					    $status['debug'][] = JText::_('USER_CREATION');
					    $status['userinfo'] = $this->getUser($userinfo);
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USER_CREATION_ERROR') .' '. $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status) {
	    try {
		    //we need to update the email
		    $params = JFusionFactory::getParams($this->getJname());
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__customer')
			    ->set('email = ' . $db->Quote($userinfo->email))
			    ->where('id_customer = ' . $db->Quote((int)$existinguser->userid));

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('PASSWORD_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function activateUser($userinfo, &$existinguser, &$status) {
	    try {
		    /* change the 'active' field of the customer in the ps_customer table to 1 */
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__customer')
			    ->set('active = 1')
			    ->where('id_customer = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function inactivateUser($userinfo, &$existinguser, &$status) {
	    try {
		    /* change the 'active' field of the customer in the ps_customer table to 0 */
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__customer')
			    ->set('active = 0')
			    ->where('id_customer = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status) {
	    try {
		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
		    } else {
			    $db = JFusionFactory::getDatabase($this->getJname());
			    // now delete the user
			    $query = 'DELETE FROM #__customer_group WHERE id_customer = ' . $existinguser->userid;
			    $db->setQuery($query);
			    $db->execute();

			    foreach($usergroups as $value) {
				    $group = new stdClass;
				    $group->id_customer = $existinguser->userid;
				    $group->id_group = $value;
				    $db->insertObject('#__customer_group', $group);
			    }
			    $status['debug'][] = JText::_('GROUP_UPDATE'). ': ' . implode (' , ', $existinguser->groups) . ' -> ' . implode (' , ', $usergroups);
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $e->getMessage();
	    }
    }
}