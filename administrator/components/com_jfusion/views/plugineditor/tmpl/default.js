if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.Plugin.module = function (action) {
    var form = $('adminForm');
    form.customcommand.set('value', action);
    form.action.set('value', 'apply');
    Joomla.submitform('saveconfig', form);
};

JFusion.Plugin.groupDataArray = [];
JFusion.Plugin.usergroupSelect = function (option) {
    $('JFusionUsergroup').set('html', this.groupDataArray[option]);
};

JFusion.Plugin.multiUsergroupSelect = function (option) {
    this.usergroupSelect(option);
    var addgroupset = $('addgroupset');
    if (option === 1) {
        addgroupset.style.display = 'block';
    } else {
        addgroupset.style.display = 'none';
    }
};