<?php namespace JFusion\Api;
use JFusion\Factory;
use JFusion\User\Userinfo;
use Joomla\Event\Event;

/**
 * Intended for direct integration with joomla (loading the joomla framework directly in to other software.)
 */
class Platform extends Base {
	/**
	 * Global platform type objects object
	 *
	 * @var Platform[]
	 */
	public static $instances = array();

	/**
	 * Global joomla object
	 *
	 * @var Platform
	 */
	public static $instance = null;

	var $activePlugin = null;

	private $globals = array();

	/**
	 *
	 */
	public function __construct()
	{
	}

	/**
	 * Get a platform type object.
	 *
	 * @param string $type
	 *
	 * @return Platform
	 */
	public static function getTypeInstance($type)
	{
		$type = ucfirst(strtolower($type));
		if (!isset(self::$instances[$type])) {
			$class = 'Platform_' . $type;
			self::$instances[$type] = new $class();
		}
		return self::$instances[$type];
	}

	/**
	 * Get a platform object.
	 *
	 * @return Platform
	 */
	public static function getInstance()
	{
		if (!isset(self::$instance)) {
			self::$instance = new Platform();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public function backupGlobal()
	{
		foreach ($GLOBALS as $n => $v) {
			$this->globals[$n] = $v;
		}
	}

	/**
	 * @return void
	 */
	public function restoreGlobal()
	{
		foreach ($this->globals as $n => $v) {
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
	 * @param Userinfo $userinfo
	 *
	 * @return void
	 */
	public function register(Userinfo $userinfo)
	{
		$event = new Event('onPlatformUserRegister');
		$event->addArgument('userinfo', $userinfo);
		$event->addArgument('activePlugin', $this->activePlugin);

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

	/**
	 * @param array $userinfo
	 * @param $overwrite
	 *
	 * @return void
	 */
	public function update($userinfo, $overwrite)
	{
		$event = new Event('onPlatformUserUpdate');
		$event->addArgument('userinfo', $userinfo);
		$event->addArgument('overwrite', $overwrite);
		$event->addArgument('activePlugin', $this->activePlugin);

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

	/**
	 * @param int $userid
	 *
	 * @return void
	 */
	public function delete($userid)
	{
		$event = new Event('onPlatformUserDelete');
		$event->addArgument('userid', $userid);
		$event->addArgument('activePlugin', $this->activePlugin);

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