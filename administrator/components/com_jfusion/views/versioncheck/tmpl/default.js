if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.version = 'unknown';

JFusion.confirmSubmitPlugin = function (url)
{
    var confirmBox = new Element('div');
    confirmBox.appendChild(new Element('div', {
        'html': JFusion.JText('UPGRADE_CONFIRM_PLUGIN') + ' ' + url
    }));

    confirmBox.appendChild(new Element('button', {
        'class': 'btn btn-small',
        'html': JFusion.JText('UPGRADE'),
        'style': 'float: right;',
        'events': {
            'click': function () {
                var installPLUGIN = $('installPLUGIN');
                installPLUGIN.installPLUGIN_url.value = url;
                installPLUGIN.submit();
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

JFusion.confirmSubmit = function (action)
{
    var r = false;
    var installurl,confirmtext;
    var install = $('install');
    if (action === 'build') {
        confirmtext = JFusion.JText('UPGRADE_CONFIRM_BUILD');
        installurl = 'https://github.com/jfusion/org.jfusion.jfusion/raw/jfusion2.0/jfusion_package.zip';
    } else if (action === 'git') {
        confirmtext = JFusion.JText('UPGRADE_CONFIRM_GIT') + ' ' + install.git_tree.value;
        installurl = 'https://github.com/jfusion/org.jfusion.jfusion/raw/' + install.git_tree.value + '/jfusion_package.zip';
    } else {
        confirmtext = JFusion.JText('UPGRADE_CONFIRM_RELEASE') + ' ' + JFusion.version;
        installurl = action;
    }

    var confirmBox = new Element('div');
    confirmBox.appendChild(new Element('div', {
        'html': confirmtext
    }));

    confirmBox.appendChild(new Element('button', {
        'class': 'btn btn-small',
        'html': JFusion.JText('UPGRADE'),
        'style': 'float: right;',
        'events': {
            'click': function () {
                install.install_url.value = installurl;
                install.submit();
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