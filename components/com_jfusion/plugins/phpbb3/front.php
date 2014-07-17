<?php namespace jfusion\plugins\phpbb3;

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
use JFusion\Plugin\Plugin_Front;

defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Public Class for phpBB3
 * For detailed descriptions on these functions please check the model.abstractpublic.php
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage phpBB3
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
        return 'ucp.php?mode=register';
    }

    /**
     * @return string
     */
    function getLostPasswordURL() {
        return 'ucp.php?mode=sendpassword';
    }

    /**
     * @return string
     */
    function getLostUsernameURL() {
        return 'ucp.php?mode=sendpassword';
    }
}