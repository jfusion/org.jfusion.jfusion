<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage osCommerce
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionPublic_zencart extends JFusionPublic
{
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'zencart';
    }

    /**
     * @return string
     */
    function getRegistrationURL()
    {
        return 'index.php?main_page=login';
    }

    /**
     * @return string
     */
    function getLostPasswordURL()
    {
        return 'index.php?main_page=password_forgotten';
    }

    /**
     * @return string
     */
    function getLostUsernameURL()
    {
        return 'index.php?main_page=password_forgotten';
    }
}