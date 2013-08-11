if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.confirmInstallLanguage = function (action) {
    JFusion.confirm(JFusion.JText('INSTALL_UPGRADE_LANGUAGE_PACKAGE'), JFusion.JText('INSTALL'), function () {
        var install = $('install');
        install.install_url.set('value', action);
        install.submit();
    });
};

JFusion.confirmUninstallLanguage = function (id) {
    JFusion.confirm(JFusion.JText('UNINSTALL_UPGRADE_LANGUAGE_PACKAGE'), JFusion.JText('UNINSTALL'), function () {
        var install = $('install');
        install.eid.set('value', id);
        install.task.set('value', 'uninstallanguage');
        install.submit();
    });
};