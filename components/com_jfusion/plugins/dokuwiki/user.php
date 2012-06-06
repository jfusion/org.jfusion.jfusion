<?php

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion user class for DokuWiki
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionUser_dokuwiki extends JFusionUser {
    /**
     * @param object $userinfo
     * @param int $overwrite
     * @return array
     */
    function updateUser($userinfo, $overwrite) {
        // Initialise some variables
        $params = JFusionFactory::getParams($this->getJname());
        $helper = JFusionFactory::getHelper($this->getJname());
        $userinfo->username = $this->filterUsername($userinfo->username);
        $update_email = $params->get('update_email');
        $status = array('error' => array(),'debug' => array());
        //check to see if a valid $userinfo object was passed on
        if (!is_object($userinfo)) {
            $status['error'][] = JText::_('NO_USER_DATA_FOUND');
        } else {
            //find out if the user already exists
            $existinguser = $this->getUser($userinfo);
            if (!empty($existinguser)) {
                $changes = array();
                //a matching user has been found
                $status['debug'][] = JText::_('USER_DATA_FOUND');
                if ($existinguser->email != $userinfo->email) {
                    $status['debug'][] = JText::_('EMAIL_CONFLICT');
                    if ($update_email || $overwrite) {
                        $status['debug'][] = JText::_('EMAIL_CONFLICT_OVERWITE_ENABLED');
                        $changes['mail'] = $userinfo->email;
                        $status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
                    } else {
                        //return a email conflict
                        $status['debug'][] = JText::_('EMAIL_CONFLICT_OVERWITE_DISABLED');
                        $status['error'][] = JText::_('EMAIL') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
                        $status['userinfo'] = $existinguser;
                        return $status;
                    }
                }
                if ($existinguser->name != $userinfo->name) {
                    $changes['name'] = $userinfo->name;
                } else {
                    $status['debug'][] = JText::_('SKIPPED_NAME_UPDATE');
                }
                if (isset($userinfo->password_clear) && strlen($userinfo->password_clear)) {
                    if (!$helper->auth->verifyPassword($userinfo->password_clear, $existinguser->password)) {
                        // add password_clear to existinguser for the Joomla helper routines
                        $existinguser->password_clear = $userinfo->password_clear;
                        $changes['pass'] = $userinfo->password_clear;
                    } else {
                        $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ':' . JText::_('PASSWORD_VALID');
                    }
                } else {
                    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
                }
                //check for advanced usergroup sync
                $master = JFusionFunction::getMaster();
                if (JFusionFunction::isAdvancedUsergroupMode($this->getJname()) && $master->name != $this->getJname()) {
                    $correct_usergroup = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
                    if (!empty($correct_usergroup)) {
                        $correct_usergroup = explode(',', $correct_usergroup);
                        $update_group = 0;
                        foreach ($correct_usergroup as $value) {
                            foreach ($existinguser->group_id as $value2) {
                                if (trim($value) == trim($value2)) {
                                    $update_group++;
                                    break;
                                }
                            }
                        }
                        if (count($existinguser->group_id) != $update_group) {
                            $changes['grps'] = $correct_usergroup;
                        } else {
                            $status['debug'][] = JText::_('SKIPPED_GROUP_UPDATE') . ':' . JText::_('GROUP_VALID');
                        }
                    } else {
                        $status['error'][] = JText::_('GROUP_UPDATE_ERROR');
                    }
                }
                if (count($changes)) {
                    if (!$helper->auth->modifyUser($userinfo->username, $changes)) {
                        $status['error'][] = 'ERROR: Updating ' . $userinfo->username;
                    }
                }
                $status['userinfo'] = $existinguser;
                if (empty($status['error'])) {
                    $status['action'] = 'updated';
                }
            } else {
                $status['debug'][] = JText::_('NO_USER_FOUND_CREATING_ONE');
                $this->createUser($userinfo, $status);
                if (empty($status['error'])) {
                    $status['action'] = 'created';
                }
            }
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @return null|object
     */
    function getUser($userinfo) {
        $helper = JFusionFactory::getHelper($this->getJname());
    	if (is_object($userinfo)) {
    		$username = $this->filterUsername($userinfo->username);
		} else {
			$username = $this->filterUsername($userinfo);
		}
		$raw_user = $helper->auth->getUserData($username);
        if (is_array($raw_user)) {
            $user = new stdClass;
            $user->userid = $username;
            $user->name = $raw_user['name'];
            $user->username = $username;
            $user->password = $raw_user['pass'];
            $user->email = $raw_user['mail'];
            $user->group_id = $raw_user['grps'];
        } else {
            $user = null;
        }
        return $user;
    }

    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'dokuwiki';
    }

    /**
     * @param object $userinfo
     * @return array
     */
    function deleteUser($userinfo) {
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());
        $username = $this->filterUsername($userinfo->username);
        $user[$username] = $username;
        $helper = JFusionFactory::getHelper($this->getJname());
        if (!$helper->auth->deleteUsers($user)) {
            $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . 'No User Deleted';
        } else {
            $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array
     */
    function destroySession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());

        $params = & JFusionFactory::getParams($this->getJname());

        $cookie_path = $params->get('cookie_path', '/');
        $cookie_domain = $params->get('cookie_domain');
        $cookie_secure = $params->get('secure', false);
        $httponly = $params->get('httponly', true);

        //setup Dokuwiki's constants
        $helper = JFusionFactory::getHelper($this->getJname());
        $helper->defineConstants();

        $time = time()-3600;
        JFusionFunction::addCookie(DOKU_COOKIE, '', $time, $cookie_path, $cookie_domain, $cookie_secure, $httponly);
        // remove blank domain name cookie just in case we are using wrapper
        $source_url = $params->get('source_url');
        $cookie_path = preg_replace('#(\w{0,10}://)(.*?)/(.*?)#is', '$3', $source_url);
        $cookie_path = preg_replace('#//+#', '/', "/$cookie_path/");
        JFusionFunction::addCookie(DOKU_COOKIE, '', $time, $cookie_path, '', $cookie_secure, $httponly);
        
        $status['debug'][] = DOKU_COOKIE . " " . JText::_('DELETED');
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     * @return array
     */
    function createSession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());

        if(!empty($userinfo->password_clear)){
            $params = JFusionFactory::getParams($this->getJname());

            //set login cookie
            require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'auth' . DS .'blowfish.php';
            $cookie_path = $params->get('cookie_path', '/');
            $cookie_domain = $params->get('cookie_domain');
            $cookie_secure = $params->get('secure', false);
            $httponly = $params->get('httponly', true);

            //setup Dokuwiki's constants
            $helper = & JFusionFactory::getHelper($this->getJname());
            $helper->defineConstants();
            $salt = $helper->getCookieSalt();
            $pass = JFusion_PMA_blowfish_encrypt($userinfo->password_clear,$salt);
//            $sticky = (!empty($options['remember'])) ? 1 : 0;
            $sticky = 1;
        	$version = $helper->getVersion();
			if ( version_compare($version, '2009-12-02 "Mulled Wine"') >= 0) {
				$cookie_value = base64_encode($userinfo->username).'|'. $sticky . '|'.base64_encode($pass);
			} else {
                $cookie_value = base64_encode($userinfo->username.'|'.$sticky.'|'.$pass);
			}
            $time = 60*60*24*365;
            $debug_expiration = date("Y-m-d H:i:s", time()+$time);
            JFusionFunction::addCookie(DOKU_COOKIE, $cookie_value, $time, $cookie_path, $cookie_domain, $cookie_secure, $httponly);

            $status['debug'][JText::_('CREATED') . ' ' . JText::_('COOKIES')][] = array(JText::_('NAME') => DOKU_COOKIE, JText::_('VALUE') => $cookie_value, JText::_('EXPIRES') => $debug_expiration, JText::_('COOKIE_PATH') => $cookie_path, JText::_('COOKIE_DOMAIN') => $cookie_domain);
        }

        return $status;
    }

    /**
     * @param string $username
     * @return string
     */
    function filterUsername($username) {
        $username = str_replace(' ', '_', $username);
        $username = str_replace(':', '', $username);
        return strtolower($username);
    }

    /**
     * @param object $userinfo
     * @param array &$status
     */
    function createUser($userinfo, &$status) {
        $helper = JFusionFactory::getHelper($this->getJname());
        if (isset($userinfo->password_clear)) {
            $pass = $userinfo->password_clear;
        } else {
            $pass = $userinfo->password;
        }
        $userinfo->username = $this->filterUsername($userinfo->username);

        $params = JFusionFactory::getParams($this->getJname());
        $master = JFusionFunction::getMaster();
		$correct_usergroup = null;
        if ($master->name != $this->getJname()) {
            $correct_usergroup = JFusionFunction::getCorrectUserGroups($this->getJname(),$userinfo);
			$correct_usergroup = explode(',', $correct_usergroup);
		}
		if (!count($correct_usergroup)) $correct_usergroup = null;

        //now append the new user data
        if (!$helper->auth->createUser($userinfo->username, $pass, $userinfo->name, $userinfo->email,$correct_usergroup)) {
            //return the error
            $status['error'] = JText::_('USER_CREATION_ERROR');
        } else {
            //return the good news
            $status['debug'][] = JText::_('USER_CREATION');
            $status['userinfo'] = $this->getUser($userinfo);
        }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updatePassword($userinfo, &$existinguser, &$status)
    {
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updateEmail($userinfo, &$existinguser, &$status)
    {
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     */
    function updateUsergroup($userinfo, &$existinguser, &$status)
    {
    }
}
