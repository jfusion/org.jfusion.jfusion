<?php namespace JFusion\Plugins\moodle;

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

use JFusion\Plugin\Plugin_Front;

/**
 * JFusion Public Class for Moodle 1.8+
 * For detailed descriptions on these functions please check Plugin_Front
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Moodle
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Front extends Plugin_Front
{
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
