<?php namespace JFusion\Plugins\prestashop;


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
use Exception;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;
use Tools;
use Validate;

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
class User extends Plugin_User
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    /**
     * @param Userinfo $userinfo
     *
     * @return null|Userinfo
     */
    function getUser(Userinfo $userinfo) {
	    $user = null;
	    try {
		    //get the identifier

		    list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, null, 'email', 'id_customer');

		    // Get user info from database
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('id_customer as userid, email, email as username, passwd as password, firstname, lastname, active')
			    ->from('#__customer')
			    ->where($identifier_type . ' =' . $db->quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();
		    if ($result) {
			    $result->block = false;
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
				   $result->activation = Framework::getHash(Framework::genRandomPassword());
			   }

			    $user = new Userinfo($this->getJname());
			    $user->bind($result);
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        // read through params for cookie key (the salt used)
        return $user;
    }

    /**
     * @param Userinfo $userinfo
     *
     * @return array
     */
    function deleteUser(Userinfo $userinfo) {
	    /* Warning: this function mimics the original prestashop function which is a suggestive deletion,
		all user information remains in the table for past reference purposes. To delete everything associated
		with an account and an account itself, you will have to manually delete them from the table yourself. */
	    // get the identifier

	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__customer')
		    ->set('deleted = 1')
		    ->where('id_customer = ' . $db->quote($userinfo->userid));

	    $db->setQuery($query);
	    $status[LogLevel::DEBUG][] = Text::_('USER_DELETION') . ': ' . $userinfo->username;
		return $status;
    }


	/**
	 * @param Userinfo $userinfo
	 * @param string $options
	 *
	 * @return array
	 */
	function destroySession(Userinfo $userinfo, $options) {
		$status = array('error' => array(), 'debug' => array());
		$params = Factory::getParams($this->getJname());

		$status = $this->curlLogout($userinfo, $options, $params->get('logout_type'));
		return $status;
	}

	/**
	 * @param Userinfo $userinfo
	 * @param array $options
	 *
	 * @return array
	 */
	function createSession(Userinfo $userinfo, $options) {
		if (!empty($userinfo->block) || !empty($userinfo->activation)) {
			$status['error'][] = Text::_('FUSION_BLOCKED_USER');
		} else {
			$params = Factory::getParams($this->getJname());
			$status = $this->curlLogin($userinfo, $options, $params->get('brute_force'));
		}
		return $status;
	}

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser) {
	    $this->helper->loadFramework();

	    $existinguser->password = Tools::encrypt($userinfo->password_clear);

	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__customer')
		    ->set('passwd = ' . $db->quote($existinguser->password))
		    ->where('id_customer = ' . $db->quote((int)$existinguser->userid));

	    $db->setQuery($query);

	    $db->execute();

	    $this->debugger->addDebug(Text::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
    }

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
    function createUser(Userinfo $userinfo) {
	    $db = Factory::getDatabase($this->getJname());

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

			    $this->debugger->addDebug(Text::_('USER_CREATION'));
			    $this->debugger->set('userinfo', $this->getUser($userinfo));
		    }
	    }
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser) {
	    //we need to update the email
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__customer')
		    ->set('email = ' . $db->quote($userinfo->email))
		    ->where('id_customer = ' . $db->quote((int)$existinguser->userid));

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function activateUser(Userinfo $userinfo, Userinfo &$existinguser) {
	    /* change the 'active' field of the customer in the ps_customer table to 1 */
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__customer')
		    ->set('active = 1')
		    ->where('id_customer = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function inactivateUser(Userinfo $userinfo, Userinfo &$existinguser) {
	    /* change the 'active' field of the customer in the ps_customer table to 0 */
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__customer')
		    ->set('active = 0')
		    ->where('id_customer = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$usergroups = $this->getCorrectUserGroups($userinfo);
		if (empty($usergroups)) {
			throw new RuntimeException(Text::_('USERGROUP_MISSING'));
		} else {
			$db = Factory::getDatabase($this->getJname());
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

			$this->debugger->addDebug(Text::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . implode(' , ', $usergroups));
		}
    }
}