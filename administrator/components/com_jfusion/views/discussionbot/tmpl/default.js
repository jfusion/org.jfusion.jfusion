if (typeof JFusion === 'undefined') {
    var JFusion = {};
}

JFusion.removePair = function (id) {
    var form = $('adminForm');
    form.remove.set('value', id);
    form.submit();
};