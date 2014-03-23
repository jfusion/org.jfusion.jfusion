<?php namespace JFusion\Plugins\prestashop;


/**
 * JFusion Public Class for PrestaShop
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
use JFusion\Plugin\Plugin_Front;

defined('_JEXEC') or die('Restricted access');


/**
 * JFusion Public Class for PrestaShop
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage PrestaShop
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */


class Front extends Plugin_Front
{
	/**
	 * @var $helper Helper
	 */
	var $helper;

    /**
     * @return string
     */
    function getRegistrationURL() {
        return 'authentication.php';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return 'password.php';
    }

    /**
     * @return string
     */
    function getLostUsernameURL() {
        return '';
    }
}