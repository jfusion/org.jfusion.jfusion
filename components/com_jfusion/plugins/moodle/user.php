<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Moodle
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

/** NOTE 1
 * We can map the sitepolicy system on the block field. The sitepolicy system in Moodle works as follows:
 * If, in the moodle table "config" the record "sitepolicy" is not empty but contains an URL to a page
 * The field "policyagreed" in the usertable is activated and should contain a 1 if policy is agreed
 * With moodle as master this can be used to block a user to an integration as long as policy is not agreed
 * If you use Moodle as slave, You should use the policy agreed page in Moodle to contain an explanation why
 * the user is blocked.
 * LATER
 * We are probably better off using the delete field in the userrecord. This way we block the user and can undo this
 * without the need to use the site policy
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';

/**
 * JFusion User Class for Moodle 1.8+
 * For detailed descriptions on these functions please check the model.abstractuser.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Moodle
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org */
class JFusionUser_moodle extends JFusionUser {
	function rc4encrypt($data) {
		$password = 'nfgjeingjk';
		return endecrypt($password, $data, '');
	}

	/**
	 * rc4decrypt
	 *
	 * @param string $data Data to decrypt
	 * @return string The now decrypted data
	 */
	function rc4decrypt($data) {
		$password = 'nfgjeingjk';
		return $this->endecrypt($password, $data, 'de');
	}

	/**
	 * Based on a class by Mukul Sabharwal [mukulsabharwal @ yahoo.com]
	 *
	 * @param string $pwd The password to use when encrypting or decrypting
	 * @param string $data The data to be decrypted/encrypted
	 * @param string $case Either 'de' for decrypt or '' for encrypt
	 * @return string
	 */
	function endecrypt ($pwd, $data, $case) {
		if ($case == 'de') {
			$data = urldecode($data);
		}

		$key[] = '';
		$box[] = '';
		$temp_swap = '';
		$pwd_length = 0;
		$pwd_length = strlen($pwd);

		for ($i = 0; $i <= 255; $i++) {
			$key[$i] = ord(substr($pwd, ($i % $pwd_length), 1));
			$box[$i] = $i;
		}

		$x = 0;
		for ($i = 0; $i <= 255; $i++) {
			$x = ($x + $box[$i] + $key[$i]) % 256;
			$temp_swap = $box[$i];
			$box[$i] = $box[$x];
			$box[$x] = $temp_swap;
		}

		$temp = '';
		$k = '';
		$cipherby = '';
		$cipher = '';
		$a = 0;
		$j = 0;

		for ($i = 0; $i < strlen($data); $i++) {
			$a = ($a + 1) % 256;
			$j = ($j + $box[$a]) % 256;
			$temp = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $temp;
			$k = $box[(($box[$a] + $box[$j]) % 256)];
			$cipherby = ord(substr($data, $i, 1)) ^ $k;
			$cipher .= chr($cipherby);
		}

		if ($case == 'de') {
			$cipher = urldecode(urlencode($cipher));
		} else {
			$cipher = urlencode($cipher);
		}
		return $cipher;
	}

	function &getUser($userinfo) {
		$db = JFusionFactory::getDatabase($this->getJname());
		$params = JFusionFactory::getParams($this->getJname());
		//get the identifier
		list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'username', 'email');
		//initialise some params
		$update_block = $params->get('update_block');
		$query = 'SELECT * FROM #__user WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);
		$db->setQuery($query);
		$result = $db->loadObject();
		if ($result)
		{
			// check the deleted flag
			if ($result->deleted){
				$result = null;
				return $result;
			}
			// change/add fields used by jFusion
			$result->userid = $result->id;
			$result->name = trim($result->firstname . ' ' . $result->lastname);
			$result->activation = !$result->confirmed;
			// get the policy agreed stuff
			$query = 'SELECT value FROM #__config WHERE  name = \'sitepolicy\'';
			$db->setQuery($query);
			$sitepolicy = $db->loadResult();
			if ($sitepolicy) {
				$result->block = !$result->policyagreed;
			} else {
				$result->block = 0;
			}
			$result->registerDate = date('d-m-Y H:i:s', $result->firstaccess);
			$result->lastvisitDate = date('d-m-Y H:i:s', $result->lastlogin);
		}
		return $result;
	}
	/**
	 * returns the name of this JFusion plugin
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'moodle';
	}
	function destroySession($userinfo, $options) {

		global $ch;
		global $cookiearr;
		global $cookies_to_set;
		global $cookies_to_set_index;
		$status = array();
		$tmpurl = array();
		$overridearr = array();
		$newhidden = array();
		$lines = array();
		$line=array();
		$status['debug']=array();
		$status['error']=array();
		$status['cURL']=array();
		$status['cURL']['moodle']='';
		$status['cURL']['data']= array();

		// check if curl extension is loaded
		if (!extension_loaded('curl')) {
			$status['error'][] = JFusionCurl::_('CURL_NOTINSTALLED');
			return $status;
		}

		$jname = $this->getJname();
		$params = & JFusionFactory::getParams($jname);
		$logout_url = $params->get('logout_url');

		$curl_options['post_url'] = $params->get('source_url') . $logout_url;
		$curl_options['cookiedomain'] = $params->get('cookie_domain');
		$curl_options['cookiepath'] = $params->get('cookie_path');
		$curl_options['leavealone'] = $params->get('leavealone');
		$curl_options['secure'] = $params->get('secure');
		$curl_options['httponly'] = $params->get('httponly');
		$curl_options['verifyhost'] = 0; //$params->get('ssl_verifyhost');
		$curl_options['httpauth'] = $params->get('httpauth');
		$curl_options['httpauth_username'] = $params->get('curl_username');
		$curl_options['httpauth_password'] = $params->get('curl_password');
		$curl_options['integrationtype']=0;
		$curl_options['debug'] =0;

		// to prevent endless loops on systems where there are multiple places where a user can login
		// we post an unique ID for the initiating software so we can make a difference between
		// a user logging out or another jFusion installation, or even another system with reverse dual login code.
		// We always use the source url of the initializing system, here the source_url as defined in the joomla_int
		// plugin. This is totally transparent for the the webmaster. No additional setup is needed


		$my_ID = rtrim(parse_url(JURI::root(), PHP_URL_HOST).parse_url(JURI::root(), PHP_URL_PATH), '/');
		$curl_options['jnodeid'] = $my_ID;

		$remotedata =JFusionCurl::ReadPage($curl_options, $status, true);
		if (!empty($status['error'])) {
			$status['debug'][]= JText::_('CURL_COULD_NOT_READ_PAGE: '). $curl_options['post_url'];
		} else {
			// get the form with no name and id!
			$parser = new JFusionCurlHtmlFormParser($remotedata);
			$result = $parser->parseForms();
			$frmcount = count($result);
			$myfrm = -1;
			$i = 0;
			do {
				$form_action = htmlspecialchars_decode($result[$i]['form_data']['action']);
				if (strpos($curl_options['post_url'],$form_action) !==false){
					$myfrm = $i;
					break;
				}
				$i +=1;
			} while ($i<$frmcount);

			if ($myfrm == -1) {
				// did not find a session key, so perform a brute force logout
				$status = JFusionJplugin::destroySession($userinfo, $options, $this->getJname());
			} else {
				$elements_keys = array_keys($result[$myfrm]['form_elements']);
				$elements_values = array_values($result[$myfrm]['form_elements']);
				$elements_count  = count($result[$myfrm]['form_elements']);
				$sessionkey = '';
				for ($i = 0; $i <= $elements_count-1; $i++) {
					if (strtolower($elements_keys[$i]) == 'sesskey') {
						$sessionkey=$elements_values[$i]['value'];
						break;
					}
				}
				if ($sessionkey == '') {
					// did not find a session key, so perform a brute force logout
					$status = JFusionJplugin::destroySession($userinfo, $options, $this->getJname());
				} else {
					$curl_options['post_url'] = $curl_options['post_url']."?sesskey=$sessionkey";
					$status = JFusionJplugin::destroySession($userinfo, $options, $this->getJname(),$params->get('logout_type'),$curl_options);
				}
			}
		}
		// check if the logout was successfull
		if (!empty($status['cURL']['moodle'])) {
			$loggedin_user = $this->rc4decrypt($status['cURL']['moodle']);
			$status['debug'][] = JText::_('CURL_MOODLE_USER') . " " . $loggedin_user;
			if ($loggedin_user != 'nobody') {
				$status['debug'][] = JText::_('CURL_LOGOUT_FAILURE');
			}
		}
		return $status;
	}
	function createSession($userinfo, $options) {
		$status = array();

		// If a session expired by not accessing Moodle for a long time we cannot login normally.
		// Also we want to disable the remember me effects, we are going to login anyway
		// we find out by reading the MOODLEID_ cookie and brute force login if MOODLE_ID is not nobody
		$curl_options = array();
		$curl_options['hidden']='0';
		$params = JFusionFactory::getParams($this->getJname());
		$logintype = $params->get('brute_force');
		if (isset($_COOKIE['MOODLEID_'])){
			$loggedin_user = $this->rc4decrypt($_COOKIE['MOODLEID_']);
			if ($loggedin_user == 'nobody') {
				$logintype = 'standard';
				$curl_options['hidden']='1' ;
			}
		}
		$status = JFusionJplugin::createSession($userinfo, $options, $this->getJname(),$logintype,$curl_options);
		// check if the login was successfull
		if (!empty($status['cURL']['moodle'])) {
			$loggedin_user = $this->rc4decrypt($status['cURL']['moodle']);
			$status['debug'][] = JText::_('CURL_MOODLE_USER') . " " . $loggedin_user;
			if ($loggedin_user != $userinfo->username) {
				$status['debug'][] = JText::_('CURL_LOGIN_FAILURE');
			}
		}
		return $status;
	}
	function filterUsername($username) {
		//Moodle has a switch to allow any character or just alphanumeric, dot, hypen (will be extendedn with @ and _ in Moodle 2.0
		// I recommend to set allow extended usernames to true in Moodles config.
		// must make note of this in docs.
		return $username;
	}
	function updatePassword($userinfo, $existinguser, &$status) {
		$params = JFusionFactory::getParams('moodle');
		if ($params->get('passwordsaltmain')) {
			$existinguser->password = md5($userinfo->password_clear . $params->get('passwordsaltmain'));
		} else {
			$existinguser->password = md5($userinfo->password_clear);
		}
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'UPDATE #__user SET password =' . $db->Quote($existinguser->password) . ' WHERE id =' . $existinguser->userid;
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
		//TODO ? check for duplicates, or leave it atdb error
		//we need to update the email
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'UPDATE #__user SET email =' . $db->Quote($userinfo->email) . ' WHERE id =' . (int)$existinguser->userid;
		$db->setQuery($query);
		if (!$db->query()) {
			$status['error'][] = JText::_('EMAIL_UPDATE_ERROR') . $db->stderr();
		} else {
			$status['debug'][] = JText::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email;
		}
	}
	function blockUser($userinfo, &$existinguser, &$status) {
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT value FROM #__config WHERE  name = \'sitepolicy\'';
		$db->setQuery($query);
		$sitepolicy = $db->loadObject();
		if ($sitepolicy->value) {
			$query = 'UPDATE #__user SET policyagreed = false WHERE id =' . (int)$existinguser->userid;
			$db->setQuery($query);
			if (!$db->query()) {
				$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
			} else {
				$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
			}
		} else {
			$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . JText::_('BLOCK_UPDATE_SITEPOLICY_NOT_SET');
		}
	}
	function unblockUser($userinfo, &$existinguser, &$status) {
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'SELECT value FROM #__config WHERE  name = sitepolicy';
		$db->setQuery($query);
		$sitepolicy = $db->loadObject();
		if ($sitepolicy->value) {
			$query = 'UPDATE #__user SET policyagreed = true WHERE id =' . (int)$existinguser->userid;
			$db->setQuery($query);
			if (!$db->query()) {
				$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . $db->stderr();
			} else {
				$status['debug'][] = JText::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block;
			}
		} else {
			$status['error'][] = JText::_('BLOCK_UPDATE_ERROR') . JText::_('BLOCK_UPDATE_SITEPOLICY_NOT_SET');
		}
	}
	function activateUser($userinfo, &$existinguser, &$status) {
		//activate the user
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'UPDATE #__user SET confirmed = true WHERE id =' . (int)$existinguser->userid;
		$db->setQuery($query);
		if (!$db->query()) {
			$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
		} else {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}
	function inactivateUser($userinfo, &$existinguser, &$status) {
		$db = JFusionFactory::getDatabase($this->getJname());
		$query = 'UPDATE #__user SET confirmed = false WHERE id =' . (int)$existinguser->userid;
		$db->setQuery($query);
		if (!$db->query()) {
			$status['error'][] = JText::_('ACTIVATION_UPDATE_ERROR') . $db->stderr();
		} else {
			$status['debug'][] = JText::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation;
		}
	}
	function createUser($userinfo, &$status) {
		// first find out if the user already exists, but with deleted flag set
		$db = JFusionFactory::getDatabase($this->getJname());
		$params = JFusionFactory::getParams($this->getJname());
		//get the identifier
		list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'username', 'email');
		$query = 'SELECT * FROM #__user WHERE ' . $identifier_type . ' = ' . $db->Quote($identifier);
		$db->setQuery($query);
		$result = $db->loadObject();
		if ($result) {
			//We have a record, probably with the deleted flag set.
			// Thus for Moodle internal working we need to use this record and resurrect the user
			$query = "UPDATE #__user SET deleted = '0' WHERE id = ". $db->Quote($result->id);
			$db->setQuery($query);
			if (!$db->query()) {
				//return the error
				$status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
				return;
			}
			//return the good news
			$status['userinfo'] = $this->getUser($userinfo);
			$status['debug'][] = JText::_('USER_CREATION');
			return;
		}

		//find out what usergroup should be used
		$db = JFusionFactory::getDatabase($this->getJname());
		$params = JFusionFactory::getParams($this->getJname());
		$usergroups = (substr($params->get('usergroup'), 0, 2) == 'a:') ? unserialize($params->get('usergroup')) : $params->get('usergroup', 18);
		//check to make sure that if using the advanced group mode, $userinfo->group_id exists
		if (is_array($usergroups) && !isset($userinfo->group_id)) {
			$status['error'][] = JText::_('GROUP_UPDATE_ERROR') . ": " . JText::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID');
			return null;
		}
		$default_group_id = (is_array($usergroups)) ? $usergroups[$userinfo->group_id] : $usergroups;
		// get some config items
		$query = 'SELECT value FROM #__config WHERE  name = \'mnet_localhost_id\'';
		$db->setQuery($query);
		$mnet_localhost_id = $db->loadResult();
		$query = 'SELECT value FROM #__config WHERE  name = \'lang\'';
		$db->setQuery($query);
		$lang = $db->loadResult();
		$query = 'SELECT value FROM #__config WHERE  name = \'country\'';
		$db->setQuery($query);
		$country = $db->loadResult();

		//prepare the variables
		$user = new stdClass;
		$user->id = $record_id;
		$user->auth = 'manual';
		if ($userinfo->activation) {
			$user->confirmed = 0;
		} else {
			$user->confirmed = 1;
		}
		$user->policyagreed = !$userinfo->block; // just write, true doesn't harm
		$user->deleted = 0;
		$user->mnethostid = $mnet_localhost_id;
		$user->username = $userinfo->username;
		if (isset($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
			$params = JFusionFactory::getParams('moodle');
			if ($params->get('passwordsaltmain')) {
				$user->password = md5($userinfo->password_clear . $params->get('passwordsaltmain'));
			} else {
				$user->password = md5($userinfo->password_clear);
			}
		} else {
			if (!empty($userinfo->password_salt)) {
				$user->password = $userinfo->password . ':' . $userinfo->password_salt;
			} else {
				$user->password = $userinfo->password;
			}
		}
		// $user->idnumber= ??
		$parts = explode(' ', $userinfo->name);
		$user->firstname = trim($parts[0]);
		if ($parts[(count($parts) - 1) ]) {
			for ($i = 1;$i < (count($parts));$i++) {
				$lastname = $lastname . ' ' . $parts[$i];
			}
		}
		$user->lastname = trim($lastname);
		$user->email = strtolower($userinfo->email);
		$user->country = $country;
		$user->lang = $lang;
		if ($record_id==null) {
			$user->firstaccess = time();
		}
		$user->timemodified = time();
		//now append the new user data
		if (!$db->insertObject('#__user', $user, 'id')) {
			//return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
			return;
		}
		// get new ID
		$userid = $db->insertid();
		// have to set user preferences
		$user_1 = new stdClass;
		$user_1->id = null;
		$user_1->userid = $userid;
		$user_1->name = 'auth_forcepasswordchange';
		$user_1->value = 0;
		if (!$db->insertObject('#__user_preferences', $user_1, 'id')) {
			//return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
			return;
		}
		$user_1->id = null;
		$user_1->userid = $userid;
		$user_1->name = 'email_bounce_count';
		$user_1->value = 1;
		if (!$db->insertObject('#__user_preferences', $user_1, 'id')) {
			//return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
			return;
		}
		$user_1->id = null;
		$user_1->userid = $userid;
		$user_1->name = 'email_send_count';
		$user_1->value = 1;
		if (!$db->insertObject('#__user_preferences', $user_1, 'id')) {
			//return the error
			$status['error'][] = JText::_('USER_CREATION_ERROR') . $db->stderr();
			return;
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
		$query = "UPDATE #__user SET deleted = '1' WHERE id =" . (int)$userinfo->userid;
		$db->setQuery($query);
		if (!$db->query()) {
			$status['error'][] = JText::_('USER_DELETION_ERROR') . $db->stderr();
		} else {
			$status['debug'][] = JText::_('USER_DELETION') . ': ' . $userinfo->userid . ' -> ' . $userinfo->username;
		}
		return $status;
	}
	/*       function updateUsergroup($userinfo, &$existinguser, &$status, $jname) {

	Moodles groupnigs depend on the course. In the current implementation you can map groups FROM moodles
	roles to usertype. because of the connection between courses, roles and groups the reverse is (not yet) possible.
	We have to come up with a way to handle this
	}
	*/
}