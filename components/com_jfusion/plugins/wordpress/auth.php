<?php

 /*
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
 
// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Authentication Class for wordpress
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Wordpress
 * @author     JFusion Team-- Henk Wevers <webmaster@jfusion.org>
 * @copyright  2010 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_wordpress extends JFusionAuth 
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'wordpress';
    }

/*
 * NOTE
 * In Wordpress 2.5 and up the password has generation is done by a public domain
 * Portable PHP password hashing framework. The same framework is used by phpBBs, but alas,
 * the signal chatacter is changed. Because of this we cannot load the original framework
 * and the phpBB3 framework at the same time in case phpBB3 and wor4dpress are used together using
 * JFusion; For this reason the original class is put into the file PasswordHash.php is reneamed to
 * PasswordHashOrg.php and the class PasswordHash has been renamed to PasswordHashOrg.
 * This way we have no clash in the hash classnames. 
 * 
 */


    /**
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo) {
        // get the encryption PHP file
        if (!class_exists('PasswordHashOrg')) {
            require_once JFUSION_PLUGIN_PATH . DS . $this->getJname() . DS . 'PasswordHashOrg.php';
        }
        $t_hasher = new PasswordHashOrg(8, true);
        $check = $t_hasher->CheckPassword($userinfo->password_clear, $userinfo->password);
        //$check will be true or false if the passwords match
        unset($t_hasher);
        //cleanup
        if ($check) {
            //password is correct and return the wordpress password hash
            return $userinfo->password;
        } else {
            //no PHPass used, return the md5 password hash
            $encrypt_password = md5($userinfo->password_clear);
            return $encrypt_password;
        }
    }
}
