<?php

/**
 * @package JFusion
 * @subpackage Models
 * @author JFusion development team -- Morten Hundevad
 * @copyright Copyright (C) 2008 JFusion -- Morten Hundevad. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

require_once(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.cookie.php');

/**
 * Singleton static only class that creates instances for each specific JFusion plugin.
 * @package JFusion
 */

class JFusionCurlFrameless {
	var $location = null;
	var $ch = null;

	var $cookies = array();

	/**
	 *
	 */
	function __construct()
    {
    }

	/*
	 * function read_header
	 * Basic  code was found on Svetlozar Petrovs website http://svetlozar.net/page/free-code.html.
	 * The code is free to use and similar code can be found on other places on the net.
	 */

    /**
     * @param $ch
     * @param $string
     * @return int
     */
    function read_header($ch, $string) {
		$length = strlen($string);
		if(!strncmp($string, "Location:", 9)) {
            $this->location = trim(substr($string, 9, -1));
		} else if(!strncmp($string, "Set-Cookie:", 11)) {
            $string = trim(substr($string, 11, -1));
			$parts = explode(';', $string);

            list($name,$value) = explode('=', $parts[0]);

            $cookie = new stdClass;
            $cookie->name = trim($name);
            $cookie->value = trim($value);
            $cookie->expires = 0;

            if (isset($parts[1])) {
                list($name,$value) = explode('=', $parts[1]);
                if ($name == 'expires') {
                    $cookie->expires = strtotime($value);
                }
            }
            $this->cookies[] = $cookie;
		}
		return $length;
	}

    /**
     * @param $data
     * @return array
     */
    function display(&$data) {
        $status = array('error' => array(),'debug' => array());

		$url = $data->source_url;

        $config = JFactory::getConfig();
        $sefenabled = $config->get('sef');
        if(!empty($sefenabled)) {
			$uri = JURI::getInstance();
			$current = $uri->toString( array( 'path', 'query'));

	        $menu = JMenu::getInstance('site');
        	$item = $menu->getActive();
			$index = '/' . $item->route;
			$pos = strpos($current, $index);
			if ( $pos !== false ) {
				$current = substr($current, $pos+strlen($index));
			}
			$current = ltrim ( $current , '/' );
        } else {
			$current = JFactory::getApplication()->input->get('jfile') . '?';
            $current .= $this->buildUrl('GET');
        }

		$url .= $current;
		$post = $this->buildUrl('POST');

		$files = $_FILES;
		$filepath = array();
		if($post) {
			foreach($files as $userfile=>$file) {
				if (is_array($file)) {
					if(is_array($file['name'])) {
						foreach ($file['name'] as $key => $value) {
							$name=$file['name'][$key];
							$path=$file['tmp_name'][$key];
							if ($name) {
								$filepath[$key] = JPATH_ROOT . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $name;
								rename($path, $filepath[$key]);
								$post[$userfile . '[' . $key . ']'] = '@' . $filepath[$key];
							}
						}
					} else {
						$path = $file['tmp_name'];
						$name=$file['name'];
						$key = $path;
						$filepath[$key] = JPATH_ROOT . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $name;
						rename($path, $filepath[$key]);
						$post[$userfile] = '@' . $filepath[$key];
					}
				}
			}
		}

        $this->ch = curl_init($url);
		if ($post) {
			curl_setopt($this->ch, CURLOPT_POST, 1);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
		} else {
			curl_setopt($this->ch, CURLOPT_POST, 0);
		}

		if(!empty($data->httpauth) ) {
			curl_setopt($this->ch,CURLOPT_USERPWD,$data->httpauth_username . ':' . $data->httpauth_password);

			switch ($data->httpauth) {
				case "basic":
					$data->httpauth = CURLAUTH_BASIC;
					break;
				case "gssnegotiate":
					$data->httpauth = CURLAUTH_GSSNEGOTIATE;
					break;
				case "digest":
					$data->httpauth = CURLAUTH_DIGEST;
					break;
				case "ntlm":
					$data->httpauth = CURLAUTH_NTLM;
					break;
				case "anysafe":
					$data->httpauth = CURLAUTH_ANYSAFE;
					break;
				case "any":
				default:
					$data->httpauth = CURLAUTH_ANY;
			}

			curl_setopt($this->ch,CURLOPT_HTTPAUTH,$data->httpauth);
		}

		curl_setopt($this->ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		$ref = isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:'';
		curl_setopt($this->ch, CURLOPT_REFERER, $ref);

		$headers[] = 'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'];
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($this->ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this,'read_header'));
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($this->ch, CURLOPT_FAILONERROR,0);
		curl_setopt($this->ch, CURLOPT_MAXREDIRS, 2 );
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
		$data->verifyhost = isset($data->verifyhost) ? $data->verifyhost : 2;
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $data->verifyhost);

		curl_setopt($this->ch, CURLOPT_HEADER, 0);

		$_COOKIE['jfusionframeless'] = true;
		curl_setopt($this->ch, CURLOPT_COOKIE, JFusionCookies::buildCookie());
		unset($_COOKIE['jfusionframeless']);

		$data->buffer = curl_exec($this->ch);

		if ( $this->location ) {
			$data->location = $this->location;
		}

		$data->cookie_domain = isset($data->cookie_domain) ? $data->cookie_domain : '';
		$data->cookie_path = isset($data->cookie_path) ? $data->cookie_path : '';

	    $cookies = JFusionFactory::getCookies();
        foreach ($this->cookies as $cookie) {
	        $cookies->addCookie($cookie->name, urldecode($cookie->value), $cookie->expires, $data->cookie_path, $data->cookie_domain);
        }

		if (curl_error($this->ch)) {
			$status['error'][] = JText::_('CURL_ERROR_MSG') . ': ' . curl_error($this->ch) . ' URL:' . $url;
			curl_close($this->ch);
			return $status;
		}

		curl_close($this->ch);

		if (count($filepath)) {
			foreach($filepath as $value) {
				unlink($value);
			}
		}
		return $status;
	}

    /**
     * @param string $type
     * @return mixed|string
     */
    function buildUrl($type='GET') {
	    if ($type == 'POST') {
		    $var = $_POST;
	    } else {
		    $var = $_GET;
	    }
		unset($var['Itemid'],$var['option'],$var['view'],$var['jFusion_Route'],$var['jfile']);
		if ($type=='POST') return $var;
		return http_build_query($var);
	}
}