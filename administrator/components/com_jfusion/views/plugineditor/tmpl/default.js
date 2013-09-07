if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.Plugin.module = function (action) {
    var form = $('adminForm');
    form.customcommand.set('value', action);
    form.action.set('value', 'apply');
    Joomla.submitform('saveconfig', form);
};