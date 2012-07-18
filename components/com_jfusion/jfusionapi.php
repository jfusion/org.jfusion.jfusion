<?php
function initJFusionAPI() {
	if (!defined('_JEXEC')) {
		$secretkey = 'secret passphrase';
		if ($secretkey == 'secret passphrase') {
			exit('please check your secret passphrase in this file');
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
	
	private $class = null;
	private $type = null;
	private $task = null;
	private $payload = null;
	private $secretkey = null;
	private $hash = null;
	private $error = null;
	private $debug = null;

    /**
     * @param string $url
     * @param string $secretkey
     */
    public function __construct($url = '', $secretkey = '')
	{
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
			
			$session = JFusionAPI::getSession('key');
			if (isset($session['hash'])) {
				$this->hash = $session['hash'];
			}	
		}
		$this->setTarget($url, $secretkey);
	}

    /**
     * @param string $url
     * @param string $secretkey
     */
    public function setTarget($url = '', $secretkey = '')
	{
		$this->url = $url;
		$this->secretkey = $secretkey;
	}

    /**
     * @return null
     */
    public function getError() {
		return $this->error;
    }

    /**
     * @return null
     */
    public function getDebug() {
		return $this->debug;
    }

    /**
     * @param $class
     */
    private function setClass($class)
    {
		$this->class = ucfirst(strtolower($class));
    }

    /**
     * @param $type
     */
    private function setType($type)
    {
		$this->type = strtolower($type);
    }

    /**
     * @param $task
     */
    private function setTask($task)
    {
		$this->task = ucfirst(strtolower($task));
    }

    /**
     * @param $read
     * @return string
     */
    public function read($read)
    {
		return (string) preg_replace( '/[^A-Z_]/i', '', $_REQUEST[$read]);
    }

    /**
     * @param $payload
     */
    private function setPayload($payload)
    {
		$this->payload = $payload;
    }

    /**
     * @return bool
     */
    private function retrieveKey()
	{
		if ($this->hash && $this->sid) return true;
		$FileData = $this->_raw('get','status', 'key', null);
		if ($this->error) {
			return false;
		} elseif (isset($FileData['hash'])) {
			$this->hash = $FileData['hash'];
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
        $FileData = $this->_raw('get','status', 'ping', null);
        if ($this->error) {
            return false;
        } elseif (isset($FileData['payload'])) {
            if ($FileData['payload'] == 'pong') {
                return true;
            }
        }
        return false;
    }

    public function parse() {
    	$this->setClass($this->read('jfclass'));    	
    	$this->setType($this->read('jftype'));
    	$this->setTask($this->read('jftask'));    	
    	
		//controller for when api gets called externally
		if ($this->type) {
			$class = $this->createClass();
            if ($class) {
                $function = $this->type.$this->task;
                $payload = null;
                if (method_exists ( $class , $function )) {
                    $payload = $class->$function();
                }

                $this->error = $class->error;
                $this->debug = $class->debug;

                $data['payload'] = $payload;
                $this->doOutput($data, $class->encrypt);
            }
		}
    }

    /**
     * @return null|JFusionAPIBase
     */
    public function createClass() {
        //controller for when api gets called externally
        $class = null;
        if ($this->class) {
            $class = 'JFusionAPI_'.$this->class;
            $class = new $class($this->createkey());
        }
        return $class;
    }

    /**
     * @param $class
     * @param $task
     * @param $return
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
     * @param $payload
     * @return bool
     */
    public function set($class, $task, $payload)
	{
    	return $this->_raw('set',$class, $task, $payload);
	}

    /**
     * @param $class
     * @param $task
     * @param null $payload
     * @return bool
     */
    public function get($class, $task, $payload=null)
	{
    	return $this->_raw('get',$class, $task, $payload);
	}

    /**
     * @param $class
     * @param $task
     * @param array $payload
     * @param string $return
     * @return bool
     */
    public function execute($class, $task, $payload=array(), $return='')
	{
		if (!empty($return)) {
			header('Location: '.$this->getExecuteURL($class,$task,$return).'&jfpayload='.base64_encode(serialize($payload)));
			return true;
		} else {
			return $this->_raw('execute',$class, $task, $payload);
		}
	}

    /**
     * @param string $type
     * @param string $class
     * @param string $task
     * @param array $payload

     * @return bool
     */
    private function _raw($type, $class, $task, $payload)
    {
    	$key = true;
        $class = $this->createClass();
    	if ($class && $class->encrypt) {
    		$key = $this->retrieveKey();
    	}
    	if ($key) {
	    	$this->setType($type);
	    	$this->setClass($class);
	    	$this->setTask($task);
			$this->setPayload($payload);
	        
	        $result = $this->post();
	        $FileData = $result['FileData'];
	        $FileInfo = $result['FileInfo'];

	        $FileData = $this->getOutput($FileData);

			if ($this->error) {
				return false;
			}
			return $FileData;
    	}
    	return false;
    }

    /**
     * @static
     * @param null $class
     * @param bool $delete
     * @return mixed
     */
    static function getSession($class=null,$delete=false)
	{
		$return = null;
	    if (empty($class)) {
        	if (isset($_SESSION['JFusionAPI'])) {
	            $return = $_SESSION['JFusionAPI'];
	            if ($delete) {
	                unset($_SESSION['JFusionAPI']);
	            }
        	}
        } else {
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
    static function setSession($class=null,$value)
	{
		if (empty($class)) {
			$_SESSION['JFusionAPI'] = $value;
		} else {
			$_SESSION['JFusionAPI'][$class] = $value;
		}
	}

    /**
     * @return \stdClass
     */
    private function createkey()
    {
    	$keyinfo = new stdClass;
    	if (!$this->hash) {
    		$session = JFusionAPI::getSession('key');
    		$this->hash = $session['hash'];
    	}
    	$keyinfo->key = md5($this->secretkey);
    	$keyinfo->hash = $this->hash;
    	return $keyinfo;
    }

    /**
     * @static
     * @param $keyinfo
     * @param $payload
     * @return null|string
     */
    public static function encrypt($keyinfo, $payload)
    {
    	if (isset($keyinfo->key) && isset($keyinfo->hash)) {
	    	$encrypted = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $keyinfo->key, serialize($payload), MCRYPT_MODE_NOFB, $keyinfo->hash)));
    	} else {
    		$encrypted = null;
    	}
		return $encrypted;
    }

    /**
     * @static
     * @param $keyinfo
     * @param $payload
     * @return bool|mixed
     */
    public static function decrypt($keyinfo, $payload)
    {
    	if (isset($keyinfo->key) && isset($keyinfo->hash)) {
	        $decrypted = @unserialize(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $keyinfo->key, base64_decode($payload), MCRYPT_MODE_NOFB, $keyinfo->hash)));
    	} else {
    		$decrypted = false;
    	}
		return $decrypted;
    }

    /**
     * @param array $post
     * @return array|bool
     */
    private function post($post=array())
    {
    	$this->error = null;
    	$this->debug = null;
        $result = false;
		//check to see if cURL is loaded
        if (!function_exists('curl_init')) {
        	$this->error[] = 'JfusionAPI: sorry cURL is needed for JFusionAPI';
        } else {
            if ($this->sid) {
                $post['PHPSESSID'] = $this->sid;
            }
            if ($this->class) {
                $post['jfclass'] = $this->class;
            }
            if ($this->type) {
                $post['jftype'] = $this->type;
            }
            if ($this->task) {
                $post['jftask'] = $this->task;
            }
            if ($this->payload) {
                $post['jfpayload'] = JFusionAPI::encrypt($this->createkey(),$this->payload);
            }
            $this->class = $this->type = $this->task = $this->payload = null;

            $crl = curl_init();
            curl_setopt($crl, CURLOPT_URL,$this->url);
            curl_setopt($crl, CURLOPT_HEADER, 0);
            curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($crl, CURLOPT_POST ,1);
            curl_setopt($crl, CURLOPT_POSTFIELDS , $post);
            $result['FileData'] = curl_exec($crl);
            $result['FileInfo'] = curl_getinfo($crl);

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
     */
    private function doOutput($output,$encrypt=false)
    {
		$output['PHPSESSID'] = $this->sid;
		$output['error'] = $this->error;
		$output['debug'] = $this->debug;
		if ($encrypt) {
			echo JFusionAPI::encrypt($this->createkey() , $output);
		} else {
			echo base64_encode(serialize($output));
		}
		exit();
	}

    /**
     * @param $input
     * @return bool
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
		if (isset($return['error'])) {
			$this->error = $return['error'];
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
	public $error = null;
	public $debug = null;	
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
     * @return string
     */
    protected function buildPayload($payload)
	{
		return base64_encode(serialize($payload));
    }

    /**
     * @param string|null $url Url of where to redirect to
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
    	 
		$session = JFusionAPI::getSession('key');
		$session['hash'] = $iv;
		
		JFusionAPI::setSession('key',$session);
		$return['hash'] = $iv;
		return $return;
	}

    /**
     * @return array
     */
    public function getPing()
	{
		$return['payload'] = 'pong';
		return $return;
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
	
	public function executeLogout()
	{
		if ($this->readPayload(false)) {
            $joomla = new JFusionAPI_Joomla();

            if (isset($userinfo['plugin'])) {
                $joomla->setActivePlugin($userinfo['plugin']);
            }

            $username = isset($this->payload['username']) ? $this->payload['username'] : null;
            $joomla->logout($username);
		}
        $this->doExit();
	}
	
	public function executeRegister()
	{
		if ( $this->payload ) {
            if ( isset($this->payload['userinfo']) && get_class($this->payload['userinfo']) == 'stdClass') {
                $mainframe = JFusionAPIInternal::startJoomla();

                $updateinfo = $this->payload['userinfo'];

                if (isset($this->payload['overwrite']) && $this->payload['overwrite']) {
                    $overwrite = 1;
                } else {
                    $overwrite = 0;
                }

                $plugins = JFusionFunction::getSlaves();
                $plugins[] = JFusionFunction::getMaster();

                // TODO: Determin if this can be removed ?
                if ( isset($this->payload['plugin']) ) {
                    foreach ($plugins as $key => $plugin) {
                        if ($plugin->name == $this->payload['plugin']) {
                            unset($plugins[$key]);
                        }
                    }
                }

                foreach ($plugins as $plugin) {
                    $PluginUserUpdate = &JFusionFactory::getUser($plugin->name);

                    $existinguser = $PluginUserUpdate->getUser($updateinfo);

                    if(!$existinguser) {
                        $status = array('error' => array(),'debug' => array());
                        $PluginUserUpdate->createUser($updateinfo,$status);

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
            } else {
                $this->error[] = 'invalid payload';
            }
		} else {
			$this->error[] = 'invalid payload';
		}
	}
	
	public function executeUpdate()
	{
		if ( $this->payload ) {
			if ( isset($this->payload['userinfo']) && is_array($this->payload['userinfo'])) {
				$mainframe = JFusionAPIInternal::startJoomla();
				if (isset($this->payload['overwrite']) && $this->payload['overwrite']) {
					$overwrite = 1;
				} else {
					$overwrite = 0;
				}
				
 				$plugins = JFusionFunction::getSlaves();
 				$plugins[] = JFusionFunction::getMaster();

				foreach ($plugins as $key => $plugin) {
					if (!array_key_exists($plugin->name,$this->payload['userinfo'])) {
						unset($plugins[$key]);
					}
				}
				foreach ($plugins as $plugin) {
					$PluginUserUpdate = &JFusionFactory::getUser($plugin->name);
					$updateinfo = $this->payload['userinfo'][$plugin->name];
					
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
			} else {
				$this->error[] = 'invalid payload';
			}
		} else {
			$this->error[] = 'invalid payload';
		}
	}

    public function executeDelete()
    {
        /**
         * TODO: THINK THIS IS INCORRECT.
         */
        if ( $this->payload ) {
            if ( isset($this->payload['userid']) ) {
                $mainframe = JFusionAPIInternal::startJoomla();

                /**
                 * @ignore
                 * @var $user JUser
                 */
                $user = JUser::getInstance($this->payload['userid']);

                if ($user) {
                    $user->delete();
                    $this->debug[] = 'user deleted: '.$this->payload['userid'];
                } else {
                    $this->error[] = 'invalid user';
                }
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

            include_once(JPATH_LIBRARIES . DS . 'loader.php');
            $autoloaders = spl_autoload_functions();
            if ($autoloaders && in_array('__autoload', $autoloaders)) {
                spl_autoload_register('__autoload');
            }

            require_once JPATH_ROOT . DS . 'includes' . DS . 'framework.php';
//			include_once JPATH_LIBRARIES . DS . 'import.php'; //include not require, so we only get it if its there ...
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
        }

        $mainframe = JFactory::getApplication('site');
        $GLOBALS['mainframe'] = & $mainframe;
        error_reporting($old);
        return $mainframe;
    }

    public function setActivePlugin($plugin)
    {
        $this->activePlugin = $plugin;
    }

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
            $session_table = & JTable::getInstance('session');
            if ($session_table->load($id)) {
                $session_table->data = $session_data;
                $session_table->store();
            } else {
                // if load failed then we assume that it is because
                // the session doesn't exist in the database
                // therefore we use insert instead of store
                $app = &JFactory::getApplication();
                $session_table->data = $session_data;
                $session_table->insert($id, $app->getClientId());
            }
        }
    }

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
                    $user = &JFactory::getUser($lookupUser->id);
                }
            } else {
                $user = &JFactory::getUser($username);
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
        $uri = & JURI::getInstance();
        //add a variable to ensure refresh
        $link = $uri->toString();
    }
}