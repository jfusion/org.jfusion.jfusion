<?php namespace JFusion\Plugins\wordpress;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team -- Henk Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use Exception;
use JFusion\Curl\Curl;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use JFusion\Plugin\Plugin_User;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion User Class for Wordpress 3+
 * For detailed descriptions on these functions please check the model.abstractuser.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team -- Hek Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org */


/**
 *
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
		    list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'user_login', 'user_email', 'ID');
		    // Get a database object
		    $db = Factory::getDatabase($this->getJname());
		    //make the username case insensitive
		    if ($identifier_type == 'username') {
			    $identifier = $this->filterUsername($identifier);
		    }
		    // internal note: working toward the JFusion 2.0 plugin system, we read all available userdata into the user object
		    // conversion to the JFusion user object will be done at the end for JFusion 1.x
		    // we add an local user field to keep the original data
		    // will be further developed for 2.0 allowing centralized registration

		    $query = $db->getQuery(true)
			    ->select('*, ID as userid, display_name as name, user_login as username, user_email as email, user_pass as password, user_registered as registerDate, user_activation_key as activation')
			    ->from('#__users')
			    ->where($identifier_type . ' = ' . $db->quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();
		    if ($result) {
			    // get the meta userdata
			    $query = $db->getQuery(true)
				    ->select('*')
				    ->from('#__usermeta')
				    ->where('user_id = ' . $db->quote($result->userid));

			    $db->setQuery($query);
			    $usermeta = $db->loadObjectList();
			    if ($usermeta) {
				    foreach ($usermeta as $metarecord) {
					    $result->{$metarecord->meta_key} = $metarecord->meta_value;
				    }
			    }
			    $jFusionUserObject = $this->convertUserobjectToJFusion($result);
			    $jFusionUserObject->{$this->getJname() . '_UserObject'} = $result;
			    $result = $jFusionUserObject;

			    $user = new Userinfo($this->getJname());
			    $user->bind($result);
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
	    return $user;
	}

    /**
     * Routine to convert userobject to standardized JFusion version
     *
     * @param $user
     *
     * @return stdClass
     */
    function convertUserobjectToJFusion($user) {
		$result = new stdClass;
		// have to figure out what to use a s the name. Guess display name will do.
		//     $result->name         = $user->first_name;
		//     if (user->last_name) { $result->name .= $user_last_name;}
		$result->password_salt = null;

		// usergroup (actually role) is in a serialized field of the user metadata table
		// unserialize. Gives an array with capabilities

	    $database_prefix = $this->params->get('database_prefix');
		$capabilities = $database_prefix . 'capabilities';
		$capabilities = unserialize($user->$capabilities);
		// make sure we only have activated capabilities
		$x = array_keys($capabilities, '1');
		// get the values to test
		$y = array_values($x);
		// now find out what we have
		$groupid = 4; // default to subscriber
		$groupname = 'subscriber';

		$groups = $this->helper->getUsergroupListWP();

        $result->groups = array();
        $result->groupnames = array();
		// find the most capable one
		foreach ($y as $cap) {
			foreach ($groups as $group) {
				if(strtolower($group->name)== strtolower($cap)){
					$groupid = $group->id;
					$groupname = $cap;

                    $result->groups[] = $groupid;
                    $result->groupnames[] = $groupname;
				}
			}
		}
        if (empty($result->groups)) {
            $result->groups[] = $groupid;
            $result->groupnames[] = $groupname;
        }
		// fill the userobject
		$result->group_id          = $groupid;
		$result->group_name        = $groupname;
		$result->block             = false;

		// todo get to find out where user status stands for. As far as I can see we have also two additional fields
		// in a multi site, one of the spam. This maybe linked to block.
		return $result;
	}

    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array|bool|string
     */
    function destroySession(Userinfo $userinfo, $options) {
	    require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';

	    $status = array(LogLevel::ERROR => array(), LogLevel::DEBUG => array());
		$wpnonce = array();

		$logout_url = $this->params->get('logout_url');

		$curl_options['post_url'] = $this->params->get('source_url') . $logout_url;
		$curl_options['cookiedomain'] = $this->params->get('cookie_domain');
		$curl_options['cookiepath'] = $this->params->get('cookie_path');
		$curl_options['leavealone'] = $this->params->get('leavealone');
		$curl_options['secure'] = $this->params->get('secure');
		$curl_options['httponly'] = $this->params->get('httponly');
		$curl_options['verifyhost'] = 0; //$this->params->get('ssl_verifyhost');
		$curl_options['httpauth'] = $this->params->get('httpauth');
		$curl_options['httpauth_username'] = $this->params->get('curl_username');
		$curl_options['httpauth_password'] = $this->params->get('curl_password');
		$curl_options['integrationtype'] = 0;
		$curl_options['debug'] = 0;

		// to prevent endless loops on systems where there are multiple places where a user can login
		// we post an unique ID for the initiating software so we can make a difference between
		// a user logging out or another jFusion installation, or even another system with reverse dual login code.
		// We always use the source url of the initializing system, here the source_url as defined in the joomla_int
		// plugin. This is totally transparent for the the webmaster. No additional setup is needed

	    /**
	     * TODO: CHANGE THIS SO THAT WE HAVE A WAY TO RETRIEVE "SYSTEM" Jnodeid from framework
	     * $my_ID = rtrim(parse_url(JUri::root(), PHP_URL_HOST) . parse_url(Uri::root(), PHP_URL_PATH), '/');
	     * $curl_options['jnodeid'] = $my_ID;
	     */

		$curl = new Curl($curl_options);
		
		$remotedata = $curl->ReadPage();
		if (!empty($curl->status['error'])) {
			$curl->status[LogLevel::DEBUG][] = Text::_('CURL_COULD_NOT_READ_PAGE: ') . $curl->options['post_url'];
		} else {
	        // get _wpnonce security value
	        preg_match('/action=logout.+?_wpnonce=([\w\s-]*)["\']/i', $remotedata, $wpnonce);
	        if (!empty($wpnonce[1])) {
				$curl_options['post_url'] = $curl_options['post_url'] . '?action=logout&_wpnonce=' . $wpnonce[1];
				$status = $this->curlLogout($userinfo, $options, $this->params->get('logout_type'), $curl_options);
	        } else {
	          // non wpnonce, we are probably not on the logout page. Just report
	          $status[LogLevel::DEBUG][] = Text::_('NO_WPNONCE_FOUND: ');

	          //try to delete all cookies
	          $cookie_name = $this->params->get('cookie_name');
	          $cookie_domain = $this->params->get('cookie_domain');
	          $cookie_path = $this->params->get('cookie_path');
	          $cookie_hash = $this->params->get('cookie_hash');

	          $cookies = array();
	          $cookies[0][0] = 'wordpress_logged_in' . $cookie_name . '=';
	          $cookies[1][0] = 'wordpress' . $cookie_name . '=';
	          $status = $curl->deletemycookies($status, $cookies, $cookie_domain, $cookie_path, '');

	          $cookies = array();
	          $cookies[1][0] = 'wordpress' . $cookie_name . '=';

	          $path = $cookie_path . 'wp-content/plugins';
	          $status = $curl->deletemycookies($status, $cookies, $cookie_domain, $path, '');

	          $path = $cookie_path . 'wp-admin';
	          $status = $curl->deletemycookies($status, $cookies, $cookie_domain, $path, '');
	        }
	    }
		return $status;
	}


    /**
     * @param Userinfo $userinfo
     * @param array $options
     *
     * @return array|string
     */
    function createSession(Userinfo $userinfo, $options) {
        $status = array('error' => array(), 'debug' => array());
        //do not create sessions for blocked users
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = Text::_('FUSION_BLOCKED_USER');
        } else {
            $status = $this->curlLogin($userinfo, $options, $this->params->get('brute_force'));
        }
		return $status;
	}

    /**
     * @param string $username
     *
     * @return mixed|string
     */
    function filterUsername($username) {
		// strip all tags
		$username = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $username);
		$username = strip_tags($username);
		$username = preg_replace('/[\r\n\t ]+/', ' ', $username);
		$username = trim($username);
		// remove accents
		$username = $this->helper->remove_accentsWP($username);
		// Kill octets
		$username = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '', $username);
		$username = preg_replace('/&.+?;/', '', $username); // Kill entities

		// If strict, reduce to ASCII for max portability.
		$strict = true; // default behaviour of WP 3, can be moved to params if we need i to be choice
		if ($strict) {
			$username = preg_replace('|[^a-z0-9 _.\-@]|i', '', $username);
		}
		// Consolidate contiguous whitespace
		$username = preg_replace('|\s+|', ' ', $username);
		return $username;
	}

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser) {
	    // get the encryption PHP file
	    if (!class_exists('PasswordHashOrg')) {
		    require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'PasswordHashOrg.php';
	    }
	    $t_hasher = new PasswordHashOrg(8, true);
	    $existinguser->password = $t_hasher->HashPassword($userinfo->password_clear);
	    unset($t_hasher);
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__users')
		    ->set('user_pass = ' . $db->quote($existinguser->password))
		    ->where('ID = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
	}

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function updateUsername(Userinfo $userinfo, Userinfo &$existinguser) {
		// not implemented in jFusion 1.x
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
		    ->update('#__users')
		    ->set('user_email = ' . $db->quote($userinfo->email))
		    ->where('ID = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
	}

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @throws RuntimeException
	 * @return void
	 */
    function blockUser(Userinfo $userinfo, Userinfo &$existinguser) {
		// not supported for Wordpress
	    throw new RuntimeException('Blocking not supported by Wordpress');
	}

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function unblockUser(Userinfo $userinfo, Userinfo &$existinguser) {
	}

    /**
     * @param Userinfo $userinfo
     * @param Userinfo $existinguser
     *
     * @return void
     */
    function activateUser(Userinfo $userinfo, Userinfo &$existinguser) {
	    //activate the user
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__users')
		    ->set('user_activation_key = ' . $db->quote(''))
		    ->where('ID = ' . (int)$existinguser->userid);

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
	    //set activation key
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__users')
		    ->set('user_activation_key = ' . $db->quote($userinfo->activation))
		    ->where('ID = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	}

	/**
	 * @param Userinfo $userinfo
	 *
	 * @throws \RuntimeException
	 * @return void
	 */
    function createUser(Userinfo $userinfo) {
	    //find out what usergroup should be used
	    $db = Factory::getDatabase($this->getJname());
	    $usergroups = $this->getCorrectUserGroups($userinfo);
	    if (empty($usergroups)) {
		    throw new RuntimeException(Text::_('USERGROUP_MISSING'));
	    } else {
		    $update_activation = $this->params->get('update_activation');
		    $default_role_id = $usergroups[0];
		    $default_role_name = strtolower($this->helper->getUsergroupNameWP($default_role_id));
		    $default_role = array();
		    $default_role[$default_role_name] = 1;

		    $default_userlevel = $this->helper->WP_userlevel_from_role(0, $default_role_name);
		    if (isset($userinfo->password_clear)) {
			    //we can update the password
			    if (!class_exists('PasswordHashOrg')) {
				    require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'PasswordHashOrg.php';
			    }
			    $t_hasher = new PasswordHashOrg(8, true);
			    $user_password = $t_hasher->HashPassword($userinfo->password_clear);
			    unset($t_hasher);
		    } else {
			    $user_password = $userinfo->password;
		    }
		    if (!empty($userinfo->activation) && $update_activation) {
			    $user_activation_key = $userinfo->activation;
		    } else {
			    $user_activation_key = '';
		    }

		    //prepare the variables
		    $user = new stdClass;
		    $user->ID = null;
		    $user->user_login = $this->filterUsername($userinfo->username);
		    $user->user_pass = $user_password;
		    $user->user_nicename = strtolower($userinfo->username);
		    $user->user_email = strtolower($userinfo->email);
		    $user->user_url = '';
		    $user->user_registered = date('Y-m-d H:i:s', time()); // seems WP has a switch to use GMT. Could not find that
		    $user->user_activation_key = $user_activation_key;
		    $user->user_status = 0;
		    $user->display_name = $userinfo->username;
		    //now append the new user data
		    $db->insertObject('#__users', $user, 'ID');

		    // get new ID
		    $user_id = $db->insertid();

		    // have to set user metadata
		    $metadata = array();

		    $parts = explode(' ', $userinfo->name);
		    $metadata['first_name'] = trim($parts[0]);
		    if ($parts[(count($parts) - 1) ]) {
			    for ($i = 1;$i < (count($parts));$i++) {
				    if (isset($metadata['last_name'])) {
					    $metadata['last_name'] .= ' ' . trim($parts[$i]);
				    } else {
					    $metadata['last_name'] = trim($parts[$i]);
				    }
			    }
		    }

		    $database_prefix = $this->params->get('database_prefix');

		    $metadata['nickname'] = $userinfo->username;
		    $metadata['description'] = '';
		    $metadata['rich_editing'] = 'true';
		    $metadata['comment_shortcuts'] = 'false';
		    $metadata['admin_color'] = 'fresh';
		    $metadata['use_ssl'] = '0';
		    $metadata['aim'] = '';
		    $metadata['yim'] = '';
		    $metadata['jabber'] = '';
		    $metadata[$database_prefix . 'capabilities'] = serialize($default_role);
		    $metadata[$database_prefix . 'user_level'] = sprintf('%u', $default_userlevel);
		    //		$metadata['default_password_nag'] = '0'; //no nag! can be omitted

		    $meta = new stdClass;
		    $meta->umeta_id = null;
		    $meta->user_id = $user_id;

		    $keys=array_keys($metadata);
		    foreach($keys as $key){
			    $meta->meta_key = $key;
			    $meta->meta_value = $metadata[$key];
			    $meta->umeta_id = null;
			    $db->insertObject('#__usermeta', $meta, 'umeta_id');
		    }
		    //return the good news
		    $this->debugger->addDebug(Text::_('USER_CREATION'));
		    $this->debugger->set('userinfo', $this->getUser($userinfo));
	    }
	}

    /**
     * @param Userinfo $userinfo
     *
     * @return array
     */
    function deleteUser(Userinfo $userinfo) {
		//setup status array to hold debug info and errors
        $status = array('error' => array(), 'debug' => array());

	    $db = Factory::getDatabase($this->getJname());
	    $reassign = $this->params->get('reassign_blogs');
	    $reassign_to = $this->params->get('reassign_username');
	    $user_id = $userinfo->userid;

	    // decide if we need to reassign
	    if (($reassign == '1') && (trim($reassign_to))){
		    // see if we have a valid user
		    $query = $db->getQuery(true)
			    ->select('*')
			    ->from('#__users')
			    ->where('user_login = ' . $db->quote($reassign_to));

		    $db->setQuery($query);
		    $result = $db->loadObject();
		    if (!$result) {
			    $reassign = '';
		    } else {
			    $reassign = $result->ID;
		    }
	    } else {
		    $reassign = '';
	    }

	    // handle posts and links
	    if ($reassign){
		    $query = $db->getQuery(true)
			    ->select('ID')
			    ->from('#__posts')
			    ->where('post_author = ' . $user_id);

		    $db->setQuery($query);
		    if ($db->execute()) {
			    $results = $db->loadObjectList();
			    if ($results) {
				    foreach ($results as $row) {
					    $query = $db->getQuery(true)
						    ->update('#__posts')
						    ->set('post_author = ' . $reassign)
						    ->where('ID = ' . (int)$row->ID);

					    $db->setQuery($query);
					    $db->execute();
				    }
				    $status[LogLevel::DEBUG][] = 'Reassigned posts from user with id ' . $user_id . ' to user ' . $reassign;
			    }

			    $query = $db->getQuery(true)
				    ->select('link_id')
				    ->from('#__links')
				    ->where('link_owner = ' . $user_id);

			    $db->setQuery($query);
			    if ($db->execute()) {
				    $results = $db->loadObjectList();
				    if ($results) {
					    foreach ($results as $row) {
						    $query = $db->getQuery(true)
							    ->update('#__links')
							    ->set('link_owner = ' . $reassign)
							    ->where('link_id = ' . $row->link_id);

						    $db->setQuery($query);
						    $db->execute();
					    }
					    $status[LogLevel::DEBUG][] = 'Reassigned links from user with id ' . $user_id . ' to user ' . $reassign;
				    }
			    }
		    }
	    } else {
		    $query = $db->getQuery(true)
			    ->delete('#__posts')
			    ->where('post_author = ' . $user_id);

		    $db->setQuery($query);
		    $db->execute();
		    $status[LogLevel::DEBUG][] = 'Deleted posts from user with id ' . $user_id;

		    $query = $db->getQuery(true)
			    ->delete('#__links')
			    ->where('link_owner = ' . $user_id);

		    $db->setQuery($query);
		    $db->execute();
		    $status[LogLevel::DEBUG][] = 'Deleted links from user ' . $user_id;
	    }
	    // now delete the user
	    $query = $db->getQuery(true)
		    ->delete('#__users')
		    ->where('ID = ' . $user_id);

	    $db->setQuery($query);
	    $db->execute();
	    $status[LogLevel::DEBUG][] = 'Deleted userrecord of user with userid ' . $user_id;

	    // delete usermeta
	    $query = $db->getQuery(true)
		    ->delete('#__usermeta')
		    ->where('user_id = ' . $user_id);

	    $db->setQuery($query);
	    $db->execute();
	    $status[LogLevel::DEBUG][] = 'Deleted usermetarecord of user with userid ' . $user_id;

		return $status;
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

			$database_prefix = $this->params->get('database_prefix');

			$caps = array();
			foreach($usergroups as $usergroup) {
				$newgroupname = strtolower($this->helper->getUsergroupNameWP($usergroup));
				$caps[$newgroupname] = '1';
			}

			$capsfield = serialize($caps);

			$query = $db->getQuery(true)
				->update('#__usermeta')
				->set('meta_value = ' . $db->quote($capsfield))
				->where('meta_key = ' . $db->quote($database_prefix . 'capabilities'))
				->where('user_id = ' . (int)$existinguser->userid);

			$db->setQuery($query);
			$db->execute();

			$this->debugger->addDebug(Text::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinguser->groups) . ' -> ' . implode(' , ', $usergroups));
		}
	}
}