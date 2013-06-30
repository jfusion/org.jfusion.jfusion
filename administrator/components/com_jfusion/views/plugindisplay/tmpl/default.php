<?php
/**
 * @package JFusion
 * @subpackage Views
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 *
 * @var $this jfusionViewplugindisplay
 */

// no direct access
defined('_JEXEC') or die('Restricted access');
JFusionFunctionAdmin::displayDonate();

//load mootools
JHtml::_('behavior.framework', true);
$images = 'components/com_jfusion/images/';
$document = JFactory::getDocument();
$document->addScript('components/com_jfusion/js/File.Upload.js');
?>
<script type="text/javascript">
//<![CDATA[
function changesetting(fieldname, fieldvalue, jname){
    //change the image
    var url = '<?php echo JURI::root() . 'administrator/index.php'; ?>';
    var syncdata = 'jname=' + jname + '&field_name=' + fieldname + '&field_value=' + fieldvalue + '&task=changesettings&option=com_jfusion';
    new Request.JSON({ url: url, method: 'get',

        onRequest: function() {
            showSpinner(jname,fieldname);
        },
        onSuccess: function(JSONobject) {
	        JFusion.OnMessages(JSONobject.messages);

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
        }, onError: function(JSONobject) {
		    JFusion.OnError(JSONobject);
        }

    }).send(syncdata);
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
        new Request.JSON({url: url ,
            onSuccess: function(JSONobject) {
	            JFusion.OnMessages(JSONobject.messages);
                if(JSONobject.status === true) {
                    //add new row
                    addRow(JSONobject.new_jname, JSONobject.rowhtml);
                }
            }, onError: function(JSONobject) {
		        JFusion.OnError(JSONobject);
            }
        }).get({'option': 'com_jfusion', 'task': 'plugincopy', 'jname': jname, 'new_jname': newjname});
    }
}

function addRow(newjname, rowhtml){
    var div = new Element('div');
    div.set('html', '<table>' + '<tr id="'+newjname+'">'+rowhtml+'</tr>' + '</table>');
    div.getElement('tr').inject($("sort_table"),'top');
    div.dispose();
    initSortables();
}

function initSortables() {
    var url = '<?php echo JURI::root() . 'administrator/index.php'; ?>';

    /* allow for updates of row order */
    var ajaxsync = new Request.JSON({ url: url,
        method: 'get',
        onSuccess: function(JSONobject) {
	        JFusion.OnMessages(JSONobject.messages);
        }, onError: function(JSONobject) {
		    JFusion.OnError(JSONobject);
        }
    });

    new Sortables('sort_table',{
        /* set options */
        handle: 'div.dragHandles',

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
            var rowcount = 0;
            $$('#sort_table tr').each(function(tr) {
                $(tr.id).setAttribute('class', 'row' + rowcount);
                if (rowcount === 0) {
                    rowcount = 1;
                } else {
                    rowcount = 0;
                }
                sortorder = sortorder +  tr.id  + '|';
            });

            //update the database
            ajaxsync.send('option=com_jfusion&task=saveorder&tmpl=component&sort_order='+sortorder);
        }
    });
}

function deleteplugin(jname) {
    var confirmdelete = confirm('<?php echo JText::_('DELETE',true) . ' ' . JText::_('PLUGIN',true) . ' ' ;?>' + jname + "?");
    if(confirmdelete) {
        //update the database
        var url = '<?php echo JURI::root() . 'administrator/index.php'; ?>';

        // this code will send a data object via a GET request and alert the retrieved data.
        new Request.JSON({url: url ,
            onSuccess: function(JSONobject) {
	            JFusion.OnMessages(JSONobject.messages);
                if(JSONobject.status ===  true) {
                    var el = $(JSONobject.jname);
                    el.parentNode.removeChild(el);
                }
            }, onError: function(JSONobject) {
		        JFusion.OnError(JSONobject);
            }}).get({'option': 'com_jfusion',
                'task': 'uninstallplugin',
                'jname': jname,
                'tmpl': 'component'});
    }
}

window.addEvent('domready',function() {
	var installGIT = $('installGIT');
	installGIT.set('send',
        { onSuccess: function(JSONobject) {
            $('spinnerGIT').innerHTML = '';
            if (JSON.validate(JSONobject)) {
	            JFusion.OnMessages(JSONobject.messages);
                var response = JSON.decode(JSONobject);
                if (response.overwrite != 1 && response.status === true) {
                    addRow(response.jname, response.rowhtml);
                }
            } else {
	            JFusion.OnError(JSONobject);
            }
        }, data: {
            ajax: true
        }
        });
	installGIT.addEvent('submit', function(e) {
        new Event(e).stop();
        $('spinnerGIT').innerHTML = '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">';
        this.send('?ajax=true');
    });

    var installURL = $('installURL');
    installURL.set('send',
        { onSuccess: function(JSONobject) {
            $('spinnerURL').innerHTML = '';
            if (JSON.validate(JSONobject)) {
	            JFusion.OnMessages(JSONobject.messages);
                var response = JSON.decode(JSONobject);
                if (response.overwrite != 1 && response.status === true) {
                    addRow(response.jname, response.rowhtml);
                }
            } else {
	            JFusion.OnError(JSONobject);
            }
        }
        });
    installURL.addEvent('submit', function(e) {
        new Event(e).stop();
        $('spinnerURL').innerHTML = '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">';
        this.send('?ajax=true');
    });

    var installDIR = $('installDIR');
    installDIR.set('send',
        { onSuccess: function(JSONobject) {
            $('spinnerDIR').innerHTML = '';
            if (JSON.validate(JSONobject)) {
	            JFusion.OnMessages(JSONobject.messages);
                var response = JSON.decode(JSONobject);
                if (response.overwrite != 1 && response.status === true) {
                    addRow(response.jname, response.rowhtml);
                }
            } else {
	            JFusion.OnError(JSONobject);
            }
        }
        });
    installDIR.addEvent('submit', function(e) {
        new Event(e).stop();
        $('spinnerDIR').innerHTML = '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">';
        this.send('?ajax=true');
    });

    var installZIP = $('installZIP');
    installZIP.addEvent('submit', function(e) {
        new Event(e).stop();
        $('spinnerZIP').innerHTML = '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">';
        if (typeof FormData === "undefined") {
            this.submit();
        } else {
            var upload = new File.Upload({
                url:  '<?php echo JURI::root() . 'administrator/index.php'; ?>' ,
                data: {
                    option: 'com_jfusion',
                    task : 'installplugin',
                    installtype : 'upload',
                    ajax : 'true' } ,
                images: ['install_package'],
                onComplete : function (JSONobject) {
                    $('spinnerZIP').innerHTML = '';
                    if (JSON.validate(JSONobject)) {
	                    JFusion.OnMessages(JSONobject.messages);
                        var response = JSON.decode(JSONobject);
                        if (response.overwrite != 1 && response.status === true) {
                            addRow(response.jname, response.rowhtml);
                        }
                    } else {
	                    JFusion.OnError(JSONobject);
                    }
                }
            });
            upload.send();
        }
    });
    initSortables();
});
//]]>
</script>
<div class="jfusion">
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
	                <img src="<?php echo $images; ?>wizard_icon.png" border="0" alt="<?php echo JText::_('WIZARD');?>" style="margin-left: 10px;" /> = <?php echo JText::_('WIZARD');?>
	                <img src="<?php echo $images; ?>edit.png" border="0" alt="<?php echo JText::_('EDIT');?>" /> = <?php echo JText::_('EDIT');?>
	                <img src="<?php echo $images; ?>copy_icon.png" border="0" alt="<?php echo JText::_('COPY');?>" style="margin-left: 10px;" /> = <?php echo JText::_('COPY');?>
	                <img src="<?php echo $images; ?>delete_icon.png" border="0" alt="<?php echo JText::_('DELETE');?>" style="margin-left: 10px;" /> = <?php echo JText::_('DELETE');?>
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
	//display installer data ?>

	<form id="installGIT" method="post" action="./index.php" enctype="multipart/form-data">
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
	                                 * @var $plugin JXMLElement
	                                 */
	                                foreach ($this->VersionData as $plugin): ?>
	                                    <option value="<?php echo (string)$plugin->remotefile; ?>"><?php echo $plugin->name() . ' - ' . (string)$plugin->description; ?></option>
	                                    <?php endforeach; ?>
	                            </select>
	                            <input type="submit" name="button" id="submitter" />
	                            <div id="spinnerGIT">
	                            </div>
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
	                            <input type="submit" value="<?php echo JText::_('UPLOAD_FILE'); ?> &amp; <?php echo JText::_('INSTALL'); ?>"/>
	                            <div id="spinnerZIP">
	                            </div>
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
	                        <td width="120"><label for="install_directory">
	                            <?php echo JText::_('INSTALL_DIRECTORY'); ?> :
	                        </label>
	                        </td>
	                        <td>
	                            <input type="text" id="install_directory" name="install_directory" class="input_box" size="150" value="" />
	                            <input type="submit" value="<?php echo JText::_('INSTALL'); ?>"/>
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
	                            <input type="submit" value="<?php echo JText::_('INSTALL'); ?>"/>
	                            <div id="spinnerURL">
	                            </div>
	                        </td>
	                    </tr>
	                </table>
	            </td>
	        </tr>
	    </table>
	</form>
</div>

