//<!--
function submitbutton(pressbutton) {
    var form = document.adminForm;
    if (pressbutton == 'applyconfig') {
        form.action.value = 'apply';
        submitform('saveconfig');
        return;
    }

    submitform(pressbutton);
}

function submitbutton3(pressbutton)
{
    var form = document.adminForm;

    // do field validation
    if (form.install_directory.value == "") {
        alert("<?php echo JText::_( 'NO_DIRECTORY'); ?>" );
    } else {
        form.installtype.value = 'folder';
        form.submit();
    }
}

function submitbutton4(pressbutton)
{
    var form = document.adminForm;

    // do field validation
    if (form.install_url.value == "" || form.install_url.value == "http://") {
        alert("<?php echo JText::_( 'NO_URL'); ?>" );
    } else {
        form.installtype.value = 'url';
        form.submit();
    }
}


function setCheckedValue(radioObj, newValue) {
	if(radioObj) {
        var radioLength = radioObj.length;
        if(radioLength === undefined) {
            radioObj.checked = (radioObj.value == newValue.toString());
            return;
        }
        for(var i = 0; i < radioLength; i++) {
            radioObj[i].checked = radioObj[i].value == newValue.toString();
        }
    }
}

function setSort(col){
	var form = document.adminForm;
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
	if(radioObj) {
        var radioLength = radioObj.length;
        if(radioLength === undefined) {
            if(radioObj.checked) {
                return radioObj.value;
            }
        } else {
            for(var i = 0; i < radioLength; i++) {
                if(radioObj[i].checked) {
                    return radioObj[i].value;
                }
            }
        }
    }
	return "";
}


if (typeof Joomla != 'undefined') {
	Joomla.submitbutton = function(pressbutton) {
		var form = document.adminForm;
	    if (pressbutton == 'applyconfig') {
	        form.action.value = 'apply';
	        submitform('saveconfig');
	        return;
	    }

	    submitform(pressbutton);
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