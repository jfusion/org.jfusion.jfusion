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
JFusionFunctionAdmin::displayDonate();

//load mootools
JHTML::_('behavior.modal', 'a.modal');


$images = 'components/com_jfusion/images/';
?>
<script type="text/javascript">

function changesetting(fieldname, fieldvalue, jname){
	//change the image
	var url = '<?php echo JURI::root() . 'administrator/index.php'; ?>';
	var syncdata = 'jname=' + jname + '&field_name=' + fieldname + '&field_value=' + fieldvalue + '&task=changesettings&option=com_jfusion';

	var req = new Ajax(url, { method: 'get',  
          	
        onRequest: function() { 
        	showSpinner(jname,fieldname);    
        },
        onComplete: function(JSONobject) { 
        	var response = Json.evaluate(JSONobject);

            $('errormessages').innerHTML = response.errormessage;

        	//also update the check_encryption and dual_login fields if needed
        	if (fieldname == 'master' || fieldname == 'slave') {
           		if (fieldvalue == 1 && fieldname == 'master') {
                    //also untick other masters

           			var mtable=$('sortables');
           			var tablelength = mtable.rows.length - 1;
            		for (var i=1; i<=tablelength; i++) {
            			updateJavaScript(mtable.rows[i].id,"master",0);
            		}
            	}
        		updateJavaScript(jname,"check_encryption",fieldvalue);
    			updateJavaScript(jname,"dual_login",fieldvalue);
                //also ensure the opposite value is set for master or slave
                if (fieldvalue == 1) {
                	if (fieldname == 'master') {
                        updateJavaScript(jname,"slave",0);
                    } else {
                    	updateJavaScript(jname,"master",0);
                    }
                }
        	}
        	//update the image and link
        	updateJavaScript(jname,fieldname,fieldvalue);
        }

                    }); 
                    req.request(syncdata);
        

}

function updateJavaScript(plugin,field, value) {
	var tdElem = $(plugin + '_' + field);
	var newValue = 0;
	if (value == 1) {
		tdElem.firstChild.firstChild.src = "components/com_jfusion/images/tick.png";
	} else {
		tdElem.firstChild.firstChild.src = "components/com_jfusion/images/cross.png";
		newValue = 1;
	} 
	tdElem.firstChild.href = "javascript: changesetting('"+field+"','"+newValue+"','"+plugin+"')";
	
}

function showSpinner(jname,fieldname) {
	var tdElem = $(jname + '_' + fieldname);
	tdElem.firstChild.firstChild.src = "components/com_jfusion/images/spinner.gif";
}

function copyplugin(jname) {
	var newjname = prompt('Please type in the name to use for the copied plugin. This name must not already be in use.', '');
    if(newjname) {
        var url = '<?php echo JURI::root() . 'administrator/index.php'; ?>';

     // this code will send a data object via a GET request and alert the retrieved data.
        var jsonRequest = new Ajax(url, { method: 'get',
            onComplete: function(JSONobject) {
		        var results = Json.evaluate(JSONobject);
		
		        if(results.status === true) {
		            //add new row
		            addRow(results.newjname, results.rowhtml);
		        }
		        alert(results.message);
          	} 
        });
        jsonRequest.request('option=com_jfusion&task=plugincopy&jname=' + jname + '&new_jname=' + newjname);
    }
}

function addRow(newjname, rowhtml) {
    var div = new Element('div');
    div.setHTML('<table>' + '<tr id="'+newjname+'">'+rowhtml+'</tr>' + '</table>');
    div.getElement('tr').inject($("sort_table"),'top');
    initSortables();
}

function initSortables() {
    var url = '<?php echo JURI::root() . 'administrator/index.php'; ?>';

    /* allow for updates of row order */
    
	var dragHandles = document.getElementsByName('handle');

    var ajaxsync = new Ajax(url, {
	    method: 'get',
        onComplete: function(JSONobject) {
            var response = Json.evaluate(JSONobject);
            if (response.status === false) {
                alert(response.message);
            }
        }
	});
	
    new Sortables('sort_table',{
		/* set options */
      	handles: dragHandles, 

		
		/* initialization stuff here */
		initialize: function() {
			// do nothing yet
		},
		/* once an item is selected */
		onStart: function(el) {
			//a little fancy work to hide the clone which mootools 1.1 doesn't seem to give the option for
			var checkme = $$('div tr#' + el.id);
			if (checkme[1]) {
				checkme[1].setStyle('display','none');
			}
		},

		onComplete: function(el) {
			//build a string of the order
			var sortorder = '';
			var rowcount = '0';
			$$('#sort_table tr').each(function(tr) {
                $(tr.id).setAttribute('class', 'row' + rowcount);
				if (rowcount == '0') {
					rowcount = '1';
				} else {
					rowcount = '0';
				}
                sortorder = sortorder +  tr.id  + '|';
		    });

			//update the database
            ajaxsync.request('option=com_jfusion&task=saveorder&tmpl=component&sort_order='+sortorder);

	   }
    });
}
  
function deleteplugin(jname) {
	var confirmdelete = confirm('<?php echo JText::_('DELETE',true) . ' ' . JText::_('PLUGIN',true) . ' ' ;?>' + jname + "?");
    if(confirmdelete) {
    	//update the database
        var url = '<?php echo JURI::root() . 'administrator/index.php'; ?>';
	
        // this code will send a data object via a GET request and alert the retrieved data.
        var jsonRequest = new Ajax(url, { method: 'get',
            onComplete: function(JSONobject) {
        		var results = Json.evaluate(JSONobject);

            	if(results.status === true) {
	            	var el = $(results.jname);
	               	el.parentNode.removeChild(el);
            	}
            	alert(results.message);
			}
		});
    	jsonRequest.request('option=com_jfusion&task=uninstallplugin&tmpl=component&jname='+jname);
    }
}

window.addEvent('domready',function() {
	$('installSVN').addEvent('submit', function(e) {
		new Event(e).stop();

		var spinner = $('spinnerSVN');
	    spinner.innerHTML = '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">';
		this.send({ method: 'post',
			onComplete: function(JSONobject) {
			var response = Json.evaluate(JSONobject);
			    
		    if (response.overwrite != 1 && response.status === true) {
		    	addRow(response.jname, response.rowhtml);
		    }
		    var spinner = $('spinnerSVN');
		    spinner.innerHTML = '';
		    alert(response.message);
            }, data: this.toQueryString()+'&ajax=true'
		});
	});

	$('installURL').addEvent('submit', function(e) {
		new Event(e).stop();
		var spinner = $('spinnerURL');
	    spinner.innerHTML = '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">';			
            this.send({method : 'post',
                onComplete: function(JSONobject) {
	            var response = Json.evaluate(JSONobject);                              
	
				if (response.overwrite != 1 && response.status === true) {
				   	addRow(response.jname, response.rowhtml);
				}
				var spinner = $('spinnerURL');
				spinner.innerHTML = '';
				alert(response.message);
			}, data: this.toQueryString()+'&ajax=true'
		});
	});

	$('installDIR').addEvent('submit', function(e) {
		new Event(e).stop();
		var spinner = $('spinnerDIR');
	    spinner.innerHTML = '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">';
            this.send({method : 'post',
                onComplete: function(JSONobject) {
	            var response = Json.evaluate(JSONobject);
	
			    if (response.overwrite != 1 && response.status === true) {
			    	addRow(response.jname, response.rowhtml);
			    }
			    var spinner = $('spinnerDIR');
			    spinner.innerHTML = '';
			    alert(response.message);
            }, data: this.toQueryString()+'&ajax=true'
		});
	});

	$('installZIP').addEvent('submit', function(e) {
		new Event(e).stop();
		var spinner = $('installZIP');
	    spinner.innerHTML = '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">';			

		this.submit();
	});

	initSortables();
});
</script>

<div id='errormessages'><?php echo $this->errormessage; ?></div>
<form method="post" action="index.php" name="adminForm">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="saveorder" />
	
	<table class="adminlist" style="border-spacing:1px;" id="sortables">
	    <thead>
	        <tr>
	            <th class="title" width="20px;">
	            </th>
	            <th class="title" align="left">
	            	<?php echo JText::_('NAME');?>
	            </th>
	            <th class="title" width="75px" align="center">
	            	<?php echo JText::_('ACTIONS');?>
	            </th>
	            <th class="title" align="center">
	            	<?php echo JText::_('DESCRIPTION');?>
	            </th>
	            <th class="title" width="40px" align="center">
	            	<?php echo JText::_('MASTER'); ?>
	            </th>
	            <th class="title" width="40px" align="center">
	            	<?php echo JText::_('SLAVE'); ?>
	            </th>
	            <th class="title" width="40px" align="center">
	            	<?php echo JText::_('CHECK_ENCRYPTION'); ?>
	            </th>
	            <th class="title" width="40px" align="center">
	            	<?php echo JText::_('DUAL_LOGIN');?>
	            </th>
	            <th class="title" align="center">
	            	<?php echo JText::_('STATUS');?>
	            </th>
	            <th class="title" align="center">
	            	<?php echo JText::_('USERS');?>
	            </th>
	            <th class="title" align="center">
	            	<?php echo JText::_('REGISTRATION');?>
	            </th>
	            <th class="title" align="center">
	            	<?php echo JText::_('DEFAULT_USERGROUP');?>
	            </th>
	        </tr>
	    </thead>
	    <tbody id="sort_table">
		<?php
		//loop through the JFusion plugins
        $row_count = 0;
		foreach($this->plugins as $record) {
		?>
			<tr id="<?php echo $record->name; ?>" class="row<? echo ($row_count % 2); ?>">
				<?php echo $this->generateRowHTML($record)?>
		    </tr>
		<?php
            $row_count++;
        } ?>
		</tbody>
	</table>
	<br />
	
	<table style="width:100%;">
	    <tr>
	        <td style="text-align: left;">
	            <img src="<?php echo $images; ?>edit.png" border="0" alt="<?php echo JText::_('EDIT');?>" /> = <?php echo JText::_('EDIT');?>
	            <img src="<?php echo $images; ?>copy_icon.png" border="0" alt="<?php echo JText::_('COPY');?>" style="margin-left: 10px;" /> = <?php echo JText::_('COPY');?>
	            <img src="<?php echo $images; ?>delete_icon.png" border="0" alt="<?php echo JText::_('DELETE');?>" style="margin-left: 10px;" /> = <?php echo JText::_('DELETE');?>
	            <img src="<?php echo $images; ?>wizard_icon.png" border="0" alt="<?php echo JText::_('WIZARD');?>" style="margin-left: 10px;" /> = <?php echo JText::_('WIZARD');?>
	            <img src="<?php echo $images; ?>info.png" border="0" alt="<?php echo JText::_('INFO');?>" style="margin-left: 10px;" /> = <?php echo JText::_('INFO');?>
	        </td>
	        <td style="text-align: right;">
	            <img src="<?php echo $images; ?>tick.png" border="0" alt="<?php echo JText::_('ENABLED'); ?>" /> = <?php echo JText::_('ENABLED'); ?>
	            <img src="<?php echo $images; ?>cross.png" border="0" alt="<?php echo JText::_('DISABLED');?>" style="margin-left: 10px;" /> = <?php echo JText::_('DISABLED');?>
	            <img src="<?php echo $images; ?>cross_dim.png" border="0" alt="<?php echo JText::_('CONFIG_FIRST');?>" style="margin-left: 10px;" /> = <?php echo JText::_('CONFIG_FIRST');?>
	        </td>
	    </tr>
	</table>

</form>
<br/><br/>

<?php echo JText::_('PLUGIN_INSTALL_INSTR'); ?><br/>

<?php if($this->VersionData) {
//display installer data
$jfusion_plugins = $this->VersionData->getElementByPath('plugins')->children(); ?>

<form id="installSVN" method="post" action="index.php" enctype="multipart/form-data">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="installplugin" />
	<input type="hidden" name="installtype" value="url" />

	<table class="adminform">
		<tr>
			<td>
	    		<img src="components/com_jfusion/images/folder_url.png">
			</td>
			<td>
			    <table>
			    	<tr>
			    		<th colspan="2">
			    			<?php echo JText::_('INSTALL') . ' ' . JText::_('FROM') . ' JFusion ' .JText::_('SERVER'); ?>
			    		</th>
			    	</tr>
			    	<tr>
			    		<td width="120">
			    			<label for="install_url2">
			    				<?php echo JText::_('PLUGIN') . ' ' . JText::_('NAME'); ?> :
			    			</label>
			    		</td>
			    		<td>
			        		<select name="install_url" id="install_url2">
			    				<?php
                                /**
                                 * @ignore
                                 * @var $plugin JSimpleXMLElement
                                 */
                                foreach ($jfusion_plugins as $plugin): ?>
			    				<option value="<?php echo $plugin->getElementByPath('remotefile')->data() ?>"><?php echo $plugin->name() . ' - ' . $plugin->getElementByPath('description')->data(); ?></option>
			    				<?php endforeach; ?>
			            	</select>
			    			<input type="submit" name="button" id="submitter" /><div id="spinnerSVN"></div>
			    		</td>
			    	</tr>
		    	</table>
			</td>
		</tr>
	</table>
</form>
<?php }  else { ?>
    <table class="adminform">
        <tr>
            <td>
                <img src="components/com_jfusion/images/folder_url.png">
            </td>
            <td>
                <table>
                    <tr>
                        <th colspan="2">
                            <?php echo JText::_('INSTALL') . ' ' . JText::_('FROM') . ' JFusion ' .JText::_('SERVER'); ?>
                        </th>
                    </tr>
                    <tr>
                        <td width="120">
                            <label for="install_url2">
                                <?php echo JText::_('PLUGIN') . ' ' . JText::_('NAME'); ?> :
                            </label>
                        </td>
                        <td>
                            <?php echo JText::_('ERROR_LOADING_REMOTE_PLUGIN_DATA_FROM_JFUSION_SERVER'); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
<?php } ?>

<form id="installZIP" method="post" action="index.php" enctype="multipart/form-data">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="installplugin" />
	<input type="hidden" name="installtype" value="upload" />
		<table class="adminform">
			<tr>
				<td>
		    		<img src="components/com_jfusion/images/folder_zip.png">
				</td>
				<td>
		    		<table>
		    			<tr>
		    				<th colspan="2">
		    					<?php echo JText::_('UPLOAD_PACKAGE'); ?>
		    				</th>
		    			</tr>
		    			<tr>
		    				<td width="120">
		    					<label for="install_package">
		    						<?php echo JText::_('PACKAGE_FILE'); ?> :
		    					</label>
		    				</td>
		   					 <td>
		    					<input class="input_box" id="install_package" name="install_package" type="file" size="57" />
		    					<input type="button" value="<?php echo JText::_('UPLOAD_FILE'); ?> &amp; <?php echo JText::_('INSTALL'); ?>"  onclick="$('installZIP').submit();"/>
		    					<div id="spinnerZIP"></div>
		    				</td>
		    			</tr>
		    		</table>
				</td>
			</tr>
		</table>
	</form>

<form id="installDIR" method="post" action="index.php" enctype="multipart/form-data">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="installplugin" />
	<input type="hidden" name="installtype" value="folder" />
	<table class="adminform">
		<tr>
			<td>
		    	<img src="components/com_jfusion/images/folder_dir.png">
			</td>
			<td>
		    	<table>
		    		<tr>
		    			<th colspan="2">
		    				<?php echo JText::_('INSTALL_FROM_DIRECTORY'); ?>
		    			</th>
		    		</tr>
		    		<tr>
		    			<td width="120">
		    				<label for="install_directory">
			    				<?php echo JText::_('INSTALL_DIRECTORY'); ?> :
			    			</label>
		    			</td>
		    			<td>
                            <input type="text" id="install_directory" name="install_directory" class="input_box" size="150" value="<?php echo JPATH_COMPONENT_ADMINISTRATOR.DS.'packages'.DS.'jfusion_xxxxxxx.zip'; ?>" />
						    <input type="submit"  value="<?php echo JText::_('INSTALL'); ?>"/>
						    <div id="spinnerDIR">
						    </div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</form>

<form id="installURL" method="post" action="index.php" enctype="multipart/form-data">
	<input type="hidden" name="option" value="com_jfusion" />
	<input type="hidden" name="task" value="installplugin" />
	<input type="hidden" name="installtype" value="url" />
	<table class="adminform">
		<tr>
		<td>
		    <img src="components/com_jfusion/images/folder_url.png">
		</td>
		<td>
		    <table>
			    <tr>
				    <th colspan="2">
				    	<?php echo JText::_('INSTALL_FROM_URL'); ?>
				    </th>
			    </tr>
			    <tr>
				    <td width="120">
					    <label for="install_url">
						    <?php echo JText::_('INSTALL_URL'); ?> :
					    </label>
				    </td>
				    <td>
					    <input type="text" id="install_url" name="install_url" class="input_box" size="150" value="http://" />
					    <input type="submit"   value="<?php echo JText::_('INSTALL'); ?>"/>
					    <div id="spinnerURL">
					    </div>
				    </td>
			    </tr>
		    </table>
		</td>
		</tr>
	</table>
</form>