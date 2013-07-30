//<!--
if (typeof JFusion === 'undefined') {
    var JFusion = {};
}
JFusion.Plugin = {};
JFusion.Text = [];
JFusion.url = '';

/**
 * @return {string}
 */
JFusion.JText = function (key) {
    return this.Text[key.toUpperCase()] || key.toUpperCase();
};

JFusion.OnError = function (messages, force) {
    var systemMessageContainer = $('system-message-container');
    systemMessageContainer.empty();
    if (messages.indexOf('<!') === 0) {
        this.OnMessage('error', [ this.JText('SESSION_TIMEOUT') ], force);
    } else {
        this.OnMessage('error', [ messages ], force);
    }
};

JFusion.OnMessages = function (messages) {
    var systemMessageContainer = $('system-message-container');
    systemMessageContainer.empty();

    this.OnMessage('message', messages.message);
    this.OnMessage('notice', messages.notice);
    this.OnMessage('warning', messages.warning);
    this.OnMessage('error', messages.error);
};

JFusion.OnMessage = function (type, messages) {
    var div, systemMessageContainer, errorlist;
    if (messages instanceof Array) {
        if (messages.length) {
            systemMessageContainer = $('system-message-container');

            errorlist = { 'error': 'alert-error', 'warning': '', 'notice': 'alert-info', 'message': 'alert-success'};

            div = new Element('div', {'class': 'alert' + ' ' + errorlist[type] });

            new Element('h4', {'class': 'alert-heading', 'html' : this.JText(type) }).inject(div);
            Array.each(messages, function (message) {
                new Element('p', { 'html' : message }).inject(div);
            });
            div.inject(systemMessageContainer);
        }
    }
};

JFusion.groupDataArray = [];
JFusion.usergroupSelect = function (option) {
    $('JFusionUsergroup').set('html', this.groupDataArray[option]);
};

JFusion.multiUsergroupSelect = function (option) {
    this.usergroupSelect(option);

    var addgroupset = $('addgroupset');
    if (option === 1) {
        addgroupset.style.display = 'block';
    } else {
        addgroupset.style.display = 'none';
    }
};

JFusion.changeSetting = function (fieldname, fieldvalue, jname) {
    //change the image
    new Request.JSON({
        url: JFusion.url,
        noCache: true,
        onRequest: function () {
            var element = $(jname + '_' + fieldname).getFirst().getFirst();
            element.set('src', 'components/com_jfusion/images/spinner.gif');
        },
        onSuccess: function (JSONobject) {
            JFusion.OnMessages(JSONobject.messages);

            JFusion.updateList(JSONobject.pluginlist);
        },
        onError: function (JSONobject) {
            JFusion.OnError(JSONobject);
        }
    }).get({'option': 'com_jfusion',
            'task': 'changesettings',
            'jname': jname,
            'field_name': fieldname,
            'field_value': fieldvalue});
};

JFusion.copyPlugin = function (jname) {
    var newjname = prompt(JFusion.JText('COPY_MESSAGE'), '');
    if (newjname) {
        // this code will send a data object via a GET request and alert the retrieved data.
        new Request.JSON({
            url: JFusion.url,
            noCache: true,
            onSuccess: function (JSONobject) {
                JFusion.OnMessages(JSONobject.messages);

                JFusion.updateList(JSONobject.pluginlist);
            },
            onError: function (JSONobject) {
                JFusion.OnError(JSONobject);
            }
        }).get({'option': 'com_jfusion',
                'task': 'plugincopy',
                'jname': jname,
                'new_jname': newjname});
    }
};

JFusion.deletePlugin = function (jname) {
    var confirmdelete = confirm(JFusion.JText('DELETE') + ' ' + JFusion.JText('PLUGIN') + ' ' + jname + '?');
    if (confirmdelete) {
        // this code will send a data object via a GET request and alert the retrieved data.
        new Request.JSON({
            url: JFusion.url,
            noCache: true,
            onSuccess: function (JSONobject) {
                JFusion.OnMessages(JSONobject.messages);
                if (JSONobject.status ===  true) {
                    var el = $(JSONobject.jname);
                    el.parentNode.removeChild(el);
                }
            },
            onError: function (JSONobject) {
                JFusion.OnError(JSONobject);
            }
        }).get({'option': 'com_jfusion',
                'task': 'uninstallplugin',
                'jname': jname,
                'tmpl': 'component'});
    }
};

JFusion.updateList = function (html) {
    var list = $('sort_table');
    list.empty();
    list.set('html', html);
    this.initSortables();
};

JFusion.initSortables = function () {
    /* allow for updates of row order */
    new Sortables('sort_table', {
        /* set options */
        handle: 'div.dragHandles',

        /* initialization stuff here */
        initialize: function () {
            // do nothing yet
        },
        /* once an item is selected */
        onStart: function (el) {
            //a little fancy work to hide the clone which mootools 1.1 doesn't seem to give the option for
            var checkme = $$('div tr#' + el.id);
            if (checkme[1]) {
                checkme[1].setStyle('display', 'none');
            }
        },
        onComplete: function () {
            var sortorder, rowcount;
            //build a string of the order
            sortorder = '';
            rowcount = 0;
            $$('#sort_table tr').each(function (tr) {
                $(tr.id).setAttribute('class', 'row' + (rowcount % 2));
                rowcount++;
                sortorder = sortorder +  tr.id  + '|';
            });

            new Request.JSON({
                url: JFusion.url,
                noCache: true,
                onSuccess: function (JSONobject) {
                    JFusion.OnMessages(JSONobject.messages);

                    JFusion.updateList(JSONobject.pluginlist);
                },
                onError: function (JSONobject) {
                    JFusion.OnError(JSONobject);
                }
            }).get({'option': 'com_jfusion',
                    'task': 'saveorder',
                    'tmpl': 'component',
                    'sort_order': sortorder});
        }
    });
};

JFusion.module = function (action) {
    var form = $('adminForm');
    form.customcommand.value = action;
    form.action.value = 'apply';
    Joomla.submitform('saveconfig', form);
};

JFusion.submitParams = function (name, value, title) {
    var id, save, n;
    id = $(name + '_id');
    if (id.value !== value) {
        save = $(name + '_save');
        if (save) {
            save.set('src', 'components/com_jfusion/images/filesave.png');
        }
        $(name + '_id').value = value;
    }
    n = $(name + '_name');
    if (n) {
        if (title) {
            n.value = title;
        } else {
            n.value = value;
        }
    }
    SqueezeBox.close();
};

JFusion.addPair = function (name, id) {
    var index, list, tr;
    index = 0;
    while (true) {
        list = document.getElementById(id + 'value' + index);
        if (!list) {
            break;
        }
        index++;
    }
    tr = new Element('tr', {
        'id': id + index
    });

    tr.appendChild(new Element('td')).appendChild(new Element('input', {
        'type': 'text',
        'id': id + 'name' + index,
        'name': name + '[name][' + index + ']',
        'size': 50,
        'events': {
            'change': function () {
                $(id + '_save').set('src', 'components/com_jfusion/images/filesave.png');
            }
        }
    }));

    tr.appendChild(new Element('td')).appendChild(new Element('input', {
        'type': 'text',
        'id': id + 'value' + index,
        'name': name + '[value][' + index + ']',
        'size': 50,
        'events': {
            'change': function () {
                $(id + '_save').set('src', 'components/com_jfusion/images/filesave.png');
            }
        }
    }));

    tr.appendChild(new Element('td')).appendChild(new Element('a', {
        'href': 'javascript:void(0);',
        'html': this.JText('DELETE_PAIR'),
        'events': {
            'click': function () {
                JFusion.removePair(id, index);
            }
        }
    }));

    $(id + '_params').appendChild(tr);
};

JFusion.removePair = function (id, index) {
    $(id + '_params').removeChild($(id + index));
    $(id + '_save').set('src', 'components/com_jfusion/images/filesave.png');
};

JFusion.closeAdopt = function () {
    $(this.options.target).inject($(this.options.returnTo));
};

JFusion.addPlugin = function (button) {
    button.form.jfusion_task.value = 'add';
    button.form.task.value = 'advancedparam';
    button.form.submit();
};

JFusion.removePlugin = function (button, value) {
    button.form.jfusion_task.value = 'remove';
    button.form.jfusion_value.value = value;
    button.form.task.value = 'advancedparam';
    button.form.submit();
};

/**
 * Joomla stuff
 */


Joomla.submitbutton = function (pressbutton) {
    var adminForm = $('adminForm');
    if (pressbutton === 'applyconfig') {
        adminForm.action.value = 'apply';
        Joomla.submitform('saveconfig', adminForm);
    } else if (pressbutton === 'import') {
        adminForm.encoding = 'multipart/form-data';
        Joomla.submitform(pressbutton, adminForm);
    } else {
        Joomla.submitform(pressbutton, adminForm);
    }

};

Joomla.getCheckedValue = function (radioObj) {
    var r, i, radioLength;
    r = '';
    if (radioObj) {
        radioLength = radioObj.length;
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
    var i, radioLength;
    if (radioObj) {
        radioLength = radioObj.length;
        if (radioLength === undefined) {
            radioObj.checked = (radioObj.value === newValue.toString());
        } else {
            for (i = 0; i < radioLength; i++) {
                radioObj[i].checked = radioObj[i].value === newValue.toString();
            }
        }
    }
};

Joomla.setSort = function (col) {
    var form, prevCol, direction;
    form = $('adminForm');
    prevCol = form.log_sort.value;
    if (prevCol === col) {
        direction = form.log_dir.value;
        if (direction === '1') {
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