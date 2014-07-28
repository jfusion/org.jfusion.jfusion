<?php namespace JFusion;

/**
 * Model for all jfusion related function
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

use Joomla\Language\Text;


use JFusion\Parser\Parser;
use Psr\Log\LogLevel;
use \stdClass;
use \SimpleXMLElement;
use \Exception;
use \RuntimeException;


/**
 * Class for general JFusion functions
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class Framework
{
	/**
	 * Returns the JFusion plugin name of the software that is currently the master of user management
	 *
	 * @return object master details
	 */
	public static function getMaster()
	{
		static $jfusion_master;
		if (!isset($jfusion_master)) {
			$db = Factory::getDBO();

			$query = $db->getQuery(true)
				->select('*')
				->from('#__jfusion')
				->where('master = 1')
				->where('status = 1');

			$db->setQuery($query);
			$jfusion_master = $db->loadObject();
		}
		return $jfusion_master;
	}
	
	/**
	 * Returns the JFusion plugin name of the software that are currently the slaves of user management
	 *
	 * @return object slave details
	 */
	public static function getSlaves()
	{
		static $jfusion_slaves;
		if (!isset($jfusion_slaves)) {
			$db = Factory::getDBO();

			$query = $db->getQuery(true)
				->select('*')
				->from('#__jfusion')
				->where('slave = 1')
				->where('status = 1');

			$db->setQuery($query);
			$jfusion_slaves = $db->loadObjectList();
		}
		return $jfusion_slaves;
	}

    /**
     * Delete old user data in the lookup table
     *
     * @param object $userinfo userinfo of the user to be deleted
     *
     * @return string nothing
     */
    public static function removeUser($userinfo)
    {
	    /**
	     * TODO: need to be change to remove the user correctly with the new layout.
	     */
	    //Delete old user data in the lookup table
	    $db = Factory::getDBO();

	    try {
		    $query = $db->getQuery(true)
			    ->delete('#__jfusion_users_plugin')
			    ->where('userid = ' . $userinfo->userid);
	        $db->setQuery($query);

		    $db->execute();
	    } catch (Exception $e) {
		    static::raise(LogLevel::WARNING, $e);
	    }
    }

    /**
     * Parses text from bbcode to html, html to bbcode, or html to plaintext
     * $options include:
     * strip_all_html - if $to==bbcode, strips all unsupported html from text (default is false)
     * bbcode_patterns - if $to==bbcode, adds additional html to bbcode rules; array [0] startsearch, [1] startreplace, [2] endsearch, [3] endreplace
     * parse_smileys - if $to==html, disables the bbcode smiley parsing; useful for plugins that do their own smiley parsing (default is true)
	 * custom_smileys - if $to==html, adds custom smileys to parser; array in the format of array[$smiley] => $path.  For example $options['custom_smileys'][':-)'] = 'http://mydomain.com/smileys/smile.png';
     * html_patterns - if $to==html, adds additional bbcode to html rules;
     *     Must be an array of elements with the custom bbcode as the key and the value in the format described at http://nbbc.sourceforge.net/readme.php?page=usage_add
     *     For example $options['html_patterns']['mono'] = array('simple_start' => '<tt>', 'simple_end' => '</tt>', 'class' => 'inline', 'allow_in' => array('listitem', 'block', 'columns', 'inline', 'link'));
     * character_limit - if $to==html OR $to==plaintext, limits the number of visible characters to the user
     * plaintext_line_breaks - if $to=='plaintext', should line breaks when converting to plaintext be replaced with <br /> (br) (default), converted to spaces (space), or left as \n (n)
     * plain_tags - if $to=='plaintext', array of custom bbcode tags (without brackets) that should be stripped
     *
     * @param string $text    the actual text
     * @param string $to      what to convert the text to; bbcode, html, or plaintext
     * @param mixed  $options array with parser options
     *
     * @return string with converted text
     */
    public static function parseCode($text, $to, $options = array())
    {
	    $parser = new Parser();
	    return $parser->parseCode($text, $to, $options);
    }

	/**
	 * Retrieves the source of the avatar for a Joomla supported component
	 *
	 * @param string  $software    software name
	 * @param User\Userinfo $userinfo
	 *
	 * @return string nothing
	 */
	public static function getAltAvatar($software, $userinfo)
	{
		$application = Factory::getApplication();
		try {
			if (!$userinfo) {
				//no user was found
				return $application->getDefaultAvatar();
			} else {
				switch($software) {
					case 'gravatar':
						$avatar = 'http://www.gravatar.com/avatar.php?gravatar_id=' . md5(strtolower($userinfo->email)) . '&size=40';
						break;
					default:
						$avatar = $application->getDefaultAvatar();
						break;
				}
			}
		} catch (Exception $e) {
			$avatar = $application->getDefaultAvatar();
		}
		return $avatar;
	}

    /**
     * Converts a string to all ascii characters
     *
     * @param string $input str to convert
     *
     * @return string converted string
     */
    public static function strtoascii($input)
    {
        $output = '';
        foreach (str_split($input) as $char) {
            $output.= '&#' . ord($char) . ';';
        }
        return $output;
    }

	/**
	 * Convert a utf-8 joomla string in to a valid encoding matching the table/filed it will be sent to
	 *
	 * @static
	 *
	 * @param string $string string to convert
	 * @param string $jname  used to get the database object, and point to the static stored data
	 * @param string $table  table that we will be looking at
	 * @param string $field  field that we will be looking at
	 *
	 * @throws RuntimeException
	 * @return bool|string
	 */
    public static function encodeDBString($string, $jname, $table, $field) {
        static $data;
	    if (!isset($data)) {
		    $data = array();
	    }

        if (!isset($data[$jname][$table])) {
            $db = Factory::getDatabase($jname);
            $query = 'SHOW FULL FIELDS FROM ' . $table;
            $db->setQuery($query);
            $fields = $db->loadObjectList();

            foreach ($fields as $f) {
                if ($f->Collation) {
                    $data[$jname][$table][$f->Field] = $f->Collation;
                }
            }
        }

        if (isset($data[$jname][$table][$field]) ) {
	        $encoding = false;
        	list($charset) = explode('_', $data[$jname][$table][$field]);
            switch ($charset) {
                case 'latin1':
                	$encoding = 'ISO-8859-1';
                    break;
                case 'utf8':
                    break;
                default:
	                throw new RuntimeException('JFusion Encoding support missing: ' . $charset);
                    break;
            }
            if ($encoding) {
	            if (function_exists ('iconv')) {
                    $converted = iconv('utf-8', $encoding, $string);
	            } else if (function_exists('mb_convert_encoding')) {
                    $converted = mb_convert_encoding($string, $encoding, 'utf-8');
                } else {
		            throw new RuntimeException('JFusion: missing iconv or mb_convert_encoding');
                }
                if ($converted !== false) {
                	$string = $converted;
                } else {
	                throw new RuntimeException('JFusion Encoding failed ' . $charset);
                }
            }
        }
        return $string;
    }

    /**
     * Check if feature exists
     *
     * @static
     * @param string $jname
     * @param string $feature feature
     *
     * @return bool
     */
    public static function hasFeature($jname, $feature) {
        $return = false;
	    $admin = Factory::getAdmin($jname);
	    $public = Factory::getFront($jname);
	    $user = Factory::getUser($jname);
        switch ($feature) {
            //Admin Features
            case 'wizard':
	            $return = $admin->methodDefined('setupFromPath');
                break;
            //Public Features
            case 'search':
                $return = ($public->methodDefined('getSearchQuery') || $public->methodDefined('getSearchResults'));
                break;
            case 'whosonline':
                $return = $public->methodDefined('getOnlineUserQuery');
                break;
            case 'breadcrumb':
                $return = $public->methodDefined('getPathWay');
                break;
            case 'frontendlanguage':
                $return = $public->methodDefined('setLanguageFrontEnd');
                break;
            case 'frameless':
                $return = $public->methodDefined('getBuffer');
                break;
            //User Features
            case 'useractivity':
                $return = $user->methodDefined('activateUser');
                break;
            case 'duallogin':
                $return = $user->methodDefined('createSession');
                break;
            case 'duallogout':
                $return = $user->methodDefined('destroySession');
                break;
            case 'updatepassword':
                $return = $user->methodDefined('updatePassword');
                break;
            case 'updateusername':
                $return = $user->methodDefined('updateUsername');
                break;
            case 'updateemail':
                $return = $user->methodDefined('updateEmail');
                break;
            case 'updateusergroup':
                $return = $user->methodDefined('updateUsergroup');
                break;
            case 'updateuserlanguage':
                $return = $user->methodDefined('updateUserLanguage');
                break;
            case 'syncsessions':
                $return = $user->methodDefined('syncSessions');
                break;
            case 'blockuser':
                $return = $user->methodDefined('blockUser');
                break;
            case 'activateuser':
                $return = $user->methodDefined('activateUser');
                break;
            case 'deleteuser':
                $return = $user->methodDefined('deleteUser');
                break;
        }
        return $return;
    }

	/**
	 * Checks to see if a JFusion plugin is properly configured
	 *
	 * @param string $data file path or file content
	 * @param boolean $isFile load from file
	 *
	 * @return SimpleXMLElement returns true if plugin is correctly configured
	 */
	public static function getXml($data, $isFile=true)
	{
		// Disable libxml errors and allow to fetch error information as needed
		libxml_use_internal_errors(true);

		if ($isFile) {
			// Try to load the XML file
			$xml = simplexml_load_file($data);
		} else {
			// Try to load the XML string
			$xml = simplexml_load_string($data);
		}

		if ($xml === false) {
			static::raise(LogLevel::ERROR, Text::_('JLIB_UTIL_ERROR_XML_LOAD'));

			if ($isFile) {
				static::raise(LogLevel::ERROR, $data);
			}
			foreach (libxml_get_errors() as $error) {
				static::raise(LogLevel::ERROR, $error->message);
			}
		}
		return $xml;
	}

	/**
	 * Raise warning function that can handle arrays
	 *
	 * @param        $type
	 * @param array|string|Exception  $message   message itself
	 * @param string $jname
	 *
	 * @return string nothing
	 */
	public static function raise($type, $message, $jname = '') {
		if (is_array($message)) {
			foreach ($message as $msgtype => $msg) {
				//if still an array implode for nicer display
				if (is_numeric($msgtype)) {
					$msgtype = $jname;
				}
				static::raise($type, $msg, $msgtype);
			}
		} else {
			$app = Factory::getApplication();
			if ($message instanceof Exception) {
				$message = $message->getMessage();
			}
			if (!empty($jname)) {
				$message = $jname . ': ' . $message;
			}
			$app->enqueueMessage($message, strtolower($type));
			/**
			 * TODO: REMOVE

			switch(strtolower($type)) {
				case 'notice':
					static::raiseNotice($message, $jname);
					break;
				case 'error':
					static::raiseError($message, $jname);
					break;
				case 'warning':
					static::raiseWarning($message, $jname);
					break;
				case 'message':
					static::raiseMessage($message, $jname);
					break;
			}
			 */
		}
	}

	/**
	 * @param string $filename file name or url
	 *
	 * @return boolean|stdClass
	 */
	public static function getImageSize($filename) {
		$result = false;
		ob_start();

		if (strpos($filename, '://') !== false && function_exists('fopen') && ini_get('allow_url_fopen')) {
			$stream = fopen($filename, 'r');

			$rawdata = stream_get_contents($stream, 24);
			if($rawdata) {
				$type = null;
				/**
				 * check for gif
				 */
				if (strlen($rawdata) >= 10 && strpos($rawdata, 'GIF89a') === 0 || strpos($rawdata, 'GIF87a') === 0) {
					$type = 'gif';
				}
				/**
				 * check for png
				 */
				if (!$type && strlen($rawdata) >= 24) {
					$head = unpack('C8', $rawdata);
					$png = array(1 => 137, 2 => 80, 3 => 78, 4 => 71, 5 => 13, 6 => 10, 7 => 26, 8 => 10);
					if ($head === $png) {
						$type = 'png';
					}
				}
				/**
				 * check for jpg
				 */
				if (!$type) {
					$soi = unpack('nmagic/nmarker', $rawdata);
					if ($soi['magic'] == 0xFFD8) {
						$type = 'jpg';
					}
				}
				if (!$type) {
					if ( substr($rawdata, 0, 2) == 'BM' ) {
						$type = 'bmp';
					}
				}
				switch($type) {
					case 'gif':
						$data = unpack('c10', $rawdata);

						$result = new stdClass;
						$result->width = $data[8]*256 + $data[7];
						$result->height = $data[10]*256 + $data[9];
						break;
					case 'png':
						$type = substr($rawdata, 12, 4);
						if ($type === 'IHDR') {
							$info = unpack('Nwidth/Nheight', substr($rawdata, 16, 8));

							$result = new stdClass;
							$result->width = $info['width'];
							$result->height = $info['height'];
						}
						break;
					case 'bmp':
						$header = unpack('H*', $rawdata);
						// Process the header
						// Structure: http://www.fastgraph.com/help/bmp_header_format.html
						// Cut it in parts of 2 bytes
						$header = str_split($header[1], 2);
						$result = new stdClass;
						$result->width = hexdec($header[19] . $header[18]);
						$result->height = hexdec($header[23] . $header[22]);
						break;
					case 'jpg':
						$pos = 0;
						while(1) {
							$pos += 2;
							$data = substr($rawdata, $pos, 9);
							if (strlen($data) < 4) {
								break;
							}
							$info = unpack('nmarker/nlength', $data);
							if ($info['marker'] == 0xFFC0) {
								if (strlen($data) >= 9) {
									$info = unpack('nmarker/nlength/Cprecision/nheight/nwidth', $data);

									$result = new stdClass;
									$result->width = $info['width'];
									$result->height = $info['height'];
								}
								break;
							} else {
								$pos += $info['length'];
								if (strlen($rawdata) < $pos+9) {
									$rawdata .= stream_get_contents($stream, $info['length']+9);
								}
							}
						}
						break;
					default:
						/**
						 * Fallback to original getimagesize this may be slower than the original but safer.
						 */
						$rawdata .= stream_get_contents($stream);
						$temp = tmpfile();
						fwrite($temp, $rawdata);
						$meta_data = stream_get_meta_data($temp);

						$info = getimagesize($meta_data['uri']);

						if ($info) {
							$result = new stdClass;
							$result->width = $info[0];
							$result->height = $info[1];
						}
						fclose($temp);
						break;
				}
			}
			fclose($stream);
		}
		if (!$result) {
			$info = getimagesize($filename);

			if ($info) {
				$result = new stdClass;
				$result->width = $info[0];
				$result->height = $info[1];
			}
		}
		ob_end_clean();
		return $result;
	}

	/**
	 * @param $seed
	 *
	 * @return string
	 */
	public static function getHash($seed)
	{
		return md5(Factory::getConfig()->get('secret') . $seed);
	}



	/**
	 * @param string $jname
	 * @param bool   $default
	 *
	 * @return mixed;
	 */
	public static function getUserGroups($jname = '', $default = false) {
		$params = Factory::getConfig();
		$usergroups = $params->get('usergroups', false);

		if ($jname) {
			if (isset($usergroups->{$jname})) {
				$usergroups = $usergroups->{$jname};

				if ($default) {
					if (isset($usergroups[0])) {
						$usergroups = $usergroups[0];
					} else {
						$usergroups = null;
					}
				}
			} else {
				if ($default) {
					$usergroups = null;
				} else {
					$usergroups = array();
				}
			}
		}
		return $usergroups;
	}

	/**
	 * @return stdClass;
	 */
	public static function getUpdateUserGroups() {
		$params = Factory::getConfig();
		$usergroupmodes = $params->get('updateusergroups', new stdClass());
		return $usergroupmodes;
	}

	/**
	 * returns true / false if the plugin is in advanced usergroup mode or not...
	 *
	 * @param string $jname plugin name
	 *
	 * @return boolean
	 */
	public static function updateUsergroups($jname) {
		$updateusergroups = static::getUpdateUserGroups();
		$advanced = false;
		if (isset($updateusergroups->{$jname}) && $updateusergroups->{$jname}) {
			$master = Framework::getMaster();
			if ($master->name != $jname) {
				$advanced = true;
			}
		}
		return $advanced;
	}

	/**
	 * authenticate a user/password
	 *
	 * @param string $username username
	 * @param string $password password
	 *
	 * @return boolean
	 */
	public static function authenticate($username, $password) {
		$updateusergroups = static::getUpdateUserGroups();
		$advanced = false;
		if (isset($updateusergroups->{$jname}) && $updateusergroups->{$jname}) {
			$master = Framework::getMaster();
			if ($master->name != $jname) {
				$advanced = true;
			}
		}
		return $advanced;
	}

	/**
	 * Generate a random password
	 *
	 * @param   integer  $length  Length of the password to generate
	 *
	 * @return  string  Random Password
	 *
	 * @since   11.1
	 */
	public static function genRandomPassword($length = 8)
	{
		$salt = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$base = strlen($salt);
		$makepass = '';

		/*
		 * Start with a cryptographic strength random string, then convert it to
		 * a string with the numeric base of the salt.
		 * Shift the base conversion on each character so the character
		 * distribution is even, and randomize the start shift so it's not
		 * predictable.
		 */
		$random = static::genRandomBytes($length + 1);
		$shift = ord($random[0]);

		for ($i = 1; $i <= $length; ++$i)
		{
			$makepass .= $salt[($shift + ord($random[$i])) % $base];
			$shift += ord($random[$i]);
		}

		return $makepass;
	}

	/**
	 * Generate random bytes.
	 *
	 * @param   integer  $length  Length of the random data to generate
	 *
	 * @return  string  Random binary data
	 *
	 * @since  12.1
	 */
	public static function genRandomBytes($length = 16)
	{
		$length = (int) $length;
		$sslStr = '';

		/*
		 * If a secure randomness generator exists and we don't
		 * have a buggy PHP version use it.
		 */
		if (function_exists('openssl_random_pseudo_bytes')
			&& (version_compare(PHP_VERSION, '5.3.4') >= 0 || IS_WIN))
		{
			$sslStr = openssl_random_pseudo_bytes($length, $strong);

			if ($strong)
			{
				return $sslStr;
			}
		}

		/*
		 * Collect any entropy available in the system along with a number
		 * of time measurements of operating system randomness.
		 */
		$bitsPerRound = 2;
		$maxTimeMicro = 400;
		$shaHashLength = 20;
		$randomStr = '';
		$total = $length;

		// Check if we can use /dev/urandom.
		$urandom = false;
		$handle = null;

		// This is PHP 5.3.3 and up
		if (function_exists('stream_set_read_buffer') && @is_readable('/dev/urandom'))
		{
			$handle = @fopen('/dev/urandom', 'rb');

			if ($handle)
			{
				$urandom = true;
			}
		}

		while ($length > strlen($randomStr))
		{
			$bytes = ($total > $shaHashLength)? $shaHashLength : $total;
			$total -= $bytes;

			/*
			 * Collect any entropy available from the PHP system and filesystem.
			 * If we have ssl data that isn't strong, we use it once.
			 */
			$entropy = rand() . uniqid(mt_rand(), true) . $sslStr;
			$entropy .= implode('', @fstat(fopen(__FILE__, 'r')));
			$entropy .= memory_get_usage();
			$sslStr = '';

			if ($urandom)
			{
				stream_set_read_buffer($handle, 0);
				$entropy .= @fread($handle, $bytes);
			}
			else
			{
				/*
				 * There is no external source of entropy so we repeat calls
				 * to mt_rand until we are assured there's real randomness in
				 * the result.
				 *
				 * Measure the time that the operations will take on average.
				 */
				$samples = 3;
				$duration = 0;

				for ($pass = 0; $pass < $samples; ++$pass)
				{
					$microStart = microtime(true) * 1000000;
					$hash = sha1(mt_rand(), true);

					for ($count = 0; $count < 50; ++$count)
					{
						$hash = sha1($hash, true);
					}

					$microEnd = microtime(true) * 1000000;
					$entropy .= $microStart . $microEnd;

					if ($microStart >= $microEnd)
					{
						$microEnd += 1000000;
					}

					$duration += $microEnd - $microStart;
				}

				$duration = $duration / $samples;

				/*
				 * Based on the average time, determine the total rounds so that
				 * the total running time is bounded to a reasonable number.
				 */
				$rounds = (int) (($maxTimeMicro / $duration) * 50);

				/*
				 * Take additional measurements. On average we can expect
				 * at least $bitsPerRound bits of entropy from each measurement.
				 */
				$iter = $bytes * (int) ceil(8 / $bitsPerRound);

				for ($pass = 0; $pass < $iter; ++$pass)
				{
					$microStart = microtime(true);
					$hash = sha1(mt_rand(), true);

					for ($count = 0; $count < $rounds; ++$count)
					{
						$hash = sha1($hash, true);
					}

					$entropy .= $microStart . microtime(true);
				}
			}

			$randomStr .= sha1($entropy, true);
		}

		if ($urandom)
		{
			@fclose($handle);
		}

		return substr($randomStr, 0, $length);
	}

	/**
	 * @static
	 * @param $url
	 *
	 * @return bool|string|array
	 */
	public static function getFileData($url)
	{
		ob_start();
		if (function_exists('curl_init')) {
			//curl is the preferred function
			$crl = curl_init();
			curl_setopt($crl, CURLOPT_URL, $url);
			curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($crl, CURLOPT_TIMEOUT, 20);
			$FileData = curl_exec($crl);
			$FileInfo = curl_getinfo($crl);
			curl_close($crl);
			if ($FileInfo['http_code'] != 200) {
				//there was an error
				Framework::raise(LogLevel::WARNING, $FileInfo['http_code'] . ' error for file:' . $url);
				$FileData = false;
			}
		} else {
			//see if we can use fopen to get file
			$fopen_check = ini_get('allow_url_fopen');
			if (!empty($fopen_check)) {
				$FileData = file_get_contents($url);
			} else {
				Framework::raise(LogLevel::WARNING, Text::_('CURL_DISABLED'));
				$FileData = false;
			}
		}
		ob_end_clean();
		return $FileData;
	}

	/**
	 * @static
	 *
	 * @return string
	 */
	public static function getNodeID()
	{
		$params = Factory::getConfig();
		$url = $params->get('url');
		return strtolower(rtrim(parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH), '/'));
	}
}