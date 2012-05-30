<?php

/**
 * This is view file for plugineditor
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Plugineditor
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

<form method="post" action="index.php" name="adminForm" id="adminForm">
    <input type="hidden" name="option" value="com_jfusion" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="action" value="" />
    <input type="hidden" name="customcommand" value="" />

    <table>
        <tr>
            <td width="100px">
                <img src="components/com_jfusion/images/jfusion_large.png" height="75px" width="75px">
            </td>
            <td width="100px">
                <img src="components/com_jfusion/images/editor.png" height="75px" width="75px">
            </td>
            <td>
                <h2>
                    <?php echo $this->jname . ' ' . JText::_('PLUGIN_EDITOR');?>
                </h2>
            </td>
        </tr>
    </table>
    <br/>

    <?php
    if ($this->params) {
        jimport('joomla.html.pane');
        $paneTabs = & JPane::getInstance('tabs');
        echo $paneTabs->startPane('jfusion_plugin_editor');
        $inbox = 0;
        foreach ($this->params as $param) {
            if ($param[5] == 'jfusionbox') {
                if (!empty($inbox)) {
                    echo '</table>';
                    echo $paneTabs->endPanel();
                } else {
                    $inbox = 1;
                }
                echo $paneTabs->startPanel( JText::_($param[3]), $param[3] );
                echo '<table>';
            } else if (!empty($param[0]) && $param[3] != ' ' && $param[3][0] != '@') {
                echo '<tr><td class="contentbox_label" width="250px">' . $param[0] . '</td><td class="contentbox_param">' . $param[1] . '</td></tr>';
            } else {
                echo '<tr><td colspan=2>' . $param[1] . '</td></tr>';
            }
        }
        echo '</table>';
        echo $paneTabs->endPanel();
        echo $paneTabs->endPane();
    }
    ?>
    <input type="hidden" name="jname" value="<?php echo $this->jname; ?>"/>
</form>