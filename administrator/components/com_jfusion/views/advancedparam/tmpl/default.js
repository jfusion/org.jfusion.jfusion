if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.addPlugin = function (button) {
    button.form.jfusion_task.set('value', 'add');
    button.form.task.set('value', 'advancedparam');
    button.form.submit();
};

JFusion.removePlugin = function (button, value) {
    button.form.jfusion_task.set('value', 'remove');
    button.form.jfusion_value.set('value', value);
    button.form.task.set('value', 'advancedparam');
    button.form.submit();
};

jQuery(document).ready(function (){
    jQuery('select').chosen({
        disable_search_threshold : 10,
        allow_single_deselect : true
    });
});