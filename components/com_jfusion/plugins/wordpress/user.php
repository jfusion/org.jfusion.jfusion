<?php

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
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';

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


if (!class_exists('JFusionWordpressHelper')) {
	require_once 'wordpresshelper.php';
}


class JFusionUser_wordpress extends JFusionUser {

	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()	{
		return 'wordpress';
	}

	function &getUser($userinfo) {
		//get the identifier
		list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'user_login', 'user_email');
		// Get a database object
		$db = JFusionFactory::getDatabase($this->getJname());
		//make the username case insensitive
		if ($identifier_type == 'user_login') {
			$identifier = $this->filterUsername($identifier);
		}
		//    $query = 'SELECT ID as userid, user_login as username, user_email as email, user_pass as password, null as password_salt, user_activation_key as activation, user_status as status FROM #__users WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);

		// internal note: working toward the JFusion 2.0 plugin system, we read all available userdata into the user object
		// conversion to the JFusion userobject will be done at the end for JFusion 1.x
		// we add an localuser field to keep the original data
		// will be further developed for 2.0 allowing centralized registration

		$query = 'SELECT * FROM #__users WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);
		$db->setQuery($query);
		$result = $db->loadObject();
		if ($result) {
			// get the meta userdata
			$query = 'SELECT * FROM #__usermeta WHERE user_id = ' . $db->Quote($result->ID);
			$db->setQuery($query);
			$result1 = $db->loadObjectList();
			if ($result1) {
				foreach ($result1 as $metarecord) {
					$result->{$metarecord->meta_key} = $metarecord->meta_value;
				}
			}
			$jFusionUserObject = $this->convertUserobjectToJFusion($result);
			$jFusionUserObject->{$this->getJname().'_UserObject'}=$result;
			return $jFusionUserObject;
		} else {
			return $result;
		}
	}


	/*
	 * Routine to convert userobject to standardized JFusion version
	*/

	function convertUserobjectToJFusion($user) {
		$result = new stdClass;

		$result->userid       = $user->ID;
		// have to figure out what to use a s the name. Guess display name will do.
		//     $result->name         = $user->first_name;
		//     if (user->last_name) { $result->name .= $user_last_name;}
		$result->name         = $user->display_name;
		$result->username     = $user->user_login;
		$result->email        = $user->user_email;
		$result->password     = $user->user_pass;
		$result->password_salt= null;

		// usergroup (actually role) is in a serialized field of the user metadata table
		// unserialize. Gives an array with capabilities
		$capabilities = unserialize($user->wp_capabilities);
		// make sure we only have activated capabilities
		$x = array_keys($capabilities,"1");
		// get the values to test
		$y = array_values($x);
		// now find out what we have
		$groupid=4; // default to subscriber
		$groupname='subscriber';
		$groups = JFusionWordpressHelper::getUsergroupListWP();
		// find the most capable one
		foreach ($y as $cap){
			foreach ($groups as $group) {
				if(strtolower($group->name)== strtolower($cap)){
					$groupid = $group->id;
					$groupname = $cap;
				}
			}
		}
		// fill the userobject
		$result->group_id          = $groupid;
		$result->group_name        = $groupname;
		$result->registerdate      = $user->user_registered;
		$result->activation        = $user->user_activation_key;
		$result->block             = 0;

		// todo get to find out where user status stands for. As far as I can see we have also two additioonal fields
		// in a multisite, one of the spam. This maybe linked to block.

		return $result;
	}


	function destroySession($userinfo, $options) {

		$status = array();
		$status['error'] = array();
		$status['debug'] =array();
		$params = & JFusionFactory::getParams($this->getJname());
		$cookie_domain = $params->get('cookie_domain');
		$cookie_path = $params->get('cookie_path');
		$cookie_hash = $params->get('cookie_hash');
		$forumPath =   $params->get('source_path');

		if (substr($forumPath, -1) == DS) {
			$myfile = $forumPath . 'wp-config.php';
		} else {
			$myfile = $forumPath . DS . 'wp-config.php';
		}

		if (($file_handle = @fopen($myfile, 'r')) === false) {
			JError::raiseWarning(500, JText::_('WIZARD_FAILURE') . ": $myfile " . JText::_('WIZARD_MANUAL'));
			$result = false;
			return $result;
		} else {
			//parse the file line by line to get only the config variables
			//			$file_handle = fopen($myfile, 'r');
			while (!feof($file_handle)) {
				$line = fgets($file_handle);
				if (strpos(trim($line), 'define') === 0) {
					eval($line);
				}
				if (strpos(trim($line), '$table_prefix') === 0) {
					eval($line);
				}
			}
			fclose($file_handle);
		}
		// lets try deleting the cookies
		if (defined('COOKIEHASH')) {
			$cookie_hash = COOKIEHASH;
		} else {$cookie_hash = '';
		}
		if ($cookie_hash) {
			$cookie_hash = '_'.$cookie_hash;
		}
		$cookies = array();
		$cookies[0][0] ='wordpress_logged_in'.$cookie_hash.'=';
		$cookies[1][0] ='wordpress'.$cookie_hash.'=';

		$status = JFusionCurl::deletemycookies($status, $cookies, $cookie_domain, $cookie_path, "");

		$cookies = array();
		$cookies[1][0] ='wordpress'.$cookie_hash.'=';

		$cookie_path .= 'wp-content/plugins';
		$status = JFusionCurl::deletemycookies($status, $cookies, $cookie_domain, $cookie_path, "");

		return $status;
	}

	function createSession($userinfo, $options) {
		$params = JFusionFactory::getParams($this->getJname());
		return JFusionJplugin::createSession($userinfo, $options, $this->getJname(),$params->get('brute_force'));
	}

	function filterUsername($username) {
		// strip all tags
		$username = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $username );
		$username = strip_tags($username);
		$username = preg_replace('/[\r\n\t ]+/', ' ', $username);
		$username = trim($username);
		// remove accents
		$username = JFusionWordpressHelper::remove_accentsWP( $username );
		// Kill octets
		$username = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '', $username );
		$username = preg_replace( '/&.+?;/', '', $username ); // Kill entities

		// If strict, reduce to ASCII for max portability.
		$strict = true; // default behaviour of WP 3, can be moved to params if we need i to be choice
		if ( $strict ){
			$username = preg_replace( '|[^a-z0-9 _.\-@]|i', '', $username );
		}
		// Consolidate contiguous whitespace
		$username = preg_replace( '|\s+|', ' ', $username );
		return $username;
	}

	function updatePassword($userinfo, $existinguser, &$status) {
		// get the encryption PHP file
		if (!class_exists('PasswordHashOrg')) {
			require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'PasswordHashOrg.php';
		}
		$t_hasher = new PasswordHashOrg(8, true);
		$existinguser->password = $t_hasher->HashPassword($userinfo->password_clear);
		unset($t_hasher);
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'UPDATE #__users SET user_pass =' . $db->Quote($existinguser->password) . ' WHERE ID =' . (int)$existinguser->userid;
		$db->setQuery($query);
		if (!$db->query()) {
			$status['error'][] = JText::_('PASSWORD_UPDATE_ERROR') . $db->stderr();
		} else {
			$status['debug'][] = JText::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********';
		}
	}

	function updateUsername($userinfo, &$existinguser, &$status) {
		// not implemented in jFusion 1.x
	}

	function updateEmail($userinfo, &$existinguser, &$status) {
		//we need to update the email
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'UPDATE #__users SET user_email =' . $db->Quote($userinfo->email) . ' WHERE ID =' . (int)$existinguser->userid;
		$db->setQuery($query);
		if (!$db->query()) {
			$status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
		} else {
			$status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
		}
	}

	function blockUser($userinfo, &$existinguser, &$status) {
		// not supported for Wordpress
		$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . ': Blocking not supported by Wordpress';
	}

	function unblockUser($userinfo, &$existinguser, &$status) {
	}

	function activateUser($userinfo, &$existinguser, &$status) {
		//activate the user
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'UPDATE #__users SET user_activation_key = \'\'  WHERE ID =' . (int)$existinguser->userid;
		$db->setQuery($query);
		if (!$db->query()) {
			$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
		} else {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	function inactivateUser($userinfo, &$existinguser, &$status) {
		//set activation key
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'UPDATE #__users SET user_activation_key =' . $db->Quote($userinfo->activation) . ' WHERE ID =' . (int)$existinguser->userid;
		$db->setQuery($query);
		if (!$db->query()) {
			$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
		} else {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}

	function createUser($userinfo, &$status) {
		//find out what usergroup should be used
		$db = JFusionFactory::getDatabase($this->getJname());
		$params = JFusionFactory::getParams($this->getJname());
		$usergroups = (substr($params->get('usergroup'), 0, 2) == 'a:') ? unserialize($params->get('usergroup')) : $params->get('usergroup', 18);
		//check to make sure that if using the advanced group mode, $userinfo->group_id exists
		if (is_array($usergroups) && !isset($userinfo->group_id)) {
			$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID');
			return null;
		}
		$update_activation = $params->get('update_activation');
		$default_role_id = (is_array($usergroups)) ? $usergroups[$userinfo->group_id] : $usergroups;
		$default_role_name = strtolower(JFusionWordpressHelper::getUsergroupNameWP($default_role_id));
		$default_role = array();
		$default_role[$default_role_name]=1;
		$default_userlevel = JFusionWordpressHelper::WP_userlevel_from_role(0,$default_role_name);
		$username_clean = $this->filterUsername($userinfo->username);
		if (isset($userinfo->password_clear)) {
			//we can update the password
			if (!class_exists('PasswordHashOrg')) {
				require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'PasswordHashOrg.php';
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
		$user->ID                 = null;
		$user->user_login         = $userinfo->username;
		$user->user_pass          = $user_password;
		$user->user_nicename      = strtolower($userinfo->username);
		$user->user_email         = strtolower($userinfo->email);
		$user->user_url           = '';
		$user->user_registered    = date('Y-m-d H:i:s', time()); // seems WP has a switch to use GMT. Could not find that
		$user->user_activation_key= $user_activation_key;
		$user->user_status        = 0;
		$user->display_name       = $userinfo->username;
		//now append the new user data
		if (!$db->insertObject('#__users', $user, 'ID')) {
			//return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
			return;
		}
		// get new ID
		$user_id = $db->insertid();


		// have to set user metadata
		$metadata=array();

		$parts = explode(' ', $userinfo->name);
		$metadata['first_name'] = trim($parts[0]);
		if ($parts[(count($parts) - 1) ]) {
			for ($i = 1;$i < (count($parts));$i++) {
				$metadata['last_name'] = trim($metadata['last_name'] . ' ' . $parts[$i]);
			}
		}


		$metadata['nickname']         = $userinfo->username;
		$metadata['description']      = '';
		$metadata['rich_editing']     = 'true';
		$metadata['comment_shortcuts']= 'false';
		$metadata['admin_color']      = 'fresh';
		$metadata['use_ssl']          = '0';
		$metadata['aim']              = '';
		$metadata['yim']              = '';
		$metadata['jabber']           = '';
		$metadata['wp_capabilities']  = serialize($default_role);
		$metadata['wp_user_level']    = sprintf('%u',$default_userlevel);
		//		$metadata['default_password_nag'] = '0'; //no nag! can be ommitted

		$meta = new stdClass;
		$meta->umeta_id = null;
		$meta->user_id = $user_id;

		$keys=array_keys($metadata);
		foreach($keys as $key){
			$meta->meta_key = $key;
			$meta->meta_value = $metadata[$key];
			$meta->umeta_id = null;
			if (!$db->insertObject('#__usermeta', $meta, 'umeta_id')) {
				//return the error
				$status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
				return;
			}
		}
		//return the good news
		$status['userinfo'] = $this->getUser($userinfo);
		$status['debug'][] = JText::_('USER_CREATION');
	}

	function deleteUser($userinfo) {
		//setup status array to hold debug info and errors
		$status = array();
		$status['debug'] = array();
		$status['error'] = array();
		if (!is_object($userinfo)) {
			$status['error'][] = JText::_('NO_USER_DATA_FOUND');
			return $status;
		}
		$db = JFusionFactory::getDatabase($this->getJname());
		$params = JFusionFactory::getParams($this->getJname());
		$reassign = $params->get('reassign_blogs');
		$reassign_to=$params->get('reassign_username');
		$user_id=$userinfo->userid;

		// decide if we need to reassign
		if (($reassign == '1') && (trim($reassign_to))){
			// see if we have a valid user
			$query = 'SELECT * FROM #__users WHERE user_login = ' . $db->Quote($reassign_to);
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
			$query = "SELECT ID FROM #__posts WHERE post_author = ".$user_id;
			$db->setQuery($query);
			if ($db->query()) {
                $results = $db->loadObjectList();
				if ($results) {
					foreach ($results as $row) {
						$query = "UPDATE #__posts SET post_author = ".$reassign. " WHERE ID = ". $row->ID;
						$db->setQuery($query);
						if (!$db->query()) {
							$status['error'][] = "Error Could not reassign posts by user $user_id: {$db->stderr() }";
							break;
						}
					}
					$status["debug"][] = "Reassigned posts from user with id $user_id to user $reassign.";
				} elseif ($db->getErrorNum() != 0) {
					$status['error'][] = "Error Could not retrieve posts by user $user_id: {$db->stderr() }";
				}
				$query = "SELECT link_id FROM #__links WHERE link_owner = ".$user_id;
				$db->setQuery($query);
				if ($db->query()) {
                    $results = $db->loadObjectList();
					if ($results) {
						foreach ($results as $row) {
							$query = "UPDATE #__links SET link_owner = ".$reassign. " WHERE link_id = ". $row->link_id;
							$db->setQuery($query);
							if (!$db->query()) {
								$status['error'][] = "Error Could not reassign links by user $user_id: {$db->stderr() }";
								break;
							}
						}
						$status["debug"][] = "Reassigned links from user with id $user_id to user $reassign.";
					} elseif ($db->getErrorNum() != 0) {
						$status['error'][] = "Error Could not retrieve links by user $user_id: {$db->stderr() }";
					}
				}
			}
		} else {
			$query = 'DELETE FROM #__posts WHERE post_author = ' . $user_id;
			$db->setQuery($query);
			if (!$db->query()) {
				$status['error'][] = "Error Could not delete posts by user $user_id: {$db->stderr() }";
			} else {
				$status['debug'][] = "Deleted posts from user with id $user_id.";
			}
			$query = 'DELETE FROM #__links WHERE link_owner = ' . $user_id;
			$db->setQuery($query);
			if (!$db->query()) {
				$status['error'][] = "Error Could not delete links by user $user_id: {$db->stderr() }";
			} else {
				$status['debug'][] = "Deleted links from user $user_id";
			}
		}
		// now delete the user
		$query = 'DELETE FROM #__users WHERE ID = ' . $user_id;
		$db->setQuery($query);
		if(!$db->query()){
			$status['error'][] = "Error Could not delete userrecord with userid $user_id: {$db->stderr()}";
		} else {
			$status["debug"][] = "Deleted userrecord of user with userid $user_id.";
		}
		return $status;
	}

	function updateUsergroup($userinfo, &$existinguser, &$status) {
		//check to see if we have a group_id in the $userinfo, if not return
		if (!isset($userinfo->group_id)) {
			$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID');
			return null;
		}
		$params = & JFusionFactory::getParams($this->getJname());
		$paramUsergroups = unserialize($params->get('usergroup'));
		if (isset($paramUsergroups[$userinfo->group_id])) {
			$db = JFusionFactory::getDatabase($this->getJname());
			$newgroup = $paramUsergroups[$userinfo->group_id];
			$newgroupname = strtolower(JFusionWordpressHelper::getUsergroupNameWP($newgroup));
			$oldgroupname = strtolower(JFusionWordpressHelper::getUsergroupNameWP($existinguser->group_id));

			// get the user capabilities
			$db = JFusionFactory::getDatabase($this->getJname());
			$query = "SELECT meta_value FROM #__usermeta WHERE meta_key = 'wp_capabilities' AND user_id = " . (int)($existinguser->userid);
			$db->setQuery($query);
			$capsfield = $db->loadResult();
			$caps = array();
			if ($capsfield) {
				$caps = unserialize($capsfield);
				// make it all lowercase keys
				$caps = array_change_key_case($caps,CASE_LOWER);
				// now delete the old group
				if (array_key_exists($oldgroupname,$caps)){
					unset($caps[$oldgroupname]);
				}
			}
			// ad the new group
			$caps[$newgroupname]="1";
			$capsfield = serialize($caps);
			$query = "UPDATE #__usermeta SET meta_value =" . $db->Quote($capsfield) . " WHERE meta_key = 'wp_capabilities' AND user_id =" . (int)$existinguser->userid;
			$db->setQuery($query);
			if (!$db->query()) {
				$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . $db->stderr();
			} else {
				$status['debug'][] = JText::_('GROUP_UPDATE'). ': ' . $existinguser->group_id . ' -> ' . $paramUsergroups[$userinfo->group_id];
			}
		} else {
			$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ' ' . JText::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST');
		}
	}

}