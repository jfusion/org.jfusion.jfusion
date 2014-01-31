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

//define output var for nicer code

/**
 * @ignore
 * @var $params JRegistry
 * @var $type string
 * @var $display_name string
 * @var $url_pm string
 * @var $return string
 * @var $lostpassword_url string
 * @var $lostusername_url string
 * @var $register_url string
 * @var $avatar string
 */
$maxheight = $params->get('avatar_height', 80);
$maxwidth = $params->get('avatar_width', 60);

$avatar_style = (!empty($maxheight)) ? " max-height: {$maxheight}px;" : '';
$avatar_style .= (!empty($maxwidth)) ? " max-width: {$maxwidth}px;" : '';

$custom_greeting = $params->get('custom_greeting');
$custom_greeting = $params->get('custom_greeting');
if (empty($custom_greeting)) {
	$custom_greeting = 'HINAME';
}
$custom_greeting = JText::sprintf($custom_greeting, $display_name);
?>

<form action="<?php echo JRoute::_('index.php', true, $params->get('usesecure')); ?>" method="post" name="login" id="login-form" >
	<?php if (!empty($avatar)) : ?>
		<img src="<?php echo $avatar; ?>" alt="<?php echo $display_name; ?>" style="<?php echo $avatar_style; ?>" />
	<?php endif; ?>

	<?php if ($params->get('greeting')) : ?>
		<?php echo $custom_greeting; ?>
	<?php endif; ?>

	<?php if (!empty($pmcount)) : ?>
		<?php echo JText::_('PM_START'); ?>
		<a href="<?php echo $url_pm; ?>"><?php echo JText::sprintf('PM_LINK', $pmcount["total"]); ?></a>

		<?php echo JText::sprintf('PM_END', $pmcount["unread"]); ?>
	<?php endif; ?>

	<?php if (!empty($url_viewnewmessages)) : ?>
		<a href="<?php echo $url_viewnewmessages; ?>"><?php echo JText::_('VIEW_NEW_TOPICS'); ?></a>
	<?php endif; ?>

	<input type="submit" name="Submit" class="button" value="<?php echo JText::_('BUTTON_LOGOUT'); ?>" />
	<input type="hidden" name="silent" value="true" />
	<input type="hidden" name="return" value="<?php echo $return; ?>" />

	<input type="hidden" name="task" value="user.logout" />
	<input type="hidden" name="option" value="com_users" />

	<?php echo JHTML::_('form.token'); ?>
</form>