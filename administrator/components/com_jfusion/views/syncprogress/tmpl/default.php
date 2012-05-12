<?php

/**
 * This is view file for syncprogress
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Syncprogress
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
if (!$this->syncdata['total_to_sync'] > 0) {
	?>
    <b>
    	<?php echo JText::_('SYNC_NODATA') ?>
    </b>
    <?php
} else {
	$percent = ($this->syncdata['synced_users'] / $this->syncdata['total_to_sync']) * 100;
	if ($percent != 100) {
	    $plugin_offset = (isset($this->syncdata['plugin_offset'])) ? $this->syncdata['plugin_offset'] : 0;
	    ?>
	    <b>
			<?php echo JText::_('SYNC_CURRENTLY_SYNCING'); ?> :
	    </b>
	    <?php echo $this->syncdata['slave_data'][$plugin_offset]['jname']; ?>
	    <b>
			<?php echo JText::_('SYNC_USERS_TODO_PLUGIN'); ?> :
	    </b>
	    <?php echo $this->syncdata['slave_data'][$plugin_offset]['total']; ?>	    
	    <b>
			<?php echo JText::_('SYNC_TOTAL_PROGRESS'); ?> :
	    </b>
	    <br />
	    <div style='border:1px solid #ccc; width:200px; height:20px;'>
	    	<div style='background:#ccc; height:20px; width:<?php echo $percent; ?>%'>
	    	</div>
	    </div>";
	    <?php
	}
}