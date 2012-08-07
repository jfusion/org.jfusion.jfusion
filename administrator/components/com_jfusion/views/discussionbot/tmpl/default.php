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

echo $this->toolbar;
echo '<h3>' . $this->title . '</h3>';
?>
<form name="adminForm" id='adminForm' method="post" action="index.php">
<input type='hidden' name='option' value='com_jfusion' />
<input type='hidden' name='task' value='discussionbot' />
<input type='hidden' name='tmpl' value='component' />
<input type='hidden' name='jname' value='<?php echo $this->jname; ?>' />
<input type='hidden' name='<?php echo $this->dbtask; ?>' value='<?php echo $this->encoded_pairs; ?>' />
<input type='hidden' name='ename' value='<?php echo $this->dbtask; ?>' />
<input type='hidden' name='remove' value='' />
<?php
if(!empty($this->joomlaSelectOptions)) {
	echo JHTML::_('select.genericlist', $this->joomlaSelectOptions, 'joomlaid', 'class="inputbox" style="margin-right:15px;"', 'id', 'name', '');
} elseif($this->dbtask=='pair_sections') {
	echo JText::_('NO_SECTIONS');
} else {
	echo JText::_('NO_CATEGORIES');
}

echo " => ";

if(!empty($this->forumSelectOptions)) {
	echo JHTML::_('select.genericlist', $this->forumSelectOptions, 'forumid', 'class="inputbox" style="margin-left:15px;"', 'id', 'name', '');
} else {
	echo JText::_('NO_FORUMS');
}

if(!empty($this->joomlaSelectOptions) && !empty($this->forumSelectOptions)) {
	echo '<input id="add_plugin" type=button class="button" style="margin-left:5px;" value="'.JText::_('ADD').'" onclick="if(this.value!=\'\') { $(\'adminForm\').submit(); }" /><br /><br />';
}
?>
<table class="adminlist" style="border-spacing:1px;">
<thead>
<tr>
<th class="title">
<?php
if($this->dbtask=='pair_sections') echo JText::_('SECTION');
elseif($this->dbtask=='pair_categories') echo JText::_('CATEGORY');
?>
</th>
<th class="title"><?php echo JText::_('FORUM' ); ?></th>
</tr>
</thead>
<tbody>
<?php
if(!empty($this->pairs)) :
$row_count = 0;
foreach($this->pairs as $joomlaid => $forumid) {
?>
	<tr class="row<?php echo ($row_count % 2); ?>" id='joomla<?php echo $joomlaid; ?>'>
	<td>
		<?php
		if(isset($this->joomlaoptions[$joomlaid])) echo $this->joomlaoptions[$joomlaid]->name;
		elseif($this->dbtask=='pair_sections') echo JText::_('SECTION_NOT_EXIST');
		elseif($this->dbtask=='pair_categories') echo JText::_('CATEGORY_NOT_EXIST');
		?>
		<img src="components/com_jfusion/images/delete_icon.png" onclick="$('adminForm').remove.value = '<?php echo $joomlaid; ?>'; $('adminForm').submit();"/></td>
	<td>
		<?php
		if(isset($this->forumSelectOptions[$forumid])) echo $this->forumSelectOptions[$forumid]->name;
		else echo JText::_('FORUM_NOT_EXIST');
		?>
	</td>
	</tr>
	<?php
    $row_count++;
}
endif;
?>

</tbody>
</table>
</form>