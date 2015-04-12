if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.toggleSetting = function (toggle, name) {
    var a = jQuery('#' + name + '_' + toggle).find('a');

    var div = jQuery(a).find('div');

    if (!div.hasClass('spinner')) {
        var oldtitle = a.attr('title');
        var oldclass = div.attr('class');

        div.removeClass('disabled enabled');
        div.addClass('spinner');

        jQuery.post( JFusion.url + "?option=com_jfusion&task=togglesetting", { "toggle": toggle, "name": name }, null, 'json').done(function(data) {
            if (data && data.success) {
                a.attr('title', data.data.title);
                div.addClass(data.data.class);
            } else {
                a.attr('title', oldtitle);
                div.attr('class', oldclass);
            }
        }).fail(function() {
            a.attr('title', oldtitle);
            div.attr('class', oldclass);
        }).always(function() {
            div.removeClass('spinner');
        });
    }
};

JFusion.copyPlugin = function (jname) {
    JFusion.prompt(Joomla.JText._('COPY_MESSAGE'), Joomla.JText._('COPY'), function (value) {
        if (value) {
            // this code will send a data object via a GET request and alert the retrieved data.
            new Request.JSON({
                url: JFusion.url,
                noCache: true,
                onSuccess: function (JSONobject) {
                    JFusion.onSuccess(JSONobject);

                    JFusion.updateList(JSONobject);
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
                JFusion.onSuccess(JSONobject);

                if (JSONobject.success ===  true && JSONobject.data && JSONobject.data.jname) {
                    var el = $(JSONobject.data.jname);
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
            JFusion.onSuccess(JSONobject);

            JFusion.updateList(JSONobject);
        },
        onError: function (JSONobject) {
            JFusion.OnError(JSONobject);
        }
    }).post($('install'+type).toQueryString());
};

JFusion.downloadPlugin = function () {
    window.location = $('server_install_url').getSelected().get('value');
};

JFusion.updateList = function (JSONobject) {
    if (JSONobject.success ===  true && JSONobject.data && JSONobject.data.pluginlist) {
        var list = $('sort_table');
        list.empty();
        list.set('html', JSONobject.data.pluginlist);
        this.initSortables();
    }
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
                sortorder = sortorder +  tr.id  + '|';
            });

            new Request.JSON({
                url: JFusion.url,
                noCache: true,
                onSuccess: function (JSONobject) {
                    JFusion.onSuccess(JSONobject);

                    JFusion.updateList(JSONobject);
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
                    JFusion.onSuccess(JSONobject);

                    JFusion.updateList(JSONobject);
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