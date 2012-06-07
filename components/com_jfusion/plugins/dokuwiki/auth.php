<?php

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

// no direct access
defined('_JEXEC') or die('Restricted access');

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
class JFusionAuth_dokuwiki extends JFusionAuth
{
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'dokuwiki';
    }
    	
    /**
     * Generate a encrypted password from clean password
     *
     * @param object $userinfo holds the user data
     *
     * @return string
     */
    function generateEncryptedPassword($userinfo)
    {
        /**
         * @var $helper JFusionHelper_dokuwiki
         */
        $helper = JFusionFactory::getHelper($this->getJname());
        return $helper->auth->cryptPassword($userinfo->password_clear);
    }
}