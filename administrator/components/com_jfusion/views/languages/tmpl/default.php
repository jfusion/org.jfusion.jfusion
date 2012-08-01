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
            <?php echo JText::_('LATEST_VERSION'); ?>
        </th>
    </tr>
    </thead>
    <tbody>

     <?php $row_count = 0;
     foreach ($this->lang_repo as $lang => $data) { ?>

     <tr id="<?php echo $lang; ?>" class="row<? echo $row_count; ?>">
        <?php echo '<td>'.$lang.'</td><td>'.$data['description'].'</td><td></td><td></td>'?>
    </tr>
<?php } ?>
        </tbody>


    </table>
    <br/><br/>
