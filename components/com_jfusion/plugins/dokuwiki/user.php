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
class JFusionUser_dokuwiki extends JFusionUser
{
	/**
	 * @var $helper JFusionHelper_dokuwiki
	 */
	var $helper;

    /**
     * @param object $userinfo
     * @param int $overwrite
     *
     * @return array
     */
    function updateUser($userinfo, $overwrite = 0) {
        // Initialise some variables
        $userinfo->username = $this->filterUsername($userinfo->username);
        $update_email = $this->params->get('update_email');
        $status = array('error' => array(),'debug' => array());
        //check to see if a valid $userinfo object was passed on
	    try {
		    if (!is_object($userinfo)) {
			    throw new RuntimeException(JText::_('NO_USER_DATA_FOUND'));
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
						    $status['userinfo'] = $existinguser;
						    throw new RuntimeException(JText::_('EMAIL') . ' ' . JText::_('CONFLICT') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
					    }
				    }
				    if ($existinguser->name != $userinfo->name) {
					    $changes['name'] = $userinfo->name;
				    } else {
					    $status['debug'][] = JText::_('SKIPPED_NAME_UPDATE');
				    }
				    if (isset($userinfo->password_clear) && strlen($userinfo->password_clear)) {
					    if (!$this->helper->auth->verifyPassword($userinfo->password_clear, $existinguser->password)) {
						    // add password_clear to existinguser for the Joomla helper routines
						    $existinguser->password_clear = $userinfo->password_clear;
						    $changes['pass'] = $userinfo->password_clear;
					    } else {
						    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_VALID');
					    }
				    } else {
					    $status['debug'][] = JText::_('SKIPPED_PASSWORD_UPDATE') . ': ' . JText::_('PASSWORD_UNAVAILABLE');
				    }
				    //check for advanced usergroup sync
				    if (JFusionFunction::updateUsergroups($this->getJname())) {
					    $usergroups = $this->getCorrectUserGroups($userinfo);
					    if (!empty($usergroups)) {
						    if (!$this->compareUserGroups($existinguser, $usergroups)) {
							    $changes['grps'] = $usergroups;
						    } else {
							    $status['debug'][] = JText::_('SKIPPED_GROUP_UPDATE') . ': ' . JText::_('GROUP_VALID');
						    }
					    } else {
						    throw new RuntimeException(JText::_('GROUP_UPDATE_ERROR') . ': ' . JText::_('USERGROUP_MISSING'));
					    }
				    }
				    if (count($changes)) {
					    if (!$this->helper->auth->modifyUser($userinfo->username, $changes)) {
						    throw new RuntimeException('ERROR: Updating ' . $userinfo->username);
					    }
				    }
				    $status['userinfo'] = $existinguser;
				    $status['action'] = 'updated';
			    } else {
				    $status['debug'][] = JText::_('NO_USER_FOUND_CREATING_ONE');
				    $this->createUser($userinfo, $status);
				    if (empty($status['error'])) {
					    $status['action'] = 'created';
				    } else {
					    $status['action'] = 'error';
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }

        return $status;
    }

    /**
     * @param object $userinfo
     *
     * @return null|object
     */
    function getUser($userinfo) {
    	if (is_object($userinfo)) {
    		$username = $this->filterUsername($userinfo->username);
		} else {
			$username = $this->filterUsername($userinfo);
		}
		$raw_user = $this->helper->auth->getUserData($username);
        if (is_array($raw_user)) {
            $user = new stdClass;
            $user->block = 0;
            $user->activation = '';
            $user->userid = $username;
            $user->name = $raw_user['name'];
            $user->username = $username;
            $user->password = $raw_user['pass'];
            $user->email = $raw_user['mail'];

            $groups = $raw_user['grps'];

            if (!empty($groups)) {
                $user->groups = $groups;
                $user->groupnames = $groups;
                // Support as master if using old jfusion plugins.
                $user->group_id = $groups[0];
                $user->group_name = $groups[0];
            } else {
                $user->groups = array();
                $user->groupnames = array();
                $user->group_id = null;
                $user->group_name = null;
            }
        } else {
            $user = null;
        }
        return $user;
    }

    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'dokuwiki';
    }

    /**
     * @param object $userinfo
     *
     * @return array
     */
    function deleteUser($userinfo) {
        //setup status array to hold debug info and errors
        $status = array('error' => array(),'debug' => array());
        $username = $this->filterUsername($userinfo->username);
        $user[$username] = $username;

        if (!$this->helper->auth->deleteUsers($user)) {
            $status['error'][] = JText::_('USER_DELETION_ERROR') . ' ' . 'No User Deleted';
        } else {
            $status['debug'][] = JText::_('USER_DELETION') . ' ' . $username;
        }
        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());

        $cookie_path = $this->params->get('cookie_path', '/');
        $cookie_domain = $this->params->get('cookie_domain');
        $cookie_secure = $this->params->get('secure', false);
        $httponly = $this->params->get('httponly', true);

        //setup Dokuwiki constants
	    $this->helper->defineConstants();

        $time = -3600;
        $status['debug'][] = $this->addCookie(DOKU_COOKIE, '', $time, $cookie_path, $cookie_domain, $cookie_secure, $httponly);
        // remove blank domain name cookie just in case we are using wrapper
        $source_url = $this->params->get('source_url');
        $cookie_path = preg_replace('#(\w{0,10}://)(.*?)/(.*?)#is', '$3', $source_url);
        $cookie_path = preg_replace('#//+#', '/', "/$cookie_path/");
        $status['debug'][] = $this->addCookie(DOKU_COOKIE, '', $time, $cookie_path, '', $cookie_secure, $httponly);

        return $status;
    }

    /**
     * @param object $userinfo
     * @param array $options
     *
     * @return array
     */
    function createSession($userinfo, $options) {
        $status = array('error' => array(),'debug' => array());

        if(!empty($userinfo->password_clear)){
            //set login cookie
            require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR .'blowfish.php';
            $cookie_path = $this->params->get('cookie_path', '/');
            $cookie_domain = $this->params->get('cookie_domain');
            $cookie_secure = $this->params->get('secure', false);
            $httponly = $this->params->get('httponly', true);

            //setup Dokuwiki constants
	        $this->helper->defineConstants();
            $salt = $this->helper->getCookieSalt();
            $pass = JFusion_PMA_blowfish_encrypt($userinfo->password_clear,$salt);
//            $sticky = (!empty($options['remember'])) ? 1 : 0;
            $sticky = 1;
        	$version = $this->helper->getVersion();
			if ( version_compare($version, '2009-12-02 "Mulled Wine"') >= 0) {
				$cookie_value = base64_encode($userinfo->username) . '|'. $sticky . '|' . base64_encode($pass);
			} else {
                $cookie_value = base64_encode($userinfo->username . '|' . $sticky . '|' . $pass);
			}
            $status['debug'][] = $this->addCookie(DOKU_COOKIE, $cookie_value, 60*60*24*365, $cookie_path, $cookie_domain, $cookie_secure, $httponly);
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
     *
     * @return void
     */
    function createUser($userinfo, &$status) {
	    try {
		    $usergroups = $this->getCorrectUserGroups($userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException(JText::_('USERGROUP_MISSING'));
		    } else {
			    if (isset($userinfo->password_clear)) {
				    $pass = $userinfo->password_clear;
			    } else {
				    $pass = $userinfo->password;
			    }
			    $userinfo->username = $this->filterUsername($userinfo->username);

			    $usergroup = explode(',', $usergroups[0]);
			    if (!count($usergroup)) $usergroup = null;

			    //now append the new user data
			    if (!$this->helper->auth->createUser($userinfo->username, $pass, $userinfo->name, $userinfo->email, $usergroup)) {
				    //return the error
					throw new RuntimeException();
			    }
			    //return the good news
			    $status['debug'][] = JText::_('USER_CREATION');
			    $status['userinfo'] = $this->getUser($userinfo);
		    }
	    } catch (Exception $e) {
		    $status['error'][] = JText::_('USER_CREATION_ERROR') . ': ' . $e->getMessage();
	    }
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updatePassword($userinfo, &$existinguser, &$status)
    {
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateEmail($userinfo, &$existinguser, &$status)
    {
    }

    /**
     * @param object $userinfo
     * @param object $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateUsergroup($userinfo, &$existinguser, &$status)
    {
    }
}
