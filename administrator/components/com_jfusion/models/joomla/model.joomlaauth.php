<?php

/**
 * Model for joomla actions
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
 * Common Class for Joomla JFusion plugins
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionJoomlaAuth extends JFusionAuth
{
    /**
     * Generates an encrypted password based on the userinfo passed to this function
     *
     * @param object $userinfo userdata object containing the userdata
     *
     * @return string Returns generated password
     */
    public function generateEncryptedPassword($userinfo)
    {
	    if (substr($userinfo->password, 0, 4) == '$2y$') {
		    // BCrypt passwords are always 60 characters, but it is possible that salt is appended although non standard.
		    $password60 = substr($userinfo->password, 0, 60);

		    if (JCrypt::hasStrongPasswordSupport()) {
			    $testcrypt = password_verify($userinfo->password_clear, $password60);
		    } else {
			    $testcrypt = null;
		    }
	    } elseif (substr($userinfo->password, 0, 8) == '{SHA256}') {
		    jimport('joomla.user.helper');
		    // Check the password
		    $parts	= explode(':', $userinfo->password);
		    $crypt	= $parts[0];
		    if (isset($parts[1])) {
			    $salt	= $parts[1];
		    } else {
			    $salt = null;
		    }

		    $testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $salt, 'sha256', false);
	    } else {
		    jimport('joomla.user.helper');
		    // Check the password
		    $parts	= explode(':', $userinfo->password);
		    $crypt	= $parts[0];
		    if (isset($parts[1])) {
			    $salt	= $parts[1];
		    } else {
			    $salt = null;
		    }

		    $testcrypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $salt, 'md5-hex', false);
	    }
	    return $testcrypt;
    }
}