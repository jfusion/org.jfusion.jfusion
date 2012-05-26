<?php

/**
 * This is view file for syncoptions
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Syncoptions
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
//display the paypal donation button
JFusionFunctionAdmin::displayDonate();
?>

<script type="text/javascript">
<!--

window.addEvent('domready', function() {


    var url = '<?php echo JURI::current(); ?>';
    // refresh every 15 seconds
    var timer = 1;
    var time_update = 10;
    var counter = 10;
    // periodical and dummy variables for later use
    var periodical, dummy, sub_vars;
    var start = $('start'), stop = $('stop'), log = $('log_res');
 //test
    /* our ajax istance for starting the sync */
    <?php  if(JFusionFunction::isJoomlaVersion('1.6')){
 echo 'var ajax = new Request.HTML({
           url: url,';
    } else {
 echo 'var ajax = new Ajax(url, {';
    }?>
        update: log,
        method: 'get',

        onComplete: function() {
            // when complete, check to see if we should stop the countdown
            div_content = document.getElementById('log_res').innerHTML;
            if (div_content.search(/finished/) != -1) {
                   // let's stop our timed ajax
                   $clear(periodical);
                document.getElementById("counter").innerHTML = '<b><?php echo JText::_('FINISHED'); ?></b>';
            }

        }
    });
    <?php  if(JFusionFunction::isJoomlaVersion('1.6')){
     echo 'var ajaxsync = new Request.HTML({
        url: url, ';
    } else {
echo 'var ajaxsync = new Ajax(url, {';
    }?>
        method: 'get'
    });

    /* our usersync status update function: */
    var refresh = (function() {

            //add another second to the counter
            counter = counter - 1;
            if (counter < 1) {
            div_content = document.getElementById('log_res').innerHTML;
            if (div_content.search(/finished/) != -1) {
                // let's stop our timed ajax
                $clear(periodical);
                document.getElementById("counter").innerHTML = '<b><?php echo JText::_('FINISHED'); ?></b>';
            } else {
                counter = time_update;
                // dummy to prevent caching of php
                dummy = $time() + $random(0, 100);
                //generate the get variable for submission

                sub_vars = 'option=com_jfusion&task=syncresume&tmpl=component&dummy=' + dummy + '&syncid=' + '<?php echo $this->syncid; ?>';
                for(i=0; i<document.adminForm.elements.length; i++)
                {
                    if (document.adminForm.elements[i].name=='userbatch')
                    {
                        sub_vars = sub_vars + '&' + document.adminForm.elements[i].name + '=' + document.adminForm.elements[i].value;
                    }
                }
                //document.getElementById("log_res").innerHTML = '<img src="<?php echo 'components/com_jfusion/images/ajax_loader.gif'; ?>"> Loading ....';
                progress_vars = 'option=com_jfusion&tmpl=component&task=syncprogress&syncid=' + '<?php echo $this->syncid; ?>';
    <?php  if(JFusionFunction::isJoomlaVersion('1.6')){
echo ' ajax.send(progress_vars);
                ajaxsync.send(sub_vars);';
    } else {
echo '                ajax.request(progress_vars);
                ajaxsync.request(sub_vars);';
    }    ?>

            }
        } else {
            //update the counter
            document.getElementById("counter").innerHTML = '<b><?php echo JText::_('UPDATE_IN'); ?> ' + counter + ' <?php echo JText::_('SECONDS'); ?></b>';
           }
    }
    );

    // start and stop click events
    start.addEvent('click', function(e) {
        // prevent default
        new Event(e).stop();
        // prevent insane clicks to start numerous requests
        $clear(periodical);

        /* a bit of fancy styles */
        stop.setStyle('font-weight', 'normal');
        start.setStyle('font-weight', 'bold');
        /* ********************* */

        //give the user a last chance to opt-out
        var answer = confirm("<?php echo JText::_('SYNC_CONFIRM_START'); ?>");
        if (answer) {
            var paramString = 'option=com_jfusion&task=syncinitiate&tmpl=component&syncid=<?php echo $this->syncid; ?>';
            for(i=0; i<document.adminForm.elements.length; i++){
                if (document.adminForm.elements[i].type=="select-one")
                {
                    if (document.adminForm.elements[i].options[document.adminForm.elements[i].selectedIndex].value)
                    {
                        paramString = paramString + '&' + document.adminForm.elements[i].name + '=' + document.adminForm.elements[i].options[document.adminForm.elements[i].selectedIndex].value;
                    }
                }
                if (document.adminForm.elements[i].name=='userbatch')
                {
                    paramString = paramString + '&' + document.adminForm.elements[i].name + '=' + document.adminForm.elements[i].value;
                }
            }
    <?php  if(JFusionFunction::isJoomlaVersion('1.6')){
echo ' new Request.HTML({url: url, method: \'get\'}).send(paramString);';
    } else {
echo 'new Ajax(url, {method: \'get\'}).request(paramString);';
    } ?>            
               periodical = refresh.periodical(timer * 1000, this);

        }
    }
    );

    stop.addEvent('click', function(e) {
        new Event(e).stop();
        // prevent default;

        /* a bit of fancy styles
        note: we do not remove 'ajax-loading' class
        because it is already done by 'onCancel'
        since we later do 'ajax.cancel()'
        */
        start.setStyle('font-weight', 'normal');
        stop.setStyle('font-weight', 'bold');
        /* ********************* */

        // let's stop our timed ajax
        $clear(periodical);
        // and let's stop our request in case it was waiting for a response
        ajax.cancel();
    }
    );
}
);


// -->
</script>

<table><tr>
<td width="100px"><img src="components/com_jfusion/images/jfusion_large.png" height="75px" width="75px"></td>
<td width="100px"><img src="components/com_jfusion/images/usersync.png" height="75px" width="75px"></td>
<td><h2><?php echo JText::_('USERSYNC'); ?></h2></td>
</tr></table><br/>

<h3><?php echo JText::_('SYNC_WARNING'); ?></h3><br/>

<?php
if ($this->sync_active) {
    echo '<h3 style="color:red;">' . JText::_('SYNC_IN_PROGRESS_WARNING') . "</h3><br />\n" ;
}
?>

<form method="post" action="index.php" name="adminForm" id="adminForm">
<div id="log_res">
<input type="hidden" name="option" value="com_jfusion" />
<input type="hidden" name="task" value="syncstatus" />
<input type="hidden" name="syncid" value="<?php echo $this->syncid; ?>" />

<?php if ($this->sync_mode == 'new') { ?>
    <div id="ajax_bar">
    <?php echo JText::_('SYNC_DIRECTION_SELECT'); ?>
    <select name="action" style="margin-right:10px; margin-left:5px;">
    <option value="master"><?php echo JText::_('SYNC_MASTER'); ?></option>
    <option value="slave"><?php echo JText::_('SYNC_SLAVE'); ?></option>
    </select>
    <?php echo JText::_('SYNC_NUMBER_OF_USERS'); ?>
    <input name="userbatch" class="inputbox" style="margin-right:10px; margin-left:5px;" value="500"/>
    </div><br/>

    <table class="adminlist" style="border-spacing:1px;"><thead><tr>
    <th width="50px"><?php echo JText::_('NAME'); ?></th>
    <th width="50px"><?php echo JText::_('TYPE'); ?></th>
    <th width="50px"><?php echo JText::_('USERS'); ?></th>
    <th width="200px"><?php echo JText::_('OPTIONS'); ?></th>
    </tr></thead>

    <tr><td><?php echo $this->master_data['jname']; ?></td>
    <td><?php echo JText::_('MASTER') ?></td>
    <td><?php echo $this->master_data['total']; ?></td>
    <td></td></tr>

    <?php
    foreach ($this->slave_data as $slave) { ?>

        <tr><td><?php echo $slave['jname']; ?></td>
        <td><?php echo JText::_('SLAVE') ?></td>
        <td><?php echo $slave['total']; ?></td>
        <td><select name="slave[<?php echo $slave['jname']; ?>][perform_sync]">
        <option value=""><?php echo JText::_('SYNC_EXCLUDE_PLUGIN'); ?></option>
        <option value="1"><?php echo JText::_('SYNC_INCLUDE_PLUGIN'); ?></option>
        </select></td></tr>
        <?php }
    ?>
    </table>
    <?php
} else {
    echo "<h2>" . JText::sprintf('SYNC_RESUME_SYNC', $this->syncid) . "</h2>";
    ?>
    <div id="ajax_bar">
    <?php
    echo JText::_('SYNC_NUMBER_OF_USERS');
    $user_batch = (isset($this->syncdata['userbatch'])) ? $this->syncdata['userbatch'] : 100;
    ?>
    <input name="userbatch" class="inputbox" style="margin-right:10px; margin-left:5px;" value="<?php echo $user_batch; ?>"/>
    </div><br/>
    <table class="adminlist" style="border-spacing:1px;"><thead><tr><th width="50px">
    <?php echo JText::_('PLUGIN') . ' ' . JText::_('NAME'); ?>
    </th><th align="center" class="title">
    <?php echo JText::_('SYNC_USERS_TODO'); ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USERS') . ' ' . JText::_('UNCHANGED'); ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USERS') . ' ' . JText::_('UPDATED'); ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USERS') . ' ' . JText::_('CREATED'); ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USERS') . ' ' . JText::_('DELETED'); ?>
    </th><th align="center" class="title">
    <?php echo JText::_('USER') . ' ' . JText::_('CONFLICTS'); ?>
    </th></tr></thead>
    <?php
    $row_count = 0;
    foreach ($this->syncdata['slave_data'] as $slave) {
        ?><tr class="row<?php echo $row_count;?>"><?php
        if ($row_count == 1) {
            $row_count = 0;
        } else {
            $row_count = 1;
        }
        ?><td><?php echo $slave['jname']; ?></td>
        <td><?php echo $slave['total']; ?></td>
        <td><?php echo $slave['unchanged']; ?></td>
        <td><?php echo $slave['updated']; ?></td>
        <td><?php echo $slave['created']; ?></td>
        <td><?php echo $slave['deleted']; ?></td>
        <td><?php echo $slave['error']; ?></td></tr>

        <?php
    } ?>
    </table>
    <?php
} ?>
</div>
</form>
<br/>
<div id="counter"></div><br/>

<div id="ajax_bar"><b><?php echo JText::_('SYNC_CONTROLLER'); ?></b>&nbsp;&nbsp;&nbsp;
<a id="start" href="#"><?php echo JText::_('START'); ?></a>
<span class="border">&nbsp;</span>
<a id="stop" href="#"><?php echo JText::_('STOP'); ?></a></div><br/>

<br/><br/><br/>
<?php echo '<a href="index.php?option=com_jfusion&task=syncoptions&syncid=' . $this->syncid . '">' . JText::_('SYNC_RESUME') . '</a>';
