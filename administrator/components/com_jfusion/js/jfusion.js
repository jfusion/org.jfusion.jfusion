//<!--
if('undefined'===typeof JFusion) {
    var JFusion = {};
    JFusion.Text = [];
    JFusion.url = '';
}

/**
 * @return {string}
 */
JFusion.JText = function(key) {
    key = key.toUpperCase();
    if (this.Text[key]) {
        key = this.Text[key];
    }
    return key;
};

JFusion.OnError = function(messages, force) {
    var systemMessageContainer = $('system-message-container');
    systemMessageContainer.empty();
    if (messages.indexOf('<!') == 0) {
        messages = [ this.JText('SESSION_TIMEOUT') ];
    } else {
        messages = [ messages ];
    }
    this.OnMessage('error', messages, force);
};

JFusion.OnMessages = function(messages, force) {
    var systemMessageContainer = $('system-message-container');
    systemMessageContainer.empty();

    this.OnMessage('message', messages.message, force);
    this.OnMessage('notice', messages.notice, force);
    this.OnMessage('warning', messages.warning, force);
    this.OnMessage('error', messages.error, force);
};

JFusion.OnMessage = function(type, messages, force) {
    if (messages instanceof Array) {
        if (messages.length) {
            var systemMessageContainer = $('system-message-container');

            var errorlist = { 'error' : 'alert-error', 'warning' : '', 'notice' : 'alert-info', 'message' : 'alert-success'};

            var div = new Element('div', {'class' : 'alert'+' '+ errorlist[type] });

            new Element('h4',{'class': 'alert-heading', 'html' : this.JText(type) }).inject(div);
            Array.each(messages, function(message, index) {
                new Element('p' , { 'html' : message } ).inject(div);
                if (force) {
                    alert(message);
                }
            });
            div.inject(systemMessageContainer);
        }
    }
};

JFusion.groupDataArray = [];
JFusion.usergroupSelect = function(option) {
    $('JFusionUsergroup').set('html',this.groupDataArray[option]);
};

JFusion.multiUsergroupSelect = function(option) {
    this.usergroupSelect(option);

    var addgroupset = $('addgroupset');
    if (option == 1) {
        addgroupset.style.display = 'block';
    } else {
        addgroupset.style.display = 'none';
    }
};

JFusion.changeSetting = function (fieldname, fieldvalue, jname) {
    //change the image
    var syncdata = 'jname=' + jname + '&field_name=' + fieldname + '&field_value=' + fieldvalue + '&task=changesettings&option=com_jfusion';
    new Request.JSON({ url: JFusion.url, method: 'get',

        onRequest: function() {
            var element = $(jname + '_' + fieldname).getFirst().getFirst();
            element.set('src', 'components/com_jfusion/images/spinner.gif');
        },
        onSuccess: function(JSONobject) {
            JFusion.OnMessages(JSONobject.messages);

            JFusion.updateList(JSONobject.pluginlist);
        }, onError: function(JSONobject) {
            JFusion.OnError(JSONobject);
        }

    }).send(syncdata);
};

JFusion.copyPlugin = function(jname) {
    var newjname = prompt('Please type in the name to use for the copied plugin. This name must not already be in use.', '');
    if(newjname) {
        // this code will send a data object via a GET request and alert the retrieved data.
        new Request.JSON({url: JFusion.url ,
            onSuccess: function(JSONobject) {
                JFusion.OnMessages(JSONobject.messages);

                JFusion.updateList(JSONobject.pluginlist);
            }, onError: function(JSONobject) {
                JFusion.OnError(JSONobject);
            }
        }).get({'option': 'com_jfusion', 'task': 'plugincopy', 'jname': jname, 'new_jname': newjname});
    }
};

JFusion.deletePlugin = function(jname) {
    var confirmdelete = confirm(JFusion.JText('DELETE')+' '+JFusion.JText('PLUGIN')+' ' + jname + '?');
    if(confirmdelete) {
        // this code will send a data object via a GET request and alert the retrieved data.
        new Request.JSON({url: JFusion.url ,
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
};

JFusion.updateList = function(html) {
    var list = $("sort_table");
    list.empty();
    list.set('html', html);
    this.initSortables();
};

JFusion.initSortables = function() {
    /* allow for updates of row order */
    var ajaxsync = new Request.JSON({ url: JFusion.url,
        method: 'get',
        onSuccess: function(JSONobject) {
            JFusion.OnMessages(JSONobject.messages);

            JFusion.updateList(JSONobject.pluginlist);
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
};

JFusion.module = function(action) {
    var form = $('adminForm');
    form.customcommand.value = action;
    form.action.value = 'apply';
    submitform('saveconfig');
};

JFusion.selectItemid = function(name,num,id) {
    $(name+'_id'+num).value = id;
    $(name+'_name'+num).value = id;
    $(name+'_save'+num).set('src', 'components/com_jfusion/images/filesave.png');
    SqueezeBox.close();
};

JFusion.discussionParamSet = function(name, base64) {
    $(name + '_id').value = base64;
    $(name + '_img').set('src', 'components/com_jfusion/images/filesave.png');
    SqueezeBox.close();
};

JFusion.advancedParamSet = function(title, base64, name) {
    $(name + '_id').value = base64;
    $(name + '_name').value = title;
    SqueezeBox.close();
};

JFusion.addPair = function(name,id)	{
    var tr = document.createElement('tr');

    var index = 0;
    var list;
    while (true) {
        list = document.getElementById(id+'value'+index);
        if (!list) break;
        index++;
    }
    var tr = new Element('tr', {
        'id': +id+index
    });

    var td = new Element('td');
    var input = new Element('input', {
        'type': 'text',
        'id': id+'name'+index,
        'name': name+'[name]['+index+']',
        'size': 50,
        'events': {
            'change': function () {
                $(id+'_save').set('src', 'components/com_jfusion/images/filesave.png');
            }
        }
    });
    td.appendChild(input);
    tr.appendChild(td);

    td = new Element('td');
    input = new Element('input', {
        'type': 'text',
        'id': id+'value'+index,
        'name': name+'[value]['+index+']',
        'size': 50,
        'events': {
            'change': function () {
                $(id+'_save').set('src', 'components/com_jfusion/images/filesave.png');
            }
        }
    });
    td.appendChild(input);
    tr.appendChild(td);

    var a = document.createElement('a');
    td = document.createElement('td');
    var a = new Element('a', {
        'href': 'javascript:JFusion.removePair(\'+id+\',\'+index+\');',
        'html': this.JText('DELETE_PAIR')
    });

    td.appendChild(a);
    tr.appendChild(td);

    $(id+'_params').appendChild(tr);
};

JFusion.removePair = function(id,index) {
    $(id+'_params').removeChild($(id+index));
    $(id+'_save').set('src', 'components/com_jfusion/images/filesave.png');
};

JFusion.closeAdopt = function() {
    $(this.options.target).inject($(this.options.return));
};

JFusion.addPlugin = function(button) {
    button.form.jfusion_task.value = 'add';
    button.form.task.value = 'advancedparam';
    button.form.submit();
};
JFusion.removePlugin = function(button, value) {
    button.form.jfusion_task.value = 'remove';
    button.form.jfusion_value.value = value;
    button.form.task.value = 'advancedparam';
    button.form.submit();
};

















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
};

Joomla.setCheckedValue = function (radioObj, newValue) {
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
};

Joomla.setSort = function (col) {
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
};
//-->