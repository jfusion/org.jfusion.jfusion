<?php

/**
 * abstract authentication file
 * 
 * PHP version 5
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Abstract interface for all JFusion auth implementations.
 * 
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionAuth
{
    /**
     * Generates an encrypted password based on the userinfo passed to this function
     *
     * @param object $userinfo userdata object containing the userdata
     * 
     * @return string Returns generated password
     */
    function generateEncryptedPassword($userinfo) 
    {
    }
}
