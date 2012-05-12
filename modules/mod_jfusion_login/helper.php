<?php

 /**
 * This is the login module helper file
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Jfusionlogin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

 // no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Class for the JFusion front-end login module
 *
 * @category   JFusion
 * @package    Modules
 * @subpackage Jfusionlogin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class modjfusionLoginHelper
{
    /**
     * gets the return url
     *
     * @param string $params params
     * @param string $type   type
     *
     * @return string url
     */
    function getReturnURL($params, $type)
    {
    	$override_return = JRequest::getVar('return', '', 'method', 'base64');
        if (!empty($override_return)) {
    	    return $override_return;
    	} elseif ($itemid = $params->get($type)) {
            $url = 'index.php?Itemid=' . $itemid;
            $url = JRoute::_($url);
        } else {
            // Redirect to login
            $uri = JFactory::getURI();
            $url = $uri->toString();
        }
        return base64_encode(str_replace("&amp;", "&", $url));
    }

    /**
     * gets the user type
     *
     * @return string type
     */
    function getType()
    {
        $user = JFactory::getUser();
        return (!$user->get('guest')) ? 'logout' : 'login';
    }
}
