<?php namespace JFusion\User;
/**
 * @package     Joomla.Libraries
 * @subpackage  Application
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Exception;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Language\Text;
use Psr\Log\LogLevel;
use RuntimeException;

/**
 * Joomla! CMS Application class
 *
 * @package     Joomla.Libraries
 * @subpackage  Application
 * @since       3.2
 */
class User
{
	/**
	 * @var    User  The application instance.
	 * @since  11.3
	 */
	protected static $instance;

	/**
	 * Returns a reference to the global JApplicationCms object, only creating it if it doesn't already exist.
	 *
	 * This method must be invoked as: $web = JApplicationCms::getInstance();
	 *
	 * @return  User
	 */
	public static function getInstance()
	{
		if (!static::$instance)
		{
			static::$instance = new User();
		}
		return static::$instance;
	}

	/**
	 * Login authentication function.
	 *
	 * Username and encoded password are passed the onUserLogin event which
	 * is responsible for the user validation. A successful validation updates
	 * the current session record with the user's details.
	 *
	 * Username and encoded password are sent as credentials (along with other
	 * possibilities) to each observer (authentication plugin) for user
	 * validation.  Successful validation will update the current session with
	 * the user details.
	 *
	 * @param   array  $credentials  Array('username' => string, 'password' => string)
	 * @param   array  $options      Array('remember' => boolean)
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.2
	 */
	public function login($credentials, $options = array())
	{
		$success = 0;
		$debugger = Factory::getDebugger('jfusion-loginchecker');
		$debugger->set(null, array());
		$debugger->set('init', array());
		ob_start();
		try {
			global $JFusionActive, $JFusionLoginCheckActive, $JFusionActivePlugin;

			$JFusionActive = true;

			//php 5.3 does not allow plugins to contain pass by references
			//use a global for the login checker instead

			if (!isset($options['skipplugin'])) {
				$options['skipplugin'] = array();
			}

			if (!empty($JFusionActivePlugin)) {
				$options['skipplugin'][] = $JFusionActivePlugin;
			}

			//allow for the detection of external mods to exclude jfusion plugins
			$jnodeid = Factory::getApplication()->input->get('jnodeid', null);
			if ($jnodeid) {
				$jnodeid = strtolower($jnodeid);
				$JFusionActivePlugin = $jnodeid;
				$options['skipplugin'][] = $jnodeid;
			}

			//determine if overwrites are allowed
			if ($options['overwrite']) {
				$overwrite = 1;
			} else {
				$overwrite = 0;
			}

			//get the JFusion master
			$master = Framework::getMaster();
			if (!$master) {
				throw new RuntimeException(Text::_('NO_MASTER'));
			} else {
				$MasterUserPlugin = Factory::getUser($master->name);
				//check to see if userinfo is already present

				if ($credentials['userinfo'] instanceof Userinfo) {
					//the jfusion auth plugin is enabled
					$debugger->add('init', Text::_('USING_JFUSION_AUTH'));

					$userinfo = $credentials['userinfo'];
				} else {
					$debugger->add('init', Text::_('USING_OTHER_AUTH'));
					//other auth plugin enabled get the userinfo again
					//temp userinfo to see if the user exists in the master

					$authUserinfo = new Userinfo('joomla_int');
					$authUserinfo->username = $credentials['username'];
					$authUserinfo->email = $credentials['email'];
					$authUserinfo->password_clear = $credentials['password'];
					$authUserinfo->name = $credentials['fullname'];

					//get the userinfo for real
					try {
						$userinfo = $MasterUserPlugin->getUser($authUserinfo);
					} catch (Exception $e) {
						$userinfo = null;
					}

					if (!$userinfo instanceof Userinfo) {
						//should be auto-create users?
						$params = Factory::getParams($master->name);
						$autoregister = $params->get('autoregister', 0);
						if ($autoregister == 1) {
							try {
								$debugger->add('init', Text::_('CREATING_MASTER_USER'));
								//try to create a Master user

								$MasterUserPlugin->debugger->set(null, array('error' => array(), 'debug' => array()));
								$MasterUserPlugin->doCreateUser($authUserinfo);
								$status = $MasterUserPlugin->debugger->get();

								if (empty($status['error'])) {
									//success
									//make sure the userinfo is available
									if (!empty($status['userinfo'])) {
										$userinfo = $status['userinfo'];
									} else {
										$userinfo = $MasterUserPlugin->getUser($authUserinfo);
									}

									$debugger->add('init', Text::_('MASTER') . ' ' . Text::_('USER') . ' ' . Text::_('CREATE') . ' ' . Text::_('SUCCESS'));
								} else {
									//could not create user
									$debugger->add('init', $master->name . ' ' . Text::_('USER') . ' ' . Text::_('CREATE') . ' ' . Text::_('ERROR') . ' ' . $status['error']);
									Framework::raise(LogLevel::ERROR, $status['error'], $master->name . ': ' . Text::_('USER') . ' ' . Text::_('CREATE'));
									$success = -1;
								}
							} catch (Exception $e) {
								throw new RuntimeException($master->name . ' ' . Text::_('USER') . ' ' . Text::_('CREATE') . ' ' . Text::_('ERROR') . ' ' . $e->getMessage());
							}
						} else {
							//return an error
							$debugger->add('init', Text::_('COULD_NOT_FIND_USER'));
							throw new RuntimeException(Text::_('COULD_NOT_FIND_USER'));
						}
					}
				}

				if ($userinfo instanceof Userinfo) {
					if ($success === 0) {
						//apply the clear text password to the user object
						$userinfo->password_clear = $credentials['password'];

						if ($userinfo->block || $userinfo->activation) {
							//make sure the block is also applied in slave software
							$slaves = Framework::getSlaves();
							foreach ($slaves as $slave) {
								try {
									if (!in_array($slave->name, $options['skipplugin'])) {
										$JFusionSlave = Factory::getUser($slave->name);
										$SlaveUser = $JFusionSlave->updateUser($userinfo, $overwrite);
										//make sure the userinfo is available
										if (empty($SlaveUser['userinfo'])) {
											$SlaveUser['userinfo'] = $JFusionSlave->getUser($userinfo);
										}
										if (!empty($SlaveUser['error'])) {
											$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR'), $SlaveUser['error']);
										}
										if (!empty($SlaveUser['debug'])) {
											$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('DEBUG'), $SlaveUser['debug']);
										}

										$debugger->set($slave->name . ' ' . Text::_('USERINFO'), $SlaveUser['userinfo']);
									}
								} catch (Exception $e) {
									Framework::raise(LogLevel::ERROR, $e, $slave->name);
									$debugger->add($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR'), $e->getMessage());
								}
							}
							if ($userinfo->block) {
								throw new RuntimeException(Text::_('FUSION_BLOCKED_USER'));
							} else {
								throw new RuntimeException(Text::_('FUSION_INACTIVE_USER'));
							}
						} else {
							if (!in_array($master->name, $options['skipplugin']) && $master->dual_login == 1) {
								try {
									$MasterSession = $MasterUserPlugin->createSession($userinfo, $options);

									if (!empty($MasterSession['error'])) {
										$debugger->set($master->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $MasterSession['error']);
										Framework::raise(LogLevel::ERROR, $MasterSession['error'], $master->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
										/**
										 * TODO replace below code ? or just login slaves as well?
										 */
										if ($master->name == 'joomla_int') {
											$success = -1;
										}
									}
									if (!empty($MasterSession['debug'])) {
										$debugger->set($master->name . ' ' . Text::_('SESSION') . ' ' . Text::_('DEBUG'), $MasterSession['debug']);
										//report the error back
									}
								} catch (Exception $e) {
									$debugger->set($master->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $e->getMessage());
									Framework::raise(LogLevel::ERROR, $e, $master->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
									/**
									 * TODO replace below code ? or just login slaves as well?
									 */
									if ($master->name == 'joomla_int') {
										$success = -1;
									}
								}
							}
							if ($success === 0) {
								//allow for joomlaid retrieval in the loginchecker

								$MasterUserPlugin->updateLookup($userinfo, $userinfo);

								//setup the other slave JFusion plugins
								$slaves = Factory::getPlugins('slave');
								foreach ($slaves as $slave) {
									try {
										$SlaveUserPlugin = Factory::getUser($slave->name);
										$SlaveUser = $SlaveUserPlugin->updateUser($userinfo, $overwrite);
										if (!empty($SlaveUser['debug'])) {
											$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('DEBUG'), $SlaveUser['debug']);
										}
										if (!empty($SlaveUser['error'])) {
											$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR'), $SlaveUser['error']);
											Framework::raise(LogLevel::ERROR, $SlaveUser['error'], $slave->name . ': ' . Text::_('USER') . ' ' . Text::_('UPDATE'));
										} else {
											//make sure the userinfo is available
											if (empty($SlaveUser['userinfo'])) {
												$SlaveUser['userinfo'] = $SlaveUserPlugin->getUser($userinfo);
											}

											if (isset($options['show_unsensored'])) {
												$details = $SlaveUser['userinfo']->toObject();
											} else {
												$details = $SlaveUser['userinfo']->getAnonymizeed();
											}

											$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE'), $details);

											//apply the clear text password to the user object
											$SlaveUser['userinfo']->password_clear = $credentials['password'];

											$SlaveUserPlugin->updateLookup($SlaveUser['userinfo'], $userinfo);

											if (!in_array($slave->name, $options['skipplugin']) && $slave->dual_login == 1) {
												try {
													$SlaveSession = $SlaveUserPlugin->createSession($SlaveUser['userinfo'], $options);
													if (!empty($SlaveSession['error'])) {
														$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $SlaveSession['error']);
														Framework::raise(LogLevel::ERROR, $SlaveSession['error'], $slave->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
													}
													if (!empty($SlaveSession['debug'])) {
														$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('DEBUG'), $SlaveSession['debug']);
													}
												} catch (Exception $e) {
													$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $e->getMessage());
													Framework::raise(LogLevel::ERROR, $e, $SlaveUserPlugin->getJname());
												}
											}
										}
									} catch (Exception $e) {
										Framework::raise(LogLevel::ERROR, $e, $slave->name);
										$debugger->add('error', $e->getMessage());
									}
								}
								$success = 1;
							}
						}
					}
				}
			}
		} catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e);
			$debugger->add('error', $e->getMessage());
		}
		ob_end_clean();
	    return ($success === 1);
	}

	/**
	 * Logout authentication function.
	 *
	 * Passed the current user information to the onUserLogout event and reverts the current
	 * session record back to 'anonymous' parameters.
	 * If any of the authentication plugins did not successfully complete
	 * the logout routine then the whole method fails. Any errors raised
	 * should be done in the plugin as this provides the ability to give
	 * much more information about why the routine may have failed.
	 *
	 * @param   Userinfo $userinfo   The user to load - Can be an integer or string - If string, it is converted to ID automatically
	 * @param   array       $options
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.2
	 */
	public function logout(Userinfo $userinfo = null, $options = array())
	{
		//initialise some vars
		global $JFusionActive;
		$JFusionActive = true;

		if (!isset($options['skipplugin'])) {
			$options['skipplugin'] = array();
		}

		if (!empty($JFusionActivePlugin)) {
			$options['skipplugin'][] = $JFusionActivePlugin;
		}

		//allow for the detection of external mods to exclude jfusion plugins
		global $JFusionActivePlugin;
		jimport('joomla.environment.request');
		$jnodeid = strtolower(Factory::getApplication()->input->get('jnodeid'));
		if (!empty($jnodeid)) {
			$JFusionActivePlugin = $jnodeid;
			$options['skipplugin'][] = $jnodeid;
		}

		//prevent any output by the plugins (this could prevent cookies from being passed to the header)
		ob_start();
		//logout from the JFusion plugins if done through frontend
		$debugger = Factory::getDebugger('jfusion-loginchecker');

		//get the JFusion master
		$master = Framework::getMaster();
		if ($master) {
			if (!in_array($master->name, $options['skipplugin'])) {
				$JFusionMaster = Factory::getUser($master->name);
				$userlookup = $JFusionMaster->lookupUser($userinfo);
				$debugger->set('userlookup', $userlookup);
				if ($userlookup instanceof Userinfo) {
					$details = null;
					try {
						$MasterUser = $JFusionMaster->getUser($userlookup);
					} catch (Exception $e) {
						$MasterUser = null;
					}
					if ($MasterUser instanceof Userinfo) {
						if (isset($options['show_unsensored'])) {
							$details = $MasterUser->toObject();
						} else {
							$details = $MasterUser->getAnonymizeed();
						}

						try {
							$MasterSession = $JFusionMaster->destroySession($MasterUser, $options);
							if (!empty($MasterSession['error'])) {
								Framework::raise(LogLevel::ERROR, $MasterSession['error'], $master->name . ': ' . Text::_('SESSION') . ' ' . Text::_('DESTROY'));
							}
							if (!empty($MasterSession['debug'])) {
								$debugger->set($master->name . ' logout', $MasterSession['debug']);
							}
						} catch (Exception $e) {
							Framework::raise(LogLevel::ERROR, $e, $JFusionMaster->getJname());
						}
					} else {
						Framework::raise(LogLevel::NOTICE, Text::_('LOGOUT') . ' ' . Text::_('COULD_NOT_FIND_USER'), $master->name);
					}
					$debugger->set('masteruser', $details);
				}
			}

			$slaves = Factory::getPlugins('slave');
			foreach ($slaves as $slave) {
				if (!in_array($slave->name, $options['skipplugin'])) {
					//check if sessions are enabled
					if ($slave->dual_login == 1) {
						$JFusionSlave = Factory::getUser($slave->name);
						$userlookup = $JFusionSlave->lookupUser($userinfo);
						if ($userlookup instanceof Userinfo) {
							$info = null;
							try {
								$SlaveUser = $JFusionSlave->getUser($userlookup);
							} catch (Exception $e) {
								$SlaveUser = null;
							}
							if ($SlaveUser instanceof Userinfo) {
								if (isset($options['show_unsensored'])) {
									$info = $SlaveUser->toObject();
								} else {
									$info = $SlaveUser->getAnonymizeed();
								}

								$SlaveSession = array();
								try {
									$SlaveSession = $JFusionSlave->destroySession($SlaveUser, $options);
									if (!empty($SlaveSession['error'])) {
										Framework::raise(LogLevel::ERROR, $SlaveSession['error'], $slave->name . ': ' . Text::_('SESSION') . ' ' . Text::_('DESTROY'));
									}
									if (!empty($SlaveSession['debug'])) {
										$debugger->set($slave->name . ' logout', $SlaveSession['debug']);
									}
								} catch (Exception $e) {
									Framework::raise(LogLevel::ERROR, $e, $JFusionSlave->getJname());
								}
							} else {
								Framework::raise(LogLevel::NOTICE, Text::_('LOGOUT') . ' ' . Text::_('COULD_NOT_FIND_USER'), $slave->name);
							}

							$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('DETAILS') , $info);
						}
					}
				}
			}
		}
		ob_end_clean();
		return true;
	}

	/**
     * Delete user
     *
     * @param Userinfo $userinfo
     *
     * @return boolean
     */
	public function delete(Userinfo $userinfo)
	{
		$result = false;

		$debugger = Factory::getDebugger('jfusion-deleteuser');
		$debugger->set(null, array());

		//create an array to store the debug info
		$debug_info = array();
		$error_info = array();
		//delete the master user if it is not Joomla
		$master = Framework::getMaster();
		if ($master) {
			$params = Factory::getParams($master->name);
			$deleteEnabled = $params->get('allow_delete_users', 0);
			if ($deleteEnabled) {
				$JFusionMaster = Factory::getUser($master->name);
				try {
					$MasterUser = $JFusionMaster->getUser($userinfo);

					if ($MasterUser instanceof Userinfo) {
						$status = $JFusionMaster->deleteUser($MasterUser);
						if (!empty($status['error'])) {
							$error_info[$master->name . ' ' . Text::_('USER_DELETION_ERROR') ] = $status['error'];
						}
						if (!empty($status['debug'])) {
							$debug_info[$master->name] = $status['debug'];
						}
					} else {
						$debug_info[$master->name] = Text::_('NO_USER_DATA_FOUND');
					}
				} catch (Exception $e) {
					$error_info[$master->name . ' ' . Text::_('USER_DELETION_ERROR') ] = $e->getMessage();
				}
			} else {
				$debug_info[$master->name] = Text::_('DELETE_DISABLED');
			}

			//delete the user in the slave plugins
			$slaves = Factory::getPlugins('slave');
			foreach ($slaves as $slave) {
				$params = Factory::getParams($slave->name);
				$deleteEnabled = $params->get('allow_delete_users', 0);
				if ($deleteEnabled) {
					$JFusionSlave = Factory::getUser($slave->name);
					try {
						$SlaveUser = $JFusionSlave->getUser($userinfo);

						if ($SlaveUser instanceof Userinfo) {
							$status = $JFusionSlave->deleteUser($SlaveUser);
							if (!empty($status['error'])) {
								$error_info[$slave->name . ' ' . Text::_('USER_DELETION_ERROR') ] = $status['error'];
							}
							if (!empty($status['debug'])) {
								$debug_info[$slave->name] = $status['debug'];
							}
						} else {
							$debug_info[$slave->name] = Text::_('NO_USER_DATA_FOUND');
						}
					} catch (Exception $e) {
						$error_info[$slave->name . ' ' . Text::_('USER_DELETION_ERROR') ] = $e->getMessage();
					}
				} else {
					$debug_info[$slave->name] = Text::_('DELETE') . ' ' . Text::_('DISABLED');
				}
			}
			//remove userlookup data
			Framework::removeUser($userinfo);
		} else {
			$result = false;
		}
		$debugger->set('debug', $debug_info);
		$debugger->set('error', $error_info);
		return $result;
	}

	/**
	 * Delete user
	 *
	 * @param Userinfo $userinfo
	 * @param Userinfo $olduserinfo
	 * @param bool     $new
	 *
	 * @return boolean False on Error
	 */
	public function save(Userinfo $userinfo, Userinfo $olduserinfo = null, $new = false)
	{
		$debugger = Factory::getDebugger('jfusion-saveuser');
		$debugger->set(null, array());

		//create an array to store the debug info
		$debug_info = array();
		$error_info = array();

		//check to see if we need to update the master
		$master = Framework::getMaster();
		if ($master) {
			// Recover the old data of the user
			// This is then used to determine if the username was changed
			$updateUsername = false;
			if ($new == false && $userinfo instanceof Userinfo && $olduserinfo instanceof Userinfo) {
				if ($userinfo->getJname() == $olduserinfo->getJname() && $userinfo->username != $olduserinfo->username) {
					$updateUsername = true;
				}
			}
			//retrieve the username stored in jfusion_users if it exists
			$userPlugin = Factory::getUser($userinfo->getJname());
			if ($olduserinfo instanceof Userinfo) {
				$storedUser = $userPlugin->lookupUser($olduserinfo);
			} else {
				$storedUser = null;
			}

			$master_userinfo = null;
			try {
				$JFusionMaster = Factory::getUser($master->name);
				//update the master user if not joomla_int
				if ($master->name != $userinfo->getJname()) {
					$master_userinfo = $JFusionMaster->getUser($olduserinfo);
					//if the username was updated, call the updateUsername function before calling updateUser
					if ($updateUsername) {
						if ($master_userinfo instanceof Userinfo) {
							try {
								$JFusionMaster->debugger->set(null, array('error' => array(), 'debug' => array()));
								$JFusionMaster->updateUsername($userinfo, $master_userinfo);
								if (!$JFusionMaster->debugger->isEmpty('error')) {
									$error_info[$master->name . ' ' . Text::_('USERNAME') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR') ] = $JFusionMaster->debugger->get('error');
								}
								if (!$JFusionMaster->debugger->isEmpty('debug')) {
									$debug_info[$master->name . ' ' . Text::_('USERNAME') . ' ' . Text::_('UPDATE') . ' ' . Text::_('DEBUG') ] = $JFusionMaster->debugger->get('debug');
								}
							} catch (Exception $e) {
								$status['error'][] = Text::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
							}
						} else {
							$error_info[$master->name] = Text::_('NO_USER_DATA_FOUND');
						}
					}
					try {
						//run the update user to ensure any other userinfo is updated as well
						$MasterUser = $JFusionMaster->updateUser($userinfo, 1);
						if (!empty($MasterUser['error'])) {
							$error_info[$master->name] = $MasterUser['error'];
						}
						if (!empty($MasterUser['debug'])) {
							$debug_info[$master->name] = $MasterUser['debug'];
						}
						//make sure the userinfo is available
						if (empty($MasterUser['userinfo'])) {
							$master_userinfo = $JFusionMaster->getUser($userinfo);
						} else {
							$master_userinfo = $MasterUser['userinfo'];
						}
						//update the jfusion_users_plugin table
						$JFusionMaster->updateLookup($master_userinfo, $userinfo);
					} catch (Exception $e) {
						$error_info[$master->name] = array($e->getMessage());
					}
				} else {
					//Joomla is master
					// commented out because we should use the joomla use object (in out plugins)
					//	            $master_userinfo = $JoomlaUser;
					$master_userinfo = $JFusionMaster->getUser($userinfo);
				}
			} catch (Exception $e) {
				$error_info[$master->name] = array($e->getMessage());
			}

			if ($master_userinfo instanceof Userinfo) {
				if ( !empty($userinfo->password_clear) ) {
					$master_userinfo->password_clear = $userinfo->password_clear;
				}
				//update the user details in any JFusion slaves
				$slaves = Factory::getPlugins('slave');
				foreach ($slaves as $slave) {
					try {
						$JFusionSlave = Factory::getUser($slave->name);
						//if the username was updated, call the updateUsername function before calling updateUser
						if ($updateUsername) {
							$slave_userinfo = $JFusionSlave->getUser($olduserinfo);
							if ($slave_userinfo instanceof Userinfo) {
								try {
									$JFusionSlave->debugger->set(null, array('error' => array(), 'debug' => array()));
									$JFusionSlave->updateUsername($master_userinfo, $slave_userinfo);
									if (!$JFusionSlave->debugger->isEmpty('error')) {
										$error_info[$slave->name . ' ' . Text::_('USERNAME') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR') ] = $JFusionSlave->debugger->get('error');
									}
									if (!$JFusionSlave->debugger->isEmpty('debug')) {
										$debug_info[$slave->name . ' ' . Text::_('USERNAME') . ' ' . Text::_('UPDATE') . ' ' . Text::_('DEBUG') ] = $JFusionSlave->debugger->get('debug');
									}
								}  catch (Exception $e) {
									$status['error'][] = Text::_('USERNAME_UPDATE_ERROR') . ': ' . $e->getMessage();
								}
							} else {
								$error_info[$slave->name] = Text::_('NO_USER_DATA_FOUND');
							}
						}
						$SlaveUser = $JFusionSlave->updateUser($master_userinfo, 1);
						if (!empty($SlaveUser['error'])) {
							if (!is_array($SlaveUser['error'])) {
								$error_info[$slave->name] = array($SlaveUser['error']);
							} else {
								$error_info[$slave->name] = $SlaveUser['error'];
							}
						}
						if (!empty($SlaveUser['debug'])) {
							if (!is_array($SlaveUser['debug'])) {
								$debug_info[$slave->name] = array($SlaveUser['debug']);
							} else {
								$debug_info[$slave->name] = $SlaveUser['debug'];
							}
						}
						if (empty($SlaveUser['userinfo'])) {
							$slave_userinfo = $JFusionSlave->getUser($master_userinfo);
						} else {
							$slave_userinfo = $SlaveUser['userinfo'];
						}

						//update the jfusion_users_plugin table
						$JFusionSlave->updateLookup($slave_userinfo, $userinfo);
					} catch (Exception $e) {
						$error_info[$slave->name] = $debug_info[$slave->name] + array($e->getMessage());
					}
				}
			}
		}
		$debugger->set('debug', $debug_info);
		$debugger->set('error', $error_info);
		return true;
	}
}
