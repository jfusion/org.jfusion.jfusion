<?php

/**
 * abstract authentication file
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

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Abstract interface for all JFusion auth implementations.
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionAuth extends JFusionPlugin
{
	var $helper;

	/**
	 *
	 */
	function __construct()
	{
		parent::__construct();
		//get the helper object
		$this->helper = &JFusionFactory::getHelper($this->getJname());
	}

    /**
     * Generates an encrypted password based on the userinfo passed to this function
     *
     * @param object $userinfo userdata object containing the userdata
     * 
     * @return string Returns generated password
     */
    function generateEncryptedPassword($userinfo) 
    {
        return '';
    }

	/**
	 * used by framework to ensure a password test
	 *
	 * @param object $userinfo userdata object containing the userdata
	 *
	 * @return boolean
	 */
	function checkPassword($userinfo) {
		return $this->comparePassword($userinfo->password, $this->generateEncryptedPassword($userinfo));
	}

	/**
	 * A timing safe comparison method. This defeats hacking
	 * attempts that use timing based attack vectors.
	 *
	 * @param   string  $known    A known string to check against.
	 * @param   string  $unknown  An unknown string to check.
	 *
	 * @return  boolean  True if the two strings are exactly the same.
	 *
	 * @since   3.2
	 */
	public final function comparePassword($known, $unknown)
	{
		// Prevent issues if string length is 0
		$known .= chr(0);
		$unknown .= chr(0);

		$knownLength = strlen($known);
		$unknownLength = strlen($unknown);

		// Set the result to the difference between the lengths
		$result = $knownLength - $unknownLength;

		// Note that we ALWAYS iterate over the user-supplied length to prevent leaking length info.
		for ($i = 0; $i < $unknownLength; $i++)
		{
			// Using % here is a trick to prevent notices. It's safe, since if the lengths are different, $result is already non-0
			$result |= (ord($known[$i % $knownLength]) ^ ord($unknown[$i]));
		}
		// They are only identical strings if $result is exactly 0...
		return $result === 0;
	}
}
