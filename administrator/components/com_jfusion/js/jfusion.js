//<!--
if("undefined"===typeof JFusion) {
    var JFusion={};
    JFusion.Text = [];
}

JFusion.JText = function(key) {
    key = key.toUpperCase();
    if (this.Text[key]) {
        return this.Text[key];
    } else {
        return key;
    }
}

JFusion.OnError = function(messages, force) {
    var jfusionError = $("system-message-container");
    jfusionError.empty();
    if (messages.indexOf('<!') == 0) {
        messages = [ this.JText('SESSION_TIMEOUT') ];
    } else {
        messages = [ messages ];
    }
    this.OnMessage('error', messages, force);
}

JFusion.OnMessages = function(messages, force) {
    var jfusionError = $("system-message-container");
    jfusionError.empty();

    this.OnMessage('message', messages.message, force);
    this.OnMessage('notice', messages.notice, force);
    this.OnMessage('warning', messages.warning, force);
    this.OnMessage('error', messages.error, force);
}

JFusion.OnMessage = function(type, messages, force) {
    if (messages instanceof Array) {
        if (messages.length) {
            var jfusionError = $("system-message-container");

            var errorlist = { 'error' : 'alert-error', 'warning' : '', 'notice' : 'alert-info', 'message' : 'alert-success'};

            var div = new Element('div', {'class' : 'alert'+' '+ errorlist[type] });

            new Element('h4',{'class': 'alert-heading', 'html' : this.JText(type) }).inject(div);
            Array.each(messages, function(message, index) {
                new Element('p' , { 'html' : message } ).inject(div);
                if (force) {
                    alert(message);
                }
            });
            div.inject(jfusionError);
        }
    }
}

JFusion.groupDataArray = [];
JFusion.usergroupSelect = function(option) {
    $('JFusionUsergroup').innerHTML = this.groupDataArray[option];
}

JFusion.multiUsergroupSelect = function(option) {
    this.usergroupSelect(option);

    var addgroupset = $('addgroupset');
    if (option == 1) {
        addgroupset.style.display = 'block';
    } else {
        addgroupset.style.display = 'none';
    }
}

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
//-->