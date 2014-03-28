<?php namespace JFusion\Api;
use Exception;
use JFusion\Factory;
use JFusion\Framework;
use Joomla\Event\Event;
use stdClass;

/**
 * Intended for direct integration with joomla (loading the joomla framework directly in to other software.)
 */
class Platform extends Base {
	/**
	 * Global joomla object
	 *
	 * @var    Platform
	 * @since  11.1
	 */
	public static $joomla = null;

	var $activePlugin = null;

	private $globals_backup = array();

	/**
	 *
	 */
	public function __construct()
	{
	}

	/**
	 * Get a joomla object.
	 *
	 * @see     ApiInternal
	 * @since   11.1
	 */
	public static function getInstance($start = false)
	{
		if (!self::$joomla)
		{
			self::$joomla = new Platform();
		}
		if ($start) {
			self::$joomla->getApplication();
		}
		return self::$joomla;
	}

	/**
	 * @return JApplication|JApplicationCms
	 */
	public function getApplication()
	{
		if (!defined('_JEXEC')) {
			/**
			 * @TODO determine if we really need session_write_close or if it need to be selectable
			 */
//			session_write_close();
//			session_id(null);

			// trick joomla into thinking we're running through joomla
			define('_JEXEC', true);
			define('DS', DIRECTORY_SEPARATOR);
			define('JPATH_BASE', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');

			// load joomla libraries
			require_once JPATH_BASE . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'defines.php';
			define('_JREQUEST_NO_CLEAN', true); // we don't want to clean variables as it can "corrupt" them for some applications, it also clear any globals used...

			if (!class_exists('JVersion')) {
				include_once(JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'cms' . DIRECTORY_SEPARATOR . 'version' . DIRECTORY_SEPARATOR . 'version.php');
			}

			include_once JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'import.php';
			require_once JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'loader.php';

			$autoloaders = spl_autoload_functions();
			if ($autoloaders && in_array('__autoload', $autoloaders)) {
				spl_autoload_register('__autoload');
			}

			require_once JPATH_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'framework.php';
			jimport('joomla.base.object');
			jimport('joomla.factory');
			jimport('joomla.filter.filterinput');
			jimport('joomla.error.error');
			jimport('joomla.event.dispatcher');
			jimport('joomla.event.plugin');
			jimport('joomla.plugin.helper');
			jimport('joomla.utilities.arrayhelper');
			jimport('joomla.environment.uri');
			jimport('joomla.environment.request');
			jimport('joomla.user.user');
			jimport('joomla.html.parameter');
			// JText cannot be loaded with jimport since it's not in a file called text.php but in methods
			JLoader::register('JText', JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'methods.php');
			JLoader::register('JRoute', JPATH_LIBRARIES . DIRECTORY_SEPARATOR . 'joomla' . DIRECTORY_SEPARATOR . 'methods.php');

			//load JFusion's libraries
			require_once JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'import.php';
		} elseif (!defined('IN_JOOMLA')) {
			define('IN_JOOMLA', 1);
			JFusionFunction::reconnectJoomlaDb();
		}

		$mainframe = JFactory::getApplication('site');
		$GLOBALS['mainframe'] = $mainframe;
		return $mainframe;
	}

	/**
	 * @return void
	 */
	public function backupGlobal()
	{
		foreach ($GLOBALS as $n => $v) {
			$this->globals_backup[$n] = $v;
		}
	}

	/**
	 * @return void
	 */
	public function restoreGlobal()
	{
		foreach ($this->globals_backup as $n => $v) {
			$GLOBALS[$n] = $v;
		}
	}

	/**
	 * @param string $plugin
	 *
	 * @return void
	 */
	public function setActivePlugin($plugin)
	{
		$this->activePlugin = $plugin;
	}

	/**
	 * @param string $username
	 * @param string $password
	 * @param int $remember
	 *
	 * @return void
	 */
	public function login($username, $password, $remember = 1)
	{
		$event = new Event('onPlatformLogin');
		$event->addArgument('username', $username);
		$event->addArgument('password', $password);
		$event->addArgument('remember', $remember);
		$event->addArgument('activePlugin', $this->activePlugin);

		Factory::getDispatcher()->triggerEvent($event);
	}

	/**
	 * @param null|string $username
	 *
	 * @return void
	 */
	public function logout($username=null)
	{
		$event = new Event('onPlatformLogout');
		$event->addArgument('username', $username);
		$event->addArgument('activePlugin', $this->activePlugin);

		Factory::getDispatcher()->triggerEvent($event);
	}

	/**
	 * @param object $userinfo
	 *
	 * @return void
	 */
	public function register($userinfo)
	{
		$this->getApplication();

		$plugins = Framework::getSlaves();
		$plugins[] = Framework::getMaster();

		if ($this->activePlugin) {
			foreach ($plugins as $key => $plugin) {
				if ($plugin->name == $this->activePlugin) {
					unset($plugins[$key]);
				}
			}
		}

		foreach ($plugins as $plugin) {
			try {
				$PluginUserUpdate = Factory::getUser($plugin->name);
				$existinguser = $PluginUserUpdate->getUser($userinfo);

				if(!$existinguser) {
					$status = array('error' => array(), 'debug' => array());
					$PluginUserUpdate->createUser($userinfo, $status);
					$PluginUserUpdate->mergeStatus($status);
					$status = $PluginUserUpdate->debugger->get();

					foreach ($status['error'] as $error) {
						$this->error[][$plugin->name] = $error;
					}
					foreach ($status['debug'] as $debug) {
						$this->debug[][$plugin->name] = $debug;
					}
				} else {
					$this->error[][$plugin->name] = 'user already exsists';
				}
			} catch (Exception $e) {
				$this->error[][$plugin->name] = $e->getMessage();
			}
		}
	}

	/**
	 * @param array $userinfo
	 * @param $overwrite
	 *
	 * @return void
	 */
	public function update($userinfo, $overwrite)
	{
		$this->getApplication();

		$plugins = Framework::getSlaves();
		$plugins[] = Framework::getMaster();

		foreach ($plugins as $key => $plugin) {
			if (!array_key_exists($plugin->name, $userinfo)) {
				unset($plugins[$key]);
			}
		}
		foreach ($plugins as $plugin) {
			try {
				$PluginUserUpdate = Factory::getUser($plugin->name);
				$updateinfo = $userinfo[$plugin->name];

				if ($updateinfo instanceof stdClass) {
					$userlookup = new stdClass();
					$userlookup->username = $updateinfo->username;

					$userlookup = $PluginUserUpdate->lookupUser($userlookup, $plugin->name);

					if($userlookup) {
						$existinguser = $PluginUserUpdate->getUser($updateinfo->username);

						foreach ($updateinfo as $key => $value) {
							if ($key != 'userid' && isset($existinguser->$key)) {
								if ($existinguser->$key != $updateinfo->$key) {
									$existinguser->$key = $updateinfo->$key;
								}
							}
						}

						$this->debug[][$plugin->name] = $PluginUserUpdate->updateUser($existinguser, $overwrite);
					} else {
						$this->error[][$plugin->name] = 'invalid user';
					}
				} else {
					$this->error[][$plugin->name] = 'invalid update user';
				}
			} catch (Exception $e) {
				$this->error[][$plugin->name] = $e->getMessage();
			}
		}
	}

	/**
	 * @param int $userid
	 *
	 * @return void
	 */
	public function delete($userid)
	{
		$event = new Event('onPlatformUserDelete');
		$event->addArgument('userid', $userid);

		Factory::getDispatcher()->triggerEvent($event);

		$debug = $event->getArgument('debug', null);
		if ($debug) {
			$this->debug[] = $debug;
		}

		$error = $event->getArgument('error', null);
		if ($error) {
			$this->error[] = $error;
		}
	}
}