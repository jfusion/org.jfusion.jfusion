<?php

/**
 * This is view file for logincheckerresult
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    ViewsAdmin
 * @subpackage Logincheckerresults
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
// no direct access
defined('_JEXEC') or die('Restricted access');
//please support JFusion
JFusionFunctionAdmin::displayDonate();

global $jfusionDebug;
/**
 *     Load debug library
 */
require_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'models' . DS . 'model.debug.php';

/**
 * Output information about the server for future support queries
 */
?>
<table>
    <tr>
        <td width="100px">
            <img src="components/com_jfusion/images/jfusion_large.png" height="75px" width="75px">
        </td>
        <td width="100px">
            <img src="components/com_jfusion/images/login_checker2.png" height="75px" width="75px">
        </td>
        <td>
            <h2>
                <?php echo JText::_('LOGIN_CHECKER_RESULT');?>
            </h2>
        </td>
    </tr>
</table>

<div style="border:0 none ; margin:0; padding:0 5px; width: 800px; float: left;">
	<form method="post" action="index.php" name="adminForm" id="adminForm">
		<input type="hidden" name="option" value="com_jfusion" />
		<input type="hidden" name="show_unsensored" value="<?php echo $options['show_unsensored']; ?>" />
		<input type="hidden" name="task" value="logoutcheckerresult" />
		<?php if (!empty($jfusionDebug['joomlaid'])) : ?>
		<input type="hidden" name="joomlaid" value="<?php echo $jfusionDebug['joomlaid']; ?>"/>
		<?php endif; ?>
		
		<?php
		//prevent current jooomla session from being destroyed
		global $JFusionActivePlugin, $JFusionLoginCheckActive;
		$JFusionActivePlugin = 'joomla_int';
		$JFusionLoginCheckActive = true;
		
		//output the information to the user
		debug::show($this->server_info, JText::_('SERVER') . ' ' . JText::_('CONFIGURATION'), 1);
		$textOutput[JText::_('SERVER') . ' ' . JText::_('CONFIGURATION')] = $this->server_info;
		?><br/><?php
		
		//output the information to the user
		debug::show($this->jfusion_version, JText::_('JFUSION') . ' ' . JText::_('VERSIONS'), 1);
		$textOutput[JText::_('JFUSION') . ' ' . JText::_('VERSIONS')] = $this->jfusion_version;
		?><br/><?php
		foreach ($this->plugins as $plugin) {
		    debug::show($plugin, JText::_('JFUSION') . ' ' . $plugin->name . ' ' . JText::_('PLUGIN'), 1);
		    $textOutput[JText::_('JFUSION') . ' ' . $plugin->name . ' ' . JText::_('PLUGIN')] = $plugin;
		}
		?><br/><br/><?php
	
		//output from auth plugin results
		debug::show($this->auth_userinfo, JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN'), 1);
		$textOutput[JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN')] = $this->auth_userinfo;
		
		//check to see if plugins returned true
	    if ($this->response->status === JAUTHENTICATE_STATUS_SUCCESS) { ?>
	        <table style="background-color:#d9f9e2;width:100%;">
	            <tr style="height:30px">
	                <td width="50px">
		               <img src="components/com_jfusion/images/check_good.png" height="20px" width="20px">
	                </td>
		           <td>
			           <font size="2">
			               <b>
			                   <?php echo JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('SUCCESS'); ?>
			               </b>
		                </font>
	                </td>
	            </tr>
	        </table>
		    <br/>
	        <br/>
		    <?php
			$textOutput[JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('SUCCESS')] = "";    
		    if (!empty($this->response->debug)) {
		        debug::show($this->response->debug, JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('DEBUG'), 1);
		        $textOutput[JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('DEBUG')] = $this->response->debug; 
		    }
	
		    foreach ($this->auth_results as $name => $auth_result) { ?>
	            <br/><br/>
	            <table style="width:100%">
	                <tr style="height:30px">
	                    <td ALIGN="center" colspan="2" bgcolor="#D6F2FF">
	                        <b>
	                            <?php echo $name . ' ' . JText::_('USER') . ' ' . JText::_('PLUGIN') ?>
	                        </b>
	                    </td>
	                </tr>
		            <?php
		            if ($auth_result->result == true) { ?>
		                <tr style="height:30px">
	                        <td width="50px" style="background-color:#d9f9e2;">
	                            <img src="components/com_jfusion/images/check_good.png" height="20px" width="20px">
	                        </td>
		                   <td style="background-color:#d9f9e2;">
		                       <font size="2">
		                           <b>
		                               <?php echo JText::_('USER') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('SUCCESS'); ?>
		                           </b>
	                            </font>
	                        </td>
	                    </tr>
		                <?php
		    			$textOutput[JText::_('USER') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('SUCCESS')] = "";            
		            } else { ?>
		                <tr style="height:30px">
		                   <td width="50px" style="background-color:#f9ded9;">
		                       <img src="components/com_jfusion/images/check_bad.png" height="20px" width="20px">
	                       </td>
		                   <td style="background-color:#f9ded9;">
			                   <font size="2">
				                   <b>
				                       <?php echo JText::_('USER') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('ERROR'); ?>
				                   </b>
			                   </font>
		                   </td>
	                    </tr>
		                <?php
		                $textOutput[JText::_('USER') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('ERROR')] = "";
		            }
		       ?></table><?php
	            if (!empty($auth_result->debug)) {
	            	?> <br/><br/> <?php
	                debug::show($auth_result->debug, JText::_('USER') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('DEBUG'), 1);
	                $textOutput[JText::_('USER') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('DEBUG')] = $auth_result->debug;
	            }
	        }
		    JToolBarHelper::custom( 'logoutcheckerresult', 'forward.png', 'forward.png', JText::_('Check Logout'), false, false);
		} else { ?>
		    <table style="background-color:#f9ded9;width:100%;">
		       <tr style="height:30px">
		           <td width="50px">
		               <img src="components/com_jfusion/images/check_bad.png" height="20px" width="20px">
	                </td>
			       <td>
			           <font size="2">
			               <b>
			                   <?php echo JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('ERROR'); ?>
		                   </b>
		                </font>
		            </td>
	            </tr>
	        </table>
		    <?php
			$textOutput[JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('ERROR')] = "";    
		    if (!empty($this->response->debug)) {
		    	?><br/><br/><?php
		        debug::show($this->response->debug, JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('DEBUG'), 1);
				$textOutput[JText::_('AUTHENTICATION') . ' ' . JText::_('PLUGIN') . ' ' . JText::_('DEBUG')] = $this->response->debug;        
		    }
		}
		
		//create a link to test out the logout function
		?>
	</form>
	<br/><br/>
	<?php
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
</div>