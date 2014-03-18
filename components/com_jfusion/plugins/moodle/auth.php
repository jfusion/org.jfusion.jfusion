<?php

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Moodle
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

/**
 * NOTE 1: Moodle uses no salt system by by default.
 * However you can enable salt with a parameter in the configuration file:
 * $CFG->passwordsaltmain    = 'this is the current salt';
 * because the salt is no part of the passwordhash, they have put a system in place
 * to be able to authenticate passwords with older salts (up to 20).
 * These salts are put numbered in the config file as follows:
 * $CFG->passwordsaltalt1    = 'salt number 1';
 * $CFG->passwordsaltalt2    = 'salt number 2';
 * $CFG->passwordsaltalt3    = 'salt number 3';
 * ....
 * $CFG->passwordsaltalt10    = 'salt number 10';
 * Moodles internal authentication routines test for
 * no salt used, then main salt used and thenm for one of the alt salts used
 * when a user logs in the password hash will be updated to either no salt (if configured ths way)
 * or the main salt
 *
 * NOTE 2: In earlier versions of Moodle different charactersets were used. The passwordhash
 * has not been updated, so Moodles authentication routine updates the password-hash before authorizing it.
 * This behaviour is NOT copied in this routine. If you ever run across a problem here you can use the following
 * routine before authenticating the password:
 *
 *  // get password original encoding in case it was not updated to unicode yet
 *     $textlib = textlib_get_instance();
 *     $convpassword = $textlib->convert($password, 'utf-8', get_string('oldcharset'));
 *
 *    You can find texlib in Moodles lib directory. Be aware that texlib uses an other open source lib.
 *    The characterset 'oldcharset' can be found in the moodle language files
 *    If you are unsure how to do all this you can contact me
 *
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Authentication Class for Moodle 1.8+
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Moodle
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_moodle extends \JFusion\Plugin\Plugin_Auth
{
	/**
	 * returns the name of this JFusion plugin
	 *
	 * @return string name of current JFusion plugin
	 */
	function getJname()
	{
		return 'moodle';
	}

    /**
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo) {
        $validated = false;
        if ($userinfo->password == md5($userinfo->password_clear . $this->params->get('passwordsaltmain')) or $userinfo->password == md5($userinfo->password_clear)) {
            $validated = true;
        } else {
            for ($i = 1;$i <= 20;$i++) { //20 alternative salts should be enough, right?
                $alt = 'passwordsaltalt' . $i;
                if ($this->params->get($alt)) {
                    if ($userinfo->password == md5($userinfo->password_clear . $this->params->get($alt))) {
                        $validated = true;
                        break;
                    }
                }
            }
        }
        if ($validated) {
            return $userinfo->password;  // better would be to alter the password, but we need to fool JFusions core
                                         // don't worry about password updates in the debugger, after 2 times it will be stable.
        } else {
            return md5($userinfo->password_clear . $this->params->get('passwordsaltmain')); //return default
        }
    }
}