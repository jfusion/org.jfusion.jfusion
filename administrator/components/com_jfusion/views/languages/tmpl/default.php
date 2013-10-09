<?php

/**
 * This is view file for versioncheck
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Versioncheck
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

<style type="text/css">
    tr.good0 { background-color: #ecfbf0; }
    tr.good1 { background-color: #d9f9e2; }
    tr.bad0 { background-color: #f9ded9; }
    tr.bad1 { background-color: #f9e5e2; }
    .percentbar { background:#CCCCCC; border:1px solid #666666; height:10px; }
    .percentbar div { background: #28B8C0; height: 10px; }
</style>

<table class="adminform" style="border-spacing:1px;">
    <thead>
        <tr>
            <th class="title " align="left">
                <?php echo JText::_('ID'); ?>
            </th>
            <th class="title " align="left">
                <?php echo JText::_('LANGUAGE'); ?>
            </th>
            <th class="title" align="center">
                <?php echo JText::_('TRANSLATION_STATUS'); ?>
            </th>
            <th class="title" align="center">
                <?php echo JText::_('YOUR_VERSION'); ?>
            </th>
            <th class="title" align="center">
                <?php echo JText::_('CURRENT_VERSION'); ?>
            </th>
            <th class="title" align="center">
                <?php echo JText::_('OPTIONS'); ?>
            </th>

        </tr>
    </thead>
    <tbody>
        <?php $row_count = 0;
        $scale = 1;
        foreach ($this->lang_repo as $lang => $data) {
            $percent = str_replace('%','',$data->progress); ?>
            <tr class="<?php echo $data->class.($row_count % 2); ?>">
                <td style="width:50px;">
                    <?php echo $lang; ?>
                </td>
                <td>
                    <?php echo $data->description; ?>
                </td>
                <td style="width:150px;">
                    <div>
                        <div class="percentbar" style="width:<?php echo round(100 * $scale); ?>px;">
                            <div style="width:<?php echo round($percent * $scale); ?>px;"></div>
                        </div>
                        <?php echo $data->progress; ?>
                    </div>
                </td>
                <td style="width:20%;">
                    <?php
                    if ($data->currentdate) {
                        echo $data->currentdate;
                        $mode = JText::_('UPDATE');
                    } else {
                        echo JText::_('NOT_INSTALLED');
                        $mode = JText::_('INSTALL');
                    }
                    ?>
                </td>
                <td style="width:20%;">
                    <?php echo $data->date; ?>
                </td>
                <td>
                    <?php
                    if ($data->currentdate != $data->date) {
                        ?>
                        <script type="text/javascript">
                            <!--
                            window.addEvent('domready',function() {
                                $('<?php echo $lang ;?>').addEvent('click', function(e) {
                                    new Event(e).stop();

                                    confirmSubmitLanguage('<?php echo $data->file; ?>');
                                });
                            });
                            // -->
                        </script>
                        <a id="<?php echo $lang; ?>" href="<?php echo $data->file; ?>"><?php echo $mode; ?></a> / <a href="<?php echo $data->file; ?>"><?php echo JText::_('DOWNLOAD') ; ?></a>
                        <?php
                    }
                    ?>
                </td>
            </tr>
        <?php
            $row_count++;
        } ?>
    </tbody>
</table>
<script type="text/javascript">
    <!--
    function confirmSubmitLanguage(action)
    {
        var r = false;
        var confirmtext;
        confirmtext = '<?php echo JText::_('INSTALL_UPGRADE_LANGUAGE_PACKAGE')?>';

        var agree = confirm(confirmtext);
        if (agree) {
	        var install = $('install');
            install.install_url.value = action;
            install.submit();
            r = true;
        }
        return r;
    }
    // -->
</script>
<form enctype="multipart/form-data" action="index.php" method="post" id="install" name="adminForm2">
    <input type="hidden" name="install_url" value="" />
    <input type="hidden" name="type" value="" />
    <input type="hidden" name="installtype" value="url" />
    <input type="hidden" name="redirect_url" value="index.php?option=com_jfusion&task=languages" />
    <?php if(JFusionFunction::isJoomlaVersion('1.6')){ ?>
    <input type="hidden" name="task" value="install.install" />
    <?php } else { ?>
    <input type="hidden" name="task" value="doInstall" />
    <?php } ?>
    <input type="hidden" name="option" value="com_installer" />
    <?php echo JHTML::_('form.token'); ?>
</form>
<br/><br/>
<a target="_blank" href="https://www.transifex.com/projects/p/jfusion/"><img border="0" src="components/com_jfusion/images/transifex.png"></a>
<br/><br/>