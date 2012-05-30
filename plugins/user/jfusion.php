<?php

 /**
 * This is the jfusion user plugin file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage User
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
jimport('joomla.plugin.plugin');
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
/**
 * JFusion User class
 *
 * @category   JFusion
 * @package    Plugins
 * @subpackage User
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class plgUserJfusion extends JPlugin
{
    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
     * This causes problems with cross-referencing necessary for the observer design pattern.
     *
     * @param object &$subject The object to observe
     * @param array  $config   An array that holds the plugin configuration
     *
     * @since 1.5
     * @return void
     */
    function plgUserJfusion(&$subject, $config)
    {
        parent::__construct($subject, $config);
        //load the language
        $this->loadLanguage('com_jfusion', JPATH_BASE);
    }

    /**
     * Remove all sessions for the user name
     *
     * Method is called after user data is deleted from the database
     *
     * @param array   $user   holds the user data
     * @param boolean $succes true if user was succesfully stored in the database
     * @param string  $msg    message
     *
     * @return boolean False on Falior
     */
    function onAfterDeleteUser($user, $succes, $msg)
    {
        if (!$succes) {
            $result = false;
            return $result;
        }
        //create an array to store the debug info
        $debug_info = array();
        //convert the user array into a user object
        $userinfo = (object)$user;
        //delete the master user if it is not Joomla
        $master = JFusionFunction::getMaster();
        if ($master->name != 'joomla_int') {
            $params = & JFusionFactory::getParams($master->name);
            $deleteEnabled = $params->get('allow_delete_users', 0);
            $JFusionMaster = & JFusionFactory::getUser($master->name);
            $MasterUser = $JFusionMaster->getUser($userinfo);
            if (!empty($MasterUser) && $deleteEnabled) {
                $status = $JFusionMaster->deleteUser($MasterUser);
                if (!empty($status['error'])) {
                    $debug_info[$master->name . ' ' . JText::_('ERROR') ] = $status['error'];
                }
                $debug_info[$master->name] = $status['debug'];
            } elseif ($deleteEnabled) {
                $debug_info[$master->name] = JText::_('NO_USER_DATA_FOUND');
            } else {
                $debug_info[$master->name] = JText::_('DELETE_DISABLED');
            }
        }
        //delete the user in the slave plugins
        $slaves = JFusionFunction::getPlugins();
        foreach ($slaves as $slave) {
            $params = & JFusionFactory::getParams($slave->name);
            $deleteEnabled = $params->get('allow_delete_users', 0);
            $JFusionSlave = & JFusionFactory::getUser($slave->name);
            $SlaveUser = $JFusionSlave->getUser($userinfo);
            if (!empty($SlaveUser) && $deleteEnabled) {
                $status = $JFusionSlave->deleteUser($SlaveUser);
                if (!empty($status['error'])) {
                    $debug_info[$slave->name . ' ' . JText::_('ERROR') ] = $status['error'];
                }
                $debug_info[$slave->name] = $status['debug'];
            } elseif ($deleteEnabled) {
                $debug_info[$slave->name] = JText::_('NO_USER_DATA_FOUND');
            } else {
                $debug_info[$slave->name] = JText::_('DELETE') . ' ' . JText::_('DISABLED');
            }
        }
        //remove userlookup data
        JFusionFunction::removeUser($userinfo);
        //delete any sessions that the user could have active
        $db = JFactory::getDBO();
        $db->setQuery('DELETE FROM #__session WHERE userid = ' . $db->Quote($user['id']));
        $db->Query();
        //return output if allowed
        $isAdministrator = JFusionFunction::isAdministrator();
        if ($isAdministrator === true) {
            JFusionFunction::raiseWarning('', $debug_info, 1);
        }
        $result = true;
        return $result;
    }
    /**
     * This method should handle any login logic and report back to the subject
     *
     * @param object $user     holds the user data
     * @param array &$options holding options (remember, autoregister, group)
     *
     * @return boolean True on success
     * @since 1.5
     * @access public
     */
    function onLoginUser($user, $options)
    {

        //prevent any output by the plugins (this could prevent cookies from being passed to the header)
        ob_start();

        //prevent a login if AEC denied a user
        if (defined('AEC_AUTH_ERROR_UNAME')) {
            $success = false;
            ob_end_clean();
            return $success;
        }

        jimport('joomla.user.helper');
        global $JFusionActive, $JFusionLoginCheckActive;
        $mainframe = JFactory::getApplication();
        $JFusionActive = true;

        //php 5.3 does not allow plugins to contain pass by references
        //use a global for the login checker instead
        global $jfusionDebug;
        $jfusionDebug = array();
        $jfusionDebug['init'] = array();
        //determine if overwrites are allowed
        $isAdministrator = JFusionFunction::isAdministrator();
        if (!empty($options['overwrite']) && $isAdministrator === true) {
            $overwrite = 1;
        } else {
            $overwrite = 0;
        }
        //allow for the detection of external mods to exclude jfusion plugins
        global $JFusionActivePlugin;
        jimport('joomla.environment.request');
        $jnodeid = strtolower(JRequest::getVar('jnodeid'));
        if (!empty($jnodeid)){
            $JFusionActivePlugin = $jnodeid;
        }
        //get the JFusion master
        $master = JFusionFunction::getMaster();
        //if we are in the admin and no master is selected, make joomla_int master to prevent lockouts
        if (empty($master) && $mainframe->isAdmin()) {
            $master = new stdClass();
            $master->name = 'joomla_int';
            $master->joomlaAuth = true;
        }
        //setup JFusionUser object for Joomla
        $JFusionJoomla = & JFusionFactory::getUser('joomla_int');
        if (!empty($master)) {
            $JFusionMaster = & JFusionFactory::getUser($master->name);
            //check to see if userinfo is already present
            if (!empty($user['userinfo'])) {
                //the jfusion auth plugin is enabled
                $jfusionDebug['init'][] = JText::_('USING_JFUSION_AUTH');
                $userinfo = $user['userinfo'];
            } else {
                //other auth plugin enabled get the userinfo again
                //temp userinfo to see if the user exists in the master
                $auth_userinfo = new stdClass();
                $auth_userinfo->username = $user['username'];
                $auth_userinfo->email = $user['email'];
                $auth_userinfo->password_clear = $user['password'];
                $auth_userinfo->name = $user['fullname'];
                //get the userinfo for real
                $userinfo = $JFusionMaster->getUser($auth_userinfo);
                if (isset($master->joomlaAuth)) {
                    $jfusionDebug['init'][] = JText::_('USING_JOOMLA_AUTH');
                } else {
                    $jfusionDebug['init'][] = JText::_('USING_OTHER_AUTH');
                }
                if (empty($userinfo)) {
                    //are we in Joomla's backend?  Let's check internal Joomla for the user if joomla_int isn't already the master to prevent lockouts
                    if ($master->name != 'joomla_int' && $mainframe->isAdmin()) {
                         $JFusionJoomla = & JFusionFactory::getUser('joomla_int');
                         $JoomlaUserinfo = $JFusionJoomla->getUser($auth_userinfo);
                         if (!empty($JoomlaUserinfo)) {
                             //user found in Joomla, let them pass just to be able to login to the backend
                             $userinfo = & $JoomlaUserinfo;
                         } else {
                             //user not found in Joomla, return an error
                            $jfusionDebug['init'][] = JText::_('COULD_NOT_FIND_USER');
                            ob_end_clean();
                            $success = false;
                            return $success;
                         }
                    } else {
                        //should be auto-create users?
                        $params = & JFusionFactory::getParams('joomla_int');
                        $autoregister = $params->get('autoregister', 0);
                        if ($autoregister == 1) {
                            $jfusionDebug['init'][] = JText::_('CREATING_MASTER_USER');
                            $status = array();
                            $status['debug'] = array();
                            $status['error'] = array();
                            //try to create a Master user
                            $JFusionMaster->createUser($auth_userinfo, $status);
                            if (empty($status['error'])) {
                                //success
                                //make sure the userinfo is available
                                if (!empty($status['userinfo'])) {
                                    $userinfo = $status['userinfo'];
                                } else {
                                    $userinfo = $JFusionMaster->getUser($auth_userinfo);
                                }

                                $jfusionDebug['init'][] = JText::_('MASTER') . ' ' . JText::_('USER') . ' ' . JText::_('CREATE') . ' ' . JText::_('SUCCESS');
                            } else {
                                //could not create user
                                ob_end_clean();
                                $jfusionDebug['init'][] = $master->name . ' ' . JText::_('USER') . ' ' . JText::_('CREATE') . ' ' . JText::_('ERROR') . ' ' . $status['error'];
                                JFusionFunction::raiseWarning($master->name . ' ' . JText::_('USER') . ' ' . JText::_('CREATE'), $status['error'], 1);
                                $success = false;
                                return $success;
                            }
                        } else {
                            //return an error
                            $jfusionDebug['init'][] = JText::_('COULD_NOT_FIND_USER');
                            ob_end_clean();
                            $success = false;
                            return $success;
                        }
                    }
                }
            }

            //apply the cleartext password to the user object
            $userinfo->password_clear = $user['password'];

            //if logging in via Joomla's backend, create a Joomla session and do nothing else to prevent lockouts
            if (empty($JFusionLoginCheckActive) && $mainframe->isAdmin()) {
                $JoomlaUserinfo = (empty($JoomlaUserinfo)) ? $JFusionJoomla->getUser($userinfo) : $JoomlaUserinfo;
                $JoomlaSession = $JFusionJoomla->createSession($JoomlaUserinfo, $options);
                if (!empty($JoomlaSession['error'])) {
                    //no Joomla session could be created -> deny login
                    JFusionFunction::raiseWarning('joomla_int ' . ' ' . JText::_('SESSION') . ' ' . JText::_('CREATE'), $JoomlaSession['error'], 1);
                    //hide the default Joomla login failure message
                    JError::setErrorHandling(E_WARNING, 'ignore');
                    ob_end_clean();
                    $success = false;
                    return $success;
                } else {
                    //make sure Joomla's salt is Joomla-compatible while we have the clear password
                    if (!empty($userinfo->password_clear) && strlen($userinfo->password_clear) != 32 && strpos($JoomlaUserinfo->password_salt, ':') !== false) {
                        $JoomlaUserinfo->password_clear = $userinfo->password_clear;
                        $JFusionJoomla->updatePassword($userinfo, $JoomlaUserinfo, $jfusionDebug);
                    }
                    ob_end_clean();
                    $success = true;
                    return $success;
                }
            }

            // See if the user has been blocked or is not activated
            if (!empty($userinfo->block) || !empty($userinfo->activation)) {
                //make sure the block is also applied in slave softwares
                $slaves = JFusionFunction::getSlaves();
                foreach ($slaves as $slave) {
                    if ($JFusionActivePlugin != $slave->name) {
                        $JFusionSlave = & JFusionFactory::getUser($slave->name);
                        $SlaveUser = $JFusionSlave->updateUser($userinfo, $overwrite);
                        //make sure the userinfo is available
                        if (empty($SlaveUser['userinfo'])) {
                            $SlaveUser['userinfo'] = $JFusionSlave->getUser($userinfo);
                        }
                        if (!empty($SlaveUser['error'])) {
                            $jfusionDebug[$slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $SlaveUser['error'];
                        }
                        $jfusionDebug[$slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG') ] = $SlaveUser['debug'];
                        $jfusionDebug[$slave->name . ' ' . JText::_('USERINFO') ] = $SlaveUser['userinfo'];
                    }
                }
                if (!empty($userinfo->block)) {
                    $jfusionDebug['error'][] = JText::_('FUSION_BLOCKED_USER');
                    JError::raiseWarning('500', JText::_('FUSION_BLOCKED_USER'));
                    //hide the default Joomla login failure message
                    JError::setErrorHandling(E_WARNING, 'ignore');
                    ob_end_clean();
                    $success = false;
                    return $success;
                } else {
                    $jfusionDebug['error'][] = JText::_('FUSION_INACTIVE_USER');
                    JError::raiseWarning('500', JText::_('FUSION_INACTIVE_USER'));
                    //hide the default Joomla login failure message
                    JError::setErrorHandling(E_WARNING, 'ignore');
                    ob_end_clean();
                    $success = false;
                    return $success;
                }
            }
            //check to see if we need to setup a Joomla session
            if ($master->name != 'joomla_int') {
                //setup the Joomla user
                $JoomlaUser = $JFusionJoomla->updateUser($userinfo, $overwrite);
                if (!empty($JoomlaUser['error'])) {
                    //no Joomla user could be created, fatal error
                    $jfusionDebug['joomla_int ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG') ] = $JoomlaUser['debug'];
                    $jfusionDebug['joomla_int ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $JoomlaUser['error'];
                    JFusionFunction::raiseWarning('joomla_int: ' . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE'), $JoomlaUser['error'], 1);
                    //hide the default Joomla login failure message
                    JError::setErrorHandling(E_WARNING, 'ignore');
                    ob_end_clean();
                    $success = false;
                    return $success;
                } else {
                    $jfusionDebug['joomla_int ' . JText::_('USER') . ' ' . JText::_('UPDATE') ] = $JoomlaUser['debug'];
                    if (isset($options['show_unsensored'])) {
                        $jfusionDebug['joomla_int ' . JText::_('USER') . ' ' . JText::_('DETAILS') ] = $JoomlaUser['userinfo'];
                    } else {
                        $jfusionDebug['joomla_int ' . JText::_('USER') . ' ' . JText::_('DETAILS') ] = JFusionFunction::anonymizeUserinfo($JoomlaUser['userinfo']);
                    }

                }
                //create a Joomla session
                if ($JFusionActivePlugin != 'joomla_int') {
                    $JoomlaSession = $JFusionJoomla->createSession($JoomlaUser['userinfo'], $options);
                    if (!empty($JoomlaSession['error'])) {
                        $jfusionDebug['joomla_int ' . JText::_('SESSION') . ' ' . JText::_('DEBUG') ] = $JoomlaSession['debug'];
                        $jfusionDebug['joomla_int ' . JText::_('SESSION') . ' ' . JText::_('ERROR') ] = $JoomlaSession['error'];
                        //no Joomla session could be created -> deny login
                        JFusionFunction::raiseWarning('joomla_int ' . ' ' . JText::_('SESSION') . ' ' . JText::_('CREATE'), $JoomlaSession['error'], 1);
                        //hide the default Joomla login failure message
                        JError::setErrorHandling(E_WARNING, 'ignore');
                        ob_end_clean();
                        $success = false;
                        return $success;
                    } else {
                        $jfusionDebug['joomla_int ' . JText::_('SESSION') ] = $JoomlaSession['debug'];
                    }
                }
            } else {
                //joomla already setup, we can copy its details from the master
                $JFusionJoomla = $JFusionMaster;
                $JoomlaUser = array('userinfo' => $userinfo, 'error' => '');
            }
            //setup the master session if
            //a) The master is not joomla_int and the user is logging into Joomla's frontend only
            //b) The master is joomla_int and the user is logging into either Joomla's frontend or backend
            if ($JFusionActivePlugin != $master->name && $master->dual_login == 1 && (!isset($options['group']) || $master->name == 'joomla_int')) {
                $MasterSession = $JFusionMaster->createSession($userinfo, $options);
                if (!empty($MasterSession['error'])) {
                    $jfusionDebug[$master->name . ' ' . JText::_('SESSION') . ' ' . JText::_('DEBUG') ] = $MasterSession['debug'];
                    $jfusionDebug[$master->name . ' ' . JText::_('SESSION') . ' ' . JText::_('ERROR') ] = $MasterSession['error'];
                    //report the error back
                    JFusionFunction::raiseWarning($master->name . ' ' . JText::_('SESSION') . ' ' . JText::_('CREATE'), $MasterSession['error'], 1);
                    if ($master->name == 'joomla_int') {
                        //we can not tolerate Joomla session failures
                        ob_end_clean();
                        //hide the default Joomla login failure message
                        JError::setErrorHandling(E_WARNING, 'ignore');
                        $success = false;
                        return $success;
                    }
                } else {
                    $jfusionDebug[$master->name . ' ' . JText::_('SESSION') ] = $MasterSession['debug'];
                }
            }
            //allow for joomlaid retrieval in the loginchecker
            $jfusionDebug['joomlaid'] = $JoomlaUser['userinfo']->userid;
            if ($master->name != 'joomla_int') {
                JFusionFunction::updateLookup($userinfo, $JoomlaUser['userinfo']->userid, $master->name);
            }
            //setup the other slave JFusion plugins
            $slaves = JFusionFunction::getPlugins();
            foreach ($slaves as $slave) {
                $JFusionSlave = & JFusionFactory::getUser($slave->name);
                $SlaveUser = $JFusionSlave->updateUser($userinfo, $overwrite);
                if (!empty($SlaveUser['error'])) {
                    $jfusionDebug[$slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('DEBUG') ] = $SlaveUser['debug'];
                    $jfusionDebug[$slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $SlaveUser['error'];
                    JFusionFunction::raiseWarning($slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE'), $SlaveUser['error'], 1);
                } else {
                    //make sure the userinfo is available
                    if (empty($SlaveUser['userinfo'])) {
                        $SlaveUser['userinfo'] = $JFusionSlave->getUser($userinfo);
                    }
                    $jfusionDebug[$slave->name . ' ' . JText::_('USER') . ' ' . JText::_('UPDATE') ] = $SlaveUser['debug'];
                    if (isset($options['show_unsensored'])) {
                        $jfusionDebug[$slave->name. ' ' . JText::_('USER') . ' ' . JText::_('DETAILS') ] = $SlaveUser['userinfo'];
                    } else {
                        $jfusionDebug[$slave->name. ' ' . JText::_('USER') . ' ' . JText::_('DETAILS') ] = JFusionFunction::anonymizeUserinfo($SlaveUser['userinfo']);
                    }

                    //apply the cleartext password to the user object
                    $SlaveUser['userinfo']->password_clear = $user['password'];
                    JFusionFunction::updateLookup($SlaveUser['userinfo'], $JoomlaUser['userinfo']->userid, $slave->name);
                    if (!isset($options['group']) && $slave->dual_login == 1 && $JFusionActivePlugin != $slave->name) {
                        $SlaveSession = $JFusionSlave->createSession($SlaveUser['userinfo'], $options);
                        if (!empty($SlaveSession['error'])) {
                            $jfusionDebug[$slave->name . ' ' . JText::_('SESSION') . ' ' . JText::_('DEBUG') ] = $SlaveSession['debug'];
                            $jfusionDebug[$slave->name . ' ' . JText::_('SESSION') . ' ' . JText::_('ERROR') ] = $SlaveSession['error'];
                            JFusionFunction::raiseWarning($slave->name . ' ' . JText::_('SESSION') . ' ' . JText::_('CREATE'), $SlaveSession['error'], 1);
                        } else {
                            $jfusionDebug[$slave->name . ' ' . JText::_('SESSION') ] = $SlaveSession['debug'];
                        }
                    }
                }
            }
            //Clean up the joomla session table
            $conf = JFactory::getConfig();
            $expire = ($conf->getValue('config.lifetime')) ? $conf->getValue('config.lifetime') * 60 : 900;
            $session = & JTable::getInstance('session');
            $session->purge($expire);


            $params = & JFusionFactory::getParams('joomla_int');
            $allow_redirect_login = $params->get('allow_redirect_login', 0);
            $redirecturl_login = $params->get('redirecturl_login', '');
            $source_url = $params->get('source_url', '');
            ob_end_clean();
            $jfc = JFusionFactory::getCookies();
            if ( $allow_redirect_login && !empty($redirecturl_login)) // only redirect if we are in the frontend and allowed and have an URL
            {
                $jfc->executeRedirect($source_url,$redirecturl_login);
            } else {
                $jfc->executeRedirect($source_url);
            }
            $result = true;
            return $result;
        } else {
            ob_end_clean();
            $result = false;
            return $result;
        }
    }
    /**
     * This method should handle any logout logic and report back to the subject
     *
     * @param array|object $user     holds the user data
     * @param array &$options array holding options (client, ...)
     *
     * @return object True on success
     * @since 1.5
     * @access public
     */
    function onLogoutUser($user, $options = array())
    {
        //initialise some vars
        global $JFusionActive, $jfusionDebug;
        $JFusionActive = true;
        $my = JFactory::getUser($user['id']);
        //allow for the detection of external mods to exclude jfusion plugins
        global $JFusionActivePlugin;
        jimport('joomla.environment.request');
        $jnodeid = strtolower(JRequest::getVar('jnodeid'));
        if (!empty($jnodeid)){
            $JFusionActivePlugin = $jnodeid;
        }

        //prevent any output by the plugins (this could prevent cookies from being passed to the header)
        ob_start();
        //logout from the JFusion plugins if done through frontend
        if (empty($options['clientid'][0])) {
            //get the JFusion master
            $master = JFusionFunction::getMaster();
            if ($master->name && $master->name != 'joomla_int' && $JFusionActivePlugin != $master->name) {
                $JFusionMaster = & JFusionFactory::getUser($master->name);
                $userlookup = JFusionFunction::lookupUser($master->name, $my->get('id'));
                $jfusionDebug['userlookup'] = $userlookup;
                $MasterUser = $JFusionMaster->getUser($userlookup);
                if (isset($options['show_unsensored'])) {
                    $jfusionDebug['masteruser'] = $MasterUser;
                } else {
                    $jfusionDebug['masteruser'] = JFusionFunction::anonymizeUserinfo($MasterUser);
                }
                //check if a user was found
                if (!empty($MasterUser)) {
                    $MasterSession = $JFusionMaster->destroySession($MasterUser, $options);
                    if (!empty($MasterSession['error'])) {
                        JFusionFunction::raiseWarning($master->name . ' ' . JText::_('SESSION') . ' ' . JText::_('DESTROY'), $MasterSession['error']);
                    }
                    $jfusionDebug[$master->name . ' logout'] = $MasterSession['debug'];
                } else {
                    JFusionFunction::raiseWarning($master->name . ' ' . JText::_('LOGOUT'), JText::_('COULD_NOT_FIND_USER'), 1);
                }
            }
            $slaves = JFusionFunction::getPlugins();
            foreach ($slaves as $slave) {
                //check if sessions are enabled
                if ($slave->dual_login == 1 && $JFusionActivePlugin != $slave->name) {
                    $JFusionSlave = & JFusionFactory::getUser($slave->name);
                    $userlookup = JFusionFunction::lookupUser($slave->name, $my->get('id'));
                    $SlaveUser = $JFusionSlave->getUser($userlookup);
                    if (isset($options['show_unsensored'])) {
                        $jfusionDebug[$slave->name . ' ' . JText::_('USER') . ' ' . JText::_('DETAILS') ] = $SlaveUser;
                    } else {
                        $jfusionDebug[$slave->name . ' ' . JText::_('USER') . ' ' . JText::_('DETAILS') ] = JFusionFunction::anonymizeUserinfo($SlaveUser);
                    }

                    //check if a user was found
                    if (!empty($SlaveUser)) {
                        $SlaveSession = $JFusionSlave->destroySession($SlaveUser, $options);
                        if (!empty($SlaveSession['error'])) {
                            JFusionFunction::raiseWarning($slave->name . ' ' . JText::_('SESSION') . ' ' . JText::_('DESTROY'), $SlaveSession['error'], 1);
                        }
                        if (!empty($SlaveSession['debug'])) {
                        	$jfusionDebug[$slave->name . ' logout'] = $SlaveSession['debug'];
                        }
                    } else {
                        JFusionFunction::raiseWarning($slave->name . ' ' . JText::_('LOGOUT'), JText::_('COULD_NOT_FIND_USER'), 1);
                    }
                }
            }
        }
        
        //destroy the joomla session itself
        if ($JFusionActivePlugin != 'joomla_int') {
            $JoomlaUser = & JFusionFactory::getUser('joomla_int');
            $JoomlaUser->destroySession($user, $options);
        }

        $params = & JFusionFactory::getParams('joomla_int');
        $allow_redirect_logout = $params->get('allow_redirect_logout', 0);
        $redirecturl_logout = $params->get('redirecturl_logout', '');
        $source_url = $params->get('source_url', '');
        ob_end_clean();
        $jfc = JFusionFactory::getCookies();
        if ( $allow_redirect_logout && !empty($redirecturl_logout)) // only redirect if we are in the frontend and allowed and have an URL
        {
        	$jfc->executeRedirect($source_url,$redirecturl_logout);
        } else {
        	$jfc->executeRedirect($source_url);
        }
        
        $result = true;
        return $result;
    }
    /**
     * This method is called before user is stored
     *
     * @param array   $olduser holds the user data
     * @param boolean $isnew   is new user
     *
     * @access public
     *
     * @return boolean
     */
    function onBeforeStoreUser($olduser, $isnew)
    {
        global $JFusionActive;
        if (!$JFusionActive) {
            // Recover old data from user before to save it. The purpose is to provide it to the plugins if needed
            $session = JFactory::getSession();
            $session->set('olduser', $olduser);
        }
        $result = true;
        return $result;
    }
    /**
     * This method is called after user is stored
     *
     * @param array   $user   holds the user data
     * @param boolean $isnew  is new user
     * @param boolean $succes was it a sucess
     * @param string  $msg    Message
     *
     * @access public
     * @return boolean False on Falior
     */
    function onAfterStoreUser($user, $isnew, $succes, $msg)
    {
        if (!$succes) {
            $result = false;
            return $result;
        }
        //create an array to store the debug info
        $debug_info = array();
        //prevent any output by the plugins (this could prevent cookies from being passed to the header)
        ob_start();
        $Itemid_backup = JRequest::getInt('Itemid', 0);
        global $JFusionActive;
        if (!$JFusionActive) {
            //A change has been made to a user without JFusion knowing about it
            //we need to make sure that group_id is in the $user array
            ;
            if (!isset($user['group_id']) && !isset($user['gid'])) {
                $user['group_id'] = $user['gid'];
            }
            //convert the user array into a user object
            $JoomlaUser = (object)$user;
            //check to see if we need to update the master
            $master = JFusionFunction::getMaster();
            // Recover the old data of the user
            // This is then used to determine if the username was changed
            $session = JFactory::getSession();
            $JoomlaUser->olduserinfo = (object)$session->get('olduser');
            $session->clear('olduser');
            $updateUsername = (!$isnew && $JoomlaUser->olduserinfo->username != $JoomlaUser->username) ? true : false;
            //retrieve the username stored in jfusion_users if it exists
            $db = JFactory::getDBO();
            $query = 'SELECT username FROM #__jfusion_users WHERE id = ' . (int)$JoomlaUser->id;
            $db->setQuery($query);
            $storedUsername = $db->loadResult();
            if ($updateUsername) {
                //update the jfusion_user table with the new username
                $query = 'REPLACE INTO #__jfusion_users (id, username) VALUES (' . (int)$JoomlaUser->id . ', ' . $db->Quote($JoomlaUser->username) . ')';
                $db->setQuery($query);
                if (!$db->query()) {
                    JError::raiseWarning(0, $db->stderr());
                }
                //if we had a username stored in jfusion_users, update the olduserinfo with that username before passing it into the plugins so they will find the intended user
                if (!empty($storedUsername)) {
                    $JoomlaUser->olduserinfo->username = $storedUsername;
                }
            } else {
                if (!empty($JoomlaUser->original_username)) {
                    //the user was created by JFusion's JFusionJplugin::createUser and we have the original username which must be used as the jfusion_user table has not been updated yet
                    $JoomlaUser->username = $JoomlaUser->original_username;
                } elseif (!empty($storedUsername)) {
                    //the username is not being updated but if there is a username stored in jfusion_users table, it must be used instead to prevent user duplication
                    $JoomlaUser->username = $storedUsername;
                }
            }
            $JFusionMaster = & JFusionFactory::getUser($master->name);
            //update the master user if not joomla_int
            if ($master->name != 'joomla_int') {                
                $master_userinfo = $JFusionMaster->getUser($JoomlaUser->olduserinfo);
                //if the username was updated, call the updateUsername function before calling updateUser
                if ($updateUsername) {
                    $updateUsernameStatus = array();
                    if (!empty($master_userinfo)) {
                        $JFusionMaster->updateUsername($JoomlaUser, $master_userinfo, $updateUsernameStatus);
                        if (!empty($updateUsernameStatus['error'])) {
                            $debug_info[$master->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $updateUsernameStatus['error'];
                        }
                        $debug_info[$master->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') ] = $updateUsernameStatus['debug'];
                    } else {
                        $debug_info[$master->name] = JText::_('NO_USER_DATA_FOUND');
                    }
                }
                //run the update user to ensure any other userinfo is updated as well
                $MasterUser = $JFusionMaster->updateUser($JoomlaUser, 1);
                if (!empty($MasterUser['error'])) {
                    $debug_info[$master->name] = $MasterUser['error'];
                }
                //make sure the userinfo is available
                if (empty($MasterUser['userinfo'])) {
                    $MasterUser['userinfo'] = $JFusionMaster->getUser($JoomlaUser);
                }
                $debug_info[$master->name] = $MasterUser['debug'];
                //update the jfusion_users_plugin table
                JFusionFunction::updateLookup($MasterUser['userinfo'], $JoomlaUser->id, $master->name);
            } else {
	            //Joomla is master
// commented out because we should use the joomla use object (in out plugins)
//	            $master_userinfo = & $JoomlaUser;
	            $master_userinfo = $JFusionMaster->getUser($JoomlaUser);
            	if(!JFusionFunction::isJoomlaVersion('1.6')) {
	                if ($JoomlaUser->block == 0 && !empty($JoomlaUser->activation)) {
	                    //let's clear out Joomla's activation status for sanity's sake
	                    $db = JFactory::getDBO();
	                    $query = "UPDATE #__users SET activation = '' WHERE id = " . $JoomlaUser->id;
	                    $db->setQuery($query);
	                    $db->query();
	                    $JoomlaUser->activation = '';
	                }
				}                
            }
            if ( !empty($JoomlaUser->password_clear) ) {
            	$master_userinfo->password_clear = $JoomlaUser->password_clear;
            }
            //update the user details in any JFusion slaves
            $slaves = JFusionFunction::getPlugins();
            foreach ($slaves as $slave) {
                $JFusionSlave = & JFusionFactory::getUser($slave->name);
                //if the username was updated, call the updateUsername function before calling updateUser
                if ($updateUsername) {
                    $slave_userinfo = $JFusionSlave->getUser($JoomlaUser->olduserinfo);
                    if (!empty($slave_userinfo)) {
                        $updateUsernameStatus = array();
                        $JFusionSlave->updateUsername($master_userinfo, $slave_userinfo, $updateUsernameStatus);
                        if (!empty($updateUsernameStatus['error'])) {
                            $debug_info[$slave->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') . ' ' . JText::_('ERROR') ] = $updateUsernameStatus['error'];
                        }
                        $debug_info[$slave->name . ' ' . JText::_('USERNAME') . ' ' . JText::_('UPDATE') ] = $updateUsernameStatus['debug'];
                    } else {
                        $debug_info[$slave->name] = JText::_('NO_USER_DATA_FOUND');
                    }
                }
                $SlaveUser = $JFusionSlave->updateUser($master_userinfo, 1);

                if (!empty($SlaveUser['error'])) {
                    if (!is_array($SlaveUser['error'])) {
                        $SlaveUser['error'] = array($SlaveUser['error']);
                    }
                    $debug_info[$slave->name] = $SlaveUser['error'];
                    if (!empty($SlaveUser['debug'])) {
                        if (!is_array($SlaveUser['debug'])) {
                            $SlaveUser['debug'] = array($SlaveUser['debug']);
                        }
                        $debug_info[$slave->name] = $debug_info[$slave->name] + $SlaveUser['debug'];
                    }
                } else {
                    $debug_info[$slave->name] = $SlaveUser['debug'];
                }

                //update the jfusion_users_plugin table
                JFusionFunction::updateLookup($SlaveUser['userinfo'], $JoomlaUser->id, $slave->name);
            }
        }
        //check to see if the Joomla database is still connnected incase the plugin messed it up
        JFusionFunction::reconnectJoomlaDb();
        if ($Itemid_backup!=0) {
	        //reset the global $Itemid so that modules are not repeated
	        global $Itemid;
    	    $Itemid = $Itemid_backup;
	        //reset Itemid so that it can be obtained via getVar
        	JRequest::setVar('Itemid', $Itemid_backup);
        }
        //return output if allowed
        $isAdministrator = JFusionFunction::isAdministrator();
        if ($isAdministrator === true) {
            JFusionFunction::raiseWarning('', $debug_info, 1);
        }
        //stop output buffer
        ob_end_clean();
        return true;
    }

    /*
     * joomla 1.6 compatibility code
     *
     * @param $user
     * @param array $options
     * @return bool
     */
    /**
     * @param $user
     * @param array $options
     * @return bool
     */
    public function onUserLogin($user, $options = array()){
 	    return $this->onLoginUser($user, $options);
 	}

    /**
     * @param $user
     * @param array $options
     * @return object
     */
    public function onUserLogout($user, $options = array())	{
 	    return $this->onLogoutUser($user, $options);
	}

    /**
     * @param $user
     * @param $succes
     * @param $msg
     * @return bool
     */
    public function onUserAfterDelete($user, $succes, $msg)	{
 	    return $this->onAfterDeleteUser($user, $succes, $msg);
	}

    /**
     * @param $user
     * @param $isnew
     * @param $new
     * @return bool
     */
    public function onUserBeforeSave($user, $isnew, $new){
 	    return $this->onBeforeStoreUser($user, $isnew, $new);
	}

    /**
     * @param $user
     * @param $isnew
     * @param $success
     * @param $msg
     * @return bool
     */
    public function onUserAfterSave($user, $isnew, $success, $msg) {
        if (!JPluginHelper::isEnabled('user','joomla')) {
            $userInfo = JFactory::getUser();
            $levels = implode(',', $userInfo->getAuthorisedViewLevels());
            
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);
            
            $query->select('folder, type, element, params')
            ->from('#__extensions')
            ->where('type =' . $db->Quote('plugin'))
            ->where('element =' . $db->Quote('joomla'))
            ->where('folder =' . $db->Quote('user'))
            ->where('access IN (' . $levels . ')');
                
            $plugin = $db->setQuery($query,0,1)->loadObject();
                
            $params = new JRegistry;
            $params->loadString($plugin->params);
            
            // Initialise variables.
            $app    = JFactory::getApplication();
            $config = JFactory::getConfig();
            $mail_to_user = $params->get('mail_to_user', 1);

            if ($isnew) {
                // TODO: Suck in the frontend registration emails here as well. Job for a rainy day.
            
                if ($app->isAdmin()) {
                    if ($mail_to_user) {
            
                        // Load user_joomla plugin language (not done automatically).
                        $lang = JFactory::getLanguage();
                        $lang->load('plg_user_joomla', JPATH_ADMINISTRATOR);
            
                        // Compute the mail subject.
                        $emailSubject = JText::sprintf(
                                'PLG_USER_JOOMLA_NEW_USER_EMAIL_SUBJECT',
                                $user['name'],
                                $config->get('sitename')
                        );
            
                        // Compute the mail body.
                        $emailBody = JText::sprintf(
                                'PLG_USER_JOOMLA_NEW_USER_EMAIL_BODY',
                                $user['name'],
                                $config->get('sitename'),
                                JUri::root(),
                                $user['username'],
                                $user['password_clear']
                        );
            
                        // Assemble the email data...the sexy way!
                        $mail = JFactory::getMailer()
                        ->setSender(
                                array(
                                        $config->get('mailfrom'),
                                        $config->get('fromname')
                                )
                        )
                        ->addRecipient($user['email'])
                        ->setSubject($emailSubject)
                        ->setBody($emailBody);
            
                        if (!$mail->Send()) {
                            // TODO: Probably should raise a plugin error but this event is not error checked.
                            JError::raiseWarning(500, JText::_('ERROR_SENDING_EMAIL'));
                        }
                    }
                }
            }
            else {
                // Existing user - nothing to do...yet.
            }
        }
 	    $result = $this->onAfterStoreUser($user, $isnew, $success, $msg);
 	    return $result;
	}


}