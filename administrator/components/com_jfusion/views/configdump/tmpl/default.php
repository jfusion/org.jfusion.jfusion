<?php
/**
 * @package JFusion
 * @subpackage Views
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

//load debug library
require_once(JPATH_ADMINISTRATOR .DS.'components'.DS.'com_jfusion'.DS.'models'.DS.'model.debug.php');
?>
<table>
    <tr>
        <td width="100px">
            <img src="components/com_jfusion/images/jfusion_large.png">
        </td>
        <td width="100px">
            <img src="components/com_jfusion/images/login_checker2.png">
        </td>
        <td>
            <h2>
                <?php echo JText::_('CONFIGDUMP_RESULT');?>
            </h2>
        </td>
    </tr>
</table>

<table>
    <tr>
        <td width="100px" style="background-color:#F5A9A9">
            <?php echo JText::_('CONFIGDUMP_ERROR');?>
        </td>
    </tr>
    <tr>
        <td width="100px" style="background-color:#088A08">
            <?php echo JText::_('CONFIGDUMP_SEEMS_OK');?>
        </td>
    </tr>
    <tr>
        <td width="100px" style="background-color:#FFFF00">
            <?php echo JText::_('CONFIGDUMP_POSIBLE_ERROR');?>
        </td>
    </tr>
</table>

<form method="post" action="index.php" name="adminForm" id="adminForm">
    <input type="hidden" name="option" value="com_jfusion" />
    <input type="hidden" name="task" value="configdump" />
    <table>
        <tr>
            <td>
                <?php echo JText::_('CONFIGDUMP_FILTER');?>
            </td>
            <td>
                <input type="checkbox" name="filter" value="true" />
            </td>
        </tr>
        <tr>
            <td>
                <?php echo JText::_('CONFIGDUMP_MASK');?>
            </td>
            <td>
                <input type="checkbox" checked="yes" name="mask" value="true" />
            </td>
        </tr>
    </table>
</form>

<?php
$textOutput = array();
debug::$callback = array($this,'jfusion_plugin',null);
foreach($this->jfusion_plugin as $key => $value) {
    $title = JText::_('JFUSION') . ' ' . $key . ' ' . JText::_('PLUGIN');
    debug::show($value, $title);
    $textOutput[$title] = $value;
    ?><br><?php
}

foreach($this->jfusion_module as $key => $value) {
    debug::$callback = array($this,'jfusion_module',$key);
    $title = $key . ' ' . JText::_('MODULE');
    debug::show($value, $title);
    $textOutput[$title] = $value;
    ?><br><?php
}

foreach($this->joomla_plugin as $key => $value) {
    debug::$callback = array($this,'joomla_plugin',$key);
    $title = $key . ' ' . JText::_('PLUGIN');
    debug::show($value, $title);
    $textOutput[$title] = $value;
    ?><br><?php
}
debug::$callback = array($this,'menu_item',null);
foreach($this->menu_item as $key => $value) {
    $title = $key . ' ' . JText::_('MENUITEM');
    debug::show($value, $title);
    $textOutput[$title] = $value;
    ?><br><?php
}
$debug=null;
foreach ($textOutput as $key => $value) {
    if ($debug) {
        $debug .= "\n\n".debug::getText($value, $key, 1);
    } else {
        $debug = debug::getText($value, $key, 1);
    }
}
?>
<textarea rows="10" cols="110"><?php echo $debug ?></textarea>