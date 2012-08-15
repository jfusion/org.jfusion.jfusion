<?php
/**
* @package JFusion
* @subpackage Modules
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @ignore
 * @var $joomlaUser object
 * @var $output object
 * @var $config array
 */
if(!$joomlaUser->guest) :
	if(!empty($output->avatar_source) && $config['avatar_location']=='left') :
		echo "<div style='height:{$config['avatar_height']}px; text-align:{$config['alignment']};'>";
		echo '<div style="float:left; margin-right:5px;">';
	else:
		echo "<div style='text-align:{$config['alignment']};'>";
	endif;

	if(!empty($output->avatar_source)) {
		echo "<img style='vertical-align:middle; max-width: {$output->avatar_width}px; max-height: {$output->avatar_height}px;' src='{$output->avatar_source}' alt='avatar' />";
		if($config['avatar_location']=='left')
			echo '</div>';
	}

	if(!empty($output->pm_url)) {
		echo "<div style='text-align:{$config['alignment']};'>";
		echo JText::_('PM_START');
		echo " <a href='{$output->pm_url}'>".JText::sprintf('PM_LINK', $output->pm_count['total']).'</a>';
		echo JText::sprintf('PM_END', $output->pm_count['unread']);
		echo '</div>';
	}

	if(!empty($output->newmessages_url)) {
		echo "<div style='text-align:{$config['alignment']};'>";
		echo "<a href='{$output->newmessages_url}' target='{$config['new_window']}'>" . JText::_('VIEW_NEW_TOPICS') . '</a>';
		echo '</div>';
	}

	echo '</div>';
else:
	echo $config['login_msg'];
endif;