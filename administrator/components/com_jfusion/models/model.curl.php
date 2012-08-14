<?php

/**
 * curl login model
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    Henk Wevers <henk@wevers.net>
 * @copyright 2008 - 2011  JFusion - Henk Wevers. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * HTML Form Parser
 * This will extract all forms and his elements in an
 * big assoc Array.
 * Many modifications and bug repairs by Henk Wevers
 *
 * @category  JFusion
 * @package   Models
 * @author    Peter Valicek <Sonny2@gmx.DE>
 * @copyright 2004 Peter Valicek Peter Valicek <Sonny2@gmx.DE>: GPL-2
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionCurlHtmlFormParser
{

	var $html_data = '';
	var $_return = array();
	var $_counter = '';
	var $button_counter = '';
	var $_unique_id = '';
	/**
	 * html form parser
	 *
	 * @param string $html_data the actual html string
	 *
	 * @return array html elements
	 */
	function JFusionCurlHtmlFormParser($html_data)
	{
		if (is_array($html_data)) {
			$this->html_data = join('', $html_data);
		} else {
			$this->html_data = $html_data;
		}
		$this->_return = array();
		$this->_counter = 0;
		$this->button_counter = 0;
		$this->_unique_id = md5(time());
	}

	/**
	 * Parses the forms
	 *
	 * @return string nothing
	 */
	function parseForms()
	{
		if (preg_match_all("/<form.*>.+<\/form>/isU", $this->html_data, $forms)) {
			foreach ($forms[0] as $form) {
				$this->button_counter = 0;

				//form details
				preg_match("/<form.*?name=[\"']?([\w\s-]*)[\"']?[\s>]/i", $form, $form_name);
				if ($form_name) {
					$this->_return[$this->_counter]['form_data']['name'] = preg_replace("/[\"'<>]/", "", $form_name[1]);
				}
				preg_match("/<form.*?action=(\"([^\"]*)\"|'([^']*)'|[^>\s]*)([^>]*)?>/i", $form, $action);
				if ($action) {
					$this->_return[$this->_counter]['form_data']['action'] = preg_replace("/[\"'<>]/", "", $action[1]);
				}
				preg_match("/<form.*?method=[\"']?([\w\s]*)[\"']?[\s>]/i", $form, $method);
				if ($method) {
					$this->_return[$this->_counter]['form_data']['method'] = preg_replace("/[\"'<>]/", "", $method[1]);
				}
				preg_match("/<form.*?enctype=(\"([^\"]*)\"|'([^']*)'|[^>\s]*)([^>]*)?>/i", $form, $enctype);
				if ($enctype) {
					$this->_return[$this->_counter]['form_data']['enctype'] = preg_replace("/[\"'<>]/", "", $enctype[1]);
				}
				preg_match("/<form.*?id=[\"']?([\w\s-]*)[\"']?[\s>]/i", $form, $id);
				if ($id) {
					$this->_return[$this->_counter]['form_data']['id'] = preg_replace("/[\"'<>]/", "", $id[1]);
				}

				// form elements: input type = hidden
				if (preg_match_all("/<input[^<>]+type=[\"']hidden[\"'][^<>]*>/iU", $form, $hiddens)) {
					foreach ($hiddens[0] as $hidden) {
						$this->_return[$this->_counter]['form_elements'][$this->_getName($hidden)] = array(
                            'type'  =>  'hidden',
                            'value'  =>  $this->_getValue($hidden)
						);
					}
				}

				// form elements: input type = text
				if (preg_match_all("/<input[^<>]+type=[\"']text[\"'][^<>]*>/iU", $form, $texts)) {
					foreach ($texts[0] as $text) {
						$this->_return[$this->_counter]['form_elements'][$this->_getName($text)] = array(
                            'type'  => 'text',
                            'value'  =>  $this->_getValue($text),
                            'id'  =>  $this->_getId($text),
                            'class'  =>  $this->_getClass($text)
						);
					}
				}

				// form elements: input type = password
				if (preg_match_all("/<input[^<>]+type=[\"']password[\"'][^<>]*>/iU", $form, $passwords)) {
					foreach ($passwords[0] as $password) {
						$this->_return[$this->_counter]['form_elements'][$this->_getName($password)] = array(
                            'type'  =>  'password',
                            'value'  =>  $this->_getValue($password)
						);
					}
				}

				// form elements: textarea
				if (preg_match_all("/<textarea.*>.*<\/textarea>/isU", $form, $textareas)) {
					foreach ($textareas[0] as $textarea) {
						preg_match("/<textarea.*>(.*)<\/textarea>/isU", $textarea, $textarea_value);
						$this->_return[$this->_counter]['form_elements'][$this->_getName($textarea)] = array(
                            'type'  =>  'textarea',
                            'value'  =>  $textarea_value[1]
						);
					}
				}

				// form elements: input type = checkbox
				if (preg_match_all("/<input[^<>]+type=[\"']checkbox[\"'][^<>]*>/iU", $form, $checkboxes)) {
					foreach ($checkboxes[0] as $checkbox) {
						if (preg_match("/checked/i", $checkbox)) {
							$this->_return[$this->_counter]['form_elements'][$this->_getName($checkbox)] = array(
                                'type'  =>  'checkbox',
                                'value'  =>  'on'
							);
						} else {
							$this->_return[$this->_counter]['form_elements'][$this->_getName($checkbox)] = array(
                                'type'  =>  'checkbox',
                                'value'  =>  ''
							);
						}
					}
				}

				// form elements: input type = radio
				if (preg_match_all("/<input[^<>]+type=[\"']radio[\"'][^<>]*>/iU", $form, $radios)) {
					foreach ($radios[0] as $radio) {
						if (preg_match("/checked/i", $radio)) {
							$this->_return[$this->_counter]['form_elements'][$this->_getName($radio)] = array(
                                'type'  =>  'radio',
                                'value'  =>  $this->_getValue($radio)
							);
						}
					}
				}

				// form elements: input type = submit
				if (preg_match_all("/<input[^<>]+type=[\"']submit[\"'][^<>]*>/iU", $form, $submits)) {
					foreach ($submits[0] as $submit) {
						$this->_return[$this->_counter]['buttons'][$this->button_counter] = array(
                            'type'  => 'submit',
                            'name'  => $this->_getName($submit),
                            'value'  => $this->_getValue($submit)
						);
						$this->button_counter++;
					}
				}

				// form elements: input type = button
				if (preg_match_all("/<input[^<>]+type=[\"']button[\"'][^<>]*>/iU", $form, $buttons)) {
					foreach ($buttons[0] as $button) {
						$this->_return[$this->_counter]['buttons'][$this->button_counter] = array(
                            'type'  => 'button',
                            'name'  => $this->_getName($button),
                            'value'  => $this->_getValue($button)
						);
						$this->button_counter++;
					}
				}

				// form elements: input type = reset
				if (preg_match_all("/<input[^<>]+type=[\"']reset[\"'][^<>]*>/iU", $form, $resets)) {
					foreach ($resets[0] as $reset) {
						$this->_return[$this->_counter]['buttons'][$this->button_counter] = array(
                            'type'  => 'reset',
                            'name'  => $this->_getName($reset),
                            'value'  => $this->_getValue($reset)
						);
						$this->button_counter++;
					}
				}

				// form elements: input type = image
				if (preg_match_all("/<input[^<>]+type=[\"']image[\"'][^<>]*>/iU", $form, $images)) {
					foreach ($images[0] as $image) {
						$this->_return[$this->_counter]['buttons'][$this->button_counter] = array(
                            'type'  => 'image',
                            'name'  => $this->_getName($image),
                            'value'  => $this->_getValue($image)
						);
						$this->button_counter++;
					}
				}

				// input type=select entries
				// Here I have to go on step around to grep at first all select names and then
				// the content. Seems not to work in an other way
				if (preg_match_all("/<select.*>.+<\/select>/isU", $form, $selects)) {
					foreach ($selects[0] as $select) {
						if (preg_match_all("/<option.*>.+<\/option>/isU", $select, $all_options)) {
                            $option_value = '';
							foreach ($all_options[0] as $option) {
								if (preg_match("/selected/i", $option)) {
									if (preg_match("/value=[\"'](.*)[\"']\s/iU", $option, $option_value)) {
										$option_value = $option_value[1];
										$found_selected = 1;
									} else {
										preg_match("/<option.*>(.*)<\/option>/isU", $option, $option_value);
										$option_value = $option_value[1];
										$found_selected = 1;
									}
								}
							}
							if (!isset($found_selected)) {
								if (preg_match("/value=[\"'](.*)[\"']/iU", $all_options[0][0], $option_value)) {
									$option_value = $option_value[1];
								} else {
									preg_match("/<option>(.*)<\/option>/iU", $all_options[0][0], $option_value);
									$option_value = $option_value[1];
								}
							} else {
								unset($found_selected);
							}
							$this->_return[$this->_counter]['form_elements'][$this->_getName($select)] = array(
                                'type'  => 'select',
                                'value'  => trim($option_value)
							);
						}
					}
				}

				# form elements: input type = --not defined--
				if ( preg_match_all("/<input[^<>]+name=[\"'](.*)[\"'][^<>]*>/iU", $form, $inputs)) {
					foreach ( $inputs[0] as $input ) {
						if ( !preg_match("/type=(\"([^\"]*)\"|'([^']*)'|[^>\s]*)([^>]*)?>/is", $input) ) {
							if ( !isset($this->_return[$this->_counter]['form_elements'][$this->_getName($input)]) ) {
								$this->_return[$this->_counter]['form_elements'][$this->_getName($input)] =
								array(
                                    'type'  => 'text',
                                    'value'  =>  $this->_getValue($input),
                                    'id'  =>  $this->_getId($input),
                                    'class'  =>  $this->_getClass($input)
								);

							}
						}
					}
				}

				// Update the form counter if we have more then 1 form in the HTML table
				$this->_counter++;
			}
		}
		return $this->_return;
	}

	/**
	 * gets the name
	 *
	 * @param string $string string
	 *
	 * @return string something
	 */
	function _getName($string)
	{
		if (preg_match("/name=(\"([^\"]*)\"|'([^']*)'|[^>\s]*)([^>]*)?>/is", $string, $match)) {
			//preg_match("/name=[\"']?([\w\s]*)[\"']?[\s>]/i", $string, $match)) { -- did not work as expected
			$val_match = trim($match[1]);
			$val_match = trim($val_match, "\"\'");
			unset($string);
			return trim($val_match, '"');
		}
        return false;
	}

	/**
	 * gets the value
	 *
	 * @param string $string string
	 *
	 * @return string something
	 */
	function _getValue($string)
	{
		if (preg_match("/value=(\"([^\"]*)\"|'([^']*)'|[^>\s]*)([^>]*)?>/is", $string, $match)) {
			$val_match = trim($match[1]);
			$val_match = trim($val_match, "\"\'");
			unset($string);
			return $val_match;
		}
        return false;
	}

	/**
	 * gets the id
	 *
	 * @param string $string string
	 *
	 * @return string something
	 */
	function _getId($string)
	{
		if (preg_match("/id=(\"([^\"]*)\"|'([^']*)'|[^>\s]*)([^>]*)?>/is", $string, $match)) {
			//preg_match("/name=[\"']?([\w\s]*)[\"']?[\s>]/i", $string, $match)) { -- did not work as expected
			$val_match = trim($match[1]);
			$val_match = trim($val_match, "\"\'");
			unset($string);
			return $val_match;
		}
        return false;
	}

	/**
	 * gets the class
	 *
	 * @param string $string string
	 *
	 * @return string something
	 */
	function _getClass($string)
	{
		if (preg_match("/class=(\"([^\"]*)\"|'([^']*)'|[^>\s]*)([^>]*)?>/is", $string, $match)) {
			$val_match = trim($match[1]);
			$val_match = trim($val_match, "\"\'");
			unset($string);
			return $val_match;
		}
        return false;
	}
}

/**
 * Singleton static only class that creates instances for each specific JFusion plugin.
 *
 * @category  JFusion
 * @package   Models
 * @author    Henk Wevers <henk@wevers.net>
 * @copyright 2008 JFusion - Henk Wevers. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionCurl
{
	/**
	 * NOTE: The routines buildcookie and imlodeCookies are identical to the ones in model.cookie.
	 * They are duplicated here because I want this file selfcontained so it can be used by the DSSO routines in
	 * the integrations. All you need to do there is to overload the JFusionCurl:: _ function to translate strings
	 **/

	/**
	 * Translate function, mimics the php gettext (alias _) function
	 *
	 * Do not overload when used within Joomla, the function simply calls Jtext::_
	 * When you use the JFusionCurl class outside Joomla, f.i. as part of an DSSO extension in an integration
	 * then you have to overload this function to provide the translated strings probably using native code
	 *
	 * @param string  $string The string to translate
	 * @param boolean $jsSafe Make the result javascript safe
	 *
	 * @return string The translation of the string
	 **/
    public static function _($string, $jsSafe = false)
	{
		return JText::_($string, $jsSafe);
	}

	/**
	 * Retrieve the cookies as a string cookiename=cookievalue; or as an array
	 *
	 * @param string $type the type
	 *
	 * @return string or array
	 */
	public function buildCookie($type = 'string')
	{
		switch ($type) {
			case 'array':
				return $_COOKIE;
				break;
			case 'string':
			default:
				return JFusionCurl::implodeCookies($_COOKIE, ';');
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
	 * @param array  $array     array
	 * @param string $delimeter delemeter
	 * @param string $keyssofar keyssofar
	 *
	 * @return string imploded cookies
	 */
	function implodeCookies($array, $delimeter, $keyssofar = '')
	{
		$output = '';
		foreach ($array as $key => $value) {
			if (!is_array($value)) {
				if ($keyssofar) {
					$pair = $keyssofar . '[' . $key . ']=' . urlencode($value) . $delimeter;
				} else {
					$pair = $key . '=' . urlencode($value) . $delimeter;
				}
				if ($output != '') {
					$output .= ' ';
				}
				$output .= $pair;
			} else {
				if ($output != '') {
					$output .= ' ';
				}
				$output .= self::implodeCookies($value, $delimeter, $key . $keyssofar);
			}
		}
		return $output;
	}


	/**
	 * curl redir exec
	 *
	 * @param resource $ch ch
	 *
	 * @return string something
	 */
	function curl_redir_exec($ch)
	{
		static $curl_loops = 0;
		static $curl_max_loops = 20;
		if ($curl_loops++ >= $curl_max_loops) {
			$curl_loops = 0;
			return false;
		}

		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$data = curl_exec($ch);
		$lastdata = $data;
		$data = str_replace("\r", '', $data);
		list($header, $data) = explode("\n\n", $data, 2);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($http_code == 301 || $http_code == 302) {
			$matches = array();
			preg_match('/Location:(.*?)\n/', $header, $matches);
			$url = @parse_url(trim(array_pop($matches)));
			if (!$url) {
				//couldn't process the url to redirect to
				$curl_loops = 0;
				return $data;
			}
			$last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
			/*      if (!$url['scheme'])
			 $url['scheme'] = $last_url['scheme'];
			if (!$url['host'])
			$url['host'] = $last_url['host'];
			if (!$url['path'])
			$url['path'] = $last_url['path'];
			*/      $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']?'?'.$url['query']:'');
			curl_setopt($ch, CURLOPT_URL, $new_url);
			return JFusionCurl::curl_redir_exec($ch);
		} else {
			$curl_loops=0;
			return $lastdata;
		}
	}

	/**
	 * function read_header
	 * Basic  code was found on Svetlozar Petrovs website http://svetlozar.net/page/free-code.html.
	 * The code is free to use and similar code can be found on other places on the net.
	 *
	 * @param resource $ch     ch
	 * @param string $string string
	 *
	 * @return string something
	 */
	function read_header($ch, $string)
	{
		global $location;
		global $cookiearr;
		global $ch;
		global $cookies_to_set;
		global $cookies_to_set_index;

		$length = strlen($string);
		if (!strncmp($string, "Location:", 9)) {
			$location = trim(substr($string, 9, -1));
		}
		if (!strncmp($string, "Set-Cookie:", 11)) {
			header($string, false);
			$cookiestr = trim(substr($string, 11, -1));
			$cookie = explode(';', $cookiestr);
			$cookies_to_set[$cookies_to_set_index] = $cookie;
			$cookies_to_set_index++;
			$cookie = explode('=', $cookie[0]);
			$cookiename = trim(array_shift($cookie));
			$cookiearr[$cookiename] = trim(implode('=', $cookie));
		}

		$cookie = '';
		if (!empty($cookiearr) && (trim($string) == "")) {
			foreach ($cookiearr as $key=>$value) {
				$cookie .= "$key=$value; ";
			}
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		}
		return $length;
	}

	/**
	 * function parseURL
	 * out[0] = full url
	 * out[1] = scheme or '' if no scheme was found
	 * out[2] = username or '' if no auth username was found
	 * out[3] = password or '' if no auth password was found
	 * out[4] = domain name or '' if no domain name was found
	 * out[5] = port number or '' if no port number was found
	 * out[6] = path or '' if no path was found
	 * out[7] = query or '' if no query was found
	 * out[8] = fragment or '' if no fragment was found
	 *
	 * @param string $url url
	 *
	 * @return array output
	 */
	function parseUrl($url)
	{
		$r = '!(?:(\w+)://)?(?:(\w+)\:(\w+)@)?([^/:]+)?';
		$r .= '(?:\:(\d*))?([^#?]+)?(?:\?([^#]+))?(?:#(.+$))?!i';
		preg_match($r, $url, $out);
		return $out;
	}

	/**
	 * parses cookies
	 *
	 * @param array $cookielines cookies
	 *
	 * @return array parsed cookies
	 */
	function parsecookies($cookielines)
	{
		$line=array();
		$cookies=array();
		foreach ($cookielines as $line) {
			$cdata = array();
			$data = array();
			foreach ($line as $data) {
				$cinfo = explode('=', $data);
				$cinfo[0] = trim($cinfo[0]);
				if (!isset($cinfo[1])) {
					$cinfo[1]='';
				}
				if (strcasecmp($cinfo[0], 'expires') == 0) {
					$cinfo[1] = strtotime($cinfo[1]);
				}
				if (strcasecmp($cinfo[0], 'secure') == 0) {
					$cinfo[1] = "true";
				}
				if (strcasecmp($cinfo[0], 'httponly') == 0) {
					$cinfo[1] = "true";
				}
				if (in_array(strtolower($cinfo[0]), array('domain', 'expires', 'path', 'secure', 'comment', 'httponly'))) {
					$cdata[trim($cinfo[0])] = $cinfo[1];
				} else {
					$cdata['value']['key'] = $cinfo[0];
					$cdata['value']['value'] = $cinfo[1];
				}
			}
			$cookies[] = $cdata;
		}
		return $cookies;
	}

	/**
	 * Adds a cookie to the php header
	 *
	 * @param string $name            cookie name
	 * @param string $value           cookie value
	 * @param int    $expires         cookie expiry time
	 * @param string $cookiepath      cookie path
	 * @param string $cookiedomain    cookie domain
	 * @param int $secure          secure
	 * @param int $httponly        is the cookie http only
	 * @param string $crossdomain_url cross domain url
	 *
	 * @return string nothing
	 */
    public static function addCookie($name, $value='', $expires=0, $cookiepath='', $cookiedomain='', $secure=0, $httponly=0, $crossdomain_url='')
	{

		// Versions of PHP prior to 5.2 do not support HttpOnly cookies
		// IE is buggy when specifying a blank domain so set the cookie manually
		// solve the empty cookiedomain IE problem by specifying a domain in the plugin's parameters. <------
		if (version_compare(phpversion(), "5.2.0", ">=")) {
			setcookie($name, $value, $expires, $cookiepath, $cookiedomain, $secure, $httponly);
		} else {
			setcookie($name, $value, $expires, $cookiepath, $cookiedomain, $secure);
		}
		if ($crossdomain_url) {
			$jc = JFusionFactory::getCookies();
			$jc->addCookie($name, $value, $expires, $cookiepath, $cookiedomain, $secure, $httponly);
		}
	}

	/**
	 * sets my cookies
	 *
	 * @param string $status           cookie name
	 * @param string $mycookies_to_set cookie value
	 * @param string $cookiedomain     cookie domain
	 * @param string $cookiepath       cookie path
	 * @param int $expires          expires
	 * @param int $secure           secure
	 * @param int $httponly         is the cookie http only
	 * @param string $crossdomain_url  cross domain url
	 *
	 * @return string nothing
	 */
    public static function setmycookies($status, $mycookies_to_set, $cookiedomain, $cookiepath, $expires=0, $secure=0, $httponly=1, $crossdomain_url='')
	{
		$cookies=array();
		$cookies=JFusionCurl::parsecookies($mycookies_to_set);
		foreach ($cookies as $cookie) {
			$name='';
			$value='';
			if ($expires == 0) {
				$expires_time=0;
			} else {
				$expires_time=time()+$expires;
			}
			if (isset($cookie['value']['key'])) {
				$name= $cookie['value']['key'];
			}
			if (isset($cookie['value']['value'])) {
				$value=$cookie['value']['value'];
			}
			if (isset($cookie['expires'])) {
				$expires_time=$cookie['expires'];
			}
			if (!$cookiepath) {
				if (isset($cookie['path'])) {
					$cookiepath=$cookie['path'];
				}
			}
			if (!$cookiedomain) {
				if (isset($cookie['domain'])) {
					$cookiedomain=$cookie['domain'];
				}
			}
			JFusionCurl::addCookie($name, urldecode($value), $expires_time, $cookiepath, $cookiedomain, $secure, $httponly, $crossdomain_url);

			if (($expires_time) == 0) {
				$expires_time='Session_cookie';
			} else {
				$expires_time=date('d-m-Y H:i:s', $expires_time);
			}
			$status['debug'][] = JFusionCurl::_('CREATED') . ' ' . JFusionCurl::_('COOKIE') . ': ' . JFusionCurl::_('NAME') . '=' . $name . ', ' . JFusionCurl::_('VALUE') . '=' . urldecode($value) .', ' .JFusionCurl::_('EXPIRES') . '=' .$expires_time .', ' . JFusionCurl::_('COOKIE_PATH') . '=' . $cookiepath . ', ' . JFusionCurl::_('COOKIE_DOMAIN') . '=' . $cookiedomain. ', '.JFusionCurl::_('COOKIE_SECURE') . '=' .$secure. ', '.JFusionCurl::_('COOKIE_HTTPONLY') . '=' .$httponly;
			if ($name=='MOODLEID_') {
				$status['cURL']['moodle'] = urldecode($value);
			}
		}
		return $status;
	}

	/**
	 * delete my cookies
	 *
	 * @param string $status           cookie name
	 * @param string $mycookies_to_set cookie value
	 * @param string $cookiedomain     cookie domain
	 * @param string $cookiepath       cookie path
	 * @param string $leavealone       leavealone
	 * @param int $secure           secure
	 * @param int $httponly         is the cookie http only
	 * @param string $crossdomain_url  cross domain url
	 *
	 * @return string nothing
	 */
    public static function deletemycookies($status, $mycookies_to_set, $cookiedomain, $cookiepath, $leavealone, $secure=0, $httponly=1, $crossdomain_url='')
	{
		$cookies=array();
		$cookies=JFusionCurl::parsecookies($mycookies_to_set);
		// leavealone keys/values while deleting
		// the $leavealone is an array of key=value that controls cookiedeletion
		// key = value
		// if key is an existing cookiename then that cookie will be affected depending on the value
		// if value = '>' then the 'name' cookies with an expiration date/time > now() will not be deleted
		// if value = '0' then  the 'name' cookies will never be deleted at all
		// if name is a string than the cookie with that name will be affected
		// if name = '0' then all cookies will be affected according to the value
		// thus
		// MOODLEID_=> keeps the cookie with the name MOODLEID_ if expirationtime lies after now()
		// 0=> will keep all cookies that are not sessioncookies
		// 0=0 will keep all cookies

        $leavealonearr = array();
		if (trim($leavealone)) {
			$lines = array();
			$line=array();
			$lines = explode(',', $leavealone);
			$i = 0;

			foreach ($lines as $line) {
				$cinfo = explode('=', $line);
				if (isset($cinfo[1])) {
					$leavealonearr[$i]['name']  = $cinfo[0];
					$leavealonearr[$i]['value'] = $cinfo[1];
					$i++;
				}
			}
		}

		foreach ($cookies as $cookie) {
			// check if we schould leave the cookie alone
			$leaveit = false;
			if ($leavealone) {
				for ($i=0;$i<count($leavealonearr);$i++) {
					if (isset($cookie['value']['key'])) {
						if (($cookie['value']['key']== $leavealonearr[$i]['name']) || ($leavealonearr[$i]['name']=='0')) {
							if (($leavealonearr[$i]['value'] == '0')||($cookie['expires'] > time())) {
								$leaveit = true;
							}
						}
					}
				}
			}
			$name='';
			$value='';
			if (isset($cookie['value']['key'])) {
				$name= $cookie['value']['key'];
			}
			if (isset($cookie['expires'])) {
				$expires_time=$cookie['expires'];
			} else {
                $expires_time = 0;
            }
			if (!$cookiepath) {
				if (isset($cookie['path'])) {
					$cookiepath=$cookie['path'];
				}
			}
			if (!$cookiedomain) {
				if (isset($cookie['domain'])) {
					$cookiedomain=$cookie['domain'];
				}
			}
			if ($name=='MOODLEID_') {
				$status['cURL']['moodle'] = urldecode($cookie['value']['value']);
			}

			if (!$leaveit) {
				$expires_time=time()-30*60;
				$value = '';
				JFusionCurl::addCookie($name, urldecode($value), $expires_time, $cookiepath, $cookiedomain, $secure, $httponly, $crossdomain_url);
				if (($expires_time) == 0) {
					$expires_time='Session_cookie';
				} else {
					$expires_time=date('d-m-Y H:i:s', $expires_time);
				}
				$status['debug'][] = JFusionCurl::_('DELETED') . ' ' . JFusionCurl::_('COOKIE') . ': ' . JFusionCurl::_('NAME') . '=' . $name . ', ' . JFusionCurl::_('VALUE') . '=' . urldecode($value) .', ' .JFusionCurl::_('EXPIRES') . '=' .$expires_time .', ' . JFusionCurl::_('COOKIE_PATH') . '=' . $cookiepath . ', ' . JFusionCurl::_('COOKIE_DOMAIN') . '=' . $cookiedomain. ', '.JFusionCurl::_('COOKIE_SECURE') . '=' .$secure. ', '.JFusionCurl::_('COOKIE_HTTPONLY') . '=' .$httponly;
			} else {
				JFusionCurl::addCookie($name, urldecode($cookie['value']['value']), $expires_time, $cookiepath, $cookiedomain, $secure, $httponly, $crossdomain_url);
				if (($expires_time) == 0) {
					$expires_time='Session_cookie';
				} else {
					$expires_time=date('d-m-Y H:i:s', $expires_time);
				}
				$status['debug'][] = JFusionCurl::_('LEFT_ALONE') . ' ' . JFusionCurl::_('COOKIE') . ': ' . JFusionCurl::_('NAME') . '=' . $name . ', ' . JFusionCurl::_('VALUE') . '=' . urldecode($cookie['value']['value']) .', ' .JFusionCurl::_('EXPIRES') . '=' .$expires_time .', ' . JFusionCurl::_('COOKIE_PATH') . '=' . $cookiepath . ', ' . JFusionCurl::_('COOKIE_DOMAIN') . '=' . $cookiedomain. ', '.JFusionCurl::_('COOKIE_SECURE') . '=' .$secure. ', '.JFusionCurl::_('COOKIE_HTTPONLY') . '=' .$httponly;
			}
		}
		return $status;
	}

	/**
	 * function ReadPage
	 * This function will read a page of an integration
	 * Caller should make sure that the Curl extension is loaded
     *
	 * @param array $curl_options curl options
     * @param array &$status
     * @param bool $curlinit
	 *
	 * @return string page read
	 */

    public static function ReadPage($curl_options, &$status, $curlinit=true)
	{
		global $ch;
		global $cookiearr;
		global $cookies_to_set;
		global $cookies_to_set_index;

		$cookies_to_set=array();
		$cookies_to_set_index=0;
		$open_basedir = ini_get('open_basedir');
		$safe_mode = ini_get('safe_mode');




		// read the page
		if ($curlinit) {
			$ch = curl_init();
		}
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($ch, CURLOPT_URL, $curl_options['post_url']);
		curl_setopt($ch, CURLOPT_REFERER, "");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $curl_options['verifyhost']);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, $curl_options['debug']); // Display communication with server
		if (empty($open_basedir) && empty($safe_mode)) {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		}
		curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array('JFusionCurl','read_header'));
		if (empty($curl_options['brute_force'])){
			curl_setopt($ch, CURLOPT_COOKIE, JFusionCurl::buildCookie());
		}

		if (!empty($curl_options['httpauth'])) {
			curl_setopt($ch, CURLOPT_USERPWD, "{$curl_options['httpauth_username']}:{$curl_options['httpauth_password']}");

			switch ($curl_options['httpauth']) {
				case "basic":
					$curl_options['httpauth'] = CURLAUTH_BASIC;
					break;
				case "gssnegotiate":
					$curl_options['httpauth'] = CURLAUTH_GSSNEGOTIATE;
					break;
				case "digest":
					$curl_options['httpauth'] = CURLAUTH_DIGEST;
					break;
				case "ntlm":
					$curl_options['httpauth'] = CURLAUTH_NTLM;
					break;
				case "anysafe":
					$curl_options['httpauth'] = CURLAUTH_ANYSAFE;
					break;
				case "any":
				default:
					$curl_options['httpauth'] = CURLAUTH_ANY;
			}

			curl_setopt($ch, CURLOPT_HTTPAUTH, $curl_options['httpauth']);
		}

		if (empty($open_basedir) && empty($safe_mode)) {
			$remotedata = curl_exec($ch);
		} else {
			$remotedata= JFusionCurl::curl_redir_exec($ch);
		}
		if ($curl_options['debug']) {
			$status['cURL']['data'][]= $remotedata;
			$status['debug'][]='CURL_INFO'.': '.print_r(curl_getinfo($ch), true);
		}
		if (curl_error($ch)) {
			$status['error'][] = JFusionCurl::_('CURL_ERROR_MSG').": ".curl_error($ch);
			curl_close($ch);
			return null;
		}

		if ($curl_options['integrationtype'] ==1) {
			curl_close($ch);
		}
		return $remotedata;
	}

	/**
	 * function RemoteLogin
	 * Smart function to programatically login to an JFusion integration
	 * Will determine what to post (including, optionally, hidden form inputs) and what cookies to set.
	 * Will then login.
	 * In addition to username and password the function only needs an URL to a page with a loginform
	 * and the ID of the loginform.
	 * Including button information and hidden input posts is optionally
	 *
	 * 29-07-2011 Modified to handle logout as well when the loginform is used for logout
	 * just call as login and add $curl_options['logout'] = '1'
	 *
	 * @param array $curl_options curl options
	 *
	 * @return string something
	 */
    public static function RemoteLogin($curl_options)
	{
		global $ch;
		global $cookiearr;
		global $cookies_to_set;
		global $cookies_to_set_index;
		// extra lines for passing curl options to other routines, like ambrasubs payment processor
		// we are using the super global $_SESSION to pass data in $_SESSION[$var]
		if (isset($curl_options['usesessvar'])){
			$var = 'curl_options';
			if(!array_key_exists($var,$_SESSION)) $_SESSION[$var]='';
			$_SESSION[$var]=$curl_options;
			$GLOBALS[$var]=&$_SESSION[$var];
		}
		// end extra lines
        $status = array('error' => array(),'debug' => array());
		$tmpurl = array();
		$overridearr = array();
		$newhidden = array();
		$lines = array();
		$line=array();
		$cookies_to_set=array();
		$status['cURL']=array();
		$status['cURL']['moodle']='';
		$status['cURL']['data']= array();
		$cookies_to_set_index=0;
		$open_basedir = ini_get('open_basedir');
		$safe_mode = ini_get('safe_mode');

		// check parameters and set defaults
		if (!isset($curl_options['integrationtype'])) {
			$curl_options['integrationtype'] = 1;
		}
		if (!isset($curl_options['relpath'])) {
			$curl_options['relpath'] = false;
		}
		if (!isset($curl_options['hidden'])) {
			$curl_options['hidden'] = false;
		}
		if (!isset($curl_options['buttons'])) {
			$curl_options['buttons'] = false;
		}
		if (!isset($curl_options['override'])) {
			$curl_options['override'] = null;
		}
		if (!isset($curl_options['cookiedomain'])) {
			$curl_options['cookiedomain'] = '';
		}
		if (!isset($curl_options['cookiepath'])) {
			$curl_options['cookiepath'] = '';
		}
		if (!isset($curl_options['expires'])) {
			$curl_options['expires'] = 1800;
		}
		if (!isset($curl_options['input_username_id'])) {
			$curl_options['input_username_id'] = '';
		}
		if (!isset($curl_options['input_password_id'])) {
			$curl_options['input_password_id'] = '';
		}
		if (!isset($curl_options['secure'])) {
			$curl_options['secure'] = 0;
		}
		if (!isset($curl_options['httponly'])) {
			$curl_options['httponly'] = 0;
		}
		if (!isset($curl_options['verifyhost'])) {
			$curl_options['verifyhost'] = 1;
		}
		if (!isset($curl_options['crossdomain_url'])) {
			$curl_options['crossdomain_url'] = '';
		}
		if (!isset($curl_options['debug'])) {
			$curl_options['debug'] = false;
		}

		// find out if we have a SSL enabled website
		if (strpos($curl_options['post_url'], 'https://') === false) {
			$ssl_string = 'http://';
		} else {
			$ssl_string = 'https://';
		}

		// check if curl extension is loaded
		if (!isset($curl_options['post_url']) || !isset($curl_options['formid'])) {
			$status['error'][] = JFusionCurl::_('CURL_FATAL');
			return $status;
		}
		if (!extension_loaded('curl')) {
			$status['error'][] = JFusionCurl::_('CURL_NOTINSTALLED');
			return $status;
		}
		$status['debug'][] = JFusionCurl::_('CURL_POST_URL_1')." ".$curl_options['post_url'];
		$remotedata = JFusionCurl::ReadPage($curl_options, $status,true);
		if (!empty($status['error'])) {
			return $status;
		}
		$status['debug'][] = JFusionCurl::_('CURL_PHASE_1');
		$status1=JFusionCurl::setmycookies($status, $cookies_to_set, $curl_options['cookiedomain'], $curl_options['cookiepath'], $curl_options['expires'], $curl_options['secure'], $curl_options['httponly'], $curl_options['crossdomain_url']);
		$status = array_merge($status,$status1);
		//find out if we have the form with the name/id specified
		$parser = new JFusionCurlHtmlFormParser($remotedata);
		$result = $parser->parseForms();
		$frmcount = count($result);
		$myfrm = -1;
		$i = 0;
		do {
			if (isset($result[$i]['form_data']['name'])) {
				if ($result[$i]['form_data']['name']==$curl_options['formid']) {
					$myfrm = $i;
					break;
				}
			}
			if (isset($result[$i]['form_data']['id'])) {
				if ($result[$i]['form_data']['id']==$curl_options['formid']) {
					$myfrm = $i;
					break;
				}
			}
			if (isset($result[$i]['form_data']['action'])) {
				if (htmlspecialchars_decode($result[$i]['form_data']['action'])==$curl_options['formid']) {
					$myfrm = $i;
					break;
				}
			}
			$i +=1;
		} while ($i<$frmcount);

		if ($myfrm == -1) {
			$helpthem = '';
			if ($frmcount >0) {
				$i = 0;
				$helpthem = 'I found';
				do {
					if (isset($result[$i]['form_data']['id'])) {
						$helpthem = $helpthem.' -- Name='.$result[$i]['form_data']['name'].' &ID='.$result[$i]['form_data']['id'];
					}
					$i +=1;
				} while ($i<$frmcount);
			}
			$status['debug'][] = JFusionCurl::_('CURL_NO_LOGINFORM')." ".$helpthem;
			return $status;
		}
		$status['debug'][] = JFusionCurl::_('CURL_VALID_FORM');


		// by now we have the specified  login/logout form, lets get the data needed to login/logout
		// we went to all this trouble to get to the hidden input entries.
		// The stuff is there to enhance security and is, yes, hidden
		$form_action = htmlspecialchars_decode($result[$myfrm]['form_data']['action']);
		$form_method = $result[$myfrm]['form_data']['method'];
		$elements_keys = array_keys($result[$myfrm]['form_elements']);
		$elements_values = array_values($result[$myfrm]['form_elements']);
		$elements_count  = count($result[$myfrm]['form_elements']);

		// override keys/values from hidden inputs
		// the $override is an array of keys/values that override existing keys/values

		if (empty($curl_options['logout'])){

			if ($curl_options['override']) {
				$lines = explode(',', $curl_options['override']);
				foreach ($lines as $line) {
					$cinfo = explode('=', $line);
					$overridearr[$cinfo[0]]['value'] = $cinfo[1];
					$overridearr[$cinfo[0]]['type'] = 'hidden';
				}
				$newhidden= array_merge($result[$myfrm]['form_elements'], $overridearr);
				$elements_keys = array_keys($newhidden);
				$elements_values = array_values($newhidden);
				$elements_count  = count($newhidden);
			}
		}
		// now construct the action parameter

		// we have 4 possible options:
		// case 0 Form action is without httpo.. and relpath = 0 , special case
		// case 1 Form action is without http.. and relpath = 1 , just construct the action
		// case 2 form_action is a full url, eg http..... and relpath = 0 This is easy, we do nothing at all
		// case 3 form_action is a full url, eg http..... and relpath = 1 special case

		$rel = (int)($curl_options['relpath']);
		//      if (substr($form_action,0,strlen($ssl_string))== $ssl_string) $hashttp = 2; else $hashttp = 0;
		if (substr($form_action, 0, strlen('http'))== 'http') {
			$hashttp = 2;
		} else {
			$hashttp = 0;
		}

		switch($rel+$hashttp) {
			case 0:
				//add a / in front of form_action
				if (substr($form_action, 0, 1) != "/") {
					$form_action = '/'.$form_action;
				}
				// we need to correct various situations like
				// relative url from basedir, relative url from postdir etc
				$tmpurl   = JFusionCurl::parseUrl($curl_options['post_url']);
				$pathinfo1  = pathinfo($form_action);
				$pathinfo = pathinfo($tmpurl[6]);
				//$status['debug'][] = 'post_url   : '.print_r($curl_options['post_url'],true);
				//$status['debug'][] = 'tmpurl     : '.print_r($tmpurl,true);
				//$status['debug'][] = 'form_action: '.print_r($form_action,true);
				//$status['debug'][] = 'pathinfo1  : '.print_r($pathinfo1,true);
				//$status['debug'][] = 'pathinfo   : '.print_r($pathinfo,true);
				if ($pathinfo['dirname'] == $pathinfo1['dirname']) {
					$pathinfo['dirname']='';
				} //prevent double directory

				// replace windows DS bt unix DS
				$pathinfo['dirname'] = str_replace("\\", "/", $pathinfo['dirname']);
				// get rid of the trailing /  in dir
				rtrim($pathinfo['dirname'], '/');
				$port = !empty($tmpurl[5]) ? ":".$tmpurl[5] : '';
				$form_action = $ssl_string.$tmpurl[4].$port.$pathinfo['dirname'].$form_action;
				//$status['debug'][] = 'form_action_final: '.print_r($form_action,true);
				break;
			case 1:
				//add a / in front of form_action
				if (substr($form_action, 0, 1) != "/") {
					$form_action = '/'.$form_action;
				}
				$curl_options['post_url']=rtrim($curl_options['post_url'], '/');
				$form_action = $curl_options['post_url'].$form_action;
				break;
			case 2:
				//do nothing at all
				break;
			case 3:
				// reserved, maybe something pops up, then we use this
				break;
		}

        $input_username_name='';
        $input_password_name = '';
		if (empty($curl_options['logout'])){
			for ($i = 0; $i <= $elements_count-1; $i++) {
				if ($curl_options['input_username_id']) {
					if (strtolower($elements_keys[$i]) == strtolower($curl_options['input_username_id'])) {
						$input_username_name=$elements_keys[$i];
						break;
					}
				}
				if ($input_username_name == "") {
					if (strpos(strtolower($elements_keys[$i]), 'user')!==false) {
						$input_username_name=$elements_keys[$i];
					}
					if (strpos(strtolower($elements_keys[$i]), 'name')!==false) {
						$input_username_name=$elements_keys[$i];
					}
				}
			}


			if ($input_username_name == "") {
				$status['error'][] = JFusionCurl::_('CURL_NO_NAMEFIELD');
				return $status;
			}

			for ($i = 0; $i <= $elements_count-1; $i++) {
				if ($curl_options['input_password_id']) {
					if (strtolower($elements_keys[$i]) == strtolower($curl_options['input_password_id'])) {
						$input_password_name=$elements_keys[$i];
						break;
					}
				}
				if (strpos(strtolower($elements_keys[$i]), 'pass')!==false) {
					$input_password_name=$elements_keys[$i];
				}
			}

			if ($input_password_name=="") {
				$status['error'][] = JFusionCurl::_('CURL_NO_PASSWORDFIELD');
				return $status;
			}
			$status['debug'][] = JFusionCurl::_('CURL_VALID_USERNAME');
		}
		// we now set the submit parameters. These are:
		// all form_elements name=value combinations with value != '' and type hidden
		$strParameters='';
		if ($curl_options['hidden']) {
			for ($i = 0; $i <= $elements_count-1; $i++) {
				if (($elements_values[$i] ['value'] != '')&& ($elements_values[$i] ['type'] == 'hidden')) {
					$strParameters .= '&'.$elements_keys[$i].'='.urlencode($elements_values[$i] ['value']);
				}
			}
		}

		// code for buttons submitted by Daniel Baur
		if ($curl_options['buttons']) {
			if (isset($result[$myfrm] ['buttons'][0]['type'])) {
				if ($result[$myfrm] ['buttons'][0]['type'] =='submit') {
					if ($result[$myfrm]['buttons'][0]['name']) {
						$strParameters .= '&'.$result[$myfrm]['buttons'][0]['name'].'='.urlencode($result[$myfrm] ['buttons'][0]['value']);
					} else {
						$strParameters .= '&'.'submit'.'='.urlencode($result[$myfrm] ['buttons'][0]['value']);
					}
				}
			}
		}

		// extra post parameter to avoid endless loop when more then one jFusion is installed
		if (isset($curl_options['jnodeid'])) {
			$strParameters .= '&jnodeid='.urlencode($curl_options['jnodeid']);
		}

		// extra post parameter to signal a host calling
		if (isset($curl_options['jhost'])) {
			$strParameters .= '&jhost=true';
		}

		if (empty($curl_options['logout'])){
			$post_params = $input_username_name."=".urlencode($curl_options['username'])."&".$input_password_name."=".urlencode($curl_options['password']);
			$post_params_debug = $input_username_name."=".urlencode($curl_options['username'])."&".$input_password_name."=xxxxxx";
			$status['debug'][] = JFusionCurl::_('CURL_STARTING_LOGIN')." ".$form_action." parameters= ".$post_params_debug.$strParameters;
		} else {
			$post_params = '';
			$status['debug'][] = JFusionCurl::_('CURL_STARTING_LOGOUT')." ".$form_action." parameters= ".$strParameters;
		}



		// finally submit the login/logout form:
		if ($curl_options['integrationtype'] == 1) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt($ch, CURLOPT_REFERER, "");
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $curl_options['verifyhost']);
			curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			curl_setopt($ch, CURLOPT_HEADERFUNCTION, array('JFusionCurl','read_header'));
			if (empty($curl_options['brute_force'])){
				curl_setopt($ch, CURLOPT_COOKIE, JFusionCurl::buildCookie());
			}
			curl_setopt($ch, CURLOPT_VERBOSE, $curl_options['debug']); // Display communication with server
		}
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_URL, $form_action);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params.$strParameters);
		if (!empty($curl_options['httpauth'])) {
			curl_setopt($ch, CURLOPT_USERPWD, "{$curl_options['httpauth_username']}:{$curl_options['httpauth_password']}");
			curl_setopt($ch, CURLOPT_HTTPAUTH, $curl_options['httpauth']);
		}

		$remotedata = curl_exec($ch);
		if ($curl_options['debug']) {
			$status['cURL']['data'][]= $remotedata;
			$status['debug'][]='CURL_INFO'.': '.print_r(curl_getinfo($ch), true);
		}
		if (curl_error($ch)) {
			$status['error'][] = JFusionCurl::_('CURL_ERROR_MSG').": ".curl_error($ch);
			curl_close($ch);
			return $status;
		}
		curl_close($ch);

		//we have to set the cookies now

		if (empty($curl_options['logout'])){
			$status['debug'][] = JFusionCurl::_('CURL_LOGIN_FINISHED');
			$status=JFusionCurl::setmycookies($status, $cookies_to_set, $curl_options['cookiedomain'], $curl_options['cookiepath'], $curl_options['expires'], $curl_options['secure'], $curl_options['httponly'], $curl_options['crossdomain_url']);
		} else {
			$status['debug'][] = JFusionCurl::_('CURL_LOGOUT_FINISHED');
			$status=JFusionCurl::deletemycookies($status, $cookies_to_set, $curl_options['cookiedomain'], $curl_options['cookiepath'], $curl_options['expires'], $curl_options['secure'], $curl_options['httponly'], $curl_options['crossdomain_url']);
		}
		$cookies_to_set_index = 0;
		return $status;
	}

	/**
	 * RemoteLogout
	 *
	 * @param array $curl_options curl options
	 *
	 * @return string something
	 */
    public static function RemoteLogout($curl_options)
	{
		$status=array();
		global $ch;
		global $cookiearr;
		global $cookies_to_set;
		global $cookies_to_set_index;
		$tmpurl = array();
		$cookies_to_set=array();
		$cookies_to_set_index=0;
		$status['debug']=array();
		$status['error']=array();
		$status['cURL']=array();
		$status['cURL']['moodle']='';
		$status['cURL']['data']= array();

		// check parameters and set defaults
		if (!isset($curl_options['post_url'])) {
			$status['error'][]= 'Fatal programming error : no post_url!';
			return $status;
		}
		if (!isset($curl_options['cookiedomain'])) {
			$curl_options['cookiedomain'] = '';
		}
		if (!isset($curl_options['cookiepath'])) {
			$curl_options['cookiepath'] = '';
		}
		if (!isset($curl_options['leavealone'])) {
			$curl_options['leavealone'] = null;
		}
		if (!isset($curl_options['secure'])) {
			$curl_options['secure'] = 0;
		}
		if (!isset($curl_options['httponly'])) {
			$curl_options['httponly'] = 0;
		}
		if (!isset($curl_options['verifyhost'])) {
			$curl_options['verifyhost'] = 1;
		}
		if (!isset($curl_options['crossdomain_url'])) {
			$curl_options['crossdomain_url'] = '';
		}
		if (!isset($curl_options['debug'])) {
			$curl_options['debug'] = false;
		}

		// prevent usererror by not supplying trailing backslash.
		// make sure that when parameters are sent we do not add a backslash
		if (strpos($curl_options['post_url'], '?') === false) {
			if (!(substr($curl_options['post_url'], -1) == "/")) {
				$curl_options['post_url'] = $curl_options['post_url']."/";
			}
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($ch, CURLOPT_REFERER, "");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $curl_options['verifyhost']);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array('JFusionCurl','read_header'));
		curl_setopt($ch, CURLOPT_URL, $curl_options['post_url']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, $curl_options['debug']); // Display communication with server

		if (!empty($curl_options['httpauth'])) {
			curl_setopt($ch, CURLOPT_USERPWD, "{$curl_options['httpauth_username']}:{$curl_options['httpauth_password']}");

			switch ($curl_options['httpauth']) {
				case "basic":
					$curl_options['httpauth'] = CURLAUTH_BASIC;
					break;
				case "gssnegotiate":
					$curl_options['httpauth'] = CURLAUTH_GSSNEGOTIATE;
					break;
				case "digest":
					$curl_options['httpauth'] = CURLAUTH_DIGEST;
					break;
				case "ntlm":
					$curl_options['httpauth'] = CURLAUTH_NTLM;
					break;
				case "anysafe":
					$curl_options['httpauth'] = CURLAUTH_ANYSAFE;
					break;
				case "any":
				default:
					$curl_options['httpauth'] = CURLAUTH_ANY;
			}

			curl_setopt($ch, CURLOPT_HTTPAUTH, $curl_options['httpauth']);
		}

		$remotedata = curl_exec($ch);
		if ($curl_options['debug']) {
			$status['cURL']['data'][]= $remotedata;
			$status['debug'][]='CURL_INFO'.': '.print_r(curl_getinfo($ch), true);
		}
		if (curl_error($ch)) {
			$status['error'][] = JFusionCurl::_('CURL_ERROR_MSG').": ".curl_error($ch);
			curl_close($ch);
			return $status;
		}
		curl_close($ch);

		//we have to delete the cookies now
		$status=JFusionCurl::deletemycookies($status, $cookies_to_set, $curl_options['cookiedomain'], $curl_options['cookiepath'], $curl_options['leavealone'], $curl_options['secure'], $curl_options['httponly'], $curl_options['crossdomain_url']);
		$cookies_to_set_index = 0;
		return $status;
	}

	/**
	 * remote logout url
	 *
	 * @param array $curl_options curl options
	 *
	 * @return string something
	 */
    public static function RemoteLogoutUrl($curl_options)
	{
		$status=array();
		global $ch;
		global $cookiearr;
		global $cookies_to_set;
		global $cookies_to_set_index;
		$tmpurl = array();
		$cookies_to_set=array();
		$cookies_to_set_index=0;
		$status['debug']=array();
		$status['error']=array();
		$status['cURL']=array();
		$status['cURL']['moodle']='';
		$status['cURL']['data']= array();
		$open_basedir = ini_get('open_basedir');
		$safe_mode = ini_get('safe_mode');

		// check parameters and set defaults
		if (!isset($curl_options['post_url'])) {
			$status['error'][]= 'Fatal programming error : no post_url!';
			return $status;
		}
		if (!isset($curl_options['cookiedomain'])) {
			$curl_options['cookiedomain'] = '';
		}
		if (!isset($curl_options['cookiepath'])) {
			$curl_options['cookiepath'] = '';
		}
		if (!isset($curl_options['leavealone'])) {
			$curl_options['leavealone'] = null;
		}
		if (!isset($curl_options['secure'])) {
			$curl_options['secure'] = 0;
		}
		if (!isset($curl_options['httponly'])) {
			$curl_options['httponly'] = 0;
		}
		if (!isset($curl_options['verifyhost'])) {
			$curl_options['verifyhost'] = 1;
		}
		if (!isset($curl_options['crossdomain_url'])) {
			$curl_options['crossdomain_url'] = '';
		}
		if (!isset($curl_options['debug'])) {
			$curl_options['debug'] = false;
		}
		if (!isset($curl_options['postfields'])) {
			$curl_options['postfields'] = '';
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($ch, CURLOPT_REFERER, "");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $curl_options['verifyhost']);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array('JFusionCurl','read_header'));
		curl_setopt($ch, CURLOPT_URL, $curl_options['post_url']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIE, JFusionCurl::buildCookie());
		curl_setopt($ch, CURLOPT_VERBOSE, $curl_options['debug']); // Display communication with server
		if (empty($open_basedir) && empty($safe_mode)) {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		}

		if (isset($curl_options['jnodeid'])) {
			if ($curl_options['postfields']) {
				$curl_options['postfields']=$curl_options['postfields'].'&jnodeid='.$curl_options['jnodeid'];
			} else {
				$curl_options['postfields']= 'jnodeid='.$curl_options['jnodeid'];
			}
		}

		if ($curl_options['postfields']){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $curl_options['postfields']);
		}
		curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array('JFusionCurl','read_header'));
		curl_setopt($ch, CURLOPT_COOKIE, JFusionCurl::buildCookie());

		if (!empty($curl_options['httpauth'])) {
			curl_setopt($ch, CURLOPT_USERPWD, "{$curl_options['httpauth_username']}:{$curl_options['httpauth_password']}");

			switch ($curl_options['httpauth']) {
				case "basic":
					$curl_options['httpauth'] = CURLAUTH_BASIC;
					break;
				case "gssnegotiate":
					$curl_options['httpauth'] = CURLAUTH_GSSNEGOTIATE;
					break;
				case "digest":
					$curl_options['httpauth'] = CURLAUTH_DIGEST;
					break;
				case "ntlm":
					$curl_options['httpauth'] = CURLAUTH_NTLM;
					break;
				case "anysafe":
					$curl_options['httpauth'] = CURLAUTH_ANYSAFE;
					break;
				case "any":
				default:
					$curl_options['httpauth'] = CURLAUTH_ANY;
			}

			curl_setopt($ch, CURLOPT_HTTPAUTH, $curl_options['httpauth']);
		}

		if (empty($open_basedir) && empty($safe_mode)) {
			$remotedata = curl_exec($ch);
		} else {
			$remotedata= JFusionCurl::curl_redir_exec($ch);
		}
		if ($curl_options['debug']) {
			$status['cURL']['data'][]= $remotedata;
			$status['debug'][]='CURL_INFO'.': '.print_r(curl_getinfo($ch), true);
		}
		$status['debug'][]= JText::_('CURL_LOGOUT_URL').': '.  $curl_options['post_url'];
		if (curl_error($ch)) {
			$status['error'][] = JFusionCurl::_('CURL_ERROR_MSG').": ".curl_error($ch);
			curl_close($ch);
			return $status;
		}
		curl_close($ch);
		$status=JFusionCurl::setmycookies($status, $cookies_to_set, $curl_options['cookiedomain'], $curl_options['cookiepath'], $curl_options['expires'], $curl_options['secure'], $curl_options['httponly'], $curl_options['crossdomain_url']);
		$cookies_to_set_index = 0;
		return $status;
	}
}
