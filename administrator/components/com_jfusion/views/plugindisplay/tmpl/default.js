if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

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
    var messageBox = new Element('div');
    messageBox.appendChild(new Element('div', {
        'html': JFusion.JText('COPY_MESSAGE')
    }));

    var inputDiv = new Element('div');
    inputDiv.appendChild(new Element('input', {
        'id': 'plugincopyname',
        'name': 'plugincopyname',
        'type': 'text',
        'width': 300
    }));
    messageBox.appendChild(inputDiv);
    messageBox.appendChild(new Element('button', {
        'class': 'btn btn-small',
        'html': JFusion.JText('COPY'),
        'style': 'float: right;',
        'events': {
            'click': function () {
                var input = $('plugincopyname');
                var newjname = input.get('value');
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
                    SqueezeBox.close();
                }
            }
        }
    }));
    SqueezeBox.open(messageBox, {
        handler : 'adopt',
        overlayOpacity : 0.7,
        size: {x: 320,
            y: 120}
    });
};

JFusion.deletePlugin = function (jname) {
    var confirmBox = new Element('div');
    confirmBox.appendChild(new Element('div', {
        'html': JFusion.JText('DELETE') + ' ' + JFusion.JText('PLUGIN') + ' ' + jname + '?'
    }));

    confirmBox.appendChild(new Element('button', {
        'class': 'btn btn-small',
        'html': JFusion.JText('DELETE'),
        'style': 'float: right;',
        'events': {
            'click': function () {
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
                SqueezeBox.close();
            }
        }
    }));
    SqueezeBox.open(confirmBox, {
        handler : 'adopt',
        overlayOpacity : 0.7,
        size: {x: 320,
            y: 120}
    });
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