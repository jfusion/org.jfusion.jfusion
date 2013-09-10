if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.confirmInstallLanguage = function (action) {
    JFusion.confirm(Joomla.JText._('INSTALL_UPGRADE_LANGUAGE_PACKAGE'), Joomla.JText._('INSTALL'), function () {
        var install = $('install');
        install.install_url.set('value', action);
        install.submit();
    });
};

JFusion.confirmUninstallLanguage = function (id) {
    JFusion.confirm(Joomla.JText._('UNINSTALL_UPGRADE_LANGUAGE_PACKAGE'), Joomla.JText._('UNINSTALL'), function () {
        var install = $('install');
        install.eid.set('value', id);
        install.task.set('value', 'uninstallanguage');
        install.submit();
    });
};