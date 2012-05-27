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
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_phpbb3 extends JFusionAuth 
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'phpbb3';
    }

    /**
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo) {
        // get the encryption PHP file
        if (!class_exists('PasswordHash')) {
            require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'PasswordHash.php';
        }
        $t_hasher = new PasswordHash(8, true);
        $check = $t_hasher->CheckPassword($userinfo->password_clear, $userinfo->password);

        if ($check) {
            //password is correct and return the phpbb3 password hash
            return $userinfo->password;
        } else {
            //no phpbb3 encryption used and return the phpbb2 password hash
			$password_old_format = addslashes($userinfo->password_clear);

			if ($t_hasher->CheckPassword($userinfo->password_clear, md5($this->utf8_to_cp1252($password_old_format)))) {
				//password is correct
				return $userinfo->password;
			} elseif ($t_hasher->CheckPassword($userinfo->password_clear, md5($password_old_format))) {
				//password is correct
				return $userinfo->password;				
			} elseif (md5($this->utf8_to_cp1252($password_old_format)) ===  $userinfo->password) {
				//password is correct
				return $userinfo->password;								
			} else {
				//ah who cares lets just a md5 standar encryption
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
    
}
