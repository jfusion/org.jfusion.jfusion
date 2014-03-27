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
use Jfusion\Framework;
use Joomla\Event\Event;
use Joomla\Input\Input;
use JFusion\Session\Session;
use Joomla\Language\Text;
use stdClass;


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
		ob_start();
		$success = 0;
		global $JFusionActive, $JFusionLoginCheckActive, $JFusionActivePlugin;

		$JFusionActive = true;

		//php 5.3 does not allow plugins to contain pass by references
		//use a global for the login checker instead

		$debugger = Factory::getDebugger('jfusion-loginchecker');
		$debugger->set(null, array());
		$debugger->set('init', array());

		if (!isset($options['skipplugin'])) {
			$options['skipplugin'] = array();
		}

		if (!empty($JFusionActivePlugin)) {
			$options['skipplugin'][] = $JFusionActivePlugin;
		}

		//allow for the detection of external mods to exclude jfusion plugins
		$jnodeid = strtolower(Factory::getApplication()->input->get('jnodeid'));
		if (!empty($jnodeid)) {
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
		//setup JFusionUser object for Joomla
		$JFusionJoomla = Factory::getUser('joomla_int');
		if ($master) {
			$MasterUserPlugin = Factory::getUser($master->name);
			//check to see if userinfo is already present

			if (!empty($credentials['userinfo'])) {
				//the jfusion auth plugin is enabled
				$debugger->add('init', Text::_('USING_JFUSION_AUTH'));

				$userinfo = $credentials['userinfo'];
			} else {
				//other auth plugin enabled get the userinfo again
				//temp userinfo to see if the user exists in the master
				$auth_userinfo = new stdClass();
				$auth_userinfo->username = $credentials['username'];
				$auth_userinfo->email = $credentials['email'];
				$auth_userinfo->password_clear = $credentials['password'];
				$auth_userinfo->name = $credentials['fullname'];
				//get the userinfo for real
				try {
					$userinfo = $MasterUserPlugin->getUser($auth_userinfo);
				} catch (Exception $e) {
					$userinfo = null;
				}
				/*
				if (isset($master->joomlaAuth)) {
					$debugger->add('init', Text::_('USING_JOOMLA_AUTH'));
				} else {
					$debugger->add('init', Text::_('USING_OTHER_AUTH'));
				}
				if (empty($userinfo)) {
					//are we in Joomla backend?  Let's check internal Joomla for the user if joomla_int isn't already the master to prevent lockouts
					if ($master->name != 'joomla_int' && $mainframe->isAdmin()) {
						$JFusionJoomla = Factory::getUser('joomla_int');
						try {
							$JoomlaUserinfo = $JFusionJoomla->getUser($auth_userinfo);
						} catch (Exception $e) {
							$JoomlaUserinfo = null;
						}
						if (!empty($JoomlaUserinfo)) {
							//user found in Joomla, let them pass just to be able to login to the backend
							$userinfo = $JoomlaUserinfo;
						} else {
							//user not found in Joomla, return an error
							$debugger->add('init', Text::_('COULD_NOT_FIND_USER'));
							$success = -1;
						}
					} else {
						//should be auto-create users?
						$params = Factory::getParams('joomla_int');
						$autoregister = $params->get('autoregister', 0);
						if ($autoregister == 1) {
							try {
								$debugger->add('init', Text::_('CREATING_MASTER_USER'));
								$status = array('error' => array(), 'debug' => array());
								//try to create a Master user
								$JFusionMaster->createUser($auth_userinfo, $status);
								$JFusionMaster->mergeStatus($status);
								$status = $JFusionMaster->debugger->get();

								if (empty($status['error'])) {
									//success
									//make sure the userinfo is available
									if (!empty($status['userinfo'])) {
										$userinfo = $status['userinfo'];
									} else {
										$userinfo = $JFusionMaster->getUser($auth_userinfo);
									}

									$debugger->add('init', Text::_('MASTER') . ' ' . Text::_('USER') . ' ' . Text::_('CREATE') . ' ' . Text::_('SUCCESS'));
								} else {
									//could not create user
									$debugger->add('init', $master->name . ' ' . Text::_('USER') . ' ' . Text::_('CREATE') . ' ' . Text::_('ERROR') . ' ' . $status['error']);
									$this->raise('error', $status['error'], $master->name . ': ' . Text::_('USER') . ' ' . Text::_('CREATE'));
									$success = -1;
								}
							} catch (Exception $e) {
								Framework::raiseError($e, $JFusionMaster->getJname());
								$debugger->add('error', $e->getMessage());
								$success = -1;
							}
						} else {
							//return an error
							$debugger->add('init', Text::_('COULD_NOT_FIND_USER'));
							$success = -1;
						}
					}
				}
				*/
			}

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
							Framework::raiseError($e, $slave->name);
							$debugger->add($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR'), $e->getMessage());
						}
					}
					if (!empty($userinfo->block)) {
						$debugger->add('error', Text::_('FUSION_BLOCKED_USER'));
						$this->raise('warning', Text::_('FUSION_BLOCKED_USER'));
						$success = -1;
					} else {
						$debugger->add('error', Text::_('FUSION_INACTIVE_USER'));
						$this->raise('warning', Text::_('FUSION_INACTIVE_USER'));
						$success = -1;
					}
				} else {
					/**
					 * TODO: skip this ?  && (!isset($options['group']) || $master->name == 'joomla_int')
					 */
					if (!in_array($master->name, $options['skipplugin']) && $master->dual_login == 1) {
						try {
							$MasterSession = $MasterUserPlugin->createSession($userinfo, $options);

							if (!empty($MasterSession['error'])) {
								$debugger->set($master->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $MasterSession['error']);
								$this->raise('error', $MasterSession['error'], $master->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
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
							Framework::raiseError($e, $master->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
							if ($master->name == 'joomla_int') {
								$success = -1;
							}
						}
					}
					if ($success === 0) {
						//allow for joomlaid retrieval in the loginchecker
/*
						$debugger->set('joomlaid', $JoomlaUser['userinfo']->userid);
						if ($master->name != 'joomla_int') {
							Framework::updateLookup($userinfo, $JoomlaUser['userinfo']->userid, $master->name);
						}
*/
						//setup the other slave JFusion plugins
						$slaves = Factory::getPlugins('slave');
						foreach ($slaves as $slave) {
							try {
								$JFusionSlave = Factory::getUser($slave->name);
								$SlaveUser = $JFusionSlave->updateUser($userinfo, $overwrite);
								if (!empty($SlaveUser['debug'])) {
									$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('DEBUG'), $SlaveUser['debug']);
								}
								if (!empty($SlaveUser['error'])) {
									$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR'), $SlaveUser['error']);
									$this->raise('error', $SlaveUser['error'], $slave->name . ': ' . Text::_('USER') . ' ' . Text::_('UPDATE'));
								} else {
									//make sure the userinfo is available
									if (empty($SlaveUser['userinfo'])) {
										$SlaveUser['userinfo'] = $JFusionSlave->getUser($userinfo);
									}

									if (isset($options['show_unsensored'])) {
										$details = $SlaveUser['userinfo'];
									} else {
										$details = Framework::anonymizeUserinfo($SlaveUser['userinfo']);
									}

									$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE'), $details);

									//apply the clear text password to the user object
									$SlaveUser['userinfo']->password_clear = $credentials['password'];

									Framework::updateLookup($SlaveUser['userinfo'], $slave->name, $userinfo, $master->name);

									if (!in_array($slave->name, $options['skipplugin']) && $slave->dual_login == 1) {
										try {
											$SlaveSession = $JFusionSlave->createSession($SlaveUser['userinfo'], $options);
											if (!empty($SlaveSession['error'])) {
												$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $SlaveSession['error']);
												$this->raise('error', $SlaveSession['error'], $slave->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
											}
											if (!empty($SlaveSession['debug'])) {
												$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('DEBUG'), $SlaveSession['debug']);
											}
										} catch (Exception $e) {
											$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $e->getMessage());
											Framework::raiseError($e, $JFusionSlave->getJname());
										}
									}
								}
							} catch (Exception $e) {
								Framework::raiseError($e, $slave->name);
								$debugger->add('error', $e->getMessage());
							}
						}
						$success = 1;
					}
				}
























				//if logging in via Joomla backend, create a Joomla session and do nothing else to prevent lockouts
				if (empty($JFusionLoginCheckActive) && $mainframe->isAdmin()) {
					try {
						$JoomlaUserinfo = (empty($JoomlaUserinfo)) ? $JFusionJoomla->getUser($userinfo) : $JoomlaUserinfo;

						$JoomlaSession = $JFusionJoomla->createSession($JoomlaUserinfo, $options);
						if (!empty($JoomlaSession['error'])) {
							//no Joomla session could be created -> deny login
							$this->raise('error', $JoomlaSession['error'], 'joomla_int: ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
							$success = -1;
						} else {
							//make sure we have the clear password
							if (!empty($userinfo->password_clear)) {
								$status = array('error' => array(), 'debug' => array());
								try {
									$JFusionJoomla->updatePassword($userinfo, $JoomlaUserinfo, $status);
								} catch (Exception $e) {
									$JFusionJoomla->debugger->add('error', Text::_('PASSWORD_UPDATE_ERROR') . ' ' . $e->getMessage());
								}
								$JFusionJoomla->mergeStatus($status);
								$debugger->merge($JFusionJoomla->debugger->get());
							}
							$success = 1;
						}
					} catch (Exception $e) {
						Framework::raiseError($e, $JFusionJoomla->getJname());
						$debugger->add('error', $e->getMessage());
						$success = -1;
					}
				} else  {
					// See if the user has been blocked or is not activated
					/*
					if (!empty($userinfo->block) || !empty($userinfo->activation)) {
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
								Framework::raiseError($e, $slave->name);
								$debugger->add($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR'), $e->getMessage());
							}
						}
						if (!empty($userinfo->block)) {
							$debugger->add('error', Text::_('FUSION_BLOCKED_USER'));
							$this->raise('warning', Text::_('FUSION_BLOCKED_USER'));
							$success = -1;
						} else {
							$debugger->add('error', Text::_('FUSION_INACTIVE_USER'));
							$this->raise('warning', Text::_('FUSION_INACTIVE_USER'));
							$success = -1;
						}
					}*/ if (true) {} else {
						$JoomlaUser = array('userinfo' => null, 'error' => '');
						//check to see if we need to setup a Joomla session
						if ($master->name != 'joomla_int') {
							try {
								//setup the Joomla user
								$JoomlaUser = $JFusionJoomla->updateUser($userinfo, $overwrite);
								if (!empty($JoomlaUser['debug'])) {
									$debugger->set('joomla_int ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('DEBUG'), $JoomlaUser['debug']);
								}
								if (!empty($JoomlaUser['error'])) {
									//no Joomla user could be created, fatal error
									$debugger->set('joomla_int ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR'), $JoomlaUser['error']);
									$this->raise('error', $JoomlaUser['error'], 'joomla_int: ' . Text::_('USER') . ' ' . Text::_('UPDATE'));
									$success = -1;
								} else {
									if (isset($options['show_unsensored'])) {
										$details = $JoomlaUser['userinfo'];
									} else {
										$details = Framework::anonymizeUserinfo($JoomlaUser['userinfo']);
									}
									$debugger->set('joomla_int ' . Text::_('USER') . ' ' . Text::_('DETAILS'), $details);

									//create a Joomla session

									if (!in_array('joomla_int', $options['skipplugin'])) {
										try {
											$JoomlaSession = $JFusionJoomla->createSession($JoomlaUser['userinfo'], $options);
											if (!empty($JoomlaSession['error'])) {
												//no Joomla session could be created -> deny login
												$debugger->set('joomla_int ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $JoomlaSession['error']);
												$this->raise('error', $JoomlaSession['error'], 'joomla_int: ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
												$success = -1;
											}
											if (!empty($JoomlaSession['debug'])) {
												$debugger->set('joomla_int ' . Text::_('SESSION') . ' ' . Text::_('DEBUG'), $JoomlaSession['debug']);
											}
										} catch (Exception $e) {
											Framework::raiseError($e, $JFusionJoomla->getJname());
											$debugger->set('joomla_int ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $e->getMessage());
											$success = -1;
										}
									}
								}
							} catch (Exception $e) {
								Framework::raiseError($e, $JFusionJoomla->getJname());
								$debugger->add('error', $e->getMessage());
							}
						} else {
							//joomla already setup, we can copy its details from the master
							$JoomlaUser['userinfo'] = $userinfo;
						}
						if ($success === 0) {
							//setup the master session if
							//a) The master is not joomla_int and the user is logging into Joomla frontend only
							//b) The master is joomla_int and the user is logging into either Joomla frontend or backend
							/*
							if (!in_array($master->name, $options['skipplugin']) && $master->dual_login == 1 && (!isset($options['group']) || $master->name == 'joomla_int')) {
								try {
									$MasterSession = $MasterUserPlugin->createSession($userinfo, $options);

									if (!empty($MasterSession['error'])) {
										$debugger->set($master->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $MasterSession['error']);
										$this->raise('error', $MasterSession['error'], $master->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
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
									Framework::raiseError($e, $master->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
									if ($master->name == 'joomla_int') {
										$success = -1;
									}
								}
							}
							*/
							if ($success === 0) {
								//allow for joomlaid retrieval in the loginchecker
								$debugger->set('joomlaid', $JoomlaUser['userinfo']->userid);
								if ($master->name != 'joomla_int') {
									Framework::updateLookup($userinfo, $JoomlaUser['userinfo']->userid, $master->name);
								}
								//setup the other slave JFusion plugins
								$slaves = Factory::getPlugins('slave');
								foreach ($slaves as $slave) {
									try {
										$JFusionSlave = Factory::getUser($slave->name);
										$SlaveUser = $JFusionSlave->updateUser($userinfo, $overwrite);
										if (!empty($SlaveUser['debug'])) {
											$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('DEBUG'), $SlaveUser['debug']);
										}
										if (!empty($SlaveUser['error'])) {
											$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE') . ' ' . Text::_('ERROR'), $SlaveUser['error']);
											$this->raise('error', $SlaveUser['error'], $slave->name . ': ' . Text::_('USER') . ' ' . Text::_('UPDATE'));
										} else {
											//make sure the userinfo is available
											if (empty($SlaveUser['userinfo'])) {
												$SlaveUser['userinfo'] = $JFusionSlave->getUser($userinfo);
											}

											if (isset($options['show_unsensored'])) {
												$details = $SlaveUser['userinfo'];
											} else {
												$details = Framework::anonymizeUserinfo($SlaveUser['userinfo']);
											}

											$debugger->set($slave->name . ' ' . Text::_('USER') . ' ' . Text::_('UPDATE'), $details);

											//apply the clear text password to the user object
											$SlaveUser['userinfo']->password_clear = $credentials['password'];
											Framework::updateLookup($SlaveUser['userinfo'], $JoomlaUser['userinfo']->userid, $slave->name);
											if (!isset($options['group']) && $slave->dual_login == 1 && $JFusionActivePlugin != $slave->name) {
												try {
													$SlaveSession = $JFusionSlave->createSession($SlaveUser['userinfo'], $options);
													if (!empty($SlaveSession['error'])) {
														$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $SlaveSession['error']);
														$this->raise('error', $SlaveSession['error'], $slave->name . ': ' . Text::_('SESSION') . ' ' . Text::_('CREATE'));
													}
													if (!empty($SlaveSession['debug'])) {
														$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('DEBUG'), $SlaveSession['debug']);
													}
												} catch (Exception $e) {
													$debugger->set($slave->name . ' ' . Text::_('SESSION') . ' ' . Text::_('ERROR'), $e->getMessage());
													Framework::raiseError($e, $JFusionSlave->getJname());
												}
											}
										}
									} catch (Exception $e) {
										Framework::raiseError($e, $slave->name);
										$debugger->add('error', $e->getMessage());
									}
								}
								$success = 1;
							}
						}
					}
				}
			}
		} else {
			$success = -1;
		}
		ob_end_clean();
		/*
		if ($success === 1) {
				//Clean up the joomla session table
			$conf = JFactory::getConfig();
			$expire = ($conf->get('lifetime')) ? $conf->get('lifetime') * 60 : 900;

			try {
				$db =  JFactory::getDbo();
				$query = $db->getQuery(true)
				->delete('#__session')
				->where('time < ' . (int) (time() - $expire));
				$db->setQuery($query);

				$db->execute();
			} catch (Exception $e) {
			}

		    if (!$mainframe->isAdmin()) {
			    $params = Factory::getParams('joomla_int');
			    $allow_redirect_login = $params->get('allow_redirect_login', 0);
			    $redirecturl_login = $params->get('redirecturl_login', '');
			    $source_url = $params->get('source_url', '');
			    $jfc = Factory::getCookies();
			    if ($allow_redirect_login && !empty($redirecturl_login)) {
				    // only redirect if we are in the frontend and allowed and have an URL
				    $jfc->executeRedirect($source_url, $redirecturl_login);
			    } else {
				    $jfc->executeRedirect($source_url);
			    }
		    }
	    }
		*/
	    return ($success === 1);
/*

		$event = new Event('onApplicationLogin');

		$event->addArgument('credentials', $credentials);
		$event->addArgument('options', $options);

		Factory::getDispatcher()->triggerEvent($event);

		return $event->getArgument('status', false);
*/
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
	 * @param   integer  $userid   The user to load - Can be an integer or string - If string, it is converted to ID automatically
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.2
	 */
	public function logout($userid = null)
	{
		$event = new Event('onApplicationLogout');

		$event->addArgument('userid', $userid);

		Factory::getDispatcher()->triggerEvent($event);

		return $event->getArgument('status', false);
	}
}
