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
class JFusionAuth_oscommerce extends JFusionAuth 
{
    function generateEncryptedPassword($userinfo) {
        $params = JFusionFactory::getParams($this->getJname());
        $osCversion = $params->get('osCversion');
        switch ($osCversion) {
            case 'osc2':
            case 'osc3':
            case 'osczen':
            case 'oscmax':
                if ($userinfo->password_salt) {
                    return md5($userinfo->password_salt . $userinfo->password_clear);
                } else {
                    return md5($userinfo->password_clear);
                }
            case 'oscxt':
            case 'oscseo':
                return md5($userinfo->password_clear);
        }
        return md5($userinfo->password_clear);
    }
    /**
     * returns the name of this JFusion plugin
     * @return string name of current JFusion plugin
     */
    function getJname() 
    {
        return 'oscommerce';
    }
}