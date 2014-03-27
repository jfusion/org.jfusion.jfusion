<?php namespace JFusion\Api;

use stdClass;

function initApi() {
	if (!defined('_JEXEC')) {
		$secretkey = 'secret passphrase';
		if ($secretkey == 'secret passphrase') {
			exit('JFusion Api Disabled');
		}
		$Api = new Api('', $secretkey);
		$Api->parse();
	}
}
// add everything inside a function to prevent 'sniffing';
if (!defined('_JFUSIONAPI_INTERNAL')) {
	initApi();
}

/**
 * Api class
 *
 * @category   JFusion
 * @package    API
 * @subpackage Api
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Api {
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

			$this->hash = Api::getSession('hash');
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

		$data=array();
		$encrypt = false;
		//controller for when api gets called externally
		if ($type) {
			$class = $this->createClass($this->read('jfclass'));
			if ($class) {
				$function = $type . $task;
				if (method_exists($class, $function)) {
					$data['payload'] = $class->$function();

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
	 * @return null|Base
	 */
	public function createClass($class) {
		//controller for when api gets called externally
		$class = ucfirst(strtolower($class));
		if ($class) {
			$class =  __NAMESPACE__ . '\\' . $class;;
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
	 * @param array $payload
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
		if (isset($_SESSION['Api'])) {
			if (isset($_SESSION['Api'][$class])) {
				$return = $_SESSION['Api'][$class];
				if ($delete) {
					unset($_SESSION['Api'][$class]);
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
		$_SESSION['Api'][$class] = $value;
	}

	/**
	 * @return stdClass
	 */
	private function createkey()
	{
		$keyinfo = new stdClass;
		if (!$this->hash) {
			$this->hash = Api::getSession('hash');
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
			$decrypted = json_decode(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $keyinfo->secret, base64_decode($payload), MCRYPT_MODE_NOFB, $keyinfo->hash)));
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
			$this->error[] = 'JfusionAPI: sorry cURL is needed for Api';
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
				$post['jfpayload'] = Api::encrypt($this->createkey(), $payload);
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
		$output['PHPSESSID'] = $this->sid;
		$output['error'] = $this->error;
		$output['debug'] = $this->debug;
		$result = null;
		if ($encrypt) {
			$result = Api::encrypt($this->createkey() , $output);
			if ($result == null) {
				$output['error'] = 'Encryption failed';
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
		$return = Api::decrypt($this->createkey() , $input);
		if (!is_array($return)) {
			ob_start();
			$return = json_decode(trim(base64_decode($input)));
			ob_end_clean();
		}
		if (!is_array($return)) {
			$this->error[] = 'JfusionAPI: error output: ' . $input;
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