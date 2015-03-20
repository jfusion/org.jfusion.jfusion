<?php

 /*
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
 
// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Authentication Class for phpBB3
 * For detailed descriptions on these functions please check JFusionAuth
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_phpbb31 extends JFusionAuth
{
	/**
	 * @var $helper JFusionHelper_phpbb31
	 */
	var $helper;

    var $itoa64;
    var $iteration_count_log2;
    var $portable_hashes;
    var $random_state;

    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'phpbb31';
    }

    /**
     * @param int $iteration_count_log2
     * @param bool $portable_hashes
     */
    function __construct($iteration_count_log2 = 8, $portable_hashes = true)
    {
	    parent::__construct();

        $this->itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31) $iteration_count_log2 = 8;
        $this->iteration_count_log2 = $iteration_count_log2;
        $this->portable_hashes = $portable_hashes;
        $this->random_state = microtime() . getmypid();
    }

    /**
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo) {
        $userinfo->password_clear = $this->helper->clean_string($userinfo->password_clear);

        $check = $this->doCheckPassword($userinfo->password_clear, $userinfo->password);

        if ($check) {
            //password is correct and return the phpbb3 password hash
            return $userinfo->password;
        } else {
            //no phpbb3 encryption used and return the phpbb2 password hash
			$password_old_format = addslashes($userinfo->password_clear);

			if ($this->doCheckPassword($userinfo->password_clear, md5($this->utf8_to_cp1252($password_old_format)))) {
				//password is correct
				return $userinfo->password;
			} elseif ($this->doCheckPassword($userinfo->password_clear, md5($password_old_format))) {
				//password is correct
				return $userinfo->password;				
			} elseif (md5($this->utf8_to_cp1252($password_old_format)) ===  $userinfo->password) {
				//password is correct
				return $userinfo->password;								
			} else {
				//ah who cares lets just a md5 standard encryption
				$encrypt_password = md5($password_old_format);
                return $encrypt_password;
			}				

        }
    }

    /**
     * @param $string
     * @return string
     */
    function utf8_to_cp1252($string)
    {
        static $transform = array(
            "\xE2\x82\xAC" => "\x80",
            "\xE2\x80\x9A" => "\x82",
            "\xC6\x92" => "\x83",
            "\xE2\x80\x9E" => "\x84",
            "\xE2\x80\xA6" => "\x85",
            "\xE2\x80\xA0" => "\x86",
            "\xE2\x80\xA1" => "\x87",
            "\xCB\x86" => "\x88",
            "\xE2\x80\xB0" => "\x89",
            "\xC5\xA0" => "\x8A",
            "\xE2\x80\xB9" => "\x8B",
            "\xC5\x92" => "\x8C",
            "\xC5\xBD" => "\x8E",
            "\xE2\x80\x98" => "\x91",
            "\xE2\x80\x99" => "\x92",
            "\xE2\x80\x9C" => "\x93",
            "\xE2\x80\x9D" => "\x94",
            "\xE2\x80\xA2" => "\x95",
            "\xE2\x80\x93" => "\x96",
            "\xE2\x80\x94" => "\x97",
            "\xCB\x9C" => "\x98",
            "\xE2\x84\xA2" => "\x99",
            "\xC5\xA1" => "\x9A",
            "\xE2\x80\xBA" => "\x9B",
            "\xC5\x93" => "\x9C",
            "\xC5\xBE" => "\x9E",
            "\xC5\xB8" => "\x9F",
            "\xC2\xA0" => "\xA0",
            "\xC2\xA1" => "\xA1",
            "\xC2\xA2" => "\xA2",
            "\xC2\xA3" => "\xA3",
            "\xC2\xA4" => "\xA4",
            "\xC2\xA5" => "\xA5",
            "\xC2\xA6" => "\xA6",
            "\xC2\xA7" => "\xA7",
            "\xC2\xA8" => "\xA8",
            "\xC2\xA9" => "\xA9",
            "\xC2\xAA" => "\xAA",
            "\xC2\xAB" => "\xAB",
            "\xC2\xAC" => "\xAC",
            "\xC2\xAD" => "\xAD",
            "\xC2\xAE" => "\xAE",
            "\xC2\xAF" => "\xAF",
            "\xC2\xB0" => "\xB0",
            "\xC2\xB1" => "\xB1",
            "\xC2\xB2" => "\xB2",
            "\xC2\xB3" => "\xB3",
            "\xC2\xB4" => "\xB4",
            "\xC2\xB5" => "\xB5",
            "\xC2\xB6" => "\xB6",
            "\xC2\xB7" => "\xB7",
            "\xC2\xB8" => "\xB8",
            "\xC2\xB9" => "\xB9",
            "\xC2\xBA" => "\xBA",
            "\xC2\xBB" => "\xBB",
            "\xC2\xBC" => "\xBC",
            "\xC2\xBD" => "\xBD",
            "\xC2\xBE" => "\xBE",
            "\xC2\xBF" => "\xBF",
            "\xC3\x80" => "\xC0",
            "\xC3\x81" => "\xC1",
            "\xC3\x82" => "\xC2",
            "\xC3\x83" => "\xC3",
            "\xC3\x84" => "\xC4",
            "\xC3\x85" => "\xC5",
            "\xC3\x86" => "\xC6",
            "\xC3\x87" => "\xC7",
            "\xC3\x88" => "\xC8",
            "\xC3\x89" => "\xC9",
            "\xC3\x8A" => "\xCA",
            "\xC3\x8B" => "\xCB",
            "\xC3\x8C" => "\xCC",
            "\xC3\x8D" => "\xCD",
            "\xC3\x8E" => "\xCE",
            "\xC3\x8F" => "\xCF",
            "\xC3\x90" => "\xD0",
            "\xC3\x91" => "\xD1",
            "\xC3\x92" => "\xD2",
            "\xC3\x93" => "\xD3",
            "\xC3\x94" => "\xD4",
            "\xC3\x95" => "\xD5",
            "\xC3\x96" => "\xD6",
            "\xC3\x97" => "\xD7",
            "\xC3\x98" => "\xD8",
            "\xC3\x99" => "\xD9",
            "\xC3\x9A" => "\xDA",
            "\xC3\x9B" => "\xDB",
            "\xC3\x9C" => "\xDC",
            "\xC3\x9D" => "\xDD",
            "\xC3\x9E" => "\xDE",
            "\xC3\x9F" => "\xDF",
            "\xC3\xA0" => "\xE0",
            "\xC3\xA1" => "\xE1",
            "\xC3\xA2" => "\xE2",
            "\xC3\xA3" => "\xE3",
            "\xC3\xA4" => "\xE4",
            "\xC3\xA5" => "\xE5",
            "\xC3\xA6" => "\xE6",
            "\xC3\xA7" => "\xE7",
            "\xC3\xA8" => "\xE8",
            "\xC3\xA9" => "\xE9",
            "\xC3\xAA" => "\xEA",
            "\xC3\xAB" => "\xEB",
            "\xC3\xAC" => "\xEC",
            "\xC3\xAD" => "\xED",
            "\xC3\xAE" => "\xEE",
            "\xC3\xAF" => "\xEF",
            "\xC3\xB0" => "\xF0",
            "\xC3\xB1" => "\xF1",
            "\xC3\xB2" => "\xF2",
            "\xC3\xB3" => "\xF3",
            "\xC3\xB4" => "\xF4",
            "\xC3\xB5" => "\xF5",
            "\xC3\xB6" => "\xF6",
            "\xC3\xB7" => "\xF7",
            "\xC3\xB8" => "\xF8",
            "\xC3\xB9" => "\xF9",
            "\xC3\xBA" => "\xFA",
            "\xC3\xBB" => "\xFB",
            "\xC3\xBC" => "\xFC",
            "\xC3\xBD" => "\xFD",
            "\xC3\xBE" => "\xFE",
            "\xC3\xBF" => "\xFF"
        );
        return strtr($string, $transform);
    }

    /**
     * @param $count
     * @return string
     */
    function get_random_bytes($count) {
        $output = '';
	    ob_start();
        if (($fh = fopen('/dev/urandom', 'rb'))) {
            $output = fread($fh, $count);
            fclose($fh);
        }
	    ob_end_clean();
        if (strlen($output) < $count) {
            $output = '';
            for ($i = 0;$i < $count;$i+= 16) {
                $this->random_state = md5(microtime() . $this->random_state);
                $output.= pack('H*', md5($this->random_state));
            }
            $output = substr($output, 0, $count);
        }
        return $output;
    }

    /**
     * @param $input
     * @param $count
     * @return string
     */
    function encode64($input, $count) {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output.= $this->itoa64[$value & 0x3f];
            if ($i < $count) $value|= ord($input[$i]) << 8;
            $output.= $this->itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $count) break;
            if ($i < $count) $value|= ord($input[$i]) << 16;
            $output.= $this->itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $count) break;
            $output.= $this->itoa64[($value >> 18) & 0x3f];
        }
        while ($i < $count);
        return $output;
    }

    /**
     * @param $input
     * @return string
     */
    function gensalt_private($input) {
        $output = '$H$';
        $output.= $this->itoa64[min($this->iteration_count_log2 + ((PHP_VERSION >= '5') ? 5 : 3), 30) ];
        $output.= $this->encode64($input, 6);
        return $output;
    }

    /**
     * @param $password
     * @param $setting
     * @return string
     */
    function crypt_private($password, $setting) {
        $output = '*0';
        if (substr($setting, 0, 2) == $output) $output = '*1';
        if (substr($setting, 0, 3) != '$H$') return $output;
        $count_log2 = strpos($this->itoa64, $setting[3]);
        if ($count_log2 < 7 || $count_log2 > 30) return $output;
        $count = 1 << $count_log2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) != 8) return $output;
        # We're kind of forced to use MD5 here since it's the only
        # cryptographic primitive available in all versions of PHP
        # currently in use.  To implement our own low-level crypto
        # in PHP would result in much worse performance and
        # consequently in lower iteration counts and hashes that are
        # quicker to crack (by non-PHP code).
        if (PHP_VERSION >= '5') {
            $hash = md5($salt . $password, true);
            do {
                $hash = md5($hash . $password, true);
            }
            while (--$count);
        } else {
            $hash = pack('H*', md5($salt . $password));
            do {
                $hash = pack('H*', md5($hash . $password));
            }
            while (--$count);
        }
        $output = substr($setting, 0, 12);
        $output.= $this->encode64($hash, 16);
        return $output;
    }

    /**
     * @param $input
     * @return string
     */
    function gensalt_extended($input) {
        $count_log2 = min($this->iteration_count_log2 + 8, 24);
        # This should be odd to not reveal weak DES keys, and the
        # maximum valid value is (2**24 - 1) which is odd anyway.
        $count = (1 << $count_log2) - 1;
        $output = '_';
        $output.= $this->itoa64[$count & 0x3f];
        $output.= $this->itoa64[($count >> 6) & 0x3f];
        $output.= $this->itoa64[($count >> 12) & 0x3f];
        $output.= $this->itoa64[($count >> 18) & 0x3f];
        $output.= $this->encode64($input, 3);
        return $output;
    }

    /**
     * @param $input
     * @return string
     */
    function gensalt_blowfish($input) {
        # This one needs to use a different order of characters and a
        # different encoding scheme from the one in encode64() above.
        # We care because the last character in our encoded string will
        # only represent 2 bits.  While two known implementations of
        # bcrypt will happily accept and correct a salt string which
        # has the 4 unused bits set to non-zero, we do not want to take
        # chances and we also do not want to waste an additional byte
        # of entropy.
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $output = '$2a$';
        $output.= chr(ord('0') + $this->iteration_count_log2 / 10);
        $output.= chr(ord('0') + $this->iteration_count_log2 % 10);
        $output.= '$';
        $i = 0;
        do {
            $c1 = ord($input[$i++]);
            $output.= $itoa64[$c1 >> 2];
            $c1 = ($c1 & 0x03) << 4;
            if ($i >= 16) {
                $output.= $itoa64[$c1];
                break;
            }
            $c2 = ord($input[$i++]);
            $c1|= $c2 >> 4;
            $output.= $itoa64[$c1];
            $c1 = ($c2 & 0x0f) << 2;
            $c2 = ord($input[$i++]);
            $c1|= $c2 >> 6;
            $output.= $itoa64[$c1];
            $output.= $itoa64[$c2 & 0x3f];
        }
        while (1);
        return $output;
    }

    /**
     * @param $password
     * @return string
     */
    function HashPassword($password) {
        $password = $this->helper->clean_string($password);

        $random = '';
        if (CRYPT_BLOWFISH == 1 && !$this->portable_hashes) {
            $random = $this->get_random_bytes(16);
            $hash = crypt($password, $this->gensalt_blowfish($random));
            if (strlen($hash) == 60) return $hash;
        }
        if (CRYPT_EXT_DES == 1 && !$this->portable_hashes) {
            if (strlen($random) < 3) $random = $this->get_random_bytes(3);
            $hash = crypt($password, $this->gensalt_extended($random));
            if (strlen($hash) == 20) return $hash;
        }
        if (strlen($random) < 6) $random = $this->get_random_bytes(6);
        $hash = $this->crypt_private($password, $this->gensalt_private($random));
        if (strlen($hash) == 34) return $hash;
        # Returning '*' on error is safe here, but would _not_ be safe
        # in a crypt(3)-like function used _both_ for generating new
        # hashes and for validating passwords against existing hashes.
        return '*';
    }

    /**
     * @param $password
     * @param $stored_hash
     * @return bool
     */
    function doCheckPassword($password, $stored_hash) {
        $hash = $this->crypt_private($password, $stored_hash);
        if ($hash[0] == '*') $hash = crypt($password, $stored_hash);
        return $hash == $stored_hash;
    }
}
