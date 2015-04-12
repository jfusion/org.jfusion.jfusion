<?php
/**
* @package JFusion
* @subpackage Views
* @author JFusion development team
* @copyright Copyright (C) 2008 JFusion. All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/

// no direct access
defined('_JEXEC') or die('Restricted access');
?>
<div class="jfusion">
	<?php
	echo $this->toolbar;
	echo '<h3>' . $this->title . '</h3>';
	?>
	<form name="adminForm" id="adminForm" method="post" action="index.php?option=com_jfusion">
		<input type="hidden" name="task" value="discussionbot" />
		<input type="hidden" name="tmpl" value="component" />
		<input type="hidden" name="jname" value="<?php echo $this->jname; ?>" />
		<input type="hidden" name="<?php echo $this->ename; ?>" value="<?php echo $this->hash; ?>" />
		<input type="hidden" name="ename" value="<?php echo $this->ename; ?>" />
		<input type="hidden" name="remove" value="" />
		<?php
		if(!empty($this->joomlaSelectOptions)) {
			echo JHTML::_('select.genericlist', $this->joomlaSelectOptions, 'joomlaid', 'class="inputbox" style="margin-right:15px;"', 'id', 'name', '');
		} elseif($this->ename == 'pair_sections') {
			echo JText::_('NO_SECTIONS');
		} else {
			echo JText::_('NO_CATEGORIES');
		}

		echo ' => ';

		if(!empty($this->forumSelectOptions)) {
			echo JHTML::_('select.genericlist', $this->forumSelectOptions, 'forumid', 'class="inputbox" style="margin-left:15px;"', 'id', 'name', '');
		} else {
			echo JText::_('NO_FORUMS');
		}

		if(!empty($this->joomlaSelectOptions) && !empty($this->forumSelectOptions)) {
			echo '<input id="add_plugin" type=button class="button" style="margin-left:5px;" value="' . JText::_('ADD') . '" onclick="if(this.value!=\'\') { $(\'adminForm\').submit(); }" /><br /><br />';
		}
		?>
		<table class="jfusionlist" style="border-spacing:1px;">
			<thead>
				<tr>
					<th class="title">
						<?php
						if($this->ename == 'pair_sections') echo JText::_('SECTION');
						elseif($this->ename == 'pair_categories') echo JText::_('CATEGORY');
						?>
					</th>
					<th class="title">
						<?php echo JText::_('FORUM' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if(!empty($this->pairs)) :
				foreach($this->pairs as $joomlaid => $forumid) {
				?>
					<tr id='joomla<?php echo $joomlaid; ?>'>
						<td>
							<?php
							if(isset($this->joomlaoptions[$joomlaid])) echo $this->joomlaoptions[$joomlaid]->name;
							elseif($this->ename == 'pair_sections') echo JText::_('SECTION_NOT_EXIST');
							elseif($this->ename == 'pair_categories') echo JText::_('CATEGORY_NOT_EXIST');
							?>
							<img src="components/com_jfusion/images/delete_icon.png" onclick="JFusion.removePair('<?php echo $joomlaid; ?>');"/>
						</td>
						<td>
							<?php
							if(isset($this->forumSelectOptions[$forumid])) echo $this->forumSelectOptions[$forumid]->name;
							else echo JText::_('FORUM_NOT_EXIST');
							?>
						</td>
					</tr>
					<?php
				}
				endif;
				?>
			</tbody>
		</table>
	</form>
</div>