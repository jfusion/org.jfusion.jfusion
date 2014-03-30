<?php namespace JFusion\Plugins\gallery2;

/**
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage Gallery2 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use GalleryUtilities;
use JFusion\Plugin\Plugin_Auth;
use JFusion\User\Userinfo;

defined('_JEXEC') or die('Restricted access');

/**
 * @category  Gallery2
 * @package   JFusionPlugins
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org 
 */
class Auth extends Plugin_Auth
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    /**
     * @param Userinfo $userinfo
     * @return string
     */
    function generateEncryptedPassword(Userinfo $userinfo) {
        $this->helper->loadGallery2Api(false);
        $testcrypt = GalleryUtilities::md5Salt($userinfo->password_clear, $userinfo->password_salt);
        return $testcrypt;
    }
}
