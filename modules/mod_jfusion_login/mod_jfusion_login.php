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
 * require the module helper
 */
require_once dirname(__FILE__) . DS . 'helper.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jplugin.php';
$user = JFactory::getUser();
/**
 * @var $params JParameter
 */
$params->def('greeting', 1);
$type = modjfusionLoginHelper::getType();
$return = modjfusionLoginHelper::getReturnURL($params, $type);
//check if the JFusion component is installed
$model_file = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.factory.php';
$factory_file = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_jfusion' . DS . 'models' . DS . 'model.jfusion.php';
if (file_exists($model_file) && file_exists($factory_file)) {
    /**
     * require the JFusion libraries
     */
    include_once $model_file;
    include_once $factory_file;
    //get any custom URLs
    $lostpassword_url = $params->get('lostpassword_url');
    $lostusername_url = $params->get('lostusername_url');
    $register_url = $params->get('register_url');
    //get the itemid and jname to get any missing urls
    $link_itemid = $params->get('itemid');
    if (is_numeric($link_itemid)) {
        $menu = & JSite::getMenu();
        $menu_param = & $menu->getParams($link_itemid);
        $plugin_param = unserialize(base64_decode($menu_param->get('JFusionPluginParam')));
        $link_jname = $plugin_param['jfusionplugin'];
    } else {
        $link_jname = $link_itemid;
    }
    //get the default URLs if no custom URL specified
    $LinkPlugin = & JFusionFactory::getPublic($link_jname);
    if (empty($lostpassword_url) && method_exists($LinkPlugin, 'getLostPasswordURL')) {
        $lostpassword_url = JFusionFunction::routeURL($LinkPlugin->getLostPasswordURL(), $link_itemid);
    }
    if (empty($lostusername_url) && method_exists($LinkPlugin, 'getLostUsernameURL')) {
        $lostusername_url = JFusionFunction::routeURL($LinkPlugin->getLostUsernameURL(), $link_itemid);
    }
    if (empty($register_url) && method_exists($LinkPlugin, 'getRegistrationURL')) {
        $register_url = JFusionFunction::routeURL($LinkPlugin->getRegistrationURL(), $link_itemid);
    }
    //now find out from which plugin the avatars need to be displayed
    $itemid = $params->get('itemidAvatarPMs');
    if (is_numeric($itemid)) {
        $menu = & JSite::getMenu();
        $menu_param = & $menu->getParams($itemid);
        $plugin_param = unserialize(base64_decode($menu_param->get('JFusionPluginParam')));
        $jname = $plugin_param['jfusionplugin'];
    } else {
        $jname = $itemid;
    }
    
	if (!$user->get('guest')) {
        $userlookup = JFusionFunction::lookupUser($jname, $user->get('id'));
        if (!empty($userlookup)) {
            $JFusionUser = & JFusionFactory::getUser($link_jname);
            $userinfo = $JFusionUser->getUser($userlookup);
            if (!empty($userinfo)) {
                $display_name = ($params->get('name') && isset($userinfo->name)) ? $userinfo->name : $userinfo->username;
            } else {
                $display_name = ($params->get('name')) ? $user->get('name') : $user->get('username');
            }
        }
	}
    
    if (!empty($jname) && $jname != 'joomla_int' && !$user->get('guest')) {
        $JFusionPlugin = & JFusionFactory::getForum($jname);
        //check to see if we found a user
        if (!empty($userlookup)) {
            if ($params->get('avatar')) {
                // retrieve avatar
                $avatarSrc = $params->get('avatar_software');
                if ($avatarSrc == '' || $avatarSrc == 'jfusion') {
                    $avatar = $JFusionPlugin->getAvatar($userlookup->userid);
                } else {
                    $avatar = JFusionFunction::getAltAvatar($avatarSrc, $user->get('id'));
                }
                if (empty($avatar)) {
                    $avatar = 'components/com_jfusion/images/noavatar.png';
                }
            }
            if ($params->get('pmcount') && $jname != 'joomla_ext') {
                $pmcount = $JFusionPlugin->getPrivateMessageCounts($userlookup->userid);
                $url_pm = JFusionFunction::routeURL($JFusionPlugin->getPrivateMessageURL(), $itemid);
            } else {
                $pmcount = false;
            }
            if ($params->get('viewnewmessages') && $jname != 'joomla_ext') {
                $url_viewnewmessages = JFusionFunction::routeURL($JFusionPlugin->getViewNewMessagesURL(), $itemid);
            } else {
                $url_viewnewmessages = false;
            }
        }
    } else {
        //show the avatar if it is not set to JFusion
        if ($params->get('avatar')) {
            //retrieve avatar
            $avatarSrc = $params->get('avatar_software');
            if ($avatarSrc != 'jfusion') {
                $avatar = JFusionFunction::getAltAvatar($avatarSrc, $user->get('id'));
            } else {
                $avatar = 'components/com_jfusion/images/noavatar.png';
            }
        } else {
            $avatar = false;
        }
        $pmcount = $url_viewnewmessages = false;
    }
}
//use the Joomla default if JFusion specified none
if (empty($lostpassword_url)) {
    $lostpassword_url = JRoute::_( JFusionJplugin::getLostPasswordURL());
}
if (empty($lostusername_url)) {
    $lostusername_url = JRoute::_(JFusionJplugin::getLostUsernameURL());
}
if (empty($register_url)) {
    $register_url = JRoute::_(JFusionJplugin::getRegistrationUrl());
}
//render the login module
require_once JModuleHelper::getLayoutPath('mod_jfusion_login');
