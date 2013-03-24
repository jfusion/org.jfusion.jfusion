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
 *
 * @var $this jfusionViewimportexport
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
	if ( isset($this->list) ) {
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
		        foreach ($this->list->children() as $key => $val) {
			        $original_name = $val->getElementByPath('originalname')->data();
			        $name = $val->getElementByPath('name')->data();

			        if ($name && $original_name == $pluginname) {
				        $version = $val->getElementByPath('version')->data();
				        $description = $val->getElementByPath('description')->data();
				        $creator = $val->getElementByPath('creator')->data();
				        $date = $val->getElementByPath('date')->data();
				        $remotefile = $val->getElementByPath('remotefile')->data();

				        $version = $version?$version:JText::_('UNKNOWN');
				        $description = $description?$description:JText::_('JNONE');
				        $creator = $creator?$creator:JText::_('UNKNOWN');
				        $date = $date?$date:JText::_('UNKNOWN');
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
                                <input type=radio name="url" value="<?php echo base64_encode($remotefile); ?>">
                            </td>
                            <td>
						        <?php echo ucfirst($name); ?>
                                <a href="javascript:void(0);" id="plugin<?php echo $key;?>">[+]</a>
                                <div style="display:none;" id="xplugin<?php echo $key; ?>">
							        <?php echo JText::_('VERSION').': '.$version?>
                                    <br/>
	                                <?php echo JText::_('DATE').': '.$date?>
                                    <br/>
							        <?php echo JText::_('DESCRIPTION').': '.ucfirst($description)?>
                                    <br/>
							        <?php echo JText::_('CREATOR').': '.$creator; ?>
                                    <br/>
	                                <?php echo JText::_('URL').': <a href="'.$remotefile.'">'.$remotefile. '</a>';?>
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