<?php namespace JFusion\Plugins\dokuwiki;

/**
 * file containing auth function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFusion\Plugin\Plugin_Auth;
use JFusion\User\Userinfo;

/**
 * JFusion auth plugin class
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Auth extends Plugin_Auth
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    /**
     * Generate a encrypted password from clean password
     *
     * @param Userinfo $userinfo holds the user data
     *
     * @return string
     */
    function generateEncryptedPassword(Userinfo $userinfo)
    {
        return $this->helper->auth->cryptPassword($userinfo->password_clear);
    }
}