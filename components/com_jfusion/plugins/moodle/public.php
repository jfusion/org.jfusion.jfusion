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

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Public Class for Moodle 1.8+
 * For detailed descriptions on these functions please check JFusionPublic
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Moodle
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_moodle extends JFusionPublic {
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'moodle';
    }

    /**
     * @return string
     */
    function getRegistrationURL() {
        return 'login/signup.php';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return 'login/forgot_password.php';
    }

    /**
     * @return string
     */
    function getLostUsernameURL() {
        return 'login/forgot_password.php';
    }
}
