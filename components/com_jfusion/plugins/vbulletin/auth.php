<?php namespace JFusion\Plugins\vbulletin;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
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
 * JFusion Authentication Class for vBulletin
 * For detailed descriptions on these functions please check the model.abstractauth.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Auth extends Plugin_Auth
{
    /**
     * @param Userinfo $userinfo
     *
     * @return string
     */
    function generateEncryptedPassword(Userinfo $userinfo)
    {
        //are we logging in with the dual login plugin?
        if (strlen($userinfo->password_clear) == 32 && defined('_VBULLETIN_JFUSION_HOOK') && defined('_VB_SECURITY_CHECK')) {
            /**
             * vBulletin hashes the password client side thus a clear password is not available for use.
             * Therefore, we must use vB's already hashed password to authenticate and the only way to do this
             * is to compare it using vB's model then rewriting the Master's password to that of vB's.
             * Obviously not ideal but the only way to make it happen.  Used a couple constants to make sure that
             * vB's hook file is initiating the request.
             */
            $securitycrypt = md5('jfusion' . md5($userinfo->password_clear . $userinfo->password_salt));
            if ($securitycrypt == _VB_SECURITY_CHECK) {
                $testcrypt = md5($userinfo->password_clear . $userinfo->password_salt);
            } else {
                $testcrypt = $securitycrypt;
            }
        } else {
            $testcrypt = md5(md5($userinfo->password_clear) . $userinfo->password_salt);
        }
        return $testcrypt;
    }
}
