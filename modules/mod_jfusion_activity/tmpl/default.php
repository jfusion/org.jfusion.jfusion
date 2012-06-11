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
 * @var $config array
 */
if (empty($results)) :
    echo JText::_('NO_POSTS');
else :
?>
<ul style='list-style: none;'>
<?php
foreach($results as $r) {
	$output =& $r->output;

	if(!empty($r->output->avatar_source)) :
		echo '<li style="clear:left; margin-bottom: 5px;">';
		echo '<img style="vertical-align:middle; float:left; margin:3px; max-width: '.$output->avatar_width.'px; max-height: '.$output->avatar_height.'px;" src="'.$output->avatar_source.'" alt="'.JText::_('AVATAR').'"/>';
	else:
		echo '<li style="margin-bottom: 5px;">';
	endif;

	echo '<a href="'.$output->subject_url.'" target="'.$config['new_window'].'">'.$output->subject.'</a>';
	if($output->newpost) :
	   echo ' <img src="' . JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/new.png" style="margin-left:2px; margin-right:2px;" alt="'.JText::_('NEW').'"/>';
	endif;

	if ($config['showuser']):
    	if (!empty($output->profile_url)) :
    		echo '<b> - <a href="'.$output->profile_url.'" target="'.$config['new_window'].'">'.$output->display_name.'</a></b>';
    	elseif(!empty($r->guest)) :
    		echo ' - ' . $output->display_name . ' ('.JText::_('GUEST').')';
        else :
        	echo ' - ' . $output->display_name;
    	endif;
	endif;

	echo ' ' . $output->date;

	if(!empty($output->reply_count)) :
		echo ' [' . $output->reply_count .']';
	elseif(!empty($output->body)):
		echo ' - ' .$output->body;
	endif;

	echo '</li>';
}
?>
</ul>
<?php endif; ?>
</div>