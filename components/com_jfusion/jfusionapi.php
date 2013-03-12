<?php
function initJFusionAPI() {
	if (!defined('_JEXEC')) {
		$secretkey = 'secret passphrase';
		if ($secretkey == 'secret passphrase') {
			exit('JFusion Api Disabled');
		}
		$JFusionAPI = new JFusionAPI('',$secretkey);
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
			$data = (string) preg_replace( '/[^A-Z_]/i', '', $_REQUEST[$read]);
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

		$data=array();
		$encrypt = false;
		//controller for when api gets called externally
		if ($type) {
			$class = $this->createClass($this->read('jfclass'));
			if ($class) {
				$function = $type.$task;
				if (method_exists( $class , $function )) {
					$data['payload'] = $class->$function();

					$this->error = $class->error;
					$this->debug = $class->debug;

					$encrypt = $class->encrypt;
				} else {
					$this->error[] = 'Class: '.get_class($class).' Method: '.$function.' undefined';
				}
			} else {
				$this->error[] = 'Type: '.$type.' Class: undefined';
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
			$class = 'JFusionAPI_'.$class;
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
	public function getExecuteURL($class,$task,$return)
	{
		$url = $this->url.'?jftask='.$task.'&jfclass='.$class.'&jftype=execute&jfreturn='.base64_encode($return);
		if ($this->sid) {
			$url .= '&PHPSESSID='.$this->sid;
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
	public function set($class, $task, $payload=array())
	{
		return $this->raw('set',$class, $task, $payload);
	}

	/**
	 * @param $class
	 * @param $task
	 * @param array $payload
	 *
	 * @return bool
	 */
	public function get($class, $task, $payload=array())
	{
		return $this->raw('get',$class, $task, $payload);
	}

	/**
	 * @param $class
	 * @param $task
	 * @param array $payload
	 * @param string $return
	 *
	 * @return bool
	 */
	public function execute($class, $task, $payload=array(), $return='')
	{
		if (!empty($return)) {
			header('Location: '.$this->getExecuteURL($class,$task,$return).'&jfpayload='.base64_encode(serialize($payload)));
			return true;
		} else {
			return $this->raw('execute',$class, $task, $payload);
		}
	}

	/**
	 * @param string $type
	 * @param string $class
	 * @param string $task
	 * @param array $payload

	 * @return mixed
	 */
	private function raw($type, $class, $task, $payload=array())
	{
		$key = true;
		$c = $this->createClass($class);
		if ($c && $c->encrypt) {
			$key = $this->retrieveKey();
		}
		if ($key) {
			$result = $this->post($class,$type,$task,$payload);

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
	static function getSession($class,$delete=false)
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
	static function setSession($class,$value)
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
			$encrypted = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $keyinfo->secret, serialize($payload), MCRYPT_MODE_NOFB, $keyinfo->hash)));
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
			$decrypted = @unserialize(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $keyinfo->secret, base64_decode($payload), MCRYPT_MODE_NOFB, $keyinfo->hash)));
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
	private function post($class,$type,$task,$payload=array())
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
				$post['jfpayload'] = JFusionAPI::encrypt($this->createkey(),$payload);
			}

			$crl = curl_init();
			curl_setopt($crl, CURLOPT_URL,$this->url);
			curl_setopt($crl, CURLOPT_HEADER, 0);
			curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5);
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
	private function doOutput($output,$encrypt=false)
	{
		$output['PHPSESSID'] = $this->sid;
		$output['error'] = $this->error;
		$output['debug'] = $this->debug;
		$result = null;
		if ($encrypt) {
			$result = JFusionAPI::encrypt($this->createkey() , $output);
			if ($result == null) {
				$output['error'] = 'Encryption failed';
			}
		}
		if ($result == null) {
			$result = base64_encode(serialize($output));
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
			$return = @unserialize(trim(base64_decode($input)));
		}
		if (!is_array($return)) {
			$this->error[] = 'JfusionAPI: error output: '. $input;
			return false;
		} else if (isset($return['PHPSESSID'])) {
			$this->sid = $return['PHPSESSID'];
		}

		if (isset($return['debug'])) {
			$this->debug = $return['debug'];
		}
		if (isset($return['error']) && !empty($return['error'])) {
			return false;
		} else if (isset($return['payload'])) {
			return $return['payload'];
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
			$payload = @unserialize(trim(base64_decode($_GET['jfpayload'])));
		} else if ($encrypt && isset($_POST['jfpayload'])) {
			$payload = JFusionAPI::decrypt($this->key , $_POST['jfpayload']);
		}
		if (isset($payload) && is_array($payload) ) {
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
		return base64_encode(serialize($payload));
	}

	/**
	 * @param string|null $url Url of where to redirect to
	 *
	 * @return void
	 */
	protected function doExit($url = null) {
		if ($url && isset($_GET['jfreturn'])) {
			$url .= '&jfreturn='.$_GET['jfreturn'];
		} else if (isset($_GET['jfreturn'])) {
			$url = base64_decode($_GET['jfreturn']);
		}

		if ( $url ) {
			header('Location: '.$url);
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
			$iv .= chr(mt_rand(0,255));
		}

		JFusionAPI::setSession('hash',$iv);
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
		$mainframe = JFusionAPIInternal::startJoomla();
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
			JFusionAPI::setSession('user',$session);
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
		$session = JFusionAPI::getSession('user',true);
		if (isset($session['login'])) {
			$userinfo = $session['login'];
			if (is_array($userinfo)) {
				$joomla = new JFusionAPIInternal();

				if (isset($userinfo['plugin'])) {
					$joomla->setActivePlugin($userinfo['plugin']);
				}
				$joomla->login($userinfo['username'],$userinfo['password']);
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
			$joomla = new JFusionAPIInternal();

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
		if ( $this->payload ) {
			if ( isset($this->payload['userinfo']) && get_class($this->payload['userinfo']) == 'stdClass') {

				$joomla = new JFusionAPIInternal();

				if (isset($userinfo['plugin'])) {
					$joomla->setActivePlugin($userinfo['plugin']);
				}

				if (isset($this->payload['overwrite']) && $this->payload['overwrite']) {
					$overwrite = 1;
				} else {
					$overwrite = 0;
				}

				$joomla->register($this->payload['userinfo'],$overwrite);

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
		if ( $this->payload ) {
			if ( isset($this->payload['userinfo']) && is_array($this->payload['userinfo'])) {
				$joomla = new JFusionAPIInternal();

				if (isset($this->payload['overwrite']) && $this->payload['overwrite']) {
					$overwrite = 1;
				} else {
					$overwrite = 0;
				}

				$joomla->update($this->payload['userinfo'],$overwrite);

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
		if ( $this->payload ) {
			if ( isset($this->payload['userid']) ) {
				$joomla = new JFusionAPIInternal();

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
			JFusionAPI::setSession('cookie',$session);
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
			$session = JFusionAPI::getSession('cookie',true);

			if ( isset($session['cookies']) && count($session['cookies']) && is_array($session['cookies']) ) {
				foreach($session['cookies'] as $key => $value ) {
					header('Set-Cookie: '.$value, false);
				}
			}

			if ( count($this->payload['url']) ) {
				foreach($this->payload['url'] as $key => $value ) {
					unset($this->payload['url'][$key]);

					$this->payload = $this->buildPayload($this->payload);

					$this->doExit($key.'?jfpayload='.$this->payload.'&PHPSESSID='.$value.'&jftype=execute&jfclass=cookie&jftask=cookies');
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
	var $activePlugin = null;
	/**
	 *
	 */
	public function __construct()
	{
	}

	/**
	 * @return JApplication
	 */
	public static function startJoomla()
	{
		$old = error_reporting(0);
		if (!defined('_JEXEC')) {
			/**
			 * todo: determin if we really need session_write_close or if it need to be selectable
			 */
			session_write_close();
			// trick joomla into thinking we're running through joomla
			define('_JEXEC', true);
			define('DS', DIRECTORY_SEPARATOR);
			define('JPATH_BASE', dirname(__FILE__). DS . '..'. DS . '..');

			// load joomla libraries
			require_once JPATH_BASE . DS . 'includes' . DS . 'defines.php';
			define('_JREQUEST_NO_CLEAN', true); // we dont want to clean variables as it can "commupt" them for some applications, it also clear any globals used...

			if (!class_exists('JVersion')) {
				if (file_exists(JPATH_LIBRARIES.DS.'cms'.DS.'version'.DS.'version.php')) {
					include_once(JPATH_LIBRARIES.DS.'cms'.DS.'version'.DS.'version.php');
				} elseif (file_exists(JPATH_LIBRARIES.DS.'joomla'.DS.'version.php')) {
					include_once(JPATH_LIBRARIES.DS.'joomla'.DS.'version.php');
				} elseif (file_exists(JPATH_ROOT.DS.'includes'.DS.'version.php')) {
					include_once(JPATH_ROOT.DS.'includes'.DS.'version.php');
				}
			}

			include_once JPATH_LIBRARIES.'/import.php';

			require_once JPATH_LIBRARIES . DS . 'loader.php';

			$autoloaders = spl_autoload_functions();
			if ($autoloaders && in_array('__autoload', $autoloaders)) {
				spl_autoload_register('__autoload');
			}

			require_once JPATH_ROOT . DS . 'includes' . DS . 'framework.php';
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
			JLoader::register('JText', JPATH_LIBRARIES . DS . 'joomla' . DS . 'methods.php');
			JLoader::register('JRoute', JPATH_LIBRARIES . DS . 'joomla' . DS . 'methods.php');

			//load JFusion's libraries
			require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS  . 'models' . DS . 'model.factory.php';
			require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS  . 'models' . DS . 'model.jfusion.php';
		} elseif (!defined('IN_JOOMLA')) {
			define('IN_JOOMLA', 1);
			JFusionFunction::reconnectJoomlaDb();
		}

		$mainframe = JFactory::getApplication('site');
		$GLOBALS['mainframe'] = $mainframe;
		error_reporting($old);
		return $mainframe;
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
	public function login($username,$password,$remember = 1)
	{
		$mainframe = self::startJoomla();

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
		//by php's session_write_close and thus the user is not logged into Joomla.  php bug?
		if (!defined('IN_JOOMLA')) {
			/**
			 * @ignore
			 * @var $session_table JTableSession
			 */
			$session_table = JTable::getInstance('session');
			if ($session_table->load($id)) {
				$session_table->data = $session_data;
				$session_table->store();
			} else {
				// if load failed then we assume that it is because
				// the session doesn't exist in the database
				// therefore we use insert instead of store
				$app = JFactory::getApplication();
				$session_table->data = $session_data;
				$session_table->insert($id, $app->getClientId());
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
		$mainframe = self::startJoomla();

		if ($this->activePlugin) {
			global $JFusionActivePlugin;
			$JFusionActivePlugin = $this->activePlugin;
		}

		$user = new stdClass;
		if ($username) {
			if ($this->activePlugin) {
				$lookupUser = JFusionFunction::lookupUser($this->activePlugin,null,false,$username);
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

		//redirect to prevent fatal errors on some servers
		$uri = JURI::getInstance();
		//add a variable to ensure refresh
		$link = $uri->toString();
	}

	/**
	 * @param object $userinfo
	 *
	 * @return void
	 */
	public function register($userinfo)
	{
		$mainframe = self::startJoomla();

		$plugins = JFusionFunction::getSlaves();
		$plugins[] = JFusionFunction::getMaster();

		if ( $this->activePlugin ) {
			foreach ($plugins as $key => $plugin) {
				if ($plugin->name == $this->activePlugin) {
					unset($plugins[$key]);
				}
			}
		}

		foreach ($plugins as $plugin) {
			$PluginUserUpdate = JFusionFactory::getUser($plugin->name);

			$existinguser = $PluginUserUpdate->getUser($userinfo);

			if(!$existinguser) {
				$status = array('error' => array(),'debug' => array());
				$PluginUserUpdate->createUser($userinfo,$status);

				foreach ($status['error'] as $error) {
					$this->error[][$plugin->name] = $error;
				}
				foreach ($status['debug'] as $debug) {
					$this->debug[][$plugin->name] = $debug;
				}
			} else {
				$this->error[][$plugin->name] = 'user already exsists';
			}
		}
	}

	/**
	 * @param array $userinfo
	 * @param $overwrite
	 *
	 * @return void
	 */
	public function update($userinfo,$overwrite)
	{
		$mainframe = self::startJoomla();

		$plugins = JFusionFunction::getSlaves();
		$plugins[] = JFusionFunction::getMaster();

		foreach ($plugins as $key => $plugin) {
			if (!array_key_exists($plugin->name,$userinfo)) {
				unset($plugins[$key]);
			}
		}
		foreach ($plugins as $plugin) {
			$PluginUserUpdate = JFusionFactory::getUser($plugin->name);
			$updateinfo = $userinfo[$plugin->name];

			if (get_class($updateinfo) == 'stdClass') {
				$lookupUser = JFusionFunction::lookupUser($plugin->name,'',false,$updateinfo->username);

				if($lookupUser) {
					$existinguser = $PluginUserUpdate->getUser($updateinfo->username);

					foreach ($updateinfo as $key => $value) {
						if ($key != 'userid' && isset($existinguser->$key)) {
							if ( $existinguser->$key != $updateinfo->$key ) {
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
		}
	}

	/**
	 * @param int $userid
	 *
	 * @return void
	 */
	public function delete($userid)
	{
		/**
		 * TODO: THINK THIS IS INCORRECT.
		 */
		$mainframe = self::startJoomla();

		/**
		 * @ignore
		 * @var $user JUser
		 */
		$user = JUser::getInstance($userid);

		if ($user) {
			if ($user->delete()) {
				$this->debug[] = 'user deleted: '.$userid;
			} else {
				$this->error[] = 'Delete user failed: '.$userid;
			}
		} else {
			$this->error[] = 'invalid user';
		}
	}
}