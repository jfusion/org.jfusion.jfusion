<?php namespace JFusion\Plugins\dokuwiki;

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
use JFusion\Framework;
use JFusion\Plugin\Plugin_User;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use \Exception;
use \RuntimeException;
use \stdClass;

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
class JFusionUser_dokuwiki extends Plugin_User
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    /**
     * @param Userinfo $userinfo
     * @param int $overwrite
     *
     * @return array
     */
    function updateUser(Userinfo $userinfo, $overwrite = 0) {
        // Initialise some variables
        $userinfo->username = $this->filterUsername($userinfo->username);
        $status = array('error' => array(), 'debug' => array());
        //check to see if a valid $userinfo object was passed on
	    try {
		    if (!is_object($userinfo)) {
			    throw new RuntimeException(Text::_('NO_USER_DATA_FOUND'));
		    } else {
			    //find out if the user already exists
			    $existinguser = $this->getUser($userinfo);
			    if (!empty($existinguser)) {
				    $changes = array();
				    //a matching user has been found
				    $status['debug'][] = Text::_('USER_DATA_FOUND');
				    if (strtolower($existinguser->email) != strtolower($userinfo->email)) {
					    $status['debug'][] = Text::_('EMAIL_CONFLICT');
					    $update_email = $this->params->get('update_email', false);
					    if ($update_email || $overwrite) {
						    $status['debug'][] = Text::_('EMAIL_CONFLICT_OVERWITE_ENABLED');
						    $changes['mail'] = $userinfo->email;
						    $status['debug'][] = Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
					    } else {
						    //return a email conflict
						    $status['debug'][] = Text::_('EMAIL_CONFLICT_OVERWITE_DISABLED');
						    $status['userinfo'] = $existinguser;
						    throw new RuntimeException(Text::_('EMAIL') . ' ' . Text::_('CONFLICT') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
					    }
				    }
				    if ($existinguser->name != $userinfo->name) {
					    $changes['name'] = $userinfo->name;
				    } else {
					    $status['debug'][] = Text::_('SKIPPED_NAME_UPDATE');
				    }
				    if (isset($userinfo->password_clear) && strlen($userinfo->password_clear)) {
					    if (!$this->helper->auth->verifyPassword($userinfo->password_clear, $existinguser->password)) {
						    // add password_clear to existinguser for the Joomla helper routines
						    $existinguser->password_clear = $userinfo->password_clear;
						    $changes['pass'] = $userinfo->password_clear;
						    $status['debug'][] = Text::_('PASSWORD_UPDATE')  . ': ' . substr($userinfo->password_clear, 0, 6) . '********';
					    } else {
						    $status['debug'][] = Text::_('SKIPPED_PASSWORD_UPDATE') . ': ' . Text::_('PASSWORD_VALID');
					    }
				    } else {
					    $status['debug'][] = Text::_('SKIPPED_PASSWORD_UPDATE') . ': ' . Text::_('PASSWORD_UNAVAILABLE');
				    }
				    //check for advanced usergroup sync

				    if (Framework::updateUsergroups($this->getJname())) {
					    $usergroups = $this->getCorrectUserGroups($userinfo);
					    if (!empty($usergroups)) {
						    if (!$this->compareUserGroups($existinguser, $usergroups)) {
							    $changes['grps'] = $usergroups;
						    } else {
							    $status['debug'][] = Text::_('SKIPPED_GROUP_UPDATE') . ': ' . Text::_('GROUP_VALID');
						    }
					    } else {
						    throw new RuntimeException(Text::_('GROUP_UPDATE_ERROR') . ': ' . Text::_('USERGROUP_MISSING'));
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
				    $status['debug'][] = Text::_('NO_USER_FOUND_CREATING_ONE');
				    $this->createUser($userinfo, $status);
				    if (empty($status['error'])) {
					    $status['action'] = 'created';
				    }
			    }
		    }
	    } catch (Exception $e) {
		    $status['error'][] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * @param Userinfo $userinfo
     *
     * @return null|Userinfo
     */
    function getUser(Userinfo $userinfo) {
	    $username = $this->filterUsername($userinfo->username);

		$raw_user = $this->helper->auth->getUserData($username);
        if (is_array($raw_user)) {
	        $result = new stdClass;
	        $result->block = false;
	        $result->activation = null;
	        $result->userid = $username;
	        $result->name = $raw_user['name'];
	        $result->username = $username;
	        $result->password = $raw_user['pass'];
	        $result->email = $raw_user['mail'];

            $groups = $raw_user['grps'];

            if (!empty($groups)) {
	            $result->groups = $groups;
	            $result->groupnames = $groups;
                // Support as master if using old jfusion plugins.
	            $result->group_id = $groups[0];
	            $result->group_name = $groups[0];
            } else {
	            $result->groups = array();
	            $result->groupnames = array();
	            $result->group_id = null;
	            $result->group_name = null;
            }
	        $user = new Userinfo($this->getJname());
	        $user->bind($result);
        } else {
            $user = null;
        }
        return $user;
    }

    /**
     * @param Userinfo $userinfo
     *
     * @return array
     */
    function deleteUser(Userinfo $userinfo) {
        //setup status array to hold debug info and errors
        $status = array('error' => array(), 'debug' => array());
        $username = $this->filterUsername($userinfo->username);
        $user[$username] = $username;

        if (!$this->helper->auth->deleteUsers($user)) {
            $status['error'][] = Text::_('USER_DELETION_ERROR') . ' ' . 'No User Deleted';
        } else {
            $status['debug'][] = Text::_('USER_DELETION') . ' ' . $username;
        }
        return $status;
    }

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $options) {
        $status = array('error' => array(), 'debug' => array());

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
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array
     */
    function createSession(Userinfo $userinfo, $options) {
        $status = array('error' => array(), 'debug' => array());

        if(!empty($userinfo->password_clear)){
            //set login cookie
            require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'blowfish.php';
            $cookie_path = $this->params->get('cookie_path', '/');
            $cookie_domain = $this->params->get('cookie_domain');
            $cookie_secure = $this->params->get('secure', false);
            $httponly = $this->params->get('httponly', true);

            //setup Dokuwiki constants
	        $this->helper->defineConstants();
            $salt = $this->helper->getCookieSalt();
            $pass = JFusion_PMA_blowfish_encrypt($userinfo->password_clear, $salt);
//            $sticky = (!empty($options['remember'])) ? 1 : 0;
            $sticky = 1;
        	$version = $this->helper->getVersion();
			if ( version_compare($version, '2009-12-02 "Mulled Wine"') >= 0) {
				$cookie_value = base64_encode($userinfo->username) . '|' . $sticky . '|' . base64_encode($pass);
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
     * @param Userinfo $userinfo
     * @param array &$status
     *
     * @return void
     */
    function createUser(Userinfo $userinfo, &$status) {
	    try {
		    $usergroups = $this->getCorrectUserGroups($userinfo);
		    if (empty($usergroups)) {
			    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
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
			    $status['debug'][] = Text::_('USER_CREATION');
			    $status['userinfo'] = $this->getUser($userinfo);
		    }
	    } catch (Exception $e) {
		    $status['error'][] = Text::_('USER_CREATION_ERROR') . ': ' . $e->getMessage();
	    }
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     * @param array $status
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser, &$status)
    {
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     * @param array $status
     *
     * @return void
     */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser, &$status)
    {
    }

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     * @param array $status
     *
     * @return void
     */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser, &$status)
    {
    }
}
