<?php namespace JFusion\Plugins\moodle;

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
use Exception;
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
class User extends Plugin_User
{
	/**
	 * @param $data
	 *
	 * @return mixed
	 */
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

	/**
	 * @param Userinfo $userinfo
	 *
	 * @return Userinfo|null
	 */
	function &getUser(Userinfo $userinfo) {
		$user = null;
		try {
			$db = Factory::getDatabase($this->getJname());
			//get the identifier
			list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'username', 'email', 'id');

			$query = $db->getQuery(true)
				->select('*, id as userid')
				->from('#__user')
				->where($identifier_type . ' = ' . $db->quote($identifier));

			$db->setQuery($query);
			$result = $db->loadObject();
			if ($result)
			{
				// check the deleted flag
				if ($result->deleted){
					$result = null;
				} else {
					// change/add fields used by jFusion
					$result->name = trim($result->firstname . ' ' . $result->lastname);
					$result->activation = !$result->confirmed;
					// get the policy agreed stuff

					$query = $db->getQuery(true)
						->select('value')
						->from('#__config')
						->where('name = ' . $db->quote('sitepolicy'));

					$db->setQuery($query);
					$sitepolicy = $db->loadResult();
					if ($sitepolicy) {
						$result->block = !$result->policyagreed;
					} else {
						$result->block = false;
					}
					$result->registerDate = date('d-m-Y H:i:s', $result->firstaccess);
					$result->lastvisitDate = date('d-m-Y H:i:s', $result->lastlogin);

					$user = new Userinfo($this->getJname());
					$user->bind($result);
				}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		}
		return $user;
	}

	/**
	 * Function that automatically logs out the user from the integrated software
	 * $result[LogLevel::ERROR] (contains any error messages)
	 * $result[LogLevel::DEBUG] (contains information on what was done)
	 *
	 * @param Userinfo $userinfo contains the userinfo
	 * @param array $options  contains Array with the login options, such as remember_me
	 *
	 * @return array result Array containing the result of the session destroy
	 */
	function destroySession(Userinfo $userinfo, $options)
    {
        $status = array(LogLevel::ERROR => array(), LogLevel::DEBUG => array());

        // find out if moodle stores its sessions on disk or in the database

        $db = Factory::getDatabase($this->getJname());
        //get the identifier
        $query = $db->getQuery(true)
            ->select('value')
            ->from('#__config')
            ->where('name = ' . $db->quote('dbsessions'));

        $db->setQuery($query);
        $dbsessions = $db->loadResult();
        $query = $db->getQuery(true)
            ->select('value')
            ->from('#__config')
            ->where('name = ' . $db->quote('sessioncookie'));

        $db->setQuery($query);
        $postfix = $db->loadResult();
        $cookieName = 'MoodleSession'.$postfix;
        $currentSession = $_COOKIE[$cookieName];
        $sessionFile = $this->params->get('dataroot', '') . 'sessions/sess_' . $currentSession;
        // find out the current session name

        if ($dbsessions){
            $query = $db->getQuery(true)
                ->delete('#__sessions')
                ->where('sid = ' .  $db->quote($currentSession));

            $db->setQuery($query);
            $db->execute();
            $status[LogLevel::DEBUG][] = 'Moodle: session ' . $currentSession . ' deleted in database';
        } else {
            $result = unlink($sessionFile);
            if ($result) {
                $status[LogLevel::DEBUG][] = 'Moodle: session ' . $currentSession . ' deleted as file';
            } else {
                $status[LogLevel::DEBUG][] = 'Moodle: session ' . $currentSession . ' could not delete file ' . $sessionFile;
            }
        }

        return $status;
	}

	/**
	 * Function that automatically logs in the user from the integrated software
	 * $result[LogLevel::ERROR] (contains any error messages)
	 * $result[LogLevel::DEBUG] (contains information on what was done)
	 *
	 * @param Userinfo $userinfo contains the userinfo
	 * @param array  $options  contains array with the login options, such as remember_me     *
	 *
	 * @return array result Array containing the result of the session creation
	 */
	function createSession(Userinfo $userinfo, $options) {
		// If a session expired by not accessing Moodle for a long time we cannot login normally.
		// Also we want to disable the remember me effects, we are going to login anyway
		// we find out by reading the MOODLEID_ cookie and brute force login if MOODLE_ID is not nobody
		$curl_options = array();
		$curl_options['hidden'] = '0';
		$logintype = $this->params->get('brute_force');
		if (isset($_COOKIE['MOODLEID_'])){
			$loggedin_user = $this->rc4decrypt($_COOKIE['MOODLEID_']);
			if ($loggedin_user == 'nobody') {
				$logintype = 'standard';
				$curl_options['hidden'] = '1' ;
			}
		}
		$status = $this->curlLogin($userinfo, $options, $logintype, $curl_options);
		// check if the login was successful
		if (!empty($status['cURL']['moodle'])) {
			$loggedin_user = $this->rc4decrypt($status['cURL']['moodle']);
			$status[LogLevel::DEBUG][] = Text::_('CURL_MOODLE_USER') . ' ' . $loggedin_user;
			if ($loggedin_user != $userinfo->username) {
				$status[LogLevel::DEBUG][] = Text::_('CURL_LOGIN_FAILURE');
			}
		}
		return $status;
	}

	/**
	 * Function that updates the user password
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 */
	function updatePassword(Userinfo $userinfo, Userinfo &$existinguser) {
		if ($this->params->get('passwordsaltmain')) {
			$existinguser->password = md5($userinfo->password_clear . $this->params->get('passwordsaltmain'));
		} else {
			$existinguser->password = md5($userinfo->password_clear);
		}
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__user')
			->set('password = ' . $db->quote($existinguser->password))
			->where('id = ' . $existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->addDebug(Text::_('PASSWORD_UPDATE')  . ': ' . substr($existinguser->password, 0, 6) . '********');
	}

	/**
	 * Function that updates the username
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 */
	function updateUsername(Userinfo $userinfo, Userinfo &$existinguser) {
		// not implemented in jFusion 1.x
	}

	/**
	 * Function that updates the user email address
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 */
	function updateEmail(Userinfo $userinfo, Userinfo &$existinguser) {
		//TODO ? check for duplicates, or leave it at db error
		//we need to update the email
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__user')
			->set('email = ' . $db->quote($userinfo->email))
			->where('id = ' . (int)$existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->addDebug(Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
	}

	/**
	 * Function that updates the blocks the user account
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @throws RuntimeException
	 */
	function blockUser(Userinfo $userinfo, Userinfo &$existinguser) {
		$db = Factory::getDatabase($this->getJname());
		$query = $db->getQuery(true)
			->select('value')
			->from('#__config')
			->where('name = ' . $db->quote('sitepolicy'));
		$db->setQuery($query);
		$sitepolicy = $db->loadObject();
		if ($sitepolicy->value) {
			$query = $db->getQuery(true)
				->update('#__user')
				->set('policyagreed = false')
				->where('id = ' . (int)$existinguser->userid);

			$db->setQuery($query);
			$db->execute();

			$this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
		} else {
			throw new RuntimeException(Text::_('BLOCK_UPDATE_SITEPOLICY_NOT_SET'));
		}
	}

	/**
	 * Function that unblocks the user account
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 *
	 * @throws RuntimeException
	 */
	function unblockUser(Userinfo $userinfo, Userinfo &$existinguser) {
		$db = Factory::getDatabase($this->getJname());
		$query = $db->getQuery(true)
			->select('value')
			->from('#__config')
			->where('name = ' . $db->quote('sitepolicy'));
		$db->setQuery($query);
		$sitepolicy = $db->loadObject();
		if ($sitepolicy->value) {

			$query = $db->getQuery(true)
				->update('#__user')
				->set('policyagreed = true')
				->where('id = ' . (int)$existinguser->userid);

			$db->setQuery($query);
			$db->execute();

			$this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
		} else {
			throw new RuntimeException(Text::_('BLOCK_UPDATE_SITEPOLICY_NOT_SET'));
		}
	}

	/**
	 * Function that activates the users account
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 */
	function activateUser(Userinfo $userinfo, Userinfo &$existinguser) {
		//activate the user
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__user')
			->set('confirmed = true')
			->where('id = ' . (int)$existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	}

	/**
	 * Function that inactivates the users account
	 *
	 * @param Userinfo $userinfo      Object containing the new userinfo
	 * @param Userinfo &$existinguser Object containing the old userinfo
	 */
	function inactivateUser(Userinfo $userinfo, Userinfo &$existinguser) {
		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__user')
			->set('confirmed = false')
			->where('id = ' . (int)$existinguser->userid);

		$db->setQuery($query);
		$db->execute();

		$this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
	}

	/**
	 * Function that creates a new user account
	 *
	 * @param Userinfo $userinfo Object containing the new userinfo
	 *
	 * @throws \RuntimeException
	 *
	 * @return Userinfo
	 */
	function createUser(Userinfo $userinfo) {
		// first find out if the user already exists, but with deleted flag set
		$db = Factory::getDatabase($this->getJname());
		//get the identifier
		list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'username', 'email', 'id');

		$query = $db->getQuery(true)
			->select('*')
			->from('#__user')
			->where($identifier_type . ' = ' . $db->quote($identifier));

		$db->setQuery($query);
		$result = $db->loadObject();
		if ($result) {
			//We have a record, probably with the deleted flag set.
			// Thus for Moodle internal working we need to use this record and resurrect the user
			$query = $db->getQuery(true)
				->update('#__user')
				->set('deleted = 0')
				->where('id = ' . $db->quote($result->id));
			$db->setQuery($query);
			$db->execute();
		} else {
			//find out what usergroup should be used
			$db = Factory::getDatabase($this->getJname());

			$usergroups = $this->getCorrectUserGroups($userinfo);
			if (empty($usergroups)) {
				throw new RuntimeException(Text::_('ADVANCED_GROUPMODE_MASTER_NOT_HAVE_GROUPID'));
			} else {
				// get some config items
				$query = $db->getQuery(true)
					->select('value')
					->from('#__config')
					->where('name = ' . $db->quote('mnet_localhost_id'));

				$db->setQuery($query);
				$mnet_localhost_id = $db->loadResult();

				$query = $db->getQuery(true)
					->select('value')
					->from('#__config')
					->where('name = ' . $db->quote('lang'));

				$db->setQuery($query);
				$lang = $db->loadResult();

				$query = $db->getQuery(true)
					->select('value')
					->from('#__config')
					->where('name = ' . $db->quote('country'));

				$db->setQuery($query);
				$country = $db->loadResult();

				//prepare the variables
				$user = new stdClass;
				$user->id = null;
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
					if ($this->params->get('passwordsaltmain')) {
						$user->password = md5($userinfo->password_clear . $this->params->get('passwordsaltmain'));
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
				$lastname = '';
				if ($parts[(count($parts) - 1) ]) {
					for ($i = 1;$i < (count($parts));$i++) {
						if (!empty($lastname)) {
							$lastname = $lastname . ' ' . $parts[$i];
						} else {
							$lastname = $parts[$i];
						}

					}
				}
				$user->lastname = trim($lastname);
				$user->email = strtolower($userinfo->email);
				$user->country = $country;
				$user->lang = $lang;
				$user->firstaccess = time();
				$user->timemodified = time();
				//now append the new user data
				$db->insertObject('#__user', $user, 'id');

				// get new ID
				$userid = $db->insertid();
				// have to set user preferences
				$user_1 = new stdClass;
				$user_1->id = null;
				$user_1->userid = $userid;
				$user_1->name = 'auth_forcepasswordchange';
				$user_1->value = 0;
				$db->insertObject('#__user_preferences', $user_1, 'id');

				$user_1->id = null;
				$user_1->userid = $userid;
				$user_1->name = 'email_bounce_count';
				$user_1->value = 1;
				$db->insertObject('#__user_preferences', $user_1, 'id');

				$user_1->id = null;
				$user_1->userid = $userid;
				$user_1->name = 'email_send_count';
				$user_1->value = 1;
				$db->insertObject('#__user_preferences', $user_1, 'id');
			}
		}

		//return the good news
		return $this->getUser($userinfo);
	}

	/**
	 * Function that deletes a user account
	 *
	 * @param Userinfo $userinfo Object containing the existing userinfo
	 *
	 * @throws \RuntimeException
	 *
	 * @return boolean returns true on success and false on error
	 */
	function deleteUser(Userinfo $userinfo) {
		$status = array(LogLevel::ERROR => array(), LogLevel::DEBUG => array());

		//setup status array to hold debug info and errors

		$db = Factory::getDatabase($this->getJname());

		$query = $db->getQuery(true)
			->update('#__user')
			->set('deleted = 1')
			->where('id = ' . (int)$userinfo->userid);

		$db->setQuery($query);
		$db->execute();

		$status[LogLevel::DEBUG][] = Text::_('USER_DELETION') . ': ' . $userinfo->userid . ' -> ' . $userinfo->username;

		return $status;
	}

	/*
	public function updateUsergroup($userinfo, &$existinguser)
	{
		Moodles groupings depend on the course. In the current implementation you can map groups FROM moodles
		roles to usertype. because of the connection between courses, roles and groups the reverse is (not yet) possible.
		We have to come up with a way to handle this
	}
	*/
}