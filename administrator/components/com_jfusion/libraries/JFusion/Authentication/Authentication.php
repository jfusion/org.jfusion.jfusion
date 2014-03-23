<?php namespace JFusion\Authentication;
/**
 * @package     Joomla.Platform
 * @subpackage  User
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

use JFusion\Factory;
use JFusion\Framework;
use JFusion\Debugger\Debugger;;

use JFusion\Object\Object;
use Joomla\Language\Text;

use \stdClass;
use \Exception;

/**
 * Authentication class, provides an interface for the Joomla authentication system
 *
 * @package     Joomla.Platform
 * @subpackage  User
 * @since       11.1
 */
class Authentication extends Object
{
	// Shared success status
	/**
	 * This is the status code returned when the authentication is success (permit login)
	 * @const  STATUS_SUCCESS successful response
	 * @since  11.2
	 */
	const STATUS_SUCCESS = 1;

	// These are for authentication purposes (username and password is valid)
	/**
	 * Status to indicate cancellation of authentication (unused)
	 * @const  STATUS_CANCEL cancelled request (unused)
	 * @since  11.2
	 */
	const STATUS_CANCEL = 2;

	/**
	 * This is the status code returned when the authentication failed (prevent login if no success)
	 * @const  STATUS_FAILURE failed request
	 * @since  11.2
	 */
	const STATUS_FAILURE = 4;

	// These are for authorisation purposes (can the user login)
	/**
	 * This is the status code returned when the account has expired (prevent login)
	 * @const  STATUS_EXPIRED an expired account (will prevent login)
	 * @since  11.2
	 */
	const STATUS_EXPIRED = 8;

	/**
	 * This is the status code returned when the account has been denied (prevent login)
	 * @const  STATUS_DENIED denied request (will prevent login)
	 * @since  11.2
	 */
	const STATUS_DENIED = 16;

	/**
	 * This is the status code returned when the account doesn't exist (not an error)
	 * @const  STATUS_UNKNOWN unknown account (won't permit or prevent login)
	 * @since  11.2
	 */
	const STATUS_UNKNOWN = 32;

	/**
	 * An array of Observer objects to notify
	 *
	 * @var    array
	 * @since  12.1
	 */
	protected $observers = array();

	/**
	 * The state of the observable object
	 *
	 * @var    mixed
	 * @since  12.1
	 */
	protected $state = null;

	/**
	 * A multi dimensional array of [function][] = key for observers
	 *
	 * @var    array
	 * @since  12.1
	 */
	protected $methods = array();

	/**
	 * @var    Authentication  Authentication instances container.
	 * @since  11.3
	 */
	protected static $instance;

	/**
	 * Constructor
	 *
	 * @since   11.1
	 */
	public function __construct()
	{
	}

	/**
	 * Returns the global authentication object, only creating it
	 * if it doesn't already exist.
	 *
	 * @return  Authentication  The global JAuthentication object
	 *
	 * @since   11.1
	 */
	public static function getInstance()
	{
		if (empty(self::$instance))
		{
			self::$instance = new Authentication;
		}

		return self::$instance;
	}

	/**
	 * Get the state of the JAuthentication object
	 *
	 * @return  mixed    The state of the object.
	 *
	 * @since   11.1
	 */
	public function getState()
	{
		return $this->state;
	}

	/**
	 * Attach an observer object
	 *
	 * @param   object  $observer  An observer object to attach
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public function attach($observer)
	{
		if (is_array($observer))
		{
			if (!isset($observer['handler']) || !isset($observer['event']) || !is_callable($observer['handler']))
			{
				return;
			}

			// Make sure we haven't already attached this array as an observer
			foreach ($this->observers as $check)
			{
				if (is_array($check) && $check['event'] == $observer['event'] && $check['handler'] == $observer['handler'])
				{
					return;
				}
			}

			$this->observers[] = $observer;
			end($this->observers);
			$methods = array($observer['event']);
		}
		else
		{
			if (!($observer instanceof Authentication))
			{
				return;
			}

			// Make sure we haven't already attached this object as an observer
			$class = get_class($observer);

			foreach ($this->observers as $check)
			{
				if ($check instanceof $class)
				{
					return;
				}
			}

			$this->observers[] = $observer;
			$methods = array_diff(get_class_methods($observer), get_class_methods('JPlugin'));
		}

		$key = key($this->observers);

		foreach ($methods as $method)
		{
			$method = strtolower($method);

			if (!isset($this->methods[$method]))
			{
				$this->methods[$method] = array();
			}

			$this->methods[$method][] = $key;
		}
	}

	/**
	 * Detach an observer object
	 *
	 * @param   object  $observer  An observer object to detach.
	 *
	 * @return  boolean  True if the observer object was detached.
	 *
	 * @since   11.1
	 */
	public function detach($observer)
	{
		$retval = false;

		$key = array_search($observer, $this->observers);

		if ($key !== false)
		{
			unset($this->observers[$key]);
			$retval = true;

			foreach ($this->methods as &$method)
			{
				$k = array_search($key, $method);

				if ($k !== false)
				{
					unset($method[$k]);
				}
			}
		}

		return $retval;
	}

	/**
	 * Finds out if a set of login credentials are valid by asking all observing
	 * objects to run their respective authentication routines.
	 *
	 * @param   array  $credentials  Array holding the user credentials.
	 * @param   array  $options      Array holding user options.
	 *
	 * @return  AuthenticationResponse  Response object with status variable filled in for last plugin or first successful plugin.
	 *
	 * @see     AuthenticationResponse
	 * @since   11.1
	 */
	public function authenticate($credentials, $options = array())
	{
		// Create authentication response
		$response = new AuthenticationResponse;

		$debugger = Factory::getDebugger('jfusion-authentication');
		$debugger->set(null, array());

		$db = Factory::getDBO();
		//get the JFusion master
		$master = Framework::getMaster();
		if (!empty($master)) {
			$JFusionMaster = Factory::getUser($master->name);
			try {
				$userinfo = $JFusionMaster->getUser($credentials['username']);
			} catch (Exception $e) {
				$userinfo = null;
			}
			//check if a user was found
			if (!empty($userinfo)) {
				/**
				 * check to see if the login checker wanted a skip password
				 * TODO: DO WE still need to allow this ?
				 * $debug = \JFusionFunction::isAdministrator();
				 */
				$debug = false;
				if (!empty($options['skip_password_check']) && $debug === true) {
					$debugger->add('debug', Text::_('SKIPPED') . ' ' . Text::_('PASSWORD') . ' ' . Text::_('ENCRYPTION') . ' ' . Text::_('CHECK'));
					$response->status = Authentication::STATUS_SUCCESS;
					$response->email = $userinfo->email;
					$response->fullname = $userinfo->name;
					$response->error_message = '';
					$response->userinfo = $userinfo;
				} else {
					// Joomla does not like blank passwords
					if (empty($credentials['password'])) {
						$response->status = Authentication::STATUS_FAILURE;
						$response->error_message = Text::_('EMPTY_PASSWORD_NO_ALLOWED');
					} else {
						//store this to be stored jfusion_user table by the joomla_int createUser function
						$userinfo->credentialed_username = $credentials['username'];
						//apply the clear text password to the user object
						$userinfo->password_clear = $credentials['password'];
						//check the master plugin for a valid password
						$model = Factory::getAuth($master->name);

						try {
							$check = $model->checkPassword($userinfo);
						} catch (Exception $e) {
							Framework::raiseError($e, $model->getJname());
							$check = false;
						}
						if ($check) {
							//found a match
							$debugger->add('debug', $master->name . ' ' . Text::_('PASSWORD') . ' ' . Text::_('ENCRYPTION') . ' ' . Text::_('CHECK') . ': ' . Text::_('SUCCESS'));
							$response->status = Authentication::STATUS_SUCCESS;
							$response->email = $userinfo->email;
							$response->fullname = $userinfo->name;
							$response->error_message = '';
							$response->userinfo = $userinfo;
						} else {
							$testcrypt = $model->generateEncryptedPassword($userinfo);
							if (isset($options['show_unsensored'])) {
								$debugger->add('debug', $master->name . ' ' . Text::_('PASSWORD') . ' ' . Text::_('ENCRYPTION') . ' ' . Text::_('CHECK') . ': ' . $testcrypt . ' vs ' . $userinfo->password);
							} else {
								$debugger->add('debug', $master->name . ' ' . Text::_('PASSWORD') . ' ' . Text::_('ENCRYPTION') . ' ' . Text::_('CHECK') . ': ' .  substr($testcrypt, 0, 6) . '******** vs ' . substr($userinfo->password, 0, 6) . '********');
							}

							//otherwise check the other authentication models
							$query = $db->getQuery(true)
								->select('name')
								->from('#__jfusion')
								->where('master = 0')
								->where('check_encryption = 1');

							$db->setQuery($query);
							$auth_models = $db->loadObjectList();
							//loop through the different models
							foreach ($auth_models as $auth_model) {
								try {
									//Generate an encrypted password for comparison
									$model = Factory::getAuth($auth_model->name);
									$JFusionSlave = Factory::getUser($auth_model->name);
									$slaveuserinfo = $JFusionSlave->getUser($userinfo);
									// add in the clear password to be able to generate the hash
									if (!empty($slaveuserinfo)) {
										$slaveuserinfo->password_clear = $userinfo->password_clear;
										$testcrypt = $model->generateEncryptedPassword($slaveuserinfo);
										$check = $model->checkPassword($slaveuserinfo);
									} else {
										$testcrypt = $model->generateEncryptedPassword($userinfo);
										$check = $model->checkPassword($userinfo);
									}

									if ($check) {
										//found a match
										$debugger->add('debug', $auth_model->name . ' ' . Text::_('PASSWORD') . ' ' . Text::_('ENCRYPTION') . ' ' . Text::_('CHECK') . ': ' . Text::_('SUCCESS'));
										$response->status = Authentication::STATUS_SUCCESS;
										$response->email = $userinfo->email;
										$response->fullname = $userinfo->name;
										$response->error_message = '';
										$response->userinfo = $userinfo;
										//update the password format to what the master expects
										$JFusionMaster = Factory::getUser($master->name);
										//make sure that the password_clear is not already hashed which may be the case for some dual login plugins

										if (strlen($userinfo->password_clear) != 32) {
											$status = array('error' => array(), 'debug' => array());
											try {
												$JFusionMaster->updatePassword($userinfo, $userinfo, $status);
											} catch (Exception $e) {
												$JFusionMaster->debugger->add('error', Text::_('PASSWORD_UPDATE_ERROR') . ' ' . $e->getMessage());
											}
											$JFusionMaster->mergeStatus($status);
											$status = $JFusionMaster->debugger->get();
											if (!empty($status['error'])) {
												foreach($status['error'] as $error) {
													$debugger->add('debug', $auth_model->name . ' ' . Text::_('PASSWORD') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR') . ': ' . $error);
												}
												Framework::raise('error', $status['error'], $master->name. ' ' .Text::_('PASSWORD') . ' ' . Text::_('UPDATE'));
											} else {
												$debugger->add('debug', $auth_model->name . ' ' . Text::_('PASSWORD') . ' ' . Text::_('UPDATE') . ' ' . Text::_('SUCCESS'));
											}
										} else {
											$debugger->add('debug', $auth_model->name . ' ' . Text::_('SKIPPED_PASSWORD_UPDATE') . ': ' . Text::_('PASSWORD_UNAVAILABLE'));
										}
									} else {
										if (isset($options['show_unsensored'])) {
											$debugger->add('debug', $auth_model->name . ' ' . Text::_('PASSWORD') . ' ' . Text::_('ENCRYPTION') . ' ' . Text::_('CHECK') . ': ' .  $testcrypt . ' vs ' . $userinfo->password);
										} else {
											$debugger->add('debug', $auth_model->name . ' ' . Text::_('PASSWORD') . ' ' . Text::_('ENCRYPTION') . ' ' . Text::_('CHECK') . ': ' .  substr($testcrypt, 0, 6) . '******** vs ' . substr($userinfo->password, 0, 6) . '********');
										}
									}
								} catch (Exception $e) {
									Framework::raiseError($e);
								}
							}
						}
					}
				}
			} else {
				$response->error_message = Text::_('USER_NOT_EXIST');
				$debugger->add('debug', Text::_('USER_NOT_EXIST'));
			}
		} else {
			$response->status = Authentication::STATUS_UNKNOWN;
			$response->error_message = Text::_('JOOMLA_AUTH_PLUGIN_USED_NO_MASTER');
			$debugger->add('debug', Text::_('JOOMLA_AUTH_PLUGIN_USED_NO_MASTER'));
		}
		$response->debugger = $debugger;

		return $response;
	}
}

/**
 * Authentication response class, provides an object for storing user and error details
 *
 * @package     Joomla.Platform
 * @subpackage  User
 * @since       11.1
 */
class AuthenticationResponse
{
	/**
	 * Response status (see status codes)
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $status = Authentication::STATUS_FAILURE;

	/**
	 * The type of authentication that was successful
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $type = '';

	/**
	 *  The error message
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $error_message = '';

	/**
	 * Any UTF-8 string that the End User wants to use as a username.
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $username = '';

	/**
	 * Any UTF-8 string that the End User wants to use as a password.
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $password = '';

	/**
	 * The email address of the End User as specified in section 3.4.1 of [RFC2822]
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $email = '';

	/**
	 * UTF-8 string free text representation of the End User's full name.
	 *
	 * @var    string
	 * @since  11.1
	 *
	 */
	public $fullname = '';

	/**
	 * Userinfo
	 *
	 * @var    stdClass
	 * @since  11.1
	 *
	 */
	public $userinfo = '';

	/**
	 * The End User's date of birth as YYYY-MM-DD. Any values whose representation uses
	 * fewer than the specified number of digits should be zero-padded. The length of this
	 * value MUST always be 10. If the End User user does not want to reveal any particular
	 * component of this value, it MUST be set to zero.
	 *
	 * For instance, if a End User wants to specify that his date of birth is in 1980, but
	 * not the month or day, the value returned SHALL be "1980-00-00".
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $birthdate = '';

	/**
	 * The End User's gender, "M" for male, "F" for female.
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $gender = '';

	/**
	 * UTF-8 string free text that SHOULD conform to the End User's country's postal system.
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $postcode = '';

	/**
	 * The End User's country of residence as specified by ISO3166.
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $country = '';

	/**
	 * End User's preferred language as specified by ISO639.
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $language = '';

	/**
	 * ASCII string from TimeZone database
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $timezone = '';

	/**
	 * ASCII string from TimeZone database
	 *
	 * @var    Debugger
	 * @since  11.1
	 */
	public $debugger = null;
}
