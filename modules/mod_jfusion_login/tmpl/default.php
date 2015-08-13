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
 * @var $twofactormethods array
 */

if (JPluginHelper::isEnabled('authentication', 'openid')) {
	JHTML::_('script', 'openid.js');
}
?>
<form action="<?php echo JRoute::_('index.php', true, $params->get('usesecure')); ?>" method="post" name="login" id="login-form" class="form-inline" >
	<?php if ($params->get('pretext')) : ?>
		<div class="pretext">
			<p><?php echo $params->get('pretext'); ?></p>
		</div>
	<?php endif; ?>
	<div class="userdata">
		<div id="form-login-username" class="control-group">
			<div class="controls">
				<?php if (!$params->get('show_labels')) : ?>
					<div class="input-prepend">
						<span class="add-on">
							<span class="icon-user hasTooltip" title="<?php echo JText::_('USERNAME') ?>"></span>
							<label for="modlgn-username" class="element-invisible"><?php echo JText::_('USERNAME'); ?></label>
						</span>
						<input id="modlgn-username" type="text" name="username" class="input-small" tabindex="0" size="18" placeholder="<?php echo JText::_('USERNAME') ?>" />
					</div>
				<?php else: ?>
					<label for="modlgn-username"><?php echo JText::_('USERNAME') ?></label>
					<input id="modlgn-username" type="text" name="username" class="input-small" tabindex="0" size="18" placeholder="<?php echo JText::_('USERNAME') ?>" />
				<?php endif; ?>
			</div>
		</div>
		<div id="form-login-password" class="control-group">
			<div class="controls">
				<?php if (!$params->get('show_labels')) : ?>
					<div class="input-prepend">
						<span class="add-on">
							<span class="icon-lock hasTooltip" title="<?php echo JText::_('PASSWORD') ?>"></span>
							<label for="modlgn-passwd" class="element-invisible"><?php echo JText::_('PASSWORD'); ?></label>
						</span>
						<input id="modlgn-passwd" type="password" name="password" class="input-small" tabindex="0" size="18" placeholder="<?php echo JText::_('PASSWORD') ?>" />
					</div>
				<?php else: ?>
					<label for="modlgn-passwd"><?php echo JText::_('PASSWORD') ?></label>
					<input id="modlgn-passwd" type="password" name="password" class="input-small" tabindex="0" size="18" placeholder="<?php echo JText::_('PASSWORD') ?>" />
				<?php endif; ?>
			</div>
		</div>

		<?php if (count($twofactormethods) > 1): ?>
			<div id="form-login-secretkey" class="control-group">
				<div class="controls">
					<?php if (!$params->get('show_labels')) : ?>
						<div class="input-prepend input-append">
							<span class="add-on">
								<span class="icon-star hasTooltip" title="<?php echo JText::_('JGLOBAL_SECRETKEY'); ?>">
								</span>
									<label for="modlgn-secretkey" class="element-invisible"><?php echo JText::_('JGLOBAL_SECRETKEY'); ?>
									</label>
							</span>
							<input id="modlgn-secretkey" type="text" name="secretkey" class="input-small" tabindex="0" size="18" placeholder="<?php echo JText::_('JGLOBAL_SECRETKEY') ?>" />
							<span class="btn width-auto hasTooltip" title="<?php echo JText::_('JGLOBAL_SECRETKEY_HELP'); ?>">
								<span class="icon-help"></span>
							</span>
						</div>
					<?php else: ?>
						<label for="modlgn-secretkey"><?php echo JText::_('JGLOBAL_SECRETKEY') ?></label>
						<input id="modlgn-secretkey" type="text" name="secretkey" class="input-small" tabindex="0" size="18" placeholder="<?php echo JText::_('JGLOBAL_SECRETKEY') ?>" />
						<span class="btn width-auto hasTooltip" title="<?php echo JText::_('JGLOBAL_SECRETKEY_HELP'); ?>">
							<span class="icon-help"></span>
						</span>
					<?php endif; ?>

				</div>
			</div>
		<?php endif; ?>

		<?php if (JPluginHelper::isEnabled('system', 'remember') && $params->get('show_rememberme')) : ?>
			<div id="form-login-remember" class="control-group checkbox">
				<label for="modlgn-remember" class="control-label"><?php echo JText::_('REMEMBER_ME') ?></label> <input id="modlgn-remember" type="checkbox" name="remember" class="inputbox" value="yes"/>
			</div>
		<?php endif; ?>

		<div id="form-login-submit" class="control-group">
			<div class="controls">
				<button type="submit" tabindex="0" name="Submit" class="btn btn-primary"><?php echo JText::_('BUTTON_LOGIN') ?></button>
			</div>
		</div>

		<?php if ($params->get('lostpassword_show') || $params->get('lostusername_show') || $params->get('register_show')) : ?>
			<ul class="unstyled">
				<?php if ($params->get('lostpassword_show')) : ?>
					<li>
						<a href="<?php echo $lostpassword_url; ?>"><?php echo JText::_('FORGOT_YOUR_PASSWORD'); ?></a>
					</li>
				<?php endif; ?>

				<?php if ($params->get('lostusername_show')) : ?>
					<li>
						<a href="<?php echo $lostusername_url; ?>"><?php echo JText::_('FORGOT_YOUR_USERNAME'); ?></a>
					</li>
				<?php endif; ?>

				<?php if ($params->get('register_show')) : ?>
					<li>
						<a href="<?php echo $register_url; ?>"><?php echo JText::_('REGISTER'); ?></a>
					</li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>

		<input type="hidden" name="task" value="user.login" />
		<input type="hidden" name="option" value="com_users" />
		<input type="hidden" name="silent" value="true" />
		<input type="hidden" name="return" value="<?php echo $return; ?>" />
		<?php echo JHTML::_('form.token'); ?>
	</div>

	<?php if ($params->get('posttext')) : ?>
		<div class="posttext">
			<p><?php echo $params->get('posttext'); ?></p>
		</div>
	<?php endif; ?>
</form>
