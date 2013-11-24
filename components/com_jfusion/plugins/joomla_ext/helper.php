<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpbb
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Helper Class for joomla_ext
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage joomla_ext
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionHelper_joomla_ext extends JFusionPlugin
{
    /**
     * Returns the name for this plugin
     *
     * @return string
     */
    function getJname() {
        return 'joomla_ext';
    }

	/**
	 * Formats a password using the current encryption.
	 *
	 * @param   string   $plaintext     The plaintext password to encrypt.
	 * @param   string   $salt          The salt to use to encrypt the password. []
	 *                                  If not present, a new salt will be
	 *                                  generated.
	 * @param   string   $encryption    The kind of password encryption to use.
	 *                                  Defaults to md5-hex.
	 * @param   boolean  $show_encrypt  Some password systems prepend the kind of
	 *                                  encryption to the crypted password ({SHA},
	 *                                  etc). Defaults to false.
	 *
	 * @return  string  The encrypted password.
	 *
	 * @since   11.1
	 * @note    In Joomla! CMS 3.2 the default encrytion has been changed to bcrypt. When PHP 5.5 is the minimum
	 *          supported version it will be changed to the PHP PASSWORD_DEFAULT constant.
	 */
	public function getCryptedPassword($plaintext, $salt = '', $encryption = 'bcrypt', $show_encrypt = false)
	{
		// Not all controllers check the length, although they should to avoid DOS attacks.
		// The maximum password length for bcrypt is 55:
		if (strlen($plaintext) > 55) {
			JFusionFunction::raiseError(JText::_('JLIB_USER_ERROR_PASSWORD_TOO_LONG'));
			return false;
		}

		// Get the salt to use.
		if (empty($salt)) {
			$salt = JUserHelper::getSalt($encryption, $salt, $plaintext);
		}

		// Encrypt the password.
		switch ($encryption) {
			case 'plain':
				return $plaintext;

			case 'sha':
				$encrypted = base64_encode(mhash(MHASH_SHA1, $plaintext));

				return ($show_encrypt) ? '{SHA}' . $encrypted : $encrypted;

			case 'crypt':
			case 'crypt-des':
			case 'crypt-md5':
			case 'crypt-blowfish':
				return ($show_encrypt ? '{crypt}' : '') . crypt($plaintext, $salt);

			case 'md5-base64':
				$encrypted = base64_encode(mhash(MHASH_MD5, $plaintext));

				return ($show_encrypt) ? '{MD5}' . $encrypted : $encrypted;

			case 'ssha':
				$encrypted = base64_encode(mhash(MHASH_SHA1, $plaintext . $salt) . $salt);

				return ($show_encrypt) ? '{SSHA}' . $encrypted : $encrypted;

			case 'smd5':
				$encrypted = base64_encode(mhash(MHASH_MD5, $plaintext . $salt) . $salt);

				return ($show_encrypt) ? '{SMD5}' . $encrypted : $encrypted;

			case 'aprmd5':
				$length = strlen($plaintext);
				$context = $plaintext . '$apr1$' . $salt;
				$binary = $this->bin(md5($plaintext . $salt . $plaintext));

				for ($i = $length; $i > 0; $i -= 16) {
					$context .= substr($binary, 0, ($i > 16 ? 16 : $i));
				}
				for ($i = $length; $i > 0; $i >>= 1) {
					$context .= ($i & 1) ? chr(0) : $plaintext[0];
				}

				$binary = $this->bin(md5($context));

				for ($i = 0; $i < 1000; $i++) {
					$new = ($i & 1) ? $plaintext : substr($binary, 0, 16);

					if ($i % 3) {
						$new .= $salt;
					}
					if ($i % 7) {
						$new .= $plaintext;
					}
					$new .= ($i & 1) ? substr($binary, 0, 16) : $plaintext;
					$binary = $this->bin(md5($new));
				}

				$p = array();

				for ($i = 0; $i < 5; $i++) {
					$k = $i + 6;
					$j = $i + 12;

					if ($j == 16) {
						$j = 5;
					}
					$p[] = $this->toAPRMD5((ord($binary[$i]) << 16) | (ord($binary[$k]) << 8) | (ord($binary[$j])), 5);
				}

				return '$apr1$' . $salt . '$' . implode('', $p) . $this->toAPRMD5(ord($binary[11]), 3);

			case 'md5-hex':
				$encrypted = ($salt) ? md5($plaintext . $salt) : md5($plaintext);

				return ($show_encrypt) ? '{MD5}' . $encrypted : $encrypted;

			case 'sha256':
				$encrypted = ($salt) ? hash('sha256', $plaintext . $salt) . ':' . $salt : hash('sha256', $plaintext);

				return ($show_encrypt) ? '{SHA256}' . $encrypted : '{SHA256}' . $encrypted;

			// 'bcrypt' is the default case starting in CMS 3.2.
			case 'bcrypt':
			default:
				$useStrongEncryption = $this->hasStrongPasswordSupport();

				if ($useStrongEncryption === true) {
					$encrypted = password_hash($plaintext, PASSWORD_BCRYPT);

					if (!$encrypted) {
						// Something went wrong fall back to sha256.
						return $this->getCryptedPassword($plaintext, '', 'sha256', false);
					}

					return ($show_encrypt) ? '{BCRYPT}' . $encrypted : $encrypted;
				} else {
					// BCrypt isn't available but we want strong passwords, fall back to sha256.
					return $this->getCryptedPassword($plaintext, '', 'sha256', false);
				}
		}
	}

	/**
	 * Converts to allowed 64 characters for APRMD5 passwords.
	 *
	 * @param   string   $value  The value to convert.
	 * @param   integer  $count  The number of characters to convert.
	 *
	 * @return  string  $value converted to the 64 MD5 characters.
	 *
	 * @since   11.1
	 */
	private function toAPRMD5($value, $count)
	{
		/* 64 characters that are valid for APRMD5 passwords. */
		$APRMD5 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		$aprmd5 = '';
		$count = abs($count);

		while (--$count) {
			$aprmd5 .= $APRMD5[$value & 0x3f];
			$value >>= 6;
		}
		return $aprmd5;
	}

	/**
	 * Converts hexadecimal string to binary data.
	 *
	 * @param   string  $hex  Hex data.
	 *
	 * @return  string  Binary data.
	 *
	 * @since   11.1
	 */
	private function bin($hex)
	{
		$bin = '';
		$length = strlen($hex);

		for ($i = 0; $i < $length; $i += 2) {
			$tmp = sscanf(substr($hex, $i, 2), '%x');
			$bin .= chr(array_shift($tmp));
		}
		return $bin;
	}

	/**
	 * Tests for the availability of updated crypt().
	 * Based on a method by Anthony Ferrera
	 *
	 * @return  boolean  True if updated crypt() is available.
	 *
	 * @note    To be removed when PHP 5.3.7 or higher is the minimum supported version.
	 * @see     https://github.com/ircmaxell/password_compat/blob/master/version-test.php
	 * @since   3.2
	 */
	public function hasStrongPasswordSupport()
	{
		static $pass = null;

		if (is_null($pass)) {
			if ($this->params->get('strong_passwords', true)) {
				// Check to see whether crypt() is supported.
				if (version_compare(PHP_VERSION, '5.3.7', '>=') === true) {
					// We have safe PHP version.
					$pass = true;
				} else {
					$hash = '$2y$04$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
					$test = crypt("password", $hash);
					$pass = $test == $hash;
				}
				if ($pass && !defined('PASSWORD_DEFAULT')) {
					// Always make sure that the password hashing API has been defined.
					require_once JFUSION_PLUGIN_PATH . DIRECTORY_SEPARATOR . $this->getJname() . DIRECTORY_SEPARATOR . 'password.php';
				}
			} else {
				$pass = false;
			}
		}
		return $pass;
	}
}