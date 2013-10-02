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
        jimport('joomla.user.helper');
        $crypt = JUserHelper::getCryptedPassword($userinfo->password_clear, $userinfo->password_salt);
        return $crypt;
    }
}