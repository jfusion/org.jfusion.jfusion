//<!--
function submitbutton(pressbutton) {
    if (pressbutton == 'applyconfig') {
        $('adminForm').action.value = 'apply';
        submitform('saveconfig');
    } else {
        submitform(pressbutton);
    }
}

function submitbutton3(pressbutton) {
    var form = $('adminForm');
    // do field validation
    if (form.install_directory.value === "") {
        alert("<?php echo JText::_( 'NO_DIRECTORY'); ?>");
    } else {
        form.installtype.value = 'folder';
        form.submit();
    }
}

function submitbutton4(pressbutton) {
    var form = $('adminForm');

    // do field validation
    if (form.install_url.value === "" || form.install_url.value == "http://") {
        alert("<?php echo JText::_( 'NO_URL'); ?>");
    } else {
        form.installtype.value = 'url';
        form.submit();
    }
}


function setCheckedValue(radioObj, newValue) {
	if (radioObj) {
        var radioLength = radioObj.length;
        if (radioLength === undefined) {
            radioObj.checked = (radioObj.value == newValue.toString());
        } else {
            for (var i= 0; i < radioLength; i++) {
                radioObj[i].checked = radioObj[i].value == newValue.toString();
            }
        }
    }
}

function setSort(col){
	var form = $('adminForm');
	var prevCol = form.log_sort.value;
	if (prevCol == col) {
        var direction = form.log_dir.value;
        if (direction == '1') {
            form.log_dir.value = '-1';
        } else {
            form.log_dir.value = '1';
        }
    } else {
        form.log_dir.value = '1';
    }
	form.log_sort.value=col;
	form.submit();
}

function getCheckedValue(radioObj) {
    var r = "";
	if(radioObj) {
        var radioLength = radioObj.length;
        if(radioLength === undefined) {
            if(radioObj.checked) {
                r = radioObj.value;
            }
        } else {
            for(var i = 0; i < radioLength; i++) {
                if(radioObj[i].checked) {
                    r = radioObj[i].value;
                }
            }
        }
    }
	return r;
}

function doImport(jname) {
    var form = $('adminForm');
    form.action.value='import';
    form.jname.value=jname;
    form.encoding='multipart/form-data';
    submitbutton('plugineditor');
}

function doExport(jname) {
    var form = $('adminForm');
    form.action.value='export';
    form.jname.value=jname;
    submitbutton('plugineditor');
}

function module(action) {
    var form = $('adminForm');
    form.customcommand.value = action;
    form.action.value = 'apply';
    submitform('saveconfig');
}

function usergroupSelect(option)
{
    document.getElementById("JFusionUsergroup").innerHTML = myArray[option];
}

function multiUsergroupSelect(option)
{
    usergroupSelect(option);

    var addgroupset = document.getElementById('addgroupset');
    if (option== 1) {
        addgroupset.style.display = 'block';
    } else {
        addgroupset.style.display = 'none';
    }
}

if (typeof Joomla != 'undefined') {
	Joomla.submitbutton = function(pressbutton) {
        if (pressbutton == 'applyconfig') {
            $('adminForm').action.value = 'apply';
            submitform('saveconfig');
        } else {
            submitform(pressbutton);
        }
	};
	
	Joomla.getCheckedValue = function(radioObj) {
		return getCheckedValue(radioObj);
	};
	
	
	Joomla.submitbutton3 = function(pressbutton){
        return submitbutton3(pressbutton);
	};
	
	Joomla.submitbutton4 = function(pressbutton)
	{
        return submitbutton4(pressbutton);
	};
	
	Joomla.setCheckedValue= function(radioObj, newValue) {
		return setCheckedValue(radioObj, newValue);
	};
	
	Joomla.setSort = function(col){
		return setSort(col);
	};
}
//-->