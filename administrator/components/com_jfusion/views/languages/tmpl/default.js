if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.confirmSubmitLanguage = function (action) {
    var r = false;
    var confirmtext;
    confirmtext = JFusion.JText('INSTALL_UPGRADE_LANGUAGE_PACKAGE');

    var agree = confirm(confirmtext);
    if (agree) {
        var install = $('install');
        install.install_url.value = action;
        install.submit();
        r = true;
    }
    return r;
};