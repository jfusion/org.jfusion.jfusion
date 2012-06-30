<?php

/**
 * This is view file for cpanel
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Cpanel
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
<table class="adminform">
	<tr>
		<td width="55%" valign="top">
			<div id="cpanel">
			    <div style="float:left;">
			            <div class="icon">
			                <a href="index.php?option=com_jfusion&task=plugineditor&jname=joomla_int" >
                                <img src="components/com_jfusion/images/joomla.png">
                                <span>
                                    <?php echo JText::_('JOOMLA_OPTIONS'); ?>
                                </span>
			                </a>
			            </div>
			    </div>
			    <div style="float:left;">
			            <div class="icon">
			                <a href="index.php?option=com_jfusion&task=plugindisplay" >
                                <img src="components/com_jfusion/images/controlpanel.png">
                                <span>
                                    <?php echo JText::_('CONFIGURE_PLUGINS'); ?>
                                </span>
			                </a>
			            </div>
			    </div>
			    <div style="float:left;">
			            <div class="icon">
			                <a href="index.php?option=com_jfusion&task=syncoptions" >
                                <img src="components/com_jfusion/images/syncmaster.png">
                                <span>
                                    <?php echo JText::_('NEW_USER_SYNC'); ?>
                                </span>
			                </a>
			            </div>
			    </div>
			    <div style="float:left;">
			            <div class="icon">
			                <a href="index.php?option=com_jfusion&task=synchistory" >
                                <img src="components/com_jfusion/images/synchistory_small.png">
                                <span>
                                    <?php echo JText::_('USER_SYNC_HISTORY'); ?>
                                </span>
			                </a>
			            </div>
			    </div>
			
			    <div style="float:left;">
			            <div class="icon">
			                <a href="index.php?option=com_jfusion&task=loginchecker" >
                                <img src="components/com_jfusion/images/login_checker_small.png">
                                <span>
                                    <?php echo JText::_('CP_LOGIN_CHECKER'); ?>
                                </span>
			                </a>
			            </div>
			    </div>
			    <div style="float:left;">
			            <div class="icon">
			                <a href="index.php?option=com_jfusion&task=configdump" >
                                <img src="components/com_jfusion/images/configdump.png">
                                <span>
                                    <?php echo JText::_('CONFIG_DUMP'); ?>
                                </span>
			                </a>
			            </div>
			    </div>
                <div style="float:left;">
                    <div class="icon">
                        <a href="index.php?option=com_jfusion&task=versioncheck" >
                            <img src="components/com_jfusion/images/versioncheck_small.png">
                            <span
                                ><?php echo JText::_('VERSION_CHECK'); ?>
                            </span>
                        </a>
                    </div>
                </div>
			    <div style="float:left;">
			            <div class="icon">
			                <a href="http://support.jfusion.org" >
                                <img src="components/com_jfusion/images/help.png">
                                <span>
                                    <?php echo JText::_('CP_HELP'); ?>
                                </span>
			                </a>
			            </div>
			    </div>
			    <div style="float:left;">
			            <div class="icon">
			                <a href="http://www.jfusion.org/docs" >
                                <img src="components/com_jfusion/images/documentation.png">
                                <span>
                                    <?php echo JText::_('DOCUMENTATION'); ?>
                                </span>
			                </a>
			            </div>
			    </div>    
			</div>
		</td>
		<td width="45%" valign="top">
			<?php
			//check to see if JFusion is enabled
			$plugin_user = JFusionFunctionAdmin::isPluginInstalled('jfusion', 'user', 1);
			$plugin_auth = JFusionFunctionAdmin::isPluginInstalled('jfusion', 'authentication', 1);
			if ($plugin_user && $plugin_auth) {
            ?>
			    <table style="background-color:#d9f9e2;width:100%;">
                    <tr>
                        <td width="50px">
                            <img src="components/com_jfusion/images/check_good.png">
                        </td>
			             
                        <td>
                            <h2>
			                     <?php echo JText::_('PLUGINS_ENABLED'); ?>
                            </h2>
                        </td>
                        <td>
                            <a href="index.php?option=com_jfusion&task=disableplugins" onCLick="return confirm('<?php echo JText::_('PLUGINS_DISABLE_CONFIRM'); ?>')">
                                <?php echo JText::_('PLUGINS_DISABLE'); ?>
                            </a>
                        </td>
	               </tr>
                </table>
            <?php
			} else {
            ?>
			    <table style="background-color:#f9ded9;width:100%;">
                    <tr>
                        <td width="50px">
                            <img src="components/com_jfusion/images/check_bad.png">
                        </td>
                        <td>
                            <h2>
                                <?php echo JText::_('PLUGINS_DISABLED'); ?>
                            </h2>
                        </td>
                        <td>
                            <a href="index.php?option=com_jfusion&task=enableplugins" onCLick="return confirm('<?php echo JText::_('PLUGINS_ENABLE_CONFIRM'); ?>')">
                                <?php echo JText::_('PLUGINS_ENABLE'); ?>
                            </a>
                        </td>
                    </tr>
                </table>
            <?php
			}
			jimport('joomla.html.pane');
            if ($this->JFusionCpanel) {
                $pane = & JPane::getInstance('tabs');
                echo $pane->startPane('pane');
                foreach ($this->JFusionCpanel->item as $item) {
                    echo $pane->startPanel($item->title[0]->data(), $item->title[0]->data());
                    echo $item->body[0]->data();
                    echo $pane->endPanel();
                }
            }
			?>
		</td>
	</tr>
</table>