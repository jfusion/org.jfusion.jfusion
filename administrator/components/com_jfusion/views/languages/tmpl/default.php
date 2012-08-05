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

<table>
    <tr>
        <td width="100px">
            <img src="components/com_jfusion/images/jfusion_large.png">
        </td>
        <td width="100px">
            <img src="components/com_jfusion/images/language.png">
        </td>
        <td>
            <h2>
                <?php echo JText::_('LANGUAGES'); ?>
            </h2>
        </td>
    </tr>
</table>
<br/>

<style type="text/css">
    tr.good0 { background-color: #ecfbf0; }
    tr.good1 { background-color: #d9f9e2; }
    tr.bad0 { background-color: #f9ded9; }
    tr.bad1 { background-color: #f9e5e2; }
    table.adminform td {width: 33%;}
</style>

<table class="adminform" style="border-spacing:1px;">
    <thead>
    <tr>
        <th class="title " align="left" width="20px;">
            <?php echo JText::_('ID'); ?>
        </th>
        <th class="title " align="left">
            <?php echo JText::_('DESCRIPTION'); ?>
        </th>
        <th class="title" align="center">
            <?php echo JText::_('YOUR_VERSION'); ?>
        </th>
        <th class="title" align="center">
            <?php echo JText::_('CURRENT_VERSION'); ?>
        </th>
        <th class="title">
            <?php echo JText::_('INSTALL_UPGRADE'); ?>
        </th>
    </tr>
    </thead>
    <tbody>
        <?php $row_count = 0;
        foreach ($this->lang_repo as $lang => $data) { ?>
            <tr class="row<? echo $row_count; ?>">
                <td>
                    <?php echo $lang; ?>
                </td>
                <td>
                    <?php echo $data['description']; ?>
                </td>
                <td>
                    <?php
                    if (isset($this->lang_installed[$lang])) {
                        echo $this->lang_installed[$lang];
                        $mode = JText::_('UPDATE');
                    } else {
                        $mode = JText::_('INSTALL');
                    }
                    ?>
                </td>
                <td>
                    <?php echo $data['date']; ?>
                </td>
                <td>
                    <?php
                    if (!isset($this->lang_installed[$lang]) || ($this->lang_installed[$lang] != $data['date'] ) ) {
                    ?>
                        <script type="text/javascript">
                            <!--
                            window.addEvent('domready',function() {
                                $('<?php echo $lang ;?>').addEvent('click', function(e) {
                                    new Event(e).stop();

                                    confirmSubmitLanguage('<?php echo $data['file']; ?>');
                                });
                            });
                            // -->
                        </script>
                        <a id="<?php echo $lang ?>" href="<?php echo $data['file']; ?>"><?php echo $mode;?></a> / <a href="<?php echo $data['file']; ?>"><?php echo JText::_('DOWNLOAD') ;?></a>
                    <?php
                    }
                    ?>
                </td>
            </tr>
        <?php } ?>
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
            $('install').install_url.value = action;
            $('install').submit();
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
    <?php if(JFusionFunction::isJoomlaVersion('1.6')){ ?>
    <input type="hidden" name="task" value="install.install" />
    <?php } else { ?>
    <input type="hidden" name="task" value="doInstall" />
    <?php } ?>
    <input type="hidden" name="option" value="com_installer" />
    <?php echo JHTML::_('form.token'); ?>
</form>
<br/><br/>
