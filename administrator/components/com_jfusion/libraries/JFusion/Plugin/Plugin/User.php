<?php namespace JFusion\Plugin;

/**
 * Abstract user class
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

use JFusion\Curl\Curl;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Joomla\Language\Text;
use Joomla\Registry\Registry;

use Psr\Log\LogLevel;
use \RuntimeException;
use \Exception;
use \stdClass;

/**
 * Abstract interface for all JFusion plugin implementations.
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.orgrg
 */
class Plugin_User extends Plugin
{
	var $helper;

	/**
	 * @param string $instance instance name of this plugin
	 */
	function __construct($instance)
	{
		parent::__construct($instance);
		//get the helper object
		$this->helper = & Factory::getHelper($this->getJname(), $this->getName());
	}

    /**
     * gets the userinfo from the JFusion integrated software. direct from the software
     *
     * @param Userinfo $userinfo contains the object of the user
     *
     * @return null|Userinfo Userinfo containing the user information
     */
	public function getUser(Userinfo $userinfo)
    {
        return null;
    }

	/**
	 * Gets the userinfo from the JFusion integrated software. it uses the lookup function
	 *
	 * @param Userinfo $userinfo contains the object of the user
	 *
	 * @return null|Userinfo Userinfo containing the user information
	 */
	final public function findUser(Userinfo $userinfo)
	{
		$userloopup = $this->lookupUser($userinfo);
		if ($userloopup instanceof Userinfo) {
			$user = $this->getUser($userloopup);
		} else {
			$user = $this->getUser($userinfo);
			if ($user instanceof Userinfo) {
				/**
				 * TODO: determine if the update lookup should really be here or not.
				 */
				$this->updateLookup($user, $userinfo);
			}
		}
		return $user;
	}

    /**
     * Returns the identifier and identifier_type for getUser
     *
     * @param Userinfo  &$userinfo      object with user identifying information
     * @param string    $username_col   Database column for username
     * @param string    $email_col      Database column for email
     * @param string    $userid_col     Database column for userid
	 * @param bool      $lowerEmail     Boolean to lowercase emails for comparison
     *
     * @return array array($identifier, $identifier_type)
     */
	public final function getUserIdentifier(Userinfo &$userinfo, $username_col, $email_col, $userid_col, $lowerEmail = true)
    {
	    $login_identifier = $this->params->get('login_identifier', 1);

	    if ($this->getJname() == $userinfo->getJname() && $userinfo->userid != null) {
		    $login_identifier = 4;
	    } elseif ($username_col == null) {
		    $login_identifier = 2;
	    }
        switch ($login_identifier) {
            default:
            case 1:
                // username
	            $identifier = $userinfo->username;
                $identifier_type = $username_col;
                break;
            case 2:
                // email
	            $identifier = $userinfo->email;
                $identifier_type = $email_col;
                break;
            case 3:
                // username or email
	            $pattern = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/i';
	            if (preg_match($pattern, $userinfo->username)) {
		            $identifier_type = $email_col;
		            $identifier = $userinfo->email;
	            } else {
		            $identifier_type = $username_col;
		            $identifier = $userinfo->username;
	            }
                break;
	        case 4:
		        // userid
		        $identifier_type = $userid_col;
		        $identifier = $userinfo->userid;
		        break;
        }
        if ($lowerEmail && $identifier_type == $email_col) {
            $identifier_type = 'LOWER(' . $identifier_type . ')';
            $identifier = strtolower($identifier);
        }
        return array($identifier_type, $identifier);
    }

    /**
     * Function that automatically logs out the user from the integrated software
     * $result['error'] (contains any error messages)
     * $result['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo contains the userinfo
     * @param array $options  contains Array with the login options, such as remember_me
     *
     * @return array result Array containing the result of the session destroy
     */
    function destroySession(Userinfo $userinfo, $options)
    {
        $result = array();
        $result['error'] = array();
        $result['debug'] = array();
        return $result;
    }

    /**
     * Function that automatically logs in the user from the integrated software
     * $result['error'] (contains any error messages)
     * $result['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo contains the userinfo
     * @param array  $options  contains array with the login options, such as remember_me     *
     *
     * @return array result Array containing the result of the session creation
     */
    function createSession(Userinfo $userinfo, $options)
    {
        return array();
    }

    /**
     * Function that filters the username according to the JFusion plugin
     *
     * @param string $username Username as it was entered by the user
     *
     * @return string filtered username that should be used for lookups
     */
    function filterUsername($username)
    {
        return $username;
    }

    /**
     * Updates or creates a user for the integrated software. This allows JFusion to have external software as slave for user management
     * $result['error'] (contains any error messages)
     * $result['userinfo'] (contains the userinfo object of the integrated software user)
     *
     * @param Userinfo $userinfo  contains the userinfo
     * @param int    $overwrite determines if the userinfo can be overwritten
     *
     * @throws RuntimeException
     *
     * @return array result Array containing the result of the user update
     */
    function updateUser(Userinfo $userinfo, $overwrite = 0)
    {
        $status = array('error' => array(), 'debug' => array());
	    $this->debugger->set(null, $status);
	    try {
		    if ($userinfo instanceof Userinfo) {
			    //get the user
			    $existinguser = $this->getUser($userinfo);
			    if ($existinguser instanceof Userinfo) {
				    $changed = false;
				    //a matching user has been found
				    $this->debugger->add('debug', Text::_('USER_DATA_FOUND'));

				    if($this->doUpdateEmail($userinfo, $existinguser, $overwrite)) {
					    $changed = true;
				    }

				    if($this->doUpdatePassword($userinfo, $existinguser)) {
					    $changed = true;
				    }

				    if ($this->doUpdateBlock($userinfo, $existinguser, $overwrite)) {
					    $changed = true;
				    }

				    if($this->doUpdateActivate($userinfo, $existinguser, $overwrite)) {
					    $changed = true;
				    }

				    if($this->doUpdateUsergroup($userinfo, $existinguser)) {
					    $changed = true;
				    }

				    if($this->doUserLanguage($userinfo, $existinguser)) {
					    $changed = true;
				    }

				    if ($this->debugger->isEmpty('error')) {
					    if ($changed == true) {
						    $this->debugger->set('action', 'updated');
						    //let's get updated information
						    $this->debugger->set('userinfo', $this->getUser($userinfo));
					    } else {
						    $this->debugger->set('action', 'unchanged');
						    $this->debugger->set('userinfo', $existinguser);
					    }
				    }
			    } else {
				    $this->createUser($userinfo);
			    }
		    } else {
			    throw new RuntimeException(Text::_('NO_USER_DATA_FOUND'));
		    }
	    } catch (Exception $e) {
		    $this->debugger->add('error', $e->getMessage());
	    }
	    $status = $this->debugger->get();
        return $status;
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @return boolean return true if changed
	 */
	function doUpdateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$changed = false;
		//check for advanced usergroup sync
		if (!$userinfo->block && empty($userinfo->activation)) {
			if (Framework::updateUsergroups($this->getJname())) {
				try {
					$usergroup_updated = $this->executeUpdateUsergroup($userinfo, $existinguser);
					if ($usergroup_updated) {
						$changed = true;
					} else {
						$this->debugger->add('debug', Text::_('SKIPPED_GROUP_UPDATE') . ':' . Text::_('GROUP_VALID'));
					}
				} catch (Exception $e) {
					$this->debugger->add('error', Text::_('GROUP_UPDATE_ERROR') . ' ' . $e->getMessage());
				}
			}
		}
		return $changed;
	}

    /**
     * Function that determines if the usergroup needs to be updated and executes updateUsergroup if it does
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     *
     * @return boolean Whether updateUsergroup was executed or not
     */
    function executeUpdateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
    {
        $changed = false;
        $usergroups = $this->getCorrectUserGroups($userinfo);
		if (!$this->compareUserGroups($existinguser, $usergroups)) {
			$this->updateUsergroup($userinfo, $existinguser);
			$changed = true;
        }
    	return $changed;
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @return boolean return true if changed
	 */
	function doUpdatePassword(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$changed = false;

		if (!empty($userinfo->password_clear) && strlen($userinfo->password_clear) != 32) {
			// add password_clear to existinguser for the Joomla helper routines
			$existinguser->password_clear = $userinfo->password_clear;
			//check if the password needs to be updated
			try {
				$model = Factory::getAuth($this->getJname());
				if (!$model->checkPassword($existinguser)) {
					try {
						$this->updatePassword($userinfo, $existinguser);
						$changed = true;
					} catch (Exception $e) {
						$this->debugger->add('error', Text::_('PASSWORD_UPDATE_ERROR') . ' ' . $e->getMessage());
					}
				} else {
					$this->debugger->add('debug', Text::_('SKIPPED_PASSWORD_UPDATE') . ':' . Text::_('PASSWORD_VALID'));
				}
			} catch (Exception $e) {
				$this->debugger->add('error', Text::_('SKIPPED_PASSWORD_UPDATE') . ':' . $e->getMessage());
			}
		} else {
			$this->debugger->add('debug', Text::_('SKIPPED_PASSWORD_UPDATE') . ': ' . Text::_('PASSWORD_UNAVAILABLE'));
		}
		return $changed;
	}

    /**
     * Function that updates the user password
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }

    /**
     * Function that updates the username
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     */
    function updateUsername(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }


	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 * @param          $overwrite
	 *
	 * @throws RuntimeException
	 * @return boolean return true if changed
	 */
	function doUpdateEmail(Userinfo $userinfo, Userinfo &$existinguser, $overwrite)
	{
		$changed = false;

		if (strtolower($existinguser->email) != strtolower($userinfo->email)) {
			$this->debugger->add('debug', Text::_('EMAIL_CONFLICT'));
			$update_email = $this->params->get('update_email', false);
			if ($update_email || $overwrite) {
				$this->debugger->add('debug', Text::_('EMAIL_CONFLICT_OVERWITE_ENABLED'));
				try {
					$this->updateEmail($userinfo, $existinguser);
					$changed = true;
				} catch (Exception $e) {
					$this->debugger->add('error', Text::_('EMAIL_UPDATE_ERROR') . ' ' . $e->getMessage());
				}
			} else {
				//return a email conflict
				$this->debugger->add('debug', Text::_('EMAIL_CONFLICT_OVERWITE_DISABLED'));

				$this->debugger->set('userinfo', $existinguser);
				throw new RuntimeException(Text::_('EMAIL') . ' ' . Text::_('CONFLICT') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
			}
		}
		return $changed;
	}

    /**
     * Function that updates the user email address
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }

    /**
     * Function that updates the usergroup
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 * @param          $overwrite
	 *
	 * @return boolean return true if changed
	 */
	function doUpdateBlock(Userinfo $userinfo, Userinfo &$existinguser, $overwrite)
	{
		$changed = false;
		//check the blocked status
		if ($existinguser->block != $userinfo->block) {

			$update_block = $this->params->get('update_block', false);
			if ($update_block || $overwrite) {
				if ($userinfo->block) {
					//block the user
					try {
						$this->blockUser($userinfo, $existinguser);
						$changed = true;
					} catch (Exception $e) {
						$this->debugger->add('error', Text::_('BLOCK_UPDATE_ERROR') . ' ' . $e->getMessage());
					}
					$changed = true;
				} else {
					//unblock the user
					try {
						$this->unblockUser($userinfo, $existinguser);
						$changed = true;
					} catch (Exception $e) {
						$this->debugger->add('error', Text::_('BLOCK_UPDATE_ERROR') . ' ' . $e->getMessage());
					}
				}
			} else {
				//return a debug to inform we skipped this step
				$this->debugger->add('debug', Text::_('SKIPPED_BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
			}
		}
		return $changed;
	}

    /**
     * Function that updates the blocks the user account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     */
    function blockUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }

    /**
     * Function that unblocks the user account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     */
    function unblockUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }


	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 * @param          $overwrite
	 *
	 * @return boolean return true if changed
	 */
	function doUpdateActivate(Userinfo $userinfo, Userinfo &$existinguser, $overwrite)
	{
		$changed = false;
		//check the activation status
		if (isset($existinguser->activation)) {
			if ($existinguser->activation != $userinfo->activation) {

				$update_activation = $this->params->get('update_activation', false);
				if ($update_activation || $overwrite) {
					if ($userinfo->activation) {
						//inactive the user
						try {
							$this->inactivateUser($userinfo, $existinguser);
							$changed = true;
						} catch (Exception $e) {
							$this->debugger->add('error', Text::_('ACTIVATION_UPDATE_ERROR') . ' ' . $e->getMessage());
						}
					} else {
						//activate the user
						try {
							$this->activateUser($userinfo, $existinguser);
							$changed = true;
						} catch (Exception $e) {
							$this->debugger->add('error', Text::_('ACTIVATION_UPDATE_ERROR') . ' ' . $e->getMessage());
						}
					}
				} else {
					//return a debug to inform we skipped this step
					$this->debugger->add('debug', Text::_('SKIPPED_ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
				}
			}
		}
		return $changed;
	}
    /**
     * Function that activates the users account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     */
    function activateUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }

    /**
     * Function that inactivates the users account
     * $status['error'] (contains any error messages)
     * $status['debug'] (contains information on what was done)
     *
     * @param Userinfo $userinfo      Object containing the new userinfo
     * @param Userinfo &$existinguser Object containing the old userinfo
     *
     * @throws RuntimeException
     */
    function inactivateUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }


	/**
	 * @param Userinfo $userinfo
	 */
	function doCreateUser(Userinfo $userinfo)
	{
		//check activation and block status
		$create_inactive = $this->params->get('create_inactive', 1);
		$create_blocked = $this->params->get('create_blocked', 1);

		if ((empty($create_inactive) && !empty($userinfo->activation)) || (empty($create_blocked) && !empty($userinfo->block))) {
			//block user creation
			$this->debugger->add('debug', Text::_('SKIPPED_USER_CREATION'));
			$this->debugger->set('debug', 'unchanged');
			$this->debugger->set('userinfo', null);
		} else {
			$this->debugger->add('debug', Text::_('NO_USER_FOUND_CREATING_ONE'));
			try {
				$this->createUser($userinfo);
				if ($this->debugger->isEmpty('error')) {
					$this->debugger->set('action', 'created');
				}
			} catch (Exception $e) {
				$this->debugger->add('error', Text::_('USER_CREATION_ERROR') . $e->getMessage());
			}
		}
	}


    /**
     * Function that creates a new user account
     *
     * @param Userinfo $userinfo Object containing the new userinfo
     */
    function createUser(Userinfo $userinfo)
    {
    }

    /**
     * Function that deletes a user account
     *
     * @param Userinfo $userinfo Object containing the existing userinfo
     *
     * @return array status Array containing the errors and result of the function
     */
    function deleteUser(Userinfo $userinfo)
    {
        //setup status array to hold debug info and errors
        $status = array('error' => array(), 'debug' => array());
        $status['error'][] = Text::_('DELETE_FUNCTION_MISSING');
        return $status;
    }

	/**
	 * @param Userinfo $userinfo
	 * @param Userinfo $existinguser
	 *
	 * @return boolean return true if changed
	 */
	function doUserLanguage(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$changed = false;
		//Update the user language with the current used in Joomla or the one existing from an other plugin
		if (empty($userinfo->language)) {
			$user_lang = '';
			if (!empty($userinfo->params)) {
				$params = new Registry($userinfo->params);
				$user_lang = $params->get('language');
			}
			$userinfo->language = !empty($user_lang) ? $user_lang : Factory::getLanguage()->getTag();
		}
		if (!empty($userinfo->language) && isset($existinguser->language) && !empty($existinguser->language) && $userinfo->language != $existinguser->language) {
			try {
				$this->updateUserLanguage($userinfo, $existinguser);
				$existinguser->language = $userinfo->language;
				$this->debugger->add('debug', Text::_('LANGUAGE_UPDATED') . ' : ' . $existinguser->language . ' -> ' . $userinfo->language);

				$changed = true;
			} catch (Exception $e) {
				$this->debugger->add('error', Text::_('LANGUAGE_UPDATED_ERROR') . ' ' . $e->getMessage());
			}
		} else {
			//return a debug to inform we skipped this step
			$this->debugger->add('debug', Text::_('LANGUAGE_NOT_UPDATED'));
		}
		return $changed;
	}

    /**
     * Function that update the language of a user
     *
     * @param Userinfo $userinfo Object containing the existing userinfo
     * @param Userinfo $existinguser         Object JLanguage containing the current language of Joomla
     */
    function updateUserLanguage(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $this->debugger->add('debug', __METHOD__ . ' function not implemented');
    }

	/**
	 * compare set of usergroup with a user returns true if the usergroups are correct
	 *
	 * @param Userinfo $userinfo user with current usergroups
	 * @param array $usergroups array with the correct usergroups
	 *
	 * @return boolean
	 */
	public function compareUserGroups(Userinfo $userinfo, $usergroups) {
		if (!is_array($usergroups)) {
			$usergroups = array($usergroups);
		}
		$correct = false;
		if (isset($userinfo->groups)) {
			$count = 0;
			if ( count($usergroups) == count($userinfo->groups) ) {
				foreach ($usergroups as $group) {
					if (in_array($group, $userinfo->groups, true)) {
						$count++;
					}
				}
				if (count($userinfo->groups) == $count) {
					$correct = true;
				}
			}
		} else {
			foreach ($usergroups as $group) {
				if ($group == $userinfo->group_id) {
					$correct = true;
					break;
				}
			}
		}
		return $correct;
	}

	/**
	 * Function That find the correct user group index
	 *
	 * @param Userinfo $userinfo
	 *
	 * @return int
	 */
	function getUserGroupIndex(Userinfo $userinfo)
	{
		$index = 0;

		$master = Framework::getMaster();
		if ($master) {
			$mastergroups = Framework::getUserGroups($master->name);

			$groups = array();
			if ($userinfo) {
				if (isset($userinfo->groups)) {
					$groups = $userinfo->groups;
				} elseif (isset($userinfo->group_id)) {
					$groups[] = $userinfo->group_id;
				}
			}

			foreach ($mastergroups as $key => $mastergroup) {
				if ($mastergroup) {
					if ( count($mastergroup) == count($groups) ) {
						$count = 0;
						foreach ($mastergroup as $value) {
							if (in_array($value, $groups, true)) {
								$count++;
							}
						}
						if (count($groups) == $count ) {
							$index = $key;
							break;
						}
					}
				}
			}
		}
		return $index;
	}

	/**
	 * Common code for user.php
	 *
	 * @param Userinfo $userinfo userinfo
	 * @param array $options  options
	 * @param string $type    jname
	 * @param array $curl_options_merge
	 *
	 * @return string nothing
	 */
	final public function curlLogin(Userinfo $userinfo, $options, $type = 'brute_force', $curl_options_merge = array())
	{
		require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
		$curl_options = array();
		$status = array('error' => array(), 'debug' => array());
		$source_url = $this->params->get('source_url');
		$login_url = $this->params->get('login_url');
		//prevent user error by not supplying trailing forward slash
		if (substr($source_url, -1) != '/') {
			$source_url = $source_url . '/';
		}
		//prevent user error by preventing a heading forward slash
		ltrim($login_url, '/');
		$curl_options['post_url'] = $source_url . $login_url;

		$curl_options['formid'] = $this->params->get('loginform_id');

		$login_identifier = $this->params->get('login_identifier', '1');
		$identifier = ($login_identifier === '2') ? 'email' : 'username';

		$curl_options['username'] = $userinfo->$identifier;
		$curl_options['password'] = $userinfo->password_clear;
		$integrationtype1 = $this->params->get('integrationtype');
		$curl_options['relpath']=  $this->params->get('relpath');
		$curl_options['hidden'] = $this->params->get('hidden');
		$curl_options['buttons'] = $this->params->get('buttons');
		$curl_options['override'] = $this->params->get('override');
		$curl_options['cookiedomain'] = $this->params->get('cookie_domain');
		$curl_options['cookiepath'] = $this->params->get('cookie_path');
		$curl_options['expires'] = $this->params->get('cookie_expires');
		$curl_options['input_username_id'] = $this->params->get('input_username_id');
		$curl_options['input_password_id'] = $this->params->get('input_password_id');
		$curl_options['secure'] = $this->params->get('secure');
		$curl_options['httponly'] = $this->params->get('httponly');
		$curl_options['verifyhost'] = 0; //$params->get('ssl_verifyhost');
		$curl_options['httpauth'] = $this->params->get('httpauth');
		$curl_options['httpauth_username'] = $this->params->get('curl_username');
		$curl_options['httpauth_password'] = $this->params->get('curl_password');

		// to prevent endless loops on systems where there are multiple places where a user can login
		// we post an unique ID for the initiating software so we can make a difference between
		// a user logging in or another jFusion installation, or even another system with reverse dual login code.
		// We always use the source url of the initializing system, here the source_url as defined in the joomla_int
		// plugin. This is totally transparent for the the webmaster. No additional setup is needed

		$curl_options['jnodeid'] = Framework::getNodeID();

		// For further simplifying setup we send also an indication if this system is a host. Other hosts should
		// only perform local joomla login when received this post. We define being a host if we have
		// at least one slave.

		$plugins = Factory::getPlugins('slave');
		if (count($plugins) > 2 ) {
			$jhost = true;
		} else {
			$jhost = false;
		}

		if ($jhost) {
			$curl_options['jhost'] = true;
		}
		if (!empty($curl_options_merge)) {
			$curl_options = array_merge($curl_options, $curl_options_merge);
		}

		// This check is just for Jfusion 1.x to support the reverse dual login function
		// We need to check if JFusion tries to create this session because of this integration
		// initiated a login by means of the reverse dual login extensions. Note that
		// if the curl routines are not used, the same check must be performed in the
		// create session routine in the user.php file of the plugin concerned.
		// In version 2.0 we will never reach this point as the user plugin will handle this
		$jnodeid = strtolower(Factory::getApplication()->input->get('jnodeid'));
		if (!empty($jnodeid)) {
			if($jnodeid == Factory::getPluginNodeId($this->getJname())) {
				// do not create a session, this integration started the log in and the user is already logged in
				$status['debug'][] = Text::_('ALREADY_LOGGED_IN');
				return $status;
			}
		}

		// correction of the integration type for Joomla Joomla using a sessionid in the logout form
		// for joomla 1.5 we need integration type 1 for login (LI) and 0 for logout (LO)
		// this is backward compatible
		// joomla 1.5  : use 3
		// joomla 1.6+ : use 1

		switch ($integrationtype1) {
			case '0':				// LI = 0  LO = 0
			case '2':				// LI = 0, LO = 1
				$integrationtype = 0;
				break;
			case '1':				// LI = 1  LO = 1
			case '3':				// LI = 1, LO = 0
			default:
				$integrationtype = 1;
				break;
		}

		$curl_options['integrationtype'] = $integrationtype;


		// extra lines for passing curl options to other routines, like ambrasubs payment processor
		// we are using the super global $_SESSION to pass data in $_SESSION[$var]
		$var = 'curl_options';
		if(!array_key_exists($var, $_SESSION)) $_SESSION[$var] = '';
		$_SESSION[$var] = $curl_options;
		$GLOBALS[$var] = &$_SESSION[$var];
		// end extra lines

		$type = strtolower($type);
		switch ($type) {
			case 'url':
//              $status = JFusionCurl::RemoteLoginUrl($curl_options);
				$status['error'][] = Text::_('CURL_LOGINTYPE_NOT_SUPPORTED');
				break;
			case 'brute_force':
				$curl_options['brute_force'] = $type;
				$status = Curl::RemoteLogin($curl_options);
				break;
			default:
				$status = Curl::RemoteLogin($curl_options);
		}
		$status['debug'][] = Text::_('CURL_LOGINTYPE') . '=' . $type;
		return $status;
	}

	/**
	 * @return array|string
	 */
	final public function curlReadPage()
    {
        require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
        $curl_options = array();
        $status = array('error' => array(), 'debug' => array());
        $status['cURL'] = array();
        $status['cURL']['moodle'] = '';
        $status['cURL']['data'] = array();

        // check if curl extension is loaded
        if (!extension_loaded('curl')) {
            $status['error'][] = Curl::_('CURL_NOTINSTALLED');
            return $status;
        }

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
        $curl_options['integrationtype']=0;
        $curl_options['debug'] =0;

        // to prevent endless loops on systems where there are multiple places where a user can login
        // we post an unique ID for the initiating software so we can make a difference between
        // a user logging out or another jFusion installation, or even another system with reverse dual login code.
        // We always use the source url of the initializing system, here the source_url as defined in the joomla_int
        // plugin. This is totally transparent for the the webmaster. No additional setup is needed


        $curl_options['jnodeid'] = Framework::getNodeID();
        $remotedata = Curl::RemoteReadPage($curl_options);
        return $remotedata;

    }



	/**
	 * Function that automatically logs out the user from the integrated software
	 *
	 * @param Userinfo $userinfo contains the userinfo
	 * @param array  $options  contains Array with the login options, such as remember_me
	 * @param string $type     method of destruction
	 * @param array $curl_options_merge
	 *
	 * @return array result Array containing the result of the session destroy
	 */
	final public function curlLogout(Userinfo $userinfo, $options, $type = 'brute_force', $curl_options_merge = array())
	{
		require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
		$curl_options = array();
		$status = array('error' => array(), 'debug' => array());

		$source_url = $this->params->get('source_url');
		$logout_url = $this->params->get('logout_url');
		//prevent user error by not supplying trailing forward slash
		if (substr($source_url, -1) != '/') {
			$source_url = $source_url . '/';
		}
		//prevent user error by preventing a heading forward slash
		ltrim($logout_url, '/');
		$curl_options['post_url'] = $source_url . $logout_url;

		$curl_options['formid'] = $this->params->get('loginform_id');
		$curl_options['username'] = $userinfo->username;
//        $curl_options['password'] = $userinfo->password_clear;
		$integrationtype1 = $this->params->get('integrationtype');
		$curl_options['relpath'] = $this->params->get('relpathl', $this->params->get('relpath', 0));
		$curl_options['hidden'] = '1';
		$curl_options['buttons'] = '1';
		$curl_options['override'] = '';
		$curl_options['cookiedomain'] = $this->params->get('cookie_domain');
		$curl_options['cookiepath'] = $this->params->get('cookie_path');
		$curl_options['expires'] = time() - 30*60*60;
		$curl_options['input_username_id'] = $this->params->get('input_username_id');
		$curl_options['input_password_id'] = $this->params->get('input_password_id');
		$curl_options['secure'] = $this->params->get('secure');
		$curl_options['httponly'] = $this->params->get('httponly');
		$curl_options['verifyhost'] = 0; //$params->get('ssl_verifyhost');
		$curl_options['httpauth'] = $this->params->get('httpauth');
		$curl_options['httpauth_username'] = $this->params->get('curl_username');
		$curl_options['httpauth_password'] = $this->params->get('curl_password');
		$curl_options['leavealone'] = $this->params->get('leavealone');
		$curl_options['postfields'] = $this->params->get('postfields','');
		$curl_options['logout'] = '1';

		// to prevent endless loops on systems where there are multiple places where a user can login
		// we post an unique ID for the initiating software so we can make a difference between
		// a user logging in or another jFusion installation, or even another system with reverse dual login code.
		// We always use the source url of the initializing system, here the source_url as defined in the joomla_int
		// plugin. This is totally transparent for the the webmaster. No additional setup is needed

		$curl_options['jnodeid'] = Framework::getNodeID();

		// For further simplifying setup we send also an indication if this system is a host. Other hosts should
		// only perform local joomla login when received this post. We define being a host if we have
		// at least one slave.


		$plugins = Factory::getPlugins('slave');
		if (count($plugins) > 2 ) {
			$jhost = true;
		} else {
			$jhost = false;
		}

		if ($jhost) {
			$curl_options['jhost'] = true;
		}
		if (!empty($curl_options_merge)) {
			$curl_options = array_merge($curl_options, $curl_options_merge);
		}

		// This check is just for Jfusion 1.x to support the reverse dual login function
		// We need to check if JFusion tries to delete this session because of this integration
		// initiated a logout by means of the reverse dual login extensions. Note that
		// if the curl routines are not used, the same check must be performed in the
		// destroysession routine in the user.php file of the plugin concerned.
		// In version 2.0 we will never reach this point as the user plugin will handle this
		$jnodeid = strtolower(Factory::getApplication()->input->get('jnodeid'));
		if (!empty($jnodeid)) {
			if($jnodeid == Factory::getPluginNodeId($this->getJname())) {
				// do not delete a session, this integration started the log out and the user is already logged out
				$status['debug'][] = Text::_('ALREADY_LOGGED_OUT');
				return $status;
			}
		}

		// correction of the integration type for Joomla Joomla using a sessionid in the logout form
		// for joomla 1.5 we need integration type 1 for login (LI) and 0 for logout (LO)
		// this is backward compatible
		// joomla 1.5  : use 3
		// joomla 1.6+ : use 1

		switch ($integrationtype1) {
			case '0':				// LI = 0  LO = 0
			case '3':				// LI = 1, LO = 0
				$integrationtype = 0;
				break;
			case '1':				// LI = 1  LO = 1
			case '2':				// LI = 0, LO = 1
			default:
				$integrationtype = 1;
				break;
		}
		$curl_options['integrationtype'] = $integrationtype;

		$type = strtolower($type);
		switch ($type) {
			case 'url':
				$status = Curl::RemoteLogoutUrl($curl_options);
				break;
			case 'form':
				$status = Curl::RemoteLogin($curl_options);
				break;
			case 'brute_force':
			default:
				$status = Curl::RemoteLogout($curl_options);
		}
		$status['debug'][] = Text::_('CURL_LOGOUTTYPE') . '=' . $type;
		return $status;
	}

	/**
	 * return the correct usergroups for a given user
	 *
	 * @param Userinfo|null $userinfo user with correct usergroups, if null it will return the usergroup for new users
	 *
	 * @return array
	 */
	final public function getCorrectUserGroups(Userinfo $userinfo)
	{
		$jname = $this->getJname();
		$group = array();

		$master = Framework::getMaster();
		if ($master->name == $jname) {
			$group = Framework::getUserGroups($master->name, true);
		} else {
			$slavegroups = Framework::getUserGroups($jname);

			$user = Factory::getUser($master->name);
			$index = $user->getUserGroupIndex($userinfo);

			if (isset($slavegroups[$index])) {
				$group = $slavegroups[$index];
			}

			if ($group === null && isset($slavegroups[0])) {
				$group =  $slavegroups[0];
			}
		}
		if (!is_array($group)) {
			if ($group !==  null) {
				$group = array($group);
			} else {
				$group = array();
			}
		}
		return $group;
	}

	/**
	 * Adds a cookie to the php header
	 *
	 * @param string $name      cookie name
	 * @param string $value     cookie value
	 * @param int    $expires   cookie expiry time
	 * @param string $path      cookie path
	 * @param string $domain    cookie domain
	 * @param bool $secure      is the secure
	 * @param bool $httponly    is the cookie http only
	 * @param bool $mask        should debug info be masked ?
	 *
	 * @return array            cookie debug info
	 */
	final public function addCookie($name, $value, $expires, $path, $domain, $secure = false, $httponly = false, $mask = false)
	{
		$cookies = Factory::getCookies();
		return $cookies->addCookie($name, $value, $expires, $path, $domain, $secure, $httponly, $mask);
	}

	/**
	 * Returns the userinfo data for JFusion plugin based on the userid
	 *
	 * @param Userinfo $exsistinginfo     user info
	 *
	 * @return Userinfo|null returns user login info from the requester software or null
	 *
	 */
	final public function lookupUser(Userinfo $exsistinginfo)
	{
		$user = null;
		if ($exsistinginfo) {
			//initialise some vars
			$db = Factory::getDBO();

			$query = $db->getQuery(true)
				->select('b.userid, b.username, b.email')
				->from('#__jfusion_users_plugin AS a')
				->innerJoin('#__jfusion_users_plugin AS b ON a.id = b.id')
				->where('b.jname = ' . $db->quote($this->getJname()))
				->where('a.jname = ' . $db->quote($exsistinginfo->getJname()));

			$search = array();
			if (isset($exsistinginfo->userid)) {
				$search[] = 'a.userid = ' . $db->quote($exsistinginfo->userid);
			}
			if (isset($exsistinginfo->username)) {
				$search[] = 'a.username = ' . $db->quote($exsistinginfo->username);
			}
			if (isset($exsistinginfo->email)) {
				$search[] = 'a.email = ' . $db->quote($exsistinginfo->email);
			}
			if (!empty($search)) {
				$query->where('( ' . implode(' OR ', $search) . ' )');
				$db->setQuery($query);
				$result = $db->loadObject();

				if ($result) {
					$user = new Userinfo($this->getJname());
					$user->bind($result);
				}
			}
		}
		return $user;
	}

	/**
	 * Updates the JFusion user lookup table during login
	 *
	 * @param Userinfo $userinfo         object containing the userdata
	 * @param Userinfo $exsistinginfo    object containing the userdata
	 */
	final public function updateLookup(Userinfo $userinfo, Userinfo $exsistinginfo)
	{
		$jname = $this->getJname();
		if ($userinfo->getJname() == $jname) {
			$db = Factory::getDBO();
			//we don't need to update the lookup for internal joomla unless deleting a user

			try {
				$query = $db->getQuery(true)
					->select('*')
					->from('#__jfusion_users_plugin')
					->where('( userid = ' . $db->quote($exsistinginfo->userid) . ' AND ' . 'jname = ' . $db->quote($exsistinginfo->getJname()) . ' )', 'OR')
					->where('( email = ' . $db->quote($userinfo->email) . ' AND ' . 'jname = ' . $db->quote($exsistinginfo->getJname()) . ' )', 'OR');
				if ($jname != $exsistinginfo->getJname()) {
					$query->where('( userid = ' . $db->quote($userinfo->userid) . ' AND ' . 'jname = ' . $db->quote($jname) . ' )', 'OR')
						->where('( email = ' . $db->quote($userinfo->email) . ' AND ' . 'jname = ' . $db->quote($jname) . ' )', 'OR');
				}
				$db->setQuery($query);

				$db->loadObjectList('jname');
				$list = $db->loadResult();
				if (empty($list)) {
					$first = new stdClass();
					$first->id = -1;
					$first->username = $exsistinginfo->username;
					$first->userid = $exsistinginfo->userid;
					$first->email = $exsistinginfo->email;
					$first->jname = $exsistinginfo->getJname();
					$db->insertObject('#__jfusion_users_plugin', $first, 'autoid');

					$first->id = $first->autoid;
					$db->updateObject('#__jfusion_users_plugin', $first, 'autoid');
					if ($jname != $exsistinginfo->getJname()) {
						$second = new stdClass();
						$second->id = $first->id;
						$second->username = $userinfo->username;
						$second->userid = $userinfo->userid;
						$second->email = $userinfo->email;
						$second->jname = $jname;
						$db->insertObject('#__jfusion_users_plugin', $second);
					}
				} else if (!isset($list[$exsistinginfo->getJname()])) {
					$first = new stdClass();
					$first->id = $list[$jname]->id;
					$first->username = $exsistinginfo->username;
					$first->userid = $exsistinginfo->userid;
					$first->email = $exsistinginfo->email;
					$first->jname = $exsistinginfo->getJname();
					$db->insertObject('#__jfusion_users_plugin', $first, 'autoid');
				} else if (!isset($list[$jname])) {
					$first = new stdClass();
					$first->id = $list[$exsistinginfo->getJname()]->id;
					$first->username = $userinfo->username;
					$first->userid = $userinfo->userid;
					$first->userid = $userinfo->userid;
					$first->jname = $jname;
					$db->insertObject('#__jfusion_users_plugin', $first, 'autoid');
				} else {
					$first = $list[$jname];
					$first->username = $userinfo->username;
					$first->userid = $userinfo->userid;
					$first->email = $userinfo->email;
					$db->updateObject('#__jfusion_users_plugin', $first, 'autoid');
				}
			} catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e);
			}
		}
	}

	/**
	 * Updates the JFusion user lookup table during login
	 *
	 * @param Userinfo $userinfo    object containing the userdata
	 */
	final public function deleteLookup(Userinfo $userinfo)
	{
		if ($userinfo->getJname() == $this->getJname()) {
			$db = Factory::getDBO();
			//we don't need to update the lookup for internal joomla unless deleting a user

			try {
				$query = $db->getQuery(true)
					->delete('#__jfusion_users_plugin')
					->where('userid = ' . $db->quote($userinfo->userid))
					->where('jname = ' . $db->quote($this->getJname()));

				$db->setQuery($query);
				$db->execute();
			} catch (Exception $e) {
				Framework::raise(LogLevel::ERROR, $e);
			}
		}
	}
}
