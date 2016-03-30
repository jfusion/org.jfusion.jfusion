<?php
function initJFusionAPI() {
	if (!defined('_JEXEC')) {
		$secretkey = 'secret passphrase';
		if ($secretkey == 'secret passphrase') {
			exit('JFusion Api Disabled');
		}
		$JFusionAPI = new JFusionAPI('', $secretkey);
		$JFusionAPI->parse();
	}
}
// add everything inside a function to prevent 'sniffing';
if (!defined('_JFUSIONAPI_INTERNAL')) {
	initJFusionAPI();
}

/**
 * JFusionAPI class
 *
 * @category   JFusion
 * @package    API
 * @subpackage JFusionAPI
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAPI {
	public $url;
	public $sid = null;

	private $secret = null;
	private $hash = null;
	private $error = array();
	private $debug = array();

	/**
	 * @param string $url
	 * @param string $secret
	 */
	public function __construct($url = '', $secret = '')
	{
		if (!function_exists('mcrypt_decrypt') || !function_exists('mcrypt_encrypt')) {
			$this->error[] = 'Missing: mcrypt';
		}
		if ($url == '') {
			if (session_id()) {
				session_write_close();
			}
			ini_set('session.use_cookies', '0');
			ini_set('session.use_trans_sid', '1');
			ini_set('session.use_only_cookies', '0');

			session_name('PHPSESSID');
			session_start();
			$this->sid = session_id();

			$this->hash = JFusionAPI::getSession('hash');
		}
		$this->setTarget($url, $secret);
	}

	/**
	 * @param string $url
	 * @param string $secret
	 *
	 * @return void
	 */
	public function setTarget($url = '', $secret = '')
	{
		$this->url = $url;
		$this->secret = $secret;
	}

	/**
	 * @return array
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * @return array
	 */
	public function getDebug() {
		return $this->debug;
	}

	/**
	 * @param $read
	 *
	 * @return string
	 */
	public function read($read)
	{
		$data = null;
		if (isset($_REQUEST[$read])) {
			$data = (string) preg_replace('/[^A-Z_]/i', '', $_REQUEST[$read]);
		}
		return $data;
	}

	/**
	 * @return bool
	 */
	private function retrieveKey()
	{
		if ($this->hash && $this->sid) return true;
		$hash = $this->get('status', 'key');
		if (empty($this->error) && $hash) {
			$this->hash = $hash;
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public function ping()
	{
		if ($this->hash && $this->sid) return true;
		$pong = $this->get('status', 'ping');
		if (empty($this->error) && $pong == 'pong') {
			return true;
		}
		return false;
	}

	/**
	 * @return void
	 */
	public function parse() {
		$type = strtolower($this->read('jftype'));
		$task = ucfirst(strtolower($this->read('jftask')));

		$data=new stdClass();
		$encrypt = false;
		//controller for when api gets called externally
		if ($type) {
			$class = $this->createClass($this->read('jfclass'));
			if ($class) {
				$function = $type . $task;
				if (method_exists($class, $function)) {
					$data->payload = $class->$function();

					$this->error = $class->error;
					$this->debug = $class->debug;

					$encrypt = $class->encrypt;
				} else {
					$this->error[] = 'Class: ' . get_class($class) . ' Method: ' . $function . ' undefined';
				}
			} else {
				$this->error[] = 'Type: ' . $type . ' Class: undefined';
			}
		} else {
			$this->error[] = 'type not defined';
		}
		$this->doOutput($data, $encrypt);
	}

	/**
	 * @param string $class
	 * @return null|JFusionAPIBase
	 */
	public function createClass($class) {
		//controller for when api gets called externally
		$class = ucfirst(strtolower($class));
		if ($class) {
			$class = 'JFusionAPI_' . $class;
			$class = new $class($this->createkey());
		} else {
			$class = null;
		}
		return $class;
	}

	/**
	 * @param $class
	 * @param $task
	 * @param $return
	 *
	 * @return string
	 */
	public function getExecuteURL($class, $task, $return)
	{
		$url = $this->url . '?jftask=' . $task . '&jfclass=' . $class . '&jftype=execute&jfreturn=' . base64_encode($return);
		if ($this->sid) {
			$url .= '&PHPSESSID=' . $this->sid;
		}
		return $url;
	}

	/**
	 * @param $class
	 * @param $task
	 * @param array $payload
	 *
	 * @return bool
	 */
	public function set($class, $task, $payload = array())
	{
		return $this->raw('set', $class, $task, $payload);
	}

	/**
	 * @param $class
	 * @param $task
	 * @param array $payload
	 *
	 * @return bool
	 */
	public function get($class, $task, $payload = array())
	{
		return $this->raw('get', $class, $task, $payload);
	}

	/**
	 * @param $class
	 * @param $task
	 * @param $payload
	 * @param string $return
	 *
	 * @return bool
	 */
	public function execute($class, $task, $payload = array(), $return = '')
	{
		if (!empty($return)) {
			header('Location: ' . $this->getExecuteURL($class, $task, $return) . '&jfpayload=' . base64_encode(json_encode($payload)));
			return true;
		} else {
			return $this->raw('execute', $class, $task, $payload);
		}
	}

	/**
	 * @param string $type
	 * @param string $class
	 * @param string $task
	 * @param array $payload

	 * @return mixed
	 */
	private function raw($type, $class, $task, $payload = array())
	{
		$key = true;
		$c = $this->createClass($class);
		if ($c && $c->encrypt) {
			$key = $this->retrieveKey();
		}
		if ($key) {
			$result = $this->post($class, $type, $task, $payload);

			$result = $this->getOutput($result);
			if (empty($this->error)) {
				return $result;
			}
		}

		return false;
	}

	/**
	 * @static
	 * @param string $class
	 * @param bool $delete
	 *
	 * @return mixed
	 */
	static function getSession($class, $delete = false)
	{
		$return = null;
		if (isset($_SESSION['JFusionAPI'])) {
			if (isset($_SESSION['JFusionAPI'][$class])) {
				$return = $_SESSION['JFusionAPI'][$class];
				if ($delete) {
					unset($_SESSION['JFusionAPI'][$class]);
				}
			}
		}
		return $return;
	}

	/**
	 * @static
	 * @param string $class
	 * @param mixed $value
	 */
	static function setSession($class, $value)
	{
		$_SESSION['JFusionAPI'][$class] = $value;
	}

	/**
	 * @return \stdClass
	 */
	private function createkey()
	{
		$keyinfo = new stdClass;
		if (!$this->hash) {
			$this->hash = JFusionAPI::getSession('hash');
		}
		$keyinfo->secret = md5($this->secret);
		$keyinfo->hash = $this->hash;
		return $keyinfo;
	}

	/**
	 * @static
	 * @param $keyinfo
	 * @param array $payload
	 *
	 * @return null|string
	 */
	public static function encrypt($keyinfo, $payload=array())
	{
		if (isset($keyinfo->secret) && isset($keyinfo->hash) && function_exists('mcrypt_encrypt')) {
			$encrypted = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $keyinfo->secret, json_encode($payload), MCRYPT_MODE_NOFB, $keyinfo->hash)));
		} else {
			$encrypted = null;
		}
		return $encrypted;
	}

	/**
	 * @static
	 * @param $keyinfo
	 * @param $payload
	 *
	 * @return bool|array
	 */
	public static function decrypt($keyinfo, $payload)
	{
		if (isset($keyinfo->secret) && isset($keyinfo->hash) && function_exists('mcrypt_decrypt')) {
			ob_start();
			$decrypted = json_decode(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $keyinfo->secret, base64_decode($payload), MCRYPT_MODE_NOFB, $keyinfo->hash)), true);
			ob_end_clean();
		} else {
			$decrypted = false;
		}
		return $decrypted;
	}

	/**
	 * @param string $class
	 * @param string $type
	 * @param string $task
	 * @param array $payload
	 *
	 * @return string|bool
	 */
	private function post($class, $type, $task, $payload = array())
	{
		$this->error = array();
		$this->debug = array();
		$result = false;
		//check to see if cURL is loaded
		if (!function_exists('curl_init')) {
			$this->error[] = 'JfusionAPI: sorry cURL is needed for JFusionAPI';
		} elseif (!function_exists('mcrypt_decrypt') || !function_exists('mcrypt_encrypt')) {
			$this->error[] = 'Missing: mcrypt';
		} else {
			$post=array();
			if ($this->sid) {
				$post['PHPSESSID'] = $this->sid;
			}
			$post['jfclass'] = $class;
			$post['jftype'] = strtolower($type);
			$post['jftask'] = $task;

			if (!empty($payload)) {
				$post['jfpayload'] = JFusionAPI::encrypt($this->createkey(), $payload);
			}

			$crl = curl_init();
			curl_setopt($crl, CURLOPT_URL, $this->url);
			curl_setopt($crl, CURLOPT_HEADER, 0);
			curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($crl, CURLOPT_TIMEOUT, 10);
			curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($crl, CURLOPT_POST ,1);
			curl_setopt($crl, CURLOPT_POSTFIELDS , $post);
			$result = curl_exec($crl);
			if (curl_error($crl)) {
				$this->error[] = curl_error($crl);
			}
			curl_close($crl);
		}
		return $result;
	}

	/**
	 * @param $output
	 * @param bool $encrypt
	 *
	 * @return void
	 */
	private function doOutput($output, $encrypt = false)
	{
		$output->PHPSESSID = $this->sid;
		$output->error = $this->error;
		$output->debug = $this->debug;
		$result = null;
		if ($encrypt) {
			$result = JFusionAPI::encrypt($this->createkey() , $output);
			if ($result == null) {
				$output->error = 'Encryption failed';
			}
		}
		if ($result == null) {
			$result = base64_encode(json_encode($output));
		}
		echo $result;
		exit();
	}

	/**
	 * @param $input
	 *
	 * @return bool|mixed
	 */
	private function getOutput($input)
	{
		$return = JFusionAPI::decrypt($this->createkey() , $input);
		if (!is_array($return)) {
			ob_start();
			$return = json_decode(trim(base64_decode($input)), true);
			ob_end_clean();
		}
		if (!is_array($return)) {
			$this->error[] = 'JfusionAPI: error output: ' . $input;
			return false;
		} else if (isset($return->PHPSESSID)) {
			$this->sid = $return->PHPSESSID;
		}

		if (isset($return->debug)) {
			$this->debug = $return->debug;
		}
		if (isset($return->error) && !empty($return->error)) {
			return false;
		} else if (isset($return->payload)) {
			return $return->payload;
		}
		return true;
	}
}

/**
 *
 */
class JFusionAPIBase {
	public $encrypt = true;
	public $payload = array();
	public $error = array();
	public $debug = array();
	public $key = null;

	/**
	 * @param $key
	 */
	public function __construct($key)
	{
		$this->key = $key;
		$this->readPayload($this->encrypt);
	}

	/**
	 * @param $encrypt
	 *
	 * @return bool
	 */
	protected function readPayload($encrypt)
	{
		if (!$encrypt && isset($_GET['jfpayload'])) {
			ob_start();
			$payload = json_decode(trim(base64_decode($_GET['jfpayload'])), true);
			ob_end_clean();
		} else if ($encrypt && isset($_POST['jfpayload'])) {
			$payload = JFusionAPI::decrypt($this->key , $_POST['jfpayload']);
		}
		if (isset($payload) && is_array($payload)) {
			$this->payload = $payload;
			return true;
		}
		return false;
	}

	/**
	 * @param $payload
	 *
	 * @return string
	 */
	protected function buildPayload($payload)
	{
		return base64_encode(json_encode($payload));
	}

	/**
	 * @param string|null $url Url of where to redirect to
	 *
	 * @return void
	 */
	protected function doExit($url = null) {
		if ($url && isset($_GET['jfreturn'])) {
			$url .= '&jfreturn=' . $_GET['jfreturn'];
		} else if (isset($_GET['jfreturn'])) {
			$url = base64_decode($_GET['jfreturn']);
		}

		if ($url) {
			header('Location: ' . $url);
		}
		exit();
	}
}

/**
 *
 */
class JFusionAPI_Status extends JFusionAPIBase {
	public $encrypt = false;

	/**
	 * @return array
	 */
	public function getKey()
	{
//      $hash = sha1($hash); //to improve variance
//		srand((double) microtime() * 1000000);
//		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_NOFB), MCRYPT_RAND);
		$iv = '';

		$seed = hexdec(substr(md5(microtime()), -8)) & 0x7fffffff;
		mt_srand($seed);
		for($i = 0; $i < 32; $i++) {
			$iv .= chr(mt_rand(0, 255));
		}

		JFusionAPI::setSession('hash', $iv);
		return $iv;
	}

	/**
	 * @return array
	 */
	public function getPing()
	{
		return 'pong';
	}
}

/**
 *
 */
class JFusionAPI_User extends JFusionAPIBase {
	/**
	 * @return mixed
	 */
	public function getUser()
	{

		$joomla = JFusionAPIInternal::getInstance(true);
		$plugin = isset($this->payload['plugin']) ? $this->payload['plugin'] : 'joomla_int';

		$userPlugin = JFusionFactory::getUser($plugin);
		return $userPlugin->getUser($this->payload['username']);
	}

	/**
	 * @return bool
	 */
	public function setLogin()
	{
		if(!empty($this->payload['username']) && !empty($this->payload['password'])) {
			$session['login'] = $this->payload;
			JFusionAPI::setSession('user', $session);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @return void
	 */
	public function executeLogin()
	{
		$session = JFusionAPI::getSession('user', true);
		if (isset($session['login'])) {
			$userinfo = $session['login'];
			if (is_array($userinfo)) {
				$joomla = JFusionAPIInternal::getInstance();

				if (isset($userinfo['plugin'])) {
					$joomla->setActivePlugin($userinfo['plugin']);
				}
				$joomla->login($userinfo['username'], $userinfo['password']);
			}
		}
		$this->doExit();
	}

	/**
	 * @return void
	 */
	public function executeLogout()
	{
		if ($this->readPayload(false)) {
			$joomla = JFusionAPIInternal::getInstance();

			if (isset($userinfo['plugin'])) {
				$joomla->setActivePlugin($userinfo['plugin']);
			}

			$username = isset($this->payload['username']) ? $this->payload['username'] : null;
			$joomla->logout($username);
		}
		$this->doExit();
	}

	/**
	 * @return void
	 */
	public function executeRegister()
	{
		if ($this->payload) {
			$userinfo = null;
			if (is_array($this->payload['userinfo'])) {
				$userinfo = new stdClass();
				foreach ($this->payload['userinfo'] as $key => $value){
					$userinfo->$key = $value;
				}
			}
			if ($userinfo instanceof stdClass) {
				$joomla = JFusionAPIInternal::getInstance();

				if (isset($userinfo->plugin)) {
					$joomla->setActivePlugin($userinfo->plugin);
				}

				if (isset($this->payload['overwrite']) && $this->payload['overwrite']) {
					$overwrite = 1;
				} else {
					$overwrite = 0;
				}

				$joomla->register($userinfo, $overwrite);

				$this->error = $joomla->error;
				$this->debug = $joomla->debug;
			} else {
				$this->error[] = 'invalid payload';
			}
		} else {
			$this->error[] = 'invalid payload';
		}
	}

	/**
	 * @return void
	 */
	public function executeUpdate()
	{
		if ($this->payload) {
			$userinfo = null;
			if (isset($this->payload['userinfo']) && is_array($this->payload['userinfo'])) {
				$userinfo = new stdClass();
				foreach ($this->payload['userinfo'] as $key => $value){
					$userinfo->$key = $value;
				}
			}
			if ($userinfo instanceof stdClass) {
				$joomla = JFusionAPIInternal::getInstance();

				if (isset($this->payload['overwrite']) && $this->payload['overwrite']) {
					$overwrite = 1;
				} else {
					$overwrite = 0;
				}

				$joomla->update($userinfo, $overwrite);

				$this->error = $joomla->error;
				$this->debug = $joomla->debug;
			} else {
				$this->error[] = 'invalid payload';
			}
		} else {
			$this->error[] = 'invalid payload';
		}
	}

	/**
	 * @return void
	 */
	public function executeDelete()
	{
		if ($this->payload) {
			if (isset($this->payload['userid'])) {
				$joomla = JFusionAPIInternal::getInstance();

				$joomla->delete($this->payload['userid']);

				$this->error = $joomla->error;
				$this->debug = $joomla->debug;
			} else {
				$this->error[] = 'invalid payload';
			}
		} else {
			$this->error[] = 'invalid payload';
		}
	}
}

/**
 *
 */
class JFusionAPI_Cookie extends JFusionAPIBase {
	/**
	 * @return bool
	 */
	public function setCookies()
	{
		if (is_array($this->payload)) {
			$session['cookies'] = $this->payload;
			JFusionAPI::setSession('cookie', $session);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @return void
	 */
	public function executeCookies()
	{
		if ($this->readPayload(false)) {
			$session = JFusionAPI::getSession('cookie', true);

			if ( isset($session['cookies']) && count($session['cookies']) && is_array($session['cookies']) ) {
				foreach($session['cookies'] as $cookie ) {
					setcookie($cookie['name'], $cookie['value'], $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
				}
			}
			if ( count($this->payload['url']) ) {
				foreach($this->payload['url'] as $key => $value ) {
					unset($this->payload['url'][$key]);

					$this->payload = $this->buildPayload($this->payload);

					$this->doExit($key . '?jfpayload=' . $this->payload . '&PHPSESSID=' . $value . '&jftype=execute&jfclass=cookie&jftask=cookies');
				}
			} else {
				$this->doExit();
			}
		}
		exit;
	}
}

/**
 * Intended for direct integration with joomla (loading the joomla framework directly in to other software.)
 */
class JFusionAPIInternal extends JFusionAPIBase {
	/**
	 * Global joomla object
	 *
	 * @var    JFusionAPIInternal
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
	 * @see     JFusionAPIInternal
	 * @since   11.1
	 */
	public static function getInstance($start = false)
	{
		if (!self::$joomla)
		{
			self::$joomla = new JFusionAPIInternal();
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
		if (!defined('_JEXEC') && !defined('JPATH_PLATFORM')) {
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

			define('JPATH_PLATFORM', JPATH_LIBRARIES);

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
		$mainframe = $this->getApplication();

		if ($this->activePlugin) {
			global $JFusionActivePlugin;
			$JFusionActivePlugin = $this->activePlugin;
		}

		// do the login
		$credentials = array('username' => $username, 'password' => $password);
		$options = array('entry_url' => JURI::root() . 'index.php?option=com_user&task=login', 'silent' => true);

		$options['remember'] = $remember;

		$mainframe->login($credentials, $options);

		//clean up the joomla session object before continuing
		$session = JFactory::getSession();
		$id = $session->getId();
		$session_data = session_encode();
		$session->close();

		//if we are not frameless, then we need to manually update the session data as on some servers, this data is getting corrupted
		//by php session_write_close and thus the user is not logged into Joomla.  php bug?
		if (!defined('IN_JOOMLA') && $id) {
			$jdb = JFactory::getDbo();

			$query = $jdb->getQuery(true);

			$query->select('*')
				->from('#__session')
				->where('session_id = ' . $jdb->quote($id));


			$jdb->setQuery($query, 0 , 1);

			$data = $jdb->loadObject();
			if ($data) {
				$data->time = time();
				$jdb->updateObject('#__session', $data, 'session_id');
			} else {
				// if load failed then we assume that it is because
				// the session doesn't exist in the database
				// therefore we use insert instead of store
				$app = JFactory::getApplication();

				$data = new stdClass();
				$data->session_id = $id;
				$data->data = $session_data;
				$data->client_id = $app->getClientId();
				$data->username = '';
				$data->guest = 1;
				$data->time = time();

				$jdb->insertObject('#__session', $data, 'session_id');
			}
		}
	}

	/**
	 * @param null|string $username
	 *
	 * @return void
	 */
	public function logout($username=null)
	{
		$mainframe = $this->getApplication();

		if ($this->activePlugin) {
			global $JFusionActivePlugin;
			$JFusionActivePlugin = $this->activePlugin;
		}

		$user = new stdClass;
		if ($username) {
			if ($this->activePlugin) {
				$lookupUser = JFusionFunction::lookupUser($this->activePlugin, null, false, $username);
				if (!empty($lookupUser)) {
					$user = JFactory::getUser($lookupUser->id);
				}
			} else {
				$user = JFactory::getUser($username);
			}
		}
		if (isset($user->userid) && $user->userid) {
			$mainframe->logout($user->userid);
		} else {
			$mainframe->logout();
		}

		// clean up session
		$session = JFactory::getSession();
		$session->close();
	}

	/**
	 * @param object $userinfo
	 *
	 * @return void
	 */
	public function register($userinfo)
	{
		$this->getApplication();

		$plugins = JFusionFunction::getSlaves();
		$plugins[] = JFusionFunction::getMaster();

		if ($this->activePlugin) {
			foreach ($plugins as $key => $plugin) {
				if ($plugin->name == $this->activePlugin) {
					unset($plugins[$key]);
				}
			}
		}

		foreach ($plugins as $plugin) {
			try {
				$PluginUserUpdate = JFusionFactory::getUser($plugin->name);
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

		$plugins = JFusionFunction::getSlaves();
		$plugins[] = JFusionFunction::getMaster();

		foreach ($plugins as $key => $plugin) {
			if (!array_key_exists($plugin->name, $userinfo)) {
				unset($plugins[$key]);
			}
		}
		foreach ($plugins as $plugin) {
			try {
				$PluginUserUpdate = JFusionFactory::getUser($plugin->name);

				$updateinfo = null;
				if (is_array($userinfo[$plugin->name])) {
					$updateinfo = new stdClass();
					foreach ($userinfo[$plugin->name] as $key => $value){
						$updateinfo->$key = $value;
					}
				}

				if ($updateinfo instanceof stdClass) {
					$lookupUser = JFusionFunction::lookupUser($plugin->name, '', false, $updateinfo->username);

					if($lookupUser) {
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
		$this->getApplication();

		$user = JUser::getInstance($userid);

		if ($user) {
			if ($user->delete()) {
				$this->debug[] = 'user deleted: ' . $userid;
			} else {
				$this->error[] = 'Delete user failed: ' . $userid;
			}
		} else {
			$this->error[] = 'invalid user';
		}
	}
}