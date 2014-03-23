<?php namespace JFusion\Plugins\smf2;

/**
 * @package JFusion_SMF
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
use JFusion\Plugin\Plugin_Auth;

defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Authentication Class for SMF 1.1.x
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * @package JFusion_SMF
 */
class Auth extends Plugin_Auth
{
    /**
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo)
    {
        $testcrypt = sha1(strtolower($userinfo->username) . $userinfo->password_clear) ;
        return $testcrypt;
    }
}
