if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.response = { 'completed' : false , 'slave_data' : [] , 'errors' : [] };

JFusion.periodical = false;
// refresh every 10 seconds
JFusion.syncRunning = -1;
JFusion.counter = 10;
JFusion.undateInterval = 1000;

JFusion.renderSyncHead = function() {
    var root = new Element('thead');
    var tr = new Element('tr');

    new Element('th',{'html': JFusion.JText('PLUGIN') + ' ' + JFusion.JText('NAME')}).inject(tr);
    new Element('th',{'html': JFusion.JText('SYNC_PROGRESS'), 'width': 200}).inject(tr);
    new Element('th',{'html': JFusion.JText('SYNC_USERS_TODO')}).inject(tr);
    new Element('th',{'html': JFusion.JText('USERS') + ' ' + JFusion.JText('CREATED')}).inject(tr);
    new Element('th',{'html': JFusion.JText('USERS') + ' ' + JFusion.JText('DELETED')}).inject(tr);
    new Element('th',{'html': JFusion.JText('USERS') + ' ' + JFusion.JText('UPDATED')}).inject(tr);
    new Element('th',{'html': JFusion.JText('USER') + ' ' + JFusion.JText('CONFLICTS')}).inject(tr);
    new Element('th',{'html': JFusion.JText('USERS') + ' ' + JFusion.JText('UNCHANGED')}).inject(tr);

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
    JFusion.renderSyncBody(data).inject(root);

    root.inject(log_res);
};

JFusion.startSync = function() {
    JFusion.syncRunning = 1;

    /* our usersync status update function: */
    var refresh = (function() {
        //add another second to the counter
        JFusion.counter -= 1;
        if (JFusion.counter < 1) {
            if (!JFusion.response.completed) {
                JFusion.counter = 10;

                /* our ajax istance for starting the sync */
                new Request.JSON({
                    url: JFusion.url,
                    noCache: true,
                    onSuccess: function(JSONobject) {
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
        if (JFusion.response.completed) {
            JFusion.stopSync();
            text = JFusion.JText('FINISHED');

            start.set('html', '<strong>'+JFusion.JText('CLICK_FOR_MORE_DETAILS')+'</strong>');
            start.set('href', 'index.php?option=com_jfusion&task=syncstatus&syncid='+JFusion.syncid);
            start.removeEvents('click');
        } else if (JFusion.syncRunning === 0) {
            text = JFusion.JText('PAUSED');

            start.set('html', JFusion.JText('RESUME'));
        } else {
            text = JFusion.JText('UPDATE_IN')+ ' ' + JFusion.counter + ' '+JFusion.JText('SECONDS');

            start.set('html', JFusion.JText('PAUSE'));
        }
        $('counter').set('html', '<strong>'+text+'</strong>');
    }
};

JFusion.render = function(JSONobject) {
    JFusion.response = JSONobject;

    JFusion.OnMessages(JSONobject.messages);
    if (JSONobject.messages.error) {
        JFusion.stopSync();
    } else {
        JFusion.renderSync(JSONobject);

        if (JSONobject.completed) {
            JFusion.update();
        }
    }
};
