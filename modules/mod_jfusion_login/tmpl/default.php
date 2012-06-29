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
* @copyright  2008-2010 JFusion. All rights reserved.
* @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
* @link       http://www.jfusion.org
*/

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @ignore
 * @var $params JParameter
 * @var $type string
 * @var $display_name string
 * @var $maxheight int
 * @var $url_pm string
 * @var $return string
 * @var $lostpassword_url string
 * @var $lostusername_url string
 * @var $register_url string
 */
//define output var for nicer code
$output = '';
$form_id = (JFusionFunction::isJoomlaVersion('1.6')) ? 'login-form' : 'form-login';
if ( $params->get('layout') == 'horizontal' ) {
    if ($type == 'logout') {
        $output .= '<form action="index.php" method="post" name="login" id="'.$form_id.'">';

        if (!empty($avatar)) {
            $maxheight = $params->get('avatar_height', 80);
            $maxwidth = $params->get('avatar_width', 60);

            $output .= '<img src="' . $avatar .'" alt="' .$display_name . '" style="';
            $output .= (!empty($maxheight)) ? " max-height: {$maxheight}px;" : "";
            $output .= (!empty($maxwidth)) ? " max-width: {$maxwidth}px;" : "";
            $output .= '" />' . "\n";
        }

        if ($params->get('greeting')) {
        	$custom_greeting = $params->get('custom_greeting');
        	if (empty($custom_greeting)) {
        		$custom_greeting = 'HINAME';
        	}
        	$output .= JText::sprintf($custom_greeting, $display_name);
        	
            //if (!empty($pmcount)) {
                //$output .= '<br/>' . JText::_('PM_START');
                //$output .= '<a href="'.$url_pm.'">'.JText::sprintf('PM_LINK', $pmcount["total"])."</a>";
                //$output .= JText::sprintf('PM_END', $pmcount["unread"]);
            //}
        }

        if (!empty($pmcount)) {
            $output .= JText::_('PM_START').' ';
            $output .= ' <a href="'.$url_pm.'">'.JText::sprintf('PM_LINK', $pmcount["total"]).'</a> ';
            $output .= JText::sprintf('PM_END', $pmcount["unread"]).' ';
        }

        if (!empty($url_viewnewmessages)) {
            $output .= '<a href="'. $url_viewnewmessages .'">'.JText::_('VIEW_NEW_TOPICS').'</a> ';
        }

        $output .= '<input type="submit" name="Submit" class="button" value="' .JText::_('BUTTON_LOGOUT'). '" />';
        $output .= '<input type="hidden" name="silent" value="true" />';
        $output .= '<input type="hidden" name="return" value="' .$return .'" />';

        if(JFusionFunction::isJoomlaVersion('1.6')){
            $output .= '<input type="hidden" name="task" value="user.logout" />';
            $output .= '<input type="hidden" name="option" value="com_users" />';
        } else {
            $output .= '<input type="hidden" name="task" value="logout" />';
            $output .= '<input type="hidden" name="option" value="com_user" />';
        }
        $output .= JHTML::_('form.token');
        $output .= '</form>';
    } else {

        if (JPluginHelper::isEnabled('authentication', 'openid')) {
            JHTML::_('script', 'openid.js');
        }

        $output .= '<form action="' . JRoute::_( 'index.php', true, $params->get('usesecure')). '" method="post" name="login" id="'.$form_id.'" >';
        $output .= $params->get('pretext');
        if ($params->get('show_labels',1)) {
        	$output .= '<label for="modlgn_username">'. JText::_('USERNAME') .'</label> ';
        }
        $output .= '<input placeholder="'.JText::_('USERNAME').'" id="modlgn_username" type="text" name="username" class="inputbox" alt="username" size="18" /> ';
        if ($params->get('show_labels',1)) {
        	$output .= '<label for="modlgn_passwd">'. JText::_('PASSWORD'). '</label> ';
        }
        if(JFusionFunction::isJoomlaVersion('1.6')){
            $output .= '<input placeholder="'.JText::_('PASSWORD').'" id="modlgn_passwd" type="password" name="password" class="inputbox" size="18" alt="password" /> ';
        } else {
            $output .= '<input placeholder="'.JText::_('PASSWORD').'" id="modlgn_passwd" type="password" name="passwd" class="inputbox" size="18" alt="password" /> ';
        }

        if ($params->get('show_rememberme')) {
            $output .= '<label for="modlgn_remember">' .JText::_('REMEMBER_ME') .'</label> ';
            $output .= '<input id="modlgn_remember" type="checkbox" name="remember" value="yes" alt="Remember Me" /> ';
        }
        $output .= '<input type="submit" name="Submit" class="button" value="' .JText::_('BUTTON_LOGIN').'" /> ';

        if ($params->get('lostpassword_show') || $params->get('lostusername_show') || $params->get('register_show')) {

            $output .= '<ul>';
            if ($params->get('lostpassword_show')) {
                $output .= '<li><a href="' .$lostpassword_url . '">' . JText::_('FORGOT_YOUR_PASSWORD') . '</a></li>';
            }

            if ($params->get('lostusername_show')) {
                $output .= '<li><a href="' .$lostusername_url . '">'. JText::_('FORGOT_YOUR_USERNAME') . '</a></li>';
            }

            $usersConfig = &JComponentHelper::getParams('com_users');
            if ($params->get('register_show')) {
                $output .= '<li><a href="' . $register_url .'">' . JText::_('REGISTER') . '</a> </li>';
            }
            $output .= '</ul>';
        }

        $output .= $params->get('posttext');

        if(JFusionFunction::isJoomlaVersion('1.6')){
            $output .= '<input type="hidden" name="task" value="user.login" />';
            $output .= '<input type="hidden" name="option" value="com_users" />';
        } else {
            $output .= '<input type="hidden" name="task" value="login" />';
            $output .= '<input type="hidden" name="option" value="com_user" />';
        }
        $output .= '<input type="hidden" name="silent" value="true" />';
        $output .= '<input type="hidden" name="return" value="' .$return . '" />';
        $output .= JHTML::_('form.token') . '</form>';
    }
} else {
    if ($type == 'logout') {
        $output .= '<form action="index.php" method="post" name="login" id="'.$form_id.'">';

        if (!empty($avatar)) {
            $maxheight = $params->get('avatar_height', 80);
            $maxwidth = $params->get('avatar_width', 60);

            $output .= '<div align="center"><img src="' . $avatar .'" alt="' .$display_name . '" style="';
            $output .= (!empty($maxheight)) ? " max-height: {$maxheight}px;" : "";
            $output .= (!empty($maxwidth)) ? " max-width: {$maxwidth}px;" : "";
            $output .= '" /></div>' . "\n";
        }

        if ($params->get('greeting')) {
			$custom_greeting = $params->get('custom_greeting');
        	if (empty($custom_greeting)) {
        		$custom_greeting = 'HINAME';       		
        	}
            $output .= '<div align="center">' . JText::sprintf($custom_greeting, $display_name);
            //if (!empty($pmcount)) {
                //$output .= '<br/>' . JText::_('PM_START');
                //$output .= '<a href="'.$url_pm.'">'.JText::sprintf('PM_LINK', $pmcount["total"])."</a>";
                //$output .= JText::sprintf('PM_END', $pmcount["unread"]);
            //}
            $output .= '</div>';
        }

        if (!empty($pmcount)) {
            $output .= '<div align="center">' . JText::_('PM_START');
            $output .= ' <a href="'.$url_pm.'">'.JText::sprintf('PM_LINK', $pmcount["total"]).'</a>';
            $output .= JText::sprintf('PM_END', $pmcount["unread"]);
            $output .= '</div>';
        }

        if (!empty($url_viewnewmessages)) {
            $output .= '<div align="center"><a href="'. $url_viewnewmessages .'">'.JText::_('VIEW_NEW_TOPICS').'</a></div>';
        }

        $output .= '<div align="center">';
        $output .= '<input type="submit" name="Submit" class="button" value="' .JText::_('BUTTON_LOGOUT'). '" />';
        $output .= '</div>';

        if(JFusionFunction::isJoomlaVersion('1.6')){
            $output .= '<input type="hidden" name="task" value="user.logout" />';
            $output .= '<input type="hidden" name="option" value="com_users" />';
        } else {
            $output .= '<input type="hidden" name="task" value="logout" />';
            $output .= '<input type="hidden" name="option" value="com_user" />';
        }

        $output .= '<input type="hidden" name="silent" value="true" />';
        $output .= '<input type="hidden" name="return" value="' .$return .'" />';
        $output .= JHTML::_('form.token');
        $output .= '</form>';
    } else {
        if (JPluginHelper::isEnabled('authentication', 'openid')) {
            JHTML::_('script', 'openid.js');
        }
        $output .= '<form action="' . JRoute::_( 'index.php', true, $params->get('usesecure')). '" method="post" name="login" id="'.$form_id.'" >';
        $output .= $params->get('pretext');
        $output .= '<p id="form-login-username">';
        if ($params->get('show_labels',1)) {
        	$output .= '<label for="modlgn_username">'. JText::_('USERNAME') .'</label><br />';
        }
        $output .= '<input placeholder="'.JText::_('USERNAME').'" id="modlgn_username" type="text" name="username" class="inputbox" alt="username" size="18" />';
        $output .= '</p><p id="form-login-password">';
        
        if ($params->get('show_labels',1)) {
        	$output .= '<label for="modlgn_passwd">'. JText::_('PASSWORD'). '</label><br />';
        }        
        if(JFusionFunction::isJoomlaVersion('1.6')){
            $output .= '<input placeholder="'.JText::_('PASSWORD').'" id="modlgn_passwd" type="password" name="password" class="inputbox" size="18" alt="password" /></p> ';
        } else {
            $output .= '<input placeholder="'.JText::_('PASSWORD').'" id="modlgn_passwd" type="password" name="passwd" class="inputbox" size="18" alt="password" /></p> ';
        }

        if ($params->get('show_rememberme')) {
            $output .= '<p id="form-login-remember">';
            $output .= '<label for="modlgn_remember">' .JText::_('REMEMBER_ME') .'</label>';
            $output .= '<input id="modlgn_remember" type="checkbox" name="remember" value="yes" alt="Remember Me" /></p>';
        }
        $output .= '<input type="submit" name="Submit" class="button" value="' .JText::_('BUTTON_LOGIN').'" />';

        if ($params->get('lostpassword_show') || $params->get('lostusername_show') || $params->get('register_show')) {

            $output .= '<ul>';
            if ($params->get('lostpassword_show')) {
                $output .= '<li><a href="' .$lostpassword_url . '">' . JText::_('FORGOT_YOUR_PASSWORD') . '</a></li>';
            }

            if ($params->get('lostusername_show')) {
                $output .= '<li><a href="' .$lostusername_url . '">'. JText::_('FORGOT_YOUR_USERNAME') . '</a></li>';
            }

            $usersConfig = &JComponentHelper::getParams('com_users');
            if ($params->get('register_show')) {
                $output .= '<li><a href="' . $register_url .'">' . JText::_('REGISTER') . '</a> </li>';
            }
            $output .= '</ul>';
        }

        $output .= $params->get('posttext');

        if(JFusionFunction::isJoomlaVersion('1.6')){
            $output .= '<input type="hidden" name="task" value="user.login" />';
            $output .= '<input type="hidden" name="option" value="com_users" />';
        } else {
            $output .= '<input type="hidden" name="task" value="login" />';
            $output .= '<input type="hidden" name="option" value="com_user" />';
        }
        $output .= '<input type="hidden" name="silent" value="true" />';
        $output .= '<input type="hidden" name="return" value="' .$return . '" />';
        $output .= JHTML::_('form.token') . '</form>';
    }
}

echo $output;