<?php
/**
 * @package JFusion
 * @author JFusion development team
 * @copyright Copyright (C) 2009 JFusion. All rights reserved.
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Jfusion_Joomla_Helper_Cookies extends Mage_Core_Helper_Abstract {

	public $ch;

	public $cookiearr;

	public $cookies_to_set;

	public $cookies_to_set_index;

	protected static $_instance;

	/**
	 * Singleton pattern implementation
	 *
     * @param resource $ch
     *
	 * @return Jfusion_Joomla_Helper_Cookies
	 */
	static public function instance($ch) {
		if (! self::$_instance) {
			self::$_instance = new Jfusion_Joomla_Helper_Cookies ( );
			self::$_instance->cookies_to_set_index = 0;
			self::$_instance->cookies_to_set = array ();
			self::$_instance->cookiearr = array ();
		}
		
		self::$_instance->ch = $ch;
		return self::$_instance;
	}
	
	/**
	 * Callback function used by Curl to set the headers
	 *
	 * @param resource $ch
	 * @param string $string
	 * @return integer
	 */
	public function getHeader($ch, $string) {
		
		$curl_obj = self::instance ($ch);
		
		$length = strlen ( $string );
		if (! strncmp ( $string, "Set-Cookie:", 11 )) {
			header ( $string, false );
			$cookiestr = trim ( substr ( $string, 11, - 1 ) );
			$cookie = explode ( ';', $cookiestr );
			$curl_obj->cookies_to_set [$curl_obj->cookies_to_set_index] = $cookie;
			$curl_obj->cookies_to_set_index ++;
			$cookie = explode ( '=', $cookie [0] );
			$cookiename = trim ( array_shift ( $cookie ) );
			$curl_obj->cookiearr [$cookiename] = trim ( implode ( '=', $cookie ) );
		}
		
		$cookie = "";
		if (! empty ( $curl_obj->cookiearr ) && (trim ( $string ) == "")) {
			foreach ( $curl_obj->cookiearr as $key => $value ) {
				$cookie .= "$key=$value; ";
			}
			curl_setopt ( $curl_obj->ch, CURLOPT_COOKIE, $cookie );
		}
		return $length;
	}

	/**
	 * Add cookie in the header
	 *
	 * @param string $name
	 * @param string $value
	 * @param int $expires
	 * @param string $cookiepath
	 * @param string $cookiedomain
	 * @param int $secure
	 * @param int $httponly
	 */
	private function addCookie($name, $value = '', $expires = 0, $cookiepath = '', $cookiedomain = '', $secure = 0, $httponly = 0) {
		// Versions of PHP prior to 5.2 do not support HttpOnly cookies and IE is buggy when specifying a blank domain so set the cookie manually
		$cookie = "Set-Cookie: {$name}=" . urlencode ( $value );
		if ($expires > 0) {
			$cookie .= "; expires=" . gmdate ( 'D, d-M-Y H:i:s \\G\\M\\T', $expires );
		}
		if (! empty ( $cookiepath )) {
			$cookie .= "; path={$cookiepath}";
		}
		if (! empty ( $cookiedomain )) {
			$cookie .= "; domain={$cookiedomain}";
		}
		if ($secure == true) {
			$cookie .= '; Secure';
		}
		if ($httponly == true) {
			$cookie .= "; HttpOnly";
		}
		header ( $cookie, false );
	}

	/**
	 * Parse cookie array to extract them and provide as convenient for the self::setCookies
	 *
	 * @param array $cookielines
	 * @return array
	 */
	function parsecookies($cookielines) {
		$line = array ();
		$cookies = array ();
		foreach ( $cookielines as $line ) {
			$cdata = array ();
			$data = array ();
			foreach ( $line as $data ) {
				$cinfo = explode ( '=', $data );
				$cinfo [0] = trim ( $cinfo [0] );
				if (! isset ( $cinfo [1] )) {
					$cinfo [1] = '';
				}
				if (strcasecmp ( $cinfo [0], 'expires' ) == 0) $cinfo [1] = strtotime ( $cinfo [1] );
				if (strcasecmp ( $cinfo [0], 'secure' ) == 0) $cinfo [1] = "true";
				if (strcasecmp ( $cinfo [0], 'httponly' ) == 0) $cinfo [1] = "true";
				if (in_array ( strtolower ( $cinfo [0] ), array ('domain', 'expires', 'path', 'secure', 'comment', 'httponly' ) )) {
					$cdata [trim ( $cinfo [0] )] = $cinfo [1];
				}
				else {
					$cdata ['value'] ['key'] = $cinfo [0];
					$cdata ['value'] ['value'] = $cinfo [1];
				}
			}
			$cookies [] = $cdata;
		}
		return $cookies;
	}

	/**
	 * Make some fixes before to add cookies into the header
	 *
	 * @param array $mycookies_to_set
	 * @param string $cookiedomain
	 * @param string $cookiepath
	 * @param integer $expires
	 * @param integer $secure
	 * @param integer $httponly
	 */
	public function setCookies($mycookies_to_set, $cookiedomain = null, $cookiepath = null, $expires = 0, $secure = 0, $httponly = 1) {
		$cookies = array ();
		$cookies = self::parsecookies ( $mycookies_to_set );
		foreach ( $cookies as $cookie ) {
			$name = "";
			$value = "";
			if ($expires == 0) {
				$expires_time = 0;
			}
			else {
				$expires_time = time () + $expires;
			}
			if (isset ( $cookie ['value'] ['key'] )) {
				$name = $cookie ['value'] ['key'];
			}
			if (isset ( $cookie ['value'] ['value'] )) {
				$value = $cookie ['value'] ['value'];
			}
			if (isset ( $cookie ['expires'] )) {
				$expires_time = $cookie ['expires'];
			}
			if (! $cookiepath) {
				if (isset ( $cookie ['path'] )) {
					$cookiepath = $cookie ['path'];
				}
			}
			if (! $cookiedomain) {
				if (isset ( $cookie ['domain'] )) {
					$cookiedomain = $cookie ['domain'];
				}
			}
			self::addCookie ( $name, urldecode ( $value ), $expires_time, $cookiepath, $cookiedomain, $secure, $httponly );
			if (($expires_time) == 0) {
				$expires_time = 'Session_cookie';
			}
			else {
				$expires_time = date ( 'd-m-Y H:i:s', $expires_time );
			}
			//$status ['debug'] [] = JText::_ ( 'CREATED' ) . ' ' . JText::_ ( 'COOKIE' ) . ': ' . JText::_ ( 'NAME' ) . '=' . $name . ', ' . JText::_ ( 'VALUE' ) . '=' . urldecode ( $value ) . ', ' . JText::_ ( 'EXPIRES' ) . '=' . $expires_time . ', ' . JText::_ ( 'COOKIE_PATH' ) . '=' . $cookiepath . ', ' . JText::_ ( 'COOKIE_DOMAIN' ) . '=' . $cookiedomain . ', ' . JText::_ ( 'COOKIE_SECURE' ) . '=' . $secure . ', ' . JText::_ ( 'COOKIE_HTTPONLY' ) . '=' . $httponly;
		}
		return;
	}

	/**
	 * Retrieve the cookies as a string cookiename=cookievalue; or as an array
	 *
	 * @param string $type
	 * @return string or array
	 */
	public function getCookies($type = 'string'){
		
		switch ($type) {
			case 'array':
				return $_COOKIE;
				break;
			case 'string':
			default:
				return self::implodeCookies( $_COOKIE, ';' );
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
     * @param array $array
     * @param string $delimeter
     * @param string $keyssofar
     *
     * @return string
	 */
	function implodeCookies($array, $delimeter, $keyssofar = '') {
		$output = '';
		foreach ( $array as $key => $value ) {
			if (! is_array ( $value )) {
				if ($keyssofar) $pair = $keyssofar . '[' . $key . ']=' . $value . $delimeter;
				else $pair = $key . '=' . $value . $delimeter;
				if ($output != '') $output .= ' ';
				$output .= $pair;
			}
			else {
				if ($output != '') $output .= ' ';
				$output .= self::implodeCookies ( $value, $delimeter, $key . $keyssofar );
			}
		}
		return $output;
	}
}