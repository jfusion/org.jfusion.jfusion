//<!--
if (typeof JFusion === 'undefined') {
    var JFusion = {};
}
JFusion.Plugin = {};
JFusion.View = {};
JFusion.url = '';

JFusion.Text = {
    strings: {},
    '_': function(key, def) {
        if (typeof def === 'undefined') {
            def = key.toUpperCase();
        }
        return typeof this.strings[key.toUpperCase()] !== 'undefined' ? this.strings[key.toUpperCase()] : def;
    },
    load: function(object) {
        $.each(object, function( key, value ) {
            JFusion.Text.strings[key.toUpperCase()] = value;
        });
        return this;
    }
};

JFusion.onSuccess = function (JSONobject) {
    Joomla.removeMessages();
    if (!JSONobject.success && JSONobject.message) {
        JFusion.confirm(JSONobject.message, Joomla.JText._('OK'));
    }

    if (JSONobject.messages) {
        Joomla.renderMessages(JSONobject.messages);
    }
};

JFusion.OnError = function (messages) {
    var message = {};

    if (messages.indexOf('<!') === 0) {
        message.error = [ Joomla.JText._('SESSION_TIMEOUT') ];
    } else {
        message.error = [ messages ];
    }

    Joomla.renderMessages(message);
};

JFusion.confirm = function (message, button, fn) {
    var confirmBox = new Element('div',{ 'style': 'height: 100%;'});
    confirmBox.appendChild(new Element('div', {
        'style': 'min-height: 100px;',
        'html': message
    }));

    confirmBox.appendChild(new Element('button', {
        'class': 'btn btn-small',
        'html': button,
        'style': 'float: right; vertical-align: bottom;',
        'events': {
            'click': function () {
                if (fn) {
                    fn();
                }
                SqueezeBox.close();
            }
        }
    }));
    SqueezeBox.open(confirmBox, {
        handler : 'adopt',
        overlayOpacity : 0.7,
        size: {x: 320,
            y: 'auto'}
    });
};

JFusion.prompt = function (message, button, fn) {
    var messageBox = new Element('div', { 'style': 'height: 100%;'});
    messageBox.appendChild(new Element('div', {
        'html': message
    }));

    var inputDiv = new Element('div');
    inputDiv.appendChild(new Element('input', {
        'id': 'jfusionprompt',
        'name': 'jfusionprompt',
        'type': 'text',
        'style': 'width: 300px'
    }));
    messageBox.appendChild(inputDiv);
    messageBox.appendChild(new Element('button', {
        'class': 'btn btn-small',
        'html': button,
        'style': 'float: right;',
        'events': {
            'click': function () {
                var input = $('jfusionprompt');
                var newvalue = input.get('value');
                fn(newvalue);
                SqueezeBox.close();
            }
        }
    }));
    SqueezeBox.open(messageBox, {
        handler : 'adopt',
        overlayOpacity : 0.7,
        size: {x: 320,
            y: 'auto'}
    });
};

JFusion.submitParams = function (name, value, title) {
    var id, save, n;
    id = $(name + '_id');
    if (id.get('value') !== value) {
        save = $(name + '_save');
        if (save) {
            save.set('src', 'components/com_jfusion/images/filesave.png');
        }
        $(name + '_id').set('value', value);
    }
    n = $(name + '_name');
    if (n) {
        if (title) {
            n.set('value', title);
        } else {
            n.set('value', value);
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
        'html': Joomla.JText._('DELETE_PAIR'),
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


/**
 * Joomla stuff
 */
Joomla.submitbutton = function (pressbutton) {
    var adminForm = $('adminForm');
    if (pressbutton === 'applyconfig') {
        adminForm.action.set('value', 'apply');
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
                r = radioObj.get('value');
            }
        } else {
            for (i = 0; i < radioLength; i++) {
                if (radioObj[i].checked) {
                    r = radioObj[i].get('value');
                }
            }
        }
    }
    return r;
};

Joomla.setCheckedValue = function (radioObj, newValue) {
    var radioLength;
    if (radioObj) {
        radioLength = radioObj.length;
        if (radioLength === undefined) {
            radioObj.checked = (radioObj.get('value') === newValue.toString());
        } else {
            var options = radioObj.getElements('option');
            options.each(function(option) {
                option.checked = option.get('value') === newValue.toString();
            });
        }
    }
};

Joomla.setSort = function (col) {
    var form, prevCol, direction;
    form = $('adminForm');
    prevCol = form.log_sort.get('value');
    if (prevCol === col) {
        direction = form.log_dir.get('value');
        if (direction === '1') {
            form.log_dir.set('value', -1);
        } else {
            form.log_dir.set('value', 1);
        }
    } else {
        form.log_dir.set('value', 1);
    }
    form.log_sort.set('value', col);
    form.submit();
};
//-->