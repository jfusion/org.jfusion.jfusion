if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.version = 'unknown';

JFusion.confirmInstallPlugin = function (url) {
    JFusion.confirm(Joomla.JText._('UPGRADE_CONFIRM_PLUGIN') + ' ' + url, Joomla.JText._('UPGRADE'), function () {
        var installPLUGIN = $('installPLUGIN');
        installPLUGIN.installPLUGIN_url.set('value', url);
        installPLUGIN.submit();
    });
};

JFusion.confirmInstall = function (action) {
    var installurl, confirmtext;
    var install = $('install');
    if (action === 'build') {
        confirmtext = Joomla.JText._('UPGRADE_CONFIRM_BUILD');
        installurl = 'https://github.com/jfusion/org.jfusion.jfusion/raw/jfusion2.0/jfusion_package.zip';
    } else if (action === 'git') {
        confirmtext = Joomla.JText._('UPGRADE_CONFIRM_GIT') + ' ' + install.git_tree.get('value');
        installurl = 'https://github.com/jfusion/org.jfusion.jfusion/raw/' + install.git_tree.get('value') + '/jfusion_package.zip';
    } else {
        confirmtext = Joomla.JText._('UPGRADE_CONFIRM_RELEASE') + ' ' + JFusion.version;
        installurl = action;
    }

    JFusion.confirm(confirmtext, Joomla.JText._('UPGRADE'), function () {
        install.install_url.set('value', installurl);
        install.submit();
    });
};