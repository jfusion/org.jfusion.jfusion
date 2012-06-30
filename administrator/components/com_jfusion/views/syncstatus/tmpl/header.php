<?php

/**
 * This is view file for syncstatus
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Syncstatus
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
$inline = (!empty($this->sync_completed) || JRequest::getVar('tmpl') != 'component') ? true : false;
//check to see if there is anything to output
if (!isset($this->syncdata['slave_data']) || !$this->syncdata['slave_data']) {
    ?>
    <b style="color:red;">
        <?php echo JText::_('SYNC_NODATA') ?>
    </b>
    <?php
} else {
    if (!empty($this->syncdata['completed'])) {
        echo '<br/>';
        //check to see if there were any errors
        if (!empty($this->syncdata['sync_errors'])) {
            //redirect to resolve errors
            if (!$inline) {
                //in a modal window so redirect parent
                echo '<h2><a href="javascript:void(0);" onclick="window.parent.location=\'index.php?option=com_jfusion&task=syncerror&syncid=' . $this->syncdata['syncid'] . '\';">' . JText::_('SYNC_CONFLICT') . '</a></h2>';
            } else {
                echo '<h2><a href="index.php?option=com_jfusion&task=syncerror&syncid=' . $this->syncdata['syncid'] . '">' . JText::_('SYNC_CONFLICT') . '</a></h2>';
            }
        } else {
            //inform about the success
            echo '<h2>' . JText::_('SYNC_SUCCESS') . '</h2>';
        }
    } else {
        echo '<br/>';
        //sync did not finish so let's give the option to resume it
        if (!$inline) {
            echo '<h2><a href="javascript:void(0);" onclick="window.parent.location=\'index.php?option=com_jfusion&task=syncoptions&syncid=' . $this->syncdata['syncid'] . '\';">' . JText::_('SYNC_INCOMPLETE') . '</a></h2>';
        } else {
            echo '<h2><a href="index.php?option=com_jfusion&task=syncoptions&syncid=' . $this->syncdata['syncid'] . '">' . JText::_('SYNC_INCOMPLETE') . '</a></h2>';
        }
    }
    ?>
    <h2><?php echo JText::_('SYNC_STATUS'); ?></h2>

    <table class="adminlist" style="border-spacing:1px;"><thead><tr><th width="50px">
    <?php echo JText::_('PLUGIN') . ' ' . JText::_('NAME');
    ?>
    </th><th align="center" class="title">
    <?php echo JText::_('SYNC_USERS_TODO');
    ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USERS') . ' ' . JText::_('UNCHANGED');
    ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USERS') . ' ' . JText::_('UPDATED');
    ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USERS') . ' ' . JText::_('CREATED');
    ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USER') . ' ' . JText::_('CONFLICTS');
    ?>
    </th></tr></thead>
    <?php
    $row_count = 0;
    foreach ($this->syncdata['slave_data'] as $slave) {
        ?><tr class="row<?php echo $row_count; ?>"><?php
        if ($row_count == 1) {
            $row_count = 0;
        } else {
            $row_count = 1;
        }
        ?>
        <td><?php echo $slave['jname']; ?></td>
        <td><?php echo $slave['total']; ?></td>
        <td><?php echo $slave['unchanged']; ?></td>
        <td><?php echo $slave['updated']; ?></td>
        <td><?php echo $slave['created']; ?></td>
        <td><?php echo $slave['error']; ?></td></tr>

        <?php
    } ?>
    </table>

    <?php
    echo '<br/><h2>' . JText::_('SYNC_LOG') . '</h2><br/>';?>

    <a href="index.php?option=com_jfusion&amp;task=syncstatus&amp;syncid=<?php echo $this->syncdata['syncid']; ?>"><?php echo JText::_('CLICK_FOR_MORE_DETAILS'); ?></a>
<?php
}