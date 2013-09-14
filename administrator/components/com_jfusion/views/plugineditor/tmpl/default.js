if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.Plugin.module = function (action, arg1, arg2) {
    var form = $('adminForm');
    form.customcommand.set('value', action);
    if (arg1) {
        form.customarg1.set('value', arg1);
    }
    if (arg2) {
        form.customarg2.set('value', arg2);
    }
    form.action.set('value', 'apply');
    Joomla.submitform('saveconfig', form);
};