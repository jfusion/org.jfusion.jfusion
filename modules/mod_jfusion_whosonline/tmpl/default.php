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
?>
<div>
<?php
/**
 * @ignore
 * @var $outputs array
 * @var $output object
 * @var $config array
 */
foreach($outputs as $output) {
	echo '<h4>'.$output->title.'</h4>';

	if(!empty($output->error)) {
		echo $output->error;
	} elseif(!empty($output->custom_output)) {
		echo $output->custom_output;
	}else {
		if($config['showmode'] != '1') {
			echo JText::_('WE_HAVE').'&nbsp;';
			echo ($output->num_guests==0 || $output->num_guests>1) ? JText::sprintf('GUESTS',$output->num_guests) : JText::sprintf('GUEST','1');
			echo '&nbsp;' . JText::_('AND') . '&nbsp;';
			echo ($output->num_members==0 || $output->num_members>1) ? JText::sprintf('MEMBERS',$output->num_members) : JText::sprintf('MEMBER','1');
			echo '&nbsp;'.JText::_('ONLINE') . '<br />';
		}

		if(!is_array($output->online_users) && $config['showmode'] == '2') {
			echo JText::_('NO_USERS_ONLINE');
		} elseif (!empty($config['showmode'])) {
			 echo '<ul>';

			 foreach($output->online_users as $u) {
			 	$user_output =& $u->output;
				echo '<li>';
			 	if(!empty($user_output->avatar_source)) {
			 		echo "<img style='vertical-align:middle; margin:3px; max-width: {$user_output->avatar_width}px; max-height: {$user_output->avatar_height}px;' src='{$user_output->avatar_source}' alt='avatar' />";
			 	}

			 	if(!empty($user_output->user_url)) {
			 		echo '<b><a href="'. $user_output->user_url . '">'.$user_output->display_name.'</a></b>';
			 	} else {
			 		echo '<b>'.$user_output->display_name.'</b>';
			 	}
			 	echo '</li>';
			 }

		 	echo '</ul>';
		}
	}
}
?>
</div>