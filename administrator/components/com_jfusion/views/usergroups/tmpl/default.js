if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

if (typeof JFusion.renderPlugin === 'undefined') {
    JFusion.renderPlugin = [];
}
if (typeof JFusion.usergroups === 'undefined') {
    JFusion.usergroups = [];
}
if (typeof JFusion.pairs === 'undefined') {
    JFusion.pairs = [];
}
if (typeof JFusion.plugins === 'undefined') {
    JFusion.plugins = [];
}

JFusion.createRows = function() {
    (function( $ ) {
        if (JFusion.pairs && $.isEmptyObject(JFusion.pairs) === false) {
            var length = 0;
            var name = null;

            $.each(JFusion.pairs, function( key, pair ) {
                if (pair.length > length) {
                    length = pair.length;
                    name = key;
                }
            });

            if (length > 0 && name) {
                $.each(JFusion.pairs[name], function() {
                    JFusion.createRow(false);
                });
            } else {
                JFusion.createRow(true);
            }
        } else {
            JFusion.createRow(true);
        }

        JFusion.initSortables();
        $('select').chosen({
            disable_search_threshold : 10,
            allow_single_deselect : true
        });
    })(jQuery);
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
    (function( $ ) {
        var sort_table = $('#sort_table');

        var index = $( sort_table ).children().length;

        var tr = $('<tr>');
        tr.attr( 'id', 'usergroup' + index);

        tr.append(JFusion.createDragHandle(index));

        $.each(JFusion.plugins, function( key, plugin ) {
            tr.append(JFusion.render(index, plugin, newrow));
        });

        tr.append(JFusion.createRemove(index));

        sort_table.append(tr);
    })(jQuery);
};

JFusion.render = function(index, plugin, newrow) {
    return (function( $ ) {
        var td = $('<td>');

        var div = $('<div>');
        div.attr('id', plugin.name);

        var update = $('#updateusergroups_' + plugin.name);

        var master = $(plugin).prop('master');

        if (!master && index !== 0) {
            if (update && !update.prop('checked')) {
                div.hide();
            }
        }

        div.append(JFusion.renderGroup(index, plugin, newrow));

        td.append(div);
        return td;
    })(jQuery);
};

JFusion.renderGroup = function(index, plugin, newrow) {
    var element;
    if(plugin.name in JFusion.renderPlugin && typeof JFusion.renderPlugin[plugin.name] === 'function') {
        var pair = null;
        if (!newrow && plugin.name in JFusion.pairs && index in JFusion.pairs[plugin.name]) {
            pair = JFusion.pairs[plugin.name][index];
        }
        element = JFusion.renderPlugin[plugin.name](index, plugin, pair, JFusion.usergroups[plugin.name]);
    } else {
        element = new Element('div');
    }
    return element;
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