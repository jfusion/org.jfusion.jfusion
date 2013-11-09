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
    JFusion.prompt(Joomla.JText._('COPY_MESSAGE'), Joomla.JText._('COPY'), function (value) {
        if (value) {
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
                    'new_jname': value});
            SqueezeBox.close();
        }
    });
};

JFusion.deletePlugin = function (jname) {

    JFusion.confirm(Joomla.JText._('DELETE') + ' ' + Joomla.JText._('PLUGIN') + ' ' + jname + '?', Joomla.JText._('DELETE'), function () {
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
    });
};

JFusion.submitForm = function (type) {
    new Request.JSON({
        noCache: true,
        format: 'json',
        onRequest: function () {
            $('spinner'+type).set('html', '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">');
        },
        onSuccess: function(JSONobject) {
            $('spinner'+type).set('html', '');
            JFusion.OnMessages(JSONobject.messages);

            JFusion.updateList(JSONobject.pluginlist);
        },
        onError: function (JSONobject) {
            JFusion.OnError(JSONobject);
        }
    }).post($('install'+type).toQueryString());
};

JFusion.downloadPlugin = function () {
    window.location = $('server_install_url').getSelected().get('value');
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
            var sortorder;
            //build a string of the order
            sortorder = '';
            $$('#sort_table tr').each(function (tr, index) {
                tr.setAttribute('class', 'row' + (index % 2));
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

window.addEvent('domready',function() {
    $('installSERVER').addEvent('submit', function(e) {
        e.stop();
        JFusion.submitForm('SERVER');
    });

    $('installDIR').addEvent('submit', function(e) {
        e.stop();
        JFusion.submitForm('DIR');
    });

    $('installURL').addEvent('submit', function(e) {
        e.stop();
        JFusion.submitForm('URL');
    });

    var installZIP = $('installZIP');
    installZIP.addEvent('submit', function(e) {
        var upload;
        e.stop();
        $('spinnerZIP').set('html', '<img border="0" alt="loading" src="components/com_jfusion/images/spinner.gif">');
        //noinspection JSHint
        upload = new File.Upload({
            url:  JFusion.url,
            noCache: true,
            format: 'json',
            data: {
                option: 'com_jfusion',
                task : 'installplugin',
                installtype : 'upload'},
            images: ['install_package'],
            onComplete: function (result) {
                $('spinnerZIP').set('html', '');
                if (JSON.validate(result)) {
                    var JSONobject = JSON.decode(result);
                    JFusion.OnMessages(JSONobject.messages);

                    JFusion.updateList(JSONobject.pluginlist);
                } else {
                    JFusion.OnError(result);
                }
            },
            onException:  function () {
                $('installZIP').submit();
            }
        });
        upload.send();
    });
    JFusion.initSortables();
});