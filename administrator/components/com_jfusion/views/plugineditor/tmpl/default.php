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
    <?php
    if ($this->params) {
        jimport('joomla.html.pane');
	    echo JHtml::_('tabs.start','jfusion_plugin_editor', array('startOffset'=>2));
        $inbox = 0;
        foreach ($this->params as $param) {
            $label = isset($param[0]) ? $param[0] : '';
            $content = isset($param[1]) ? $param[1] : '';
            $titel = isset($param[3]) ? $param[3] : '';
            $name = isset($param[5]) ? $param[5] : '';
            if ($name == 'jfusionbox') {
                if (!empty($inbox)) {
                    echo '</table>';
                } else {
                    $inbox = 1;
                }
	            echo JHtml::_('tabs.panel', JText::_($titel), $titel);
                echo '<table>';
            } else if (!empty($label) && $titel != ' ' && strpos($titel , '@') !== 0) {
                echo '<tr><td width="250px">' . $label . '</td><td>' . $content . '</td></tr>';
            } else {
                echo '<tr><td colspan=2>' . $content . '</td></tr>';
            }
        }
        echo '</table>';
	    echo JHtml::_('tabs.end');
    }
    ?>
    <input type="hidden" name="jname" value="<?php echo $this->jname; ?>"/>
</form>