<?php namespace JFusion\Plugins\prestashop;


 /*
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage PrestaShop
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
 
// no direct access
use JFusion\Plugin\Plugin_Auth;
use Tools;

defined('_JEXEC') or die('Restricted access');


/**
 * JFusion Authentication Class for PrestaShop
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage PrestaShop
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionAuth_prestashop extends Plugin_Auth
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    /**
     * @param \JFusion\User\Userinfo $userinfo
     *
     * @return string
     */
    function generateEncryptedPassword(\JFusion\User\Userinfo $userinfo) {
	    $this->helper->loadFramework();
	    return Tools::encrypt($userinfo->password_clear);
    }
}