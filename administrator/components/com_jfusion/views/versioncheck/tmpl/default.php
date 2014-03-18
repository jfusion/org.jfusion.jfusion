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
echo JFusionFunctionAdmin::getDonationBanner();
?>
<form method="post" action="index.php?option=com_jfusion" name="adminForm" id="adminForm">
	<input type="hidden" name="task" value="languages" />
</form>
<div class="jfusion">
	<table class="jfusionform" style="border-spacing:1px;">
	    <thead>
	        <tr>
	            <th class="title" align="left">
	                <?php echo JText::_('SERVER_SOFTWARE'); ?>
	            </th>
	            <th class="title" align="center">
	                <?php echo JText::_('YOUR_VERSION'); ?>
	            </th>
	            <th class="title" align="center">
	                <?php echo JText::_('MINIMUM_VERSION'); ?>
	            </th>
	        </tr>
	    </thead>
	    <tbody>
	    <?php
	    $row_count = 0;
	    foreach ($this->system as $software) {
	        ?>
	        <tr class="<?php echo $software->class.($row_count % 2);?>">
	            <td>
	                <?php echo $software->name; ?>
	            </td>
	            <td>
	                <?php echo $software->oldversion; ?>
	            </td>
	            <td>
	                <?php echo $software->version ;?>
	            </td>
	        </tr>
	        <?php
	        $row_count++;
	    }
	    ?>
	    </tbody>
	</table>
	<?php
	if ($this->server_compatible) {
	//output the good news
	    ?>
	<table style="background-color: #d9f9e2; width: 100%;">
	    <tr>
	        <td>
	            <img src="components/com_jfusion/images/check_good.png">
	        </td>
	        <td>
	            <h2>
	                <?php echo JText::_('SERVER_UP2DATE'); ?>
	            </h2>
	        </td>
	    </tr>
	</table>

	<?php
	} else {
	//output the bad news and automatic upgrade option
	    ?>
	<table style="background-color:#f9ded9;">
	    <tr>
	        <td width="50px">
	            <img src="components/com_jfusion/images/check_bad.png">
	        </td>
	        <td>
	            <h2>
	                <?php echo JText::_('SERVER_OUTDATED'); ?>
	            </h2>
	        </td>
	    </tr>
	</table>
	<?php
	}
	?>

	<br/><br/>
	<table class="jfusionform" style="border-spacing:1px;">
	    <thead>
	    <tr>
	        <th class="title" align="left">
	            <?php echo JText::_('JFUSION_SOFTWARE'); ?>
	        </th>
	        <th class="title" align="center">
			    <?php echo JText::_('DATE'); ?>
	        </th>
	        <th class="title" align="center">
	            <?php echo JText::_('YOUR_VERSION'); ?>
	        </th>
	        <th class="title" align="center">
			    <?php echo JText::_('DATE'); ?>
	        </th>
	        <th class="title" align="center">
	            <?php echo JText::_('CURRENT_VERSION'); ?>
	        </th>
	    </tr>
	    </thead>
	    <tbody>
	    <?php
	    foreach ($this->components as $component) {
	        ?>
	    <tr class="<?php echo $component->class.($row_count % 2);?>">
	        <td>
	            <?php echo JText::_('JFUSION') . ' ' . $component->name . ' ' . JText::_('VERSION') ;?>
	        </td>
	        <td>
			    <?php
		        if ($component->olddate && is_int($component->olddate)) {
			        $date = JFactory::getDate($component->olddate);
			        $date->setTimezone(new DateTimeZone(\JFusion\Framework::getJoomlaTimezone()));
			        echo $date->toSql(true);
		        } else {
			        echo JText::_('UNKNOWN');
		        }
			    ?>
	        </td>
	        <td>
	            <?php
	            echo $component->oldversion;
	            if ($component->oldrev) {
	                echo ' Rev ( ' . substr($component->oldrev, 0, 8) . '... )';
	            }
	            ?>
	        </td>
	        <td>
			    <?php
		        if ($component->date) {
			        $date = JFactory::getDate($component->date);
			        $date->setTimezone(new DateTimeZone(\JFusion\Framework::getJoomlaTimezone()));
			        echo $date->toSql(true);
		        } else {
			        echo JText::_('UNKNOWN');
		        }
			    ?>
	        </td>
	        <td>
	            <?php
	            echo $component->version;
	            if ($component->rev) {
	                echo ' Rev ( ' . substr($component->rev, 0, 8) . '... )';
	            }
	            ?>

	            <?php
	            if ($component->updateurl) {
	                ?>
	                <script type="text/javascript">
	                    <!--
	                    window.addEvent('domready',function() {
	                        $('<?php echo md5($component->name);?>').addEvent('click', function(e) {
		                        e.stop();

		                        JFusion.confirmInstall('<?php echo $component->updateurl; ?>');
	                        });
	                    });
	                    // -->
	                </script>

	                <a id="<?php echo md5($component->name); ?>" href="<?php echo $component->updateurl; ?>"><?php echo JText::_('UPDATE') ;?></a> / <a href="<?php echo $component->updateurl; ?>"><?php echo JText::_('DOWNLOAD') ;?></a>

	                <?php
	            }
	            ?>
	        </td>
	    </tr>
	        <?php
	        $row_count++;
	    }
	    ?>
	    </tbody>
	</table>
	<br/>

	<table class="jfusionform" style="border-spacing:1px;">
	    <thead>
	    <tr>
	        <th class="title" align="left">
	            <?php echo JText::_('JFUSION_PLUGINS'); ?>
	        </th>
	        <th class="title" align="center">
			    <?php echo JText::_('DATE'); ?>
	        </th>
	        <th class="title" align="center">
	            <?php echo JText::_('YOUR_VERSION'); ?>
	        </th>
	        <th class="title" align="center">
			    <?php echo JText::_('DATE'); ?>
	        </th>
	        <th class="title" align="center">
	            <?php echo JText::_('CURRENT_VERSION'); ?>
	        </th>
	    </tr>
	    </thead>
	    <tbody>
	    <?php
	    foreach ($this->jfusion_plugins as $jfusion_plugin) {
	        ?>
	    <tr class="<?php echo $jfusion_plugin->class.($row_count % 2);?>">
	        <td>
	            <?php echo $jfusion_plugin->name;?>
	        </td>
	        <td>
			    <?php
			    if ($jfusion_plugin->olddate) {
				    $date = JFactory::getDate($jfusion_plugin->olddate);
				    echo $date->toSql(true);
			    } else {
				    echo JText::_('UNKNOWN');
			    }
			    ?>
	        </td>
	        <td>
	            <?php
	            echo $jfusion_plugin->oldversion;
	            if ($jfusion_plugin->oldrev) {
	                echo ' Rev ( ' . substr($jfusion_plugin->oldrev, 0, 8) . '... )';
	            }
	            ?>
	        </td>
	        <td>
			    <?php
		        if ($jfusion_plugin->date) {
			        $date = JFactory::getDate($jfusion_plugin->date);
			        $date->setTimezone(new DateTimeZone(\JFusion\Framework::getJoomlaTimezone()));
			        echo $date->toSql(true);
		        } else {
			        echo JText::_('UNKNOWN');
		        }
			    ?>
	        </td>
	        <td>
	            <?php
	            echo $jfusion_plugin->version;
	            if ($jfusion_plugin->rev) {
	                echo ' Rev ( ' . substr($jfusion_plugin->rev, 0, 8) . '... )';
	            }
	            ?>
	            <?php
	            if ($jfusion_plugin->updateurl) {
	                ?>
	                <script type="text/javascript">
	                    <!--
	                    window.addEvent('domready',function() {
	                        $('<?php echo md5($jfusion_plugin->id); ?>').addEvent('click', function(e) {
		                        e.stop();

		                        JFusion.confirmInstallPlugin('<?php echo $jfusion_plugin->updateurl; ?>');
	                        });
	                    });
	                    // -->
	                </script>

	                <a id="<?php echo md5($jfusion_plugin->id); ?>" href="<?php echo $jfusion_plugin->updateurl; ?>"><?php echo JText::_('UPDATE') ;?></a> / <a href="<?php echo $jfusion_plugin->updateurl; ?>"><?php echo JText::_('DOWNLOAD') ;?></a>
	                <?php
	            }
	            ?>
	    </tr>
	        <?php
	        $row_count++;
	    }
	    ?>
	    </tbody>
	</table>
	<?php
	if ($this->up2date) {
	    //output the good news
	    ?>
		<table style="background-color:#d9f9e2;width:100%;">
		    <tr>
		        <td>
		            <img src="components/com_jfusion/images/check_good.png">
		        <td>
		            <h2>
		                <?php echo JText::_('JFUSION_UP2DATE'); ?>
		            </h2>
		        </td>
		    </tr>
		</table>
	<?php
	} else {
	    //output the bad news and automatic upgrade option
	    ?>
		<table style="background-color:#f9ded9;">
		    <tr>
		        <td width="50px">
		            <img src="components/com_jfusion/images/check_bad.png">
		        </td>
		        <td>
		            <h2>
		                <?php echo JText::_('JFUSION_OUTDATED'); ?>
		            </h2>
		        </td>
		    </tr>
		</table>
	<?php
	}
	?>
	<br/><br/><br/>
	<table style="background-color:#ffffce;width:100%;">
	    <tr>
	        <td width="50px">
	            <img src="components/com_jfusion/images/advanced.png" height="75" width="75">
	        </td>
	        <td>
	            <h3>
	                <?php echo JText::_('ADVANCED') . ' ' . JText::_('VERSION') . ' ' . JText::_('MANAGEMENT'); ?>
	            </h3>

	            <form enctype="multipart/form-data" action="index.php" method="post" id="install" name="install">
	                <input type="hidden" name="install_url" value="" />
	                <input type="hidden" name="type" value="" />
	                <input type="hidden" name="installtype" value="url" />
		            <input type="hidden" name="task" value="install.install" />
	                <input type="hidden" name="option" value="com_installer" />
	                <?php echo JHTML::_('form.token'); ?>
	                <strong>
	                    <?php echo JText::_('ADVANCED_WARNING'); ?>
	                </strong>
	                <br/>
	                <input id="build" type="button" value="<?php echo JText::_('INSTALL') . ' ' . JText::_('LATEST') . ' ' . JText::_('DEVELOPMENT') . ' ' . JText::_('RELEASE'); ?>"/>
	                <br/>
		            <label for="git_tree">Git Tree:</label>
	                <input id="git_tree" type="text" name="git_tree" size="40"/>
	                <input id="git" type="button" value="<?php echo JText::_('INSTALL') . ' ' . JText::_('SPECIFIC') . ' Git Tree'; ?>"/>
	                <br/>
	            </form>

	            <form id="installPLUGIN" method="post" action="index.php" enctype="multipart/form-data">
	                <input type="hidden" name="option" value="com_jfusion" />
	                <input type="hidden" name="task" value="installplugin" />
	                <input type="hidden" name="installtype" value="url" />
	                <input type="hidden" id="installPLUGIN_url" name="install_url" class="input_box" size="150" value="" />
	            </form>
	        </td>
	    </tr>
	</table>
</div>