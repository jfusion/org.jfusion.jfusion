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

require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.cookie.php');

/**
 * Singleton static only class that creates instances for each specific JFusion plugin.
 * @package JFusion
 */

class JFusionCurlFrameless{
	var $location = null;
	var $ch = null;

	var $cookiearr = null;
	var $cookies_to_set = null;
	var $cookies_to_set_index = null;

    /**
    * Gets an Fusion front object
    *
    * @return object JFusionCurlFrameless JFusionCurlFrameless object for the JFusionCurlFrameless
    */
    function &getInstance()
    {
        static $instances;

        //only create a new plugin instance if it has not been created before
        if (!isset($instances)) {
			$instances = new JFusionCurlFrameless();
        }
        return $instances;
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
		$curlframeless = JFusionCurlFrameless::getInstance();
		$curlframeless->cookiearr;
		$curlframeless->cookies_to_set;
		$curlframeless->cookies_to_set_index;

		$length = strlen($string);
		if(!strncmp($string, "Location:", 9)){
			$curlframeless->location = trim(substr($string, 9, -1));
		}
		if(!strncmp($string, "Set-Cookie:", 11)){
			header($string,false);
			$cookiestr = trim(substr($string, 11, -1));
			$cookie = explode(';', $cookiestr);
			$curlframeless->cookies_to_set[$curlframeless->cookies_to_set_index] = $cookie;
			$curlframeless->cookies_to_set_index++;
			$cookie = explode('=', $cookie[0]);
			$cookiename = trim(array_shift($cookie));
			$curlframeless->cookiearr[$cookiename] = trim(implode('=', $cookie));
		}

		$cookie = "";
		if(!empty($curlframeless->cookiearr) && (trim($string) == "")){
			foreach ($curlframeless->cookiearr as $key=>$value){
				$cookie .= "$key=$value; ";
			}
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		}
		return $length;
	}

    /**
     * @param $data
     * @return array
     */
    function display(&$data) {
		$status = array();
		$curlframeless = JFusionCurlFrameless::getInstance();

		$url = $data->source_url;

        $config =& JFactory::getConfig();
        $sefenabled = $config->getValue('config.sef');
        if(!empty($sefenabled)) {
			$uri     = & JURI::getInstance();
			$current = $uri->toString( array( 'path', 'query'));			

        	$menus = & JSite::getMenu();
        	$menu = $menus->getActive();
			$index = '/'.$menu->route;
			$pos = strpos($current, $index);
			if ( $pos !== false ) {
				$current = substr($current, $pos+strlen($index));
			}
			$current = ltrim ( $current , '/' );
        } else {
			$current = JRequest::getVar('jfile').'?';
            $current .= JFusionCurlFrameless::buildUrl('GET');
        }

		$url .= $current;

		$post = JFusionCurlFrameless::buildUrl('POST');
		$files = JRequest::get('FILES');
		$filepath = array();
		if($post) {
			foreach($files as $userfile=>$file) {
				if (is_array($file)) {
					if(is_array($file['name'])) {
						foreach ($file['name'] as $key => $value) {
							$name=$file['name'][$key];
							$path=$file['tmp_name'][$key];
							if ($name) {
								$filepath[$key] = JPATH_ROOT.DS.'tmp'.DS.$name;
								rename($path, $filepath[$key]);
								$post[$userfile.'['.$key.']']='@'.$filepath[$key];
							}
						}
					} else {
						$path = $file['tmp_name'];
						$name=$file['name'];
						$key = $path;
						$filepath[$key] = JPATH_ROOT.DS.'tmp'.DS.$name;
						rename($path, $filepath[$key]);
						$post[$userfile]='@'.$filepath[$key];
					}
				}
			}
		}

		$curlframeless->ch = curl_init($url);
		if ($post) {
			curl_setopt($curlframeless->ch, CURLOPT_POST, 1);
			curl_setopt($curlframeless->ch, CURLOPT_POSTFIELDS, $post);
		} else {
			curl_setopt($curlframeless->ch, CURLOPT_POST, 0);
		}

		if(!empty($data->httpauth) ) {
			curl_setopt($curlframeless->ch,CURLOPT_USERPWD,$data->httpauth_username.':'.$data->httpauth_password);

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

			curl_setopt($curlframeless->ch,CURLOPT_HTTPAUTH,$data->httpauth);
		}

		curl_setopt($curlframeless->ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		$ref = isset($_SERVER["HTTP_REFERER"])?$_SERVER["HTTP_REFERER"]:"";
		curl_setopt($curlframeless->ch, CURLOPT_REFERER, $ref);

		$headers[] = 'X-Forwarded-For: '.$_SERVER['REMOTE_ADDR'];
		curl_setopt($curlframeless->ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($curlframeless->ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($curlframeless->ch, CURLOPT_HEADERFUNCTION, array('JFusionCurlFrameless','read_header'));
		curl_setopt($curlframeless->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlframeless->ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($curlframeless->ch, CURLOPT_FAILONERROR,0);
		curl_setopt($curlframeless->ch, CURLOPT_MAXREDIRS, 2 );
		curl_setopt($curlframeless->ch, CURLOPT_SSL_VERIFYPEER, 0);
		$data->verifyhost = isset($data->verifyhost)?$data->verifyhost:1;
		curl_setopt($curlframeless->ch, CURLOPT_SSL_VERIFYHOST,$data->verifyhost);

		curl_setopt($curlframeless->ch, CURLOPT_HEADER, 0);

		$_COOKIE['jfusionframeless'] = true;
		curl_setopt($curlframeless->ch, CURLOPT_COOKIE, JFusionCookies::buildCookie());
		unset($_COOKIE['jfusionframeless']);

		$data->buffer = curl_exec($curlframeless->ch);

		if ( $curlframeless->location ) {
			$data->location = $curlframeless->location;
		}

		$data->cookie_domain = isset($data->cookie_domain) ? $data->cookie_domain : '';
		$data->cookie_path = isset($data->cookie_path) ? $data->cookie_path : '';
		if (isset($curlframeless->cookies_to_set) && is_array($curlframeless->cookies_to_set)) {
			foreach ($curlframeless->cookies_to_set as $key=>$value) {
				$cookieExpite = 0;
				$value2 = trim($value[0]);
				list($cookieName,$cookieValue) = explode('=', $value2);
				unset($value[0]);
				if (isset($value) && is_array($value)) {
					foreach ($value as $key2=>$value2) {
						$value2 = trim($value2);
						$value2 = explode('=', $value2);

						if ($value2[0] == 'expires') {
							$cookieExpite = strtotime($value2[1]);
						}
					}
				}
				JFusionFunction::addCookie($cookieName, urldecode($cookieValue),$cookieExpite,$data->cookie_path,$data->cookie_domain);
			}
		}

		if (curl_error($curlframeless->ch)) {
			$status['error'][] = JText::_('CURL_ERROR_MSG').": ".curl_error($curlframeless->ch).' URL:'.$url;
			curl_close($curlframeless->ch);
			return $status;
		}

		curl_close($curlframeless->ch);

		if (count($filepath)) {
			foreach($filepath as $key=>$value) {
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
		$var = JRequest::get($type);
		unset($var['Itemid'],$var['option'],$var['view'],$var['jFusion_Route'],$var['jfile']);
		if ($type=='POST') return $var;
		return http_build_query($var);
	}
}