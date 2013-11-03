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

jimport('joomla.html.pane');

?>
<div class="jfusion">
	<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
		<?php
		echo JHtml::_('tabs.start','jfusion_import_export', array('startOffset'=>2));
		echo JHtml::_('tabs.panel',JText::_('IMPORT'), 'IMPORT');
		?>
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
		            <label for="database_type"><?php echo JText::_('DATABASE_TYPE'); ?></label>
	            </td>
	            <td>
	                <input name="database_type" id="database_type" value="" class="text_area" size="20" type="text">
	            </td>
	        </tr>
	        <tr>
	            <td>
		            <label for="database_host"><?php echo JText::_('DATABASE_HOST'); ?></label>
	            </td>
	            <td>
	                <input name="database_host" id="database_host" value="" class="text_area" size="20" type="text">
	            </td>
	        </tr>
	        <tr>
	            <td>
		            <label for="database_name"><?php echo JText::_('DATABASE_NAME'); ?></label>
	            </td>
	            <td>
	                <input name="database_name" id="database_name" value="" class="text_area" size="20" type="text">
	            </td>
	        </tr>
	        <tr>
	            <td>
		            <label for="database_user"><?php echo JText::_('DATABASE_USER'); ?></label>
	            </td>
	            <td>
	                <input name="database_user" id="database_user" value="" class="text_area" size="20" type="text">
	            </td>
	        </tr>
	        <tr>
	            <td>
		            <label for="database_password"><?php echo JText::_('DATABASE_PASSWORD'); ?></label>
	            </td>
	            <td>
	                <input name="database_password" id="database_password" value="" class="text_area" size="20" type="text">
	            </td>
	        </tr>
	        <tr>
	            <td>
		            <label for="database_prefix"><?php echo JText::_('DATABASE_PREFIX'); ?></label>
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
	                    <input type=radio id="xmlname" name="xmlname" value="" checked>
	                </td>
	                <td>
		                <label for="xmlname"><?php echo JText::_('JNONE'); ?></label>
	                </td>
	            </tr>
	            <br/>

				<?php
		        $db = JFactory::getDBO();

				$query = $db->getQuery(true)
					->select('name , original_name')
					->from('#__jfusion')
					->where('name = ' . $db->Quote($this->jname));

		        $db->setQuery($query);
		        $plugin = $db->loadObject();
		        if ($plugin) {
			        $pluginname = $plugin->original_name ? $plugin->original_name : $plugin->name;
			        /**
			         * @ignore
			         * @var $val SimpleXMLElement
			         */
			        foreach ($this->list->children() as $key => $val) {
				        $original_name = (string)$val->originalname;
				        $name = (string)$val->name;

				        if ($name && $original_name == $pluginname) {
					        $version = (string)$val->version;
					        $description = (string)$val->description;
					        $creator = (string)$val->creator;
					        $date = (string)$val->date;
					        $remotefile = (string)$val->remotefile;

					        $version = $version?$version:JText::_('UNKNOWN');
					        $description = $description?$description:JText::_('JNONE');
					        $creator = $creator?$creator:JText::_('UNKNOWN');
					        $date = $date?$date:JText::_('UNKNOWN');
					        ?>

					        <script type="text/javascript">
	                            window.addEvent('domready',function() {
	                                $('plugin<?php echo $name;?>').addEvent('click', function() {
		                                JFusion.doShowHide(this.get('id'));
	                                });
	                            });
	                        </script>
	                        <tr>
	                            <td style="vertical-align: top;">
	                                <input type=radio name="url" id="importexport<?php echo $name; ?>" value="<?php echo base64_encode($remotefile); ?>">
	                            </td>
	                            <td>
		                            <label for="importexport<?php echo $name; ?>"><?php echo ucfirst($name); ?></label>
	                                <a href="javascript:void(0);" id="plugin<?php echo $name; ?>">[+]</a>
	                                <div style="display:none;" id="xplugin<?php echo $name; ?>">
								        <?php echo JText::_('VERSION') . ': ' . $version; ?>
	                                    <br/>
		                                <?php echo JText::_('DATE') . ': ' . $date; ?>
	                                    <br/>
								        <?php echo JText::_('DESCRIPTION') . ': ' . ucfirst($description); ?>
	                                    <br/>
								        <?php echo JText::_('CREATOR') . ': ' . $creator; ?>
	                                    <br/>
		                                <?php echo JText::_('URL') . ': <a href="' . $remotefile . '">' . $remotefile . '</a>'; ?>
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
		echo JHtml::_('tabs.panel',JText::_('EXPORT'), 'EXPORT');
		?>
		<?php echo JText::_('EXPORT_DATABASE_INFO_DESC'); ?>
	    <br>
	    <br>
		<label for="dbinfo"><?php echo JText::_('EXPORT_DATABASE_INFO'); ?></label> <input id="dbinfo" name="dbinfo" type="checkbox"><br/>

	    <input type="hidden" name="jname" value="<?php echo $this->jname; ?>"/>
		<?php
		echo JHtml::_('tabs.end');
		?>
	</form>
</div>