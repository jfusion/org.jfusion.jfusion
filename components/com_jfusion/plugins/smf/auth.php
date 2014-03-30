<?php namespace JFusion\Plugins\smf;

/**
 * file containing auth function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
use JFusion\Plugin\Plugin_Auth;
use JFusion\User\Userinfo;

defined('_JEXEC') or die('Restricted access');
/**
 * JFusion auth plugin class
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Auth extends Plugin_Auth
{
    /**
     * Generate a encrypted password from clean password
     *
     * @param Userinfo $userinfo holds the user data
     *
     * @return string
     */
    function generateEncryptedPassword(Userinfo $userinfo)
    {
        $testcrypt = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
        return $testcrypt;
    }
}
