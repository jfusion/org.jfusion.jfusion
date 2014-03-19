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

    confirmtext = Joomla.JText._('UPGRADE_CONFIRM_RELEASE') + ' ' + JFusion.version;
    installurl = action;

    JFusion.confirm(confirmtext, Joomla.JText._('UPGRADE'), function () {
        install.install_url.set('value', installurl);
        install.submit();
    });
};