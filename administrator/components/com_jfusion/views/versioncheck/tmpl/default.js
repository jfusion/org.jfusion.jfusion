if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.version = 'unknown';

JFusion.confirmSubmitPlugin = function (url)
{
    var r = false;
    var confirmtext = JFusion.JText('UPGRADE_CONFIRM_PLUGIN') + ' ' + url;

    var agree = confirm(confirmtext);
    if (agree) {
        var installPLUGIN = $('installPLUGIN');
        installPLUGIN.installPLUGIN_url.value = url;
        installPLUGIN.submit();
        r = true;
    }
    return r;
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

    var agree = confirm(confirmtext);
    if (agree) {
        install.install_url.value = installurl;
        install.submit();
        r = true;
    }
    return r;
};