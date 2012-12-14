<?php

/**
 * This is view file for loginchecker
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Loginchecker
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
?>

<script type="text/javascript">
    function doShowHide(item) {
        var obj=$("x"+item);
        var col=$(item);
        if (obj.style.display=="none") {
            obj.style.display="block";
            col.innerHTML="[-]";
        } else {
            obj.style.display="none";
            col.innerHTML="[+]";
        }
    }

    function doImport(jname) {
        var form = $('adminForm');
        form.action.value = 'import';
        form.jname.value = jname;
        form.encoding = 'multipart/form-data';
        submitbutton('plugineditor');
    }

    function doExport(jname) {
        var form = $('adminForm');
        form.action.value = 'export';
        form.jname.value = jname;
        submitbutton('plugineditor');
    }
</script>

<?php
jimport('joomla.html.pane');
$paneTabs = JPane::getInstance('tabs');
?>

<form method="post" action="index.php" name="adminForm" id="adminForm">
	<?php
	echo $paneTabs->startPane('jfusion_import_export');
	echo $paneTabs->startPanel( JText::_('IMPORT'), 'IMPORT' );
	?>
    <input type="hidden" name="option" value="com_jfusion" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="action" value="" />
    <table>
        <tr>
            <td>
				<?php echo JText::_('IMPORT_XML_FILE'); ?>
            </td>
            <td>
                <input name="file" size="60" type="file">
            </td>
        </tr>
        <tr>
            <td>
				<?php echo JText::_('DATABASE_TYPE'); ?>
            </td>
            <td>
                <input name="database_type" id="database_type" value="" class="text_area" size="20" type="text">
            </td>
        </tr>
        <tr>
            <td>
				<?php echo JText::_('DATABASE_HOST'); ?>
            </td>
            <td>
                <input name="database_host" id="database_host" value="" class="text_area" size="20" type="text">
            </td>
        </tr>
        <tr>
            <td>
				<?php echo JText::_('DATABASE_NAME'); ?>
            </td>
            <td>
                <input name="database_name" id="database_name" value="" class="text_area" size="20" type="text">
            </td>
        </tr>
        <tr>
            <td>
				<?php echo JText::_('DATABASE_USER'); ?>
            </td>
            <td>
                <input name="database_user" id="database_user" value="" class="text_area" size="20" type="text">
            </td>
        </tr>
        <tr>
            <td>
				<?php echo JText::_('DATABASE_PASSWORD'); ?>
            </td>
            <td>
                <input name="database_password" id="database_password" value="" class="text_area" size="20" type="text">
            </td>
        </tr>
        <tr>
            <td>
				<?php echo JText::_('DATABASE_PREFIX'); ?>
            </td>
            <td>
                <input name="database_prefix" id="database_prefix" value="" class="text_area" size="20" type="text">
            </td>
        </tr>
    </table>
    <br>
    <br>
	<?php
	if ( isset($this->xmlList->document) ) {
		echo JText::_('IMPORT_FROM_SERVER');
		?>
        <table>
            <tr>
                <td>
                    <input type=radio name="xmlname" value="" checked>
                </td>
                <td>
					<?php echo JText::_('JNONE'); ?>
                </td>
            </tr>
            <br/>

			<?php
	        $db = JFactory::getDBO();
	        $query = 'SELECT name , original_name from #__jfusion WHERE name = ' . $db->Quote($this->jname);
	        $db->setQuery($query);
	        $plugin = $db->loadObject();
	        if ($plugin) {
		        $pluginname = $plugin->original_name ? $plugin->original_name : $plugin->name;
		        /**
		         * @ignore
		         * @var $val JSimpleXMLElement
		         */
		        foreach ($this->xmlList->document->children() as $key => $val) {
			        $original_name = $val->attributes('original_name');
			        $name = $val->attributes('name');
			        if ($name && $original_name == $pluginname) {
				        $pluginVersion = $val->attributes('version')?$val->attributes('version'):JText::_('UNKNOWN');
				        $pluginDesc = $val->attributes('desc')?$val->attributes('desc'):JText::_('JNONE');
				        $pluginCreator = $val->attributes('creator')?$val->attributes('creator'):JText::_('UNKNOWN');
				        ?>

                        <script type="text/javascript">
                            window.addEvent('domready',function() {
                                $('plugin<?php echo $key;?>').addEvent('click', function(e) {
                                    doShowHide(this.id);
                                });
                            });
                        </script>
                        <tr>
                            <td style="vertical-align:top">
                                <input type=radio name="xmlname" value="<?php echo $name; ?>">
                            </td>
                            <td>
						        <?php echo ucfirst($name); ?>
                                <a href="javascript:void(0);" id="plugin<?php echo $key;?>">[+]</a>
                                <div style="display:none;" id="xplugin<?php echo $key; ?>">
							        <?php echo JText::_('VERSION').': '.$pluginVersion?>
                                    <br/>
							        <?php echo JText::_('DESCRIPTION').': '.ucfirst($pluginDesc)?>
                                    <br/>
							        <?php echo JText::_('CREATOR').': '.$pluginCreator; ?>
                                </div>
                            </td>
                        </tr>
				        <?php
			        }
		        }
	        }
			?>
        </table>
		<?php
	} else {
		echo JText::_('NO_CONECTION_TO_JFUSION_SERVER');
	}
	echo $paneTabs->endPanel();
	echo $paneTabs->startPanel( JText::_('EXPORT'), 'EXPORT' );
	?>
	<?php echo JText::_('EXPORT_DATABASE_INFO_DESC'); ?>
    <br>
    <br>
	<?php echo JText::_('EXPORT_DATABASE_INFO'); ?> <input name="dbinfo" type="checkbox"><br/>

    <input type="hidden" name="jname" value="<?php echo $this->jname; ?>"/>
	<?php
	echo $paneTabs->endPanel();
	echo $paneTabs->endPane();
	?>
</form>