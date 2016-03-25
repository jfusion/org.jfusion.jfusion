<?php
// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();
/**
 * JFusionCookies class
 *
 * @category   JFusion
 * @package    Model
 * @subpackage JFusionCookies
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionCookies {
	/**
     * Variable to store cookie data
     */
	var $_cookies = array ();

    /**
     * @param null $secret
     */
    public function __construct($secret = null)
	{
		$this->secret = $secret;
	}
	
	/**
     * addCookie
     *
     * @param string $cookie_name
     * @param string $cookie_value
     * @param int $cookie_expires_time
     * @param string $cookiepath
     * @param string $cookiedomain
     * @param int $cookie_secure
     * @param int $cookie_httponly
     * @param boolean $mask
     *
     * @return array Cookie debug info
     */
    function addCookie($cookie_name, $cookie_value = '', $cookie_expires_time = 0, $cookiepath = '', $cookiedomain = '', $cookie_secure = false, $cookie_httponly = false, $mask = false) {
    	if ($cookie_expires_time != 0) {
    		$cookie_expires_time = time() + intval($cookie_expires_time);
    	} else {
    		$cookie_expires_time = 0;
    	}

	    list ($url, $cookiedomain) = $this->getApiUrl($cookiedomain);

	    $cookie = new stdClass();
	    $cookie->name = $cookie_name;
	    $cookie->value = $cookie_value;
	    $cookie->expire = $cookie_expires_time;
	    $cookie->path = $cookiepath;
	    $cookie->domain = $cookiedomain;
	    $cookie->secure = $cookie_secure;
	    $cookie->httponly = $cookie_httponly;
		if ($url) {
			$mainframe = JFusionFactory::getApplication();
			if (!$mainframe->isAdmin()) {
				$this->_cookies[$url][] = $cookie;
			}
		} else {
			setcookie($cookie->name, $cookie->value, $cookie->expire, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httponly);
		}

        $debug = array();
        $debug[JText::_('COOKIE')][JText::_('JFUSION_CROSS_DOMAIN_URL')] = $url;
	    $debug[JText::_('COOKIE')][JText::_('COOKIE_DOMAIN')] = $cookiedomain;
        $debug[JText::_('COOKIE')][JText::_('NAME')] = $cookie_name;
        if ($mask) {
            $debug[JText::_('COOKIE')][JText::_('VALUE')] = substr($cookie_value, 0, 6) . '********';
        } else {
            $debug[JText::_('COOKIE')][JText::_('VALUE')] = $cookie_value;
        }
        if (($cookie_expires_time) == 0) {
            $cookie_expires_time = 'Session_cookie';
        } else {
            $cookie_expires_time = date('d-m-Y H:i:s', $cookie_expires_time);
        }
        $debug[JText::_('COOKIE')][JText::_('COOKIE_EXPIRES')] = $cookie_expires_time;
        $debug[JText::_('COOKIE')][JText::_('COOKIE_PATH')] = $cookiepath;
        $debug[JText::_('COOKIE')][JText::_('COOKIE_SECURE')] = $cookie_secure;
        $debug[JText::_('COOKIE')][JText::_('COOKIE_HTTPONLY')] = $cookie_httponly;
        return $debug;
    }

    /**
     * Execute the cross domain login redirects
     *
     * @param string $source_url
     * @param string $return
     */
    function executeRedirect($source_url = null, $return = null) {
    	$mainframe = JFusionFactory::getApplication();
    	if (!$mainframe->isAdmin() || !$this->secret) {
	    	if(count($this->_cookies)) {
	    		if (empty($return)) {
                    $return = JFusionFactory::getApplication()->input->getBase64('return', '');
	    			if ($return) {
	    				$return = base64_decode($return);
	    				if( stripos($return, 'http://') === false && stripos($return, 'https://') === false ) {
	    					$return = ltrim($return, '/');
	    					$return = $source_url . $return;
	    				}
	    			}
	    		}
		
				require_once(JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'jfusionapi.php');

                $api = null;
                $data = array();
		    	foreach($this->_cookies as $key => $cookies) {
		    		$api = new JFusionAPI($key, $this->secret);
		    		if ($api->set('Cookie', 'Cookies', $cookies)) {
		    			$data['url'][$api->url] = $api->sid;
		    		}
				}
                if ($api) {
                    unset($data['url'][$api->url]);
                    $api->execute('cookie', 'cookies', $data, $return);
                }
	    	}
	    	if (!empty($return)) {
	    		$mainframe->redirect($return);
	    	}
    	}
    }

    /**
     * @param $cookiedomain
     * @return array
     */
    public function getApiUrl($cookiedomain) {
    	$url = null;
		if (strpos($cookiedomain, 'http://') === 0) {
			$cookiedomain = str_replace('http://', '', $cookiedomain);
			$url = 'http://' . ltrim($cookiedomain, '.');
		} else if (strpos($cookiedomain, 'https://') === 0) {
			$cookiedomain = str_replace('https://', '', $cookiedomain);
			$url = 'https://' . ltrim($cookiedomain, '.');
		}
		if ($url) {
			$url = rtrim($url, '/');
			$url = $url . '/jfusionapi.php';
		}
    	return array($url, $cookiedomain);
    }

	/**
	 * Retrieve the cookies as a string cookiename=cookievalue; or as an array
	 *
	 * @param string $type
	 * @return string or array
	 */
	public function buildCookie($type = 'string') {
		switch ($type) {
			case 'array':
				return $_COOKIE;
				break;
			case 'string':
			default:
				return $this->implodeCookies($_COOKIE, ';');
			break;
		}
	}

	/**
	 * Can implode an array of any dimension
	 * Uses a few basic rules for implosion:
	 *        1. Replace all instances of delimeters in strings by '/' followed by delimeter
	 *        2. 2 Delimeters in between keys
	 *        3. 3 Delimeters in between key and value
	 *        4. 4 Delimeters in between key-value pairs
	 *
	 * @see model.curl.php
     *
     * @param array $array
     * @param string $delimeter
     * @param string $keyssofar
     *
     * @return string
	 */
	public function implodeCookies($array, $delimeter, $keyssofar = '') {
		$output = '';
		foreach ($array as $key => $value) {
			if (! is_array($value)) {
				if ($keyssofar) $pair = $keyssofar . '[' . $key . ']=' . urlencode($value) . $delimeter;
				else $pair = $key . '=' . urlencode($value) . $delimeter;
				if ($output != '') $output .= ' ';
				$output .= $pair;
			}
			else {
				if ($output != '') $output .= ' ';
				$output .= $this->implodeCookies($value, $delimeter, $key . $keyssofar);
			}
		}
		return $output;
	}
}
