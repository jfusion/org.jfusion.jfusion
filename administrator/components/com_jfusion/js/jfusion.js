//<!--
function submitbutton(pressbutton) {
    var adminForm = $('adminForm');
    if (pressbutton == 'applyconfig') {
        adminForm.action.value = 'apply';
        submitform('saveconfig');
    } else if (pressbutton == 'import') {
        adminForm.encoding = 'multipart/form-data';
        submitform(pressbutton);
    } else {
        submitform(pressbutton);
    }
}

function setCheckedValue(radioObj, newValue) {
    var i;
	if (radioObj) {
        var radioLength = radioObj.length;
        if (radioLength === undefined) {
            radioObj.checked = (radioObj.value == newValue.toString());
        } else {
            for (i = 0; i < radioLength; i++) {
                radioObj[i].checked = radioObj[i].value == newValue.toString();
            }
        }
    }
}

function setSort(col) {
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
	form.log_sort.value = col;
	form.submit();
}

function getCheckedValue(radioObj) {
    var r = "", i;
	if (radioObj) {
        var radioLength = radioObj.length;
        if (radioLength === undefined) {
            if (radioObj.checked) {
                r = radioObj.value;
            }
        } else {
            for (i = 0; i < radioLength; i++) {
                if (radioObj[i].checked) {
                    r = radioObj[i].value;
                }
            }
        }
    }
	return r;
}

function module(action) {
    var form = $('adminForm');
    form.customcommand.value = action;
    form.action.value = 'apply';
    submitform('saveconfig');
}

function usergroupSelect(option) {
    $('JFusionUsergroup').innerHTML = myArray[option];
}

function multiUsergroupSelect(option) {
    usergroupSelect(option);

    var addgroupset = $('addgroupset');
    if (option == 1) {
        addgroupset.style.display = 'block';
    } else {
        addgroupset.style.display = 'none';
    }
}

if (typeof Joomla != 'undefined') {
	Joomla.submitbutton = function (pressbutton) {
        var adminForm = $('adminForm');
        if (pressbutton == 'applyconfig') {
            adminForm.action.value = 'apply';
            submitform('saveconfig');
        } else if (pressbutton == 'import') {
            adminForm.encoding = 'multipart/form-data';
            submitform(pressbutton);
        } else {
            submitform(pressbutton);
        }
	};

	Joomla.getCheckedValue = function (radioObj) {
		return getCheckedValue(radioObj);
	};

	Joomla.setCheckedValue = function (radioObj, newValue) {
		return setCheckedValue(radioObj, newValue);
	};

	Joomla.setSort = function (col) {
		return setSort(col);
	};
}

function evaluateJSON(string) {
    var response;
    try {
        response = Json.evaluate(string,true);
    } catch (error){

    }
    if ((typeof response ) != 'object') {
        response = null;
    }
    return response;
}

function jfusionError(string, force) {
    if (string.indexOf('<!') == 0) {
        string = 'SESSION TIMEOUT';
    }
    var jfusionError = $("system-message-container");
    if (jfusionError) {
        jfusionError.innerHTML = string;
        if (force) {
            alert(string);
        }
    } else {
        alert(string);
    }
}
//-->