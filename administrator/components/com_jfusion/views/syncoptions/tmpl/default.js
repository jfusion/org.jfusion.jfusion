if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.response = { data : { 'completed' : false , 'slave_data' : [] } };

JFusion.periodical = false;
// refresh every 10 seconds
JFusion.syncRunning = -1;
JFusion.counter = 10;
JFusion.undateInterval = 1000;

JFusion.renderSyncHead = function() {
    var root = new Element('thead');
    var tr = new Element('tr');

    new Element('th',{'html': Joomla.JText._('PLUGIN') + ' ' + Joomla.JText._('NAME')}).inject(tr);
    new Element('th',{'html': Joomla.JText._('SYNC_PROGRESS'), 'width': 200}).inject(tr);
    new Element('th',{'html': Joomla.JText._('SYNC_USERS_TODO')}).inject(tr);
    new Element('th',{'html': Joomla.JText._('USERS') + ' ' + Joomla.JText._('CREATED')}).inject(tr);
    new Element('th',{'html': Joomla.JText._('USERS') + ' ' + Joomla.JText._('DELETED')}).inject(tr);
    new Element('th',{'html': Joomla.JText._('USERS') + ' ' + Joomla.JText._('UPDATED')}).inject(tr);
    new Element('th',{'html': Joomla.JText._('USER') + ' ' + Joomla.JText._('CONFLICTS')}).inject(tr);
    new Element('th',{'html': Joomla.JText._('USERS') + ' ' + Joomla.JText._('UNCHANGED')}).inject(tr);

    tr.inject(root);
    return root;
};

JFusion.renderSyncBody = function (data) {
    var root = new Element('tBody');
    for (var i=0; i<data.slave_data.length; i++) {
        var info = data.slave_data[i];
        var tr = new Element('tr');

        //NAME
        new Element('td',{'html': info.jname , 'width': 200}).inject(tr);

        // SYNC_PROGRESS
        var outer = new Element('div').inject(tr);

        var pct = 0;
        var synced = info.total_to_sync-info.total;
        if (synced !== 0 && info.total_to_sync !== 0) {
            pct = (synced/info.total_to_sync) * 100;
        }
        var color = 'blue';
        if (pct === 100) {
            color = 'green';
        }
        new Element('div',{'style': 'background-color:'+color+'; width:'+pct+'%','html': '&nbsp;'}).inject(outer);

        var progress = new Element('td');
        outer.inject(progress);
        progress.inject(tr);

        //SYNC_USERS_TODO
        new Element('td',{'html': info.total_to_sync-synced}).inject(tr);
        //CREATED
        new Element('td',{'html': info.created}).inject(tr);
        //DELETED
        new Element('td',{'html': info.deleted}).inject(tr);
        //UPDATED
        new Element('td',{'html': info.updated}).inject(tr);
        //CONFLICTS
        new Element('td',{'html': info.error}).inject(tr);
        //UNCHANGED
        new Element('td',{'html': info.unchanged}).inject(tr);

        tr.inject(root);
    }
    return root;
};

JFusion.renderSync = function(data) {
    var log_res = $('log_res');
    log_res.empty();

    var root = new Element('table',{ 'class': 'jfusionlist' });
    JFusion.renderSyncHead().inject(root);
    JFusion.renderSyncBody(data.data).inject(root);

    root.inject(log_res);
};

JFusion.startSync = function() {
    JFusion.syncRunning = 1;

    /* our usersync status update function: */
    var refresh = (function() {
        //add another second to the counter
        JFusion.counter -= 1;
        if (JFusion.counter < 1) {
            if (!JFusion.response.data.completed) {
                JFusion.counter = 10;

                /* our ajax istance for starting the sync */
                new Request.JSON({
                    url: JFusion.url,
                    noCache: true,
                    onSuccess: function(JSONobject) {
                        JFusion.onSuccess(JSONobject);

                        JFusion.render(JSONobject);
                    }, onError: function(JSONobject) {
                        JFusion.OnError(JSONobject);
                        JFusion.stopSync();
                    }
                }).get({'option': 'com_jfusion',
                        'task': 'syncprogress',
                        'tmpl': 'component',
                        'syncid': JFusion.syncid});

                new Request.JSON({
                    url: JFusion.url,
                    noCache: true,
                    onSuccess: function(JSONobject) {
                        JFusion.onSuccess(JSONobject);

                        JFusion.render(JSONobject);
                    }, onError: function(JSONobject) {
                        JFusion.OnError(JSONobject);
                        JFusion.stopSync();
                    }
                }).get({'option': 'com_jfusion',
                        'task': 'syncresume',
                        'tmpl': 'component',
                        'syncid': JFusion.syncid});
            }
        } else {
            JFusion.update();
        }
    });

    JFusion.periodical = refresh.periodical(JFusion.undateInterval, this);

    JFusion.renderSync(JFusion.response);
};

JFusion.stopSync = function() {
    JFusion.syncRunning = 0;
    // let's stop our timed ajax
    clearInterval(JFusion.periodical);
};

JFusion.update = function() {
    if (JFusion.syncRunning !== -1) {
        var text;
        var start = $('start');
        if (JFusion.response.data.completed) {
            JFusion.stopSync();
            text = Joomla.JText._('FINISHED');

            start.set('html', '<strong>' + Joomla.JText._('CLICK_FOR_MORE_DETAILS') + '</strong>');
            start.set('href', 'index.php?option=com_jfusion&task=syncstatus&syncid=' + JFusion.syncid);
            start.removeEvents('click');
        } else if (JFusion.syncRunning === 0) {
            text = Joomla.JText._('PAUSED');

            start.set('html', Joomla.JText._('RESUME'));
        } else {
            text = Joomla.JText._('UPDATE_IN') + ' ' + JFusion.counter + ' ' + Joomla.JText._('SECONDS');

            start.set('html', Joomla.JText._('PAUSE'));
        }
        $('counter').set('html', '<strong>'+text+'</strong>');
    }
};

JFusion.render = function(JSONobject) {
    JFusion.response = JSONobject;

    if (!JSONobject.success || (JSONobject.messages && JSONobject.messages.error)) {
        JFusion.stopSync();
    } else {
        JFusion.renderSync(JSONobject);

        if (JSONobject.data.completed) {
            JFusion.update();
        }
    }
};

window.addEvent('domready', function() {
        // start and stop click events
        $('start').addEvent('click', function(e) {
            // prevent default
            e.stop();
            if (JFusion.syncRunning === 1) {
                JFusion.stopSync();
            } else {
                // prevent insane clicks to start numerous requests
                clearInterval(JFusion.periodical);

                if (JFusion.syncMode === 'new') {
                    var form = $('syncForm');
                    var count = 0;

                    if (form) {
                        var select = form.getElements('select[name^=slave]');

                        select.each(function (el) {
                            var value = el.get('value');
                            if (value) {
                                JFusion.response.data.slave_data[count] = {
                                    "jname": value,
                                    "total": JFusion.slaveData[value].total,
                                    "total_to_sync": JFusion.slaveData[value].total,
                                    "created": 0,
                                    "deleted": 0,
                                    "updated": 0,
                                    "error": 0,
                                    "unchanged": 0};
                                count++;
                            }
                        });
                    }
                    if (JFusion.response.data.slave_data.length) {
                        //give the user a last chance to opt-out

                        JFusion.confirm(Joomla.JText._('SYNC_CONFIRM_START'), Joomla.JText._('OK'), function () {
                            JFusion.syncMode = 'resume';
                            //do start
                            new Request.JSON({
                                url: JFusion.url,
                                noCache: true,
                                onSuccess: function (JSONobject) {
                                    JFusion.onSuccess(JSONobject);

                                    JFusion.render(JSONobject);
                                }, onError: function (JSONobject) {
                                    JFusion.OnError(JSONobject);
                                    JFusion.stopSync();
                                }}).get(form.toQueryString() + '&option=com_jfusion&task=syncinitiate&tmpl=component&syncid=' + JFusion.syncid);
                            JFusion.startSync();
                        });
                    } else {
                        JFusion.OnError(Joomla.JText._('SYNC_NODATA'));
                    }
                } else {
                    JFusion.startSync();
                }
            }
            JFusion.update();
        });
    }
);