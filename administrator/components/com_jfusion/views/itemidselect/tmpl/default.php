<?php

/**
 * This is view file for loginchecker
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Loginchecker
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
?>

<h3><?php echo JText::_('SELECT_INTEGRATED_VIEW')?> </h3>

<table class="adminlist" style="border-spacing:1px;">
	<thead>
        <tr>
	        <th width="10">
	        	<?php echo JText::_('ITEMID'); ?>
	        </th>
	        <th class="title">
	        	<?php echo JText::_('MENU'); ?>
	        </th>
	        <th class="title">
	       		<?php echo JText::_('TITLE'); ?>
	        </th>
	        <th class="title">
	        	<?php echo JText::_('ALIAS'); ?>
	        </th>
	        <th class="title">
	        	<?php echo JText::_('JFUSION') . ' ' . JText::_('PLUGIN'); ?>
	        </th>
	        <th width="7%">
	        	<?php echo JText::_('HELP_VISUAL'); ?>
	        </th>
        </tr>
	</thead>
	<tbody>
    <?php
    $row_count = 0;
    foreach ($this->menuitems as $row) {
		?>
		<tr class="row<?php echo $row_count; ?>">
		<?php
    	if ($row_count == 1) {
        	$row_count = 0;
       	} else {
       		$row_count = 1;
     	}
		?>
			<td>
            	<a style="cursor: pointer;" onclick="window.parent.jSelectItemid('<?php echo $this->ename; ?>','<?php echo $row->id; ?>',<?php echo $this->elId; ?>);">
            		<?php echo htmlspecialchars($row->id, ENT_QUOTES, 'UTF-8'); ?>
            	</a>
            </td>
            <td>
            	<?php echo $row->menutype;?>
            </td>
            <td>
            	<a style="cursor: pointer;" onclick="window.parent.jSelectItemid('<?php echo $this->ename; ?>','<?php echo $row->id; ?>',<?php echo $this->elId; ?>);">
            		<?php echo $row->name; ?>
            	</a>
            </td>
            <td>
            	<?php echo $row->alias; ?>
            </td>
            <td>
            	<?php //get the plugin name
            	$params = new JParameter($row->params);
            	$jPluginParam = unserialize(base64_decode($params->get('JFusionPluginParam')));
            	if (is_array($jPluginParam)) {
                	echo $jPluginParam['jfusionplugin'];
            	} else {
                	echo JText::_('NO_PLUGIN_SELECTED');
            	}
            	?>
            </td>
            <td>
            	<?php echo $params->get('visual_integration'); ?>
            </td>
		</tr>
        <?php
        }
        if (count($this->menuitems) == 0) { ?>
        <tr>
        	<td colspan=6>
        		<?php echo JText::_('NO_JFUSION_MENU_ITEMS'); ?>
        	</td>
        </tr>
        <?php
        }
        ?>
	</tbody>
</table>
<?php echo '<br/><h3>' . JText::_('SELECT_DIRECT_VIEW') . '</h3>'; ?>
<table class="adminlist" style="border-spacing:1px;">
	<thead>
        <tr>
	        <th class="title">
	       		<?php echo JText::_('NAME'); ?>
	        </th>
	        <th class="title">
	        	<?php echo JText::_('DESCRIPTION'); ?>
	        </th>
	        <th class="title">
	        	<?php echo JText::_('URL'); ?>
	        </th>
        </tr>
    </thead>
	<tbody>
    <?php
    $row_count = 0;
    foreach ($this->directlinks as $row) {
 		if ($row_count == 1) {
      		$row_count = 0;
		} else {
			$row_count = 1;
		}
	?>
		<tr class="row<?php echo $row_count; ?>">
            <td>
	            <a style="cursor: pointer;" onclick="window.parent.jSelectItemid('<?php echo $this->ename; ?>','<?php echo $row->name; ?>',<?php echo $this->elId; ?>);">
	            	<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?>
	            </a>
            </td>
            <td>
	            <?php
	            $JFusionParam = & JFusionFactory::getParams($row->name);
	            $description = $JFusionParam->get('description');
	            echo $description;
	            ?>
            </td>
            <td>
	            <?php
	            $source_url = $JFusionParam->get('source_url');
	            echo $source_url;
	            ?>
            </td>
		</tr>
	<?php
	}
	?>
	</tbody>
</table>