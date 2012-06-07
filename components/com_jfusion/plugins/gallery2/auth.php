<?php

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
defined('_JEXEC') or die('Restricted access');
/**
 * load the Abstract Auth Class
 */
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.abstractauth.php';
/**
 * @category  Gallery2
 * @package   JFusionPlugins
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org 
 */
class JFusionAuth_gallery2 extends JFusionAuth {
    /**
     * @return string
     */
    function getJname()
    {
        return 'gallery2';
    }

    /**
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo) {
        /**
         * @var $helper JFusionHelper_gallery2
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        $helper->loadGallery2Api(false);
        $testcrypt = GalleryUtilities::md5Salt($userinfo->password_clear, $userinfo->password_salt);
        return $testcrypt;
    }
}
