if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.createRows = function() {
    if (JFusion.pairs && JFusion.pairs.joomla_int) {
        Array.each(JFusion.pairs.joomla_int, function (pair) {
            JFusion.createRow();
        });
    } else {
        JFusion.createRow();
    }
    JFusion.initSortables();
};

JFusion.createDragHandle = function(index) {
    var td = new Element('td', {
        'width': '20px'
    });
    var div = new Element('div', {
        'class': 'dragHandles',
        'id' : 'dragHandles'
    });

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
    var td = new Element('td', {
        'width': '20px'});
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

JFusion.createRow = function() {
    var sort_table = $('sort_table');

    var index = sort_table.getChildren().length;
    var num = index % 2;

    var tr = new Element('tr', { 'id': 'usergroup'+index,
        'class': 'row'+num});
    tr.appendChild(JFusion.createDragHandle(index));

    Array.each(JFusion.plugins, function (plugin) {
        tr.appendChild(JFusion.render(index, plugin));
    });

    tr.appendChild(JFusion.createRemove(index));

    sort_table.appendChild(tr);
};

JFusion.render = function(index, plugin) {
    var td = new Element('td');

    var div = new Element('div', {'id': plugin.name});

    div.appendChild(JFusion.renderGroup(index, plugin));

    td.appendChild(div);
    return td;
};

JFusion.renderGroup = function(index, plugin) {
    var mode = $('usergroupmodes_'+plugin.name);
    if(plugin.name in JFusion.renderPlugin && typeof JFusion.renderPlugin[plugin.name] === 'function') {
        var pair = null;
        if (plugin.name in JFusion.pairs && index in JFusion.pairs[plugin.name]) {
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
        options = { 'name': 'usergroups['+plugin.name+']['+index+']'};
    }
    options.id = 'usergroups_'+plugin.name+index;

    var select = new Element('select', options);

    var groups = JFusion.usergroups[plugin.name];

    Array.each(groups, function (group) {
        var selected = '';
        if (pair !== null && pair.contains(group.id)) {
            selected = 'selected';
        }
        select.appendChild(new Element('option', {'value': group.id,
            'selected': selected,
            'html': group.name}));
    });
    return select;
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
        }
    });
};

Joomla.submitbutton = function (pressbutton) {
    if (pressbutton === 'add') {
        JFusion.createRow();
        JFusion.initSortables();
    }  else {
        Joomla.submitform(pressbutton, $('adminForm'));
    }
};