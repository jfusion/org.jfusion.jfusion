<?php

/**
 * This is the jfusion user plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage Authentication
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
/**
 * Load the JFusion framework
 */
jimport('joomla.event.plugin');
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';

/**
 * JFusion Authentication class
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage Authentication
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class plgAuthenticationjfusion extends JPlugin
{
	var $name = 'jfusion';
    /**
     * Constructor
     *
     * For php4 compatibility we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
     * This causes problems with cross-referencing necessary for the observer design pattern.
     *
     * @param object &$subject The object to observe
     * @param array  $config   An array that holds the plugin configuration
     *
     * @since 1.5
     * @return void
     */
    function plgAuthenticationjfusion(&$subject, $config)
    {
        parent::__construct($subject, $config);
        //load the language
        $this->loadLanguage('com_jfusion', JPATH_BASE);
    }
    /**
     * This method should handle any authentication and report back to the subject
     *
     * @param array  $credentials Array holding the user credentials
     * @param array  $options     Array of extra options
     * @param object &$response   Authentication response object
     *
     * @access public
     * @return boolean
     * @since 1.5
     */
    function onAuthenticate($credentials, $options, &$response)
    {
        jimport('joomla.user.helper');
        global $JFusionLoginCheckActive;
        $mainframe = JFactory::getApplication();
        // Initialize variables
        $response->debug = array();
        $db = JFactory::getDBO();
        //get the JFusion master
        $master = JFusionFunction::getMaster();
        if (!empty($master)) {
            $JFusionMaster = JFusionFactory::getUser($master->name);
            $userinfo = $JFusionMaster->getUser($credentials['username']);
            //check if a user was found
            if (!empty($userinfo)) {
                //check to see if the login checker wanted a skip password
                $debug = JFusionFunction::isAdministrator();
                if (!empty($options['skip_password_check']) && $debug === true) {
                    $response->debug[] = JText::_('SKIPPED') . ' ' . JText::_('PASSWORD') . ' ' . JText::_('ENCRYPTION') . ' ' . JText::_('CHECK');
                    $response->status = JAUTHENTICATE_STATUS_SUCCESS;
                    $response->email = $userinfo->email;
                    $response->fullname = $userinfo->name;
                    $response->error_message = '';
                    $response->userinfo = $userinfo;
                    $result = true;
                    return $result;
                }
                // Joomla does not like blank passwords
                if (empty($credentials['password'])) {
                    $response->status = JAUTHENTICATE_STATUS_FAILURE;
                    $response->error_message = JText::_('EMPTY_PASSWORD_NO_ALLOWED');
                    $result = false;
                    return $result;
                }
                //store this to be stored jfusion_user table by the joomla_int createUser function
                $userinfo->credentialed_username = $credentials['username'];
                //apply the clear text password to the user object
                $userinfo->password_clear = $credentials['password'];
                //check the master plugin for a valid password
                $model = JFusionFactory::getAuth($master->name);
                $testcrypt = $model->generateEncryptedPassword($userinfo);
                if (isset($options['show_unsensored'])) {
                    $response->debug[] = $master->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('ENCRYPTION') . ' ' . JText::_('CHECK') . ': ' . $testcrypt . ' vs ' . $userinfo->password;
                } else {
                    $response->debug[] = $master->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('ENCRYPTION') . ' ' . JText::_('CHECK') . ': ' .  substr($testcrypt, 0, 6) . '******** vs ' . substr($userinfo->password, 0, 6) . '********';
                }

                if ($testcrypt == $userinfo->password) {
                    //found a match
                    $response->debug[] = $master->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('ENCRYPTION') . ' ' . JText::_('CHECK') . ': ' . JText::_('SUCCESS');
                    $response->status = JAUTHENTICATE_STATUS_SUCCESS;
                    $response->email = $userinfo->email;
                    $response->fullname = $userinfo->name;
                    $response->error_message = '';
                    $response->userinfo = $userinfo;
                    $result = true;
                    return $result;
                }

                //otherwise check the other authentication models
                $query = 'SELECT name FROM #__jfusion WHERE master = 0 AND check_encryption = 1';
                $db->setQuery($query);
                $auth_models = $db->loadObjectList();
                //loop through the different models
                foreach ($auth_models as $auth_model) {
                    //Generate an encrypted password for comparison
                    $model = JFusionFactory::getAuth($auth_model->name);
                    $JFusionSlave = JFusionFactory::getUser($auth_model->name);
                    $slaveuserinfo = $JFusionSlave->getUser($userinfo);
                    // add in the clear password to be able to generate the hash
                    if (!empty($slaveuserinfo)) {
                        $slaveuserinfo->password_clear = $userinfo->password_clear;
                        $testcrypt = $model->generateEncryptedPassword($slaveuserinfo);
                        $check = ($testcrypt == $slaveuserinfo->password);
                    } else {
                        $testcrypt = $model->generateEncryptedPassword($userinfo);
                        $check = ($testcrypt == $userinfo->password);
                    }

                    if (isset($options['show_unsensored'])) {
                        $response->debug[] = $auth_model->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('ENCRYPTION') . ' ' . JText::_('CHECK') . ': ' .  $testcrypt . ' vs ' . $userinfo->password;
                    } else {
                        $response->debug[] = $auth_model->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('ENCRYPTION') . ' ' . JText::_('CHECK') . ': ' .  substr($testcrypt, 0, 6) . '******** vs ' . substr($userinfo->password, 0, 6) . '********';
                    }

                    if ($check) {
                        //found a match
                        $response->debug[] = $auth_model->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('ENCRYPTION') . ' ' . JText::_('CHECK') . ': ' . JText::_('SUCCESS');
                        $response->status = JAUTHENTICATE_STATUS_SUCCESS;
                        $response->email = $userinfo->email;
                        $response->fullname = $userinfo->name;
                        $response->error_message = '';
                        $response->userinfo = $userinfo;
                        //update the password format to what the master expects
                        $status = array('error' => array(),'debug' => array());
                        $JFusionMaster = JFusionFactory::getUser($master->name);
                        //make sure that the password_clear is not already hashed which may be the case for some dual login plugins
                        if (strlen($userinfo->password_clear) != 32) {
                            $JFusionMaster->updatePassword($userinfo, $userinfo, $status);
                            if (!empty($status['error'])) {
                                $response->debug[] = $auth_model->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') . ': ' . $status['error'];
                                JFusionFunction::raiseWarning($master->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('UPDATE'), $status['error'], 1);
                            } else {
                                $response->debug[] = $auth_model->name . ' ' . JText::_('PASSWORD') . ' ' . JText::_('UPDATE') . ' ' . JText::_('SUCCESS');
                            }
                        } else {
                            $status['debug'][] = $auth_model->name . ' ' . JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
                        }
                        $result = true;
                        return $result;
                    }
                }

                if (empty($JFusionLoginCheckActive) && $mainframe->isAdmin()) {
                    //Logging in via Joomla admin but JFusion failed so attempt the normal joomla behaviour
	                $JAuth = JPATH_PLUGINS . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'joomla.php';
	                $method = 'onUserAuthenticate';
                    if (file_exists($JAuth) && $method) {
                        require_once($JAuth);
                        plgAuthenticationJoomla::$method($credentials, $options, $response);
                        $response->debug[] = JText::_('JOOMLA_AUTH_PLUGIN_USED_JFUSION_FAILED');
                    }
                }

                if (isset($response->status) && $response->status != JAUTHENTICATE_STATUS_SUCCESS) {
                    //no matching password found
                    $response->status = JAUTHENTICATE_STATUS_FAILURE;
                    $response->error_message = JText::_('FUSION_INVALID_PASSWORD');
                }
            } else {
                if (empty($JFusionLoginCheckActive) && $mainframe->isAdmin()) {
                    //Logging in via Joomla admin but JFusion failed so attempt the normal joomla behaviour
	                $JAuth = JPATH_PLUGINS . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'joomla.php';
	                $method = 'onUserAuthenticate';
                    if (file_exists($JAuth) && $method) {
                        require_once($JAuth);
                        plgAuthenticationJoomla::$method($credentials, $options, $response);
                        $response->debug[] = JText::_('JOOMLA_AUTH_PLUGIN_USED_JFUSION_FAILED');
                    }
                }

                if (isset($response->status) && $response->status != JAUTHENTICATE_STATUS_SUCCESS) {
                    $response->status = JAUTHENTICATE_STATUS_FAILURE;
                    $response->error_message = JText::_('USER_NOT_EXIST');
                }
            }
        } else {
            //we have to call the main Joomla plugin as we have no master
	        $JAuth = JPATH_PLUGINS . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'joomla.php';
	        $method = 'onUserAuthenticate';
            if (file_exists($JAuth) && $method) {
                require_once($JAuth);
                plgAuthenticationJoomla::$method($credentials, $options, $response);
                $response->debug[] = JText::_('JOOMLA_AUTH_PLUGIN_USED_NO_MASTER');
            }
        }
        return false;
    }

    /**
     * @param $credentials
     * @param $options
     * @param $response
     */
    function onUserAuthenticate($credentials, $options, &$response){
        $this->onAuthenticate($credentials, $options, $response);
    }
}
