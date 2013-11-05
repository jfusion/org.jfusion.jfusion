if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.createRows = function() {
    if (JFusion.pairs && JFusion.pairs.joomla_int) {
        Array.each(JFusion.pairs.joomla_int, function () {
            JFusion.createRow(false);
        });
    } else {
        JFusion.createRow(true);
    }
    JFusion.initSortables();
    jQuery('select').chosen({
        disable_search_threshold : 10,
        allow_single_deselect : true
    });
};

JFusion.createDragHandle = function(index) {
    var td = new Element('td', {
        'width': '20px',
        'class': 'dragHandles',
        'id' : 'dragHandles'
    });
    var div = new Element('div');

    var img = new Element('img', {
        'src': 'components/com_jfusion/images/draggable.png',
        'name' : 'handle'
    });
    img.inject(div);

    var input = new Element('input', {
        'type': 'hidden',
        'name' : 'sort[]',
        'value' : index
    });
    input.inject(div);

    div.inject(td);
    return td;
};

JFusion.createRemove = function(index) {
    var td = new Element('td');
    var img = new Element('img', {
        'src': 'components/com_jfusion/images/cross.png',
        'name' : 'handle',
        'events': {
            'click': function () {
                $('sort_table').removeChild($('usergroup'+index));
            }
        }
    });
    img.inject(td);
    return td;
};

JFusion.createRow = function(newrow) {
    var sort_table = $('sort_table');

    var index = sort_table.getChildren().length;

    var classes = 'row' + (index % 2);
    if (!index) {
        classes += ' defaultusergroup';
    }

    var tr = new Element('tr', { 'id': 'usergroup'+index,
        'class': classes});
    tr.appendChild(JFusion.createDragHandle(index));

    Array.each(JFusion.plugins, function (plugin) {
        tr.appendChild(JFusion.render(index, plugin, newrow));
    });

    tr.appendChild(JFusion.createRemove(index));

    sort_table.appendChild(tr);
};

JFusion.render = function(index, plugin, newrow) {
    var td = new Element('td');

    var div = new Element('div', {'id': plugin.name});

    var update = $('updateusergroups_'+plugin.name);

    if (!plugin.master && index !== 0) {
        if (update && !update.checked) {
            div.hide();
        }
    }

    div.appendChild(JFusion.renderGroup(index, plugin, newrow));

    td.appendChild(div);
    return td;
};

JFusion.renderGroup = function(index, plugin, newrow) {
    if(plugin.name in JFusion.renderPlugin && typeof JFusion.renderPlugin[plugin.name] === 'function') {
        var pair = null;
        if (!newrow && plugin.name in JFusion.pairs && index in JFusion.pairs[plugin.name]) {
            pair = JFusion.pairs[plugin.name][index];
        }

        return JFusion.renderPlugin[plugin.name](index, plugin, pair);
    } else {
        return new Element('div');
    }
};

JFusion.renderDefault = function(index, plugin, pair) {
    var options = {};
    if (plugin.isMultiGroup) {
        options = {
            'multiple': 'multiple',
            'size': 5,
            'name': 'usergroups['+plugin.name+']['+index+'][]' };
    } else {
        options = { 'name': 'usergroups['+plugin.name+']['+index+'][]'};
    }
    options.id = 'usergroups_'+plugin.name+index;

    var select = new Element('select', options);

    var groups = JFusion.usergroups[plugin.name];

    if (!plugin.isMultiGroup) {
        select.appendChild(new Element('option', {'value': 'JFUSION_NO_USERGROUP',
            'html': Joomla.JText._('SELECT_ONE')}));
    }

    Array.each(groups, function (group) {
        var options = {'value': group.id,
            'html': group.name};

        if (pair !== null && (pair.contains(group.id) || pair.toString() === group.id.toString())) {
            options.selected = 'selected';
        }
        select.appendChild(new Element('option', options));
    });
    return select;
};

JFusion.initSortables = function () {
    /* allow for updates of row order */
    new Sortables('sort_table', {
        /* set options */
        handle: 'td.dragHandles',

        /* initialization stuff here */
        initialize: function () {
            // do nothing yet
        },
        /* once an item is selected */
        onStart: function (el) {
            // do nothing yet
        },
        onComplete: function () {
            $$('#sort_table tr').each(function (tr, index) {
                if (index) {
                    tr.setAttribute('class', 'row' + (index % 2));
                } else {
                    tr.setAttribute('class', 'row' + (index % 2) + ' defaultusergroup');
                }
            });
            JFusion.updatePlugins();
        }
    });
};

JFusion.updatePlugins = function () {
    Array.each(JFusion.plugins, function (plugin) {
        var update = $('updateusergroups_'+plugin.name);
        $$('div #'+ plugin.name).each(function (div, index) {
            if (!plugin.master && (update && !update.checked) && index !== 0) {
                div.hide();
            } else {
                div.show();
            }
        });
    });
};

Joomla.submitbutton = function (pressbutton) {
    if (pressbutton === 'add') {
        JFusion.createRow(true);
        JFusion.initSortables();
        jQuery('select').chosen({
            disable_search_threshold : 10,
            allow_single_deselect : true
        });
    }  else {
        Joomla.submitform(pressbutton, $('adminForm'));
    }
};

window.addEvent('domready',function() {
    JFusion.createRows();

    Array.each(JFusion.plugins, function (plugin) {
        var update = $('updateusergroups_'+plugin.name);
        if (update){
            update.addEvent('click', function() {
                JFusion.updatePlugins();
            });
        }
    });
});