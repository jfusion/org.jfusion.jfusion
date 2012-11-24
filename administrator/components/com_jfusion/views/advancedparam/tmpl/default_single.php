<?php

/**
 * This is view file for advancedparam
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Advancedparam
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
$isJ16 = JFusionFunction::isJoomlaVersion('1.6');

?>
<h1>Select Plugin Single</h1>
<form
    action="index.php?option=com_jfusion&task=advancedparamsubmit&tmpl=component&elNum=<?php echo $this->elNum; ?>"
    method="post" name="adminForm" id="adminForm">
<table class="paramlist admintable" style="width:100%;border-spacing:1px;">
    <tbody>
        <tr>
            <td class="paramlist_key">JFusion Plugin</td>
            <td class="paramlist_value"><?php echo $this->output; ?></td>
        </tr>
        <tr style="padding:0; margin:0;">
            <td colspan="2" style="padding:0; margin:0;">
                <?php
                if ($isJ16 && !empty($this->comp)):
                    $fieldsets = $this->comp->getFieldsets();
                    $pane = JPane::getInstance('tabs', array('startOffset'=>2));
                    echo $pane->startPane('params');
                    foreach ($fieldsets as $fieldset):
                        echo $pane->startPanel(JText::_($fieldset->name.'_jform_fieldset_label'), $fieldsets);
                        echo '<fieldset class="panelform">';
                        echo '<dl>';
                        foreach($this->comp->getFieldset($fieldset->name) as $field):
                            // If the field is hidden, just display the input.
                            if ($field->hidden):
                                echo $field->input;
                            else:
                                echo '<dt>' . $field->label . '</dt>';
                                echo '<dd' . (($field->type == 'Editor' || $field->type == 'Textarea') ? ' style="clear: both; margin: 0;"' : '') . '>';
                                echo $field->input;
                                echo '</dd>';
                            endif;
                        endforeach;
                        echo '</dl>';
                        echo '</fieldset>';
                        echo $pane->endPanel();
                    endforeach;
                    $pane->endPane();
                else:
                    if ($this->comp && ($params = $this->comp->render('params'))) {
                        echo $params;
                    }
                endif;
                ?>
            </td>
        </tr>
        <tr>
            <td colspan="2"><input type="submit" value="Save" /></td>
        </tr>
    </tbody>
</table>
</form>
