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
use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;
use Psr\Log\LogLevel;

defined('_JEXEC') or die('Restricted access');

/**
 * require the module helper
 */
require_once dirname(__FILE__) . '/helper.php';
$user = JFactory::getUser();
/**
 * @ignore
 * @var $params JRegistry
 */
$params->def('greeting', 1);
$type = modjfusionLoginHelper::getType();
$return = modjfusionLoginHelper::getReturnURL($params, $type);
$twofactormethods = modjfusionLoginHelper::getTwoFactorMethods();
//check if the JFusion component is installed
$factory_file = JPATH_ADMINISTRATOR . '/components/com_jfusion/import.php';
try {
	if (file_exists($factory_file)) {
	    /**
	     * require the JFusion libraries
	     */
	    include_once $factory_file;
	    //get any custom URLs
	    $lostpassword_url = $params->get('lostpassword_url');
	    $lostusername_url = $params->get('lostusername_url');
	    $register_url = $params->get('register_url');
	    //get the itemid and jname to get any missing urls
	    $link_itemid = $params->get('itemid');

		if (empty($link_itemid)) {
			throw new RuntimeException(JText::_('NO_ITEMID_SELLECTED'));
		}

	    if (is_numeric($link_itemid)) {
		    $menu = JMenu::getInstance('site');

	        $menu_param = $menu->getParams($link_itemid);
	        $plugin_param = unserialize(base64_decode($menu_param->get('JFusionPluginParam')));
	        $link_jname = $plugin_param['jfusionplugin'];
	    } else {
	        $link_jname = $link_itemid;
	    }
	    //get the default URLs if no custom URL specified
	    $LinkPlugin = Factory::getFront($link_jname);
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

		if (empty($link_itemid)) {
			throw new RuntimeException(JText::_('NO_AVATAR_TARGET_SELLECTED'));
		}

	    if (is_numeric($itemid)) {
		    $menu = JMenu::getInstance('site');
	        $menu_param = $menu->getParams($itemid);
	        $plugin_param = unserialize(base64_decode($menu_param->get('JFusionPluginParam')));
	        $jname = $plugin_param['jfusionplugin'];
	    } else {
	        $jname = $itemid;
	    }

		$userlookup = null;
		if (!$user->get('guest')) {
			$userlookup = new \JFusion\User\Userinfo('joomla_int');
			$userlookup->userid = $user->get('id');
			$userlookup->username = $user->get('username');
			$userlookup->email = $user->get('email');

			$PluginUser = Factory::getUser($jname);
			$userlookup = $PluginUser->lookupUser($userlookup);
	        if ($userlookup) {
		        try {
			        $JFusionUser = Factory::getUser($link_jname);
			        $userinfo = $JFusionUser->getUser($userlookup);
		        } catch (Exception $e) {
			        $userinfo = null;
		        }
	            if ($userinfo instanceof Userinfo) {
	                $display_name = ($params->get('name') && isset($userinfo->name)) ? $userinfo->name : $userinfo->username;
	            } else {
	                $display_name = ($params->get('name')) ? $user->get('name') : $user->get('username');
	            }
	        }
		}

	    if (!empty($jname) && $jname != 'joomla_int' && !$user->get('guest')) {
		    /**
		     * @ignore
		     * @var $platform \JFusion\Plugin\Platform\Joomla
		     */
		    $platform = Factory::getPlatform('Joomla', $jname);
	        //check to see if we found a user
	        if ($userlookup) {
	            if ($params->get('avatar')) {
	                // retrieve avatar
	                $avatar_software = $params->get('avatar_software');
	                if ($avatar_software != 'jfusion') {
		                $avatar = Framework::getAltAvatar($avatar_software, $userlookup);
	                } else {
		                $avatar = $platform->getAvatar($userlookup->userid);
	                }
	                if (empty($avatar)) {
	                    $avatar = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
	                }
	            }
	            if ($params->get('pmcount') && $jname != 'joomla_ext') {
	                $pmcount = $platform->getPrivateMessageCounts($userlookup->userid);
	                $url_pm = JFusionFunction::routeURL($platform->getPrivateMessageURL(), $itemid);
	            } else {
	                $pmcount = false;
	            }
	            if ($params->get('viewnewmessages') && $jname != 'joomla_ext') {
	                $url_viewnewmessages = JFusionFunction::routeURL($platform->getViewNewMessagesURL(), $itemid);
	            } else {
	                $url_viewnewmessages = false;
	            }
	        }
	    } else {
	        //show the avatar if it is not set to JFusion
	        if ($params->get('avatar')) {
	            //retrieve avatar
		        $avatar_software = $params->get('avatar_software');
	            if ($avatar_software != 'jfusion' && $userlookup) {
	                $avatar = Framework::getAltAvatar($avatar_software, $userlookup);
	            } else {
	                $avatar = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
	            }
	        } else {
	            $avatar = false;
	        }
	        $pmcount = $url_viewnewmessages = false;
	    }
	}
	$public = Factory::getFront('joomla_int');
	//use the Joomla default if JFusion specified none
	if (empty($lostpassword_url)) {
	    $lostpassword_url = JRoute::_($public->getLostPasswordURL());
	}
	if (empty($lostusername_url)) {
	    $lostusername_url = JRoute::_($public->getLostUsernameURL());
	}
	if (empty($register_url)) {
	    $register_url = JRoute::_($public->getRegistrationUrl());
	}
	$layout = 'default';
	if ($params->get('layout') == 'horizontal') {
		$layout .= '_horizontal';
	}
	if ($type == 'logout') {
		$layout .= '_logout';
	}
	//render the login module
	require_once JModuleHelper::getLayoutPath('mod_jfusion_login', $layout);
} catch (Exception $e) {
	Framework::raise(LogLevel::ERROR, $e, 'mod_jfusion_login');
	echo $e->getMessage();
}