if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.version = 'unknown';

JFusion.confirmInstallPlugin = function (url) {
    JFusion.confirm(JFusion.JText('UPGRADE_CONFIRM_PLUGIN') + ' ' + url, JFusion.JText('UPGRADE'), function () {
        var installPLUGIN = $('installPLUGIN');
        installPLUGIN.installPLUGIN_url.set('value', url);
        installPLUGIN.submit();
    });
};

JFusion.confirmInstall = function (action) {
    var installurl, confirmtext;
    var install = $('install');
    if (action === 'build') {
        confirmtext = JFusion.JText('UPGRADE_CONFIRM_BUILD');
        installurl = 'https://github.com/jfusion/org.jfusion.jfusion/raw/jfusion2.0/jfusion_package.zip';
    } else if (action === 'git') {
        confirmtext = JFusion.JText('UPGRADE_CONFIRM_GIT') + ' ' + install.git_tree.get('value');
        installurl = 'https://github.com/jfusion/org.jfusion.jfusion/raw/' + install.git_tree.get('value') + '/jfusion_package.zip';
    } else {
        confirmtext = JFusion.JText('UPGRADE_CONFIRM_RELEASE') + ' ' + JFusion.version;
        installurl = action;
    }

    JFusion.confirm(confirmtext, JFusion.JText('UPGRADE'), function () {
        install.install_url.set('value', installurl);
        install.submit();
    });
};