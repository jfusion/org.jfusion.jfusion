<?php namespace JFusion\Plugins\wordpress;

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
use Exception;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;
use RuntimeException;
use stdClass;

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
class User extends Plugin_User
{
    /**
     * @param Userinfo $userinfo
     *
     * @return null|Userinfo
     */
    function getUser(Userinfo $userinfo)
    {
	    $user = null;
        try {
	        list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, null, 'customers_email_address', 'customers_id');

            $osCversion = $this->params->get('osCversion');
            $db = Factory::getDatabase($this->getJname());

            $query = $db->getQuery(true)
                ->select('customers_id')
                ->from('#__customers')
                ->where($identifier_type . ' = ' . $db->Quote($identifier));

            $db->setQuery($query);
            $userid = $db->loadResult();
            if ($userid) {
                $query1 = $query2 = null;
                $query1 = $db->getQuery(true)
                    ->select('customers_id as userid, customers_group_pricing as group_id, customers_firstname as name, customers_lastname as lastname, customers_password as password, null as password_salt')
                    ->from('#__customers')
                    ->where('userid = ' . $db->Quote($userid));

                $query2 = $db->getQuery(true)
                    ->select('customers_info_date_account_created as registerDate, customers_info_date_of_last_logon as lastvisitDate, customers_info_date_account_last_modified as modifiedDate')
                    ->from('#__customers_info')
                    ->where('customers_info_id = ' . $db->Quote($userid));

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

                    $result->activation = null;
                    $result->block = false;
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
	                $user = new Userinfo($this->getJname());
	                $user->bind($result);
                }
            }
        } catch (Exception $e) {
            Framework::raiseError($e, $this->getJname());
        }
	    return $user;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $options)
    {
        $status = $this->curlLogout($userinfo, $options, $this->params->get('logout_type'));
        return $status;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array|string
     */
    function createSession(Userinfo $userinfo, $options)
    {
        // need to make the username equal the email
        $userinfo->username = $userinfo->email;
        return $this->curlLogin($userinfo, $options, $this->params->get('brute_force'));
    }

    /**
     * @param string $username
     *
     * @return string
     */
    function filterUsername($username)
    {
        //no username filtering implemented yet
        return $username;
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo &$existinguser
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $existinguser->password = '';
	    for ($i = 0; $i < 10; $i++) {
		    $existinguser->password .= mt_rand((double)microtime() * 1000000);
	    }
	    $salt = substr(md5($existinguser->password), 0, 2);
	    $existinguser->password = md5($salt . $userinfo->password_clear) . ':' . $salt;
	    $db = Factory::getDatabase($this->getJname());
	    $modified_date = date('Y-m-d H:i:s', time());
	    $query1 = $query2 = null;
	    $query1 = (string)$db->getQuery(true)
		    ->update('#__customers')
		    ->set('customers_password = ' . $db->quote($existinguser->password))
		    ->where('customers_id  = ' . $db->Quote($existinguser->userid));

	    $query2 = (string)$db->getQuery(true)
		    ->update('#__customers_info')
		    ->set('customers_info_date_account_last_modified = ' . $db->quote($modified_date))
		    ->where('customers_info_id  = ' . $db->quote($existinguser->userid));

	    $db->transactionStart();
	    $db->setQuery($query1);
	    $db->execute();

	    $db->setQuery($query2);
	    $db->execute();

	    $db->transactionCommit();

	    $this->debugger->add('debug', Text::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updateUsername(Userinfo $userinfo, Userinfo &$existinguser)
    {
        // no username in oscommerce
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo &$existinguser
	 *
	 * @throws \Exception
	 * @return void
	 */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser)
    {
        try {
            $osCversion = $this->params->get('osCversion');
            //we need to update the email
            $db = Factory::getDatabase($this->getJname());
            $modified_date = date('Y-m-d H:i:s', time());
            $query1 = $query2 = null;
            $query1 = (string)$db->getQuery(true)
                ->update('#__customers')
                ->set('customers_email_address = ' . $db->quote($existinguser->email))
                ->where('customers_id  = ' . $db->quote($existinguser->userid));
            $query2 = (string)$db->getQuery(true)
                ->update('#__customers_info')
                ->set('customers_info_date_account_last_modified = ' . $db->quote($modified_date))
                ->where('customers_info_id  = ' . $db->quote($existinguser->userid));
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
	        throw $e;
        }
    }

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \Exception
	 * @return void
	 */
    function createUser(Userinfo $userinfo)
    {
        try {
            $db = Factory::getDatabase($this->getJname());
            //prepare the variables
            $user = new stdClass;
            $user->customers_id = null;
            $user->customers_gender = 'm'; // ouch, empty is female, so this is an arbitrarily choice
            $parts = explode(' ', $userinfo->name);
            $user->customers_firstname = $parts[0];
            $lastname = '';
            if ($parts[(count($parts) - 1)]) {
                for ($i = 1; $i < (count($parts)); $i++) {
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
                $user->customers_password = '';
                for ($i = 0; $i < 10; $i++) {
                    $user->customers_password .= mt_rand((double)microtime() * 1000000);
                }
                $salt = substr(md5($user->customers_password), 0, 2);
                $user->customers_password = md5($salt . $userinfo->password_clear) . ':' . $salt;
            } else {
                if (!empty($userinfo->password_salt)) {
                    $user->customers_password = $userinfo->password . ':' . $userinfo->password_salt;
                } else {
                    $user->customers_password = $userinfo->password;
                }
            }
            $usergroups = $this->getCorrectUserGroups($userinfo);
            //    $user->customers_newsletter = null;
            if (empty($usergroups)) {
                throw new RuntimeException(Text::_('USERGROUP_MISSING'));
            }
            $user->customers_group_pricing = $usergroups[0];
            //        $user->customers_paypal_ec = '0';   // must be an unique number?????.

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
                        $user_1 = new stdClass;
                        $user_1->customers_info_id = $userid;
                        $user_1->customers_info_date_of_last_logon = null;
                        $user_1->customers_info_number_of_logons = null;
                        $user_1->customers_info_date_account_created = date('Y-m-d H:i:s', time());
                        $user_1->customers_info_date_account_last_modified = null;
                        $user_1->global_product_notifications = 0;
                        $ok = $db->insertObject('#__customers_info', $user_1, 'customers_info_id');
                        if ($ok) {
                            $db->transactionCommit();

	                        $this->debugger->add('debug', Text::_('USER_CREATION'));
	                        $this->debugger->set('userinfo', $this->getUser($userinfo));
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if (isset($db)) {
                $db->transactionRollback();
            }
	        throw $e;
        }
    }

    /**
     * @param Userinfo $userinfo
     *
     * @return array|bool
     */
    function deleteUser(Userinfo $userinfo)
    {
        $status = array('error' => array(), 'debug' => array());
        try {
            $db = Factory::getDatabase($this->getJname());
            //setup status array to hold debug info and errors

            //set the userid
            //check to see if a valid $userinfo object was passed on
            if (!is_object($userinfo)) {
                throw new RuntimeException(Text::_('NO_USER_DATA_FOUND'));
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
                $errors[] = 'Error Could not delete userrecord with userid ' . $user_id;
                $debug[] = 'Deleted userrecord of user with id ' . $user_id;

                // delete adressbook items osc2 & osc3 & osczen & oscxt & oscmax
                $querys[] = $db->getQuery(true)
                    ->delete('#__address_book')
                    ->where('customers_id = ' . $db->quote($user_id));
                $errors[] = 'Error Could not delete addressbookitems with userid ' . $user_id;
                $debug[] = 'Deleted addressbook items of user with id ' . $user_id;

                // delete customer from who's on line osc2 & osc3 & osczen & oscxt & oscmax
                $querys[] = $db->getQuery(true)
                    ->delete('#__whos_online')
                    ->where('customers_id = ' . $db->quote($user_id));
                $errors[] = 'Error Could not delete customer on line with userid ' . $user_id;
                $debug[] = 'Deleted customer online entry of user with id ' . $user_id;

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
                    $errors[] = 'Error Could not delete customer reviews with userid ' . $user_id;
                    $debug[] = 'Deleted customer rieviews of user with id ' . $user_id;
                } else {
                    $querys[] = (string)$db->getQuery(true)
                        ->update('#__reviews')
                        ->set('customers_id = null')
                        ->where('customers_id  = ' . $db->Quote((int)$user_id));

                    $errors[] = 'Error Could not delete customer reviews with userid ' . $user_id;
                    $debug[] = 'Deleted customer rieviews of user with id ' . $user_id;
                }

                // Delete user info osc2 & osczen & oscxt
                $querys[] = $db->getQuery(true)
                    ->delete('#__customers_info')
                    ->where('customers_info_id = ' . $db->quote($user_id));
                $errors[] = 'Error Could not delete useinfo with userid ' . $user_id;
                $debug[] = 'Deleted userinfo of user with id ' . $user_id;

                // delete  customer basket osc2 & osczen
                $querys[] = $db->getQuery(true)
                    ->delete('#__customers_basket')
                    ->where('customers_id = ' . $db->quote($user_id));
                $errors[] = 'Error Could not delete customer basket with userid ' . $user_id;
                $debug[] = 'Deleted customer basket items of user with id ' . $user_id;

                // delete  customer basket attributes osc2 & osczen
                $querys[] = $db->getQuery(true)
                    ->delete('#__customers_basket_attributes')
                    ->where('customers_id = ' . $db->quote($user_id));
                $errors[] = 'Error Could not delete customer basket attributes with userid ' . $user_id;
                $debug[] = 'Deleted customer basket attributes items of user with id ' . $user_id;

                foreach ($querys as $key => $value) {
                    try {
                        $db->setQuery($value);
                        $db->execute();
                        $status['debug'][] = $debug[$key];
                    } catch (Exception $e) {
                        $status['error'][] = $errors[$key] . ': ' . $e->getMessage();
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
	 * @param Userinfo $userinfo
	 * @param Userinfo &$existinguser
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
		    $usergroup = $usergroups[0];
		    $db = Factory::getDataBase($this->getJname());
		    //set the usergroup in the user table
		    $query = $db->getQuery(true)
			    ->update('#__customers')
			    ->set('customers_group_pricing = ' . $usergroup)
			    ->where('entity_id  = ' . $existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $this->debugger->add('debug', Text::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . $usergroup);
	    }
    }
}