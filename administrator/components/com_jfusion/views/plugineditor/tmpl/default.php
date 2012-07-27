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
                <img src="components/com_jfusion/images/jfusion_large.png">
            </td>
            <td width="100px">
                <img src="components/com_jfusion/images/editor.png">
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
        $paneTabs = JPane::getInstance('tabs');
        echo $paneTabs->startPane('jfusion_plugin_editor');
        $inbox = 0;
        foreach ($this->params as $param) {
            $label = isset($param[0]) ? $param[0] : '';
            $content = isset($param[1]) ? $param[1] : '';
            $titel = isset($param[3]) ? $param[3] : '';
            $name = isset($param[5]) ? $param[5] : '';
            if ($name == 'jfusionbox') {
                if (!empty($inbox)) {
                    echo '</table>';
                    echo $paneTabs->endPanel();
                } else {
                    $inbox = 1;
                }
                echo $paneTabs->startPanel( JText::_($titel), $titel );
                echo '<table>';
            } else if (!empty($label) && $titel != ' ' && strpos($titel , '@') !== 0) {
                echo '<tr><td width="250px">' . $label . '</td><td>' . $content . '</td></tr>';
            } else {
                echo '<tr><td colspan=2>' . $content . '</td></tr>';
            }
        }
        echo '</table>';
        echo $paneTabs->endPanel();
        echo $paneTabs->endPane();
    }
    ?>
    <input type="hidden" name="jname" value="<?php echo $this->jname; ?>"/>
</form>