<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_oscommerce extends JFusionUser 
{
    /**
     * @param object $userinfo
     * @return null|object
     */
    function getUser($userinfo) {
	    try {
		    $identifier = $userinfo;
		    if (is_object($userinfo)) {
			    $identifier = $userinfo->email;
		    }
		    $osCversion = $this->params->get('osCversion');
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('customers_id')
			    ->from('#__customers')
			    ->where('customers_email_address = ' . $db->Quote($identifier));

		    $db->setQuery($query);
		    $userid = $db->loadResult();
		    if ($userid) {
			    $query1 = $query2 = null;
			    switch ($osCversion) {
				    case 'osc2':
					    $query1 = $db->getQuery(true)
						    ->select('customers_id as userid, 0 as group_id, customers_firstname as name, customers_lastname as lastname, customers_password as password, null as password_salt')
						    ->from('#__customers')
						    ->where('customers_id = ' . $db->Quote($userid));

					    $query2 = $db->getQuery(true)
						    ->select('customers_info_date_account_created as registerDate, customers_info_date_of_last_logon as lastvisitDate, customers_info_date_account_last_modified as modifiedDate')
						    ->from('#__customers_info')
						    ->where('customers_info_id = ' . $db->Quote($userid));
					    break;
				    case 'osc3':
					    $query1 = $db->getQuery(true)
						    ->select('customers_id as userid, 0 as group_id, customers_firstname as name, customers_lastname as lastname, customers_password as password, null as password_salt, date_account_created as registerDate, date_last_logon as lastvisitDate, date_account_last_modified as modifiedDate')
						    ->from('#__customers')
						    ->where('customers_id = ' . $db->Quote($userid));
					    break;
				    case 'osczen':
					    $query1 = $db->getQuery(true)
						    ->select('customers_id as userid, customers_group_pricing as group_id, customers_firstname as name, customers_lastname as lastname, customers_password as password, null as password_salt')
						    ->from('#__customers')
						    ->where('customers_id = ' . $db->Quote($userid));

					    $query2 = $db->getQuery(true)
						    ->select('customers_info_date_account_created as registerDate, customers_info_date_of_last_logon as lastvisitDate, customers_info_date_account_last_modified as modifiedDate')
						    ->from('#__customers_info')
						    ->where('customers_info_id = ' . $db->Quote($userid));
					    break;
				    case 'oscxt':
				    case 'oscseo':
					    $query1 = $db->getQuery(true)
						    ->select('customers_id as userid, customers_status as group_id, customers_firstname as name, customers_lastname as lastname, customers_password as password, null as password_salt')
						    ->from('#__customers')
						    ->where('customers_id = ' . $db->Quote($userid));

					    $query2 = $db->getQuery(true)
						    ->select('customers_info_date_account_created as registerDate, customers_info_date_of_last_logon as lastvisitDate, customers_info_date_account_last_modified as modifiedDate')
						    ->from('#__customers_info')
						    ->where('customers_info_id = ' . $db->Quote($userid));
					    break;
				    case 'oscmax':
					    $query1 = $db->getQuery(true)
						    ->select('customers_id as userid, customers_group_id as group_id, customers_firstname as name, customers_lastname as lastname, customers_password as password, null as password_salt ')
						    ->from('#__customers')
						    ->where('customers_id = ' . $db->Quote($userid));

					    $query2 = $db->getQuery(true)
						    ->select('customers_info_date_account_created as registerDate, customers_info_date_of_last_logon as lastvisitDate, customers_info_date_account_last_modified as modifiedDate')
						    ->from('#__customers_info')
						    ->where('customers_info_id = ' . $db->Quote($userid));
					    break;
			    }
			    if ($query1) {
				    // get the details
				    $db->setQuery($query1);
				    $result = $db->loadObject();
				    $result->username = $identifier;
				    $result->email = $identifier;
				    if (!empty($result->activation)) {
					    $result->activation = !$result->activation;
				    }
				    $result->groups = array($result->group_id);

				    $result->activation = '';
				    $result->block = 0;
				    $password = $result->password;
				    $hashArr = explode(':', $password);
				    $result->password = $hashArr[0];
				    if (!empty($hashArr[1])) {
					    $result->password_salt = $hashArr[1];
				    }
				    if ($result) {
					    if ($query2) {
						    $db->setQuery($query2);
						    $result1 = $db->loadObject();
						    if ($result1) {
							    $result->registerDate = $result1->registerDate;
							    $result->lastvisitDate = $result1->lastvisitDate;
							    $result->modifiedDate = $result1->modifiedDate;
						    }
					    }
				    }
				    return $result;
			    }
		    }
	    } catch (Exception $e) {
			JFusionFunction::raiseError($e, $this->getJname());
	    }
        return null;
    }
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'oscommerce';
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());
	    try {
		    $userinfo->username = $userinfo->email;
		    $osCversion = $this->params->get('osCversion');

		    switch ($osCversion) {
			    case 'osc3':
				    $session_id=$_COOKIE['osCsid'];
				    if ($session_id == '') {
					    $status['error'][] = 'Error Could find session cookie make sure COOKIE PATH IS SET TO / in both osC and JFusion plugin settings';
				    } else {
					    $db = JFusionFactory::getDatabase($this->getJname());
					    $query = $db->getQuery(true)
						    ->delete('#__sessions')
						    ->where('id = ' . $db->quote($session_id));

					    $db->setQuery($query);
					    try {
						    $db->execute();
						    $status['debug'][] = 'Deleted sessionrecord with id '.$session_id;
					    } catch (Exception $e) {
						    $status['error'][] = 'Error Could not delete session with sessionID '.$session_id.': '.$e->getMessage();
					    }
				    }
				    break;
			    default:
				    $status = $this->curlLogout($userinfo, $options, $this->params->get('logout_type'));
		    }
	    } catch (Exception $e) {
		    JFusionFunction::raiseError($e, $this->getJname());
	    }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array|string
     */
    function createSession($userinfo, $options) {
        // need to make the username equal the email
        $userinfo->username = $userinfo->email;
        return $this->curlLogin($userinfo, $options, $this->params->get('brute_force'));
    }

    /**
     * @param string $username
     * @return string
     */
    function filterUsername($username) {
        //no username filtering implemented yet
        return $username;
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status) {
	    try {
		    $osCversion = $this->params->get('osCversion');
		    $existinguser->password = '';
		    for ($i = 0;$i < 10;$i++) {
			    $existinguser->password.= mt_rand((double)microtime() * 1000000);
		    }
		    $salt = substr(md5($existinguser->password), 0, 2);
		    $existinguser->password = md5($salt . $userinfo->password_clear) . ':' . $salt;
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $modified_date = date('Y-m-d H:i:s', time());
		    $query1 = $query2 = null;
		    switch ($osCversion) {
			    case 'osc2':
			    case 'osczen':
			    case 'oscxt':
			    case 'oscseo':
			    case 'oscmax':
			        $query1 = (string)$db->getQuery(true)
					    ->update('#__customers')
					    ->set('customers_password = ' . $db->quote($existinguser->password))
					    ->where('customers_id  = ' . $db->Quote($existinguser->userid));

			        $query2 = (string)$db->getQuery(true)
					    ->update('#__customers_info')
					    ->set('customers_info_date_account_last_modified = ' . $db->quote($modified_date))
					    ->where('customers_info_id  = ' . $db->quote($existinguser->userid));
				    break;
			    case 'osc3':
				    $query1 = (string)$db->getQuery(true)
					    ->update('#__customers')
					    ->set('customers_password = ' . $db->quote($existinguser->password))
					    ->set('date_account_last_modified = ' . $db->quote($modified_date))
					    ->where('customers_id  = ' . $db->Quote($existinguser->userid));
				    break;
		    }
		    if ($query1) {
			    $db->transactionStart();
			    $db->setQuery($query1);
			    $db->execute();

			    if ($query2) {
				    $db->setQuery($query2);
				    $db->execute();
			    }
			    $db->transactionCommit();
			    $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
		    } else {
			    throw new RuntimeException();
		    }
	    } catch (Exception $e) {
		    if (isset($db)) {
			    $db->transactionRollback();
		    }
		    $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateUsername($userinfo, &$existinguser, &$status) {
        // no username in oscommerce
        
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status) {
	    try {
		    $osCversion = $this->params->get('osCversion');
		    //we need to update the email
		    $db = JFusionFactory::getDatabase($this->getJname());
		    $modified_date = date('Y-m-d H:i:s', time());
		    $query1 = $query2 = null;
		    switch ($osCversion) {
			    case 'osc2':
			    case 'osczen':
			    case 'oscxt':
			    case 'oscseo':
			    case 'oscmax':
				    $query1 = (string)$db->getQuery(true)
					    ->update('#__customers')
					    ->set('customers_email_address = ' . $db->quote($existinguser->email))
					    ->where('customers_id  = ' . $db->quote($existinguser->userid));

				    $query2 = (string)$db->getQuery(true)
					    ->update('#__customers_info')
					    ->set('customers_info_date_account_last_modified = ' . $db->quote($modified_date))
					    ->where('customers_info_id  = ' . $db->quote($existinguser->userid));
				    break;
			    case 'osc3':
				    $query1 = (string)$db->getQuery(true)
					    ->update('#__customers')
					    ->set('customers_email_address = ' . $db->quote($existinguser->email))
					    ->set('date_account_last_modified = ' . $db->quote($modified_date))
					    ->where('customers_id  = ' . $db->Quote($existinguser->userid));
				    break;
		    }
		    if ($query1) {
			    $db->transactionStart();
			    $db->setQuery($query1);
			    $db->execute();

			    if ($query2) {
				    $db->setQuery($query2);
				    $db->execute();
			    }
		    } else {
			    throw new RuntimeException();
		    }
	    } catch (Exception $e) {
		    if (isset($db)) {
			    $db->transactionRollback();
		    }
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
        //activate the user not supported
        
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function inactivateUser($userinfo, &$existinguser, &$status) {
        // inactivate the user is not supported
        
    }

    /**
     * @param object $userinfo
     * @param array $status
     *
     * @return void
     */
    function createUser($userinfo, &$status) {
	    try {
		    $osCversion = $this->params->get('osCversion');
		    $db = JFusionFactory::getDatabase($this->getJname());
		    //prepare the variables
		    $user = new stdClass;
		    $user->customers_id = null;
		    $user->customers_gender = 'm'; // ouch, empty is female, so this is an arbitrarily choice
		    $parts = explode(' ', $userinfo->name);
		    $user->customers_firstname = $parts[0];
		    $lastname = '';
		    if ($parts[(count($parts) - 1) ]) {
			    for ($i = 1;$i < (count($parts));$i++) {
				    $lastname = $lastname . ' ' . $parts[$i];
			    }
		    }
		    $user->customers_lastname = $lastname;
		    // $user->customers_dob = ''; date of birth
		    $user->customers_email_address = strtolower($userinfo->email);
		    $user->customers_default_address_id = null;
		    $user->customers_telephone = '';
		    //$user->customers_fax = null;
		    if (isset($userinfo->password_clear)) {
			    if ($osCversion != 'oscxt') {
				    $user->customers_password = '';
				    for ($i = 0;$i < 10;$i++) {
					    $user->customers_password.= mt_rand((double)microtime() * 1000000);
				    }
				    $salt = substr(md5($user->customers_password), 0, 2);
				    $user->customers_password = md5($salt . $userinfo->password_clear) . ':' . $salt;
			    } else {
				    $user->customers_password = md5($userinfo->password_clear);
			    }
		    } else {
			    if (!empty($userinfo->password_salt)) {
				    $user->customers_password = $userinfo->password . ':' . $userinfo->password_salt;
			    } else {
				    $user->customers_password = $userinfo->password;
			    }
		    }
		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
		    //    $user->customers_newsletter = null;
		    switch ($osCversion) {
			    case 'osc2':
				    // nothing extra, this is the basic osCommerce

				    break;
			    case 'osc3':
				    $user->customers_status = 1;
				    $user->number_of_logons = 0;
				    // $user->customers_ip_address = null;
				    $user->date_account_created = date('Y-m-d H:i:s', time());
				    // $user->date_account_last_modified = date ( 'Y-m-d H:i:s', time ());
				    $user->global_product_notifications = 0;
				    break;
			    case 'osczen':
				    if (empty($usergroups)) {
					    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
				    }
				    $user->customers_group_pricing = $usergroups[0];
				    //        $user->customers_paypal_ec = '0';   // must be an unique number?????.

				    break;
			    case 'oscxt':
			    case 'oscseo':
				    if (empty($usergroups)) {
					    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
				    }
				    $user->customers_status = $usergroups[0];
				    //        $user->customers_paypal_ec = '0';   // must be an unique number?????.

				    break;
			    case 'oscmax':
				    if (empty($usergroups)) {
					    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
				    }
				    $user->customers_group_id = $usergroups[0];
				    // get the groupname
				    $db1 = JFusionFactory::getDatabase($this->getJname());

				    $query = $db->getQuery(true)
					    ->select('customers_group_name')
					    ->from('#__customers_groups')
					    ->where('customers_group_id = ' . $user->customers_group_id)
				        ->where('language_id = ' . $userinfo->language);

				    $db1->setQuery($query);
				    $user->customers_group_name = $db1->loadResult();
				    break;
		    }
		    //now append the new user data
		    $db->transactionStart();
		    $ok = $db->insertObject('#__customers', $user, 'customers_id');
		    if ($ok) {
			    $userid = $db->insertid();
			    // make a default address/ This is mandatory, but ala, we don't have much info!
			    $user_1 = new stdClass;
			    $user_1->customers_id = $userid;
			    $user_1->entry_gender = $user->customers_gender;
			    $user_1->entry_firstname = $user->customers_firstname;
			    $user_1->entry_lastname = $user->customers_lastname;

			    $default_country = $this->params->get('default_country');
			    $user_1->entry_country_id = $default_country;
			    $ok = $db->insertObject('#__address_book', $user_1, 'address_book_id');
			    if ($ok) {
				    $infoid = $db->insertid();
				    $query = $db->getQuery(true)
					    ->update('#__customers')
					    ->set('customers_default_address_id = ' . $db->quote((int)$infoid))
					    ->where('customers_id  = ' . $db->Quote((int)$userid));

				    $db->setquery($query);
				    $ok = $db->execute();
				    if ($ok) {
					    // need to set the customer ifo for some integrations
					    switch ($osCversion) {
						    case 'osc2':
						    case 'osczen':
						    case 'oscxt':
						    case 'oscseo':
						    case 'oscmax':
							    $user_1 = new stdClass;
							    $user_1->customers_info_id = $userid;
							    $user_1->customers_info_date_of_last_logon = null;
							    $user_1->customers_info_number_of_logons = null;
							    $user_1->customers_info_date_account_created = date('Y-m-d H:i:s', time());
							    $user_1->customers_info_date_account_last_modified = null;
							    $user_1->global_product_notifications = 0;
							    $ok = $db->insertObject('#__customers_info', $user_1, 'customers_info_id');
							    break;
					    }
					    if ($ok) {
						    $db->transactionCommit();
						    $status['debug'][] = JText::_('USER_CREATION');
						    $status['userinfo'] = $this->getUser($userinfo);
					    }
				    }
			    }
		    }
	    } catch (Exception $e) {
		    if (isset($db)) {
			    $db->transactionRollback();
		    }
		    $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' .$e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @return array|bool
     */
    function deleteUser($userinfo) {
	    $status = array('error' => array(),'debug' => array());
	    try {
		    $osCversion = $this->params->get('osCversion');
		    $db = JFusionFactory::getDatabase($this->getJname());
		    //setup status array to hold debug info and errors

		    //set the userid
		    //check to see if a valid $userinfo object was passed on
		    if (!is_object($userinfo)) {
			    throw new RuntimeException(JText::_('NO_USER_DATA_FOUND'));
		    }
		    $existinguser = $this->getUser($userinfo);
		    if (!empty($existinguser)) {
			    $querys = array();
			    $errors = array();
			    $debug = array();
			    $user_id = $existinguser->userid;

			    // Delete userrecordosc2 & osc3 & osczen & oscxt &oscmax
			    $querys[] = $db->getQuery(true)
				    ->delete('#__customers')
				    ->where('customers_id = ' . $db->quote($user_id));
			    $errors[] = 'Error Could not delete userrecord with userid '.$user_id;
			    $debug[] = 'Deleted userrecord of user with id '.$user_id;

			    // delete adressbook items osc2 & osc3 & osczen & oscxt & oscmax
			    $querys[] = $db->getQuery(true)
				    ->delete('#__address_book')
				    ->where('customers_id = ' . $db->quote($user_id));
			    $errors[] = 'Error Could not delete addressbookitems with userid '.$user_id;
			    $debug[] = 'Deleted addressbook items of user with id '.$user_id;

			    // delete customer from who's on line osc2 & osc3 & osczen & oscxt & oscmax
			    $querys[] = $db->getQuery(true)
				    ->delete('#__whos_online')
				    ->where('customers_id = ' . $db->quote($user_id));
			    $errors[] = 'Error Could not delete customer on line with userid '.$user_id;
			    $debug[] = 'Deleted customer online entry of user with id '.$user_id;

			    // delete review items osc2 & osc3 &  osczen & oscxt
			    $delete_reviews = $this->params->get('delete_reviews');
			    if ($delete_reviews == '1') {
				    try {
					    $query = $db->getQuery(true)
						    ->select('reviews_id')
						    ->from('#__reviews')
						    ->where('customers_id = ' . $db->quote((int)$user_id));

					    $db->setQuery($query);
					    $db->execute();
					    $reviews = $db->loadObjectList();
					    foreach ($reviews as $review) {
						    $db->setQuery('DELETE from #__reviews_description where reviews_id = \'' . (int)$review->reviews_id . '\'');
						    $db->execute();
					    }
				    } catch (Exception $e) {

				    }
				    $querys[] = $db->getQuery(true)
					    ->delete('#__reviews')
					    ->where('customers_id = ' . (int)$user_id);
				    $errors[] = 'Error Could not delete customer reviews with userid '.$user_id;
				    $debug[] = 'Deleted customer rieviews of user with id '.$user_id;
			    } else {
				    $querys[] = (string)$db->getQuery(true)
					    ->update('#__reviews')
					    ->set('customers_id = null')
					    ->where('customers_id  = ' . $db->Quote((int)$user_id));

				    $errors[] = 'Error Could not delete customer reviews with userid '.$user_id;
				    $debug[] = 'Deleted customer rieviews of user with id '.$user_id;
			    }

			    switch ($osCversion) {
				    case 'oscxt':
				    case 'oscseo':
					    $querys[] = $db->getQuery(true)
						    ->delete('#__products_notifications')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete product notifications with userid '.$user_id;
					    $debug[] = 'Deleted products notifications of user with id '.$user_id;

					    $querys[] = $db->getQuery(true)
						    ->delete('#__customers_customers_status_history')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete customer history with userid '.$user_id;
					    $debug[] = 'Deleted customer history of user with id '.$user_id;

					    $querys[] = $db->getQuery(true)
						    ->delete('#__customers_ip')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete customer ip with userid '.$user_id;
					    $debug[] = 'Deleted customer ip of user with id '.$user_id;

					    $querys[] = $db->getQuery(true)
						    ->delete('#__admin_access')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete admin accessid '.$user_id;
					    $debug[] = 'Deleted admin accessith id '.$user_id;
					    break;
				    case 'osc2':
				    case 'osczen':
				    case 'oscmax':
					    // Delete user info osc2 & osczen & oscxt
					    $querys[] = $db->getQuery(true)
						    ->delete('#__customers_info')
						    ->where('customers_info_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete useinfo with userid '.$user_id;
					    $debug[] = 'Deleted userinfo of user with id '.$user_id;

				        // delete  customer basket osc2 & osczen
					    $querys[] = $db->getQuery(true)
						    ->delete('#__customers_basket')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete customer basket with userid '.$user_id;
					    $debug[] = 'Deleted customer basket items of user with id '.$user_id;

					    // delete  customer basket attributes osc2 & osczen
					    $querys[] = $db->getQuery(true)
						    ->delete('#__customers_basket_attributes')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete customer basket attributes with userid '.$user_id;
					    $debug[] = 'Deleted customer basket attributes items of user with id '.$user_id;
					    break;
				    case 'osc3':
					    $querys[] = $db->getQuery(true)
						    ->delete('#__shopping_carts')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete customer shopping cart with userid '.$user_id;
					    $debug[] = 'Deleted customer shopping cart of user with id '.$user_id;

					    $querys[] = $db->getQuery(true)
						    ->delete('#__shopping_carts_custom_variants_values')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete customer shopping cart variantswith userid '.$user_id;
					    $debug[] = 'Deleted customer shopping cart variants of user with id '.$user_id;

					    $querys[] = $db->getQuery(true)
						    ->delete('#__product_notifications')
						    ->where('customers_id = ' . $db->quote($user_id));
					    $errors[] = 'Error Could not delete customer product notifications with userid '.$user_id;
					    $debug[] = 'Deleted customer product notifications of user with id '.$user_id;
					    break;
			    }

			    foreach($querys as $key => $value){
				    try {
					    $db->setQuery($value);
					    $db->execute();
					    $status['debug'][] = $debug[$key];
				    } catch (Exception $e) {
					    $status['error'][] = $errors[$key].': '.$e->getMessage();
						break;
				    }
			    }
			    return $status;
		    }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
	    return $status;
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status) {
	    try {
		    $osCversion = $this->params->get('osCversion');
		    $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(), $userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
		    } else {
			    $usergroup = $usergroups[0];
			    $db = JFusionFactory::getDataBase($this->getJname());
			    switch ($osCversion) {
				    case 'osczen':
					    //set the usergroup in the user table
					    $query = $db->getQuery(true)
						    ->update('#__customers')
						    ->set('customers_group_pricing = ' . $usergroup)
						    ->where('entity_id  = ' . $existinguser->userid);

					    $db->setQuery($query);
					    $db->execute();

					    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
					    break;
				    case 'oscmax':
					    //set the usergroup in the user table
					    $query = $db->getQuery(true)
						    ->update('#__customers')
						    ->set('customers_group_id = ' . $usergroup)
						    ->where('entity_id  = ' . $existinguser->userid);

					    $db->setQuery($query);
					    $db->execute();

					    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;

					    //set the usergroup name  in the user table
					    $query = $db->getQuery(true)
						    ->select('customers_group_name')
						    ->from('#__customers_groups')
						    ->where('customers_group_id = ' . implode (' , ', $existinguser->groups))
					        ->where('language_id = ' . $existinguser->language);

					    $db->setQuery($query);
					    $customers_group_name = $db->loadResult();

					    $query = $db->getQuery(true)
						    ->update('#__customers')
						    ->set('customers_group_iname = ' . $customers_group_name)
						    ->where('entity_id  = ' . $existinguser->userid);

					    $db->setQuery($query);
					    $db->execute();

					    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
					    break;
				    case 'oscxt':
				    case 'oscseo':
					    $query = $db->getQuery(true)
						    ->update('#__customers')
						    ->set('customers_status = ' . $usergroup)
						    ->where('entity_id  = ' . $existinguser->userid);

					    $db->setQuery($query);
				        $db->execute();

				        $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
					    break;
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . $e->getMessage();
	    }
    }
}