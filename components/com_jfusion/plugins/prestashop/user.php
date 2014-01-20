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
class JFusionUser_prestashop extends JFusionUser
{
	/**
	 * @var $helper JFusionHelper_prestashop
	 */
	var $helper;

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

		    $query = $db->getQuery(true)
			    ->select('id_customer as userid, email, email as username, passwd as password, firstname, lastname, active')
			    ->from('#__customer')
			    ->where('email =' . $db->quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();
		    if ($result) {
			    $result->block = 0;
			    $query = $db->getQuery(true)
				    ->select('id_group')
				    ->from('#__customer_group')
				    ->where('id_customer =' . $db->quote($result->userid));

			    $db->setQuery($query);
			    $groups = $db->loadObjectList();

			    if ($groups) {
				    foreach($groups as $group) {
					    $result->groups[] = $result->group_id = $group->id_group;

					    $result->groupnames[] = $result->group_name = $this->helper->getGroupName($result->group_id);
				    }
			    }

			   if ($result->active) {
				   $result->activation = '';
			   } else {
				   jimport('joomla.user.helper');
				   $result->activation = JFusionFunction::getHash(JUserHelper::genRandomPassword());
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
			    ->where('id_customer = ' . $db->quote($identifier));

		    $db->setQuery($query);
		    $status['debug'][] = 'Deleted user';
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
		return $status;
    }


	/**
	 * @param object $userinfo
	 * @param string $options
	 *
	 * @return array
	 */
	function destroySession($userinfo, $options) {
		$status = array('error' => array(), 'debug' => array());
		$params = JFusionFactory::getParams($this->getJname());

		$status = $this->curlLogout($userinfo, $options, $params->get('logout_type'));
		return $status;
	}

	/**
	 * @param object $userinfo
	 * @param array $options
	 *
	 * @return array
	 */
	function createSession($userinfo, $options) {
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
			$status['error'][] = JText::_('FUSION_BLOCKED_USER');
		} else {
			$params = JFusionFactory::getParams($this->getJname());
			$status = $this->curlLogin($userinfo, $options, $params->get('brute_force'));
		}
		return $status;
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
	        $this->helper->loadFramework();

	        $existinguser->password = Tools::encrypt($userinfo->password_clear);

	        $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__customer')
			    ->set('passwd = ' . $db->quote($existinguser->password))
			    ->where('id_customer = ' . $db->quote((int)$existinguser->userid));

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

		    $usergroups = $this->getCorrectUserGroups($userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException('USERGROUP_MISSING');
		    } else {
			    $this->helper->loadFramework();

			    $source_path = $this->params->get('source_path');

			    /* split full name into first and with/or without middlename, and lastname */
			    $usernames = explode(' ', $userinfo->name);

			    $firstname = $usernames[0];
			    $lastname = '';
			    if (count($usernames)) {
				    $lastname = $usernames[count($usernames)-1];
			    }

			    if (isset($userinfo->password_clear)) {
				    $password = Tools::encrypt($userinfo->password_clear);
			    } else {
				    $password = $userinfo->password;
			    }

			    if (!Validate::isName($firstname)) {
				    throw new RuntimeException(Tools::displayError('first name wrong'));
			    } elseif (!Validate::isName($lastname)) {
				    throw new RuntimeException(Tools::displayError('second name wrong'));
			    } elseif (!Validate::isEmail($userinfo->email)) {
				    throw new RuntimeException(Tools::displayError('e-mail not valid'));
			    } elseif (!Validate::isPasswd($password)) {
				    throw new RuntimeException(Tools::displayError('invalid password'));
			    } else {
				    $now = date('Y-m-d h:m:s');
				    $ps_customer = new stdClass;
				    $ps_customer->id_customer = null;
				    $ps_customer->id_gender = 1;
				    $ps_customer->id_default_group = $usergroups[0];
				    $ps_customer->secure_key = md5(uniqid(rand(), true));
				    $ps_customer->email = $userinfo->email;
				    $ps_customer->passwd = $password;
				    $ps_customer->last_passwd_gen = date('Y-m-d h:m:s', strtotime('-6 hours'));
				    $ps_customer->birthday = date('Y-m-d', mktime(0, 0, 0, '01', '01', '2000'));
				    $ps_customer->lastname = $lastname;
				    $ps_customer->newsletter = 0;
				    $ps_customer->ip_registration_newsletter = $_SERVER['REMOTE_ADDR'];
				    $ps_customer->optin = 0;
				    $ps_customer->firstname = $firstname;
				    $ps_customer->active = 1;
				    $ps_customer->deleted = 0;
				    $ps_customer->date_add = $now;
				    $ps_customer->date_upd = $now;

				    /* enter customer account into prestashop database */ // if all information is validated
				    $db->insertObject('#__customer', $ps_customer, 'id_customer');

				    // enter customer group into database
				    $ps_address = new stdClass;
				    $ps_address->id_customer = $ps_customer->id_customer;
				    $ps_address->id_address = null;
				    $ps_address->id_country = 17;
				    $ps_address->id_state = 0;
				    $ps_address->id_manufacturer = 0;
				    $ps_address->id_supplier = 0;
				    $ps_address->alias = 'My address';
				    $ps_address->company = '';
				    $ps_address->lastname = $lastname;
				    $ps_address->firstname = $firstname;
				    $ps_address->address1 = 'Update with your real address';
				    $ps_address->address2 = '';
				    $ps_address->postcode = 'Postcode';
				    $ps_address->city = 'Not known';
				    $ps_address->other = '';
				    $ps_address->phone = '';
				    $ps_address->phone_mobile = '';
				    $ps_address->date_add = $now;
				    $ps_address->date_upd = $now;
				    $ps_address->active = 1;
				    $ps_address->deleted = 0;

				    $usergroups = $this->getCorrectUserGroups($userinfo);

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
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USER_CREATION_ERROR') . ' ' . $e->getMessage();
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
		    $db = JFusionFactory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__customer')
			    ->set('email = ' . $db->quote($userinfo->email))
			    ->where('id_customer = ' . $db->quote((int)$existinguser->userid));

		    $db->setQuery($query);
		    $db->execute();

		    $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
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
		    $usergroups = $this->getCorrectUserGroups($userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
		    } else {
			    $db = JFusionFactory::getDatabase($this->getJname());
			    // now delete the user
			    $query = $db->getQuery(true)
				    ->delete('#__customer_group')
				    ->where('id_customer = ' .  $existinguser->userid);

			    $db->setQuery($query);
			    $db->execute();


			    $query = $db->getQuery(true)
				    ->update('#__customer')
				    ->set('id_default_group = ' . $db->quote($usergroups[0]))
				    ->where('id_customer = ' . (int)$existinguser->userid);

			    $db->setQuery($query);
			    $db->execute();

			    foreach($usergroups as $value) {
				    $group = new stdClass;
				    $group->id_customer = $existinguser->userid;
				    $group->id_group = $value;
				    $db->insertObject('#__customer_group', $group);
			    }
			    $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . implode(' , ', $usergroups);
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $e->getMessage();
	    }
    }
}