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
 * load the jplugin model
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';

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
        $identifier = $userinfo;
        if (is_object($userinfo)) {
            $identifier = $userinfo->email;
        }
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT customers_id FROM #__customers WHERE customers_email_address = ' . $db->Quote($identifier);
        $db->setQuery($query);
        $userid = $db->loadResult();
        if ($userid) {
            $query1 = $query2 = null;
            switch ($osCversion) {
                case 'osc2':
                    $query1 = 'SELECT ' . 'customers_id          as userid,' . '0                        as group_id,' . 'customers_firstname   as name,' . 'customers_lastname    as lastname,' . 'customers_password    as password,' . 'null                  as password_salt ' . 'FROM #__customers WHERE customers_id = ' . $db->Quote($userid);
                    $query2 = 'SELECT ' . 'customers_info_date_account_created as registerDate,' . 'customers_info_date_of_last_logon   as lastvisitDate, ' . 'customers_info_date_account_last_modified as modifiedDate ' . 'FROM #__customers_info WHERE customers_info_id = ' . $db->Quote($userid);
                break;
                case 'osc3':
                    $query1 = 'SELECT ' . 'customers_id          as userid,' . '0                     as group_id,' . 'customers_firstname   as name,' . 'customers_lastname    as lastname,' . 'customers_password    as password,' . 'null                  as password_salt,' . 'date_account_created  as registerDate, ' . 'date_last_logon       as lastvisitDate,' . 'date_account_last_modified as modifiedDate ' . 'FROM #__customers WHERE customers_id = ' . $db->Quote($userid);
                    $query2 = '';
                break;
                case 'osczen':
                    $query1 = 'SELECT ' . 'customers_id          as userid,' . 'customers_group_pricing as group_id,' . 'customers_firstname   as name,' . 'customers_lastname    as lastname,' . 'customers_password    as password,' . 'null                  as password_salt ' . 'FROM #__customers WHERE customers_id = ' . $db->Quote($userid);
                    $query2 = 'SELECT ' . 'customers_info_date_account_created as registerDate,' . 'customers_info_date_of_last_logon   as lastvisitDate, ' . 'customers_info_date_account_last_modified as modifiedDate ' . 'FROM #__customers_info WHERE customers_info_id = ' . $db->Quote($userid);
                break;
                case 'oscxt':
                case 'oscseo':
                    $query1 = 'SELECT ' . 'customers_id             as userid,' . 'customers_status         as group_id,' . 'customers_firstname     as name,' . 'customers_lastname     as lastname,' . 'customers_password     as password,' . 'null                     as password_salt ' . 'FROM #__customers WHERE customers_id = ' . $db->Quote($userid);
                    $query2 = 'SELECT ' . 'customers_info_date_account_created as registerDate,' . 'customers_info_date_of_last_logon   as lastvisitDate, ' . 'customers_info_date_account_last_modified as modifiedDate ' . 'FROM #__customers_info WHERE customers_info_id = ' . $db->Quote($userid);
                break;
                case 'oscmax':
                    $query1 = 'SELECT ' . 'customers_id             as userid,' . 'customers_group_id        as group_id,' . 'customers_firstname     as name,' . 'customers_lastname     as lastname,' . 'customers_password     as password,' . 'null                     as password_salt ' . 'FROM #__customers WHERE customers_id = ' . $db->Quote($userid);
                    $query2 = 'SELECT ' . 'customers_info_date_account_created as registerDate,' . 'customers_info_date_of_last_logon   as lastvisitDate, ' . 'customers_info_date_account_last_modified as modifiedDate ' . 'FROM #__customers_info WHERE customers_info_id = ' . $db->Quote($userid);
                break;
            }
            if ($query1 && $query2) {
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
        $userinfo->username = $userinfo->email;
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');

        switch ($osCversion) {
            case 'osc3':
                $session_id=$_COOKIE['osCsid'];
                if ($session_id == '') {
                    $status['error'][] = 'Error Could find session cookie make sure COOKIE PATH IS SET TO / in both osC and JFusion plugin settings';
                } else {
                    $db = JFusionFactory::getDatabase($this->getJname());
                    $query = 'DELETE FROM #__sessions WHERE id = \'' . $session_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete session with sessionID '.$session_id.': '.$db->stderr();
                    } else {
                        $status['debug'][] = 'Deleted sessionrecord with id '.$session_id;
                    }
                }
                break;
            default:
                $status = JFusionJplugin::destroySession($userinfo, $options, $this->getJname(),$params->get('logout_type'));
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array|string
     */
    function createSession($userinfo, $options) {
        $params = JFusionFactory::getParams($this->getJname());
        // need to make the username equal the email
        $userinfo->username = $userinfo->email;
        return JFusionJplugin::createSession($userinfo, $options, $this->getJname(),$params->get('brute_force'));
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
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
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
                $query1 = 'UPDATE #__customers ' . 'SET customers_password =' . $db->quote($existinguser->password) . 'WHERE customers_id = ' . $db->Quote($existinguser->userid);
                $query2 = 'UPDATE #__customers_info ' . 'SET customers_info_date_account_last_modified =' . $db->Quote($modified_date) . ' WHERE customers_info_id =' . $db->Quote($existinguser->userid);
            break;
            case 'osc3':
                $query1 = 'UPDATE #__customers ' . ' SET customers_password =' . $db->quote($existinguser->password) . ',date_account_last_modified=' . $db->Quote($modified_date) . ' WHERE customers_id = ' . $db->Quote($existinguser->userid);
                $query2 = '';
            break;
        }
        if ($query1) {
            $db->BeginTrans();
            $db->setQuery($query1);
            if (!$db->query()) {
                $db->RollbackTrans();
                $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
                return;
            } else {
                if ($query2) {
                    $db->setQuery($query2);
                    if (!$db->query()) {
                        $db->RollbackTrans();
                        $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
                        return;
                    }
                }
            }
            $db->CommitTrans();
            $status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
        } else {
            $status['error'][] = JText::_('PASSWORD_UPDATE_ERROR');
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
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
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
                $query1 = 'UPDATE #__customers ' . 'SET customers_email_address =' . $db->quote($existinguser->email) . 'WHERE customers_id = ' . $db->Quote($existinguser->userid);
                $query2 = 'UPDATE #__customers_info ' . 'SET customers_info_date_account_last_modified =' . $db->Quote($modified_date) . ' WHERE customers_info_id =' . $db->Quote($existinguser->userid);
            break;
            case 'osc3':
                $query1 = 'UPDATE #__customers ' . ' SET customers_email_address =' . $db->quote($existinguser->email) . ',date_account_last_modified=' . $db->Quote($modified_date) . ' WHERE customers_id = ' . $db->Quote($existinguser->userid);
                $query2 = '';
            break;
        }
        if ($query1) {
            $db->BeginTrans();
            $db->setQuery($query1);
            if (!$db->query()) {
                $db->RollbackTrans();
                $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
                return;
            } else {
                if ($query2) {
                    $db->setQuery($query2);
                    if (!$db->query()) {
                        $db->RollbackTrans();
                        $status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
                        return;
                    }
                }
            }
        } else {
            $status['error'][] = JText::_('EMAIL_UPDATE_ERROR');
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
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
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
                $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
                if (empty($usergroups)) {
                    $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING');
                    return;
                }
                $usergroup = $usergroups[0];
                $user->customers_group_pricing = $usergroup;
                //        $user->customers_paypal_ec = '0';   // must be an unique number?????.
                
            break;
            case 'oscxt':
            case 'oscseo':
            $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
            if (empty($usergroups)) {
                    $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING');
                    return;
                }
                $usergroup = $usergroups[0];
                $user->customers_status = $usergroup;
                //        $user->customers_paypal_ec = '0';   // must be an unique number?????.
                
            break;
            case 'oscmax':
                $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
                if (empty($usergroups)) {
                    $status['error'][] = JText::_('ERROR_CREATE_USER') . ' ' . JText::_('USERGROUP_MISSING');
                    return;
                }
                $usergroup = $usergroups[0];
                $user->customers_group_id = $usergroup;
                // get the groupname
                $db1 = JFusionFactory::getDatabase($this->getJname());
                $query = 'SELECT customers_group_name from #__customers_groups WHERE customers_group_id = ' . $usergroup . ' AND language_id = ' . $userinfo->language;
                $db1->setQuery($query);
                $user->customers_group_name = $db1->loadResult();
            break;
        }
        //now append the new user data
        $db->BeginTrans();
        $ok = $db->insertObject('#__customers', $user, 'customers_id');
        if ($ok) {
            $userid = $db->insertid();
            // make a default address/ This is mandatory, but ala, we don't have much info!
            $user_1 = new stdClass;
            $user_1->customers_id = $userid;
            $user_1->entry_gender = $user->customers_gender;
            $user_1->entry_firstname = $user->customers_firstname;
            $user_1->entry_lastname = $user->customers_lastname;
            $params = JFusionFactory::getParams($this->getJname());
            $default_country = $params->get('default_country');
            $user_1->entry_country_id = $default_country;
            $ok = $db->insertObject('#__address_book', $user_1, 'address_book_id');
            if ($ok) {
                $infoid = $db->insertid();
                $query = 'UPDATE #__customers set customers_default_address_id = \'' . (int)$infoid . '\' where customers_id = \'' . (int)$userid . '\'';
                $db->setquery($query);
                $ok = $db->query();
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
                        $db->CommitTrans();
                        $status['debug'][] = JText::_('USER_CREATION');
                        $status['userinfo'] = $this->getUser($userinfo);
                        return;
                    }
                }
            }
        }
        $status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
        $db->RollbackTrans();
    }

    /**
     * @param object $userinfo
     * @return array|bool
     */
    function deleteUser($userinfo) {
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
        $db = JFusionFactory::getDatabase($this->getJname());
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());
        //set the userid
        //check to see if a valid $userinfo object was passed on
        if (!is_object($userinfo)) {
            $status['error'][] = JText::_('NO_USER_DATA_FOUND');
            return $status;
        }
        $existinguser = $this->getUser($userinfo);
        if (!empty($existinguser)) {
            $user_id = $existinguser->userid;
            // Delete userrecordosc2 & osc3 & osczen & oscxt &oscmax
            $query = 'DELETE FROM #__customers WHERE customers_id = \'' . $user_id . '\'';
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = 'Error Could not delete userrecord with userid '.$user_id.': '.$db->stderr();
                return $status;
            } else {
                $status['debug'][] = 'Deleted userrecord of user with id '.$user_id;
            }
            // delete adressbook items osc2 & osc3 & osczen & oscxt & oscmax
            $query = 'DELETE FROM #__address_book WHERE customers_id = \'' . $user_id . '\'';
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = 'Error Could not delete addressbookitems with userid '.$user_id.': '.$db->stderr();
                return $status;
            } else {
                $status['debug'][] = 'Deleted addressbook items of user with id '.$user_id;
            }
            // delete customer from who's on line osc2 & osc3 & osczen & oscxt & oscmax
            $query = 'DELETE FROM #__whos_online WHERE customer_id = \'' . $user_id . '\'';
            $db->setQuery($query);
            if (!$db->query()) {
                $status['error'][] = 'Error Could not delete customer on line with userid '.$user_id.': '.$db->stderr();
                return $status;
            } else {
                $status['debug'][] = 'Deleted customer online entry of user with id '.$user_id;
            }
            // delete review items osc2 & osc3 &  osczen & oscxt
            $delete_reviews = $params->get('delete_reviews');
            if ($delete_reviews == '1') {
                $db->query('select reviews_id from #__reviews where customers_id = \'' . (int)$user_id . '\'');
                $reviews = $db->loadObjectList();
                foreach ($reviews as $review) {
                    $db->query('delete from #__reviews_description where reviews_id = \'' . (int)$review->reviews_id . '\'');
                }
                $db->query('DELETE FROM #__reviews WHERE customers_id = \'' . (int)$user_id . '\'');
            } else {
                $db->query('UPDATE #__reviews set customers_id = null where customers_id = \'' . (int)$user_id . '\'');
            }
            if (!$db->query()) {
                $status['error'][] = 'Error Could not delete customer reviews with userid '.$user_id.': '.$db->stderr();
                return $status;
            } else {
                $status['debug'][] = 'Deleted customer rieviews of user with id '.$user_id;
            }
            switch ($osCversion) {
                case 'oscxt':
                case 'oscseo':
                    $query = 'DELETE FROM #__products_notifications WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete product notifications with userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted products notifications of user with id '.$user_id;
                    }
                    $query = 'DELETE FROM #__customers_customers_status_history WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete customer history with userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted customer history of user with id '.$user_id;
                    }
                    $query = 'DELETE FROM #__customers_ip WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete customer ip with userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted customer ip of user with id '.$user_id;
                    }
                    $query = 'DELETE FROM #__admin_access WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete admin accessid '.$user_id.': '.$db->stderr();
                    } else {
                        $status['debug'][] = 'Deleted admin accessith id '.$user_id;
                    }
                    return $status;
                case 'osc2':
                case 'osczen':
                case 'oscmax':
                    // Delete user info osc2 & osczen & oscxt
                    $query = 'DELETE FROM #__customers_info WHERE customers_info_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete useinfo with userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted userinfo of user with id '.$user_id;
                    }
                    // delete  customer basket osc2 & osczen
                    $query = 'DELETE FROM #__customers_basket WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete customer basket with userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted customer basket items of user with id '.$user_id;
                    }
                    // delete  customer basket attributes osc2 & osczen
                    $query = 'DELETE FROM #__customers_basket_attributes WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete customer basket attributes with userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted customer basket attributes items of user with id '.$user_id;
                    }
                break;
                case 'osc3':
                    $query = 'DELETE FROM #__shopping_carts WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete customer shopping cart with userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted customer shopping cart of user with id '.$user_id;
                    }
                    $query = 'DELETE FROM #__shopping_carts_custom_variants_values WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete customer shopping cart variantswith userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted customer shopping cart variants of user with id '.$user_id;
                    }
                    $query = 'DELETE FROM #__product_notifications WHERE customers_id = \'' . $user_id . '\'';
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = 'Error Could not delete customer product notifications with userid '.$user_id.': '.$db->stderr();
                        return $status;
                    } else {
                        $status['debug'][] = 'Deleted customer product notifications of user with id '.$user_id;
                    }
                break;
            }
            return $status;
        }
        return false;
    }

    /**
     * @param object $userinfo
     * @param object &$existinguser
     * @param array &$status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status) {
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
        $usergroups = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
        if (empty($usergroups)) {
            $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ': ' . JText::_('USERGROUP_MISSING');
        } else {
            $usergroup = $usergroups[0];
            $db = JFusionFactory::getDataBase($this->getJname());
            switch ($osCversion) {
                case 'osczen':
                    //set the usergroup in the user table
                    $query = 'UPDATE #__customers SET customers_group_pricing = ' . $usergroup . ' WHERE entity_id =' . $existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                    } else {
                        $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
                    }
                    break;
                case 'oscmax':
                    //set the usergroup in the user table
                    $query = 'UPDATE #__customers SET customers_group_id = ' . $usergroup . ' WHERE entity_id =' . $existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                    } else {
                        $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
                    }

                    //set the usergroup name  in the user table
                    $db1 = JFusionFactory::getDatabase($this->getJname());
                    $query = 'SELECT customers_group_name from #__customers_groups WHERE customers_group_id = ' . implode (' , ', $existinguser->groups) . ' AND language_id = ' . $existinguser->language;
                    $db1->setQuery($query);
                    $customers_group_name = $db1->loadResult();
                    $query = 'UPDATE #__customers SET customers_group_iname = ' . $customers_group_name . ' WHERE entity_id =' . $existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                    } else {
                        $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
                    }
                    break;
                case 'oscxt':
                case 'oscseo':
                    $query = 'UPDATE #__customers SET customers_status = ' . $usergroup . ' WHERE entity_id =' . $existinguser->userid;
                    $db->setQuery($query);
                    if (!$db->query()) {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
                    } else {
                        $status['debug'][] = JText::_('GROUP_UPDATE') . ': ' . implode (' , ', $existinguser->groups) . ' -> ' . $usergroup;
                    }
                    break;
            }
        }
    }
}